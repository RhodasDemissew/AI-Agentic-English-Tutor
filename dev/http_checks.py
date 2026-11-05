"""HTTP integration checks for the local fixture; standard library only."""
import urllib.request, urllib.parse, http.cookiejar, re, json, sys, time
from html.parser import HTMLParser

BASE = 'http://localhost:8088'
fixture = json.load(open(sys.argv[1], encoding='utf-8-sig'))

def check(ok, message):
    if not ok:
        raise AssertionError(message)
    print('PASS:', message, flush=True)

class Fields(HTMLParser):
    def __init__(self):
        super().__init__(); self.fields = {}; self.textarea = None; self.select = None
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'input' and a.get('name') and a.get('type') not in ('submit', 'checkbox', 'radio'):
            self.fields[a['name']] = a.get('value', '')
        if tag == 'textarea':
            self.textarea = a.get('name'); self.fields[self.textarea] = ''
        if tag == 'select': self.select = a.get('name')
        if tag == 'option' and self.select and ('selected' in a or self.select not in self.fields):
            self.fields[self.select] = a.get('value', '')
    def handle_endtag(self, tag):
        if tag == 'textarea': self.textarea = None
        if tag == 'select': self.select = None
    def handle_data(self, data):
        if self.textarea: self.fields[self.textarea] += data

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self, path, data=None, raw=False):
        try:
            r = self.opener.open(BASE + path, urllib.parse.urlencode(data).encode() if data is not None else None, timeout=90)
        except urllib.error.HTTPError as e:
            r = e
        body = r.read()
        return body if raw else body.decode('utf-8', errors='replace')
    def login(self, name, password):
        page = self.get('/login/index.php')
        token = re.search(r'name="logintoken" value="([^"]+)"', page).group(1)
        page = self.get('/login/index.php', {'username': name, 'password': password, 'logintoken': token})
        check('loginerrors' not in page and 'name="logintoken"' not in page, name + ' login')

cmid = fixture['cmid']; draft = fixture['draft']; published = fixture['published']
view = '/mod/aienglishlesson/view.php?id=' + str(cmid)
student = Client(); student.login('aielstudent', 'LocalStudent-42!')
page = student.get(view)
check('A day at the market' in page and 'TEACHER-ONLY-SECRET' not in page, 'Published lesson renders without teacher key')
check('&lt;script&gt;' in page and '<script>alert(1)</script>' not in page, 'AI text is escaped by controlled template')
check('Mark section complete' in page, 'Student completion form renders')
hidden = student.get(view + '&version=' + str(draft))
check('Learn to ask politely' not in hidden and 'TEACHER-ONLY-SECRET' not in hidden, 'Direct draft URL exposes no content')
edit = student.get('/mod/aienglishlesson/edit.php?id=%s&version=%s' % (cmid, draft))
check('name="body_0"' not in edit and 'TEACHER-ONLY-SECRET' not in edit, 'Student cannot open draft editor')
pdfpath = '/pluginfile.php/%s/mod_aienglishlesson/source/0/sample.pdf' % fixture['contextid']
check(not student.get(pdfpath, raw=True).startswith(b'%PDF-'), 'Student cannot download teacher PDF')
anonymous = Client()
check(not anonymous.get(pdfpath, raw=True).startswith(b'%PDF-'), 'Anonymous PDF download denied')
parser = Fields(); parser.feed(page); key = parser.fields['sesskey']
bad = student.get('/mod/aienglishlesson/action.php', {'id': cmid, 'action': 'progress', 'version': published, 'section': 0, 'sesskey': 'invalid'})
check('0 of 10 sections completed' in student.get(view), 'CSRF rejection leaves progress unchanged')
result = student.get('/mod/aienglishlesson/action.php', {'id': cmid, 'action': 'progress', 'version': published, 'section': 0, 'sesskey': key})
check('1 of 10 sections completed' in result, 'Student progress persists and advances')
student.get('/mod/aienglishlesson/action.php', {'id': cmid, 'action': 'publish', 'version': draft, 'sesskey': key})
check('Learn to ask politely' not in student.get(view + '&version=' + str(draft)), 'Student publish attempt denied')

teacher = Client(); teacher.login('admin', 'LocalDev-Only-42!')
check(teacher.get(pdfpath, raw=True).startswith(b'%PDF-'), 'Teacher PDF download succeeds')
editor = '/mod/aienglishlesson/edit.php?id=%s&version=%s' % (cmid, draft)
page = teacher.get(editor); parser = Fields(); parser.feed(page)
check('body_0' in parser.fields, 'Teacher draft edit form renders')
parser.fields['body_0'] = 'EDITED-DRAFT-MARKER <script>alert(1)</script>'
parser.fields['submitbutton'] = 'Save changes'
page = teacher.get(editor, parser.fields)
check('EDITED-DRAFT-MARKER' in page and '<script>alert(1)</script>' not in page, 'Draft edits save and HTML is escaped')
check('EDITED-DRAFT-MARKER' not in student.get(view), 'Draft edits do not alter published lesson')
parser = Fields(); parser.feed(page)
publish = {'id': cmid, 'action': 'publish', 'version': draft, 'modified': parser.fields['modified'], 'sesskey': parser.fields['sesskey']}
teacher.get('/mod/aienglishlesson/action.php', publish)
check('EDITED-DRAFT-MARKER' not in student.get(view), 'Publication blocked until edited listening audio is rebuilt')
teacher.get('/mod/aienglishlesson/action.php', {'id': cmid, 'action': 'audio', 'version': draft, 'sesskey': parser.fields['sesskey']})
for attempt in range(45):
    page = teacher.get(view + '&version=' + str(draft) + '&section=6')
    if '<audio controls' in page:
        break
    time.sleep(2)
check('<audio controls' in page, 'Queued audio rebuild completes through Moodle cron')
teacher.get('/mod/aienglishlesson/action.php', {'id': cmid, 'action': 'publish', 'version': draft, 'modified': parser.fields['modified'], 'sesskey': parser.fields['sesskey']})
page = student.get(view)
check('EDITED-DRAFT-MARKER' in page and 'TEACHER-ONLY-SECRET' not in page, 'Approved draft becomes student-visible without answer key')
check('<script>alert(1)</script>' not in page, 'Student view does not execute edited HTML')
page = teacher.get('/course/modedit.php?update=%s&return=1' % cmid)
check('name="gradeband"' in page and 'name="duration"' in page, 'Native activity settings form renders')
print('HTTP checks complete; no provider calls were requested.')

"""End-to-end stored-content activities: no external AI calls."""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, json, sys, uuid, io, wave
from html.parser import HTMLParser
BASE = 'http://localhost:8088'
fixture = json.load(open(sys.argv[1], encoding='utf-8-sig'))
def check(ok, label):
    if not ok: raise AssertionError(label)
    print('PASS:', label, flush=True)
class Fields(HTMLParser):
    def __init__(self): super().__init__(); self.fields = {}; self.textarea = None; self.select = None
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='input' and a.get('name') and a.get('type') not in ('submit','checkbox','radio','file'): self.fields[a['name']]=a.get('value','')
        if tag=='textarea': self.textarea=a.get('name'); self.fields[self.textarea]=''
        if tag=='select': self.select=a.get('name')
        if tag=='option' and self.select and ('selected' in a or self.select not in self.fields): self.fields[self.select]=a.get('value','')
    def handle_endtag(self,tag):
        if tag=='textarea': self.textarea=None
        if tag=='select': self.select=None
    def handle_data(self,data):
        if self.textarea: self.fields[self.textarea]+=data
class Client:
    def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,path,data=None,raw=False,file=None):
        headers={}
        if file:
            boundary='----AIEL'+uuid.uuid4().hex; body=b''
            for k,v in data.items(): body+=('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode()
            body+=('--'+boundary+'\r\nContent-Disposition: form-data; name="recording"; filename="'+file[0]+'"\r\nContent-Type: '+file[1]+'\r\n\r\n').encode()+file[2]+b'\r\n'+('--'+boundary+'--\r\n').encode()
            headers['Content-Type']='multipart/form-data; boundary='+boundary
        else: body=urllib.parse.urlencode(data).encode() if data is not None else None
        try: r=self.opener.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=60)
        except urllib.error.HTTPError as e: r=e
        body=r.read(); return body if raw else body.decode('utf-8',errors='replace')
    def login(self,user,password):
        page=self.get('/login/index.php'); token=re.search(r'name="logintoken" value="([^"]+)"',page).group(1)
        page=self.get('/login/index.php',{'username':user,'password':password,'logintoken':token})
        check('name="logintoken"' not in page,user+' login')
def fields(page):
    p=Fields();p.feed(page);return p.fields
cm=fixture['cmid']; version=fixture['published']; draft=fixture['draft']
view='/mod/aienglishlesson/view.php?id='+str(cm)
student=Client();student.login('aielstudent','LocalStudent-42!')
teacher=Client();teacher.login('admin','LocalDev-Only-42!')
page=student.get(view+'&section=5'); key=fields(page)['sesskey']
check('AFTER-SUBMISSION-MODEL' not in page and 'TEACHER-ONLY-SECRET' not in page,'Feedback and teacher keys hidden before submission')
check('Show a hint' in page and 'Explain more simply' in page,'Stored help available without AI')
payload={'id':cm,'action':'submit','version':version,'section':5,'sesskey':key,'response':'FICTIONAL-READING-RESPONSE <script>alert(7)</script>'}
page=student.get('/mod/aienglishlesson/action.php',payload)
check('FICTIONAL-READING-RESPONSE' in page and '&lt;script&gt;' in page,'Reading response saved and escaped')
check('AFTER-SUBMISSION-MODEL' in page and 'TEACHER-ONLY-SECRET' not in page,'Model feedback released only after submission; keys still hidden')
page=student.get(view+'&section=6')
check('<audio controls' in page and 'Read the transcript' in page,'Listening player and transcript available')
audio=re.search(r'<audio[^>]*src="([^"]+)"',page).group(1).replace(BASE,'')
wav=student.get(audio,raw=True);check(wav[:4]==b'RIFF' and wav[8:12]==b'WAVE','Published cached listening audio downloads')
anonymous=Client();check(anonymous.get(audio,raw=True)[:4]!=b'RIFF','Anonymous listening access denied')
draftaudio=audio.replace('/audio/'+str(version)+'/', '/audio/'+str(draft)+'/')
check(student.get(draftaudio,raw=True)[:4]!=b'RIFF','Draft audio inaccessible to students')
page=student.get(view+'&section=7');check('data-action="record"' in page and 'name="recording"' in page,'Speaking recorder and upload fallback render')
buffer=io.BytesIO()
with wave.open(buffer,'wb') as w: w.setnchannels(1);w.setsampwidth(2);w.setframerate(8000);w.writeframes(b'\x00\x00'*8000)
payload.update(section=7,response='FICTIONAL-SPEAKING-NOTES')
page=student.get('/mod/aienglishlesson/action.php',payload,file=('practice.wav','audio/wav',buffer.getvalue()))
check('Your response is saved' in page and '/recording/' in page,'Speaking upload saved with notes')
recording=re.search(r'src="([^"]+/recording/[^"]+)"',page).group(1).replace(BASE,'')
check(student.get(recording,raw=True)[:4]==b'RIFF','Learner can replay own recording')
check(teacher.get(recording,raw=True)[:4]==b'RIFF','Teacher can review recording')
peer=Client();peer.login('aielpeer','LocalPeer-42!')
check(peer.get(recording,raw=True)[:4]!=b'RIFF','Another enrolled learner cannot access recording')
payload.update(response='INVALID-UPLOAD-MARKER')
page=student.get('/mod/aienglishlesson/action.php',payload,file=('bad.wav','audio/wav',b'<script>bad</script>'))
check('Choose a valid WAV' in page and 'INVALID-UPLOAD-MARKER' not in student.get(view+'&section=7'),'Invalid recording rejected without replacing prior response')
payload.update(section=8,response='FICTIONAL-WRITING-RESPONSE')
page=student.get('/mod/aienglishlesson/action.php',payload)
check('FICTIONAL-WRITING-RESPONSE' in student.get(view+'&section=8'),'Writing response persists across navigation')
report='/mod/aienglishlesson/responses.php?id='+str(cm)
check('FICTIONAL-WRITING-RESPONSE' in teacher.get(report),'Teacher can review written responses')
check('FICTIONAL-WRITING-RESPONSE' not in peer.get(report),'Teacher response report denied to learners')
payload.update(action='clearresponse',section=5)
student.get('/mod/aienglishlesson/action.php',payload)
check('FICTIONAL-READING-RESPONSE' not in student.get(view+'&section=5'),'Learner can delete own response')
page=teacher.get(view+'&version='+str(draft)); f=fields(page)
teacher.get('/mod/aienglishlesson/action.php',{'id':cm,'version':draft,'action':'publish','modified':f['modified'],'sesskey':f['sesskey']})
check('No published lesson' not in student.get(view),'Fully prepared draft can be published')
check(student.get(recording,raw=True)[:4]!=b'RIFF','Archived-version recording is not exposed in current student lesson')
print('Student content checks complete; all interactions use stored content.',flush=True)

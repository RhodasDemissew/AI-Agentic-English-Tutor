"""Local HTTP checks with cron stopped; no external provider requests."""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, json, sys, io, wave, uuid, socket
lookup = socket.getaddrinfo
socket.getaddrinfo = lambda host, *args, **kwargs: lookup('127.0.0.1' if host == 'localhost' else host, *args, **kwargs)
BASE = 'http://localhost:8088'
fixture = json.load(open(sys.argv[1], encoding='utf-8-sig'))
def check(ok, message):
    if not ok: raise AssertionError(message)
    print('PASS:', message, flush=True)
class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self, path, data=None, upload=None, raw=False):
        headers = {}
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        if upload:
            boundary = 'AIEL' + uuid.uuid4().hex
            body = b''.join(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode() for k,v in data.items())
            body += ('--'+boundary+'\r\nContent-Disposition: form-data; name="lessonaudio"; filename="'+upload[0]+'"\r\nContent-Type: audio/wav\r\n\r\n').encode()+upload[1]+('\r\n--'+boundary+'--\r\n').encode()
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        try: response = self.opener.open(urllib.request.Request(BASE+path, data=body, headers=headers), timeout=60)
        except urllib.error.HTTPError as error: response = error
        content = response.read()
        return content if raw else content.decode('utf-8', errors='replace')
    def login(self, name, password):
        token = re.search(r'name="logintoken" value="([^"]+)"', self.get('/login/index.php')).group(1)
        page = self.get('/login/index.php', {'username':name,'password':password,'logintoken':token})
        check('name="logintoken"' not in page, name + ' login')
teacher = Client(); teacher.login('admin', 'LocalDev-Only-42!')
student = Client(); student.login('aielstudent', 'LocalStudent-42!')
cm = fixture['cmid']; version = fixture['portableversion']
view = '/mod/aienglishlesson/view.php?id='+str(cm)+'&version='+str(version)+'&section=6'
page = teacher.get(view)
key = re.search(r'name="sesskey" value="([^"]+)"', page).group(1)
check('Direct preparation:' in page and 'Upload or replace this section audio' in page, 'Teacher sees direct mode and audio upload')
payload = {'id':cm,'version':version,'section':6,'action':'uploadaudio','sesskey':key}
buffer = io.BytesIO()
with wave.open(buffer, 'wb') as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(8000); w.writeframes(b'\0\0'*800)
wav = buffer.getvalue()
page = teacher.get('/mod/aienglishlesson/action.php', payload, ('teacher.wav', wav))
audio = re.search(r'<audio[^>]*src="([^"]+)"',page).group(1).replace(BASE,'')
check(teacher.get(audio,raw=True) == wav, 'Teacher upload replaces draft audio')
check(not student.get(audio,raw=True).startswith(b'RIFF'), 'Uploaded draft audio remains hidden from learners')
bad = dict(payload, sesskey='invalid')
teacher.get('/mod/aienglishlesson/action.php',bad,('bad.wav',b'not audio'))
check(teacher.get(audio,raw=True) == wav, 'Invalid session token cannot replace audio')
page = teacher.get('/mod/aienglishlesson/action.php',payload,('bad.wav',b'not audio'))
check('Choose a valid MP3 or WAV' in page and teacher.get(audio,raw=True)==wav, 'Invalid teacher audio rejected without changing saved media')
page = student.get('/mod/aienglishlesson/view.php?id='+str(cm))
studentkey = re.search(r'name="sesskey" value="([^"]+)"',page).group(1)
student.get('/mod/aienglishlesson/action.php',dict(payload,sesskey=studentkey),('student.wav',wav))
check(teacher.get(audio,raw=True)==wav, 'Student cannot upload lesson audio')
prepare = '/mod/aienglishlesson/prepareaudio.php'
args = {'id':cm,'version':version,'sesskey':key}
page = teacher.get(prepare,args)
check('aiel-audio-progress' in page and 'mod_aienglishlesson/prepareaudio' in page, 'Audio preparation page loads sequential worker')
result = json.loads(teacher.get(prepare,dict(args,step=1)))
check(result['done'], 'Authenticated audio worker completes with cron stopped')
speakingpage = teacher.get(view.replace('section=6', 'section=7'))
speakingaudio = re.search(r'<audio[^>]*src="([^"]+)"',speakingpage).group(1).replace(BASE,'')
check(len(teacher.get(speakingaudio,raw=True)) > 1000, 'Direct worker generated real speech for the missing section')
check('"done"' not in student.get(prepare,dict(args,step=1,sesskey=studentkey)), 'Audio worker denied to student')
check('"done"' not in teacher.get(prepare,dict(args,step=1,sesskey='invalid')), 'Audio worker rejects invalid session token')
check('aiel-audio-progress' not in teacher.get(prepare+'?'+urllib.parse.urlencode(args)), 'GET cannot trigger audio preparation')
page = teacher.get('/mod/aienglishlesson/action.php',{'id':cm,'action':'generate','sesskey':key})
check('Generation: failed. Attempts: 1.' in page, 'Direct generation executes immediately with cron stopped; disabled provider fails safely')
settings = teacher.get('/admin/settings.php?section=modsettingaienglishlesson')
check('name="s_aienglishlesson_apikey"' in settings and 'autocomplete="new-password"' in settings, 'Native API key input renders in real Moodle settings')
print('Portable HTTP checks complete. No live AI requests.', flush=True)

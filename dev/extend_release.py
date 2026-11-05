from pathlib import Path
source=Path('aienglishlesson/amd/src/recorder.js').read_text(encoding='utf-8')
p=Path('aienglishlesson/amd/build/recorder.min.js');p.parent.mkdir(parents=True,exist_ok=True)
p.write_text(source.replace("define(['core/str']", "define('mod_aienglishlesson/recorder', ['core/str']",1),encoding='utf-8')
strings={
'speechbinary':'Local eSpeak NG executable path', 'feedback':'Model response and self-check', 'hint':'Show a hint', 'simpler':'Explain more simply', 'extraexample':'Show another example', 'audioscript':'Spoken script (audio transcript)',
'listen':'Listen', 'localvoice':'Computer-generated voice, prepared locally. Replay as often as you need.', 'transcript':'Read the transcript', 'showmeaning':'Reveal meaning and example', 'yourresponse':'Your response or speaking notes',
'recordingnotice':'Record up to two minutes, listen back, then submit when ready. Your teacher can review submitted recordings. Microphone access requires your permission and HTTPS (or localhost). No AI analysis or pronunciation score is produced.',
'record':'Record', 'stoprecording':'Stop recording', 'downloadrecording':'Download this recording', 'uploadrecording':'Or choose an audio recording (maximum 8 MB)', 'recording':'Speaking recording',
'noaistudent':'Your response stays in Moodle. No AI service receives or grades it.', 'submitresponse':'Save my response and show model feedback', 'responsesaved':'Your response is saved. You can revise it and submit again.',
'feedbacknotice':'This is teacher-approved reference feedback for comparison, not an evaluation of your individual answer.', 'clearresponse':'Delete my response and recording',
'micunavailable':'Recording is unavailable in this browser. Use HTTPS or upload an audio file instead.', 'micdenied':'Microphone access failed or was denied. You can upload an audio file instead.', 'recordingactive':'Recording — stops automatically after two minutes.', 'recordingready':'Recording ready. Listen, then submit to save it. If no file is selected below, download this recording and choose it for upload.', 'recordingtoolarge':'Recording is too large. Please record a shorter response.',
'recordingerror':'Choose a valid WAV, MP3, Ogg, WebM or M4A audio file no larger than 8 MB.', 'emptyresponse':'Enter a response or attach a speaking recording before submitting.',
'reviewresponses':'Review learner responses', 'backtolesson':'Back to lesson', 'buildaudio':'Prepare listening and speaking audio', 'audiostatus':'Audio status: {$a}. Audio is built locally by cron. If it fails, check the speech-engine path and add a spoken script in the draft editor.',
'notready':'Before publishing, include practice for all four skills, a listening transcript, and prepared listening audio. Edit the draft, prepare audio, wait for cron and preview it before publishing.',
'privacy:metadata:response':'Learner-written response to a practice task.', 'privacy:metadata:responses':'Saved learner responses and linked speaking recordings. Only the learner and authorised teachers can access these.'
}
p=Path('aienglishlesson/lang/en/aienglishlesson.php')
with p.open('a',encoding='utf-8') as f:
 for k,v in strings.items(): f.write("$string['"+k+"'] = '"+v.replace("'","\\'")+"';\n")

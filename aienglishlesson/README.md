# AI English Lessons 0.3.0 (testing release)

Moodle 4.4 activity. Teacher PDF generation; published four-skill lessons; cached audio; learner text/recording submissions. No student AI requests.

Upgrade by installing this ZIP over the earlier plugin, without uninstalling it. Complete Moodle's database upgrade. Existing data and the stored key are preserved.

For hosting without Poppler/eSpeak/working cron, configure the activity module settings:

- Lesson preparation mode: Direct.
- Require Poppler PDF inspection: unchecked.
- Lesson audio preparation: Gemini cloud speech, or Teacher uploads audio.
- Speech model: gemini-3.8-flash-lite-tts; voice: Kore.

Keep the existing lesson-generation provider/key settings. Save. The key field is intentionally blank after saving; blank preserves the stored key.

Click Generate lesson and keep the page open. Review/edit the draft. Click Prepare listening and speaking audio, keep that page open, then return to the lesson and publish after review. Alternatively upload MP3/WAV files (up to 8 MB) from each draft audio section. Student playback uses stored files.

Direct mode avoids manual cron runs but hosting request timeouts still apply. Moodle cron should still be configured for other site tasks. Cloud speech uses the existing Gemini credentials and shared request cap. It requires an eligible deployment and model access. This release does not resolve Gemini's child-directed application restrictions.

Without Poppler, PDF size/signature/range syntax are checked, but page count/encryption/range bounds cannot be verified locally. The whole PDF is transmitted. Missing eSpeak does not matter for cloud or uploaded audio. Editing a draft clears its old audio.

Live cloud speech has not been tested with a real key; request formatting, response validation, cached processing and access rules were tested using fictional provider responses. Direct HTTP processing and local synthesis were tested with cron stopped. No credentials are bundled.

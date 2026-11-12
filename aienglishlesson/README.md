# AI English Lessons 0.6.6

Moodle 4.4 activity with staged lesson generation, teacher review, four assessed skills, a ten-question quiz, cached audio and one final gradebook item.

Install this ZIP over the existing plugin without uninstalling it, then complete Moodle's database upgrade. Existing published lessons without a quiz keep skills-only grading. New generated drafts include a quiz; review and edit its questions before publishing.

## Teacher flow

1. Create or edit an activity. Add one to five source files: PDF, DOCX, PPTX, ODT, TXT, MD, CSV, HTML/HTM, PNG, JPEG or WebP. A page range applies only to one PDF.
2. Set the grade band and English level. The new activity duration defaults to 60 minutes.
3. Generate the lesson. Direct mode runs three browser requests in sequence; keep the preparation page open. Background mode requires Moodle cron.
4. Review the draft, the quiz and the generated audio. Generated speech starts automatically when configured. Failed audio sections can be retried or replaced with uploaded MP3/WAV.
5. Publish the approved draft. Learners can submit responses and retake the same ten-question quiz. The latest completed quiz attempt counts.

## Grading

Skills is the average of the latest scored Reading, Writing, Listening and Speaking answers. The final grade is 50% Skills and 50% Quiz. It reaches Moodle's single gradebook item when all five results are available. Teachers can review and change skill scores. A grade from an earlier version remains visible until the new version has all required scores. Export grades through Moodle's gradebook.

Answer evaluation is disabled by default in administrator settings. When enabled, learner responses and media are sent to the configured Gemini provider for feedback; credentials remain server-side. Provider eligibility and quota requirements still apply.

Source files, answer keys and draft content are teacher-only. Student quiz pages do not reveal correct choices until after a completed attempt. Backup, restore and privacy export include quiz attempts and grade components.

This package has local lint and smoke checks. A live Moodle 4.4 site and real provider key are needed to verify the full upload, generation, audio and gradebook flow before wider use.

Version 0.6.1 raises the response allowance for the long second generation stage on Gemini 3.5 Flash and reports distinct output limit, content restriction and short audio script failures. Install the updated ZIP and retry generation if the previous attempt failed at stage two.

Version 0.6.2 defaults new installations to Gemini 3.5 Flash Lite and gives it the same response allowance for the long second stage. Existing site settings are preserved: after upgrading, change Model ID to `gemini-3.5-flash-lite` and save it. The speech model setting is separate and is unchanged.

Version 0.6.3 preserves a draft when generated listening or speaking scripts are shorter than the target, and warns the teacher to review and expand them before publishing. Missing scripts still fail generation. Local eSpeak NG reads new audio at a measured 120 words per minute. Cloud speech remains a separate preparation step after the draft is saved.

Version 0.6.4 labels the first five sections and recap as ungraded practice, reserves submissions for the four assessed skills, and sends learners from the recap completion button to the quiz. New lesson prompts require self-contained content and keep worked examples in their teaching section. The quiz review colors correct and incorrect choices. Teacher response review starts with a list of students in the current published version. The activity icon and lesson header use the supplied tutor branding. Existing published lesson text and grades are not rewritten.

Version 0.6.5 adds a browser-only writing space for ungraded practice, keeps assessed submissions on the current section, and displays missing-answer messages beside inputs. It also keeps transcripts closed until opened and allows relevant supplementary source material in drafts.

Version 0.6.6 removes ungraded practice prompts and writing spaces from learner pages and draft editing. The first five sections teach the topic, and the recap summarizes it. New drafts leave non-assessed practice fields empty. Younger grades show a built-in lesson graphic and open, colorful vocabulary cards without additional provider requests. Draft review warns teachers about assessed questions mentioning unseen visuals. Existing published lesson text, grades, and the lesson JSON format remain compatible.

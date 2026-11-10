# AI English Lessons 0.6.0

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

# 0.4.1 stability update

This is a code-only Moodle upgrade; no database migration is needed. Install the updated plugin on a development Moodle 4.4 site, visit the Moodle notifications page, and purge caches.

Generation now rejects unreadable or incomplete provider output instead of filling missing lesson sections. Direct jobs can be resumed from the teacher view; background mode continues to use Moodle ad-hoc tasks and cron. Each job has at most three generation attempts, and each provider request has at most three HTTP attempts that count toward the daily quota.

If `pdfinfo`, `pdfseparate`, and `pdfunite` are available, a selected page range is extracted before submission. Without extraction, the full PDF is sent and the range remains a prompt instruction. Listening audio must be uploaded or prepared before a new draft can be published. Existing published versions are not changed.

## Local verification

Run `php dev/stability_checks.php`, `php dev/job_checks.php`, and `php dev/pdf_checks.php`. These portable checks use mock data. Run `docker compose up -d` and the fixture, HTTP, audio, and schema checks in `docs/TESTING.md` for Moodle integration. A live authorized provider request and browser microphone test remain separate manual checks.

## Teacher workflow

Upload a short English PDF, set grade, topic, proficiency, and optional page range, then save the activity. Generate, review all ten sections, and edit the draft. If a job remains queued, use **Retry or resume generation**. Upload MP3/WAV audio for listening or prepare it with the configured speech provider. Publish only after audio is available. Enroll a test student to read the published version, save answers or a speaking recording, and mark sections complete. Open-ended responses await teacher review; an exact answer is checked only when the teacher prefixes its answer with `[exact] ` in the draft editor.

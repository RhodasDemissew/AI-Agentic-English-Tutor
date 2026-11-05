# Install and test AI English Lessons 0.2.0

Use `dist/mod_aienglishlesson-0.2.0.zip`. The separate `dist/ai-english-lessons-demo.mbz` is a sample lesson backup, not a plugin installer. The sample is prepared demonstration content, not live AI output, and contains cached listening/speaking audio without student submissions.

## Quickest test on this computer

1. Keep Docker Desktop running. From the project folder run `docker compose up -d` if needed.
2. Open http://localhost:8088/mod/aienglishlesson/view.php?id=12.
3. Sign in as `aielstudent` with password `LocalStudent-42!` for the student experience.
4. For teacher controls, log out and sign in as `admin` with password `LocalDev-Only-42!`.

These are local-only development accounts. Do not create them with these passwords on a publicly accessible site.

## Install on your Moodle test site

1. Sign in as a Moodle administrator. This release targets Moodle 4.4; testing used 4.4.2 Build 20240812, PHP 8.2 and MariaDB 10.11.
2. Open **Site administration > Plugins > Install plugins**.
3. Upload `mod_aienglishlesson-0.2.0.zip`, accept the installation checks and complete the database upgrade. The plugin identifies itself as an alpha testing release.
4. Confirm **AI English Lessons** appears in a course's **Add an activity or resource** menu.
5. Leave the AI provider disabled for the sample test. No API key is needed for installation or the sample lesson.

If your host disables ZIP installation, give the ZIP to the server administrator. They can extract its `aienglishlesson` folder into Moodle's `mod/` directory and run `php admin/cli/upgrade.php --non-interactive` from the Moodle directory. Upgrading an existing 0.1.0 installation uses the same folder and preserves its records.

## Restore the ready-made sample

1. Create or open an empty test course.
2. Open the course's **More > Course reuse** menu, choose **Restore** (some themes expose Restore directly).
3. Upload `ai-english-lessons-demo.mbz` and continue.
4. Restore into the test course using **Merge the backup course into this course**. Do not choose a delete-existing-content option.
5. Complete the restore and open **At the market - sample lesson**.
6. Enrol a separate test account with the **Student** role. Use that account in another browser/private window. Switching an administrator's role is not an adequate test of enrolment and recording ownership.

The backup includes the activity, its published content, source PDF and two cached WAV files. It does not configure the AI provider, import API keys, or import student submissions. The sample's generated-origin label means prepared example material rather than a quotation from the PDF; the sample is not evidence of live AI generation.

## Student checklist

| Test | Expected result |
| --- | --- |
| Open the lesson | Ten sections appear with reading, listening, speaking and writing |
| Open vocabulary meanings, hints and simpler explanations | Saved help appears immediately |
| Submit a reading answer | It persists after refreshing; prepared model feedback appears |
| Open Listening and press Play | The stored voice reads the passage; the transcript can be expanded |
| Open Speaking | A model player, recording controls and upload fallback appear |
| Record a short sentence and stop | Browser asks for microphone permission; a preview becomes available |
| Submit the recording, then reload | Your saved recording can be replayed |
| Upload a WAV/MP3/OGG/WebM/M4A instead | Valid audio up to 8 MB can be saved |
| Write a dialogue and submit | Writing persists; model feedback is shown for comparison |
| Mark a section complete | The progress count increases |
| Sign in as a second student | They cannot see the first student's responses or recordings |
| Look for teacher controls or source PDF | Students have no generation, draft-editing, publication or source-download controls |

Microphone recording needs HTTPS, except browsers normally allow localhost. The recorder stops at two minutes. If the browser cannot attach the recording automatically, download the preview and select it using the upload control before submitting. Actual microphone capture and visual layout remain manual acceptance checks; automated tests verified recording uploads and access rules.

## Teacher checklist

Open **Review student responses** and check the writing and recording. Teacher review does not assign grades. Prepared feedback is shared model guidance, not an automatic evaluation of an individual student.

For generation on an eligible provider deployment:

1. The server needs Poppler `pdfinfo`, eSpeak NG, permitted PHP `proc_open`, and Moodle cron running regularly. On Debian/Ubuntu the administrator can install the tools with `sudo apt-get install poppler-utils espeak-ng`. Paths default to `/usr/bin/pdfinfo` and `/usr/bin/espeak-ng`. The supplied Docker environment already includes both.
2. Configure the provider at **Site administration > Plugins > Activity modules > AI English Lessons**. Enter the key there, not in chat. Read `PROVIDER.md` for the current adapter's eligibility restriction; do not enable it for a school deployment merely by ticking a checkbox. Live generation has not yet been tested with a real key.
3. Add an activity, upload a small PDF you may send to the provider, choose level/topic, and save. The whole PDF is transmitted even if a page range is selected.
4. Click **Generate lesson**. Refresh after cron processes the queued job. On the local installation cron already runs; an administrator can also run `php admin/cli/cron.php` from Moodle's directory.
5. Open the draft. Review and edit the text, practice tasks, feedback and audio scripts. Student pages still show the previous publication.
6. After editing, click **Build listening audio**, allow cron to finish, and refresh. Audio status should become `ready`.
7. Click **Publish approved draft**. Students now see that version. All four skill tasks and listening audio must be present.

## If something does not work

- **No published lesson:** restore the sample, or finish generation/review/publication first.
- **Job stays queued:** ask the Moodle administrator to check cron and ad-hoc tasks.
- **Audio status failed:** check the eSpeak NG executable path and PHP process-execution permissions, then rebuild audio.
- **Generation diagnostic `configuration`:** provider is disabled or its required settings are missing. This is expected during the key-free sample test.
- **Microphone unavailable:** use HTTPS and a supported browser, or upload an existing audio file.
- **Upload rejected:** use a supported real audio file under 8 MB; check server upload/post limits too.

Send the version, exact error text, action you took, and a screenshot if convenient. Never include API keys. See `STATUS.md` for completed automated verification and remaining limitations.

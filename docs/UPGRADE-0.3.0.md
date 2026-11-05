# Upgrade to AI English Lessons 0.3.0

This release removes the mandatory Poppler dependency, adds cloud speech and teacher audio uploads, and supports lesson preparation without cron. It also replaces the JavaScript click-to-edit key control with a native password input. Existing lessons, uploads and settings are preserved.

## Install on your hosted Moodle

1. Back up your Moodle site as usual before updating plugins.
2. Sign in as administrator. Go to **Site administration > Plugins > Install plugins**.
3. Upload `mod_aienglishlesson-0.3.0.zip` and complete Moodle's upgrade. Do not uninstall the earlier plugin.
4. If your host blocks ZIP upgrades, have its administrator replace `mod/aienglishlesson` with the folder in the ZIP, then open **Site administration > Notifications** to complete the upgrade.
5. Open **Site administration > Plugins > Activity modules > AI English Lessons** and save the settings below.

## Settings for hosting without server executables

| Setting | Value |
| --- | --- |
| Lesson preparation mode | **Direct - no cron needed for lesson preparation** |
| Require Poppler PDF inspection | **Unchecked** |
| Lesson audio preparation | **Gemini cloud speech** for automatic audio; **Teacher uploads audio** for MP3/WAV you supply |
| Gemini speech model ID | `gemini-3.8-flash-lite-tts` |
| Gemini speech voice | `Kore` |
| AI provider, lesson model, API key | Keep your existing configured settings |
| Timeout / daily attempts | Existing defaults 120 seconds / 10 attempts are suitable for an initial small test |

The audio setting defaults to uploads so upgrading alone does not start cloud speech requests. Choose cloud speech explicitly if you want it. Cloud speech uses the same saved Gemini key and provider eligibility/non-billed project requirements. Every missing audio section uses a request from the same daily cap; a typical lesson with two audio sections needs one generation request plus two speech requests. Account quotas and model access still apply. No billing is enabled by the plugin.

Leave the key field blank to retain its saved value. The field deliberately never displays the stored key. Replace it only to rotate the key; a single `-` clears it. A key previously shared in a screenshot should be revoked and replaced privately.

The `/usr/bin/pdfinfo` and `/usr/bin/espeak-ng` paths can stay as they are. Missing executables no longer block the direct/cloud workflow. eSpeak NG is used only if you select Local eSpeak NG.

## End-to-end teacher test

1. Open your existing activity. A previous failed job does not prevent a new attempt.
2. Start with a short PDF. Click **Generate lesson** once and keep the page open. In Direct mode the teacher's request runs generation immediately; no visit to Ad hoc tasks is needed.
3. A successful request opens the draft. Review and edit its text, feedback and audio scripts.
4. Click **Prepare listening and speaking audio**. Keep the preparation page open. It prepares missing sections sequentially and saves each completed file. When complete, click **Return to lesson**.
5. Listen and review. Click **Publish approved draft**, and make the activity visible to students when ready.
6. Sign in as a separate enrolled student. Verify reading, listening, speaking submissions and writing. Student viewing/playback does not call the provider.

For upload mode, open each draft Listening/Speaking section, expand **Upload or replace this section audio**, and choose an MP3 or WAV up to 8 MB matching the displayed script. Save. This also works as a fallback in cloud/local mode. All listening sections need audio before publication. Upload controls are teacher-only. Uploads are allowed only on drafts.

Editing a draft clears its previous audio to avoid mismatched narration. Finish editing before generating/uploading audio. Saved audio is included in Moodle activity backup/restore.

## Limits and failures

- **Without Poppler:** file size, PDF signature/type and range syntax remain checked. Actual page count, encryption and range bounds are unknown, so the page-count cap cannot be enforced locally. The AI provider must interpret the PDF. The entire PDF is still transmitted.
- **Direct-mode timeout:** hosting web/proxy timeouts still apply, even if the plugin requests more PHP execution time. If the page times out, refresh the activity to inspect status before retrying. A missing draft may require a smaller PDF or a host timeout adjustment. Direct mode cannot guarantee long-running processing on every shared host.
- **Audio failed:** the draft displays a safe code such as `failed:http403`, `failed:limit`, `failed:transient` or `failed:speechaudio`. Check model access/quota/configuration, then prepare again. Completed section files are reused. You can upload files instead.
- **Provider policy:** adding cloud audio does not resolve the existing Gemini eligibility restriction for child-directed applications. Resolve the actual deployment's eligibility or use a suitable provider arrangement before enabling requests. See PROVIDER.md.

## Why cron was needed before

Version 0.2.0 only queued work. Moodle cron was supposed to pick it up automatically. Your hosted site showed a large overdue task backlog, and you had to start the individual task manually. The exact hosting cause was not diagnosed remotely.

Version 0.3.0 defaults to Direct mode. Generation runs during the teacher request, and the audio preparation page makes one authenticated request per missing section. Students cannot trigger either operation. Keep the teacher's preparation page open; returning to the draft and preparing again resumes missing audio.

Background mode is still available for hosts with working cron. The hosting administrator should still repair automatic Moodle cron because other Moodle functions need it; Direct mode only removes this plugin's dependency during teacher preparation. Existing queued tasks become harmless no-ops once their recorded generation job is complete.

## Verification

Tested locally against Moodle 4.4.2/PHP 8.2: upgrade, optional/strict PDF checks, direct generation orchestration using a fictional provider, sequential speech caching/retry, teacher audio upload, student denial, CSRF checks, direct HTTP audio synthesis with cron stopped, and native key rendering. Cloud request/response handling used simulated responses, not a real account. Live cloud speech quality and account access remain to be tested on your eligible deployment. No provider key or billed request was used in development.

Official implementation references: [Gemini speech REST instructions](https://aistudio.google.com/docs/speech-generation), [speech pricing and quotas](https://aistudio.google.com/docs/pricing#gemini-3.8-flash-lite-tts). Pricing lists free-tier speech, but availability depends on the project and quotas; the plugin cannot prove billing status.

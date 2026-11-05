# Verification status - 0.2.0 alpha

The 0.2.0 installation ZIP and a key-free sample lesson backup are prepared for Moodle testing. This is not a claim of production certification or successful live AI generation.

## Verified in the local Moodle installation

Environment: Moodle 4.4.2 Build 20240812, PHP 8.2, MariaDB 10.11, Docker Linux containers. Checks completed during the November 2026 development session.

- Existing 0.1.0 schema upgraded to 0.2.0; Moodle's full database-schema checker reports `Database structure is ok.`
- Smoke suite: activity creation through Moodle API, permissions, PDF inspection/range validation, removal of teacher keys, rejection of unknown lesson fields, duplicate prevention, fixture generation, preservation of publication, and maximum three transient attempts.
- Student HTTP suite: logins; saved hints; feedback withheld before submission; escaped/persistent reading responses; cached WAV download; anonymous/draft media denial; speaking upload and replay; teacher access; peer denial; invalid MIME rejected without replacing previous work; persistent writing; teacher report access; response deletion; publication; archived-media denial.
- Editing/publishing HTTP suite: native form, teacher-only PDF, draft/editor denial, CSRF rejection, saved progress, student publication denial, escaped edits, published-version isolation, blocked publication until audio rebuilt, actual queued synthesis through cron, successful publication afterward.
- Audio: eSpeak NG generated WAV files for listening and speaking; files served through Moodle's protected File API. This verifies synthesis and retrieval, not subjective voice quality.
- Backup/restore: versions, source PDF, cached audio, student text and recording files restored with mapped IDs; generation tasks are not replayed. A test-harness setting name was corrected from `userinfo` to root setting `users` before the passing run.
- Privacy: learner/context discovery; archive contains the learner's writing and WAV recording; approved deletion removes responses/recordings/progress.
- Lifecycle cleanup, write-only key rendering, File API upload/update, viewed completion, provider-disabled check and connection test blocked before network.
- Clean portable demonstration backup created with no learner data, then restored and checked for both audio files. Its local original is activity ID 12.
- PHP syntax checked for all plugin files; JavaScript built module syntax checked. Runtime integration ran under PHP 8.2.
- Automated stored-content checks confirm zero provider attempts. Fixtures and sample lesson are explicitly not live AI output.

Moodle core emitted messages about inapplicable course settings while restoring an activity backup; restoration and data assertions passed.

## Still requires external/manual acceptance

- A real provider key and an eligible provider/deployment arrangement for live PDF generation. No API key or billed request was used. The current Gemini adapter's school-deployment restriction is described in PROVIDER.md.
- Visual layout and actual microphone capture: browser access host unavailable; native app access timed out waiting for approval. No repeated UI retries. Recording upload, replay and permission rules passed HTTP tests, but a real microphone test remains in TESTING.md.
- The exact production Moodle 4.4.2+ Build 20240823 and the user's own hosting configuration.
- Concurrent multi-process stress and independent security/accessibility review were not performed.

## Scope and limits

All four skills use saved content. Teacher-only generation, editable drafts, publication, cached local speech, recording/upload, responses, prepared feedback, progress, backup and privacy are implemented. There is no student AI chat, pronunciation scoring, personalised automatic assessment or gradebook grading. Model feedback appears after submission but does not evaluate that individual response.

A selected page range only scopes the prompt: the whole source PDF is transmitted. Jobs read settings/files when they run. Text is plain and escaped; English UI only. The voice uses local eSpeak NG. Viewing completion and the section checklist are separate. History persists until activity deletion; no scheduled retention feature is implemented. Teacher review remains necessary.

## Delivery

`dist/mod_aienglishlesson-0.2.0.zip` is the plugin. `dist/ai-english-lessons-demo.mbz` is a restoreable example after plugin installation. Both have checksums in `dist/SHA256SUMS.txt`. No credentials are bundled in the plugin ZIP. Development scripts and local environment credentials are outside it.

See TESTING.md for the test steps and ARCHITECTURE.md for class-by-class explanation. The earlier 0.1.0 ZIP is superseded for current testing.

# Implementation status

Alpha 0.1.0. Milestone 1 and 2 implementation plus the basic publishing/study flow from Milestone 3.

Implemented: native activity lifecycle, settings form, File API PDF upload and teacher-only serving, lesson reader, grade/proficiency settings, native PDF adapter, structured validation, background jobs, duplicate prevention, three-attempt transient retry, application request/PDF limits, safe diagnostics, editable drafts, separate publication, section progress, Moodle view completion, backup/restore, privacy export/deletion, and ZIP packaging.

Verified on 2026-11-04 against the local Docker Moodle 4.4.2 site:

| Check | Result |
| --- | --- |
| PHP syntax, all plugin PHP files | Passed (Windows PHP 8.3; execution tested under Docker PHP 8.2) |
| Moodle installation and plugin schema | Installed; final XMLDB schema parses. Corrected empty CHAR defaults reported by the first install. |
| Native activity creation and settings form | Passed through Moodle API and authenticated HTTP |
| File API PDF save/update and inspection | Passed with a real one-page fixture PDF; invalid range rejected |
| PDF access | Teacher download works; student and anonymous access denied |
| Generation orchestration | Valid draft saved with a test double; duplicate jobs prevented; regeneration preserves publication; transient failures stop after three attempts |
| Teacher editing and publishing | Passed authenticated HTTP checks; draft edits leave publication unchanged; approved draft becomes visible |
| Student isolation | Draft URL/editor/publish access denied; teacher answer keys absent from student output |
| Output safety and CSRF | Script text escaped; edited markup not executed; invalid session token makes no progress change |
| Progress and completion | Section progress persists; Moodle view completion recorded |
| Saved lesson API usage | Zero API attempts during viewing, editing and publishing |
| Backup/restore | Moodle activity archive created and restored; both versions and PDF recovered; jobs not replayed |
| Privacy | Learner/context discovery and approved learner progress deletion passed |
| Lifecycle deletion | Restored activity deletion removes its versions and source files |
| Credential rendering | Saved fictional credential absent from settings HTML |
| Actual Moodle ad-hoc execution | Disabled-provider job ran through the task CLI and failed with safe `configuration` code, one attempt, zero API calls |

The clean smoke suite passed 11 checks, authenticated HTTP suite 19 checks, backup/privacy suite 14 checks, and focused final suite 4 checks. Backup restore emitted core messages about inapplicable course settings for an activity backup; restoration and data assertions succeeded. Test harness issues and the interrupted installation's missing defaults were repaired before the successful checks. The original Windows source mount was replaced with a Docker volume to remove slow file scanning.

**Not verified:** live provider connection/PDF generation, exact production Build 20240823, provider eligibility, visual browser inspection, privacy export archive contents, simultaneous multi-process race stress, or audio/tutor features. No real provider key or billed API call has been used. Test doubles verify orchestration only, not generation quality or live API compatibility.

Delivery: `dist/mod_aienglishlesson-0.1.0.zip`; archive integrity and single `aienglishlesson/` root verified. SHA-256: `d24a435bae64ca07d5b94061e4b6522f82735e4264c4f050d971b2c89d5ce6dd`. PHP syntax and `git diff --check` passed after the final changes. Web, database and recurring cron services are running; cron reports successful runs. Local demonstration: `http://localhost:8088/mod/aienglishlesson/view.php?id=4` (fictional fixture, not real AI output).

Unfinished: controlled student tutor actions; student text submission/feedback; audio generation/cache; recording; pronunciation assessment; visual generation beyond vocabulary cards; visual editing of section structure; automatic status polling; automated semantic age/accuracy assessment. Teacher review remains mandatory. There is no consequential grading.

Limits: all PDF pages are transmitted even when a page range is requested; pending jobs read current activity settings/files; all section content is plain text; only English UI strings; section completion is distinct from Moodle view completion; source PDFs never reach students. No scheduled history retention policy yet. Job and version history persist until activity deletion. Source author metadata uses Moodle core File API privacy handling; student progress is exported/deleted by this plugin.

Compatibility reference: official Moodle v4.4.2 source at `cc2edf3b8ccaa116e19ea2bb5daec7ed8adb3acd` (Build 20240812), including Page lifecycle/File API/backup/restore, Choice privacy API, course `add_moduleinfo`, Moodle curl, locking and ad-hoc task manager. Official [activity documentation](https://docs.moodle.org/dev/Modules) was consulted; archived 4.4 developer-doc routes returned 404, so actual 4.4 source was the compatibility authority. Exact production 4.4.2+ Build 20240823 is not yet tested.

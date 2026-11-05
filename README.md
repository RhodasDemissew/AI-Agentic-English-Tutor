# AI English Lessons - Moodle activity

Native `mod_aienglishlesson`, alpha 0.2.0 for Moodle 4.4. Teachers upload a PDF, generate and review a draft, then publish stored content covering reading, listening, speaking and writing. Students use saved content and submit text/audio without AI requests.

## Start here

- [Install and test](docs/TESTING.md): simple ZIP installation, sample restoration and student/teacher checklists.
- [How the system works](docs/ARCHITECTURE.md): technologies, classes, database, communication diagram and workflows.
- [Verification and limits](docs/STATUS.md): what passed and what remains unverified.
- [Provider configuration](docs/PROVIDER.md): adapter requirements and unresolved school-deployment eligibility.

## Deliverables

- `dist/mod_aienglishlesson-0.2.0.zip`: upload through Moodle's plugin installer.
- `dist/ai-english-lessons-demo.mbz`: restore into a test course AFTER installing the plugin. Includes prepared content and cached audio, without learner submissions or API settings.

The sample needs no key. Real PDF generation requires an eligible configured provider and key; no live provider generation has yet been verified. The plugin defaults to disabled AI. Do not paste keys into chat or source code.

## Local environment

Docker Desktop must run Linux containers. Existing installation:

```powershell
docker compose up -d
```

Open http://localhost:8088/mod/aienglishlesson/view.php?id=12. Local student: `aielstudent` / `LocalStudent-42!`. Local administrator: `admin` / `LocalDev-Only-42!`. These development credentials must not be used on an exposed site.

The local stack uses Moodle 4.4.2 Build 20240812, PHP 8.2, MariaDB 10.11, Poppler and eSpeak NG. The requested exact 4.4.2+ Build 20240823 is not separately verified. The web port is bound to localhost; the database has no published host port. Source and data use Docker volumes; only the plugin is mounted from this workspace. No live Moodle site has been modified.

For a new local setup, with Git and Python 3 installed, run `powershell -ExecutionPolicy Bypass -File dev/setup.ps1`. Initial image downloads may take time. `docker compose stop` preserves data. Do not remove volumes unless intentionally discarding local data.

## Development verification

Scripts create disposable fictional data and are restricted to the localhost development site. Run from this project directory. Keep cron running.

```powershell
python dev/sample_pdf.py
docker compose exec -T --user www-data web php /opt/aiel-dev/smoke.php
docker compose exec -T --user www-data web php /opt/aiel-dev/prepare_student_fixture.php
docker compose cp web:/var/moodledata/aiel-test.json .local/aiel-test.json
python dev/student_checks.py .local/aiel-test.json
docker compose exec -T --user www-data web php /opt/aiel-dev/post_checks.php
docker compose exec -T --user www-data web php /opt/aiel-dev/final_checks.php
```

The editing/publishing suite needs a fresh fixture:

```powershell
docker compose exec -T --user www-data web php /opt/aiel-dev/smoke.php
docker compose cp web:/var/moodledata/aiel-test.json .local/aiel-test.json
python dev/http_checks.py .local/aiel-test.json
docker compose exec -T --user www-data web php admin/cli/check_database_schema.php
```

Build the ZIP with `powershell -File dev/package.ps1`. Rebuild the recorder's distributed module with `python dev/build_js.py` if its source changes. `dev/export_demo.php` creates and restore-tests a clean sample activity; unlike `smoke.php`, its content contains no escaping/secret-marker test strings. Generated local demo IDs change on each run.

## Server requirements for new lessons

The Moodle server needs `pdfinfo`, `espeak-ng`, PHP process execution and working Moodle cron. The Docker image includes these. Configure binary paths in the plugin settings. Microphone capture needs HTTPS or localhost; audio upload is available as a fallback. Existing cached audio does not require synthesis during student use.

License: GNU GPL v3 or later; see `aienglishlesson/COPYING.txt`.

# AI English Lessons — Moodle activity

Native `mod_aienglishlesson`, alpha 0.1.0, for Moodle 4.4. PHP, Moodle File API, database, Mustache and ad-hoc tasks; no separate backend. Start with the [status and checks](docs/STATUS.md).

## Local Windows setup

Prerequisites: Docker Desktop running Linux containers, Git, PowerShell, Python 3 (source preparation and packaging). From this directory:

```powershell
powershell -ExecutionPolicy Bypass -File dev/setup.ps1
```

The script clones official Moodle tag `v4.4.2`, builds PHP 8.2 with required extensions and Poppler, and installs Moodle in project `aienglishlesson-dev`. Source is copied into a Docker volume to avoid slow Windows bind-mount file scans; only the plugin is mounted live from this workspace. The site is bound only to `http://localhost:8088`. Local account: `admin` / `LocalDev-Only-42!`. These intentionally public development credentials must never be used on an exposed site. The database has no host port. Existing unrelated Docker projects are untouched.

The official tag reports **4.4.2 (Build: 20240812)**. The requested production build is **4.4.2+ (20240823)**; testing the release baseline does not establish an exact-build production test. Do not expose this old development Moodle to the Internet. The setup uses upstream source unchanged, plus a local `config.php` and plugin mount. If port 8088 is occupied, change both `compose.yaml` and `dev/config.php` before installation.

Existing installation:

```powershell
docker compose up -d web cron
docker compose exec -T --user www-data web php admin/cli/upgrade.php --non-interactive
docker compose exec -T --user www-data web php admin/cli/cron.php
docker compose logs --tail 60 cron
```

`docker compose stop` stops only this project and preserves data. Do not remove volumes unless you deliberately want to discard local courses and accounts. First image downloads can take several minutes; retry `docker compose up -d web` after transient registry/DNS failures.

## Installable ZIP

Run `powershell -File dev/package.ps1`. The ZIP is `dist/mod_aienglishlesson-0.1.0.zip`, with exactly one `aienglishlesson/` root directory. In a **staging Moodle 4.4** site: Site administration → Plugins → Install plugins → upload ZIP → review checks → upgrade. Alternatively copy the source folder to `mod/aienglishlesson` and run Moodle's upgrade CLI. No live site has been accessed.

Poppler `pdfinfo` must be installed on the Moodle server and its executable path configured. The supplied Docker image includes it. PHP needs permission to execute that command and access its temporary directory. The first version targets Linux server execution; the development host can be Windows.

## Teacher workflow

1. Enable editing in a course; add **AI English Lessons**.
2. Enter title, PDF, grade/age band, proficiency (including Not specified), topic and/or page range, duration, and optional instructions. Save.
3. Click **Generate lesson**. Cron runs the queued job; refresh the page to see status, attempt count, and safe diagnostic code.
4. Open the new draft version. **Edit draft** allows plain-text editing of explanations, models, practice, teacher answer keys and vocabulary cards.
5. Review accuracy, child suitability, provenance, and answer placement, then **Publish approved draft**. Students see only the current publication. Regeneration creates another draft without changing that publication.

Students navigate sections, practise with a teacher/notebook, and mark sections complete. This does not collect student answers or grade them. Moodle's standard manual completion and automatic **view** completion are supported; the section checklist is independent of Moodle's view-completion rule.

Generation reads the PDF and settings present when its task runs. Avoid changing them while a job is queued. A selected page range scopes the prompt, but the **entire PDF is transmitted**; upload a physically extracted PDF if other pages must not leave the server. No silent truncation occurs. PDFs are teacher-only because source books may include answer keys.

## Provider configuration and the eligibility blocker

Read [provider findings](docs/PROVIDER.md) before enabling requests. Gemini is **disabled by default**. Its current terms prohibit API clients directed towards or likely accessed by under-18s. Adult developers using fictional responses do **not** automatically resolve that restriction for this KG–12-intended application. A checkbox cannot change the terms. Establish eligibility with the provider or replace the adapter with an appropriately selected provider before enabling generation. Student AI actions remain absent.

When an eligible deployment/provider arrangement has been established, configure locally at **Site administration → Plugins → Activity modules → AI English Lessons**. Enter the server-side API key there, never in chat, source, or a ZIP. The adapter is complete without a key. An administrator can alternatively force the `apikey` setting through `$CFG->forced_plugin_settings['aienglishlesson']['apikey']` in private Moodle configuration. Protect site configuration/database backups.

The key field never returns the saved value to the browser. Leave it blank to preserve it, enter a replacement to rotate it, or enter a single `-` to clear it. The Gemini adapter requires both eligibility and non-billed-project confirmations. Use a free-tier project **without an active billing account**. It cannot remotely prove billing status. Daily request caps do not make a paid key free. Do not attach billing or use paid credentials without separate authorisation. Configure the model available to the key, timeout, daily attempts and PDF limits. The default model was verified in official documentation; model access and free quotas can change.

**Test provider connection** makes a model-metadata request only, counts against the local cap, and checks `generateContent` support. It does not generate text or prove billing/terms eligibility. For a real generation test, upload `dev/sample.pdf`, click Generate lesson, run cron, review the draft, and record the model, date and result without logging secrets. No real generation has been claimed by automated fixtures.

## Diagnostics

- `configuration`: provider disabled, confirmations absent, key missing or invalid model syntax.
- `http400`: model/request rejected; check current API compatibility and PDF.
- `http401` / `http403`: invalid key, region or access restriction.
- `http404` / `model`: model unavailable or incompatible.
- `limit`: local rolling 24-hour API-attempt cap reached; wait before creating another job.
- `transient`: timeout, network, 429 or server error; maximum three attempts with Moodle task backoff.
- `incomplete`: safety block or output cutoff; no partial lesson is saved.
- `invalidlesson`: structured data failed local checks; no lesson saved.
- `missingpdf`, `invalidpdf`, `rangeerror`, `unreadablepdf`, `toomanypages`, `nopdfinfo`: fix the document or server PDF inspection configuration.
- `retrylimit`: three interrupted attempts; create a fresh job after fixing the cause.
- `internal`: inspect server configuration; provider responses/keys are deliberately not persisted in diagnostics.

## Verification

The development scripts create fictional local data and must only run on this isolated site:

```powershell
python dev/sample_pdf.py
docker compose exec -T --user www-data web php /opt/aiel-dev/smoke.php
docker compose cp web:/var/moodledata/aiel-test.json .local/aiel-test.json
python dev/http_checks.py .local/aiel-test.json
docker compose exec -T --user www-data web php /opt/aiel-dev/post_checks.php
docker compose exec -T --user www-data web php /opt/aiel-dev/final_checks.php
docker compose exec -T --user www-data web php admin/cli/adhoc_task.php --execute --classname='\mod_aienglishlesson\task\generate'
```

Re-run `smoke.php` to create a fresh fixture before each HTTP run. It leaves a test course and student `aielstudent` / `LocalStudent-42!` for inspection. Test-double generation is labelled as a fixture. It sends no AI requests. See STATUS for checks actually completed and pending work.

License: GNU GPL v3 or later; see `aienglishlesson/COPYING.txt`.

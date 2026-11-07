# AI pipeline: Windows Server / XAMPP / Antigravity

This folder contains a staging development roadmap, a bounded hourly Antigravity
runner, and an optional attendance snapshot batch. It is **not a completed AI
product or an automatic production deployment system**. All project edits for this
package stay in `ai_pipeline/`. Application services are reused without modifying
the portal. Feedback/CQI adapters and portal integration tests remain roadmap work.

## Requirements and layout

- Python **3.10+**, PHP **8.1+** with `mysqli`/mysqlnd; the existing core uses PHP 8
  features. PHP `curl` is required when processing real AI jobs.
- Existing application schema and services, plus a separately provisioned disposable
  `cea_standardisation_test_...` database containing the required core schema/fixtures.
- Antigravity CLI installed and authenticated as the Windows task account.
- Ollama listening on loopback, with the exact configured model installed, for LLM
  health checks/jobs. Attendance calculations themselves do not need Ollama.
- No third-party Python libraries are required. This package does not train an ML
  model or estimate detention probabilities.

Use a separate checkout, for example `C:\classattendance-staging`, for development.
Do not point the development scheduler at `C:\xampp\htdocs\classattendance.in`.
Use a restricted staging DB account with access only to the disposable database;
configure the checkout's application `.env.php` through your usual deployment process.
The test override must differ from its `DB_NAME` (an unused staging default name is
suitable). Never copy live database credentials into the staging agent account.
`environment=staging` is a configuration check, not an OS sandbox; also configure
Antigravity's filesystem/command permissions and Windows account access appropriately.

Keep runtime files and the Python environment outside every Apache-served folder:

```text
C:\classattendance-staging\ai_pipeline\    versioned source
C:\classattendance-ai\staging-config.json private configuration, no credentials
C:\classattendance-ai\staging\            state, locks, run logs, venv
```

`ai_pipeline/.htaccess` denies all HTTP access, including `api/`. Its PHP files are
server-side includes, not public routes. On XAMPP, ensure Apache honors this rule
(`AllowOverride AuthConfig` or `All`) or install an equivalent `Require all denied`
Directory rule. Verify an HTTP request for `ai_pipeline/ai_tasks.json` returns 403
before copying this package under any public document root. Never rely on hiding
filenames. Restrict the private runtime folder's Windows ACL to the service account
and administrators, and keep sufficient disk space/log retention.

## Windows setup

1. Place this folder in the separate staging checkout, alongside the existing
   application. Install Python, XAMPP PHP, Antigravity CLI and Ollama as required.
   Provision the isolated test DB separately; the scheduled agent does not create
   databases or manage database users.
2. Copy `pipeline_config.example.json` to the private config location above. Replace
   executable paths with actual absolute paths (`where agy` / `where php`), select
   the test DB, and keep `environment` set to `staging`.
3. Open Command Prompt as the account that will run the task:

   ```bat
   set "AI_PIPELINE_CONFIG=C:\classattendance-ai\staging-config.json"
   set "AI_PIPELINE_PYTHON=C:\Path\To\Python312\python.exe"
   ollama pull gemma2:2b
   C:\classattendance-staging\ai_pipeline\setup_windows_lightsail.bat
   ```

   Setup creates `runtime_dir\venv`, runs offline tests and read-only health checks,
   and returns nonzero on failure. It does not install global packages or migrate
   databases. Ollama must already be running. Its model name must match exactly.
4. Set `AI_PIPELINE_PYTHON` to the virtual environment path printed by setup for
   subsequent runs. Authenticate the CLI interactively once as the task account.
   Verify `agy -p "Reply with READY"` exits successfully without interaction under
   that account. The configured permission policy must permit the staging work;
   this package does not bypass permission prompts. Confirm Windows Server support
   with the installed CLI version rather than assuming Windows desktop sandbox
   features work on the server.
5. Run `run_hourly_development.bat` once manually and inspect private logs before
   enabling a recurring schedule. TASK-01 applies/verifies the pipeline migration
   in the isolated DB. Subsequent integration tasks require representative fixtures.

The batch wrappers return their child's exit status and do not pause. Set the two
environment variables in the scheduled-task account environment, or in a private
launcher outside the web root. Task Scheduler does not inherit variables set in a
separate interactive command window. Environment paths must not contain shell
metacharacters. Default examples use paths without spaces; Task Scheduler's quoted
script-path example below supports ordinary spaces.

## Hourly scheduler: choose one

**Windows Task Scheduler (preferred for a server):** create a task under the same
Windows account used for CLI authentication. Set an hourly repeating trigger,
`Do not start a new instance`, a maximum runtime long enough for implementation
plus all verification commands, and a startup trigger if recovery after reboot is
needed. Do not assume an interactive desktop/RDP session remains active.

- Program: `C:\Windows\System32\cmd.exe`
- Arguments: `/d /c ""C:\classattendance-staging\ai_pipeline\run_hourly_development.bat""`
- Start in: `C:\classattendance-staging`

**Antigravity sidecar:** edit `sidecar.json` to match the actual staging path and
private configuration/Python paths, then copy it into
`%USERPROFILE%\.gemini\config\sidecars\hourly-ai-developer\sidecar.json`.
Merge the following into the existing global
`%USERPROFILE%\.gemini\config\config.json`; do not overwrite existing settings:

```json
{"sidecars":{"hourly-ai-developer":{"enabled":true}}}
```

The sidecar schedules the locked CLI wrapper instead of starting untracked
`agentapi` conversations. It therefore does not need `agentapi`'s `projectId`.
Antigravity manages sidecar processes; keep the app running and verify behavior
after sign-out/reboot. The manifest uses a five-field cron schedule in the server's
scheduler timezone; configure/verify the Windows timezone explicitly. Do not enable
both scheduling methods. Paths containing spaces may require adapting the `cmd /c`
quoting in the sidecar; test the exact manifest command before enabling it.

Verified against official documentation on 2026-10-24:
[sidecars](https://antigravity.google/docs/sidecars/),
[headless CLI](https://antigravity.google/docs/cli/headless).
The CLI is invoked with `-p`; bare `agy "prompt"` is not used for unattended work.
No real Windows/Antigravity invocation has been performed during this local update.

## State, validation and recovery

`ai_tasks.json` is a versioned roadmap template. The runner creates a private copy
at `runtime_dir\ai_tasks.state.json`. Updating the template does not overwrite an
existing runtime roadmap. Reconcile changes deliberately while scheduling is stopped.
All tasks remain PENDING until the actual staging run passes.

The runner holds an OS lock for the whole agent/check sequence, selects one task
with satisfied dependencies, increments attempts, and writes state atomically. It
requires a matching result receipt, implementation outputs, acceptance evidence,
and successful verification commands. It never equates a successful agent exit
or PHP syntax check with complete behavior validation. Receipts are evidence
reports, not an independent proof of correctness; review implementation and tests
before production release. There is no automatic commit or deployment.

Useful commands (using the configured virtual environment):

```bat
"%AI_PIPELINE_PYTHON%" C:\classattendance-staging\ai_pipeline\worker\task_runner.py status
"%AI_PIPELINE_PYTHON%" C:\classattendance-staging\ai_pipeline\worker\task_runner.py run
"%AI_PIPELINE_PYTHON%" C:\classattendance-staging\ai_pipeline\worker\task_runner.py retry --task TASK-01 --confirm-stopped
```

Failed/blocked tasks stop further dispatch, including independent pending tasks.
An interrupted IN_PROGRESS task becomes FAILED on the next run. First stop surviving
agent/child processes, inspect the changes and logs, resolve the cause, then use
`retry --confirm-stopped`. Tasks are never silently reclaimed after a timer expires.
Retries are bounded by `max_attempts` (default 3). A run's timeout terminates its
process tree. A forced parent termination can leave children behind, which is why
manual confirmation is required for recovery. Do not delete locks/state to bypass it.

Each run has `assignment.json`, `result.json`, `agent.log` and numbered check logs
under `runtime_dir\runs`. `execution_log.jsonl` records task outcomes. Restrict these
logs and rotate/archive completed runs while the scheduler is stopped. Never log
credentials, student identifiers, raw feedback or unredacted prompts.

## Attendance snapshots and optional nightly processing

`worker/attendance_ml_predictor.py` retains the original filename as a compatibility
entry point; it delegates to `worker/attendance_batch.php` and defaults to dry-run.
The PHP batch uses `College\AttendanceService` with enrollment, joining dates,
class scope, permission policy, missing-data categories and regulation thresholds.
It stores provisional results, policy hash and cutoff date, not invented risk
probabilities or final-attendance projections. The 14-day value is the recent
percentage minus the term percentage, and remains null when evidence is uncertain.
Recovery counts address only the aggregate target; subject requirements still apply.

Writes are transactional and unique by student/class/date/policy, so rerunning a
batch updates the same snapshot rather than adding duplicates. No student identity
or roll number is printed to batch logs. Retention is operational: schedule explicit
snapshot/report cleanup only after agreeing the audit retention policy.

For production, deploy the reviewed folder separately, back up the DB, apply the
migration explicitly with your normal migration account, and verify it. The SQL
adds analytics tables only; it does not activate a provider or change consent.
`CREATE TABLE IF NOT EXISTS` does not upgrade old incompatible tables. If a previous
prototype table exists, retain/rename it and migrate its data deliberately; the
verifier fails rather than treating an old shape as compatible. DDL may commit
implicitly, so a transaction is not a migration rollback. See `database/SCHEMA_NOTES.md`.

Use a separate production config/runtime with `environment=production`. The
attendance batch is disabled there until `allow_production_batch=true`. Set
`process_ai_jobs=true` only if the existing worker/provider/module/consent workflow
is deliberately configured; this is separately disabled by default. Provider
selection remains in the existing administrator UI. Python's Ollama health config
does not change the PHP provider configuration.

Schedule `run_nightly_batch.bat` daily at the intended Windows local time under the
correct account. It verifies schema, writes attendance snapshots, and optionally
runs the existing `tools/ai_worker.php --limit=10`. It stops on command failures and
uses a shared lock within that runtime. It does not automatically run future
feedback/CQI tools. Worker exit success does not mean every AI job succeeded; inspect
`ai_jobs` status/error fields using existing administrative tools.

For read-only UI integration, include `api/risk_service.php` from an authenticated
portal request, pass the existing `mysqli` connection, student ID and class ID to
`RiskService::getStudentSnapshot`. It enforces scope and ignores missing, stale,
old-class or changed-policy snapshots. No portal files were edited; examples and
scoped integration tests are explicitly TASK-07 work.

## Local checks and release limits

Run from this folder, with Python 3.10+ and PHP 8.1+:

```text
python -m unittest discover -s tests -p "test_*.py"
php tests/test_metrics.php
php tests/verify_pipeline.php --check-php-lint
```

These offline checks touch only this folder and launch no agent/database/model.
Server-only checks include `--check-core-schema`, `--check-schema` and
`--full-audit` on `tests/verify_pipeline.php`. Supply
`--database=cea_standardisation_test_NAME` for staging; `--apply-schema` is accepted
only with an explicit isolated database override. `--full-audit` means lint plus
schema, not an end-to-end certification. The roadmap requires separate behavior
and Windows execution evidence before completion. Use `WINDOWS_VALIDATION.md` to
record actual server results.

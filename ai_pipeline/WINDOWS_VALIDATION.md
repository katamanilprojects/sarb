# Windows validation record

Current status: **PENDING**. This update was checked locally on macOS only.
Do not mark Windows validation complete without running these checks on the server.

Record date, Windows version, task account, PHP/Python/Antigravity/Ollama versions,
actual executable paths and timezone, without credentials or student data.

- [ ] Apache returns 403 for ai_pipeline/ai_tasks.json and worker source files.
- [ ] Private config/runtime/venv are outside all Apache document roots with restricted ACLs.
- [ ] Headless CLI authenticates under the actual Task Scheduler account.
- [ ] Setup succeeds and missing DB/model causes a nonzero exit.
- [ ] Pipeline schema applies twice to an isolated DB without changing provider/module/consent rows.
- [ ] Old prototype schema fails verification with a clear incompatibility result.
- [ ] One manually launched hourly run completes only the assigned task.
- [ ] Concurrent invocation is blocked; failed tasks prevent downstream dispatch.
- [ ] Task timeout stops its child process tree; interrupted work requires explicit recovery.
- [ ] Scheduled invocation works without an RDP session and after reboot.
- [ ] Attendance matches canonical policy with representative isolated fixtures.
- [ ] Dry runs leave the DB unchanged; repeated writes update the same snapshot.
- [ ] Production configuration rejects autonomous development and disables batch writes by default.
- [ ] Batch failures reach Task Scheduler; individual AI job failures are separately visible.
- [ ] Backup, deployment and rollback have been rehearsed in staging.

Add timestamps, exact check scope, expected/actual results and private log locations
as each item is verified. Keep fixture identities and credentials out of this file.

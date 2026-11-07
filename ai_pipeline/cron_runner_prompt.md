# Instructions for the assigned Antigravity development run

This file is read by `agy -p` through `worker/task_runner.py`. Do not schedule this
prompt directly: the Python runner owns locking, task selection, recovery and tests.

1. Read the absolute JSON file in the `AI_PIPELINE_ASSIGNMENT` environment variable.
   It identifies exactly one task, its run ID, staging database, interpreters,
   acceptance criteria, and the result file you must write. If absent, stop.
2. Work only in `ai_pipeline/` in the assigned staging checkout. Read the existing
   application interfaces needed by the task, but do not edit other directories.
   Follow repository instructions. Do not commit, deploy, publish, enable features,
   change consent, process production AI jobs, or alter production data.
3. Use the explicitly assigned isolated `cea_standardisation_test_...` database
   for all integration work. Existing `.env.php` remains owned by the application;
   never parse/copy its credentials or print them. Use the pipeline PHP bootstrap.
   Migrations may run only against the assigned isolated database. Do not change
   service ACLs or provision/drop databases from this scheduled run.
4. Implement the assigned task and meaningful tests. Inspect actual source columns
   before querying feedback. Use existing `College\AttendanceService` for attendance
   and `Services\AIJobService` for consent, authorized queuing and review. Current AI
   task keys are `ATTAINMENT_ANALYSIS` and `ATR_PROPOSAL`; do not invent new keys.
   Preserve module controls, PII-free evidence, worker leases and human approval.
   Never treat attendance heuristics as calibrated probabilities or official decisions.
5. Run every assigned check with the supplied interpreters. Do not weaken a test,
   replace behavior tests with lint, or fabricate fixtures/results to get a pass.
   The runner repeats the checks. If required evidence, schema or permission is
   absent, report BLOCKED with the concrete prerequisite; never silently skip it.
6. Write only the assigned result JSON (and implementation/test files). Do not edit
   `ai_tasks.json`, runtime state, runner code, private configuration or assignment.
   Use the following shape, preserving the assigned task ID and run ID:

```json
{
  "task_id": "TASK-01",
  "run_id": "COPY_FROM_ASSIGNMENT",
  "status": "COMPLETE",
  "summary": "Describe the implemented behavior and validation scope.",
  "acceptance_evidence": ["One concrete evidence description per acceptance criterion, in order."]
}
```

Use `"status": "BLOCKED"` with the reason in `summary` when blocked. Exit after
this one task. Passing staging checks is not production release authorization.

# Schema contract and migration notes

Static application-interface inspection found the following existing contracts:

- `attendance(stu_id,sub_id,date,hour,status)` is scoped through current enrollment
  and class membership. Counting all historical rows per student is incorrect.
- `College\AttendanceService` owns policy, permissions, coverage checks and the
  categories ELIGIBLE, CONDONABLE, DETAINED, POLICY_REVIEW and DATA_UNAVAILABLE.
- `AIJobService` supports ATTAINMENT_ANALYSIS and ATR_PROPOSAL, with consent,
  reviewed evidence snapshots, provider hashes, leases and human review.
- `ai_providers` rows and `MOD_AI_ASSISTANCE` are operator-controlled. Migrations
  must not overwrite provider ID 1, assume user ID 1, or automatically enable AI.

The previous unverified prototype SQL defined incompatible risk fields and could
overwrite provider settings. Version 2 stores canonical categories and JSON metrics,
adds class/date/policy keys, and creates draft-only feedback report storage. It does
not add foreign keys blindly: legacy ID signedness/engines need server inspection.

Before production migration:

1. Record the DB engine/version and SHOW CREATE TABLE for existing analytics tables.
2. Take and verify a recoverable backup using the existing deployment procedure.
3. Run the migration twice against the isolated clone; verify column types and unique
   keys with `tests/verify_pipeline.php --check-schema --database=...` and compare
   provider/module/consent rows before and after.
4. If old analytics tables exist, preserve them with a distinct archive name after
   reviewing dependencies, then explicitly migrate compatible historical values.
   Never relabel heuristic probabilities as authoritative attendance evidence.
5. Apply the reviewed migration to the selected production DB, verify it, then
   enable only the intended operational batch.

Rollback: disable the nightly task and restore the prior application release first.
The new tables are additive and can remain unused. Restore data from the verified
backup only when required and reviewed; do not automatically drop analytics tables.
MySQL/MariaDB DDL may commit implicitly, so rollback() cannot undo CREATE/ALTER.

Server DB engine/version, migration replay and populated integration checks:
**NOT RUN in this local update**. TASK-01 must record actual server evidence here.

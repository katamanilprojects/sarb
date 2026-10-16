# Production export verification — 16 October 2026

Sources: [structure export (5)](</Users/anilkumarkatam/Downloads/u182589698_jntuaceasarb (5).sql>) and [structure plus academic settings export (6)](</Users/anilkumarkatam/Downloads/u182589698_jntuaceasarb (6).sql>), both from MariaDB 11.8.9.

## Current status — production verification received

The operator supplied successful execution output from all eight verification queries on MariaDB 11.8.9 in [the pasted verification output](/Users/anilkumarkatam/.codex/attachments/ed249c4f-37c9-4cf6-88d3-dc8c987e53f3/Pasted%20text.txt). This supersedes the earlier pre-retry export findings below.

- Migration 001's stored scope column and unique (regulation_scope,setting_key) index are present; only the new scoped index appears in the old/new-index check.
- All six program/regulation configurations report one matching regulation, zero mismatched settings and OK: B.Tech R20/R23/R26 (10 checked values each), MCA R20 (7), M.Tech R21/R25 (9 each). All 55 expected settings match.
- All 532 supported PG course records are 40/60: MCA R20 36, M.Tech R21 210, M.Tech R25 286. No legacy 30/70 or nonstandard maxima remain in the checked scope. The earlier planned 246 repairs were the MCA/R21 legacy records; the R25 group was already configured at 40/60 and must not be reported as 286 additional repairs.
- Valid before/after configuration audit snapshots exist for all six regulation IDs (1,3,4,5,6,7), with the latest timestamp reported as 2026-10-16 13:52:39. There are 34 department-scoped snapshot entries in the output; every reported entry has valid JSON. The output does not include actor_user_id, so it does not independently confirm actor attribution.
- The helper-procedure query returns zero rows; it has been cleaned up. No additional procedure deletion is necessary.
- The optional schema_migrations table is present. This check establishes its existence, not its per-file checksum records.

Both migrations' intended database state is now verified in the supplied production output. No migration rerun is required based on these results. The queries remained valid with information_schema as the session database because application references and metadata filters explicitly name the production schema. This confirms the database configuration and cleanup; it does not establish end-to-end portal/results functional testing. No production database connection or modification was performed by this review.

## Historical status — exports before the successful retry

| Migration | Result |
| --- | --- |
| 20261015_001_regulation_setting_scope.sql | Its intended schema result is present: the STORED regulation_scope column and unique (regulation_scope,setting_key) index exist; the old uk_reg_setting index is absent. |
| 20261015_002_verified_regulation_policies.sql | Its intended data state is absent. All six regulation groups lack cia_scheme_json. R20 at regulation ID 1 and R21 at ID 5 also lack grade_bands_json. |

The structure export contains all 90 baseline tables and 821 expected baseline columns plus the new scope column, for 822 columns. All exported tables use InnoDB. Result/review/SEE dependencies and checked key uniqueness constraints are present.

Export (6) contains **256 academic_settings rows**, with no duplicate regulation-ID/key pairs:

| Regulation ID | Label | Setting rows | CIA scheme JSON | Grade-band JSON |
| --- | --- | ---: | --- | --- |
| 1 | R20 | 3 | Missing | Missing |
| 3 | R20 | 38 | Missing | Present |
| 4 | R23 | 65 | Missing | Present |
| 5 | R21 | 3 | Missing | Missing |
| 6 | R25 | 44 | Missing | Present |
| 7 | R26 | 65 | Missing | Present |
| NULL | Legacy/global scope | 38 | Not used for exact-regulation activation | — |

Existing grade bands in the four populated groups do not establish that the new migration ran. The new per-regulation CIA schemes are absent everywhere.

## Reported error identifies the stop point

The user reported:

```text
#1644 - Set an active academic administrator @cea_regulation_actor_id and @cea_regulation_reason.
```

This is the migration's intentional audit-identity guard. It runs before START TRANSACTION and before persistent settings/curriculum/audit updates. The administrator variable was missing/invalid, the reason was missing/blank, or the values were set in a different database session. No persistent configuration writes occur in the failing CALL. This error does not establish a later SQL compatibility problem; the remaining procedure has not yet been validated by a successful production CALL.

## Historical retry and completed cleanup

The operator selected active superadmin users.id 541 as the migration audit owner. The corrected manual import placed executable actor/reason parameters and the complete migration bodies in one request, preserving the temporary catalog needed by the procedure. A standalone CALL in a later request would not preserve that catalog. The full script removed its helper after successful execution; the production verification above confirms that no helper remains.

The one-time manual import copies, import instructions, verification SQL, deployment inventories and temporary local scripts were removed with user authorization after successful production verification. Their prior validation results are retained below. No further retry, standalone CALL or helper deletion is required based on the received output.

The permanent source migrations remain unchanged:

- [20261015_001_regulation_setting_scope.sql](/Applications/MAMP/htdocs/classattendance.in/database/migrations/20261015_001_regulation_setting_scope.sql)
- [20261015_002_verified_regulation_policies.sql](/Applications/MAMP/htdocs/classattendance.in/database/migrations/20261015_002_verified_regulation_policies.sql)

Future deployment guidance remains in [the production runbook](production-regulations-2026-10-15.md). The completed verification checked the full approved CIA/grade matrices, supported PG maxima, audit snapshots, optional migration-ledger existence and helper-procedure absence. No student records or full users-table export were needed.

## Production import bundle validation

The exact `manual-import/20261016_production_regulation_update.sql` bundle passed **19 checks on MySQL 8.0.44** in a disposable schema containing only synthetic fixtures. Checks cover preservation of both original SQL bodies; applying all six complete setting matrices; the approved PG maxima repair; audit attribution to synthetic active superadmin 541 and the supplied reason; removal of the helper procedure; repeat execution without duplicate history; and unchanged contents of all six entered CIA/result/SEE tables. The first scope migration was already present for this test, matching the supplied production export.

The previous second-migration-only actor-541 copy also passed 13 checks. A broader regression rerun completed 78 results checks and 62 CIA checks, then stopped at the separate PHP configuration tool because the local docs/regulations folder no longer contained the required source PDFs. Neither SQL import reads those files. No source documents or source hash checks were bypassed or replaced.

These local synthetic checks were performed on MySQL, rather than MariaDB 11.8.9. The later operator-supplied production output at the beginning of this report confirms the intended database state on MariaDB 11.8.9. Production was not directly accessed or modified by this preparation.

## Verification database-selection correction

The operator reported `#1109 - Unknown table 'schema_migrations' in information_schema` while running the verification query. This identifies information_schema as the selected database for that unqualified table reference; it does not establish migration success or failure in the application database.

The initial verification correction began with `USE u182589698_jntuaceasarb` and reports the selected database. Its optional migration-ledger check queries only information_schema.TABLES for existence, because direct phpMyAdmin imports need not create schema_migrations. The remaining configuration, maxima, audit and helper checks are unchanged. The file makes no database data/schema changes and does not need the migration to be rerun.

Validation of this correction: the saved verification file was tested using a local-only in-memory database-name substitution, beginning from information_schema in an explicit read-only transaction. Explicit application-database selection and all eight queries completed successfully on the local MySQL server. No application data or schema was changed.

## Independent-query verification correction

A subsequent verification attempt reported `#1109 - Unknown table 'programs' in information_schema`, so the earlier session-level USE change was insufficient for how the operator ran the query. The final verification helper removed that dependency: all five application table names are qualified with u182589698_jntuaceasarb, and all metadata schema filters use that literal database name. No USE statement is required. Each SELECT/CTE is independent and read-only. The initial query reports both the current session database and the intended verification database.

All eight final queries completed on local MySQL in eight separate explicit read-only connections with information_schema selected throughout. Only an in-memory test copy substituted the local application database name. No production connection or data changes were performed. This supersedes the preceding session-selection fix. Do not rerun the migration solely to resolve this verification query error.

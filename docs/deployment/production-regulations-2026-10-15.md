# Production regulation migrations — 15 October 2026

These two SQL migrations are the production deliverable for the CIA/results follow-up. They have been executed against a schema-only copy with synthetic fixtures, including deliberate failures. The working local database and production have not been changed.

## Confirmed production engine

Production was reported as **MariaDB 11.8.9-MariaDB-log / MariaDB Server** on 16 October 2026. Its documented features cover JSON_TABLE (10.6+), JSON_ARRAYAGG (10.5+), indexed STORED generated columns and atomic ALTER. The current SQL is therefore expected to be compatible at the feature/syntax level. Exact-engine execution remains unverified: the completed regression runs used MySQL 8.0.44. Validate both complete SQL files, rollback and replay on an isolated restored MariaDB 11.8.9 database before production deployment. No SQL changes or production database changes were made for this documentation review.

Sources: [JSON_TABLE](https://mariadb.com/docs/server/reference/sql-functions/special-functions/json-functions/json_table), [JSON_ARRAYAGG](https://mariadb.com/docs/server/reference/sql-functions/aggregate-functions/json_arrayagg), [generated columns](https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/generated-columns), [atomic ALTER](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table).

## Files and order

1. [`20261015_001_regulation_setting_scope.sql`](../../database/migrations/20261015_001_regulation_setting_scope.sql): replaces the label-only settings constraint with regulation-ID scope; preserves legacy settings without a regulation ID. No setting values change. Its atomic ALTER safely replays after an interrupted migration-ledger write.
2. [`20261015_002_verified_regulation_policies.sql`](../../database/migrations/20261015_002_verified_regulation_policies.sql): installs the six reviewed CIA/grade configurations, repairs confirmed PG curriculum maxima, and records before/after snapshots with operator, authority and source PDF hashes. Its persistent data writes are one transaction and safely replay without duplicate history.

Program/regulation IDs are resolved from the actual database. No development IDs are embedded. The second migration requires exactly one mapping for each of MCA R20, M.Tech R21/R25 and B.Tech R20/R23/R26. Incomplete or ambiguous mappings fail before data updates. The shipped schema has mixed text collations; the SQL explicitly handles those comparisons.

On the inspected local database, the maxima repair affects 36 MCA R20 and 210 M.Tech R21 curriculum rows. Production reports its actual count. Only PG theory/PE/OE/lab rows having the confirmed legacy 30/70 pair change to 40/60. Already-correct 40/60 rows remain unchanged. An unexpected/null pair stops the whole data migration for review. Projects, audit/skill/integrated/co-curricular courses and attainment overrides are excluded from generic correction.

The SQL does not change entered marks, examination results, publication states, uploaded documents or SEE evidence. Existing lab marks are not automatically divided between the new record and other components.

## Apply through the repository runner

Use a dedicated migration connection and the repository's verified-backup workflow. `tools/migrate.php` requires the backup file plus its adjacent successful isolated-restore `database-test.json` manifest when applying to the configured working database.

Preview pending files:

```sh
php tools/migrate.php
```

Apply after substituting the real backup path, active academic administrator's **users.id**, and authority:

```sh
php tools/migrate.php --apply \
  --backup=/absolute/path/to/verified/database.sql \
  --actor=ACTUAL_ADMIN_USER_ID \
  --reason="Reviewed regulation PDFs and approved CIA decisions, 15 October 2026"
```

The actor must be active and have role `admin`, `superadmin` or `academic_section`. A faculty ID or database login name is not a substitute. Missing/invalid actor or blank reason stops the data migration. The runner supports MySQL `DELIMITER` blocks and drains procedure result sets before updating the checksum ledger.

## Apply directly through a MySQL client

Use the same verified backup and a dedicated connection with ALTER/CREATE ROUTINE/EXECUTE/DROP ROUTINE and relevant table privileges. The files use JSON functions supported by MySQL 8.0 and MariaDB 11.8, plus MySQL/MariaDB-client `DELIMITER` directives. An importer that splits every semicolon cannot run the second file correctly.

In that connection, replace the operator placeholder and set the authority, then source both files in order:

```sql
SET @cea_regulation_actor_id = ACTUAL_ADMIN_USER_ID;
SET @cea_regulation_reason = 'Reviewed regulation PDFs and approved CIA decisions, 15 October 2026';
SOURCE /absolute/path/to/20261015_001_regulation_setting_scope.sql;
SOURCE /absolute/path/to/20261015_002_verified_regulation_policies.sql;
```

A successful direct run reports `configured_regulations` and `repaired_curriculum_maxima`, then removes its helper procedure and temporary tables. Direct MySQL execution does not populate the repository migration ledger; use the repository runner afterward to reconcile the ledger safely. The SQL's replay guards prevent a second value change or duplicate history.

## phpMyAdmin import parameters

The example SET statements in the versioned migration header are comments. For a direct phpMyAdmin import, prepend executable SET statements containing the real active application administrator users.id and a nonblank reason to a separate copy of the full second SQL file. Import that combined file in one request. Setting variables in a previous phpMyAdmin request may not preserve them for the import. Retain the original versioned migration file/checksum.

A #1644 error naming @cea_regulation_actor_id/@cea_regulation_reason means execution stopped at the audit-identity check before its persistent data updates. Do not retry only CALL in a new session: the full migration's temporary catalog must be recreated too. The full file recreates the helper procedure and normally drops it after success.

Detailed review of the supplied production exports and retry instructions: [production verification report](production-schema-verification-2026-10-16.md).

## Confirm completion

The following read-only checks should show the new unique index and six exact regulation-ID configurations:

```sql
SHOW INDEX FROM academic_settings WHERE Key_name='uk_regulation_scope_setting';
SELECT p.prog_shortname,r.id,r.regulation,a.setting_key,a.setting_value
FROM academic_settings a
JOIN regulations r ON r.id=a.reg_id
JOIN programs p ON p.id=r.prog_id
WHERE a.setting_key IN('cia_scheme_json','grade_bands_json')
ORDER BY p.prog_shortname,r.regulation,a.setting_key;
SELECT entity_id,actor_user_id,reason,created_at
FROM academic_record_snapshots
WHERE entity_type='REGULATION_CONFIG'
ORDER BY id DESC;
```

Open Examination Results → Course policy readiness. Existing attainment/subject overrides may supersede regulation values; any flagged conflict still requires review. Complete missing curriculum links, mark entry and nonstandard-course rules before relying on totals or publishing a representative college results file.

## Failure and recovery

Data errors roll back all settings, maxima and snapshots in the second file. The schema migration is separate atomic DDL and may already have completed. Fix the reported mapping/maxima/identity/audit problem and replay; the ledger records a file only after success. The second SQL file drops/recreates its helper routine on replay, including after a failed CALL.

Retain the verified pre-migration backup and new audit snapshots. To revert the entire release, restore schema and data together from that backup. Recreating the old label-only index while distinct B.Tech R20/MCA R20 settings exist would fail; reverting that index alone is not a valid rollback.

## Verification coverage

The exact SQL files and runner are exercised by `tests/production_regulation_sql.php`. Coverage includes missing/invalid operator, blank authority, incomplete program mapping, unexpected maxima, forced audit failure and complete rollback, every reviewed setting/grade matrix, fractional MCA points, program-specific R20 weights, source-stamped history, safe replay with/without a migration-ledger row, helper cleanup, and preservation of every entered marks/results/SEE row.

For an isolated run:

```sh
php tools/prepare_results_test.php
php tests/production_regulation_sql.php --database=cea_results_test_<12 hex>
php tests/results_portal_regression.php --database=cea_results_test_<12 hex> --student
php tools/sync_core.php --check
```

The tests invoke the baseline results, CIA and verified-configuration suites and reject the configured working database. Remove the disposable database after verification.

The approved formulas, original defects with examples and remaining gaps are in [the implementation report](../cia-policy-and-results-corrections-2026-10-15.md).

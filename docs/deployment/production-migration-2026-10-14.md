# Production database upgrade — October 14, 2026

Import **`database/migrations/archive/migration_production_complete_20261014.sql`** into
the existing application database. It upgrades the schema represented by
`u182589698_jntuaceasarb_server_131026.sql`, including databases on which some of
the earlier migrations have already run.

## Import

1. Export a fresh full database backup including application data.
2. Pause application writes during the import.
3. In phpMyAdmin, select the existing application database, open **Import**, and
   choose `database/migrations/archive/migration_production_complete_20261014.sql`. Keep the original file's
   contents intact. Alternatively use the MySQL client with the database selected.
4. Stop on any SQL error. Success is indicated by
   `COMPLETE: production upgrade 20261014`, followed by the migration ledger
   containing the six covered baseline/runner versions. Existing other versions
   are retained.
5. Resume application writes after a successful import.

Use this consolidated file instead of importing the older files again. Do not
import a local schema dump (`database/dumps/u182589698_jntuaceasarb_local_db_scheme_.sql`) over the populated production database. The supplied
October 13 schema export omits operational data from several tables, so it is
not a substitute for the fresh full backup in step 1.

The file targets **MySQL 8.0+ or MariaDB 10.4+**. It needs normal table/data/index
permissions and permission to create temporary tables. It does not need stored
routine privileges or custom `DELIMITER` handling. It contains no credentials,
`USE`, database deletion, academic-row deletion, or disabled foreign-key checks.

## Covered changes

| Original source | Consolidated behavior |
| --- | --- |
| `migrations/2026_10_05_curriculum_co_matrix_dual_sync.sql` | Add missing CO curriculum links; backfill master/sibling COs and mappings; correct `idx_co_curr_num` to `(curr_sub_id, co_number)`. |
| `database/migrations/archive/migration_accreditation_071026.sql` | Accreditation modules, per-regulation defaults, lab rubrics and CQI tables. |
| `database/migrations/archive/20261008_001_trusted_core.sql` | Permissions, review/audit history, report snapshots, policies and AI job infrastructure. |
| `database/migrations/archive/20261008_002_document_lifecycle.sql` | Retained document deletion metadata and certificate verification tokens. |
| `database/migrations/archive/20261008_003_attainment_integrations.sql` | Attainment policies/imports, raw marks, higher precision, AI providers and job metadata. |
| `database/migrations/archive/20261008_004_ai_queue_guards.sql` | Queue ownership/retry/review fields and provider version. |
| `migrations/2026_10_09_exam_results_scheme_sync_and_see_attainment.sql` | Malpractice status, result indexes, 60-character SEE evidence basis and result module description. |
| `database/migrations/archive/20261013_001_faculty_sort_order.sql` | Faculty ordering and its department index. Existing nonzero orders survive; unranked members append after the current maximum. |
| `database/migrations/archive/migration_production_server_sync_20261008.sql` | Its accreditation/integration schema changes are included. Existing module switches and rubric definitions are retained; unrelated building seeds are omitted. |

The October 6 migrations' regulation fields, category/type tables, and R25/R26
seed records are already represented in the supplied server baseline. The
consolidated file checks for the relevant baseline tables/fields and does not
repeat the older scripts' deletes and regulation resets. It is not an installer
for an empty database or a database predating that baseline.

## Compatibility and recovery

- Both the repaired October 9 file and the consolidated file use block comments,
  which do not consume a following statement when an importer joins lines.
- Metadata checks and prepared DDL replace `CREATE INDEX IF NOT EXISTS`,
  `ADD INDEX IF NOT EXISTS`, and `ADD COLUMN IF NOT EXISTS` extensions that are
  incompatible with MySQL. Existing same-named incorrect nonunique indexes are
  repaired. An equivalent UNIQUE index is retained; a conflicting custom UNIQUE
  constraint is reported instead of silently dropping its integrity rule.
- Numeric conversions retain the greater of the existing and required integer
  capacity and fractional precision. For example, an existing `DECIMAL(20,3)`
  becomes `DECIMAL(22,5)`, rather than being narrowed to `DECIMAL(12,5)`. Grade
  points converted from an integer may similarly be wider than `DECIMAL(8,3)`.
  A combination exceeding the engine's decimal limits fails without narrowing.
- Existing feature enablement/visibility, academic setting values, custom rubric
  IDs/values, and nonzero faculty orders are preserved. New defaults are scoped
  by regulation ID, including regulations sharing the R20 code.
- Migration checksums are checked before application-table changes. The six
  immutable baseline/runner entries are recorded after the included changes
  succeed. Existing checksum values are never overwritten. Original files in
  `database/migrations/` and `database/baselines/` remain unchanged.
- The final completeness check verifies that every one of the local schema's
  821 columns across 90 tables exists before recording those versions or printing
  success. A partially existing table with an unexpected missing column is
  reported for repair rather than being silently accepted by `CREATE TABLE IF
  NOT EXISTS`.
- DDL implicitly commits. An interrupted import can leave completed schema
  changes in place; reconnect and import the same consolidated file again after
  resolving the reported cause. A second successful import adds no duplicate
  COs, mappings, rubric defaults, columns, indexes or ledger versions.
- Duplicate `(uglab_mark_id, criterion_name)` evidence is reported by the new
  unique index. Both rows are retained for review; this migration never silently
  deletes or combines student marks. A checksum conflict similarly requires
  investigation of the recorded version; do not overwrite its checksum.
- Duplicate legacy master COs sharing `(curr_sub_id, co_number)` are detected
  before application-table changes. Both definitions remain available for review;
  the migration does not arbitrarily pick or delete either definition.
- Errors naming `cea_error_*` are intentional preflight failures identifying an
  absent baseline table/column or conflicting migration checksum. Do not use an
  importer option that ignores errors and continues.

## Verification

Integration checks are in `tests/test_production_migration_20261014.py`. They
require an explicitly supplied disposable server socket and do not read the
application's database configuration. They create and remove uniquely named test
databases. The fixture restore alone disables FK checking because the supplied
schema export includes reference seed rows without their operational parent data;
the migration itself runs with foreign-key checks enabled.

The expanded audit covers fresh baseline import, repeat import under strict/ANSI
SQL modes, partial older migration recovery, interrupted consolidated import
recovery, standalone October 9 replay, invalid baseline rejection, checksum
conflict rejection, retention of duplicate evidence, decimal widening and engine
capacity limits, duplicate master CO rejection, preservation of stronger/custom
unique constraints and extra result statuses, the fully applied older consolidated
route, incomplete-schema rejection, and importing with all SQL lines flattened.
The schema comparison checks column types, minimum numeric/string capacities,
defaults, nullability, extra attributes, indexes and foreign-key column mappings
against a separately restored local-schema reference.

Re-review result: **18 checks passed on MariaDB 10.4.27**, including the complete
90-table/821-column reference comparison. All six immutable migration/baseline
file checksums remain unchanged. The application database was not accessed.

Verified consolidated file SHA-256:
`c9315d0e6929678758cfa8edb8ab943316932010bd1011e7d06942f9e3c64994`.

Runtime validation uses the available MariaDB 10.4.27 server. MySQL compatibility
was checked against its documented DDL/prepared-statement syntax; a MySQL server
was not available locally for runtime verification.

Compatibility references: MySQL's [CREATE INDEX syntax](https://dev.mysql.com/doc/refman/8.0/en/create-index.html),
[prepared statements](https://dev.mysql.com/doc/refman/8.4/en/prepare.html), and
[INSERT SELECT restrictions](https://dev.mysql.com/doc/refman/8.0/en/insert-select.html).
Decimal capacity references: [MySQL numeric type syntax](https://dev.mysql.com/doc/refman/8.0/en/numeric-type-syntax.html)
and [MariaDB DECIMAL](https://mariadb.com/docs/server/reference/data-types/numeric-data-types/decimal).

Current extension: [attainment and optional AI release 2026.10.08.2](attainment-ai-release-2026-10-08.md). The document below records the preceding foundation release.

# Standardisation release 2026.10.08.1: validation and recovery

This release is applied to the local shared database configured by the workspace `.env.php`. It has not been published to a remote production host. Previously disabled modules remain disabled. Do not enable results, certificates, lesson plans or promotion solely because their code is present; use the acceptance requirements in the workspace implementation-status document.

## Preserved baseline and migration evidence

Private original source archive and database backup:

`/private/tmp/cea-standardisation-baseline-20261008/`

- `source.tar.gz`: original source/configuration, including pre-existing user edits; excludes uploaded documents, vendor packages, logs and Git metadata.
- `manifest.json`: original source hashes.
- `database.sql.gz`: consistent database dump, including triggers/routines, restored to an isolated test database.
- `database-test.json`: restore database identity, backup path and SHA-256.
- `reconciliation.json`: read-only historical-data findings.

Fresh backup immediately preceding schema application:

`/private/tmp/cea-standardisation-preapply-20261008/database.sql.gz`

It was restored to `cea_standardisation_test_856d6794`. Its restore manifest is alongside the backup. The migration tool requires that manifest's backup path and checksum before applying to the configured working database.

These are private development backups in temporary storage, not an operational backup-retention service. Copy them to the college's approved persistent backup location before relying on them for deployment recovery. Uploaded documents require a separate protected backup.

The isolated test databases contain deliberate test fixtures. They are not authoritative academic records, do not drive either normal portal, and must not be copied back over the working database.

## Schema and source versions

- `database/baselines/20261007_accreditation.sql` preserves the supplied recent migration. The baseline tool verifies expected existing tables/module keys/settings and records its checksum without rerunning seeds or overwriting college settings.
- `database/migrations/20261008_001_trusted_core.sql` adds permission, review, snapshot, policy-version, sequence and inactive AI infrastructure; extends results/CQI/course-review metadata and rubric uniqueness.
- `database/migrations/20261008_002_document_lifecycle.sql` adds retained document-deletion metadata and opaque certificate verification tokens.
- `schema_migrations` records applied version/checksum. MariaDB DDL implicitly commits; statements are replay-safe so an interrupted migration can be resumed after diagnosis. There is no blanket down-migration that deletes evidence tables.
- `tools/sync_core.php` distributes the staff portal's twelve common PHP core files to the student portal. Both copies carry release metadata. Modify the staff source, then synchronise and check parity.
- The source manifest is `/private/tmp/cea-standardisation-release-20261008.json`. It includes both portals' source, migration scripts and relevant protection/release files while excluding credentials, uploaded content, logs, vendor code and Git metadata.

Use the bundled PHP executable at `/Applications/XAMPP/xamppfiles/bin/php`. From the staff portal directory:

```text
php tools/migrate.php
php tools/baseline.php
php tools/sync_core.php --check
php tools/release_manifest.php --verify=/private/tmp/cea-standardisation-release-20261008.json
```

For another approved deployment, first make and successfully restore a fresh backup and create its corresponding verified restore manifest. Inspect pending migrations, then supply `--apply --backup=/approved/path/database.sql.gz`. Never apply the archived baseline seed file to an already populated deployment merely to record its version.

## Reproducible focused tests

Tests require a separately restored database whose name begins `cea_standardisation_test_` and differs from configured `DB_NAME`. They deliberately modify only that disposable database.

```text
php tools/migrate.php --database=cea_standardisation_test_EXAMPLE --apply
php tools/baseline.php --database=cea_standardisation_test_EXAMPLE --apply
php tests/academic_workflows.php --database=cea_standardisation_test_EXAMPLE
php tests/certificate_concurrency.php --database=cea_standardisation_test_EXAMPLE
```

The workflow suite includes the trusted-core checks. At handover it passed 98 combined checks; concurrency produced twelve distinct numbers. Forced trigger failures prove transaction rollback, not just successful execution. The supplied local audit dataset is required for representative fixtures; this is not a generic empty-database seeder.

Thirty-three page/request/PDF checks were also run against a loopback test server with isolated DB connections and controlled test sessions. The helper was kept outside the application and the test server was stopped after validation. Real credential login, deployed verification keys, browser interaction and departmental user acceptance remain separate checks.

Eleven direct checks of local Apache confirmed both sign-in pages load and configuration, SQL, Git metadata, internal core/tools and retained upload paths are denied. Preserve the workspace/root and child `.htaccess` files; equivalent rules must be configured explicitly if deploying behind a different web server.

## Private documents and later AI

Set `COLLEGE_DOCUMENT_ROOT` in the private environment configuration to the approved persistent directory outside the complete web document root. Grant the actual PHP service account access, test uploads/downloads/backup restoration, and migrate retained legacy files with a reviewed manifest. The path is deliberately not guessed. New uploads report a configuration requirement until it is set. Legacy files are streamed through ownership-checked endpoints and their direct web paths are denied.

QR generation is inactive pending a local renderer; no external QR service receives certificate links. Random-token public verification is implemented and returns limited credential information. Certificate templates, custody transitions and the private file lifecycle require further acceptance before enabling those modules.

AI consent/job tables are inactive infrastructure only. No model download, inference service, worker, opt-in screen, model evaluation or recurring job was created. Future processing must consume approved snapshots from an isolated restored analysis copy and produce reviewed drafts.

## Recovery procedure

1. Stop writes on the affected deployment and preserve its current database/source/documents before taking recovery action.
2. Diagnose the failed operation. A failed academic workflow should roll back its data and database audit together; investigate the logged error and original snapshots rather than manually deleting evidence.
3. For an interrupted additive migration, inspect the actual schema and migration ledger; resume the unchanged replay-safe migration after resolving the cause.
4. For a release rollback, restore the matching preserved source into a staging location, including pre-existing user edits and the environment configuration. Review new files separately; do not reset unrelated user work or replace vendor/document directories from an archive that excludes them.
5. Restore the selected database backup into a separate recovery database and verify schema, counts, permissions, login and representative academic reports. Reconcile any legitimate writes since the backup. Only then switch the deployment to the accepted source/database pair during an agreed maintenance window.
6. Preserve snapshots/audit and post-backup work. Never overwrite the live database with a test database or restore an old dump merely because a screen fails.

No Git commit, remote push, student-portal Git initialisation or source reset was performed. Existing local changes were retained. Repository ownership, dependency locking, CI, operational monitoring and a college-run production recovery drill remain outstanding.

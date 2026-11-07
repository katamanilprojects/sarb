# Maintenance & Operations Guide

This document covers recurring maintenance routines, database backup strategies, CLI operational tools (`tools/`), log management, forensic audit retention, and password migration procedures.

---

## 1. Database Backup & Disaster Recovery

Because attendance, internal/external assessment marks, and certificate ledgers constitute official statutory records, regular backups of both the MariaDB/MySQL database and the private student document repository (`COLLEGE_DOCUMENT_ROOT`) are mandatory.

### 1.1 Automated Nightly Backup Script
Create a scheduled cron task to dump the database and archive private documents:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/attendance_db"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_NAME="u182589698_jntuaceasarb"
DB_USER="u182589698_jntuaceasarb"

mkdir -p "$BACKUP_DIR"

# Perform compressed dump including routines and triggers
mysqldump --single-transaction --routines --triggers -u "$DB_USER" -p'YourPassword' "$DB_NAME" | gzip > "$BACKUP_DIR/backup_${DB_NAME}_${TIMESTAMP}.sql.gz"

# Retain backups for 30 days
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +30 -delete
```

---

## 2. CLI Operational & Verification Tools (`tools/`)

All CLI scripts inside `tools/` deny HTTP web requests (`404`) and must be executed from the command line in the repository root (`/Applications/MAMP/htdocs/classattendance.in`):

1. **Shared Core Parity Synchronization (`tools/sync_core.php`)**:
   - Synchronizes the 29 shared core classes from `jntuacea/core/` to `jntuaceastudents/core/` and updates `core/release.json`:
     ```bash
     php tools/sync_core.php
     php tools/sync_core.php --check
     ```
2. **Migration & Baseline Verification (`tools/migrate.php`, `tools/baseline.php`)**:
   - Inspects or applies additive SQL migrations tracked in `schema_migrations` with immutable SHA-256 checksums:
     ```bash
     php tools/migrate.php
     php tools/baseline.php
     ```
3. **Read-Only Historical Data Reconciliation (`tools/reconcile.php`)**:
   - Outputs a read-only JSON report identifying unlinked `subjects.curr_sub_id` candidates, missing `classes.batch_id` assignments, and shared-faculty diary periods without modifying records:
     ```bash
     php tools/reconcile.php
     ```
4. **Release Manifest Verification (`tools/release_manifest.php`)**:
   - Generates or verifies SHA-256 source integrity manifests across both portals:
     ```bash
     php tools/release_manifest.php --verify=/path/to/manifest.json
     ```
5. **Disposable Test Database Provisioning (`tools/prepare_results_test.php`)**:
   - Clones a schema-only disposable database (`cea_results_test_<12 hex>`) with zero application records for running `tests/results_portal_regression.php`:
     ```bash
     php tools/prepare_results_test.php
     ```
6. **Bounded Optional AI Queue Worker (`tools/ai_worker.php`)**:
   - Processes up to `N` queued opt-in AI draft jobs (`ai_jobs`) serialized per database via `GET_LOCK`:
     ```bash
     php tools/ai_worker.php --limit=10
     ```
7. **AI Pipeline Background Workers (`ai_pipeline/`)**:
   - Runs health checks and executes background qualitative feedback sentiment analysis workers storing draft reports in `ai_analysis_reports`:
     ```bash
     python ai_pipeline/tools/health_check.py
     python ai_pipeline/workers/analyze_feedback.py
     ```

---

## 3. Password Hash Migration (MD5 to BCrypt)

The system automatically upgrades passwords from legacy MD5 to BCrypt as users log in.

### 3.1 How the Automatic Migration Operates
In `User::authenticate()` (`user.class.php`):
1. User enters plain password $P$.
2. The database stores hash $H$.
3. If $H$ does not begin with `$2y$` (BCrypt salt marker) and `md5($P) === $H`:
   - Authentication succeeds.
   - `password_hash($P, PASSWORD_BCRYPT)` computes a new secure hash.
   - The user record is updated immediately (`UPDATE users SET password = ? WHERE id = ?`).
   - A log entry is appended to `logs/activity_*.log`.

### 3.2 Auditing Migration Progress
To inspect how many user accounts have completed migration to BCrypt:

```sql
SELECT 
    COUNT(*) AS total_users,
    SUM(CASE WHEN password LIKE '$2y$%' THEN 1 ELSE 0 END) AS bcrypt_upgraded,
    SUM(CASE WHEN password NOT LIKE '$2y$%' THEN 1 ELSE 0 END) AS legacy_md5
FROM users;
```

---

## 4. Log Rotation, Snapshots & Forensic Audit Retention

### 4.1 Flat-File Logs (`jntuacea/logs/` & `jntuaceastudents/logs/`)
The `Logs` class writes logs partitioned by date:
- `logs/activity_YYYY-MM-DD.log`
- `logs/error_YYYY-MM-DD.log`

Set up a logrotate policy or cron job to prune flat-file logs older than 60 days:
```bash
find /Applications/MAMP/htdocs/classattendance.in/jntuacea/logs/ -name "*.log" -mtime +60 -delete
find /Applications/MAMP/htdocs/classattendance.in/jntuaceastudents/logs/ -name "*.log" -mtime +60 -delete
```

### 4.2 Database Forensic Audit & Snapshot Tables
For regulatory auditing and compliance retention, never truncate or drop evidence tables:
- **`audit_logs`**: Primary unified security and statutory governance audit trail capturing authenticated user ID (`users.id`), username, role, action, entity type/ID, client IP address, user agent, and timestamp.
- **`academic_record_snapshots`**: Atomic before/after JSON snapshots for 7-day student attendance corrections, HOD attendance deletion approvals, SEE blueprint revisions, and SEE result replacements.
- **`workflow_review_history`**: Immutable state transition history across course completion audits, CQI actions, attendance deletion requests, and examination notifications.
- **`report_snapshots`**: Reproducible report metric and policy SHA-256 hashes (`cqi_atr`, `external_co_attainment`).
- **`academic_settings_audit` & `academic_policy_versions`**: Immutable log of academic policy parameter mutations and regulation-wide SHA-256 policy versions.
- **`course_completion_audits` & `course_cqi_actions`**: Formal NBA Criterion 2.2 course delivery records and Criterion 3 closed-loop CQI action records.
- **`activity_logs` & `fac_activity_logs`**: Legacy operational log tables.

---

## 5. Academic Term Rollover Checklist

At the beginning of a new academic semester:
1. **SuperAdmin**:
   - Register incoming graduation cohorts in `superadminbatches.php` (e.g., 2025–2029).
   - Configure master Vision, Mission, PEOs, and PO-PEO articulation matrices in `superadminbatchobe.php`.
   - Create/activate the new academic year entry in `superadminacademicyears.php`.
   - Review regulation policy parameters in `superadminacademicsettings.php` and attainment profiles in `superadminattainmentsettings.php`.
   - Review module visibility and role action permissions (`view`, `edit`, `approve`, `export`) in `superadminfeatures.php`.
   - Generate new class sections in `superadminclasses.php` and bind them to their respective cohort batch (`batch_id`).
   - Update class timing schedules in `superadminclasstimings.php` if semester bell timings change.
2. **Academic Section / Exam Cell**:
   - Maintain master syllabus curriculum courses and master COs in `academicsectioncurriculumsubjects.php`.
   - Create examination notifications, preview/import CSV results, audit CIA discrepancies, review (`REVIEWED`), and publish (`PUBLISHED`) results in `academicsectionresults.php`.
   - Verify incoming student biographical profiles and private Document Vault scans in `adminstudentprofiles.php`.
   - Record physical certificate intake and process certificate requests in `admincustodyledger.php`.
3. **Admin (Principal)**:
   - Enroll incoming cohorts into classes (`adminenrollstudents.php`, `adminuploadst.php`).
   - Create faculty accounts and configure department-wise faculty display order (`faculties.sort_order`) in `adminfac.php`.
4. **HOD**:
   - Assign faculty to subjects (`hodmapfaculty.php`) and map elective/lab batches to students (`hodmapstudents.php`).
   - Build conflict-checked weekly timetables (`hodmanage_timetable.php`).
   - Review submitted course completion audits and close verified CQI actions in `reviewqueue.php`.
5. **Faculty**:
   - Formulate or verify Course Outcomes (`facaddcos.php`) and CO-PO matrices (`facarticulationmatrix.php`).
   - Upload lecture lesson plans via CSV or form in `facaddlessonplan.php`.
   - Conduct classes with frictionless topic entry in `facaddattendance.php`.
   - Enter CIA/SEE marks (`facaddciamarks.php`, `facaddcialabmarks.php`, `facseemarks.php`) or import external attainment CSV evidence (`attainmentimport.php`).
   - At semester conclusion, execute end-of-course reconciliation, submit compliance audits (`faclessonplanreconciliation.php`), and record CQI execution evidence (`reviewqueue.php`).

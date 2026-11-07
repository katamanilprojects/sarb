# System Architecture Overview

This document describes the architectural layout, runtime environment, shared trusted core, authentication & request security mechanisms, and forensic logging infrastructure of the JNTUACEA Class Attendance, CIA Marks, OBE & Institutional Governance System (`classattendance.in`).

---

## 1. Technical Stack & Unified Repository Layout

- **Runtime**: PHP 8.0+ / PHP 8.3 (strict types enabled across core and service modules)
- **Web Server**: Apache 2.4+ (hosted via MAMP / XAMPP / LAMP stack with `.htaccess` hardening)
- **Database**: MariaDB 10.4+ / MySQL 8.0+ (`utf8mb4` charset, 92 tables across 12 domains, 839 columns)
- **Frontend**: Server-rendered HTML5, Bootstrap 4/5, jQuery, DataTables, FontAwesome, Bootstrap Icons, Chart.js
- **PDF & Spreadsheet Engines**: TCPDF (`services/*PDFService.php`), FPDI, mPDF, PhpSpreadsheet (`services/FeedbackExcelService.php`)
- **Configuration Mechanism**: PHP constant definitions in root `.env.php` (templated via `.env.example`)

### Unified Repository Structure
```text
classattendance.in/
├── .env.example              # Environment configuration template
├── .env.php                  # Local/production environment credentials (git-ignored)
├── .htaccess                 # Root Apache access hardening rules
├── ai_pipeline/              # Background AI/ML analytics, risk prediction pipeline & workers
│   ├── api/                  # High-performance risk assessment API (`risk_service.php`)
│   ├── database/             # Pipeline analytics schema & notes (`migration_ai_analytics.sql`)
│   ├── tests/                # Automated pipeline verification & unit tests
│   └── worker/               # Nightly/hourly batch predictors (`attendance_batch.php`, `runtime.py`)
├── database/                 # Schema dumps, baselines, seeds, and migrations
│   ├── baselines/
│   ├── dumps/
│   ├── migrations/
│   │   └── archive/          # Consolidated and historical SQL migrations
│   └── seeds/
├── docs/                     # Complete technical, database, workflow & deployment docs
├── jntuacea/                 # Staff, Faculty, HOD, Academic Section, Admin & SuperAdmin Portal
│   ├── core/                 # 29 Shared Trusted Core classes (namespace College)
│   ├── services/             # 21 Enterprise domain & PDF/Excel/AI service classes
│   ├── views/                # Modular UI view partials and readiness consoles
│   └── *.class.php / *.php   # Domain models and page controllers
├── jntuaceastudents/         # Student Self-Service Portal
│   ├── core/                 # Synchronized copy of 29 Shared Trusted Core classes
│   ├── services/             # 8 Student-scoped domain and assessment services
│   └── *.class.php / *.php   # Student models and page controllers
├── tests/                    # Automated workflow, core, attainment, AI & migration test suites
└── tools/                    # CLI maintenance, migration, core-sync, reconciliation & AI worker scripts
```

---

## 2. Architectural Pattern

The application follows a **Layered Page-Controller / Domain-Service / Shared Trusted Core** architectural pattern:

1. **Page Controllers & Views (`jntuacea/*.php`, `jntuacea/views/` & `jntuaceastudents/*.php`)**:
   - Serve as entry points for HTTP requests across all roles (e.g., `facaddattendance.php`, `adminenrollstudents.php`, `hodmanage_timetable.php`, `attainmentimport.php`, `reviewqueue.php`, `aiassistance.php`, `cia_policy.php`, `pg_cia_form.php`, `facbulkd2dmarks.php`, `hodresults.php`, `studentresults.php`, `studentsubatt.php`, `studentcourseatt.php`).
   - Manage request parsing, session verification, `FeatureManager` module/action guards, and server-rendered HTML rendering with modular view partials (`results_readiness.php`, `results_import_preview.php`, `results_import_history.php`, `academic_regulations_hub.php`, `manage_academic_settings.php`, `partials/faculty_remarks_card.php`).
   - Include role-specific layout fragments (`header.php`, `facmenu.php` / `hodmenu.php` / `adminmenu.php` / `academicsectionmenu.php` / `superadminmenu.php` / `studentmenu.php`, `footer.php`).

2. **Shared Trusted Core (`jntuacea/core/` & `jntuaceastudents/core/` — `namespace College`)**:
   - Twenty-nine strict-typed foundational classes synchronized across both portals via `php tools/sync_core.php` and verified by `core/release.json`:
     - **Infrastructure & Security (5 classes)**: `Connection` (single shared `mysqli` connection per request with CLI test override), `Clock` (`Asia/Kolkata` institutional time), `DateRange` (parameter-bound `YYYY-MM-DD` date range validation), `RequestSecurity` (automatic CSRF token injection/verification, same-origin checks, and resource scope guards), `RecordScope` (ownership assertions for subjects, classes, students, documents, certificates, and curriculum).
     - **Attendance Engine (3 classes)**: `AttendanceCalculator` (unrounded regulatory standing classification: `ELIGIBLE`, `CONDONATION`, `DETAINED`, `PROVISIONAL`, `POLICY_REVIEW`), `AttendanceService` (unified attendance counting with joining-date, mapped-subject, permission, and missing-period checks), `AttendanceCorrection` (atomic 7-day student status corrections and row-locked HOD deletion reviews with before/after snapshots).
     - **Assessment, CIA & Regulations Engine (8 classes)**: `AssessmentPolicy` (exact regulation ID policy lookup and SHA-256 policy versioning), `VerifiedRegulations` (statutory regulation rules and PDF source hashes for MCA R20, M.Tech R21, M.Tech R25, B.Tech R20/R23/R26), `CiaCalculator` (strict per-course and per-student CIA policy evaluation with zero-score preservation and complete-cohort enforcement), `CiaPolicySettings` (administrative regulation CIA scheme editor with SHA-256 state tracking), `CiaService` (scoped class and subject CIA loader), `PgCiaService` (separate postgraduate assessment component identities: mids 1/2, assignment 3, continuous evaluation 4, lab internal 11, record 12), `MarksNormalizer` (gross-to-net scaling, choice-group evaluation, equal multi-CO sharing, and grade-band midpoint conversion), `AttainmentCalculator` (choice-aware, equal-share multi-CO direct attainment with separate measured, estimated, and missing student counts).
     - **Examination Results & Ingestion Engine (7 classes)**: `ResultsPolicy` (course-specific assessment and grade-band resolution), `ResultRow` (row-level normalization of grades, points, credits, and sentinels), `ResultsCsv` (atomic CSV validator supporting `REGULAR`, `SUPPLEMENTARY`, and `REVALUATION` notifications), `ResultVersions` (canonical result set generation, duplicate prevention, and incremental merge vs complete replacement diffing), `ResultsReadiness` (pre-publication audit across curriculum links, class dates, CIA formulas, and grading profiles), `ResultsSeeSync` (transactional regular SEE evidence synchronization and withdrawal staleness tracking), `PublishedResults` (student-scoped published result and attempt GPA reader).
     - **Catalog, Timetables, Documents & AI (6 classes)**: `SubjectCatalog` (curriculum linkage lookup), `TimetableWriter` (mutex-locked timetable slot writer with class, room, and faculty conflict checks, supporting parallel electives/labs and shared open electives), `DocumentStorage` (private document repository outside web root with legacy fallback), `CertificateNumber` (atomic per-prefix sequence generator), `ReportValue` (provisional/unavailable metric value wrapper), and `AIProvider` (bounded HTTP adapter for local Ollama and OpenAI-compatible Chat Completions endpoints).

3. **Domain Service Models (`*.class.php` & `services/`)**:
   - Encapsulate business logic, transaction handling, before/after snapshots, and validations.
   - Core administrative models: `User`, `Admin`, `Faculty`, `HOD`, `AcademicSection`, `SuperAdmin`, `AdminUser`, `Departments`, `Programs`, `AcademicYears`, `Regulations`.
   - Course and offering models: `Subject` (offerings, elective groups, batch divisions), `CurriculumSubject` (master catalog), `Syllabus`.
   - Assessment & OBE models: `CIA`, `CIAMarks`, `CIADataExchange`, `AssessmentStructure`, `COAttainment`, `CourseOutcome`, `SEEAssessment`, `LearningAnalytics`, `Timetable`, `AttendanceRules`, `FacCIAAnalysis2`, `FeedbackService`.
   - Enterprise accreditation, governance & AI services (`jntuacea/services/`):
     - `FeatureManager`: Dynamic **28-module** system gatekeeper, module dependency resolver, role visibility manager, and granular action-level RBAC enforcer (`role_module_permissions`: `view`, `edit`, `approve`, `export`).
     - `SettingsService`: Autonomous regulation policy parameter engine backed by `academic_settings_audit` and `academic_policy_versions`.
     - `BatchOBEService`: Multi-year cohort OBE hierarchy (`vision_mission` &rarr; `peos` &rarr; `po_pso` &rarr; Macro-Attainment), PO/PSO Articulation Matrix (NBA Tables 3.2 & 3.3.2), and longitudinal multi-batch quality analytics.
     - `ExamResultsService`: Examination notification lifecycle (`DRAFT` &rarr; `REVIEWED` &rarr; `PUBLISHED`), CSV preview/ingestion, fractional grade point scale engine, CIA vs. uploaded internal marks discrepancy audit, audited SEE marks auto-synchronizer, and R23 Section 18 credit promotion simulation.
     - `AttainmentPolicyService` & `AttainmentImportService`: Regulation/subject-type attainment profiles (`attainment_policies`), per-subject overrides (`subject_attainment_overrides`), and independent external CSV evidence import (`attainment_import_batches`).
     - `SEEBlueprintService`, `QuestionMarksService` & `SeeMarksWriter`: Protected SEE question paper blueprint revisions (with snapshot archiving and score retention), question-level scoring, and direct ledger writes.
     - `LabAssessmentService`: Atomic laboratory continuous evaluation rubric validation and persistence (`lab_rubric_evaluations`).
     - `LessonPlanService` & `AcademicReviewService`: Lecture delivery planning, diary reconciliation, serialized NBA Criterion 2.2 course completion compliance audits (`course_completion_audits`), closed-loop CQI lifecycle (`course_cqi_actions`), and evidence-based Action Taken Report (ATR) PDF export.
     - `StudentProfileService`: Biographical dossier, private Document Vault, original certificates custody ledger, statutory certificate issuance, and opaque token verification.
     - `ReportSnapshotService`: Reproducible report snapshot persistence (`report_snapshots`) with policy and metric SHA-256 hashes.
     - `AIProviderSettings` & `AIJobService`: Opt-in AI provider configuration (`ai_providers`), user task consent (`ai_job_consents`), evidence-only prompt construction, and human-reviewed draft lifecycle (`ai_jobs`).
     - PDF & Excel Dossier Generators: `EBluebookPDFService` (strictly immune 14-page operational e-Bluebook), `CourseMasterFilePDFService` (7-section accreditation dossier reflecting persisted review states), `OBEAnalysisPDFService` (NBA attainment reports & SAR Form C-3), `EnhancedPDFService`, and `FeedbackExcelService`.

4. **AJAX Endpoints**:
   - Lightweight PHP scripts (`ajax_handler.php`, `ajax_get_classes.php`, `ajax_get_timings.php`, `curriculum_subject_ajax.php`) that enforce session/CSRF/scope guards, invoke model classes, and return JSON responses.

---

## 3. Security, Authentication & Access Control Architecture

### 3.1 Session, CAPTCHA & Brute-Force Guard
Authentication is handled in `index.php` using the `User` class:
- **Rate-Limiting / Lockout**: Tracks failed login attempts per username using session keys:
  - `login_attempts_<md5(username)>`: Increments on every invalid password attempt.
  - `login_locked_<md5(username)>`: Enforces a 15-minute lockout (`lockDuration = 900`) once attempts reach 5 (`maxAttempts = 5`).
- **Cloudflare Turnstile Verification**: When `TURNSTILE_SECRET` is configured, login requests must pass server-side Turnstile verification; verification failures are strictly enforced.
- **Session Fixation Prevention**: Calls `session_regenerate_id(true)` immediately upon successful authentication.
- **Session Context Variables**:
  - `$_SESSION['user']`: Username / Roll number (lowercase string).
  - `$_SESSION['userid']`: Primary key integer in `users` table (`users.id`).
  - `$_SESSION['role']`: Normalized role string (`superadmin`, `admin`, `academic_section`, `hod`, `faculty`, `student`).
  - `$_SESSION['name']`: Uppercase display name.
  - `$_SESSION['dept_id']`: Department ID (set for HOD role).
  - `$_SESSION['facid']`: Faculty record ID (set for Faculty role).

### 3.2 Request Security & Ownership Scope (`\College\RequestSecurity` & `\College\RecordScope`)
Initialized automatically whenever `DBCredentials` is instantiated:
- **CSRF & Same-Origin Enforcement**: `\College\RequestSecurity::guardResources()` manages a per-session 64-character CSPRNG token (`$_SESSION['_csrf_token']`), validates `Origin`/`Referer` headers and `_csrf` tokens on all mutating `POST`/`PUT`/`PATCH`/`DELETE` requests, and uses an output buffer to automatically inject hidden `_csrf` inputs into internal `<form method="post">` tags and attach `X-CSRF-Token` headers to same-origin `fetch()` and `XMLHttpRequest` calls.
- **Record Ownership Scope**: `\College\RecordScope` verifies that the signed-in actor has legitimate authority over requested `sub_id`, `class_id`, `stu_id`/`student_id`, `doc_id`, `cert_id`, or `curr_sub_id` parameters before controller or service logic executes.

### 3.3 Transparent Password Hash Migration (MD5 to BCrypt)
The system supports seamless legacy migration from MD5 to standard PHP `password_hash()` (BCrypt):
1. When a user submits credentials, `User::authenticate()` inspects the stored password hash in `users.password`.
2. If the hash begins with `$2y$` (standard BCrypt prefix), it verifies via `password_verify($password, $dbPassword)`.
3. If it is legacy MD5:
   - It checks `md5($password) === $dbPassword`.
   - On match, it immediately generates a new BCrypt hash via `password_hash($password, PASSWORD_BCRYPT)`, updates `users.password`, and logs the upgrade event.

### 3.4 Server & File Access Hardening
Direct web access to sensitive environment definitions, SQL dumps, internal core/tools/tests directories, log files, and retained legacy uploads is blocked via root and directory-level `.htaccess` rules:
- Prohibits web access to `.env*`, `*.sql`, `*.log`, `*.git*`, `composer.*`, `database/`, `tools/`, `tests/`, `jntuacea/core/`, `jntuaceastudents/core/`, and `uploads/student_docs/`.
- Student documents are stored outside the web root when `COLLEGE_DOCUMENT_ROOT` is configured and streamed exclusively through ownership-checked `viewdocument.php`.

---

## 4. Logging, Snapshots & Audit Subsystems

The application employs a multi-tiered forensic audit strategy combining flat-file logs with immutable database audit and snapshot tables:

### 4.1 Flat-File Logging (`logs.class.php`)
Located in `jntuacea/logs/` and `jntuaceastudents/logs/` with daily-stamped log files:
- **Activity Logs** (`logs/activity_YYYY-MM-DD.log`): Records logins, password updates, and operational events via `Logs::activityLog()`.
- **Error Logs** (`logs/error_YYYY-MM-DD.log`): Records SQL prepare/execute failures and exceptions via `Logs::errLog()`.

### 4.2 Database Forensic & Governance Audit Trails
- **`audit_logs` Table (Primary Unified Audit Trail)**: `DBCredentials::dbActivityLog()` and `DBCredentials::logAudit()` write all authenticated operational and security events directly to `audit_logs`, recording the signed-in `users.id` (`user_id`), `username`, `role`, `action`, `entity_type`, `entity_id`, `target_user_id`, `status`, `details`, `ip_address`, and `user_agent`.
- **`academic_record_snapshots` Table**: Captures atomic before-and-after JSON payloads (`before_json`, `after_json`) with actor `users.id`, `department_id`, and mandatory `reason` whenever sensitive academic records are corrected, deleted, or replaced (e.g., 7-day single-student attendance corrections, HOD attendance deletion approvals, SEE blueprint revisions, and SEE result replacements).
- **`workflow_review_history` Table**: Immutable state-transition log (`from_status` &rarr; `to_status`, `actor_user_id`, `remarks`) tracking review lifecycles across course completion audits, CQI actions, attendance deletion requests, and examination notifications.
- **`academic_settings_audit` & `academic_policy_versions` Tables**: Track individual parameter edits (`academic_settings_audit`) and regulation-wide SHA-256 policy snapshot versions (`academic_policy_versions`).
- **`report_snapshots` Table**: Persists reproducible report state (`policy_hash`, `metrics_hash`, `scope_json`, `metrics_json`, `cutoff_at`) for official accreditation exports such as Action Taken Reports (`download_atr.php`).
- **`student_risk_snapshots` Table**: Stores daily/periodic attendance risk evaluation snapshots computed via `\College\AttendanceService` and predictive background analytics (`ai_pipeline/`), recording `policy_hash`, canonical category (`ELIGIBLE`, `CONDONABLE`, `DETAINED`, `POLICY_REVIEW`, `DATA_UNAVAILABLE`), and JSON metrics (`attendance_pct`, `safe_margin`, `recovery_periods`).
- **`ai_analysis_reports` Table**: Stores human-reviewable draft and finalized AI analytics reports (`report_type`, `entity_type`, `entity_id`, `source_hash`, `analysis_json`, `model_used`, `status`).
- **`activity_logs` & `fac_activity_logs` Tables**: Legacy administrative and faculty operational log tables retained for historical continuity.

---

## 5. Transaction Safety

Critical operations (such as attendance marking, single-student attendance correction, attendance deletion approval, lab rubric submission, SEE blueprint revision, timetable slot booking, and result imports) wrap all data mutations, snapshot writes, and `audit_logs` entries inside a single atomic database transaction on the shared request connection (`\College\Connection::get()`):
```php
$this->conn->begin_transaction();
try {
    // 1. Lock target rows (FOR UPDATE) and capture before_json snapshot
    // 2. Execute domain INSERT / UPDATE / DELETE statements
    // 3. Persist academic_record_snapshots / workflow_review_history
    // 4. Write to audit_logs via $this->dbActivityLog(...) and verify true return
    $this->conn->commit();
} catch (\Throwable $e) {
    $this->conn->rollback();
    $this->logs->errLog("Transaction failed: " . $e->getMessage());
    throw $e;
}
```
If any step—including the audit or snapshot insert—fails, the entire transaction rolls back together, guaranteeing zero orphaned records or unaudited mutations.

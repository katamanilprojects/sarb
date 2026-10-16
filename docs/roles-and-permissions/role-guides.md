# Role-by-Role Operations Guide

This guide details the operational responsibilities, primary daily workflows, and underlying PHP model and service methods invoked by each of the six primary user roles in **JNTUACEA** (`jntuacea/`) and **JNTUACEASTUDENTS** (`jntuaceastudents/`).

For the exhaustive file-by-file and 90-table read/write mapping, consult [user_feature_file_table_mapping.md](./user_feature_file_table_mapping.md).

---

## 1. SuperAdmin Guide

### 1.1 Overview
The SuperAdmin role is responsible for institutional master data setup, degree governance, academic policy parameters, feature toggle & granular action RBAC management, attainment profiles, optional AI provider configuration, and cohort configurations before an academic session commences.

### 1.2 Key Responsibilities & Model/Service Methods
- **Statutory Academic Regulations & Governance (`superadminregulations.php`, `superadminregulationdetails.php`)**:
  - Defines degree programs and autonomous academic regulations with statutory degree ceilings (`normal_duration_years`, `total_degree_credits`, `lateral_entry_credits`, `honors_credits`, `minor_credits`, `has_gap_year`, `has_internal_improvement`, etc.).
  - **Category Credit Distribution**: Manages statutory course categories (`regulation_course_categories`) with required credits, min/max limits, and live statutory validation badge (`Balanced`, `Deficit`, or `Excess`) ensuring $\sum \text{Required Credits} = \text{Total Degree Credits}$.
  - **Course Types & Assessment Schemes**: Configures `regulation_course_types` across canonical categories (`THEORY`, `LAB`, `INTEGRATED`, `PROJECT`, `AUDIT_NON_CREDIT`, `OTHER`) with default CIE/SEE marks and pass thresholds.
  - **1-Click Regulation Cloning Engine**: Clones complete regulatory packages (parameters, categories, course types, and policy settings) from existing regulations (e.g., seeding R26 B.Tech from R23 B.Tech).
- **Autonomous Academic Policy Engine (`superadminacademicsettings.php`)**:
  - Configures regulation-specific academic settings via `Services\SettingsService` and `\College\AssessmentPolicy` across all 12 policy domains (`CIA`, `SEE`, `ATTENDANCE`, `ATTAINMENT`, `GRADING`, `GENERAL`, `PROMOTION`, `HONORS`, `MINOR`, `MOOCS`, `PROJECT`, `ACTIVITIES`), recording mutations to `academic_settings_audit` and `academic_policy_versions`.
- **Attainment Profiles & Subject Overrides (`superadminattainmentsettings.php`)**:
  - Configures regulation- and subject-type-scoped attainment profiles (`attainment_policies`) and course-offering overrides (`subject_attainment_overrides`) via `Services\AttainmentPolicyService` (defining SEE maxima, `grade_bands`, `choice_groups`, and grade estimation policy).
- **Optional AI Provider Configuration (`superadminaiproviders.php`)**:
  - Configures local (`OLLAMA`) or cloud (`OPENAI_COMPATIBLE`) Chat Completions endpoints in `ai_providers` via `Services\AIProviderSettings` (storing only the `CEA_AI_...` environment variable name, never raw API secrets).
- **Feature Toggle & Action RBAC Governance (`superadminfeatures.php`)**:
  - Dynamically controls global enablement and per-role visibility (`VISIBLE`, `HIDDEN`, `READONLY`) of **28 system feature modules** (`system_feature_modules`), plus granular per-role action permissions (`view`, `edit`, `approve`, `export`) stored in `role_module_permissions` via `Services\FeatureManager::savePermission()`.
- **Academic Years (`superadminacademicyears.php`)**: `SuperAdmin::addAcademicYear()`, `SuperAdmin::getAcademicYears()`. Configures active terms (`2024-2025`, `2025-2026`).
- **Departments & Specializations (`superadmindepts.php`, `superadminspecs.php`)**: Creates academic departments and branch specializations.
- **Class Timings (`superadminclasstimings.php`)**: Establishes master bell schedules (`class_timings`, `class_timing_schedule`) with start/end times.
- **Classes & Sections (`superadminclasses.php`, `superadminprogramclasses.php`)**: Generates cohort sections mapped to regulation, specialization, and optional section via `SuperAdmin::generateClassName($spec_shortname, $yearsem, $section = '')`, assigning graduating batch cohorts (`batch_id`).
- **Student Cohort Batches & Full-Cycle OBE (`superadminbatches.php`, `superadminbatchobe.php`)**:
  - Defines permanent student cohorts (`student_batches`) and master Vision/Mission (`vision_mission`), PEOs (`peos`), PEO-Mission (`peo_mission_mapping`), and PO-PEO (`po_peo_mapping`) articulation matrices via `Services\BatchOBEService`.
  - Computes evidence-grounded Macro-Attainment rollups and longitudinal multi-batch quality analytics (`MOD_BATCH_OBE`).
- **NBA Program Outcomes (`superadminpopso.php`)**: Curates PO1–PO12 and PSO1–PSO4 statements in `po_pso`.

---

## 2. Admin Guide (The Principal)

### 2.1 Overview
The Admin role represents the Principal and Institutional Registrar. The Principal monitors college-wide operations, manages departmental leadership (HODs), audits faculty onboarding and custom department ordering, oversees student enrollments, monitors institutional attendance & feedback, and performs audited credential maintenance.

### 2.2 Key Responsibilities & Model/Service Methods
- **Department Operations Gateway (`adminfacst.php`)**:
  - Executive hub organizing departments, assigning HODs (`hod_profile.php`, `edit_hod.php`), inspecting faculty rosters, and reviewing class cohorts.
- **Faculty Onboarding & Department-Wise Ordering (`adminfac.php`, `adminaddfaculty.php`, `admineditfaculty.php`)**:
  - Registers new faculty members via `Admin::addFaculty($data)` and updates designations/statuses.
  - Manages custom department-wise faculty display order (`faculties.sort_order`, indexed by `idx_faculties_dept_sort`) via `Admin::reorderDepartmentFaculties($dept_id, $ordered_ids)`, `Admin::moveFacultySortOrder($faculty_id, $direction)`, and `Admin::resetFacultySortOrderToDefault($dept_id)`.
- **Student Enrollment (`adminenrollstudents.php`, `adminuploadst.php`, `adminunenrollstudents.php`, `editstudent.php`)**:
  - Adds individual students or processes bulk CSV uploads via `Admin::addStudent($rollno, $name, $class_id, $date_of_joining)`.
  - Provisions user accounts in `users` with role `'student'`.
- **Subject & Student Mapping**:
  - Maps faculty to courses (`Admin::addFacultySubjectMapping()`) and students to subject rosters (`Admin::addStudentSubjectMapping()`).
- **Audited Password Resets (`adminresetfacultypwd.php`, `adminresetstudentpwd.php`)**:
  - Resets forgotten student/faculty passwords with mandatory justification remarks (`Admin::resetStudentPwdWithRemarks()`, `Admin::resetFacPwdWithRemarks()`).
- **Student Feedback & Survey Oversight (`adminshowfeedbackstatus.php`, `adminviewfeedback.php`)**:
  - Monitors college-wide feedback submission progress and analyzes multi-level feedback reports using `FeedbackService`, exporting PDF (`download_feedback_enhanced.php`) and Excel (`download_feedback_excel.php`) dossiers.
- **Student Dossier, Private Document Vault & Custody Oversight (`adminstudentprofiles.php`, `admincustodyledger.php`, `viewdocument.php`, `viewcertificate.php`)**:
  - Inspects biographical dossiers and streams private student documents via `viewdocument.php` (`Services\StudentProfileService`).
  - Oversees the physical certificate custody ledger (`student_custodial_records`) and approves statutory certificate applications (`student_certificate_requests`).
- **Institutional Results & Attainment Oversight (`academicsectionresults.php`, `attainmentimport.php`, `adminciaanalysis2.php`)**:
  - Reviews published semester results, CIA vs. uploaded internal reconciliation audits, and college-wide OBE attainment analytics.
- **Institutional Attendance Audits (`adminshowallclsattendance.php`, `download_attendance_gazette.php`, `export_attendance_excel.php`)**:
  - Scrutinizes combined, enhanced, and subject-wise attendance rosters to prepare condonation and detention lists.

---

## 3. Academic Section Guide

### 3.1 Overview
The Academic Section role oversees examination infrastructure, centralized curriculum syllabus catalogs, examination results publication (`DRAFT` &rarr; `REVIEWED` &rarr; `PUBLISHED`), independent attainment CSV imports, student biographical verification, custody ledger management, and institution-wide attendance auditing.

### 3.2 Key Responsibilities & Model/Service Methods
- **Autonomous Course Structure & BoS Engine (`academicsectioncoursestructure.php`)**:
  - Builds structured semester-by-semester curriculum roadmaps (8 semesters for UG B.Tech, 4 semesters for PG M.Tech) across programs, regulations, departments, and specializations.
  - **Live Category Compliance Card**: Calculates accumulated category credits against SuperAdmin statutory quotas.
  - **Elective Tracks & BoS Dossier Export**: Organizes Professional/Open Elective tracks and exports Board of Studies curriculum blueprints.
- **Academic Regulations Reference Hub (`academicsectionregulations.php`, `views/academic_regulations_hub.php`)**:
  - Read-only institutional reference displaying statutory degree parameters, category credit distributions, course types, and active policy rules.
- **Master Curriculum Subject Catalog & Master COs (`academicsectioncurriculumsubjects.php`, `curriculum_subject.class.php`)**:
  - Manages the master course catalog (`curriculum_subjects`) and master Course Outcomes (`curriculum_course_outcomes`, `curriculum_co_po_mapping`) with dual-sync to active course offerings.
  - **Deterministic AICTE/UGC Credit Calculation**: Computes credits automatically via $C = L + T + 0.5 \times \max(P, PR)$.
  - **Assessment Scheme Auto-Defaults**: Pre-fills CIE/SEE max marks from `regulation_course_types` and captures elective tracks, delivery modes (`CONVENTIONAL`, `BLENDED`, `ONLINE_MOOC`), and prerequisites.
- **Examination Results Publication & CIA Reconciliation (`academicsectionresults.php`)**:
  - Creates examination notifications (`ExamResultsService::createNotification()`) with `workflow_status = 'DRAFT'` and `exam_type = 'REGULAR' | 'SUPPLEMENTARY' | 'REVALUATION'`.
  - Previews and imports bulk CSV result rosters (`ExamResultsService::previewCsv()`, `importCsv()`) via `\College\ResultsCsv`, preserving fractional grade points.
  - Audits live portal CIA vs. uploaded internal marks (`ExamResultsService::getNotificationCiaDiscrepancies()`), marks notifications `REVIEWED` (`reviewNotification()`), and publishes them (`togglePublishStatus()`).
  - Runs advisory credit-based promotion simulations (`MOD_CREDIT_PROMOTION`).
- **Independent Attainment CSV Import (`attainmentimport.php`)**:
  - Previews and commits external assessment evidence (`Services\AttainmentImportService`) for courses within scope.
- **Student Biographical Dossier, Private Vault & Custody Ledger (`adminstudentprofiles.php`, `admincustodyledger.php`, `viewdocument.php`, `viewcertificate.php`)**:
  - Verifies student profiles and private vault documents (`StudentProfileService::verifyDocument()`).
  - Manages physical certificate custody (`student_custodial_records`) and issues statutory certificates (`StudentProfileService::updateCertificateStatus()`) with atomic numbering (`certificate_sequences`) and opaque verification tokens.
- **Campus Buildings, Halls & Syllabus (`academicsectionmanagebuildings.php`, `academicsectionsyllabus.php`)**:
  - Manages buildings (`buildings`), halls (`halls`), and syllabus documents.

---

## 4. Head of Department (HOD) Guide

### 4.1 Overview
The HOD role manages departmental teaching operations, allocates subjects, coordinates conflict-checked weekly timetables, governs attendance deletion requests, and reviews/closes course completion compliance audits, CQI actions, and optional AI drafts.

### 4.2 Key Responsibilities & Model/Service Methods
- **Department Subject & Student Allocation (`hodmapfaculty.php`, `hodmapstudents.php`)**:
  - Assigns faculty members to departmental subjects (`HOD::mapFacultyToSubject()`) and maps students to electives/lab batches (`HOD::mapStudentsToSubject()`).
- **Conflict-Checked Timetable Scheduling (`hodmanage_timetable.php`)**:
  - Builds the department's weekly timetable grid using `Timetable` and `\College\TimetableWriter::add()`, enforcing class, room, and faculty overlap checks while permitting parallel electives/labs within a class and shared Open Electives across classes.
- **Row-Locked Attendance Deletion Approval & Audit History (`hodviewdelrequests.php`)**:
  - Reviews faculty deletion requests under `MOD_ATT_REQUESTS` via `HOD::processDeleteRequest()` (`\College\AttendanceCorrection::reviewDeleteRequest()`), locking the request row, refusing ambiguous co-teaching deletions, archiving before/after rows in `academic_record_snapshots`, and purging mistaken records from `attendance` and `diary`.
  - Inspects forensic audit history in `audit_logs` (`HOD::getAttendanceDeleteAuditLogs()`).
- **Attendance Condonation & Detention Gazette (`hodshowclsattendance.php`, `download_attendance_gazette.php`)**:
  - Evaluates student regulatory attendance standing via `\College\AttendanceService::classSummary()` and exports the official Gazette PDF (`MOD_ATTENDANCE_CONDONATION_GAZETTE`).
- **Course Review Queue, CQI Closure & ATR Export (`reviewqueue.php`, `faclessonplanreconciliation.php`, `download_atr.php`)**:
  - Reviews faculty NBA Criterion 2.2 course completion audits (`Services\AcademicReviewService::reviewAudit()`), approving or returning them with remarks.
  - Reviews proposed CQI remedial actions (`AcademicReviewService::reviewCqi()`: `APPROVED` or `REVISION_REQUESTED`), verifies faculty execution evidence, and formally closes completed CQI actions (`AcademicReviewService::closeCqi()`).
  - Exports snapshot-backed Action Taken Report PDFs (`download_atr.php`) and Autonomous Course Master Files (`download_coursemasterfile.php`).
- **Departmental Attainment, Imports & Optional AI Review (`hodciaanalysis2.php`, `attainmentimport.php`, `aiassistance.php`)**:
  - Reviews departmental OBE attainment matrices, imports external attainment CSV evidence (`attainmentimport.php`), and reviews/approves opt-in AI drafts (`aiassistance.php`, `MOD_AI_ASSISTANCE`).
- **Department Feedback Review (`hodviewfeedback.php`)**:
  - Analyzes CO feedback, 5-domain CES scores, and anonymous Faculty Appraisals across departmental courses.

---

## 5. Faculty Guide

### 5.1 Overview
The Faculty role handles classroom-level execution: formulating lecture plans, marking daily attendance with zero friction, performing 7-day student attendance corrections, reconciling plans with diary entries, entering internal and external assessment marks, importing attainment evidence, tracking CQI execution, and reviewing student feedback.

### 5.2 Key Responsibilities & Model/Service Methods
- **Lesson Planning (`facaddlessonplan.php`)**:
  - Formulates lecture delivery schedules with unit, lecture number, planned topic, target CO, Bloom level, and pedagogy, or bulk-uploads via CSV (`Services\LessonPlanService`).
- **Frictionless Daily Attendance, Diary & 7-Day Corrections (`facaddattendance.php`, `facadddairy.php`, `facupdatestudentattendance.php`, `facexceptionalattendance.php`)**:
  - Marks period attendance with natural free-text syllabus topic notes (`Faculty::markAttendance()`).
  - Corrects individual student attendance statuses within the 7-day window (`facupdatestudentattendance.php`, `\College\AttendanceCorrection::updateStudentStatuses()`) with mandatory justification reasons and automatic before/after snapshots.
  - Submits session deletion requests (`facadddelattrequest.php`, `Faculty::submitAttendanceDeleteRequest()`).
- **End-of-Course Plan Reconciliation, Compliance Audit & CQI Execution (`faclessonplanreconciliation.php`, `reviewqueue.php`, `download_atr.php`)**:
  - Maps chronological teaching diary entries to planned lectures and submits the NBA Criterion 2.2 course completion compliance audit (`course_completion_audits`, locked upon submission until reviewed).
  - Proposes remedial actions in `course_cqi_actions` (`MOD_CQI_ACTION_TRACKING`), revises returned proposals, records `execution_evidence` upon completion in `reviewqueue.php` (`Services\AcademicReviewService`), and exports the Action Taken Report PDF (`download_atr.php`).
- **CIA Marks Evaluation & Atomic Lab Rubrics (`facaddciamarks.php`, `facaddcialabmarks.php`, `facaddpgciamarks.php`, `facaddprojectciamarks.php`)**:
  - Records Mid-1 and Mid-2 scores across Theory, Lab, PG, and Project courses.
  - Uses the Rubrics Assistant modal (`MOD_LAB_RUBRICS`) backed by `Services\LabAssessmentService` to validate and atomically commit criteria breakdowns (`lab_rubric_evaluations`) alongside `uglab_internal_assessment_marks`.
  - Views real-time Bloom HOTS/LOTS cognitive distribution badges (`MOD_BLOOM_ANALYSIS`) and uploads assessment attachments (`facciaattachments.php`).
- **Semester End Examination (SEE) Evaluation & Attainment Import (`facseemarks.php`, `facseecompques.php`, `facseemarksentry.php`, `facseedirectmarks.php`, `attainmentimport.php`)**:
  - **Mode A (Question-Level Blueprint)**: Configures SEE blueprints (`Services\SEEBlueprintService`, with protected snapshot-archived revisions when marks exist) and enters question-by-question marks (`Services\QuestionMarksService`).
  - **Mode B (Direct Marks Ledger)**: Enters consolidated external marks (`Services\SeeMarksWriter`).
  - **Mode C (Audited Auto-Sync from Published Results)**: Syncs external marks from an explicitly selected published `REGULAR` notification (`ExamResultsService::syncResultsToSeeMarks()`), requiring confirmation and a reason when replacing existing marks.
  - **Independent Attainment CSV Import (`attainmentimport.php`)**: Previews and commits external aggregate, gross/net scaled, grade-derived, or question-wise CSV evidence (`Services\AttainmentImportService`).
- **Outcome-Based Education, Dossiers & Optional AI Drafts (`facaddcos.php`, `facarticulationmatrix.php`, `facciaanalysis2.php`, `aiassistance.php`)**:
  - Formulates Course Outcomes, maps CO-PO matrices, and generates e-Bluebooks (`download_bluebook.php`), Course Master Files (`download_coursemasterfile.php`), NBA OBE dossiers, and SAR Form C-3 reports (`download_obe_analysis.php`).
  - Opts in to `MOD_AI_ASSISTANCE` (`aiassistance.php`) to queue and review PII-free CO attainment narrative drafts or ATR action proposals.

---

## 6. Student Guide (`jntuaceastudents`)

### 6.1 Overview
The Student role provides learners with access to their academic schedules, subject rosters, attendance percentages and risk gauges, class timetables, examination grade sheets, statutory certificate self-service, private document vault, and confidential 3-tier feedback surveys via `jntuaceastudents/`.

### 6.2 Key Responsibilities & Model/Service Methods
- **Authentication & Dashboard (`jntuaceastudents/index.php`, `studenthome.php`)**:
  - Students sign in using their Hall Ticket / Roll Number credentials.
  - `studenthome.php` renders today's schedule, registered subjects, and the real-time **Attendance Risk Gauge** (`MOD_STUDENT_ATTENDANCE_RISK` via `StudentAttendanceService` & `\College\AttendanceService`), displaying **Safe Margin**, **Recovery Periods Needed**, and standing badges (`ELIGIBLE`, `CONDONATION`, `DETAINED`, `PROVISIONAL`).
- **Biographical Dossier & Private Cloud Document Vault (`studentprofile.php`, `studentdocuments.php`, `viewdocument.php`)**:
  - Students manage their biographical profile (`student_profiles`) and upload PDF/PNG/JPG scans of statutory certificates (`student_documents`) into private storage (`COLLEGE_DOCUMENT_ROOT` via `\College\DocumentStorage`), streaming their own documents securely through `viewdocument.php` and tracking physical custody holdings (`student_custodial_records`).
- **Official Examination Results & Fractional SGPA Grade Cards (`studentresults.php`, `studentsubatt.php`, `studentsubjects.php`)**:
  - Powered by `ExamResultsService`: displays published examination notifications, internal/external/total marks, letter grades, fractional or integer grade points (`decimal(13,3)`), credits, and real-time SGPA.
- **Statutory Certificates Self-Service & Opaque Token Verification (`studentcertificates.php`, `viewcertificate.php`)**:
  - Students apply online for **Bonafide**, **Study & Conduct**, **Custodial**, **Transfer Certificate (TC)**, and **No Dues** certificates (`student_certificate_requests`).
  - Once approved, students view and print their official certificate via `viewcertificate.php?id=<REQ_ID>` (protected by `RecordScope::certificate()`), while third parties can verify authenticity via `viewcertificate.php?id=<REQ_ID>&token=<OPAQUE_TOKEN>` using the 64-character CSPRNG `verification_token`.
- **Attendance Breakdown, Timetable & 3-Tier Confidential Surveys (`studentsubatt.php`, `studenttimetable.php`, `studentfeedback.php`)**:
  - Views period-by-period attendance and teaching diary logs (`studentsubatt.php`) and weekly bell schedules (`studenttimetable.php`).
  - Completes confidential accreditation surveys through `StudentFeedbackService`:
    1. **Faculty Teaching Appraisal (`student_faculty_feedback.php`)**: 19-criterion evaluation (`fac_q1`–`fac_q19`) with strict student anonymity (`is_anonymous = 1`).
    2. **Course Outcome Survey (`student_co_feedback.php`)**: Self-perceived achievement per Course Outcome (CO1–CO6).
    3. **Course End Survey (`student_ces_feedback.php`)**: 16-item evaluation across 5 domains plus qualitative recommendations.

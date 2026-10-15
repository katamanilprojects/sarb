# Role-by-Role Operations Guide

This guide details the operational responsibilities, primary daily workflows, and underlying PHP model methods invoked by each of the six primary user roles in the system.

For the exhaustive file-by-file and database table read/write mapping, consult [user_feature_file_table_mapping.md](file:///Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/docs/roles-and-permissions/user_feature_file_table_mapping.md).

---

## 1. SuperAdmin Guide

### 1.1 Overview
The SuperAdmin role is responsible for institutional master data setup, degree governance, academic policy parameters, feature toggle management, and cohort configurations before an academic session commences.

### 1.2 Key Responsibilities & Model Methods
- **Statutory Academic Regulations & Governance (`superadminregulations.php`, `superadminregulationdetails.php`)**:
  - Defines degree programs and autonomous academic regulations with statutory degree ceilings (`normal_duration_years`, `total_degree_credits`, `lateral_entry_credits`, `honors_credits`, `minor_credits`, `has_gap_year`, `has_internal_improvement`, `max_continuous_gap_years`, etc.).
  - **Category Credit Distribution**: Manages statutory course categories (`regulation_course_categories`) with required credits, min/max limits, and live statutory validation badge (`Balanced`, `Deficit`, or `Excess`) ensuring $\sum \text{Required Credits} = \text{Total Degree Credits}$.
  - **Course Types & Assessment Schemes**: Configures `regulation_course_types` across canonical categories (`THEORY`, `LAB`, `INTEGRATED`, `PROJECT`, `COMPREHENSIVE_VIVA`, `INTERNSHIP`, `AUDIT_NON_CREDIT`) with default CIE/SEE marks, CIE pass marks, SEE pass marks, and aggregate pass marks.
  - **1-Click Regulation Cloning Engine**: Clones complete regulatory packages (parameters, categories, course types, and policy settings) from existing regulations (e.g., seeding R26 B.Tech from R23 B.Tech).
- **Autonomous Academic Policy Engine**: Configures regulation-specific academic settings in `superadminacademicsettings.php` via `SettingsService` across all 12 policy domains (`PROMOTION`, `HONORS`, `MINOR`, `MOOCS`, `PROJECT`, `ACTIVITIES`, `CIA`, `ATTENDANCE`, `GRADING`, `MALPRACTICE`, `DETENTION`, `GENERAL`).
- **Feature Toggle Governance**: In `superadminfeatures.php`, dynamically controls global enablement and per-role visibility (`VISIBLE`, `HIDDEN`, `READONLY`) of 27 system feature modules via `FeatureManager`.
- **Academic Years**: `SuperAdmin::addAcademicYear()`, `SuperAdmin::getAcademicYears()`. Configures active terms (`2024-2025`, `2025-2026`).
- **Departments & Specializations**: Creates academic departments and branch specializations (e.g., CSE - Artificial Intelligence & Machine Learning).
- **Class Timings**: Establishes master bell schedules in `superadminclasstimings.php` with start/end times.
- **Classes & Sections**: Generates cohort sections mapped to regulation, specialization, and optional section via `SuperAdmin::generateClassName($spec_shortname, $yearsem, $section = '')` (e.g., "CSE - III-I - Sec A"), assigning graduating batch cohorts (`batch_id`).
- **Student Cohort Batches & Governance (`superadminbatches.php`)**:
  - Defines permanent student cohorts (`student_batches`) anchored by entry and graduation years (e.g. `2025-2029`).
  - Manages cohort programs, regulations, and active states via `BatchOBEService`.
- **Batch OBE Setup & Full-Cycle Articulation (`superadminbatchobe.php`)**:
  - Vision & Mission: Defines cohort and department-specific Vision statements and multiple Mission points ($M_1, M_2, \dots$).
  - Program Educational Objectives: Configures PEOs (target score, title, description).
  - PEO-Mission Articulation Matrix: Maps PEOs to Mission points with weights ($0, 1, 2, 3$).
  - PO-PEO Articulation Matrix: Maps POs/PSOs to PEOs with weights ($0, 1, 2, 3$).
  - Macro-Attainment Calculation: Computes multi-level backwards attainment traceability ($CO \rightarrow PO \rightarrow PEO \rightarrow \text{Mission}$) with compliance statuses.
  - **Longitudinal Multi-Batch Quality Analytics (`MOD_BATCH_OBE`)**: Tracks cohort-over-cohort PEO attainment and PO attainment trajectories across multiple graduated cohorts with interactive radar and bar progression charts.
- **NBA Program Outcomes**: Uploads PO1-PO12 and PSO1-PSO4 statements into `po_pso` via `superadminpopso.php`.

---

## 2. Admin Guide (The Principal)

### 2.1 Overview
The Admin role represents the Principal and Institutional Registrar. The Principal monitors college-wide operations, manages departmental leadership (HODs), audits faculty onboarding, oversees student enrollments, monitors institutional attendance & feedback, and performs audited credential maintenance.

### 2.2 Key Responsibilities & Model Methods
- **Department Operations Gateway (`adminfacst.php`)**:
  - Acts as the executive hub organizing departments, assigning HODs (`hod_profile.php`, `edit_hod.php`), inspecting faculty rosters, and reviewing class cohorts.
- **Student Enrollment**:
  - Adds individual students or processes bulk CSV uploads (`adminenrollstudents.php`, `adminuploadst.php`).
  - Calls `Admin::addStudent($rollno, $name, $class_id, $date_of_joining)`.
  - Provisions user account in `users` with default credentials and role `'student'`.
- **Faculty Onboarding**:
  - Registers new faculty members in `adminaddfaculty.php` via `Admin::addFaculty($data)`.
  - Updates designations, departments, and active statuses in `admineditfaculty.php`.
- **Subject & Student Mapping**:
  - Maps faculty to courses (`Admin::addFacultySubjectMapping()`).
  - Maps students to subject rosters (`Admin::addStudentSubjectMapping()`).
- **Audited Password Resets**:
  - Resets forgotten student/faculty passwords with mandatory justification remarks (`Admin::resetStudentPwdWithRemarks()`, `Admin::resetFacPwdWithRemarks()`).
- **Student Feedback & Survey Oversight**:
  - Monitors college-wide feedback submission progress and response rates in `adminshowfeedbackstatus.php`.
  - Analyzes multi-level feedback reports (by Class, Subject, Faculty, and Department) in `adminviewfeedback.php` using `FeedbackService`.
  - Generates comprehensive PDF reports (`download_feedback_enhanced.php`) and Excel analytical workbooks (`download_feedback_excel.php`).
- **Student Dossier, Document Vault & Custody Oversight (`adminstudentprofiles.php`, `admincustodyledger.php`)**:
  - Inspects biographical dossiers, parental metrics, entrance ranks, and digital credential vaults of all enrolled students.
  - Verifies student-uploaded certificates and marks profiles as officially verified (`StudentProfileService::verifyProfile()`, `verifyDocument()`).
  - Oversees the college safe custody ledger, logging original certificates deposited during admission and tracking temporary/permanent checkouts.
  - Reviews and approves statutory certificate applications (Custodial, TC, Study/Conduct, Bonafide, No Dues).
- **Institutional Results Publication Oversight (`academicsectionresults.php`)**:
  - Reviews published semester results notifications and institutional grade distributions.
- **Institutional Attendance Audits**:
  - Scrutinizes combined, enhanced, and subject-wise attendance rosters via `adminshowallclsattendance.php` to prepare condonation and detention lists.

---

## 3. Academic Section Guide

### 3.1 Overview
The Academic Section role oversees examination infrastructure, centralized curriculum syllabus catalogs, examination results publication, student biographical verification, custody ledger management, and institution-wide attendance auditing.

### 3.2 Key Responsibilities & Model Methods
- **Autonomous Course Structure & BoS Engine (`academicsectioncoursestructure.php`)**:
  - Builds structured semester-by-semester curriculum roadmaps (8 semesters for UG B.Tech, 4 semesters for PG M.Tech) across programs, regulations, departments, and specializations.
  - **Live Category Compliance Card**: Interactively calculates accumulated category credits against SuperAdmin statutory quotas, warning of deficits or excesses before Board of Studies approval.
  - **Elective Tracks & Verticals**: Organizes Professional Elective tracks (PE-1 to PE-5) and Open Electives (OE-1 to OE-4) for interdisciplinary specialization pathways.
  - **Board of Studies (BoS) Dossier Export**: Generates standardized, printable curriculum blueprints and BoS meeting documentation.
- **Academic Regulations Reference Hub (`academicsectionregulations.php`, `views/academic_regulations_hub.php`)**:
  - Centralized, read-only institutional reference displaying statutory degree parameters, category credit distributions, course types, assessment schemes, and active policy rules.
- **Master Curriculum Subject Catalog (`academicsectioncurriculumsubjects.php`)**:
  - Manages the master course catalog across regulations using `CurriculumSubject`.
  - **Deterministic AICTE/UGC Credit Calculation**: Computes credits automatically on entry via $C = L + T + 0.5 \times \max(P, PR)$.
  - **Assessment Scheme Auto-Defaults**: Selecting a course category and course type automatically pre-fills standard CIE max marks, SEE max marks, and evaluation attributes from `regulation_course_types`.
  - Captures elective tracks, delivery modes (`OFFLINE`, `ONLINE`, `HYBRID`), and prerequisite course codes.
- **Examination Results Publication & SEE Marks Auto-Sync (`academicsectionresults.php`)**:
  - Creates examination release notifications (`ExamResultsService::createNotification()`) tied to degree program, regulation, semester, and academic year.
  - Processes bulk CSV uploads of official examination results (`ExamResultsService::importResultsCsv()`).
  - Controls 1-click publishing toggle (`ExamResultsService::togglePublishStatus()`) making semester grade sheets and calculated SGPA instantly accessible to students.
- **Student Biographical Dossier & Vault Verification (`adminstudentprofiles.php`)**:
  - Inspects student admission metrics, parent contact data, and verified document vaults.
  - Audits and approves student uploaded credential documents (`StudentProfileService::verifyDocument()`).
- **Physical Custody Ledger & Statutory Certificates (`admincustodyledger.php`)**:
  - Operates the safe custody ledger for physical original certificates deposited by students.
  - Records temporary loan checkouts with return promise dates and handles exit clearances.
  - Approves and issues verified statutory certificates (`StudentProfileService::updateCertificateStatus()`) with official QR serial numbers.
- **Campus Buildings & Examination Halls**:
  - Creates physical buildings and halls in `academicsectionmanagebuildings.php`.
  - Invokes `AcademicSection::getAllBuildings()`, `AcademicSection::addBuilding()`, and `AcademicSection::addHall()`.
  - Used for examination seating planning and classroom allocation.
- **Syllabus Management**:
  - Uploads official curriculum regulation PDFs and course syllabus files via `Syllabus::addSyllabus()`.
- **Emergency Password Resets**:
  - Alternative administrative office for student/faculty password resets (`AcademicSection::resetStudentPwdWithRemarks()`).
- **College-Wide Attendance Monitoring**:
  - Views attendance percentages across all departments to prepare condonation and detention lists for academic council review (`academicsectionshowallclsattendance.php`).

---

## 4. Head of Department (HOD) Guide

### 4.1 Overview
The HOD role manages departmental teaching operations, allocates subjects, coordinates weekly timetables, governs attendance deletion requests, and approves end-of-course compliance audits.

### 4.2 Key Responsibilities & Model Methods
- **Department Subject Allocation**:
  - Assigns faculty members to departmental subjects via `HOD::mapFacultyToSubject()`.
  - Subject and class offerings are queried dynamically via `Subject` model.
- **Timetable Scheduling**:
  - Builds the department's weekly timetable grid in `hodmanage_timetable.php` using `Timetable` model methods, preventing instructor conflicts.
- **Attendance Deletion Approval Gate & Audit History (`hodviewdelrequests.php`)**:
  - Reviews correction requests submitted by faculty with single-tier HOD authority (`MOD_ATTENDANCE_DELETE_REQUESTS`).
  - Approves or rejects via `HOD::processDeleteRequest($request_id, $action)` to automatically purge mistaken records from `attendance` and `diary`.
  - Inspects forensic audit log history via the Audit Trail tab querying `audit_logs` (`HOD::getAttendanceDeleteAuditLogs()`).
- **Attendance Condonation & Detention Gazette (`hodshowclsattendance.php`)**:
  - Evaluates student regulatory attendance standing ($\ge 75\%$ Eligible, $65-74.9\%$ Condonable, $<65\%$ Detained) and exports official Gazette PDF (`MOD_ATTENDANCE_CONDONATION_GAZETTE`).
- **Course Delivery Compliance & Syllabus Audit Gate**:
  - Reviews faculty course completion audits in `faclessonplanreconciliation.php` or departmental audit views.
  - Reviews planned vs conducted lectures, unit milestone dates, deviations, and compensatory classes. Formally signs off for NBA Criterion 2.2 compliance.
  - Downloads Autonomous Course Master File dossier via `hodhome.php` (`MOD_COURSE_MASTER_FILE`).
- **Departmental Performance & CIA Review**:
  - Audits subject-wise attendance percentages (`hodshowallclsattendance.php`).
  - Reviews Mid-1 and Mid-2 internal assessment marks across all departmental classes (`hodshowcls_cia.php`).
- **Department Feedback Review**:
  - Analyzes Course Outcome (CO) feedback, Course End Survey (CES) 5-domain scores, and Faculty Appraisals across all departmental courses in `hodviewfeedback.php`.
  - Downloads official enhanced PDF reports and multi-sheet Excel workbooks for departmental NAAC/NBA documentation.

---

## 5. Faculty Guide

### 5.1 Overview
The Faculty role handles classroom-level execution: formulating lecture plans, marking daily attendance with zero friction, logging teaching topics, reconciling plans with diary entries, entering internal and external assessment marks, analyzing student learning outcomes, and reviewing student feedback for continuous pedagogical improvement.

### 5.2 Key Responsibilities & Model Methods
- **Lesson Planning**:
  - Formulates lecture delivery schedules in `facaddlessonplan.php` with unit, lecture number, planned topic, target CO, Bloom level, and pedagogy.
  - Supports bulk CSV template upload with automatic sanitization and validation (`LessonPlanService`).
- **Frictionless Daily Attendance & Teaching Diary**:
  - Marks period attendance in `facaddattendance.php` with natural free-text syllabus topic notes (zero friction, clean 4-column diary layout).
  - Unrecorded hour validation prevents accidental duplicates.
  - Subject offerings queried via `Subject` class.
  - Exceptional attendance for approved student activities via `Faculty::markExceptionalAttendance()`.
- **Attendance Correction Requests**:
  - Submits correction/deletion requests via `Faculty::submitAttendanceDeleteRequest()` when incorrect periods were entered.
- **End-of-Course Plan Reconciliation, Compliance Audit & CQI Tracking**:
  - In `faclessonplanreconciliation.php`, maps chronological teaching diary entries to planned lesson lectures using 1-Click Auto-Sequence or custom mapping.
  - Tracks unit milestone completion dates, syllabus coverage percentage, pedagogical deviations, and compensatory classes.
  - Submits formal NBA Criterion 2.2 course completion compliance audit for HOD approval.
  - Logs remedial action plans in `course_cqi_actions` for Course Outcomes exhibiting attainment shortfalls (`MOD_CQI_ACTION_TRACKING`).
  - Downloads Autonomous Course Master File dossier (`MOD_COURSE_MASTER_FILE`).
- **CIA Marks Evaluation & Lab Rubrics**:
  - Records Mid-1 and Mid-2 scores across Theory, Lab, PG, and Project courses using `CIA` and `CIAMarks` models.
  - In `facaddcialabmarks.php`, utilizes additive Rubrics Assistant modal (`MOD_LAB_RUBRICS`) to calculate tripartite criteria scores into `day_to_day_marks[]` while preserving direct manual entry.
  - Views real-time ambient Bloom HOTS/LOTS advisory progress meter in `facaddciamarks.php` and `facquestionco.php` (`MOD_BLOOM_ANALYSIS`).
  - Uploads exam attachments (question papers, answer keys) via `Faculty::addCIAAttachment()`.
- **Semester End Examination (SEE) Evaluation (`facseemarks.php`)**:
  - Enters external marks using `SEEAssessment` across three flexible operating modes:
    - **Mode A (Question-Level)**: Detailed question-by-question scoring mapped to Course Outcomes for direct question-level attainment computation.
    - **Mode B (Direct Marks Ledger)**: Direct entering of consolidated external examination marks (scaled out of 70.00).
    - **Mode C (Auto-Sync from Published Examination Results)**: Faculty click **Sync from Published Results** to automatically pull verified external marks and grade points from `ExamResultsService::syncResultsToSeeMarks()`, eliminating repetitive manual data entry.
- **Outcome-Based Education**:
  - Formulates Course Outcomes (`facaddcos.php`).
  - Fills CO-PO articulation matrices (`facarticulationmatrix.php`).
  - Maps assessment questions to COs and Bloom's taxonomy (`facquestionco.php`).
  - Generates attainment reports (`facciaanalysis2.php`).
- **Official Accreditation Reports & Dossiers**:
  - Generates complete official e-Bluebooks via `download_bluebook.php` (`EBluebookPDFService`).
  - Downloads standalone NBA SAR Form C-3 dossiers via `download_obe_analysis.php?action=sar_form_c3` (`MOD_SAR_FORM_C3`).
  - Downloads comprehensive NBA/NAAC Course Assessment & Attainment dossiers via `download_obe_analysis.php` (`OBEAnalysisPDFService`).
- **Feedback & Continuous Improvement**:
  - Reviews indirect Course Outcome attainment ratings and 5-domain Course End Survey results in `facviewfeedback.php`.
  - Reviews student appraisal of teaching performance with strict student anonymity (`is_anonymous = 1`).
  - Inspects collapsible qualitative remarks cards (CES Part-C and Faculty Feedback) to identify curriculum and instructional enhancements.
  - Exports official PDF and Excel feedback dossiers for personal academic portfolios.

---

## 6. Student Guide (`jntuaceastudents`)

### 6.1 Overview
The Student role provides learners with access to their academic schedules, subject rosters, attendance percentages, class timetables, examination grade sheets, statutory certificate self-service, institutional document vault, and multi-tier feedback surveys via the mobile-friendly student portal.

### 6.2 Key Responsibilities & Model Methods
- **Authentication & Dashboard**:
  - Students sign in using their Hall Ticket / Roll Number credentials via `jntuaceastudents/index.php`.
  - `studenthome.php` renders the daily schedule, next upcoming class period, registered subjects, and rapid access to attendance breakdown.
- **Biographical Dossier & Cloud Document Vault (`studentprofile.php`)**:
  - Students manage their official institutional profile via `StudentProfileService`:
    - Personal & contact details (Father name, Mother name, DOB, Blood Group, Aadhaar Number, Category/Quota, Admission details, permanent address).
    - Cloud Document Vault: upload verified PDF/JPG scans of statutory certificates (Aadhaar, SSC / 10th Marks Card, Intermediate / 12th / Diploma, EAMCET / ECET Rank Card, Caste / Category Certificate, Income Certificate).
    - Tracks Academic Section verification badges (`PENDING`, `VERIFIED`, `REJECTED`) and institutional custody holdings.
- **Official Examination Results & SGPA Grade Cards (`studentresults.php`)**:
  - Powered by `ExamResultsService::getStudentPublishedResults()`:
    - Filters published examination notifications by regulation, year, and semester.
    - Displays subject-by-subject internal marks, external marks, total score, letter grade (`O`, `S`, `A`, `B`, `C`, `D`, `E`, `F`), grade points (0–10 scale), credits, and pass/fail result status.
    - Computes real-time Semester Grade Point Average (SGPA):
      $$\text{SGPA} = \frac{\sum (C_i \times GP_i)}{\sum C_i}$$
    - Offers clean responsive layout and one-click printable grade card views.
- **Statutory Certificates Self-Service & Cryptographic QR Verification (`studentcertificates.php`, `viewcertificate.php`)**:
  - Powered by `StudentProfileService`:
    - Students apply online for official institutional certificates: **Bonafide / Study Certificate**, **Conduct Certificate**, **Custodial Certificate** (listing original certificates held in institutional custody), and **Transfer Certificate (TC)**.
    - Specifies purpose of request (Passport, Bank Loan, Higher Studies, Scholarship, Visa, Employment).
    - Real-time approval tracking: `SUBMITTED` &rarr; `PROCESSING` &rarr; `APPROVED` &rarr; `ISSUED`.
    - Once approved, students can immediately view and print their tamper-evident official certificate via `student_print_certificate.php` complete with scannable SHA-256 QR verification token, institutional watermark, and issuing authority endorsements (`MOD_CERTIFICATE_QR`).
- **Subject Roster & Instructor Information**:
  - `studentsubjects.php` queries `StudentRepository` to display enrolled courses, subject codes, and assigned faculty details.
- **Attendance Transparency, Risk Gauge & Session Diary History**:
  - `studenthome.php` renders real-time **Attendance Risk Gauge** (`MOD_STUDENT_ATTENDANCE_RISK`):
    - **Safe Margin**: Number of upcoming lecture periods student can miss while maintaining $\ge 75\%$ attendance.
    - **Recovery Periods Needed**: Consecutive class periods required to restore attendance if $< 75\%$.
    - Color-coded regulatory status badges (Green = Safe, Yellow = Condonable, Red = Detention Risk).
  - `studentsubatt.php` queries `StudentAttendanceService` to present comprehensive subject-wise attendance statistics:
    - Conducted class hours, attended hours, and percentage.
    - Condonation threshold warnings based on autonomous `attendance_rules`.
    - Monthly breakdown and period-by-period diary records showing what syllabus topic was taught during each class.
- **Weekly Bell Schedule**:
  - `studenttimetable.php` queries `Timetable` to render the student cohort's complete weekly timetable grid.
- **3-Tier Confidential Feedback & Quality Surveys**:
  - Students complete mandatory accreditation surveys through `StudentFeedbackService`:
    1. **Faculty Teaching Appraisal (`student_faculty_feedback.php`)**: Rates instructor performance on 10 pedagogic dimensions with strict student anonymity.
    2. **Course Outcome Survey (`student_co_feedback.php`)**: Evaluates self-perceived achievement for each Course Outcome statement (CO1 to CO6).
    3. **Course End Survey (`student_ces_feedback.php`)**: Evaluates curriculum quality across 5 institutional domains and captures qualitative recommendations.
- **Account Security**:
  - Students manage their passwords independently in `stchgpwd.php`.


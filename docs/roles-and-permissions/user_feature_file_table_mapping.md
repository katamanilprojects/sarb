# Master User, Feature, File & Database Table Mapping

This document provides a comprehensive, ground-truth mapping across all 6 system roles in the **JNTUACEA Attendance, CIA, OBE & Institutional Management Portals** (`jntuacea` and `jntuaceastudents`).

Every mapping has been verified against the physical codebase and the active MySQL database (`u182589698_jntuaceasarb` - 56 tables).

---

## Table of Contents
1. [SuperAdmin Role](#1-superadmin-role)
2. [Admin Role (The Principal)](#2-admin-role-the-principal)
3. [Academic Section Role](#3-academic-section-role)
4. [Head of Department (HOD) Role](#4-head-of-department-hod-role)
5. [Faculty Role](#5-faculty-role)
6. [Student Role (`jntuaceastudents`)](#6-student-role-jntuaceastudents)
7. [Comprehensive Database Table Utilization Matrix](#7-comprehensive-database-table-utilization-matrix)

---

## 1. SuperAdmin Role
**Context**: Central IT / University Administrator with unrestricted governance over institutional parameters, academic structure, degrees, and system policies.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 1.1 | **Governance** | Institutional Control Center | - | `superadminhome.php`, `superadminmenu.php`, `header.php`, `footer.php` | `departments`, `programs`, `specialization`, `classes`, `academic_settings`, `system_feature_modules`, `users` | `activity_logs` | Executive dashboard displaying system KPIs, active terms, and quick action hubs. |
| 1.2 | **Governance** | Feature Toggle Management | - | `superadminfeatures.php`, `services/FeatureManager.php` | `system_feature_modules` | `system_feature_modules`, `activity_logs` | Toggles modules globally or customizes visibility (`VISIBLE`, `HIDDEN`, `READONLY`) per role. |
| 1.3 | **Policy & Terms** | Autonomous Academic Settings | - | `superadminacademicsettings.php`, `views/manage_academic_settings.php`, `services/SettingsService.php` | `regulations`, `academic_settings`, `academic_settings_audit` | `academic_settings`, `academic_settings_audit`, `activity_logs` | Configures regulation-specific parameters (CIA weightages, attendance thresholds, OBE targets). |
| 1.4 | **Policy & Terms** | Academic Years Setup | - | `superadminacademicyears.php`, `academicyears.class.php` | `academic_years` | `academic_years`, `activity_logs` | Creates and activates academic years (`2024-2025`, `2025-2026`). |
| 1.5 | **Policy & Terms** | Attendance Rules & Condonation | - | `superadminattendancerules.php`, `views/manage_attendance_rules.php`, `attendancerules.class.php` | `attendance_rules`, `regulations` | `attendance_rules`, `activity_logs` | Establishes detention/condonation percentage thresholds per regulation. |
| 1.6 | **Structure** | Academic Regulations & Statutory Framework | - | `superadminregulations.php`, `superadminregulationdetails.php`, `views/manage_regulations.php`, `regulations.class.php` | `regulations`, `programs`, `regulation_course_categories`, `regulation_course_types`, `academic_settings` | `regulations`, `regulation_course_categories`, `regulation_course_types`, `academic_settings`, `activity_logs` | Defines degree regulations (R20, R23, R25, R26), statutory ceilings (duration, degree/LES/honors/minors credits, gap year, internal improvement), category credit targets, course evaluation types, and regulation cloning. |
| 1.7 | **Structure** | Degree Programs Management | - | `superadminprograms.php`, `programs.class.php` | `programs` | `programs`, `activity_logs` | Configures degree programs (B.Tech, M.Tech, MCA) and their standard duration. |
| 1.8 | **Structure** | Academic Departments | - | `superadmindepts.php`, `departments.class.php` | `departments` | `departments`, `activity_logs` | Manages college departments (Civil, EEE, MECH, ECE, CSE, Chem, Humanities, etc.). |
| 1.9 | **Structure** | Branch Specializations | - | `superadminspecs.php`, `departments.class.php`, `programs.class.php` | `specialization`, `departments`, `programs` | `specialization`, `activity_logs` | Creates specialization branches mapped to programs and departments. |
| 1.10 | **Classes & OBE** | Bell Schedule & Class Timings | - | `superadminclasstimings.php`, `timetable.class.php` | `class_timings`, `class_timing_schedule`, `programs` | `class_timings`, `class_timing_schedule`, `activity_logs` | Defines institutional period timing slots and associates schedules with class cohorts. |
| 1.11 | **Classes & OBE** | Cohort & Section Generation | - | `superadminclasses.php`, `superadminprogramclasses.php`, `superadmin.class.php` | `classes`, `specialization`, `regulations`, `student_batches`, `academic_years` | `classes`, `activity_logs` | Generates official class sections (`<Spec> - <YearSem> - Sec <Section>`) for active terms. |
| 1.12 | **Classes & OBE** | Program Outcomes (PO/PSO) Master | `MOD_CO_PO` | `superadminpopso.php`, `includes/popso_handlers.php`, `includes/popso_config.php`, `views/popso_*.php` | `po_pso`, `departments`, `programs`, `regulations`, `specialization` | `po_pso`, `activity_logs` | Curates NBA PO1-PO12 and PSO1-PSO4 statements per regulation and specialization. |
| 1.13 | **Governance** | System Administrators | - | `superadminadmins.php`, `adminuser.class.php` | `users` | `users`, `activity_logs` | Provisions institutional Admin (Principal) and Academic Section accounts. |
| 1.14 | **Security** | SuperAdmin Password Change | - | `superadminchgpwd.php`, `user.class.php` | `users` | `users`, `activity_logs` | Updates password hash for SuperAdmin login account. |
| 1.15 | **Cohorts & Batches** | Student Batches & Cohort Governance | `MOD_STUDENT_BATCHES` | `superadminbatches.php`, `services/BatchOBEService.php` | `batches`, `programs`, `classes` | `batches`, `classes`, `activity_logs` | Governs multi-year graduation cohorts, program duration, and cohort section mappings. |
| 1.16 | **Accreditation & OBE** | Master Vision, Mission, PEOs & Cohort Inheritance | `MOD_BATCH_GOVERNANCE` | `superadminbatchobe.php`, `services/BatchOBEService.php` | `student_batches`, `departments`, `vision_mission`, `peos`, `peo_mission_mapping`, `po_peo_mapping`, `batch_peo_targets`, `po_pso` | `vision_mission`, `peos`, `peo_mission_mapping`, `po_peo_mapping`, `batch_peo_targets`, `activity_logs` | Formulates master Vision/Mission, PEOs, PEO-Mission & PO-PEO articulation, cohort inheritance, and multi-year macro-attainment rollups. |


---

## 2. Admin Role (The Principal)
**Context**: The Institutional Leader and Registrar. The Principal monitors college-wide teaching, oversees departmental operations (HOD, Faculty, Students), monitors attendance & feedback, and performs audited credential maintenance.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 2.1 | **Operations** | Executive Principal Dashboard | - | `adminhome.php`, `adminmenu.php`, `adminheader.php`, `adminfooter.php` | `departments`, `classes`, `faculties`, `students`, `attendance`, `attendance_rules` | `activity_logs` | High-level operational cockpit displaying department rosters and today's metrics. |
| 2.2 | **Departments** | Department Operations Center | - | `adminfacst.php`, `departments.class.php` | `departments`, `users`, `faculties`, `classes` | None | Core operational gateway organizing HOD profiles, faculty lists, and classes by department. |
| 2.3 | **Departments** | HOD Assignment & Profile | - | `hod_profile.php`, `edit_hod.php`, `admin.class.php` | `users`, `departments`, `faculties` | `users`, `activity_logs` | Assigns or updates the Head of Department for each academic branch. |
| 2.4 | **Departments** | Faculty Onboarding & Profiles | - | `adminfac.php`, `adminaddfaculty.php`, `admineditfaculty.php`, `adminfacultyprofile.php`, `admin.class.php` | `faculties`, `users`, `departments`, `faculty_sub`, `subjects`, `classes` | `faculties`, `users`, `activity_logs` | Provisions faculty user accounts, edits designations, and views instructional profiles. |
| 2.5 | **Departments** | Department Classes & Rosters | - | `adminviewclasses.php`, `adminviewstudents.php` | `classes`, `specialization`, `departments`, `regulations`, `students` | None | Inspects active class cohorts, admitted student rolls, and enrollment counts. |
| 2.6 | **Departments** | Student Enrollment & Management | - | `adminenrollstudents.php`, `adminuploadst.php`, `adminunenrollstudents.php`, `editstudent.php`, `admin.class.php` | `students`, `users`, `classes`, `student_batches`, `student_sub`, `subjects` | `students`, `users`, `student_sub`, `activity_logs` | Enrolls individual students or bulk uploads CSV lists, creating `users` records. |
| 2.7 | **Departments** | Faculty & Student Subject Mapping | - | `adminfac.php`, `adminenrollstudents.php`, `admin.class.php` | `faculty_sub`, `student_sub`, `subjects`, `classes`, `faculties`, `students` | `faculty_sub`, `student_sub`, `activity_logs` | Maps faculty to course offerings (`faculty_sub`) and students to elective rosters (`student_sub`). |
| 2.8 | **Attendance** | Institutional Attendance Auditing | `MOD_ATTENDANCE` | `adminshowallclsattendance.php`, `module_showallcls_*.php`, `adminshowclsattendance.php`, `adminshowfacattendance.php`, `download_attendance.php`, `export_attendance_excel.php` | `attendance`, `diary`, `classes`, `subjects`, `students`, `faculties`, `attendance_rules`, `faculty_sub`, `student_sub` | None | Audits college-wide class attendance (combined, enhanced, subject-wise), faculty engagement, and exports. |
| 2.9 | **Analytics** | OBE & CIA Executive Analysis | `MOD_OBE_ANALYSIS` | `adminciaanalysis2.php`, `ciaanalysis2_common.php`, `ciaanalysis2_common_chart.php`, `LearningAnalytics`, `OBEAnalysisPDFService` | `internal_assessments`, `internal_assessment_marks`, `uglab_internal_assessment_marks`, `pg_internal_assessment_marks`, `ugproject_internal_assessment_marks`, `external_assessment_marks`, `course_outcomes`, `co_po_mapping`, `po_pso`, `assessment_components`, `assessment_questions`, `question_co_mapping`, `classes`, `subjects`, `students`, `faculties`, `academic_settings` | None | Reviews multi-level direct/indirect CO-PO attainment curves, Mid marks distributions, and exports dossiers. |
| 2.10 | **Quality & Surveys** | Student Feedback Oversight | `MOD_FEEDBACK` | `adminshowfeedbackstatus.php`, `adminviewfeedback.php`, `modulefeedback.php`, `FeedbackService`, `EnhancedPDFService`, `FeedbackExcelService` | `student_faculty_feedback`, `student_co_feedback`, `student_ces_feedback`, `subject_questionnaire_questions`, `student_questionnaire_responses`, `classes`, `subjects`, `faculties`, `students`, `departments` | None | Real-time response tracking and institutional feedback analysis (by Class, Subject, Faculty, Dept). |
| 2.11 | **Accounts** | Audited Staff Password Reset | - | `adminresetfacultypwd.php`, `admin.class.php` | `users`, `faculties`, `departments` | `users`, `activity_logs` | Resets faculty passwords with mandatory audit justification remarks. |
| 2.12 | **Accounts** | Audited Student Password Reset | - | `adminresetstudentpwd.php`, `admin.class.php` | `users`, `students`, `classes` | `users`, `activity_logs` | Resets student passwords with mandatory audit justification remarks. |
| 2.13 | **Security** | Principal Password Change | - | `adminchgpwd.php`, `user.class.php` | `users` | `users`, `activity_logs` | Updates password hash for the logged-in Principal. |
| 2.14 | **Student Records** | Student Dossier & Vault Verification | `MOD_STUDENT_PROFILE` | `adminstudentprofiles.php`, `services/StudentProfileService.php` | `students`, `student_profiles`, `student_documents`, `classes` | `student_documents`, `activity_logs` | Reviews student demographic dossiers and audits uploaded scanned certificates. |
| 2.15 | **Custody & Certificates** | Original Certificates Custody Ledger & Certificates | `MOD_STUDENT_PROFILE` | `admincustodyledger.php`, `services/StudentProfileService.php` | `students`, `student_custodial_records`, `student_certificate_requests`, `classes` | `student_custodial_records`, `student_certificate_requests`, `activity_logs` | Tracks physical custody of original certificates, temporary checkouts, and approves statutory certificate applications. |


---

## 3. Academic Section Role
**Context**: Central Academic Branch and Examination Center. Manages the official course catalog, campus spatial infrastructure, regulation syllabi, and performs centralized attendance monitoring.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 3.1 | **Operations** | Academic Section Dashboard | - | `academicsectionhome.php`, `academicsectionmenu.php`, `academicsectionheader.php`, `academicsectionfooter.php` | `curriculum_subjects`, `regulations`, `buildings`, `halls` | `activity_logs` | Central dashboard with shortcuts to course catalog, course structure, spatial infrastructure, and syllabi. |
| 3.2 | **Curriculum** | Master Curriculum Subjects | - | `academicsectioncurriculumsubjects.php`, `curriculum_subject.class.php`, `curriculum_subject_ajax.php` | `curriculum_subjects`, `regulations`, `departments`, `programs`, `specialization`, `regulation_course_types` | `curriculum_subjects`, `activity_logs` | Manages authoritative curriculum catalog with auto-calculated credits ($C = L + T + 0.5P$), auto-defaulted CIE/SEE marks from course types, elective tracks, delivery modes, and master COs/matrix. |
| 3.3 | **Infrastructure** | Campus Buildings & Exam Halls | - | `academicsectionmanagebuildings.php`, `academicsection.class.php`, `managebuildings_public.php` | `buildings`, `halls` | `buildings`, `halls`, `activity_logs` | Manages physical campus buildings, examination halls, seating capacity, and coordinates. |
| 3.4 | **Curriculum** | Syllabus Document Repository | - | `academicsectionsyllabus.php`, `syllabus.class.php` | `syllabus`, `regulations`, `programs`, `departments`, `specialization` | `syllabus`, `activity_logs` | Uploads and maintains regulation curriculum books and detailed syllabus PDF documents. |
| 3.5 | **Curriculum** | Academic Regulations Reference Hub | - | `academicsectionregulations.php`, `views/academic_regulations_hub.php`, `regulations.class.php` | `regulations`, `programs`, `regulation_course_categories`, `regulation_course_types` | None | Regulations reference console displaying degree duration, credit ceilings, category balances, and linking directly to Course Structure and Catalog. |
| 3.6 | **Attendance** | Central Attendance Monitoring | `MOD_ATTENDANCE` | `academicsectionshowallclsattendance.php`, `module_showallcls_*.php`, `attendance_report_helpers.php` | `attendance`, `classes`, `subjects`, `students`, `attendance_rules` | None | Audits university-wide class attendance rosters for condonation and detention scrutiny. |
| 3.7 | **Accounts** | Audited Faculty Password Reset | - | `academicsectionresetfacultypwd.php`, `academicsection.class.php` | `users`, `faculties` | `users`, `activity_logs` | Secondary registry office for resetting faculty passwords with audit logs. |
| 3.8 | **Accounts** | Audited Student Password Reset | - | `academicsectionresetstudentpwd.php`, `academicsection.class.php` | `users`, `students`, `classes` | `users`, `activity_logs` | Secondary registry office for resetting student passwords with audit logs. |
| 3.9 | **Security** | Academic Section Password Change | - | `academicsectionchgpwd.php`, `user.class.php` | `users` | `users`, `activity_logs` | Updates password hash for the Academic Section user account. |
| 3.10 | **Examination Branch** | Semester Results Publication & CSV Ingestion | `MOD_RESULTS_PUBLICATION` | `academicsectionresults.php`, `services/ExamResultsService.php` | `exam_notifications`, `exam_results`, `academic_years`, `regulations` | `exam_notifications`, `exam_results`, `activity_logs` | Ingests university CSV result rosters, computes UGC 10-point scale grades, and publishes notifications. |
| 3.11 | **Curriculum** | Autonomous Course Structure & BoS Engine | - | `academicsectioncoursestructure.php`, `curriculum_subject.class.php` | `curriculum_subjects`, `regulations`, `programs`, `specialization`, `regulation_course_categories`, `regulation_course_types` | None | Interactive semester-by-semester course structure roadmap (8 sems UG / 4 sems PG), statutory category compliance cards, elective vertical track grouping, and Board of Studies (BoS) printable dossier & CSV export. |


---

## 4. Head of Department (HOD) Role
**Context**: Departmental Academic Executive. Allocates courses, coordinates weekly bell schedules, governs attendance deletion requests, signs off on syllabus audits, and monitors departmental quality.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 4.1 | **Operations** | Department Cockpit & Dashboard | - | `hodhome.php`, `hodmenu.php`, `hodheader.php`, `hodfooter.php` | `departments`, `faculties`, `classes`, `attendance_delete_requests`, `diary`, `attendance` | `activity_logs` | Daily departmental overview of classes conducted, pending deletions, and faculty roster. |
| 4.2 | **Timetable** | Department Timetable Coordination | - | `hodmanage_timetable.php`, `timetable.class.php`, `faculty_weekly_timetable.php` | `classes`, `subjects`, `faculties`, `class_timings`, `timing_templates`, `class_timetables` | `class_timings`, `timing_templates`, `class_timetables`, `activity_logs` | Builds weekly timetable grid, assigning subject periods to faculty without room/slot collisions. |
| 4.3 | **Workload** | Faculty Course Allocation | - | `hodmapfaculty.php`, `hodviewfaculties.php`, `hodfacultyprofile.php`, `hodeditfaculty.php`, `hod.class.php` | `faculty_sub`, `faculties`, `users`, `subjects`, `classes` | `faculty_sub`, `faculties`, `activity_logs` | Allots departmental subjects to instructors and monitors teaching workloads. |
| 4.4 | **Workload** | Student Elective & Batch Mapping | - | `hodmapstudents.php`, `hodviewstudents.php`, `hod.class.php` | `student_sub`, `students`, `subjects`, `classes` | `student_sub`, `activity_logs` | Maps student cohorts to elective course offerings and laboratory batches. |
| 4.5 | **Governance** | Attendance Deletion Approval Gate | `MOD_ATT_REQUESTS` | `hodviewdelrequests.php`, `hodviewattendance_todel.php`, `hod.class.php` | `attendance_delete_requests`, `attendance`, `diary`, `faculties`, `subjects`, `classes` | `attendance_delete_requests` (UPDATED), `attendance` (DELETED), `diary` (DELETED), `fac_activity_logs`, `activity_logs` | Multi-step review gate approving or rejecting faculty requests to purge mistaken attendance hours. |
| 4.6 | **Attendance** | Department Attendance Oversight | `MOD_ATTENDANCE` | `hodshowallclsattendance.php`, `hodshowclsattendance.php`, `hodshowfacattendance.php`, `hodshowattendance.php`, `module_showallcls_*.php` | `attendance`, `diary`, `classes`, `subjects`, `faculties`, `students`, `attendance_rules` | None | Period-by-period attendance scrutiny and faculty instructional compliance audit. |
| 4.7 | **Attendance** | Substitute Attendance Marking | `MOD_ATTENDANCE` | `hodfacaddattendance.php`, `subject.class.php` | `faculty_sub`, `subjects`, `classes`, `students`, `attendance` | `attendance`, `diary`, `fac_activity_logs` | Allows HOD to directly record class attendance in emergency or substitute scenarios. |
| 4.8 | **Permissions** | Student Activity Duty Leaves | - | `hodmanagepermissions.php` | `student_permissions`, `students`, `classes` | `student_permissions`, `activity_logs` | Grants approved attendance exemptions (Sports, NCC, Placement Drives, Medical). |
| 4.9 | **Curriculum** | Department Classes & Subjects View | - | `hodviewclasses.php`, `hodviewsubjects.php`, `viewallsubjects.php` | `classes`, `subjects`, `curriculum_subjects`, `specialization` | None | Reviews semester-wise course offerings, syllabus codes, and section assignments. |
| 4.10 | **Assessments** | Department CIA Marks Review | `MOD_CIA_MARKS` | `hodshowcls_cia.php`, `download_cls_cia.php` | `internal_assessments`, `internal_assessment_marks`, `uglab_internal_assessment_marks`, `pg_internal_assessment_marks`, `ugproject_internal_assessment_marks`, `classes`, `subjects`, `students` | None | Reviews Mid-1 and Mid-2 internal assessment marks ledgers across departmental classes. |
| 4.11 | **Analytics** | Department OBE Attainment Analytics | `MOD_OBE_ANALYSIS` | `hodciaanalysis2.php`, `ciaanalysis2_common.php`, `LearningAnalytics`, `OBEAnalysisPDFService` | `internal_assessments`, `internal_assessment_marks`, `external_assessment_marks`, `course_outcomes`, `co_po_mapping`, `po_pso`, `assessment_components`, `assessment_questions`, `question_co_mapping`, `classes`, `subjects`, `students`, `academic_settings` | None | Department-wide Course Outcome direct/indirect attainment and NBA compliance analytics. |
| 4.12 | **Quality & Surveys** | Department Feedback Appraisal | `MOD_FEEDBACK` | `hodviewfeedback.php`, `modulefeedback.php`, `FeedbackService`, `EnhancedPDFService`, `FeedbackExcelService` | `student_faculty_feedback`, `student_co_feedback`, `student_ces_feedback`, `subject_questionnaire_questions`, `student_questionnaire_responses`, `classes`, `subjects`, `faculties`, `students` | None | Analyzes Faculty Appraisals, CO surveys, and Course End Surveys for departmental faculty. |
| 4.13 | **Security** | HOD Password Change | - | `hodchgpwd.php`, `user.class.php` | `users` | `users`, `activity_logs` | Updates password hash for the logged-in HOD account. |

---

## 5. Faculty Role
**Context**: Classroom Course Instructor and Academic Evaluator. Formulates lecture delivery schedules, logs daily student attendance with zero friction, marks internal and external exams, reconciles lesson plans, and analyzes learning outcomes.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 5.1 | **Operations** | Faculty Coursework Dashboard | - | `fachome.php`, `facmenu.php`, `facheader.php`, `facfooter.php` | `faculties`, `users`, `faculty_sub`, `subjects`, `classes`, `academic_settings`, `system_feature_modules`, `attendance_delete_requests`, `attendance` | `activity_logs` | Central dashboard showing today's teaching schedule, active subjects, and pending actions. |
| 5.2 | **Attendance** | Frictionless Daily Attendance & Diary | `MOD_ATTENDANCE` & `MOD_CLASS_DIARY` | `facaddattendance.php`, `facadddairy.php`, `faculty.class.php`, `subject.class.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `attendance`, `diary`, `attendance_rules` | `attendance` (INSERT), `diary` (INSERT), `fac_activity_logs` (INSERT) | Records hourly student attendance with free-text syllabus delivery topic notes. |
| 5.3 | **Attendance** | Grouped / Combined Attendance | `MOD_GROUPED_ATT` | `facaddgroupedattendance.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `attendance` | `attendance` (INSERT), `diary` (INSERT), `fac_activity_logs` (INSERT) | Concurrently records attendance across combined elective sections or grouped classes. |
| 5.4 | **Attendance** | Exceptional / Activity Attendance | `MOD_ATTENDANCE` | `facexceptionalattendance.php` | `faculty_sub`, `student_sub`, `students`, `student_permissions`, `attendance` | `attendance` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Retroactively marks attendance for students possessing sanctioned duty exemptions. |
| 5.5 | **Attendance** | Attendance Verification & Audit | `MOD_ATTENDANCE` | `facshowattendance.php`, `facupdatestudentattendance.php` | `attendance`, `diary`, `faculty_sub`, `student_sub`, `students`, `subjects`, `classes` | `attendance` (UPDATE), `fac_activity_logs` (INSERT) | Displays period attendance register, calculates subject percentages, and updates individual records. |
| 5.6 | **Attendance** | Deletion / Correction Request Submission | `MOD_ATT_REQUESTS` | `facadddelattrequest.php`, `facshowdelattrequests.php` | `attendance`, `diary`, `faculty_sub`, `subjects`, `classes`, `attendance_delete_requests` | `attendance_delete_requests` (INSERT), `fac_activity_logs` (INSERT) | Submits formal correction/purge requests to the HOD for mistakenly logged attendance hours. |
| 5.7 | **Lesson Plan** | Lesson Plan Formulation & Upload | `MOD_LESSON_PLAN` | `facaddlessonplan.php`, `services/LessonPlanService.php` | `faculty_sub`, `subjects`, `classes`, `course_outcomes`, `blooms_levels`, `lesson_plans` | `lesson_plans` (INSERT/UPDATE/DELETE), `fac_activity_logs` (INSERT) | Drafts structured unit-wise lecture plans or processes bulk CSV uploads. |
| 5.8 | **Lesson Plan** | Lesson Plan Reconciliation & Audit | `MOD_LESSON_PLAN` | `faclessonplanreconciliation.php`, `services/LessonPlanService.php` | `lesson_plans`, `diary`, `attendance`, `course_completion_audits`, `course_outcomes`, `classes`, `subjects` | `lesson_plans` (UPDATE mapped hour), `course_completion_audits` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | End-of-course reconciliation mapping teaching diary to planned topics; submits NBA audit for HOD sign-off. |
| 5.9 | **Assessments** | Theory Internal Assessment (CIA) | `MOD_CIA_MARKS` | `facaddciamarks.php`, `faceditciamarks.php`, `facviewciamarks.php`, `facciamarkscondensed.php`, `ciamarks.class.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `internal_assessments`, `internal_assessment_marks`, `assessment_components`, `academic_settings` | `internal_assessments` (INSERT), `internal_assessment_marks` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Records subjective, objective, and assignment marks for Mid-1 and Mid-2. |
| 5.10 | **Assessments** | Laboratory CIA Marks | `MOD_CIA_MARKS` | `facaddcialabmarks.php`, `faceditcialabmarks.php`, `facviewcialabmarks.php`, `facuglabciamarkscondensed.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `uglab_internal_assessment_marks` | `uglab_internal_assessment_marks` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Records Day-to-Day evaluation and Internal Lab Test marks for practical courses. |
| 5.11 | **Assessments** | PG Coursework CIA Marks | `MOD_CIA_MARKS` | `facaddpgciamarks.php`, `faceditpgciamarks.php`, `facviewpgciamarks.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `pg_internal_assessment_marks` | `pg_internal_assessment_marks` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Records postgraduate continuous internal assessment scores. |
| 5.12 | **Assessments** | Project Work CIA Marks | `MOD_CIA_MARKS` | `facaddprojectciamarks.php`, `facviewprojectciamarks.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `ugproject_internal_assessment_marks` | `ugproject_internal_assessment_marks` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Records undergraduate/postgraduate project review evaluation marks. |
| 5.13 | **Assessments** | CIA Exam Question Attachments | `MOD_CIA_METADATA` | `facciaattachments.php` | `cia_attachments`, `internal_assessments` | `cia_attachments` (INSERT/DELETE), `fac_activity_logs` (INSERT) | Uploads question papers, answer schemes, and sample answer scripts. |
| 5.14 | **Assessments** | Semester End Examination (SEE) | `MOD_SEE_MARKS` | `facseemarks.php`, `facseemarksentry.php`, `facseedirectmarks.php`, `facseecompques.php`, `facseemarkscondensed.php`, `seeassessment.class.php`, `services/ExamResultsService.php` | `faculty_sub`, `student_sub`, `students`, `subjects`, `classes`, `external_assessment_marks`, `exam_results`, `exam_notifications` | `external_assessment_marks` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Records external university marks via Mode A (question-level), Mode B (direct ledger), or Mode C (1-click auto-sync from published examination results). |

| 5.15 | **OBE Setup** | Course Outcomes & Matrix Formulation | `MOD_CO_PO` | `facaddcos.php`, `facarticulationmatrix.php`, `courseoutcome.class.php` | `course_outcomes`, `co_po_mapping`, `po_pso`, `faculty_sub`, `subjects`, `classes` | `course_outcomes` (INSERT/UPDATE/DELETE), `co_po_mapping` (INSERT/UPDATE/DELETE), `fac_activity_logs` (INSERT) | Formulates CO1-CO6 and populates the CO-PO/PSO articulation correlation matrix (weights 1, 2, 3). |
| 5.16 | **OBE Setup** | Assessment Question CO Mapping | `MOD_CIA_METADATA` | `facciacomp.php`, `facciacompques.php`, `facquestionco.php`, `assessmentstructure.class.php` | `assessment_components`, `assessment_questions`, `question_co_mapping`, `course_outcomes`, `blooms_levels` | `assessment_components` (INSERT/UPDATE), `assessment_questions` (INSERT/UPDATE), `question_co_mapping` (INSERT/UPDATE), `fac_activity_logs` (INSERT) | Maps exam questions to specific Course Outcomes and Bloom's taxonomy cognitive levels. |
| 5.17 | **OBE Analytics** | Attainment & CIA Analysis | `MOD_OBE_ANALYSIS` | `facciaanalysis2.php`, `ciaanalysis2_common.php`, `ciaanalysis2_common_chart.php`, `LearningAnalytics`, `OBEAnalysisPDFService` | `internal_assessments`, `internal_assessment_marks`, `uglab_internal_assessment_marks`, `pg_internal_assessment_marks`, `external_assessment_marks`, `course_outcomes`, `co_po_mapping`, `po_pso`, `question_co_mapping`, `academic_settings`, `student_co_feedback`, `student_ces_feedback` | None | Calculates direct & indirect CO attainment, PO attainment vectors, and OBE compliance benchmarks. |
| 5.18 | **Accreditation** | e-Bluebook & OBE Dossier Export | `MOD_OBE_ANALYSIS` | `download_bluebook.php`, `download_obe_analysis.php`, `services/EBluebookPDFService.php`, `services/OBEAnalysisPDFService.php` | `attendance`, `diary`, `lesson_plans`, `internal_assessments`, `internal_assessment_marks`, `external_assessment_marks`, `course_outcomes`, `co_po_mapping`, `po_pso`, `assessment_components`, `assessment_questions`, `question_co_mapping`, `course_completion_audits`, `faculties`, `subjects`, `classes` | None | Generates statutory official 6-Section Course e-Bluebook and comprehensive NBA Course Dossier PDFs. |
| 5.19 | **Quality & Surveys** | Student Feedback Appraisal | `MOD_FEEDBACK` | `facviewfeedback.php`, `modulefeedback.php`, `FeedbackService`, `EnhancedPDFService`, `FeedbackExcelService` | `student_faculty_feedback`, `student_co_feedback`, `student_ces_feedback`, `subject_questionnaire_questions`, `student_questionnaire_responses` | None | Inspects indirect Course Outcome survey ratings, 5-domain CES, and anonymous Faculty Appraisal scores. |
| 5.20 | **Timetable** | Weekly Teaching Timetable | - | `faculty_weekly_timetable.php`, `timetable.class.php` | `classes`, `subjects`, `faculties`, `class_timings`, `timing_templates` | None | Views personal weekly bell schedule and assigned lecture hours. |
| 5.21 | **Security** | Faculty Password Change | - | `facchgpwd.php`, `user.class.php` | `users` | `users`, `activity_logs` | Updates password hash for the logged-in faculty member. |

---

## 6. Student Role (`jntuaceastudents`)
**Context**: Learner and Course Participant. Interacts with the independent student portal (`jntuaceastudents/`) to monitor daily schedules, verify attendance percentages, view timetables, and participate in anonymous 3-tier feedback surveys.

| # | Feature Category | Feature Name | Feature Toggle Module | Key PHP Files (Controllers / Views / Services) | Database Tables Read | Database Tables Written (INSERT / UPDATE / DELETE) | Operational Description |
|---|---|---|---|---|---|---|---|
| 6.1 | **Authentication** | Student Sign-In | - | `index.php`, `user.class.php`, `logs.class.php` | `users`, `students`, `classes` | `activity_logs` (INSERT) | Authenticates student roll number credentials against `users` (`status = 1`). |
| 6.2 | **Dashboard** | Today's Schedule & Cockpit | - | `studenthome.php`, `studentmenu.php`, `stheader.php`, `stfooter.php` | `students`, `classes`, `student_sub`, `subjects`, `faculties`, `attendance`, `class_timings`, `timing_templates` | None | Displays today's schedule, enrolled subjects, quick links, and attendance alerts. |
| 6.3 | **Academics** | Enrolled Subjects & Faculty | - | `studentsubjects.php`, `services/StudentRepository.php` | `student_sub`, `subjects`, `faculty_sub`, `faculties`, `classes` | None | Displays registered theory and lab courses along with instructor contact details. |
| 6.4 | **Attendance** | Subject Attendance Breakdown | `MOD_ATTENDANCE` | `studentsubatt.php`, `services/StudentAttendanceService.php` | `attendance`, `diary`, `subjects`, `classes`, `students`, `attendance_rules` | None | Shows conducted hours, attended hours, percentage, monthly trend, and daily diary topics. |
| 6.5 | **Timetable** | Weekly Class Timetable | - | `studenttimetable.php`, `timetable.class.php` | `classes`, `subjects`, `faculties`, `class_timings`, `timing_templates` | None | Renders the class cohort's official weekly bell schedule and room allocations. |
| 6.6 | **Quality & Surveys** | Feedback Survey Hub | `MOD_FEEDBACK` | `studentfeedback.php`, `studentfeedbacksubjects.php`, `services/StudentFeedbackService.php` | `student_sub`, `subjects`, `faculty_sub`, `faculties`, `classes`, `student_faculty_feedback`, `student_co_feedback`, `student_ces_feedback` | None | Lists enrolled subjects requiring feedback and tracks completion status across 3 survey types. |
| 6.7 | **Quality & Surveys** | Faculty Teaching Appraisal Survey | `MOD_FEEDBACK` | `student_faculty_feedback.php`, `services/StudentFeedbackService.php` | `subject_questionnaire_questions`, `student_faculty_feedback`, `student_sub` | `student_faculty_feedback` (INSERT), `student_questionnaire_responses` (INSERT/UPDATE), `activity_logs` (INSERT) | Submits anonymous teaching appraisal ratings across 10 structured pedagogic questions. |
| 6.8 | **Quality & Surveys** | Course Outcome (CO) Attainment Survey | `MOD_FEEDBACK` | `student_co_feedback.php`, `services/StudentFeedbackService.php` | `course_outcomes`, `student_co_feedback`, `student_sub` | `student_co_feedback` (INSERT/UPDATE), `activity_logs` (INSERT) | Submits indirect attainment ratings (1 to 5) for each Course Outcome statement (CO1-CO6). |
| 6.9 | **Quality & Surveys** | Course End Survey (CES) | `MOD_FEEDBACK` | `student_ces_feedback.php`, `services/StudentFeedbackService.php` | `student_ces_feedback`, `student_sub` | `student_ces_feedback` (INSERT), `activity_logs` (INSERT) | Submits end-of-semester curriculum survey covering 5 institutional quality domains. |
| 6.10 | **Dossier & Vault** | Student Biographical Profile & Cloud Vault | `MOD_STUDENT_PROFILE` | `studentprofile.php`, `services/StudentProfileService.php` | `students`, `student_profiles`, `student_documents` | `student_profiles` (INSERT/UPDATE), `student_documents` (INSERT/DELETE), `activity_logs` (INSERT) | Manages demographic records and uploads statutory documents (Aadhaar, SSC, Inter/Diploma, EAMCET/ECET, Category, Income). |
| 6.11 | **Examination** | Semester Results & SGPA Grade Cards | `MOD_RESULTS_PUBLICATION` | `studentresults.php`, `services/ExamResultsService.php` | `exam_results`, `exam_notifications` | None | Displays official published semester exam scores, letter grades, grade points, and dynamic SGPA card. |
| 6.12 | **Certificates** | Statutory Certificate Requests & Issuance | `MOD_STUDENT_PROFILE` | `studentcertificates.php`, `student_print_certificate.php`, `services/StudentProfileService.php` | `student_certificate_requests`, `student_custodial_records`, `student_profiles`, `students` | `student_certificate_requests` (INSERT), `activity_logs` (INSERT) | Applies for official institutional certificates (Bonafide, Conduct, Custodial, TC) and prints approved tamper-evident certificates. |
| 6.13 | **Knowledgebase** | Student Help Portal | - | `stknowledgebase.php` | None | None | Student guidance manual explaining portal navigation, attendance criteria, and surveys. |
| 6.14 | **Security** | Student Password Change | - | `stchgpwd.php`, `user.class.php` | `users` | `users` (UPDATE), `activity_logs` (INSERT) | Allows students to securely update their login credentials. |
| 6.15 | **Authentication** | Student Logout | - | `logout.php` | None | `activity_logs` (INSERT) | Terminates active student session and redirects to sign-in page. |

---

## 7. Comprehensive Database Table Utilization Matrix

The following master matrix cross-references all **66 database tables** against the **6 roles**, detailing whether each role performs **Read** operations ($\mathbf{R}$), **Write** operations ($\mathbf{W}$ = INSERT/UPDATE/DELETE), or has **No Access** ($-$).

| # | Table Name | SuperAdmin | Admin (Principal) | Academic Section | HOD | Faculty | Student | Primary Functional Scope |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|---|
| 1 | `academic_settings` | **R / W** | **R** | **R** | **R** | **R** | - | Autonomous regulation policy engine (CIA splits, OBE targets) |
| 2 | `academic_settings_audit` | **R / W** | **R** | - | - | - | - | Audit trail of changes made to academic settings |
| 3 | `academic_years` | **R / W** | **R** | **R** | **R** | **R** | - | Academic terms repository (`2024-2025`, `2025-2026`) |
| 4 | `activity_logs` | **R / W** | **R / W** | **R / W** | **R / W** | **R / W** | **W** | Core system-wide authentication and transaction audit log |
| 5 | `assessment_components` | - | **R** | - | **R** | **R / W** | - | Assessment subdivisions (Mid-1, Mid-2, Quizzes, Assignments) |
| 6 | `assessment_questions` | - | **R** | - | **R** | **R / W** | - | Individual questions mapped within assessment components |
| 7 | `attendance` | - | **R** | **R** | **R / W** | **R / W** | **R** | Primary hourly class attendance transaction records |
| 8 | `attendance_delete_requests` | - | **R** | - | **R / W** | **R / W** | - | Multi-level workflow for attendance deletion/correction |
| 9 | `attendance_rules` | **R / W** | **R** | **R** | **R** | **R** | **R** | Regulation-specific detention and condonation thresholds |
| 10 | `blooms_levels` | **R** | **R** | - | **R** | **R** | - | Bloom's taxonomy cognitive levels (L1 Remember to L6 Create) |
| 11 | `buildings` | **R** | **R** | **R / W** | **R** | - | - | Campus spatial infrastructure (Academic blocks & facilities) |
| 12 | `cia_attachments` | - | **R** | - | **R** | **R / W** | - | Question paper and answer key document uploads for CIA |
| 13 | `class_timing_schedule` | **R / W** | **R** | - | **R** | **R** | - | Temporal validity mapping linking classes to bell schedules |
| 14 | `class_timings` | **R / W** | **R** | - | **R / W** | **R** | **R** | Master institutional period slots and start/end time hours |
| 15 | `classes` | **R / W** | **R** | **R** | **R** | **R** | **R** | Academic cohorts / sections per regulation & specialization |
| 16 | `co_po_mapping` | **R** | **R** | - | **R** | **R / W** | - | Course Outcome to Program Outcome correlation weights (1, 2, 3) |
| 17 | `course_completion_audits` | - | **R** | - | **R / W** | **R / W** | - | End-of-course syllabus delivery audit and NBA sign-offs |
| 18 | `course_outcomes` | **R** | **R** | - | **R** | **R / W** | **R** | Course Outcome statements (CO1 to CO6) per subject |
| 19 | `curriculum_subjects` | **R** | **R** | **R / W** | **R** | **R** | - | Master university course catalog with L-T-P-C credits |
| 20 | `departments` | **R / W** | **R** | **R** | **R** | **R** | - | Academic departments (ECE, CSE, MECH, EEE, etc.) |
| 21 | `diary` | - | **R** | - | **R / W** | **R / W** | **R** | Hourly teaching diary syllabus topics entered by faculty |
| 22 | `external_assessment_marks` | - | **R** | - | **R** | **R / W** | - | Semester End Examination (SEE) external marks ledgers |
| 23 | `fac_activity_logs` | - | **R** | - | **R / W** | **R / W** | - | Dedicated audit log tracking faculty attendance/marks entries |
| 24 | `faculties` | **R** | **R / W** | **R** | **R / W** | **R** | **R** | Faculty member profiles linking accounts to departments |
| 25 | `faculty_sub` | - | **R / W** | - | **R / W** | **R** | **R** | Workload allotment mapping faculty members to course offerings |
| 26 | `halls` | **R** | **R** | **R / W** | **R** | - | - | Physical examination halls, room numbers, and capacities |
| 27 | `internal_assessment_marks` | - | **R** | - | **R** | **R / W** | - | Theory CIA scores (Subjective, Objective, Assignment) |
| 28 | `internal_assessments` | - | **R** | - | **R** | **R / W** | - | Master internal assessment cycles (Mid-1, Mid-2) per subject |
| 29 | `lesson_plans` | - | **R** | - | **R** | **R / W** | - | Unit-wise lesson delivery plans and mapped diary hours |
| 30 | `pg_internal_assessment_marks` | - | **R** | - | **R** | **R / W** | - | Continuous internal assessment marks for PG courses |
| 31 | `po_pso` | **R / W** | **R** | - | **R** | **R** | - | NBA Program Outcomes (PO1-12) & PSOs (PSO1-4) statements |
| 32 | `programs` | **R / W** | **R** | **R** | **R** | **R** | - | Degree programs (B.Tech, M.Tech, MCA, etc.) |
| 33 | `question_co_mapping` | - | **R** | - | **R** | **R / W** | - | Assessment question mapping to COs and Bloom cognitive levels |
| 34 | `regulations` | **R / W** | **R** | **R** | **R** | **R** | - | Autonomous academic regulations (R20, R23, R25, R26) & statutory parameters |
| 34a | `regulation_course_categories` | **R / W** | - | **R** | - | - | - | Regulation category credit ceilings and target percentages (PC, PE, OE, etc.) |
| 34b | `regulation_course_types` | **R / W** | - | **R** | - | - | - | Regulation course types, CIE/SEE marks distribution, and evaluation schemes |
| 35 | `specialization` | **R / W** | **R** | **R** | **R** | **R** | - | Academic branches / disciplines under departments |
| 36 | `student_batches` | **R / W** | **R** | **R** | **R** | **R** | - | Admitted student cohorts tracking multi-year graduation |
| 37 | `student_ces_feedback` | - | **R** | - | **R** | **R** | **R / W** | Course End Survey (CES) student ratings across 5 domains |
| 38 | `student_co_feedback` | - | **R** | - | **R** | **R** | **R / W** | Indirect Course Outcome survey ratings (1 to 5) from students |
| 39 | `student_faculty_feedback` | - | **R** | - | **R** | **R** | **R / W** | Student teaching appraisal survey ratings for faculty |
| 40 | `student_marks` | - | **R** | - | **R** | **R** | - | Historical / legacy student marks storage |
| 41 | `student_permissions` | - | **R** | - | **R / W** | **R** | **R** | Sanctioned duty leave exemptions (Sports, NCC, Placement) |
| 42 | `student_questionnaire_responses` | - | **R** | - | **R** | **R** | **R / W** | Individual question ratings submitted in feedback surveys |
| 43 | `student_sub` | - | **R / W** | - | **R / W** | **R** | **R** | Enrollment roster mapping students to specific subjects/electives |
| 44 | `students` | **R** | **R / W** | **R** | **R** | **R** | **R** | Student profiles linking roll numbers to classes and batches |
| 45 | `subject_questionnaire_questions` | - | **R** | - | **R** | **R** | **R** | Standardized evaluation questions for student feedback |
| 46 | `subjects` | - | **R** | **R** | **R** | **R** | **R** | Active semester course offerings instantiated from curriculum |
| 47 | `system_feature_modules` | **R / W** | **R** | **R** | **R** | **R** | **R** | Feature toggle engine controlling 27 grounded system modules |
| 48 | `temp_internal_assessment_marks` | - | - | - | - | **R / W** | - | Staging table for bulk CIA theory marks upload |
| 49 | `temp_joiningdates` | - | **R / W** | - | - | - | - | Staging table for student date of joining reconciliation |
| 50 | `temp_pg_internal_assessment_marks` | - | - | - | - | **R / W** | - | Staging table for PG internal assessment marks import |
| 51 | `temp_uglab_internal_assessment_marks` | - | - | - | - | **R / W** | - | Staging table for UG Lab CIA marks CSV upload |
| 52 | `temp_ugproject_internal_assessment_marks` | - | - | - | - | **R / W** | - | Staging table for project review marks upload |
| 53 | `class_timetables` | - | - | - | **R / W** | - | - | Temporary storage for timetable schedule imports |
| 54 | `uglab_internal_assessment_marks` | - | **R** | - | **R** | **R / W** | - | Continuous assessment marks for UG laboratory courses |
| 55 | `ugproject_internal_assessment_marks` | - | **R** | - | **R** | **R / W** | - | Continuous evaluation marks for UG major/minor projects |
| 56 | `lab_rubric_templates` | **R / W** | **R** | **R** | **R / W** | **R** | - | Master repository of continuous lab evaluation rubric templates |
| 57 | `lab_rubric_criteria` | **R / W** | **R** | **R** | **R / W** | **R** | - | Assessment criteria and max marks per lab rubric template |
| 58 | `lab_subject_rubrics` | - | **R** | - | **R / W** | **R / W** | - | Course-specific rubric criteria customizations |
| 59 | `lab_rubric_evaluations` | - | **R** | - | **R** | **R / W** | - | Student criteria marks breakdowns for lab continuous evaluation |
| 60 | `course_cqi_actions` | **R** | **R** | **R** | **R / W** | **R / W** | - | CQI remedial action tracking plans and HOD review approvals |
| 61 | `vision_mission` | **R / W** | **R** | **R** | **R** | **R** | **R** | Master Institutional and Departmental Vision and Mission statements |
| 62 | `peos` | **R / W** | **R** | **R** | **R / W** | **R** | **R** | Master Program Educational Objectives anchored to departments/programs |
| 63 | `peo_mission_mapping` | **R / W** | **R** | **R** | **R / W** | **R** | - | Correlation articulation matrix between master PEOs and Mission statements |
| 64 | `po_peo_mapping` | **R / W** | **R** | **R** | **R / W** | **R** | - | Correlation articulation matrix between POs/PSOs and master PEOs |
| 65 | `batch_peo_targets` | **R / W** | **R** | **R** | **R / W** | **R** | - | Optional cohort-specific target score overrides for PEOs |
| 66 | `exam_notifications` | **R / W** | **R** | **R / W** | - | - | **R** | Examination release notifications and publication control |
| 67 | `exam_results` | **R / W** | **R** | **R / W** | - | **R** | **R** | End-semester student grades, marks, SGPA, and credit promotion |
| 68 | `student_profiles` | **R / W** | **R / W** | **R / W** | **R** | - | **R / W** | Master student biographical, parental, entrance, and address dossier |
| 69 | `student_documents` | **R / W** | **R / W** | **R / W** | **R** | - | **R / W** | Digital document vault storing verified educational credentials |
| 70 | `student_custodial_records` | **R / W** | **R / W** | **R / W** | - | - | **R** | Physical custody ledger for original certificates deposited during admission |
| 71 | `student_certificate_requests` | **R / W** | **R / W** | **R / W** | - | - | **R / W** | Statutory certificate requests with cryptographic QR validation |


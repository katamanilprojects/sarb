# Role & Permissions Matrix

This document provides a matrix of features, administrative capabilities, module dependencies, granular action permissions (`view`, `edit`, `approve`, `export`), and page access rules across all six primary system roles in **JNTUACEA** and **JNTUACEASTUDENTS**.

For the exhaustive file-by-file and 92-table read/write mapping, consult [user_feature_file_table_mapping.md](./user_feature_file_table_mapping.md).

---

## 1. Feature Access Matrix

| Feature / Capability | SuperAdmin | Admin (Principal) | Academic Section | HOD | Faculty | Student | Feature Toggle Module |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Autonomous Academic Settings** | ✅ Full | 👁️ Read | 👁️ Read | 👁️ Read | 👁️ Read | ❌ | - |
| **Regulation CIA Schemes & Policy Settings** | ✅ Full | 👁️ Read | ✅ Full | 👁️ Read | 👁️ Read | ❌ | `MOD_CIA_MARKS` |
| **Course Results Readiness Console** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Feature Toggle & Action RBAC (`role_module_permissions`)** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Attainment Profiles & Subject Overrides** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | `MOD_SEE_MARKS` |
| **AI Providers Configuration** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | `MOD_AI_ASSISTANCE` |
| **Academic Years Management** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Programs & Regulations Setup** | ✅ Full | ❌ | 👁️ Read | ❌ | ❌ | ❌ | - |
| **Department & Specialization Setup**| ✅ Full | 👁️ Read | ❌ | ❌ | ❌ | ❌ | - |
| **Class Timings Configuration** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Class Cohort & Section Creation** | ✅ Full | 👁️ Read | ❌ | ❌ | ❌ | ❌ | - |
| **Master Curriculum Catalog & COs** | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Autonomous Course Structure & BoS Engine** | ✅ Full | 👁️ Read | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Campus Buildings & Halls** | ❌ | ❌ | ✅ Full | ❌ | ❌ | ❌ | - |
| **Syllabus Document Management** | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Faculty Onboarding & Dept Sort Order** | ❌ | ✅ Full | ❌ | 👁️ Read | ❌ | ❌ | - |
| **Student Enrollment & Status** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | 👁️ Self | - |
| **Faculty Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ | ❌ | - |
| **Student Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ | ❌ | - |
| **Department Timetable Management** | ❌ | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | - |
| **Subject Allotment to Faculty** | ❌ | ✅ Full | ❌ | ✅ Full | ❌ | ❌ | - |
| **Student Elective/Batch Mapping** | ❌ | ✅ Full | ❌ | ✅ Full | ❌ | ❌ | - |
| **Daily Attendance Marking & Diary**| ❌ | ❌ | ❌ | ❌ | ✅ Full | 👁️ View | `MOD_ATTENDANCE` & `MOD_CLASS_DIARY` |
| **7-Day Student Attendance Correction** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_ATTENDANCE` |
| **Grouped / Combined Attendance** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_GROUPED_ATT` |
| **Exceptional Attendance Marking** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_ATTENDANCE` |
| **Attendance Deletion Submission** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_ATT_REQUESTS` |
| **Attendance Deletion Approval & Audit** | ✅ Full | 👁️ Read | ❌ | ✅ Approve | 👁️ Self | ❌ | `MOD_ATT_REQUESTS` |
| **Student Batches & Cohort Governance**| ✅ Full | 👁️ Read | 👁️ Read | 👁️ Read | ❌ | 👁️ Self | `MOD_BATCH_GOVERNANCE` |
| **Batch OBE, Vision/Mission & PEOs** | ✅ Full | 👁️ Read | ❌ | 👁️ Read | 👁️ Read | ❌ | `MOD_BATCH_GOVERNANCE` |
| **Batch Macro-Attainment Calculation**| ✅ Full | 👁️ Read | ❌ | 👁️ Read | 👁️ Read | ❌ | `MOD_BATCH_GOVERNANCE` |
| **Exam Results Publication (`DRAFT` &rarr; `PUBLISHED`)** | ✅ Full | ✅ Full | ✅ Full | ❌ | ⚡ Auto-Sync | 👁️ Self | `MOD_RESULTS_PUBLISH` |
| **Department Exam Results Review** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | 👁️ Self | `MOD_RESULTS_PUBLISH` |
| **Independent Attainment CSV Import** | ✅ Full | ✅ Full | ✅ Full | ✅ Dept | ✅ Subj | ❌ | `MOD_SEE_MARKS` |
| **Student Profiles & Biographical Dossier**| ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | ✅ Edit Self | `MOD_STUDENT_PROFILE` |
| **Private Student Document Vault** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | ✅ Upload Self| `MOD_STUDENT_PROFILE` |
| **Original Certificates Custody Ledger**| ✅ Full | ✅ Full | ✅ Full | ❌ | ❌ | 👁️ View Self | `MOD_CERTIFICATES` |
| **Statutory Certificate Generation** | ✅ Full | ✅ Full | ✅ Full | ❌ | ❌ | ✅ Request Self| `MOD_CERTIFICATES` |
| **Lesson Plan Formulation & Upload** | ❌ | ❌ | ❌ | 👁️ Dept | ✅ Full | ❌ | `MOD_LESSON_PLAN` |
| **Lesson Plan Reconciliation & Audit**| ❌ | 👁️ Read | ❌ | ✅ Approve | ✅ Submit | ❌ | `MOD_LESSON_PLAN` |
| **Internal Assessment (CIA) Marks** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_MARKS` |
| **Laboratory Bulk Day-to-Day Marks** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_MARKS` |
| **Design Thinking & Innovation (DTI) CIA** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_MARKS` |
| **Postgraduate (PG) CIA Marks** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_MARKS` |
| **CIA Exam Paper Attachments** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_METADATA` |
| **Semester End Exam (SEE) Marks** | ❌ | 👁️ Read | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_SEE_MARKS` |
| **CO-PO Formulation & Mapping** | 👁️ Read | 👁️ Read | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CO_PO` |
| **CO-PO Attainment Analysis** | 👁️ Read | 👁️ Read | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_OBE_ANALYSIS` |
| **e-Bluebook & OBE Dossier Export** | 👁️ Read | 👁️ Read | ❌ | ✅ Full | ✅ Full | ❌ | `MOD_OBE_ANALYSIS` |
| **NBA SAR Form C-3 Dossier** | 👁️ Read | 👁️ Read | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_SAR_FORM_C3` |
| **Autonomous Course Master File** | 👁️ Read | 👁️ Read | ❌ | ✅ Full | ✅ Full | ❌ | `MOD_COURSE_MASTER_FILE` |
| **Attendance Condonation Gazette** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | `MOD_ATTENDANCE_CONDONATION_GAZETTE` |
| **Bloom HOTS/LOTS Advisory Badge** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_BLOOM_ANALYSIS` |
| **PO/PSO Program Articulation Matrix**| ✅ Full | 👁️ Read | ❌ | ✅ Full | 👁️ Read | ❌ | `MOD_PO_PSO_ATTAINMENT` |
| **Continuous Laboratory Rubrics** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_LAB_RUBRICS` |
| **Student Attendance Risk Gauge** | ❌ | ❌ | ❌ | ❌ | ❌ | 👁️ Self | `MOD_STUDENT_ATTENDANCE_RISK` |
| **Student Course-Wise Attendance Register** | ✅ Full | ✅ Full | ✅ Full | 👁️ Dept | 👁️ Subj | 👁️ Self | `MOD_ATTENDANCE` |
| **CQI Action Tracker & ATR PDF** | ✅ Full | 👁️ Read | ❌ | ✅ Approve/Close | ✅ Execute | ❌ | `MOD_CQI_ACTION_TRACKING` |
| **Opaque Token Certificate Verification** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | 👁️ Self | `MOD_CERTIFICATE_QR` |
| **Longitudinal Multi-Batch Analytics**| ✅ Full | 👁️ Read | ❌ | 👁️ Read | ❌ | ❌ | `MOD_BATCH_OBE` |
| **Credit Promotion Advisory Simulation**| ✅ Full | 👁️ Read | ✅ Full | ❌ | ❌ | ❌ | `MOD_CREDIT_PROMOTION` |
| **Optional AI Draft Assistance** | ✅ Full | ❌ | ❌ | ✅ Opt-in & Review | ✅ Opt-in & Review | ❌ | `MOD_AI_ASSISTANCE` |
| **Student Feedback & Surveys (CO/CES/Faculty)** | 👁️ Read | ✅ Full | 👁️ Read | 👁️ Dept | 👁️ Subj | ✅ Submit | `MOD_FEEDBACK` |
| **Institutional Attendance Audits** | ✅ Full | ✅ Full | ✅ Full | 👁️ Dept | 👁️ Subj | 👁️ Self | `MOD_ATTENDANCE` |

*Legend: ✅ Full = Create/Update/Delete; 👁️ Read = View only; 👁️ Dept = Department-level scope; 👁️ Subj = Subject-level scope; 👁️ Self = Personal records only; ⚡ Auto-Sync = 1-click import from published results; ❌ = Access Denied.*

---

## 2. Page Route Authorization Mapping

### 2.1 SuperAdmin (`$_SESSION['role'] === 'superadmin'`)
- `superadminhome.php`
- `superadminfeatures.php` (Feature Toggle Management, Visibility Matrix & `role_module_permissions` Action Matrix)
- `superadminacademicsettings.php`
- `cia_policy.php` (Regulation CIA Schemes, Mid Weightages & Statutory Rules)
- `superadminattainmentsettings.php` (Attainment Profiles & Subject Overrides)
- `superadminaiproviders.php` (Optional AI Provider Configuration)
- `superadminacademicyears.php`
- `superadminadmins.php`
- `superadminattendancerules.php`
- `superadminbatches.php` (Student Batches & Cohort Lifecycle Governance)
- `superadminbatchobe.php` (Vision, Mission, PEOs, PO-PEO Articulation & Macro-Attainment)
- `superadminclasses.php` (Class Sectioning & Batch Assignment)
- `superadminclasstimings.php`
- `superadmindepts.php`, `superadmindps.php`
- `superadminprograms.php`, `superadminprogramclasses.php`
- `superadminregulations.php` (Master Regulations, Program Ceilings, Cloning & Statutory Controls)
- `superadminregulationdetails.php` (Category Credit Distribution, Course Types & Assessment Schemes, Settings)
- `superadminspecs.php`
- `superadminpopso.php`
- `adminstudentprofiles.php`, `viewdocument.php` (Inspect All Student Dossiers & Private Vault)
- `admincustodyledger.php`, `viewcertificate.php` (College Safe Custody & Certificates Ledger)
- `academicsectionresults.php`, `attainmentimport.php`, `reviewqueue.php`, `aiassistance.php`
- `superadminchgpwd.php`

### 2.2 Admin / Principal (`$_SESSION['role'] === 'admin'`)
- `adminhome.php`
- `adminfacst.php` (Department Operations Gateway: HOD, Faculty, Classes)
- `adminfac.php` (Faculty Roster, Onboarding & Department-Wise Custom Sort Order `faculties.sort_order`), `adminaddfaculty.php`, `admineditfaculty.php`, `adminfacultyprofile.php`, `hod_profile.php`, `edit_hod.php`
- `viewclasses.php` (Legacy shim: `adminviewclasses.php`)
- `enrollstudents.php`, `unenrollstudents.php`, `viewstudents.php`, `editstudent.php`, `uploadstudents.php` (Legacy shims: `adminenrollstudents.php`, `adminunenrollstudents.php`, `adminviewstudents.php`, `adminuploadst.php`)
- `adminstudentprofiles.php`, `viewdocument.php` (Comprehensive Student Dossier & Document Inspection)
- `admincustodyledger.php`, `viewcertificate.php` (Physical Custody Ledger & Certificate Approvals)
- `academicsectionresults.php`, `attainmentimport.php` (Institutional Exam Results & Attainment Imports)
- `cia_policy.php` (Regulation CIA Scheme Oversight)
- `adminresetfacultypwd.php`, `adminresetstudentpwd.php`
- `adminshowattendance.php`, `adminshowallclsattendance.php`, `adminshowclsattendance.php`, `adminshowfacattendance.php`, `download_attendance.php`, `download_attendance_gazette.php`, `export_attendance_excel.php`
- `adminciaanalysis2.php`
- `adminviewfeedback.php`, `adminshowfeedbackstatus.php`
- `adminchgpwd.php`

### 2.3 Academic Section (`$_SESSION['role'] === 'academic_section'`)
- `academicsectionhome.php`
- `adminfacst.php` (Academic Operations Hub: Class Allotment & Student Enrollment Gateway)
- `viewclasses.php` (Specialization-wise and Academic Year class rosters; legacy shim: `adminviewclasses.php`)
- `enrollstudents.php` (Student Enrollment: Bulk CSV upload & carry-forward import from previous semester; legacy shim: `adminenrollstudents.php`)
- `viewstudents.php` (Enrolled student roster, inline editing, activation/inactivation, password reset, single student manual entry; legacy shim: `adminviewstudents.php`)
- `unenrollstudents.php` (Student unenrollment and removal from class; legacy shim: `adminunenrollstudents.php`)
- `uploadstudents.php` (Direct bulk CSV student upload and enrollment hub; legacy shim: `adminuploadst.php`)
- `editstudent.php` (Student profile and enrollment record editing)
- `academicsectioncoursestructure.php` (Autonomous Course Structure & BoS Engine)
- `academicsectioncurriculumsubjects.php`, `curriculum_subject_ajax.php` (Curriculum Subject Catalog, Credits, Auto-Defaults & Master COs)
- `academicsectionmanagebuildings.php`, `managebuildings_public.php`
- `academicsectionsyllabus.php`
- `academicsectionregulations.php`, `academicsectionattendancerules.php` (Academic Regulations Reference Hub)
- `cia_policy.php` (Regulation CIA Schemes & Evaluation Scheme Management)
- `academicsectionresults.php` (Exam Notifications, CSV Preview/Import, Review & Publication)
- `attainmentimport.php` (External Attainment CSV Import)
- `adminstudentprofiles.php`, `viewdocument.php` (Student Biographical Profiles & Verification)
- `admincustodyledger.php`, `viewcertificate.php` (Custodial Document Management & Certificate Processing)
- `academicsectionresetfacultypwd.php`, `academicsectionresetstudentpwd.php`
- `academicsectionshowallclsattendance.php`, `download_attendance_gazette.php`
- `academicsectionchgpwd.php`

### 2.4 Head of Department (`$_SESSION['role'] === 'hod'`)
- `hodhome.php`, `hod_profile.php`
- `hodmanage_timetable.php`, `hodtimetable.php`, `faculty_weekly_timetable.php`, `viewtimetable.php`
- `hodmapfaculty.php`, `hodviewfaculties.php`, `hodfacultyprofile.php`, `hodeditfaculty.php`
- `hodmapstudents.php`, `hodviewstudents.php`
- `hodviewdelrequests.php`, `hodviewattendance_todel.php` (Row-locked attendance deletion approvals & audit trail)
- `hodmanagepermissions.php` (Duty leave exemptions)
- `hodviewsubjects.php`, `hodviewclasses.php`, `viewallsubjects.php`
- `hodshowallclsattendance.php`, `hodshowclsattendance.php`, `hodshowfacattendance.php`, `hodshowattendance.php`, `download_attendance_gazette.php`
- `hodfacaddattendance.php` (Direct/substitute attendance marking)
- `hodshowcls_cia.php`, `download_cls_cia.php` (CIA ledger reviews)
- `hodresults.php` (Department Examination Results & Grade Distribution Oversight)
- `hodciaanalysis2.php`, `attainmentimport.php`, `reviewqueue.php`, `download_atr.php`, `aiassistance.php`
- `hodviewfeedback.php`
- `hodchgpwd.php`

### 2.5 Faculty (`$_SESSION['role'] === 'faculty'`)
- `fachome.php`, `facknowledgebase.php`, `faceditoptions.php`, `facupdsubject.php`
- `facaddattendance.php`, `facadddairy.php`, `facexceptionalattendance.php`, `facaddgroupedattendance.php`, `facshowattendance.php`, `facupdatestudentattendance.php` (7-day single-student correction with snapshot)
- `facaddlessonplan.php` (Lesson plan creation and bulk CSV upload)
- `faclessonplanreconciliation.php`, `reviewqueue.php`, `download_atr.php` (End-of-course reconciliation, compliance audit, CQI action queue & ATR export)
- `facadddelattrequest.php`, `facshowdelattrequests.php`
- `facaddciamarks.php`, `faceditciamarks.php`, `facviewciamarks.php`, `facciamarks.php`, `facciamarkscondensed.php`, `facdticiamarkscondensed.php`, `facadddticiamarks.php`, `facbulkd2dmarks.php`, `facgroupedcia.php`, `facviewmarks.php`, `facviewmarkscsv.php`
- `facaddcialabmarks.php`, `faceditcialabmarks.php`, `facviewcialabmarks.php`, `facuglabciamarkscondensed.php`
- `facaddpgciamarks.php`, `faceditpgciamarks.php`, `facviewpgciamarks.php`, `pg_cia_form.php`, `pg_cia_components.php`
- `facaddprojectciamarks.php`, `facviewprojectciamarks.php`
- `facciaattachments.php` (Question paper and key document uploads)
- `facseemarks.php`, `facseemarksentry.php`, `facseedirectmarks.php`, `facseecompques.php`, `facseemarkscondensed.php`, `attainmentimport.php`
- `facaddcos.php`, `facarticulationmatrix.php`, `facciacomp.php`, `facciacompques.php`, `facquestionco.php`, `facmarksentry.php`
- `facciaanalysis2.php`, `aiassistance.php`
- `facviewfeedback.php`
- `faculty_weekly_timetable.php`, `viewtimetable.php`
- `facchgpwd.php`

### 2.6 Student (`$_SESSION['role'] === 'student'` in `jntuaceastudents/`)
- `index.php` (Sign-in)
- `studenthome.php` (Today's Schedule, Attendance Risk Gauge & Academic Dashboard)
- `studentprofile.php` (Biographical Dossier, Parent/Entrance Metrics & Address)
- `studentdocuments.php`, `viewdocument.php` (Private Digital Document Vault & Custody Ledger Tracking)
- `studentresults.php` (Semester Grade Cards, Fractional SGPA & Examination Results)
- `studentcertificates.php`, `viewcertificate.php` (Request & Print Custodial, TC, Study/Conduct, Bonafide, No Dues)
- `studentsubjects.php` (Registered Subjects, Faculty Roster & Exam Result Badges)
- `studentsubatt.php`, `studentsubatt1.php`, `studentsubjectatt.php` (Detailed Subject-Wise Attendance Breakdown, CIA Marks & SEE Results)
- `studentcourseatt.php` (Course-Wise Attendance Summary Register)
- `studenttimetable.php` (Class Weekly Timetable)
- `studentfeedback.php`, `studentfeedbacksubjects.php`, `student_faculty_feedback.php`, `student_co_feedback.php`, `student_ces_feedback.php`
- `stknowledgebase.php` (Student Help Manual)
- `stchgpwd.php` (Password Change)
- `logout.php` (Session Termination)

### 2.7 Shared Services & Accreditation Exports
- `download_bluebook.php`: Generates complete official e-Bluebook PDF via `services/EBluebookPDFService.php` (Sections 1, 2A, 2B, 2C, 3, 4).
- `download_coursemasterfile.php`: Generates 7-section Autonomous Course Master File PDF via `services/CourseMasterFilePDFService.php`.
- `download_obe_analysis.php`: Generates comprehensive NBA/NAAC OBE dossier and SAR Form C-3 PDF via `services/OBEAnalysisPDFService.php`.
- `download_atr.php`: Generates snapshot-backed CQI Action Taken Report (ATR) PDF via `services/AcademicReviewService.php`.
- `download_attendance_gazette.php`: Generates regulatory Attendance Condonation & Detention Gazette PDF.
- `download_cls_cia.php`, `export_excel.php`, `export_attendance_excel.php`, `generate_class_report.php`.
- `modulefeedback.php`, `download_feedback.php`, `download_feedback_enhanced.php`, `download_feedback_excel.php`, `public_feedback_status.php`.
- `viewdocument.php` & `viewcertificate.php`: Ownership-checked private document streaming and certificate verification across both portals.

---

## 3. Dynamic Feature Toggle, Dependency & Action RBAC Engine

The system employs a centralized feature and permission gatekeeper implemented in [`FeatureManager.php`](../../jntuacea/services/FeatureManager.php), backed by `system_feature_modules` (**28 modules**, with `MOD_ATTENDANCE_DELETE_REQUESTS` normalized to `MOD_ATT_REQUESTS`) and `role_module_permissions`.

### 3.1 Module Dependency Graph (`FeatureManager::isModuleEnabled()`)
A child module is active only when both its own `is_enabled_globally = 1` and all of its parent modules are enabled:
- `MOD_AI_ASSISTANCE` &rarr; requires `MOD_SEE_MARKS`
- `MOD_COURSE_MASTER_FILE` &rarr; requires `MOD_LESSON_PLAN`
- `MOD_CQI_ACTION_TRACKING` &rarr; requires `MOD_CO_PO` and `MOD_OBE_ANALYSIS`
- `MOD_LAB_RUBRICS` &rarr; requires `MOD_CIA_MARKS`
- `MOD_STUDENT_ATTENDANCE_RISK` &rarr; requires `MOD_ATTENDANCE`
- `MOD_ATTENDANCE_CONDONATION_GAZETTE` &rarr; requires `MOD_ATTENDANCE`
- `MOD_CERTIFICATE_QR` &rarr; requires `MOD_CERTIFICATES`
- `MOD_CERTIFICATES` &rarr; requires `MOD_STUDENT_PROFILE`

### 3.2 Feature Manager Enforcement APIs

| API Method | Return Type | Functional Purpose |
|---|---|---|
| `FeatureManager::isModuleEnabled($moduleKey)` | `bool` | Checks if a module and all of its parent dependencies are active globally. |
| `FeatureManager::canRoleAccess($moduleKey, $role)` | `bool` | Returns `true` if module is globally enabled, role visibility is `VISIBLE` or `READONLY`, and `role_module_permissions` allows `view`. SuperAdmin always has access. |
| `FeatureManager::canRoleWrite($moduleKey, $role)` | `bool` | Returns `true` only if module is globally enabled, role visibility is `VISIBLE`, and `role_module_permissions` allows `edit`. |
| `FeatureManager::canAction($module, $action, $role)` | `bool` | Checks granular permission (`view`, `edit`, `approve`, `export`) in `role_module_permissions` combined with module read/write status. |
| `FeatureManager::requireAction($module, $action)` | `void` | Halts execution via `\College\RequestSecurity::deny()` if the current role lacks the specified action permission. |
| `FeatureManager::savePermission($module, $role, $action, $allowed)` | `void` | SuperAdmin method persisting action permission to `role_module_permissions` and logging `CHANGE_ROLE_PERMISSION` to `audit_logs` atomically. |
| `FeatureManager::requireAccess($key, $redirectUrl=null)` | `void` | Route guard that halts execution and redirects if the authenticated user cannot access the module. |
| `FeatureManager::requireWriteAccess($key, $redirectUrl=null)` | `void` | Mutation guard that halts POST processing if the module is in `READONLY` mode or lacks write permission. |
| `FeatureManager::renderReadOnlyBanner($key, $message=null)` | `string` | Renders a styled alert banner informing the user of the read-only audit state. |

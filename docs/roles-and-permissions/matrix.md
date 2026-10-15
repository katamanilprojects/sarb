# Role & Permissions Matrix

This document provides a matrix of features, administrative capabilities, and page access rules across all six primary system roles in **JNTUACEA** and **JNTUACEASTUDENTS**.

For the exhaustive file-by-file and database table read/write mapping, consult [user_feature_file_table_mapping.md](file:///Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/docs/roles-and-permissions/user_feature_file_table_mapping.md).

---

## 1. Feature Access Matrix

| Feature / Capability | SuperAdmin | Admin (Principal) | Academic Section | HOD | Faculty | Student | Feature Toggle Module |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Autonomous Academic Settings** | ✅ Full | 👁️ Read | 👁️ Read | 👁️ Read | 👁️ Read | ❌ | - |
| **Feature Toggle Management** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Academic Years Management** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Programs & Regulations Setup** | ✅ Full | ❌ | 👁️ Read | ❌ | ❌ | ❌ | - |
| **Department & Specialization Setup**| ✅ Full | 👁️ Read | ❌ | ❌ | ❌ | ❌ | - |
| **Class Timings Configuration** | ✅ Full | ❌ | ❌ | ❌ | ❌ | ❌ | - |
| **Class Cohort & Section Creation** | ✅ Full | 👁️ Read | ❌ | ❌ | ❌ | ❌ | - |
| **Master Curriculum Catalog** | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Autonomous Course Structure & BoS Engine** | ✅ Full | 👁️ Read | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Campus Buildings & Halls** | ❌ | ❌ | ✅ Full | ❌ | ❌ | ❌ | - |
| **Syllabus Document Management** | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ | - |
| **Faculty Account Onboarding** | ❌ | ✅ Full | ❌ | 👁️ Read | ❌ | ❌ | - |
| **Student Enrollment & Status** | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ | 👁️ Self | - |
| **Faculty Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ | ❌ | - |
| **Student Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ | ❌ | - |
| **Department Timetable Management** | ❌ | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read | - |
| **Subject Allotment to Faculty** | ❌ | ✅ Full | ❌ | ✅ Full | ❌ | ❌ | - |
| **Student Elective/Batch Mapping** | ❌ | ✅ Full | ❌ | ✅ Full | ❌ | ❌ | - |
| **Daily Attendance Marking & Diary**| ❌ | ❌ | ❌ | ❌ | ✅ Full | 👁️ View | `MOD_ATTENDANCE` & `MOD_CLASS_DIARY` |
| **Grouped / Combined Attendance** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_GROUPED_ATT` |
| **Exceptional Attendance Marking** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_ATTENDANCE` |
| **Attendance Deletion Submission** | ❌ | ❌ | ❌ | ❌ | ✅ Full | ❌ | `MOD_ATT_REQUESTS` |
| **Attendance Deletion Approval** | ❌ | ❌ | ❌ | ✅ Full | ❌ | ❌ | `MOD_ATT_REQUESTS` |
| **Student Batches & Cohort Governance**| ✅ Full | 👁️ Read | 👁️ Read | 👁️ Read | ❌ | 👁️ Self | `MOD_BATCH_GOVERNANCE` |
| **Batch OBE, Vision/Mission & PEOs** | ✅ Full | 👁️ Read | ❌ | 👁️ Read | 👁️ Read | ❌ | `MOD_BATCH_GOVERNANCE` |
| **Batch Macro-Attainment Calculation**| ✅ Full | 👁️ Read | ❌ | 👁️ Read | 👁️ Read | ❌ | `MOD_BATCH_GOVERNANCE` |
| **Exam Results Publication** | ✅ Full | ✅ Full | ✅ Full | ❌ | ⚡ Auto-Sync | 👁️ Self | `MOD_RESULTS_PUBLISH` |
| **Student Profiles & Biographical Dossier**| ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | ✅ Edit Self | `MOD_STUDENT_PROFILE` |
| **Student Document Vault** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | ✅ Upload Self| `MOD_STUDENT_PROFILE` |
| **Original Certificates Custody Ledger**| ✅ Full | ✅ Full | ✅ Full | ❌ | ❌ | 👁️ View Self | `MOD_CERTIFICATES` |
| **Statutory Certificate Generation** | ✅ Full | ✅ Full | ✅ Full | ❌ | ❌ | ✅ Request Self| `MOD_CERTIFICATES` |
| **Lesson Plan Formulation & Upload** | ❌ | ❌ | ❌ | 👁️ Dept | ✅ Full | ❌ | `MOD_LESSON_PLAN` |
| **Lesson Plan Reconciliation & Audit**| ❌ | 👁️ Read | ❌ | ✅ Approve | ✅ Submit | ❌ | `MOD_LESSON_PLAN` |
| **Internal Assessment (CIA) Marks** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full | ❌ | `MOD_CIA_MARKS` |
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
| **Attendance Deletion Audit Trail** | ✅ Full | 👁️ Read | ❌ | ✅ Full | 👁️ Self | ❌ | `MOD_ATTENDANCE_DELETE_REQUESTS` |
| **Student Attendance Risk Gauge** | ❌ | ❌ | ❌ | ❌ | ❌ | 👁️ Self | `MOD_STUDENT_ATTENDANCE_RISK` |
| **CQI Remedial Action Tracker** | ❌ | 👁️ Read | ❌ | ✅ Approve | ✅ Full | ❌ | `MOD_CQI_ACTION_TRACKING` |
| **Cryptographic QR Verification** | ✅ Full | ✅ Full | ✅ Full | 👁️ Read | ❌ | 👁️ Self | `MOD_CERTIFICATE_QR` |
| **Longitudinal Multi-Batch Analytics**| ✅ Full | 👁️ Read | ❌ | 👁️ Read | ❌ | ❌ | `MOD_BATCH_OBE` |
| **Credit Promotion Advisory Simulation**| ✅ Full | 👁️ Read | ✅ Full | ❌ | ❌ | ❌ | `MOD_CREDIT_PROMOTION` |
| **Student Feedback & Surveys (CO/CES/Faculty)** | 👁️ Read | ✅ Full | 👁️ Read | 👁️ Dept | 👁️ Subj | ✅ Submit | `MOD_FEEDBACK` |
| **Institutional Attendance Audits** | ✅ Full | ✅ Full | ✅ Full | 👁️ Dept | 👁️ Subj | 👁️ Self | `MOD_ATTENDANCE` |

*Legend: ✅ Full = Create/Update/Delete; 👁️ Read = View only; 👁️ Dept = Department-level scope; 👁️ Subj = Subject-level scope; 👁️ Self = Personal records only; ⚡ Auto-Sync = 1-click import from published results; ❌ = Access Denied.*

---

## 2. Page Route Authorization Mapping

### 2.1 SuperAdmin (`$_SESSION['role'] === 'superadmin'`)
- `superadminhome.php`
- `superadminfeatures.php` (Feature Toggle Management & Visibility Matrix)
- `superadminacademicsettings.php`
- `superadminacademicyears.php`
- `superadminadmins.php`
- `superadminattendancerules.php`
- `superadminbatches.php` (Student Batches & Cohort Lifecycle Governance)
- `superadminbatchobe.php` (Vision, Mission, PEOs, PO-PEO Articulation & Macro-Attainment)
- `superadminclasses.php` (Class Sectioning & Batch Assignment)
- `superadminclasstimings.php`
- `superadmindepts.php`
- `superadminprograms.php`
- `superadminprogramclasses.php`
- `superadminregulations.php` (Master Regulations, Program Ceilings, Cloning & Statutory Controls)
- `superadminregulationdetails.php` (Category Credit Distribution, Course Types & Assessment Schemes, Settings)
- `superadminspecs.php`
- `superadminpopso.php`
- `adminstudentprofiles.php` (Inspect All Student Dossiers & Vault)
- `admincustodyledger.php` (College Safe Custody & Certificates Ledger)
- `academicsectionresults.php` (Manage Exam Results & Publication)
- `superadminchgpwd.php`

### 2.2 Admin / Principal (`$_SESSION['role'] === 'admin'`)
- `adminhome.php`
- `adminfacst.php` (Department Operations Gateway: HOD, Faculty, Classes)
- `adminfac.php`, `adminaddfaculty.php`, `admineditfaculty.php`, `adminfacultyprofile.php`, `adminuploadfac.php`
- `adminviewclasses.php`
- `adminenrollstudents.php`, `adminunenrollstudents.php`, `adminviewstudents.php`, `editstudent.php`, `adminuploadst.php`
- `adminstudentprofiles.php` (Comprehensive Student Dossier & Document Inspection)
- `admincustodyledger.php` (Physical Custody Ledger & Certificate Approvals)
- `academicsectionresults.php` (Institutional Exam Results Overview)
- `adminresetfacultypwd.php`, `adminresetstudentpwd.php`
- `adminshowattendance.php`, `adminshowallclsattendance.php`, `adminshowclsattendance.php`, `adminshowfacattendance.php`, `download_attendance.php`, `export_attendance_excel.php`
- `adminciaanalysis2.php` (Active choice-aware analysis; `adminciaanalysis.php` redirects here)
- `adminviewfeedback.php`, `adminshowfeedbackstatus.php`
- `adminchgpwd.php`

### 2.3 Academic Section (`$_SESSION['role'] === 'academic_section'`)
- `academicsectionhome.php`
- `academicsectioncoursestructure.php` (Autonomous Course Structure & BoS Engine)
- `academicsectioncurriculumsubjects.php` (Curriculum Subject Catalog, Credits & Auto-Defaults)
- `academicsectionmanagebuildings.php`, `managebuildings_public.php`
- `academicsectionsyllabus.php`
- `academicsectionregulations.php` (Academic Regulations Reference Hub - Read-Only Statutory Reference & Settings View)
- `academicsectionresults.php` (Exam Notifications & Bulk Results CSV Upload)
- `adminstudentprofiles.php` (Student Biographical Profiles & Verification)
- `admincustodyledger.php` (Custodial Document Management & Certificate Processing)
- `academicsectionresetfacultypwd.php`, `academicsectionresetstudentpwd.php`
- `academicsectionshowallclsattendance.php`
- `academicsectionchgpwd.php`

### 2.4 Head of Department (`$_SESSION['role'] === 'hod'`)
- `hodhome.php`, `hod_profile.php`
- `hodmanage_timetable.php`, `faculty_weekly_timetable.php`
- `hodmapfaculty.php`, `hodviewfaculties.php`, `hodfacultyprofile.php`, `hodeditfaculty.php`
- `hodmapstudents.php`, `hodviewstudents.php`
- `hodviewdelrequests.php`, `hodviewattendance_todel.php` (Attendance deletion approvals)
- `hodmanagepermissions.php` (Duty leave exemptions)
- `hodviewsubjects.php`, `hodviewclasses.php`, `viewallsubjects.php`
- `hodshowallclsattendance.php`, `hodshowclsattendance.php`, `hodshowfacattendance.php`, `hodshowattendance.php`
- `hodfacaddattendance.php` (Direct/substitute attendance marking)
- `hodshowcls_cia.php`, `download_cls_cia.php` (CIA ledger reviews)
- `hodciaanalysis2.php` (Active choice-aware analysis; `hodciaanalysis.php` redirects here)
- `hodviewfeedback.php`
- `hodchgpwd.php`

### 2.5 Faculty (`$_SESSION['role'] === 'faculty'`)
- `fachome.php`
- `facaddattendance.php`, `facadddairy.php`, `facexceptionalattendance.php`, `facaddgroupedattendance.php`, `facshowattendance.php`, `facupdatestudentattendance.php`
- `facaddlessonplan.php` (Lesson plan creation and bulk CSV upload)
- `faclessonplanreconciliation.php` (End-of-course reconciliation and compliance audit)
- `facadddelattrequest.php`, `facshowdelattrequests.php`
- `facaddciamarks.php`, `faceditciamarks.php`, `facviewciamarks.php`, `facciamarkscondensed.php`
- `facaddcialabmarks.php`, `faceditcialabmarks.php`, `facviewcialabmarks.php`, `facuglabciamarkscondensed.php`
- `facaddpgciamarks.php`, `faceditpgciamarks.php`, `facviewpgciamarks.php`
- `facaddprojectciamarks.php`, `facviewprojectciamarks.php`
- `facciaattachments.php` (Question paper and key document uploads)
- `facseemarks.php` (Mode A: Detailed Entry, Mode B: Direct Marks, Mode C: ⚡ Auto-Sync from Results)
- `facseemarksentry.php`, `facseedirectmarks.php`, `facseecompques.php`, `facseemarkscondensed.php`
- `facaddcos.php`, `facarticulationmatrix.php`, `facciacomp.php`, `facciacompques.php`, `facquestionco.php`, `facmarksentry.php`
- `facciaanalysis2.php` (Active choice-aware analysis; `facciaanalysis.php` redirects here)
- `facviewfeedback.php`
- `faculty_weekly_timetable.php`
- `facchgpwd.php`

### 2.6 Student (`$_SESSION['role'] === 'student'` in `jntuaceastudents/`)
- `index.php` (Sign-in)
- `studenthome.php` (Today's Schedule & Academic Dashboard)
- `studentprofile.php` (Biographical Dossier, Parent/Entrance Metrics & Address)
- `studentdocuments.php` (Digital Document Vault & Custody Ledger Tracking)
- `studentresults.php` (Semester Grade Cards, SGPA & Examination Results)
- `studentcertificates.php` (Request Custodial, TC, Study/Conduct, Bonafide, No Dues)
- `viewcertificate.php` (Official Institutional Certificate Renderer & Print View)
- `studentsubjects.php` (Registered Subjects & Faculty Roster)
- `studentsubatt.php` (Detailed Subject-Wise Attendance Breakdown & Diary History)
- `studenttimetable.php` (Class Weekly Timetable)
- `studentfeedback.php`, `studentfeedbacksubjects.php` (Feedback Survey Hub)
- `student_faculty_feedback.php` (Faculty Teaching Appraisal Survey)
- `student_co_feedback.php` (Course Outcome Direct/Indirect Survey)
- `student_ces_feedback.php` (Course End Survey across 5 Domains)
- `stknowledgebase.php` (Student Help Manual)
- `stchgpwd.php` (Password Change)
- `logout.php` (Session Termination)

### 2.7 Shared Services & Accreditation Exports
- `download_bluebook.php`: Generates complete official e-Bluebook PDF via `EBluebookPDFService` (Sections 1, 2A, 2B, 2C, 3, 4).
- `download_obe_analysis.php`: Generates comprehensive NBA/NAAC Outcome-Based Education dossier via `OBEAnalysisPDFService`.
- `download_cls_cia.php`: Generates CIA class marks summary report.
- `modulefeedback.php`: Reusable feedback presentation component embedded in `adminviewfeedback.php`, `hodviewfeedback.php`, and `facviewfeedback.php`.
- `download_feedback_enhanced.php`: Direct download endpoint for Enhanced TCPDF executive feedback reports.
- `download_feedback_excel.php`: Direct download endpoint for multi-tab PhpSpreadsheet analytical workbooks.
- `public_feedback_status.php`: Public submission status dashboard for real-time monitoring of response rates.

---

## 3. Dynamic Feature Toggle & Role-Based Visibility Engine

The system employs a centralized feature gatekeeper implemented in [`FeatureManager.php`](file:///Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/services/FeatureManager.php), backed by the `system_feature_modules` database table. The engine governs 15 distinct functional modules through four operational runtime states.

### 3.1 Operational Runtime States

```
┌─────────────────────────────────────────────────────────────┐
│                 Global Switch (`is_enabled_globally`)       │
│                                                             │
│       ┌───────────────┴───────────────┐                     │
│   [0: Disabled]                  [1: Enabled]               │
│       │                               │                     │
│  Deactivated Across              Evaluate Role              │
│   All End-User Roles            Visibility Setting          │
│                                       │                     │
│                ┌──────────────────────┼──────────────────┐  │
│                ▼                      ▼                  ▼  │
│           `VISIBLE`              `READONLY`           `HIDDEN`
│        Menu Shown,           Menu Shown, Audit View,  Menu Hidden,
│        Full Read & Write     POST Mutations Blocked,  Route Locked
│        Authorized            Read-Only Banner Shown   (Redirects)
└─────────────────────────────────────────────────────────────┘
```

1. **Globally Disabled (`is_enabled_globally = 0`)**:
   - Master deactivation switch. Overrides all individual role visibility settings.
   - Deactivated system-wide across all standard roles (Faculty, Students, HODs, Admin, Academic Section).
   - Only SuperAdmin retains access to configure, preview, or reactivate the module.
   - Any attempt to access pages guarded by `FeatureManager::requireAccess()` triggers a safe redirect back to the user's role home portal.

2. **Role `VISIBLE`**:
   - Module navigation links and action buttons are fully rendered in navigation bars and action menus.
   - Users possess full read, write, update, and submission capabilities (`canRoleWrite() === true`).

3. **Role `READONLY`**:
   - **Menu Visibility**: Navigation links remain accessible (`isFacultyVisible()` / `isHodVisible()` include `READONLY`) so faculty and leadership can inspect existing data, audit records, review rosters, and verify past entries.
   - **Route-Level Write Interception**: At the top of mutation scripts, `FeatureManager::requireWriteAccess($moduleKey)` halts any POST/mutation attempts and safely redirects or exits before any database changes occur.
   - **Visual Banners**: `FeatureManager::renderReadOnlyBanner($moduleKey)` outputs an alert banner notifying the user that the module is currently locked in audit mode.
   - **Form Field Deactivation**: Input fields, textareas, and dropdowns are marked `readonly` or `disabled`, and submit buttons are hidden or disabled.

4. **Role `HIDDEN`**:
   - Navigation links and action cards are suppressed from role menus (`facmenu.php`, `faceditoptions.php`, `hodmenu.php`, `adminshowattendance.php`).
   - Direct URL access is blocked by `FeatureManager::requireAccess($moduleKey)`, redirecting unauthorized users back to their respective home dashboard.

---

### 3.2 Feature Manager Enforcement APIs

| API Method | Return Type | Functional Purpose |
|---|---|---|
| `FeatureManager::isModuleEnabled($moduleKey)` | `bool` | Checks if a module is active globally (`is_enabled_globally = 1`). |
| `FeatureManager::canRoleAccess($moduleKey, $role)` | `bool` | Returns `true` if module is globally enabled AND role visibility is `VISIBLE` or `READONLY`. Superadmin always has access. |
| `FeatureManager::canRoleWrite($moduleKey, $role)` | `bool` | Returns `true` only if module is globally enabled AND role visibility is explicitly `VISIBLE`. |
| `FeatureManager::isRoleReadOnly($moduleKey, $role)` | `bool` | Returns `true` if module is globally enabled AND role visibility is explicitly `READONLY`. |
| `FeatureManager::isFacultyVisible($key, $includeReadOnly=true)` | `bool` | Helper for faculty navigation menus. Returns `true` for both `VISIBLE` and `READONLY` by default. |
| `FeatureManager::isFacultyReadOnly($key)` | `bool` | Returns `true` if module is restricted to read-only for faculty. |
| `FeatureManager::isFacultyWritable($key)` | `bool` | Returns `true` if faculty can submit new records or modifications. |
| `FeatureManager::isHodVisible($key, $includeReadOnly=true)` | `bool` | Helper for HOD navigation menus. Returns `true` for both `VISIBLE` and `READONLY` by default. |
| `FeatureManager::isHodReadOnly($key)` | `bool` | Returns `true` if module is restricted to read-only for HOD. |
| `FeatureManager::isHodWritable($key)` | `bool` | Returns `true` if HOD can submit approvals or modifications. |
| `FeatureManager::isStudentVisible($key)` | `bool` | Checks if module is globally enabled and visible to students (`student_visibility = 'VISIBLE'`). |
| `FeatureManager::requireAccess($key, $redirectUrl=null)` | `void` | Route guard that halts execution and redirects if the authenticated user cannot access the module. |
| `FeatureManager::requireWriteAccess($key, $redirectUrl=null)` | `void` | Mutation guard that halts POST processing if the module is in `READONLY` mode for the user's role. |
| `FeatureManager::renderReadOnlyBanner($key, $message=null)` | `string` | Renders a styled alert banner informing the user of the read-only audit state. |

---

### 3.3 Module Guarding & Enforcement Map

| Module Key | Guarded File(s) | Read Access Guard | Write/Mutation Guard | Form Deactivation & Banner |
|---|---|---|---|---|
| `MOD_ATTENDANCE` | `facaddattendance.php`, `adminshowattendance.php` | `requireAccess('MOD_ATTENDANCE')` | `requireWriteAccess('MOD_ATTENDANCE')` | Banner displayed; Save attendance button disabled. |
| `MOD_ATT_REQUESTS` | `facadddelattrequest.php`, `hodviewdelrequests.php` | `requireAccess('MOD_ATT_REQUESTS')` | `requireWriteAccess('MOD_ATT_REQUESTS')` | Banner displayed; Request/Approval action buttons disabled. |
| `MOD_CLASS_DIARY` | `facadddairy.php` | `requireAccess('MOD_CLASS_DIARY')` | `requireWriteAccess('MOD_CLASS_DIARY')` | Banner displayed; Submit Diary button disabled. |
| `MOD_CO_PO` | `facaddcos.php`, `facarticulationmatrix.php` | `requireAccess('MOD_CO_PO')` | `requireWriteAccess('MOD_CO_PO')` | Auto-switched to `view` mode; Form submission actions blocked. |
| `MOD_CIA_MARKS` | `facaddciamarks.php`, `faceditciamarks.php` | `requireAccess('MOD_CIA_MARKS')` | `requireWriteAccess('MOD_CIA_MARKS')` | Banner displayed; Score inputs marked `readonly`; Submit button disabled. |
| `MOD_CIA_METADATA` | `facciacompques.php`, `facquestionco.php` | `requireAccess('MOD_CIA_METADATA')` | Route locked if disabled | Form submission guarded. |
| `MOD_LESSON_PLAN` | `facaddlessonplan.php` | `requireAccess('MOD_LESSON_PLAN')` | `requireWriteAccess('MOD_LESSON_PLAN')` | Banner displayed; Add Lecture, CSV Upload, and Delete buttons disabled. |
| `MOD_SEE_MARKS` | `facseemarks.php`, `facseedirectmarks.php` | `requireAccess('MOD_SEE_MARKS')` | `requireWriteAccess('MOD_SEE_MARKS')` | Banner displayed; Auto-Sync and Direct Marks submit buttons disabled; Inputs `readonly`. |
| `MOD_FEEDBACK` | `jntuaceastudents/studentfeedbacksubjects.php`, `student_faculty_feedback.php`, `student_co_feedback.php`, `student_ces_feedback.php` | `requireAccess('MOD_FEEDBACK')` | Student survey submission | Student route locked if feedback feature is disabled globally or hidden. |



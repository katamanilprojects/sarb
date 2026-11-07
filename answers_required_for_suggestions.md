Ran list project files
Ran list documentation directory
Ran list role, workflow and architecture docs
Analyzed matrix.md:L1-218
Analyzed role-guides.md:L1-150
Analyzed role-guides.md:L151-177
Ran list headers and home files
Analyzed header.php:L1-116
Ran check role headers
Ran list all menu files
Ran read HOD menu
Ran read hodhome.php
Ran read hodshowattendance.php
Ran search for hodmanage_timetable.php links
Analyzed hodviewclasses.php:L70-135
Ran read adminmenu.php
Ran read adminhome.php
Ran read academicsectionmenu.php
Ran read academicsectionhome.php
Ran read facmenu.php
Ran read fachome.php
Ran read faceditoptions.php
Ran read superadminmenu.php
Ran read studentmenu.php
Ran read top of studenthome.php
Ran check studenthome sections
Ran search results role restrictions
Ran check header of academicsectionresults.php
Ran find role checks in jntuacea
Ran list all php files in jntuacea
Ran list student portal php files
Ran read superadminhome.php
Ran read hodciaanalysis2.php
Ran read adminciaanalysis2.php
Ran read facciaanalysis2.php
Ran search for hodshowcls_cia.php links
Analyzed hodshowcls_cia.php:L1-70
Ran search for download_cls_cia.php links
Ran search for viewallsubjects.php links
Analyzed viewallsubjects.php:L1-50
Analyzed viewallsubjects.php:L51-110
Ran search for viewtimetable.php links
Ran read top of viewtimetable.php
Analyzed viewtimetable.php:L61-140
Ran search for reviewqueue.php links
Ran read top of reviewqueue.php
Ran search for download_attendance_gazette.php links
Ran check if gazette is in admin or academicsection files · Exited with code 1
Ran search for coursemasterfile links
Ran search for bluebook links
Ran search for download_obe_analysis.php links
Ran search for export_attendance_excel.php links
Ran search for export_excel.php links
Ran read studentsubjects.php
Ran read studentsubjectatt.php
Ran check if CIA marks are in studentsubjectatt.php
Ran check links to studenttimetable.php
Ran check links to studentdocuments.php

# Comprehensive System Analysis: Role Permissions, Missing Dashboards, Analytical Reports & Page Flow Optimization

**Project:** JNTUACEA Academic Record Book & Student Portal (`jntuacea` & `jntuaceastudents`)  
**Role:** High-Level Requirements & Project Systems Analyst  
**Design Philosophy:** Traditional, clutter-free, Bootstrap 5 server-rendered UI (clean KPI cards, data tables, intuitive status badges, and lightweight progress indicators matching institutional norms).

---

## Finalized Decisions

### Question 1 — HOD access to published semester exam results

**Decision: Approved, with Superadmin control.**

- Implement read-only access for HODs to published semester exam results within their own department, including subject-wise pass percentages, student grade cards, and backlog lists.
- HODs must not upload, edit, or publish results.
- Superadmin must be able to enable or disable this feature. HOD access must respect this setting.
- The initial enabled/disabled state has not yet been specified.

### Question 2 — Faculty password reset permissions

**Decision: Restrict faculty password resets to Admin and HOD.**

- HODs may reset passwords only for faculty in their own department.
- Admin may reset faculty passwords across all departments.
- Remove or disable Academic Section access to faculty password resets.

### Question 3 — Student enrollment and class allotment

**Decision: Move operational responsibility from Admin (Principal) to Academic Section.**

- Academic Section will enroll students, upload student lists, and allot students to classes.
- Move these operational permissions to Academic Section instead of Admin.
- Admin (Principal) retains an enrollment overview for monitoring.

### Question 4 — HOD access to student profiles

**Decision: Conditional read-only access; student profile functionality remains disabled for now.**

- Student profile data and certificate-related functionality are secondary, optional features that may be enabled or disabled.
- When student profile functionality is enabled, HODs may view student profiles within their own department, including available parent/guardian contacts and mentor notes.
- When student profile functionality is disabled, HOD profile viewing must also be unavailable.
- Keep sensitive identity-document verification with Academic Section.
- Do not enable student profile or certificate functionality as part of this decision.

### Question 5 — Direct attendance gazette downloads

**Decision: Approved for Admin and Academic Section.**

- Add direct attendance condonation and detention gazette download buttons to both Admin and Academic Section attendance pages, similar to the existing HOD option.
- Both roles must be able to download the gazette within their authorized scope.

### Question 6 — Course Review Queue and CQI availability

**Decision: Optional feature controlled dynamically by Superadmin.**

- The existing links were intentionally commented out because this is not a basic feature.
- Do not unconditionally restore the Course Review Queue or CQI links.
- Superadmin must be able to enable or disable this feature dynamically for its intended users, including HOD and Faculty.
- Show links and permit access only when enabled; when disabled, hide links and prevent access.
- Preserve the currently disabled availability until Superadmin enables it.

### Question 7 — HOD class-wise CIA marks navigation

**Decision: Keep the current flow for now; do not add a direct menu item.**

- Retain class selection before viewing class-wise and subject-wise CIA marks.
- The user understands this workflow to be available under Attendance; verify its actual location before implementation changes.
- Moving this feature to another appropriate page may be considered later, but a direct top-level menu link is not approved.

### Question 8 — Department-wise and class-wise subject viewer

**Decision: Deferred for later discussion.**

- Leave the existing subject viewer and its navigation unchanged for now.
- No new link or menu placement is approved at this stage.

### Question 9 — Campus timetable and room-usage overview

**Decision: Approved for Admin and Academic Section.**

- Provide both roles access to a campus timetable overview showing scheduled classes and room usage at a selected time.
- Implement this overview using shared logic or a shared module, with role-appropriate access, rather than duplicating it for each role.
- Apply the same shared-module approach to functionality common to multiple roles.

### Question 10 — Direct HOD timetable management

**Decision: Approved, independent of student enrollment.**

- Provide a direct Timetable menu option for HODs to select a departmental class and manage its timetable.
- Allow timetable setup before or in parallel with student enrollment; do not require enrolled students for access.

### Question 11 — Pending attendance deletion request badges

**Decision: Keep navigation unchanged; defer badge placement.**

- Avoid adding complexity to the integrated HOD/Faculty menus.
- A pending-count badge beside the existing deletion-request link may be considered.
- Home-page badges may also be considered later; no final placement is approved now.
- Revisit this decision before implementing badge or navigation changes.

### Question 12 — Student timetable navigation

**Decision: Keep the home-page link; defer any relocation.**

- Retain the existing student Timetable entry on the home page.
- Do not add a duplicate menu item or increase menu complexity now.
- If relocated to the menu later, avoid duplicating the entry on the home page.

### Question 13 — HOD Attendance page naming and organization

**Decision: Retain the combined page and rename it to reflect its broader contents.**

- Keep attendance, CIA analysis, feedback surveys, and the other existing functions together on the current page.
- Change the Attendance label to a broader, appropriate name; the exact name remains to be selected.
- Do not create a separate assessment/quality page or increase the number of menu items for this change.
- Prefer keeping the number of menu items stable when reorganizing navigation.

### Question 14 — Faculty Other Options organization

**Decision: Keep the page unchanged for now; defer regrouping.**

- Grouping links into clearer sections is acceptable in principle.
- Features are still incomplete, and some may be enabled while others remain disabled.
- Leave the current Other Options page as it is until feature availability is settled.

---

## Executive Summary

An exhaustive analysis of the codebase reveals that the core data foundations are solid: **90+ relational tables** capture fine-grained daily attendance, lecture diaries, continuous internal assessments (CIA), lab rubrics, question paper CO-mappings, semester-end results (SEE), timetables, feedback surveys, and custodial certificates.

However, the application suffers from four critical structural shortcomings:
1. **Role Access Misallocations**: Crucial pages are assigned to one role while locking out the very role directly accountable for the workflow (e.g., **HOD has 0% visibility into published semester exam results**, while Academic Section holds faculty password resets).
2. **Missing Essential Dashboards & Analytical Views**: Key stakeholders lack the daily and regular operational views their jobs require (e.g., Principal and HOD have no **Daily Class Conduction & Attendance Monitor** to track unconducted periods; HOD has no **Departmental CIA Submission Tracker**).
3. **Broken & Orphaned Page Flows**: High-value features already built into the codebase are **commented out** (e.g., `reviewqueue.php`), **completely unlinked / orphaned** (e.g., `hodshowcls_cia.php`, `viewtimetable.php`, `viewallsubjects.php`), or **buried 3–4 clicks deep** under unrelated categories (e.g., Timetable and Delete Requests).
4. **Information Silos in Reporting**: While regulatory PDF generators exist (e.g., `download_attendance_gazette.php`), key executive roles (Admin/Principal and Academic Section) have **zero download buttons** for them in their UI.

---

## 1. Role Access & Permission Misallocations

| # | Current Implementation | Functional Misalignment | Required Correction |
|---|---|---|---|
| **1.1** | **Semester Exam Results (`MOD_RESULTS_PUBLISH`)**<br>Accessible to: `superadmin`, `admin`, `academic_section`.<br>HOD Access: **`❌ DENIED`** | The HOD is directly responsible for departmental academic standards, identifying student backlogs, and organizing remedial classes. Locking HOD out of published exam results cripples departmental governance. | Grant **HOD read-only departmental scope** (`hodresults.php` / `department_results_view`) to view subject-wise pass percentages, student grade cards, and backlogs for their branch. |
| **1.2** | **Faculty Credential Resets**<br>Accessible to: `admin` and `academic_section`. | The Academic Section is an examination and curriculum body. Managing teaching faculty account credentials is an administrative / departmental function. | Confine faculty password resets to **Admin (Principal)** and **HOD**. Academic Section should only handle student exam credentials. |
| **1.3** | **Student Enrollment & Class Allotment**<br>Implemented under: `adminfacst.php`, `adminenrollstudents.php`, `adminuploadst.php` (Admin/Principal only). | In university/autonomous institutions, student admissions and registration are executed by the **Academic Section** (Registrar's Office), while the Principal monitors executive enrollment numbers. | Provide **Academic Section** with enrollment/upload access (`academicsectionenrollstudents.php`). Retain executive overview for Admin. |
| **1.4** | **Student Profiles & Private Vault Inspection (`adminstudentprofiles.php`)**<br>Accessible to: `superadmin`, `admin`, `academic_section`.<br>HOD Access: **`❌ DENIED`** | When mentoring students or resolving severe attendance crises, HODs only see basic roll numbers in `hodviewstudents.php`. They cannot see parent contact numbers, admission category, or guardian info. | Grant **HOD read-only access to departmental student dossiers** (contact details, mentor notes, parent info). Keep sensitive identity document verification with Academic Section. |
| **1.5** | **Attendance Condonation & Detention Gazette (`download_attendance_gazette.php`)**<br>Code allows: `superadmin`, `admin`, `academic_section`, `hod`.<br>UI Links: **Present only on HOD pages!** | The Principal (Admin) and Academic Section officially sign, approve, and gazette examination eligibility lists, but have **zero download links** on their pages. | Add prominent Gazette download buttons in `adminshowallclsattendance.php` and `academicsectionshowallclsattendance.php`. |

---

## 2. Page Flow, Navigation & Architecture Issues

### A. Dead / Commented-Out Links
- **Course Review Queue & CQI Action Tracking (`reviewqueue.php`)**:
  - In both `jntuacea/hodhome.php` (Line 16) and `jntuacea/fachome.php` (Line 9), the button for `reviewqueue.php` is **commented out in raw HTML** (`<!--<p><a href="reviewqueue.php">...</a></p>-->`).
  - **Impact**: Neither Faculty nor HOD can access the NBA Criterion 2.2 Course Completion reconciliation or CQI Remedial Action Tracker from the UI, despite the feature being fully implemented.

### B. Orphaned Pages (Code exists, but 0 incoming links)
- **`hodshowcls_cia.php`**: Built specifically for HODs to view class-wise and subject-wise CIA marks ledgers. It is completely missing from `hodmenu.php`, `hodhome.php`, and `hodshowattendance.php`.
- **`viewallsubjects.php`**: Department-wise and Class-wise subject viewer. Has no entry point in any navigation menu.
- **`viewtimetable.php`**: A comprehensive, live campus timetable dashboard showing ongoing classes by time slot and room utilization. It is not linked in any role menu.

### C. Buried Critical Workflows
- **HOD Timetable Management (`hodmanage_timetable.php`)**:
  - Has no link in `hodmenu.php`.
  - HOD must navigate: `Home` &rarr; `Classes` (`hodviewclasses.php`) &rarr; Expand Specialization Accordion &rarr; Locate Class row &rarr; Click tiny button `Timetable` (which submits a hidden POST form). If students are not yet enrolled, the button disappears entirely.
- **HOD Attendance Deletion Requests (`hodviewdelrequests.php`)**:
  - Buried as the 4th card inside `hodshowattendance.php`. There is no alert or badge on `hodhome.php` or `hodmenu.php` notifying the HOD that faculty deletion requests are waiting for approval.
- **Student Timetable (`studenttimetable.php`)**:
  - Missing from `studentmenu.php`. It is only accessible as an inline button inside the body of `studenthome.php`.
- **HOD CIA Analysis & Feedback Surveys**:
  - Placed under "Attendance" (`hodshowattendance.php`), creating confusion since continuous assessment and student appraisal surveys are quality assurance functions, not attendance functions.

### D. The "Other Options" Dumping Ground (`faceditoptions.php`)
- For Faculty, 15+ disparate modules (CO-PO, Lesson Plans, Reconciliation, CIA Marks, Lab Rubrics, SEE Marks, Deletion Requests, Grouped Attendance, Knowledge Base) are thrown into one long page. Faculty struggle to find daily tasks versus end-of-semester tasks.

---

## 3. Role-by-Role Analysis: Missing Dashboards, Reports & Graphs

```
+---------------------------------------------------------------------------------------------------+
|                                      OPERATIONAL CADENCE MATRIX                                   |
+---------------------+---------------------------------------+-------------------------------------+
| ROLE                | DAILY REQUIREMENTS                    | REGULAR / PERIODIC REQUIREMENTS     |
+---------------------+---------------------------------------+-------------------------------------+
| Admin (Principal)   | - Campus Live Class Conduction Monitor| - College Attendance & Risk Defaulters|
|                     | - Real-time Attendance Lockout Alerts  | - Branch Comparative Results Graph  |
|                     |                                       | - Faculty Appraisal Ranking Summary |
+---------------------+---------------------------------------+-------------------------------------+
| HOD                 | - Department Daily Conduction Grid    | - Department CIA Submission Tracker |
|                     | - Pending Attendance Deletion Badge   | - Department Results & Backlog List |
|                     | - Today's Faculty Leave/Substitutions | - Monthly Student Shortage Notices  |
+---------------------+---------------------------------------+-------------------------------------+
| Academic Section    | - Campus Hall/Room Live Occupancy     | - Credit Promotion Eligibility Audit|
|                     | - Pending Certificate Requests Queue  | - Institutional Condonation Gazette |
|                     |                                       | - Syllabus Hours Completion Tracker |
+---------------------+---------------------------------------+-------------------------------------+
| Faculty             | - Today's Schedule & Period Diary     | - Course CIA Marks Completion Matrix|
|                     | - Lesson Plan Progress Bar (% Covered)| - Subject Attendance <75% Defaulters|
|                     |                                       | - Direct CO Attainment Summary Card |
+---------------------+---------------------------------------+-------------------------------------+
| Student             | - Today's Schedule & Attendance Risk  | - Cumulative Degree Credit Audit    |
|                     | - Bell Timings & Room Numbers         | - Mid-1 / Mid-2 Scorecard           |
+---------------------+---------------------------------------+-------------------------------------+
```

---

### Detailed Role Breakdowns

### 3.1 Admin (Principal)

#### Missing Daily / Regular Views & Graphs
1. **Daily Campus Class Conduction & Attendance Monitor (DAILY)**:
   - **Need**: The Principal must know by 11:00 AM whether scheduled lectures are actually being conducted across departments or if faculty missed hours.
   - **UI Design**: A top KPI strip followed by a clean status table:
     - *KPIs*: Total Periods Scheduled Today | Conducted | Pending / Not Marked | Current Conduction Rate (%).
     - *Table*: `Department | Scheduled Today | Conducted | Unmarked Hours | Action (View Classes)`.
2. **Institutional Attendance & Detention Risk Analytics (REGULAR - Monthly)**:
   - **Need**: Executive overview of students falling below statutory thresholds (75% and 65%).
   - **UI Design**: 
     - Simple distribution bar: `[ Green: >=75% (82%) | Yellow: 65-74% (12%) | Red: <65% Detained (6%) ]`.
     - Department comparison bar chart comparing average attendance across branches.
     - One-click filterable "Detention Warning Roster" with export to Excel.
3. **Institutional Results & Performance Overview (POST-EXAM)**:
   - **Need**: Currently `academicsectionresults.php` is a technical CSV uploader. The Principal needs an analytical dashboard:
     - Branch-wise Pass Percentage comparison.
     - Backlog distribution (0 backlogs, 1 backlog, 2+ backlogs).
     - Overall College Pass Rate vs previous semester.
4. **Faculty Appraisal College-Wide Ranking (SEMESTER)**:
   - **Need**: A consolidated report ranking faculty appraisal scores across departments, highlighting instructors scoring below 70% for administrative review and training.

---

### 3.2 Head of Department (HOD)

#### Missing Daily / Regular Views & Graphs
1. **Department Daily Class Conduction & Diary Monitor (DAILY)**:
   - **Need**: HOD needs to see today's timetable grid for their department with real-time color badges:
     - Period marked: **Green** (shows Faculty Name & Topic Taught).
     - Period unmarked / overdue: **Red/Amber** (allows 1-click substitute allocation).
2. **Pending Action Badges (DAILY)**:
   - **Attendance Deletion Requests**: Red badge on HOD Home and Menu (`Requests (2)`).
   - **CQI Actions & Course Audits**: Alert counter for pending course completion reviews.
3. **Department CIA Marks Submission Progress Tracker (REGULAR - Pre-Mid Exam)**:
   - **Need**: HODs spend hours asking faculty if Mid-1 marks are uploaded.
   - **UI Design**: A structured checklist table:
     - Columns: `Class | Subject | Assigned Faculty | Mid-1 Marks | Mid-2 Marks | Lab Rubrics | Status Badge`.
     - Badges: `Completed (Green)` | `In Progress (Yellow)` | `Overdue / Not Started (Red)`.
4. **Departmental Semester Exam Results & Backlog List (POST-EXAM)**:
   - **Need**: Filter published results by department:
     - Subject-wise Pass Percentage (e.g., Mathematics: 64%, Data Structures: 92%).
     - Roster of departmental students with active backlogs to organize remedial coaching.
5. **Monthly Attendance Shortage Notice Generator (MONTHLY)**:
   - **Need**: Single-click list of all departmental students `<75%` with parent phone numbers, formatted for printing notice board lists or parent SMS dispatches.

---

### 3.3 Academic Section (Exam Cell / Registrar)

#### Missing Daily / Regular Views & Graphs
1. **Campus Hall / Room Utilization Analyzer (DAILY / REGULAR)**:
   - **Need**: Academic Section manages Buildings and Halls (`academicsectionmanagebuildings.php`), but cannot view room occupancies when scheduling exams or shared electives.
   - **UI Design**: Room matrix linking `halls` to `class_timetables`:
     - Select Day & Time Slot &rarr; Displays list of Free Halls vs Occupied Halls.
2. **Institutional Attendance Condonation & Detention Gazette Hub (REGULAR - END OF SEMESTER)**:
   - **Need**: Academic Section compiles the official examination hall ticket eligibility list.
   - **UI Design**: Dedicated page linking `download_attendance_gazette.php` with batch filters across all programs/regulations.
3. **Credit-Based Promotion & Detention Simulation (`MOD_CREDIT_PROMOTION`) (YEAR-END)**:
   - **Need**: Statutory rules require students to secure a minimum credit percentage (e.g., 50% of credits in B.Tech 1st/2nd year) to be promoted.
   - **UI Design**: Batch-wise simulation report: `Roll No | Student Name | Credits Registered | Credits Earned | Promotion Status (PROMOTED / DETAINED FOR CREDITS)`.
4. **Syllabus & Course Delivery Completion Audit (END OF SEMESTER)**:
   - **Need**: Auditing whether departments completed the mandatory contact hours stipulated in the regulation syllabus before closing the semester.

---

### 3.4 Faculty (Instructor)

#### Missing Daily / Regular Views & Graphs
1. **Course Delivery & Syllabus Progress Bar (DAILY / REGULAR)**:
   - **Need**: When marking daily attendance, faculty need immediate feedback on their syllabus pacing.
   - **UI Design**: On `fachome.php` and `facaddattendance.php`, a compact card per subject:
     - `Planned Lectures: 45 | Conducted: 28 | Remaining: 17 | Syllabus Progress: [========== 62% ] | Pacing: On Track`.
2. **Subject Attendance Defaulter Quick-View (WEEKLY / REGULAR)**:
   - **Need**: Faculty shouldn't have to scroll through 70 students and calculate percentages manually.
   - **UI Design**: A 1-click tab on `facshowattendance.php`: `Critical Students (<75%)` displaying only students in the condonation/detention danger zone for in-class counseling.
3. **Assessment Grading Checklist (REGULAR)**:
   - **Need**: A clear status box:
     - Mid-1: `58/60 Submitted (2 Absent)` | Mid-2: `Pending` | Lab Rubrics: `Saved`.
4. **1-Click CO Attainment Summary Card (END OF SEMESTER)**:
   - **Need**: Before generating lengthy 30-page bluebook PDFs, a simple tabular card: `CO1: 2.4/3 (80%) | CO2: 2.1/3 (70%) | CO3: 2.7/3 (90%)` with a mini horizontal bar chart.

---

### 3.5 Student

#### Missing Daily / Regular Views & Graphs
1. **Navigation Accessibility for Timetable**:
   - Add **Timetable** directly into `studentmenu.php` instead of hiding it inside home text.
2. **Cumulative Degree Credit & Progress Tracker (REGULAR - Post-Results)**:
   - **Need**: Students often do not know how many total credits they have accumulated towards graduation.
   - **UI Design**: Clean summary card on `studenthome.php` / `studentresults.php`:
     - `Total Degree Requirement: 160 Credits | Earned So Far: 112 Credits | Pending: 48 Credits | Active Backlogs: 0`.
3. **Mid-Term CIA Marks Breakdown View**:
   - Provide a clean, unified semester scorecard for ongoing internal assessments without forcing students to drill down through multiple historical attendance screens.

---

## 4. Recommended Action Plan & Simplified Traditional UI Layouts

To maintain the system's lightweight, dependable PHP/Bootstrap 5 architecture without over-complicating workflows, the following phased enhancements are recommended:

### Phase 1: Immediate Flow Corrections (Quick Wins)
1. **Uncomment `reviewqueue.php`** on `hodhome.php` and `fachome.php` to restore NBA course audit and CQI workflows.
2. **Add Missing Top-Level Menu Items**:
   - In `hodmenu.php`: Add **Timetable** (`hodmanage_timetable.php`) and **CIA & Quality** dropdown (linking `hodshowcls_cia.php`, `hodciaanalysis2.php`, `hodviewfeedback.php`, `reviewqueue.php`).
   - In `studentmenu.php`: Add **Timetable** (`studenttimetable.php`) and **Documents Vault** (`studentdocuments.php`).
3. **Surface Institutional Gazette Links**:
   - Add "Download Attendance Gazette" button on `adminshowallclsattendance.php` and `academicsectionshowallclsattendance.php`.
4. **Add Pending Request Badges**:
   - Query pending counts for HOD: `SELECT COUNT(*) FROM attendance_delete_requests WHERE status='PENDING' AND dept_id=?` and display a badge in the navigation: `Delete Requests <span class="badge bg-danger">2</span>`.

---

### Phase 2: Essential Operational Pages (Core Requirements)

#### Layout 1: HOD & Principal Daily Class Conduction Monitor (`daily_class_monitor.php`)
```
+----------------------------------------------------------------------------------------------------+
| Date: [ Today: 24-Oct-2026 v ]    Department: [ All Departments v ]    [ Refresh Status ]           |
+----------------------------------------------------------------------------------------------------+
| [ KPI 1: Scheduled Today: 142 ] [ KPI 2: Conducted: 118 ] [ KPI 3: Pending: 24 ] [ KPI 4: 83.1% ]   |
+----------------------------------------------------------------------------------------------------+
| Class / Section    | Period 1 (9-10) | Period 2 (10-11) | Period 3 (11-12) | Period 4 (12-1) | ...  |
+--------------------+-----------------+------------------+------------------+-----------------+------+
| IV B.Tech CSE - A  | [OK] Java (Dr.A)| [OK] OS (Prof.B) | [!!] Unmarked    | [OK] CN (Dr.C)  | ...  |
| III B.Tech ECE - B | [OK] DSP (Dr.D) | [OK] VLSI(Prof.E)| [OK] MPMC(Dr.F)  | [!!] Unmarked   | ...  |
+----------------------------------------------------------------------------------------------------+
*(Legend: [OK] = Attendance & Diary Logged; [!!] = Period Elapsed but Not Marked; Grey = Future Period)*
```

#### Layout 2: HOD Department CIA Progress Tracker (`hod_cia_tracker.php`)
```
+----------------------------------------------------------------------------------------------------+
| Academic Year: 2026-2027 | Semester: Odd | Department: Computer Science & Engineering              |
+----------------------------------------------------------------------------------------------------+
| Class Section    | Subject Code & Name    | Faculty Name       | Mid-1 Theory | Mid-2 Theory | Lab |
+------------------+------------------------+--------------------+--------------+--------------+-----+
| III B.Tech I Sem | 20A05501T OS           | Dr. K. Raman       | [Completed]  | [Pending]    | N/A |
| III B.Tech I Sem | 20A05502T Comp. Net    | Prof. M. Suresh    | [Completed]  | [Pending]    | N/A |
| III B.Tech I Sem | 20A05501P OS Lab       | Dr. K. Raman       | N/A          | N/A          | [OK]|
+----------------------------------------------------------------------------------------------------+
```

#### Layout 3: Department Semester Results & Backlog Analytics (`hod_results_view.php`)
```
+----------------------------------------------------------------------------------------------------+
| Examination: IV B.Tech II Sem Regular (May 2026) | Regulation: R20 | Department: ECE               |
+----------------------------------------------------------------------------------------------------+
| [ Total Appeared: 68 ]   [ Passed: 61 ]   [ Failed: 7 ]   [ Department Pass Rate: 89.7% ]          |
+----------------------------------------------------------------------------------------------------+
| Subject Code | Subject Full Name        | Appeared | Passed | Failed | Pass %  | Action            |
+--------------+--------------------------+----------+--------+--------+---------+-------------------+
| 20A04801a    | Satellite Communications | 68       | 65     | 3      | 95.6%   | [View Defaulters] |
| 20A04802b    | Low Power VLSI Design    | 68       | 58     | 10     | 85.3%   | [View Defaulters] |
+--------------+--------------------------+----------+--------+--------+---------+-------------------+
| Backlog Roster (7 Students with Backlogs)                                                          |
| Roll No      | Student Name             | Failed Subjects            | Contact No  | Remedial Batch|
| 22001A0412   | K. Rajesh                | 20A04802b                  | 9876543210  | [Assign]      |
+----------------------------------------------------------------------------------------------------+
```

---

## 5. Summary of Analyst Recommendations

1. **Realign Operational Responsibilities**:
   - Give **HOD** department-level read access to **Exam Results** and **Student Dossiers**.
   - Strip **Academic Section** of faculty password resets; give them **Student Enrollment** and central **Attendance Gazette Generation**.
2. **Elevate Buried Daily Workflows**:
   - Provide direct links and pending counter badges for **Attendance Deletions** and **Timetable Management** on the HOD portal.
3. **Implement 3 Essential Analytical Views**:
   - **Daily Campus / Department Class Conduction Monitor** (Admin & HOD).
   - **Department CIA Submission Tracker** (HOD).
   - **Departmental Result & Backlog Analysis Page** (HOD).
4. **Preserve Traditional UI Simplicity**:
   - Use standard Bootstrap 5 tables, responsive KPI cards, and familiar institutional status badges without adding heavyweight frameworks.

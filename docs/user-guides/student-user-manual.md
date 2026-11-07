# Student Academic Record Book

# Student Portal User Manual

Comprehensive Student Guide for JNTUACEA Student Portal (`jntuaceastudents`)

Revised and checked 24 October 2026

Welcome to the **JNTUACEA Student Portal**. This user manual guides undergraduate (B.Tech) and postgraduate (M.Tech, MCA) students through accessing and utilizing all features of the student academic record platform.

---

## Quick Start Checklist

1. **Obtain Credentials**: Receive your portal login credentials (Hall Ticket Number and initial password) from the college Academic Section or Department HOD.
2. **First Login & Security**: Log in via the student portal URL and immediately update your default password (**Settings > Change Password**).
3. **Verify Biographical Profile**: Open **Profile** to ensure your demographic details, parental contacts, Aadhaar number, and admission quota are accurate.
4. **Inspect Enrolled Subjects**: Verify that all core courses, professional electives, open electives, and laboratories for your current semester are listed in **My Subjects**.
5. **Monitor Daily Attendance**: Track your daily period-by-period attendance, faculty lecture diary topics, and review your **Attendance Risk Gauge** safe margins.
6. **Complete End-Semester Surveys**: When feedback windows open, submit your anonymous ratings for Course Outcomes (CO), Course End Surveys (CES), and Faculty Appraisals.
7. **Access Examination Results**: Download official printable Grade Cards and Memorandums of Grades as soon as end-semester examination results are gazetted.
8. **Digital Document Vault & Certificates**: Upload required prerequisite certificates to your private vault and apply online for official institutional certificates (Bonafide, Custodial, Study & Conduct).

---

## Table of Contents

1. [Portal Access & Security](#1-portal-access--security)
   - 1.1 Logging In
   - 1.2 Changing Your Password
   - 1.3 Logging Out
2. [Student Dashboard & Attendance Risk Gauge](#2-student-dashboard--attendance-risk-gauge)
   - 2.1 Dashboard Overview & Statistics
   - 2.2 Attendance Standing Categories
   - 2.3 Safe Margin & Recovery Calculations
3. [Course Enrollment & Subject Attendance](#3-course-enrollment--subject-attendance)
   - 3.1 Enrolled Subjects Register (`studentsubjects.php`)
   - 3.2 Period-by-Period Class Roll & Faculty Diary (`studentsubjectatt.php`)
   - 3.3 Monthly Attendance Summary & Breakdown
4. [Class Timetable & Daily Schedule](#4-class-timetable--daily-schedule)
   - 4.1 Viewing Weekly Timetable (`studenttimetable.php`)
   - 4.2 Room Numbers, Buildings & Instructors
5. [Continuous Internal Assessment (CIA) Marks](#5-continuous-internal-assessment-cia-marks)
   - 5.1 Internal Assessment Visibility
   - 5.2 Mid-Term & Laboratory Internal Marks
6. [End-Semester Examination Results & Grade Cards](#6-end-semester-examination-results--grade-cards)
   - 6.1 Viewing Published Examination Results (`studentresults.php`)
   - 6.2 SGPA vs. Attempt GPA Isolation
   - 6.3 Printing the Official Memorandum of Grades
7. [Student Feedback & Institutional Surveys](#7-student-feedback--institutional-surveys)
   - 7.1 Course Outcome (CO) Indirect Feedback
   - 7.2 Course End Survey (CES)
   - 7.3 Student Appraisal of Faculty & Anonymity Guarantees
8. [Private Document Vault](#8-private-document-vault)
   - 8.1 Uploading Prerequisite Documents (`studentdocuments.php`)
   - 8.2 Verification Status Tracking
   - 8.3 Document Viewing & Security
9. [Institutional Certificate Applications](#9-institutional-certificate-applications)
   - 9.1 Supported Certificate Types
   - 9.2 Applying for a Certificate (`studentcertificates.php`)
   - 9.3 Tracking Approval & Custodial Ledger
   - 9.4 Digital Certificate Verification (`viewcertificate.php`)
10. [Troubleshooting & Help Desk](#10-troubleshooting--help-desk)

---

## 1. Portal Access & Security

### 1.1 Logging In
1. Open your web browser and navigate to the Student Portal URL (e.g., `https://<college-domain>/classattendance/jntuaceastudents/` or local deployment).
2. On the login screen:
   - **Username / Hall Ticket No**: Enter your 10-character official university roll number (e.g., `23001A0501`). All characters are case-insensitive and automatically normalized.
   - **Password**: Enter your secret password.
3. Click **Sign In**.
4. Upon successful authentication, the system opens your personal **Student Dashboard** (`studenthome.php`).

> [!NOTE]
> Sessions automatically expire after 30 minutes of inactivity to protect your personal and academic data on shared or public campus terminals.

### 1.2 Changing Your Password
For account security, change your initial password immediately after your first login:
1. Click your name or user menu in the top navigation bar and select **Change Password** (`studentpass.php`).
2. Enter your **Current Password**.
3. Enter your **New Password** (minimum 8 characters; include upper and lowercase letters, numerals, and special symbols).
4. Re-enter the new password in **Confirm New Password**.
5. Click **Update Password**. A confirmation notification confirms the update.

### 1.3 Logging Out
Always terminate your session before leaving a terminal:
1. Click the user icon in the top right corner.
2. Select **Logout** (`logout.php`).
3. Ensure the browser redirects to the login screen.

---

## 2. Student Dashboard & Attendance Risk Gauge

### 2.1 Dashboard Overview & Statistics
Upon logging in, `studenthome.php` provides an executive overview of your ongoing semester:
- **Student Profile Card**: Displays your Full Name, Hall Ticket Number, Degree Program (e.g., B.Tech Computer Science & Engineering), Current Year & Semester (e.g., III Year - I Semester), and Enrolled Section.
- **Aggregate Attendance Card**: Displays the total number of periods conducted to date, total periods you attended, and your overall cumulative attendance percentage ($\frac{\text{Periods Attended}}{\text{Periods Conducted}} \times 100\%$).

### 2.2 Attendance Standing Categories
In accordance with autonomous university regulations, your cumulative attendance is dynamically classified into one of three statutory standings:

| Attendance Percentage | Standing Status | Badge Color | Statutory Consequence |
| :---: | :---: | :---: | :--- |
| $\ge \mathbf{75.00\%}$ | **ELIGIBLE** | Green | Unconditionally eligible to sit for Semester End Examinations (SEE). |
| $\mathbf{65.00\% - 74.99\%}$ | **CONDONABLE** | Amber | Eligible for SEE only upon payment of statutory Condonation Fee and submission of valid medical/on-duty justification approved by the Principal. |
| $<\mathbf{65.00\%}$ | **DETAINED** | Red | **Detained** due to shortage of attendance. Not eligible to appear for regular Semester End Examinations; must repeat the semester in the next academic year. |

### 2.3 Safe Margin & Recovery Calculations (`MOD_STUDENT_ATTENDANCE_RISK`)
The dashboard features an automated **Predictive Attendance Risk Gauge** that provides real-time actionable advice:

1. **Safe Margin (Classes You Can Miss)**:
   - When your attendance is $\ge 75.00\%$, the system computes how many upcoming classes you can miss while remaining at or above the statutory 75.00% cutoff:
     $$\text{Safe Margin} = \max\left(0, \left\lfloor \frac{\text{Attended} - 0.75 \times \text{Conducted}}{0.75} \right\rfloor\right)$$
   - *Example*: If 100 classes were conducted and you attended 85, your Safe Margin is $\lfloor (85 - 75) / 0.75 \rfloor = 13$ classes.

2. **Recovery Periods Needed (Consecutive Classes to Attend)**:
   - If your attendance drops below $75.00\%$, the system calculates the exact number of consecutive upcoming periods you must attend without absence to regain $75.00\%$ standing:
     $$\text{Recovery Periods} = \max\left(0, \left\lceil \frac{0.75 \times \text{Conducted} - \text{Attended}}{0.25} \right\rceil\right)$$
   - *Example*: If 100 classes were conducted and you attended 70 (70%), you need $\lceil (75 - 70) / 0.25 \rceil = 20$ consecutive attended periods to reach 75%.

---

## 3. Course Enrollment & Subject Attendance

### 3.1 Enrolled Subjects Register (`studentsubjects.php`)
Open **My Subjects** from the main navigation:
1. Renders a comprehensive list of all theory courses, laboratories, electives, and project works mapped to you for the active term.
2. For each subject:
   - **Course Code**: University subject identifier (e.g., `23A05301T`).
   - **Course Title**: Full course title (e.g., `Database Management Systems`).
   - **Course Type**: Theory, Laboratory, Integrated, or Elective.
   - **Attended / Conducted**: Period ratio (e.g., `38 / 42`).
   - **Percentage**: Course-specific attendance percentage.
   - **Exam Result Status**: Badges indicating whether semester results are published.
3. Click on any course card or row to open its detailed subject attendance portal.

### 3.2 Period-by-Period Class Roll & Faculty Diary (`studentsubjectatt.php`)
Clicking on a specific course loads the authenticated subject inspection portal:
1. **Attendance Log Table**:
   - Lists every conducted period chronologically from semester start date to present.
   - **Date & Period**: Date of class and period number (Period 1 to Period 7).
   - **Faculty Teaching Diary**: Exact syllabus topic or laboratory exercise logged by the instructor for that session.
   - **Your Status**: Clearly displays **PRESENT** (green) or **ABSENT** (red).
2. **Duty Leaves & Sanctioned Permissions**:
   - Any periods covered by approved On-Duty (OD) or Medical permissions (`student_permissions`) are clearly indicated.

> [!NOTE]
> **Anti-Scraping & DoS Protection**: Access to `studentsubjectatt.php` is protected by automated scraper detection. Automated scripts, scrapers, or unauthorized web bots are automatically diverted to a lightweight honeypot page (`studentcourseatt.php`) that serves synthetic mock data without hitting the live database. Always browse the portal using a standard, modern web browser.

### 3.3 Monthly Attendance Summary & Breakdown
At the bottom of the subject attendance view, inspect your monthly summary:
- Displays month-wise classes conducted, classes attended, and monthly percentages (e.g., August, September, October, November).
- Helps you verify whether attendance shortages are localized to a specific timeframe.

---

## 4. Class Timetable & Daily Schedule

### 4.1 Viewing Weekly Timetable (`studenttimetable.php`)
Navigate to **Timetable** from the main menu:
1. Displays your section's master weekly schedule grid from Monday through Saturday across all periods (Periods 1 to 7).
2. Reflects the active bell schedule configured in `class_timings` for your cohort.
3. Each slot displays:
   - Course Code & Course Title.
   - Assigned Faculty Instructor name.
   - Building Name & Classroom Hall / Lab Number (e.g., `CSE Block - Room 204` or `DSP Lab`).

### 4.2 Parallel Electives & Lab Batches
- If you are enrolled in a Professional Elective or Open Elective, your specific elective slot is highlighted.
- For laboratory courses running with split batches (e.g., Batch 1 in Networks Lab and Batch 2 in OS Lab), your assigned batch's schedule is displayed.

---

## 5. Continuous Internal Assessment (CIA) Marks

### 5.1 Internal Assessment Visibility
Continuous assessment marks are governed by the academic calendar and feature permissions (`MOD_CIA_MARKS`):
1. Navigate to **My Subjects > [Select Course]**.
2. Scroll to the **Internal Assessment Marks** section.
3. As faculty members enter and finalize internal assessments, your component-wise scores appear:
   - **Theory Courses**:
     - Mid-Term Examination 1 (Subjective, Objective, Assignment).
     - Mid-Term Examination 2 (Subjective, Objective, Assignment).
     - Consolidated Mid Total calculated according to statutory regulation weights (e.g., 80% better mid + 20% lesser mid for R23 B.Tech).
   - **Laboratory Courses**:
     - Day-to-Day evaluation scores (continuous lab performance and record).
     - Internal Test marks.
   - **Postgraduate Courses (MCA / M.Tech)**:
     - Component 1 (Mid 1), Component 2 (Mid 2), and Component 3 (Assignment) or Component 4 (Continuous Evaluation).

> [!IMPORTANT]
> If internal marks display as `Draft` or `Pending Submission`, the faculty member has not yet submitted final validated marks. Only finalized and locked marks are statutory.

---

## 6. End-Semester Examination Results & Grade Cards

### 6.1 Viewing Published Examination Results (`studentresults.php`)
When examination results are gazetted by the Academic and Examination Branch (`MOD_RESULTS_PUBLISH`), navigate to **Results**:
1. The portal loads all published examination notifications for your hall ticket number.
2. Filter by specific examination notification or inspect all terms chronologically.
3. For each published session, the portal displays a dedicated **Memorandum of Grades Card**.

### 6.2 SGPA vs. Attempt GPA Isolation
The platform strictly isolates examination sessions to maintain academic record integrity:
- **REGULAR Examinations**:
  - The grade card displays your official **Semester Grade Point Average (SGPA)**:
    $$\text{SGPA} = \frac{\sum (\text{Course Credits} \times \text{Grade Points})}{\sum \text{Course Credits}}$$
- **SUPPLEMENTARY / REVALUATION Examinations**:
  - For supplementary or backlog attempts, the grade card displays **Attempt GPA**, isolating the credits and points earned in that specific re-examination attempt without distorting regular semester SGPA baselines.
- **Table Columns**:
  - **Subject Code & Course Title**.
  - **Internal Marks (CIE)**.
  - **External Marks (SEE)**.
  - **Total Marks**.
  - **Grade Letter** (e.g., `S`, `A+`, `A`, `B`, `C`, `D`, `E`, `F`, `ABSENT`, `WITHHELD`).
  - **Grade Points** (0 to 10 scale).
  - **Credits Registered & Credits Earned**.
  - **Result Status** (`PASS`, `FAIL`, `ABSENT`, `MALPRACTICE`, `WITHHELD`).

### 6.3 Printing the Official Memorandum of Grades
1. On the Results screen, click the **Print Grade Card** button (`window.print()`).
2. The portal automatically formats the document into an official printable layout:
   - Hides web navigation elements, sidebars, and action buttons (`dontprint`).
   - Renders the formal university header:
     ```
     JAWAHARLAL NEHRU TECHNOLOGICAL UNIVERSITY ANANTAPUR
     COLLEGE OF ENGINEERING (AUTONOMOUS), ANANTHAPURAMU
     STUDENT MEMORANDUM OF GRADES
     ```
   - Includes your Hall Ticket No, Full Name, Regulation, Academic Session, and date-timestamp.
3. Save as PDF or print directly for scholarship or job application requirements.

---

## 7. Student Feedback & Institutional Surveys

Student feedback is a mandatory statutory component for National Board of Accreditation (NBA) and NAAC quality assurance. When survey windows open, navigate to **Feedback** (`studentfeedback.php`):

### 7.1 Course Outcome (CO) Indirect Feedback
- For each enrolled course, rate your perception of outcome mastery across all Course Outcomes (CO1 through CO6).
- 5-point Likert Scale:
  - `5` = Excellent / Completely Achieved
  - `4` = Very Good
  - `3` = Good / Satisfactory
  - `2` = Fair
  - `1` = Poor / Needs Improvement

### 7.2 Course End Survey (CES)
A 16-item survey evaluating the comprehensive course experience across 5 accredited domains:
- **Domain 1**: Curriculum & Syllabus Coverage (Questions 1–4).
- **Domain 2**: Pedagogy & Teaching-Learning Process (Questions 5–8).
- **Domain 3**: Evaluation & Assessment Quality (Questions 9–11).
- **Domain 4**: Learning Resources & Academic Support (Questions 12–14).
- **Domain 5**: Outcome Attainment & Professional Growth (Questions 15–16).
- **Part-C Qualitative Feedback**:
  - Enter constructive open-ended remarks for `Useful Aspects`, `Improvement Topics`, and `Suggestions for Course Enhancement`.

### 7.3 Student Appraisal of Faculty & Anonymity Guarantees
- Evaluates teacher instructional performance across 19 pedagogical criteria (clarity of explanation, punctuality, classroom interaction, grading fairness, out-of-class mentoring).
- **Strict Anonymity Guarantee**:
  - All faculty appraisal submissions are permanently anonymized (`is_anonymous = 1`).
  - Neither your Hall Ticket Number, Name, IP address, nor timestamp is ever revealed to faculty members or departmental heads.
  - Faculty members and HODs only see aggregated ratings, percentage distributions, and anonymized qualitative feedback clusters. Feel free to provide honest, constructive feedback.

---

## 8. Private Document Vault

### 8.1 Uploading Prerequisite Documents (`studentdocuments.php`)
The college provides a private digital document vault to store and verify scanned copies of your prerequisite educational and statutory certificates.
1. Navigate to **Document Vault** (`studentdocuments.php`).
2. Select the **Document Type**:
   - `SSC_MEMO`: 10th / SSC Marks Memo.
   - `INTER_DIPLOMA_MEMO`: Intermediate / Polytechnic Diploma Memo.
   - `ALLOTMENT_ORDER`: Official Convener Admission Allotment Order.
   - `CASTE_CERTIFICATE`: Community / Caste Certificate (if applicable).
   - `INCOME_CERTIFICATE`: Statutory Income Certificate (for fee reimbursement).
   - `AADHAR_CARD`: Government Aadhaar Card scan.
   - `STUDY_CERTIFICATES`: 6th to 12th Study / Residence Certificates.
   - `TRANSFER_CERTIFICATE`: Original Transfer Certificate scan.
   - `OTHER`: Any additional academic or extracurricular credentials.
3. Click **Browse** and select your file:
   - Accepted formats: `.pdf`, `.png`, `.jpg`, `.jpeg`.
   - Maximum file size: 5 MB per document.
4. Click **Upload Document**.

### 8.2 Verification Status Tracking
Uploaded documents are listed in your vault with real-time verification indicators:
- **Pending Verification** (Yellow): Document uploaded; awaiting physical inspection by Academic Section staff.
- **Verified** (Green): Inspected, verified, and approved against physical original records.
- **Rejected** (Red): Unclear scan, incorrect document, or mismatch. Read the displayed administrative remarks, remove the rejected file, and upload a clear replacement.

### 8.3 Document Viewing & Security
- All files in the Document Vault are stored in a secure repository outside the public web root (`COLLEGE_DOCUMENT_ROOT`).
- Files can only be accessed through authenticated streaming (`viewdocument.php`), which cryptographically verifies student session ownership before delivering file bytes. Direct linking or hotlinking is strictly blocked.

---

## 9. Institutional Certificate Applications

### 9.1 Supported Certificate Types
Students can request digitally signed and numbered institutional certificates via **Certificates** (`studentcertificates.php`):

1. **Bonafide Certificate (`BONAFIDE`)**: Certifies that you are a bonafide student of JNTUACEA for the specified academic year; used for bus passes, train concessions, and bank accounts.
2. **Study & Conduct Certificate (`STUDY_CONDUCT`)**: Certifies your enrolled period, academic standing, and institutional conduct.
3. **Custodial Certificate (`CUSTODIAL`)**: Formally lists all physical original certificates (SSC, Intermediate, TC) currently held in college institutional custody; required when applying for passport issuance, visa interviews, or education bank loans.
4. **Transfer Certificate (`TRANSFER_CERTIFICATE`)**: Issued upon course completion or formal institutional clearance.
5. **No Dues Clearance Certificate (`NO_DUES`)**: Interdepartmental clearance from Library, Hostels, Sports, and Academic Section.

### 9.2 Applying for a Certificate (`studentcertificates.php`)
1. Open **Certificates > Apply for New Certificate**.
2. Select the **Certificate Type** from the dropdown menu.
3. Enter the **Purpose of Application** (e.g., "Applying for Passport Verification" or "State Scholarship Submission").
4. Specify the **Number of Copies Required**.
5. Click **Submit Application**.

### 9.3 Tracking Approval & Custodial Ledger
- Your application enters the state machine:
  $$\text{REQUESTED} \longrightarrow \text{UNDER\_REVIEW} \longrightarrow \text{APPROVED} \longrightarrow \text{ISSUED}$$
- For **Custodial Certificates**, the Academic Section cross-references the physical certificates held in your custody ledger (`student_custodial_records`). If an original certificate is temporarily checked out, return it before applying.
- Once approved, an official unique serial number is generated from the college atomic sequence generator (`\College\CertificateNumber`, e.g., `JNTUACEA/CER/2026/00142`).

### 9.4 Digital Certificate Verification (`viewcertificate.php`)
1. Click **View Certificate** beside any approved application to view and print the official document.
2. Each certificate includes an unguessable 64-character verification token and QR code.
3. External authorities, employers, and visa officers can verify the certificate's authenticity by visiting the verification URL (`viewcertificate.php?id=<ID>&token=<TOKEN>`). The public verification page confirms the student's name, roll number, certificate number, and issue date without exposing private student records.

---

## 10. Troubleshooting & Help Desk

### Common Issues & Solutions

| Issue | Probable Cause | Recommended Resolution |
| :--- | :--- | :--- |
| **Cannot log in / Invalid credentials** | Incorrect password or caps lock active. | Re-enter your 10-digit roll number. If forgotten, contact your Department HOD or Academic Section administrator for a temporary password reset. |
| **Enrolled subjects list is empty** | Academic Section has not yet mapped your cohort to the active term. | Contact your department office or HOD to confirm that student subject mappings (`student_sub`) have been initialized for your class. |
| **Attendance appears missing for recent dates** | Faculty member has not yet submitted the attendance roll for that period. | Check back after 24 hours. Attendance is updated dynamically as faculty mark daily teaching diaries. |
| **Results page shows "No Published Results"** | Examination results for your semester are still in `DRAFT` or `REVIEWED` status. | Results become visible only after formal publication by the Controller of Examinations. Wait for the official gazette announcement. |
| **Grade Card shows "Pending" for SGPA** | Grade points for one or more courses are withheld (`WITHHELD`) or incomplete. | Contact the Examination Section with your hall ticket number to resolve the pending course score. |
| **Document upload fails** | File exceeds 5 MB or is an unsupported file extension. | Convert your document scan to a standard PDF or PNG file under 5 MB and retry. |
| **Certificate request rejected** | Outstanding college dues or incorrect purpose stated. | Read the administrative remarks on the request card, resolve any pending library/hostel dues, and re-apply. |

### Institutional Contact Directory
- **Department HOD Office**: For class timetable adjustments, subject enrollment discrepancies, and duty leave approvals.
- **Academic Section**: For biographical profile corrections, original certificate custody ledger, and Bonafide/Custodial certificate issuance.
- **Examination Branch**: For result discrepancies, revaluation requests, and official duplicate memorandum of grades.
- **Computer Centre / Portal Helpdesk**: For login lockouts, password resets, and portal technical assistance.

---
*End of Student Portal User Manual.*

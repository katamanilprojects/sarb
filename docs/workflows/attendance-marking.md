# Attendance Marking & Diary Entry Workflow

This document describes the complete lifecycle of marking daily class attendance and entering teaching diary logs by faculty members.

---

## 1. Workflow Architecture & Sequence

```mermaid
sequenceDiagram
    autonumber
    actor Faculty as Faculty Member
    participant UI as facaddattendance.php
    participant Model as faculty.class.php
    participant DB as MariaDB (attendance & diary)
    participant Log as fac_activity_logs

    Faculty->>UI: Select Subject & Date
    UI->>Model: getUnmarkedHours(sub_id, date)
    Model->>DB: Query timetable & existing attendance
    DB-->>Model: Return unrecorded periods (e.g., [1, 2])
    Model-->>UI: Populate Hour Checkboxes & Student Roster
    
    Faculty->>UI: Select Hour(s), Uncheck Absentees, Enter Diary notes
    Faculty->>UI: Submit Form (POST)
    
    UI->>Model: markAttendance(hours, studentIds, sub_id, date, diary, fac_id)
    Note over Model,DB: Begin Database Transaction
    
    loop For each selected Hour
        Model->>DB: INSERT INTO diary (sub_id, faculty_id, date, hour, diary)
    end
    
    Model->>DB: getAllMappedStudents(sub_id)
    DB-->>Model: Return all enrolled student IDs
    
    loop For each selected Hour
        loop For each enrolled Student
            Note over Model: status = in_array(stu_id, studentIds) ? 'P' : 'A'
            Model->>DB: INSERT INTO attendance (stu_id, sub_id, date, hour, status)
        end
    end
    
    Model->>Log: dbActivityLog(fac_id, "Attendance", details, "Faculty")
    Note over Model,DB: Commit Transaction
    
    Model-->>UI: Return ['status' => 1]
    UI-->>Faculty: Display success notification
```

---

## 2. Step-by-Step Execution

### Step 1: Subject Selection & Unmarked Hours Fetch
- Faculty logs in and navigates to `facaddattendance.php`.
- The system loads the faculty's assigned subjects via the `Subject` service (`Subject::getOfferedSubjectsByFaculty($faculty_id)`).
- When a subject and date are picked, an AJAX request invokes `Faculty::getUnmarkedHours($sub_id, $date)`.
- The method compares the class timing periods configured in `class_timing_schedule` against periods already marked in `attendance` for that `sub_id` and `date`.
- Only unrecorded hours are displayed as selectable checkboxes. This prevents accidental duplicate attendance submissions.

### Step 2: Student Enrollment Verification
- The roster displayed to the faculty is populated from the `student_sub` junction table via `Faculty::getMappedStudents($sub_id)`.
- By default, all enrolled students are pre-checked as "Present".
- The faculty unchecks the checkboxes corresponding to students who are absent.

### Step 3: Frictionless Daily Teaching Diary
- Faculty enters a natural free-text summary of syllabus topics covered during the selected periods.
- **Zero-Friction Design**: Daily marking is 100% focused on natural topic logging with zero friction (no mandatory CO selection, no popup prompts during daily attendance).
- Lesson plan alignment and CO tagging are performed systematically at the end of the semester during the reconciliation phase (`faclessonplanreconciliation.php`), linking `diary.lesson_plan_id` to planned lectures.
- Submitting attendance without a diary entry is prevented by client-side and server-side validation.

### Step 4: Transactional Database Insertion
When the faculty submits the form:
1. `Faculty::markAttendance()` starts a transaction: `$this->conn->begin_transaction()`.
2. A record is inserted into the `diary` table for each selected period:
   ```sql
   INSERT INTO diary (sub_id, faculty_id, date, hour, diary) VALUES (?, ?, ?, ?, ?);
   ```
3. All enrolled students for that subject are fetched via `getAllMappedStudents($sub_id)`.
4. For every selected period, an attendance record is created for every mapped student:
   ```sql
   INSERT INTO attendance (stu_id, sub_id, date, hour, status) VALUES (?, ?, ?, ?, ?);
   ```
   - If the student's ID was in the checked array, `status = 'P'`.
   - If not checked, `status = 'A'`.
5. An audit entry is recorded in `fac_activity_logs`.
6. `$this->conn->commit()` is executed. If any insert fails, `$this->conn->rollback()` runs, preventing partial or inconsistent data.

---

## 3. Exceptional Attendance Marking

For special college events, sports meets, campus placement drives, or institutional permissions:
- Handled in `facexceptionalattendance.php`.
- Uses `Faculty::getStudentsForExceptionalAttendance($sub_id, $date, $hour)` and `Faculty::markExceptionalAttendance()`.
- Allows faculty to mark or update attendance for designated students outside standard timetable slots, automatically checking `student_permissions` table for sanctioned duty leaves.

---

## 4. Attendance Reports & Official Bluebook Exports

Faculty and administrators can inspect and export marked attendance through:
- **`facshowattendance.php`**: Daily period-by-period attendance view and monthly summary.
- **`adminshowallclsattendance.php` / `hodshowallclsattendance.php` / `academicsectionshowallclsattendance.php`**: Aggregate class percentage reports categorized by autonomous rules:
  - $\ge 75\%$: Satisfactory / Eligible for Semester End Examinations.
  - $65\% \text{ to } 74.99\%$: Condonation eligible (subject to medical certificate & fee).
  - $< 65\%$: Detention risk / Ineligible for SEE.
- **Attendance Condonation & Detention Gazette PDF (`MOD_ATTENDANCE_CONDONATION_GAZETTE`)**:
  - Exported via `download_attendance_gazette.php?class_id=X` from `hodshowclsattendance.php`.
  - Classifies enrolled cohort into **Eligible ($\ge 75\%$)**, **Condonation ($65.0\% \le A < 75.0\%$)**, and **Detained ($< 65.0\%$ or single subject $< 40\%$)** with signature blocks for HOD, Principal, and Controller of Examinations.
- **Student Attendance Risk Gauge (`MOD_STUDENT_ATTENDANCE_RISK`)**:
  - Displayed on `studenthome.php` with real-time **Safe Margin** ($\max(0, \lfloor(P - 0.75T)/0.75\rfloor)$) and **Recovery Periods Needed** ($\max(0, \lceil(0.75T - P)/0.25\rceil)$).
- **`download_bluebook.php`**: Generates the official university e-Bluebook via `EBluebookPDFService`:
  - **Section 2B ('DIARY OF LECTURER CLASSES')**: Rendered in a clean 4-column format (`S.No`, `Date`, `Time`, `Topics Covered`).
  - **Section 3 ('ATTENDANCE REGISTER')**: Comprehensive student-by-student period roll with monthly aggregates and overall attendance percentages.

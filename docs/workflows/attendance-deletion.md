# Attendance Deletion & Correction Lifecycle

This document describes the governance, audit control, and execution sequence for attendance deletions and corrections.

---

## 1. Overview & Policy Rationale

To maintain academic integrity and prevent unauthorized alteration of official attendance records:
- **No Direct Deletion by Faculty**: Faculty members cannot directly delete or alter previously submitted attendance sessions.
- **Audited Workflow**: Faculty must submit an explicit deletion request detailing the course, date, hour, and justification.
- **HOD Approval Gate**: Only the Head of the Department (HOD) can approve or reject the request.
- **Atomic Removal**: On approval, the system atomically purges the corresponding period records from both the `diary` and `attendance` tables, releasing that hour so it can be re-entered if necessary.

---

## 2. Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    actor Faculty
    participant FacUI as facadddelattrequest.php
    participant FacModel as faculty.class.php
    participant DB as MariaDB (attendance_delete_requests)
    actor HOD
    participant HODUI as hodviewdelrequests.php
    participant HODModel as hod.class.php
    participant Tables as diary & attendance

    Faculty->>FacUI: Navigate to "Delete Attendance Request"
    FacUI->>FacModel: getAvailableHours(sub_id, date)
    FacModel->>DB: Query diary records not pending deletion
    DB-->>FacModel: Return marked hours
    FacModel-->>FacUI: Populate Hour selection dropdown

    Faculty->>FacUI: Select Hour, Enter Reason, Submit
    FacUI->>FacModel: submitAttendanceDeleteRequest(fac_id, sub_id, date, hour, reason)
    FacModel->>DB: INSERT INTO attendance_delete_requests (status='pending')
    DB-->>FacModel: Success
    FacModel-->>FacUI: Request queued

    Note over DB,HODUI: Request sits in pending status

    HOD->>HODUI: Open "View Deletion Requests"
    HODUI->>HODModel: getAttendanceDeleteRequests(dept_id)
    HODModel->>DB: SELECT requests JOIN faculties, subjects WHERE dept_id = ?
    DB-->>HODModel: List of pending deletion requests
    HODModel-->>HODUI: Display table with Faculty, Course, Date, Hour, Reason

    alt HOD Rejects Request
        HOD->>HODUI: Click "Reject"
        HODUI->>HODModel: processDeleteRequest(id, 'Rejected')
        HODModel->>DB: UPDATE status='Rejected', approval_date=NOW()
        HODModel-->>HODUI: Status updated; no records deleted
    else HOD Approves Request
        HOD->>HODUI: Click "Approve"
        HODUI->>HODModel: processDeleteRequest(id, 'Approved')
        HODModel->>DB: UPDATE status='Approved', approval_date=NOW()
        HODModel->>HODModel: deleteAttendanceAndDiaryEntries(id)
        HODModel->>Tables: DELETE FROM diary WHERE sub_id=? AND date=? AND hour=?
        HODModel->>Tables: DELETE FROM attendance WHERE sub_id=? AND date=? AND hour=?
        HODModel-->>HODUI: Request approved; records purged
    end
```

---

## 3. Database Table Structure (`attendance_delete_requests`)

| Column | Type | Description |
|---|---|---|
| `id` | `int(11)` PK | Auto-increment unique request ID |
| `faculty_id` | `int(11)` FK | References `faculties.id` (requester) |
| `subject_id` | `int(11)` FK | References `subjects.id` |
| `date` | `varchar(10)` | Class date (YYYY-MM-DD) |
| `hour` | `int(11)` | Period number (1 to 7) |
| `reason` | `text` | Explanation for why deletion is requested |
| `status` | `enum` | `'pending'`, `'approved'`, `'rejected'` |
| `hod_remarks` | `text` | Optional remarks by HOD |
| `request_date` | `timestamp` | Submission timestamp |
| `approval_date`| `timestamp` | Decision timestamp |

---

## 4. Method Reference

### Faculty Model (`faculty.class.php`)
- `submitAttendanceDeleteRequest($faculty_id, $sub_id, $date, $hour, $reason)`: Validates period and inserts a new row into `attendance_delete_requests` with status `'Pending'`.
- `getAvailableHours($sub_id, $date)`: Returns all hours for which attendance was marked in `diary` that do **not** already have a pending deletion request.
- `getSubmittedRequests($faculty_id)`: Fetches historical deletion requests submitted by that faculty member and their current statuses.

### HOD Model (`hod.class.php`)
- `getAttendanceDeleteRequests($dept_id)`: Fetches all pending deletion requests for faculty members belonging to the HOD's department.
- `processDeleteRequest($request_id, $action)`: Sets status to `'Approved'` or `'Rejected'`, updates `approval_date = NOW()`, logs the action to `audit_logs`, and atomically purges the records if approved.
- `deleteAttendanceAndDiaryEntries($request_id)`: Internal helper called upon approval that executes:
  ```sql
  DELETE FROM diary WHERE sub_id = ? AND date = ? AND hour = ?;
  DELETE FROM attendance WHERE sub_id = ? AND date = ? AND hour = ?;
  ```
- `getAttendanceDeleteAuditLogs($dept_id)`: Queries institutional `audit_logs` table for all deletion request events (`ATTENDANCE_DELETE_REQUEST_APPROVED`, `ATTENDANCE_DELETE_REQUEST_REJECTED`) within the department, providing forensic traceability of user ID, role, client IP address, and timestamp.

---

## 5. Institutional Governance & Audit History Review

Under `MOD_ATTENDANCE_DELETE_REQUESTS`:
- **Single-Tier HOD Authorization Preserved**: Approval remains strictly under departmental HOD authority. Dual-authorization requiring Academic Section approval is avoided to maintain operational velocity.
- **Audit History Tab in `hodviewdelrequests.php`**: Department heads can switch between the "Pending Requests" queue and the "Audit Trail History" tab to review historical deletion decisions, faculty justifications, and decision timestamps.
- **Forensic Integrity**: Every deletion event writes an immutable entry into `audit_logs` capturing client IP, user session, and affected period parameters.

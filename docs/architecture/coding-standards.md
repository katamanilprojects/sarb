# Coding Standards & Conventions

This document establishes the code formatting, documentation, database interaction, and error handling standards used across the project.

---

## 1. PHPDoc Method Documentation Standard

All public and protected methods in `*.class.php` models must include comprehensive PHPDoc comments detailing the method's purpose, parameters, return structure, and a brief usage example.

```php
/**
 * Enroll a new student into a class and provision their user account.
 *
 * @param string      $rollno          Unique student roll number / hall ticket number
 * @param string      $name            Full legal name of the student
 * @param int         $class_id        Target class ID from the `classes` table
 * @param string|null $date_of_joining Optional admission date (format: YYYY-MM-DD)
 * 
 * @return array{
 *     status: int,
 *     data?: array,
 *     err?: string,
 *     message?: string
 * } Status 1 on success, 0 on validation or database failure
 *
 * @example
 * $admin = new Admin();
 * $res = $admin->addStudent('20011A0501', 'Anil Kumar', 12, '2020-11-01');
 * if ($res['status'] === 1) {
 *     // Success logic
 * }
 */
public function addStudent($rollno, $name, $class_id, $date_of_joining = null)
{
    // ...
}
```

---

## 2. Standardized Method Response Structure

Model methods must return a predictable associative array rather than raw booleans or echoing output directly. This decouples business logic from presentation.

### Success Response:
```php
return [
    'status'  => 1,
    'data'    => $results,         // Array of records or created record ID
    'message' => 'Record created successfully' // Optional human-readable notification
];
```

### Error Response:
```php
return [
    'status' => 0,
    'err'    => 'Invalid input or database execution failure',
    'data'   => []
];
```

---

## 3. Database Prepared Statements & SQL Safety

All queries accepting user or session input **must** use MySQLi prepared statements. Never concatenate variables directly into SQL strings.

```php
// Correct
$stmt = $this->conn->prepare("SELECT id, name FROM faculties WHERE dept_id = ? AND status = ?");
if (!$stmt) {
    $this->logs->errLog($this->classname . " - prepare failed: " . $this->conn->error);
    return ['status' => 0, 'err' => 'Database error'];
}

$status = 1;
$stmt->bind_param("ii", $dept_id, $status);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
```

---

## 4. Database Transaction Standards

Whenever an operation alters multiple tables or multiple rows that must succeed or fail together, wrap the calls inside an atomic transaction:

```php
$res = ['status' => 0];
$myname = $this->classname . " - markAttendance - ";

try {
    $this->conn->begin_transaction();

    // 1. Insert into diary
    $stmt1 = $this->conn->prepare("INSERT INTO diary (sub_id, faculty_id, date, hour, diary) VALUES (?, ?, ?, ?, ?)");
    // ... bind and execute ...
    $stmt1->close();

    // 2. Insert into attendance
    $stmt2 = $this->conn->prepare("INSERT INTO attendance (stu_id, sub_id, date, hour, status) VALUES (?, ?, ?, ?, ?)");
    // ... loop, bind and execute ...
    $stmt2->close();

    // 3. Activity log
    $this->dbActivityLog($faculty_id, "Attendance", "Marked for Subj ID: $sub_id", "Faculty");

    // Commit only if all operations succeeded
    $this->conn->commit();
    $res['status'] = 1;
} catch (Exception $e) {
    $this->conn->rollback();
    $this->logs->errLog($myname . "Transaction failed: " . $e->getMessage());
    $res['err'] = "Failed to mark attendance. Transaction rolled back.";
}

return $res;
```

---

## 5. Page Controller Security Pattern

Each procedural controller page must check session status and authorized role before executing business logic:

```php
<?php
session_start();

// 1. Enforce authentication and role
if (empty($_SESSION['user']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ./index.php");
    exit();
}

// 2. Load dependencies
require_once("faculty.class.php");
$faculty = new Faculty();

// 3. Page logic and rendering ...
```

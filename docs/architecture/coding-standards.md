# Coding Standards & Conventions

This document establishes the code formatting, documentation, database interaction, transaction/snapshot safety, request security, and cross-portal core parity standards used across the project.

---

## 1. PHPDoc & Type Safety Standards

All public and protected methods in `*.class.php` models must include PHPDoc comments detailing the method's purpose, parameters, return structure, and usage context. All new classes in `core/` and `services/` must declare `declare(strict_types=1);` at the top of the file and use explicit parameter and return type hints.

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
 */
public function addStudent($rollno, $name, $class_id, $date_of_joining = null)
{
    // ...
}
```

---

## 2. Standardized Method Response Structure

Legacy model methods return a predictable associative array rather than raw booleans or echoing output directly, while `core/` and `services/` classes may use typed return arrays or throw `InvalidArgumentException` / `DomainException` / `RuntimeException` for invalid inputs, violated invariants, or failed database writes.

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

## 3. Database Prepared Statements & Connection Reuse

1. **Single Shared Connection**: Always obtain the database handle via `DBCredentials` (`$this->conn`) or `\College\Connection::get()`, ensuring one connection per request across all models, services, and audit writes.
2. **Prepared Statements Only**: All queries accepting user or session input **must** use MySQLi prepared statements. Never concatenate variables directly into SQL strings.

```php
$stmt = $this->conn->prepare("SELECT id, username, designation FROM faculties WHERE dept_id = ? AND status = ? ORDER BY sort_order ASC, id ASC");
if (!$stmt) {
    $this->logs->errLog($this->classname . " - prepare failed: " . $this->conn->error);
    return ['status' => 0, 'err' => 'Database error'];
}

$status = '1';
$stmt->bind_param("is", $dept_id, $status);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
```

---

## 4. Database Transaction, Snapshot & Audit Standards

Whenever an operation alters multiple tables, mutates existing academic records, or records an audited decision, wrap all data mutations, before/after snapshots (`academic_record_snapshots`), workflow history (`workflow_review_history`), and unified audit logs (`audit_logs`) inside a single atomic transaction:

```php
$this->conn->begin_transaction();
try {
    // 1. Lock target records (FOR UPDATE) and capture before_json state when mutating existing data
    // 2. Execute domain INSERT / UPDATE / DELETE statements
    // 3. Record before/after snapshot in academic_record_snapshots (with actor users.id and reason)
    // 4. Write to unified audit_logs via $this->dbActivityLog(...) and verify it succeeds
    if (!$this->dbActivityLog((int)$_SESSION['userid'], 'ACTION_CODE', 'Descriptive details', $_SESSION['role'], 'ENTITY_TYPE', (string)$entityId)) {
        throw new \RuntimeException('Audit log failed');
    }

    $this->conn->commit();
} catch (\Throwable $e) {
    $this->conn->rollback();
    $this->logs->errLog("Transaction failed: " . $e->getMessage());
    throw $e;
}
```

### Key Audit Invariants:
- Always pass the authenticated `users.id` (`$_SESSION['userid']`)—never `faculties.id` or `students.id`—as the actor identity in `audit_logs`, `academic_record_snapshots`, and `workflow_review_history`.
- Verify that `dbActivityLog()` returns `true` inside audited transactions so that if the audit write fails, the data mutation rolls back with it.

---

## 5. Page Controller Security, CSRF & Scope Pattern

Each procedural controller page must enforce authentication, role authorization, feature/action permissions, CSRF protection, and record scope verification:

```php
<?php
session_start();

// 1. Enforce authentication and role
if (empty($_SESSION['user']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ./index.php");
    exit();
}

// 2. Load dependencies (instantiating DBCredentials automatically runs RequestSecurity::guardResources)
require_once("faculty.class.php");
require_once("services/FeatureManager.php");

// 3. Enforce module visibility and granular action permission ('view', 'edit', 'approve', 'export')
FeatureManager::requireAccess('MOD_ATTENDANCE');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \College\RequestSecurity::requirePostCsrf();
    FeatureManager::requireAction('MOD_ATTENDANCE', 'edit');
}

// 4. Verify record ownership scope before operating on subject/class/student IDs
$faculty = new Faculty();
if (!empty($_REQUEST['sub_id'])) {
    $faculty->assertSubjectScope((int)$_REQUEST['sub_id']);
}
```

---

## 6. Shared Trusted Core Synchronization (`core/`)

The 29 foundational classes in `jntuacea/core/` (`namespace College`) are shared with the student portal (`jntuaceastudents/core/`):
1. Always edit shared core files in `jntuacea/core/` first.
2. Synchronize and update `core/release.json` across both portals by running:
   ```bash
   php tools/sync_core.php
   ```
3. Verify byte-for-byte parity across all 29 core files before committing:
   ```bash
   php tools/sync_core.php --check
   ```

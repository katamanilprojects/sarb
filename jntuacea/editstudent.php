<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['academic_section', 'admin', 'superadmin'], true)) {
    header("Location: ./");
    exit();
}

$page_title = "Edit Student Profile";
if ($userRole === 'academic_section') {
    require_once("academicsectionheader.php");
} else {
    require_once("adminheader.php");
}
require_once("admin.class.php");

$obj = new Admin();
$student_id = $_GET['student_id'];

// Fetch student details
$student = $obj->getStudentDetails($student_id);

// Handle form submission for updating student details
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    // Sanitize and update student information
    $name = trim($_POST['name']);
    $roll_number = trim($_POST['roll_number']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $date_of_joining = !empty($_POST['date_of_joining']) ? trim($_POST['date_of_joining']) : null;

    $result = $obj->updateStudentDetails($student_id, $name, $roll_number, $email, $mobile, $date_of_joining);

    if ($result['status'] == 1) {
        $msg = "Student details updated successfully.";
        // Fetch updated details
        $student = $obj->getStudentDetails($student_id);
    } else {
        $err = "Failed to update student details. Please try again.";
    }
}

// Generate a new secret code for the form
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Edit Student Profile</h4>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if (isset($msg)) { echo "<div class='alert alert-success'>{$msg}</div>"; } ?>
    <?php if (isset($err)) { echo "<div class='alert alert-danger'>{$err}</div>"; } ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="post" action="editstudent.php?student_id=<?= $student_id ?>">
                <div class="mb-3">
                    <label for="name" class="form-label fw-bold">Student Name:</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="roll_number" class="form-label fw-bold">Roll Number:</label>
                    <input type="text" name="roll_number" id="roll_number" class="form-control" value="<?= htmlspecialchars($student['roll_number'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label fw-bold">Email (Optional):</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label for="mobile" class="form-label fw-bold">Mobile (Optional):</label>
                    <input type="text" name="mobile" id="mobile" class="form-control" maxlength="10" value="<?= htmlspecialchars($student['mobile'] ?? '') ?>" pattern="[0-9]{10}" title="Enter a valid 10-digit mobile number">
                </div>
                <div class="mb-3">
                    <label for="date_of_joining" class="form-label fw-bold">Date of Joining (Optional):</label>
                    <input type="date" name="date_of_joining" id="date_of_joining" class="form-control" value="<?= htmlspecialchars($student['date_of_joining'] ?? '') ?>">
                </div>

                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Details</button>
                    <a href="adminfacst.php" class="btn btn-outline-secondary">Departments Hub</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($userRole === 'academic_section') {
    require_once("academicsectionfooter.php");
} else {
    require_once("adminfooter.php");
}
?>
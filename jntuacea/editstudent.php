<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Edit Student Profile";
require_once("adminheader.php");
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

<div class="container">
    <h4>Edit Student Profile</h4>

    <?php if (isset($msg)) { echo "<div class='alert alert-success'>{$msg}</div>"; } ?>
    <?php if (isset($err)) { echo "<div class='alert alert-danger'>{$err}</div>"; } ?>

    <form method="post" action="editstudent.php?student_id=<?= $student_id ?>">
        <div class="form-group">
            <label for="name">Student Name:</label>
            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="roll_number">Roll Number:</label>
            <input type="text" name="roll_number" id="roll_number" class="form-control" value="<?= htmlspecialchars($student['roll_number']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
        </div>
        <div class="form-group">
            <label for="mobile">Mobile:</label>
            <input type="text" name="mobile" id="mobile" class="form-control" maxlength="10" value="<?= htmlspecialchars($student['mobile']) ?>" required pattern="[6789][0-9]{9}" title="Enter a valid 10-digit mobile number">
        </div>
        <div class="form-group">
            <label for="date_of_joining">Date of Joining:</label>
            <input type="date" name="date_of_joining" id="date_of_joining" class="form-control" value="<?= htmlspecialchars($student['date_of_joining'] ?? '') ?>">
        </div>

        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
        <button type="submit" class="btn btn-primary mt-3">Update Details</button>
    </form>
</div>

<?php
require_once("adminfooter.php");
?>
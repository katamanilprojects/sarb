<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "Reset Student Pwd";
require_once("adminheader.php");
require_once("admin.class.php");

$obj = new Admin();
$student_details = [];
$error = "";
$success = "";
$step = 1; // 1: Enter username, 2: Show details & reset

if (!empty($_POST)) {
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        
        if (!empty($_POST['action']) && $_POST['action'] == 'fetch') {
            // Step 1: Fetch student details
            if (!empty($_POST['username'])) {
                $result = $obj->getStudentByUsername($_POST['username']);
                if (!empty($result['status']) && $result['status'] == 1) {
                    $student_details = $result['data'];
                    $step = 2;
                    $_SESSION['reset_username'] = $_POST['username'];
                } else {
                    $error = "Student not found. Please check the username/roll number.";
                }
            } else {
                $error = "Please enter a username.";
            }
        } elseif (!empty($_POST['action']) && $_POST['action'] == 'reset') {
            // Step 2: Execute reset
            if (!empty($_SESSION['reset_username'])) {
                $username = $_SESSION['reset_username'];
                $remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : "";
                
                $res = $obj->resetStudentPwdWithRemarks($username, $remarks);
                if (!empty($res['status']) && $res['status'] == 1) {
                    $success = "Password Reset Successfully!<br/>Default password is student's roll number. User can change it after login.";
                    unset($_SESSION['reset_username']);
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
                $step = 1;
            } else {
                $error = "Session expired. Please start over.";
                $step = 1;
            }
        }
    } else {
        $error = "Session validation failed. Please try again.";
    }
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br>
    <div class="card">
        <div class="card-header">Reset Student Password</div>
        <div class="card-body">
            <div class="row">
                <div class="col-sm-2"></div>
                <div class="col-sm-8">
                    <?php
                    if (!empty($error)) {
                        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
                    }
                    if (!empty($success)) {
                        echo '<div class="alert alert-success">' . $success . '</div>';
                    }
                    ?>

                    <?php if ($step == 1) { ?>
                        <!-- Step 1: Enter Username -->
                        <h5>Step 1: Enter Student Username/Roll Number</h5>
                        <form action="adminresetstudentpwd.php" method="post">
                            <div class="form-group">
                                <label for="username">Student Username/Roll Number</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Enter student roll number" required maxlength="50">
                            </div>
                            <input type="hidden" name="action" value="fetch">
                            <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                            <button type="submit" class="btn btn-primary">Fetch Details</button>
                            <a href="adminhome.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php } elseif ($step == 2 && !empty($student_details)) { ?>
                        <!-- Step 2: Show Details & Reset -->
                        <h5>Step 2: Confirm & Reset Password</h5>
                        <div class="alert alert-info">
                            <strong>Student Details:</strong>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Roll Number:</strong></td>
                                    <td><?php echo htmlspecialchars($student_details['username']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($student_details['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($student_details['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mobile:</strong></td>
                                    <td><?php echo htmlspecialchars($student_details['mobile']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php 
                                        echo ($student_details['status'] == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <form action="adminresetstudentpwd.php" method="post">
                            <div class="form-group">
                                <label for="remarks">Remarks (Optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" 
                                          placeholder="Enter reason for password reset (e.g., Forgot password, Initial reset)" 
                                          rows="3" maxlength="500"></textarea>
                                <small class="form-text text-muted">This will be logged in the activity log.</small>
                            </div>

                            <div class="alert alert-warning">
                                <strong>Default Password:</strong> Student's roll number (will be hashed as bcrypt)<br>
                                Student must change password after first login.
                            </div>

                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset this student\'s password?');">
                                <i class="bi bi-exclamation-triangle me-1"></i> Reset Password
                            </button>
                            <a href="adminresetstudentpwd.php" class="btn btn-secondary">Reset Form</a>
                        </form>
                    <?php } ?>
                </div>
                <div class="col-sm-2"></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>

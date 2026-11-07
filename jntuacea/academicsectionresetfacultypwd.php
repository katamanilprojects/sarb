<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Academic Section no longer has permission to reset faculty passwords.
// Faculty password resets are restricted to HOD (within department) and Admin (across departments).
$_SESSION['err'] = "Access Denied: Academic Section does not have permission to reset faculty passwords. Contact Principal or Department HOD.";
header('Location: academicsectionhome.php');
exit();

if (!empty($_POST)) {
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        
        if (!empty($_POST['action']) && $_POST['action'] == 'fetch') {
            // Step 1: Fetch faculty details
            if (!empty($_POST['username'])) {
                $result = $obj->getFacultyByUsername($_POST['username']);
                if (!empty($result['status']) && $result['status'] == 1) {
                    $faculty_details = $result['data'];
                    $step = 2;
                    $_SESSION['reset_faculty_id'] = $faculty_details['id'];
                } else {
                    $error = "Faculty not found. Please check the username.";
                }
            } else {
                $error = "Please enter a username.";
            }
        } elseif (!empty($_POST['action']) && $_POST['action'] == 'reset') {
            // Step 2: Execute reset
            if (!empty($_SESSION['reset_faculty_id'])) {
                $faculty_id = $_SESSION['reset_faculty_id'];
                $remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : "";
                
                $res = $obj->resetFacPwd($faculty_id, $remarks);
                if (!empty($res['status']) && $res['status'] == 1) {
                    $success = "Password Reset Successfully!<br/>Default password is faculty's mobile number.";
                    unset($_SESSION['reset_faculty_id']);
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
        <div class="card-header">Reset Faculty Password</div>
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
                        <h5>Step 1: Enter Faculty Username</h5>
                        <form action="academicsectionresetfacultypwd.php" method="post">
                            <div class="form-group">
                                <label for="username">Faculty Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Enter faculty username" required maxlength="50">
                            </div>
                            <input type="hidden" name="action" value="fetch">
                            <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                            <br />
                            <button type="submit" class="btn btn-primary">Fetch Details</button>
                            <a href="academicsectionhome.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    <?php } elseif ($step == 2 && !empty($faculty_details)) { ?>
                        <!-- Step 2: Show Details & Reset -->
                        <h5>Step 2: Confirm & Reset Password</h5>
                        <div class="alert alert-info">
                            <strong>Faculty Details:</strong>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td><?php echo htmlspecialchars($faculty_details['username']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?php echo htmlspecialchars($faculty_details['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($faculty_details['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mobile:</strong></td>
                                    <td><?php echo htmlspecialchars($faculty_details['mobile']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Designation:</strong></td>
                                    <td><?php echo htmlspecialchars($faculty_details['designation']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php 
                                        echo ($faculty_details['status'] == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <form action="academicsectionresetfacultypwd.php" method="post">
                            <div class="form-group">
                                <label for="remarks">Remarks (Optional)</label>
                                <textarea class="form-control" id="remarks" name="remarks" 
                                          placeholder="Enter reason for password reset (e.g., Forgot password, Initial reset)" 
                                          rows="3" maxlength="500"></textarea>
                                <small class="form-text text-muted">This will be logged in the activity log.</small>
                            </div>

                            <div class="alert alert-warning">
                                <strong>Default Password:</strong> Faculty's mobile number<br>                                
                            </div>

                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset this faculty\'s password?');">
                                <i class="bi bi-exclamation-triangle me-1"></i> Reset Password
                            </button>
                            <a href="academicsectionresetfacultypwd.php" class="btn btn-secondary">Reset Form</a>
                        </form>
                    <?php } ?>
                </div>
                <div class="col-sm-2"></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("academicsectionfooter.php");
?>

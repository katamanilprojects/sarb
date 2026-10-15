<?php
require_once __DIR__ . '/bootstrap.php';


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if dept_id is not present
if (!isset($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$dept_id = $_POST['dept_id'];
$adminObj = new Admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "update_hoddetails" && empty($_POST["cancel"])) {
        $updateData = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'mobile' => $_POST['mobile'],
            'status' => $_POST['status']
        ];
        $result = $adminObj->updateHODDetails($dept_id, $updateData);

        if ($result['status'] === 1) {
            $succ = "HOD Details Updated Successfully";
        } else {
            $error = "Error updating HOD details. Please try again.";
        }
    }
    if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "reset_password") {

        if (!empty($_POST["mobile"])){

            $result = $adminObj->resetHODPwd($dept_id);
    
            if ($result['status'] === 1) {
                $succ = "HOD Password Reset Successful. Default Password is Mobile Number";
            } else {
                $error = "Error updating HOD Password. Please try again.";
            }
        }else {
                $error = "Please Update Mobile no. and Reset Password.";
        }
    }
}
$hodDetails = $adminObj->getHODDetailsByDeptID($dept_id);

?>
<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                <?php
                if (!empty($_POST["dept_fullname"])) {
                    echo '<h5>Department of ' . htmlspecialchars($_POST["dept_fullname"]) . "</h5>";
                }
                ?>
            </div>
            <div class="float-end">
                <a href="adminfacst.php" class="btn btn-sm btn-outline-success">All Departments</a>
            </div>
        </div>
        <div class="card-header">
            <h6>Head of the Department</h6>
        </div>

        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($hodDetails['data']['name']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($hodDetails['data']['email']) ?></p>
            <p><strong>Mobile:</strong> <?= htmlspecialchars($hodDetails['data']['mobile']) ?></p>
            <hr>
            <p><strong>Username:</strong> <?= htmlspecialchars($hodDetails['data']['username']) ?></p>
            <hr>
            <p><strong>Status:</strong> <?= $hodDetails['data']['status'] == 1 ? 'Active' : 'Inactive' ?></p>
        </div>
        <div class="card-footer">

            <form action="edit_hod.php" method="post" class="float-start">
                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST['dept_id']) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">

                <button type="submit" name="action" value="edit" class="btn btn-warning">Edit HOD Profile</button>
            </form>
            <form action="hod_profile.php" method="post" class="float-end" onsubmit="return confirm('Are you sure you want to Reset Password of <?= htmlspecialchars($hodDetails['data']['name']); ?> ? \n Default Password will be HOD Mobile Number.');">

                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST['dept_id']) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">
                <input type="hidden" name="mobile" value="<?= htmlspecialchars($hodDetails['data']['mobile']) ?>">
                <input type="hidden" name="whattodo" value="reset_password" />
                <button type="submit" name="action" value="Reset Password" class="btn btn-secondary">Reset Password</button>
            </form>

        </div>
    </div>
    <?php if (isset($succ)): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($succ) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

</div>
<?php
require_once("adminfooter.php");
?>
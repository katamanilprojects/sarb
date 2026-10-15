<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if dept_id is not provided
if (empty($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$dept_id = $_POST['dept_id'];
$adminObj = new Admin();

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
        </div>
        <div class="card-header">
            <h6>Head of the Department</h6>
        </div>

        <div class="card-body">
            <form action="hod_profile.php" method="post">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($hodDetails['data']['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($hodDetails['data']['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile:</label>
                    <input type="text" class="form-control" name="mobile" maxlength="10" pattern="[6789]{1}[0-9]{9}" value="<?= htmlspecialchars($hodDetails['data']['mobile']) ?>" title="Enter Valid 10-digit Mobile Number" required>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select name="status" class="form-select">
                        <option value="1" <?= $hodDetails['data']['status'] == 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $hodDetails['data']['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <br />

                <input type="hidden" name="dept_id" value="<?= $dept_id; ?>">
                <input type="hidden" name="whattodo" value="update_hoddetails" />
                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST["dept_id"]) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">

                <button type="submit" class="btn btn-danger" name="cancel" value="cancel">Cancel</button> &nbsp; &nbsp;
                <button type="submit" class="btn btn-success">Update HOD Details</button>
            </form>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>
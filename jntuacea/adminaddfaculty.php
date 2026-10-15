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
                <form action="adminfac.php" method="post">
                    <input type="hidden" name="dept_id" value="<?= $_POST['dept_id']; ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= $_POST['dept_fullname']; ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-success">All Faculty</button>
                </form>
            </div>

        </div>
        <div class="card-header">
            Add New Faculty
        </div>
        <div class="card-body">
            <form action="adminfac.php" method="post">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label for="designation">Designation:</label>
                    <select name="designation" class="form-select" required>
                        <option value="">--Select Designation--</option>
                        <option value="Professor">Professor</option>
                        <option value="Assoc. Professor">Assoc. Professor</option>
                        <option value="Asst. Professor">Asst. Professor</option>
                        <option value="Asst. Professor (Adhoc)">Asst. Professor (Adhoc)</option>
                        <option value="Guest Faculty">Guest Faculty</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile:</label>
                    <input type="text" class="form-control" name="mobile" maxlength="10" pattern="[6789]{1}[0-9]{9}" required title="Enter Valid 10-digit Mobile Number" />
                </div>

                <hr>
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="form-group mt-2">
                    <label for="sort_order">Display Order (Optional):</label>
                    <input type="number" min="1" max="999" class="form-control" name="sort_order" placeholder="Leave empty to automatically append at end of department roster">
                    <small class="text-muted">Determines position in department select boxes and rosters.</small>
                </div>
                <br />
                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST["dept_id"]) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">
                <button type="submit" name="submit" class="btn btn-success">Add Faculty</button>
            </form>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <strong>Note: All fields are mandatory. Default Password is Mobile Number</strong>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>
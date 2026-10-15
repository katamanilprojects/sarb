<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if faculty_id is not provided
if (empty($_POST['faculty_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$faculty_id = $_POST['faculty_id'];
$adminObj = new Admin();

$facultyDetails = $adminObj->getFacultyDetails($faculty_id);

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
            <?php
            if (!empty($facultyDetails['data']['name'])) {
                echo '<h6>Profile of ' . htmlspecialchars($facultyDetails['data']['name']) . "</h6>";
            }
            ?>
        </div>

        <div class="card-body">
            <form action="adminfacultyprofile.php" method="post">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($facultyDetails['data']['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="designation">Designation:</label>
                    <select name="designation" class="form-select" required>
                        <option value="">--Select Designation--</option>
                        <option value="Professor" <?= $facultyDetails['data']['designation'] == "Professor" ? 'selected' : '' ?>>Professor</option>
                        <option value="Assoc. Professor" <?= $facultyDetails['data']['designation'] == "Assoc. Professor" ? 'selected' : '' ?>>Assoc. Professor</option>
                        <option value="Asst. Professor" <?= $facultyDetails['data']['designation'] == "Asst. Professor" ? 'selected' : '' ?>>Asst. Professor</option>
                        <option value="Asst. Professor (Adhoc)" <?= $facultyDetails['data']['designation'] == "Asst. Professor (Adhoc)" ? 'selected' : '' ?>>Asst. Professor (Adhoc)</option>
                        <option value="Guest Faculty"<?= $facultyDetails['data']['designation'] == "Guest Faculty" ? 'selected' : '' ?>>Guest Faculty</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($facultyDetails['data']['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile:</label>
                    <input type="text" class="form-control" name="mobile" maxlength="10" pattern="[6789]{1}[0-9]{9}" value="<?= htmlspecialchars($facultyDetails['data']['mobile']) ?>" title="Enter Valid 10-digit Mobile Number" required>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select name="status" class="form-select">
                        <option value="1" <?= $facultyDetails['data']['status'] == 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $facultyDetails['data']['status'] == 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label for="sort_order" class="fw-bold">Display Order / Sequence:</label>
                    <input type="number" min="1" max="999" class="form-control" name="sort_order" value="<?= htmlspecialchars((string)($facultyDetails['data']['sort_order'] ?? 1)) ?>" required>
                    <small class="text-muted">Determines the position of this faculty in department select boxes and rosters.</small>
                </div>
                <br />

                <input type="hidden" name="faculty_id" value="<?= $faculty_id; ?>">
                <input type="hidden" name="whattodo" value="update_facdetails" />
                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST["dept_id"]) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">

                <button type="submit" class="btn btn-success">Update Faculty</button>
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
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$selected_sub_id = null;
$subjectDetails = null;
$msg = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input (important for security)
    $sub_id = filter_input(INPUT_POST, 'sub_id', FILTER_VALIDATE_INT);
    $subcode = filter_input(INPUT_POST, 'subcode');
    $sub_fullname = filter_input(INPUT_POST, 'sub_fullname');
    $sub_shortname = filter_input(INPUT_POST, 'sub_shortname');

    if ($sub_id && $subcode && $sub_fullname && $sub_shortname) {
        $result = $facultyObj->updateSubjectDetails($sub_id, $subcode, $sub_fullname, $sub_shortname);
        if ($result['status'] == 1) {
            $msg = "Subject details updated successfully.";
        } else {
            $msg = "Failed to update subject details.";
        }

    } else {
        if(isset($_POST["submitbtn"])){
            $msg = "Please fill out all fields.";
        }
    }

}

$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);

// Initial form load or after update
if (!empty($_POST['sub_id']) && !isset($_POST["submitbtn"])) {
    $selected_sub_id = $_POST['sub_id'];
    $subjectDetails = $facultyObj->getSubjectById($selected_sub_id);
}
?>
<div class="container">
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Edit Subject Details</div>
                <div class="card-body">
                    <form action="facupdsubject.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required onchange="this.form.submit()">
                                <option value="">Select Subject</option>
                                <?php foreach ($facultySubjects['data'] as $subject): ?>
                                    <option value="<?= htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8') ?>" <?= ($selected_sub_id == $subject['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <br />
                        <?php if ($subjectDetails): ?> 
                            <div class="form-group">
                                <label for="subcode">Subject Code:</label>
                                <input type="text" name="subcode" id="subcode" class="form-control" value="<?= $subjectDetails['data']['subcode'] ?>" required>
                            </div>
                            <br />
                            <div class="form-group">
                                <label for="sub_fullname">Subject Full Name:</label>
                                <input type="text" name="sub_fullname" id="sub_fullname" class="form-control" value="<?= $subjectDetails['data']['sub_fullname'] ?>" required>
                            </div>
                            <br />
                            <div class="form-group">
                                <label for="sub_shortname">Subject Short Name:</label>
                                <input type="text" name="sub_shortname" id="sub_shortname" class="form-control" value="<?= $subjectDetails['data']['sub_shortname'] ?>" required>
                            </div>
                            <br />
                            <button type="submit" name="submitbtn" class="btn btn-primary">Update Subject</button>
                        <?php endif; ?> 
                    </form>
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-info mt-3"><?= $msg ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once("facfooter.php");
?>
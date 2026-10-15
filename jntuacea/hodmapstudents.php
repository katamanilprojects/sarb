<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$hodObj = new HOD();
$class_id = $_POST['class_id'];

$subjectList = $hodObj->getSubjectsByClassID($class_id);

// Handle form submission
// Fetch mappings if subject is selected
$mappedStudents = [];
$unmappedStudents = [];
if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    // Handle mapping
    if (!empty($_POST['stu_ids'])) {

        if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "addstudents") {
            foreach ($_POST['stu_ids'] as $stu_id) {
                $res_arr = $hodObj->addStudentSubjectMapping($stu_id, $_POST['sub_id']);
                if (empty($res_arr['status']) || $res_arr['status'] != 1) {
                    // Handle mapping error (log, display message, etc.)
                }
            }
        }
        if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "unmapstudents") {
            foreach ($_POST['stu_ids'] as $stu_id) {
                $res_arr = $hodObj->unmapStudentSubjectMapping($stu_id, $_POST['sub_id']);
                if (empty($res_arr['status']) || $res_arr['status'] != 1) {
                    // Handle mapping error (log, display message, etc.)
                }
            }
        }
    }

    $mappedStudents = $hodObj->getMappedStudents($_POST['sub_id']);
    $unmappedStudents = $hodObj->getUnmappedStudents($_POST['sub_id']);
}



// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                Specialization: <?= htmlspecialchars($_POST['spec_fullname']) ?>
            </div>
            <div class="float-end">
                <a href="hodviewclasses.php" class="btn btn-sm btn-outline-success">All Classes</a>
            </div>
        </div>
        <div class="card-header">
            Class: <?= htmlspecialchars($_POST['class_fullname']) ?>
        </div>
    </div>
</div>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Map Students to Subject</div>
                <div class="card-body">
                    <br />
                    <form action="hodmapstudents.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($subjectList['data'] as $subject) {
                                    $selected = (!empty($_POST['sub_id']) && $_POST['sub_id'] == $subject['id']) ? 'selected' : '';
                                    echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                        <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                        <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />

                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Show Students</button>
                    </form>
                    <br />
                    <?php if (!empty($_POST['sub_id'])): ?>

                        <div class="row">
                            <div class="col-sm-6">
                                <form action="hodmapstudents.php" method="post">
                                    <input type="hidden" name="sub_id" value="<?php echo $_POST['sub_id']; ?>">

                                    <h4>Unmapped Students</h4>
                                    <select name="stu_ids[]" id="unmapped_students" class="form-control" multiple size="10">
                                        <?php
                                        foreach ($unmappedStudents as $student) {
                                            echo "<option value='{$student['id']}'>{$student['name']} ({$student['username']})</option>";
                                        }
                                        ?>
                                    </select>
                                    <br />
                                    <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                    <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                    <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                    <input type="hidden" name="whattodo" value="addstudents" />
                                    <button type="submit" class="btn btn-success">Map Selected Students</button>
                                </form>

                            </div>
                            <div class="col-sm-6">
                                <form action="hodmapstudents.php" method="post">
                                    <input type="hidden" name="sub_id" value="<?php echo $_POST['sub_id']; ?>">

                                    <h4>Mapped Students</h4>
                                        <select name="stu_ids[]" id="mapped_students" class="form-control" multiple size="10">
                                        <?php
                                        foreach ($mappedStudents as $student) {
                                            echo "<option value='{$student['id']}'>{$student['name']} ({$student['username']}) </option>";
                                        }
                                        ?>
                                    </select>
                                    <br />
                                    <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                    <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                    <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                    <input type="hidden" name="whattodo" value="unmapstudents" />
                                    <button type="submit" class="btn btn-secondary float-end">UnMap Selected Students</button>
                                </form>

                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>
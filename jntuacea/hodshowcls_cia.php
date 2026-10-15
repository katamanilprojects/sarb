<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "HOD CIA Analysis";
require_once("hod.class.php");
require_once("hodheader.php");

$hodObj = new HOD();

require_once("faculty.class.php");
$facultyObj = new Faculty();

$selected_cls_id = null;
$selected_sub_id = null;
$ciaData = null;

// Handle class selection
if (!empty($_POST['cls_id'])) {
    $selected_cls_id = $_POST['cls_id'];
    $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
}

$specializations = $hodObj->getSpecializationsByDepartment($_SESSION["dept_id"]);

// Generate class options
$classes_options = '';
if (!empty($specializations)) {
    foreach ($specializations as $spec) {
        $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
        $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
        foreach ($classes as $class) {
            $selected = ($class['id'] == $selected_cls_id) ? 'selected' : '';
            $classes_options .= "<option value='{$class['id']}' $selected>{$class['classname']}</option>";
        }
        $classes_options .= '</optgroup>';
    }
}

// Handle CIA data fetch
if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = $_POST['sub_id'];

    // Fetch CIA data (similar to faculty module)
    require_once("modulecheckcia.php");
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">HOD - Subject Wise CIA Analysis</div>
                <div class="card-body">
                    <form action="hodshowcls_cia.php" method="post" id="ciasearchform">
                        <div class="form-group">
                            <label for="cls_id">Select Class:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?= $classes_options ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="sub_id">Select Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                if (!empty($facultySubjects['data'])) {
                                    foreach ($facultySubjects['data'] as $subject) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                        <button type="submit" class="btn btn-success">Show CIA Analysis</button>
                    </form>
                </div>
            </div>
            <br />
            <?php
            // Display CIA Data (using existing module)
            require_once("moduleshowfacciamarks.php");
            ?>
        </div>
    </div>
</div>

<script>
    // Auto-submit when class changes
    document.getElementById('cls_id').addEventListener('change', function () {
        document.getElementById('ciasearchform').submit();
    });
</script>

<?php require_once("facfooter.php"); ?>
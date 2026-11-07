<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['hod', 'superadmin', 'admin'], true) || empty($_SESSION['dept_id'])) {
    header("Location: ./");
    exit();
}

$page_title = "HOD - Subject Wise CIA Analysis";
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
    $selected_cls_id = (int)$_POST['cls_id'];
    $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
}

$specializations = $hodObj->getSpecializationsByDepartment($_SESSION["dept_id"]);

// Generate class options
$classes_options = '';
if (!empty($specializations)) {
    foreach ($specializations as $spec) {
        $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
        $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $selected = ($class['id'] == $selected_cls_id) ? 'selected' : '';
                $acadYearStr = !empty($class['acad_year']) ? ' (' . htmlspecialchars($class['acad_year']) . ')' : '';
                $classes_options .= "<option value='{$class['id']}' $selected>" . htmlspecialchars($class['classname']) . $acadYearStr . "</option>";
            }
        }
        $classes_options .= '</optgroup>';
    }
}

// Handle CIA data fetch
if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && hash_equals($_SESSION['secretcode'], (string)$_POST['secretcode'])) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = (int)$_POST['sub_id'];
    $_POST['action'] = 'show_cia';

    $details = $hodObj->getClsSubFacBySubID($selected_sub_id);
    $studentList = $facultyObj->getMappedStudents($selected_sub_id);
    $assessmentDetails = [];

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
                        <input type="hidden" name="action" value="show_cia">
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                        <button type="submit" class="btn btn-success">Show CIA Analysis</button>
                    </form>
                </div>
            </div>
            <br />
            <?php
            // Display CIA Data (using existing module)
            require_once("moduleshowfacattendance.php");
            ?>
        </div>
    </div>
</div>

<script>
    // Auto-submit when class changes
    document.getElementById('cls_id').addEventListener('change', function () {
        const subSelect = document.getElementById('sub_id');
        if (subSelect) {
            subSelect.removeAttribute('required');
            subSelect.value = '';
        }
        document.getElementById('ciasearchform').submit();
    });
</script>

<?php require_once("hodfooter.php"); ?>
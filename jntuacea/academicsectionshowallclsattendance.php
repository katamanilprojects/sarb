<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "View Attendance";
require_once("academicsectionheader.php");
require_once("admin.class.php");
$adminObj = new Admin();

require_once("hod.class.php");
$hodObj = new HOD();

$departments = $adminObj->getAllDepartments();

require_once("faculty.class.php");
$facultyObj = new Faculty();

$selected_sub_id = null;
$selected_cls_id = null;
$attendanceData = null;
$showDateRange = false;
$start_date = null;
$end_date = null;

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
    }
}

if (!empty($_POST['show_date_range']) && $_POST['show_date_range'] === 'yes') {
    $showDateRange = true;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
}

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];

    $specializations = $hodObj->getSpecializationsByDepartment($selected_dept_id);

    $classes_options = '';

    if (!empty($specializations)) {
        foreach ($specializations as $spec) {
            $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
            $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
            if (!empty($classes)) {
                foreach ($classes as $class) {
                    $classes_options .= '<option value="' . $class['id'] . '"';
                    if ($class['id'] == $selected_cls_id) {
                        $classes_options .= ' selected';
                    }
                    $classes_options .= '>' . $class['classname'] .  '(' . $class['acad_year'] . ')' . '</option>';
                }
                echo '</optgroup>';
            }
        }
    }
}

$csrfcheck = 0;
if (!empty($_POST['action']) && $_POST['action'] == "view_all_overall" && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $csrfcheck = 1;
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header"> Overall Class Attendance</div>
                <div class="card-body dontprint">
                    <form action="academicsectionshowallclsattendance.php" method="post" id="showsarbform">
                        <div class="form-group">
                            <label for="dept_id">Department:</label>
                            <select name="dept_id" id="dept_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments['data'] as $dept) {
                                    if ($dept['dept_fullname'] != "Physical Education" && $dept['dept_fullname'] != "Humanities" && $dept['dept_fullname'] != "Physics" && $dept['dept_fullname'] != "Chemistry" && $dept['dept_fullname'] != "Mathematics") {
                                        $selected = (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : '';
                                        echo "<option value='{$dept['id']}' $selected>{$dept['dept_fullname']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="cls_id">Class:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php
                                echo $classes_options;
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="report_type">Type of Report:</label>
                            <select name="report_type" id="report_type" class="form-select" required>
                                <option value="">Select Type of Report</option>
                                <option value="overall" <?php if(!empty($_POST["report_type"]) && $_POST["report_type"]=="overall"){ echo 'selected'; } ?> >Overall Percentage</option>
                                <option value="enhanced" <?php if(!empty($_POST["report_type"]) && $_POST["report_type"]=="enhanced"){ echo 'selected'; } ?> >Enhanced Overall Percentage (Course-wise Total and Present Classes)</option>
                                <option value="subjectwise"<?php if(!empty($_POST["report_type"]) && $_POST["report_type"]=="subjectwise"){ echo 'selected'; } ?> >Course-wise Percentage</option>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <select name="show_date_range" id="show_date_range" class="form-select" onchange="toggleDateRange()">
                                <option value="no" <?= !$showDateRange ? 'selected' : '' ?>>Select Date Range? : No</option>
                                <option value="yes" <?= $showDateRange ? 'selected' : '' ?>>Select Date Range? : Yes</option>
                            </select>
                        </div>
                        <br />
                        <div id="dateRangeInputs" style="<?= $showDateRange ? '' : 'display:none;' ?>">
                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                            </div>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="action" value="view_all_overall" class="btn btn-primary">View Overall Attendance</button>
                    </form>
                </div>
            </div>
            <br />
            <?php
            if (!empty($_POST['action']) && $_POST['action'] == "view_all_overall" && $csrfcheck==1) {

                if(!empty($_POST['report_type']) && $_POST["report_type"]=="overall"){
                    require_once("module_showallcls_combined.php");
                }elseif(!empty($_POST['report_type']) && $_POST["report_type"]=="enhanced"){
                    require_once("module_showallcls_enhanced.php");
                }else{
                    require_once("module_showallcls_subwise.php");
                }

                echo '<br><button type="button" class="btn btn-success dontprint" onclick="window.print();">Print This Page (Prefer Lanscape Mode)</button>';            
                echo '<form action="export_attendance_excel.php" method="post" style="display:inline" class="dontprint">';
                echo '<input type="hidden" name="cls_id" value="' . $selected_cls_id . '">';
                echo '<input type="hidden" name="report_type" value="' . $_POST['report_type'] . '">';
                if (!empty($_POST['start_date'])) echo '<input type="hidden" name="start_date" value="' . $_POST['start_date'] . '">';
                if (!empty($_POST['end_date'])) echo '<input type="hidden" name="end_date" value="' . $_POST['end_date'] . '">';
                echo '<button type="submit" class="btn btn-info">Export to Excel</button>';
                echo '</form>';
                if (!empty($selected_cls_id) && \FeatureManager::isModuleEnabled('MOD_ATTENDANCE_CONDONATION_GAZETTE')) {
                    $dateParams = '';
                    if (!empty($_POST['start_date'])) $dateParams .= '&start_date=' . urlencode($_POST['start_date']);
                    if (!empty($_POST['end_date'])) $dateParams .= '&end_date=' . urlencode($_POST['end_date']);
                    echo ' <a href="download_attendance_gazette.php?cls_id=' . (int)$selected_cls_id . $dateParams . '" target="_blank" class="btn btn-warning dontprint ms-1"><i class="bi bi-file-earmark-ruled me-1"></i> Condonation & Detention Gazette</a>';
                }
            }
            ?>
        </div>
    </div>
</div>
<script>
    document.getElementById('dept_id').addEventListener('change', function() {
        document.getElementById('showsarbform').submit();
    });
    
    function toggleDateRange() {
        const dateRangeInputs = document.getElementById('dateRangeInputs');
        const showDateRangeSelect = document.getElementById('show_date_range');

        if (showDateRangeSelect.value === 'yes') {
            dateRangeInputs.style.display = 'block';
        } else {
            dateRangeInputs.style.display = 'none';
        }
    }

    toggleDateRange();
    

</script>
<?php
require_once("academicsectionfooter.php");
?>

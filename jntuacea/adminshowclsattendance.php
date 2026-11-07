<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "View Attendance";
require_once("adminheader.php");
require_once("admin.class.php");
$adminObj = new Admin();

require_once("hod.class.php");
$hodObj = new HOD();

$departments = $adminObj->getAllDepartments();

require_once("faculty.class.php");
$facultyObj = new Faculty();

// Handle subject selection and action
$selected_sub_id = null;
$selected_cls_id = null;
$attendanceData = null;
$diaryEntries = null;
$showDateRange = false;
$start_date = null;
$end_date = null;

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
    }

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

        if (!empty($selected_cls_id)) {
            $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
        }
    }
}



if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && hash_equals($_SESSION['secretcode'], (string)$_POST['secretcode'])) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = $_POST['sub_id'];

    // Fetch data based on action

    if (!empty($_POST['action'])) {

        $showDateRange = !empty($_POST['show_date_range']) && $_POST['show_date_range'] === 'yes'; // Determine whether to show date range inputs
        if ($_POST['show_date_range'] === 'no') {
            $_POST['start_date'] = '';
            $_POST['end_date'] = '';
        }
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $details = $hodObj->getClsSubFacBySubID($selected_sub_id);
        switch ($_POST['action']) {
            case 'view_overall':
                $attendanceData = $facultyObj->getOverallAttendanceBySubject($selected_sub_id, $start_date, $end_date);
                break;
            case 'view_detailed':
                $attendanceData = $facultyObj->getDetailedAttendanceBySubject($selected_sub_id, $start_date, $end_date);
                break;
            case 'show_diary':
                $diaryEntries = $facultyObj->getDiaryEntriesBySubject($selected_sub_id, $start_date, $end_date);
                break;
            case 'show_cia':
                // Check if both assessments are present
                $studentList = $facultyObj->getMappedStudents($selected_sub_id);
                $assessmentDetails = [];
                // Check if both assessments are present
                require_once("modulecheckcia.php");

                break;
        }
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header"> Subject-Wise Student Academic Record Book</div>
                <div class="card-body">
                    <form action="adminshowclsattendance.php" method="post" id="showsarbform">
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
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                    echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                                }
                                ?>
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
                        <button type="submit" name="action" value="view_overall" class="btn btn-primary">View Overall Attendance</button>
                        <button type="submit" name="action" value="view_detailed" class="btn btn-info">Detailed Attendance & e-Bluebook</button>
                        <button type="submit" name="action" value="show_diary" class="btn btn-secondary">Show Diary</button>
                        <button type="submit" name="action" value="show_cia" class="btn btn-success">Show Continuous Internal Assessment</button>
                        <?php if (!empty($selected_cls_id) && \FeatureManager::isModuleEnabled('MOD_ATTENDANCE_CONDONATION_GAZETTE')): ?>
                        <a href="download_attendance_gazette.php?cls_id=<?= (int)$selected_cls_id; ?>" target="_blank" class="btn btn-warning ms-1">
                            <i class="bi bi-file-earmark-ruled me-1"></i> Condonation & Detention Gazette
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <br />
            <?php
            require_once("moduleshowfacattendance.php");
            ?>
        </div>
    </div>
</div>
<script>
    document.getElementById('dept_id').addEventListener('change', function() {
        document.getElementById('showsarbform').submit();
    });
    document.getElementById('cls_id').addEventListener('change', function() {
        document.getElementById('showsarbform').submit();
    });

    function toggleDateRange() {
        const dateRangeInputs = document.getElementById('dateRangeInputs');
        const showDateRangeSelect = document.getElementById('show_date_range');

        if (showDateRangeSelect.value === 'yes') {
            dateRangeInputs.style.display = 'block'; // Show date inputs
        } else {
            dateRangeInputs.style.display = 'none'; // Hide date inputs
        }
    }

    // Call toggleDateRange() initially to set the correct state on page load
    toggleDateRange();
</script>
<?php
require_once("facfooter.php");
?>
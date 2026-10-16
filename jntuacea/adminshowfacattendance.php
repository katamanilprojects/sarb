<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "View Attendance";
require_once("adminheader.php");
require_once("admin.class.php");
$adminObj = new Admin();


$departments = $adminObj->getAllDepartments();

require_once("faculty.class.php");
$facultyObj = new Faculty();

// Handle subject selection and action
$selected_sub_id = null;
$selected_fac_id = null;
$attendanceData = null;
$diaryEntries = null;
$showDateRange = false;
$start_date = null;
$end_date = null;

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    $faculties = $adminObj->getFacultyByDepartment($selected_dept_id);
    if (!empty($_POST['fac_id'])) {
        $selected_fac_id = $_POST['fac_id'];
        $facultySubjects = $facultyObj->getSubjectsByFacultyId($selected_fac_id);
    }
}



if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && hash_equals($_SESSION['secretcode'], (string)$_POST['secretcode'])) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = $_POST['sub_id'];

    // Fetch data based on action

    if (!empty($_POST['action'])) {

        $showDateRange = !empty($_POST['show_date_range']) && $_POST['show_date_range'] === 'yes'; // Determine whether to show date range inputs
        if($_POST['show_date_range'] === 'no'){
            $_POST['start_date'] = '';
            $_POST['end_date'] = '';
        }
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $details = $facultyObj->getClassSubjectFacultyDetails($selected_sub_id, $selected_fac_id);
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
                    <form action="adminshowfacattendance.php" method="post" id="showsarbform">
                        <div class="form-group">
                            <label for="dept_id">Department:</label>
                            <select name="dept_id" id="dept_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments['data'] as $dept) {
                                    if($dept['dept_fullname']!="BTech1"){
                                        $selected = (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : '';
                                        echo "<option value='{$dept['id']}' $selected>{$dept['dept_fullname']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="fac_id">Faculty:</label>
                            <select name="fac_id" id="fac_id" class="form-select" required>
                                <option value="">Select Faculty</option>
                                <?php
                                foreach ($faculties['data'] as $faculty) {
                                    $selected = (!empty($selected_fac_id) && $selected_fac_id == $faculty['id']) ? 'selected' : '';
                                    echo "<option value='{$faculty['id']}' $selected>{$faculty['name']}</option>";
                                }
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
                                    echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']}</option>";
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
    document.getElementById('fac_id').addEventListener('change', function() {
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
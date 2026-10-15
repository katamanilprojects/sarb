<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATT_REQUESTS');

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$errmsg = "";
$succmsg = "";
$hoursAvailable = false;
$diaryData = null;
$presentCount = 0;
$absentCount = 0;

// Step 1: Handle request for available hours
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_hours'])) {
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hoursResult = $facultyObj->getAvailableHours($sub_id, $date);

    if (!empty($hoursResult['data'])) {
        $hoursAvailable = true;
    } else {
        $errmsg = "No hours found for the selected subject and date.";
    }
}

// Step 2: Handle request to get attendance details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_details'])) {
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hour = $_POST['hour'];
    $hoursResult = $facultyObj->getAvailableHours($sub_id, $date);

    $diaryData = $facultyObj->getDiaryEntry($sub_id, $date, $hour);
    $attendanceCounts = $facultyObj->getAttendanceCounts($sub_id, $date, $hour);

    if ($diaryData && $attendanceCounts) {
        $presentCount = $attendanceCounts['present'];
        $absentCount = $attendanceCounts['absent'];
    }
}

// Step 3: Handle request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    \FeatureManager::requireWriteAccess('MOD_ATT_REQUESTS');
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hour = $_POST['hour'];
    $reason = $_POST['reason'];

    $result = $facultyObj->submitAttendanceDeleteRequest($faculty_id, $sub_id, $date, $hour, $reason);

    if ($result['status'] == 1) {
        $succmsg = "Attendance delete request submitted successfully.";
    } else {
        $errmsg = "Failed to submit request. Please try again.";
    }
}

if (!empty($succmsg) || !empty($errmsg)) {
    if (isset($sub_id)) {
        unset($sub_id);
    }
    if (isset($date)) {
        unset($date);
    }
    if (isset($hour)) {
        unset($hour);
    }
    if (isset($_POST)) {
        unset($_POST);
    }
}

// Generate a new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATT_REQUESTS'); ?>
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Submit Attendance Delete Request</div>
                <div class="card-body">
                    <form action="facadddelattrequest.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required <?php if (!empty($_POST["sub_id"])) {
                                                                                                echo 'disabled';
                                                                                            } ?>>
                                <option value="">Select Subject</option>
                                <?php foreach ($facultySubjects['data'] as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" <?= isset($sub_id) && $sub_id == $subject['id'] ? 'selected' : '' ?>>
                                        <?= $subject['sub_fullname'] ?> (<?= $subject['subcode'] ?>) - <?= $subject['class_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <br>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" max="<?= date("Y-m-d") ?>" value="<?= isset($date) ? $date : '' ?>" required <?php if (!empty($_POST["date"])) {
                                                                                                                                                                            echo 'disabled';
                                                                                                                                                                        } ?>>
                        </div>
                        <br>
                        <?php if (!$hoursAvailable && !$diaryData): ?>
                            <button type="submit" name="get_hours" class="btn btn-primary">Get Hours</button>
                        <?php endif; ?>
                        <?php if (($hoursAvailable) && empty($_POST["hour"])): ?>

                            <div class="form-group">
                                <label for="hour">Hour:</label>
                                <select name="hour" id="hour" class="form-select" required <?php if (!empty($_POST["hour"])) {
                                                                                                echo 'disabled';
                                                                                            } ?>>
                                    <option value="">Select Hour</option>
                                    <?php foreach ($hoursResult['data'] as $hourData): ?>
                                        <option value="<?= $hourData['hour'] ?>"><?= "" . $hourData['hour_desc'] . "" ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <br>
                            <input type="hidden" name="sub_id" value=<?php echo $_POST['sub_id']; ?> />
                            <input type="hidden" name="date" value=<?php echo $_POST['date']; ?> />
                            <a href="facadddelattrequest.php" class="btn btn-outline-danger">Cancel Request</a>&nbsp;
                            <button type="submit" name="get_details" class="btn btn-info">Get Details</button>
                        <?php endif; ?>
                        <?php if (!empty($_POST["hour"])) : ?>
                            <?php                                 
                                foreach ($hoursResult['data'] as $hourData){ 

                                if ($_POST["hour"] == $hourData['hour']) {
                                    $hour_desc = $hourData['hour_desc'];
                                } else {                                    
                                    $hour_desc = $_POST["hour"];
                                } 
                                 } ?>
                            <div class="form-group">
                                <label for="hour">Hour:</label>
                                <select name="hour" id="hour" class="form-select" required disabled>
                                    <option value="<?= $_POST["hour"] ?>"> <?= $hour_desc ?></option>
                                </select>
                            </div>
                            <br>
                        <?php endif; ?>
                        <?php if ($diaryData): ?>

                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center">Selected Attendance Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($facultySubjects['data'] as $subject) {
                                            if (isset($sub_id) && $sub_id == $subject['id']) {
                                                $class = $subject['class_name'];
                                                $subject = $subject['subcode'] . ' - ' . $subject['sub_fullname'];
                                                echo '<tr><td colspan="2"><strong>Class: </strong>' . $class . '</tr>';
                                                echo '<tr><td colspan="2"><strong>Subject: </strong>' . $subject . '</tr>';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td colspan="2"><strong>Diary : </strong><?= htmlspecialchars($diaryData) ?></td>
                                        </tr>
                                        <?php if (!empty($presentCount) || !empty($absentCount)): ?>
                                            <tr>
                                                <td><strong>No. of Students Present : </strong><?= $presentCount ?></td>
                                                <td><strong>No. of Students Absent : </strong><?= $absentCount ?></td>
                                            </tr>
                                        <?php elseif ($diaryData): ?>
                                            <tr>
                                                <td colspan="2">No Attendance Only Dairy</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <br />
                            <div class="form-group">
                                <label for="reason">Reason for Delete:</label>
                                <textarea name="reason" id="reason" class="form-control" required></textarea>
                            </div>
                            <br>
                            <input type="hidden" name="sub_id" value="<?= $sub_id ?>">
                            <input type="hidden" name="date" value="<?= $date ?>">
                            <input type="hidden" name="hour" value="<?= $hour ?>">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <a href="facadddelattrequest.php" class="btn btn-outline-danger">Cancel Request</a>&nbsp;
                            <button type="submit" name="submit_request" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_ATT_REQUESTS') ? 'disabled' : ''; ?>>Submit Request</button>
                        <?php endif; ?>
                    </form>
                    <br>
                    <?php if ($errmsg): ?>
                        <div class="alert alert-danger"><?= $errmsg ?></div>
                    <?php endif; ?>
                    <?php if ($succmsg): ?>
                        <div class="alert alert-info"><?= $succmsg ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once("facfooter.php"); ?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "Update Student Attendance";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$errmsg = "";
$succmsg = "";
$hoursAvailable = false;
$studentsAvailable = false;
$attendanceData = null;
$hoursResult = ['data' => []];
$studentsResult = ['data' => []];
$selectedStudentText = "";
$sub_id = $_POST['sub_id'] ?? '';
$date = $_POST['date'] ?? '';
$hour = $_POST['hour'] ?? '';
$stu_id = $_POST['stu_id'] ?? '';
$today = date("Y-m-d");
$minAllowedDate = date("Y-m-d", strtotime("-7 days"));

function isDateWithinLastWeek($date, $minAllowedDate, $today)
{
    return !empty($date) && $date >= $minAllowedDate && $date <= $today;
}

function formatDisplayDate($date)
{
    if (empty($date)) {
        return "";
    }

    $timestamp = strtotime($date);
    return $timestamp ? date("d-m-Y", $timestamp) : $date;
}

function getStatusLabel($status)
{
    if ($status === 'P') {
        return 'Present';
    }
    if ($status === 'A') {
        return 'Absent';
    }
    return $status;
}

function getHourDisplayText($hoursResult, $selectedHour)
{
    $hourText = $selectedHour;

    if (!empty($hoursResult['data'])) {
        foreach ($hoursResult['data'] as $hourData) {
            if ($selectedHour == $hourData['hour']) {
                $start_time_12hr = !empty($hourData['start_time']) ? date("g:i A", strtotime($hourData['start_time'])) : "";
                $end_time_12hr = !empty($hourData['end_time']) ? date("g:i A", strtotime($hourData['end_time'])) : "";
                $hourText = $start_time_12hr . ' - ' . $end_time_12hr;

                if (!empty($hourData['hour_desc'])) {
                    $hourText .= ' (' . $hourData['hour_desc'] . ')';
                }
                break;
            }
        }
    }

    return $hourText;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_hours'])) {
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];

    if (!isDateWithinLastWeek($date, $minAllowedDate, $today)) {
        $errmsg = "Attendance can be updated only for dates within the last one week.";
    } else {
        $hoursResult = $facultyObj->getAvailableHours($sub_id, $date);

        if (!empty($hoursResult['data'])) {
            $hoursAvailable = true;
        } else {
            $errmsg = "No hours found for the selected subject and date.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_students'])) {
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hour = $_POST['hour'];

    if (!isDateWithinLastWeek($date, $minAllowedDate, $today)) {
        $errmsg = "Attendance can be updated only for dates within the last one week.";
    } else {
        $hoursResult = $facultyObj->getAvailableHours($sub_id, $date);
        $studentsResult = $facultyObj->getStudentsWithAttendanceByHour($sub_id, $date, $hour);

        if (!empty($hoursResult['data'])) {
            $hoursAvailable = true;
        }

        if (!empty($studentsResult['data'])) {
            $studentsAvailable = true;
        } else {
            $errmsg = "No students found for the selected subject, date, and hour.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_attendance'])) {
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hour = $_POST['hour'];
    $stu_id = $_POST['stu_id'];

    if (!isDateWithinLastWeek($date, $minAllowedDate, $today)) {
        $errmsg = "Attendance can be updated only for dates within the last one week.";
    } else {
        $hoursResult = $facultyObj->getAvailableHours($sub_id, $date);
        $studentsResult = $facultyObj->getStudentsWithAttendanceByHour($sub_id, $date, $hour);
        $attendanceData = $facultyObj->getStudentAttendanceByHour($stu_id, $sub_id, $date, $hour);

        if (!empty($hoursResult['data'])) {
            $hoursAvailable = true;
        }
        if (!empty($studentsResult['data'])) {
            $studentsAvailable = true;
        }

        foreach ($studentsResult['data'] as $student) {
            if ($student['id'] == $stu_id) {
                $selectedStudentText = $student['username'] . ' - ' . $student['name'];
                break;
            }
        }

        if (empty($attendanceData['status'])) {
            $errmsg = !empty($attendanceData['err']) ? $attendanceData['err'] : "Unable to fetch attendance for the selected student.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_attendance'])) {
    \FeatureManager::requireWriteAccess('MOD_ATTENDANCE');
    $sub_id = $_POST['sub_id'];
    $date = $_POST['date'];
    $hour = $_POST['hour'];
    $stu_id = $_POST['stu_id'];
    $new_status = $_POST['new_status'];

    if (!isDateWithinLastWeek($date, $minAllowedDate, $today)) {
        $errmsg = "Attendance can be updated only for dates within the last one week.";
    } elseif (!in_array($new_status, ['P', 'A'])) {
        $errmsg = "Invalid attendance status selected.";
    } else {
        $result = $facultyObj->updateStudentAttendanceByHour($stu_id, $sub_id, $date, $hour, $new_status, $faculty_id, trim($_POST['correction_reason'] ?? ''));

        if (!empty($result['status'])) {
            $succmsg = "Student attendance updated successfully.";
        } else {
            $errmsg = !empty($result['err']) ? $result['err'] : "Failed to update attendance. Please try again.";
        }
    }
}

if (!empty($succmsg)) {
    if (isset($sub_id)) {
        unset($sub_id);
    }
    if (isset($date)) {
        unset($date);
    }
    if (isset($hour)) {
        unset($hour);
    }
    if (isset($stu_id)) {
        unset($stu_id);
    }
    if (isset($_POST)) {
        unset($_POST);
    }
}
?>

<div class="container">
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Update Single Student Attendance</div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATTENDANCE'); ?>
                    <form action="facupdatestudentattendance.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required <?php if (!empty($_POST["sub_id"])) { echo 'disabled'; } ?>>
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
                            <input type="date" name="date" id="date" class="form-control" min="<?= $minAllowedDate ?>" max="<?= $today ?>" value="<?= isset($date) ? $date : '' ?>" required <?php if (!empty($_POST["date"])) { echo 'disabled'; } ?>>
                            <small class="text-muted">Only dates within the last one week are allowed.</small>
                        </div>
                        <br>

                        <?php if (!$hoursAvailable && !$studentsAvailable && !$attendanceData): ?>
                            <button type="submit" name="get_hours" class="btn btn-primary">Get Hours</button>
                        <?php endif; ?>

                        <?php if ($hoursAvailable && empty($_POST["hour"])): ?>
                            <div class="form-group">
                                <label for="hour">Hour:</label>
                                <select name="hour" id="hour" class="form-select" required>
                                    <option value="">Select Hour</option>
                                    <?php foreach ($hoursResult['data'] as $hourData): ?>
                                        <?php
                                            $start_time_12hr = !empty($hourData['start_time']) ? date("g:i A", strtotime($hourData['start_time'])) : "";
                                            $end_time_12hr = !empty($hourData['end_time']) ? date("g:i A", strtotime($hourData['end_time'])) : "";
                                            $hourDisplayText = $start_time_12hr . ' - ' . $end_time_12hr;
                                            if (!empty($hourData['hour_desc'])) {
                                                $hourDisplayText .= ' (' . $hourData['hour_desc'] . ')';
                                            }
                                        ?>
                                        <option value="<?= $hourData['hour'] ?>"><?= htmlspecialchars($hourDisplayText) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <br>
                            <input type="hidden" name="sub_id" value="<?= $_POST['sub_id']; ?>" />
                            <input type="hidden" name="date" value="<?= $_POST['date']; ?>" />
                            <a href="facupdatestudentattendance.php" class="btn btn-outline-danger">Cancel</a>&nbsp;
                            <button type="submit" name="get_students" class="btn btn-info">Get Students</button>
                        <?php endif; ?>

                        <?php if (!empty($_POST["hour"])): ?>
                            <?php $hour_desc = getHourDisplayText($hoursResult, $_POST["hour"]); ?>
                            <div class="form-group">
                                <label for="hour_display">Hour:</label>
                                <select name="hour_display" id="hour_display" class="form-select" required disabled>
                                    <option value="<?= $_POST["hour"] ?>"><?= htmlspecialchars($hour_desc) ?></option>
                                </select>
                            </div>
                            <br>
                        <?php endif; ?>

                        <?php if ($studentsAvailable && empty($_POST['stu_id'])): ?>
                            <div class="form-group">
                                <label for="stu_id">Student:</label>
                                <select name="stu_id" id="stu_id" class="form-select" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($studentsResult['data'] as $student): ?>
                                        <?php $statusText = !empty($student['status']) ? ' [' . getStatusLabel($student['status']) . ']' : ' [No Record]'; ?>
                                        <option value="<?= $student['id'] ?>"><?= $student['username'] ?> - <?= $student['name'] ?><?= $statusText ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <br>
                            <input type="hidden" name="sub_id" value="<?= $sub_id ?>" />
                            <input type="hidden" name="date" value="<?= $date ?>" />
                            <input type="hidden" name="hour" value="<?= $hour ?>" />
                            <a href="facupdatestudentattendance.php" class="btn btn-outline-danger">Cancel</a>&nbsp;
                            <button type="submit" name="get_attendance" class="btn btn-info">Get Attendance</button>
                        <?php endif; ?>

                        <?php if (!empty($attendanceData['status'])): ?>
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
                                                $subjectName = $subject['subcode'] . ' - ' . $subject['sub_fullname'];
                                                echo '<tr><td colspan="2"><strong>Class: </strong>' . htmlspecialchars($class) . '</td></tr>';
                                                echo '<tr><td colspan="2"><strong>Subject: </strong>' . htmlspecialchars($subjectName) . '</td></tr>';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td colspan="2"><strong>Student: </strong><?= htmlspecialchars($selectedStudentText) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Date: </strong><?= htmlspecialchars(formatDisplayDate($date)) ?></td>
                                            <td><strong>Hour: </strong><?= htmlspecialchars($hour_desc) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><strong>Current Status: </strong><?= htmlspecialchars(getStatusLabel($attendanceData['data']['status'])) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <br />
                            <div class="form-group">
                                <label for="new_status">Change Status To:</label>
                                <select name="new_status" id="new_status" class="form-select" required>
                                    <option value="">Select New Status</option>
                                    <?php if ($attendanceData['data']['status'] == 'P'): ?>
                                        <option value="A">Absent</option>
                                    <?php elseif ($attendanceData['data']['status'] == 'A'): ?>
                                        <option value="P">Present</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <br>
                            <div class="form-group">
                                <label for="correction_reason">Reason for correction</label>
                                <textarea class="form-control" name="correction_reason" id="correction_reason" maxlength="4000" required></textarea>
                            </div><br>
                            <input type="hidden" name="sub_id" value="<?= $sub_id ?>">
                            <input type="hidden" name="date" value="<?= $date ?>">
                            <input type="hidden" name="hour" value="<?= $hour ?>">
                            <input type="hidden" name="stu_id" value="<?= $stu_id ?>">
                            <a href="facupdatestudentattendance.php" class="btn btn-outline-danger">Cancel</a>&nbsp;
                            <button type="submit" name="update_attendance" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_ATTENDANCE') ? 'disabled' : ''; ?>>Update Attendance</button>
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

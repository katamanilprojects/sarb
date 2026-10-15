<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "Mark Attendance";
require_once("hodheader.php");

require_once("hod.class.php");
$hodObj = new HOD();
$faculties = $hodObj->getFacultyByDepartment($_SESSION["dept_id"]);

$subjectsDataJson = [];
if (!empty($_POST['fac_id'])) {
    $selected_fac_id = $_POST['fac_id'];
    require_once("faculty.class.php");
    $facultyObj = new Faculty();

    $selected_date = date('Y-m-d');

    // Handle form submissions (combined logic)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Validate secret code (if applicable)
        if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
            unset($_SESSION['secretcode']);

            if (!empty($_POST['sub_id']) && !empty($_POST['date']) && empty($_POST['diary'])) {
                $selected_sub_id = $_POST['sub_id'];
                $selected_date = $_POST['date'];
                $studentsList = $facultyObj->getMappedStudents($selected_sub_id);
                $unmarkedHours = $facultyObj->getUnmarkedHours($selected_sub_id, $selected_date);
            } elseif (!empty($_POST['hours']) && !empty($_POST['diary'])) {
                \FeatureManager::requireWriteAccess('MOD_ATTENDANCE');
                $stu_ids = isset($_POST['stu_ids']) ? $_POST['stu_ids'] : [];
                $result = $facultyObj->markAttendance($_POST['hours'], $stu_ids, $_POST['sub_id'], $_POST['date'], $_POST['diary'], $selected_fac_id);

                if ($result['status'] == 1) {
                    $msg = "Attendance marked successfully.";
                } else {
                    $msg = "Failed to mark attendance. Please try again.";
                }
            } else {
                //$msg = "Required fields missing. please try again";
            }
        } else {
            $msg = "Please try again";
        }
    }
    $facultySubjects = $facultyObj->getSubjectsByFacultyId($selected_fac_id);
    $subjectsDataJson = json_encode($facultySubjects);
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<style>
    #studentTable {
        border-collapse: collapse;
        width: 100%;
    }

    #studentTable th,
    #studentTable td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #studentTable th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #04AA6D;
        color: white;
    }

    .studentCheckbox {
        /* Style the checkbox */
        width: 18px;
        /* Increase size */
        height: 18px;
        cursor: pointer;
        transform: scale(1.2);
        /* Make it slightly bigger */
    }

    .highlightedRow {
        /* Style for highlighted rows */
        background-color: #04AA6D;
        /* Light yellow */
        font-weight: bold;
    }

    #confirmLabel {
        font-weight: bold;
    }
</style>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Mark Attendance</div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATTENDANCE'); ?>
                    <form action="hodfacaddattendance.php" method="post" id="addsarbform">
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
                                    if (strtotime($subject['end_date']) >= (strtotime(date('Y-m-d')) - 7 * 24 * 60 * 60)) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>" min="<?php echo date("Y-m-d"); ?>" max="<?php echo date("Y-m-d"); ?>" required>
                        </div>
                        <br />
                        <input type="hidden" name="whattodo" id="whattodo" value="getstudents" />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get Students</button>
                    </form>
                </div>
            </div>
            <?php if (!empty($selected_fac_id) && !empty($selected_sub_id)) : ?>
                <br>
                <div class="card">
                    <div class="card-body">

                        <form action="hodfacaddattendance.php" method="post">
                            <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h5>Diary</h5>
                                    <textarea name="diary" class="form-control" rows="8" required="required"></textarea>
                                </div>

                                <div class="col-sm-6">
                                    <h5> Period / Hours</h5>
                                    <?php
                                    if (!empty($unmarkedHours['data'])) {
                                        require_once("modulehoursshow.php");
                                    } else {
                                        echo "<p>No available hours for attendance marking on this date and subject.</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                    </div>
                </div>
                <br>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <h5>Students</h5>
                                <?php

                                echo "<table id='studentTable'>";
                                echo "<thead><tr><th style='text-align:center;'>S.No.</th><th style='text-align:center;'>Select</th><th>Student</th></tr></thead>";
                                echo "<tbody>";

                                $sno = 0;
                                foreach ($studentsList as $student) {
                                    // Check if student should be included based on date of joining
                                    $includeStudent = true;
                                    if (!empty($student['date_of_joining']) && $student['date_of_joining'] > $selected_date) {
                                        $includeStudent = false; // Student joined after the attendance date
                                    }
                                    
                                    if ($includeStudent) {
                                        echo "<tr id='row_{$student['id']}'>";
                                        $sno++;
                                        echo "<td style='text-align:center;'>" . $sno . "</td>";
                                        echo "<td style='text-align:center;'><input type='checkbox' class='studentCheckbox' id='{$student['id']}' name='stu_ids[]' value='{$student['id']}'></td>";
                                        echo "<td><label for='{$student['id']}'>{$student['name']} ({$student['username']})</label></td>";
                                        echo "</tr>";
                                    }
                                }

                                echo "</tbody></table>";
                                ?>

                            </div>
                        </div>
                        <br />
                        <div class="form-group">
                            <input type="checkbox" class="studentCheckbox" id="confirmCheckbox" name="confirmCheckbox" required> &nbsp;
                            <label for="confirmCheckbox" id="confirmLabel">Confirm, Number of Students Present: 0</label>
                        </div>
                        <br />
                        <input type="hidden" name="fac_id" value="<?php echo $selected_fac_id; ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-outline-primary" <?= \FeatureManager::isHodReadOnly('MOD_ATTENDANCE') ? 'disabled' : ''; ?>>Save Attendance</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
        </div>
    </div>
</div>
<script>
    const checkboxes = document.querySelectorAll('.studentCheckbox');
    const confirmLabel = document.getElementById('confirmLabel');

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            row.classList.toggle('highlightedRow', this.checked);

            // Update the confirm label directly
            const numChecked = document.querySelectorAll('.studentCheckbox:checked').length;
            confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
        });
    });

    document.getElementById('fac_id').addEventListener('change', function() {
        document.getElementById('whattodo').value = "showfacsubjects";
        document.getElementById('addsarbform').submit();
    });
</script>
<script>
    const subjectData = <?php echo $subjectsDataJson; ?>;

    document.getElementById('sub_id').addEventListener('change', function() {
        const selectedSubId = this.value;
        let foundStartDate = null;

        for (const subject of subjectData.data) {
            if (subject.id == selectedSubId) {
                foundStartDate = subject.start_date;
                break;
            }
        }

        if (foundStartDate) {
            document.getElementById('date').min = foundStartDate;
        } else {
            document.getElementById('date').min = '';
        }
    });
</script>
<?php
require_once("hodfooter.php");
?>
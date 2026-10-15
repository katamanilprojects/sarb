<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "Mark Attendance";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$selected_date = date('Y-m-d');
if (!empty($_POST['date'])) {
    $selected_date = $_POST['date'];
}

$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$subjectsDataJson = json_encode($facultySubjects['data']);

// Initialize variables
$studentsList = [];
$unmarkedHours = [];


// Handle form submissions (combined logic)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate secret code (if applicable)
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        session_regenerate_id(true);

        if (!empty($_POST['sub_id']) && !empty($_POST['date'])) {
            $selected_sub_id = $_POST['sub_id'];
            $selected_date = $_POST['date'];

            $validSubjectIds = array_column($facultySubjects['data'], 'id');

            if (!in_array($selected_sub_id, $validSubjectIds)) {
                $msg = "Invalid subject selected.";
                $selected_sub_id = null;
            }

            $d = DateTime::createFromFormat('Y-m-d', $selected_date);
            if (!$d || $d->format('Y-m-d') !== $selected_date || $d > new DateTime()) {
                $msg = "Invalid date selected.";
                $selected_date = null;
            }
        }
        if ($selected_sub_id && $selected_date) {
            if (empty($_POST['diary'])) {
                $studentsList = $facultyObj->getMappedStudents($selected_sub_id);
                $unmarkedHours = $facultyObj->getUnmarkedHours($selected_sub_id, $selected_date);
            } elseif (!empty($_POST['hours']) && !empty($_POST['diary'])) {
                \FeatureManager::requireWriteAccess('MOD_ATTENDANCE');
                $stu_ids = isset($_POST['stu_ids']) ? $_POST['stu_ids'] : [];
                $result = $facultyObj->markAttendance($_POST['hours'], $stu_ids, $selected_sub_id, $selected_date, $_POST['diary'], $faculty_id);

                if ($result['status'] == 1) {
                    $msg = "Attendance marked successfully.";
                } else {
                    $msg = "Failed to mark attendance. Please try again.";
                }
                $selected_sub_id = null;
            } else {
                $msg = "Required fields missing. please try again";
                $selected_sub_id = null;
            }
        }
    } else {
        $msg = "Please try again";
    }
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

    .selectAllCheckbox {
        /* Style the checkbox */
        width: 18px;
        /* Increase size */
        height: 18px;
        cursor: pointer;
        transform: scale(1.2);
        margin-right: 0.5em;
        /* Make it slightly bigger */
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
                    <form action="facaddattendance.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    if (strtotime($subject['end_date']) + (60 * 60 * 24 * 3) >= strtotime(date('Y-m-d'))) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8') . "' $selected>";
                                        echo htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8') . " (";
                                        echo htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8') . ") - ";
                                        echo htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8') . " - ";
                                        echo htmlspecialchars($subject['acad_year'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>" min="<?php echo $selected_date; ?>" max="<?php echo $selected_date; ?>" required />
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <?php if (!empty($selected_sub_id) && is_array($studentsList) && count($studentsList) > 0 && !empty($_POST['date'])) { ?>
                            <a href="facaddattendance.php" class="btn btn-secondary">Clear</a>
                        <?php } else { ?>
                            <button type="submit" class="btn btn-primary">Get Students</button>
                        <?php } ?>
                    </form>
                </div>
            </div>
            <?php if (!empty($selected_sub_id) && is_array($studentsList) && count($studentsList) > 0 && !empty($_POST['date'])) : ?>
                <br>
                <div class="card">
                    <div class="card-body">

                        <form action="facaddattendance.php" method="post">
                            <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                            <div class="row">
                                <div class="col-sm-6">
                                    <h5 class="mb-2">Teaching Diary</h5>
                                    <textarea name="diary" id="diaryTextarea" class="form-control mb-2" rows="6" required="required" placeholder="Topics covered in this lecture period..."></textarea>
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
                                echo "<thead><tr><th colspan='4'><input type='checkbox' class='selectAllCheckbox' id='selectAllCheckbox'><label for='selectAllCheckbox'>Select/Unselect All Students</label></th></tr>";
                                echo "<tr><th style='text-align:center;'>S.No.</th><th style='text-align:center;'>Select</th><th>Adm. No.</th><th>Student Name</th></tr></thead>";
                                echo "<tbody>";

                                $sno = 0;
                                $st_htno_doj_res = $facultyObj->getTempStHTNoByDoJ($_POST['date']);
                                $details = $facultyObj->getClassSubjectFacultyDetails($selected_sub_id, $faculty_id);
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
                                        echo "<td><label for='{$student['id']}'>{$student['username']}</label></td>";
                                        echo "<td><label for='{$student['id']}'>{$student['name']}</label></td>";
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
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-outline-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_ATTENDANCE') ? 'disabled' : ''; ?>>Save Attendance</button>
                        </form>

                        <script>
                            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                            const checkboxes = document.querySelectorAll('.studentCheckbox');
                            const confirmLabel = document.getElementById('confirmLabel');
                            const confirmCheckbox = document.getElementById('confirmCheckbox');
                            const hoursCheckboxes = document.querySelectorAll('input[name="hours[]"]'); // Get all hours checkboxes

                            checkboxes.forEach(checkbox => {
                                checkbox.addEventListener('change', function() {
                                    const row = this.closest('tr');
                                    row.classList.toggle('highlightedRow', this.checked);

                                    // Update the confirm label directly
                                    const numChecked = document.querySelectorAll('.studentCheckbox:checked').length;
                                    confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
                                });
                            });

                            confirmCheckbox.addEventListener('change', function() {
                                if (this.checked) {
                                    // Check if at least one hour is selected
                                    let atLeastOneHourSelected = false;
                                    hoursCheckboxes.forEach(hourCheckbox => {
                                        if (hourCheckbox.checked) {
                                            atLeastOneHourSelected = true;
                                        }
                                    });

                                    if (!atLeastOneHourSelected) {
                                        alert("Please select at least one Period/Hour before confirming.");
                                        this.checked = false; // Uncheck the confirm checkbox
                                    }
                                }
                            });

                            if (selectAllCheckbox) {
                                selectAllCheckbox.addEventListener('change', function() {
                                    checkboxes.forEach(checkbox => {
                                        if (checkbox !== selectAllCheckbox && checkbox !== confirmCheckbox) {
                                            checkbox.checked = this.checked;
                                            const row = checkbox.closest('tr');
                                            row.classList.toggle('highlightedRow', checkbox.checked);
                                        }
                                    });

                                    const numChecked = document.querySelectorAll('.studentCheckbox:checked').length;
                                    confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
                                });
                            }
                        </script>

                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
        </div>
    </div>
</div>
<script>
    const subjectData = <?php echo $subjectsDataJson; ?>;

    document.getElementById('sub_id').addEventListener('change', function() {
        const selectedSubId = this.value;
        let foundStartDate = null;
        let foundEndDate = null;

        for (const subject of subjectData) {
            if (subject.id == selectedSubId) {
                foundStartDate = subject.start_date;
                foundEndDate = subject.end_date;
                break;
            }
        }

        const dateInput = document.getElementById('date');
        const today = new Date().toISOString().split('T')[0];

        if (foundStartDate) {
            dateInput.min = foundStartDate;
        } else {
            dateInput.min = '';
        }

        if (foundEndDate) {
            dateInput.max = (foundEndDate > today) ? today : foundEndDate;
        } else {
            dateInput.max = today;
        }

        if (!dateInput.value || dateInput.value < dateInput.min || dateInput.value > dateInput.max) {
            if (today >= dateInput.min && today <= dateInput.max) {
                dateInput.value = today;
            } else {
                dateInput.value = dateInput.max;
            }
        }

    });
</script>
<?php
require_once("facfooter.php");
?>
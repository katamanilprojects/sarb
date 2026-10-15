<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

$page_title = "Add Exceptional Attendance";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$errmsg = "";
$succmsg = "";
$hoursMarked = false;
$diaryData = null;
$unmarkedStudents = null;
$selected_sub_id = null;
$selected_date = null;
$selected_hour = null;
$selected_hour_desc = ""; // To store the hour description for display

// Step 1: Handle request for marked hours for a subject and date
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_marked_hours'])) {
    $selected_sub_id = $_POST['sub_id'];
    $selected_date = $_POST['date'];

    // Validate inputs
    if (empty($selected_sub_id) || empty($selected_date)) {
        $errmsg = "Please select a subject and a date.";
    } else {
        // We need a method to get hours that *have* been marked.
        // Assuming getAvailableHours might return marked ones if they are available,
        // or a new method getMarkedHours is needed if getAvailableHours is only for unmarked.
        // Let's assume getMarkedHours is needed to be precise.
        $markedHoursResult = $facultyObj->getAvailableHours($selected_sub_id, $selected_date); // This method needs to be added

        if (!empty($markedHoursResult['data'])) {
            $hoursMarked = true;
            // Retain original POST data for form repopulation
            $_POST['sub_id'] = $selected_sub_id;
            $_POST['date'] = $selected_date;
        } else {
            $errmsg = "No hours found with marked attendance for the selected subject and date.";
        }
    }
}

// Step 2: Handle request to get diary and unmarked students for a specific hour
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_unmarked_students'])) {
    $selected_sub_id = $_POST['sub_id'];
    $selected_date = $_POST['date'];
    $selected_hour = $_POST['hour'];
    
    $markedHoursResult = $facultyObj->getAvailableHours($selected_sub_id, $selected_date); // This method needs to be added

    foreach ($markedHoursResult['data'] as $hourData){
        if ($hourData['hour'] == $selected_hour) {
            $selected_hour_desc = $hourData['hour_desc']; // Store the hour description for display
            break;
        }
    }

    // Validate inputs
    if (empty($selected_sub_id) || empty($selected_date) || empty($selected_hour)) {
        $errmsg = "Please select a subject, date, and hour.";
    } else {
        $diaryData = $facultyObj->getDiaryEntry($selected_sub_id, $selected_date, $selected_hour); // Existing method
        $unmarkedStudents = $facultyObj->getStudentsForExceptionalAttendance($selected_sub_id, $selected_date, $selected_hour); // New method

        if (!$diaryData) {
            $errmsg = "Could not retrieve diary entry for the selected hour.";
        }

        // Re-fetch marked hours to populate the hour dropdown if needed
        // This ensures the hour dropdown remains populated if the user goes back or there's an error
        $markedHoursResult = $facultyObj->getAvailableHours($selected_sub_id, $selected_date);
        if (!empty($markedHoursResult['data'])) {
            $hoursMarked = true;
        }
    }
}

// Step 3: Handle submission of exceptional attendance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_exceptional_attendance'])) {
    \FeatureManager::requireWriteAccess('MOD_ATTENDANCE');
    // Validate secret code for CSRF protection
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        session_regenerate_id(true);

        $selected_sub_id = $_POST['sub_id'];
        $selected_date = $_POST['date'];
        $selected_hour = $_POST['hour'];
        $stu_ids_to_mark = isset($_POST['stu_ids']) ? $_POST['stu_ids'] : [];

        if (empty($selected_sub_id) || empty($selected_date) || empty($selected_hour)) {
            $errmsg = "Required subject, date, or hour is missing.";
        } else {
            if (empty($stu_ids_to_mark) && !empty($_POST['confirmExceptionalCheckbox']) && $_POST['confirmExceptionalCheckbox'] != 'on') {
                $errmsg = "No students selected to mark attendance.";
            } else {
                // Use the existing markAttendance function
                // It expects an array for 'hours', so wrap $selected_hour in an array.
                // For exceptional attendance, diary content isn't strictly necessary for the marking function itself,
                // as it's already recorded. Passing an empty string or the retrieved diary entry is fine.
                $result = $facultyObj->markExceptionalAttendance([$selected_hour], $stu_ids_to_mark, $selected_sub_id, $selected_date, $faculty_id);


                if ($result['status'] == 1) {
                    $succmsg = "Exceptional attendance marked successfully.";
                    // Clear form after successful submission
                    $selected_sub_id = null;
                    $selected_date = null;
                    $selected_hour = null;
                    $diaryData = null;
                    $unmarkedStudents = null;
                    $hoursMarked = false;
                    unset($_POST); // Clear all POST data to reset form
                } else {
                    $errmsg = "Failed to mark exceptional attendance. Please try again.";
                }
            }
        }
    } else {
        $errmsg = "Invalid request. Please try again.";
    }
}

// Generate a new secret code for CSRF protection
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Add Attendance (For Exceptional Cases like Lateral Entry students)</div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATTENDANCE'); ?>
                    <?php if ($errmsg): ?>
                        <div class="alert alert-danger"><?= $errmsg ?></div>
                    <?php endif; ?>
                    <?php if ($succmsg): ?>
                        <div class="alert alert-info"><?= $succmsg ?></div>
                    <?php endif; ?>

                    <form action="facexceptionalattendance.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required
                                <?= ($hoursMarked || $diaryData || !empty($unmarkedStudents)) ? 'disabled' : '' ?>>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    if (strtotime($subject['end_date']) >= strtotime(date('Y-m-d'))) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        $subjectid = htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8');
                                        $sub_fullname = htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8');
                                        $subcode = htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8');
                                        $class_name = htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8');
                                        $acad_year = htmlspecialchars($subject['acad_year'], ENT_QUOTES, 'UTF-8');
                                        echo "<option value='$subjectid' $selected>$sub_fullname ($subcode) - $class_name - $acad_year</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" max="<?= date("Y-m-d") ?>"
                                value="<?= isset($_POST['date']) ? $_POST['date'] : '' ?>" required
                                <?= ($hoursMarked || $diaryData || !empty($unmarkedStudents)) ? 'disabled' : '' ?>>
                        </div>
                        <br>

                        <?php if (!$hoursMarked && !$diaryData && empty($unmarkedStudents)): ?>
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <button type="submit" name="get_marked_hours" class="btn btn-primary">Get Marked Hours</button>
                        <?php endif; ?>

                        <?php if ($hoursMarked && empty($selected_hour)): // Show hour selection after "Get Marked Hours" 
                        ?>
                            <div class="form-group">
                                <label for="hour">Hour:</label>
                                <select name="hour" id="hour" class="form-select" required>
                                    <option value="">Select Hour</option>
                                    <?php foreach ($markedHoursResult['data'] as $hourData): ?>
                                        <option value="<?= $hourData['hour'] ?>"
                                            <?= (isset($_POST['hour']) && $_POST['hour'] == $hourData['hour']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($hourData['hour_desc']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <br>
                            <input type="hidden" name="sub_id" value="<?= htmlspecialchars($selected_sub_id) ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <a href="facexceptionalattendance.php" class="btn btn-outline-danger">Cancel</a>&nbsp;
                            <button type="submit" name="get_unmarked_students" class="btn btn-info">Get Unmarked Students</button>
                        <?php endif; ?>

                        <?php if ($diaryData || !empty($unmarkedStudents)): // Show diary and student list after "Get Unmarked Students" 
                        ?>
                            <input type="hidden" name="sub_id" value="<?= htmlspecialchars($selected_sub_id) ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                            <input type="hidden" name="hour" value="<?= htmlspecialchars($selected_hour) ?>">

                            <div class="form-group">
                                <label for="hour_display">Selected Hour:</label>
                                <input type="text" class="form-control" id="hour_display" value="<?= htmlspecialchars($selected_hour_desc) ?>" disabled>
                            </div>
                            <br>

                            <div class="card mb-3">
                                <div class="card-header">Diary Entry</div>
                                <div class="card-body">
                                    <p><?= htmlspecialchars($diaryData) ?></p>
                                </div>
                            </div>

                            <h5>Students Not Marked for this Hour:</h5>
                            <?php if (!empty($unmarkedStudents)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="unmarkedStudentTable">
                                        <thead>
                                            <tr>
                                                <th colspan='4'><input type='checkbox' class='selectAllCheckbox' id='selectAllUnmarked'><label for='selectAllUnmarked'>Select/Unselect All Students</label></th>
                                            </tr>
                                            <tr>
                                                <th style='text-align:center;'>Select</th>
                                                <th>Adm. No.</th>
                                                <th>Student Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($unmarkedStudents as $student): ?>
                                                <tr>
                                                    <td style="text-align:center;"><input type="checkbox" class="studentCheckbox" name="stu_ids[]" value="<?= $student['id'] ?>"></td>
                                                    <td><?= htmlspecialchars($student['username']) ?></td>
                                                    <td><?= htmlspecialchars($student['name']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <br>
                                <div class="form-group">
                                    <input type="checkbox" class="studentCheckbox" id="confirmExceptionalCheckbox" name="confirmExceptionalCheckbox" required> &nbsp;
                                    <label for="confirmExceptionalCheckbox" id="confirmExceptionalLabel">Confirm, Number of Students to Mark: 0</label>
                                </div>
                                <br />
                                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                <button type="submit" name="submit_exceptional_attendance" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_ATTENDANCE') ? 'disabled' : ''; ?>>Mark Selected Students as Present</button>
                            <?php else: ?>
                                <div class="alert alert-info">All students are already marked for this hour.</div>
                                <a href="facexceptionalattendance.php" class="btn btn-outline-primary">Back to Selection</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    #unmarkedStudentTable {
        border-collapse: collapse;
        width: 100%;
    }

    #unmarkedStudentTable th,
    #unmarkedStudentTable td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #unmarkedStudentTable th {
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

    .selectAllCheckbox,
    .studentCheckbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        transform: scale(1.2);
        margin-right: 0.5em;
    }

    .highlightedRow {
        background-color: #04AA6D;
        font-weight: bold;
        color: white;
    }

    #confirmExceptionalLabel {
        font-weight: bold;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllUnmarkedCheckbox = document.getElementById('selectAllUnmarked');
        const unmarkedCheckboxes = document.querySelectorAll('#unmarkedStudentTable .studentCheckbox');
        const confirmExceptionalCheckbox = document.getElementById('confirmExceptionalCheckbox');
        const confirmExceptionalLabel = document.getElementById('confirmExceptionalLabel');

        function updateConfirmLabel() {
            const numChecked = document.querySelectorAll('#unmarkedStudentTable .studentCheckbox:checked').length;
            confirmExceptionalLabel.textContent = `Confirm, Number of Students to Mark: ${numChecked}`;
        }

        if (selectAllUnmarkedCheckbox) {
            selectAllUnmarkedCheckbox.addEventListener('change', function() {
                unmarkedCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    const row = checkbox.closest('tr');
                    row.classList.toggle('highlightedRow', checkbox.checked);
                });
                updateConfirmLabel();
            });
        }

        unmarkedCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('tr');
                row.classList.toggle('highlightedRow', this.checked);
                updateConfirmLabel();
            });
        });

        // Initial label update
        updateConfirmLabel();

        // Pass selected hour description to the next POST request
        const hourSelect = document.getElementById('hour');
        if (hourSelect) {
            hourSelect.addEventListener('change', function() {
                const selectedOption = hourSelect.options[hourSelect.selectedIndex];
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'hour_desc';
                hiddenInput.value = selectedOption.textContent;
                this.form.appendChild(hiddenInput);
            });
        }
    });
</script>

<?php require_once("facfooter.php"); ?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_GROUPED_ATT');

$page_title = "Mark Grouped Attendance";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$selected_date = date('Y-m-d');

$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$subjectsDataJson = json_encode($facultySubjects);

// Initialize variables
$studentsList = []; // This will now be an array of arrays, grouped by class
$groupedStudentsList = [];
$all_sub_ids = [];
$unmarkedHours = [];
$totalStudentCount = 0;
$msg = "";

// Handle form submissions (combined logic)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate secret code (if applicable)
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        session_regenerate_id(true);

        if (!empty($_POST['sub_ids']) && !empty($_POST['date'])) {
            $all_sub_ids = $_POST['sub_ids'];
            $selected_date = $_POST['date'];

            // Validate all submitted subject IDs
            $validSubjectIds = array_column($facultySubjects['data'], 'id');
            $all_valid = true;
            foreach ($all_sub_ids as $sub_id) {
                if (!in_array($sub_id, $validSubjectIds)) {
                    $all_valid = false;
                    break;
                }
            }

            if (!$all_valid) {
                $msg = "Invalid subject selected.";
                $all_sub_ids = [];
            }

            // Validate date
            $d = DateTime::createFromFormat('Y-m-d', $selected_date);
            if (!$d || $d->format('Y-m-d') !== $selected_date || $d > new DateTime()) {
                $msg = "Invalid date selected.";
                $selected_date = null;
            }

            if ($all_valid && $selected_date) {
                if (empty($_POST['diary'])) {
                    // Check if all subject codes are the same
                    $subjectCodes = [];
                    foreach ($all_sub_ids as $sub_id) {
                        foreach ($facultySubjects['data'] as $subject) {
                            if ($subject['id'] == $sub_id) {
                                $subjectCodes[] = $subject['subcode'];
                                break;
                            }
                        }
                    }
                    
                    $uniqueSubjectCodes = array_unique($subjectCodes);
                    if (count($uniqueSubjectCodes) > 1) {
                        $msg = "Error: All selected subjects must have the same subject code. Found codes: " . implode(', ', $uniqueSubjectCodes);
                        $all_sub_ids = [];
                    } else {
                        // "Get Students" logic
                        $groupedStudentsList = []; // Use a grouped array
                        $totalStudentCount = 0;

                    foreach ($all_sub_ids as $current_sub_id) {
                        // Get the class name for this subject
                        $details = $facultyObj->getClassSubjectFacultyDetails($current_sub_id, $faculty_id);
                        $classname = $details['data']['classname'] ?? "Unknown Class " . $current_sub_id; // Fallback

                        // Get students for this subject
                        $current_students = $facultyObj->getMappedStudents($current_sub_id);

                        // Add students to the grouped list
                        if (!isset($groupedStudentsList[$classname])) {
                            $groupedStudentsList[$classname] = [];
                        }

                        foreach ($current_students as $student) {
                            // Apply date_of_joining filter here
                            $includeStudent = true;
                            if (!empty($student['date_of_joining']) && $student['date_of_joining'] > $selected_date) {
                                $includeStudent = false; 
                            }

                            if ($includeStudent) {
                                // Use student ID as key to prevent duplicates
                                if (!isset($groupedStudentsList[$classname][$student['id']])) {
                                    $groupedStudentsList[$classname][$student['id']] = $student;
                                    $totalStudentCount++;
                                }
                            }
                        }
                    }

                        // Now, sort the students *within* each group by username (roll no)
                        foreach ($groupedStudentsList as $classname => &$students_in_group) {
                            usort($students_in_group, function($a, $b) {
                                return strcmp($a['username'], $b['username']);
                            });
                        }
                        unset($students_in_group); // Unset reference

                        // Get hours from the *first* subject (assuming all grouped classes have same timings)
                        if (!empty($all_sub_ids[0])) {
                            $unmarkedHours = $facultyObj->getUnmarkedHours($all_sub_ids[0], $selected_date);
                        }
                    }

                } elseif (!empty($_POST['hours']) && !empty($_POST['diary'])) {
                    \FeatureManager::requireWriteAccess('MOD_GROUPED_ATT');
                    // "Save Attendance" logic
                    $stu_ids = isset($_POST['stu_ids']) ? $_POST['stu_ids'] : [];
                    $hours = $_POST['hours'];
                    $diary = $_POST['diary'];
                    $date = $_POST['date'];

                    $success_count = 0;
                    $failure_count = 0;
                    
                    // Loop and mark attendance for EACH subject
                    foreach ($all_sub_ids as $current_sub_id) {
                        $result = $facultyObj->markAttendance(
                            $hours,
                            $stu_ids,
                            $current_sub_id, 
                            $date,
                            $diary,
                            $faculty_id
                        );
                        if ($result['status'] == 1) {
                            $success_count++;
                        } else {
                            $failure_count++;
                        }
                    }

                    if ($success_count > 0) {
                        $msg = "Attendance marked successfully for $success_count subject(s).";
                        if ($failure_count > 0) {
                             $msg .= " $failure_count subject(s) failed (this may be normal if no students are mapped).";
                        }
                    } else {
                        $msg = "Failed to mark attendance. Please try again.";
                    }
                    
                    // Reset form state
                    $all_sub_ids = [];
                    $groupedStudentsList = [];
                } else {
                    $msg = "Required fields missing. please try again";
                    $all_sub_ids = [];
                }
            }
        } else {
            $msg = "Please try again";
        }
    } else {
        // Handle non-POST or failed CSRF
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
             $msg = "Please try again";
        }
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
                <div class="card-header">Mark Grouped Attendance (e.g., Open Electives)</div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_GROUPED_ATT'); ?>
                    <form action="facaddgroupedattendance.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject(s): (Hold Ctrl/Cmd to select multiple)</label>
                            <select name="sub_ids[]" id="sub_id" class="form-select" required multiple="multiple" size="8">
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    if (strtotime($subject['end_date']) >= strtotime(date('Y-m-d'))) {
                                        // Check if this subject was one of the selected ones
                                        $selected = (!empty($all_sub_ids) && in_array($subject['id'], $all_sub_ids)) ? 'selected' : '';
                                        echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group mt-2">
                            <label for="date">Date:</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>" min="<?php echo date("Y-m-d"); ?>" max="<?php echo date("Y-m-d"); ?>" required />
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get Combined Student List</button>
                    </form>
                </div>
            </div>
            
            <?php // Show diary/student list only if "Get Students" was clicked and was successful
            if (!empty($all_sub_ids) && !empty($_POST['date']) && empty($_POST['diary'])) : ?>
                <br>
                <div class="card">
                    <div class="card-body">

                        <form action="facaddgroupedattendance.php" method="post">
                            <?php // Pass along all selected subject IDs to the save handler
                            foreach ($all_sub_ids as $sub_id_to_save): ?>
                                <input type="hidden" name="sub_ids[]" value="<?= htmlspecialchars($sub_id_to_save); ?>">
                            <?php endforeach; ?>
                            
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
                                <h5>Students (Combined List: <?php echo $totalStudentCount; ?>)</h5>
                                
                                <table id="studentTable">
                                    <thead>
                                        <tr><th colspan='4'><input type='checkbox' class='selectAllCheckbox' id='selectAllCheckbox'><label for='selectAllCheckbox'>Select/Unselect All Students</label></th></tr>
                                        <tr><th style='text-align:center;'>S.No.</th><th style='text-align:center;'>Select</th><th>Adm. No.</th><th>Student Name</th></tr>
                                    </thead>
                                
                                <?php
                                $sno = 0;
                                // $st_htno_doj_res = $facultyObj->getTempStHTNoByDoJ($_POST['date']); // This check seems specific, may not apply to electives
                                
                                foreach ($groupedStudentsList as $classname => $students_in_group) {
                                    // Add a group header row
                                    echo "<tbody class='table-group-divider'>"; // New tbody for each group
                                    echo "<tr class='table-secondary'><td colspan='4'><strong>Class: " . htmlspecialchars($classname) . "</strong> (" . count($students_in_group) . " Students)</td></tr>";

                                    if (empty($students_in_group)) {
                                        echo "<tr><td colspan='4' class='text-muted'>No students found for this class (or they joined after the selected date).</td></tr>";
                                    }

                                    foreach ($students_in_group as $student) {
                                        // date_of_joining filter was already applied in the PHP logic
                                        echo "<tr id='row_{$student['id']}'>";
                                        $sno++;
                                        echo "<td style='text-align:center;'>" . $sno . "</td>";
                                        echo "<td style='text-align:center;'><input type='checkbox' class='studentCheckbox' id='{$student['id']}' name='stu_ids[]' value='{$student['id']}'></td>";
                                        echo "<td><label for='{$student['id']}'>{$student['username']}</label></td>";
                                        echo "<td><label for='{$student['id']}'>{$student['name']}</label></td>";
                                        echo "</tr>";
                                    }
                                    echo "</tbody>"; // End group tbody
                                }

                                echo "</table>";
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
                        <button type="submit" class="btn btn-outline-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_GROUPED_ATT') ? 'disabled' : ''; ?>>Save Grouped Attendance</button>
                        </form>

                    </div>
                </div>
            <?php elseif (!empty($all_sub_ids) && empty($groupedStudentsList) && !empty($_POST['date'])) : ?>
                 <br>
                 <div class="alert alert-warning">No students are mapped to the selected subjects (or all mapped students joined after the selected date).</div>
            <?php endif; ?>
            <?php if (isset($msg)) echo '<div class="alert alert-info mt-3">' . $msg . '</div>'; ?>
        </div>
    </div>
</div>
<script>
    const subjectData = <?php echo $subjectsDataJson; ?>;

    document.getElementById('sub_id').addEventListener('change', function() {
        const selectedSubId = this.value; // This will be the first selected
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

    // --- JavaScript for Checkboxes ---
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.studentCheckbox');
    const confirmLabel = document.getElementById('confirmLabel');
    const confirmCheckbox = document.getElementById('confirmCheckbox');
    const hoursCheckboxes = document.querySelectorAll('input[name="hours[]"]'); // Get all hours checkboxes

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (row) { // Check if row exists
                row.classList.toggle('highlightedRow', this.checked);
            }

            // Update the confirm label directly
            const numChecked = document.querySelectorAll('#studentTable .studentCheckbox:checked').length;
            if (confirmLabel) { // Check if confirmLabel exists
                confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
            }
        });
    });

    if(confirmCheckbox) {
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
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            checkboxes.forEach(checkbox => {
                if (checkbox !== selectAllCheckbox && checkbox !== confirmCheckbox) {
                    checkbox.checked = this.checked;
                    const row = checkbox.closest('tr');
                    if(row) { // Add check for row existence
                        row.classList.toggle('highlightedRow', checkbox.checked);
                    }
                }
            });

            const numChecked = document.querySelectorAll('#studentTable .studentCheckbox:checked').length;
            if(confirmLabel) { // Add check for confirmLabel existence
                confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
            }
        });
    }

    // Initial update of the count label on page load
    if (confirmLabel) {
        const numChecked = document.querySelectorAll('#studentTable .studentCheckbox:checked').length;
        confirmLabel.textContent = "Confirm, Number of Students Present: " + numChecked;
    }
</script>
<?php
require_once("facfooter.php");
?>


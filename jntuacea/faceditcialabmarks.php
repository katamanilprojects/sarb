<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once __DIR__ . "/services/SettingsService.php";

$facultyObj = new Faculty();
$ciaObj = new CIA();
$settingsSvc = \Services\SettingsService::getInstance();

$currentSubId = !empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : (!empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0);
$subReg = $currentSubId > 0 ? $ciaObj->getRegulationIdForSubject($currentSubId) : 'R23';

$maxDayToDay = (float)$settingsSvc->get('lab_cia_day_to_day_marks', $subReg, 15.0);
$maxInternalTest = (float)$settingsSvc->get('lab_cia_internal_test_marks', $subReg, 15.0);

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];

    $isValid = true;
    // VALIDATE marks for each student
    foreach ($_POST['student_id'] as $index => $studentId) {
        $day_to_day_marks = $_POST['day_to_day_marks'][$index];
        $internal_test_marks = $_POST['internal_test_marks'][$index];

        // Validate marks (ensure they are within the allowed range)
        if ($day_to_day_marks > $maxDayToDay || $day_to_day_marks < 0 || $internal_test_marks > $maxInternalTest || $internal_test_marks < 0 || !is_numeric($day_to_day_marks) || !is_numeric($internal_test_marks)) {
            // Handle invalid marks
            $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
            $isValid = false;
            break;
        }
    }

    if ($isValid) {
        // Update marks for each student
        foreach ($_POST['student_id'] as $index => $studentId) {
            $marksId = $_POST['marks_id'][$index]; 
            $day_to_day_marks = $_POST['day_to_day_marks'][$index];
            $internal_test_marks = $_POST['internal_test_marks'][$index];

            $ciaObj->updateUGLabInternalAssessmentMarks($marksId, $day_to_day_marks, $internal_test_marks);
        }
        $_SESSION["succ"] = "Student Continuous Lab Internal Assessment Successfully Updated";
    }

    // Redirect back to facciamarks.php after saving
    header("Location: facciamarks.php?sub_id=$sub_id");
    exit();
}

// Get subject details and student list with marks
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList1 = $facultyObj->getMappedStudents($sub_id);

    // Fetch marks for each student
    $studentList = []; 
    foreach ($studentList1 as $student) {
        $marks = $facultyObj->getStUGLabInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
        $student['marks'] = !empty($marks) ? $marks[0] : null;
        $studentList[] = $student; // Add the modified student data to the new array
    }

    // Generate new secret code
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
    // Handle invalid request
    header("Location: facciamarks.php");
    exit();
}

require_once("facheader.php");
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Editing Continuous Internal Assessment (Lab) Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="faceditcialabmarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="2">Adm. No<br />Student Name</th>
                                    <th colspan="3" style="text-align: center;">CIA - <?php echo $assessmentNumber; ?></th>
                                </tr>
                                <tr>
                                    <th>Day-to-Day<br>(<?= $maxDayToDay ?>)</th>
                                    <th>Internal Test<br>(<?= $maxInternalTest ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $student) :
                                    $subj = rtrim(rtrim(number_format($student['marks']['day_to_day_marks'], 1), '0'), '.');
                                    $obj = rtrim(rtrim(number_format($student['marks']['internal_test_marks'], 1), '0'), '.');
                                    if(empty($subj)){
                                        $subj = 0;
                                    }
                                    if(empty($obj)){
                                        $obj = 0;
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $student['username']; ?><br /><?php echo $student['name']; ?></td>
                                        <td>
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="marks_id[]" value="<?php echo $student['marks'] ? $student['marks']['id'] : ''; ?>"> 
                                            <input type="text" name="day_to_day_marks[]" class="form-control" maxlength="5" value="<?php echo $subj; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : 'required'; ?>>
                                        </td>
                                        <td>
                                            <input type="text" name="internal_test_marks[]" class="form-control" maxlength="5" value="<?php echo $obj; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : 'required'; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="submit_action" value="submit" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Validate & Update Marks</button>
                    </form>
                </div>
                <div class="card-footer">
                    <a href='facciamarks.php' class='btn btn-warning btn-sm'>Click here to Cancel Updates and Exit</a>                            
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitButton = document.querySelector('button[name="submit_action"][value="submit"]');

        form.onsubmit = function(e) {
            // Only perform validation if the "Submit" button was clicked
            if (submitButton.clicked) {
                const daytodayInputs = document.querySelectorAll('input[name="day_to_day_marks[]"]');
                const internaltestInputs = document.querySelectorAll('input[name="internal_test_marks[]"]');

                let isValid = true;
                let firstInvalidInput = null; // To store the first invalid input

                const maxD2D = <?= json_encode($maxDayToDay) ?>;
                const maxInt = <?= json_encode($maxInternalTest) ?>;

                daytodayInputs.forEach((input, index) => {
                    const day_to_day_marks = parseFloat(input.value);
                    const internal_test_marks = parseFloat(internaltestInputs[index].value);

                    // Validate if the inputs are filled and within the specified ranges
                    if (
                        isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0 ||
                        isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0 
                    ) {
                        isValid = false;

                        // Identify the first invalid input and focus on it
                        if (!firstInvalidInput) {
                            if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                                firstInvalidInput = internaltestInputs[index];
                            } else if (isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0) {
                                firstInvalidInput = input;
                            }
                        }

                        // Apply red border to invalid fields
                        if (isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0) {
                            input.style.border = "2px solid red"; // Highlight invalid day-to-day field
                        }else{
                            input.style.border = "";
                        }
                        if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                            internaltestInputs[index].style.border = "2px solid red"; // Highlight invalid internal-test field
                        }else{
                            internaltestInputs[index].style.border = "";
                        }
                    }else{
                        // Reset the border if valid
                        input.style.border = "";
                        internaltestInputs[index].style.border = "";
                    }
                });

                if (!isValid) {
                    e.preventDefault(); // Prevent form submission if validation fails
                    alert("Please enter valid marks:\n* Day-To-Day: 0-" + maxD2D + "\n* Internal-Test: 0-" + maxInt);

                    // Focus on the first invalid input and set the cursor there
                    if (firstInvalidInput) {
                        firstInvalidInput.focus();  // Focus on the first invalid input
                        setTimeout(() => {
                            firstInvalidInput.scrollIntoView({
                                behavior: "smooth",
                                block: "center",
                                inline: "nearest"
                            });
                        }, 100); // Ensure it's visible
                    }
                }
            }
        };

        // Track which button was clicked
        document.querySelectorAll('button[name="submit_action"]').forEach(button => {
            button.onclick = function() {
                submitButton.clicked = this.value === 'submit';
            };
        });
    });
</script>

<?php
require_once("facfooter.php");
?>
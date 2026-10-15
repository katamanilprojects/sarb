<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once __DIR__ . "/services/SettingsService.php";
require_once __DIR__ . "/services/FeatureManager.php";

\FeatureManager::requireAccess('MOD_CIA_MARKS');

$facultyObj = new Faculty();
$ciaObj = new CIA();
$settingsSvc = \Services\SettingsService::getInstance();

$currentSubId = !empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : (!empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0);
$subReg = $currentSubId > 0 ? $ciaObj->getRegulationIdForSubject($currentSubId) : 'R23';

$maxActivity = (float)$settingsSvc->get('drawing_day_to_day_marks', $subReg, 15.0);
$maxInternalTest = (float)$settingsSvc->get('drawing_mid_subjective_marks', $subReg, 15.0);

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'] && !empty($_POST['submit_action'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];
    $submitAction = $_POST["submit_action"];

    if ($submitAction == 'save') {
        // Save marks temporarily
        foreach ($_POST['student_id'] as $index => $studentId) {
            $activity_marks = $_POST['activity_marks'][$index];
            $internal_test_marks = $_POST['internal_test_marks'][$index];

            // Insert or Update in temp table
            $ciaObj->saveUGLabTempMarks($studentId, $sub_id, $assessmentNumber, $activity_marks, $internal_test_marks);
        }
        $_SESSION["succ"] = "Student Continuous Lab Internal Assessment Successfully Saved Termporarily";
        
    } elseif ($submitAction == 'submit') {

        $isValid = true;
        // VALIDATE marks for each student
        foreach ($_POST['student_id'] as $index => $studentId) {
            $activity_marks = $_POST['activity_marks'][$index];
            $internal_test_marks = $_POST['internal_test_marks'][$index];

            // Validate marks (ensure they are within the allowed range)
            if ($activity_marks > $maxActivity || $activity_marks < 0 || $internal_test_marks > $maxInternalTest || $internal_test_marks < 0 || !is_numeric($activity_marks) || !is_numeric($internal_test_marks)) {
                $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
                $isValid = false;
                echo $activity_marks." - ".$internal_test_marks." - ";
                break;
            }
        }

        if ($isValid) {
            // Save marks for each student
            foreach ($_POST['student_id'] as $index => $studentId) {
                $activity_marks = $_POST['activity_marks'][$index];
                $internal_test_marks = $_POST['internal_test_marks'][$index];

                $ciaObj->addUGLabInternalAssessmentMarks($studentId, $sub_id, $assessmentNumber, $activity_marks, $internal_test_marks, $_SESSION['facid']);

                // Remove temporary marks
                $ciaObj->deleteUGLabTempMarks($studentId, $sub_id, $assessmentNumber);

            }
            $_SESSION["succ"] = "Student Continuous Internal Assessment Successfully Added";
        }
    }
    // Redirect back to facciamarks.php after saving
    header("Location: facciamarks.php");
    exit();
}

// Get subject details and student list
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList = $facultyObj->getMappedStudents($sub_id);

    // Fetch temporary marks if available
    $tempMarks = $ciaObj->getUGLabTempMarks($sub_id, $assessmentNumber);
    // Prefill form with temp marks if available
    foreach ($studentList as $key => $student) {
        if (!empty($tempMarks[$student['id']])) {
            $studentList[$key]['activity_marks'] = $tempMarks[$student['id']]['activity_marks'];
            $studentList[$key]['internal_test_marks'] = $tempMarks[$student['id']]['internal_test_marks'];
        }
    }

    // Generate new secret code
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
    // Handle invalid request (e.g., redirect to an error page)
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
                    Adding Continuous Internal Assessment (Lab) Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="facadddticiamarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.<br />Student Name</th>
                                    <th>Day-to-Day / Activity (Max <?= $maxActivity ?>)</th>
                                    <th>Internal Test (Max <?= $maxInternalTest ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $key => $student) : ?>
                                    <tr>
                                        <td><?php echo $student['username']; ?><br /><?php echo $student['name']; ?><input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>"></td>
                                        <td><input type="text" name="activity_marks[]" class="form-control" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['activity_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> /></td>
                                        <td><input type="text" name="internal_test_marks[]" class="form-control" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['internal_test_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> /></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="submit_action" value="submit" class="pull-left btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Validate & Submit Marks</button>
                            <button type="submit" name="submit_action" value="save" class="text-end btn btn-secondary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Save without Submitting</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <p class="text-muted">
                        <strong>Note:</strong>
                        <br>
                        The <strong>"Submit"</strong> button will finalize and lock your marks submission.
                        <br>
                        The <strong>"Save without Submitting"</strong> button will save your progress temporarily and you can continue later.
                    </p>
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
                const daytodayInputs = document.querySelectorAll('input[name="activity_marks[]"]');
                const internaltestInputs = document.querySelectorAll('input[name="internal_test_marks[]"]');

                let isValid = true;
                let firstInvalidInput = null; // To store the first invalid input

                const maxAct = <?= json_encode($maxActivity) ?>;
                const maxInt = <?= json_encode($maxInternalTest) ?>;

                daytodayInputs.forEach((input, index) => {
                    const activity_marks = parseFloat(input.value);
                    const internal_test_marks = parseFloat(internaltestInputs[index].value);

                    // Validate if the inputs are filled and within the specified ranges
                    if (
                        isNaN(activity_marks) || activity_marks > maxAct || activity_marks < 0 ||
                        isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0
                    ) {
                        isValid = false;

                        // Identify the first invalid input and focus on it
                        if (!firstInvalidInput) {
                            if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                                firstInvalidInput = internaltestInputs[index];
                            } else if (isNaN(activity_marks) || activity_marks > maxAct || activity_marks < 0) {
                                firstInvalidInput = input;
                            }
                        }

                        // Apply red border to invalid fields
                        if (isNaN(activity_marks) || activity_marks > maxAct || activity_marks < 0) {
                            input.style.border = "2px solid red"; // Highlight invalid day_to_day field
                        }else{
                            input.style.border = "";
                        }
                        if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                            internaltestInputs[index].style.border = "2px solid red"; // Highlight invalid internal_test field
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
                    alert("Please enter valid marks:\n* Day-To-Day: 0-" + maxAct + "\n* Internal-Test: 0-" + maxInt);

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
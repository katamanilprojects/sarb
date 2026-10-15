<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'] && !empty($_POST['submit_action'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];

    $isValid = true;
    // VALIDATE marks for each student
    foreach ($_POST['student_id'] as $index => $studentId) {
        $marks = $_POST['marks'][$index];

        // Validate marks (ensure they are within the allowed range)
        if (!is_numeric($marks) || ($assessmentNumber==1 && $marks > 30) || ($assessmentNumber==2 && $marks > 30) || ($assessmentNumber==3 && $marks > 10) || ($assessmentNumber==11 && $marks > 40)) {
            $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
            $isValid = false;
            break;
        }
    }

    if ($isValid) {
        // Save marks for each student
        foreach ($_POST['student_id'] as $index => $studentId) {
            $marksId = $_POST['marks_id'][$index];
            $marks = $_POST['marks'][$index];

            $ciaObj->updatePGInternalAssessmentMarks($marksId, $marks);
        }
        $_SESSION["succ"] = "Student Assessment Successfully Updated";
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
    $studentList1 = $facultyObj->getMappedStudents($sub_id);

    // Fetch marks for each student
    $studentList = [];
    foreach ($studentList1 as $student) {
        $marks = $facultyObj->getStPGInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
        $student['marks'] = !empty($marks) ? $marks[0] : null;
        $studentList[] = $student; // Add the modified student data to the new array
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
                    <?php
                    if (!empty($assessmentNumber) && $assessmentNumber == 3) {
                        echo 'Editing Final Assignment Marks';
                    }elseif(!empty($assessmentNumber) && $assessmentNumber==11){
                        echo 'Editing Final Lab Internal Marks';                        
                    } else {
                        echo 'Editing Internal Assessment Marks - ' . $assessmentNumber;
                    }
                    ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="faceditpgciamarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.<br />Student Name</th>
                                    <th>Marks <?php if (!empty($assessmentNumber) && $assessmentNumber == 3) {
                                                    echo '(Max. 10)';
                                                } elseif(!empty($assessmentNumber) && $assessmentNumber==11){ 
                                                    echo '(Max. 40)'; 
                                                }else {
                                                    echo '(Max. 30)';
                                                } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $key => $student) :
                                    $stmarks = rtrim(rtrim(number_format($student['marks']['marks'], 1), '0'), '.');
                                    if (empty($stmarks)) {
                                        $stmarks = 0;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $student['username']; ?><br />
                                            <?php echo $student['name']; ?>
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="marks_id[]" value="<?php echo $student['marks'] ? $student['marks']['id'] : ''; ?>">
                                        </td>
                                        <td><input type="text" name="marks[]" class="form-control" maxlength="5" value="<?php echo $stmarks; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : 'required'; ?> /></td>
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
                const marksInputs = document.querySelectorAll('input[name="marks[]"]');
                <?php
                if (!empty($assessmentNumber) && $assessmentNumber == 3) {
                    echo 'const maxMarks = 10;';
                }elseif(!empty($assessmentNumber) && $assessmentNumber==11){
                    echo 'const maxMarks = 40;';
                } else {
                    echo 'const maxMarks = 30;';
                }
                ?>

                let isValid = true;
                let firstInvalidInput = null; // To store the first invalid input

                marksInputs.forEach((input, index) => {
                    const marksMarks = parseFloat(input.value);

                    // Validate if the inputs are filled and within the specified ranges
                    if (
                        isNaN(marksMarks) || marksMarks > maxMarks || marksMarks < 0
                    ) {
                        isValid = false;

                        // Identify the first invalid input and focus on it
                        if (!firstInvalidInput) {
                            if (isNaN(marksMarks) || marksMarks > maxMarks || marksMarks < 0) {
                                firstInvalidInput = input;
                            }
                        }

                        // Apply red border to invalid fields
                        if (isNaN(marksMarks) || marksMarks > maxMarks || marksMarks < 0) {
                            input.style.border = "2px solid red"; // Highlight invalid marks field
                        } else {
                            input.style.border = "";
                        }
                    } else {
                        // Reset the border if valid
                        input.style.border = "";
                    }
                });

                if (!isValid) {
                    e.preventDefault(); // Prevent form submission if validation fails
                    alert("Please enter valid marks:\n* Marks: 0-" + maxMarks);

                    // Focus on the first invalid input and set the cursor there
                    if (firstInvalidInput) {
                        firstInvalidInput.focus(); // Focus on the first invalid input
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
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once "faculty.class.php";
require_once "cia.class.php";

$page_title = "Enter Student Marks";

if (!isset($_GET['sub_id']) || !isset($_GET['assessment_number']) || !isset($_GET['component_id']) || !isset($_GET['component_type'])) {
    $_SESSION['err'] = "Invalid request.";
    header("Location: faceditoptions.php");
    exit;
}

$facultyObj = new Faculty();
$ciaObj = new CIA();

$sub_id = intval($_GET['sub_id']);
$assessment_number = intval($_GET['assessment_number']);
$component_id = intval($_GET['component_id']);
$component_type = htmlspecialchars($_GET['component_type']);
$isSubmitted = !empty($ciaObj->isInternalAssessmentAdded($sub_id, $assessment_number));

// Condensed marks limits
$CONDENSED_SUBJECTIVE = 15;
$CONDENSED_OBJECTIVE = 10;
$CONDENSED_ASSIGNMENT = 5;

$is_edit = isset($_GET['edit']) && $_GET['edit'] == 1 ? true : false;

if (isset($_POST['save_marks'])) {
    foreach ($_POST['marks'] as $stu_id => $question_marks) {
        foreach ($question_marks as $q_id => $marks) {
            $ciaObj->addOrUpdateStudentMarks($stu_id, $q_id, floatval($marks));
        }
    }
    $_SESSION['succ'] = "Marks updated successfully.";
    header("Location: facviewmarks.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type");
    exit;
}

if (isset($_POST['delete_marks'])) {
    if ($isSubmitted) {
        $_SESSION['err'] = "Marks cannot be deleted after final submission.";
    } else {
        $deleteResult = $ciaObj->deleteStudentMarksByComponent($component_id);
        if (!empty($deleteResult['status'])) {
            $_SESSION['succ'] = "Student marks deleted successfully for the selected component.";
            header("Location: facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number");
            exit;
        } else {
            $_SESSION['err'] = "Failed to delete student marks for the selected component.";
        }
    }
}

// Get list of students enrolled in the subject
$students = $facultyObj->getMappedStudents($sub_id);

// Get list of questions for the assessment component
$questions = $ciaObj->getQuestionsByComponent($component_id);

// Calculate total full marks for this component
$totalFullMarks = 0;
foreach ($questions as $question) {
    $totalFullMarks += floatval($question['marks']);
}

$existingMarks = [];
foreach ($students as $student) {
    $studentMarks = [];
    foreach ($questions as $question) {
        $marks = $ciaObj->checkStudentMarks($student['id'], $question['id']);
        $studentMarks[$question['id']] = $marks ? $marks : 0; // Default to 0 if no marks found
    }
    $existingMarks[$student['id']] = $studentMarks;
}

if (!is_array($existingMarks) || count($existingMarks) <= 0) {
    echo "<script>window.location.href='faceditoptions.php';</script>";
    exit();
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);

require_once "facheader.php";

?>

<div class="container">

    <?php if (!empty($_SESSION["succ"])) { ?>
        <div class="alert alert-success"><?php echo $_SESSION["succ"];
                                            unset($_SESSION["succ"]); ?></div>
    <?php } ?>
    <?php if (!empty($_SESSION["err"])) { ?>
        <div class="alert alert-danger"><?php echo $_SESSION["err"];
                                        unset($_SESSION["err"]); ?></div>
    <?php } ?>
    <div class="card">
        <div class="card-header">
            <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
        </div>
        <div class="card-header"> CIA - <?php echo $assessment_number; ?> <?php echo "(" . $component_type . ")"; ?> </div>

        <div class="card-header">
            Student Marks
        </div>
        <div class="card-body">
            <style>
                .table-scrollable {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                td input[type="number"] {
                    min-width: 80px;
                    text-align: center;
                }
                th:first-child, td:first-child,
                th:nth-child(2), td:nth-child(2) {
                    position: sticky;
                    left: 0;
                    background-color: white;
                    z-index: 2;
                }
                th:nth-child(2), td:nth-child(2) {
                    left: 80px;
                    z-index: 1;
                }
            </style>
            <?php if ($is_edit) { ?>
                <form method="post" action="">
                <?php } ?>
                <div class="table-scrollable">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Adm. No.</th>
                                <th>Student Name</th>
                                <?php
                                $assess_qno = '';
                                if ($component_type == "Assignment") {
                                    $seq_no = $ciaObj->getSequenceNoByAssessCompID($component_id);
                                    $assess_qno = $assessment_number . "." . $seq_no;
                                }
                                if ($component_type == "Assignment" && count($questions) == 1 && !empty($assess_qno)) {
                                    echo '<th>' . $assess_qno . '<br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 1, '.', ''), '0'), '.') . ')</th>';
                                } elseif ($component_type == "Day-to-Day" && count($questions) == 1) {
                                    $date_object = DateTime::createFromFormat('Y-m-d', $question['question_label']);
                                    $dated = $date_object->format('d-m-Y');
                                    echo '<th>' . $dated . '<br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 1, '.', ''), '0'), '.') . ')</th>';
                                } else {
                                    foreach ($questions as $question) {
                                        echo '<th>Q' . htmlspecialchars($question['question_label']) . ' <br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 1, '.', ''), '0'), '.') . ')</th>';
                                    }
                                }
                                ?>
                                <th>Total</th>
                                <th>Condensed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student) { ?>

                                <tr>
                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <?php
                                    $st_marks = $ciaObj->getStudentMarksByStId($student['id']);
                                    $studentTotal = 0;
                                    $subjectiveMarksArray = [];

                                    foreach ($questions as $question) { 
                                        $questionLabel = $question['question_label'];
                                        $questionMarks = floatval($st_marks[$question['id']]);
                                        
                                        if ($component_type == 'Subjective') {
                                            // Extract main question number (e.g., "1" from "1a", "1b")
                                            $mainQuestionNum = preg_replace('/[^0-9]/', '', $questionLabel);
                                            if (!isset($subjectiveMarksArray[$mainQuestionNum])) {
                                                $subjectiveMarksArray[$mainQuestionNum] = 0;
                                            }
                                            // Sum all parts of the same question
                                            $subjectiveMarksArray[$mainQuestionNum] += $questionMarks;
                                        } else {
                                            $studentTotal += $questionMarks;
                                        }
                                        ?>
                                        <td>
                                            <?php
                                            if ($is_edit) {
                                            ?>
                                                <input type="number" name="marks[<?php echo $student['id']; ?>][<?php echo $question['id']; ?>]" value="<?php echo rtrim(rtrim(number_format(floatval($st_marks[$question['id']]), 1, '.', ''), '0'), '.') ?? 0; ?>" step="0.5" min="0" max="<?php echo floatval($question['marks']); ?>" class="form-control" />
                                            <?php
                                            } else {
                                                echo rtrim(rtrim(number_format(floatval($st_marks[$question['id']]), 1, '.', ''), '0'), '.');
                                            }
                                            ?>
                                        </td>
                                    <?php } 
                                    
                                    // Calculate total for subjective using either-or logic
                                    if ($component_type == 'Subjective') {
                                        $eitherOrGroups = [["1", "2"], ["3", "4"], ["5", "6"]];
                                        foreach ($eitherOrGroups as $group) {
                                            $maxMarks = 0;
                                            foreach ($group as $qNum) {
                                                if (isset($subjectiveMarksArray[$qNum])) {
                                                    $maxMarks = max($maxMarks, $subjectiveMarksArray[$qNum]);
                                                }
                                            }
                                            $studentTotal += $maxMarks;
                                        }
                                    }
                                    ?>
                                    <td style="text-align: center;">
                                        <strong><?php echo rtrim(rtrim(number_format($studentTotal, 1, '.', ''), '0'), '.'); ?></strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php
                                        $condensedMax = 0;
                                        $fullMarksForCondensed = 30; // Default for subjective
                                        
                                        if ($component_type == 'Subjective') {
                                            $condensedMax = $CONDENSED_SUBJECTIVE;
                                            $fullMarksForCondensed = 30; // 3 pairs of 10 marks each
                                        } elseif ($component_type == 'Objective') {
                                            $condensedMax = $CONDENSED_OBJECTIVE;
                                            $fullMarksForCondensed = $totalFullMarks;
                                        } elseif ($component_type == 'Assignment') {
                                            $condensedMax = $CONDENSED_ASSIGNMENT;
                                            $fullMarksForCondensed = $totalFullMarks;
                                        }
                                        $condensedTotal = $fullMarksForCondensed > 0 ? min(($studentTotal / $fullMarksForCondensed) * $condensedMax, $condensedMax) : 0;
                                        echo round($condensedTotal, 1);
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($is_edit) { ?>
                    <button type="submit" name="save_marks" class="btn btn-success float-end mt-2">Save Marks</button>
                </form>
            <?php } ?>
        </div>
        <div class="card-footer">
            <?php echo "<a href='facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number' class='float-end btn btn-success'>CIA Components</a>"; ?>
            <?php if (!$is_edit && !$isSubmitted) { ?>
                <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all student marks for this component?');">
                    <button type="submit" name="delete_marks" class="btn btn-danger float-start me-2">Delete Marks</button>
                </form>
                <a href="facviewmarks.php?sub_id=<?php echo $sub_id; ?>&assessment_number=<?php echo $assessment_number; ?>&component_id=<?php echo $component_id; ?>&component_type=<?php echo $component_type; ?>&edit=1" class="btn btn-primary float-start">Edit Marks</a>
            <?php } ?>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            if (form) {
                form.querySelectorAll('input[name^="marks"]').forEach(function(input) {
                    let originalValue = input.value;
                    input.addEventListener('input', function() {
                        if (input.value != originalValue) {
                            input.classList.add('table-warning');
                        } else {
                            input.classList.remove('table-warning');
                        }
                    });
                });
            }

            form.addEventListener('submit', function(e) {
                let hasError = false;
                <?php foreach ($questions as $question) { ?>
                    let maxMarks_<?php echo $question['id']; ?> = <?php echo floatval($question['marks']); ?>;
                    document.querySelectorAll('input[name^="marks"][name*="[<?php echo $question['id']; ?>]"]').forEach(function(input) {
                        let val = parseFloat(input.value);
                        if (val > maxMarks_<?php echo $question['id']; ?>) {
                            hasError = true;
                            input.classList.add('is-invalid');
                            if (!input.nextElementSibling) {
                                let errorMsg = document.createElement('div');
                                errorMsg.className = 'invalid-feedback';
                                errorMsg.innerText = 'Cannot exceed Max Marks: ' + maxMarks_<?php echo $question['id']; ?>;
                                input.parentNode.appendChild(errorMsg);
                            }
                        } else {
                            input.classList.remove('is-invalid');
                            if (input.nextElementSibling) {
                                input.nextElementSibling.remove();
                            }
                        }
                    });
                <?php } ?>

                if (hasError) {
                    e.preventDefault();
                    alert("Please correct the marks exceeding maximum allowed.");
                }
            });
        }
    });

</script>

<?php require_once "facfooter.php"; ?>
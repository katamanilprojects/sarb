<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

require_once "faculty.class.php";
require_once "cia.class.php";

$page_title = "Edit";

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
$component_type = $_GET['component_type'];

// Get list of students enrolled in the subject
$students = $facultyObj->getMappedStudents($sub_id);

// Get list of questions for the assessment component
$questions = $ciaObj->getQuestionsByComponent($component_id);
// Handle form submission
if (isset($_POST['marks']) && is_array($_POST['marks']) && count($_POST['marks'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    foreach ($_POST['marks'] as $stu_id => $stu_marks) {
        foreach ($stu_marks as $question_id => $marks) {
            $marks_obtained = floatval($marks);
            // Validate marks do not exceed allocated question marks
            $question_details = $ciaObj->getQuestionDetails($question_id);
            if ($marks_obtained > $question_details['marks']) {
                $_SESSION['err'] = "Marks cannot exceed allocated marks for question ID: $question_id.";
                header("Location: faceditoptions.php");
                exit;
            }

            // Insert or update marks
            $ciaObj->addOrUpdateStudentMarks($stu_id, $question_id, $marks_obtained);
        }
    }
    $_SESSION['succ'] = "Marks saved successfully.";
    header("Location: facviewmarks.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type");
    exit;
}

$existingMarks = [];
$marks_present = 0;
foreach ($students as $student) {
    $studentMarks = [];
    foreach ($questions as $question) {
        $marks = $ciaObj->checkStudentMarks($student['id'], $question['id']);
        if ($marks) {
            $marks_present = 1;
        }
        $studentMarks[$question['id']] = $marks ? $marks : 0; // Default to 0 if no marks found
    }
    $existingMarks[$student['id']] = $studentMarks;
}

if ($marks_present == 1 && is_array($existingMarks) && count($existingMarks) > 0) {
    echo "<script>window.location.href='facviewmarks.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
    exit();
}
// Handle CSV Download
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_marks_template.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array_merge(['Student ID'], ['Student Adm. & Name'], array_column($questions, 'question_label')));

    foreach ($students as $student) {
        fputcsv($output, array_merge([$student['id']], [$student['username']." - ".$student['name']], array_fill(0, count($questions), '')));
    }

    fclose($output);
    exit;
}

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($file); // Read the first row (question labels)

        $question_map = [];
        foreach ($questions as $q) {
            $question_map[$q['question_label']] = $q['id'];
        }

        while ($row = fgetcsv($file)) {
            $stu_id = $row[0];
            if (empty($stu_id)) {
                // Skip empty student ID rows (empty rows)
                continue;
            }
            foreach ($row as $index => $marks) {
                if ($index == 0) continue;
                $question_label = $headers[$index];
                if (!isset($question_map[$question_label])) continue;

                $question_id = $question_map[$question_label];
                $marks_obtained = floatval($marks);

                // Validate marks
                $question_details = $ciaObj->getQuestionDetails($question_id);
                if ($marks_obtained > $question_details['marks']) continue;
                $ciaObj->addOrUpdateStudentMarks($stu_id, $question_id, $marks_obtained);
            }
        }
        fclose($file);

        $_SESSION['succ'] = "Marks uploaded successfully.";
        header("Location: facviewmarks.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type");
        exit;
    } else {
        $_SESSION['err'] = "Please upload a valid CSV file.";
    }
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);


require_once "facheader.php";

?>

<div class="container">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
    <div class="card">
        <div class="card-header">
            <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
        </div>
        <div class="card-header"> CIA - <?php echo $assessment_number; ?> <?php echo "(" . $component_type . ")"; ?> </div>
        <div class="card-header">Student Marks</div>
    </div>

    <?php if (!empty($_SESSION["succ"])) { ?>
        <div class="alert alert-success"><?php echo $_SESSION["succ"];
                                            unset($_SESSION["succ"]); ?></div>
    <?php } ?>
    <?php if (!empty($_SESSION["err"])) { ?>
        <div class="alert alert-danger"><?php echo $_SESSION["err"];
                                        unset($_SESSION["err"]); ?></div>
    <?php } ?>

    <br>
    <?php if($component_type!="Day-to-Day"): ?>
    <div class="card">
        <div class="card-header">Download Template, Enter Marks and Upload CSV</div>
        <div class="card-body">
            <a href="facmarksentry.php?sub_id=<?php echo $sub_id; ?>&assessment_number=<?php echo $assessment_number; ?>&component_id=<?php echo $component_id; ?>&component_type=<?php echo $component_type; ?>&download_csv=1" class="btn btn-success">Download CSV Template</a>

            <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" name="upload_csv" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Upload CSV</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <br>
    <div class="card" style="overflow-x:auto;">
        <div class="card-header">
            <?php
            if($component_type=="Day-to-Day"){
                echo "Add/Enter Date-wise Marks Obtained for each Student";
            }else{
                echo "Add/Enter Question-wise Marks Obtained for each Student";
            }
            ?>
        </div>
        <div class="card-body">
            <form method="post">
                <style>
                    .table-scrollable {
                        overflow-x: auto;
                        /* Ensure horizontal scrolling */
                        -webkit-overflow-scrolling: touch;
                        /* Smooth scrolling on iOS */
                    }
                </style>
                <table class="table table-bordered table-scrollable">
                    <thead>
                        <tr>
                            <th style="position: sticky; left: 0; background-color: white;">Adm. No.</th>
                            <th>Student Name</th>
                            <?php
                            $assess_qno = '';
                            if ($component_type == "Assignment") {
                                $seq_no = $ciaObj->getSequenceNoByAssessCompID($component_id);
                                $assess_qno = $assessment_number . "." . $seq_no;
                            }
                            if ($component_type == "Assignment" && count($questions) == 1 && !empty($assess_qno)) {
                                echo '<th>' . $assess_qno . '<br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 2, '.', ''), '0'), '.') . ')</th>';
                            } elseif ($component_type == "Day-to-Day" && count($questions) == 1) {
                                $date_object = DateTime::createFromFormat('Y-m-d', $question['question_label']);
                                $dated = $date_object->format('d-m-Y');
                                echo '<th>' . $dated . '<br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 1, '.', ''), '0'), '.') . ')</th>';
                            } else {
                                foreach ($questions as $question) {
                                    echo '<th>Q' . htmlspecialchars($question['question_label']) . ' <br>(' . rtrim(rtrim(number_format(floatval($question['marks']), 2, '.', ''), '0'), '.') . ')</th>';
                                }
                            }

                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student) { ?>
                            <tr>
                                <td style="position: sticky; left: 0; background-color: white;"><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <?php foreach ($questions as $question) { ?>
                                    <td style='min-width: 80px;'>
                                        <input type="number" name="marks[<?php echo $student['id']; ?>][<?php echo $question['id']; ?>]"
                                            min="0" max="<?php echo $question['marks']; ?>"
                                            class="form-control" value="" step="0.5" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : 'required'; ?>>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <input type="submit" name="submit_marks" class="btn btn-primary" value="Save Marks" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?> />
            </form>
        </div>
    </div>

</div>

<?php require_once "facfooter.php"; ?>
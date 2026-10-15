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

// Get list of students enrolled in the subject
$students = $facultyObj->getMappedStudents($sub_id);

// Get list of questions for the assessment component
$questions = $ciaObj->getQuestionsByComponent($component_id);

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


// CSV Generation START
ob_start(); // Start output buffering

$csv_content .= "Subject Name,\"" . htmlspecialchars($subjectDetails['data']['sub_fullname']) . "\"\n";
$csv_content .= "Subject Code,\"" . htmlspecialchars($subjectDetails['data']['subcode']) . "\"\n";
$csv_content .= "CIA Number," . $assessment_number . "\n";
$csv_content .= "Component Type,\"" . htmlspecialchars($component_type) . "\"\n";
$csv_content .= "\n"; // Add an empty line for spacing

echo $csv_content;

// Question Label
echo ",Ques.,";
foreach ($questions as $question) {
    echo "Q" . htmlspecialchars($question['question_label']);
    if ($question !== end($questions)) {
        echo ",";
    }
}
echo "\n";

// Question Marks
echo ",Marks,";
foreach ($questions as $question) {

    echo "" . rtrim(rtrim(number_format(floatval($question['marks']), 2, '.', ''), '0'), '.');
    if ($question !== end($questions)) {
        echo ",";
    }
}
echo "\n";
// Question Type
echo ",Type,";
foreach ($questions as $question) {
    echo "" . htmlspecialchars($question['question_type']);
    if ($question !== end($questions)) {
        echo ",";
    }
}
echo "\n";
// Question BTL
echo ",BTL,";
foreach ($questions as $question) {
    echo "" . htmlspecialchars($question['blooms_level_id']);
    if ($question !== end($questions)) {
        echo ",";
    }
}
echo "\n";
// Question CO
echo ",CO,";
foreach ($questions as $question) {
    $res_co = $ciaObj->getCOsByQuestion($question['id']);
    echo implode('|',$res_co);
    if ($question !== end($questions)) {
        echo ",";
    }
}
echo "\n";

echo "Adm. No.,Student Name";
echo "\n";
// CSV Data
foreach ($students as $student) {
    $st_marks = $ciaObj->getStudentMarksByStId($student['id']);
    echo "\"" . htmlspecialchars($student['username']) . "\",";
    echo "\"" . htmlspecialchars($student['name']) . "\",";
        foreach ($questions as $question) {

            if (isset($st_marks[$question['id']])) {
                echo rtrim(rtrim(number_format(floatval($st_marks[$question['id']]), 2, '.', ''), '0'), '.');
            }
            echo ',';
        }
        echo "\n";
}

$csv_content = ob_get_clean(); // Get the buffered content and clear the buffer

$filename = '_';
if(!empty($subjectDetails['data']['subcode'])){
    $filename .= $subjectDetails['data']['subcode'].'_CIA'.$assessment_number;
}
$filename .= '_'.date('dmYHis').'_';

// Set headers for CSV download -  This makes the page ONLY download the CSV
header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="st_marks'.$filename.'.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo $csv_content;
exit; // VERY IMPORTANT: Stop further script execution


// The rest of the HTML page content is now commented out, as the CSV download happens first.
/*
require_once "facheader.php";

?>

<div class="container">
    // ... (Your HTML content for displaying the table) ...
</div>

<?php require_once "facfooter.php"; 
*/

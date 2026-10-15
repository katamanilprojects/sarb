<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Condensed Marks";

require_once("facheader.php");
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

// Validate GET parameters
if (empty($_GET['sub_id']) || empty($_GET['assessment_number'])) {
    $_SESSION['err'] = "Invalid request. Missing required parameters.";
    header("Location: facciamarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$assessment_number = intval($_GET['assessment_number']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$studentList = $facultyObj->getMappedStudents($sub_id);

$regulationCode = $ciaObj->getRegulationForSubject($sub_id);

$FULL_SUBJECTIVE = 30;
$FULL_D2D = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Day-to-Day");
$FULL_INTERNAL = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Internal Exam");

// Condensed marks limits dynamically retrieved from academic settings
$CONDENSED_D2D = (float)$ciaObj->getAcademicSetting('lab_cia_day_to_day_marks', $regulationCode, $sub_id, 15.0);
$CONDENSED_INT = (float)$ciaObj->getAcademicSetting('lab_cia_internal_test_marks', $regulationCode, $sub_id, 15.0);

$condensedMarks = [];
foreach ($studentList as $student) {
    $studentId = $student['id'];

    // Retrieve subjective marks
    $subjectiveMarks = $ciaObj->getSubjectiveQuestionMarks($studentId, $sub_id, $assessment_number);

    // Process highest marks from either-or pairs
    $subjectivePairs = [];
    $eitherOrGroups = [
        ["1", "2"],
        ["3", "4"],
        ["5", "6"]
    ];

    foreach ($eitherOrGroups as $group) {
        $maxMarks = 0;
        foreach ($group as $qNum) {
            if (isset($subjectiveMarks[$qNum])) {
                $maxMarks = max($maxMarks, $subjectiveMarks[$qNum]);
            }
        }
        $subjectivePairs[] = $maxMarks;
    }

    $totalSubjective = array_sum($subjectivePairs);

    // Retrieve marks by types
    $totalD2D = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Day-to-Day");
    $totalInternal = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Internal Exam");

    $scaledD2D = min(($totalD2D / max(1, $FULL_D2D)) * $CONDENSED_D2D, $CONDENSED_D2D);
    $scaledInternal = min(($totalInternal / max(1, $FULL_INTERNAL)) * $CONDENSED_INT, $CONDENSED_INT);


    $condensedMarks[$studentId] = [
        'name' => $student['name'],
        'username' => $student['username'],
        'day_to_day' => round($scaledD2D, 1),
        'internal_exam' => round($scaledInternal, 1),
        'total' => round($scaledD2D + $scaledInternal, 0)
    ];
}
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Condensed Continuous Internal Assessment Marks - <?= $assessment_number; ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname']); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode']); ?>)
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Adm. No.</th>
                                <th>Student Name</th>
                                <th>Day-to-Day (Max <?php echo $CONDENSED_D2D; ?>)</th>
                                <th>Internal Exam (Max <?php echo $CONDENSED_INT; ?>)</th>
                                <th>Total (Max <?php echo ($CONDENSED_D2D + $CONDENSED_INT); ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($condensedMarks as $marks) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($marks['username']); ?></td>
                                    <td><?= htmlspecialchars($marks['name']); ?></td>
                                    <td><?= $marks['day_to_day']; ?></td>
                                    <td><?= $marks['internal_exam']; ?></td>
                                    <td><strong><?= $marks['total']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <?php if(empty($ciaObj->isInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                    <form method="POST" action="facaddcialabmarks.php">
                        <input type="hidden" name="sub_id" value="<?= $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?= $assessment_number; ?>">
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">

                        <?php foreach ($condensedMarks as $studentId => $marks) : ?>
                            <input type="hidden" name="student_id[]" value="<?= $studentId; ?>">
                            <input type="hidden" name="day_to_day_marks[]" value="<?= $marks['day_to_day']; ?>">
                            <input type="hidden" name="internal_test_marks[]" value="<?= $marks['internal_exam']; ?>">
                        <?php endforeach; ?>

                        <button type="submit" name="submit_action" value="submit" class="btn btn-success">Submit Final Marks</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="facciamarks.php?sub_id=<?= $sub_id; ?>&assessment_number=<?= $assessment_number; ?>" class="btn btn-primary">Back to Marks</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>
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
$FULL_OBJECTIVE = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Objective");
if ($FULL_OBJECTIVE <= 0) {
    $FULL_OBJECTIVE = (float)$ciaObj->getAcademicSetting('theory_mid_objective_marks', $regulationCode, $sub_id, 10.0);
}
$FULL_ASSIGNMENT = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Assignment");
if ($FULL_ASSIGNMENT <= 0) {
    $FULL_ASSIGNMENT = (float)$ciaObj->getAcademicSetting('theory_assignment_marks', $regulationCode, $sub_id, 5.0);
}

// Condensed marks limits dynamically loaded from centralized academic settings
$CONDENSED_SUBJECTIVE = (float)$ciaObj->getAcademicSetting('theory_mid_subjective_condensed', $regulationCode, $sub_id, 15.0);
$CONDENSED_OBJECTIVE = (float)$ciaObj->getAcademicSetting('theory_mid_objective_marks', $regulationCode, $sub_id, 10.0);
$CONDENSED_ASSIGNMENT = (float)$ciaObj->getAcademicSetting('theory_assignment_marks', $regulationCode, $sub_id, 5.0);
$TOTAL_CIA_MARKS = (float)$ciaObj->getAcademicSetting('theory_cia_total_marks', $regulationCode, $sub_id, 30.0);

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

    // Retrieve objective marks
    $totalObjective = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Objective");

    // Retrieve assignment marks
    $totalAssignment = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Assignment");

    // Use dynamic full marks instead of hardcoded values
    $subjectiveTotal = min(($totalSubjective / max(1, $FULL_SUBJECTIVE)) * $CONDENSED_SUBJECTIVE, $CONDENSED_SUBJECTIVE);
    $objectiveTotal = min(($totalObjective / max(1, $FULL_OBJECTIVE)) * $CONDENSED_OBJECTIVE, $CONDENSED_OBJECTIVE);
    $assignmentTotal = min(($totalAssignment / max(1, $FULL_ASSIGNMENT)) * $CONDENSED_ASSIGNMENT, $CONDENSED_ASSIGNMENT);


    $condensedMarks[$studentId] = [
        'name' => $student['name'],
        'username' => $student['username'],
        'subjective' => round($subjectiveTotal, 1),
        'objective' => round($objectiveTotal, 1),
        'assignment' => round($assignmentTotal, 1),
        'total' => round($subjectiveTotal + $objectiveTotal + $assignmentTotal, 1)
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
                    <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Adm. No.</th>
                                <th>Student Name</th>
                                <th>Subjective (Max <?php echo $CONDENSED_SUBJECTIVE; ?>)</th>
                                <th>Objective (Max <?php echo $CONDENSED_OBJECTIVE; ?>)</th>
                                <th>Assignments (Max <?php echo $CONDENSED_ASSIGNMENT; ?>)</th>
                                <th>Total (Max <?php echo $CONDENSED_SUBJECTIVE + $CONDENSED_OBJECTIVE + $CONDENSED_ASSIGNMENT; ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($condensedMarks as $marks) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($marks['username']); ?></td>
                                    <td><?= htmlspecialchars($marks['name']); ?></td>
                                    <td><?= $marks['subjective']; ?></td>
                                    <td><?= $marks['objective']; ?></td>
                                    <td><?= $marks['assignment']; ?></td>
                                    <td><strong><?= $marks['total']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>
                        <?php if(empty($ciaObj->isInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                        <form method="POST" action="facaddciamarks.php" class="d-inline m-0">
                            <input type="hidden" name="sub_id" value="<?= $sub_id; ?>">
                            <input type="hidden" name="assessment_number" value="<?= $assessment_number; ?>">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">

                            <?php foreach ($condensedMarks as $studentId => $marks) : ?>
                                <input type="hidden" name="student_id[]" value="<?= $studentId; ?>">
                                <input type="hidden" name="subjective_marks[]" value="<?= $marks['subjective']; ?>">
                                <input type="hidden" name="objective_marks[]" value="<?= $marks['objective']; ?>">
                                <input type="hidden" name="assignment_marks[]" value="<?= $marks['assignment']; ?>">
                            <?php endforeach; ?>

                            <button type="submit" name="submit_action" value="submit" class="btn btn-success">Submit Final Marks</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <a href="facciamarks.php?sub_id=<?= $sub_id; ?>&assessment_number=<?= $assessment_number; ?>" class="btn btn-primary">Back to Marks</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>
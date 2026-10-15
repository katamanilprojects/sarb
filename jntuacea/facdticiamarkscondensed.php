<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Condensed DTI Marks";

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

// Dynamically retrieve condensed marks limits from centralized academic settings
$CONDENSED_ACTIVITY = (float)$ciaObj->getAcademicSetting('lab_cia_day_to_day_marks', $regulationCode, $sub_id, 15.0);
$CONDENSED_INTERNAL = (float)$ciaObj->getAcademicSetting('lab_cia_internal_test_marks', $regulationCode, $sub_id, 15.0);

// Calculate total possible marks for scaling
$FULL_ACTIVITY = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Activity");
$FULL_SUBJECTIVE1 = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Subjective-1");
$FULL_OBJECTIVE1 = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Objective-1");
$FULL_SUBJECTIVE2 = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Subjective-2");
$FULL_OBJECTIVE2 = $ciaObj->getTotalMarksByType($sub_id, $assessment_number, "Objective-2");

// Total possible marks for the 'Internal' condensed component (sum of the four types)
$FULL_INTERNAL_COMPONENTS = $FULL_SUBJECTIVE1 + $FULL_OBJECTIVE1 + $FULL_SUBJECTIVE2 + $FULL_OBJECTIVE2;


$condensedMarks = [];
if (!empty($studentList)) {
    foreach ($studentList as $student) {
        $studentId = $student['id'];

        // Retrieve total marks for each component type for the current student
        $totalActivity = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Activity");
        $totalSubjective1 = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Subjective-1");
        $totalObjective1 = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Objective-1");
        $totalSubjective2 = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Subjective-2");
        $totalObjective2 = $ciaObj->getMarksByType($studentId, $sub_id, $assessment_number, "Objective-2");

        // Calculate the total marks for the 'Internal' condensed component for the student
        $totalInternalComponents = $totalSubjective1 + $totalObjective1 + $totalSubjective2 + $totalObjective2;

        // Scale the marks to the condensed limits (handle division by zero)
        $scaledActivity = min(($totalActivity / max(1, $FULL_ACTIVITY)) * $CONDENSED_ACTIVITY, $CONDENSED_ACTIVITY);
        $scaledInternal = min(($totalInternalComponents / max(1, $FULL_INTERNAL_COMPONENTS)) * $CONDENSED_INTERNAL, $CONDENSED_INTERNAL);

        // Store the condensed marks for the student
        $condensedMarks[$studentId] = [
            'name' => $student['name'],
            'username' => $student['username'],
            'activity' => round($scaledActivity, 1),
            'internal' => round($scaledInternal, 1),
            'total' => round($scaledActivity + $scaledInternal, 0) // Total out of 30
        ];
    }
}

// Generate new secret code for form submission
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Condensed Continuous Internal Assessment Marks (DTI) - <?= $assessment_number; ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname']); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode']); ?>)
                </div>
                <div class="card-body">
                    <?php if (empty($condensedMarks)): ?>
                        <div class="alert alert-info">No student data found or no marks entered for the components.</div>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.</th>
                                    <th>Student Name</th>
                                    <th>Activity (Max <?php echo $CONDENSED_ACTIVITY; ?>)</th>
                                    <th>Internal (Max <?php echo $CONDENSED_INTERNAL; ?>)</th>
                                    <th>Total (Max <?php echo ($CONDENSED_ACTIVITY + $CONDENSED_INTERNAL); ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($condensedMarks as $marks) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($marks['username']); ?></td>
                                        <td><?= htmlspecialchars($marks['name']); ?></td>
                                        <td><?= $marks['activity']; ?></td>
                                        <td><?= $marks['internal']; ?></td>
                                        <td><strong><?= $marks['total']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <?php
                    // Check if the condensed marks for this assessment have already been submitted (using the lab/dti check function)
                    if(empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                        <form method="POST" action="facadddticiamarks.php">
                            <input type="hidden" name="sub_id" value="<?= $sub_id; ?>">
                            <input type="hidden" name="assessment_number" value="<?= $assessment_number; ?>">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">

                            <?php foreach ($condensedMarks as $studentId => $marks) : ?>
                                <input type="hidden" name="student_id[]" value="<?= $studentId; ?>">
                                <input type="hidden" name="activity_marks[]" value="<?= $marks['activity']; ?>">
                                <input type="hidden" name="internal_test_marks[]" value="<?= $marks['internal']; ?>">
                                <?php endforeach; ?>

                            <button type="submit" name="submit_action" value="submit" class="btn btn-success">Submit Final Marks</button>
                        </form>
                    <?php else: ?>
                         <div class="alert alert-info">Condensed marks for this assessment have already been submitted.</div>
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

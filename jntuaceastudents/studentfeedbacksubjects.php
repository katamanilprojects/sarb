<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_FEEDBACK');
require_once("services/StudentRepository.php");
require_once("services/StudentAssessmentService.php");
require_once("services/StudentFeedbackService.php");

$studentRepo = new StudentRepository();
$assessmentService = new StudentAssessmentService();
$feedbackService = new StudentFeedbackService();

// Restore or persist active class session context
if (!empty($_POST['student_id']) && !empty($_POST['class_id'])) {
    $_SESSION['active_feedback_class'] = [
        'student_id' => (int)$_POST['student_id'],
        'class_id'   => (int)$_POST['class_id'],
        'classname'  => $_POST['classname'] ?? '',
        'acad_year'  => $_POST['acad_year'] ?? ''
    ];
}

if (empty($_SESSION['active_feedback_class'])) {
    header('Location: ./studenthome.php');
    exit();
}

$context    = $_SESSION['active_feedback_class'];
$student_id = $context['student_id'];
$class_id   = $context['class_id'];
$classname  = $context['classname'];
$acad_year  = $context['acad_year'];

// Security validation
if (empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership($student_id, $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}
if (!$studentRepo->verifyClassEnrollment($student_id, $class_id)) {
    header('Location: ./studenthome.php');
    exit();
}

$page_title = "Feedback Dashboard";
require_once("stheader.php");

$raw_subjects = $assessmentService->getSubjectsByStudentId($student_id);
$subjects = array_filter($raw_subjects, function($sub) use ($assessmentService) {
    $co_result = $assessmentService->getCOsBySubjectId($sub['id']);
    return !empty($co_result['data']);
});
?>

<div class="container my-4">
    <?php if (!empty($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_msg']); ?>
            <?php unset($_SESSION['flash_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><?= htmlspecialchars($classname); ?></h5>
                <small>Academic Year: <?= htmlspecialchars($acad_year); ?></small>
            </div>
            <a href="studentfeedback.php" class="btn btn-sm btn-light">Back to Classes</a>
        </div>
        <div class="card-body">
            <?php if (!empty($subjects)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th style="width: 220px;" class="text-center">Course End Survey (CES)</th>
                                <th>Faculty Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $sub): 
                                $ces_submitted = $feedbackService->hasStudentSubmittedCES($student_id, $sub['id']);
                                $faculties = $assessmentService->getFacultiesBySubjectId($sub['id']);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['subcode']); ?></strong> - <?= htmlspecialchars($sub['sub_fullname']); ?>
                                        <br><span class="badge bg-secondary"><?= htmlspecialchars($sub['sub_type']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($ces_submitted): ?>
                                            <span class="badge bg-success py-2 px-3">Completed</span>
                                        <?php else: ?>
                                            <form action="student_ces_feedback.php" method="post" class="m-0">
                                                <input type="hidden" name="student_id" value="<?= $student_id; ?>">
                                                <input type="hidden" name="subject_id" value="<?= $sub['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Take Course Survey</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($faculties)): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <?php foreach ($faculties as $fac): 
                                                    $fac_done = $feedbackService->hasStudentSubmittedFacultyFeedback($student_id, $sub['id'], $fac['faculty_id']);
                                                ?>
                                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded bg-light">
                                                        <div>
                                                            <strong><?= htmlspecialchars($fac['faculty_name']); ?></strong>
                                                            <small class="text-muted d-block"><?= htmlspecialchars($fac['designation']); ?></small>
                                                        </div>
                                                        <?php if ($fac_done): ?>
                                                            <span class="badge bg-success">Submitted</span>
                                                        <?php else: ?>
                                                            <form action="student_faculty_feedback.php" method="post" class="m-0">
                                                                <input type="hidden" name="student_id" value="<?= $student_id; ?>">
                                                                <input type="hidden" name="subject_id" value="<?= $sub['id']; ?>">
                                                                <input type="hidden" name="faculty_id" value="<?= $fac['faculty_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-success">Rate Faculty</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small"><em>No faculty mapped to this course</em></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No feedback forms are currently active. Feedback will become available once Course Outcomes (COs) are configured for your enrolled subjects.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once("stfooter.php"); ?>

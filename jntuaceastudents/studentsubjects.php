<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once("services/StudentRepository.php");
require_once("services/StudentAssessmentService.php");

$studentRepo = new StudentRepository();
$assessmentService = new StudentAssessmentService();

// Verify that the requested student_id belongs to the logged-in user
if (empty($_POST['student_id']) || empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership($_POST['student_id'], $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (empty($_POST["student_id"]) || empty($_POST['class_id']) || empty($_POST['classname']) || empty($_POST['acad_year'])) {
    header('Location: ./studenthome.php');
    exit();
}

$page_title = "Student Dashboard";
require_once("stheader.php");

// Fetch classes and their corresponding subjects for the student
$subjects = $assessmentService->getSubjectsByStudentId($_POST['student_id']);
$studentClassStatus = $studentRepo->getStudentClassStatus((int)$_POST['student_id'], (int)$_POST['class_id']);
$isInactiveInClass = ($studentClassStatus === 0);

$isResultsPub = \FeatureManager::isStudentVisible('MOD_RESULTS_PUBLISH');
$resultsMap = [];
if ($isResultsPub) {
    require_once __DIR__ . '/services/ExamResultsService.php';
    $examSvc = \Services\ExamResultsService::getInstance();
    $studentResults = $examSvc->getStudentResults((string)$_SESSION['user']);
    foreach ($studentResults['results'] ?? [] as $resRow) {
        if (!empty($resRow['subject_id'])) {
            $resultsMap[(int)$resRow['subject_id']] ??= $resRow;
        }

    }
}
?>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header">
            <div class="float-start">
                <h6 class="mb-0">
                    <?= htmlspecialchars($_POST['classname']); ?>
                    <?php if ($isInactiveInClass): ?>
                        <span class="badge bg-secondary ms-2">Inactive / Transferred</span>
                    <?php endif; ?>
                </h6>
                <small>Academic Year: <?= htmlspecialchars($_POST['acad_year']); ?></small>
            </div>
            <div class="float-end">
                <a href="studenthome.php" class="btn btn-sm btn-outline-primary">Home</a>
            </div>
        </div>
        <div class="card-body">
            <?php if ($isInactiveInClass): ?>
                <div class="alert alert-secondary py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i><strong>Notice:</strong> You are currently marked inactive in this class (e.g. transferred or shifted mid-semester). You may click any course below to review past attendance records earned during your active period.
                </div>
            <?php endif; ?>
            <?php if (!empty($subjects)): ?>
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Full Name</th>
                            <th>Subject Type</th>
                            <?php if ($isResultsPub): ?>
                                <th class="text-center">Exam Result</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): 
                            $sRes = $resultsMap[(int)$subject['id']] ?? null;
                        ?>
                            <tr class="clickable-row" onclick="document.getElementById('subject_form_<?= htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8'); ?>').submit();">
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($subject['subcode']); ?></span></td>
                                <td class="fw-semibold"><?= htmlspecialchars($subject['sub_fullname']); ?></td>
                                <td><?= htmlspecialchars($subject['sub_type']); ?></td>
                                <?php if ($isResultsPub): ?>
                                    <td class="text-center">
                                        <?php if ($sRes): 
                                            $badge = match($sRes['result_status']) {
                                                'PASS' => 'bg-success',
                                                'FAIL' => 'bg-danger',
                                                'ABSENT' => 'bg-warning text-dark',
                                                'MALPRACTICE' => 'bg-dark',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                            <span class="badge <?= $badge ?>">Grade: <?= htmlspecialchars($sRes['grade_letter']) ?> (<?= htmlspecialchars($sRes['result_status']) ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <form id="subject_form_<?= htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8'); ?>" action="studentcourseatt.php" method="post" style="display: none;">
                                <input type="hidden" name="subject_id" value="<?= htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="subcode" value="<?= htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="sub_fullname" value="<?= htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="student_id" value="<?= htmlspecialchars($_POST["student_id"], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="class_id" value="<?= htmlspecialchars($_POST['class_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="classname" value="<?= htmlspecialchars($_POST['classname'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="acad_year" value="<?= htmlspecialchars($_POST['acad_year'], ENT_QUOTES, 'UTF-8'); ?>">

                            </form>
                        <?php endforeach; ?>
                    </tbody>
                    <style>
                        .clickable-row:hover {
                            cursor: pointer;
                            background-color: rgb(137, 225, 195);
                            /* Light grey background on hover */
                        }
                    </style>
                </table>
            <?php else: ?>
                <p class="text-muted">No subjects available for this class.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>
<?php
require_once __DIR__ . '/bootstrap.php';

// facseedirectmarks.php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_SEE_MARKS');
$page_title = "Direct External Marks Entry";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("seeassessment.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$seeObj = new SEEAssessment();

if (empty($_GET['sub_id'])) {
    header("Location: facseemarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$students = $facultyObj->getMappedStudents($sub_id);
$reg = $ciaObj->getRegulationForSubject($sub_id);
$maxMarks = (float)$ciaObj->getAcademicSetting('theory_see_max_marks', $reg, $sub_id, 70.0);

require_once __DIR__.'/core/AssessmentPolicy.php';
$profile=(new \College\AssessmentPolicy($facultyObj->getConnection()))->forSubject($sub_id);
$maxMarks=$profile['config']['net_max']??$maxMarks;

$isSubmitted = $seeObj->isSEEMarksSubmitted($sub_id);
$savedMarks = $seeObj->getSavedSEEMarks($sub_id);
$savedMap = [];
foreach ($savedMarks as $sm) {
    $savedMap[$sm['student_id']] = floatval($sm['external_marks']);
}

// Handle Direct Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_direct_marks'])) {
    \FeatureManager::requireWriteAccess('MOD_SEE_MARKS');
    if (!empty($_POST['direct_marks']) && is_array($_POST['direct_marks'])) {
        $res = $seeObj->saveDirectSEEMarks($sub_id, $_POST['direct_marks'], $maxMarks, $_SESSION['facid']);
        if ($res['status'] == 1) {
            $_SESSION['succ'] = "Direct University SEE Marks saved successfully.";
            header("Location: facseedirectmarks.php?sub_id=$sub_id");
            exit;
        } else {
            $_SESSION['err'] = "Error: " . $res['err'];
        }
    } else {
        $_SESSION['err'] = "No student marks received.";
    }
}

require_once("facheader.php");
?>

<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Mode B: Direct University Ledger Marks Entry</h5>
            <span class="badge bg-primary fs-6">Max SEE Score: <?= $maxMarks; ?></span>
        </div>
        <div class="card-header bg-light">
            <strong>Course:</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode'] ?? ''); ?>)
            | <strong>Total Students:</strong> <?= count($students); ?>
            | <strong>Status:</strong> <?= $isSubmitted ? '<span class="badge bg-success">Recorded in Database</span>' : '<span class="badge bg-warning text-dark">Pending Entry</span>'; ?>
        </div>
        <div class="card-body">
            <?= \FeatureManager::renderReadOnlyBanner('MOD_SEE_MARKS'); ?>
            <?php if (!empty($_SESSION['succ'])): ?><div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['succ']; unset($_SESSION['succ']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (!empty($_SESSION['err'])): ?><div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['err']; unset($_SESSION['err']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="alert alert-info py-2 small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Direct Entry Guidance:</strong> Directly enter the student's aggregate final score (0 to <?= $maxMarks; ?>) from the university tabulation ledger. CO values inferred from aggregate totals are estimates. Question-wise evidence can be uploaded separately.
            </div>

            <form method="POST">
                <div class="table-responsive border" style="max-height: 600px;">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th style="width: 170px;">Roll Number</th>
                                <th>Student Name</th>
                                <th style="width: 220px;" class="text-center">SEE Total Marks (Max <?= $maxMarks; ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No students mapped to this course.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $idx => $stu): 
                                    $current = isset($savedMap[$stu['id']]) ? $savedMap[$stu['id']] : '';
                                ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?= $idx + 1; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($stu['username']); ?></td>
                                        <td><?= htmlspecialchars($stu['name']); ?></td>
                                        <td>
                                            <input type="number" step="any" min="0" max="<?= $maxMarks; ?>" 
                                                   name="direct_marks[<?= $stu['id']; ?>]" 
                                                   value="<?= ($current !== '') ? $current : ''; ?>" 
                                                   class="form-control form-control-sm text-center fw-bold" 
                                                   placeholder="0 - <?= $maxMarks; ?>" 
                                                   <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'readonly' : 'required'; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to SEE Hub
                    </a>
                    <?php if (!empty($students)): ?>
                        <button type="submit" name="save_direct_marks" class="btn btn-success px-4" onclick="return confirm('Submit direct university ledger marks?');" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i> Submit University Ledger Scores
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>

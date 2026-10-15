<?php
require_once __DIR__ . '/bootstrap.php';

// facseemarks.php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_SEE_MARKS');

$page_title = "External (SEE) Examination Manager";
require_once("facheader.php");
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("seeassessment.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$seeObj = new SEEAssessment();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);

$selected_sub_id = null;
if (!empty($_POST['action']) && $_POST['action'] === 'sync_results' && !empty($_POST['sub_id'])) {
    \FeatureManager::requireWriteAccess('MOD_SEE_MARKS');
    require_once __DIR__ . '/services/ExamResultsService.php';
    $examService = \Services\ExamResultsService::getInstance();
    $syncRes = $examService->syncResultsToSeeMarks((int)$_POST['sub_id'], (int)$_SESSION['userid'], (int)($_POST['notification_id']??0),isset($_POST['replace_marks']),trim($_POST['sync_reason']??''));
    if ($syncRes['status'] === 1) {
        $_SESSION['succ'] = $syncRes['msg'];
    } else {
        $_SESSION['err'] = $syncRes['err'];
    }
    header("Location: facseemarks.php?sub_id=" . intval($_POST['sub_id']));
    exit();
}

if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = intval($_POST['sub_id']);
} elseif (!empty($_GET['sub_id'])) {
    $selected_sub_id = intval($_GET['sub_id']);
}
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container my-4"><a class="btn btn-outline-primary mb-3" href="attainmentimport.php">Import results for attainment</a><?php if(\Services\FeatureManager::canAction('MOD_AI_ASSISTANCE','view')): ?><a class="btn btn-outline-secondary mb-3 ms-2" href="aiassistance.php">Optional AI drafts</a><?php endif ?>
    <?= \FeatureManager::renderReadOnlyBanner('MOD_SEE_MARKS'); ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Semester End Examination (SEE) Evaluation Portal</h5>
        </div>
        <div class="card-body">
            <form action="facseemarks.php" method="POST">
                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">
                <div class="row align-items-end">
                    <div class="col-md-9">
                        <label for="sub_id" class="form-label fw-bold">Select Course / Subject:</label>
                        <select name="sub_id" id="sub_id" class="form-select" required>
                            <option value="">-- Choose Assigned Subject --</option>
                            <?php if (!empty($facultySubjects['data'])): ?>
                                <?php foreach ($facultySubjects['data'] as $sub): ?>
                                    <option value="<?= $sub['id']; ?>" <?= ($selected_sub_id == $sub['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($sub['sub_fullname']); ?> (<?= htmlspecialchars($sub['subcode']); ?>) - <?= htmlspecialchars($sub['class_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i> Load SEE Status</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_sub_id): 
        $subjectDetails = $facultyObj->getSubjectDetails($selected_sub_id);
        $component = $seeObj->getOrCreateSEEComponent($selected_sub_id, 'SEE-Theory');
        $isMetaAdded = $seeObj->isSEEMetadataConfigured($selected_sub_id, 'SEE-Theory');
        $isSubmitted = $seeObj->isSEEMarksSubmitted($selected_sub_id);
        $savedMarks = $isSubmitted ? $seeObj->getSavedSEEMarks($selected_sub_id) : [];
        $reg = $ciaObj->getRegulationForSubject($selected_sub_id);
        $maxSee = (float)$ciaObj->getAcademicSetting('theory_see_max_marks', $reg, $selected_sub_id, 70.0);
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><strong>Course:</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode'] ?? ''); ?>) | Regulation: <span class="badge bg-secondary"><?= htmlspecialchars($reg); ?></span> | Max SEE: <?= $maxSee; ?> Marks</span>
            <span class="badge <?= $isSubmitted ? 'bg-success' : 'bg-warning text-dark'; ?> fs-6">
                <i class="bi <?= $isSubmitted ? 'bi-check-circle-fill' : 'bi-hourglass-split'; ?> me-1"></i>
                <?= $isSubmitted ? 'SEE Results Submitted' : 'Pending Submission'; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (!empty($_SESSION['succ'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['succ']; unset($_SESSION['succ']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['err'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['err']; unset($_SESSION['err']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Step 1: QP Metadata -->
                <div class="col-md-3">
                    <div class="card h-100 border-<?= $isMetaAdded ? 'success' : 'secondary'; ?>">
                        <div class="card-body text-center d-flex flex-column">
                            <i class="bi bi-file-earmark-ruled text-<?= $isMetaAdded ? 'success' : 'secondary'; ?> display-4"></i>
                            <h5 class="card-title mt-3">Step 1: QP Metadata</h5>
                            <p class="card-text text-muted small">Configure Question 1 (1a-1j) compulsory questions and Q2-Q11 Either/Or pairs, Bloom levels, and CO mappings.</p>
                            <span class="badge <?= $isMetaAdded ? 'bg-success' : 'bg-danger'; ?> mb-3"><?= $isMetaAdded ? 'Metadata Configured' : 'Not Configured'; ?></span>
                            <div class="mt-auto">
                                <a href="facseecompques.php?sub_id=<?= $selected_sub_id; ?>&component_id=<?= $component['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-pencil-square me-1"></i> <?= $isMetaAdded ? 'QP Structure' : 'Add Structure'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Mode A Detailed Marks -->
                <div class="col-md-3">
                    <div class="card h-100 border-<?= ($isMetaAdded && !$isSubmitted) ? 'primary' : 'light'; ?>">
                        <div class="card-body text-center d-flex flex-column">
                            <i class="bi bi-table text-primary display-4"></i>
                            <h5 class="card-title mt-3">Mode A: Detailed Entry</h5>
                            <p class="card-text text-muted small">Enter marks per sub-question (1a..1j, 2a, 2b..) via web grid or CSV upload. Auto-resolves choice max pairs.</p>
                            <div class="mt-auto d-grid gap-2">
                                <?php if ($isMetaAdded): ?>
                                    <a href="facseemarksentry.php?sub_id=<?= $selected_sub_id; ?>&component_id=<?= $component['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-input-cursor-text me-1"></i> Question Entry
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Needs QP Metadata</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Mode B Direct Entry -->
                <div class="col-md-3">
                    <div class="card h-100 border-<?= ($isMetaAdded) ? 'info' : 'light'; ?>">
                        <div class="card-body text-center d-flex flex-column">
                            <i class="bi bi-card-checklist text-info display-4"></i>
                            <h5 class="card-title mt-3">Mode B: Direct Entry</h5>
                            <p class="card-text text-muted small">For manual mark ledgers. Directly input the finalized total external score (out of <?= $maxSee; ?>) for each student.</p>
                            <div class="mt-auto d-grid gap-2">
                                <?php if ($isMetaAdded): ?>
                                    <a href="facseedirectmarks.php?sub_id=<?= $selected_sub_id; ?>&component_id=<?= $component['id']; ?>" class="btn btn-info text-white btn-sm">
                                        <i class="bi bi-card-list me-1"></i> Direct Marks
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Needs QP Metadata</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Mode C Auto-Sync from Exam Results -->
                <div class="col-md-3">
                    <div class="card h-100 border-success shadow-sm">
                        <div class="card-body text-center d-flex flex-column">
                            <i class="bi bi-cloud-arrow-down-fill text-success display-4"></i>
                            <h5 class="card-title mt-3 text-success">Mode C: Auto-Sync</h5>
                            <p class="card-text text-muted small">Auto-ingest verified external scores from officially published semester results. Zero manual typing!</p>
                            <div class="mt-auto d-grid gap-2">
                                <form method="POST" onsubmit="return confirm('Auto-sync external marks from published examination results for this subject?');">
                                    <input type="hidden" name="action" value="sync_results">
                                    <label class="small"><input type="checkbox" name="replace_marks"> Replace existing marks</label><input class="form-control form-control-sm mb-2" name="sync_reason" placeholder="Replacement reason"><label>Published notification ID <input class="form-control" type="number" min="1" name="notification_id" required></label>
                                    <input type="hidden" name="sub_id" value="<?= $selected_sub_id; ?>">
                                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                                        <i class="bi bi-lightning-charge me-1"></i> Auto-Sync Results
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php if ($isSubmitted): ?>
            <div class="alert alert-light border mt-4 mb-0 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 text-success fw-bold"><i class="bi bi-graph-up-arrow me-2"></i>Analysis & Outcomes Ready</h6>
                    <span class="text-muted small">SEE performance analytics, direct attainment, and comprehensive Bluebook dossier are generated.</span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                    <a href="facciaanalysis2.php?sub_id=<?= $selected_sub_id; ?>&assessment_number=SEE" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> View SEE Analysis
                    </a>
                    <a href="facciaanalysis2.php?sub_id=<?= $selected_sub_id; ?>&assessment_number=all" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-diagram-3 me-1"></i> Direct & Overall Attainment
                    </a>
                    <a href="download_obe_analysis.php?sub_id=<?= $selected_sub_id; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> OBE Analytics (PDF)
                    </a>
                    <?php if (\FeatureManager::isFacultyVisible('MOD_SAR_FORM_C3')): ?>
                    <a href="download_obe_analysis.php?action=sar_form_c3&sub_id=<?= $selected_sub_id; ?>" target="_blank" class="btn btn-outline-warning btn-sm text-dark">
                        <i class="bi bi-file-earmark-ruled me-1"></i> NBA SAR Form C-3
                    </a>
                    <?php endif; ?>
                    <a href="download_bluebook.php?sub_id=<?= $selected_sub_id; ?>" target="_blank" class="btn btn-success btn-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i> e-Bluebook PDF
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once("facfooter.php"); ?>

<?php
require_once __DIR__ . '/bootstrap.php';

// facseecompques.php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_SEE_MARKS');

$page_title = "Configure SEE Question Paper Metadata";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("courseoutcome.class.php");
require_once("assessmentstructure.class.php");
require_once("seeassessment.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$coObj = new CourseOutcome();
$asObj = new AssessmentStructure();
$seeObj = new SEEAssessment();

if (empty($_GET['sub_id']) || empty($_GET['component_id'])) {
    header("Location: facseemarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$component_id = intval($_GET['component_id']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$cos = $coObj->getCOsBySubjectId($sub_id);
$blooms_levels = $asObj->getBloomsLevels();

if (empty($cos['data'])) {
    echo "<script>alert('Please Add Course Outcomes to proceed.'); window.location.href='faceditoptions.php';</script>";
    exit;
}

require_once __DIR__.'/core/AssessmentPolicy.php';try{(new \College\AssessmentPolicy($asObj->getConnection()))->questions($sub_id,$component_id);}catch(DomainException $ex){\College\RequestSecurity::deny($ex->getMessage());}
$existingQuestions = $asObj->getQuestionsByComponent($component_id);
$templates = $seeObj->getSEETemplates();
$template_id = !empty($_GET['template_id']) ? $_GET['template_id'] : 'see_theory_70';
if (!isset($templates[$template_id])) {
    $template_id = 'see_theory_70';
}
$template = $templates[$template_id];

// Handle Step 2: Form submission of question metadata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_see_metadata'])) {
    \FeatureManager::requireWriteAccess('MOD_SEE_MARKS');
    if (empty($_POST['questions']) || !is_array($_POST['questions'])) {
        $_SESSION['err'] = "No questions received. Please try again.";
        header("Location: facseecompques.php?sub_id=$sub_id&component_id=$component_id&template_id=$template_id");
        exit;
    }

    foreach ($_POST['questions'] as $q) {
        if (empty($q['cos']) || !is_array($q['cos'])) {
            $_SESSION['err'] = "Every question/sub-question must be mapped to at least one Course Outcome (CO).";
            header("Location: facseecompques.php?sub_id=$sub_id&component_id=$component_id&template_id=$template_id");
            exit;
        }
    }

    require_once __DIR__.'/services/SEEBlueprintService.php';
    try{(new \Services\SEEBlueprintService())->save($sub_id,$component_id,$_POST['questions'],isset($_POST['confirm_revision']),$_POST['revision_reason']??'');$_SESSION['succ']='Blueprint saved. Existing scores for retained questions were preserved; detailed totals require reconsolidation.';}
    catch(Throwable $ex){$_SESSION['err']=$ex->getMessage();header("Location: facseecompques.php?sub_id=$sub_id&component_id=$component_id&template_id=$template_id");exit;}
    header("Location: facseemarks.php?sub_id=$sub_id");
    exit;
}

require_once("facheader.php");
?>

<div class="container my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-file-earmark-ruled me-2"></i>Configure External Question Paper Blueprint</h5>
            <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to SEE Hub
            </a>
        </div>
        <div class="card-header bg-light">
            <strong>Course:</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode'] ?? ''); ?>)
            | <strong>Template:</strong> <?= htmlspecialchars($template['name']); ?>
        </div>
        <div class="card-body">
            <?= \FeatureManager::renderReadOnlyBanner('MOD_SEE_MARKS'); ?>
            <?php if (!empty($_SESSION['err'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['err']; unset($_SESSION['err']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <?php if (empty($_POST['step_subparts'])): ?>
                <!-- STEP 1: Select Sub-parts for Questions (Default: Single 10 Marks) -->
                <form method="POST" action="facseecompques.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>&template_id=<?= $template_id; ?>">
                    <div class="alert alert-info py-2">
                        <strong><?= htmlspecialchars($template['name']); ?> Structure:</strong><br>
                        &bull; <strong>Question 1 (Compulsory):</strong> <?= $template['q1_subparts']; ?> questions (1a to 1<?= chr(ord('a') + $template['q1_subparts'] - 1); ?>) &times; <?= $template['q1_marks_each']; ?> Marks = <?= $template['q1_total']; ?> Marks.<br>
                        &bull; <strong>Either-Or Choices:</strong> <?= count($template['choice_groups']); ?> pairs carrying <?= $template['choice_groups'][0]['marks']; ?> Marks each = <?= count($template['choice_groups']) * $template['choice_groups'][0]['marks']; ?> Marks.
                    </div>

                    <?php if (!empty($existingQuestions)): ?>
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Notice:</strong> Question paper metadata is already configured (<?= count($existingQuestions); ?> questions). Reconfiguring will replace existing question metadata and any detailed marks entered.
                        </div>
                    <?php endif; ?>

                    <h6 class="fw-bold mt-3">Select Sub-Part Breakdown for 10-Mark Either-Or Questions (Default: Single 10 Marks):</h6>
                    <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 28%;" class="text-center">Either-Or Choice</th>
                                <th style="width: 24%;">Question</th>
                                <th style="width: 48%;">Sub-Parts Configuration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($template['choice_groups'] as $gIndex => $grp): 
                                $isEven = ($gIndex % 2 === 0);
                                $rowBg = $isEven ? '#f0f9ff' : '#ffffff';
                                $borderTop = 'border-top: 2px solid ' . ($isEven ? '#0284c7' : '#64748b') . ';';
                            ?>
                                <tr style="background-color: <?= $rowBg; ?>; <?= $borderTop; ?>">
                                    <td rowspan="3" class="fw-bold text-center align-middle" style="background-color: <?= $isEven ? '#e0f2fe' : '#f1f5f9'; ?>; color: <?= $isEven ? '#0369a1' : '#334155'; ?>;">
                                        Either <?= $grp['pair'][0]; ?> OR <?= $grp['pair'][1]; ?><br>
                                        <span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;"><?= $grp['marks']; ?> Marks</span>
                                    </td>
                                    <td><strong>Question <?= $grp['pair'][0]; ?></strong></td>
                                    <td>
                                        <select name="subparts[<?= $grp['pair'][0]; ?>]" class="form-select form-select-sm" required>
                                            <option value="1" selected>Single (10 Marks)</option>
                                            <option value="2">a & b (e.g. 5M + 5M or 6M + 4M)</option>
                                            <option value="3">a & b & c (e.g. 4M + 3M + 3M)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr style="background-color: <?= $rowBg; ?>;">
                                    <td colspan="2" class="text-center text-muted fst-italic py-1" style="font-size: 0.85rem;">--- OR ---</td>
                                </tr>
                                <tr style="background-color: <?= $rowBg; ?>;">
                                    <td><strong>Question <?= $grp['pair'][1]; ?></strong></td>
                                    <td>
                                        <select name="subparts[<?= $grp['pair'][1]; ?>]" class="form-select form-select-sm" required>
                                            <option value="1" selected>Single (10 Marks)</option>
                                            <option value="2">a & b (e.g. 5M + 5M or 6M + 4M)</option>
                                            <option value="3">a & b & c (e.g. 4M + 3M + 3M)</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </a>
                        <button type="submit" name="step_subparts" value="1" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                            Next: Define Marks, Bloom's & COs <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

            <?php else: ?>
                <!-- STEP 2: Configure Marks, Bloom's Taxonomy, and COs (CIA Layout Perception) -->
                <form method="POST" action="facseecompques.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>&template_id=<?= $template_id; ?>">
                    <h6 class="fw-bold mb-3">Question Paper Metadata & Outcome Mapping:</h6>
                    <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th style="width: 140px;">Question Type</th>
                                <th style="width: 85px;" class="text-center">Q.No</th>
                                <th style="width: 95px;" class="text-center">Marks</th>
                                <th style="width: 175px;">Bloom's Level</th>
                                <th style="min-width: 90px;">COs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Question 1 Compulsory Short Questions -->
                            <tr class="table-warning">
                                <td colspan="6" class="fw-bold py-2">
                                    <i class="bi bi-check-all me-1"></i> Question 1 (Compulsory Short Answers - <?= $template['q1_subparts']; ?> &times; <?= $template['q1_marks_each']; ?>M = <?= $template['q1_total']; ?> Marks)
                                </td>
                            </tr>
                            <?php 
                            $qIndex = 1;
                            $alpha = range('a', 'z');
                            for ($s = 0; $s < $template['q1_subparts']; $s++): 
                                $label = '1' . $alpha[$s];
                            ?>
                                <tr style="background-color: #fffdf5;">
                                    <td class="text-center fw-bold text-muted"><?= $qIndex; ?></td>
                                    <td>
                                        <select name="questions[<?= $qIndex; ?>][type]" class="form-select form-select-sm" required>
                                            <option value="Short_Answer" selected>Short_Answer</option>
                                            <option value="Question">Question</option>
                                            <option value="Analytical">Analytical</option>
                                            <option value="Problem">Problem</option>
                                        </select>
                                    </td>
                                    <td class="text-center"><input type="text" name="questions[<?= $qIndex; ?>][label]" class="form-control form-control-sm bg-light fw-bold text-center" value="<?= $label; ?>" readonly required size="6"></td>
                                    <td><input type="number" step="0.5" min="0.5" max="10" name="questions[<?= $qIndex; ?>][marks]" class="form-control form-control-sm text-center" value="<?= $template['q1_marks_each']; ?>" required size="6"></td>
                                    <td>
                                        <select name="questions[<?= $qIndex; ?>][blooms_level]" class="form-select form-select-sm" required>
                                            <?php foreach ($blooms_levels as $b): ?>
                                                <option value="<?= $b['id']; ?>"><?= $b['id'] . '. ' . htmlspecialchars($b['blooms_label']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="min-width: 80px;">
                                        <?php foreach ($cos['data'] as $cIndex => $co): ?>
                                            <input type="checkbox" name="questions[<?= $qIndex; ?>][cos][]" value="<?= $co['id']; ?>" id="chkboxco_<?= $qIndex; ?>_<?= $co['id']; ?>">
                                            <label for="chkboxco_<?= $qIndex; ?>_<?= $co['id']; ?>" style="cursor: pointer; font-size: 0.88rem;">CO<?= $co['co_number']; ?></label><br>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php $qIndex++; endfor; ?>

                            <!-- Either-Or Choices (Alternating Background Separation) -->
                            <?php 
                            $subpartsPost = $_POST['subparts'] ?? [];
                            $partLabels = [1 => [''], 2 => ['a', 'b'], 3 => ['a', 'b', 'c']];

                            foreach ($template['choice_groups'] as $gIndex => $grp): 
                                $isEven = ($gIndex % 2 === 0);
                                $sectionBg = $isEven ? '#f0f9ff' : '#ffffff';
                                $headerBg = $isEven ? '#e0f2fe' : '#f1f5f9';
                                $headerColor = $isEven ? '#0369a1' : '#334155';
                                $borderTop = 'border-top: 2px solid ' . ($isEven ? '#0284c7' : '#64748b') . ';';
                            ?>
                                <tr style="background-color: <?= $headerBg; ?>; color: <?= $headerColor; ?>; <?= $borderTop; ?>" class="fw-bold">
                                    <td colspan="6" class="text-center py-2">
                                        <i class="bi bi-arrows-expand me-1"></i> Either <?= $grp['pair'][0]; ?> OR <?= $grp['pair'][1]; ?> (<?= $grp['marks']; ?> Marks Total)
                                    </td>
                                </tr>
                                <?php
                                foreach ($grp['pair'] as $pIdx => $parentQ):
                                    $numParts = isset($subpartsPost[$parentQ]) ? intval($subpartsPost[$parentQ]) : 1;
                                    // Default to 10.0 for single part
                                    $defaultMarks = ($numParts == 1) ? 10.0 : (($numParts == 2) ? 5.0 : 3.5);

                                    foreach ($partLabels[$numParts] as $subLabel):
                                        $fullLabel = $parentQ . $subLabel;
                                ?>
                                        <tr style="background-color: <?= $sectionBg; ?>;">
                                            <td class="text-center fw-bold text-muted"><?= $qIndex; ?></td>
                                            <td>
                                                <select name="questions[<?= $qIndex; ?>][type]" class="form-select form-select-sm" required>
                                                    <option value="Question" selected>Question</option>
                                                    <option value="Analytical">Analytical</option>
                                                    <option value="Problem">Problem</option>
                                                    <option value="Derivation">Derivation</option>
                                                    <option value="Short_Answer">Short_Answer</option>
                                                </select>
                                            </td>
                                            <td class="text-center"><input type="text" name="questions[<?= $qIndex; ?>][label]" class="form-control form-control-sm bg-light fw-bold text-center" value="<?= $fullLabel; ?>" readonly required size="6"></td>
                                            <td><input type="number" step="0.5" min="0.5" max="10" name="questions[<?= $qIndex; ?>][marks]" class="form-control form-control-sm text-center" value="<?= $defaultMarks; ?>" required size="6"></td>
                                            <td>
                                                <select name="questions[<?= $qIndex; ?>][blooms_level]" class="form-select form-select-sm" required>
                                                    <?php foreach ($blooms_levels as $b): ?>
                                                        <option value="<?= $b['id']; ?>" <?= ($b['id'] == 3) ? 'selected' : ''; ?>><?= $b['id'] . '. ' . htmlspecialchars($b['blooms_label']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="min-width: 80px;">
                                                <?php foreach ($cos['data'] as $cIndex => $co): ?>
                                                    <input type="checkbox" name="questions[<?= $qIndex; ?>][cos][]" value="<?= $co['id']; ?>" id="chkboxco_<?= $qIndex; ?>_<?= $co['id']; ?>">
                                                    <label for="chkboxco_<?= $qIndex; ?>_<?= $co['id']; ?>" style="cursor: pointer; font-size: 0.88rem;">CO<?= $co['co_number']; ?></label><br>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                <?php 
                                        $qIndex++;
                                    endforeach;
                                    if ($pIdx == 0):
                                ?>
                                        <tr style="background-color: <?= $headerBg; ?>;">
                                            <td colspan="6" class="text-center fw-bold py-1" style="color: <?= $headerColor; ?>; font-size: 0.85rem;">--- OR ---</td>
                                        </tr>
                                <?php
                                    endif;
                                endforeach;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="facseecompques.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>&template_id=<?= $template_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Subparts
                        </a>
                        <button type="submit" name="submit_see_metadata" value="1" class="btn btn-success px-4" onclick="return confirm('Save question paper metadata? This will configure all sub-questions and CO mappings.');" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i> Save SEE Question Paper Metadata
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>

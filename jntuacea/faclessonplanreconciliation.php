<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Authentication Guard
if (empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role'] !== "faculty") {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_LESSON_PLAN');

require_once("faculty.class.php");
require_once("services/LessonPlanService.php");

$facultyObj = new Faculty();
$lpService = \Services\LessonPlanService::getInstance();

$faculty_id = $_SESSION['facid'] ?? 0;
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = !empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : (!empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : null);

$msg = '';
$err = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_sub_id) {
    \FeatureManager::requireWriteAccess('MOD_LESSON_PLAN');
    // 1. Save Diary Mappings if submitted
    if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
        $mapRes = $lpService->saveDiaryLessonPlanMappings($selected_sub_id, $_POST['mapping']);
        $mappingFailed=empty($mapRes['status']);
        if($mappingFailed){$err=$mapRes['error']??'Mappings were not saved.';}
        if (isset($_POST['save_mappings_only'])) {
            if ($mapRes['status'] == 1) {
                $msg = "Diary-to-Lesson-Plan mappings saved successfully (" . $mapRes['updated_count'] . " classes updated).";
            } else {
                $err = $mapRes['error'] ?? "Failed to save diary mappings.";
            }
        }
    }

    // 2. Save Course Completion Audit (Draft or Final)
    if (isset($_POST['save_audit']) && empty($mappingFailed)) {
        $isFinal = !empty($_POST['is_final_submission']);
        $auditData = [
            'sub_id' => $selected_sub_id,
            'faculty_id' => $faculty_id,
            'total_planned_lectures' => (int)($_POST['total_planned_lectures'] ?? 0),
            'total_actual_conducted' => (int)($_POST['total_actual_conducted'] ?? 0),
            'total_compensatory_classes' => (int)($_POST['total_compensatory_classes'] ?? 0),
            'syllabus_completion_pct' => (float)($_POST['syllabus_completion_pct'] ?? 0.0),
            'unit1_completion_date' => !empty($_POST['unit1_completion_date']) ? $_POST['unit1_completion_date'] : null,
            'unit2_completion_date' => !empty($_POST['unit2_completion_date']) ? $_POST['unit2_completion_date'] : null,
            'unit3_completion_date' => !empty($_POST['unit3_completion_date']) ? $_POST['unit3_completion_date'] : null,
            'unit4_completion_date' => !empty($_POST['unit4_completion_date']) ? $_POST['unit4_completion_date'] : null,
            'unit5_completion_date' => !empty($_POST['unit5_completion_date']) ? $_POST['unit5_completion_date'] : null,
            'deviations_reason' => trim($_POST['deviations_reason'] ?? ''),
            'compensatory_actions' => trim($_POST['compensatory_actions'] ?? ''),
            'topics_beyond_syllabus' => trim($_POST['topics_beyond_syllabus'] ?? ''),
            'is_final_submission' => $isFinal
        ];

        $saveRes = $lpService->saveCourseCompletionAudit($auditData);
        if ($saveRes['status'] == 1) {
            $msg = $saveRes['message'];
        } else {
            $err = $saveRes['error'] ?? "Failed to save course completion audit.";
        }
    }

    // 3. Save Continuous Quality Improvement (CQI) Remedial Action
    if (isset($_POST['save_cqi_action'])) {
        \FeatureManager::requireWriteAccess('MOD_CQI_ACTION_TRACKING');
        require_once __DIR__.'/services/AcademicReviewService.php';
        try {
            (new \Services\AcademicReviewService())->proposeCqi($selected_sub_id,(int)($_POST['co_id']??0),$_POST['action_type']??'',trim($_POST['action_proposed']??''));
            $msg='CQI action proposed using recorded attainment evidence.';
        }catch(Throwable $e){$err=$e->getMessage();}

    }
}

// Load Subject & Reconciliation Data
$subjectDetails = null;
$recon = null;
$cos = [];
$cqiActions = [];
if ($selected_sub_id) {
    $subjectDetails = $facultyObj->getSubjectDetails($selected_sub_id);
    $recon = $lpService->getReconciliationData($selected_sub_id);

    // Load COs and CQI Actions
    $conn = \DBCredentials::getInstance()->getConnection();
    $stmtCo = $conn->prepare("SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id = ? ORDER BY co_number ASC");
    $stmtCo->bind_param("i", $selected_sub_id);
    $stmtCo->execute();
    $cos = $stmtCo->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCo->close();

    $stmtCqi = $conn->prepare("
        SELECT cqi.*, co.co_number, co.co_description
        FROM course_cqi_actions cqi
        LEFT JOIN course_outcomes co ON cqi.co_id = co.id
        WHERE cqi.sub_id = ?
        ORDER BY cqi.submitted_at DESC
    ");
    $stmtCqi->bind_param("i", $selected_sub_id);
    $stmtCqi->execute();
    $cqiActions = $stmtCqi->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCqi->close();
}

$page_title = "Course Delivery Audit & Plan Reconciliation";
require_once("facheader.php");
?>

<div class="container my-4">
    <!-- Header / Selector Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>End-of-Course Plan Reconciliation & Delivery Audit (NBA Criterion 2.2)</h5>
            <?php if ($selected_sub_id): ?>
                <div>
                    <a href="facaddlessonplan.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary me-1">
                        <i class="bi bi-calendar3 me-1"></i>Lesson Plan
                    </a>
                    <a href="download_bluebook.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary me-1">
                        <i class="bi bi-file-earmark-pdf me-1"></i>e-Bluebook
                    </a>
                    <?php if (\FeatureManager::isFacultyVisible('MOD_COURSE_MASTER_FILE')): ?>
                    <a href="download_coursemasterfile.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-journal-bookmark me-1"></i>Master Course File
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?= \FeatureManager::renderReadOnlyBanner('MOD_LESSON_PLAN'); ?>
            <form action="faclessonplanreconciliation.php" method="get" class="row g-3 align-items-end mb-2">
                <div class="col-md-9">
                    <label for="sub_id" class="form-label fw-bold">Select Course / Subject Offering:</label>
                    <select name="sub_id" id="sub_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($facultySubjects['data'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= ($selected_sub_id == $sub['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sub['sub_fullname']) ?> (<?= htmlspecialchars($sub['subcode']) ?>) - <?= htmlspecialchars($sub['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Load Reconciliation</button>
                </div>
            </form>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_sub_id && $recon): ?>
        <?php 
        $audit = $recon['audit'];
        $signoffStatus = $audit['faculty_signoff_status'] ?? 'DRAFT';
        ?>

        <!-- Audit Status Badge & Guidance -->
        <div class="alert <?= ($signoffStatus === 'SUBMITTED' || $signoffStatus === 'APPROVED') ? 'alert-success' : 'alert-info' ?> d-flex justify-content-between align-items-center mb-4">
            <div>
                <strong><i class="bi bi-info-circle me-1"></i>Course Completion Audit Status:</strong>
                <?php if ($signoffStatus === 'APPROVED'): ?>
                    <span class="badge bg-success ms-1">Approved by HOD</span>
                <?php elseif ($signoffStatus === 'SUBMITTED'): ?>
                    <span class="badge bg-primary ms-1">Submitted to HOD for Verification</span> (<?= date('d-m-Y H:i', strtotime($audit['faculty_signoff_at'])) ?>)
                <?php else: ?>
                    <span class="badge bg-warning text-dark ms-1">Draft (Not yet submitted)</span>
                <?php endif; ?>
            </div>
            <small class="text-muted">Supports NBA Criterion 2.1 & 2.2 / NAAC Criterion 2.3</small>
        </div>

        <!-- Metric Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Planned Lectures</h6>
                    <h3 class="text-primary mb-0"><?= $recon['total_planned'] ?></h3>
                    <small class="text-muted">From Course Delivery Plan</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Conducted Periods</h6>
                    <h3 class="text-success mb-0"><?= $recon['total_conducted'] ?></h3>
                    <small class="text-muted">From Daily Class Diary</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Syllabus Coverage</h6>
                    <h3 class="text-info mb-0"><?= $recon['completion_pct'] ?>%</h3>
                    <small class="text-muted"><?= ($recon['completion_pct'] >= 100) ? 'Full Coverage' : 'Deficit Identified' ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Compensatory / Extra</h6>
                    <h3 class="text-warning mb-0"><?= $recon['compensatory_count'] ?></h3>
                    <small class="text-muted">Revision / Beyond plan</small>
                </div>
            </div>
        </div>

        <!-- Uncovered Planned Lectures Alert -->
        <?php if (!empty($recon['planned_lectures'])): ?>
            <?php if (!empty($recon['uncovered_lectures'])): ?>
                <div class="alert alert-warning py-2 px-3 mb-4 small d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                    <div>
                        <strong>Uncovered Planned Lectures (<?= count($recon['uncovered_lectures']) ?>):</strong>
                        <?php 
                        $uncovLabels = array_map(function($ul) {
                            return "Lec #" . $ul['lecture_number'] . " (" . htmlspecialchars(mb_substr($ul['planned_topic'], 0, 30)) . "... [CO" . $ul['co_number'] . "])";
                        }, $recon['uncovered_lectures']);
                        echo implode(', ', array_slice($uncovLabels, 0, 4));
                        if (count($uncovLabels) > 4) {
                            echo " and " . (count($uncovLabels) - 4) . " more";
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success py-2 px-3 mb-4 small d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                    <div>
                        <strong>All <?= $recon['total_planned'] ?> planned lectures have been matched with conducted teaching diary classes!</strong>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Main Form encompassing both Step 1 (Mappings) and Step 2 (Audit Signoff) -->
        <form action="faclessonplanreconciliation.php" method="post" id="reconForm">
            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">

            <!-- STEP 1: Diary to Lesson Plan Reconciliation Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2">
                    <div>
                        <span class="fw-bold text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Step 1: End-of-Course Diary to Lesson Plan Reconciliation</span>
                        <span class="badge bg-secondary ms-2"><?= $recon['total_conducted'] ?> Conducted Classes</span>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="autoSequenceMappings()" title="Map diary entries in sequential 1-to-1 order" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                            <i class="bi bi-lightning-charge-fill me-1"></i>1-Click Auto-Sequence
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllMappings()" title="Reset all mappings to unmapped" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </button>
                        <button type="submit" name="save_mappings_only" value="1" class="btn btn-sm btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i>Save Mappings
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th style="width: 110px;" class="text-center">Date & Hr</th>
                                    <th style="min-width: 250px;">Conducted Diary Entry (Actual What Was Taught)</th>
                                    <th style="min-width: 320px;">Map to Planned Lecture (Course Delivery Plan)</th>
                                    <th style="width: 65px;" class="text-center">CO</th>
                                    <th style="width: 100px;" class="text-center">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recon['diary_entries'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No attendance or diary records found for this course offering.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $sno = 1;
                                    foreach ($recon['diary_entries'] as $d): 
                                        $dId = (int)$d['id'];
                                        $currLpId = $d['lesson_plan_id'];
                                        $isCompensatory = ($currLpId !== null && (int)$currLpId === 0);
                                        $isMapped = (!empty($currLpId) && (int)$currLpId > 0);
                                    ?>
                                        <tr id="diary-row-<?= $dId ?>">
                                            <td class="text-center fw-bold text-muted"><?= $sno++ ?></td>
                                            <td class="text-center text-nowrap">
                                                <span class="fw-bold"><?= date('d-m-Y', strtotime($d['date'])) ?></span><br>
                                                <small class="text-muted"><?= htmlspecialchars($d['hour_desc'] ?? ('Hr ' . $d['hour'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="p-1 rounded bg-light border-start border-3 border-secondary" style="font-family: inherit;">
                                                    <?= nl2br(htmlspecialchars($d['diary'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="mapping[<?= $dId ?>]" class="form-select form-select-sm diary-mapping-select" onchange="updateRowCoBadge(this, <?= $dId ?>)">
                                                    <option value="" data-co="" data-type="unmapped">-- Unmapped / Select Lecture --</option>
                                                    <option value="0" data-co="" data-type="compensatory" <?= $isCompensatory ? 'selected' : '' ?>>
                                                        -- Extra / Compensatory / Revision Class --
                                                    </option>
                                                    <?php foreach ($recon['planned_lectures'] as $p): ?>
                                                        <option value="<?= $p['id'] ?>" 
                                                                data-co="<?= $p['co_number'] ?>" 
                                                                data-unit="<?= $p['unit_number'] ?>"
                                                                data-type="planned"
                                                                <?= ($currLpId == $p['id']) ? 'selected' : '' ?>>
                                                            Lec #<?= $p['lecture_number'] ?>: <?= htmlspecialchars(mb_substr($p['planned_topic'], 0, 52)) ?> (U<?= $p['unit_number'] ?>, CO<?= $p['co_number'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="text-center" id="co-cell-<?= $dId ?>">
                                                <?php if (!empty($d['co_number'])): ?>
                                                    <span class="badge bg-success">CO<?= $d['co_number'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" id="type-cell-<?= $dId ?>">
                                                <?php if ($isCompensatory): ?>
                                                    <span class="badge bg-warning text-dark">Compensatory</span>
                                                <?php elseif ($isMapped): ?>
                                                    <span class="badge bg-primary">Planned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Unmapped</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Mapping links each conducted class to the planned topic and automatically updates the target Course Outcome (CO).</small>
                    <button type="submit" name="save_mappings_only" value="1" class="btn btn-sm btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                        <i class="bi bi-save me-1"></i>Save Mappings
                    </button>
                </div>
            </div>

            <!-- STEP 2: Course Delivery Compliance Sign-Off & Audit Form -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>Step 2: Course Completion Sign-Off & Compliance Statement (NBA Criterion 2.2)
                </div>
                <div class="card-body">
                    <input type="hidden" name="save_audit" value="1">
                    <input type="hidden" name="total_planned_lectures" value="<?= $recon['total_planned'] ?>">
                    <input type="hidden" name="total_actual_conducted" value="<?= $recon['total_conducted'] ?>">
                    <input type="hidden" name="total_compensatory_classes" value="<?= $recon['compensatory_count'] ?>">
                    <input type="hidden" name="syllabus_completion_pct" value="<?= $recon['completion_pct'] ?>">

                    <h6 class="fw-bold text-primary mb-3">1. Unit-Wise Completion Dates:</h6>
                    <div class="row g-3 mb-4">
                        <?php for ($u = 1; $u <= 5; $u++): 
                            $val = $audit["unit{$u}_completion_date"] ?? ($recon['suggested_unit_dates'][$u] ?? '');
                        ?>
                            <div class="col-md">
                                <label class="form-label small fw-bold">Unit <?= $u ?> Completed On:</label>
                                <input type="date" name="unit<?= $u ?>_completion_date" class="form-control form-control-sm" value="<?= htmlspecialchars($val ?? '') ?>">
                            </div>
                        <?php endfor; ?>
                    </div>

                    <h6 class="fw-bold text-primary mb-3">2. Schedule Deviations & Remedial Disclosures:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Reasons for Schedule Deviations / Pacing Remarks (if any):</label>
                            <textarea name="deviations_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Unit 3 required extra mathematical derivations; timetable rescheduled due to Mid Exams..."><?= htmlspecialchars($audit['deviations_reason'] ?? '') ?></textarea>
                            <small class="text-muted">Disclose reasons if actual timeline differed from planned schedule.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Compensatory / Remedial Measures Taken:</label>
                            <textarea name="compensatory_actions" class="form-control form-control-sm" rows="3" placeholder="e.g. Conducted compensatory classes on alternate Saturdays; extra revision tutorial conducted for Unit 4..."><?= htmlspecialchars($audit['compensatory_actions'] ?? '') ?></textarea>
                            <small class="text-muted">Actions taken to ensure 100% curriculum compliance.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Content Beyond Syllabus / Value Added Topics (NBA Criterion 2.1):</label>
                            <textarea name="topics_beyond_syllabus" class="form-control form-control-sm" rows="2" placeholder="e.g. Introduced industry case studies, demonstration of practical open-source tools..."><?= htmlspecialchars($audit['topics_beyond_syllabus'] ?? '') ?></textarea>
                            <small class="text-muted">Contemporary topics and real-world tools introduced beyond prescribed syllabus.</small>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="certifyCheck" required <?= ($signoffStatus === 'SUBMITTED' || $signoffStatus === 'APPROVED') ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="certifyCheck">
                                I hereby certify that the syllabus has been completed in accordance with the academic regulations, and the Course Delivery Plan has been reconciled with the actual Teaching Diary entries.
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" name="is_final_submission" value="0" class="btn btn-outline-secondary" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i>Save Draft
                        </button>
                        <button type="submit" name="is_final_submission" value="1" class="btn btn-success" <?= \FeatureManager::isFacultyReadOnly('MOD_LESSON_PLAN') ? 'disabled' : ''; ?>>
                            <i class="bi bi-send-check me-1"></i>Submit Course Completion to HOD
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (\FeatureManager::isFacultyVisible('MOD_CQI_ACTION_TRACKING')): ?>
            <!-- Continuous Quality Improvement (CQI) Remedial Action Tracker -->
            <div class="card shadow-sm border-success mt-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-arrow-repeat text-success me-2"></i> Continuous Quality Improvement (CQI) Remedial Action Tracker
                        </h5>
                        <p class="text-muted small mb-0">Record structured pedagogical interventions and remedial action plans for Course Outcomes with attainment gaps (NBA Criterion 3.3.2 & NAAC Metric 2.6.2)</p>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        NBA Criterion 3.3.2 / NAAC 2.6.2
                    </span>
                </div>
                <div class="card-body">
                    <!-- CQI Action Submission Form -->
                    <form action="faclessonplanreconciliation.php?sub_id=<?= $selected_sub_id ?>" method="post" class="border rounded p-3 bg-light mb-4">
                        <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($subjectDetails['data']['acad_year'] ?? '') ?>">
                        
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-pencil-square me-1"></i> Log New CQI Remedial Action</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Course Outcome <span class="text-danger">*</span></label>
                                <select name="co_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select CO --</option>
                                    <?php foreach ($cos as $co): ?>
                                        <option value="<?= $co['id'] ?>">
                                            CO<?= htmlspecialchars($co['co_number']) ?>: <?= htmlspecialchars(mb_strimwidth($co['co_description'], 0, 35, '...')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Action Category <span class="text-danger">*</span></label>
                                <select name="action_type" class="form-select form-select-sm" required>
                                    <option value="REMEDIAL_CLASS">Remedial Classes for Slow Learners</option>
                                    <option value="PEDAGOGICAL_CHANGE">Pedagogical / Teaching Method Innovation</option>
                                    <option value="TUTORIAL_HOURS">Additional Tutorial & Problem Solving Hours</option>
                                    <option value="ASSIGNMENT_REVISION">Problem Set & Formative Assignment Redesign</option>
                                    <option value="CONTENT_BEYOND_SYLLABUS">Industry Guest Lecture / Content Beyond Syllabus</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Target Benchmark (%)</label>
                                <input type="number" step="0.5" name="target_benchmark" id="cqi_target" class="form-control form-control-sm" value="60.0" oninput="calcCqiGap()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Attained (%)</label>
                                <input type="number" step="0.5" name="attained_percentage" id="cqi_attained" class="form-control form-control-sm" placeholder="e.g. 52.5" oninput="calcCqiGap()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Attainment Gap (%)</label>
                                <input type="number" step="0.5" name="attainment_gap" id="cqi_gap" class="form-control form-control-sm bg-white" placeholder="0.0" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Remedial Action Narrative & Methodology <span class="text-danger">*</span></label>
                                <textarea name="action_proposed" class="form-control form-control-sm" rows="2" placeholder="Detail the specific corrective action taken/planned (e.g., Conducted 3 hours of compensatory tutorials on unit 3 algorithmic tracing; provided annotated question bank)..." required></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" name="save_cqi_action" class="btn btn-primary btn-sm" <?= \FeatureManager::isFacultyReadOnly('MOD_CQI_ACTION_TRACKING') ? 'disabled' : ''; ?>>
                                    <i class="bi bi-plus-circle me-1"></i> Record CQI Remedial Action
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Existing CQI Log Table -->
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-list-check me-1"></i> Recorded CQI Interventions for this Course</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 70px;">CO</th>
                                    <th style="width: 140px;">Benchmark vs Attained</th>
                                    <th style="width: 80px;">Gap</th>
                                    <th style="width: 160px;">Intervention Type</th>
                                    <th class="text-start">Action Proposed & Narrative</th>
                                    <th style="width: 130px;">HOD Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($cqiActions)): ?>
                                    <?php foreach ($cqiActions as $act): ?>
                                        <tr>
                                            <td class="text-center fw-bold">
                                                <span class="badge bg-primary">CO<?= htmlspecialchars($act['co_number']) ?></span>
                                            </td>
                                            <td class="text-center small">
                                                <span>Target: <?= htmlspecialchars($act['target_benchmark']) ?>%</span><br>
                                                <span class="fw-semibold text-secondary">Attained: <?= $act['attained_percentage'] !== null ? htmlspecialchars($act['attained_percentage']) . '%' : 'N/A' ?></span>
                                            </td>
                                            <td class="text-center fw-bold <?= ($act['attainment_gap'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                <?= ($act['attainment_gap'] > 0) ? '+' : ''; ?><?= htmlspecialchars($act['attainment_gap']) ?>%
                                            </td>
                                            <td class="small">
                                                <span class="badge bg-secondary"><?= str_replace('_', ' ', htmlspecialchars($act['action_type'])) ?></span>
                                            </td>
                                            <td>
                                                <div><?= nl2br(htmlspecialchars($act['action_proposed'])) ?></div>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i>Submitted: <?= date('d-m-Y H:i', strtotime($act['submitted_at'])) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($act['hod_review_status'] === 'APPROVED'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Approved</span>
                                                <?php elseif ($act['hod_review_status'] === 'REVISION_REQUESTED'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i> Needs Revision</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending</span>
                                                <?php endif; ?>
                                                <?php if (!empty($act['hod_remarks'])): ?>
                                                    <div class="small text-muted mt-1" title="<?= htmlspecialchars($act['hod_remarks']) ?>">
                                                        <em><?= htmlspecialchars(mb_strimwidth($act['hod_remarks'], 0, 30, '...')) ?></em>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No CQI remedial actions recorded for this course offering yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <script>
            function calcCqiGap() {
                const target = parseFloat(document.getElementById('cqi_target').value) || 0;
                const attained = parseFloat(document.getElementById('cqi_attained').value) || 0;
                const gap = Math.max(0, target - attained);
                document.getElementById('cqi_gap').value = gap.toFixed(1);
            }
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const plannedLecturesList = <?= json_encode(array_values(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'lecture_number' => (int)$p['lecture_number'],
        'unit_number' => (int)$p['unit_number'],
        'co_number' => (int)$p['co_number']
    ];
}, $recon['planned_lectures'] ?? []))) ?>;

function autoSequenceMappings() {
    const selects = document.querySelectorAll('.diary-mapping-select');
    if (!selects.length || !plannedLecturesList.length) return;

    selects.forEach((sel, idx) => {
        if (idx < plannedLecturesList.length) {
            sel.value = plannedLecturesList[idx].id;
        } else {
            sel.value = "0"; // Compensatory for classes beyond planned count
        }
        sel.dispatchEvent(new Event('change'));
    });
}

function clearAllMappings() {
    const selects = document.querySelectorAll('.diary-mapping-select');
    selects.forEach(sel => {
        sel.value = "";
        sel.dispatchEvent(new Event('change'));
    });
}

function updateRowCoBadge(selectElem, diaryId) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    const coCell = document.getElementById('co-cell-' + diaryId);
    const typeCell = document.getElementById('type-cell-' + diaryId);

    if (!selectedOpt) return;

    const coNum = selectedOpt.getAttribute('data-co');
    const optType = selectedOpt.getAttribute('data-type');

    if (coCell) {
        if (coNum && coNum.trim() !== '') {
            coCell.innerHTML = '<span class="badge bg-success">CO' + coNum + '</span>';
        } else {
            coCell.innerHTML = '<span class="badge bg-secondary">-</span>';
        }
    }

    if (typeCell) {
        if (optType === 'compensatory') {
            typeCell.innerHTML = '<span class="badge bg-warning text-dark">Compensatory</span>';
        } else if (optType === 'planned') {
            typeCell.innerHTML = '<span class="badge bg-primary">Planned</span>';
        } else {
            typeCell.innerHTML = '<span class="badge bg-light text-muted border">Unmapped</span>';
        }
    }
}
</script>

<?php require_once("facfooter.php"); ?>

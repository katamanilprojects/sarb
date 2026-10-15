<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Security / Role Guard
if (empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role'] !== "faculty") {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_LESSON_PLAN');

require_once("faculty.class.php");
require_once("courseoutcome.class.php");
require_once("services/LessonPlanService.php");

$facultyObj = new Faculty();
$coObj = new CourseOutcome();
$lpService = \Services\LessonPlanService::getInstance();

$faculty_id = $_SESSION['facid'] ?? 0;
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = !empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : (!empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : null);

$msg = '';
$err = '';

// =========================================================================
// 1. Handle CSV Template Download (Clean HTTP stream BEFORE any HTML output)
// =========================================================================
if (isset($_GET['download_template']) && $selected_sub_id) {
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lesson_plan_template_sub' . $selected_sub_id . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM so Microsoft Excel opens it seamlessly without character corruption
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // CSV Header row
    fputcsv($out, [
        'Unit (1-5)',
        'Lecture No (1-60)',
        'Planned Topic Description',
        'Target CO Number (1-6)',
        'Bloom Level (L1-L6)',
        'Pedagogy',
        'Reference Material',
        'Planned Hours'
    ]);

    // Inspect available COs for this subject to provide accurate contextual guidance
    $cosRes = $coObj->getCOsBySubjectId($selected_sub_id);
    $availableCOs = $cosRes['data'] ?? [];
    $co1 = !empty($availableCOs[0]['co_number']) ? (int)$availableCOs[0]['co_number'] : 1;
    $co2 = !empty($availableCOs[1]['co_number']) ? (int)$availableCOs[1]['co_number'] : 1;
    $co3 = !empty($availableCOs[2]['co_number']) ? (int)$availableCOs[2]['co_number'] : 2;

    fputcsv($out, ['1', '1', 'Course Orientation & Syllabus Overview', $co1, 'L1-Remember', 'Chalk & Talk', 'Textbook 1: Ch 1', '1']);
    fputcsv($out, ['1', '2', 'Fundamental Concepts & Core Architectural Principles', $co1, 'L2-Understand', 'PPT/LCD', 'Textbook 1: Ch 1.2', '1']);
    fputcsv($out, ['1', '3', 'Mathematical Modeling & Analytical Techniques', $co2, 'L3-Apply', 'Chalk & Talk', 'Textbook 1: Ch 1.5', '1']);
    fputcsv($out, ['2', '4', 'Advanced Structural Models & Component Design', $co3, 'L3-Apply', 'PPT/LCD', 'Textbook 1: Ch 2.1', '1']);

    fclose($out);
    exit();
}

// =========================================================================
// 2. Handle Single Lecture Plan Addition / Update
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lecture']) && $selected_sub_id) {
    \FeatureManager::requireWriteAccess('MOD_LESSON_PLAN');
    $data = [
        'sub_id' => $selected_sub_id,
        'unit_number' => (int)($_POST['unit_number'] ?? 1),
        'lecture_number' => (int)($_POST['lecture_number'] ?? 1),
        'planned_topic' => trim($_POST['planned_topic'] ?? ''),
        'co_id' => (int)($_POST['co_id'] ?? 0),
        'bloom_level' => trim($_POST['bloom_level'] ?? 'L3-Apply'),
        'pedagogy' => trim($_POST['pedagogy'] ?? 'Chalk & Talk'),
        'reference_material' => trim($_POST['reference_material'] ?? ''),
        'planned_hours' => (int)($_POST['planned_hours'] ?? 1)
    ];

    if (!empty($data['planned_topic']) && $data['co_id'] > 0) {
        $res = $lpService->addOrUpdateLecturePlan($data);
        if ($res['status'] == 1) {
            $msg = "Lecture #" . $data['lecture_number'] . " saved successfully.";
        } else {
            $err = $res['error'] ?? "Failed to save lecture plan.";
        }
    } else {
        $err = "Topic description and target Course Outcome are required.";
    }
}

// =========================================================================
// 3. Handle Single Lecture Delete
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lecture_id']) && $selected_sub_id) {
    \FeatureManager::requireWriteAccess('MOD_LESSON_PLAN');
    $delId = (int)$_POST['delete_lecture_id'];
    $delRes = $lpService->deleteLecturePlan($delId, $selected_sub_id);
    if ($delRes['status'] == 1) {
        $msg = "Lecture plan entry deleted.";
    } else {
        $err = $delRes['error'] ?? "Failed to delete entry.";
    }
}

// =========================================================================
// 4. Handle Clear All Lectures
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_lectures']) && $selected_sub_id) {
    \FeatureManager::requireWriteAccess('MOD_LESSON_PLAN');
    $clearRes = $lpService->clearPlanBySubject($selected_sub_id);
    if ($clearRes['status'] == 1) {
        $msg = "All scheduled lecture plans for this course have been cleared.";
    } else {
        $err = $clearRes['error'] ?? "Failed to clear plan.";
    }
}

// =========================================================================
// 5. Handle Bulk CSV Upload (Robust Parser)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv']) && $selected_sub_id) {
    \FeatureManager::requireWriteAccess('MOD_LESSON_PLAN');
    if (!empty($_FILES['csv_file']['tmp_name']) && (is_uploaded_file($_FILES['csv_file']['tmp_name']) || (php_sapi_name() === 'cli' && file_exists($_FILES['csv_file']['tmp_name'])))) {
        $cosRes = $coObj->getCOsBySubjectId($selected_sub_id);
        $coNumToId = [];
        foreach ($cosRes['data'] ?? [] as $co) {
            $coNumToId[(int)$co['co_number']] = (int)$co['id'];
        }

        if (empty($coNumToId)) {
            $err = "Cannot import lesson plan: No Course Outcomes (COs) are configured for this course yet. Please define or import COs first under Course Outcomes.";
        } else {
            ini_set('auto_detect_line_endings', true);
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');

            // Skip header row
            $header = fgetcsv($file);
            $importedCount = 0;
            $skippedCount = 0;

            $normalizeBloom = function($input) {
                $b = strtoupper(trim((string)$input));
                if (str_starts_with($b, 'L1') || str_contains($b, 'REMEMBER')) return 'L1-Remember';
                if (str_starts_with($b, 'L2') || str_contains($b, 'UNDERSTAND')) return 'L2-Understand';
                if (str_starts_with($b, 'L3') || str_contains($b, 'APPLY')) return 'L3-Apply';
                if (str_starts_with($b, 'L4') || str_contains($b, 'ANALYZE')) return 'L4-Analyze';
                if (str_starts_with($b, 'L5') || str_contains($b, 'EVALUATE')) return 'L5-Evaluate';
                if (str_starts_with($b, 'L6') || str_contains($b, 'CREATE')) return 'L6-Create';
                return 'L3-Apply';
            };

            while (($row = fgetcsv($file, 2000, ',')) !== false) {
                // Strip UTF-8 BOM from first cell if present
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                }

                // Skip blank lines
                if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                    continue;
                }

                // Extract unit (accepts "1", "Unit 1", "U1")
                $unit = (int)preg_replace('/[^0-9]/', '', (string)($row[0] ?? '1'));
                if ($unit <= 0) $unit = 1;

                // Extract lecture number (accepts "1", "Lec 1", "Lecture 1")
                $lecNo = (int)preg_replace('/[^0-9]/', '', (string)($row[1] ?? '0'));
                $topic = trim((string)($row[2] ?? ''));

                if ($lecNo <= 0 || empty($topic)) {
                    $skippedCount++;
                    continue;
                }

                // Extract target CO number (accepts "1", "CO1", "CO 1")
                $coNum = (int)preg_replace('/[^0-9]/', '', (string)($row[3] ?? '1'));
                if ($coNum <= 0) $coNum = 1;

                $bloom = !empty($row[4]) ? $normalizeBloom($row[4]) : 'L3-Apply';
                $pedagogy = !empty($row[5]) ? trim((string)$row[5]) : 'Chalk & Talk';
                $ref = !empty($row[6]) ? trim((string)$row[6]) : '';
                $hours = !empty($row[7]) ? (int)preg_replace('/[^0-9]/', '', (string)$row[7]) : 1;
                if ($hours <= 0) $hours = 1;

                $coId = $coNumToId[$coNum] ?? (reset($coNumToId) ?: 0);
                if ($coId > 0) {
                    $saveRes = $lpService->addOrUpdateLecturePlan([
                        'sub_id' => $selected_sub_id,
                        'unit_number' => $unit,
                        'lecture_number' => $lecNo,
                        'planned_topic' => $topic,
                        'co_id' => $coId,
                        'bloom_level' => $bloom,
                        'pedagogy' => $pedagogy,
                        'reference_material' => $ref,
                        'planned_hours' => $hours
                    ]);
                    if (!empty($saveRes['status'])) {
                        $importedCount++;
                    }
                }
            }
            fclose($file);

            if ($importedCount > 0) {
                $msg = "Bulk upload successful: {$importedCount} lecture plan(s) imported/updated." . ($skippedCount > 0 ? " ({$skippedCount} blank/invalid rows skipped)." : "");
            } else {
                $err = "No valid lectures were imported from the CSV. Please ensure columns follow the template format (Unit, Lecture No, Topic Description, CO No).";
            }
        }
    } else {
        $err = "Please select a valid CSV file.";
    }
}

// =========================================================================
// 6. Load Subject Data for View
// =========================================================================
$subjectDetails = null;
$lessonPlan = [];
$subjectCOs = [];
$varianceStats = null;

if ($selected_sub_id) {
    $subjectDetails = $facultyObj->getSubjectDetails($selected_sub_id);
    $lessonPlan = $lpService->getPlanBySubject($selected_sub_id);
    $coRes = $coObj->getCOsBySubjectId($selected_sub_id);
    $subjectCOs = $coRes['data'] ?? [];
    $varianceStats = $lpService->getSyllabusVariance($selected_sub_id);
}

// Now include page header
$page_title = "Lesson Plan (Estimated Delivery Schedule)";
require_once("facheader.php");
?>

<div class="container my-4">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_LESSON_PLAN'); ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Course Delivery Plan / Lesson Plan (Estimated Diary)</h5>
            <?php if ($selected_sub_id): ?>
                <div>
                    <a href="faclessonplanreconciliation.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary me-1">
                        <i class="bi bi-clipboard-data me-1"></i>Delivery Audit & Reconciliation
                    </a>
                    <a href="facaddattendance.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-clipboard-check me-1"></i>Mark Attendance
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="facaddlessonplan.php" method="get" class="row g-3 align-items-end mb-3">
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
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Load Plan</button>
                </div>
            </form>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_sub_id): ?>
        <!-- Pacing & Variance Metrics (NBA Criterion 2.1 & 2.2) -->
        <?php if (!empty($varianceStats)): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-primary text-center p-3">
                        <h6 class="text-muted mb-1">Planned Lectures</h6>
                        <h3 class="text-primary mb-0"><?= $varianceStats['total_planned_lectures'] ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success text-center p-3">
                        <h6 class="text-muted mb-1">Conducted Periods</h6>
                        <h3 class="text-success mb-0"><?= $varianceStats['actual_conducted_lectures'] ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info text-center p-3">
                        <h6 class="text-muted mb-1">Syllabus Completion</h6>
                        <h3 class="text-info mb-0"><?= $varianceStats['completion_percentage'] ?>%</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning text-center p-3">
                        <h6 class="text-muted mb-1">COs Covered in Diary</h6>
                        <h3 class="text-warning mb-0"><?= $varianceStats['cos_covered_count'] ?> / <?= count($subjectCOs) ?></h3>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Tools: Single Add/Edit + Bulk Upload -->
        <div class="row mb-4">
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light fw-bold" id="lp_form_title">
                        <i class="bi bi-plus-circle me-1"></i>Add / Edit Lecture Plan Entry
                    </div>
                    <div class="card-body">
                        <?php if (empty($subjectCOs)): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-info-circle me-1"></i>No Course Outcomes configured yet. Please configure COs in <a href="facaddcos.php?sub_id=<?= $selected_sub_id ?>" class="alert-link">Course Outcomes Setup</a> first.
                            </div>
                        <?php else: ?>
                            <form action="facaddlessonplan.php" method="post" class="row g-2">
                                <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Unit # (1-5):</label>
                                    <select name="unit_number" id="lp_unit_number" class="form-select form-select-sm" required>
                                        <?php for ($u = 1; $u <= 5; $u++): ?>
                                            <option value="<?= $u ?>">Unit <?= $u ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Lecture #:</label>
                                    <input type="number" name="lecture_number" id="lp_lecture_number" class="form-control form-control-sm" min="1" max="100" value="<?= count($lessonPlan) + 1 ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Target Course Outcome:</label>
                                    <select name="co_id" id="lp_co_id" class="form-select form-select-sm" required>
                                        <?php foreach ($subjectCOs as $co): ?>
                                            <option value="<?= $co['id'] ?>">CO<?= $co['co_number'] ?> - <?= htmlspecialchars(substr($co['co_description'], 0, 45)) ?>...</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Planned Syllabus Topic / Concept:</label>
                                    <input type="text" name="planned_topic" id="lp_planned_topic" class="form-control form-control-sm" placeholder="e.g. Relational Algebra operations, SQL Joins" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Bloom's Level:</label>
                                    <select name="bloom_level" id="lp_bloom_level" class="form-select form-select-sm">
                                        <option value="L1-Remember">L1 - Remember</option>
                                        <option value="L2-Understand">L2 - Understand</option>
                                        <option value="L3-Apply" selected>L3 - Apply</option>
                                        <option value="L4-Analyze">L4 - Analyze</option>
                                        <option value="L5-Evaluate">L5 - Evaluate</option>
                                        <option value="L6-Create">L6 - Create</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Teaching Pedagogy:</label>
                                    <select name="pedagogy" id="lp_pedagogy" class="form-select form-select-sm">
                                        <option value="Chalk & Talk" selected>Chalk & Talk</option>
                                        <option value="PPT/LCD">PPT / Projector</option>
                                        <option value="Coding Demo">Live Coding Demo</option>
                                        <option value="Group Discussion">Group Discussion</option>
                                        <option value="Flipped Classroom">Flipped Classroom</option>
                                        <option value="Video/Animation">Video / Animation</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Reference Material:</label>
                                    <input type="text" name="reference_material" id="lp_reference_material" class="form-control form-control-sm" placeholder="e.g. T1: Ch 2.3, R1: Sec 4">
                                </div>
                                <input type="hidden" name="planned_hours" id="lp_planned_hours" value="1">
                                <div class="col-12 mt-3 d-flex gap-2">
                                    <button type="submit" name="save_lecture" class="btn btn-sm btn-primary flex-grow-1">
                                        <i class="bi bi-save me-1"></i>Save Lecture Plan
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetLectureForm()">
                                        Clear
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bulk CSV Import -->
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light fw-bold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Bulk Excel / CSV Import</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Import pre-formatted lecture plans for the entire semester (45-50 lectures) in one upload.</p>
                        <a href="facaddlessonplan.php?sub_id=<?= $selected_sub_id ?>&download_template=1" class="btn btn-sm btn-outline-success mb-3 w-100">
                            <i class="bi bi-download me-1"></i>Download CSV Template
                        </a>
                        <form action="facaddlessonplan.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Upload Completed CSV:</label>
                                <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                            </div>
                            <button type="submit" name="upload_csv" class="btn btn-sm btn-success w-100">
                                <i class="bi bi-upload me-1"></i>Upload & Populate Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled Lesson Plan Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-list-ol me-1"></i>Scheduled Course Delivery Plan (<?= count($lessonPlan) ?> Lectures)</span>
                <?php if (!empty($lessonPlan)): ?>
                    <form action="facaddlessonplan.php" method="post" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete ALL <?= count($lessonPlan) ?> planned lectures for this course? This action cannot be undone.');">
                        <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                        <button type="submit" name="clear_all_lectures" value="1" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash3 me-1"></i>Clear All Lectures
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 50px;">Lec #</th>
                            <th style="width: 70px;">Unit</th>
                            <th>Planned Topic Description</th>
                            <th style="width: 80px;">CO</th>
                            <th style="width: 110px;">Bloom</th>
                            <th style="width: 120px;">Pedagogy</th>
                            <th>Reference</th>
                            <th style="width: 85px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lessonPlan)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                                    No lesson delivery schedule entered yet. Add lectures above or use the CSV import.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lessonPlan as $lp): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $lp['lecture_number'] ?></td>
                                    <td class="text-center"><span class="badge bg-secondary">Unit <?= $lp['unit_number'] ?></span></td>
                                    <td><?= htmlspecialchars($lp['planned_topic']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary">CO<?= $lp['co_number'] ?></span></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($lp['bloom_level']) ?></span></td>
                                    <td><small><?= htmlspecialchars($lp['pedagogy']) ?></small></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($lp['reference_material'] ?? '-') ?></small></td>
                                    <td class="text-center text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary p-1 me-1" title="Edit Lecture" onclick="editLecturePlan(<?= $lp['unit_number'] ?>, <?= $lp['lecture_number'] ?>, <?= $lp['co_id'] ?>, <?= htmlspecialchars(json_encode($lp['planned_topic']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($lp['bloom_level']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($lp['pedagogy']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($lp['reference_material'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= (int)($lp['planned_hours'] ?? 1) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="facaddlessonplan.php" method="post" class="d-inline" onsubmit="return confirm('Delete Lecture #<?= $lp['lecture_number'] ?>?');">
                                            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                                            <input type="hidden" name="delete_lecture_id" value="<?= $lp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete Lecture"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function editLecturePlan(unit, lecNum, coId, topic, bloom, pedagogy, ref, hours) {
    const uEl = document.getElementById('lp_unit_number');
    const lEl = document.getElementById('lp_lecture_number');
    const cEl = document.getElementById('lp_co_id');
    const tEl = document.getElementById('lp_planned_topic');
    const bEl = document.getElementById('lp_bloom_level');
    const pEl = document.getElementById('lp_pedagogy');
    const rEl = document.getElementById('lp_reference_material');
    const hEl = document.getElementById('lp_planned_hours');

    if (uEl) uEl.value = unit;
    if (lEl) lEl.value = lecNum;
    if (cEl) cEl.value = coId;
    if (tEl) tEl.value = topic;
    if (bEl) bEl.value = bloom;
    if (pEl) pEl.value = pedagogy;
    if (rEl) rEl.value = ref;
    if (hEl) hEl.value = hours;

    const titleEl = document.getElementById('lp_form_title');
    if (titleEl) titleEl.innerHTML = '<i class="bi bi-pencil-square text-warning me-1"></i>Edit Lecture #' + lecNum;

    if (tEl) {
        tEl.focus();
        tEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function resetLectureForm() {
    const tEl = document.getElementById('lp_planned_topic');
    const rEl = document.getElementById('lp_reference_material');
    const lEl = document.getElementById('lp_lecture_number');
    const titleEl = document.getElementById('lp_form_title');

    if (tEl) tEl.value = '';
    if (rEl) rEl.value = '';
    if (titleEl) titleEl.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add / Edit Lecture Plan Entry';
}
</script>

<?php require_once("facfooter.php"); ?>

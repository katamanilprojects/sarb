<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Regulation Details";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

$regId = (int)($_GET['reg_id'] ?? $_POST['reg_id'] ?? 0);
if ($regId <= 0) {
    header('Location: superadminregulations.php');
    exit();
}

$regRes = $superadmin->getRegulationById($regId);
if (empty($regRes['status']) || empty($regRes['data'])) {
    header('Location: superadminregulations.php');
    exit();
}
$regulation = $regRes['data'];

$succMsg = '';
$errMsg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Course Categories
    if ($action === 'save_category') {
        $catData = [
            'id' => (int)($_POST['id'] ?? 0),
            'reg_id' => $regId,
            'category_code' => $_POST['category_code'] ?? '',
            'category_name' => $_POST['category_name'] ?? '',
            'min_allocation_pct' => (float)($_POST['min_allocation_pct'] ?? 0.0),
            'max_allocation_pct' => (float)($_POST['max_allocation_pct'] ?? 0.0),
            'target_credits' => (float)($_POST['target_credits'] ?? 0.0),
            'description' => $_POST['description'] ?? ''
        ];
        $res = $superadmin->saveCourseCategory($catData);
        if (!empty($res['status'])) {
            $succMsg = $res['message'] ?? 'Category saved successfully.';
        } else {
            $errMsg = $res['error'] ?? 'Failed to save category.';
        }
    } elseif ($action === 'delete_category') {
        $catId = (int)($_POST['id'] ?? 0);
        $res = $superadmin->deleteCourseCategory($catId, $regId);
        if (!empty($res['status'])) {
            $succMsg = $res['message'] ?? 'Category deleted successfully.';
        } else {
            $errMsg = $res['error'] ?? 'Failed to delete category.';
        }
    }

    // 2. Course Types
    elseif ($action === 'save_type') {
        $typeData = [
            'id' => (int)($_POST['id'] ?? 0),
            'reg_id' => $regId,
            'type_code' => $_POST['type_code'] ?? '',
            'type_name' => $_POST['type_name'] ?? '',
            'evaluation_scheme' => $_POST['evaluation_scheme'] ?? 'THEORY',
            'cie_max_marks' => (float)($_POST['cie_max_marks'] ?? 30.0),
            'see_max_marks' => (float)($_POST['see_max_marks'] ?? 70.0),
            'total_marks' => (float)($_POST['total_marks'] ?? 100.0),
            'has_see' => isset($_POST['has_see']) ? 1 : 0,
            'is_credit_course' => isset($_POST['is_credit_course']) ? 1 : 0,
            'description' => $_POST['description'] ?? ''
        ];
        $res = $superadmin->saveCourseType($typeData);
        if (!empty($res['status'])) {
            $succMsg = $res['message'] ?? 'Course type saved successfully.';
        } else {
            $errMsg = $res['error'] ?? 'Failed to save course type.';
        }
    } elseif ($action === 'delete_type') {
        $typeId = (int)($_POST['id'] ?? 0);
        $res = $superadmin->deleteCourseType($typeId, $regId);
        if (!empty($res['status'])) {
            $succMsg = $res['message'] ?? 'Course type deleted successfully.';
        } else {
            $errMsg = $res['error'] ?? 'Failed to delete course type.';
        }
    }

    // 3. Statutory Boundaries
    elseif ($action === 'save_boundaries') {
        $boundData = [
            'id' => $regId,
            'regulation' => $regulation['regulation'],
            'prog_id' => $regulation['prog_id'],
            'start_year' => !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null,
            'normal_duration_years' => (int)($_POST['normal_duration_years'] ?? 4),
            'max_duration_years' => (int)($_POST['max_duration_years'] ?? 8),
            'gap_year_extension_years' => (int)($_POST['gap_year_extension_years'] ?? 0),
            'total_semesters' => (int)($_POST['total_semesters'] ?? 8),
            'total_degree_credits' => (float)($_POST['total_degree_credits'] ?? 160.0),
            'lateral_entry_credits' => (float)($_POST['lateral_entry_credits'] ?? 0.0),
            'honors_credits' => (float)($_POST['honors_credits'] ?? 0.0),
            'minor_credits' => (float)($_POST['minor_credits'] ?? 0.0),
            'has_lateral_entry' => isset($_POST['has_lateral_entry']) ? 1 : 0,
            'has_honors' => isset($_POST['has_honors']) ? 1 : 0,
            'has_minors' => isset($_POST['has_minors']) ? 1 : 0,
            'has_gap_year' => isset($_POST['has_gap_year']) ? 1 : 0,
            'has_internal_improvement' => isset($_POST['has_internal_improvement']) ? 1 : 0,
            'effective_admitted_batch' => trim($_POST['effective_admitted_batch'] ?? ''),
            'les_effective_batch' => trim($_POST['les_effective_batch'] ?? '')
        ];
        $res = $superadmin->addOrUpdateRegulation($boundData);
        if (!empty($res['status'])) {
            $succMsg = 'Statutory degree boundaries updated successfully.';
            $regulation = $superadmin->getRegulationById($regId)['data'] ?? $regulation;
        } else {
            $errMsg = $res['error'] ?? 'Failed to update boundaries.';
        }
    }

    // 4. Clone From Another Regulation
    elseif ($action === 'clone_from') {
        $sourceRegId = (int)($_POST['source_reg_id'] ?? 0);
        $res = $superadmin->cloneRegulation($sourceRegId, $regId);
        if (!empty($res['status'])) {
            $succMsg = $res['message'] ?? 'Cloned successfully.';
        } else {
            $errMsg = $res['error'] ?? 'Failed to clone.';
        }
    }
}

// Fetch all regulations for cloning option
$allRegulations = $superadmin->getAllRegulations()['data'] ?? [];

// Fetch current categories & types
$categories = $superadmin->getCourseCategoriesByRegId($regId)['data'] ?? [];
$courseTypes = $superadmin->getCourseTypesByRegId($regId)['data'] ?? [];

// Calculate total target credits & allocation percentages
$totalTargetCredits = 0.0;
$totalMinPct = 0.0;
$totalMaxPct = 0.0;
foreach ($categories as $c) {
    $totalTargetCredits += (float)($c['target_credits'] ?? 0.0);
    $totalMinPct += (float)($c['min_allocation_pct'] ?? 0.0);
    $totalMaxPct += (float)($c['max_allocation_pct'] ?? 0.0);
}

// Active tab
$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'categories');
?>

<div class="container-fluid px-4 py-4">
    <!-- Top Breadcrumb and Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="superadminregulations.php">Regulations</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($regulation['regulation']) ?> (<?= htmlspecialchars($regulation['prog_shortname']) ?>)</li>
                </ol>
            </nav>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-gear-wide-connected text-primary me-2"></i>
                Regulation <?= htmlspecialchars($regulation['regulation']) ?> - Course Categories & Evaluation Types
            </h4>
            <span class="text-muted small">
                Program: <strong><?= htmlspecialchars($regulation['prog_fullname'] . ' (' . $regulation['prog_shortname'] . ')') ?></strong>
            </span>
        </div>
        <div>
            <a href="superadminregulations.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="bi bi-arrow-left me-1"></i>Back to Regulations
            </a>
            <a href="superadminacademicsettings.php?reg=<?= urlencode($regulation['regulation']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-sliders me-1"></i>Academic Settings Engine
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($succMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($succMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($errMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Statutory Credits</div>
                            <h3 class="fw-bold mb-0"><?= number_format((float)$regulation['total_degree_credits'], 1) ?></h3>
                            <div class="small mt-1 text-white-70">
                                Target Sum: <?= number_format($totalTargetCredits, 1) ?>
                                <?php if (abs($totalTargetCredits - (float)$regulation['total_degree_credits']) < 0.05): ?>
                                    <span class="badge bg-white text-primary ms-1"><i class="bi bi-check-circle"></i> Balanced</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle"></i> Reconcile</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="bi bi-award fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Course Categories</div>
                            <h3 class="fw-bold mb-0"><?= count($categories) ?></h3>
                            <div class="small mt-1 text-white-70">Alloc: <?= number_format($totalMinPct, 1) ?>% - <?= number_format($totalMaxPct, 1) ?>%</div>
                        </div>
                        <i class="bi bi-tags fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Course Types & Schemes</div>
                            <h3 class="fw-bold mb-0"><?= count($courseTypes) ?></h3>
                            <div class="small mt-1 text-white-70">Theory, Lab, Projects, etc.</div>
                        </div>
                        <i class="bi bi-ui-checks-grid fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-dark bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Degree Duration</div>
                            <h3 class="fw-bold mb-0"><?= (int)$regulation['normal_duration_years'] ?>y / <?= (int)$regulation['total_semesters'] ?>s</h3>
                            <div class="small mt-1 text-white-70">Max <?= (int)$regulation['max_duration_years'] ?>y | Gap +<?= (int)$regulation['gap_year_extension_years'] ?>y</div>
                        </div>
                        <i class="bi bi-clock-history fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab === 'boundaries') ? 'active' : '' ?> px-3" id="pills-boundaries-tab" data-bs-toggle="pill" data-bs-target="#pills-boundaries" type="button" role="tab">
                <i class="bi bi-bounding-box-circles me-1"></i>Statutory Degree Boundaries
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab === 'categories') ? 'active' : '' ?> px-3" id="pills-categories-tab" data-bs-toggle="pill" data-bs-target="#pills-categories" type="button" role="tab">
                <i class="bi bi-tags me-1"></i>Course Categories & Allocation (%)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab === 'types') ? 'active' : '' ?> px-3" id="pills-types-tab" data-bs-toggle="pill" data-bs-target="#pills-types" type="button" role="tab">
                <i class="bi bi-card-checklist me-1"></i>Course Types & Evaluation Schemes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= ($activeTab === 'clone') ? 'active' : '' ?> px-3" id="pills-clone-tab" data-bs-toggle="pill" data-bs-target="#pills-clone" type="button" role="tab">
                <i class="bi bi-copy me-1"></i>Clone Categories & Schemes
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- =============================================================== -->
        <!-- TAB 1: STATUTORY DEGREE BOUNDARIES -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'boundaries') ? 'show active' : '' ?>" id="pills-boundaries" role="tabpanel">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-shield-check me-2"></i>Statutory Regulation Boundaries & Feature Controls</h5>
                    <small class="text-muted">Degree ceilings, graduation duration, credit limits, and statutory pathway switches (B.Tech R23 / M.Tech R25).</small>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="superadminregulationdetails.php?reg_id=<?= $regId ?>">
                        <input type="hidden" name="action" value="save_boundaries">
                        <input type="hidden" name="reg_id" value="<?= $regId ?>">
                        <input type="hidden" name="tab" value="boundaries">

                        <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-calendar3 me-1"></i>Degree Durations & Semesters</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Normal Duration (Years) <span class="text-danger">*</span></label>
                                <input type="number" name="normal_duration_years" class="form-control" value="<?= (int)($regulation['normal_duration_years'] ?? 4) ?>" min="1" max="6" required>
                                <div class="form-text">e.g. 4 for B.Tech, 2 for M.Tech/MCA</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Maximum Duration (Years) <span class="text-danger">*</span></label>
                                <input type="number" name="max_duration_years" class="form-control" value="<?= (int)($regulation['max_duration_years'] ?? 8) ?>" min="1" max="12" required>
                                <div class="form-text">2N rule: 8 for B.Tech, 4 for M.Tech</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Gap Year Extension (Years)</label>
                                <input type="number" name="gap_year_extension_years" class="form-control" value="<?= (int)($regulation['gap_year_extension_years'] ?? 0) ?>" min="0" max="4">
                                <div class="form-text">e.g. 2 for B.Tech (Incubation/Startup), 0 for PG</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Total Semesters <span class="text-danger">*</span></label>
                                <input type="number" name="total_semesters" class="form-control" value="<?= (int)($regulation['total_semesters'] ?? 8) ?>" min="1" max="12" required>
                                <div class="form-text">8 for UG, 4 for PG</div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-award me-1"></i>Credit Ceilings & Degree Pathways</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Total Degree Credits <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" name="total_degree_credits" class="form-control" value="<?= (float)($regulation['total_degree_credits'] ?? 160.0) ?>" min="10" max="300" required>
                                <div class="form-text">B.Tech R23: 163.0, M.Tech R25: 75.0</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Lateral Entry Scheme (LES) Credits</label>
                                <input type="number" step="0.5" name="lateral_entry_credits" class="form-control" value="<?= (float)($regulation['lateral_entry_credits'] ?? 0.0) ?>" min="0" max="250">
                                <div class="form-text">B.Tech R23 LES: 120.0 credits</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Honors Degree Additional Credits</label>
                                <input type="number" step="0.5" name="honors_credits" class="form-control" value="<?= (float)($regulation['honors_credits'] ?? 0.0) ?>" min="0" max="50">
                                <div class="form-text">B.Tech R23: 15.0 credits</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Minor Degree Additional Credits</label>
                                <input type="number" step="0.5" name="minor_credits" class="form-control" value="<?= (float)($regulation['minor_credits'] ?? 0.0) ?>" min="0" max="50">
                                <div class="form-text">B.Tech R23: 12.0 credits</div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-toggle2-on me-1"></i>Statutory Academic Pathways & Feature Switches</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card h-100 p-3 bg-light border">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="has_lateral_entry" id="has_lateral_entry" value="1" <?= !empty($regulation['has_lateral_entry']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="has_lateral_entry">Lateral Entry Scheme (LES)</label>
                                    </div>
                                    <small class="text-muted">Allows direct entry to 2nd year (3rd semester) for diploma/B.Sc holders.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 p-3 bg-light border">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="has_honors" id="has_honors" value="1" <?= !empty($regulation['has_honors']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="has_honors">Honors Degree Pathway</label>
                                    </div>
                                    <small class="text-muted">Allows eligible students with CGPA &ge; 8.0 without backlogs to pursue honors courses.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 p-3 bg-light border">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="has_minors" id="has_minors" value="1" <?= !empty($regulation['has_minors']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="has_minors">Minor Degree Pathway</label>
                                    </div>
                                    <small class="text-muted">Allows students to earn an interdisciplinary minor track outside parent discipline.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 p-3 bg-light border">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="has_gap_year" id="has_gap_year" value="1" <?= !empty($regulation['has_gap_year']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="has_gap_year">Gap Year for Entrepreneurship / Incubation</label>
                                    </div>
                                    <small class="text-muted">Permits students to take 1-2 gap years for startup incubation without academic penalty (Section 2.g).</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 p-3 bg-light border">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="has_internal_improvement" id="has_internal_improvement" value="1" <?= !empty($regulation['has_internal_improvement']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="has_internal_improvement">Internal Improvement Re-registration</label>
                                    </div>
                                    <small class="text-muted">Permits PG students to re-register for subjects with internal marks &lt; 50% (M.Tech R25 Section 4).</small>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-people me-1"></i>Batch Applicability & Calendar</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Start Year</label>
                                <input type="number" name="start_year" class="form-control" value="<?= htmlspecialchars((string)($regulation['start_year'] ?? '')) ?>" placeholder="e.g. 2023">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Regular Effective Batch</label>
                                <input type="text" name="effective_admitted_batch" class="form-control" value="<?= htmlspecialchars((string)($regulation['effective_admitted_batch'] ?? '')) ?>" placeholder="e.g. 2023-24 onwards">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Lateral Entry Effective Batch</label>
                                <input type="text" name="les_effective_batch" class="form-control" value="<?= htmlspecialchars((string)($regulation['les_effective_batch'] ?? '')) ?>" placeholder="e.g. 2024-25 onwards">
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-save me-1"></i>Save Statutory Boundaries
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 2: COURSE CATEGORIES -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'categories') ? 'show active' : '' ?>" id="pills-categories" role="tabpanel">
            <?php
            $degCredits = (float)($regulation['total_degree_credits'] ?? 0.0);
            $diffCredits = round($totalTargetCredits - $degCredits, 2);
            ?>
            <div class="p-3 mb-3 rounded border <?= (abs($diffCredits) < 0.05 ? 'bg-success-subtle border-success' : ($diffCredits > 0 ? 'bg-danger-subtle border-danger' : 'bg-warning-subtle border-warning')) ?>">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fw-bold">
                            <?php if (abs($diffCredits) < 0.05): ?>
                                <i class="bi bi-check-circle-fill text-success me-2"></i>Statutory Credit Reconciliation: Compliant
                            <?php elseif ($diffCredits > 0): ?>
                                <i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>Statutory Credit Reconciliation: Excess by <?= abs($diffCredits) ?> Credits
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Statutory Credit Reconciliation: Deficit by <?= abs($diffCredits) ?> Credits
                            <?php endif; ?>
                        </h6>
                        <span class="small text-muted">
                            Statutory Degree Limit: <strong><?= number_format($degCredits, 1) ?> credits</strong> | 
                            Sum of Category Targets: <strong><?= number_format($totalTargetCredits, 1) ?> credits</strong> | 
                            Min Alloc Sum: <strong><?= number_format($totalMinPct, 1) ?>%</strong> | 
                            Max Alloc Sum: <strong><?= number_format($totalMaxPct, 1) ?>%</strong>
                        </span>
                    </div>
                    <div>
                        <?php if (abs($diffCredits) < 0.05): ?>
                            <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>100% Balanced</span>
                        <?php elseif ($diffCredits > 0): ?>
                            <span class="badge bg-danger fs-6">+<?= abs($diffCredits) ?> Cr Excess</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark fs-6">-<?= abs($diffCredits) ?> Cr Deficit</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-tags me-2"></i>Curriculum Course Categories</h5>
                        <small class="text-muted">Define statutory course categories (BS, ES, PC, PE, etc.) and mandated percentage / credit limits for <?= htmlspecialchars($regulation['regulation']) ?>.</small>
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openNewCategoryModal()">
                        <i class="bi bi-plus-circle me-1"></i>Add Course Category
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Code</th>
                                    <th>Category Name</th>
                                    <th class="text-center" style="width: 150px;">Allocation % Range</th>
                                    <th class="text-center" style="width: 130px;">Target Credits</th>
                                    <th>Description / Regulatory Notes</th>
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                            No course categories defined yet for <?= htmlspecialchars($regulation['regulation']) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary fs-6 px-2 py-1"><?= htmlspecialchars($cat['category_code']) ?></span>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <?= htmlspecialchars($cat['category_name']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">
                                                    <?= number_format((float)$cat['min_allocation_pct'], 1) ?>% - <?= number_format((float)$cat['max_allocation_pct'], 1) ?>%
                                                </span>
                                            </td>
                                            <td class="text-center fw-bold text-primary">
                                                <?= number_format((float)$cat['target_credits'], 1) ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars($cat['description'] ?? '—') ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-outline-primary btn-sm py-0 px-2 me-1" 
                                                        onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" title="Edit Category">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete course category <?= htmlspecialchars($cat['category_code']) ?>?');">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="reg_id" value="<?= $regId ?>">
                                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                    <input type="hidden" name="tab" value="categories">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Category">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 2: COURSE TYPES & EVALUATION SCHEMES -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'types') ? 'show active' : '' ?>" id="pills-types" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-card-checklist me-2"></i>Curriculum Course Types & Evaluation Schemes</h5>
                        <small class="text-muted">Configure Course Types (Theory, Lab, Project, Mandatory, etc.) with autonomous CIE/SEE mark distributions and scheme engines for <?= htmlspecialchars($regulation['regulation']) ?>.</small>
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#typeModal" onclick="openNewTypeModal()">
                        <i class="bi bi-plus-circle me-1"></i>Add Course Type
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 140px;">Type Code</th>
                                    <th>Full Name</th>
                                    <th style="width: 160px;">Evaluation Scheme</th>
                                    <th class="text-center" style="width: 180px;">Marks Breakdown (CIE + SEE = Total)</th>
                                    <th class="text-center" style="width: 120px;">Has SEE?</th>
                                    <th class="text-center" style="width: 120px;">Credits?</th>
                                    <th>Description / Notes</th>
                                    <th class="text-center" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courseTypes)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                            No course types defined yet for <?= htmlspecialchars($regulation['regulation']) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courseTypes as $t): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <span class="badge bg-secondary fs-6 px-2 py-1"><?= htmlspecialchars($t['type_code']) ?></span>
                                            </td>
                                            <td class="fw-semibold text-dark">
                                                <?= htmlspecialchars($t['type_name']) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $schemeClass = match($t['evaluation_scheme']) {
                                                    'THEORY' => 'bg-info text-dark',
                                                    'LAB' => 'bg-success text-white',
                                                    'INTEGRATED' => 'bg-primary text-white',
                                                    'PROJECT' => 'bg-warning text-dark',
                                                    'AUDIT_NON_CREDIT' => 'bg-secondary text-white',
                                                    default => 'bg-light text-dark border'
                                                };
                                                ?>
                                                <span class="badge <?= $schemeClass ?>"><?= htmlspecialchars($t['evaluation_scheme']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">
                                                    <strong><?= number_format((float)$t['cie_max_marks'], 0) ?></strong> CIE + 
                                                    <strong><?= number_format((float)$t['see_max_marks'], 0) ?></strong> SEE = 
                                                    <span class="text-primary fw-bold"><?= number_format((float)$t['total_marks'], 0) ?></span>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($t['has_see'])): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="bi bi-x-circle me-1"></i>No (Internal)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($t['is_credit_course'])): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-check-circle me-1"></i>Credit</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle">Non-Credit</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars($t['description'] ?? '—') ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-outline-primary btn-sm py-0 px-2 me-1" 
                                                        onclick="editCourseType(<?= htmlspecialchars(json_encode($t)) ?>)" title="Edit Type">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete course type <?= htmlspecialchars($t['type_code']) ?>?');">
                                                    <input type="hidden" name="action" value="delete_type">
                                                    <input type="hidden" name="reg_id" value="<?= $regId ?>">
                                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                    <input type="hidden" name="tab" value="types">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Type">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- TAB 4: CLONE CATEGORIES & SCHEMES -->
        <!-- =============================================================== -->
        <div class="tab-pane fade <?= ($activeTab === 'clone') ? 'show active' : '' ?>" id="pills-clone" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-copy me-2"></i>Clone Categories & Evaluation Schemes</h5>
                    <small class="text-muted">Quickly replicate all course categories (BS, ES, PC, etc.) and evaluation types (Theory, Lab, Project, etc.) from an existing regulation into <?= htmlspecialchars($regulation['regulation']) ?>.</small>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info border-info d-flex align-items-center mb-4">
                        <i class="bi bi-info-circle fs-3 me-3 text-info"></i>
                        <div>
                            <strong>How Cloning Works:</strong><br>
                            Categories and course types from the source regulation will be duplicated and assigned to <strong><?= htmlspecialchars($regulation['regulation']) ?> (<?= htmlspecialchars($regulation['prog_shortname']) ?>)</strong>. 
                            If a category or course type code already exists in <?= htmlspecialchars($regulation['regulation']) ?>, it will be safely preserved.
                        </div>
                    </div>

                    <form method="POST" action="superadminregulationdetails.php?reg_id=<?= $regId ?>" onsubmit="return confirm('Are you sure you want to clone categories and course types from the selected regulation?');">
                        <input type="hidden" name="action" value="clone_from">
                        <input type="hidden" name="reg_id" value="<?= $regId ?>">
                        <input type="hidden" name="tab" value="categories">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Source Regulation to Copy From <span class="text-danger">*</span></label>
                                <select name="source_reg_id" class="form-select" required>
                                    <option value="">-- Choose Source Regulation --</option>
                                    <?php foreach ($allRegulations as $r): ?>
                                        <?php if ((int)$r['id'] === $regId) continue; ?>
                                        <option value="<?= $r['id'] ?>">
                                            <?= htmlspecialchars($r['regulation'] . ' - ' . $r['prog_fullname'] . ' (' . $r['prog_shortname'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning px-4 fw-bold text-dark">
                            <i class="bi bi-copy me-1"></i>Execute Clone Operation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal 1: Course Category (Add / Edit) -->
<!-- ========================================================================= -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="superadminregulationdetails.php?reg_id=<?= $regId ?>" class="modal-content">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="reg_id" value="<?= $regId ?>">
            <input type="hidden" name="id" id="cat_id" value="0">
            <input type="hidden" name="tab" value="categories">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="categoryModalLabel"><i class="bi bi-tag me-2"></i>Add Course Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Category Code <span class="text-danger">*</span></label>
                        <input type="text" name="category_code" id="cat_code" class="form-control text-uppercase" placeholder="e.g. BS" maxlength="20" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold small">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="cat_name" class="form-control" placeholder="e.g. Basic Science" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Min Alloc %</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="min_allocation_pct" id="cat_min_pct" class="form-control" value="0.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Max Alloc %</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="max_allocation_pct" id="cat_max_pct" class="form-control" value="0.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Target Credits</label>
                        <input type="number" step="0.5" min="0" max="200" name="target_credits" id="cat_credits" class="form-control" value="0.0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Description / Regulatory Notes</label>
                        <textarea name="description" id="cat_desc" class="form-control" rows="2" placeholder="e.g. Mathematics, Physics, Chemistry courses"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="catSubmitBtn">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal 2: Course Type (Add / Edit) -->
<!-- ========================================================================= -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="superadminregulationdetails.php?reg_id=<?= $regId ?>" class="modal-content">
            <input type="hidden" name="action" value="save_type">
            <input type="hidden" name="reg_id" value="<?= $regId ?>">
            <input type="hidden" name="id" id="type_id" value="0">
            <input type="hidden" name="tab" value="types">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="typeModalLabel"><i class="bi bi-card-checklist me-2"></i>Add Course Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Course Type Code <span class="text-danger">*</span></label>
                        <input type="text" name="type_code" id="type_code" class="form-control" placeholder="e.g. Theory, Lab" maxlength="30" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Type Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" id="type_name" class="form-control" placeholder="e.g. Theory Course" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Evaluation Scheme Engine <span class="text-danger">*</span></label>
                        <select name="evaluation_scheme" id="type_scheme" class="form-select" required onchange="onSchemeChange()">
                            <option value="THEORY">THEORY (Mid Exams + SEE Theory)</option>
                            <option value="LAB">LAB (Continuous Lab + Lab SEE)</option>
                            <option value="INTEGRATED">INTEGRATED (Theory Cum Lab)</option>
                            <option value="PROJECT">PROJECT (Reviews / PRC + Viva)</option>
                            <option value="AUDIT_NON_CREDIT">AUDIT_NON_CREDIT (Mandatory / Satisfactory)</option>
                            <option value="OTHER">OTHER (Technical Seminar / Viva)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">CIE Max Marks</label>
                        <input type="number" step="0.5" min="0" max="100" name="cie_max_marks" id="type_cie" class="form-control" value="30.00" oninput="calcTotalMarks()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">SEE Max Marks</label>
                        <input type="number" step="0.5" min="0" max="100" name="see_max_marks" id="type_see" class="form-control" value="70.00" oninput="calcTotalMarks()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Total Course Marks</label>
                        <input type="number" step="0.5" min="0" max="200" name="total_marks" id="type_total" class="form-control" value="100.00">
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="has_see" id="type_has_see" value="1" checked>
                            <label class="form-check-label fw-bold small" for="type_has_see">
                                Includes Semester End Examination (SEE)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_credit_course" id="type_is_credit" value="1" checked>
                            <label class="form-check-label fw-bold small" for="type_is_credit">
                                Counts toward Degree Credits (Credit-bearing)
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small">Description / Evaluation Details</label>
                        <textarea name="description" id="type_desc" class="form-control" rows="2" placeholder="e.g. Mid examinations and external university question paper examination"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="typeSubmitBtn">Save Course Type</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewCategoryModal() {
    document.getElementById('categoryModalLabel').innerHTML = '<i class="bi bi-tag me-2"></i>Add Course Category';
    document.getElementById('cat_id').value = '0';
    document.getElementById('cat_code').value = '';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_min_pct').value = '0.00';
    document.getElementById('cat_max_pct').value = '0.00';
    document.getElementById('cat_credits').value = '0.0';
    document.getElementById('cat_desc').value = '';
    document.getElementById('catSubmitBtn').textContent = 'Add Category';
}

function editCategory(c) {
    document.getElementById('categoryModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Course Category: ' + c.category_code;
    document.getElementById('cat_id').value = c.id;
    document.getElementById('cat_code').value = c.category_code;
    document.getElementById('cat_name').value = c.category_name;
    document.getElementById('cat_min_pct').value = c.min_allocation_pct;
    document.getElementById('cat_max_pct').value = c.max_allocation_pct;
    document.getElementById('cat_credits').value = c.target_credits;
    document.getElementById('cat_desc').value = c.description || '';
    document.getElementById('catSubmitBtn').textContent = 'Update Category';
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function openNewTypeModal() {
    document.getElementById('typeModalLabel').innerHTML = '<i class="bi bi-card-checklist me-2"></i>Add Course Type';
    document.getElementById('type_id').value = '0';
    document.getElementById('type_code').value = '';
    document.getElementById('type_name').value = '';
    document.getElementById('type_scheme').value = 'THEORY';
    document.getElementById('type_cie').value = '30.00';
    document.getElementById('type_see').value = '70.00';
    document.getElementById('type_total').value = '100.00';
    document.getElementById('type_has_see').checked = true;
    document.getElementById('type_is_credit').checked = true;
    document.getElementById('type_desc').value = '';
    document.getElementById('typeSubmitBtn').textContent = 'Add Course Type';
}

function editCourseType(t) {
    document.getElementById('typeModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Course Type: ' + t.type_code;
    document.getElementById('type_id').value = t.id;
    document.getElementById('type_code').value = t.type_code;
    document.getElementById('type_name').value = t.type_name;
    document.getElementById('type_scheme').value = t.evaluation_scheme;
    document.getElementById('type_cie').value = t.cie_max_marks;
    document.getElementById('type_see').value = t.see_max_marks;
    document.getElementById('type_total').value = t.total_marks;
    document.getElementById('type_has_see').checked = (parseInt(t.has_see, 10) === 1);
    document.getElementById('type_is_credit').checked = (parseInt(t.is_credit_course, 10) === 1);
    document.getElementById('type_desc').value = t.description || '';
    document.getElementById('typeSubmitBtn').textContent = 'Update Course Type';
    new bootstrap.Modal(document.getElementById('typeModal')).show();
}

function calcTotalMarks() {
    const cie = parseFloat(document.getElementById('type_cie').value) || 0;
    const see = parseFloat(document.getElementById('type_see').value) || 0;
    document.getElementById('type_total').value = (cie + see).toFixed(2);
}

function onSchemeChange() {
    const scheme = document.getElementById('type_scheme').value;
    if (scheme === 'AUDIT_NON_CREDIT') {
        document.getElementById('type_is_credit').checked = false;
        document.getElementById('type_has_see').checked = false;
    } else if (scheme === 'OTHER') {
        document.getElementById('type_has_see').checked = false;
        document.getElementById('type_is_credit').checked = true;
        document.getElementById('type_cie').value = '100.00';
        document.getElementById('type_see').value = '0.00';
        calcTotalMarks();
    }
}
</script>

<?php
require_once("superadminfooter.php");
?>

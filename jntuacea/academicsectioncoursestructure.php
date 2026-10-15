<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Module: Academic Section Course Structure Engine
 * Provides complete multi-semester Course Structure Roadmap (8 sems for UG, 4 sems for PG),
 * Statutory Category Credit Reconciliation, Elective Tracks & Verticals, and BoS Dossier Export.
 */

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Course Structure";
require_once("academicsectionheader.php");
require_once("curriculum_subject.class.php");
require_once("regulations.class.php");
require_once("programs.class.php");

$currSubObj = new CurriculumSubject();
$programsObj = new Programs();
$regulationsObj = new Regulations();

$msg = '';
$errmsg = '';

// Handle POST actions (Save subject, Toggle status, Quick delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_subject') {
        $res = $currSubObj->addOrUpdateSubject($_POST);
        if (!empty($res['status'])) {
            $msg = $res['message'] ?? 'Course saved successfully in structure.';
        } else {
            $errmsg = $res['error'] ?? 'Failed to save course.';
        }
    } elseif ($action === 'toggle_status') {
        $subId = (int)($_POST['id'] ?? 0);
        $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : null;
        $res = $currSubObj->toggleStatusSubject($subId, $newStatus);
        if (!empty($res['status'])) {
            $msg = $res['message'] ?? 'Course status updated.';
        } else {
            $errmsg = $res['error'] ?? 'Failed to update status.';
        }
    }
}

// Context parameters
$selectedProgId = (int)($_GET['prog_id'] ?? $_POST['prog_id'] ?? 0);
$selectedRegId = (int)($_GET['reg_id'] ?? $_POST['reg_id'] ?? 0);
$selectedSpecId = (int)($_GET['spec_id'] ?? $_POST['spec_id'] ?? 0);

// Load all programs
$programs = $programsObj->getAllPrograms()['data'] ?? [];

// Default to first program if not set
if (empty($selectedProgId) && !empty($programs)) {
    $selectedProgId = (int)$programs[0]['id'];
}

// Load regulations and specializations for program
$regulations = $currSubObj->getRegulationsByProgramId($selectedProgId)['data'] ?? [];
$specializations = $currSubObj->getSpecializationsByProgramId($selectedProgId)['data'] ?? [];

// Validate and default regulation for program
$regFound = false;
foreach ($regulations as $r) {
    if ((int)$r['id'] === $selectedRegId) {
        $regFound = true;
        break;
    }
}
if (!$regFound) {
    $selectedRegId = 0;
    if (!empty($regulations)) {
        foreach ($regulations as $r) {
            if (in_array(strtoupper($r['regulation']), ['R23', 'R25'])) {
                $selectedRegId = (int)$r['id'];
                break;
            }
        }
        if (empty($selectedRegId)) {
            $selectedRegId = (int)$regulations[0]['id'];
        }
    }
}

// Validate and default specialization for program
$specFound = false;
foreach ($specializations as $s) {
    if ((int)$s['id'] === $selectedSpecId) {
        $specFound = true;
        break;
    }
}
if (!$specFound && !empty($specializations)) {
    $selectedSpecId = (int)$specializations[0]['id'];
}

// Active view mode: 'roadmap', 'compliance', 'electives', 'bos_print'
$viewMode = $_GET['view'] ?? 'roadmap';

// Load Course Structure, Compliance, and Electives if full context is available
$structureData = null;
$complianceData = null;
$electivesData = null;
$dynamicConfig = ['categories' => [], 'types' => []];

if ($selectedProgId > 0 && $selectedRegId > 0 && $selectedSpecId > 0) {
    $structureData = $currSubObj->getCourseStructureByBranch($selectedProgId, $selectedRegId, $selectedSpecId);
    $complianceData = $currSubObj->getCategoryCompliance($selectedProgId, $selectedRegId, $selectedSpecId);
    $electivesData = $currSubObj->getElectiveTracks($selectedProgId, $selectedRegId, $selectedSpecId);
    $dynamicConfig = $currSubObj->getCategoriesAndTypesByRegId($selectedRegId);
}

// Active program, regulation, specialization metadata
$activeProg = null;
foreach ($programs as $p) {
    if ((int)$p['id'] === $selectedProgId) { $activeProg = $p; break; }
}
$activeReg = null;
foreach ($regulations as $r) {
    if ((int)$r['id'] === $selectedRegId) { $activeReg = $r; break; }
}
$activeSpec = null;
foreach ($specializations as $s) {
    if ((int)$s['id'] === $selectedSpecId) { $activeSpec = $s; break; }
}

$isUG = (($activeProg['program_level'] ?? 'UG') === 'UG');
?>

<style>
.print-only {
    display: none;
}
@media print {
    .dontprint, nav, header, footer, .btn, .modal, .alert, .dropdown, #filterForm {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    body {
        background-color: #fff !important;
        font-size: 10pt;
    }
    .table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .table th, .table td {
        border: 1px solid #333 !important;
        padding: 4px 6px !important;
    }
    .page-break {
        page-break-after: always;
    }
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Printable Official BoS Header -->
    <?php if (!empty($activeSpec) && !empty($activeReg)): ?>
        <div class="print-only mb-4 text-center border-bottom pb-3">
            <h4 class="fw-bold mb-1">JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU</h4>
            <h5 class="fw-bold mb-1 text-uppercase text-secondary"><?= htmlspecialchars($activeSpec['spec_fullname'] ?? '') ?> (<?= htmlspecialchars($activeSpec['spec_shortname'] ?? '') ?>)</h5>
            <h6 class="fw-bold mb-1 text-dark">
                <?= htmlspecialchars($activeProg['prog_fullname'] ?? '') ?> — <?= htmlspecialchars($activeReg['regulation'] ?? '') ?> REGULATION
            </h6>
            <div class="small text-muted">
                Board of Studies (BoS) Approved Course Structure &amp; Scheme of Instruction (Total Degree Credits: <?= number_format((float)($activeReg['total_degree_credits'] ?? 160.0), 1) ?>)
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Action Bar & Breadcrumbs -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 dontprint">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="academicsectionhome.php">Academic Section</a></li>
                    <li class="breadcrumb-item"><a href="academicsectionregulations.php">Regulations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Course Structure</li>
                </ol>
            </nav>
            <h4 class="mb-0 text-primary fw-bold">
                <i class="bi bi-diagram-2-fill me-2"></i>Curriculum Course Structure & BoS Dossier Engine
            </h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm" onclick="exportStructureToCsv()">
                <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print BoS Dossier
            </button>
            <?php if ($selectedProgId > 0 && $selectedRegId > 0 && $selectedSpecId > 0): ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="openNewCourseModal()">
                    <i class="bi bi-plus-circle me-1"></i>Add Course to Structure
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm dontprint" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errmsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm dontprint" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errmsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Context Selector Card -->
    <div class="card shadow-sm border-0 mb-4 dontprint">
        <div class="card-body py-3">
            <form method="GET" action="academicsectioncoursestructure.php" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Program</label>
                    <select name="prog_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ((int)$p['id'] === $selectedProgId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['prog_shortname'] . ' - ' . $p['prog_fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Regulation</label>
                    <select name="reg_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($regulations as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ((int)$r['id'] === $selectedRegId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['regulation']) ?> (<?= htmlspecialchars($r['prog_shortname']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Branch / Specialization</label>
                    <select name="spec_id" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($specializations as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ((int)$s['id'] === $selectedSpecId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['spec_fullname'] . ' (' . $s['spec_shortname'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i>Load Structure
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($structureData && !empty($structureData['regulation']) && !empty($structureData['specialization'])): ?>
        <?php
        $reg = $structureData['regulation'];
        $prog = $structureData['program'];
        $spec = $structureData['specialization'];
        $semesters = $structureData['semesters'];
        $totals = $structureData['grand_totals'];
        $comp = $complianceData['overall'] ?? [];
        $isCompliant = !empty($comp['is_compliant']);
        ?>

        <!-- Context Header & Reconciliation KPI Card -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge <?= $isUG ? 'bg-primary' : 'bg-dark' ?> fs-6">
                                <?= htmlspecialchars($prog['program_level']) ?>
                            </span>
                            <h4 class="fw-bold mb-0 text-dark">
                                <?= htmlspecialchars($spec['spec_fullname']) ?> (<?= htmlspecialchars($spec['spec_shortname']) ?>)
                            </h4>
                            <span class="badge bg-secondary fs-6">
                                <?= htmlspecialchars($reg['regulation']) ?> Regulation
                            </span>
                        </div>
                        <div class="text-muted small">
                            Degree: <strong><?= htmlspecialchars($prog['prog_fullname']) ?></strong> | 
                            Duration: <strong><?= (int)$reg['normal_duration_years'] ?> Years (<?= (int)$reg['total_semesters'] ?> Semesters)</strong> | 
                            Max Duration: <strong><?= (int)$reg['max_duration_years'] ?> Years</strong>
                            <?php if (!empty($reg['has_lateral_entry'])): ?>
                                | LES Credits: <strong><?= number_format((float)$reg['lateral_entry_credits'], 1) ?></strong>
                            <?php endif; ?>
                            <?php if (!empty($reg['has_honors'])): ?>
                                | Honors: <strong>+<?= number_format((float)$reg['honors_credits'], 1) ?> cr</strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-end">
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <div class="small text-muted fw-bold text-uppercase">Statutory Degree Credits</div>
                                <h3 class="fw-bold mb-0 text-primary"><?= number_format((float)$totals['total_credits'], 1) ?> / <?= number_format((float)$reg['total_degree_credits'], 1) ?></h3>
                            </div>
                            <div class="ms-2">
                                <?php if ($isCompliant): ?>
                                    <span class="badge bg-success fs-6 p-2"><i class="bi bi-shield-check me-1"></i>100% Compliant</span>
                                <?php elseif ($comp['difference'] > 0): ?>
                                    <span class="badge bg-danger fs-6 p-2">+<?= abs($comp['difference']) ?> Cr Excess</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark fs-6 p-2">-<?= abs($comp['difference']) ?> Cr Deficit</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs: Roadmap vs Compliance vs Elective Tracks vs BoS View -->
        <ul class="nav nav-tabs mb-4 dontprint" id="structureTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($viewMode === 'roadmap') ? 'active' : '' ?> fw-bold" id="roadmap-tab" data-bs-toggle="tab" data-bs-target="#roadmap-pane" type="button" role="tab">
                    <i class="bi bi-layers me-1"></i>Semester-by-Semester Roadmap (<?= count($semesters) ?> Sems, <?= $totals['total_courses'] ?> Courses)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($viewMode === 'compliance') ? 'active' : '' ?> fw-bold" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance-pane" type="button" role="tab">
                    <i class="bi bi-pie-chart me-1"></i>Statutory Category Credit Compliance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($viewMode === 'electives') ? 'active' : '' ?> fw-bold" id="electives-tab" data-bs-toggle="tab" data-bs-target="#electives-pane" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-1"></i>Elective Tracks & Verticals (PE, OE, Minor, Honors)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="structureTabsContent">
            <!-- =============================================================== -->
            <!-- TAB 1: SEMESTER-BY-SEMESTER ROADMAP -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?= ($viewMode === 'roadmap') ? 'show active' : '' ?>" id="roadmap-pane" role="tabpanel">
                <?php if (empty($semesters)): ?>
                    <div class="card shadow-sm border-0 text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                            <h5 class="fw-bold text-dark">No Curriculum Courses Defined</h5>
                            <p class="text-muted small">No courses found for this branch in regulation <?= htmlspecialchars($reg['regulation']) ?>.</p>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="openNewCourseModal()">
                                <i class="bi bi-plus-circle me-1"></i>Add First Course
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($semesters as $semName => $sData): ?>
                        <?php
                        $subs = $sData['subjects'];
                        $sTot = $sData['totals'];
                        ?>
                        <div class="card shadow-sm border-0 mb-4 page-break">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="bi bi-calendar-event me-2"></i><?= htmlspecialchars($semName) ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?= count($subs) ?> Courses | 
                                        L-T-P: <strong><?= $sTot['lecture_hours'] ?>-<?= $sTot['tutorial_hours'] ?>-<?= $sTot['practical_hours'] ?></strong> | 
                                        Semester Credits: <strong class="text-primary"><?= number_format($sTot['credits'], 1) ?></strong> | 
                                        Marks: <strong><?= number_format($sTot['cie_marks'], 0) ?> CIE + <?= number_format($sTot['see_marks'], 0) ?> SEE = <?= number_format($sTot['total_marks'], 0) ?> Total</strong>
                                    </small>
                                </div>
                                <div class="dontprint">
                                    <button class="btn btn-outline-primary btn-sm py-1 px-2" onclick="openNewCourseModal('<?= htmlspecialchars($semName) ?>')">
                                        <i class="bi bi-plus-circle me-1"></i>Add to <?= htmlspecialchars($semName) ?>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3" style="width: 60px;">#</th>
                                                <th style="width: 120px;">Code</th>
                                                <th>Course Title</th>
                                                <th style="width: 90px;">Category</th>
                                                <th style="width: 120px;">Course Type</th>
                                                <th class="text-center" style="width: 110px;">L - T - P</th>
                                                <th class="text-center" style="width: 80px;">Credits</th>
                                                <th class="text-center" style="width: 150px;">CIE + SEE = Total</th>
                                                <th style="width: 150px;">Track / Mode</th>
                                                <th class="text-center pe-3 dontprint" style="width: 100px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subs as $sub): ?>
                                                <tr>
                                                    <td class="ps-3 text-muted fw-bold"><?= (int)$sub['subject_sno'] ?></td>
                                                    <td class="font-monospace fw-bold text-dark">
                                                        <?= htmlspecialchars($sub['subcode']) ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($sub['sub_fullname']) ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars($sub['sub_shortname']) ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2">
                                                            <?= htmlspecialchars($sub['course_category']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?= htmlspecialchars($sub['sub_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center font-monospace small">
                                                        <?= (float)$sub['lecture_hours'] ?> - <?= (float)$sub['tutorial_hours'] ?> - <?= (float)($sub['practical_hours'] ?: $sub['pr_hours']) ?>
                                                    </td>
                                                    <td class="text-center fw-bold text-primary fs-6">
                                                        <?= number_format((float)$sub['credits'], 1) ?>
                                                    </td>
                                                    <td class="text-center small">
                                                        <?= number_format((float)$sub['cie_max_marks'], 0) ?> + <?= number_format((float)$sub['see_max_marks'], 0) ?> = 
                                                        <strong class="text-dark"><?= number_format((float)$sub['total_marks'], 0) ?></strong>
                                                    </td>
                                                    <td class="small">
                                                        <?php if (!empty($sub['elective_track'])): ?>
                                                            <div><span class="badge bg-info-subtle text-info border border-info-subtle"><?= htmlspecialchars($sub['elective_track']) ?></span></div>
                                                        <?php endif; ?>
                                                        <div class="text-muted"><?= htmlspecialchars($sub['delivery_mode'] ?? 'CONVENTIONAL') ?></div>
                                                    </td>
                                                    <td class="text-center pe-3 dontprint">
                                                        <button class="btn btn-outline-primary btn-sm py-0 px-2" 
                                                                onclick="editCourse(<?= htmlspecialchars(json_encode($sub)) ?>)" title="Edit Course">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" action="academicsectioncoursestructure.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>" class="d-inline" onsubmit="return confirm('Inactivate course <?= htmlspecialchars($sub['subcode']) ?>?');">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                            <input type="hidden" name="new_status" value="0">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Inactivate Course">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <!-- Semester Summary Row -->
                                            <tr class="table-light fw-bold">
                                                <td colspan="5" class="ps-3 text-uppercase text-secondary small">
                                                    Total for <?= htmlspecialchars($semName) ?>:
                                                </td>
                                                <td class="text-center font-monospace">
                                                    <?= $sTot['lecture_hours'] ?> - <?= $sTot['tutorial_hours'] ?> - <?= $sTot['practical_hours'] ?>
                                                </td>
                                                <td class="text-center text-primary fs-6">
                                                    <?= number_format($sTot['credits'], 1) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= number_format($sTot['cie_marks'], 0) ?> + <?= number_format($sTot['see_marks'], 0) ?> = <?= number_format($sTot['total_marks'], 0) ?>
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Grand Totals Bar -->
                    <div class="card shadow-sm border-0 bg-primary text-white mb-5 page-break">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="text-white-50 text-uppercase small fw-bold">Degree Total Courses</div>
                                    <h3 class="fw-bold mb-0"><?= $totals['total_courses'] ?> Courses</h3>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-white-50 text-uppercase small fw-bold">Degree Total Credits</div>
                                    <h3 class="fw-bold mb-0"><?= number_format($totals['total_credits'], 1) ?> Credits</h3>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-white-50 text-uppercase small fw-bold">Total Contact Hours</div>
                                    <h3 class="fw-bold mb-0"><?= $totals['total_lecture'] + $totals['total_tutorial'] + $totals['total_practical'] ?> Hrs / Week</h3>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-white-50 text-uppercase small fw-bold">Degree Total Marks</div>
                                    <h3 class="fw-bold mb-0"><?= number_format($totals['total_marks'], 0) ?> Marks</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 2: STATUTORY CATEGORY CREDIT COMPLIANCE -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?= ($viewMode === 'compliance') ? 'show active' : '' ?>" id="compliance-pane" role="tabpanel">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-pie-chart me-2"></i>Statutory Category Credit Reconciliation</h5>
                            <small class="text-muted">Direct comparison between credits allocated in this branch curriculum vs SuperAdmin mandated regulation limits.</small>
                        </div>
                        <div>
                            <?php if ($isCompliant): ?>
                                <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>100% Compliant</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Reconciliation Required</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 100px;">Code</th>
                                        <th>Category Name</th>
                                        <th class="text-center" style="width: 140px;">Mandated Alloc %</th>
                                        <th class="text-center" style="width: 130px;">Target Credits</th>
                                        <th class="text-center" style="width: 140px;">Allocated Credits</th>
                                        <th class="text-center" style="width: 130px;">Difference</th>
                                        <th class="text-center" style="width: 120px;">Courses</th>
                                        <th class="text-center pe-3" style="width: 130px;">Compliance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($complianceData['categories'])): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                No category compliance data available.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($complianceData['categories'] as $c): ?>
                                            <?php
                                            $cStatus = $c['status'];
                                            $statusBadge = match($cStatus) {
                                                'COMPLIANT' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Compliant</span>',
                                                'DEFICIT' => '<span class="badge bg-warning text-dark"><i class="bi bi-dash-circle me-1"></i>Deficit (' . $c['difference'] . ')</span>',
                                                'EXCESS' => '<span class="badge bg-danger"><i class="bi bi-plus-circle me-1"></i>Excess (+' . $c['difference'] . ')</span>',
                                                default => '<span class="badge bg-secondary">' . htmlspecialchars($cStatus) . '</span>'
                                            };
                                            ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($c['category_code']) ?></span>
                                                </td>
                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </td>
                                                <td class="text-center text-muted small">
                                                    <?= number_format($c['min_pct'], 1) ?>% - <?= number_format($c['max_pct'], 1) ?>%
                                                </td>
                                                <td class="text-center fw-bold text-secondary">
                                                    <?= number_format($c['target_credits'], 1) ?>
                                                </td>
                                                <td class="text-center fw-bold text-primary fs-6">
                                                    <?= number_format($c['allocated_credits'], 1) ?>
                                                    <small class="text-muted d-block fw-normal">(<?= $c['allocated_pct'] ?>%)</small>
                                                </td>
                                                <td class="text-center fw-bold <?= ($c['difference'] < 0 ? 'text-warning' : ($c['difference'] > 0 ? 'text-danger' : 'text-success')) ?>">
                                                    <?= ($c['difference'] > 0 ? '+' : '') . number_format($c['difference'], 1) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border"><?= (int)$c['course_count'] ?></span>
                                                </td>
                                                <td class="text-center pe-3">
                                                    <?= $statusBadge ?>
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
            <!-- TAB 3: ELECTIVE TRACKS & VERTICALS -->
            <!-- =============================================================== -->
            <div class="tab-pane fade <?= ($viewMode === 'electives') ? 'show active' : '' ?>" id="electives-pane" role="tabpanel">
                <?php if (empty($electivesData['tracks'])): ?>
                    <div class="card shadow-sm border-0 text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-diagram-3 fs-1 text-muted d-block mb-3"></i>
                            <h5 class="fw-bold text-dark">No Elective Tracks or Verticals Configured</h5>
                            <p class="text-muted small">Professional Electives (PE), Open Electives (OE), and Skill Courses with specific tracks will appear here.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($electivesData['tracks'] as $t): ?>
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-primary me-2"><?= htmlspecialchars($t['category']) ?></span>
                                            <strong class="text-dark"><?= htmlspecialchars($t['track_name']) ?></strong>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= count($t['subjects']) ?> Courses</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($t['subjects'] as $es): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                    <div>
                                                        <span class="font-monospace fw-bold text-primary me-2"><?= htmlspecialchars($es['subcode']) ?></span>
                                                        <span class="text-dark fw-semibold"><?= htmlspecialchars($es['sub_fullname']) ?></span>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($es['yearsem']) ?> | L-T-P: <?= $es['lecture_hours'] ?>-<?= $es['tutorial_hours'] ?>-<?= $es['practical_hours'] ?></small>
                                                    </div>
                                                    <span class="badge bg-light text-dark border fw-bold"><?= (float)$es['credits'] ?> Cr</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <i class="bi bi-diagram-2 fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-dark">Select Program, Regulation & Branch</h5>
                <p class="text-muted small">Choose the program context from the filters above to load the complete course structure.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- Modal: Add / Edit Course in Structure -->
<!-- ========================================================================= -->
<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="academicsectioncoursestructure.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>" class="modal-content">
            <input type="hidden" name="action" value="save_subject">
            <input type="hidden" name="prog_id" value="<?= $selectedProgId ?>">
            <input type="hidden" name="reg_id" value="<?= $selectedRegId ?>">
            <input type="hidden" name="spec_id" value="<?= $selectedSpecId ?>">
            <input type="hidden" name="id" id="modal_sub_id" value="0">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="courseModalLabel"><i class="bi bi-book me-2"></i>Add Course to Structure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Semester / YearSem <span class="text-danger">*</span></label>
                        <select name="yearsem" id="modal_yearsem" class="form-select" required>
                            <?php foreach ($currSubObj->getYearSemOptions() as $y): ?>
                                <option value="<?= htmlspecialchars($y) ?>"><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">S.No in Semester <span class="text-danger">*</span></label>
                        <input type="number" name="subject_sno" id="modal_sno" class="form-control" value="1" min="1" max="30" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Course Code <span class="text-danger">*</span></label>
                        <input type="text" name="subcode" id="modal_subcode" class="form-control font-monospace text-uppercase" placeholder="e.g. 23A05101" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small">Full Course Title <span class="text-danger">*</span></label>
                        <input type="text" name="sub_fullname" id="modal_fullname" class="form-control" placeholder="e.g. Data Structures & Algorithms" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Short Name <span class="text-danger">*</span></label>
                        <input type="text" name="sub_shortname" id="modal_shortname" class="form-control text-uppercase" placeholder="e.g. DSA" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Course Category <span class="text-danger">*</span></label>
                        <select name="course_category" id="modal_category" class="form-select" required>
                            <?php foreach ($dynamicConfig['categories'] as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category_code']) ?>">
                                    <?= htmlspecialchars($cat['category_code'] . ' - ' . $cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Course Type & Scheme <span class="text-danger">*</span></label>
                        <select name="sub_type" id="modal_subtype" class="form-select" required onchange="onModalTypeChange()">
                            <?php foreach ($dynamicConfig['types'] as $tp): ?>
                                <option value="<?= htmlspecialchars($tp['type_code']) ?>"
                                        data-cie="<?= (float)$tp['cie_max_marks'] ?>"
                                        data-see="<?= (float)$tp['see_max_marks'] ?>"
                                        data-total="<?= (float)$tp['total_marks'] ?>"
                                        data-has-see="<?= (int)$tp['has_see'] ?>">
                                    <?= htmlspecialchars($tp['type_code'] . ' (' . $tp['cie_max_marks'] . ' CIE + ' . $tp['see_max_marks'] . ' SEE)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- L-T-P-C Live Calculator -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Lecture (L)</label>
                        <input type="number" step="0.5" min="0" max="10" name="lecture_hours" id="modal_l" class="form-control" value="3.0" oninput="calcModalCredits()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Tutorial (T)</label>
                        <input type="number" step="0.5" min="0" max="10" name="tutorial_hours" id="modal_t" class="form-control" value="0.0" oninput="calcModalCredits()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Practical (P)</label>
                        <input type="number" step="0.5" min="0" max="10" name="practical_hours" id="modal_p" class="form-control" value="0.0" oninput="calcModalCredits()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-primary">Credits (C) <span class="badge bg-primary-subtle text-primary small">Auto</span></label>
                        <input type="number" step="0.5" min="0" max="25" name="credits" id="modal_c" class="form-control fw-bold text-primary" value="3.0">
                    </div>

                    <!-- Marks Breakdown -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">CIE Max Marks</label>
                        <input type="number" step="1" name="cie_max_marks" id="modal_cie" class="form-control" value="30" oninput="calcModalTotalMarks()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">SEE Max Marks</label>
                        <input type="number" step="1" name="see_max_marks" id="modal_see" class="form-control" value="70" oninput="calcModalTotalMarks()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Total Marks</label>
                        <input type="number" step="1" name="total_marks" id="modal_total" class="form-control" value="100">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Elective Track / Vertical</label>
                        <input type="text" name="elective_track" id="modal_track" class="form-control" placeholder="e.g. Artificial Intelligence & ML Track">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Delivery Mode</label>
                        <select name="delivery_mode" id="modal_mode" class="form-select">
                            <option value="CONVENTIONAL">Conventional (Classroom / Lab)</option>
                            <option value="BLENDED">Blended (Flipped Classroom)</option>
                            <option value="ONLINE_MOOC">Online / MOOC (SWAYAM / NPTEL)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small">Prerequisites</label>
                        <input type="text" name="prerequisites" id="modal_prereq" class="form-control" placeholder="e.g. 23A05101 Programming in C">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="modalSubmitBtn">Save Course</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewCourseModal(preferredSem) {
    document.getElementById('courseModalLabel').innerHTML = '<i class="bi bi-book me-2"></i>Add Course to Structure';
    document.getElementById('modal_sub_id').value = '0';
    if (preferredSem) {
        document.getElementById('modal_yearsem').value = preferredSem;
    }
    document.getElementById('modal_subcode').value = '';
    document.getElementById('modal_fullname').value = '';
    document.getElementById('modal_shortname').value = '';
    document.getElementById('modal_l').value = '3.0';
    document.getElementById('modal_t').value = '0.0';
    document.getElementById('modal_p').value = '0.0';
    document.getElementById('modal_c').value = '3.0';
    document.getElementById('modal_track').value = '';
    document.getElementById('modal_prereq').value = '';
    document.getElementById('modalSubmitBtn').textContent = 'Save Course to Structure';
    onModalTypeChange();
    calcModalCredits();
    new bootstrap.Modal(document.getElementById('courseModal')).show();
}

function editCourse(c) {
    document.getElementById('courseModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Course: ' + c.subcode;
    document.getElementById('modal_sub_id').value = c.id;
    document.getElementById('modal_yearsem').value = c.yearsem;
    document.getElementById('modal_sno').value = c.subject_sno || 1;
    document.getElementById('modal_subcode').value = c.subcode;
    document.getElementById('modal_fullname').value = c.sub_fullname;
    document.getElementById('modal_shortname').value = c.sub_shortname;
    document.getElementById('modal_category').value = c.course_category;
    document.getElementById('modal_subtype').value = c.sub_type;
    document.getElementById('modal_l').value = c.lecture_hours;
    document.getElementById('modal_t').value = c.tutorial_hours;
    document.getElementById('modal_p').value = c.practical_hours || c.pr_hours || 0.0;
    document.getElementById('modal_c').value = c.credits;
    document.getElementById('modal_cie').value = c.cie_max_marks || 30;
    document.getElementById('modal_see').value = c.see_max_marks || 70;
    document.getElementById('modal_total').value = c.total_marks || 100;
    document.getElementById('modal_track').value = c.elective_track || '';
    document.getElementById('modal_mode').value = c.delivery_mode || 'CONVENTIONAL';
    document.getElementById('modal_prereq').value = c.prerequisites || '';
    document.getElementById('modalSubmitBtn').textContent = 'Update Course';
    new bootstrap.Modal(document.getElementById('courseModal')).show();
}

function calcModalCredits() {
    const l = parseFloat(document.getElementById('modal_l').value) || 0;
    const t = parseFloat(document.getElementById('modal_t').value) || 0;
    const p = parseFloat(document.getElementById('modal_p').value) || 0;
    const c = l + t + (0.5 * p);
    document.getElementById('modal_c').value = c.toFixed(1);
}

function onModalTypeChange() {
    const sel = document.getElementById('modal_subtype');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.cie) {
        document.getElementById('modal_cie').value = opt.dataset.cie;
        document.getElementById('modal_see').value = opt.dataset.see;
        document.getElementById('modal_total').value = opt.dataset.total;
    }
}

function calcModalTotalMarks() {
    const cie = parseFloat(document.getElementById('modal_cie').value) || 0;
    const see = parseFloat(document.getElementById('modal_see').value) || 0;
    document.getElementById('modal_total').value = (cie + see).toFixed(0);
}

function exportStructureToCsv() {
    const semCards = document.querySelectorAll('#roadmap-pane .card');
    if (!semCards.length) {
        alert('No course structure data available to export.');
        return;
    }

    let csv = [];
    csv.push(['"JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU"']);
    csv.push(['"<?= addslashes($activeSpec['spec_fullname'] ?? '') ?> - <?= addslashes($activeReg['regulation'] ?? '') ?> REGULATION"']);
    csv.push(['"Course Structure & Scheme of Instruction"']);
    csv.push([]);

    semCards.forEach(card => {
        const titleEl = card.querySelector('.card-header h5');
        if (titleEl) {
            csv.push(['"' + titleEl.textContent.trim().replace(/"/g, '""') + '"']);
        }
        const rows = card.querySelectorAll('table tbody tr');
        if (rows.length) {
            csv.push(['"S.No"', '"Course Code"', '"Course Title"', '"Category"', '"Course Type"', '"L-T-P"', '"Credits"', '"CIE Marks"', '"SEE Marks"', '"Total Marks"', '"Track"']);
            rows.forEach(r => {
                const cols = r.querySelectorAll('td');
                if (cols.length >= 9) {
                    const rowData = [];
                    cols.forEach(c => rowData.push('"' + c.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""') + '"'));
                    csv.push(rowData.join(','));
                }
            });
            csv.push([]);
        }
    });

    const csvContent = "data:text/csv;charset=utf-8," + csv.map(e => Array.isArray(e) ? e.join(',') : e).join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "Course_Structure_<?= preg_replace('/[^A-Za-z0-9_]/', '_', ($activeReg['regulation'] ?? 'Reg') . '_' . ($activeSpec['spec_shortname'] ?? 'Branch')) ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
require_once("academicsectionfooter.php");
?>

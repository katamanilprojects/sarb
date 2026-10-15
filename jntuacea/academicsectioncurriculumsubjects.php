<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Curriculum Subjects";
require_once("academicsectionheader.php");
require_once("curriculum_subject.class.php");

$obj = new CurriculumSubject();
$msg = '';
$errmsg = '';

// Check if editing a specific subject
$editSubject = null;
if (!empty($_GET['edit_id'])) {
    $editRes = $obj->getSubjectById((int)$_GET['edit_id']);
    if (!empty($editRes['status']) && !empty($editRes['data'])) {
        $editSubject = $editRes['data'];
        // Auto-hydrate filter selections if not provided in URL
        if (empty($_REQUEST['prog_id'])) $_REQUEST['prog_id'] = $editSubject['prog_id'];
        if (empty($_REQUEST['reg_id'])) $_REQUEST['reg_id'] = $editSubject['reg_id'];
        if (empty($_REQUEST['spec_id'])) $_REQUEST['spec_id'] = $editSubject['spec_id'];
        if (empty($_REQUEST['yearsem'])) $_REQUEST['yearsem'] = $editSubject['yearsem'];
    }
}

// Handle Actions (Add, Update, Inactivate / Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_subject') {
        $res = $obj->addOrUpdateSubject($_POST);
        if (!empty($res['status'])) {
            $msg = $res['message'];
            // Reset edit mode after successful save
            $editSubject = null;
        } else {
            $errmsg = $res['error'];
        }
    } elseif ($action === 'toggle_status') {
        $subId = (int)($_POST['id'] ?? 0);
        $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : null;
        $res = $obj->toggleStatusSubject($subId, $newStatus);
        if (!empty($res['status'])) {
            $msg = $res['message'];
        } else {
            $errmsg = $res['error'];
        }
    }
}

// Filter selections
$selectedProgId = (int)($_REQUEST['prog_id'] ?? 0);
$selectedRegId = (int)($_REQUEST['reg_id'] ?? 0);
$selectedSpecId = (int)($_REQUEST['spec_id'] ?? 0);
$selectedYearSem = trim($_REQUEST['yearsem'] ?? '');

$programs = $obj->getAllPrograms()['data'] ?? [];

// Filter regulations by selected program if chosen, otherwise show all with program label
$regulations = [];
if (!empty($selectedProgId)) {
    $regulations = $obj->getRegulationsByProgramId($selectedProgId)['data'] ?? [];
} else {
    $regulations = $obj->getAllRegulations()['data'] ?? [];
}

$specializations = [];
if (!empty($selectedProgId)) {
    $specializations = $obj->getSpecializationsByProgramId($selectedProgId)['data'] ?? [];
}
$yearSemOptions = $obj->getYearSemOptions();

// Load subjects if all 4 context keys are provided
$subjectsList = [];
if (!empty($selectedProgId) && !empty($selectedRegId) && !empty($selectedSpecId) && !empty($selectedYearSem)) {
    $subjectsList = $obj->getSubjectsByContext($selectedProgId, $selectedRegId, $selectedSpecId, $selectedYearSem)['data'] ?? [];
}

// Fetch dynamic regulation categories and course types if reg_id is available
$dynamicConfig = ['categories' => [], 'types' => []];
if (!empty($selectedRegId)) {
    $dynamicConfig = $obj->getCategoriesAndTypesByRegId($selectedRegId);
}
?>

<div class="container my-3">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-book me-2"></i>Curriculum Subjects Management (Academic Section)</h5>
            <small class="badge bg-light text-primary">Master Subject Catalog</small>
        </div>
        <div class="card-body">
            <?php if (!empty($msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <?php if (!empty($errmsg)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($errmsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <!-- Context Filter Form -->
            <form method="GET" action="academicsectioncurriculumsubjects.php" class="row g-2 mb-2" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Program <span class="text-danger">*</span></label>
                    <select name="prog_id" id="prog_id" class="form-select form-select-sm" required onchange="onProgramChange()">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>" <?= ($selectedProgId == $prog['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prog['prog_shortname'] . ' - ' . $prog['prog_fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Regulation <span class="text-danger">*</span></label>
                    <select name="reg_id" id="reg_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Regulation --</option>
                        <?php foreach ($regulations as $reg): ?>
                            <option value="<?= $reg['id'] ?>" <?= ($selectedRegId == $reg['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($reg['regulation'] . ' (' . ($reg['prog_shortname'] ?? '') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Specialization <span class="text-danger">*</span></label>
                    <select name="spec_id" id="spec_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Specialization --</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?= $spec['id'] ?>" <?= ($selectedSpecId == $spec['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($spec['spec_shortname'] . ' - ' . $spec['spec_fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Year - Sem <span class="text-danger">*</span></label>
                    <select name="yearsem" id="yearsem" class="form-select form-select-sm" required>
                        <option value="">-- Select Year & Sem --</option>
                        <?php foreach ($yearSemOptions as $yso): ?>
                            <option value="<?= $yso ?>" <?= ($selectedYearSem == $yso) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($yso) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 text-end mt-3">
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-search me-1"></i> Get Subjects
                    </button>
                    <?php if (!empty($selectedProgId)): ?>
                        <a href="academicsectioncurriculumsubjects.php" class="btn btn-outline-secondary btn-sm ms-2">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($selectedProgId) && !empty($selectedRegId) && !empty($selectedSpecId) && !empty($selectedYearSem)): ?>
        <!-- Add / Edit Form Card -->
        <div class="card shadow-sm mb-4 border-info">
            <div class="card-header bg-info bg-opacity-10 text-dark d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-<?= !empty($editSubject) ? 'pencil-square text-warning' : 'plus-circle text-success' ?> me-2"></i>
                    <?= !empty($editSubject) ? 'Edit Curriculum Subject: ' . htmlspecialchars($editSubject['subcode']) : 'Add New Curriculum Subject' ?>
                </h6>
                <?php if (!empty($editSubject)): ?>
                    <a href="academicsectioncurriculumsubjects.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>&yearsem=<?= urlencode($selectedYearSem) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Cancel Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="academicsectioncurriculumsubjects.php" id="subjectForm">
                    <input type="hidden" name="action" value="save_subject">
                    <input type="hidden" name="id" value="<?= !empty($editSubject) ? (int)$editSubject['id'] : '' ?>">
                    <input type="hidden" name="prog_id" value="<?= $selectedProgId ?>">
                    <input type="hidden" name="reg_id" value="<?= $selectedRegId ?>" id="form_reg_id">
                    <input type="hidden" name="spec_id" value="<?= $selectedSpecId ?>">
                    <input type="hidden" name="yearsem" value="<?= htmlspecialchars($selectedYearSem) ?>">

                    <div class="row g-2">
                        <!-- S.No -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">S.No <span class="text-danger">*</span></label>
                            <select name="subject_sno" id="subject_sno" class="form-select form-select-sm" required>
                                <?php 
                                $nextSno = !empty($editSubject) ? $editSubject['subject_sno'] : (count($subjectsList) + 1);
                                for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($nextSno == $i) ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Subject Code with Auto-Retrieval -->
                        <div class="col-md-3 position-relative">
                            <label class="form-label fw-bold">Subject Code <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="subcode" id="subcode" class="form-control text-uppercase" 
                                       placeholder="e.g. 23A05301T" required 
                                       value="<?= htmlspecialchars($editSubject['subcode'] ?? '') ?>"
                                       <?= empty($editSubject) ? 'onblur="lookupSubjectCode()"' : '' ?> autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="lookupSubjectCode()" title="Lookup catalog details">
                                    <i class="bi bi-search" id="lookupIcon"></i>
                                </button>
                            </div>
                            <small id="lookupStatus" class="form-text"></small>
                        </div>

                        <!-- Subject Short Name -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Short Name <span class="text-danger">*</span></label>
                            <input type="text" name="sub_shortname" id="sub_shortname" class="form-control form-control-sm text-uppercase" 
                                   placeholder="e.g. DBMS" required value="<?= htmlspecialchars($editSubject['sub_shortname'] ?? '') ?>">
                        </div>

                        <!-- Subject Type -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Subject Type <span class="text-danger">*</span></label>
                            <select name="sub_type" id="sub_type" class="form-select form-select-sm" required onchange="onTypeChange()">
                                <?php
                                $typeMetaMap = [];
                                if (!empty($dynamicConfig['types'])) {
                                    foreach ($dynamicConfig['types'] as $dt) {
                                        $typeMetaMap[$dt['type_code']] = $dt;
                                    }
                                }

                                $subTypes = [];
                                if (!empty($dynamicConfig['types'])) {
                                    foreach ($dynamicConfig['types'] as $dt) {
                                        $subTypes[$dt['type_code']] = $dt['type_name'] . ' (' . $dt['evaluation_scheme'] . ')';
                                    }
                                } else {
                                    $defaultTypes = ["Theory", "Lab", "Integrated", "Mandatory Course", "Skill Oriented Course", "Honors / Minors", "Project", "Comprehensive Viva", "Audit Course", "Seminar"];
                                    foreach ($defaultTypes as $dt) {
                                        $subTypes[$dt] = $dt;
                                    }
                                }

                                $currentType = $editSubject['sub_type'] ?? 'Theory';
                                if (!empty($currentType) && !array_key_exists($currentType, $subTypes)) {
                                    $subTypes[$currentType] = $currentType;
                                }

                                foreach ($subTypes as $tCode => $tLabel): 
                                    $tm = $typeMetaMap[$tCode] ?? null;
                                ?>
                                    <option value="<?= htmlspecialchars($tCode) ?>" 
                                            <?= ($currentType === $tCode) ? 'selected' : '' ?>
                                            <?= $tm ? 'data-cie="' . (float)$tm['cie_max_marks'] . '" data-see="' . (float)$tm['see_max_marks'] . '" data-total="' . (float)$tm['total_marks'] . '" data-has-see="' . (int)$tm['has_see'] . '"' : '' ?>>
                                        <?= htmlspecialchars($tLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Course Category -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Course Category</label>
                            <select name="course_category" id="course_category" class="form-select form-select-sm">
                                <option value="">-- Select Category --</option>
                                <?php
                                $categories = [];
                                if (!empty($dynamicConfig['categories'])) {
                                    foreach ($dynamicConfig['categories'] as $dc) {
                                        $categories[$dc['category_code']] = $dc['category_code'] . ' - ' . $dc['category_name'];
                                    }
                                } else {
                                    $categories = [
                                        "BS" => "BS - Basic Science",
                                        "ES" => "ES - Engineering Science",
                                        "HS" => "HS - Humanities & Social Sciences",
                                        "PC" => "PC - Professional Core",
                                        "PE" => "PE - Professional Elective",
                                        "OE" => "OE - Open Elective",
                                        "MC" => "MC - Mandatory Course",
                                        "PR" => "PR - Project / Internship",
                                        "SC" => "SC - Skill Oriented Course",
                                        "AC" => "AC - Audit Course"
                                    ];
                                }

                                $currentCat = strtoupper(trim($editSubject['course_category'] ?? ''));
                                if (!empty($currentCat) && !array_key_exists($currentCat, $categories)) {
                                    $categories[$currentCat] = $currentCat;
                                }

                                foreach ($categories as $catCode => $catLabel): ?>
                                    <option value="<?= htmlspecialchars($catCode) ?>" <?= ($currentCat === $catCode) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($catLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Full Name -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Subject Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="sub_fullname" id="sub_fullname" class="form-control form-control-sm" 
                                   placeholder="e.g. Database Management Systems" required 
                                   value="<?= htmlspecialchars($editSubject['sub_fullname'] ?? '') ?>">
                        </div>

                        <!-- L - T - Pr - P - C (Hours and Credits with Live Auto-Calculation) -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Lecture (L)</label>
                            <input type="number" step="0.5" min="0" max="20" name="lecture_hours" id="lecture_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['lecture_hours'] ?? '3.0') ?>" required oninput="calcCredits()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold">Tutorial (T)</label>
                            <input type="number" step="0.5" min="0" max="10" name="tutorial_hours" id="tutorial_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['tutorial_hours'] ?? '0.0') ?>" required oninput="calcCredits()">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Practical (Pr)</label>
                            <input type="number" step="0.5" min="0" max="20" name="pr_hours" id="pr_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['pr_hours'] ?? '0.0') ?>" required oninput="calcCredits()">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Practical Lab (P)</label>
                            <input type="number" step="0.5" min="0" max="20" name="practical_hours" id="practical_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['practical_hours'] ?? '0.0') ?>" required oninput="calcCredits()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold text-primary">Credits (C) <span class="badge bg-primary-subtle text-primary small">Auto</span></label>
                            <input type="number" step="0.5" min="0" max="30" name="credits" id="credits" 
                                   class="form-control form-control-sm fw-bold text-primary" value="<?= htmlspecialchars($editSubject['credits'] ?? '3.0') ?>" required>
                        </div>

                        <!-- Autonomous Marks Scheme & Delivery Details -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">CIE Marks</label>
                            <input type="number" step="1" name="cie_max_marks" id="cie_max_marks" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['cie_max_marks'] ?? '30') ?>" oninput="calcTotalMarks()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">SEE Marks</label>
                            <input type="number" step="1" name="see_max_marks" id="see_max_marks" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['see_max_marks'] ?? '70') ?>" oninput="calcTotalMarks()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Total Marks</label>
                            <input type="number" step="1" name="total_marks" id="total_marks" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['total_marks'] ?? '100') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Elective Track / Vertical</label>
                            <input type="text" name="elective_track" id="elective_track" 
                                   class="form-control form-control-sm" placeholder="e.g. AI & ML Track / Track 1" value="<?= htmlspecialchars($editSubject['elective_track'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Delivery Mode</label>
                            <select name="delivery_mode" id="delivery_mode" class="form-select form-select-sm">
                                <?php
                                $curMode = $editSubject['delivery_mode'] ?? 'CONVENTIONAL';
                                ?>
                                <option value="CONVENTIONAL" <?= ($curMode === 'CONVENTIONAL') ? 'selected' : '' ?>>Conventional (Classroom)</option>
                                <option value="BLENDED" <?= ($curMode === 'BLENDED') ? 'selected' : '' ?>>Blended Mode</option>
                                <option value="ONLINE_MOOC" <?= ($curMode === 'ONLINE_MOOC') ? 'selected' : '' ?>>Online / MOOC (SWAYAM)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Prerequisites</label>
                            <input type="text" name="prerequisites" id="prerequisites" 
                                   class="form-control form-control-sm" placeholder="e.g. 23A05101T Programming in C" value="<?= htmlspecialchars($editSubject['prerequisites'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-<?= !empty($editSubject) ? 'warning' : 'success' ?> btn-sm px-4">
                            <i class="bi bi-<?= !empty($editSubject) ? 'check-circle' : 'plus-circle' ?> me-1"></i>
                            <?= !empty($editSubject) ? 'Update Subject' : 'Save Subject' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabular List Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-list-columns-reverse me-2"></i>Defined Subjects (<?= count($subjectsList) ?>)
                </h6>
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($selectedYearSem) ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">S.No</th>
                                <th style="width: 120px;">Code</th>
                                <th class="text-start">Subject Full Name</th>
                                <th>Short Name</th>
                                <th>Type</th>
                                <th style="width: 70px;">Category</th>
                                <th style="width: 45px;">L</th>
                                <th style="width: 45px;">T</th>
                                <th style="width: 45px;">Pr</th>
                                <th style="width: 45px;">P</th>
                                <th style="width: 60px;">Credits</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjectsList)): ?>
                                <tr>
                                    <td colspan="13" class="text-muted py-4">No subjects found for this regulation, branch, and semester. Use the form above to add subjects.</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $totalCredits = 0;
                                foreach ($subjectsList as $sub): 
                                    $isActive = ((int)($sub['status'] ?? 1) === 1);
                                    if ($isActive) {
                                        $totalCredits += (float)$sub['credits'];
                                    }
                                    $rowClass = $isActive ? '' : 'table-light text-muted opacity-75';
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="fw-bold"><?= htmlspecialchars($sub['subject_sno']) ?></td>
                                        <td>
                                            <span class="badge <?= $isActive ? 'bg-primary' : 'bg-secondary' ?> fs-7">
                                                 <?= htmlspecialchars($sub['subcode']) ?>
                                            </span>
                                        </td>
                                        <td class="text-start fw-semibold">
                                            <?= htmlspecialchars($sub['sub_fullname']) ?>
                                            <?php if (!$isActive): ?>
                                                <span class="badge bg-danger ms-1 text-white">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($sub['sub_shortname']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sub['sub_type']) ?></span></td>
                                        <td>
                                            <?php if (!empty($sub['course_category'])): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($sub['course_category']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($sub['lecture_hours']) ?></td>
                                        <td><?= htmlspecialchars($sub['tutorial_hours']) ?></td>
                                        <td><?= htmlspecialchars($sub['pr_hours'] ?? '0.0') ?></td>
                                        <td><?= htmlspecialchars($sub['practical_hours']) ?></td>
                                        <td class="fw-bold <?= $isActive ? 'text-success' : 'text-muted' ?>"><?= htmlspecialchars($sub['credits']) ?></td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Edit button -->
                                            <a href="academicsectioncurriculumsubjects.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>&yearsem=<?= urlencode($selectedYearSem) ?>&edit_id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Edit Subject">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>

                                            <!-- Master Course Outcomes button -->
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-master-co" 
                                                    data-bs-toggle="modal" data-bs-target="#masterCOModal"
                                                    data-id="<?= (int)$sub['id'] ?>" 
                                                    data-subcode="<?= htmlspecialchars($sub['subcode'] ?? '', ENT_QUOTES) ?>" 
                                                    data-subname="<?= htmlspecialchars($sub['sub_fullname'] ?? '', ENT_QUOTES) ?>" 
                                                    title="Manage Master Course Outcomes">
                                                <i class="bi bi-award"></i> Master COs
                                            </button>

                                            <!-- Master Articulation Matrix button -->
                                            <button type="button" class="btn btn-sm btn-outline-info me-1 btn-master-matrix" 
                                                    data-bs-toggle="modal" data-bs-target="#masterMatrixModal"
                                                    data-id="<?= (int)$sub['id'] ?>" 
                                                    data-subcode="<?= htmlspecialchars($sub['subcode'] ?? '', ENT_QUOTES) ?>" 
                                                    data-subname="<?= htmlspecialchars($sub['sub_fullname'] ?? '', ENT_QUOTES) ?>" 
                                                    title="Manage Master CO-PO Articulation Matrix">
                                                <i class="bi bi-grid-3x3"></i> Matrix
                                            </button>

                                            <!-- Inactivate / Activate toggle form -->
                                            <form method="POST" action="academicsectioncurriculumsubjects.php" class="d-inline" onsubmit="return confirm('<?= $isActive ? "Are you sure you want to inactivate this subject?" : "Are you sure you want to activate this subject?" ?>');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $isActive ? 0 : 1 ?>">
                                                <input type="hidden" name="prog_id" value="<?= $selectedProgId ?>">
                                                <input type="hidden" name="reg_id" value="<?= $selectedRegId ?>">
                                                <input type="hidden" name="spec_id" value="<?= $selectedSpecId ?>">
                                                <input type="hidden" name="yearsem" value="<?= htmlspecialchars($selectedYearSem) ?>">
                                                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $isActive ? 'Inactivate Subject' : 'Activate Subject' ?>">
                                                    <i class="bi bi-<?= $isActive ? 'slash-circle' : 'check-circle' ?>"></i> <?= $isActive ? 'Inactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="10" class="text-end pe-3">Active Total Credits:</td>
                                    <td class="text-success"><?= number_format($totalCredits, 1) ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Master Course Outcomes Modal -->
<div class="modal fade" id="masterCOModal" tabindex="-1" aria-labelledby="masterCOModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="masterCOModalLabel"><i class="bi bi-award me-2"></i>Curriculum Master Course Outcomes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong id="modalSubjectInfo">Subject Code & Name</strong>
                    <div class="text-muted small">These master catalog outcomes are inherited by default across faculty offerings under this syllabus.</div>
                </div>

                <div id="modalCOAlert"></div>

                <!-- Existing Master COs Table -->
                <h6 class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Approved Catalog COs</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle text-center mb-0" id="masterCOTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">CO No.</th>
                                <th class="text-start">Course Outcome Description</th>
                                <th style="width: 140px;">Bloom's Level</th>
                                <th style="width: 100px;">Target %</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="masterCOTableBody">
                            <tr>
                                <td colspan="5" class="text-muted py-3">Loading master outcomes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add / Edit Master CO Form -->
                <div class="card border-primary">
                    <div class="card-header bg-light fw-bold py-2">
                        <span id="formCardTitle"><i class="bi bi-plus-circle me-1"></i>Add / Update Master CO</span>
                    </div>
                    <div class="card-body py-2">
                        <form id="masterCOForm" onsubmit="submitMasterCO(event)">
                            <input type="hidden" id="modal_curr_sub_id" name="curr_sub_id" value="">
                            <input type="hidden" name="action" value="save_master_co">
                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">CO Number <span class="text-danger">*</span></label>
                                    <input type="number" id="modal_co_number" name="co_number" class="form-control form-control-sm" min="1" max="10" placeholder="e.g. 1" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Bloom's Taxonomy Level</label>
                                    <select id="modal_bloom_level" name="bloom_level" class="form-select form-select-sm">
                                        <option value="L1-Remember">L1 - Remember</option>
                                        <option value="L2-Understand">L2 - Understand</option>
                                        <option value="L3-Apply" selected>L3 - Apply</option>
                                        <option value="L4-Analyze">L4 - Analyze</option>
                                        <option value="L5-Evaluate">L5 - Evaluate</option>
                                        <option value="L6-Create">L6 - Create</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Target Threshold (%)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" id="modal_target_threshold" name="target_threshold_percent" class="form-control" value="60.0" min="1" max="100" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">CO Statement / Description <span class="text-danger">*</span></label>
                                    <textarea id="modal_co_description" name="co_description" class="form-control form-control-sm" rows="2" placeholder="Upon completion of this course, students will be able to..." required></textarea>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="resetMasterCOForm()">Clear</button>
                                <button type="submit" class="btn btn-primary btn-sm" id="modalSubmitBtn"><i class="bi bi-save me-1"></i>Save Master CO</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Master Articulation Matrix Modal -->
<div class="modal fade" id="masterMatrixModal" tabindex="-1" aria-labelledby="masterMatrixModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title" id="masterMatrixModalLabel"><i class="bi bi-grid-3x3 me-2"></i>Curriculum Master Articulation Matrix (CO-PO/PSO)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong id="modalMatrixSubjectInfo">Subject Code & Name</strong>
                    <div class="text-muted small">
                        This matrix maps Course Outcomes to Program Outcomes (PO1-PO12, PSO1-PSO4) with weightages: <strong>1 = Low, 2 = Medium, 3 = High</strong>.
                        Saving this matrix will automatically update the Master Catalog and cascade to all active offerings teaching this subject.
                    </div>
                </div>

                <div id="modalMatrixAlert"></div>

                <form id="masterMatrixForm" onsubmit="submitMasterMatrix(event)">
                    <input type="hidden" id="modal_matrix_curr_sub_id" name="curr_sub_id" value="">
                    <input type="hidden" name="action" value="save_master_matrix">

                    <div id="matrixTableContainer" class="table-responsive mb-3">
                        <div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading articulation matrix...</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Leave blank if a CO does not correlate to a specific PO/PSO.</small>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Close</button>
                            <button type="submit" id="modalMatrixSubmitBtn" class="btn btn-primary btn-sm">
                                <i class="bi bi-save me-1"></i>Save Master Articulation Matrix
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function onProgramChange() {
    const progId = document.getElementById('prog_id').value;
    const specSelect = document.getElementById('spec_id');
    const regSelect = document.getElementById('reg_id');

    specSelect.innerHTML = '<option value="">Loading...</option>';
    regSelect.innerHTML = '<option value="">Loading...</option>';

    if (!progId) {
        specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
        regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
        return;
    }

    // Fetch specializations for program
    fetch('curriculum_subject_ajax.php?action=get_specializations&prog_id=' + progId)
        .then(response => response.json())
        .then(res => {
            specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
            if (res.status === 1 && res.data) {
                res.data.forEach(spec => {
                    const opt = document.createElement('option');
                    opt.value = spec.id;
                    opt.textContent = spec.spec_shortname + ' - ' + spec.spec_fullname;
                    specSelect.appendChild(opt);
                });
            }
        })
        .catch(err => {
            console.error('Error fetching specializations:', err);
            specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
        });

    // Fetch regulations for program
    fetch('curriculum_subject_ajax.php?action=get_regulations&prog_id=' + progId)
        .then(response => response.json())
        .then(res => {
            regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
            if (res.status === 1 && res.data) {
                res.data.forEach(reg => {
                    const opt = document.createElement('option');
                    opt.value = reg.id;
                    opt.textContent = reg.regulation + ' (' + (reg.prog_shortname || '') + ')';
                    regSelect.appendChild(opt);
                });
            }
        })
        .catch(err => {
            console.error('Error fetching regulations:', err);
            regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
        });
}

function lookupSubjectCode() {
    const subcodeInput = document.getElementById('subcode');
    const subcode = subcodeInput ? subcodeInput.value.trim() : '';
    const regId = document.getElementById('form_reg_id') ? document.getElementById('form_reg_id').value : '';
    const statusEl = document.getElementById('lookupStatus');
    const iconEl = document.getElementById('lookupIcon');

    if (!subcode) return;

    if (iconEl) iconEl.className = 'spinner-border spinner-border-sm';
    if (statusEl) {
        statusEl.className = 'form-text text-muted';
        statusEl.textContent = 'Searching catalog...';
    }

    fetch('curriculum_subject_ajax.php?action=lookup_code&subcode=' + encodeURIComponent(subcode) + '&reg_id=' + encodeURIComponent(regId))
        .then(r => r.json())
        .then(res => {
            if (iconEl) iconEl.className = 'bi bi-search';
            if (res.status === 1 && res.data) {
                const d = res.data;
                if (statusEl) {
                    statusEl.className = 'form-text text-success fw-bold';
                    statusEl.textContent = '✓ Details auto-retrieved from catalog!';
                }
                if (d.sub_fullname) document.getElementById('sub_fullname').value = d.sub_fullname;
                if (d.sub_shortname) document.getElementById('sub_shortname').value = d.sub_shortname;
                if (d.sub_type) {
                    const typeSel = document.getElementById('sub_type');
                    if (typeSel) {
                        let optExists = Array.from(typeSel.options).some(o => o.value === d.sub_type);
                        if (!optExists && d.sub_type) {
                            const newOpt = new Option(d.sub_type, d.sub_type, true, true);
                            typeSel.add(newOpt);
                        } else {
                            typeSel.value = d.sub_type;
                        }
                    }
                }
                if (d.course_category !== undefined && document.getElementById('course_category')) {
                    const catSel = document.getElementById('course_category');
                    if (catSel) {
                        let catExists = Array.from(catSel.options).some(o => o.value === d.course_category);
                        if (!catExists && d.course_category) {
                            const newOpt = new Option(d.course_category, d.course_category, true, true);
                            catSel.add(newOpt);
                        } else {
                            catSel.value = d.course_category;
                        }
                    }
                }
                if (d.lecture_hours !== undefined) document.getElementById('lecture_hours').value = d.lecture_hours;
                if (d.tutorial_hours !== undefined) document.getElementById('tutorial_hours').value = d.tutorial_hours;
                if (d.pr_hours !== undefined && document.getElementById('pr_hours')) {
                    document.getElementById('pr_hours').value = d.pr_hours;
                }
                if (d.practical_hours !== undefined) document.getElementById('practical_hours').value = d.practical_hours;
                if (d.credits !== undefined) document.getElementById('credits').value = d.credits;
                if (d.subject_sno !== undefined && document.getElementById('subject_sno')) {
                    document.getElementById('subject_sno').value = d.subject_sno;
                }
                if (d.cie_max_marks !== undefined && document.getElementById('cie_max_marks')) {
                    document.getElementById('cie_max_marks').value = d.cie_max_marks;
                }
                if (d.see_max_marks !== undefined && document.getElementById('see_max_marks')) {
                    document.getElementById('see_max_marks').value = d.see_max_marks;
                }
                if (d.total_marks !== undefined && document.getElementById('total_marks')) {
                    document.getElementById('total_marks').value = d.total_marks;
                }
                if (d.elective_track !== undefined && document.getElementById('elective_track')) {
                    document.getElementById('elective_track').value = d.elective_track;
                }
                if (d.delivery_mode !== undefined && document.getElementById('delivery_mode')) {
                    document.getElementById('delivery_mode').value = d.delivery_mode;
                }
                if (d.prerequisites !== undefined && document.getElementById('prerequisites')) {
                    document.getElementById('prerequisites').value = d.prerequisites;
                }
            } else {
                if (statusEl) {
                    statusEl.className = 'form-text text-muted';
                    statusEl.textContent = 'New subject code (enter details below)';
                }
            }
        })
        .catch(err => {
            if (iconEl) iconEl.className = 'bi bi-search';
            if (statusEl) {
                statusEl.className = 'form-text text-danger';
                statusEl.textContent = 'Lookup error';
            }
        });
}

// Master Course Outcomes Modal & AJAX Management
let activeCurrSubId = null;
let currentMasterCOList = [];

function openMasterCOModal(currSubId, subCode, subName) {
    if (!currSubId) return;
    activeCurrSubId = parseInt(currSubId, 10);
    document.getElementById('modal_curr_sub_id').value = activeCurrSubId;
    if (subCode || subName) {
        document.getElementById('modalSubjectInfo').textContent = (subCode || '') + ' - ' + (subName || '');
    }
    document.getElementById('modalCOAlert').innerHTML = '';
    resetMasterCOForm();
    loadMasterCOs(activeCurrSubId);

    const modalEl = document.getElementById('masterCOModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function loadMasterCOs(currSubId) {
    const tbody = document.getElementById('masterCOTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading master outcomes...</td></tr>';

    fetch('curriculum_subject_ajax.php?action=get_master_cos&curr_sub_id=' + currSubId)
        .then(r => r.json())
        .then(res => {
            if (res.status === 1 && res.data && res.data.length > 0) {
                currentMasterCOList = res.data;
                let html = '';
                res.data.forEach((co, idx) => {
                    const bloomSafe = co.bloom_level || 'L3-Apply';
                    const thresh = co.target_threshold_percent || '60.0';
                    const descSafe = (co.co_description || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');

                    html += `<tr>
                        <td><span class="badge bg-secondary">CO${co.co_number}</span></td>
                        <td class="text-start">${descSafe}</td>
                        <td><span class="badge bg-info text-dark">${bloomSafe}</span></td>
                        <td><span class="badge bg-light text-dark border">${thresh}%</span></td>
                        <td>
                            <button type="button" class="btn btn-outline-warning btn-sm py-0 px-1 me-1 btn-edit-master-co" data-idx="${idx}" title="Edit CO">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 btn-del-master-co" data-id="${co.id}" data-num="${co.co_number}" title="Delete CO">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                const nextNum = res.data.length + 1;
                document.getElementById('modal_co_number').value = nextNum;
            } else if (res.status === 1) {
                currentMasterCOList = [];
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted py-3">No master course outcomes defined yet. Use the form below to add CO1, CO2, etc.</td></tr>';
                document.getElementById('modal_co_number').value = 1;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger py-3"><i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to load master COs.') + '</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error loading master COs:', err);
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger py-3">Failed to load outcomes. Please check network/console.</td></tr>';
        });
}

function submitMasterCO(e) {
    e.preventDefault();
    const btn = document.getElementById('modalSubmitBtn');
    const alertBox = document.getElementById('modalCOAlert');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const formData = new FormData(document.getElementById('masterCOForm'));

    fetch('curriculum_subject_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master CO';

        if (res.status === 1) {
            alertBox.innerHTML = '<div class="alert alert-success alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-check-circle me-1"></i>' + (res.message || 'Master CO saved successfully.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
            resetMasterCOForm();
            loadMasterCOs(activeCurrSubId);
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to save master CO.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master CO';
        alertBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">Network error while saving.</div>';
    });
}

function editMasterCO(coNum, coDesc, bloomLevel, thresh) {
    document.getElementById('modal_co_number').value = coNum;
    document.getElementById('modal_co_description').value = coDesc;
    if (bloomLevel) document.getElementById('modal_bloom_level').value = bloomLevel;
    if (thresh) document.getElementById('modal_target_threshold').value = thresh;
    document.getElementById('formCardTitle').innerHTML = '<i class="bi bi-pencil-square me-1 text-warning"></i>Editing Master CO' + coNum;
    document.getElementById('modal_co_description').focus();
}

function deleteMasterCO(coId, coNum) {
    if (!confirm('Are you sure you want to delete Master CO' + coNum + '?')) return;

    const alertBox = document.getElementById('modalCOAlert');
    const formData = new FormData();
    formData.append('action', 'delete_master_co');
    formData.append('curr_sub_id', activeCurrSubId);
    formData.append('co_id', coId);

    fetch('curriculum_subject_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 1) {
            alertBox.innerHTML = '<div class="alert alert-success alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-check-circle me-1"></i>Master CO' + coNum + ' deleted.' +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
            loadMasterCOs(activeCurrSubId);
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to delete CO.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(err => {
        console.error('Error deleting master CO:', err);
        alertBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">Network error while deleting CO.</div>';
    });
}

function resetMasterCOForm() {
    document.getElementById('modal_co_description').value = '';
    document.getElementById('modal_bloom_level').value = 'L3-Apply';
    document.getElementById('modal_target_threshold').value = '60.0';
    document.getElementById('formCardTitle').innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add / Update Master CO';
    let maxCoNum = 0;
    if (currentMasterCOList && currentMasterCOList.length > 0) {
        currentMasterCOList.forEach(co => {
            const num = parseInt(co.co_number, 10);
            if (!isNaN(num) && num > maxCoNum) maxCoNum = num;
        });
    }
    document.getElementById('modal_co_number').value = maxCoNum + 1;
}

let activeMatrixCurrSubId = null;

function openMasterMatrixModal(currSubId, subcode, fullname) {
    if (!currSubId) return;
    activeMatrixCurrSubId = parseInt(currSubId, 10);
    document.getElementById('modal_matrix_curr_sub_id').value = activeMatrixCurrSubId;
    if (subcode || fullname) {
        document.getElementById('modalMatrixSubjectInfo').innerHTML = 
            '<span class="badge bg-dark me-2">' + (subcode || '') + '</span>' + (fullname || '');
    }
    document.getElementById('modalMatrixAlert').innerHTML = '';
    loadMasterMatrix(activeMatrixCurrSubId);

    const modalEl = document.getElementById('masterMatrixModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function loadMasterMatrix(currSubId) {
    const container = document.getElementById('matrixTableContainer');
    container.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading articulation matrix...</div>';

    fetch('curriculum_subject_ajax.php?action=get_master_matrix&curr_sub_id=' + currSubId)
        .then(r => r.json())
        .then(res => {
            if (res.status === 1) {
                const cos = res.cos || [];
                const pops = res.po_psos || [];
                const mappings = res.mappings || {};

                if (cos.length === 0) {
                    container.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-circle me-1"></i>No Master COs defined yet for this subject. Please define Course Outcomes first using the "Master COs" button.</div>';
                    document.getElementById('modalMatrixSubmitBtn').disabled = true;
                    return;
                }

                if (pops.length === 0) {
                    container.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-circle me-1"></i>No Program Outcomes (POs/PSOs) configured for this regulation and specialization.</div>';
                    document.getElementById('modalMatrixSubmitBtn').disabled = true;
                    return;
                }

                document.getElementById('modalMatrixSubmitBtn').disabled = false;

                let html = '<table class="table table-bordered table-sm align-middle text-center mb-0" style="font-size: 0.85rem;">';
                html += '<thead class="table-light"><tr><th style="min-width: 80px; width: 90px;">CO #</th>';
                pops.forEach(p => {
                    const descSafe = (p.description || '').replace(/"/g, '&quot;');
                    html += `<th title="${descSafe}" style="min-width: 65px;">${p.code}</th>`;
                });
                html += '</tr></thead><tbody>';

                cos.forEach(co => {
                    const coDescSafe = (co.co_description || '').replace(/"/g, '&quot;');
                    html += `<tr><td class="fw-bold bg-light" title="${coDescSafe}"><span class="badge bg-primary">CO${co.co_number}</span></td>`;
                    pops.forEach(p => {
                        const key = `${co.id}-${p.id}`;
                        const val = mappings[key] !== undefined ? mappings[key] : '';
                        html += `<td>
                            <select name="mapping[${co.id}][${p.id}]" class="form-select form-select-sm text-center py-0 px-1 border-secondary">
                                <option value="" ${val === '' ? 'selected' : ''}>-</option>
                                <option value="1" ${val === 1 ? 'selected' : ''}>1</option>
                                <option value="2" ${val === 2 ? 'selected' : ''}>2</option>
                                <option value="3" ${val === 3 ? 'selected' : ''}>3</option>
                            </select>
                        </td>`;
                    });
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert alert-danger mb-0">' + (res.error || 'Failed to load articulation matrix.') + '</div>';
                document.getElementById('modalMatrixSubmitBtn').disabled = true;
            }
        })
        .catch(err => {
            console.error('Error loading master matrix:', err);
            container.innerHTML = '<div class="alert alert-danger mb-0">Network error while loading matrix.</div>';
            document.getElementById('modalMatrixSubmitBtn').disabled = true;
        });
}

function submitMasterMatrix(e) {
    e.preventDefault();
    const btn = document.getElementById('modalMatrixSubmitBtn');
    const alertBox = document.getElementById('modalMatrixAlert');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving &amp; Cascading...';

    const formData = new FormData(document.getElementById('masterMatrixForm'));

    fetch('curriculum_subject_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master Articulation Matrix';

        if (res.status === 1) {
            alertBox.innerHTML = '<div class="alert alert-success alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-check-circle me-1"></i>' + (res.message || 'Master Articulation Matrix saved successfully.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
            loadMasterMatrix(activeMatrixCurrSubId);
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to save matrix.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master Articulation Matrix';
        alertBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">Network error while saving matrix.</div>';
    });
}

// Attach live input debounce for instant auto-population and modal events
let lookupTimerAcademic = null;
document.addEventListener('DOMContentLoaded', function() {
    const subCodeEl = document.getElementById('subcode');
    if (subCodeEl) {
        subCodeEl.addEventListener('input', function() {
            clearTimeout(lookupTimerAcademic);
            const val = this.value.trim();
            if (val.length >= 3) {
                lookupTimerAcademic = setTimeout(lookupSubjectCode, 600);
            }
        });
    }

    // Modal show events via Bootstrap
    const coModalEl = document.getElementById('masterCOModal');
    if (coModalEl) {
        coModalEl.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (btn && btn.dataset && btn.dataset.id) {
                openMasterCOModal(btn.dataset.id, btn.dataset.subcode, btn.dataset.subname);
            }
        });
    }

    const matrixModalEl = document.getElementById('masterMatrixModal');
    if (matrixModalEl) {
        matrixModalEl.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (btn && btn.dataset && btn.dataset.id) {
                openMasterMatrixModal(btn.dataset.id, btn.dataset.subcode, btn.dataset.subname);
            }
        });
    }

    // Delegated click listeners for table action buttons and edit/delete in modals
    document.addEventListener('click', function(e) {
        // Master CO button in table
        const coBtn = e.target.closest('.btn-master-co');
        if (coBtn) {
            openMasterCOModal(coBtn.dataset.id, coBtn.dataset.subcode, coBtn.dataset.subname);
            return;
        }

        // Master Matrix button in table
        const matrixBtn = e.target.closest('.btn-master-matrix');
        if (matrixBtn) {
            openMasterMatrixModal(matrixBtn.dataset.id, matrixBtn.dataset.subcode, matrixBtn.dataset.subname);
            return;
        }

        // Edit Master CO inside modal
        const editCoBtn = e.target.closest('.btn-edit-master-co');
        if (editCoBtn) {
            const idx = parseInt(editCoBtn.dataset.idx, 10);
            const co = currentMasterCOList[idx];
            if (co) {
                editMasterCO(co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent);
            }
            return;
        }

        // Delete Master CO inside modal
        const delCoBtn = e.target.closest('.btn-del-master-co');
        if (delCoBtn) {
            const coId = parseInt(delCoBtn.dataset.id, 10);
            const coNum = parseInt(delCoBtn.dataset.num, 10);
            deleteMasterCO(coId, coNum);
            return;
        }
    });
});

function calcCredits() {
    const lEl = document.getElementById('lecture_hours');
    const tEl = document.getElementById('tutorial_hours');
    const prEl = document.getElementById('pr_hours');
    const pEl = document.getElementById('practical_hours');
    const credEl = document.getElementById('credits');
    if (!lEl || !credEl) return;

    const l = parseFloat(lEl.value) || 0;
    const t = parseFloat(tEl.value) || 0;
    const pr = parseFloat(prEl ? prEl.value : 0) || 0;
    const p = parseFloat(pEl ? pEl.value : 0) || 0;
    const pract = Math.max(pr, p);
    const c = l + t + (0.5 * pract);
    credEl.value = c.toFixed(1);
}

function calcTotalMarks() {
    const cieEl = document.getElementById('cie_max_marks');
    const seeEl = document.getElementById('see_max_marks');
    const totEl = document.getElementById('total_marks');
    if (!cieEl || !seeEl || !totEl) return;

    const cie = parseFloat(cieEl.value) || 0;
    const see = parseFloat(seeEl.value) || 0;
    totEl.value = (cie + see).toFixed(0);
}

function onTypeChange() {
    const sel = document.getElementById('sub_type');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.cie) {
        const cieEl = document.getElementById('cie_max_marks');
        const seeEl = document.getElementById('see_max_marks');
        const totEl = document.getElementById('total_marks');
        if (cieEl) cieEl.value = opt.dataset.cie;
        if (seeEl) seeEl.value = opt.dataset.see;
        if (totEl) totEl.value = opt.dataset.total;
    }
}
</script>

<?php
require_once("academicsectionfooter.php");
?>

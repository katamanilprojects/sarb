<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

$page_title = "Manage Classes";

$yearSemOptions = [
    "I Yr - I Sem",
    "II Yr - I Sem",
    "III Yr - I Sem",
    "IV Yr - I Sem",
    "I Yr - II Sem",
    "II Yr - II Sem",
    "III Yr - II Sem",
    "IV Yr - II Sem",
    "I Sem",
    "II Sem",
    "III Sem",
    "IV Sem"
];

$msg = '';
$errmsg = '';

$academic_years = $superadmin->getActiveAcademicYears()['data'];
$programs = $superadmin->getAllPrograms();
$programRows = $programs['data'] ?? [];
$classes = $superadmin->getAllClasses();
$timingTemplates = $superadmin->getAllTimingTemplates();
$regulations = $superadmin->getAllRegulations()['data'];
$activeSpecializations = $superadmin->getActiveSpecializations();
$allSpecializations = $superadmin->getAllSpecializations();
$classesByProgram = $superadmin->getClassesByProgram();

$programMap = [];
foreach ($programRows as $prog) {
    $programMap[(int)$prog['id']] = $prog;
}

require_once __DIR__ . '/services/BatchOBEService.php';
$batchService = \Services\BatchOBEService::getInstance();
$studentBatches = $batchService->getBatches();

$programClassMap = [];
if (!empty($classesByProgram['data'])) {
    foreach ($classesByProgram['data'] as $row) {
        $programClassMap[(int)$row['id']] = $row['prog_shortname'];
    }
}

$activeSpecMap = [];
if (!empty($activeSpecializations['data'])) {
    foreach ($activeSpecializations['data'] as $spec) {
        $activeSpecMap[(int)$spec['id']] = $spec;
    }
}
if (!empty($allSpecializations['data'])) {
    foreach ($allSpecializations['data'] as $spec) {
        if (!isset($activeSpecMap[(int)$spec['id']])) {
            $activeSpecMap[(int)$spec['id']] = $spec;
        }
    }
}

$editClassId = (isset($_GET['edit']) && $_GET['edit'] !== 'new') ? (int)$_GET['edit'] : null;
$showEditForm = isset($_GET['edit']);
$editClass = null;

if ($editClassId && !empty($classes['data'])) {
    foreach ($classes['data'] as $class) {
        if ((int)$class['id'] === $editClassId) {
            $editClass = $class;
            break;
        }
    }
}

if ($editClass && !empty($editClass['spec_id']) && !isset($activeSpecMap[(int)$editClass['spec_id']])) {
    foreach ($allSpecializations['data'] as $spec) {
        if ((int)$spec['id'] === (int)$editClass['spec_id']) {
            $activeSpecMap[(int)$spec['id']] = $spec;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['save_schedule']) && !empty($_POST['class_id']) && !empty($_POST['timing_id']) && !empty($_POST['from_date'])) {
        $classId = (int)$_POST['class_id'];
        $newFromDate = $_POST['from_date'];
        $existingSchedule = $superadmin->getClassTimingSchedule($classId);
        $hasOverlap = false;

        if (!empty($existingSchedule['data'])) {
            foreach ($existingSchedule['data'] as $scheduleRow) {
                $rowFrom = $scheduleRow['from_date'];
                $rowTo = !empty($scheduleRow['to_date']) ? $scheduleRow['to_date'] : null;
                if ($newFromDate === $rowFrom) {
                    $hasOverlap = true;
                    break;
                }
                if (!empty($rowTo) && $newFromDate > $rowFrom && $newFromDate <= $rowTo) {
                    $hasOverlap = true;
                    break;
                }
            }
        }

        if ($hasOverlap) {
            $errmsg = 'Overlapping timing schedule dates are not allowed. Please choose a different effective date.';
            $showEditForm = true;
            $editClassId = $classId;
        } else {
            $result = $superadmin->saveClassTimingSchedule($classId, (int)$_POST['timing_id'], $newFromDate);
            if (!empty($result['status'])) {
                header("Location: superadminclasses.php?edit=" . $classId . "&msg=" . urlencode("Class timing schedule saved successfully."));
                exit();
            }
            $errmsg = $result['error'] ?? 'Failed to save class timing schedule.';
            $showEditForm = true;
            $editClassId = $classId;
        }
    } elseif (!empty($_POST['acad_year']) && !empty($_POST['classname']) && !empty($_POST['yearsem']) && !empty($_POST['spec_id']) && !empty($_POST['timing_id']) && !empty($_POST['reg_id'])) {
        $selectedRegulation = '';
        foreach ($regulations as $regulation) {
            if ((int)$regulation['id'] === (int)$_POST['reg_id']) {
                $selectedRegulation = $regulation['regulation'];
                break;
            }
        }

        $section = strtoupper(trim($_POST['section'] ?? ''));
        $data = [
            'id' => $_POST['id'] ?? null,
            'acad_year' => $_POST['acad_year'],
            'classname' => trim($_POST['classname']),
            'yearsem' => $_POST['yearsem'],
            'section' => $section,
            'spec_id' => (int)$_POST['spec_id'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'timing_id' => (int)$_POST['timing_id'],
            'reg_id' => (int)$_POST['reg_id'],
            'batch_id' => !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null,
            'status' => $_POST['status'] ?? 1
        ];

        $result = $superadmin->addOrUpdateClass($data);
        if (!empty($result['status'])) {
            header("Location: superadminclasses.php?msg=" . urlencode("Class saved successfully."));
            exit();
        }
        $errmsg = $result['error'] ?? 'Failed to save class.';
    } elseif (!empty($_POST['delete_class_id'])) {
        $result = $superadmin->deleteClass((int)$_POST['delete_class_id']);
        if (!empty($result['status'])) {
            header("Location: superadminclasses.php?msg=" . urlencode("Class deleted successfully."));
            exit();
        }
        $errmsg = $result['error'] ?? 'Failed to delete class.';
    }
}

if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$classes = $superadmin->getAllClasses();
$classSchedule = ['status' => 0, 'data' => []];
if ($editClassId && !empty($classes['data'])) {
    foreach ($classes['data'] as $class) {
        if ((int)$class['id'] === (int)$editClassId) {
            $editClass = $class;
            break;
        }
    }
}
if ($editClass && !empty($editClass['id'])) {
    $classSchedule = $superadmin->getClassTimingSchedule($editClass['id']);
}

$groupedClassCards = [];
if (!empty($classes['data'])) {
    foreach ($classes['data'] as $class) {
        $spec = $activeSpecMap[(int)$class['spec_id']] ?? null;
        $programName = $spec['prog_shortname'] ?? ($programClassMap[(int)$class['id']] ?? 'Unknown Program');
        $academicYear = $class['acad_year'];
        $yearSem = $class['yearsem'];
        if (!isset($groupedClassCards[$academicYear])) {
            $groupedClassCards[$academicYear] = [];
        }
        if (!isset($groupedClassCards[$academicYear][$programName])) {
            $groupedClassCards[$academicYear][$programName] = [];
        }
        if (!isset($groupedClassCards[$academicYear][$programName][$yearSem])) {
            $groupedClassCards[$academicYear][$programName][$yearSem] = [];
        }
        $groupedClassCards[$academicYear][$programName][$yearSem][] = [
            'class' => $class,
            'spec_shortname' => $spec['spec_shortname'] ?? '-',
            'prog_shortname' => $programName
        ];
    }
    krsort($groupedClassCards);
}

require_once("superadminheader.php");
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header">Manage Classes</div>
        <div class="card-body">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if (!empty($errmsg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errmsg); ?></div>
            <?php endif; ?>

            <button type="button" class="btn btn-primary mb-3" id="addClassBtn" style="display: <?= $showEditForm ? 'none' : 'inline-block'; ?>;">Add Class</button>

            <div id="editForm" style="display: <?= $showEditForm ? 'block' : 'none'; ?>;">
                <div class="card mb-4 border-0 bg-light">
                    <div class="card-header"><?= $editClass ? 'Edit Class' : 'Add Class'; ?></div>
                    <div class="card-body">
                        <form action="superadminclasses.php" method="post" class="row g-3">
                            <div class="col-md-4">
                                <label for="acad_year" class="form-label">Academic Year</label>
                                <select name="acad_year" id="acad_year" class="form-select" required>
                                    <option value="">Select Academic Year</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?= htmlspecialchars($year['acad_year']); ?>" <?= $editClass && $editClass['acad_year'] == $year['acad_year'] ? 'selected' : ''; ?>><?= htmlspecialchars($year['acad_year']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="yearsem" class="form-label">Year-Sem</label>
                                <select name="yearsem" id="yearsem" class="form-select" required>
                                    <option value="">Select Year-Sem</option>
                                    <?php foreach ($yearSemOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option); ?>" <?= $editClass && $editClass['yearsem'] == $option ? 'selected' : ''; ?>><?= htmlspecialchars($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="section" class="form-label">Section <small class="text-muted">(Optional)</small></label>
                                <input type="text" name="section" id="section" class="form-control" maxlength="10" placeholder="e.g. A or 1" value="<?= $editClass ? htmlspecialchars($editClass['section'] ?? '') : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="spec_id" class="form-label">Specialization</label>
                                <select name="spec_id" id="spec_id" class="form-select" required>
                                    <option value="">Select Specialization</option>
                                    <?php foreach ($activeSpecMap as $spec): ?>
                                        <option value="<?= (int)$spec['id']; ?>" <?= $editClass && (int)$editClass['spec_id'] === (int)$spec['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($spec['prog_shortname'] . ' - ' . $spec['spec_shortname']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="classname" class="form-label">Class Name</label>
                                <input type="text" name="classname" id="classname" class="form-control" required value="<?= $editClass ? htmlspecialchars($editClass['classname']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required value="<?= $editClass ? htmlspecialchars($editClass['start_date']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required value="<?= $editClass ? htmlspecialchars($editClass['end_date']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="timing_id" class="form-label">Default Timing Template</label>
                                <select name="timing_id" id="timing_id" class="form-select" required>
                                    <option value="">-- Select Class Timing Template --</option>
                                    <?php foreach ($timingTemplates as $template): ?>
                                        <option value="<?= (int)$template['timing_id']; ?>" <?= $editClass && !empty($editClass['timing_id']) && (int)$editClass['timing_id'] === (int)$template['timing_id'] ? 'selected' : ''; ?>>Template - <?= (int)$template['timing_id']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="reg_id" class="form-label">Regulation</label>
                                <select name="reg_id" id="reg_id" class="form-select" required>
                                    <option value="">Select Regulation</option>
                                    <?php foreach ($regulations as $regulation): $specForLabel = $editClass && !empty($editClass['spec_id']) ? ($activeSpecMap[(int)$editClass['spec_id']] ?? null) : null; ?>
                                        <option value="<?= (int)$regulation['id']; ?>" <?= $editClass && !empty($editClass['reg_id']) && (int)$editClass['reg_id'] === (int)$regulation['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($regulation['regulation'] . (!empty($regulation['prog_id']) && !empty($programMap[(int)$regulation['prog_id']]['prog_shortname']) ? ' - ' . $programMap[(int)$regulation['prog_id']]['prog_shortname'] : '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="batch_id" class="form-label">Graduating Batch (Cohort)</label>
                                <select name="batch_id" id="batch_id" class="form-select">
                                    <option value="">-- No Batch Assigned --</option>
                                    <?php foreach ($studentBatches as $sb): ?>
                                        <option value="<?= (int)$sb['id']; ?>" <?= $editClass && !empty($editClass['batch_id']) && (int)$editClass['batch_id'] === (int)$sb['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($sb['batch_name'] . ' (' . $sb['prog_shortname'] . ' - ' . $sb['regulation'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="1" <?= $editClass && (int)$editClass['status'] === 1 ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?= $editClass && (int)$editClass['status'] === 0 ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <input type="hidden" name="id" id="id" value="<?= $editClass ? (int)$editClass['id'] : ''; ?>">
                                <button type="button" class="btn btn-outline-danger" id="cancelClassBtn">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Class</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($editClass): ?>
                <div class="card mb-4 border-0 bg-light">
                    <div class="card-header">Class Timing Schedule</div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Only same-date or closed-range overlaps are blocked. New dates after the current active start date are allowed.</p>
                        <form action="superadminclasses.php?edit=<?= (int)$editClass['id']; ?>" method="post" class="row g-3 align-items-end">
                            <input type="hidden" name="save_schedule" value="1">
                            <input type="hidden" name="class_id" value="<?= (int)$editClass['id']; ?>">
                            <div class="col-md-5">
                                <label for="schedule_timing_id" class="form-label">Timing Template</label>
                                <select name="timing_id" id="schedule_timing_id" class="form-select" required>
                                    <option value="">-- Select Class Timing Template --</option>
                                    <?php foreach ($timingTemplates as $template): ?>
                                        <option value="<?= (int)$template['timing_id']; ?>">Template - <?= (int)$template['timing_id']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="from_date" class="form-label">Effective From Date</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Save Schedule</button>
                            </div>
                        </form>
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Template ID</th>
                                        <th>From Date</th>
                                        <th>To Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($classSchedule['data'])): ?>
                                        <?php foreach ($classSchedule['data'] as $schedule): ?>
                                            <tr>
                                                <td><?= (int)$schedule['timing_id']; ?></td>
                                                <td><?= htmlspecialchars($schedule['from_date']); ?></td>
                                                <td><?= !empty($schedule['to_date']) ? htmlspecialchars($schedule['to_date']) : 'Current'; ?></td>
                                                <td><?= empty($schedule['to_date']) ? 'Active' : 'Closed'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No schedule entries found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($groupedClassCards)): ?>
                <div class="accordion" id="classesYearAccordion">
                    <?php $yearIndex = 0; foreach ($groupedClassCards as $academicYear => $programs): $yearIndex++; ?>
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="classes-heading-year-<?= $yearIndex; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#classes-collapse-year-<?= $yearIndex; ?>" aria-expanded="false" aria-controls="classes-collapse-year-<?= $yearIndex; ?>">
                                    Academic Year: <?= htmlspecialchars($academicYear); ?>
                                </button>
                            </h2>
                            <div id="classes-collapse-year-<?= $yearIndex; ?>" class="accordion-collapse collapse" aria-labelledby="classes-heading-year-<?= $yearIndex; ?>" data-bs-parent="#classesYearAccordion">
                                <div class="accordion-body">
                                    <div class="accordion" id="classesProgramAccordion-<?= $yearIndex; ?>">
                                        <?php $programIndex = 0; foreach ($programs as $programName => $yearSems): $programIndex++; ?>
                                            <div class="accordion-item mb-3">
                                                <h2 class="accordion-header" id="classes-heading-program-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#classes-collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" aria-expanded="false" aria-controls="classes-collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                        Program: <?= htmlspecialchars($programName); ?>
                                                    </button>
                                                </h2>
                                                <div id="classes-collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" class="accordion-collapse collapse" aria-labelledby="classes-heading-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" data-bs-parent="#classesProgramAccordion-<?= $yearIndex; ?>">
                                                    <div class="accordion-body">
                                                        <div class="accordion" id="classesYearSemAccordion-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                            <?php $yearSemIndex = 0; foreach ($yearSems as $yearSem => $classRows): $yearSemIndex++; ?>
                                                                <div class="accordion-item mb-3">
                                                                    <h2 class="accordion-header" id="classes-heading-yearsem-<?= $yearIndex; ?>-<?= $programIndex; ?>-<?= $yearSemIndex; ?>">
                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#classes-collapse-yearsem-<?= $yearIndex; ?>-<?= $programIndex; ?>-<?= $yearSemIndex; ?>" aria-expanded="false" aria-controls="classes-collapse-yearsem-<?= $yearIndex; ?>-<?= $programIndex; ?>-<?= $yearSemIndex; ?>">
                                                                            Year-Sem: <?= htmlspecialchars($yearSem); ?>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="classes-collapse-yearsem-<?= $yearIndex; ?>-<?= $programIndex; ?>-<?= $yearSemIndex; ?>" class="accordion-collapse collapse" aria-labelledby="classes-heading-yearsem-<?= $yearIndex; ?>-<?= $programIndex; ?>-<?= $yearSemIndex; ?>" data-bs-parent="#classesYearSemAccordion-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                                        <div class="accordion-body p-0">
                                                                            <div class="table-responsive">
                                                                                <table class="table table-bordered table-striped align-middle mb-0">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>Class Name</th>
                                                                                            <th>Section</th>
                                                                                            <th>Batch</th>
                                                                                            <th>Specialization</th>
                                                                                            <th>Start Date</th>
                                                                                            <th>End Date</th>
                                                                                            <th>Timing ID</th>
                                                                                            <th>Regulation</th>
                                                                                            <th>Status</th>
                                                                                            <th>Actions</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody>
                                                                                        <?php foreach ($classRows as $row): $class = $row['class']; ?>
                                                                                            <tr>
                                                                                                <td><?= htmlspecialchars($class['classname']); ?></td>
                                                                                                <td><?= htmlspecialchars($class['section'] ?: '—'); ?></td>
                                                                                                <td><?= !empty($class['batch_name']) ? '<span class="badge bg-light text-primary border">' . htmlspecialchars($class['batch_name']) . '</span>' : '<span class="text-muted small">—</span>'; ?></td>
                                                                                                <td><?= htmlspecialchars($row['spec_shortname']); ?></td>
                                                                                                <td><?= htmlspecialchars($class['start_date']); ?></td>
                                                                                                <td><?= htmlspecialchars($class['end_date']); ?></td>
                                                                                                <td><?= (int)$class['timing_id']; ?></td>
                                                                                                <td><?= htmlspecialchars($class['regulation'] ?? ($class['reg'] ?? '')); ?></td>
                                                                                                <td><?= !empty($class['status']) ? 'Active' : 'Inactive'; ?></td>
                                                                                                <td>
                                                                                                    <a href="superadminclasses.php?edit=<?= (int)$class['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                                                                    <form action="superadminclasses.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($class['classname']); ?> ?');">
                                                                                                        <button type="submit" name="delete_class_id" value="<?= (int)$class['id']; ?>" class="btn btn-sm btn-danger">Delete</button>
                                                                                                    </form>
                                                                                                </td>
                                                                                            </tr>
                                                                                        <?php endforeach; ?>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No classes found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const addClassBtn = document.getElementById('addClassBtn');
const editForm = document.getElementById('editForm');
const cancelClassBtn = document.getElementById('cancelClassBtn');

if (addClassBtn) {
    addClassBtn.addEventListener('click', function() {
        window.location.href = 'superadminclasses.php?edit=new';
    });
}

if (cancelClassBtn) {
    cancelClassBtn.addEventListener('click', function() {
        window.location.href = 'superadminclasses.php';
    });
}

if (window.location.search.includes('edit=new')) {
    editForm.style.display = 'block';
    if (addClassBtn) {
        addClassBtn.style.display = 'none';
    }
    document.getElementById('id').value = '';
    document.getElementById('acad_year').value = '';
    document.getElementById('yearsem').value = '';
    document.getElementById('section').value = '';
    document.getElementById('spec_id').value = '';
    document.getElementById('classname').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('timing_id').value = '';
    document.getElementById('reg_id').value = '';
    document.getElementById('status').value = '1';
}
</script>

<?php require_once("footer.php"); ?>

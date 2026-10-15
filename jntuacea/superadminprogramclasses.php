<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

$page_title = "Manage Program Classes";

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

$academic_years = $superadmin->getActiveAcademicYears()['data'] ?? [];
$programs = $superadmin->getAllPrograms();
$programRows = $programs['data'] ?? [];
$regulations = $superadmin->getAllRegulations()['data'] ?? [];
$timingTemplates = $superadmin->getAllTimingTemplates();
$timingTemplateRows = $timingTemplates['data'] ?? $timingTemplates ?? [];

$programMap = [];
foreach ($programRows as $prog) {
    $programMap[(int)$prog['id']] = $prog;
}

$editMode = isset($_GET['edit']);
$editProgId = !empty($_GET['prog_id']) ? (int)$_GET['prog_id'] : null;
$editAcadYear = $_GET['acad_year'] ?? '';
$editYearSem = $_GET['yearsem'] ?? '';

$selectedProgId = $editProgId;
$selectedAcadYear = $editAcadYear;
$selectedYearSem = $editYearSem;

$groupClassesResult = null;
$groupClasses = [];
$editSummary = null;
$groupScheduleRows = [];

$loadGroupContext = function($progId, $acadYear, $yearSem) use ($superadmin, $programMap, &$groupClassesResult, &$groupClasses, &$editSummary, &$groupScheduleRows) {
    $groupClassesResult = null;
    $groupClasses = [];
    $editSummary = null;
    $groupScheduleRows = [];

    if (empty($progId) || empty($acadYear) || empty($yearSem)) {
        return;
    }

    $groupClassesResult = $superadmin->getClassesByAcadYrPrgYrSem($acadYear, (int)$progId, $yearSem);
    $groupClasses = $groupClassesResult['data'] ?? [];

    if (empty($groupClasses)) {
        return;
    }

    $firstClass = $groupClasses[0];
    $programName = $programMap[(int)$progId]['prog_shortname'] ?? ($firstClass['prog_shortname'] ?? '');

    $editSummary = [
        'prog_id' => (int)$progId,
        'prog_shortname' => $programName,
        'acad_year' => $acadYear,
        'yearsem' => $yearSem,
        'start_date' => $firstClass['start_date'] ?? '',
        'end_date' => $firstClass['end_date'] ?? '',
        'timing_id' => $firstClass['timing_id'] ?? '',
        'reg_id' => $firstClass['reg_id'] ?? '',
        'regulation' => $firstClass['regulation'] ?? ($firstClass['reg'] ?? ''),
        'status' => $firstClass['status'] ?? 1,
        'classes' => $groupClasses,
    ];

    foreach ($groupClasses as $classRow) {
        if (empty($classRow['id'])) {
            continue;
        }
        $scheduleResult = $superadmin->getClassTimingSchedule((int)$classRow['id']);
        $scheduleRows = $scheduleResult['data'] ?? [];
        foreach ($scheduleRows as $scheduleRow) {
            $key = ($scheduleRow['timing_id'] ?? '') . '|' . ($scheduleRow['from_date'] ?? '') . '|' . ($scheduleRow['to_date'] ?? '');
            if (!isset($groupScheduleRows[$key])) {
                $groupScheduleRows[$key] = $scheduleRow;
            }
        }
    }

    if (!empty($groupScheduleRows)) {
        $groupScheduleRows = array_values($groupScheduleRows);
        usort($groupScheduleRows, function ($a, $b) {
            return strcmp($b['from_date'] ?? '', $a['from_date'] ?? '');
        });
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAcadYear = $_POST['acad_year'] ?? '';
    $postYearSem = $_POST['yearsem'] ?? '';
    $postProgId = !empty($_POST['prog_id']) ? (int)$_POST['prog_id'] : 0;
    $postStartDate = $_POST['start_date'] ?? '';
    $postEndDate = $_POST['end_date'] ?? '';
    $postTimingId = !empty($_POST['timing_id']) ? (int)$_POST['timing_id'] : 0;
    $postRegId = !empty($_POST['reg_id']) ? (int)$_POST['reg_id'] : 0;
    $postStatus = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $postScheduleFromDate = $_POST['schedule_from_date'] ?? '';

    $selectedProgId = $postProgId ?: $selectedProgId;
    $selectedAcadYear = $postAcadYear ?: $selectedAcadYear;
    $selectedYearSem = $postYearSem ?: $selectedYearSem;

    $selectedRegulation = '';
    foreach ($regulations as $regulation) {
        if ((int)$regulation['id'] === $postRegId) {
            $selectedRegulation = $regulation['regulation'];
            break;
        }
    }

    $existingGroupResult = (!empty($postAcadYear) && !empty($postYearSem) && !empty($postProgId))
        ? $superadmin->getClassesByAcadYrPrgYrSem($postAcadYear, $postProgId, $postYearSem)
        : null;
    $existingGroupRows = $existingGroupResult['data'] ?? [];

    if (!empty($_POST['delete_class_group'])) {
        if (!empty($existingGroupRows)) {
            $result = $superadmin->bulkDeleteClasses($postAcadYear, $postProgId, $postYearSem);
            if (!empty($result['status'])) {
                header("Location: superadminprogramclasses.php?msg=" . urlencode("Class group deleted successfully."));
                exit();
            }
            $errmsg = $result['error'] ?? 'Failed to delete class group.';
        } else {
            $errmsg = 'No classes are present for the selected program and year-sem.';
        }
    } elseif (!empty($_POST['save_group_schedule'])) {
        if (empty($postAcadYear) || empty($postYearSem) || empty($postProgId) || empty($postTimingId) || empty($postScheduleFromDate)) {
            $errmsg = 'Please select program, academic year, year-sem, timing template and effective date.';
            $editMode = true;
        } elseif (empty($existingGroupRows)) {
            $errmsg = 'No classes are present for the selected program and year-sem.';
            $editMode = true;
        } else {
            $hasOverlap = false;
            foreach ($existingGroupRows as $classRow) {
                $scheduleResult = $superadmin->getClassTimingSchedule((int)$classRow['id']);
                $scheduleRows = $scheduleResult['data'] ?? [];
                foreach ($scheduleRows as $scheduleRow) {
                    $rowFrom = $scheduleRow['from_date'] ?? '';
                    $rowTo = $scheduleRow['to_date'] ?? null;
                    if ($postScheduleFromDate === $rowFrom) {
                        $hasOverlap = true;
                        break 2;
                    }
                    if (!empty($rowTo) && $postScheduleFromDate > $rowFrom && $postScheduleFromDate <= $rowTo) {
                        $hasOverlap = true;
                        break 2;
                    }
                }
            }

            if ($hasOverlap) {
                $errmsg = 'Overlapping timing schedule dates are not allowed for this class group. Please choose a different effective date.';
                $editMode = true;
            } else {
                $result = $superadmin->saveBulkClassTimingSchedule($postAcadYear, $postProgId, $postYearSem, $postTimingId, $postScheduleFromDate);
                if (!empty($result['status'])) {
                    $url = 'superadminprogramclasses.php?edit=1&prog_id=' . $postProgId . '&acad_year=' . urlencode($postAcadYear) . '&yearsem=' . urlencode($postYearSem) . '&msg=' . urlencode('Class group timing schedule saved successfully.');
                    header('Location: ' . $url);
                    exit();
                }
                $errmsg = $result['error'] ?? 'Failed to save class group timing schedule.';
                $editMode = true;
            }
        }
    } elseif (isset($_POST['class_group'])) {
        if (empty($postAcadYear) || empty($postYearSem) || empty($postProgId) || empty($postTimingId) || empty($postRegId)) {
            $errmsg = 'Please fill all required fields.';
            $editMode = true;
        } elseif ($_POST['class_group'] === 'update') {
            if (!empty($existingGroupRows)) {
                $result = $superadmin->bulkUpdateClasses($postProgId, $postAcadYear, $postYearSem, [
                    'acad_year' => $postAcadYear,
                    'start_date' => $postStartDate,
                    'end_date' => $postEndDate,
                    'timing_id' => $postTimingId,
                    'reg_id' => $postRegId,
                    'status' => $postStatus
                ]);
                if (!empty($result['status'])) {
                    $url = 'superadminprogramclasses.php?edit=1&prog_id=' . $postProgId . '&acad_year=' . urlencode($postAcadYear) . '&yearsem=' . urlencode($postYearSem) . '&msg=' . urlencode('Classes updated successfully for all specializations in the program.');
                    header('Location: ' . $url);
                    exit();
                }
                $errmsg = $result['error'] ?? 'Failed to update class group.';
                $editMode = true;
            } else {
                $errmsg = 'No classes are present for the selected program and year-sem.';
                $editMode = true;
            }
        } elseif ($_POST['class_group'] === 'add') {
            if (!empty($existingGroupRows)) {
                $errmsg = 'Classes are already present for the selected program and year-sem.';
                $editMode = true;
            } else {
                $dept = 0;
                $specializations = $superadmin->getSpecializationsByProgramID($postProgId, $dept);
                $specRows = $specializations['data'] ?? [];
                if (empty($specRows)) {
                    $errmsg = 'No specializations found for the selected program.';
                    $editMode = true;
                } else {
                    $result = ['status' => 0];
                    foreach ($specRows as $spec) {
                        $data = [
                            'acad_year' => $postAcadYear,
                            'classname' => $superadmin->generateClassName($spec['spec_shortname'] ?? 'Class', $postYearSem),
                            'yearsem' => $postYearSem,
                            'section' => '',
                            'spec_id' => $spec['id'],
                            'start_date' => $postStartDate,
                            'end_date' => $postEndDate,
                            'timing_id' => $postTimingId,
                            'reg_id' => $postRegId,
                            'status' => $postStatus,
                        ];
                        $result = $superadmin->addOrUpdateClass($data);
                        if (empty($result['status'])) {
                            break;
                        }
                    }
                    if (!empty($result['status'])) {
                        $url = 'superadminprogramclasses.php?edit=1&prog_id=' . $postProgId . '&acad_year=' . urlencode($postAcadYear) . '&yearsem=' . urlencode($postYearSem) . '&msg=' . urlencode('Classes added successfully for all specializations in the program.');
                        header('Location: ' . $url);
                        exit();
                    }
                    $errmsg = $result['error'] ?? 'Failed to add class group.';
                    $editMode = true;
                }
            }
        }
    }
}

if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$groupedClasses = $superadmin->getGroupedClassesByProgram();
$groupedClassRows = $groupedClasses['data'] ?? $groupedClasses ?? [];

$groupedCards = [];
foreach ($groupedClassRows as $classGroup) {
    $academicYear = $classGroup['acad_year'] ?? '';
    $programName = $classGroup['prog_shortname'] ?? 'Unknown Program';
    if (!isset($groupedCards[$academicYear])) {
        $groupedCards[$academicYear] = [];
    }
    if (!isset($groupedCards[$academicYear][$programName])) {
        $groupedCards[$academicYear][$programName] = [];
    }
    $groupedCards[$academicYear][$programName][] = $classGroup;
}
if (!empty($groupedCards)) {
    krsort($groupedCards);
}

if ($editMode && !empty($selectedProgId) && !empty($selectedAcadYear) && !empty($selectedYearSem)) {
    $loadGroupContext($selectedProgId, $selectedAcadYear, $selectedYearSem);
}

require_once("superadminheader.php");
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header">Manage Program Classes</div>
        <div class="card-body">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if (!empty($errmsg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errmsg); ?></div>
            <?php endif; ?>

            <button type="button" class="btn btn-primary mb-3" id="addClassBtn" style="display: <?= $editMode ? 'none' : 'inline-block'; ?>;">Add / Update Class Group</button>

            <div id="editForm" style="display: <?= $editMode ? 'block' : 'none'; ?>;">
                <div class="card mb-4 border-0 bg-light">
                    <div class="card-header"><?= !empty($editSummary) ? 'Edit Class Group' : 'Add Class Group'; ?></div>
                    <div class="card-body">
                        <form action="superadminprogramclasses.php" method="post" class="row g-3">
                            <div class="col-md-4">
                                <label for="prog_id" class="form-label">Program</label>
                                <select name="prog_id" id="prog_id" class="form-select" required>
                                    <option value="">Select Program</option>
                                    <?php foreach ($programRows as $prog): ?>
                                        <option value="<?= (int)$prog['id']; ?>" <?= (!empty($selectedProgId) && (int)$selectedProgId === (int)$prog['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($prog['prog_shortname']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="acad_year" class="form-label">Academic Year</label>
                                <select name="acad_year" id="acad_year" class="form-select" required>
                                    <option value="">Select Academic Year</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?= htmlspecialchars($year['acad_year']); ?>" <?= (!empty($selectedAcadYear) && $selectedAcadYear == $year['acad_year']) ? 'selected' : ''; ?>><?= htmlspecialchars($year['acad_year']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="yearsem" class="form-label">Year-Sem</label>
                                <select name="yearsem" id="yearsem" class="form-select" required>
                                    <option value="">Select Year-Sem</option>
                                    <?php foreach ($yearSemOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option); ?>" <?= (!empty($selectedYearSem) && $selectedYearSem == $option) ? 'selected' : ''; ?>><?= htmlspecialchars($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required value="<?= !empty($editSummary['start_date']) ? htmlspecialchars($editSummary['start_date']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required value="<?= !empty($editSummary['end_date']) ? htmlspecialchars($editSummary['end_date']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="timing_id" class="form-label">Default Timing Template</label>
                                <select name="timing_id" id="timing_id" class="form-select" required>
                                    <option value="">-- Select Class Timing Template --</option>
                                    <?php foreach ($timingTemplateRows as $template): ?>
                                        <option value="<?= (int)$template['timing_id']; ?>" <?= (!empty($editSummary['timing_id']) && (int)$editSummary['timing_id'] === (int)$template['timing_id']) ? 'selected' : ''; ?>>Template - <?= (int)$template['timing_id']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="reg_id" class="form-label">Regulation</label>
                                <select name="reg_id" id="reg_id" class="form-select" required>
                                    <option value="">Select Regulation</option>
                                    <?php foreach ($regulations as $regulation): ?>
                                        <option value="<?= (int)$regulation['id']; ?>" <?= (!empty($editSummary['reg_id']) && (int)$editSummary['reg_id'] === (int)$regulation['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($regulation['regulation'] . (!empty($regulation['prog_id']) && !empty($programMap[(int)$regulation['prog_id']]['prog_shortname']) ? ' - ' . $programMap[(int)$regulation['prog_id']]['prog_shortname'] : '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="1" <?= (!isset($editSummary['status']) || (int)$editSummary['status'] === 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?= (isset($editSummary['status']) && (int)$editSummary['status'] === 0) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <input type="hidden" name="class_group" id="class_group" value="<?= !empty($editSummary) ? 'update' : 'add'; ?>">
                                <button type="button" class="btn btn-outline-danger" id="cancelClassBtn">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Class Group</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($editSummary)): ?>
                    <div class="card mb-4 border-0 bg-light">
                        <div class="card-header">Class Group Timing Schedule</div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Only same-date or closed-range overlaps are blocked. New dates after the current active start date are allowed.</p>
                            <form action="superadminprogramclasses.php?edit=1&prog_id=<?= (int)$selectedProgId; ?>&acad_year=<?= urlencode($selectedAcadYear); ?>&yearsem=<?= urlencode($selectedYearSem); ?>" method="post" class="row g-3 align-items-end">
                                <input type="hidden" name="save_group_schedule" value="1">
                                <input type="hidden" name="prog_id" value="<?= (int)$selectedProgId; ?>">
                                <input type="hidden" name="acad_year" value="<?= htmlspecialchars($selectedAcadYear); ?>">
                                <input type="hidden" name="yearsem" value="<?= htmlspecialchars($selectedYearSem); ?>">
                                <div class="col-md-5">
                                    <label for="schedule_timing_id" class="form-label">Timing Template</label>
                                    <select name="timing_id" id="schedule_timing_id" class="form-select" required>
                                        <option value="">-- Select Class Timing Template --</option>
                                        <?php foreach ($timingTemplateRows as $template): ?>
                                            <option value="<?= (int)$template['timing_id']; ?>">Template - <?= (int)$template['timing_id']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="schedule_from_date" class="form-label">Effective From Date</label>
                                    <input type="date" name="schedule_from_date" id="schedule_from_date" class="form-control" required value="<?= date('Y-m-d'); ?>">
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
                                        <?php if (!empty($groupScheduleRows)): ?>
                                            <?php foreach ($groupScheduleRows as $schedule): ?>
                                                <tr>
                                                    <td><?= (int)($schedule['timing_id'] ?? 0); ?></td>
                                                    <td><?= htmlspecialchars($schedule['from_date'] ?? ''); ?></td>
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

            <?php if (!empty($groupedCards)): ?>
                <div class="accordion" id="acadYearAccordion">
                    <?php $yearIndex = 0; foreach ($groupedCards as $academicYear => $programSet): $yearIndex++; ?>
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="heading-year-<?= $yearIndex; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-year-<?= $yearIndex; ?>" aria-expanded="false" aria-controls="collapse-year-<?= $yearIndex; ?>">
                                    Academic Year: <?= htmlspecialchars($academicYear); ?>
                                </button>
                            </h2>
                            <div id="collapse-year-<?= $yearIndex; ?>" class="accordion-collapse collapse" aria-labelledby="heading-year-<?= $yearIndex; ?>" data-bs-parent="#acadYearAccordion">
                                <div class="accordion-body">
                                    <div class="accordion" id="programAccordion-<?= $yearIndex; ?>">
                                        <?php $programIndex = 0; foreach ($programSet as $programName => $classGroups): $programIndex++; ?>
                                            <div class="accordion-item mb-3">
                                                <h2 class="accordion-header" id="heading-program-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" aria-expanded="false" aria-controls="collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>">
                                                        Program: <?= htmlspecialchars($programName); ?>
                                                    </button>
                                                </h2>
                                                <div id="collapse-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" class="accordion-collapse collapse" aria-labelledby="heading-program-<?= $yearIndex; ?>-<?= $programIndex; ?>" data-bs-parent="#programAccordion-<?= $yearIndex; ?>">
                                                    <div class="accordion-body p-0">
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered table-striped align-middle mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Year-Sem</th>
                                                                        <th>Start Date</th>
                                                                        <th>End Date</th>
                                                                        <th>Timing ID</th>
                                                                        <th>Regulation</th>
                                                                        <th>Status</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($classGroups as $classGroup): ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($classGroup['yearsem'] ?? ''); ?></td>
                                                                            <td><?= htmlspecialchars($classGroup['start_date'] ?? ''); ?></td>
                                                                            <td><?= htmlspecialchars($classGroup['end_date'] ?? ''); ?></td>
                                                                            <td><?= (int)($classGroup['timing_id'] ?? 0); ?></td>
                                                                            <td><?= htmlspecialchars($classGroup['regulation'] ?? ($classGroup['reg'] ?? '')); ?></td>
                                                                            <td>
                                                                                <?php
                                                                                $endDate = !empty($classGroup['end_date']) ? strtotime($classGroup['end_date']) : false;
                                                                                $now = time();
                                                                                echo ($endDate && $endDate < $now) ? 'Completed' : (!empty($classGroup['status']) ? 'Active' : 'Inactive');
                                                                                ?>
                                                                            </td>
                                                                            <td>
                                                                                <a href="superadminprogramclasses.php?edit=1&prog_id=<?= (int)($classGroup['prog_id'] ?? 0); ?>&acad_year=<?= urlencode($classGroup['acad_year'] ?? ''); ?>&yearsem=<?= urlencode($classGroup['yearsem'] ?? ''); ?>" class="btn btn-sm btn-primary">Edit</a>
                                                                                <form action="superadminprogramclasses.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this class group?');">
                                                                                    <input type="hidden" name="delete_class_group" value="1">
                                                                                    <input type="hidden" name="acad_year" value="<?= htmlspecialchars($classGroup['acad_year'] ?? ''); ?>">
                                                                                    <input type="hidden" name="prog_id" value="<?= (int)($classGroup['prog_id'] ?? 0); ?>">
                                                                                    <input type="hidden" name="yearsem" value="<?= htmlspecialchars($classGroup['yearsem'] ?? ''); ?>">
                                                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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
            <?php else: ?>
                <div class="alert alert-info">No class groups found.</div>
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
        window.location.href = 'superadminprogramclasses.php?edit=new';
    });
}

if (cancelClassBtn) {
    cancelClassBtn.addEventListener('click', function() {
        window.location.href = 'superadminprogramclasses.php';
    });
}

if (window.location.search.includes('edit=new') && editForm) {
    editForm.style.display = 'block';
    if (addClassBtn) {
        addClassBtn.style.display = 'none';
    }
    const ids = ['prog_id','acad_year','yearsem','start_date','end_date','timing_id','reg_id','status','class_group'];
    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === 'status') el.value = '1';
        else if (id === 'class_group') el.value = 'add';
        else el.value = '';
    });
}
</script>

<?php require_once("footer.php"); ?>

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$pagetitle = "Faculty Weekly Timetable";

require_once('admin.class.php');
require_once('hod.class.php');
require_once('faculty.class.php');
require_once('timetable.class.php');

// Determine user role and set appropriate objects
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$facId = $_SESSION['facid'] ?? 0;
$deptId = $_SESSION['dept_id'] ?? 0;

// Initialize objects based on role
if ($userRole === 'admin') {
    $userObj = new Admin();
    require_once('adminheader.php');
} elseif ($userRole === 'academic_section') {
    $userObj = new Admin();
    require_once('academicsectionheader.php');
} elseif ($userRole === 'hod') {
    $userObj = new HOD();
    require_once('hodheader.php');
} elseif ($userRole === 'faculty') {
    $userObj = new HOD();
    require_once('facheader.php');
} else {
    header("Location: ./");
    exit;
}

$timetableObj = new Timetable();

// Default selected faculty (for faculty role, use their own ID)
$selectedFacultyId = 0;
$selectedDeptId = 0;
$msg = '';
$msgtype = 'info';

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    if (!empty($_POST['whatdo']) && $_POST['whatdo'] == 'viewtimetable') {
        $selectedFacultyId = !empty($_POST['facultyid']) ? intval($_POST['facultyid']) : 0;
        $selectedDeptId = !empty($_POST['deptid']) ? intval($_POST['deptid']) : 0;
    }
}

// For faculty role, force their own ID
if ($userRole === 'faculty') {
    $selectedFacultyId = $facId;
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

function getWeekdays()
{
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
}

function normalizeFacultyRecord($faculty)
{
    return [
        'id' => (int)($faculty['id'] ?? $faculty['facid'] ?? $faculty['faculty_id'] ?? 0),
        'name' => $faculty['name'] ?? $faculty['facname'] ?? $faculty['faculty_name'] ?? $faculty['username'] ?? '',
        'designation' => $faculty['designation'] ?? '',
        'deptname' => $faculty['deptname'] ?? $faculty['dept_fullname'] ?? ''
    ];
}

function buildSubjectClassMapFromSchedule($schedule)
{
    $subjectClassMap = [];

    foreach ($schedule as $day => $hours) {
        foreach ($hours as $hourId => $entry) {
            $subcode = $entry['subcode'];
            $classname = $entry['classname'];

            if (!isset($subjectClassMap[$subcode])) {
                $subjectClassMap[$subcode] = [
                    'subcode' => $subcode,
                    'subfullname' => $entry['subfullname'] ?? $subcode,
                    'subtype' => $entry['subtype'] ?? 'Theory',
                    'classes' => []
                ];
            }

            if (!in_array($classname, $subjectClassMap[$subcode]['classes'])) {
                $subjectClassMap[$subcode]['classes'][] = $classname;
            }
        }
    }

    usort($subjectClassMap, function ($a, $b) {
        return strcmp($a['subcode'], $b['subcode']);
    });

    return array_values($subjectClassMap);
}

function buildUniqueClassesFromSubjects($subjectClassMap)
{
    $uniqueClasses = [];
    foreach ($subjectClassMap as $subject) {
        foreach ($subject['classes'] as $class) {
            if (!in_array($class, $uniqueClasses)) {
                $uniqueClasses[] = $class;
            }
        }
    }
    return $uniqueClasses;
}

/**
 * Merge multiple per-group subject-class maps (keyed by subcode) into one
 * combined, de-duplicated list. Used to build a single Subjects/Courses
 * legend across all timing groups instead of repeating one per group.
 */
function mergeSubjectClassMaps($subjectClassMaps)
{
    $merged = [];

    foreach ($subjectClassMaps as $subjectList) {
        foreach ($subjectList as $subject) {
            $subcode = $subject['subcode'];

            if (!isset($merged[$subcode])) {
                $merged[$subcode] = [
                    'subcode' => $subject['subcode'],
                    'subfullname' => $subject['subfullname'],
                    'subtype' => $subject['subtype'],
                    'classes' => []
                ];
            }

            foreach ($subject['classes'] as $class) {
                if (!in_array($class, $merged[$subcode]['classes'])) {
                    $merged[$subcode]['classes'][] = $class;
                }
            }
        }
    }

    usort($merged, function ($a, $b) {
        return strcmp($a['subcode'], $b['subcode']);
    });

    return array_values($merged);
}

/**
 * Merge multiple per-group overlapping-entry lists into one combined list
 * for the single, page-level "overlapping classes" notice.
 */
function mergeOverlappingEntries($overlappingEntryLists)
{
    $merged = [];
    foreach ($overlappingEntryLists as $list) {
        foreach ($list as $entry) {
            $merged[] = $entry;
        }
    }
    return $merged;
}

function buildOverlappingEntries($schedule, $allEntries)
{
    $displayedKeys = [];
    foreach ($schedule as $day => $hours) {
        foreach ($hours as $hourId => $entry) {
            $displayedKeys[] = $day . '_' . $hourId;
        }
    }

    $overlappingEntries = [];
    for ($i = 0; $i < count($allEntries); $i++) {
        for ($j = $i + 1; $j < count($allEntries); $j++) {
            if (
                $allEntries[$i]['day'] === $allEntries[$j]['day'] &&
                $allEntries[$i]['starttime'] === $allEntries[$j]['starttime'] &&
                $allEntries[$i]['endtime'] === $allEntries[$j]['endtime']
            ) {
                $key_i = $allEntries[$i]['day'] . '_' . $allEntries[$i]['hourId'];
                $key_j = $allEntries[$j]['day'] . '_' . $allEntries[$j]['hourId'];

                if (in_array($key_i, $displayedKeys) && !in_array($key_j, $displayedKeys)) {
                    $overlappingEntries[] = $allEntries[$j];
                } elseif (in_array($key_j, $displayedKeys) && !in_array($key_i, $displayedKeys)) {
                    $overlappingEntries[] = $allEntries[$i];
                }
            }
        }
    }

    return $overlappingEntries;
}

function detectGroupLabelFromClassname($classname)
{
    $classname = trim((string)$classname);
    $lower = strtolower($classname);

    if (strpos($lower, 'm.tech') !== false || strpos($lower, 'mtech') !== false) {
        return 'M.Tech';
    }
    if (strpos($lower, 'mca') !== false) {
        return 'MCA';
    }
    if (strpos($lower, 'b.tech') !== false || strpos($lower, 'btech') !== false) {
        return 'B.Tech';
    }

    return $classname !== '' ? $classname : 'Other';
}

function indexTimeslotsByHourId($allTimeslots)
{
    $indexed = [];
    foreach ($allTimeslots as $slot) {
        if (isset($slot['id'])) {
            $indexed[(int)$slot['id']] = $slot;
        }
    }
    return $indexed;
}

function buildScheduleTimingGroups($schedule, $allEntries, $allTimeslots)
{
    $timeslotsByHourId = indexTimeslotsByHourId($allTimeslots);
    $groups = [];

    foreach ($schedule as $day => $hours) {
        foreach ($hours as $hourId => $entry) {
            $slotMeta = $timeslotsByHourId[(int)$hourId] ?? null;
            $timingId = (int)($slotMeta['timing_id'] ?? 0);
            $groupKey = $timingId > 0 ? 'timing_' . $timingId : 'default';

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'timing_id' => $timingId,
                    'label' => detectGroupLabelFromClassname($entry['classname'] ?? ''),
                    'schedule' => [],
                    'timeslots' => [],
                    'entries' => []
                ];
            }

            $groups[$groupKey]['schedule'][$day][$hourId] = $entry;
        }
    }

    foreach ($groups as $groupKey => &$group) {
        foreach ($group['schedule'] as $day => $hours) {
            foreach ($hours as $hourId => $entry) {
                if (isset($timeslotsByHourId[(int)$hourId])) {
                    $group['timeslots'][(int)$hourId] = $timeslotsByHourId[(int)$hourId];
                }
            }
        }

        if ($group['timing_id'] > 0) {
            foreach ($allTimeslots as $slot) {
                if ((int)($slot['timing_id'] ?? 0) === $group['timing_id']) {
                    $group['timeslots'][(int)$slot['id']] = $slot;
                }
            }
        }

        foreach ($allEntries as $entry) {
            $entryHourId = (int)($entry['hourId'] ?? 0);
            $slotMeta = $timeslotsByHourId[$entryHourId] ?? null;
            $entryTimingId = (int)($slotMeta['timing_id'] ?? 0);
            $entryGroupKey = $entryTimingId > 0 ? 'timing_' . $entryTimingId : 'default';

            if ($entryGroupKey === $groupKey) {
                $group['entries'][] = $entry;
            }
        }

        $group['timeslots'] = array_values($group['timeslots']);
        usort($group['timeslots'], function ($a, $b) {
            $cmp = strcmp($a['start_time'], $b['start_time']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int)$a['id'] <=> (int)$b['id']);
        });
    }
    unset($group);

    uasort($groups, function ($a, $b) {
        $aStart = $a['timeslots'][0]['start_time'] ?? '99:99:99';
        $bStart = $b['timeslots'][0]['start_time'] ?? '99:99:99';
        return strcmp($aStart, $bStart);
    });

    return array_values($groups);
}
/**
 * Build unified time grid from faculty schedule
 * Groups hour IDs by actual start/end times, sorted chronologically
 * Shows ALL time slots from templates used, but filters out unused extra hours
 */
function buildUnifiedTimeGrid($rawSchedule, $allTimeslots)
{
    $timeSlots = [];
    $hourIdMapping = [];
    $usedHourIds = [];

    // Collect all hour IDs actually used by faculty
    foreach ($rawSchedule as $day => $hours) {
        foreach ($hours as $hourId => $entry) {
            $usedHourIds[] = $hourId;
        }
    }

    // Step 1: Collect all time slots from the templates
    foreach ($allTimeslots as $timeslot) {
        $startTime = $timeslot['start_time'];
        $endTime = $timeslot['end_time'];
        $slotKey = $startTime . '-' . $endTime;
        $isExtra = (strpos($timeslot['hour'], 'E.Hr') !== false || strpos($timeslot['hour_desc'], 'Extra') !== false);

        // Skip extra hours if not used by faculty
        if ($isExtra && !in_array($timeslot['id'], $usedHourIds)) {
            continue;
        }

        if (!isset($timeSlots[$slotKey])) {
            $timeSlots[$slotKey] = [
                'slot_key' => $slotKey,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hour_ids' => [],
                'hour_desc' => $timeslot['hour_desc'],
                'is_extra' => $isExtra
            ];
        }

        // Map this hour_id to the unified slot
        if (!in_array($timeslot['id'], $timeSlots[$slotKey]['hour_ids'])) {
            $timeSlots[$slotKey]['hour_ids'][] = $timeslot['id'];
        }
        $hourIdMapping[$timeslot['id']] = $slotKey;
    }

    // Step 2: Sort by start_time
    uasort($timeSlots, function ($a, $b) {
        return strcmp($a['start_time'], $b['start_time']);
    });

    // Step 3: Re-index array
    $timeSlots = array_values($timeSlots);

    return ['slots' => $timeSlots, 'mapping' => $hourIdMapping];
}

/**
 * Process schedule with unified time grid and merge consecutive hours
 */
function processSchedule($rawSchedule, $unifiedGrid)
{
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $processed = [];
    $slots = $unifiedGrid['slots'];
    $mapping = $unifiedGrid['mapping'];

    foreach ($weekdays as $day) {
        $daySchedule = $rawSchedule[$day] ?? [];
        $slotSchedule = [];

        // Map entries to unified slots
        foreach ($daySchedule as $hourId => $entry) {
            $slotKey = $mapping[$hourId] ?? null;
            if ($slotKey) {
                // Find slot index
                foreach ($slots as $idx => $slot) {
                    if ($slot['slot_key'] === $slotKey) {
                        $slotSchedule[$idx] = $entry;
                        break;
                    }
                }
            }
        }

        // Merge consecutive hours
        $merged = [];
        $skip = [];
        $slotIndices = array_keys($slotSchedule);
        sort($slotIndices);

        for ($i = 0; $i < count($slotIndices); $i++) {
            $currentIdx = $slotIndices[$i];

            if (in_array($currentIdx, $skip)) continue;

            $current = $slotSchedule[$currentIdx];
            $consecutive = 1;
            $endTime = $current['endtime'];

            // Check for consecutive slots with same subject and class
            for ($j = $i + 1; $j < count($slotIndices); $j++) {
                $nextIdx = $slotIndices[$j];

                // Must be consecutive indices
                if ($nextIdx !== $slotIndices[$j - 1] + 1) break;

                $next = $slotSchedule[$nextIdx];

                if (
                    $current['subcode'] === $next['subcode'] &&
                    $current['classname'] === $next['classname']
                ) {
                    $consecutive++;
                    $endTime = $next['endtime'];
                    $skip[] = $nextIdx;
                } else {
                    break;
                }
            }

            $current['colspan'] = $consecutive;
            $current['endtime'] = $endTime;
            $current['slot_index'] = $currentIdx;
            $merged[$currentIdx] = $current;
        }

        $processed[$day] = ['merged' => $merged, 'skip' => $skip];
    }

    return $processed;
}

// Fetch departments (for admin and academic_section only)
$departments = [];
if ($userRole === 'admin' || $userRole === 'academic_section') {
    $deptResult = $userObj->getAllDepartments();
    if (!empty($deptResult['status']) && $deptResult['status'] == 1) {
        $departments = $deptResult['data'];
    }
}

// Fetch faculty list based on role
$faculties = [];
if ($userRole === 'admin' || $userRole === 'academic_section') {
    // Admin can see all faculty
    if (!empty($selectedDeptId)) {
        $facResult = $userObj->getFacultyByDepartment($selectedDeptId);
    } else {
        // Get all faculties across departments
        $facResult = ['status' => 0, 'data' => []];
        foreach ($departments as $dept) {
            $tempResult = $userObj->getFacultyByDepartment($dept['id']);
            if (!empty($tempResult['status']) && $tempResult['status'] == 1) {
                $facResult['data'] = array_merge($facResult['data'], $tempResult['data']);
                $facResult['status'] = 1;
            }
        }
    }
    if (!empty($facResult['status']) && $facResult['status'] == 1) {
        $faculties = array_map('normalizeFacultyRecord', $facResult['data']);
    }
} elseif ($userRole === 'hod') {
    // HOD can see only their department faculty
    $facResult = $userObj->getFacultyByDepartment($deptId);
    if (!empty($facResult['status']) && $facResult['status'] == 1) {
        $faculties = array_map('normalizeFacultyRecord', $facResult['data']);
    }
} elseif ($userRole === 'faculty') {
    // Faculty can see only themselves
    $facResult = $userObj->getFacultyDetails($facId);
    if (!empty($facResult['status']) && $facResult['status'] == 1) {
        $faculties = [normalizeFacultyRecord($facResult['data'])];
    }
}

// Fetch timetable data if faculty is selected
$weeklyTimetable = [];
$facultyName = '';
$facultyDept = '';
$totalHours = 0;
$processedSchedule = [];
$unifiedTimeGrid = [];
$uniqueSubjects = [];
$uniqueClasses = [];
$overlappingEntries = [];
$timetableGroups = [];
$renderMultipleGroups = false;

if ($selectedFacultyId > 0) {
    $timetableResult = $timetableObj->getFacultyWeeklyTimetable($selectedFacultyId);
    if (!empty($timetableResult['status']) && $timetableResult['status'] == 1) {
        $weeklyTimetable = $timetableResult;
        $facultyName = $timetableResult['facultyname'] ?? '';
        $facultyDept = $timetableResult['deptname'] ?? '';
        $totalHours = $timetableResult['totalhours'] ?? 0;

        $allEntriesResult = $timetableObj->getAllFacultyTimetableEntries($selectedFacultyId);
        $allEntries = [];
        if (!empty($allEntriesResult['status']) && $allEntriesResult['status'] == 1) {
            $allEntries = $allEntriesResult['data'];
        }

        $allTimeslotsResult = $timetableObj->getAllTimeSlotsForFaculty($selectedFacultyId);
        $allTimeslots = [];
        if (!empty($allTimeslotsResult['status']) && $allTimeslotsResult['status'] == 1) {
            $allTimeslots = $allTimeslotsResult['data'];
        }

        $timingGroups = buildScheduleTimingGroups($timetableResult['schedule'], $allEntries, $allTimeslots);

        if (empty($timingGroups)) {
            $timingGroups[] = [
                'key' => 'default',
                'timing_id' => 0,
                'label' => 'Timetable',
                'schedule' => $timetableResult['schedule'],
                'timeslots' => $allTimeslots,
                'entries' => $allEntries
            ];
        }

        foreach ($timingGroups as $group) {
            $groupProcessedSchedule = [];
            $groupUnifiedTimeGrid = [];
            $groupUniqueSubjects = [];
            $groupUniqueClasses = [];
            $groupOverlappingEntries = [];

            if (!empty($group['schedule'])) {
                $groupUniqueSubjects = buildSubjectClassMapFromSchedule($group['schedule']);
                $groupUniqueClasses = buildUniqueClassesFromSubjects($groupUniqueSubjects);
                $groupOverlappingEntries = buildOverlappingEntries($group['schedule'], $group['entries']);
                $groupUnifiedTimeGrid = buildUnifiedTimeGrid($group['schedule'], $group['timeslots']);
                $groupProcessedSchedule = processSchedule($group['schedule'], $groupUnifiedTimeGrid);
            }

            $timetableGroups[] = [
                'label' => $group['label'],
                'schedule' => $group['schedule'],
                'processedSchedule' => $groupProcessedSchedule,
                'unifiedTimeGrid' => $groupUnifiedTimeGrid,
                'uniqueSubjects' => $groupUniqueSubjects,
                'uniqueClasses' => $groupUniqueClasses,
                'overlappingEntries' => $groupOverlappingEntries
            ];
        }

        if (count($timetableGroups) === 1) {
            $processedSchedule = $timetableGroups[0]['processedSchedule'];
            $unifiedTimeGrid = $timetableGroups[0]['unifiedTimeGrid'];
            $uniqueSubjects = $timetableGroups[0]['uniqueSubjects'];
            $uniqueClasses = $timetableGroups[0]['uniqueClasses'];
            $overlappingEntries = $timetableGroups[0]['overlappingEntries'];
        } else {
            $renderMultipleGroups = true;
            $processedSchedule = [];
            $unifiedTimeGrid = [];
            $uniqueSubjects = [];
            $uniqueClasses = [];
            $overlappingEntries = [];
        }
    }
}

// Build page-level (faculty-wide) combined stats that must render ONCE,
// regardless of how many timing groups (e.g. B.Tech / M.Tech) exist.
$combinedUniqueSubjects = [];
$combinedUniqueClasses = [];
$combinedOverlappingEntries = [];

if (!empty($timetableGroups)) {
    $allGroupSubjectMaps = array_column($timetableGroups, 'uniqueSubjects');
    $combinedUniqueSubjects = mergeSubjectClassMaps($allGroupSubjectMaps);
    $combinedUniqueClasses = buildUniqueClassesFromSubjects($combinedUniqueSubjects);

    $allGroupOverlaps = array_column($timetableGroups, 'overlappingEntries');
    $combinedOverlappingEntries = mergeOverlappingEntries($allGroupOverlaps);
}

// Get current day and time for highlighting
$currentDay = date('l');
$currentTime = date('H:i:s');

?>

<link rel="stylesheet" href="css/faculty_weekly_timetable.css">

<div class="container">



    <!-- Faculty Selection Form -->
    <?php if ($userRole !== 'faculty'): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-week-fill"></i> Faculty Weekly Timetable</h5>
            </div>
            <div class="card-body">
                <form action="faculty_weekly_timetable.php" method="post">
                    <div class="row g-3">
                        <?php if ($userRole === 'admin' || $userRole === 'academic_section'): ?>
                            <div class="col-md-4">
                                <label class="form-label"><strong>Department</strong></label>
                                <select name="deptid" id="deptid" class="form-select">
                                    <option value="">-- All Departments --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= ($selectedDeptId == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['dept_fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-<?= ($userRole === 'admin' || $userRole === 'academic_section') ? '6' : '10' ?>">
                            <label class="form-label"><strong>Faculty</strong> <span class="text-danger">*</span></label>
                            <select name="facultyid" id="facultyid" class="form-select" required>
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?= $faculty['id'] ?>" <?= ($selectedFacultyId == $faculty['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($faculty['name']) ?> (<?= htmlspecialchars($faculty['designation'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <input type="hidden" name="whatdo" value="viewtimetable">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> View Timetable</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-week-fill"></i> Faculty Weekly Timetable</h5>
            </div>
        </div>
    <?php endif; ?>
    <!-- Alert Messages -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgtype ?> alert-dismissible fade show">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Timetable Display -->
    <?php if ($selectedFacultyId > 0 && !empty($weeklyTimetable['schedule'])): ?>

        <?php
        $displayGroups = $renderMultipleGroups ? $timetableGroups : [[
            'label' => '',
            'processedSchedule' => $processedSchedule,
            'unifiedTimeGrid' => $unifiedTimeGrid,
            'uniqueSubjects' => $uniqueSubjects,
            'uniqueClasses' => $uniqueClasses,
            'overlappingEntries' => $overlappingEntries
        ]];
        ?>

        <!-- Stats Cards (faculty-level, rendered ONCE using combined totals) -->
        <div class="stats-row">
            <div class="card stats-card" style="border-left: 4px solid #28a745;">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><i class="bi bi-clock-fill"></i> Teaching Hours/Week</h6>
                    <h3 class="mb-0"><?= $totalHours ?></h3>
                </div>
            </div>
            <div class="card stats-card" style="border-left: 4px solid #007bff;">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><i class="bi bi-book-fill"></i> Subjects/Courses</h6>
                    <h3 class="mb-0"><?= count($combinedUniqueSubjects) ?></h3>
                </div>
            </div>
            <div class="card stats-card" style="border-left: 4px solid #17a2b8;">
                <div class="card-body">
                    <h6 class="text-muted mb-1"><i class="bi bi-people-fill"></i> Classes</h6>
                    <h3 class="mb-0"><?= count($combinedUniqueClasses) ?></h3>
                </div>
            </div>
        </div>

        <!-- Live Faculty Location Status (faculty-level, rendered ONCE) -->
        <div class="card mb-3" style="border-left: 4px solid #28a745;">
            <div class="card-body">
                <h6 class="mb-3">
                    <i class="bi bi-geo-alt-fill"></i> Where is <?= htmlspecialchars($facultyName) ?>?
                    <span class="badge bg-secondary ms-2" id="lastUpdated">Loading...</span>
                </h6>
                <div id="facultyCurrentLocation" style="min-height: 80px; display: flex; align-items: center;">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Detecting current location...</span>
                </div>
            </div>
        </div>

        <!-- View Toggle (Desktop/Tablet only) - toggles ALL group grid/list views together -->
        <div class="view-toggle">
            <button class="btn btn-primary" id="gridViewBtn" onclick="switchView('grid')">
                <i class="bi bi-grid-3x3-gap-fill"></i> Grid View
            </button>
            <button class="btn btn-outline-primary" id="listViewBtn" onclick="switchView('list')">
                <i class="bi bi-list-ul"></i> List View
            </button>
        </div>

        <?php foreach ($displayGroups as $groupIndex => $groupData): ?>
            <?php
            $processedSchedule = $groupData['processedSchedule'];
            $unifiedTimeGrid = $groupData['unifiedTimeGrid'];
            ?>
            <?php if ($renderMultipleGroups): ?>
                <div class="alert alert-secondary py-2 mb-3">
                    <strong><?= htmlspecialchars($groupData['label']) ?> Timetable</strong>
                </div>
            <?php endif; ?>

            <!-- Weekly Timetable Grid (Desktop/Tablet) -->
            <div class="card timetable-container" id="gridView-<?= $groupIndex ?>">
                <div class="card-body timetable-grid p-0">
                    <div style="overflow-x: auto;">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th class="day-header">Day / Time</th>
                                    <?php if (!empty($unifiedTimeGrid['slots'])): ?>
                                        <?php foreach ($unifiedTimeGrid['slots'] as $slot): ?>
                                            <th class="time-header <?= !empty($slot['is_extra']) ? 'extra-hour-header' : '' ?>">
                                                <div><?= htmlspecialchars($slot['hour_desc']) ?></div>
                                                <small style="font-weight: 400; opacity: 0.9;">
                                                    <?= $timetableObj->formatTime12Hour($slot['start_time']) ?> - <?= $timetableObj->formatTime12Hour($slot['end_time']) ?>
                                                </small>
                                                <?php if (!empty($slot['is_extra'])): ?>
                                                    <span class="extra-hour-label">(Optional)</span>
                                                <?php endif; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                foreach ($weekdays as $day):
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($day) ?></strong></td>
                                        <?php if (!empty($unifiedTimeGrid['slots'])): ?>
                                            <?php foreach ($unifiedTimeGrid['slots'] as $slotIdx => $slot): ?>
                                                <?php
                                                // Check if this slot should be skipped (part of merged cell)
                                                if (!empty($processedSchedule[$day]['skip']) && in_array($slotIdx, $processedSchedule[$day]['skip'])) {
                                                    continue;
                                                }

                                                $entry = $processedSchedule[$day]['merged'][$slotIdx] ?? null;

                                                // Check if this is current period
                                                $isCurrentPeriod = false;
                                                if ($currentDay === $day && $currentTime >= $slot['start_time'] && $currentTime <= $slot['end_time']) {
                                                    $isCurrentPeriod = true;
                                                }

                                                $colspan = $entry['colspan'] ?? 1;
                                                ?>
                                                <td <?= $colspan > 1 ? 'colspan="' . $colspan . '"' : '' ?>>
                                                    <?php if ($entry): ?>
                                                        <?php
                                                        $subtype = strtolower($entry['subtype'] ?? 'theory');
                                                        $cardClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : 'theory';
                                                        $hasMultipleClasses = isset($entry['classes']) && count($entry['classes']) > 1;
                                                        if ($hasMultipleClasses) {
                                                            $cardClass = 'multiple-classes';
                                                        }
                                                        ?>
                                                        <div class="period-card <?= $cardClass ?> <?= $isCurrentPeriod ? 'current-period' : '' ?>">
                                                            <?php if ($colspan > 1): ?>
                                                                <span class="consecutive-badge"><?= $colspan ?> hrs</span>
                                                            <?php endif; ?>
                                                            <?php
                                                            $displayCode = $entry['subcode'];
                                                            if (strlen($displayCode) > 12) {
                                                                $displayCode = substr($displayCode, 0, 12) . '...';
                                                            }
                                                            ?>
                                                            <span class="period-code" title="<?= htmlspecialchars($entry['subcode']) ?>"><?= htmlspecialchars($displayCode) ?></span>
                                                            <?php if (!empty($entry['subtype'])): ?>
                                                                <span class="period-type <?= ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : 'theory' ?>"><?= htmlspecialchars($entry['subtype']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($hasMultipleClasses): ?>
                                                                <div class="class-list">
                                                                    <i class="bi bi-people-fill"></i> <?= htmlspecialchars($entry['classname']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="period-location">
                                                                <i class="bi bi-building"></i> <?= htmlspecialchars($entry['buildingname'] ?? 'TBA') ?><br>
                                                                <i class="bi bi-door-open"></i> <?= htmlspecialchars($entry['classhallname'] ?? 'TBA') ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="free-slot">-</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div id="listView-<?= $groupIndex ?>">
                <?php
                $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                foreach ($weekdays as $day):
                ?>
                    <div class="day-card">
                        <div class="day-header">
                            <i class="bi bi-calendar-day"></i> <?= htmlspecialchars($day) ?>
                        </div>
                        <div>
                            <?php
                            $dayHasClasses = false;
                            if (!empty($unifiedTimeGrid['slots'])):
                                foreach ($unifiedTimeGrid['slots'] as $slotIdx => $slot):
                                    if (!empty($processedSchedule[$day]['skip']) && in_array($slotIdx, $processedSchedule[$day]['skip'])) {
                                        continue;
                                    }

                                    $entry = $processedSchedule[$day]['merged'][$slotIdx] ?? null;

                                    if ($entry):
                                        $dayHasClasses = true;
                                        $subtype = strtolower($entry['subtype'] ?? 'theory');
                                        $cardClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : 'theory';
                                        $colspan = $entry['colspan'] ?? 1;
                                        $hasMultipleClasses = isset($entry['classes']) && count($entry['classes']) > 1;
                            ?>
                                        <div class="period-item">
                                            <div class="period-time">
                                                <i class="bi bi-clock"></i>
                                                <?= $timetableObj->formatTime12Hour($entry['starttime']) ?> - <?= $timetableObj->formatTime12Hour($entry['endtime']) ?>
                                                <?php if ($colspan > 1): ?>
                                                    <span class="badge bg-warning text-dark ms-2"><?= $colspan ?> hrs</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="period-content">
                                                <div class="period-badge <?= $cardClass ?>"></div>
                                                <div class="period-details">
                                                    <div class="period-code-list"><?= htmlspecialchars($entry['subcode']) ?></div>
                                                    <span class="period-type-list <?= $cardClass ?>"><?= htmlspecialchars($entry['subtype']) ?></span>
                                                    <?php if ($hasMultipleClasses): ?>
                                                        <div style="font-size: 0.8rem; color: #2d3748; margin: 4px 0;">
                                                            <i class="bi bi-people-fill"></i> <?= htmlspecialchars($entry['classname']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="period-location-list">
                                                        <i class="bi bi-building"></i> <?= htmlspecialchars($entry['buildingname'] ?? 'TBA') ?> |
                                                        <i class="bi bi-door-open"></i> <?= htmlspecialchars($entry['classhallname'] ?? 'TBA') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php
                                    endif;
                                endforeach;
                            endif;

                            if (!$dayHasClasses):
                                ?>
                                <div class="free-period">
                                    <i class="bi bi-calendar-x"></i> No classes scheduled
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Subject and Class Legend (faculty-level, rendered ONCE using combined data) -->
        <div class="card mt-3">
            <div class="card-header">
                <strong><i class="bi bi-book"></i> Subjects/Courses (<?= count($combinedUniqueSubjects) ?>)</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Type</th>
                                <th>Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($combinedUniqueSubjects as $subject): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($subject['subcode']) ?></strong></td>
                                    <td><?= htmlspecialchars($subject['subfullname']) ?></td>
                                    <td>
                                        <?php
                                        $subtype = strtolower($subject['subtype'] ?? 'theory');
                                        $badgeClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'bg-success' : 'bg-primary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($subject['subtype']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars(implode(', ', $subject['classes'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($combinedOverlappingEntries)): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong><i class="bi bi-info-circle"></i> Note:</strong> The following classes occur at the same time as other classes and are not shown in the timetable grid above:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($combinedOverlappingEntries as $entry): ?>
                                <li>
                                    <strong><?= htmlspecialchars($entry['subcode']) ?></strong> -
                                    <?= htmlspecialchars($entry['classname']) ?>
                                    (<?= htmlspecialchars($entry['day']) ?>, <?= $timetableObj->formatTime12Hour($entry['starttime']) ?> - <?= $timetableObj->formatTime12Hour($entry['endtime']) ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php if ($selectedFacultyId > 0 && !empty($weeklyTimetable['schedule'])): ?>
    <script>
        // Compatibility data payload: one entry per timing group (B.Tech, M.Tech, etc.)
        const timetableDisplayGroups = <?= json_encode($displayGroups, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const facultyNameData = <?= json_encode($facultyName, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
<?php endif; ?>

<script>
    function getCurrentISTTime() {
        const now = new Date();
        const istTime = new Date(now.toLocaleString('en-US', {
            timeZone: 'Asia/Kolkata'
        }));
        const hours = istTime.getHours();
        const minutes = istTime.getMinutes();
        const seconds = istTime.getSeconds();
        return {
            day: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][istTime.getDay()],
            time: `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`,
            displayTime: formatTime12Hour(hours, minutes, seconds),
            hours: hours,
            minutes: minutes,
            seconds: seconds
        };
    }

    function TestgetCurrentISTTime() {
        // TEST MODE: Monday, Feb 6, 2026 at 10:40 AM
        const testDate = new Date('2026-02-06T10:40:00');
        const hours = testDate.getHours();
        const minutes = testDate.getMinutes();
        const seconds = testDate.getSeconds();

        return {
            day: "Friday",
            time: "10:40:00",
            displayTime: formatTime12Hour(hours, minutes, seconds),
            hours: hours,
            minutes: minutes,
            seconds: seconds
        };
    }

    function formatTime12Hour(hours, minutes, seconds) {
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHour = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
        return `${displayHour}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} ${ampm}`;
    }
    /**
     * Look across ALL timing groups (B.Tech, M.Tech, etc.) for the current
     * day/time and return whichever group has a class right now. This
     * replaces the old single-group-only lookup so the live status card
     * works correctly no matter which program is currently in session.
     */
    function findCurrentClass() {
        const currentIST = getCurrentISTTime();
        const currentDay = currentIST.day;
        const currentTime = currentIST.time;

        const groups = (typeof timetableDisplayGroups !== 'undefined') ? timetableDisplayGroups : [];

        for (const group of groups) {
            const daySchedule = group.processedSchedule && group.processedSchedule[currentDay] ?
                group.processedSchedule[currentDay].merged : null;
            if (!daySchedule) continue;

            for (let slotIdx in daySchedule) {
                const entry = daySchedule[slotIdx];
                if (currentTime >= entry.starttime && currentTime < entry.endtime) {
                    return {
                        status: 'in_class',
                        data: entry,
                        day: currentDay
                    };
                }
            }
        }

        let nextClass = null;
        for (const group of groups) {
            const daySchedule = group.processedSchedule && group.processedSchedule[currentDay] ?
                group.processedSchedule[currentDay].merged : null;
            if (!daySchedule) continue;

            for (let slotIdx in daySchedule) {
                const entry = daySchedule[slotIdx];
                if (entry.starttime > currentTime) {
                    if (!nextClass || entry.starttime < nextClass.starttime) {
                        nextClass = entry;
                    }
                }
            }
        }

        if (groups.length === 0) {
            return {
                status: 'no_schedule',
                day: currentDay
            };
        }

        return {
            status: 'free',
            nextClass: nextClass,
            day: currentDay
        };
    }

    function formatTime(time) {
        if (!time) return 'TBA';
        const parts = time.split(':');
        const hour = parseInt(parts[0]);
        const minute = parts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
        return `${displayHour}:${minute} ${ampm}`;
    }

    function updateFacultyLocationDisplay() {
        const locationDiv = document.getElementById('facultyCurrentLocation');
        if (!locationDiv) return;
        const result = findCurrentClass();
        const currentIST = getCurrentISTTime();
        if (result.status === 'in_class') {
            const data = result.data;
            locationDiv.innerHTML = `
            <div class="status-in-class">
                <span class="status-icon">🟢</span>
                <div class="status-content">
                    <div class="status-title">Currently Teaching</div>
                    <div class="location-details">
                        <div><strong>${data.subcode}</strong> - ${data.subfullname || data.subcode}</div>
                        <div>👥 <strong>Class:</strong> ${data.classname}</div>
                        <div>📍 <strong>Location:</strong> ${data.buildingname || 'TBA'} - ${data.classhallname || 'TBA'}</div>
                        <div>⏰ <strong>Time:</strong> ${formatTime(data.starttime)} - ${formatTime(data.endtime)}</div>
                        ${data.colspan > 1 ? `<div>⏱️ <strong>Duration:</strong> ${data.colspan} consecutive hours</div>` : ''}
                    </div>
                </div>
            </div>
        `;
        } else if (result.status === 'free') {
            const nextClassHTML = result.nextClass ? `
            <div class="next-class-info">
                <strong>📅 Next Class:</strong> ${result.nextClass.subcode} at 
                <span class="time-badge">${formatTime(result.nextClass.starttime)}</span> in 
                ${result.nextClass.classhallname || 'TBA'}
            </div>
        ` : '<div class="text-muted mt-2" style="font-style: italic;">No more classes scheduled today</div>';
            locationDiv.innerHTML = `
            <div class="status-free">
                <span class="status-icon">🟡</span>
                <div class="status-content">
                    <div class="status-title">Free Period</div>
                    <div style="color: #6b7280; margin-top: 4px;">Currently not teaching</div>
                    ${nextClassHTML}
                </div>
            </div>
        `;
        } else {
            locationDiv.innerHTML = `
            <div class="status-no-period">
                <span class="status-icon">⚫</span>
                <div class="status-content">
                    <div class="status-title">No Classes Today</div>
                    <div style="color: #6b7280; margin-top: 4px;">No classes scheduled for ${result.day}</div>
                </div>
            </div>
        `;
        }
        updateLastUpdatedTime(currentIST.displayTime);
    }

    function scheduleSmartRefresh() {
        setTimeout(function() {
            updateFacultyLocationDisplay();
            scheduleSmartRefresh();
        }, 30000);
    }

    function updateLastUpdatedTime(displayTime) {
        const badge = document.getElementById('lastUpdated');
        if (badge) {
            badge.textContent = displayTime || 'Just now';
        }
    }

    /**
     * Toggle ALL grid/list view pairs together (one pair per timing group).
     * IDs are suffixed per group (gridView-0, listView-0, gridView-1, ...)
     * so we use querySelectorAll instead of getElementById to ensure every
     * group's view is switched consistently, not just the first one.
     */
    function switchView(view) {
        const gridViews = document.querySelectorAll('[id^="gridView-"]');
        const listViews = document.querySelectorAll('[id^="listView-"]');
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');

        if (gridViews.length === 0 || listViews.length === 0 || !gridBtn || !listBtn) return;

        if (view === 'grid') {
            gridViews.forEach(el => el.style.display = 'block');
            listViews.forEach(el => el.style.display = 'none');
            gridBtn.classList.remove('btn-outline-primary');
            gridBtn.classList.add('btn-primary');
            listBtn.classList.remove('btn-primary');
            listBtn.classList.add('btn-outline-primary');
            localStorage.setItem('timetableView', 'grid');
        } else {
            gridViews.forEach(el => el.style.display = 'none');
            listViews.forEach(el => el.style.display = 'block');
            gridBtn.classList.remove('btn-primary');
            gridBtn.classList.add('btn-outline-primary');
            listBtn.classList.remove('btn-outline-primary');
            listBtn.classList.add('btn-primary');
            localStorage.setItem('timetableView', 'list');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth >= 768) {
            const savedView = localStorage.getItem('timetableView') || 'grid';
            switchView(savedView);
        }

        <?php if ($userRole === 'admin' || $userRole === 'academic_section'): ?>
            const deptSelect = document.getElementById('deptid');
            if (deptSelect) {
                deptSelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        <?php endif; ?>

        <?php if ($selectedFacultyId > 0 && !empty($weeklyTimetable['schedule'])): ?>
            if (typeof timetableDisplayGroups !== 'undefined') {
                updateFacultyLocationDisplay();
                scheduleSmartRefresh();
            }
        <?php endif; ?>
    });
</script>

<?php
if ($userRole === 'admin') {
    require_once('adminfooter.php');
} elseif ($userRole === 'hod') {
    require_once('hodfooter.php');
} else {
    require_once('facfooter.php');
}

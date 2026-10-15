<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "My Timetable";
require_once("stheader.php");
require_once("services/StudentRepository.php");
require_once("timetable.class.php");

$studentRepo = new StudentRepository();
$timetableObj = new Timetable();

// Get student's class_id
$classes = $studentRepo->getEnrolledClassesByUsername($_SESSION['user']);
$currentClass = null;
foreach ($classes as $class) {
    $endDate = new DateTime($class['end_date']);
    $endDate->setTime(23, 59, 59);
    if ($endDate >= new DateTime()) {
        $currentClass = $class;
        break;
    }
}

if (!$currentClass) {
    echo '<div class="container"><div class="alert alert-warning">No active class found.</div></div>';
    require_once("stfooter.php");
    exit();
}

$class_id = $currentClass['class_id'];
$student_id = $currentClass['student_id'];
$timetableData = $timetableObj->getTimetableByClass($class_id, $student_id);
$timeSlotsData = $timetableObj->getAvailableTimeSlots($class_id);
$weekdays = $timetableObj->getWeekdays();

$timeSlots = !empty($timeSlotsData['status']) && $timeSlotsData['status'] == 1 ? $timeSlotsData['data'] : [];
$timetableEntries = !empty($timetableData['status']) && $timetableData['status'] == 1 ? $timetableData['data'] : [];

// Filter extra hours
$usedHourIds = [];
foreach ($timetableEntries as $entry) {
    if (!in_array($entry['Hour'], $usedHourIds)) {
        $usedHourIds[] = $entry['Hour'];
    }
}

$filteredTimeSlots = [];
foreach ($timeSlots as $slot) {
    $isExtra = (strpos($slot['hour'], 'E.Hr') !== false || strpos($slot['hour_desc'], 'Extra') !== false);
    if ($isExtra && !in_array($slot['id'], $usedHourIds)) {
        continue;
    }
    $filteredTimeSlots[] = $slot;
}
$timeSlots = $filteredTimeSlots;

// Organize grid data
$gridData = [];
foreach ($timetableEntries as $entry) {
    $day = $entry['weekday'];
    $hourId = $entry['Hour'];
    if (!isset($gridData[$day][$hourId])) {
        $gridData[$day][$hourId] = [];
    }
    $gridData[$day][$hourId][] = $entry;
}

// Calculate unique subjects from timetable entries
$uniqueSubjects = [];
foreach ($timetableEntries as $entry) {
    if (!empty($entry['subject_id'])) {
        $subcode = $entry['subject_code'];
        if (!isset($uniqueSubjects[$subcode])) {
            $uniqueSubjects[$subcode] = [
                'subcode' => $subcode,
                'subfullname' => $entry['subfullname'] ?? $subcode,
                'subtype' => $entry['subtype'] ?? 'Theory',
                'subject_sno' => $entry['subject_sno'] ?? 999
            ];
        }
    }
}

// Sort subjects by subject_sno
usort($uniqueSubjects, function($a, $b) {
    return $a['subject_sno'] <=> $b['subject_sno'];
});
$processedGridData = [];
$skipCells = [];

foreach ($weekdays as $day) {
    $dayData = $gridData[$day] ?? [];
    $processedGridData[$day] = [];
    
    foreach ($timeSlots as $slotIndex => $slot) {
        $slotId = $slot['id'];
        $cellKey = $day . '_' . $slotId;
        
        if (in_array($cellKey, $skipCells)) {
            continue;
        }
        
        $entries = $dayData[$slotId] ?? [];
        
        if (!empty($entries)) {
            $firstEntry = $entries[0];
            if (!empty($firstEntry['subject_code'])) {
                $consecutive = 1;
                $lastEndTime = $firstEntry['end_time'];
                
                for ($j = $slotIndex + 1; $j < count($timeSlots); $j++) {
                    $nextSlot = $timeSlots[$j];
                    $nextEntries = $dayData[$nextSlot['id']] ?? [];
                    
                    if (!empty($nextEntries) && 
                        !empty($nextEntries[0]['subject_code']) &&
                        $firstEntry['subject_code'] === $nextEntries[0]['subject_code'] &&
                        (!empty($firstEntry['subject_id']) ? $firstEntry['subject_id'] === $nextEntries[0]['subject_id'] : true)) {
                        
                        $consecutive++;
                        $lastEndTime = $nextEntries[0]['end_time'];
                        $skipCells[] = $day . '_' . $nextSlot['id'];
                    } else {
                        break;
                    }
                }
                
                $firstEntry['colspan'] = $consecutive;
                $firstEntry['merged_end_time'] = $lastEndTime;
                $firstEntry['all_entries'] = $entries;
                $processedGridData[$day][$slotId] = $firstEntry;
            }
        }
    }
}
?>

<style>
    .timetable-grid {
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }
    .timetable-grid table {
        margin-bottom: 0;
        table-layout: fixed;
        width: 100%;
    }
    .timetable-grid th {
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
        padding: 16px 10px;
        border: 1px solid #e2e8f0;
    }
    .timetable-grid .day-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-size: 0.95rem;
        position: sticky;
        left: 0;
        z-index: 20;
        min-width: 100px;
    }
    .timetable-grid .time-header {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        color: white;
        font-size: 0.85rem;
        min-width: 140px;
    }
    .timetable-grid td {
        padding: 8px;
        vertical-align: middle;
        border: 1px solid #e2e8f0;
        min-height: 100px;
        background: #fafafa;
    }
    .timetable-grid tbody td:first-child {
        position: sticky;
        left: 0;
        z-index: 10;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        font-weight: 600;
        color: #2d3748;
    }
    .period-card {
        background: white;
        border-radius: 8px;
        padding: 12px;
        min-height: 90px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .period-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }
    .period-card.theory {
        border-left: 4px solid #4299e1;
        background: linear-gradient(135deg, #ebf8ff 0%, #ffffff 100%);
    }
    .period-card.lab {
        border-left: 4px solid #48bb78;
        background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
    }
    .period-card.free {
        background: #f7fafc;
        border: 2px dashed #cbd5e0;
        color: #a0aec0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 90px;
    }
    .period-card.activity {
        border-left: 4px solid #6c757d;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    }
    .period-code {
        font-weight: 700;
        font-size: 1rem;
        color: #2d3748;
        margin-bottom: 4px;
        display: block;
    }
    .period-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .period-type.theory {
        background: #4299e1;
        color: white;
    }
    .period-type.lab {
        background: #48bb78;
        color: white;
    }
    .period-location {
        font-size: 0.75rem;
        color: #718096;
        margin-top: 4px;
    }
    @media (max-width: 768px) {
        .timetable-grid {
            overflow-x: auto;
        }
        .period-card {
            min-height: 80px;
            padding: 8px;
        }
        .period-code {
            font-size: 0.85rem;
        }
    }
</style>

<div class="container">
    <div class="card mb-3">
        <div class="card-header">
            <strong>My Timetable</strong> - <?= htmlspecialchars($currentClass['classname']) ?>
        </div>
        <div class="card-body timetable-grid p-0">
            <div style="overflow-x: auto;">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th class="day-header">Day / Time</th>
                            <?php foreach ($timeSlots as $slot): ?>
                                <th class="time-header">
                                    <div><?= htmlspecialchars($slot['hour_desc']) ?></div>
                                    <small style="font-weight: 400; opacity: 0.9;">
                                        <?= $timetableObj->formatTime12Hour($slot['start_time']) ?>
                                    </small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekdays as $day): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($day) ?></strong></td>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <?php
                                    $cellKey = $day . '_' . $slot['id'];
                                    if (in_array($cellKey, $skipCells)) {
                                        continue;
                                    }
                                    
                                    $entry = $processedGridData[$day][$slot['id']] ?? null;
                                    $colspan = $entry['colspan'] ?? 1;
                                    ?>
                                    <td<?= $colspan > 1 ? ' colspan="' . $colspan . '"' : '' ?>>
                                        <?php if ($entry && !empty($entry['subject_code'])): ?>
                                            <?php
                                            $subtype = strtolower($entry['subtype'] ?? 'theory');
                                            $cardClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : 'theory';
                                            if (empty($entry['subject_id'])) {
                                                $cardClass = 'activity';
                                            }
                                            ?>
                                            <div class="period-card <?= $cardClass ?>">
                                                <?php if ($colspan > 1): ?>
                                                    <span class="badge bg-warning text-dark" style="position: absolute; top: 8px; right: 8px; font-size: 0.7rem;"><?= $colspan ?> hrs</span>
                                                <?php endif; ?>
                                                <span class="period-code"><?= empty($entry['subject_id']) ? '📌 ' : '' ?><?= htmlspecialchars($entry['subject_code']) ?></span>
                                                <?php if (!empty($entry['subtype'])): ?>
                                                    <span class="period-type <?= $cardClass ?>"><?= htmlspecialchars($entry['subtype']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($entry['subfullname'])): ?>
                                                    <div style="font-size: 0.75rem; color: #4a5568; margin: 4px 0; font-weight: 500;">
                                                        <?= htmlspecialchars($entry['subfullname']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($colspan > 1): ?>
                                                    <small style="display: block; color: #718096; font-size: 0.7rem; margin-top: 4px;">
                                                        <?= $timetableObj->formatTime12Hour($entry['start_time']) ?> - <?= $timetableObj->formatTime12Hour($entry['merged_end_time']) ?>
                                                    </small>
                                                <?php endif; ?>
                                                <div class="period-location">
                                                    <i class="bi bi-building"></i> <?= htmlspecialchars($entry['building_name']) ?><br>
                                                    <i class="bi bi-door-open"></i> <?= htmlspecialchars($entry['class_hall_name']) ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="period-card free">
                                                <span>-</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Enrolled Subjects Summary -->
    <?php if (!empty($uniqueSubjects)): ?>
    <div class="card mt-3">
        <div class="card-header">
            <strong><i class="bi bi-book"></i> My Enrolled Subjects (<?= count($uniqueSubjects) ?>)</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uniqueSubjects as $subject): ?>
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
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once("stfooter.php");
?>

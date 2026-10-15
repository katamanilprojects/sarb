<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Manage Timetable";
require_once("hod.class.php");
require_once("timetable.class.php");
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id']) && empty($_GET['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$hodObj = new HOD();
$timetableObj = new Timetable();

$class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : intval($_GET['class_id']);
$class_fullname = !empty($_POST['class_fullname']) ? $_POST['class_fullname'] : (!empty($_GET['class_fullname']) ? $_GET['class_fullname'] : '');
$spec_fullname = !empty($_POST['spec_fullname']) ? $_POST['spec_fullname'] : (!empty($_GET['spec_fullname']) ? $_GET['spec_fullname'] : '');

// Get current view (default: subject)
$current_view = isset($_GET['view']) ? $_GET['view'] : 'subject';

$msg = '';
$msg_type = 'info';

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    // Add Timetable Entry
    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "addentry") {
        $weekday = trim($_POST['weekday']);
        $hour = intval($_POST['hour']); // This is the class_timings.id value
        $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
        $activity_name = !empty($_POST['activity_name']) ? trim($_POST['activity_name']) : null;
        $building_name = trim($_POST['building_name']);
        $class_hall_name = trim($_POST['class_hall_name']);

        if (empty($weekday) || empty($hour) || empty($building_name) || empty($class_hall_name)) {
            $msg = "Weekday, Time Slot, Building Name and Room/Hall Name are required!";
            $msg_type = 'danger';
        } elseif (empty($subject_id) && empty($activity_name)) {
            $msg = "Either select a Subject or enter an Activity Name (e.g., Library, Sports, Break)!";
            $msg_type = 'danger';
        } else {
            // Validate for conflicts
            /*$timeConflict = $timetableObj->validateSubjectTimeConflict($class_id, $weekday, $hour);
            if ($timeConflict['has_conflict']) {
                $msg = "Time slot conflict! This class already has a subject scheduled at this time.";
                $msg_type = 'danger';
            } else {
                $roomConflict = $timetableObj->validateRoomConflict($class_id, $weekday, $hour, $building_name, $class_hall_name);
                if ($roomConflict['has_conflict']) {
                    $conflictInfo = $roomConflict['data'][0];
                    $msg = "Room conflict! {$building_name} - {$class_hall_name} is already booked for {$conflictInfo['classname']} at this time.";
                    $msg_type = 'danger';
                } else {
                    $result = $timetableObj->addTimetableEntry($class_id, $weekday, $hour, $subject_id, $building_name, $class_hall_name, $activity_name);
                    if ($result['status'] === 1) {
                        $msg = $result['message'];
                        $msg_type = 'success';
                    } else {
                        $msg = $result['message'] ?? "Failed to add timetable entry";
                        $msg_type = 'danger';
                    }
                }
            }
                */
                    $result = $timetableObj->addTimetableEntry($class_id, $weekday, $hour, $subject_id, $building_name, $class_hall_name, $activity_name);
                    if ($result['status'] === 1) {
                        $msg = $result['message'];
                        $msg_type = 'success';
                    } else {
                        $msg = $result['message'] ?? "Failed to add timetable entry";
                        $msg_type = 'danger';
                    }
        }
    }

    // Delete Timetable Entry
    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "deleteentry") {
        $entry_id = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        if ($entry_id > 0) {
            $result = $timetableObj->deleteTimetableEntryById($entry_id);
        } else {
            $weekday = trim($_POST['weekday']);
            $hour = intval($_POST['hour']);
            $result = $timetableObj->deleteTimetableEntry($class_id, $weekday, $hour);
        }

        if ($result['status'] === 1) {
            $msg = $result['message'];
            $msg_type = 'success';
        } else {
            $msg = $result['message'] ?? "Failed to delete timetable entry";
            $msg_type = 'danger';
        }
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Fetch data for the selected class
$subjectList = $hodObj->getSubjectsByClassID($class_id);
$classInfoResult = $timetableObj->getClassInfo($class_id);
if ($classInfoResult['status'] === 1 && !empty($classInfoResult['data'])) {
    $class_info = $classInfoResult['data'];
    if (empty($class_fullname)) {
        $class_fullname = $class_info['classname'];
    }
}

// Fetch buildings and halls from database
$buildingsWithHalls = $hodObj->getBuildingsWithHalls();
$buildingHallsJson = json_encode($buildingsWithHalls['data'] ?? []);

$timetableData = $timetableObj->getTimetableByClass($class_id);
$timeSlotsData = $timetableObj->getAvailableTimeSlots($class_id);
$weekdays = $timetableObj->getWeekdays();

$subjects = !empty($subjectList['status']) && $subjectList['status'] == 1 ? $subjectList['data'] : [];
$timeSlots = !empty($timeSlotsData['status']) && $timeSlotsData['status'] == 1 ? $timeSlotsData['data'] : [];
$timetableEntries = !empty($timetableData['status']) && $timetableData['status'] == 1 ? $timetableData['data'] : [];

// Filter extra hours - only show if actually used by class
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

// Organize data for grid view with consecutive hour merging
$gridData = [];
foreach ($timetableEntries as $entry) {
    $day = $entry['weekday'];
    $hourId = $entry['Hour'];
    if (!isset($gridData[$day][$hourId])) {
        $gridData[$day][$hourId] = [];
    }
    $gridData[$day][$hourId][] = $entry;
}

// Process grid data for consecutive hour merging
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

// Organize data for subject view
$subjectView = [];
$activityView = [];
foreach ($timetableEntries as $entry) {
    if (!empty($entry['subject_id'])) {
        $subKey = $entry['subject_id'];
        if (!isset($subjectView[$subKey])) {
            $subjectView[$subKey] = [
                'subject_code' => $entry['subject_code'],
                'subject_name' => $entry['subfullname'],
                'subject_type' => $entry['subtype'],
                'subject_sno' => $entry['subject_sno'] ?? 999,
                'schedules' => []
            ];
        }
        $subjectView[$subKey]['schedules'][] = [
            'id' => $entry['id'] ?? 0,
            'weekday' => $entry['weekday'],
            'hour' => $entry['Hour'],
            'hour_desc' => $entry['hour_desc'],
            'start_time' => $entry['start_time'],
            'end_time' => $entry['end_time'],
            'building' => $entry['building_name'],
            'room' => $entry['class_hall_name']
        ];
    } elseif (!empty($entry['subject_code'])) {
        $actKey = $entry['subject_code'];
        if (!isset($activityView[$actKey])) {
            $activityView[$actKey] = [
                'activity_name' => $entry['subject_code'],
                'schedules' => []
            ];
        }
        $activityView[$actKey]['schedules'][] = [
            'id' => $entry['id'] ?? 0,
            'weekday' => $entry['weekday'],
            'hour' => $entry['Hour'],
            'hour_desc' => $entry['hour_desc'],
            'start_time' => $entry['start_time'],
            'end_time' => $entry['end_time'],
            'building' => $entry['building_name'],
            'room' => $entry['class_hall_name']
        ];
    }
}

// Sort subjects by subject_sno
usort($subjectView, function ($a, $b) {
    return $a['subject_sno'] <=> $b['subject_sno'];
});
?>

<style>
    .view-tabs {
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 20px;
    }

    .view-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        font-weight: 500;
        padding: 10px 20px;
    }

    .view-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: none;
    }

    .view-tabs .nav-link:hover {
        border-bottom-color: #0d6efd;
    }

    .add-form-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .stats-card {
        border-left: 4px solid #28a745;
    }

    /* Modern Grid View Styling */
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

    .period-location i {
        margin-right: 4px;
    }

    /* Subject View Modern Styling */
    .subject-card {
        border: none;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .subject-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }

    .subject-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 20px;
        border: none;
    }

    .subject-card .card-body {
        padding: 0;
    }

    .schedule-row {
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }

    .schedule-row:last-child {
        border-bottom: none;
    }

    .schedule-row:hover {
        background: #f7fafc;
    }

    .schedule-day {
        font-weight: 600;
        color: #4a5568;
        min-width: 100px;
    }

    .schedule-time {
        color: #2d3748;
        font-weight: 500;
        min-width: 180px;
    }

    .schedule-location {
        color: #718096;
        font-size: 0.9rem;
    }

    .delete-btn-inline {
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .schedule-row:hover .delete-btn-inline {
        opacity: 1;
    }

    /* Stats Cards */
    .stats-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }

    /* View Tabs */
    .view-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 24px;
    }

    .view-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #718096;
        font-weight: 600;
        padding: 12px 24px;
        transition: all 0.2s ease;
    }

    .view-tabs .nav-link.active {
        color: #667eea;
        border-bottom-color: #667eea;
        background: none;
    }

    .view-tabs .nav-link:hover {
        color: #667eea;
        border-bottom-color: #667eea;
    }

    /* Form Section */
    .add-form-section {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 28px;
        border: 1px solid #e2e8f0;
    }

    /* Responsive */
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
    <!-- Header Card -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <?php if (!empty($spec_fullname)): ?>
                        <strong><?= htmlspecialchars($spec_fullname) ?></strong>
                    <?php endif; ?>
                    <br><small class="text-muted">Class: <?= htmlspecialchars($class_fullname) ?></small>
                </div>
                <div class="col-md-6 text-end">
                    <a href="hodviewclasses.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left-circle-fill"></i> All Classes
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">

            <!-- Alert Messages -->
            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h6 class="text-muted">Total Entries</h6>
                            <h3><?= count($timetableEntries) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card" style="border-left: 4px solid #007bff;">
                        <div class="card-body">
                            <h6 class="text-muted">Subjects Scheduled</h6>
                            <h3><?= count($subjectView) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h6 class="text-muted">Available Subjects</h6>
                            <h3><?= count($subjects) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <h6 class="text-muted">Time Slots</h6>
                            <h3><?= count($timeSlots) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Entry Form -->
            <div class="add-form-section">
                <h5 class="mb-3"><i class="bi bi-plus-circle-fill"></i> Add Timetable Entry</h5>
                <form action="hodmanage_timetable.php" method="post">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><strong>Weekday *</strong></label>
                            <select name="weekday" class="form-select" required>
                                <option value="">Select Day</option>
                                <?php foreach ($weekdays as $day): ?>
                                    <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Time Slot *</strong></label>
                            <select name="hour" class="form-select" required>
                                <option value="">Select Time</option>
                                <?php if (!empty($timeSlots)): ?>
                                    <?php foreach ($timeSlots as $slot): ?>
                                        <option value="<?= $slot['id'] ?>">
                                            <?= htmlspecialchars($slot['hour_desc']) ?>
                                            (<?= $timetableObj->formatTime12Hour($slot['start_time']) ?> -
                                            <?= $timetableObj->formatTime12Hour($slot['end_time']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Subject</strong></label>
                            <select name="subject_id" class="form-select" id="subject_select">
                                <option value="">-- Select Subject --</option>
                                <?php if (!empty($subjects)): ?>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>">
                                            <?= htmlspecialchars($subject['subcode'] ?? '') ?> -
                                            <?= htmlspecialchars($subject['sub_fullname'] ?? '') ?>
                                            <?php if (!empty($subject['sub_type'])): ?>
                                                (<?= htmlspecialchars($subject['sub_type']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">OR enter activity below</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>Activity Name</strong></label>
                            <input type="text" name="activity_name" class="form-control" id="activity_input"
                                placeholder="e.g., Library, Sports, Games" list="activity-list">
                            <datalist id="activity-list">
                                <option value="Library">
                                <option value="Sports">
                                <option value="Games">
                                <option value="Break">
                                <option value="Lunch">
                                <option value="Assembly">
                                <option value="Mentoring">
                            </datalist>
                            <small class="text-muted">For non-subject activities</small>
                        </div>
                        <!-- Building Name Select Box -->
                        <div class="col-md-3">
                            <label class="form-label"><strong>Building Name</strong> <span class="text-danger">*</span></label>
                            <select name="building_name" class="form-select" id="building-select" required>
                                <option value="">-- Select Building --</option>
                                <?php 
                                if (!empty($buildingsWithHalls['data'])) {
                                    foreach ($buildingsWithHalls['data'] as $building_name => $halls) {
                                        echo '<option value="' . htmlspecialchars($building_name) . '">' . htmlspecialchars($building_name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Room/Hall Name Select Box (Dependent) -->
                        <div class="col-md-3">
                            <label class="form-label"><strong>Room/Hall Name</strong> <span class="text-danger">*</span></label>
                            <select name="class_hall_name" class="form-select" id="room-select" required disabled>
                                <option value="">-- First Select Building --</option>
                            </select>
                            <small class="text-muted">Select building first</small>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const buildingSelect = document.getElementById('building-select');
                                const roomSelect = document.getElementById('room-select');

                                // Building-Room mapping from database
                                const buildingRooms = <?= $buildingHallsJson ?>;

                                // Handle building selection change
                                buildingSelect.addEventListener('change', function() {
                                    const selectedBuilding = this.value;

                                    // Clear existing room options
                                    roomSelect.innerHTML = '<option value="">-- Select Room/Hall --</option>';

                                    if (selectedBuilding && buildingRooms[selectedBuilding]) {
                                        // Enable the room select dropdown
                                        roomSelect.disabled = false;

                                        // Populate rooms for the selected building
                                        buildingRooms[selectedBuilding].forEach(function(room) {
                                            const option = document.createElement('option');
                                            option.value = room;
                                            option.textContent = room;
                                            roomSelect.appendChild(option);
                                        });
                                    } else {
                                        // Disable room select if no building selected
                                        roomSelect.disabled = true;
                                        roomSelect.innerHTML = '<option value="">-- First Select Building --</option>';
                                    }
                                });
                            });
                        </script>

                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3 d-flex align-items-end">
                            <input type="hidden" name="whattodo" value="addentry">
                            <input type="hidden" name="class_id" value="<?= $class_id ?>">
                            <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>">
                            <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>">
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Add Entry
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- View Tabs -->
            <ul class="nav nav-tabs view-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $current_view == 'subject' ? 'active' : '' ?>"
                        href="?class_id=<?= $class_id ?>&view=subject&class_fullname=<?= urlencode($class_fullname) ?>&spec_fullname=<?= urlencode($spec_fullname) ?>">
                        <i class="bi bi-book-fill"></i> Subject View
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_view == 'grid' ? 'active' : '' ?>"
                        href="?class_id=<?= $class_id ?>&view=grid&class_fullname=<?= urlencode($class_fullname) ?>&spec_fullname=<?= urlencode($spec_fullname) ?>">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Grid View
                    </a>
                </li>
            </ul>

            <!-- View Content -->
            <?php if ($current_view == 'subject'): ?>
                <!-- SUBJECT VIEW -->
                <div class="card">
                    <div class="card-header">
                        <strong><i class="bi bi-book-fill"></i> Subject-wise Schedule</strong>
                        <span class="badge bg-light text-dark"><?= count($subjectView) ?> subjects</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($subjectView)): ?>
                            <?php foreach ($subjectView as $subKey => $subData): ?>
                                <div class="subject-card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <strong style="font-size: 1.1rem;"><?= htmlspecialchars($subData['subject_code']) ?></strong> -
                                                <?= htmlspecialchars($subData['subject_name']) ?>
                                                <?php if (!empty($subData['subject_type'])): ?>
                                                    <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($subData['subject_type']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <span class="badge bg-light text-dark"><?= count($subData['schedules']) ?> periods/week</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($subData['schedules'] as $schedule): ?>
                                            <div class="schedule-row">
                                                <div style="display: flex; gap: 20px; align-items: center; flex: 1;">
                                                    <div class="schedule-day">
                                                        <i class="bi bi-calendar-day"></i> <?= htmlspecialchars($schedule['weekday']) ?>
                                                    </div>
                                                    <div class="schedule-time">
                                                        <i class="bi bi-clock"></i> <?= htmlspecialchars($schedule['hour_desc']) ?>
                                                        <small class="text-muted d-block" style="margin: 5px;">
                                                            <?= $timetableObj->formatTime12Hour($schedule['start_time']) ?> -
                                                            <?= $timetableObj->formatTime12Hour($schedule['end_time']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="schedule-location">
                                                        <i class="bi bi-building"></i> <?= htmlspecialchars($schedule['building']) ?> -
                                                        <i class="bi bi-door-open"></i> <?= htmlspecialchars($schedule['room']) ?>
                                                    </div>
                                                </div>
                                                <div class="delete-btn-inline">
                                                    <form action="hodmanage_timetable.php?view=subject&class_id=<?= $class_id ?>&class_fullname=<?= urlencode($class_fullname) ?>&spec_fullname=<?= urlencode($spec_fullname) ?>" method="post" style="display:inline;"
                                                        onsubmit="return confirm('Delete this entry?');">
                                                        <input type="hidden" name="class_id" value="<?= $class_id ?>">
                                                        <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>">
                                                        <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>">
                                                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                                        <input type="hidden" name="weekday" value="<?= htmlspecialchars($schedule['weekday']) ?>">
                                                        <input type="hidden" name="hour" value="<?= $schedule['hour'] ?>">
                                                        <input type="hidden" name="entry_id" value="<?= $schedule['id'] ?? '' ?>">
                                                        <input type="hidden" name="whattodo" value="deleteentry">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Activities Section -->
                            <?php if (!empty($activityView)): ?>
                                <h5 class="mt-4 mb-3"><i class="bi bi-calendar-event"></i> Other Activities</h5>
                                <?php foreach ($activityView as $actKey => $actData): ?>
                                    <div class="subject-card" style="border-left: 4px solid #6c757d;">
                                        <div class="card-header" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <strong style="font-size: 1.1rem;">📌 <?= htmlspecialchars($actData['activity_name']) ?></strong>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <span class="badge bg-light text-dark"><?= count($actData['schedules']) ?> periods/week</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($actData['schedules'] as $schedule): ?>
                                                <div class="schedule-row">
                                                    <div style="display: flex; gap: 20px; align-items: center; flex: 1;">
                                                        <div class="schedule-day">
                                                            <i class="bi bi-calendar-day"></i> <?= htmlspecialchars($schedule['weekday']) ?>
                                                        </div>
                                                        <div class="schedule-time">
                                                            <i class="bi bi-clock"></i> <?= htmlspecialchars($schedule['hour_desc']) ?>
                                                            <small class="text-muted d-block" style="margin: 5px;">
                                                                <?= $timetableObj->formatTime12Hour($schedule['start_time']) ?> -
                                                                <?= $timetableObj->formatTime12Hour($schedule['end_time']) ?>
                                                            </small>
                                                        </div>
                                                        <div class="schedule-location">
                                                            <i class="bi bi-building"></i> <?= htmlspecialchars($schedule['building']) ?> -
                                                            <i class="bi bi-door-open"></i> <?= htmlspecialchars($schedule['room']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="delete-btn-inline">
                                                        <form action="hodmanage_timetable.php?view=subject&class_id=<?= $class_id ?>&class_fullname=<?= urlencode($class_fullname) ?>&spec_fullname=<?= urlencode($spec_fullname) ?>" method="post" style="display:inline;"
                                                            onsubmit="return confirm('Delete this entry?');">
                                                            <input type="hidden" name="class_id" value="<?= $class_id ?>">
                                                            <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>">
                                                            <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>">
                                                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                                                            <input type="hidden" name="weekday" value="<?= htmlspecialchars($schedule['weekday']) ?>">
                                                            <input type="hidden" name="hour" value="<?= $schedule['hour'] ?>">
                                                            <input type="hidden" name="entry_id" value="<?= $schedule['id'] ?? '' ?>">
                                                            <input type="hidden" name="whattodo" value="deleteentry">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No subjects scheduled yet. Use the form above to add timetable entries.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- GRID VIEW -->
                <div class="card">
                    <div class="card-header">
                        <strong><i class="bi bi-grid-3x3-gap-fill"></i> Weekly Timetable Grid</strong>
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
                                                $allEntries = $entry['all_entries'] ?? [];
                                                ?>
                                                <td<?= $colspan > 1 ? ' colspan="' . $colspan . '"' : '' ?>>
                                                    <?php if ($entry && !empty($entry['subject_code'])): ?>
                                                        <?php if (count($allEntries) > 1): ?>
                                                            <?php foreach ($allEntries as $idx => $subEntry): ?>
                                                                <?php
                                                                $subtype = strtolower($subEntry['subtype'] ?? 'theory');
                                                                $cardClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : 'theory';
                                                                if (empty($subEntry['subject_id'])) {
                                                                    $cardClass = 'activity';
                                                                }
                                                                ?>
                                                                <div class="period-card <?= $cardClass ?>" style="<?= $idx > 0 ? 'margin-top: 8px;' : '' ?>">
                                                                    <span class="period-code"><?= empty($subEntry['subject_id']) ? '📌 ' : '' ?><?= htmlspecialchars($subEntry['subject_code']) ?></span>
                                                                    <?php if (!empty($subEntry['subtype'])): ?>
                                                                        <span class="period-type <?= $cardClass ?>"><?= htmlspecialchars($subEntry['subtype']) ?></span>
                                                                    <?php endif; ?>
                                                                    <div class="period-location">
                                                                        <i class="bi bi-building"></i> <?= htmlspecialchars($subEntry['building_name']) ?><br>
                                                                        <i class="bi bi-door-open"></i> <?= htmlspecialchars($subEntry['class_hall_name']) ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
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
                                                        <?php endif; ?>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Subject Faculty Details Table -->
    <?php
    // Fetch subject-faculty mapping for this class
    $subjectFacultyData = $hodObj->getFacSubMapByClassID($class_id);
    if (!empty($subjectFacultyData['status']) && $subjectFacultyData['status'] == 1 && !empty($subjectFacultyData['data'])):
        // Group faculties by subject
        $groupedData = [];
        foreach ($subjectFacultyData['data'] as $row) {
            $key = $row['subcode'];
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'subject_sno' => $row['subject_sno'],
                    'subcode' => $row['subcode'],
                    'sub_fullname' => $row['sub_fullname'],
                    'faculties' => []
                ];
            }
            $groupedData[$key]['faculties'][] = $row['faculty_name'];
        }
        // Sort by subject_sno, then by subcode
        usort($groupedData, function($a, $b) {
            $snoCompare = $a['subject_sno'] <=> $b['subject_sno'];
            return $snoCompare !== 0 ? $snoCompare : strcmp($a['subcode'], $b['subcode']);
        });
    ?>
    <div class="card mt-4">
        <div class="card-header">
            <strong><i class="bi bi-people-fill"></i> Subject Faculty Details</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">S.No</th>
                        <th style="width: 120px;">Subject Code</th>
                        <th>Subject Name</th>
                        <th>Faculty Name(s)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prevSno = null;
                    $snoRowspan = [];
                    // Calculate rowspans for S.No
                    foreach ($groupedData as $idx => $item) {
                        if (!isset($snoRowspan[$item['subject_sno']])) {
                            $snoRowspan[$item['subject_sno']] = 0;
                        }
                        $snoRowspan[$item['subject_sno']]++;
                    }
                    
                    foreach ($groupedData as $idx => $item): 
                        $showSno = ($prevSno !== $item['subject_sno']);
                        $prevSno = $item['subject_sno'];
                    ?>
                        <tr>
                            <?php if ($showSno): ?>
                                <td class="text-center" rowspan="<?= $snoRowspan[$item['subject_sno']] ?>"><?= htmlspecialchars($item['subject_sno']) ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($item['subcode']) ?></td>
                            <td><?= htmlspecialchars($item['sub_fullname']) ?></td>
                            <td><?= htmlspecialchars(implode(', ', $item['faculties'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
require_once("hodfooter.php");
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
$page_title = "Home";
require_once("stheader.php");

require_once("services/StudentRepository.php");
require_once("timetable.class.php");
require_once("services/StudentAttendanceService.php");
$studentRepo = new StudentRepository();
$timetableObj = new Timetable();
$attendanceService = new StudentAttendanceService();

// Fetch classes and their corresponding subjects for the student
$classes = $studentRepo->getEnrolledClassesByUsername($_SESSION['user']);

// Get current active class for today's schedule
$currentClass = null;
foreach ($classes as $class) {
    $endDate = new DateTime($class['end_date']);
    $endDate->setTime(23, 59, 59);
    if ($endDate >= new DateTime()) {
        $currentClass = $class;
        break;
    }
}

// Fetch today's schedule
$todaySchedule = [];
if ($currentClass) {
    $class_id = $currentClass['class_id'];
    $student_id = $currentClass['student_id'];
    $today = date('l'); // e.g., 'Monday'
    $timetableData = $timetableObj->getTimetableByClass($class_id, $student_id);
    
    if (!empty($timetableData['status']) && $timetableData['status'] == 1) {
        $rawSchedule = [];
        foreach ($timetableData['data'] as $entry) {
            if ($entry['weekday'] === $today) {
                $rawSchedule[] = $entry;
            }
        }
        
        // Sort by start time
        usort($rawSchedule, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });
        
        // Merge consecutive same subjects
        $todaySchedule = [];
        $processed = [];
        
        for ($i = 0; $i < count($rawSchedule); $i++) {
            if (isset($processed[$i])) continue;
            
            $current = $rawSchedule[$i];
            $merged = $current;
            $hourCount = 1;
            $processed[$i] = true;
            
            // Only merge subjects (not activities)
            if (!empty($current['subject_id'])) {
                // Look for all consecutive matching subjects
                $lastEndTime = $current['end_time'];
                
                for ($j = $i + 1; $j < count($rawSchedule); $j++) {
                    if (isset($processed[$j])) continue;
                    
                    $next = $rawSchedule[$j];
                    
                    // Check if same subject and consecutive time
                    if ($current['subject_code'] === $next['subject_code'] && 
                        $current['subject_id'] === $next['subject_id'] &&
                        $lastEndTime === $next['start_time']) {
                        
                        $merged['end_time'] = $next['end_time'];
                        $lastEndTime = $next['end_time'];
                        $hourCount++;
                        $processed[$j] = true;
                    }
                }
            }
            
            $merged['hour_count'] = $hourCount;
            $todaySchedule[] = $merged;
        }
    }
}

?>

<style>
    .schedule-card {
        border-left: 4px solid #4299e1;
        transition: all 0.3s ease;
    }
    .schedule-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .schedule-card.lab {
        border-left-color: #48bb78;
    }
    .schedule-card.activity {
        border-left-color: #6c757d;
    }
    .time-badge {
        background: #f7fafc;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #2d3748;
        line-height: 1.2;
    }
</style>

<div class="container">
    <?php if (!empty($_SESSION['err'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['err']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['err']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['succ'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['succ']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['succ']); ?>
    <?php endif; ?>
    <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-person-badge me-2"></i>My Details</strong>
            <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['user']); ?></span>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-1"><strong>Username / Roll No:</strong> <?= htmlspecialchars($_SESSION['user']); ?></li>
                        <li class="list-group-item px-0 py-1"><strong>Full Name:</strong> <?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['user']); ?></li>
                    </ul>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <?php if (\FeatureManager::isStudentVisible('MOD_STUDENT_PROFILE')): ?>
                            <a href="studentprofile.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-person-lines-fill me-1"></i> My Profile & Docs
                            </a>
                        <?php endif; ?>
                        <?php if (\FeatureManager::isStudentVisible('MOD_RESULTS_PUBLISH')): ?>
                            <a href="studentresults.php" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exam Results
                            </a>
                        <?php endif; ?>
                        <?php if (\FeatureManager::isStudentVisible('MOD_CERTIFICATES')): ?>
                            <a href="studentcertificates.php" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-award me-1"></i> Certificates
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (\FeatureManager::isStudentVisible('MOD_STUDENT_ATTENDANCE_RISK') && $currentClass): 
        $risk = $attendanceService->getStudentAttendanceRiskMetrics((int)$currentClass['student_id'], (int)$currentClass['class_id']);
    ?>
        <!-- Attendance Risk Gauge (NBA Criterion 4.1 & NAAC Metric 2.2.1) -->
        <div class="card mb-3 border-<?= $risk['status_class']; ?> shadow-sm">
            <div class="card-header bg-<?= $risk['status_class']; ?> text-white d-flex justify-content-between align-items-center">
                <strong>
                    <i class="bi bi-speedometer2 me-2"></i> Attendance Risk Gauge & Recovery Analytics
                </strong>
                <span class="badge bg-light text-dark fw-bold">
                    Status: <?= htmlspecialchars($risk['category']); ?> (<?= $risk['percentage'] === null ? 'Not available' : $risk['percentage']; ?>%)
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center align-items-stretch">
                    <!-- Aggregate Attendance Card -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-center">
                            <small class="text-muted text-uppercase fw-bold">Aggregate Attendance</small>
                            <h2 class="display-6 fw-bold text-<?= $risk['status_class']; ?> my-1">
                                <?= $risk['percentage'] === null ? 'Not available' : $risk['percentage']; ?><small class="fs-5">%</small>
                            </h2>
                            <div class="small text-muted">
                                Attended <strong><?= $risk['total_attended']; ?></strong> of <strong><?= $risk['total_conducted']; ?></strong> conducted periods
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-<?= $risk['status_class']; ?>" role="progressbar" style="width: <?= min(100.0, (float)($risk['percentage'] ?? 0.0)); ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Safe Margin Card -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-center">
                            <small class="text-muted text-uppercase fw-bold">Safe Cushion Margin</small>
                            <h2 class="display-6 fw-bold text-success my-1">
                                <?= $risk['safe_margin'] === null ? 'Under review' : $risk['safe_margin']; ?>
                            </h2>
                            <div class="small text-muted">
                                Period(s) you can miss before falling below the 75% regulatory requirement
                            </div>
                        </div>
                    </div>

                    <!-- Recovery Periods Card -->
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-center">
                            <small class="text-muted text-uppercase fw-bold">Recovery Periods Required</small>
                            <h2 class="display-6 fw-bold <?= ($risk['recovery_needed'] > 0) ? 'text-danger' : 'text-secondary'; ?> my-1">
                                <?= $risk['recovery_needed'] === null ? 'Under review' : $risk['recovery_needed']; ?>
                            </h2>
                            <div class="small text-muted">
                                Consecutive period(s) you must attend to restore attendance to 75%
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-<?= $risk['status_class']; ?>-subtle border border-<?= $risk['status_class']; ?>-subtle text-<?= $risk['status_class']; ?>-emphasis mt-3 mb-0 small d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                    <div>
                        <strong>Regulatory Guidance:</strong> <?= htmlspecialchars($risk['advice']); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <br>

    <?php if (!empty($todaySchedule)): ?>
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-calendar-day"></i> <strong>Today's Schedule</strong> - <?= date('l, F j, Y') ?>
            <a href="studenttimetable.php" class="btn btn-sm btn-light float-end">View Full Timetable</a>
        </div>
        <div class="card-body p-2">
            <?php foreach ($todaySchedule as $entry): 
                $subtype = strtolower($entry['subtype'] ?? 'theory');
                $cardClass = ($subtype === 'lab' || $subtype === 'laboratory') ? 'lab' : '';
                if (empty($entry['subject_id'])) {
                    $cardClass = 'activity';
                }
            ?>
                <div class="card schedule-card <?= $cardClass ?> mb-2">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <span class="time-badge">
                                    <i class="bi bi-clock"></i> <?= $timetableObj->formatTime12Hour($entry['start_time']) ?>
                                    <?php if ($entry['hour_count'] > 1): ?>
                                        <br><small class="text-muted">to <?= $timetableObj->formatTime12Hour($entry['end_time']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="col-md-4">
                                <strong style="font-size: 1.1rem;"><?= htmlspecialchars($entry['subject_code']) ?></strong>
                                <?php if (!empty($entry['subtype'])): ?>
                                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($entry['subtype']) ?></span>
                                <?php endif; ?>
                                <?php if ($entry['hour_count'] > 1): ?>
                                    <span class="badge bg-info ms-2"><?= $entry['hour_count'] ?> hrs</span>
                                <?php endif; ?>
                                <br><small class="text-muted"><?= htmlspecialchars($entry['subfullname'] ?? '') ?></small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($entry['building_name']) ?><br>
                                    <i class="bi bi-door-open"></i> <?= htmlspecialchars($entry['class_hall_name']) ?>
                                </small>
                            </div>
                            <div class="col-md-3 text-end">
                                <small class="text-muted"><?= htmlspecialchars($entry['hour_desc']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            My Classes
        </div>
        <div class="card-body">
            <?php if (!empty($classes)): ?>
                <div class="row">
                    <?php foreach ($classes as $class): ?>
                        <?php
                        $currentDate = new DateTime();
                        $endDate = new DateTime($class['end_date']);
                        $endDate->setTime(23, 59, 59);
                        $isClassEnded = $endDate < $currentDate;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light <?php echo $isClassEnded ? 'text-secondary' : 'text-primary'; ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($class['classname']); ?></h5>
                                    <p class="card-text">Academic Year: <?= htmlspecialchars($class['acad_year']); ?></p>
                                    <form action="studentsubjects.php" method="post">
                                        <input type="hidden" name="student_id" value="<?= $class['student_id']; ?>">
                                        <input type="hidden" name="class_id" value="<?= $class['class_id']; ?>">
                                        <input type="hidden" name="classname" value="<?= $class['classname']; ?>">
                                        <input type="hidden" name="acad_year" value="<?= $class['acad_year']; ?>">
                                        <button type="submit" class="btn <?php echo $isClassEnded ? 'btn-outline-secondary' : 'btn-outline-success'; ?>">View Subjects</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="col-md-12">
                    <div class="alert alert-warning text-center">
                        You are not enrolled in any classes.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <br>
    <div class="card">
        <div class="card-header">Things to Know</div>
        <div class="card-body">
            <ul>
                <li><a href="stknowledgebase.php">Click here to learn key strategies for successful outcome-based education.</a></li>
            </ul>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>
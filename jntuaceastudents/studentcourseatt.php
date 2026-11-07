<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once("logger.php");

// Extract POST inputs with safe fallbacks
$subcode   = htmlspecialchars($_POST['subcode'] ?? '23A30502', ENT_QUOTES, 'UTF-8');
$subname   = htmlspecialchars($_POST['sub_fullname'] ?? 'General Course', ENT_QUOTES, 'UTF-8');
$classname = htmlspecialchars($_POST['classname'] ?? 'B.Tech (CSE) - II Yr - I Sem', ENT_QUOTES, 'UTF-8');
$acadYear  = htmlspecialchars($_POST['acad_year'] ?? '2026-2027', ENT_QUOTES, 'UTF-8');
$studentId = htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES, 'UTF-8');
$classId   = htmlspecialchars($_POST['class_id'] ?? '', ENT_QUOTES, 'UTF-8');

// Deterministic mock attendance generator (August 17, 2026 to October 23, 2026)
// Zero database hits, executed in <0.1ms
$mockClasses = [];
$mockPeriods = [
    ['9:30 AM', '10:30 AM'],
    ['10:30 AM', '11:30 AM'],
    ['11:45 AM', '12:45 PM'],
    ['2:45 PM', '3:45 PM']
];

$seed = crc32($subcode . ($studentId ?: '9524'));
$startDate = new DateTime('2026-08-17');
$endDate   = new DateTime('2026-10-23');
$interval  = new DateInterval('P1D');
$period    = new DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

$pIdx = 0;
$totalConducted = 0;
$totalPresent = 0;
$totalAbsent = 0;

$diaryTopics = [
    "Introduction to unit concepts and core principles.",
    "Detailed analytical formulations and example walkthroughs.",
    "Problem solving and discussion on real-world applications.",
    "Review of key topics and student question-answer session."
];

foreach ($period as $dt) {
    $weekday = (int)$dt->format('N'); // 1 = Monday, 7 = Sunday
    if ($weekday === 1 || $weekday === 3 || $weekday === 5) { // Mon, Wed, Fri
        $p = $mockPeriods[$pIdx % count($mockPeriods)];
        $topic = $diaryTopics[$pIdx % count($diaryTopics)];
        $pIdx++;
        $totalConducted++;

        // Mostly Absent rate (~15-25% present, ~75-85% absent)
        $isPresent = (($dt->format('j') + $pIdx + ($seed % 7)) % 5) === 0;
        if ($isPresent) {
            $totalPresent++;
            $status = 'Present';
            $badge = 'bg-success';
        } else {
            $totalAbsent++;
            $status = 'Absent';
            $badge = 'bg-danger';
        }

        $mockClasses[] = [
            'date'   => $dt->format('d-m-Y'),
            'time'   => $p[0] . ' - ' . $p[1],
            'status' => $status,
            'badge'  => $badge,
            'topic'  => $topic
        ];
    }
}

$percentage = ($totalConducted > 0) ? round(($totalPresent / $totalConducted) * 100, 1) : 0;
$pctClass = match(true) {
    $percentage >= 75.0 => 'text-success',
    $percentage >= 65.0 => 'text-warning',
    default => 'text-danger'
};
$statusLabel = match(true) {
    $percentage >= 75.0 => 'Eligible (≥ 75%)',
    $percentage >= 65.0 => 'Condonable (65-75%)',
    default => 'Shortage (< 65%)'
};

$page_title = "Student Dashboard";
if (!empty($_SESSION["user"]) && !empty($_SESSION["role"]) && $_SESSION['role'] === "student") {
    require_once("stheader.php");
} else {
    require_once("header.php");
    require_once("studentmenu.php");
}
?>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header">
            <div class="float-start">
                <h6 class="mb-0"><?= $classname ?></h6>
                <small>Academic Year: <?= $acadYear ?></small>
            </div>
            <div class="float-end d-flex gap-2">
                <form action="studentsubjects.php" method="post" class="d-inline mb-0">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <input type="hidden" name="classname" value="<?= $classname ?>">
                    <input type="hidden" name="acad_year" value="<?= $acadYear ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Subjects
                    </button>
                </form>
                <a href="studenthome.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-house me-1"></i>Home
                </a>
            </div>
        </div>
        <div class="card-header">
            <?= $subcode ?> <br> <?= $subname ?>
        </div>
        <div class="card-body">
            <!-- Attendance Summary Cards -->
            <div class="row g-3 mb-4 text-center">
                <div class="col-6 col-md-3">
                    <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                        <small class="text-muted text-uppercase fw-bold">Conducted</small>
                        <h3 class="fw-bold text-dark my-1"><?= $totalConducted ?></h3>
                        <small class="text-muted">Total Classes</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                        <small class="text-muted text-uppercase fw-bold">Present / Attended</small>
                        <h3 class="fw-bold text-success my-1"><?= $totalPresent ?></h3>
                        <small class="text-muted">Attended</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                        <small class="text-muted text-uppercase fw-bold">Absent</small>
                        <h3 class="fw-bold text-danger my-1"><?= $totalAbsent ?></h3>
                        <small class="text-muted">Missed</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                        <small class="text-muted text-uppercase fw-bold">Percentage</small>
                        <?php if ($totalConducted > 0): ?>
                            <h3 class="fw-bold <?= $pctClass ?> my-1"><?= number_format($percentage, 1) ?>%</h3>
                            <small class="<?= $pctClass ?> fw-semibold"><?= $statusLabel ?></small>
                        <?php else: ?>
                            <h3 class="fw-bold text-secondary my-1">—</h3>
                            <small class="text-muted">No classes held</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Table -->
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th colspan=3>Attendance</th>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <th>Hour</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mockClasses as $cls): ?>
                        <tr>
                            <td><?= htmlspecialchars($cls['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($cls['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge <?= $cls['badge'] ?>"><?= $cls['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr>

            <!-- Course Outcomes -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th colspan="2">Course Outcomes</th>
                    </tr>
                    <tr>
                        <th>CO No.</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>CO1</td><td>Understand fundamental principles and concepts of the course.</td></tr>
                    <tr><td>CO2</td><td>Apply analytical techniques to formulate and solve domain problems.</td></tr>
                    <tr><td>CO3</td><td>Design and evaluate solutions meeting required specifications.</td></tr>
                    <tr><td>CO4</td><td>Analyze performance and interpret experimental results effectively.</td></tr>
                </tbody>
            </table>
            <hr>

            <!-- Lecture Diary -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th colspan=3>Lecture Diary</th>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Diary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mockClasses as $cls): ?>
                        <tr>
                            <td><?= htmlspecialchars($cls['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($cls['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($cls['topic'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr>

            <!-- Continuous Internal Assessment -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th colspan="2">Continuous Internal Assessment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><th>Final CIA</th><td>Pending — No finalized assessment records.</td></tr>
                </tbody>
            </table>

            <!-- Semester End Examination (SEE) Results -->
            <br>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                    <span><i class="bi bi-award me-2"></i><strong>Semester End Examination (SEE) Results & Grade Card</strong></span>
                    <span class="badge bg-secondary">Awaiting Publication</span>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 text-center text-muted">
                        <i class="bi bi-info-circle me-1"></i> Official semester examination results for this subject have not been published yet.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>

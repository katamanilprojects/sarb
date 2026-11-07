<?php
require_once __DIR__ . '/bootstrap.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once("logger.php");
logRequest();

// Intercept unauthorized scraper bot with static dummy response from studentcourseatt.php
if (isScraperBot()) {
    logInvalidResponseServed('studentsubjectatt.php');
    require(__DIR__ . "/studentcourseatt.php");
    exit();
}


require_once("services/StudentRepository.php");
require_once("services/StudentAttendanceService.php");
require_once("services/StudentAssessmentService.php");
require_once("services/FeatureManager.php");

$studentRepo = new StudentRepository();
$attendanceService = new StudentAttendanceService();
$assessmentService = new StudentAssessmentService();

// Verify that the requested student_id belongs to the logged-in user
if (empty($_POST['student_id']) || empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership($_POST['student_id'], $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (empty($_POST["subcode"]) || empty($_POST["sub_fullname"]) || empty($_POST["subject_id"]) || empty($_POST["student_id"]) || empty($_POST['class_id']) || empty($_POST['classname']) || empty($_POST['acad_year'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (!$studentRepo->verifyClassEnrollment((int)$_POST['student_id'], (int)$_POST['class_id'], false) || !$studentRepo->verifySubjectEnrollment((int)$_POST['student_id'], (int)$_POST['subject_id'])) {
    header('Location: ./studenthome.php');
    exit();
}
$classid_validation = 1;
$studentClassStatus = $studentRepo->getStudentClassStatus((int)$_POST['student_id'], (int)$_POST['class_id']);
$isInactiveInClass = ($studentClassStatus === 0);

$page_title = "Student Dashboard";
require_once("stheader.php");

$studentList = array(0 => array("id" => $_POST["student_id"], "username" => $_SESSION["user"]));

// Fetch classes and their corresponding subjects for the student
$attendanceData = $attendanceService->getStudentAttendanceData($_POST['subject_id'], $_POST['student_id']);
$studentPermissions = $attendanceService->getStudentPermissions($_POST['student_id'], $_POST['class_id']);
$diaryEntries = $attendanceService->getDiaryEntriesBySubject($_POST['subject_id']);
$courseOutcomes = $assessmentService->getCOsBySubjectId($_POST['subject_id']);

// Calculate subject attendance summary metrics with safe zero handling
$totalConducted = count($attendanceData);
$totalPresent = 0;
$totalAbsent = 0;
$totalLeave = 0;
$totalPermission = 0;

foreach ($attendanceData as $attRow) {
    $permissionKey = $attRow['date'] . '_' . $attRow['hour'];
    $hasPermission = isset($studentPermissions[$permissionKey]);

    if ($hasPermission) {
        $totalPermission++;
    } elseif ($attRow['status'] === 'P') {
        $totalPresent++;
    } elseif ($attRow['status'] === 'A') {
        $totalAbsent++;
    } elseif ($attRow['status'] === 'L') {
        $totalLeave++;
    }
}

$subjectPercentage = ($totalConducted > 0) ? round(($totalPresent / $totalConducted) * 100, 1) : null;
?>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header">
            <div class="float-start">
                <h6 class="mb-0">
                    <?= htmlspecialchars($_POST['classname']); ?>
                    <?php if ($isInactiveInClass): ?>
                        <span class="badge bg-secondary ms-2">Inactive / Transferred</span>
                    <?php endif; ?>
                </h6>
                <small>Academic Year: <?= htmlspecialchars($_POST['acad_year']); ?></small>
            </div>
            <div class="float-end d-flex gap-2">
                <form action="studentsubjects.php" method="post" class="d-inline mb-0">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($_POST['student_id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($_POST['class_id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="classname" value="<?= htmlspecialchars($_POST['classname'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="acad_year" value="<?= htmlspecialchars($_POST['acad_year'], ENT_QUOTES, 'UTF-8'); ?>">
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
            <?= htmlspecialchars($_POST['subcode']); ?> <br> <?= htmlspecialchars($_POST["sub_fullname"]); ?>
        </div>
        <div class="card-body">
            <?php if ($isInactiveInClass): ?>
                <div class="alert alert-secondary py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i><strong>Note:</strong> You are currently marked inactive in this class section (e.g. transferred or shifted mid-semester). Displaying past attendance records accrued during your active enrollment.
                </div>
            <?php endif; ?>
            <?php if (\FeatureManager::isStudentVisible('MOD_ATTENDANCE')): ?>
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
                            <?php if ($totalPermission > 0): ?>
                                <small class="text-info fw-semibold">+<?= $totalPermission ?> OD / Perm</small>
                            <?php else: ?>
                                <small class="text-muted">Attended</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                            <small class="text-muted text-uppercase fw-bold">Absent</small>
                            <h3 class="fw-bold text-danger my-1"><?= $totalAbsent ?></h3>
                            <?php if ($totalLeave > 0): ?>
                                <small class="text-warning fw-semibold">+<?= $totalLeave ?> Leave</small>
                            <?php else: ?>
                                <small class="text-muted">Missed</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light shadow-sm h-100 d-flex flex-column justify-content-center">
                            <small class="text-muted text-uppercase fw-bold">Percentage</small>
                            <?php if ($totalConducted > 0 && $subjectPercentage !== null): 
                                $pctClass = match(true) {
                                    $subjectPercentage >= 75.0 => 'text-success',
                                    $subjectPercentage >= 65.0 => 'text-warning',
                                    default => 'text-danger'
                                };
                                $statusLabel = match(true) {
                                    $subjectPercentage >= 75.0 => 'Eligible (≥ 75%)',
                                    $subjectPercentage >= 65.0 => 'Condonable (65-75%)',
                                    default => 'Shortage (< 65%)'
                                };
                            ?>
                                <h3 class="fw-bold <?= $pctClass ?> my-1"><?= number_format($subjectPercentage, 1) ?>%</h3>
                                <small class="<?= $pctClass ?> fw-semibold"><?= $statusLabel ?></small>
                            <?php else: ?>
                                <h3 class="fw-bold text-secondary my-1">—</h3>
                                <small class="text-muted">No classes held</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($attendanceData)): ?>
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
                            <?php
                            foreach ($attendanceData as $attendance): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($attendance['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars(date("g:i A", strtotime($attendance['start_time'])), ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars(date("g:i A", strtotime($attendance['end_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php
                                        $permissionKey = $attendance['date'] . '_' . $attendance['hour'];
                                        $hasPermission = isset($studentPermissions[$permissionKey]);

                                        switch ($attendance['status']) {
                                            case 'P':
                                                if ($hasPermission) {
                                                    echo '<span class="badge bg-info">Permission</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">Present</span>';
                                                }
                                                break;
                                            case 'A':
                                                if ($hasPermission) {
                                                    echo '<span class="badge bg-info">Permission</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Absent</span>';
                                                }
                                                break;
                                            case 'L':
                                                echo '<span class="badge bg-warning">Leave</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Not Recorded</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No attendance records found yet.</div>
                <?php endif; ?>
                <hr>
            <?php endif; ?>
            <?php if (!empty($courseOutcomes['data']) && \FeatureManager::isStudentVisible('MOD_CO_PO')) { ?>
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
                        <?php
                        foreach ($courseOutcomes['data'] as $index => $co) {
                            echo "<tr><td>CO" . htmlspecialchars($co['co_number']) . "</td><td>" . htmlspecialchars($co['co_description']) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php } ?>

            <hr>

            <?php if (!empty($diaryEntries) && \FeatureManager::isStudentVisible('MOD_CLASS_DIARY')) : ?>
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
                        <?php
                        foreach ($diaryEntries as $entry) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars(date('d-m-Y', strtotime($entry['date'])), ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . htmlspecialchars(date("g:i A", strtotime($entry['start_time'])), ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars(date("g:i A", strtotime($entry['end_time'])), ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . htmlspecialchars($entry['diary'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>



            <?php if (\FeatureManager::isStudentVisible('MOD_CIA_MARKS')):
                require_once __DIR__ . '/core/CiaService.php';
                require_once __DIR__ . '/core/PgCiaService.php';
                $ciaSubject = (int)$_POST['subject_id'];
                $ciaStudent = (int)$_POST['student_id'];
                try {
                    $ciaReport = (new \College\CiaService($assessmentService->getConnection(), $_SESSION))->forSubject($ciaSubject, $ciaStudent);
                    $ciaResult = $ciaReport['values'][$ciaSubject][$ciaStudent] ?? ['final' => null, 'reason' => 'No finalized assessment records.'];
                    $ciaEvidence = $ciaReport['evidence'][$ciaSubject][$ciaStudent] ?? [];
                    $ciaPg = ($ciaReport['subjects'][0]['program_level'] ?? '') === 'PG';
                } catch (Throwable $e) {
                    $ciaResult = ['final' => null, 'reason' => $e->getMessage()];
                    $ciaEvidence = [];
                }
                $ciaEscape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="2">Continuous Internal Assessment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ciaEvidence as $ciaRow): ?>
                            <tr>
                                <td><?= $ciaEscape($ciaPg ? \College\PgCiaService::label((int)$ciaRow['assessment_number']) : 'Assessment ' . $ciaRow['assessment_number']) ?></td>
                                <td>
                                    <?php $ciaParts = [];
                                    foreach (['subjective_marks' => 'Subjective', 'objective_marks' => 'Objective', 'assignment_marks' => 'Assignment', 'day_to_day_marks' => 'Day to day', 'internal_test_marks' => 'Internal test', 'component1_marks' => 'Component 1', 'component2_marks' => 'Component 2', 'marks' => 'Marks'] as $ciaField => $ciaLabel) {
                                        if (array_key_exists($ciaField, $ciaRow)) {
                                            $ciaParts[] = $ciaLabel . ': ' . $ciaRow[$ciaField];
                                        }
                                    } ?>
                                    <?= $ciaEscape(implode('; ', $ciaParts)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Final CIA</th>
                            <td><?= $ciaResult['final'] !== null ? $ciaEscape($ciaResult['final']) : 'Pending — ' . $ciaEscape($ciaResult['reason']) ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (\FeatureManager::isStudentVisible('MOD_RESULTS_PUBLISH')):
                require_once __DIR__ . '/services/ExamResultsService.php';
                $examSvc = \Services\ExamResultsService::getInstance();
                $subResult = $examSvc->getStudentSubjectResult((string)$_SESSION['user'], (int)$_POST['subject_id'], (string)$_POST['subcode']);
            ?>
                <br>
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                        <span><i class="bi bi-award me-2"></i><strong>Semester End Examination (SEE) Results & Grade Card</strong></span>
                        <?php if ($subResult): ?>
                            <span class="badge bg-success">Published: <?= date('d M Y', strtotime($subResult['release_date'])) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Awaiting Publication</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($subResult):
                            $statusBadge = match ($subResult['result_status']) {
                                'PASS' => 'bg-success',
                                'FAIL' => 'bg-danger',
                                'ABSENT' => 'bg-warning text-dark',
                                'MALPRACTICE' => 'bg-dark',
                                default => 'bg-secondary'
                            };
                        ?>
                            <div class="p-3 bg-light border-bottom">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($subResult['notification_title']) ?></h6>
                                        <small class="text-muted">
                                            Regulation: <strong><?= htmlspecialchars($subResult['regulation']) ?></strong> |
                                            Sem: <strong><?= htmlspecialchars($subResult['yearsem']) ?></strong> |
                                            Session: <strong><?= htmlspecialchars($subResult['month_year']) ?></strong>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                        <span class="badge <?= $statusBadge ?> fs-6 px-3 py-2">RESULT: <?= htmlspecialchars($subResult['result_status']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0 text-center">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Internal (IM)</th>
                                            <th>External (EM)</th>
                                            <th>Total Marks</th>
                                            <th>Grade Letter</th>
                                            <th>Grade Points</th>
                                            <th>Credits Registered</th>
                                            <th>Credits Earned</th>
                                            <th>Final Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="fw-semibold text-secondary">
                                                <?= ($subResult['internal_marks'] !== null) ? number_format((float)$subResult['internal_marks'], 1) : '—' ?>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <?= ($subResult['external_marks'] !== null) ? number_format((float)$subResult['external_marks'], 1) : ($subResult['result_status'] === 'ABSENT' ? 'AB' : '—') ?>
                                            </td>
                                            <td class="fw-bold fs-6">
                                                <?= ($subResult['total_marks'] !== null) ? number_format((float)$subResult['total_marks'], 1) : '—' ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($subResult['grade_letter'] ?? '—') ?></span>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars((string)($subResult['grade_points'] ?? '—')) ?>
                                            </td>
                                            <td>
                                                <?= number_format((float)$subResult['credits_registered'], 1) ?>
                                            </td>
                                            <td class="fw-bold text-success">
                                                <?= number_format((float)$subResult['credits_earned'], 1) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($subResult['result_status']) ?></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">
                                <i class="bi bi-info-circle me-1"></i> Official semester examination results for this subject have not been published yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>
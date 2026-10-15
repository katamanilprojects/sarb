<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_RESULTS_PUBLISH');

$page_title = "Results";
require_once("stheader.php");
require_once __DIR__ . '/services/ExamResultsService.php';

$htno = trim($_SESSION['user'] ?? '');
$service = \Services\ExamResultsService::getInstance();

$selectedNotificationId = isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0;
$resultsData = $service->getStudentResults($htno, $selectedNotificationId > 0 ? $selectedNotificationId : null);

// Group results by exam notification
$grouped = [];
foreach ($resultsData['results'] as $row) {
    $notifId = $row['notification_id'];
    if (!isset($grouped[$notifId])) {
        $grouped[$notifId] = [
            'info' => [
                'title' => $row['notification_title'],
                'month_year' => $row['month_year'],
                'release_date' => $row['release_date'],
                'yearsem' => $row['yearsem'],
                'academic_year' => $row['academic_year'],
                'regulation' => $row['regulation']
            ],
            'items' => [],
            'reg_credits' => 0.0,
            'earned_credits' => 0.0,
            'weighted_points' => 0.0
        ];
    }
    $grouped[$notifId]['items'][] = $row;
    $reg = (float)$row['credits_registered'];
    $earned = (float)$row['credits_earned'];
    $pts = (float)$row['grade_points'];

    $grouped[$notifId]['reg_credits'] += $reg;
    $grouped[$notifId]['earned_credits'] += $earned;
    $grouped[$notifId]['weighted_points'] += ($reg * $pts);
}

// Calculate SGPA for each notification
foreach ($grouped as $k => $grp) {
    $grouped[$k]['sgpa'] = ($grp['reg_credits'] > 0) 
        ? round($grp['weighted_points'] / $grp['reg_credits'], 2) 
        : 0.00;
}
?>

<div class="container my-3">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Examination Results & Grade Cards</h4>
            <small class="text-muted">Verified End-Semester Examination Grades for Roll No: <strong><?= htmlspecialchars($htno) ?></strong></small>
        </div>
        <div class="dontprint">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="bi bi-printer me-1"></i> Print Grade Card
            </button>
        </div>
    </div>

    <!-- Official Printable Header (Visible on print) -->
    <div class="d-none d-print-block text-center border-bottom pb-3 mb-4">
        <h4 class="mb-0 fw-bold">JAWAHARLAL NEHRU TECHNOLOGICAL UNIVERSITY ANANTAPUR</h4>
        <h5 class="text-muted">COLLEGE OF ENGINEERING (AUTONOMOUS), ANANTHAPURAMU</h5>
        <h6 class="fw-bold mt-2">STUDENT MEMORANDUM OF GRADES</h6>
        <div class="row mt-3 text-start small">
            <div class="col-6">
                <strong>Hall Ticket No:</strong> <?= htmlspecialchars($htno) ?><br>
                <strong>Student Name:</strong> <?= htmlspecialchars($_SESSION['name'] ?? '') ?>
            </div>
            <div class="col-6 text-end">
                <strong>Generated On:</strong> <?= date('d-m-Y H:i:s') ?>
            </div>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox text-muted display-4"></i>
                <h5 class="mt-3 text-secondary">No Published Examination Results Found</h5>
                <p class="text-muted small mb-0">
                    Results have not yet been published for roll number <strong><?= htmlspecialchars($htno) ?></strong>.<br>
                    Please contact the Academic / Examination Section if you believe this is an error.
                </p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $notifId => $exam): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h6 class="mb-1 text-primary fw-bold">
                            <i class="bi bi-award me-1"></i> <?= htmlspecialchars($exam['info']['title']) ?>
                        </h6>
                        <span class="badge bg-secondary me-1">Regulation: <?= htmlspecialchars($exam['info']['regulation']) ?></span>
                        <span class="badge bg-info text-dark me-1">Sem: <?= htmlspecialchars($exam['info']['yearsem']) ?></span>
                        <span class="badge bg-light text-dark border">Exam Date: <?= htmlspecialchars($exam['info']['month_year']) ?></span>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block">Published: <?= date('d M Y', strtotime($exam['info']['release_date'])) ?></span>
                        <span class="badge bg-success fs-6 mt-1">SGPA: <?= number_format($exam['sgpa'], 2) ?></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-dark small">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 15%;">Subject Code</th>
                                    <th>Course Title</th>
                                    <th class="text-center" style="width: 8%;">Internal</th>
                                    <th class="text-center" style="width: 8%;">External</th>
                                    <th class="text-center" style="width: 8%;">Total</th>
                                    <th class="text-center" style="width: 8%;">Grade</th>
                                    <th class="text-center" style="width: 8%;">Grade Points</th>
                                    <th class="text-center" style="width: 8%;">Credits</th>
                                    <th class="text-center" style="width: 10%;">Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($exam['items'] as $item): 
                                    $statusBadge = match($item['result_status']) {
                                        'PASS' => 'bg-success',
                                        'FAIL' => 'bg-danger',
                                        'ABSENT' => 'bg-warning text-dark',
                                        'MALPRACTICE' => 'bg-dark',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                    <tr>
                                        <td><?= $idx++ ?></td>
                                        <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($item['subject_code']) ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($item['subject_name'] ?? '—') ?></td>
                                        <td class="text-center text-muted"><?= $item['internal_marks'] !== null ? number_format((float)$item['internal_marks'], 1) : '—' ?></td>
                                        <td class="text-center fw-bold"><?= $item['external_marks'] !== null ? number_format((float)$item['external_marks'], 1) : '—' ?></td>
                                        <td class="text-center fw-bold"><?= $item['total_marks'] !== null ? number_format((float)$item['total_marks'], 1) : '—' ?></td>
                                        <td class="text-center"><span class="badge bg-primary fs-6"><?= htmlspecialchars($item['grade_letter'] ?? '—') ?></span></td>
                                        <td class="text-center"><?= htmlspecialchars((string)($item['grade_points'] ?? '—')) ?></td>
                                        <td class="text-center"><?= number_format((float)$item['credits_registered'], 1) ?></td>
                                        <td class="text-center"><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($item['result_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold small">
                                <tr>
                                    <td colspan="8" class="text-end">Summary for Semester:</td>
                                    <td class="text-center">Earned: <?= number_format($exam['earned_credits'], 1) ?> / <?= number_format($exam['reg_credits'], 1) ?></td>
                                    <td class="text-center text-success">SGPA: <?= number_format($exam['sgpa'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
@media print {
    .dontprint { display: none !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
}
</style>

<?php require_once("stfooter.php"); ?>

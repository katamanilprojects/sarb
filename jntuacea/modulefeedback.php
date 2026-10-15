<?php require_once __DIR__.'/bootstrap.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
/**
 * modulefeedback.php - Unified Presentation Component for Course Outcome & Questionnaire Feedback
 * Displays KPI summary cards, structured feedback tables, interactive charts, and enhanced download actions.
 */

// Determine active view mode and feedback dataset
$hasNewServiceData = !empty($feedbackData) && is_array($feedbackData) && ($feedbackData['status'] ?? 0) == 1;

// Resolve effective view level cleanly
$viewLevelCandidate = $view_level ?? 'subject';
if ((isset($selected_sub_id) && $selected_sub_id === 'all') || ($viewLevelCandidate === 'class')) {
    $activeViewLevel = 'class';
} elseif ($viewLevelCandidate === 'faculty') {
    $activeViewLevel = 'faculty';
} elseif ($viewLevelCandidate === 'department') {
    $activeViewLevel = 'department';
} else {
    $activeViewLevel = (!empty($selected_sub_id) && $selected_sub_id !== 'all') ? 'subject' : (!empty($selected_cls_id) ? 'class' : 'subject');
}

// Normalize summary metrics
$kpi_enrolled = 0;
$kpi_responded = 0;
$kpi_response_rate = 0;
$kpi_overall_avg = 0;
$kpi_positive_pct = 0;

if ($hasNewServiceData) {
    if ($activeViewLevel === 'subject') {
        $kpi_enrolled = $feedbackData['total_enrolled'] ?? 0;
        $kpi_responded = $feedbackData['total_responded'] ?? 0;
        $kpi_response_rate = $feedbackData['response_rate'] ?? 0;
        $kpi_overall_avg = $feedbackData['summary']['overall_avg'] ?? 0;
        $kpi_positive_pct = $feedbackData['summary']['positive_response_pct'] ?? 0;
    } elseif ($activeViewLevel === 'class') {
        $kpi_enrolled = $feedbackData['total_students'] ?? 0;
        $kpi_responded = $feedbackData['total_responded'] ?? 0;
        $kpi_response_rate = $feedbackData['response_rate'] ?? 0;
        $kpi_overall_avg = $feedbackData['summary']['overall_avg'] ?? 0;
        $kpi_positive_pct = $feedbackData['summary']['positive_response_pct'] ?? 0;
    } elseif ($activeViewLevel === 'faculty') {
        $kpi_overall_avg = $feedbackData['summary']['overall_co_avg'] ?? 0;
        $kpi_positive_pct = $feedbackData['summary']['positive_response_pct'] ?? 0;
    } elseif ($activeViewLevel === 'department') {
        $kpi_enrolled = $feedbackData['summary']['total_students'] ?? 0;
        $kpi_responded = $feedbackData['summary']['total_responses'] ?? 0;
        $kpi_overall_avg = $feedbackData['summary']['overall_avg'] ?? 0;
        if ($kpi_enrolled > 0) {
            $kpi_response_rate = round(($kpi_responded / $kpi_enrolled) * 100, 1);
        }
    }
} else {
    // Legacy fallback calculations
    if (!empty($coFeedbackReport) && is_array($coFeedbackReport)) {
        $sum = 0;
        $cnt = 0;
        $maxResp = 0;
        foreach ($coFeedbackReport as $co) {
            $sum += floatval($co['average_rating'] ?? 0);
            $cnt++;
            if (intval($co['total_responses'] ?? 0) > $maxResp) {
                $maxResp = intval($co['total_responses']);
            }
        }
        $kpi_overall_avg = $cnt > 0 ? round($sum / $cnt, 2) : 0;
        $kpi_responded = $maxResp;
        $kpi_enrolled = $totalStudents ?? 0;
        if ($kpi_enrolled > 0) {
            $kpi_response_rate = round(($kpi_responded / $kpi_enrolled) * 100, 1);
        }
    }
}

// Check whether we have data to render and whether valid parameters exist for export
$hasContent = $hasNewServiceData || !empty($coFeedbackReport) || !empty($qnFeedbackReport);

$canExport = $hasContent && (
    ($activeViewLevel === 'subject' && !empty($selected_sub_id) && $selected_sub_id !== 'all') ||
    ($activeViewLevel === 'class' && !empty($selected_cls_id)) ||
    ($activeViewLevel === 'faculty' && !empty($selected_fac_id)) ||
    ($activeViewLevel === 'department' && !empty($selected_dept_id))
);

$exportSubId = ($selected_sub_id === 'all' || empty($selected_sub_id)) ? '' : $selected_sub_id;
$effectiveAcadYear = !empty($selected_acad_year) ? $selected_acad_year : ($feedbackData['meta']['acad_year'] ?? '');
?>

<style>
    .chart-container-responsive {
        position: relative;
        width: 100%;
        min-height: 250px;
        max-height: 350px;
    }
    .chart-container-responsive-sm {
        position: relative;
        width: 100%;
        min-height: 200px;
        max-height: 260px;
    }
</style>

<?php if ($hasContent): ?>
    <div class="card mt-3 shadow-sm border-0">
        <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center py-2">
            <h6 class="mb-0">
                <i class="bi bi-bar-chart-line-fill me-2"></i>Feedback Analytics &amp; Reports
            </h6>
            <?php if ($canExport): ?>
                <div class="d-flex gap-2">
                    <!-- Export Action Buttons -->
                    <form action="download_feedback_excel.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="xlsx">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success bg-white shadow-sm">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                        </button>
                    </form>

                    <form action="download_feedback_enhanced.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="csv">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary bg-white shadow-sm">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                        </button>
                    </form>

                    <form action="download_feedback_enhanced.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="pdf">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger bg-white shadow-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <!-- KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <?php if ($activeViewLevel === 'department'): ?>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Active Classes</div>
                            <h3 class="mt-2 mb-0 text-primary"><?= $feedbackData['summary']['total_classes'] ?? 0 ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Total Courses</div>
                            <h3 class="mt-2 mb-0 text-primary"><?= $feedbackData['summary']['total_subjects'] ?? 0 ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Total Responses</div>
                            <h3 class="mt-2 mb-0 text-success"><?= $kpi_responded ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Dept Average</div>
                            <h3 class="mt-2 mb-0 <?= $kpi_overall_avg > 0 ? 'text-dark' : 'text-muted' ?>">
                                <?= $kpi_overall_avg > 0 ? number_format($kpi_overall_avg, 2) . ' <small class="text-muted fs-6">/ 5.0</small>' : 'N/A' ?>
                            </h3>
                        </div>
                    </div>

                <?php elseif ($activeViewLevel === 'faculty'): ?>
                    <div class="col-md-4">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Assigned Courses</div>
                            <h3 class="mt-2 mb-0 text-primary"><?= $feedbackData['summary']['total_subjects'] ?? count($subjects ?? []) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">CO Feedback Average</div>
                            <h3 class="mt-2 mb-0 <?= $kpi_overall_avg > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= $kpi_overall_avg > 0 ? number_format($kpi_overall_avg, 2) . ' <small class="text-muted fs-6">/ 5.0</small>' : 'N/A' ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light border rounded text-center">
                            <div class="text-muted small text-uppercase fw-semibold">Faculty Survey Score</div>
                            <h3 class="mt-2 mb-0 text-dark">
                                <?= !empty($feedbackData['faculty_evaluations']['avg_score']) ? number_format($feedbackData['faculty_evaluations']['avg_score'], 2) : 'N/A' ?>
                            </h3>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 bg-light border rounded text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold">Total Enrolled</div>
                            <h3 class="mt-2 mb-0 text-primary"><?= $kpi_enrolled ?></h3>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 bg-light border rounded text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold">Responses</div>
                            <h3 class="mt-2 mb-0 text-success">
                                <?= $kpi_responded ?>
                                <?php if ($kpi_response_rate > 0): ?>
                                    <small class="text-muted fs-6 d-block">(<?= $kpi_response_rate ?>%)</small>
                                <?php endif; ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 bg-light border rounded text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold">Average Rating</div>
                            <h3 class="mt-2 mb-0 <?= $kpi_overall_avg > 0 ? 'text-dark' : 'text-muted' ?>">
                                <?= $kpi_overall_avg > 0 ? number_format($kpi_overall_avg, 2) . ' <small class="text-muted fs-6">/ 5.0</small>' : 'N/A' ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 bg-light border rounded text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold">Positive Rate (&ge;3★)</div>
                            <h3 class="mt-2 mb-0 text-info"><?= $kpi_positive_pct > 0 ? $kpi_positive_pct . '%' : 'N/A' ?></h3>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md">
                        <div class="p-3 bg-light border rounded text-center h-100">
                            <div class="text-muted small text-uppercase fw-semibold">Indirect Attainment</div>
                            <?php 
                                $avgLvl = floatval($feedbackData['summary']['avg_indirect_level'] ?? 0);
                                $lvlBadge = $avgLvl >= 2.5 ? 'bg-success' : ($avgLvl >= 1.5 ? 'bg-primary' : ($avgLvl >= 0.5 ? 'bg-warning text-dark' : 'bg-secondary'));
                            ?>
                            <div class="mt-1">
                                <span class="badge <?= $lvlBadge ?> fs-6 py-1 px-3">
                                    Level <?= number_format($avgLvl, 1) ?> / 3.0
                                </span>
                                <?php if (!empty($feedbackData['summary']['avg_target_pct'])): ?>
                                    <small class="text-muted d-block mt-1"><?= $feedbackData['summary']['avg_target_pct'] ?>% students &ge; 60% target</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- VIEW LEVEL SPECIFIC CONTENT -->
            <?php if ($activeViewLevel === 'department' && !empty($feedbackData['classes'])): ?>
                <!-- Department Level Roll-up Table -->
                <h5 class="fw-bold text-secondary mb-3">Class-Wise Feedback Performance Overview</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 40%;">Class Name</th>
                                <th style="width: 15%;">Academic Year</th>
                                <th style="width: 10%;" class="text-center">Courses</th>
                                <th style="width: 15%;" class="text-center">Responses</th>
                                <th style="width: 15%;" class="text-center">Average (1-5)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sno = 1; foreach ($feedbackData['classes'] as $cls): 
                                $stats = $cls['stats'] ?? [];
                                $avg = floatval($stats['overall_avg'] ?? 0);
                            ?>
                                <tr>
                                    <td><?= $sno++ ?></td>
                                    <td><strong><?= htmlspecialchars($cls['classname']) ?></strong></td>
                                    <td><?= htmlspecialchars($cls['acad_year']) ?></td>
                                    <td class="text-center"><?= $stats['total_subjects'] ?? 0 ?></td>
                                    <td class="text-center">
                                        <?= $stats['total_responded'] ?? 0 ?>
                                        <?php if (!empty($stats['response_rate'])): ?>
                                            <span class="badge bg-light text-dark border ms-1"><?= $stats['response_rate'] ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $avg >= 3.5 ? 'bg-success' : ($avg >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                            <?= number_format($avg, 2) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Department Level Chart -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card p-3 shadow-none border">
                            <h6 class="fw-bold text-secondary text-center mb-3">Class Performance Comparison (Average Rating 1-5)</h6>
                            <div class="chart-container-responsive">
                                <canvas id="deptClassChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function() {
                        function initDeptChart() {
                            const ctx = document.getElementById('deptClassChart');
                            if (!ctx) return;
                            const classLabels = <?= json_encode(array_map(fn($c) => ($c['classname'] ?? '') . ' (' . ($c['acad_year'] ?? '') . ')', $feedbackData['classes'])) ?>;
                            const classRatings = <?= json_encode(array_map(fn($c) => round(floatval($c['stats']['overall_avg'] ?? 0), 2), $feedbackData['classes'])) ?>;

                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: classLabels,
                                    datasets: [{
                                        label: 'Class Average Rating',
                                        data: classRatings,
                                        backgroundColor: classRatings.map(r => r >= 3.5 ? 'rgba(25, 135, 84, 0.8)' : (r >= 2.5 ? 'rgba(13, 110, 253, 0.8)' : 'rgba(255, 193, 7, 0.8)')),
                                        borderColor: classRatings.map(r => r >= 3.5 ? '#198754' : (r >= 2.5 ? '#0d6efd' : '#ffc107')),
                                        borderWidth: 1,
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    scales: {
                                        x: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
                                    }
                                }
                            });
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initDeptChart);
                        } else {
                            initDeptChart();
                        }
                    })();
                </script>

            <?php elseif ($activeViewLevel === 'faculty' && !empty($feedbackData['subjects'])): ?>
                <!-- Faculty Level Overview -->
                <?php if (!empty($feedbackData['academic_year_summary'])): ?>
                    <h5 class="fw-bold text-secondary mb-3">
                        <i class="bi bi-calendar3 me-2"></i>Academic Year-Wise Performance Overview
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Academic Year</th>
                                    <th class="text-center">Assigned Courses</th>
                                    <th class="text-center">CO Responses</th>
                                    <th class="text-center">CO Feedback Average</th>
                                    <th class="text-center">Faculty Survey Score (1-5)</th>
                                    <th class="text-center">Overall Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackData['academic_year_summary'] as $ayStats): 
                                    $coA = floatval($ayStats['co_average'] ?? 0);
                                    $feS = floatval($ayStats['faculty_eval_score'] ?? 0);
                                    $ovR = floatval($ayStats['overall_rating'] ?? 0);
                                ?>
                                    <tr>
                                        <td><strong><i class="bi bi-calendar-check me-1 text-primary"></i><?= htmlspecialchars($ayStats['acad_year']) ?></strong></td>
                                        <td class="text-center"><?= $ayStats['total_subjects'] ?></td>
                                        <td class="text-center"><?= $ayStats['total_co_responses'] ?></td>
                                        <td class="text-center">
                                            <?php if ($coA > 0): ?>
                                                <span class="badge <?= $coA >= 3.5 ? 'bg-success' : ($coA >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                                    <?= number_format($coA, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($feS > 0): ?>
                                                <span class="badge <?= $feS >= 3.5 ? 'bg-success' : ($feS >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                                    <?= number_format($feS, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($ovR > 0): ?>
                                                <strong class="text-<?= $ovR >= 3.5 ? 'success' : ($ovR >= 2.5 ? 'primary' : 'warning') ?> fs-6">
                                                    <?= number_format($ovR, 2) ?> <small class="text-muted">/ 5.0</small>
                                                </strong>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold text-secondary mb-3">Assigned Courses Feedback</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Class</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th class="text-center">Academic Year</th>
                                <th class="text-center">CO Feedback Average</th>
                                <th class="text-center">Performance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbackData['subjects'] as $sub): 
                                $sid = $sub['id'] ?? 0;
                                $avg = floatval($sub['summary']['overall_co_avg'] ?? 0);
                                if ($avg <= 0 && !empty($feedbackData['co_feedback'])) {
                                    $subCOs = array_filter($feedbackData['co_feedback'], fn($c) => ($c['subject_id'] ?? 0) == $sid && intval($c['total_responses'] ?? 0) > 0);
                                    if (!empty($subCOs)) {
                                        $subCoSum = array_sum(array_map(fn($c) => floatval($c['average_rating']) * intval($c['total_responses']), $subCOs));
                                        $subCoCnt = array_sum(array_column($subCOs, 'total_responses'));
                                        $avg = $subCoCnt > 0 ? round($subCoSum / $subCoCnt, 2) : 0;
                                    }
                                }
                                $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.0 ? 'Good' : ($avg > 0 ? 'Needs Improvement' : 'Pending'));
                                $statusBadge = $avg >= 4.0 ? 'bg-success' : ($avg >= 3.0 ? 'bg-primary' : ($avg > 0 ? 'bg-warning text-dark' : 'bg-secondary'));
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($sub['classname']) ?></td>
                                    <td><strong><?= htmlspecialchars($sub['subcode']) ?></strong></td>
                                    <td><?= htmlspecialchars($sub['sub_fullname']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($sub['acad_year']) ?></td>
                                    <td class="text-center">
                                        <?php if ($avg > 0): ?>
                                            <span class="badge <?= $statusBadge ?> fs-6"><?= number_format($avg, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $statusBadge ?>"><?= $status ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($feedbackData['co_feedback']) && empty($hideFacultyCOBreakdown)): ?>
                    <h5 class="fw-bold text-secondary mb-3">Course Outcomes Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th class="text-center">CO No</th>
                                    <th>Description</th>
                                    <th class="text-center">Average (1-5)</th>
                                    <th class="text-center">Responses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackData['co_feedback'] as $co): ?>
                                    <tr>
                                        <td><small><?= htmlspecialchars($co['classname']) ?></small></td>
                                        <td><strong><?= htmlspecialchars($co['subcode']) ?></strong></td>
                                        <td class="text-center">CO<?= htmlspecialchars($co['co_number']) ?></td>
                                        <td><?= htmlspecialchars($co['co_description']) ?></td>
                                        <td class="text-center fw-bold"><?= number_format($co['average_rating'], 2) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($co['total_responses']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($feedbackData['faculty_evaluations']['total_evaluations'])): ?>
                    <h5 class="fw-bold text-secondary mt-4 mb-3">
                        <i class="bi bi-star-fill text-warning me-2"></i>Student Evaluation on Faculty (19 OBE Parameters)
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80%;">Evaluation Domain / Parameter Statement</th>
                                    <th style="width: 20%;" class="text-center">Average Rating (1-5)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $facDefs = FeedbackService::getFacultyQuestions();
                                    foreach ($facDefs as $secTitle => $secQs):
                                        $secDomainAvg = $feedbackData['faculty_evaluations']['domain_averages'][$secTitle] ?? null;
                                ?>
                                    <tr class="table-primary bg-opacity-25">
                                        <td class="fw-bold text-dark py-2">
                                            <i class="bi bi-folder2-open me-2 text-primary"></i><?= htmlspecialchars($secTitle) ?>
                                        </td>
                                        <td class="text-center py-2">
                                            <?php if ($secDomainAvg !== null): ?>
                                                <span class="badge bg-primary fs-6" title="Domain Overall Average">
                                                    Avg: <?= number_format(floatval($secDomainAvg), 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($secQs as $qKey => $qText): 
                                        $qAvg = floatval($feedbackData['faculty_evaluations']['averages'][$qKey] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($qText) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $qAvg >= 3.5 ? 'bg-success' : ($qAvg >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                                    <?= number_format($qAvg, 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php 
                        $remarks = $feedbackData['faculty_evaluations']['remarks'] ?? [];
                        $collapseId = 'facRemarksCollapse';
                        $showSubjectLabel = true;
                        $metaSubCode = $feedbackData['meta']['subcode'] ?? '';
                        $metaSubName = $feedbackData['meta']['sub_fullname'] ?? '';
                        include('views/partials/faculty_remarks_card.php');
                    ?>
                <?php endif; ?>

                <!-- Faculty Level Charts -->
                <div class="row g-4 mt-2 mb-4">
                    <?php if (!empty($feedbackData['subjects'])): ?>
                        <div class="col-md-6">
                            <div class="card p-3 shadow-none border h-100">
                                <h6 class="fw-bold text-secondary text-center mb-3">Assigned Courses CO Feedback Average</h6>
                                <div class="chart-container-responsive">
                                    <canvas id="facSubjectsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($feedbackData['faculty_evaluations']['domain_averages'])): ?>
                        <div class="col-md-6">
                            <div class="card p-3 shadow-none border h-100">
                                <h6 class="fw-bold text-secondary text-center mb-3">Student Evaluation: OBE Domain Scores</h6>
                                <div class="chart-container-responsive">
                                    <canvas id="facDomainChart"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                    (function() {
                        function initFacCharts() {
                            <?php if (!empty($feedbackData['subjects'])): ?>
                            const ctxSub = document.getElementById('facSubjectsChart');
                            if (ctxSub) {
                                const subLabels = <?= json_encode(array_map(fn($s) => ($s['subcode'] ?? '') . ' (' . ($s['classname'] ?? '') . ')', $feedbackData['subjects'])) ?>;
                                <?php
                                    $subAvgs = [];
                                    foreach ($feedbackData['subjects'] as $s) {
                                        $sid = $s['id'] ?? 0;
                                        $scos = array_filter($feedbackData['co_feedback'] ?? [], fn($c) => ($c['subject_id'] ?? 0) == $sid);
                                        $savg = !empty($scos) ? round(array_sum(array_column($scos, 'average_rating')) / count($scos), 2) : 0;
                                        $subAvgs[] = $savg;
                                    }
                                ?>
                                const subRatings = <?= json_encode($subAvgs) ?>;

                                new Chart(ctxSub, {
                                    type: 'bar',
                                    data: {
                                        labels: subLabels,
                                        datasets: [{
                                            label: 'Average CO Rating',
                                            data: subRatings,
                                            backgroundColor: subRatings.map(r => r >= 3.5 ? 'rgba(25, 135, 84, 0.8)' : (r >= 2.5 ? 'rgba(13, 110, 253, 0.8)' : 'rgba(255, 193, 7, 0.8)')),
                                            borderColor: subRatings.map(r => r >= 3.5 ? '#198754' : (r >= 2.5 ? '#0d6efd' : '#ffc107')),
                                            borderWidth: 1,
                                            borderRadius: 4
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
                                        }
                                    }
                                });
                            }
                            <?php endif; ?>

                            <?php if (!empty($feedbackData['faculty_evaluations']['domain_averages'])): ?>
                            const ctxDom = document.getElementById('facDomainChart');
                            if (ctxDom) {
                                const domLabels = <?= json_encode(array_keys($feedbackData['faculty_evaluations']['domain_averages'])) ?>;
                                const domScores = <?= json_encode(array_values($feedbackData['faculty_evaluations']['domain_averages'])) ?>;

                                new Chart(ctxDom, {
                                    type: 'bar',
                                    data: {
                                        labels: domLabels,
                                        datasets: [{
                                            label: 'OBE Domain Average (1-5)',
                                            data: domScores,
                                            backgroundColor: 'rgba(13, 110, 253, 0.75)',
                                            borderColor: '#0d6efd',
                                            borderWidth: 1,
                                            borderRadius: 4
                                        }]
                                    },
                                    options: {
                                        indexAxis: 'y',
                                        responsive: true,
                                        scales: {
                                            x: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
                                        }
                                    }
                                });
                            }
                            <?php endif; ?>
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initFacCharts);
                        } else {
                            initFacCharts();
                        }
                    })();
                </script>

            <?php elseif ($activeViewLevel === 'class' && $hasNewServiceData && !empty($feedbackData['subjects'])): ?>
                <!-- Class Level Subject-wise overview -->
                <h5 class="fw-bold text-secondary mb-3">Subject Performance Overview</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 14%;">Subject Code</th>
                                <th style="width: 36%;">Subject Full Name</th>
                                <th style="width: 20%;">Faculty Assigned</th>
                                <th style="width: 12%;" class="text-center">Average (1-5)</th>
                                <th style="width: 13%;" class="text-center">Indirect Attainment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sno = 1; foreach ($feedbackData['subjects'] as $sub): 
                                $avg = floatval($sub['summary']['overall_co_avg'] ?? 0);
                                $subNba = $sub['summary']['nba_indirect_attainment'] ?? ['level' => 0, 'pct_meeting_target' => 0, 'badge_class' => 'secondary'];
                            ?>
                                <tr>
                                    <td><?= $sno++ ?></td>
                                    <td><strong><?= htmlspecialchars($sub['subcode']) ?></strong></td>
                                    <td><?= htmlspecialchars($sub['sub_fullname']) ?></td>
                                    <td><?= htmlspecialchars($sub['faculty_names']) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $avg >= 3.5 ? 'bg-success' : ($avg >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                            <?= number_format($avg, 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $subNba['badge_class'] ?> fs-6">
                                            Level <?= $subNba['level'] ?> (<?= $subNba['pct_meeting_target'] ?>%)
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Class Level Chart -->
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="card p-3 shadow-none border">
                            <h6 class="fw-bold text-secondary text-center mb-3">Class Subject Performance &amp; Attainment Comparison</h6>
                            <div class="chart-container-responsive">
                                <canvas id="classSubjectChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    (function() {
                        function initClassChart() {
                            const ctx = document.getElementById('classSubjectChart');
                            if (!ctx) return;
                            const subLabels = <?= json_encode(array_map(fn($s) => ($s['subcode'] ?? '') . ' - ' . ($s['sub_shortname'] ?? ($s['sub_fullname'] ?? '')), $feedbackData['subjects'])) ?>;
                            const subRatings = <?= json_encode(array_map(fn($s) => round(floatval($s['summary']['overall_co_avg'] ?? 0), 2), $feedbackData['subjects'])) ?>;

                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: subLabels,
                                    datasets: [{
                                        label: 'Subject Average Rating',
                                        data: subRatings,
                                        backgroundColor: subRatings.map(r => r >= 3.5 ? 'rgba(25, 135, 84, 0.8)' : (r >= 2.5 ? 'rgba(13, 110, 253, 0.8)' : 'rgba(255, 193, 7, 0.8)')),
                                        borderColor: subRatings.map(r => r >= 3.5 ? '#198754' : (r >= 2.5 ? '#0d6efd' : '#ffc107')),
                                        borderWidth: 1,
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
                                    }
                                }
                            });
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initClassChart);
                        } else {
                            initClassChart();
                        }
                    })();
                </script>

                <!-- Detailed Class CO Table -->
                <h5 class="fw-bold text-secondary mb-3">Detailed Course Outcome Breakdown &amp; Indirect Attainment</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th class="text-center">CO No</th>
                                <th>Description</th>
                                <th class="text-center">Average Rating</th>
                                <th class="text-center">Responses</th>
                                <th class="text-center">&ge; 60% Target Met</th>
                                <th class="text-center">Indirect Attainment Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbackData['co_feedback'] as $co): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($co['subcode']) ?></strong> - <?= htmlspecialchars($co['sub_fullname']) ?></td>
                                    <td class="text-center">CO<?= htmlspecialchars($co['co_number']) ?></td>
                                    <td><?= htmlspecialchars($co['co_description']) ?></td>
                                    <td class="text-center fw-bold"><?= number_format($co['average_rating'], 2) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($co['total_responses']) ?></td>
                                    <td class="text-center">
                                        <strong><?= $co['target_pct'] ?? 0 ?>%</strong>
                                        <small class="text-muted d-block">(<?= ($co['target_count'] ?? 0) . '/' . ($co['total_responses'] ?? 0) ?>)</small>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $lvl = $co['attainment_level'] ?? 0;
                                            $bClass = $co['badge_class'] ?? 'bg-secondary';
                                            $lbl = $co['attainment_label'] ?? "Level $lvl";
                                        ?>
                                        <span class="badge <?= $bClass ?> fs-6">Level <?= $lvl ?></span>
                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars($lbl) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- Single Subject Level View (or Legacy Multi-Subject) -->
                <?php 
                    $coItems = $hasNewServiceData ? ($feedbackData['co_feedback'] ?? []) : ($coFeedbackReport ?? []);
                    $qnItems = $hasNewServiceData ? ($feedbackData['qn_feedback'] ?? []) : ($qnFeedbackReport ?? []);
                ?>

                <!-- Visual Analytics & Charts (Placed before detailed tables) -->
                <?php if (!empty($coItems) && $activeViewLevel === 'subject'): ?>
                    <div class="row g-3 mb-4">
                        <!-- Chart 1: Average CO Rating -->
                        <div class="col-md-6">
                            <div class="card p-3 shadow-none border h-100">
                                <h6 class="fw-bold text-secondary text-center mb-3">CO Average Ratings &amp; Target Threshold (3.0 / 60%)</h6>
                                <div class="chart-container-responsive">
                                    <canvas id="coFeedbackChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Chart 2: 5-Star Rating Distribution -->
                        <div class="col-md-6">
                            <div class="card p-3 shadow-none border h-100">
                                <h6 class="fw-bold text-secondary text-center mb-3">CO Rating Distribution (5★ to 1★)</h6>
                                <div class="chart-container-responsive">
                                    <canvas id="coDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($feedbackData['ces_feedback']['domain_averages'])): ?>
                            <!-- Chart 3: Course End Survey (CES) 5 Domains (Balanced Two-Column) -->
                            <div class="col-md-6">
                                <div class="card p-3 shadow-none border h-100">
                                    <h6 class="fw-bold text-secondary text-center mb-3">Course End Survey (CES) 5-Domain Performance</h6>
                                    <div class="chart-container-responsive">
                                        <canvas id="cesDomainChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card p-3 shadow-none border h-100">
                                    <h6 class="fw-bold text-secondary text-center mb-3">CES Domain Scorecard</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Domain</th>
                                                    <th class="text-center" style="width: 28%;">Score (1-5)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($feedbackData['ces_feedback']['domain_averages'] as $dTitle => $dScore): 
                                                    $sc = floatval($dScore);
                                                    $scBadge = $sc >= 3.5 ? 'bg-success' : ($sc >= 2.5 ? 'bg-primary' : 'bg-warning text-dark');
                                                ?>
                                                    <tr>
                                                        <td><small class="fw-semibold text-secondary"><?= htmlspecialchars($dTitle) ?></small></td>
                                                        <td class="text-center">
                                                            <span class="badge <?= $scBadge ?> fs-6 py-1 px-2"><?= number_format($sc, 2) ?></span>
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

                    <script>
                        (function() {
                            function initSubjectCharts() {
                                const coLabels = <?= json_encode(array_map(function ($co) {
                                    return 'CO' . $co['co_number'];
                                }, $coItems)) ?>;
                                const coRatings = <?= json_encode(array_map(function ($co) {
                                    return round(floatval($co['average_rating']), 2);
                                }, $coItems)) ?>;
                                const targetPcts = <?= json_encode(array_map(function ($co) {
                                    return floatval($co['target_pct'] ?? 0);
                                }, $coItems)) ?>;

                                // Chart 1: CO Ratings
                                const ctx1 = document.getElementById('coFeedbackChart');
                                if (ctx1) {
                                    new Chart(ctx1, {
                                        type: 'bar',
                                        data: {
                                            labels: coLabels,
                                            datasets: [{
                                                label: 'Average CO Rating (out of 5.0)',
                                                data: coRatings,
                                                backgroundColor: coRatings.map(r => r >= 3.5 ? 'rgba(25, 135, 84, 0.8)' : (r >= 3.0 ? 'rgba(13, 110, 253, 0.8)' : 'rgba(220, 53, 69, 0.8)')),
                                                borderColor: coRatings.map(r => r >= 3.5 ? '#198754' : (r >= 3.0 ? '#0d6efd' : '#dc3545')),
                                                borderWidth: 1,
                                                borderRadius: 4
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    max: 5,
                                                    ticks: { stepSize: 1 }
                                                }
                                            }
                                        }
                                    });
                                }

                                // Chart 2: Rating Distribution Stacked Bar
                                const ctx2 = document.getElementById('coDistributionChart');
                                if (ctx2) {
                                    new Chart(ctx2, {
                                        type: 'bar',
                                        data: {
                                            labels: coLabels,
                                            datasets: [
                                                {
                                                    label: '5 Stars (Excellent)',
                                                    data: <?= json_encode(array_map(fn($c) => intval($c['count_5'] ?? 0), $coItems)) ?>,
                                                    backgroundColor: 'rgba(25, 135, 84, 0.85)'
                                                },
                                                {
                                                    label: '4 Stars (Very Good)',
                                                    data: <?= json_encode(array_map(fn($c) => intval($c['count_4'] ?? 0), $coItems)) ?>,
                                                    backgroundColor: 'rgba(13, 110, 253, 0.85)'
                                                },
                                                {
                                                    label: '3 Stars (Good)',
                                                    data: <?= json_encode(array_map(fn($c) => intval($c['count_3'] ?? 0), $coItems)) ?>,
                                                    backgroundColor: 'rgba(13, 202, 240, 0.85)'
                                                },
                                                {
                                                    label: '2 Stars (Fair)',
                                                    data: <?= json_encode(array_map(fn($c) => intval($c['count_2'] ?? 0), $coItems)) ?>,
                                                    backgroundColor: 'rgba(255, 193, 7, 0.85)'
                                                },
                                                {
                                                    label: '1 Star (Poor)',
                                                    data: <?= json_encode(array_map(fn($c) => intval($c['count_1'] ?? 0), $coItems)) ?>,
                                                    backgroundColor: 'rgba(220, 53, 69, 0.85)'
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            scales: {
                                                x: { stacked: true },
                                                y: { stacked: true, beginAtZero: true }
                                            }
                                        }
                                    });
                                }

                                <?php if (!empty($feedbackData['ces_feedback']['domain_averages'])): ?>
                                // Chart 3: CES 5-Domain Horizontal Bar Chart
                                const ctx3 = document.getElementById('cesDomainChart');
                                if (ctx3) {
                                    const domainLabels = <?= json_encode(array_keys($feedbackData['ces_feedback']['domain_averages'])) ?>;
                                    const domainScores = <?= json_encode(array_values($feedbackData['ces_feedback']['domain_averages'])) ?>;

                                    new Chart(ctx3, {
                                        type: 'bar',
                                        data: {
                                            labels: domainLabels,
                                            datasets: [{
                                                label: 'Domain Average (1 to 5)',
                                                data: domainScores,
                                                backgroundColor: 'rgba(13, 110, 253, 0.75)',
                                                borderColor: '#0d6efd',
                                                borderWidth: 1,
                                                borderRadius: 4
                                            }]
                                        },
                                        options: {
                                            indexAxis: 'y',
                                            responsive: true,
                                            scales: {
                                                x: {
                                                    beginAtZero: true,
                                                    max: 5,
                                                    ticks: { stepSize: 1 }
                                                }
                                            }
                                        }
                                    });
                                }
                                <?php endif; ?>
                            }
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', initSubjectCharts);
                            } else {
                                initSubjectCharts();
                            }
                        })();
                    </script>
                <?php endif; ?>

                <!-- 2. Course Outcomes (CO) Attainment (Part-A) -->
                <?php if (!empty($coItems)): ?>
                    <h5 class="fw-bold text-secondary mb-3">Course Outcome (CO) Indirect Attainment Ratings</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%;" class="text-center">CO No.</th>
                                    <th style="width: 32%;">Course Outcome Description</th>
                                    <th style="width: 12%;" class="text-center">Average (1-5)</th>
                                    <th style="width: 20%;" class="text-center">Rating Distribution (5★ to 1★)</th>
                                    <th style="width: 13%;" class="text-center">&ge; 60% Target Met</th>
                                    <th style="width: 15%;" class="text-center">Indirect Attainment Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coItems as $co): ?>
                                    <tr>
                                        <td class="text-center"><strong>CO<?= htmlspecialchars($co['co_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($co['co_description']) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-2">
                                                <span class="badge <?= floatval($co['average_rating']) >= 3.5 ? 'bg-success' : 'bg-primary' ?> fs-6">
                                                    <?= number_format($co['average_rating'], 2) ?>
                                                </span>
                                            </div>
                                            <small class="text-muted d-block mt-1">(<?= htmlspecialchars($co['total_responses']) ?> resp)</small>
                                        </td>
                                        <td>
                                            <?php 
                                                $cTotal = max(1, intval($co['total_responses']));
                                                $cnt5 = intval($co['count_5'] ?? 0);
                                                $cnt4 = intval($co['count_4'] ?? 0);
                                                $cnt3 = intval($co['count_3'] ?? 0);
                                                $cnt2 = intval($co['count_2'] ?? 0);
                                                $cnt1 = intval($co['count_1'] ?? 0);

                                                $p5 = ($cnt5 / $cTotal) * 100;
                                                $p4 = ($cnt4 / $cTotal) * 100;
                                                $p3 = ($cnt3 / $cTotal) * 100;
                                                $p2 = ($cnt2 / $cTotal) * 100;
                                                $p1 = ($cnt1 / $cTotal) * 100;

                                                $deg5 = $p5 * 3.6;
                                                $deg4 = $deg5 + ($p4 * 3.6);
                                                $deg3 = $deg4 + ($p3 * 3.6);
                                                $deg2 = $deg3 + ($p2 * 3.6);

                                                $conic = "#198754 0deg {$deg5}deg, #0d6efd {$deg5}deg {$deg4}deg, #0dcaf0 {$deg4}deg {$deg3}deg, #ffc107 {$deg3}deg {$deg2}deg, #dc3545 {$deg2}deg 360deg";
                                                $pieTitle = "5★: $cnt5 (" . round($p5, 1) . "%)\n4★: $cnt4 (" . round($p4, 1) . "%)\n3★: $cnt3 (" . round($p3, 1) . "%)\n2★: $cnt2 (" . round($p2, 1) . "%)\n1★: $cnt1 (" . round($p1, 1) . "%)";
                                            ?>
                                            <div class="d-flex align-items-center gap-2" title="<?= htmlspecialchars($pieTitle) ?>">
                                                <div style="width: 44px; height: 44px; min-width: 44px; border-radius: 50%; background: conic-gradient(<?= $conic ?>); position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.15);">
                                                    <div style="position: absolute; top: 12px; left: 12px; width: 20px; height: 20px; background: #fff; border-radius: 50%;"></div>
                                                </div>
                                                <div style="font-size: 0.73rem; line-height: 1.25;" class="w-100">
                                                    <div class="d-flex justify-content-between">
                                                        <span><span class="d-inline-block rounded-circle me-1" style="width:7px;height:7px;background:#198754;"></span>5★</span>
                                                        <strong class="text-success"><?= $cnt5 ?></strong>
                                                        <span class="ms-2"><span class="d-inline-block rounded-circle me-1" style="width:7px;height:7px;background:#0d6efd;"></span>4★</span>
                                                        <strong class="text-primary"><?= $cnt4 ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span><span class="d-inline-block rounded-circle me-1" style="width:7px;height:7px;background:#0dcaf0;"></span>3★</span>
                                                        <strong class="text-info text-dark"><?= $cnt3 ?></strong>
                                                        <span class="ms-2"><span class="d-inline-block rounded-circle me-1" style="width:7px;height:7px;background:#ffc107;"></span>2★</span>
                                                        <strong class="text-warning text-dark"><?= $cnt2 ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span><span class="d-inline-block rounded-circle me-1" style="width:7px;height:7px;background:#dc3545;"></span>1★</span>
                                                        <strong class="text-danger"><?= $cnt1 ?></strong>
                                                        <span class="text-muted ms-2">(Total: <?= $cTotal ?>)</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= $co['target_pct'] ?? 0 ?>%</strong>
                                            <small class="text-muted d-block">(<?= ($co['target_count'] ?? 0) . '/' . ($co['total_responses'] ?? 0) ?>)</small>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                                $lvl = $co['attainment_level'] ?? 0;
                                                $bClass = $co['badge_class'] ?? 'bg-secondary';
                                                $lbl = $co['attainment_label'] ?? "Level $lvl";
                                            ?>
                                            <span class="badge <?= $bClass ?> fs-6">Level <?= $lvl ?></span>
                                            <small class="text-muted d-block mt-1"><?= htmlspecialchars($lbl) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($qnItems)): ?>
                    <h5 class="fw-bold text-secondary mt-4 mb-3">Additional Questionnaire Responses</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%;" class="text-center">Q No.</th>
                                    <th style="width: 60%;">Question Text</th>
                                    <th style="width: 16%;">Created By</th>
                                    <th style="width: 16%;" class="text-center">Average Rating (1-5)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($qnItems as $qn): ?>
                                    <tr>
                                        <td class="text-center">Q<?= htmlspecialchars($qn['question_number']) ?></td>
                                        <td><?= htmlspecialchars($qn['question_text']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($qn['created_by_role'] ?? 'faculty')) ?></td>
                                        <td class="text-center fw-bold"><?= number_format($qn['average_rating'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- 3. Course End Survey (CES Part-B) 16 Parameters -->
                <?php if (!empty($feedbackData['ces_feedback']['total_responses'])): ?>
                    <h5 class="fw-bold text-secondary mt-4 mb-3">
                        <i class="bi bi-card-checklist text-primary me-2"></i>Course End Survey (CES - 16 Evaluation Parameters)
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80%;">Survey Domain / Evaluation Statement</th>
                                    <th style="width: 20%;" class="text-center">Average Rating (1-5)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $cesDefs = FeedbackService::getCesQuestions();
                                    foreach ($cesDefs as $secTitle => $secQs):
                                        $cesDomainAvg = $feedbackData['ces_feedback']['domain_averages'][$secTitle] ?? null;
                                ?>
                                    <tr class="table-primary bg-opacity-25">
                                        <td class="fw-bold text-dark py-2">
                                            <i class="bi bi-journal-bookmark-fill me-2 text-primary"></i><?= htmlspecialchars($secTitle) ?>
                                        </td>
                                        <td class="text-center py-2">
                                            <?php if ($cesDomainAvg !== null): ?>
                                                <span class="badge bg-primary fs-6" title="Domain Average Rating">
                                                    Avg: <?= number_format(floatval($cesDomainAvg), 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($secQs as $qKey => $qText): 
                                        $qAvg = floatval($feedbackData['ces_feedback']['averages'][$qKey] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($qText) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $qAvg >= 3.5 ? 'bg-success' : ($qAvg >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                                    <?= number_format($qAvg, 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- 4. Faculty Pedagogical Feedback (19 OBE Parameters) -->
                <?php if (!empty($feedbackData['faculty_evaluations']['total_evaluations'])): ?>
                    <h5 class="fw-bold text-secondary mt-4 mb-3">
                        <i class="bi bi-person-check-fill text-primary me-2"></i>Student Evaluation on Faculty (19 OBE Parameters)
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80%;">Evaluation Domain / Parameter Statement</th>
                                    <th style="width: 20%;" class="text-center">Average Rating (1-5)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $facDefs = FeedbackService::getFacultyQuestions();
                                    foreach ($facDefs as $secTitle => $secQs):
                                        $secDomainAvg = $feedbackData['faculty_evaluations']['domain_averages'][$secTitle] ?? null;
                                ?>
                                    <tr class="table-primary bg-opacity-25">
                                        <td class="fw-bold text-dark py-2">
                                            <i class="bi bi-folder2-open me-2 text-primary"></i><?= htmlspecialchars($secTitle) ?>
                                        </td>
                                        <td class="text-center py-2">
                                            <?php if ($secDomainAvg !== null): ?>
                                                <span class="badge bg-primary fs-6" title="Domain Overall Average">
                                                    Avg: <?= number_format(floatval($secDomainAvg), 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($secQs as $qKey => $qText): 
                                        $qAvg = floatval($feedbackData['faculty_evaluations']['averages'][$qKey] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($qText) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $qAvg >= 3.5 ? 'bg-success' : ($qAvg >= 2.5 ? 'bg-primary' : 'bg-warning text-dark') ?> fs-6">
                                                    <?= number_format($qAvg, 2) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- 5. Qualitative Written Feedback (Part-C & Faculty Remarks) - Collapsible Ribbons -->
                <?php if (!empty($feedbackData['ces_feedback']['remarks'])): ?>
                    <div class="card mb-3 border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2" 
                             role="button" data-bs-toggle="collapse" data-bs-target="#cesSubjectRemarksCollapse" aria-expanded="false" aria-controls="cesSubjectRemarksCollapse"
                             style="cursor: pointer;">
                            <h6 class="mb-0 text-secondary fw-bold">
                                <i class="bi bi-chat-left-text-fill text-info me-2"></i>Student Qualitative Remarks (Course End Survey - Part-C)
                                <span class="badge bg-secondary ms-2"><?= count($feedbackData['ces_feedback']['remarks']) ?> Feedback Comments</span>
                            </h6>
                            <span class="text-primary small fw-semibold">
                                <i class="bi bi-chevron-down"></i> Click to View / Hide
                            </span>
                        </div>
                        <div id="cesSubjectRemarksCollapse" class="collapse">
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($feedbackData['ces_feedback']['remarks'] as $rem): ?>
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0 shadow-sm p-3 h-100">
                                                <div class="d-flex justify-content-between text-muted small mb-2">
                                                    <strong>Student: <?= htmlspecialchars($rem['student_roll'] ?? 'Anonymous') ?></strong>
                                                    <span><?= htmlspecialchars($rem['submitted_at'] ?? '') ?></span>
                                                </div>
                                                <?php if (!empty($rem['useful_aspects'])): ?>
                                                    <p class="mb-1 small"><strong>Useful Aspects:</strong> <?= nl2br(htmlspecialchars($rem['useful_aspects'])) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($rem['improvement_topics'])): ?>
                                                    <p class="mb-1 small"><strong>Topics for Improvement:</strong> <?= nl2br(htmlspecialchars($rem['improvement_topics'])) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($rem['suggestions'])): ?>
                                                    <p class="mb-0 small"><strong>Suggestions:</strong> <?= nl2br(htmlspecialchars($rem['suggestions'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                    $remarks = $feedbackData['faculty_evaluations']['remarks'] ?? [];
                    $collapseId = 'facSubjectRemarksCollapse';
                    $showSubjectLabel = false;
                    $metaSubCode = $feedbackData['meta']['subcode'] ?? '';
                    $metaSubName = $feedbackData['meta']['sub_fullname'] ?? '';
                    include('views/partials/faculty_remarks_card.php');
                ?>

            <?php endif; ?>
        </div>

        <!-- Footer Actions -->
        <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Generated by Academic Quality Assurance Feedback System</small>
            <?php if ($canExport): ?>
                <div class="d-flex gap-2">
                    <form action="download_feedback_excel.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="xlsx">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                        </button>
                    </form>

                    <form action="download_feedback_enhanced.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="csv">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                        </button>
                    </form>

                    <form action="download_feedback_enhanced.php" method="post" target="_blank" class="d-inline m-0">
                        <input type="hidden" name="format" value="pdf">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($activeViewLevel) ?>">
                        <input type="hidden" name="sub_id" value="<?= htmlspecialchars($exportSubId) ?>">
                        <input type="hidden" name="cls_id" value="<?= htmlspecialchars($selected_cls_id ?? '') ?>">
                        <input type="hidden" name="fac_id" value="<?= htmlspecialchars($selected_fac_id ?? '') ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($selected_dept_id ?? '') ?>">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($effectiveAcadYear) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!empty($selected_sub_id) || !empty($selected_cls_id) || !empty($selected_fac_id) || !empty($selected_dept_id)): ?>
    <div class="alert alert-info mt-4 d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>No feedback responses found for the selected criteria.</div>
    </div>
<?php else: ?>
    <div class="alert alert-info mt-4 d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>Please select a subject, class, faculty, or department to view feedback analytics and reports.</div>
    </div>
<?php endif; ?>
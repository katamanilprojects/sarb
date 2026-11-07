<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (empty($_SESSION['user']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'hod' || empty($_SESSION['dept_id'])) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_RESULTS_PUBLISH');

require_once __DIR__ . '/dbcredentials.class.php';

$db = \DBCredentials::getInstance()->getConnection();
$dept_id = (int)$_SESSION['dept_id'];

// Get active view tab (default: pass_percentage)
$current_view = $_GET['view'] ?? $_POST['view'] ?? 'pass_percentage';
$selected_notification_id = !empty($_REQUEST['notification_id']) ? (int)$_REQUEST['notification_id'] : 0;
$selected_class_id = !empty($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : 0;
$search_htno = !empty($_REQUEST['search_htno']) ? strtoupper(trim((string)$_REQUEST['search_htno'])) : '';

// CSV Export Handler (must execute before any HTML output)
if (!empty($_POST['action']) && $_POST['action'] === 'export_csv' && $selected_notification_id > 0) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dept_results_notif_' . $selected_notification_id . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['HTNO', 'Student Name', 'Class', 'Subject Code', 'Subject Name', 'Internal', 'External', 'Total', 'Grade', 'Grade Points', 'Result Status']);

    $exportSql = "SELECT er.htno, u.name as student_name, c.classname, er.subject_code, er.subject_name, 
                         er.internal_marks, er.external_marks, er.total_marks, er.grade_letter, er.grade_points, er.result_status
                  FROM exam_results er
                  JOIN students s ON (s.id = er.student_id OR s.username = er.htno)
                  JOIN users u ON u.username = s.username
                  JOIN classes c ON c.id = s.class_id
                  JOIN specialization sp ON sp.id = c.spec_id
                  WHERE sp.dept_id = ? AND er.notification_id = ?";
    if ($selected_class_id > 0) {
        $exportSql .= " AND c.id = " . (int)$selected_class_id;
    }
    $exportSql .= " ORDER BY c.classname ASC, er.htno ASC, er.subject_code ASC";
    $expStmt = $db->prepare($exportSql);
    $expStmt->bind_param("ii", $dept_id, $selected_notification_id);
    $expStmt->execute();
    $expRes = $expStmt->get_result();
    while ($row = $expRes->fetch_assoc()) {
        fputcsv($output, [
            $row['htno'],
            $row['student_name'],
            $row['classname'],
            $row['subject_code'],
            $row['subject_name'],
            $row['internal_marks'],
            $row['external_marks'],
            $row['total_marks'],
            $row['grade_letter'],
            $row['grade_points'],
            $row['result_status']
        ]);
    }
    $expStmt->close();
    fclose($output);
    exit();
}

$page_title = "Department Results";
require_once("hodheader.php");
require_once("hod.class.php");
$hodObj = new HOD();

// 1. Fetch published notifications applicable to this department's students
$notifQuery = "SELECT DISTINCT en.id, en.notification_code, en.title, en.academic_year, en.yearsem, en.month_year, en.exam_type, en.release_date
               FROM exam_notifications en
               JOIN exam_results er ON er.notification_id = en.id
               JOIN students s ON (s.id = er.student_id OR s.username = er.htno)
               JOIN classes c ON c.id = s.class_id
               JOIN specialization sp ON sp.id = c.spec_id
               WHERE sp.dept_id = ? AND en.is_published = 1 AND en.workflow_status = 'PUBLISHED'
               ORDER BY en.id DESC";

$notifStmt = $db->prepare($notifQuery);
$notifStmt->bind_param("i", $dept_id);
$notifStmt->execute();
$notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notifStmt->close();

if ($selected_notification_id === 0 && !empty($notifications)) {
    $selected_notification_id = (int)$notifications[0]['id'];
}

// 2. Fetch classes in this department for filtering
$classesQuery = "SELECT c.id, c.classname, c.acad_year, sp.spec_fullname, sp.spec_shortname
                 FROM classes c
                 JOIN specialization sp ON sp.id = c.spec_id
                 WHERE sp.dept_id = ?
                 ORDER BY sp.id ASC, c.classname ASC";
$classStmt = $db->prepare($classesQuery);
$classStmt->bind_param("i", $dept_id);
$classStmt->execute();
$deptClasses = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$classStmt->close();
?>

<div class="container my-4">
    <!-- Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="bi bi-award text-primary me-2"></i>Department Examination Results & Analytics
                    </h4>
                    <p class="text-muted mb-0 small">
                        Read-only institutional view of published semester grade sheets, course-wise pass percentages, and active backlogs for students of your department.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();">
                        <i class="bi bi-printer me-1"></i> Print Roster
                    </button>
                    <?php if ($selected_notification_id > 0): ?>
                    <form method="post" action="hodresults.php" class="m-0">
                        <input type="hidden" name="action" value="export_csv">
                        <input type="hidden" name="notification_id" value="<?= $selected_notification_id ?>">
                        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                        <button type="submit" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-excel me-1"></i> Export to CSV
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Strip -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body py-3">
            <form method="get" action="hodresults.php" class="row g-2 align-items-end">
                <input type="hidden" name="view" value="<?= htmlspecialchars($current_view) ?>">

                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">Published Examination Notification:</label>
                    <select name="notification_id" class="form-select form-select-sm" onchange="this.form.submit();">
                        <?php if (empty($notifications)): ?>
                            <option value="">No published results available for your department</option>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <option value="<?= (int)$notif['id'] ?>" <?= ($selected_notification_id === (int)$notif['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($notif['title']) ?> (<?= htmlspecialchars($notif['month_year']) ?> - <?= htmlspecialchars($notif['exam_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Class Cohort (Optional):</label>
                    <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit();">
                        <option value="0">-- All Department Classes --</option>
                        <?php foreach ($deptClasses as $cls): ?>
                            <option value="<?= (int)$cls['id'] ?>" <?= ($selected_class_id === (int)$cls['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['classname']) ?> (<?= htmlspecialchars($cls['acad_year']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Filter by Roll Number:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search_htno" class="form-control" placeholder="e.g. 21001A0501" value="<?= htmlspecialchars($search_htno) ?>">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($current_view === 'pass_percentage') ? 'active fw-bold' : '' ?>" 
               href="hodresults.php?view=pass_percentage&notification_id=<?= $selected_notification_id ?>&class_id=<?= $selected_class_id ?>">
                <i class="bi bi-pie-chart me-1"></i> Subject Pass Percentages
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_view === 'grade_cards') ? 'active fw-bold' : '' ?>" 
               href="hodresults.php?view=grade_cards&notification_id=<?= $selected_notification_id ?>&class_id=<?= $selected_class_id ?>&search_htno=<?= urlencode($search_htno) ?>">
                <i class="bi bi-card-checklist me-1"></i> Student Grade Sheets
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_view === 'backlogs') ? 'active fw-bold text-danger' : 'text-danger' ?>" 
               href="hodresults.php?view=backlogs&notification_id=<?= $selected_notification_id ?>&class_id=<?= $selected_class_id ?>">
                <i class="bi bi-exclamation-triangle me-1"></i> Department Backlogs Roster
            </a>
        </li>
    </ul>

    <?php if ($selected_notification_id <= 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>No published examination results found for your department yet. Results will appear here automatically once published by the Academic Section.
        </div>
    <?php else: ?>

        <?php if ($current_view === 'pass_percentage'): ?>
            <!-- TAB 1: PASS PERCENTAGES -->
            <?php
            $statsSql = "SELECT er.subject_code, er.subject_name, c.classname,
                                COUNT(*) as total_appeared,
                                SUM(CASE WHEN er.result_status = 'PASS' THEN 1 ELSE 0 END) as total_passed,
                                SUM(CASE WHEN er.result_status = 'FAIL' THEN 1 ELSE 0 END) as total_failed,
                                SUM(CASE WHEN er.result_status = 'ABSENT' THEN 1 ELSE 0 END) as total_absent,
                                ROUND((SUM(CASE WHEN er.result_status = 'PASS' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as pass_percentage
                         FROM exam_results er
                         JOIN students s ON (s.id = er.student_id OR s.username = er.htno)
                         JOIN classes c ON c.id = s.class_id
                         JOIN specialization sp ON sp.id = c.spec_id
                         WHERE sp.dept_id = ? AND er.notification_id = ?";
            if ($selected_class_id > 0) {
                $statsSql .= " AND c.id = " . (int)$selected_class_id;
            }
            $statsSql .= " GROUP BY er.subject_code, er.subject_name, c.classname
                           ORDER BY c.classname ASC, er.subject_code ASC";

            $statStmt = $db->prepare($statsSql);
            $statStmt->bind_param("ii", $dept_id, $selected_notification_id);
            $statStmt->execute();
            $subStats = $statStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $statStmt->close();

            $totalAppearedSum = 0;
            $totalPassedSum = 0;
            $totalFailedSum = 0;
            foreach ($subStats as $st) {
                $totalAppearedSum += $st['total_appeared'];
                $totalPassedSum += $st['total_passed'];
                $totalFailedSum += $st['total_failed'];
            }
            $overallDeptPassPct = ($totalAppearedSum > 0) ? round(($totalPassedSum / $totalAppearedSum) * 100, 1) : 0;
            ?>

            <!-- KPI Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-primary text-white p-3">
                        <span class="small text-uppercase opacity-75">Subject Entries</span>
                        <h3 class="fw-bold mb-0"><?= count($subStats) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-info text-white p-3">
                        <span class="small text-uppercase opacity-75">Total Course Registrations</span>
                        <h3 class="fw-bold mb-0"><?= $totalAppearedSum ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-success text-white p-3">
                        <span class="small text-uppercase opacity-75">Passed Units</span>
                        <h3 class="fw-bold mb-0"><?= $totalPassedSum ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-warning text-dark p-3">
                        <span class="small text-uppercase opacity-75">Overall Pass Rate</span>
                        <h3 class="fw-bold mb-0"><?= $overallDeptPassPct ?>%</h3>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold py-3">
                    <i class="bi bi-table me-2"></i>Course-Wise Pass Percentages
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>S.No</th>
                                    <th>Subject Code</th>
                                    <th>Subject Full Name</th>
                                    <th>Class Section</th>
                                    <th class="text-center">Appeared</th>
                                    <th class="text-center">Passed</th>
                                    <th class="text-center">Failed</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-center">Pass %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($subStats)): ?>
                                    <tr><td colspan="9" class="text-center text-muted p-4">No examination results recorded for this selection.</td></tr>
                                <?php else: ?>
                                    <?php $idx = 1; foreach ($subStats as $row): 
                                        $pct = (float)$row['pass_percentage'];
                                        $badgeClass = ($pct >= 75) ? 'bg-success' : (($pct >= 50) ? 'bg-warning text-dark' : 'bg-danger');
                                    ?>
                                        <tr>
                                            <td><?= $idx++ ?></td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['subject_code']) ?></span></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['subject_name']) ?></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($row['classname']) ?></span></td>
                                            <td class="text-center"><?= (int)$row['total_appeared'] ?></td>
                                            <td class="text-center fw-bold text-success"><?= (int)$row['total_passed'] ?></td>
                                            <td class="text-center fw-bold text-danger"><?= (int)$row['total_failed'] ?></td>
                                            <td class="text-center text-muted"><?= (int)$row['total_absent'] ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $badgeClass ?> px-3 py-2 fs-6"><?= $pct ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($current_view === 'grade_cards'): ?>
            <!-- TAB 2: GRADE SHEETS -->
            <?php
            $gradeSql = "SELECT er.htno, u.name as student_name, c.classname, er.subject_code, er.subject_name,
                                er.internal_marks, er.external_marks, er.total_marks, er.grade_letter, er.grade_points,
                                er.credits_registered, er.credits_earned, er.result_status
                         FROM exam_results er
                         JOIN students s ON (s.id = er.student_id OR s.username = er.htno)
                         JOIN users u ON u.username = s.username
                         JOIN classes c ON c.id = s.class_id
                         JOIN specialization sp ON sp.id = c.spec_id
                         WHERE sp.dept_id = ? AND er.notification_id = ?";
            if ($selected_class_id > 0) {
                $gradeSql .= " AND c.id = " . (int)$selected_class_id;
            }
            if ($search_htno !== '') {
                $gradeSql .= " AND er.htno LIKE '%" . $db->real_escape_string($search_htno) . "%'";
            }
            $gradeSql .= " ORDER BY c.classname ASC, er.htno ASC, er.subject_code ASC LIMIT 500";

            $grdStmt = $db->prepare($gradeSql);
            $grdStmt->bind_param("ii", $dept_id, $selected_notification_id);
            $grdStmt->execute();
            $gradeRoster = $grdStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $grdStmt->close();
            ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold"><i class="bi bi-card-checklist me-2"></i>Student Examination Results Roster</span>
                    <span class="badge bg-secondary"><?= count($gradeRoster) ?> Records Displayed</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0 text-center">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>HTNO</th>
                                    <th class="text-start">Student Name</th>
                                    <th>Class</th>
                                    <th>Subject Code</th>
                                    <th class="text-start">Subject Name</th>
                                    <th>Internal</th>
                                    <th>External</th>
                                    <th>Total</th>
                                    <th>Grade</th>
                                    <th>Points</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($gradeRoster)): ?>
                                    <tr><td colspan="11" class="text-center text-muted p-4">No student records match the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($gradeRoster as $gr): 
                                        $resStatus = $gr['result_status'];
                                        $sBadge = match($resStatus) {
                                            'PASS' => 'bg-success',
                                            'FAIL' => 'bg-danger',
                                            'ABSENT' => 'bg-warning text-dark',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                        <tr>
                                            <td class="font-monospace fw-bold"><?= htmlspecialchars($gr['htno']) ?></td>
                                            <td class="text-start"><?= htmlspecialchars($gr['student_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($gr['classname']) ?></span></td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($gr['subject_code']) ?></span></td>
                                            <td class="text-start small"><?= htmlspecialchars($gr['subject_name']) ?></td>
                                            <td><?= $gr['internal_marks'] !== null ? number_format((float)$gr['internal_marks'], 1) : '—' ?></td>
                                            <td><?= $gr['external_marks'] !== null ? number_format((float)$gr['external_marks'], 1) : ($resStatus === 'ABSENT' ? 'AB' : '—') ?></td>
                                            <td class="fw-bold"><?= $gr['total_marks'] !== null ? number_format((float)$gr['total_marks'], 1) : '—' ?></td>
                                            <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($gr['grade_letter'] ?? '—') ?></span></td>
                                            <td><?= htmlspecialchars((string)($gr['grade_points'] ?? '—')) ?></td>
                                            <td><span class="badge <?= $sBadge ?>"><?= htmlspecialchars($resStatus) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($current_view === 'backlogs'): ?>
            <!-- TAB 3: DEPARTMENT BACKLOGS -->
            <?php
            $backlogSql = "SELECT er.htno, u.name as student_name, u.mobile as student_mobile, c.classname, 
                                  er.subject_code, er.subject_name, er.internal_marks, er.external_marks, 
                                  er.total_marks, er.grade_letter, er.result_status
                           FROM exam_results er
                           JOIN students s ON (s.id = er.student_id OR s.username = er.htno)
                           JOIN users u ON u.username = s.username
                           JOIN classes c ON c.id = s.class_id
                           JOIN specialization sp ON sp.id = c.spec_id
                           WHERE sp.dept_id = ? AND er.notification_id = ?
                             AND er.result_status IN ('FAIL', 'ABSENT', 'MALPRACTICE')";
            if ($selected_class_id > 0) {
                $backlogSql .= " AND c.id = " . (int)$selected_class_id;
            }
            $backlogSql .= " ORDER BY c.classname ASC, er.htno ASC, er.subject_code ASC";

            $bklStmt = $db->prepare($backlogSql);
            $bklStmt->bind_param("ii", $dept_id, $selected_notification_id);
            $bklStmt->execute();
            $backlogRoster = $bklStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $bklStmt->close();

            $uniqueBacklogStudents = count(array_unique(array_column($backlogRoster, 'htno')));
            ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-danger text-white p-3">
                        <span class="small text-uppercase opacity-75">Students with Backlogs in this Examination</span>
                        <h3 class="fw-bold mb-0"><?= $uniqueBacklogStudents ?> Students</h3>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-warning text-dark p-3">
                        <span class="small text-uppercase opacity-75">Total Arrear Subject Units</span>
                        <h3 class="fw-bold mb-0"><?= count($backlogRoster) ?> Papers</h3>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-4 border-danger">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Department Remedial & Backlog Candidate Roster</span>
                    <span class="badge bg-danger"><?= count($backlogRoster) ?> Arrears</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th>S.No</th>
                                    <th>HTNO</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th class="text-center">Int</th>
                                    <th class="text-center">Ext</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backlogRoster)): ?>
                                    <tr><td colspan="11" class="text-center text-success p-4"><i class="bi bi-check-circle me-1"></i>No backlogs found for the selected examination! 100% pass achieved.</td></tr>
                                <?php else: ?>
                                    <?php $bIdx = 1; foreach ($backlogRoster as $bRow): ?>
                                        <tr>
                                            <td><?= $bIdx++ ?></td>
                                            <td class="font-monospace fw-bold text-danger"><?= htmlspecialchars($bRow['htno']) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($bRow['student_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($bRow['classname']) ?></span></td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($bRow['subject_code']) ?></span></td>
                                            <td class="small"><?= htmlspecialchars($bRow['subject_name']) ?></td>
                                            <td class="text-center text-muted"><?= $bRow['internal_marks'] !== null ? number_format((float)$bRow['internal_marks'], 1) : '—' ?></td>
                                            <td class="text-center text-danger fw-bold"><?= $bRow['external_marks'] !== null ? number_format((float)$bRow['external_marks'], 1) : ($bRow['result_status'] === 'ABSENT' ? 'AB' : '—') ?></td>
                                            <td class="text-center fw-bold"><?= $bRow['total_marks'] !== null ? number_format((float)$bRow['total_marks'], 1) : '—' ?></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= htmlspecialchars($bRow['grade_letter'] ?? 'F') ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= htmlspecialchars($bRow['result_status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php
require_once("hodfooter.php");
?>

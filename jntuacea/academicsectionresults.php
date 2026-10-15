<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: Academic Section & Examination Results Publication Management
 * 
 * Allows Academic Section / Admin to create exam notifications, upload
 * official examination result CSVs, and publish grade cards to students.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'academic_section'], true)) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_RESULTS_PUBLISH');
require_once __DIR__ . '/services/ExamResultsService.php';

$examService = \Services\ExamResultsService::getInstance();

if (($_GET['action'] ?? '') === 'export_cia_discrepancy') {
    $notifId = (int)($_GET['notif_id'] ?? 0);
    $subId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
    $onlyMismatches = !empty($_GET['mismatches_only']);
    $data = $examService->getNotificationCiaDiscrepancies($notifId, $subId, $onlyMismatches);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cia_reconciliation_notif_' . $notifId . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['HTNO', 'Student Name', 'Subject Code', 'Subject Name', 'Class', 'Regulation', 'Portal Calculated CIA', 'CSV Uploaded Internal', 'Difference', 'External Marks', 'Total Marks', 'Grade', 'Result Status', 'Reconciliation Status']);
    if (!empty($data['rows'])) {
        foreach ($data['rows'] as $row) {
            fputcsv($out, [
                $row['htno'],
                $row['student_name'],
                $row['subject_code'],
                $row['subject_name'],
                $row['classname'],
                $row['regulation'],
                $row['calculated_cia'] !== null ? $row['calculated_cia'] : 'N/A',
                $row['uploaded_internal'] !== null ? $row['uploaded_internal'] : 'N/A',
                $row['difference'] !== null ? $row['difference'] : 'N/A',
                $row['external_marks'] !== null ? $row['external_marks'] : 'N/A',
                $row['total_marks'] !== null ? $row['total_marks'] : 'N/A',
                $row['grade_letter'],
                $row['result_status'],
                $row['reconcile_status']
            ]);
        }
    }
    fclose($out);
    exit();
}

$page_title = "Exam Results Management";

require_once __DIR__ . '/header.php';

// Render appropriate role menu
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminmenu.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/adminmenu.php';
} else {
    require_once __DIR__ . '/academicsectionmenu.php';
}

require_once __DIR__ . '/programs.class.php';
require_once __DIR__ . '/regulations.class.php';
require_once __DIR__ . '/academicyears.class.php';

$programsObj = new \Programs();
$regulationsObj = new \Regulations();
$acadYearsObj = new \AcademicYears();

$programs = $programsObj->getAllPrograms()['data'] ?? [];
$regulations = $regulationsObj->getAllRegulations()['data'] ?? [];
$acadYears = $acadYearsObj->getActiveAcademicYears()['data'] ?? [];

$succMsg = '';
$errMsg = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_RESULTS_PUBLISH');
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF mismatch).";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);

        if ($action === 'create_notification') {
            $res = $examService->createNotification($_POST, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        } elseif ($action === 'upload_results_csv') {
            $notifId = (int)($_POST['notification_id'] ?? 0);
            if ($notifId <= 0) {
                $errMsg = "Invalid notification selected.";
            } elseif (empty($_FILES['results_csv']['tmp_name'])) {
                $errMsg = "Please select a CSV file to upload.";
            } else {
                try {
                    $res=$examService->previewResultsCsv($notifId,$_FILES['results_csv']['tmp_name']);
                    if(!$res['status']){$errMsg=implode(' | ',array_slice($res['errors'],0,20));}
                    else {
                        if(!empty($_SESSION['results_preview']['path'])){@unlink($_SESSION['results_preview']['path']);}
                        $path=tempnam(sys_get_temp_dir(),'cea-results-');chmod($path,0600);
                        if(!copy($_FILES['results_csv']['tmp_name'],$path)){throw new RuntimeException('Unable to store results preview.');}
                        $_SESSION['results_preview']=['path'=>$path,'hash'=>$res['source_hash'],'notification'=>$notifId,'token'=>bin2hex(random_bytes(32)),'rows'=>array_slice($res['rows'],0,20),'count'=>count($res['rows']),'expires'=>time()+1800];
                        $succMsg='Preview validated. Inspect the records below before importing the draft.';
                    }
                }catch(Throwable $e){$errMsg=$e->getMessage();}
            }
        } elseif ($action === 'commit_results_preview') {
            $preview=$_SESSION['results_preview']??null;
            if(!$preview||$preview['expires']<time()||!hash_equals($preview['token'],$_POST['preview_token']??'')||!is_file($preview['path'])||!hash_equals($preview['hash'],hash_file('sha256',$preview['path']))){$errMsg='The preview expired or changed. Upload and validate the file again.';}
            else{
                $res=$examService->importResultsCsv((int)$preview['notification'],$preview['path'],$userId);
                if($res['status']){$succMsg=$res['msg'];unlink($preview['path']);unset($_SESSION['results_preview']);}
                else{$errMsg=$res['err'].' '.implode(' | ',array_slice($res['errors']??[],0,10));}
            }
        } elseif ($action === 'review_results') {
            $res=$examService->reviewResults((int)($_POST['notification_id']??0),$userId,trim($_POST['review_remarks']??''));
            if($res['status']){$succMsg=$res['msg'];}else{$errMsg=$res['err'];}
        } elseif ($action === 'toggle_publish') {
            $notifId = (int)($_POST['notification_id'] ?? 0);
            $newStatus = (int)($_POST['new_status'] ?? 0);
            $res = $examService->togglePublishStatus($notifId, $newStatus, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        } elseif ($action === 'save_promotion_override') {
            $classId = (int)($_POST['class_id'] ?? 0);
            $rollNo = trim($_POST['roll_no'] ?? '');
            $reason = trim($_POST['override_reason'] ?? 'Academic Committee Resolution');
            if ($classId > 0 && !empty($rollNo)) {
                $ok = $examService->savePromotionCommitteeOverride($classId, $rollNo, $reason, $userId, $role);
                if ($ok) {
                    $succMsg = "Promotion Committee override recorded for student $rollNo.";
                } else {
                    $errMsg = "Failed to record committee override.";
                }
            }
            $activeTab = 'simulation';
        }
    }
}

// Generate CSRF token
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$notifications = $examService->getNotifications();

$isCreditPromoEnabled = \Services\FeatureManager::isModuleEnabled('MOD_CREDIT_PROMOTION');
$activeTab = $_GET['tab'] ?? ($activeTab ?? 'notifications');
$simClasses = $isCreditPromoEnabled ? $examService->getClassesForSimulation() : [];
$simClassId = isset($_GET['sim_class_id']) ? (int)$_GET['sim_class_id'] : ($simClasses[0]['id'] ?? 0);
$simResult = ($isCreditPromoEnabled && $simClassId > 0) ? $examService->evaluateCreditPromotionSimulation($simClassId) : null;

$reconNotifId = isset($_GET['notif_id']) ? (int)$_GET['notif_id'] : ($notifications[0]['id'] ?? 0);
$reconSubId = !empty($_GET['recon_sub_id']) ? (int)$_GET['recon_sub_id'] : null;
$reconMismatchesOnly = !empty($_GET['mismatches_only']);

$reconResult = null;
$reconSubjects = [];
if ($activeTab === 'reconciliation' && $reconNotifId > 0) {
    $allReconData = $examService->getNotificationCiaDiscrepancies($reconNotifId, null, false);
    if (!empty($allReconData['rows'])) {
        $subjMap = [];
        foreach ($allReconData['rows'] as $r) {
            if ($r['subject_id'] > 0 && !isset($subjMap[$r['subject_id']])) {
                $subjMap[$r['subject_id']] = [
                    'id'   => $r['subject_id'],
                    'code' => $r['subject_code'],
                    'name' => $r['subject_name']
                ];
            }
        }
        $reconSubjects = array_values($subjMap);
    }

    if ($reconSubId !== null || $reconMismatchesOnly) {
        $reconResult = $examService->getNotificationCiaDiscrepancies($reconNotifId, $reconSubId, $reconMismatchesOnly);
    } else {
        $reconResult = $allReconData;
    }
}
?>

<div class="container my-4">
    <!-- Header Card -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary fw-bold">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Semester Exam Results Publication
                    </h4>
                    <p class="text-muted mb-0 small">
                        Upload official examination results CSVs, publish grade cards to students, and auto-feed SEE marks into OBE attainment.
                    </p>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNotifModal">
                        <i class="bi bi-plus-circle me-1"></i> New Exam Notification
                    </button>
                    <a href="data:text/csv;charset=utf-8,HTNO,SubCode,SubName,Internals,Externals,Total,Grade,GradePoints,Credits,Status%0A21021A0501,20A05301T,Operating Systems,28,45,73,A,8,3,PASS" 
                       download="sample_exam_results_template.csv" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Sample CSV Template
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($succMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($succMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $errMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4 shadow-sm bg-white p-2 rounded border" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= ($activeTab === 'notifications') ? 'active' : ''; ?>" 
               href="?tab=notifications">
                <i class="bi bi-megaphone-fill me-1"></i> 1. Examination Results & Ingestion
            </a>
        </li>
        <?php if ($isCreditPromoEnabled): ?>
        <li class="nav-item">
            <a class="nav-link <?= ($activeTab === 'simulation') ? 'active' : ''; ?>" 
               href="?tab=simulation&sim_class_id=<?= (int)$simClassId; ?>">
                <i class="bi bi-shield-check me-1 text-warning"></i> 2. Credit Promotion Simulation (R23 Sec 18)
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?= ($activeTab === 'reconciliation') ? 'active' : ''; ?>" 
               href="?tab=reconciliation<?= $reconNotifId > 0 ? '&notif_id=' . (int)$reconNotifId : ''; ?>">
                <i class="bi bi-arrow-left-right me-1 text-info"></i> <?= $isCreditPromoEnabled ? '3.' : '2.'; ?> CIA vs Exam Internal Reconciliation & Discrepancies
            </a>
        </li>
    </ul>

    <?php if ($activeTab === 'notifications'): ?>
        <!-- Notifications Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-megaphone-fill me-2"></i> Examination Notifications</h6>
                <span class="badge bg-secondary"><?= count($notifications); ?> Notifications</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Notification Code & Title</th>
                            <th>Program / Reg</th>
                            <th>Year-Sem</th>
                            <th>Month-Year</th>
                            <th>Results Uploaded</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No examination notifications created yet. Click "New Exam Notification" to get started.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $idx => $n): ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?= $idx + 1; ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($n['notification_code']); ?></div>
                                        <div class="small text-secondary"><?= htmlspecialchars($n['title']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($n['prog_shortname']); ?></span>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($n['regulation']); ?></span>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($n['yearsem']); ?></span></td>
                                    <td><?= htmlspecialchars($n['month_year']); ?></td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= (int)$n['results_count']; ?> records</span>
                                        <span class="text-muted small">(<?= (int)$n['students_count']; ?> students)</span>
                                    </td>
                                    <td>
                                        <?php if ($n['is_published']): ?>
                                            <span class="badge bg-success"><i class="bi bi-eye me-1"></i> Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i> <?= htmlspecialchars($n['workflow_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ((int)$n['results_count'] > 0): ?>
                                            <a href="?tab=reconciliation&notif_id=<?= (int)$n['id']; ?>" 
                                               class="btn btn-sm btn-outline-info me-1" 
                                               title="Audit CIA vs Uploaded Internal Marks">
                                                <i class="bi bi-arrow-left-right"></i> Audit CIA
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary upload-csv-btn me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#uploadCsvModal"
                                                data-id="<?= (int)$n['id']; ?>"
                                                data-title="<?= htmlspecialchars($n['title']); ?>"
                                                title="Upload Results CSV">
                                            <i class="bi bi-upload"></i> Upload CSV
                                        </button>

                                        <?php if(!$n['is_published'] && $n['workflow_status']==='DRAFT'): ?>
                                        <form method="post" class="d-inline"><input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']) ?>"><input type="hidden" name="action" value="review_results"><input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>"><input name="review_remarks" placeholder="Verification observations" required><button class="btn btn-sm btn-outline-primary">Mark reviewed</button></form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                                            <input type="hidden" name="action" value="toggle_publish">
                                            <input type="hidden" name="notification_id" value="<?= (int)$n['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?= $n['is_published'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm <?= $n['is_published'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                    title="<?= $n['is_published'] ? 'Unpublish from student view' : 'Publish to students'; ?>">
                                                <?= $n['is_published'] ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($isCreditPromoEnabled && $activeTab === 'simulation'): ?>
        <!-- Simulation Class Selector Card -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="simulation">
                    <div class="col-md-8">
                        <label class="form-label mb-1 fw-bold text-dark small"><i class="bi bi-mortarboard me-1"></i> Target Class for Promotion Simulation:</label>
                        <select name="sim_class_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($simClasses as $sc): ?>
                                <option value="<?= (int)$sc['id']; ?>" <?= ($simClassId === (int)$sc['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sc['classname'] . ' (' . $sc['yearsem'] . ' | ' . $sc['acad_year'] . ') — ' . $sc['student_count'] . ' Students'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mt-auto">
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            <i class="bi bi-play-circle me-1"></i> Run Advisory Simulation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Advisory Regulatory Banner -->
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start mb-4 p-3">
            <i class="bi bi-shield-exclamation text-warning fs-3 me-3 mt-1"></i>
            <div>
                <h6 class="fw-bold mb-1">Advisory Dry-Run Simulation Tool (Autonomous R23 Section 18 & NBA Criterion 4.1)</h6>
                <p class="mb-0 small text-dark">
                    This simulation evaluates credit accumulation thresholds and attendance margins for academic progression. 
                    <strong>Guardrail Guarantee:</strong> This tool operates in dry-run mode and <em>never blocks</em> CSV result imports, student grade cards, or result publications in <code>ExamResultsService</code>.
                </p>
            </div>
        </div>

        <?php if ($simResult && !empty($simResult['class'])): 
            $sum = $simResult['summary'] ?? [];
        ?>
            <!-- Simulation Parameters & KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Target Transition</div>
                        <div class="fs-5 fw-bold text-primary mt-1"><?= htmlspecialchars($simResult['transition']); ?></div>
                        <div class="small text-muted mt-auto font-monospace"><?= htmlspecialchars($simResult['class']['yearsem']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Regulatory Credit Threshold</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><?= (float)$simResult['credit_threshold_pct']; ?>% <span class="fs-6 text-muted">credits</span></div>
                        <div class="small text-muted mt-auto"><?= htmlspecialchars($simResult['rule_description']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Eligible Candidates</div>
                        <div class="fs-4 fw-bold text-success mt-1"><?= (int)($sum['promoted_count'] ?? 0); ?> / <?= (int)($sum['total_candidates'] ?? 0); ?></div>
                        <div class="small text-success mt-auto"><i class="bi bi-check-circle me-1"></i> Satisfies credit & attendance rules</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">At-Risk & Cleared</div>
                        <div class="fs-4 fw-bold text-danger mt-1">
                            <?= (int)($sum['at_risk_count'] ?? 0); ?> Risk
                            <?php if (!empty($sum['committee_cleared_count'])): ?>
                                <span class="fs-6 text-warning">(<?= (int)$sum['committee_cleared_count']; ?> Cleared)</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-auto">Advisory committee review enabled</div>
                    </div>
                </div>
            </div>

            <!-- Candidates Simulation Table -->
            <div class="card shadow-sm border-0 mb-4 bg-white">
                <div class="card-header bg-light py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill me-2"></i> Student Promotion Eligibility Register</h6>
                    <span class="badge bg-primary"><?= count($simResult['students']); ?> Students</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Credits Earned / Reg</th>
                                <th>Credit %</th>
                                <th>Attendance %</th>
                                <th>Progression Status</th>
                                <th>Advisory Findings</th>
                                <th>Committee Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($simResult['students'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No students registered in this class.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($simResult['students'] as $stu): ?>
                                    <tr>
                                        <td class="text-center font-monospace fw-bold"><?= htmlspecialchars($stu['roll_no']); ?></td>
                                        <td><?= htmlspecialchars($stu['name']); ?></td>
                                        <td class="text-center">
                                            <?= (float)$stu['credits_earned']; ?> / <?= (float)$stu['credits_registered']; ?>
                                        </td>
                                        <td class="text-center fw-bold <?= $stu['credit_qualified'] ? 'text-success' : 'text-danger'; ?>">
                                            <?= (float)$stu['credit_pct']; ?>%
                                        </td>
                                        <td class="text-center fw-bold <?= $stu['attendance_qualified'] ? 'text-success' : 'text-danger'; ?>">
                                            <?= (float)$stu['attendance_pct']; ?>%
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stu['status'] === 'PROMOTED'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-check-circle me-1"></i> PROMOTED
                                                </span>
                                            <?php elseif ($stu['status'] === 'PROMOTED WITH CONDONATION'): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                                    <i class="bi bi-file-medical me-1"></i> CONDONATION
                                                </span>
                                            <?php elseif ($stu['status'] === 'CLEARED BY COMMITTEE'): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                    <i class="bi bi-award me-1"></i> COMMITTEE CLEARED
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="bi bi-exclamation-octagon me-1"></i> AT RISK
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($stu['flag_reason'] ?: 'Eligible as per R23 Section 18'); ?>
                                            <?php if ($stu['has_override']): ?>
                                                <div class="text-success fw-semibold"><i class="bi bi-shield-check"></i> Resolution: <?= htmlspecialchars($stu['override_reason']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stu['status'] === 'AT RISK' && !$stu['has_override']): ?>
                                                <button class="btn btn-sm btn-outline-warning override-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#overrideModal"
                                                        data-rollno="<?= htmlspecialchars($stu['roll_no']); ?>"
                                                        data-name="<?= htmlspecialchars($stu['name']); ?>"
                                                        data-classid="<?= (int)$simClassId; ?>">
                                                    <i class="bi bi-shield-plus me-1"></i> Clear by Committee
                                                </button>
                                            <?php elseif ($stu['has_override']): ?>
                                                <span class="badge bg-light text-success border"><i class="bi bi-check2"></i> Cleared</span>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif ($activeTab === 'reconciliation'): ?>
        <!-- Reconciliation Filter Card -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="reconciliation">
                    <div class="col-md-5">
                        <label class="form-label mb-1 fw-bold text-dark small"><i class="bi bi-megaphone me-1"></i> Exam Notification:</label>
                        <select name="notif_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($notifications as $n): ?>
                                <option value="<?= (int)$n['id']; ?>" <?= ($reconNotifId === (int)$n['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($n['notification_code'] . ' - ' . $n['title'] . ' (' . (int)$n['results_count'] . ' records)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold text-dark small"><i class="bi bi-book me-1"></i> Subject Filter:</label>
                        <select name="recon_sub_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Subjects (Notification Wide)</option>
                            <?php foreach ($reconSubjects as $s): ?>
                                <option value="<?= (int)$s['id']; ?>" <?= ($reconSubId === (int)$s['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($s['code'] . ' - ' . $s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-3 pt-1">
                            <input class="form-check-input" type="checkbox" name="mismatches_only" value="1" id="mismatchesOnly" <?= $reconMismatchesOnly ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="form-check-label small fw-semibold text-dark" for="mismatchesOnly">
                                Variances Only
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 mt-auto d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <?php if ($reconNotifId > 0 && !empty($reconResult['rows'])): ?>
                            <a href="?action=export_cia_discrepancy&notif_id=<?= (int)$reconNotifId ?>&subject_id=<?= (int)($reconSubId ?? 0) ?>&mismatches_only=<?= $reconMismatchesOnly ? '1' : '0' ?>" 
                               class="btn btn-sm btn-outline-success" title="Download Reconciliation Audit CSV">
                                <i class="bi bi-download me-1"></i> CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informative Reconciliation Banner -->
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-start mb-4 p-3">
            <i class="bi bi-info-circle-fill text-info fs-3 me-3 mt-1"></i>
            <div>
                <h6 class="fw-bold mb-1">Continuous Internal Assessment (CIA) vs Uploaded Results Reconciliation Audit</h6>
                <p class="mb-0 small text-dark">
                    Audits continuous internal evaluation marks calculated live from faculty course portals (Mid examinations, Quizzes, Assignments, Day-to-Day Lab evaluations according to autonomous regulations) against the final internal marks (<code>im</code>) uploaded in the official examination result dump.
                    <strong>Tolerance Rules:</strong> Variances &le; 1.0 marks are flagged as minor rounding variances; discrepancies &gt; 1.0 mark indicate significant divergence requiring Controller of Examinations verification.
                </p>
            </div>
        </div>

        <?php if ($reconResult): 
            $sum = $reconResult['summary'] ?? [];
        ?>
            <!-- Reconciliation KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Exam Records Audited</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><?= (int)($sum['total_records'] ?? 0); ?></div>
                        <div class="small text-muted mt-auto"><?= (int)($sum['displayed_records'] ?? 0); ?> matching current filter</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Verified Matches</div>
                        <div class="fs-4 fw-bold text-success mt-1"><?= (int)($sum['matches'] ?? 0); ?></div>
                        <div class="small text-success mt-auto"><i class="bi bi-check-circle me-1"></i> Portal CIA equals Uploaded</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Rounding Variances (&le; 1.0)</div>
                        <div class="fs-4 fw-bold text-warning mt-1"><?= (int)($sum['rounding_variances'] ?? 0); ?></div>
                        <div class="small text-muted mt-auto">Acceptable Mid 80:20 rounding</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                        <div class="text-muted small fw-semibold">Discrepancies (&gt; 1.0)</div>
                        <div class="fs-4 fw-bold text-danger mt-1">
                            <?= (int)($sum['discrepancies'] ?? 0); ?>
                        </div>
                        <div class="small text-muted mt-auto">
                            <?php if (!empty($sum['missing_portal_cia'])): ?>
                                <span class="text-secondary"><?= (int)$sum['missing_portal_cia']; ?> missing portal CIA</span>
                            <?php else: ?>
                                Requires verification
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Register Table -->
            <div class="card shadow-sm border-0 mb-4 bg-white">
                <div class="card-header bg-light py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2"></i> Internal Marks Audit Register</h6>
                    <span class="badge bg-secondary"><?= count($reconResult['rows']); ?> Records</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Hall Ticket & Student</th>
                                <th>Subject</th>
                                <th>Class & Reg</th>
                                <th class="text-center">Portal CIA</th>
                                <th class="text-center">CSV Internal (im)</th>
                                <th class="text-center">Variance (&Delta;)</th>
                                <th class="text-center">External (em)</th>
                                <th class="text-center">Grade</th>
                                <th class="text-end pe-3">Audit Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reconResult['rows'])): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        No discrepancy records found for the selected criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reconResult['rows'] as $idx => $r): ?>
                                    <tr class="<?= ($r['reconcile_status'] === 'DISCREPANCY') ? 'table-danger-subtle' : ''; ?>">
                                        <td class="ps-3 text-muted"><?= $idx + 1; ?></td>
                                        <td>
                                            <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($r['htno']); ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($r['student_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary"><?= htmlspecialchars($r['subject_code']); ?></div>
                                            <div class="small text-secondary"><?= htmlspecialchars($r['subject_name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['classname'] ?: '—'); ?></span>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($r['regulation'] ?: '—'); ?></span>
                                        </td>
                                        <td class="text-center font-monospace fw-bold">
                                            <?= $r['calculated_cia'] !== null ? number_format((float)$r['calculated_cia'], 2) : '<span class="text-muted small">N/A</span>'; ?>
                                        </td>
                                        <td class="text-center font-monospace fw-bold">
                                            <?= $r['uploaded_internal'] !== null ? number_format((float)$r['uploaded_internal'], 2) : '<span class="badge bg-light text-dark border">Exempt</span>'; ?>
                                        </td>
                                        <td class="text-center font-monospace">
                                            <?php if ($r['difference'] !== null): ?>
                                                <?php if (abs($r['difference']) < 0.001): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success">0.00</span>
                                                <?php elseif (abs($r['difference']) <= 1.0): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning">
                                                        <?= ($r['difference'] > 0 ? '+' : '') . number_format((float)$r['difference'], 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger fw-bold">
                                                        <?= ($r['difference'] > 0 ? '+' : '') . number_format((float)$r['difference'], 2); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace">
                                            <?= $r['external_marks'] !== null ? (float)$r['external_marks'] : '<span class="text-muted">&mdash;</span>'; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($r['grade_letter'] ?: '—'); ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <?php if ($r['reconcile_status'] === 'MATCH'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Verified</span>
                                            <?php elseif ($r['reconcile_status'] === 'VARIANCE'): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i> Rounding</span>
                                            <?php elseif ($r['reconcile_status'] === 'DISCREPANCY'): ?>
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancy</span>
                                            <?php elseif ($r['reconcile_status'] === 'MISSING_PORTAL_CIA'): ?>
                                                <span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i> No Portal CIA</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border">Exempt (-7)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: Committee Resolution Override -->
<div class="modal fade" id="overrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="save_promotion_override">
            <input type="hidden" name="class_id" id="overrideClassId" value="<?= (int)$simClassId; ?>">
            <input type="hidden" name="roll_no" id="overrideRollNo">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus me-2"></i> Clear Candidate by Committee Resolution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border mb-3">
                    <div class="small fw-bold text-dark">Student Candidate:</div>
                    <div id="overrideStudentInfo" class="text-primary fw-semibold"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Committee Resolution & Justification <span class="text-danger">*</span></label>
                    <textarea name="override_reason" rows="3" class="form-control" placeholder="e.g. Cleared by Dean & DAAC Committee Resolution subject to pending revaluation results or university relaxation..." required></textarea>
                    <div class="form-text small">This administrative override will be permanently logged to institutional <code>audit_logs</code>.</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning fw-bold"><i class="bi bi-save me-1"></i> Record Committee Override</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create Notification -->
<div class="modal fade" id="createNotifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="create_notification">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> New Exam Notification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notification Code <span class="text-danger">*</span></label>
                    <input type="text" name="notification_code" class="form-control" placeholder="e.g. BTECH_R20_3_1_NOV2025" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notification Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. B.Tech III Year I Sem (R20) Regular & Supple Exams - Nov 2025" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Degree Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= (int)$p['id']; ?>"><?= htmlspecialchars($p['prog_shortname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Regulation <span class="text-danger">*</span></label>
                        <select name="regulation_id" class="form-select" required>
                            <?php foreach ($regulations as $r): ?>
                                <option value="<?= (int)$r['id']; ?>"><?= htmlspecialchars($r['regulation']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year" class="form-select" required>
                            <?php foreach ($acadYears as $ay): ?>
                                <option value="<?= htmlspecialchars($ay['acad_year']); ?>"><?= htmlspecialchars($ay['acad_year']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Year-Semester <span class="text-danger">*</span></label>
                        <input type="text" name="yearsem" class="form-control" placeholder="e.g. III Yr - I Sem" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Month & Year <span class="text-danger">*</span></label>
                        <input type="text" name="month_year" class="form-control" placeholder="e.g. November 2025" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Release Date</label>
                        <input type="date" name="release_date" class="form-control" value="<?= date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_published" value="1" id="publishCheck" checked>
                        <label class="form-check-label fw-semibold" for="publishCheck">Publish to student view immediately</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Save Notification</button>
            </div>
        </form>
    </div>
</div>

<?php $preview=$_SESSION['results_preview']??null;if($preview && $preview['expires']>=time()): ?>
<div class="container my-4 card p-3"><h4>Validated draft preview</h4><p><?= (int)$preview['count'] ?> records validated. Showing up to 20 records. Importing saves an unpublished draft.</p><div class="table-responsive"><table class="table"><tr><th>Hall ticket</th><th>Subject</th><th>Internal</th><th>External</th><th>Total</th><th>Grade</th><th>Credits</th><th>Status</th></tr><?php foreach($preview['rows'] as $r):?><tr><?php foreach(['htno','subject_code','internal_marks','external_marks','total_marks','grade_letter','credits_registered','result_status'] as $key):?><td><?= htmlspecialchars((string)$r[$key]) ?></td><?php endforeach;?></tr><?php endforeach;?></table></div><form method="post"><input type="hidden" name="action" value="commit_results_preview"><input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']) ?>"><input type="hidden" name="preview_token" value="<?= htmlspecialchars($preview['token']) ?>"><button class="btn btn-primary">Import validated draft</button></form></div>
<?php endif; ?>
<!-- Modal: Upload Results CSV -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="upload_results_csv">
            <input type="hidden" name="notification_id" id="uploadNotifId">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i> Upload Results CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border mb-3">
                    <div class="small fw-bold text-dark">Target Notification:</div>
                    <div id="uploadNotifTitle" class="text-primary fw-semibold small"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Results CSV File <span class="text-danger">*</span></label>
                    <input type="file" name="results_csv" accept=".csv" class="form-control" required>
                    <div class="form-text small">
                        Required columns: HTNO, SubCode, Internals, Externals, Total, Grade, GradePoints, Credits, Status. SubName is optional. The notification must match the original course offering and every course must have a curriculum mapping.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark fw-bold"><i class="bi bi-cloud-upload me-1"></i> Validate & Preview Results</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.upload-csv-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('uploadNotifId').value = this.dataset.id;
        document.getElementById('uploadNotifTitle').textContent = this.dataset.title;
    });
});

document.querySelectorAll('.override-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const rollNo = this.dataset.rollno;
        const name = this.dataset.name;
        const classId = this.dataset.classid;
        document.getElementById('overrideRollNo').value = rollNo;
        document.getElementById('overrideClassId').value = classId;
        document.getElementById('overrideStudentInfo').textContent = `${name} (${rollNo})`;
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

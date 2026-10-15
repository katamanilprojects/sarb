<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: Institutional Physical Custodial Ledger & Statutory Certificates Engine
 * 
 * Accessible by Superadmin, Principal (Admin), and Academic Section.
 * Manages physical original certificates deposited by students, temporary checkout logs,
 * permanent handovers, and statutory certificate applications (Custodial, Bonafide, Study & Conduct, TC).
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'academic_section', 'academicsection'], true)) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CERTIFICATES', $role);

$page_title = "Certificates Ledger";

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/services/StudentProfileService.php';

// Render appropriate role menu
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminmenu.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/adminmenu.php';
} else {
    require_once __DIR__ . '/academicsectionmenu.php';
}

$service = \Services\StudentProfileService::getInstance();
$userId = (int)($_SESSION['userid'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);

$msg = "";
$err = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    \FeatureManager::requireWriteAccess('MOD_CERTIFICATES');
    // 1. Add Physical Document to Custody
    if ($_POST['action'] === 'add_custody') {
        $cRoll = trim($_POST['roll_no'] ?? '');
        $cDocName = trim($_POST['document_name'] ?? '');
        $cSerial = trim($_POST['certificate_serial_no'] ?? '');
        $cDate = !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d');
        $cRemarks = trim($_POST['remarks'] ?? '');

        if (empty($cRoll) || empty($cDocName)) {
            $err = "Roll number and document name are required.";
        } else {
            $recId = $service->addCustodialRecord($cRoll, $cDocName, !empty($cSerial) ? $cSerial : null, $cDate, $userId, !empty($cRemarks) ? $cRemarks : null);
            if ($recId > 0) {
                $msg = "Original certificate '$cDocName' recorded in safe custody for student $cRoll.";
            } else {
                $err = "Failed to record custodial item.";
            }
        }
    }
    // 2. Temporary Return (Checkout)
    elseif ($_POST['action'] === 'temp_return') {
        $recId = (int)($_POST['record_id'] ?? 0);
        $purpose = trim($_POST['purpose'] ?? 'Student requested temporary withdrawal');
        $issDate = !empty($_POST['issued_date']) ? $_POST['issued_date'] : date('Y-m-d');
        $expDate = !empty($_POST['expected_return_date']) ? $_POST['expected_return_date'] : null;
        $remarks = trim($_POST['remarks'] ?? '');

        if ($service->temporarilyReturnCertificate($recId, $purpose, $issDate, $expDate, $userId, $remarks)) {
            $msg = "Certificate marked as temporarily checked out.";
        } else {
            $err = "Failed to update custody record.";
        }
    }
    // 3. Mark Returned Back to Custody
    elseif ($_POST['action'] === 'mark_returned') {
        $recId = (int)($_POST['record_id'] ?? 0);
        $retDate = !empty($_POST['actual_returned_date']) ? $_POST['actual_returned_date'] : date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? 'Returned by student');

        if ($service->markCertificateReturned($recId, $retDate, $userId, $remarks)) {
            $msg = "Certificate returned back to college safe custody.";
        } else {
            $err = "Failed to update custody status.";
        }
    }
    // 4. Permanent Return (Exit)
    elseif ($_POST['action'] === 'perm_return') {
        $recId = (int)($_POST['record_id'] ?? 0);
        $issDate = !empty($_POST['issued_date']) ? $_POST['issued_date'] : date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? 'Permanently handed over at graduation / exit');

        if ($service->permanentlyReturnCertificate($recId, $issDate, $userId, $remarks)) {
            $msg = "Certificate permanently returned to student.";
        } else {
            $err = "Failed to update custody status.";
        }
    }
    // 5. Update Certificate Request Status
    elseif ($_POST['action'] === 'update_cert_status') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($service->updateCertificateStatus($reqId, $status, $userId, !empty($remarks) ? $remarks : null)) {
            $msg = "Certificate request status updated to: $status";
        } else {
            $err = "Failed to update certificate request status.";
        }
    }
}

// Filters
$custodyFilter = trim($_GET['custody_status'] ?? '');
$searchTerm = trim($_GET['search'] ?? '');
$certTypeFilter = trim($_GET['cert_type'] ?? '');
$certStatusFilter = trim($_GET['cert_status'] ?? '');
$activeTab = trim($_GET['tab'] ?? 'custody');

$custodialRecords = $service->getAllCustodialRecords(
    !empty($custodyFilter) ? $custodyFilter : null,
    !empty($searchTerm) ? $searchTerm : null
);

$certRequests = $service->getAllCertificateRequests(
    !empty($certTypeFilter) ? $certTypeFilter : null,
    !empty($certStatusFilter) ? $certStatusFilter : null
);
?>

<div class="container my-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-journal-bookmark-fill me-2"></i>Physical Custodial Ledger & Statutory Certificates</h4>
            <small class="text-muted">Master Registry of Original Documents, Temporary Handover Logs & Official Certificates</small>
        </div>
        <div class="d-flex gap-2">
            <a href="adminstudentprofiles.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-badge me-1"></i> Student Profiles & Dossier
            </a>
            <a href="academicsectionresults.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exam Results
            </a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="custodyTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'custody' ? 'active fw-bold' : '' ?>" href="admincustodyledger.php?tab=custody">
                <i class="bi bi-safe me-1"></i> Physical Custody Ledger (<?= count($custodialRecords) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'requests' ? 'active fw-bold' : '' ?>" href="admincustodyledger.php?tab=requests">
                <i class="bi bi-award me-1"></i> Statutory Certificate Requests (<?= count($certRequests) ?>)
            </a>
        </li>
    </ul>

    <!-- TAB 1: Physical Custodial Ledger -->
    <?php if ($activeTab === 'custody'): ?>
        <div class="row g-4">
            <!-- Left: Add New Physical Record Form -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Receive Original Document</h6>
                    </div>
                    <div class="card-body">
                        <form action="admincustodyledger.php?tab=custody" method="POST">
                            <input type="hidden" name="action" value="add_custody">

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Student Roll No / Username</label>
                                <input type="text" name="roll_no" class="form-control" placeholder="e.g. 20001A0118" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Original Certificate Name</label>
                                <select name="document_name" class="form-select" required>
                                    <option value="">-- Select Certificate --</option>
                                    <option value="SSC / 10th Marks Memo (Original)">SSC / 10th Marks Memo (Original)</option>
                                    <option value="Intermediate / +2 Marks Memo (Original)">Intermediate / +2 Marks Memo (Original)</option>
                                    <option value="Diploma Certificate & Memos (Original)">Diploma Certificate & Memos (Original)</option>
                                    <option value="Transfer Certificate (TC Original)">Transfer Certificate (TC Original)</option>
                                    <option value="Study & Conduct Certificate (Original)">Study & Conduct Certificate (Original)</option>
                                    <option value="Integrated Community / Caste Certificate">Integrated Community / Caste Certificate</option>
                                    <option value="Income Certificate">Income Certificate</option>
                                    <option value="Migration Certificate (Original)">Migration Certificate (Original)</option>
                                    <option value="Equivalence Certificate">Equivalence Certificate</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Certificate Serial / Registration No</label>
                                <input type="text" name="certificate_serial_no" class="form-control" placeholder="Serial No printed on certificate">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Date Received in Custody</label>
                                <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Storage Location / Rack Reference</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Almirah 2, Shelf B, Bundle 2025"></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-box-seam me-1"></i> Register into Safe Custody
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Custody Ledger Table & Filters -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-3 bg-light">
                    <div class="card-body p-3">
                        <form action="admincustodyledger.php" method="GET" class="row g-2 align-items-center">
                            <input type="hidden" name="tab" value="custody">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search Roll No, Student Name, Document..." value="<?= htmlspecialchars($searchTerm) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="custody_status" class="form-select">
                                    <option value="">-- All Custody Statuses --</option>
                                    <option value="IN_CUSTODY" <?= $custodyFilter === 'IN_CUSTODY' ? 'selected' : '' ?>>In Safe Custody</option>
                                    <option value="TEMPORARILY_RETURNED" <?= $custodyFilter === 'TEMPORARILY_RETURNED' ? 'selected' : '' ?>>Temporarily Checked Out</option>
                                    <option value="PERMANENTLY_RETURNED" <?= $custodyFilter === 'PERMANENTLY_RETURNED' ? 'selected' : '' ?>>Permanently Returned</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-table me-2"></i>Deposited Certificates Ledger (<?= count($custodialRecords) ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark small">
                                    <tr>
                                        <th>Student / Roll No</th>
                                        <th>Certificate Name</th>
                                        <th>Received Date</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($custodialRecords)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No custody records found matching criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($custodialRecords as $rec): 
                                            $cBadge = match($rec['status']) {
                                                'IN_CUSTODY' => 'bg-success',
                                                'TEMPORARILY_RETURNED' => 'bg-warning text-dark',
                                                'PERMANENTLY_RETURNED' => 'bg-secondary',
                                                default => 'bg-info'
                                            };
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($rec['roll_no']) ?></span>
                                                    <strong class="d-block small text-dark"><?= htmlspecialchars($rec['student_name']) ?></strong>
                                                    <small class="text-muted"><?= htmlspecialchars($rec['classname'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($rec['document_name']) ?></strong>
                                                    <?php if (!empty($rec['certificate_serial_no'])): ?>
                                                        <small class="text-muted d-block font-monospace">SN: <?= htmlspecialchars($rec['certificate_serial_no']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-muted"><?= date('d M Y', strtotime($rec['received_date'])) ?></td>
                                                <td><span class="badge <?= $cBadge ?>"><?= htmlspecialchars($rec['status']) ?></span></td>
                                                <td class="text-end">
                                                    <?php if ($rec['status'] === 'IN_CUSTODY'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#tempModal<?= $rec['id'] ?>">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i> Checkout
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#permModal<?= $rec['id'] ?>">
                                                            <i class="bi bi-box-arrow-right me-1"></i> Exit Return
                                                        </button>
                                                    <?php elseif ($rec['status'] === 'TEMPORARILY_RETURNED'): ?>
                                                        <form action="admincustodyledger.php?tab=custody" method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_returned">
                                                            <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm certificate returned back into safe custody?');">
                                                                <i class="bi bi-box-arrow-in-down me-1"></i> Check In
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Closed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Modal for Temp Checkout -->
                                            <?php if ($rec['status'] === 'IN_CUSTODY'): ?>
                                                <div class="modal fade" id="tempModal<?= $rec['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form action="admincustodyledger.php?tab=custody" method="POST">
                                                                <input type="hidden" name="action" value="temp_return">
                                                                <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Temporary Checkout: <?= htmlspecialchars($rec['document_name']) ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body text-start">
                                                                    <div class="mb-3">
                                                                        <label class="form-label small fw-bold">Purpose of Temporary Withdrawal</label>
                                                                        <input type="text" name="purpose" class="form-control" placeholder="e.g. Passport Verification, Visa Interview" required>
                                                                    </div>
                                                                    <div class="row g-2 mb-3">
                                                                        <div class="col-6">
                                                                            <label class="form-label small fw-bold">Issued Date</label>
                                                                            <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <label class="form-label small fw-bold">Expected Return Date</label>
                                                                            <input type="date" name="expected_return_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <label class="form-label small fw-bold">Remarks / Undertaking Reference</label>
                                                                        <textarea name="remarks" class="form-control" rows="2" placeholder="Student undertaking letter reference"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-warning">Confirm Temporary Checkout</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Modal for Permanent Exit Return -->
                                                <div class="modal fade" id="permModal<?= $rec['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form action="admincustodyledger.php?tab=custody" method="POST">
                                                                <input type="hidden" name="action" value="perm_return">
                                                                <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Permanent Return: <?= htmlspecialchars($rec['document_name']) ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body text-start">
                                                                    <p class="small text-danger">
                                                                        This will permanently mark the certificate as handed over to the student (e.g. Course Completion / TC Issued).
                                                                    </p>
                                                                    <div class="mb-3">
                                                                        <label class="form-label small fw-bold">Date Handed Over</label>
                                                                        <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                                    </div>
                                                                    <div>
                                                                        <label class="form-label small fw-bold">Handover Remarks / TC Ref No</label>
                                                                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Course completed, signed receipt in file"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Confirm Permanent Handover</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- TAB 2: Statutory Certificate Applications Queue -->
    <?php else: ?>
        <div class="card shadow-sm border-0 mb-3 bg-light">
            <div class="card-body p-3">
                <form action="admincustodyledger.php" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="requests">
                    <div class="col-md-5">
                        <select name="cert_type" class="form-select">
                            <option value="">-- All Certificate Types --</option>
                            <option value="CUSTODIAL" <?= $certTypeFilter === 'CUSTODIAL' ? 'selected' : '' ?>>Custodial Certificate</option>
                            <option value="BONAFIDE" <?= $certTypeFilter === 'BONAFIDE' ? 'selected' : '' ?>>Bonafide Certificate</option>
                            <option value="STUDY_CONDUCT" <?= $certTypeFilter === 'STUDY_CONDUCT' ? 'selected' : '' ?>>Study & Conduct</option>
                            <option value="TRANSFER_CERTIFICATE" <?= $certTypeFilter === 'TRANSFER_CERTIFICATE' ? 'selected' : '' ?>>Transfer Certificate (TC)</option>
                            <option value="NO_DUES" <?= $certTypeFilter === 'NO_DUES' ? 'selected' : '' ?>>No-Dues Clearance</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="cert_status" class="form-select">
                            <option value="">-- All Request Statuses --</option>
                            <option value="REQUESTED" <?= $certStatusFilter === 'REQUESTED' ? 'selected' : '' ?>>Requested (Pending)</option>
                            <option value="APPROVED" <?= $certStatusFilter === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                            <option value="GENERATED" <?= $certStatusFilter === 'GENERATED' ? 'selected' : '' ?>>Generated</option>
                            <option value="ISSUED" <?= $certStatusFilter === 'ISSUED' ? 'selected' : '' ?>>Issued</option>
                            <option value="REJECTED" <?= $certStatusFilter === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-inbox-fill me-2"></i>Incoming Certificate Applications Queue (<?= count($certRequests) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark small">
                            <tr>
                                <th>Certificate Ref No</th>
                                <th>Student Particulars</th>
                                <th>Certificate Type</th>
                                <th>Purpose / Addressed Authority</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($certRequests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No certificate requests matching criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($certRequests as $req): 
                                    $stBadge = match($req['status']) {
                                        'REQUESTED' => 'bg-warning text-dark',
                                        'APPROVED' => 'bg-info text-dark',
                                        'GENERATED', 'ISSUED' => 'bg-success',
                                        'REJECTED' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                    <tr>
                                        <td><strong class="font-monospace small"><?= htmlspecialchars($req['certificate_no']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($req['roll_no']) ?></span>
                                            <strong class="d-block small text-dark"><?= htmlspecialchars($req['student_name']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($req['classname'] ?? '') ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-primary border"><?= htmlspecialchars($req['cert_type']) ?></span></td>
                                        <td class="small"><?= htmlspecialchars($req['purpose']) ?></td>
                                        <td class="small text-muted"><?= date('d M Y', strtotime($req['requested_date'])) ?></td>
                                        <td><span class="badge <?= $stBadge ?>"><?= htmlspecialchars($req['status']) ?></span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <!-- View / Print Certificate -->
                                                <a href="viewcertificate.php?id=<?= $req['id'] ?>" target="_blank" class="btn btn-outline-primary" title="Preview / Print Certificate">
                                                    <i class="bi bi-printer"></i> Print
                                                </a>

                                                <?php if ($req['status'] === 'REQUESTED'): ?>
                                                    <!-- Approve Button -->
                                                    <form action="admincustodyledger.php?tab=requests" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_cert_status">
                                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="status" value="APPROVED">
                                                        <button type="submit" class="btn btn-outline-success" title="Approve Request">
                                                            <i class="bi bi-check2"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if (in_array($req['status'], ['APPROVED', 'GENERATED'])): ?>
                                                    <!-- Mark Issued Button -->
                                                    <form action="admincustodyledger.php?tab=requests" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_cert_status">
                                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                        <input type="hidden" name="status" value="ISSUED">
                                                        <button type="submit" class="btn btn-outline-info" title="Mark Issued">
                                                            <i class="bi bi-send-check"></i> Issue
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_CERTIFICATES');

$page_title = "Certificates";
require_once __DIR__ . '/services/StudentProfileService.php';

$rollNo = trim($_SESSION['user'] ?? '');
$service = \Services\StudentProfileService::getInstance();

$msg = "";
$err = "";

// Handle Certificate Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_cert') {
    \FeatureManager::requireWriteAccess('MOD_CERTIFICATES');
    $certType = trim($_POST['cert_type'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $specificDetails = trim($_POST['specific_details'] ?? '');

    $validTypes = ['CUSTODIAL', 'BONAFIDE', 'STUDY_CONDUCT', 'TRANSFER_CERTIFICATE', 'NO_DUES'];

    if (!in_array($certType, $validTypes)) {
        $err = "Please select a valid certificate type.";
    } elseif (empty($purpose)) {
        $err = "Please enter the reason or purpose for requesting this certificate.";
    } else {
        $customFields = [
            'specific_details' => $specificDetails,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $res = $service->requestCertificate($rollNo, $certType, $purpose, $customFields);
        if ($res['status'] === 1) {
            $msg = $res['msg'];
        } else {
            $err = $res['err'];
        }
    }
}

$requests = $service->getCertificateRequests($rollNo);
$preselectedType = $_GET['cert_type'] ?? '';

require_once("stheader.php");
?>

<div class="container my-3">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-award-fill me-2"></i>Statutory Certificate Request & Issuance Engine</h4>
            <small class="text-muted">Official Academic Section Certificates for Roll No: <strong><?= htmlspecialchars($rollNo) ?></strong></small>
        </div>
        <div class="d-flex gap-2">
            <a href="studentprofile.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-lines-fill me-1"></i> Biographical Profile
            </a>
            <a href="studentdocuments.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-folder2-open me-1"></i> Document Vault
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

    <div class="row g-4">
        <!-- 1. Request Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-send-plus me-2"></i>New Certificate Application</h6>
                </div>
                <div class="card-body">
                    <form action="studentcertificates.php" method="POST">
                        <input type="hidden" name="action" value="request_cert">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Certificate Type</label>
                            <select name="cert_type" class="form-select" required>
                                <option value="">-- Choose Certificate Type --</option>
                                <option value="CUSTODIAL" <?= $preselectedType === 'CUSTODIAL' ? 'selected' : '' ?>>Custodial Certificate (Documents in Safe Custody)</option>
                                <option value="BONAFIDE" <?= $preselectedType === 'BONAFIDE' ? 'selected' : '' ?>>Bonafide Certificate</option>
                                <option value="STUDY_CONDUCT" <?= $preselectedType === 'STUDY_CONDUCT' ? 'selected' : '' ?>>Study & Conduct Certificate</option>
                                <option value="TRANSFER_CERTIFICATE" <?= $preselectedType === 'TRANSFER_CERTIFICATE' ? 'selected' : '' ?>>Transfer Certificate (TC)</option>
                                <option value="NO_DUES" <?= $preselectedType === 'NO_DUES' ? 'selected' : '' ?>>Institutional No-Dues Clearance</option>
                            </select>
                            <small class="text-muted d-block mt-1">
                                <strong>Custodial:</strong> Certifies all original certificates deposited with the college.<br>
                                <strong>Bonafide:</strong> For Passport, Bank Loan, Bus Pass, Scholarships.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Purpose / Addressed Authority</label>
                            <input type="text" name="purpose" class="form-control" placeholder="e.g. State Bank of India Education Loan, Passport Seva Kendra, Jnanabhumi" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Additional Specific Requirements (Optional)</label>
                            <textarea name="specific_details" class="form-control" rows="2" placeholder="Mention any specific remarks to include"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary shadow-sm">
                                <i class="bi bi-file-earmark-arrow-up me-1"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. Request Applications History -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i>My Certificate Applications (<?= count($requests) ?>)</h6>
                    <span class="badge bg-light text-dark border">Institutional Tracking</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text text-muted display-4"></i>
                            <h6 class="text-secondary mt-3">No certificate applications on record</h6>
                            <p class="text-muted small mb-0">Use the form on the left to apply for Custodial, Bonafide, Study/Conduct, or Transfer certificates.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Tracking Ref No</th>
                                        <th>Type</th>
                                        <th>Purpose</th>
                                        <th>Date Applied</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $req): 
                                        $statusBadge = match($req['status']) {
                                            'REQUESTED' => 'bg-warning text-dark',
                                            'APPROVED' => 'bg-info text-dark',
                                            'GENERATED', 'ISSUED' => 'bg-success',
                                            'REJECTED' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        $typeLabel = match($req['cert_type']) {
                                            'CUSTODIAL' => 'Custodial Certificate',
                                            'BONAFIDE' => 'Bonafide Certificate',
                                            'STUDY_CONDUCT' => 'Study & Conduct',
                                            'TRANSFER_CERTIFICATE' => 'Transfer Certificate (TC)',
                                            'NO_DUES' => 'No-Dues Clearance',
                                            default => $req['cert_type']
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <strong class="font-monospace small d-block"><?= htmlspecialchars($req['certificate_no']) ?></strong>
                                            </td>
                                            <td><span class="badge bg-light text-primary border small"><?= $typeLabel ?></span></td>
                                            <td class="small"><?= htmlspecialchars($req['purpose']) ?></td>
                                            <td class="small text-muted"><?= date('d M Y', strtotime($req['requested_date'])) ?></td>
                                            <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($req['status']) ?></span></td>
                                            <td class="text-end">
                                                <?php if (in_array($req['status'], ['APPROVED', 'GENERATED', 'ISSUED'])): ?>
                                                    <a href="viewcertificate.php?id=<?= $req['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-printer me-1"></i> Print / Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">In Review</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once("stfooter.php"); ?>

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_STUDENT_PROFILE');

$page_title = "Document Vault";
require_once __DIR__ . '/services/StudentProfileService.php';

$rollNo = trim($_SESSION['user'] ?? '');
$service = \Services\StudentProfileService::getInstance();

$msg = "";
$err = "";

// Handle Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_STUDENT_PROFILE');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    $docType = trim($_POST['doc_type'] ?? 'OTHER');
    $docTitle = trim($_POST['doc_title'] ?? '');
    $isOriginal = !empty($_POST['is_original_submitted']) ? true : false;
    $custodyLocation = trim($_POST['custody_location_ref'] ?? '');

    if (empty($docTitle)) {
        $err = "Please provide a descriptive title for the document.";
    } elseif (empty($_FILES['doc_file']['name'])) {
        $err = "Please select a file to upload.";
    } else {
        $uploadRes = $service->uploadDocument(
            $rollNo,
            $docType,
            $docTitle,
            $_FILES['doc_file'],
            $isOriginal,
            !empty($custodyLocation) ? $custodyLocation : null,
            (int)($_SESSION['userid'] ?? $_SESSION['id'] ?? 0)
        );

        if ($uploadRes['status'] === 1) {
            $msg = $uploadRes['msg'];
        } else {
            $err = $uploadRes['err'];
        }
    }
}

// Handle Document Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_doc') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    if ($service->deleteDocument($docId, $rollNo, false)) {
        $msg = "Document removed from vault.";
    } else {
        $err = "Cannot delete this document (it may already be verified by Academic Section).";
    }
}

$documents = $service->getDocuments($rollNo);
$custodyRecords = $service->getCustodialRecords($rollNo);

require_once("stheader.php");
?>

<div class="container my-3">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-folder2-open me-2"></i>Student Document Vault & Custody Ledger</h4>
            <small class="text-muted">Repository of Verified Academic Credentials for Roll No: <strong><?= htmlspecialchars($rollNo) ?></strong></small>
        </div>
        <div class="d-flex gap-2">
            <a href="studentprofile.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-lines-fill me-1"></i> Biographical Profile
            </a>
            <?php if (\FeatureManager::isStudentVisible('MOD_CERTIFICATES')): ?>
                <a href="studentcertificates.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-award me-1"></i> Request Statutory Certificates
                </a>
            <?php endif; ?>
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
        <!-- 1. Upload Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-cloud-arrow-up me-2"></i>Upload to Vault</h6>
                </div>
                <div class="card-body">
                    <form action="studentdocuments.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_doc">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Document Type</label>
                            <select name="doc_type" class="form-select" required>
                                <option value="SSC_MEMO">SSC / 10th Marks Memo</option>
                                <option value="INTER_DIPLOMA_MEMO">Intermediate / Diploma Marks Memo</option>
                                <option value="ALLOTMENT_ORDER">EAPCET / ECET Allotment Order</option>
                                <option value="JOINING_REPORT">College Joining Report</option>
                                <option value="CASTE_CERT">Caste / Community Certificate</option>
                                <option value="INCOME_CERT">Income / EWS Certificate</option>
                                <option value="TC_ORIGINAL">Transfer Certificate (TC)</option>
                                <option value="STUDY_CONDUCT">Study & Conduct Certificate</option>
                                <option value="AADHAR">Aadhaar Card Scan</option>
                                <option value="OTHER">Other Academic / Statutory Document</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Document Title / Label</label>
                            <input type="text" name="doc_title" class="form-control" placeholder="e.g. SSC Original Marks Memo" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select File (PDF / JPG / PNG, Max 10MB)</label>
                            <input type="file" name="doc_file" class="form-control" accept=".pdf,image/jpeg,image/png,image/webp" required>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_original_submitted" id="isOrig" value="1">
                                <label class="form-check-label small fw-bold" for="isOrig">
                                    Physical Original Deposited in College Custody
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Check this if the original hardcopy certificate was handed over to the Academic Section during admission. This automatically registers it in the Institutional Custodial Ledger for statutory custodial certificate issuance.
                            </small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i> Secure Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. Vault Inventory & Custodial Ledger -->
        <div class="col-lg-8">
            <!-- Physical Custodial Records Card -->
            <?php if (!empty($custodyRecords)): ?>
                <div class="card shadow-sm border-0 mb-4 border-start border-success border-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold text-success"><i class="bi bi-journal-bookmark-fill me-2"></i>Physical Documents in College Custody</h6>
                            <small class="text-muted">Registered in Institutional Safe Deposit for Custodial Certificate generation</small>
                        </div>
                        <?php if (\FeatureManager::isStudentVisible('MOD_CERTIFICATES')): ?>
                            <a href="studentcertificates.php?cert_type=CUSTODIAL" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-file-earmark-text me-1"></i> Get Custodial Certificate
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Deposit Date</th>
                                        <th>Current Custody Status</th>
                                        <th>Remarks / Return Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($custodyRecords as $cr): 
                                        $custBadge = match($cr['status']) {
                                            'IN_CUSTODY' => 'bg-success',
                                            'TEMPORARILY_RETURNED' => 'bg-warning text-dark',
                                            'PERMANENTLY_RETURNED' => 'bg-secondary',
                                            default => 'bg-info'
                                        };
                                        $custLabel = match($cr['status']) {
                                            'IN_CUSTODY' => 'In Safe Custody',
                                            'TEMPORARILY_RETURNED' => 'Temporarily Checked Out',
                                            'PERMANENTLY_RETURNED' => 'Permanently Returned',
                                            default => $cr['status']
                                        };
                                    ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($cr['document_name']) ?></td>
                                            <td class="small text-muted"><?= date('d M Y', strtotime($cr['received_date'])) ?></td>
                                            <td><span class="badge <?= $custBadge ?>"><?= $custLabel ?></span></td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars($cr['remarks'] ?? 'Held in College Records') ?>
                                                <?php if ($cr['status'] === 'TEMPORARILY_RETURNED' && !empty($cr['expected_return_date'])): ?>
                                                    <span class="d-block text-danger">Expected Return: <?= date('d M Y', strtotime($cr['expected_return_date'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Document Vault Files Card -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-collection me-2"></i>Vault Inventory (<?= count($documents) ?> Files)</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cloud-slash text-muted display-4"></i>
                            <h6 class="text-secondary mt-3">Vault is currently empty</h6>
                            <p class="text-muted small mb-0">Upload your educational certificates and statutory documents using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Verification</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): 
                                        $fileIcon = str_contains($doc['mime_type'], 'pdf') ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary';
                                        $sizeKb = round($doc['file_size'] / 1024, 1);
                                        $sizeStr = ($sizeKb > 1024) ? round($sizeKb / 1024, 2) . " MB" : $sizeKb . " KB";
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi <?= $fileIcon ?> fs-4 me-2"></i>
                                                    <div>
                                                        <strong class="d-block text-dark"><?= htmlspecialchars($doc['doc_title']) ?></strong>
                                                        <small class="text-muted"><?= htmlspecialchars($doc['file_name']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border small"><?= htmlspecialchars($doc['doc_type']) ?></span>
                                                <?php if (!empty($doc['is_original_submitted'])): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success small d-block mt-1">Physical Original</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= $sizeStr ?></td>
                                            <td class="small text-muted"><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></td>
                                            <td>
                                                <?php if (!empty($doc['is_verified']) && (int)$doc['is_verified'] === 1): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="viewdocument.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-outline-primary" title="View Document">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (empty($doc['is_verified'])): ?>
                                                        <form action="studentdocuments.php" method="POST" class="d-inline" onsubmit="return confirm('Remove this document from vault?');">
                                                            <input type="hidden" name="action" value="delete_doc">
                                                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete Document">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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

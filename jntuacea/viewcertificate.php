<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';


if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

require_once __DIR__ . '/services/FeatureManager.php';
require_once __DIR__ . '/services/StudentProfileService.php';
$service = \Services\StudentProfileService::getInstance();

$verifyToken = $_GET['verify'] ?? $_GET['token'] ?? null;
$isPublicVerification = false;

if (!empty($verifyToken)) {
    // Public cryptographic verification endpoint (NAAC Metric 6.2, NBA Criterion 4.3)
    $verifyResult = $service->verifyCertificateByToken((string)$verifyToken);
    if (!$verifyResult || empty($verifyResult['certificate'])) {
        die("Invalid, expired, or counterfeit credential token. Institutional verification failed.");
    }
    $cert=$verifyResult['certificate'];
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Certificate verification</title><body><main><h1>Certificate verified</h1><p>JNTUA College of Engineering, Ananthapuramu</p><dl>';
    foreach(['certificate_no'=>'Certificate number','name'=>'Holder','roll_no'=>'Hall ticket','cert_type'=>'Certificate type','status'=>'Status','issued_at'=>'Issued at'] as $key=>$label){echo '<dt>'.$e($label).'</dt><dd>'.$e($cert[$key]??'Not recorded').'</dd>';}
    echo '</dl><p>This page confirms the credential record. Detailed student documents require authorised access.</p></main></body></html>';exit;
} else {
    // Standard authenticated session
    $role = $_SESSION['role'] ?? '';
    $user = $_SESSION['user'] ?? '';
    if (empty($user)) {
        header("Location: ./index.php");
        exit();
    }
    \FeatureManager::requireAccess('MOD_CERTIFICATES');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try { $renderData = $service->getCertificateRenderData($id); }
    catch (\DomainException $e) { \College\RequestSecurity::deny($e->getMessage()); }

    if (!$renderData) {
        die("Invalid certificate reference or certificate record not found.");
    }

    $cert = $renderData['certificate'];
    if(!in_array($cert['status'],['APPROVED','GENERATED','ISSUED'],true)||empty($cert['approved_by'])||empty($cert['approved_at'])){\College\RequestSecurity::deny('This certificate awaits authorised approval.');}
    $student = $renderData['student'];
    $custodyItems = $renderData['custody_items'];
    $customFields = $renderData['custom_fields'];

    // If logged in as student, verify ownership
    if (strtolower(trim($role)) === 'student' && strtoupper(trim($cert['roll_no'])) !== strtoupper(trim($user))) {
        die("Access denied. You are not authorized to view this certificate.");
    }
}

$certType = $cert['cert_type'];
$studentName = strtoupper($student['name'] ?? $cert['roll_no']);
$fatherName = strtoupper($student['father_name'] ?? '—');
$rollNo = htmlspecialchars($cert['roll_no']);
$progName = htmlspecialchars($student['prog_fullname'] ?? $student['prog_shortname'] ?? 'B.Tech (Degree)');
$deptName = htmlspecialchars($student['dept_name'] ?? 'Engineering');
$className = htmlspecialchars($student['classname'] ?? 'N/A');
$acadYear = htmlspecialchars($student['acad_year'] ?? date('Y'));
$batchCohort = !empty($student['batch_name']) 
    ? htmlspecialchars($student['batch_name'] . " (" . $student['admission_year'] . "–" . $student['graduation_year'] . ")")
    : htmlspecialchars(date('Y') . "–" . (date('Y') + 4));

$certTitle = match($certType) {
    'CUSTODIAL' => 'CUSTODIAL CERTIFICATE',
    'BONAFIDE' => 'BONAFIDE CERTIFICATE',
    'STUDY_CONDUCT' => 'STUDY AND CONDUCT CERTIFICATE',
    'TRANSFER_CERTIFICATE' => 'TRANSFER CERTIFICATE',
    'NO_DUES' => 'NO DUES / INSTITUTIONAL CLEARANCE',
    default => 'OFFICIAL CERTIFICATE'
};

$isQrEnabled = \Services\FeatureManager::isModuleEnabled('MOD_CERTIFICATE_QR');
$certToken = $cert['verification_token'] ?? hash('sha256', $cert['certificate_no'] . $cert['roll_no']);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verifyUrl = $protocol . $host . "/jntuacea/viewcertificate.php?verify=" . urlencode($certToken);
$qrApiUrl = '';
$isQrEnabled = false; // Activate only after a local QR renderer is installed; verification URLs remain available.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $certTitle ?> - <?= $rollNo ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Times New Roman', Times, serif;
            color: #111;
        }
        .cert-container {
            max-width: 820px;
            margin: 30px auto;
            background: #ffffff;
            padding: 50px 60px;
            border: 2px solid #222;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .cert-border {
            border: 1px solid #777;
            padding: 25px;
            position: relative;
        }
        .cert-header {
            text-align: center;
            border-bottom: 2px double #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .univ-name {
            font-size: 1.35rem;
            font-weight: bold;
            color: #0d3b66;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }
        .college-name {
            font-size: 1.15rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 2px;
        }
        .office-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #666;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        .cert-title-badge {
            text-align: center;
            margin: 25px 0 20px 0;
        }
        .cert-title-text {
            font-size: 1.3rem;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 1.5px;
            color: #111;
        }
        .cert-body-p {
            font-size: 1.05rem;
            line-height: 1.85;
            text-align: justify;
            margin-bottom: 18px;
        }
        .sign-area {
            margin-top: 60px;
        }
        .sign-title {
            font-size: 0.95rem;
            font-weight: bold;
        }
        @media print {
            body { background: transparent; }
            .dontprint { display: none !important; }
            .cert-container {
                margin: 0 auto;
                padding: 30px 40px;
                border: 2px solid #000;
                box-shadow: none;
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<?php if ($isPublicVerification): ?>
<div class="container dontprint mt-3 mb-2" style="max-width: 820px;">
    <div class="alert alert-success d-flex align-items-center border-success shadow-sm mb-0">
        <i class="bi bi-patch-check-fill fs-2 me-3 text-success"></i>
        <div>
            <h6 class="alert-heading mb-1 fw-bold text-success">AUTHENTIC INSTITUTIONAL CREDENTIAL VERIFIED</h6>
            <div class="small text-dark">
                This academic certificate was cryptographically validated against the JNTUACEA Student Academic Ledger.
                Certificate No: <strong><?= htmlspecialchars($cert['certificate_no']) ?></strong> | Issued to: <strong><?= htmlspecialchars($studentName) ?></strong> (<?= $rollNo ?>)
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container dontprint my-3 text-center">
    <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm me-2">
        <i class="bi bi-printer me-1"></i> Print / Save as PDF
    </button>
    <button onclick="window.close()" class="btn btn-outline-secondary px-3">
        <i class="bi bi-x-circle me-1"></i> Close
    </button>
</div>

<div class="cert-container">
    <div class="cert-border">
        <!-- Official Institutional Header -->
        <div class="cert-header">
            <div class="univ-name">JAWAHARLAL NEHRU TECHNOLOGICAL UNIVERSITY ANANTAPUR</div>
            <div class="college-name">COLLEGE OF ENGINEERING (AUTONOMOUS), ANANTHAPURAMU</div>
            <div class="text-muted small">Ananthapuramu - 515 002, Andhra Pradesh, India</div>
            <div class="office-name mt-2">OFFICE OF THE ACADEMIC SECTION</div>
        </div>

        <!-- Meta Ref and Date -->
        <div class="d-flex justify-content-between align-items-center mb-3 small">
            <div><strong>Ref No:</strong> <span class="font-monospace"><?= htmlspecialchars($cert['certificate_no']) ?></span></div>
            <div><strong>Date:</strong> <?= date('d/m/Y', strtotime($cert['requested_date'])) ?></div>
        </div>

        <!-- Certificate Title -->
        <div class="cert-title-badge">
            <span class="cert-title-text"><?= $certTitle ?></span>
        </div>

        <!-- Certificate Content by Type -->
        <div class="cert-content mt-4">
            <?php if ($certType === 'BONAFIDE'): ?>
                <p class="cert-body-p">
                    &nbsp;&nbsp;&nbsp;&nbsp;This is to certify that <strong>Mr. / Ms. <?= $studentName ?></strong>, 
                    Son / Daughter of <strong>Sri <?= $fatherName ?></strong>, bearing Roll No: 
                    <strong><?= $rollNo ?></strong>, is a bonafide student of this institution. 
                    He / She is pursuing <strong><?= $progName ?></strong> in <strong><?= $deptName ?></strong> 
                    (Class: <strong><?= $className ?></strong>), belonging to the <strong><?= $batchCohort ?></strong> 
                    cohort during the Academic Year <strong><?= $acadYear ?></strong>.
                </p>
                <p class="cert-body-p">
                    This certificate is issued upon the candidate's formal request to be submitted for the purpose of 
                    <strong><?= htmlspecialchars($cert['purpose']) ?></strong>.
                </p>

            <?php elseif ($certType === 'CUSTODIAL'): ?>
                <p class="cert-body-p">
                    &nbsp;&nbsp;&nbsp;&nbsp;This is to certify that <strong>Mr. / Ms. <?= $studentName ?></strong>, 
                    Son / Daughter of <strong>Sri <?= $fatherName ?></strong>, bearing Roll No: 
                    <strong><?= $rollNo ?></strong>, is a bonafide student of this institution studying 
                    <strong><?= $progName ?></strong> in <strong><?= $deptName ?></strong>.
                </p>
                <p class="cert-body-p mb-2">
                    The following <strong>ORIGINAL CERTIFICATES</strong> submitted by the student at the time of admission 
                    are currently held in the safe custody of this institution:
                </p>

                <div class="table-responsive my-3">
                    <table class="table table-bordered table-sm small align-middle mb-0" style="border-color: #333;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 8%;">S.No</th>
                                <th>Name of the Original Certificate</th>
                                <th style="width: 20%;">Date of Deposit</th>
                                <th style="width: 20%;">Custody Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($custodyItems)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-2 text-muted">
                                        All original statutory credentials verified and deposited at admission counter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($custodyItems as $cItem): ?>
                                    <tr>
                                        <td class="text-center"><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($cItem['document_name']) ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($cItem['received_date'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cItem['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="cert-body-p mt-3">
                    This certificate is issued at the request of the student to produce before 
                    <strong><?= htmlspecialchars($cert['purpose']) ?></strong>.
                </p>

            <?php elseif ($certType === 'STUDY_CONDUCT'): ?>
                <p class="cert-body-p">
                    &nbsp;&nbsp;&nbsp;&nbsp;This is to certify that <strong>Mr. / Ms. <?= $studentName ?></strong>, 
                    Son / Daughter of <strong>Sri <?= $fatherName ?></strong>, bearing Roll No: 
                    <strong><?= $rollNo ?></strong>, has studied / is currently studying 
                    <strong><?= $progName ?></strong> in the Department of <strong><?= $deptName ?></strong> 
                    at Jawaharlal Nehru Technological University Anantapur College of Engineering (Autonomous), Ananthapuramu 
                    during the academic period <strong><?= $batchCohort ?></strong>.
                </p>
                <p class="cert-body-p">
                    During their period of study in this college, their conduct and character have been found to be 
                    <strong>SATISFACTORY & GOOD</strong>.
                </p>
                <p class="cert-body-p">
                    Purpose: <strong><?= htmlspecialchars($cert['purpose']) ?></strong>.
                </p>

            <?php elseif ($certType === 'TRANSFER_CERTIFICATE'): ?>
                <p class="cert-body-p">
                    &nbsp;&nbsp;&nbsp;&nbsp;This Transfer Certificate certifies the institutional exit particulars of 
                    <strong>Mr. / Ms. <?= $studentName ?></strong>, Roll No: <strong><?= $rollNo ?></strong>, 
                    admitted into <strong><?= $progName ?></strong> (<strong><?= $deptName ?></strong>).
                </p>
                <div class="row g-2 small border p-3 my-2" style="line-height: 1.8;">
                    <div class="col-6"><strong>Date of Admission:</strong> <?= !empty($student['admission_date']) ? date('d/m/Y', strtotime($student['admission_date'])) : 'At Admission' ?></div>
                    <div class="col-6"><strong>Date of Leaving:</strong> <?= date('d/m/Y') ?></div>
                    <div class="col-6"><strong>Class Last Studied:</strong> <?= $className ?></div>
                    <div class="col-6"><strong>Whether Qualified for Degree:</strong> YES / AS PER CURRICULUM</div>
                    <div class="col-12"><strong>Reason for Leaving:</strong> <?= htmlspecialchars($cert['purpose']) ?></div>
                </div>

            <?php else: ?>
                <p class="cert-body-p">
                    &nbsp;&nbsp;&nbsp;&nbsp;This is an institutional clearance and no-dues certificate issued to 
                    <strong>Mr. / Ms. <?= $studentName ?></strong> (Roll No: <strong><?= $rollNo ?></strong>) 
                    for the purpose of <strong><?= htmlspecialchars($cert['purpose']) ?></strong>.
                </p>
            <?php endif; ?>
        </div>

        <!-- Signature Blocks -->
        <div class="row sign-area text-center">
            <div class="col-4">
                <br><br>
                <div class="border-top pt-2">
                    <span class="sign-title">Superintendent</span><br>
                    <small class="text-muted">Academic Section</small>
                </div>
            </div>
            <div class="col-4 text-center">
                <?php if ($isQrEnabled): ?>
                    <div class="p-2 border rounded d-inline-block text-center bg-white shadow-sm" style="border-style: solid !important; max-width: 140px;">
                        <img src="<?= htmlspecialchars($qrApiUrl) ?>" alt="Verification QR Code" width="85" height="85" class="img-fluid mb-1">
                        <div style="font-size: 0.65rem; font-weight: bold; color: #0d3b66; line-height: 1.1;">
                            <i class="bi bi-qr-code-scan"></i> SCAN TO VERIFY<br>
                            <span class="text-muted font-monospace" style="font-size: 0.6rem;"><?= htmlspecialchars(substr($cert['certificate_no'], 0, 16)) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-2 border rounded d-inline-block small text-muted" style="border-style: dashed !important;">
                        <i class="bi bi-shield-check text-primary"></i><br>
                        Verified Institutional<br>Record Seal
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-4">
                <br><br>
                <div class="border-top pt-2">
                    <span class="sign-title">PRINCIPAL</span><br>
                    <small class="text-muted">JNTUACEA</small>
                </div>
            </div>
        </div>

        <!-- Security Footer -->
        <div class="text-center mt-4 pt-3 border-top small text-muted font-monospace" style="font-size: 0.75rem;">
            Institutional Auth Token: <?= $certToken ?><br>
            <?php if ($isQrEnabled): ?>
                Verification URL: <a href="<?= htmlspecialchars($verifyUrl) ?>" target="_blank" class="text-muted text-decoration-none"><?= htmlspecialchars($verifyUrl) ?></a><br>
            <?php endif; ?>
            Official Document of JNTUA College of Engineering (Autonomous), Ananthapuramu. Generated automatically via Examination & Student Information System.
        </div>
    </div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_STUDENT_PROFILE');

$page_title = "Profile";
require_once __DIR__ . '/services/StudentProfileService.php';

$rollNo = trim($_SESSION['user'] ?? '');
$service = \Services\StudentProfileService::getInstance();

$msg = "";
$err = "";

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    \FeatureManager::requireWriteAccess('MOD_STUDENT_PROFILE');
    $profileData = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'mobile' => $_POST['mobile'] ?? '',
        'dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
        'gender' => $_POST['gender'] ?? null,
        'blood_group' => $_POST['blood_group'] ?? null,
        'aadhar_number' => $_POST['aadhar_number'] ?? null,
        'father_name' => $_POST['father_name'] ?? null,
        'mother_name' => $_POST['mother_name'] ?? null,
        'parent_phone' => $_POST['parent_phone'] ?? null,
        'parent_email' => $_POST['parent_email'] ?? null,
        'admission_category' => $_POST['admission_category'] ?? null,
        'rank_obtained' => !empty($_POST['rank_obtained']) ? (int)$_POST['rank_obtained'] : null,
        'hall_ticket_admission' => $_POST['hall_ticket_admission'] ?? null,
        'admission_date' => !empty($_POST['admission_date']) ? $_POST['admission_date'] : null,
        'permanent_address' => $_POST['permanent_address'] ?? null,
        'current_address' => $_POST['current_address'] ?? null,
    ];

    if ($service->saveProfile($rollNo, $profileData)) {
        $msg = "Profile information updated successfully!";
        if (!empty($profileData['name'])) {
            $_SESSION['name'] = $profileData['name'];
        }
    } else {
        $err = "An error occurred while saving profile details.";
    }
}

$profile = $service->getProfile($rollNo);

require_once("stheader.php");
?>

<div class="container my-3">
    <!-- Top Action Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-person-lines-fill me-2"></i>Student Biographical Dossier & Profile</h4>
            <small class="text-muted">Master Institutional Record for Roll No: <strong><?= htmlspecialchars($rollNo) ?></strong></small>
        </div>
        <div class="d-flex gap-2">
            <a href="studentdocuments.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-folder2-open me-1"></i> Document Vault
            </a>
            <?php if (\FeatureManager::isStudentVisible('MOD_CERTIFICATES')): ?>
                <a href="studentcertificates.php" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-award me-1"></i> Request Certificates
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

    <!-- Status & Academic Affiliation Summary -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($profile['name'] ?? $_SESSION['name'] ?? $rollNo) ?></h5>
                    <p class="text-muted small mb-2">
                        <strong>Roll No:</strong> <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($rollNo) ?></span>
                        &bull; <strong>Program:</strong> <?= htmlspecialchars($profile['prog_fullname'] ?? $profile['prog_shortname'] ?? 'B.Tech') ?>
                        &bull; <strong>Department:</strong> <?= htmlspecialchars($profile['dept_name'] ?? 'Engineering') ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-primary">Class: <?= htmlspecialchars($profile['classname'] ?? 'N/A') ?></span>
                        <span class="badge bg-info text-dark">Regulation: <?= htmlspecialchars($profile['regulation'] ?? 'Standard') ?></span>
                        <?php if (!empty($profile['batch_name'])): ?>
                            <span class="badge bg-success">Batch Cohort: <?= htmlspecialchars($profile['batch_name']) ?> (<?= htmlspecialchars($profile['admission_year']) ?>–<?= htmlspecialchars($profile['graduation_year']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if (!empty($profile['is_verified']) && (int)$profile['is_verified'] === 1): ?>
                        <span class="badge bg-success p-2 fs-6">
                            <i class="bi bi-patch-check-fill me-1"></i> Verified by Academic Section
                        </span>
                        <div class="text-muted small mt-1">Verified on: <?= date('d M Y', strtotime($profile['verified_at'])) ?></div>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark p-2 fs-6">
                            <i class="bi bi-hourglass-split me-1"></i> Profile Pending Verification
                        </span>
                        <div class="text-muted small mt-1">Please ensure all details are accurate</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <form action="studentprofile.php" method="POST">
        <input type="hidden" name="action" value="save_profile">

        <div class="row g-3">
            <!-- 1. Basic & Contact Information -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-person me-2"></i>1. Basic & Contact Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Full Name (As per SSC / Secondary Memo)</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" placeholder="student@example.com">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Student Mobile Phone</label>
                                <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($profile['mobile'] ?? '') ?>" placeholder="10-digit mobile">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($profile['dob'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">-- Select Gender --</option>
                                    <option value="MALE" <?= ($profile['gender'] ?? '') === 'MALE' ? 'selected' : '' ?>>Male</option>
                                    <option value="FEMALE" <?= ($profile['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                                    <option value="OTHER" <?= ($profile['gender'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Blood Group</label>
                                <input type="text" name="blood_group" class="form-control" value="<?= htmlspecialchars($profile['blood_group'] ?? '') ?>" placeholder="e.g. O+, A+, B+">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Aadhaar Card Number</label>
                                <input type="text" name="aadhar_number" class="form-control" value="<?= htmlspecialchars($profile['aadhar_number'] ?? '') ?>" placeholder="12-digit UIDAI Number">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Parental Information -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-people me-2"></i>2. Parental Particulars</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Father / Guardian Name</label>
                            <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($profile['father_name'] ?? '') ?>" placeholder="Full Father Name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Mother Name</label>
                            <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($profile['mother_name'] ?? '') ?>" placeholder="Full Mother Name">
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Parent Contact Phone</label>
                                <input type="text" name="parent_phone" class="form-control" value="<?= htmlspecialchars($profile['parent_phone'] ?? '') ?>" placeholder="Parent Mobile">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Parent Email Address</label>
                                <input type="email" name="parent_email" class="form-control" value="<?= htmlspecialchars($profile['parent_email'] ?? '') ?>" placeholder="parent@example.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Admission & Entrance Records -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-card-checklist me-2"></i>3. Admission & Entrance Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Admission Quota / Category</label>
                                <select name="admission_category" class="form-select">
                                    <option value="">-- Select Category --</option>
                                    <option value="CONVENOR" <?= ($profile['admission_category'] ?? '') === 'CONVENOR' ? 'selected' : '' ?>>Convenor (EAPCET)</option>
                                    <option value="ECET_LATERAL" <?= ($profile['admission_category'] ?? '') === 'ECET_LATERAL' ? 'selected' : '' ?>>ECET (Lateral Entry)</option>
                                    <option value="SPOT_ADMISSION" <?= ($profile['admission_category'] ?? '') === 'SPOT_ADMISSION' ? 'selected' : '' ?>>Spot Admission</option>
                                    <option value="MANAGEMENT_NRI" <?= ($profile['admission_category'] ?? '') === 'MANAGEMENT_NRI' ? 'selected' : '' ?>>Management / NRI Quota</option>
                                    <option value="PGECET_GATE" <?= ($profile['admission_category'] ?? '') === 'PGECET_GATE' ? 'selected' : '' ?>>PGECET / GATE (M.Tech)</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Entrance Exam State Rank</label>
                                <input type="number" name="rank_obtained" class="form-control" value="<?= htmlspecialchars((string)($profile['rank_obtained'] ?? '')) ?>" placeholder="e.g. 3420">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Entrance Hall Ticket No</label>
                                <input type="text" name="hall_ticket_admission" class="form-control" value="<?= htmlspecialchars($profile['hall_ticket_admission'] ?? '') ?>" placeholder="Entrance HT No">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Date of College Admission</label>
                                <input type="date" name="admission_date" class="form-control" value="<?= htmlspecialchars($profile['admission_date'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Address Details -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-geo-alt me-2"></i>4. Residential Addresses</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Permanent Address</label>
                            <textarea name="permanent_address" class="form-control" rows="2" placeholder="House/Flat No, Street, Village/Town, Mandal, District, State, PIN"><?= htmlspecialchars($profile['permanent_address'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="form-label small fw-bold">Current / Present Communication Address</label>
                            <textarea name="current_address" class="form-control" rows="2" placeholder="Hostel / Room Address or same as permanent"><?= htmlspecialchars($profile['current_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end mt-4 mb-5">
            <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                <i class="bi bi-save me-1"></i> Save Profile Details
            </button>
        </div>
    </form>
</div>

<?php require_once("stfooter.php"); ?>

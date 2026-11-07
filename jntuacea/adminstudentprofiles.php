<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: Student Profiles, Dossier & Document Vault Management
 * 
 * Accessible by Superadmin, Principal (Admin), and Academic Section.
 * Provides institutional oversight, verification, and inspection of student
 * personal records, admission metrics, and uploaded credential documents.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'academic_section', 'academicsection', 'hod'], true)) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_STUDENT_PROFILE', $role);

$page_title = "Student Profiles & Dossier";

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/services/StudentProfileService.php';

// Render appropriate role menu
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminmenu.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/adminmenu.php';
} elseif ($role === 'hod') {
    require_once __DIR__ . '/hodmenu.php';
} else {
    require_once __DIR__ . '/academicsectionmenu.php';
}

$service = \Services\StudentProfileService::getInstance();
$db = \DBCredentials::getInstance()->getConnection();
$userId = (int)($_SESSION['userid'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
$isHod = ($role === 'hod');
$hodDeptId = $isHod ? (int)($_SESSION['dept_id'] ?? 0) : 0;

$msg = "";
$err = "";

// Handle Verification Actions (Academic Section / Admin only; HOD cannot verify sensitive documents)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($isHod) {
        $err = "Access denied: Sensitive document verification is restricted to Academic Section.";
    } else {
        \FeatureManager::requireWriteAccess('MOD_STUDENT_PROFILE');
        if ($_POST['action'] === 'verify_profile') {
            $vRoll = trim($_POST['roll_no'] ?? '');
            if ($service->verifyProfile($vRoll, $userId)) {
                $msg = "Student profile for $vRoll marked as VERIFIED.";
            } else {
                $err = "Failed to verify profile.";
            }
        } elseif ($_POST['action'] === 'verify_doc') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $vRemarks = trim($_POST['remarks'] ?? 'Verified by Academic Section');
            if ($service->verifyDocument($docId, $userId, $vRemarks)) {
                $msg = "Document #$docId verified successfully.";
            } else {
                $err = "Failed to verify document.";
            }
        }
    }
}

// Student search & lookup
$searchRoll = trim($_GET['roll_no'] ?? '');
$filterDept = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$filterClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($isHod) {
    $filterDept = $hodDeptId;
}

$profile = null;
$documents = [];
$custodyRecords = [];

if (!empty($searchRoll)) {
    $profile = $service->getProfile($searchRoll);
    if ($profile) {
        if ($isHod && (int)($profile['dept_id'] ?? 0) !== $hodDeptId) {
            $profile = null;
            $err = "Access denied: Student with Roll Number " . htmlspecialchars($searchRoll) . " belongs to another department.";
        } else {
            $documents = $service->getDocuments($searchRoll);
            $custodyRecords = $service->getCustodialRecords($searchRoll);
        }
    } else {
        $err = "No student found with Roll Number / Username: " . htmlspecialchars($searchRoll);
    }
}

// Fetch departments for filter
$depts = [];
$dSql = "SELECT id, dept_fullname AS dept_name, dept_shortname FROM departments";
if ($isHod) {
    $dSql .= " WHERE id = " . $hodDeptId;
}
$dSql .= " ORDER BY dept_fullname ASC";
$dRes = $db->query($dSql);
if ($dRes) {
    while ($r = $dRes->fetch_assoc()) {
        $depts[] = $r;
    }
}

// Fetch active classes for filter
$classes = [];
$cSql = "SELECT c.id, c.classname, s.dept_id FROM classes c LEFT JOIN specialization s ON c.spec_id = s.id";
if ($isHod) {
    $cSql .= " WHERE s.dept_id = " . $hodDeptId;
}
$cSql .= " ORDER BY c.classname ASC";
$cRes = $db->query($cSql);
if ($cRes) {
    while ($r = $cRes->fetch_assoc()) {
        $classes[] = $r;
    }
}

// If no specific student selected, show recent or filtered student roster
$studentRoster = [];
if (empty($profile)) {
    $rosterSql = "
        SELECT u.username AS roll_no, u.name, u.email, u.mobile,
               c.classname, d.dept_fullname AS dept_name,
               sp.is_verified, sp.dob, sp.aadhar_number,
               (SELECT COUNT(*) FROM student_documents sd WHERE sd.roll_no = u.username) AS doc_count
        FROM users u
        LEFT JOIN (
            SELECT s1.username, s1.class_id
            FROM students s1
            INNER JOIN (
                SELECT username, MAX(id) AS max_id FROM students GROUP BY username
            ) s2 ON s1.id = s2.max_id
        ) latest_st ON u.username = latest_st.username
        LEFT JOIN classes c ON latest_st.class_id = c.id
        LEFT JOIN specialization s ON c.spec_id = s.id
        LEFT JOIN departments d ON s.dept_id = d.id
        LEFT JOIN student_profiles sp ON u.username = sp.roll_no
        WHERE u.role = 'student'
    ";
    $params = [];
    $types = "";

    if ($filterDept > 0) {
        $rosterSql .= " AND s.dept_id = ?";
        $params[] = $filterDept;
        $types .= "i";
    }
    if ($filterClass > 0) {
        $rosterSql .= " AND c.id = ?";
        $params[] = $filterClass;
        $types .= "i";
    }

    $rosterSql .= " ORDER BY u.username ASC LIMIT 50";

    $stmt = $db->prepare($rosterSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rRes = $stmt->get_result();
    while ($row = $rRes->fetch_assoc()) {
        $studentRoster[] = $row;
    }
}
?>

<div class="container my-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-primary"><i class="bi bi-person-badge-fill me-2"></i>Student Profiles & Document Dossier</h4>
            <small class="text-muted"><?= $isHod ? 'Departmental Student Profiles & Digital Records' : 'Master Student Profiles, Document Vault & Verification Cockpit' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$isHod): ?>
            <a href="admincustodyledger.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-journal-bookmark me-1"></i> Custodial Ledger & Certificates
            </a>
            <?php endif; ?>
            <a href="<?= $isHod ? 'hodresults.php' : 'academicsectionresults.php' ?>" class="btn btn-outline-secondary btn-sm">
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

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body p-3">
            <form action="adminstudentprofiles.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="roll_no" class="form-control" placeholder="Search Roll No / Username..." value="<?= htmlspecialchars($searchRoll) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="dept_id" class="form-select" <?= $isHod ? 'disabled' : '' ?>>
                        <?php if (!$isHod): ?>
                            <option value="0">-- All Departments --</option>
                        <?php endif; ?>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $filterDept === (int)$d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isHod): ?>
                        <input type="hidden" name="dept_id" value="<?= $hodDeptId ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <select name="class_id" class="form-select">
                        <option value="0">-- All Classes --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterClass === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['classname']) ?>
                            </option>
                        <?php endforeach; ?>
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

    <!-- View Student Dossier when Selected -->
    <?php if ($profile): ?>
        <div class="card shadow-sm border-0 mb-4 border-top border-primary border-4">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1 text-primary fw-bold">
                        <?= htmlspecialchars($profile['name']) ?> 
                        <span class="badge bg-secondary font-monospace ms-2"><?= htmlspecialchars($profile['roll_no']) ?></span>
                    </h5>
                    <div class="small text-muted">
                        <?= htmlspecialchars($profile['prog_fullname'] ?? 'B.Tech') ?> &bull; 
                        <?= htmlspecialchars($profile['dept_name'] ?? '') ?> &bull; 
                        Class: <strong><?= htmlspecialchars($profile['classname'] ?? 'N/A') ?></strong>
                        <?php if (!empty($profile['batch_name'])): ?>
                            &bull; Cohort: <span class="badge bg-success-subtle text-success border border-success"><?= htmlspecialchars($profile['batch_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if (!empty($profile['is_verified']) && (int)$profile['is_verified'] === 1): ?>
                        <span class="badge bg-success fs-6 p-2"><i class="bi bi-patch-check-fill me-1"></i> Profile Verified</span>
                    <?php elseif (!$isHod): ?>
                        <form action="adminstudentprofiles.php?roll_no=<?= urlencode($searchRoll) ?>" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="verify_profile">
                            <input type="hidden" name="roll_no" value="<?= htmlspecialchars($profile['roll_no']) ?>">
                            <button type="submit" class="btn btn-success btn-sm shadow-sm" onclick="return confirm('Confirm verification of student profile?');">
                                <i class="bi bi-check2-circle me-1"></i> Mark Profile Verified
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark fs-6 p-2"><i class="bi bi-hourglass-split me-1"></i> Verification Pending (Academic Section)</span>
                    <?php endif; ?>
                    <?php if (!$isHod): ?>
                    <a href="admincustodyledger.php?search=<?= urlencode($profile['roll_no']) ?>" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-journal-bookmark me-1"></i> Custody Ledger
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-4">
                    <!-- 1. Personal & Contact Particulars -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 bg-light rounded h-100 border">
                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-person me-1"></i> Personal Particulars</h6>
                            <ul class="list-unstyled small mb-0 lh-lg">
                                <li><strong>DOB:</strong> <?= !empty($profile['dob']) ? date('d-m-Y', strtotime($profile['dob'])) : '—' ?></li>
                                <li><strong>Gender:</strong> <?= htmlspecialchars($profile['gender'] ?? '—') ?></li>
                                <li><strong>Blood Group:</strong> <?= htmlspecialchars($profile['blood_group'] ?? '—') ?></li>
                                <li><strong>Aadhaar:</strong> <?= htmlspecialchars($profile['aadhar_number'] ?? '—') ?></li>
                                <li><strong>Mobile:</strong> <?= htmlspecialchars($profile['mobile'] ?? '—') ?></li>
                                <li><strong>Email:</strong> <?= htmlspecialchars($profile['email'] ?? '—') ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- 2. Parental Information -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 bg-light rounded h-100 border">
                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-people me-1"></i> Parental Details</h6>
                            <ul class="list-unstyled small mb-0 lh-lg">
                                <li><strong>Father:</strong> <?= htmlspecialchars($profile['father_name'] ?? '—') ?></li>
                                <li><strong>Mother:</strong> <?= htmlspecialchars($profile['mother_name'] ?? '—') ?></li>
                                <li><strong>Parent Mobile:</strong> <?= htmlspecialchars($profile['parent_phone'] ?? '—') ?></li>
                                <li><strong>Parent Email:</strong> <?= htmlspecialchars($profile['parent_email'] ?? '—') ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- 3. Admission & Entrance -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 bg-light rounded h-100 border">
                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-card-checklist me-1"></i> Admission & Quota</h6>
                            <ul class="list-unstyled small mb-0 lh-lg">
                                <li><strong>Quota:</strong> <?= htmlspecialchars($profile['admission_category'] ?? '—') ?></li>
                                <li><strong>Entrance Rank:</strong> <?= htmlspecialchars((string)($profile['rank_obtained'] ?? '—')) ?></li>
                                <li><strong>Entrance HT No:</strong> <?= htmlspecialchars($profile['hall_ticket_admission'] ?? '—') ?></li>
                                <li><strong>Admission Date:</strong> <?= !empty($profile['admission_date']) ? date('d-m-Y', strtotime($profile['admission_date'])) : '—' ?></li>
                                <li><strong>Regulation:</strong> <?= htmlspecialchars($profile['regulation'] ?? 'Standard') ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- 4. Addresses -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 bg-light rounded h-100 border">
                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i> Addresses</h6>
                            <div class="small mb-2">
                                <strong>Permanent:</strong><br>
                                <span class="text-muted"><?= nl2br(htmlspecialchars($profile['permanent_address'] ?? 'Not provided')) ?></span>
                            </div>
                            <div class="small">
                                <strong>Communication:</strong><br>
                                <span class="text-muted"><?= nl2br(htmlspecialchars($profile['current_address'] ?? 'Same as permanent')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Vault Section -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="bi bi-folder2-open me-2"></i>Uploaded Documents & Certificates (<?= count($documents) ?>)
                    </h6>
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-light border text-center py-3 small text-muted">
                            No uploaded credentials present in student's digital vault.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle border mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Document Title</th>
                                        <th>Category Type</th>
                                        <th>Uploaded On</th>
                                        <th>Physical Original</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-file-earmark-text text-primary me-1"></i>
                                                <strong><?= htmlspecialchars($doc['doc_title']) ?></strong>
                                                <small class="text-muted d-block"><?= htmlspecialchars($doc['file_name']) ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($doc['doc_type']) ?></span></td>
                                            <td class="small text-muted"><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></td>
                                            <td>
                                                <?php if (!empty($doc['is_original_submitted'])): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success">In College Custody</span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Digital Scan Only</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($doc['is_verified'])): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="viewdocument.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="View Document">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <?php if (!$isHod && empty($doc['is_verified'])): ?>
                                                    <form action="adminstudentprofiles.php?roll_no=<?= urlencode($searchRoll) ?>" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="verify_doc">
                                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Mark Verified">
                                                            <i class="bi bi-check2"></i> Verify
                                                        </button>
                                                    </form>
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

    <!-- Student Roster List when browsing -->
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-people me-2"></i>Student Roster (Showing <?= count($studentRoster) ?> records)</h6>
                <span class="badge bg-light text-dark border">Select any student to view dossier</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark small">
                            <tr>
                                <th>Roll Number</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Class</th>
                                <th>Contact</th>
                                <th class="text-center">Documents</th>
                                <th class="text-center">Profile Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($studentRoster)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No students matching the filter criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($studentRoster as $s): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($s['roll_no']) ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($s['name']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($s['dept_name'] ?? '—') ?></td>
                                        <td class="small"><?= htmlspecialchars($s['classname'] ?? '—') ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($s['mobile'] ?? $s['email'] ?? '—') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark"><?= (int)$s['doc_count'] ?> files</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($s['is_verified'])): ?>
                                                <span class="badge bg-success">Verified</span>
                                            <?php elseif (!empty($s['dob'])): ?>
                                                <span class="badge bg-warning text-dark">Submitted</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Incomplete</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="adminstudentprofiles.php?roll_no=<?= urlencode($s['roll_no']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-folder2-open me-1"></i> View Dossier
                                            </a>
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

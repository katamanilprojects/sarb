<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: Superadmin Student Cohorts & Batches Management
 * 
 * Part of Batch-Centric Governance Architecture.
 * Allows Superadmin to configure student cohorts (e.g. 2025-2029),
 * map them to autonomous regulations, and launch batch OBE setup.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'superadmin') {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_BATCH_GOVERNANCE');

$page_title = "Student Batches";

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/superadminmenu.php';
require_once __DIR__ . '/services/BatchOBEService.php';
require_once __DIR__ . '/programs.class.php';
require_once __DIR__ . '/regulations.class.php';

$batchService = \Services\BatchOBEService::getInstance();
$programsObj = new \Programs();
$regulationsObj = new \Regulations();

$programs = $programsObj->getAllPrograms()['data'] ?? [];
$regulations = $regulationsObj->getAllRegulations()['data'] ?? [];

$succMsg = '';
$errMsg = '';

// Handle Create / Edit POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_BATCH_GOVERNANCE');
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF mismatch).";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);

        if ($action === 'create_batch') {
            $res = $batchService->createBatch($_POST, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        } elseif ($action === 'update_batch') {
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $res = $batchService->updateBatch($batchId, $_POST, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        }
    }
}

// Generate CSRF token
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$selectedProgram = isset($_GET['prog_id']) ? (int)$_GET['prog_id'] : 0;
$batches = $batchService->getBatches($selectedProgram > 0 ? $selectedProgram : null);
?>

<div class="container my-4">
    <!-- Breadcrumb / Header -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary fw-bold">
                        <i class="bi bi-people-fill me-2"></i> Student Batches (Cohorts)
                    </h4>
                    <p class="text-muted mb-0 small">
                        Anchor academic regulations, vision/mission, PEOs, and POs/PSOs to permanent student graduation batches.
                    </p>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                        <i class="bi bi-plus-circle me-1"></i> Add New Batch
                    </button>
                    <a href="superadminbatchobe.php" class="btn btn-outline-success">
                        <i class="bi bi-diagram-3 me-1"></i> Batch OBE & Attainment
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
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filter by Program -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold text-secondary small">Filter by Degree Program:</label>
                </div>
                <div class="col-auto">
                    <select name="prog_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">All Programs</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= (int)$prog['id']; ?>" <?= ($selectedProgram === (int)$prog['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($prog['prog_shortname'] . ' - ' . $prog['prog_fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary"><?= count($batches); ?> Batches</span>
                </div>
            </form>
        </div>
    </div>

    <!-- Batches Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3 border-0">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse me-2"></i> Admitted Student Batches</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Batch Name</th>
                        <th>Program</th>
                        <th>Regulation</th>
                        <th>Admission Year</th>
                        <th>Graduation Year</th>
                        <th>Classes Mapped</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No student batches found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $idx => $b): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $idx + 1; ?></td>
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($b['batch_name']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($b['prog_shortname']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($b['regulation']); ?></span>
                                </td>
                                <td><?= (int)$b['admission_year']; ?></td>
                                <td><?= (int)$b['graduation_year']; ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= (int)$b['classes_count']; ?> classes</span>
                                </td>
                                <td>
                                    <?php if ($b['is_active']): ?>
                                        <span class="badge bg-success">Active Cohort</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Graduated / Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="superadminbatchobe.php?batch_id=<?= (int)$b['id']; ?>" class="btn btn-sm btn-outline-success me-1" title="Configure Vision, Mission & PEOs">
                                        <i class="bi bi-diagram-3"></i> OBE Setup
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary edit-batch-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editBatchModal"
                                            data-id="<?= (int)$b['id']; ?>"
                                            data-prog="<?= (int)$b['program_id']; ?>"
                                            data-reg="<?= (int)$b['regulation_id']; ?>"
                                            data-name="<?= htmlspecialchars($b['batch_name']); ?>"
                                            data-admin="<?= (int)$b['admission_year']; ?>"
                                            data-grad="<?= (int)$b['graduation_year']; ?>"
                                            data-active="<?= (int)$b['is_active']; ?>"
                                            title="Edit Batch">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Batch -->
<div class="modal fade" id="createBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="create_batch">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Add New Student Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Degree Program <span class="text-danger">*</span></label>
                    <select name="program_id" class="form-select" required>
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= (int)$prog['id']; ?>">
                                <?= htmlspecialchars($prog['prog_shortname'] . ' - ' . $prog['prog_fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Autonomous Regulation <span class="text-danger">*</span></label>
                    <select name="regulation_id" class="form-select" required>
                        <option value="">-- Select Regulation --</option>
                        <?php foreach ($regulations as $reg): ?>
                            <option value="<?= (int)$reg['id']; ?>">
                                <?= htmlspecialchars($reg['regulation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Batch Name (Cohort) <span class="text-danger">*</span></label>
                    <input type="text" name="batch_name" class="form-control" placeholder="e.g. 2025-2029" required>
                    <div class="form-text small">Standard format: Entry Year - Exit Year (e.g. 2025-2029 for 4-year B.Tech).</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Admission Year <span class="text-danger">*</span></label>
                        <input type="number" name="admission_year" class="form-control" value="<?= date('Y'); ?>" min="2000" max="2099" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Graduation Year <span class="text-danger">*</span></label>
                        <input type="number" name="graduation_year" class="form-control" value="<?= date('Y') + 4; ?>" min="2000" max="2099" required>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createBatchActive" checked>
                        <label class="form-check-label fw-semibold" for="createBatchActive">Active Studying Cohort</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Create Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Batch -->
<div class="modal fade" id="editBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="update_batch">
            <input type="hidden" name="batch_id" id="editBatchId">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Student Batch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Degree Program <span class="text-danger">*</span></label>
                    <select name="program_id" id="editProgramId" class="form-select" required>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= (int)$prog['id']; ?>">
                                <?= htmlspecialchars($prog['prog_shortname'] . ' - ' . $prog['prog_fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Autonomous Regulation <span class="text-danger">*</span></label>
                    <select name="regulation_id" id="editRegulationId" class="form-select" required>
                        <?php foreach ($regulations as $reg): ?>
                            <option value="<?= (int)$reg['id']; ?>">
                                <?= htmlspecialchars($reg['regulation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Batch Name <span class="text-danger">*</span></label>
                    <input type="text" name="batch_name" id="editBatchName" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Admission Year <span class="text-danger">*</span></label>
                        <input type="number" name="admission_year" id="editAdmissionYear" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Graduation Year <span class="text-danger">*</span></label>
                        <input type="number" name="graduation_year" id="editGraduationYear" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editBatchActive">
                        <label class="form-check-label fw-semibold" for="editBatchActive">Active Studying Cohort</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark fw-bold"><i class="bi bi-save me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.edit-batch-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editBatchId').value = this.dataset.id;
        document.getElementById('editProgramId').value = this.dataset.prog;
        document.getElementById('editRegulationId').value = this.dataset.reg;
        document.getElementById('editBatchName').value = this.dataset.name;
        document.getElementById('editAdmissionYear').value = this.dataset.admin;
        document.getElementById('editGraduationYear').value = this.dataset.grad;
        document.getElementById('editBatchActive').checked = (parseInt(this.dataset.active) === 1);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

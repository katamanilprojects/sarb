<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: Superadmin Feature Module Management & Visibility Matrix
 * 
 * Allows Superadmin to enable/disable features globally and control
 * runtime visibility for Faculty, Students, and HODs without code modifications.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Feature Management";

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/superadminmenu.php';
require_once __DIR__ . '/services/FeatureManager.php';

$featureManager = \Services\FeatureManager::getInstance();

$succMsg = '';
$errMsg = '';

// Handle Feature Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF token mismatch). Please reload and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);

        if ($action === 'save_permission') {
            try { $featureManager->savePermission($_POST['module_key']??'',$_POST['permission_role']??'',$_POST['permission_action']??'',($_POST['is_allowed']??'0')==='1'); $succMsg='Permission saved.'; }
            catch (Throwable $e) { $errMsg=$e->getMessage(); }
        } elseif ($action === 'save_features') {
            $submitted = $_POST['features'] ?? [];
            $result = $featureManager->updateBatch($submitted, $userId);
            if ($result['status'] === 1) {
                $succMsg = "Feature settings updated successfully ({$result['updated_count']} modules processed).";
            } else {
                $errMsg = "Failed to update feature settings: " . ($result['err'] ?? 'Unknown error');
            }
        }
    }
}

// Generate new CSRF token
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$modulesGrouped = $featureManager->getModulesGroupedByCategory();
?>

<div class="container my-4">
    <!-- Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="bi bi-toggles text-primary me-2"></i>Feature Management & Visibility Matrix
                    </h4>
                    <p class="text-muted mb-0 small">
                        Centrally enable/disable system modules and control visibility for Faculty, Students, and HODs without code changes.
                    </p>
                </div>
                <div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="bi bi-shield-check me-1"></i>Superadmin Authorization
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($succMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($succMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Instructions / Context Callout -->
    <div class="alert alert-info border-info-subtle shadow-sm mb-4 small">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-info-circle-fill fs-5 text-info"></i>
            <strong class="text-info-emphasis">How Feature Toggles Work:</strong>
        </div>
        <ul class="mb-0 ps-3">
            <li><strong>Global Switch (On/Off)</strong>: When turned <em>Off</em>, the feature is disabled globally across all users and pages.</li>
            <li><strong>Faculty Visibility</strong>: <em>Visible</em> (accessible in menus), <em>Hidden</em> (concealed from menus and routes), or <em>Read-Only</em> (viewable but cannot edit).</li>
            <li><strong>Student Visibility</strong>: Controls whether the corresponding card/tab appears in the Student Portal.</li>
            <li><strong>HOD Visibility</strong>: Controls whether HOD has management tabs for this module.</li>
        </ul>
    </div>

    <!-- Toggle Form -->
    <form action="superadminfeatures.php" method="POST">
        <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
        <input type="hidden" name="action" value="save_features">

        <?php foreach ($modulesGrouped as $category => $modules): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light border-bottom d-flex align-items-center justify-content-between py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary text-uppercase px-2 py-1"><?= htmlspecialchars($category); ?></span>
                        <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($category); ?> Modules</h6>
                    </div>
                    <span class="text-muted small"><?= count($modules); ?> module(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted text-uppercase">
                                <tr>
                                    <th style="width: 32%;">Module & Purpose</th>
                                    <th style="width: 15%; text-align: center;">Global Status</th>
                                    <th style="width: 18%;">Faculty Access</th>
                                    <th style="width: 18%;">Student Access</th>
                                    <th style="width: 17%;">HOD Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $mod): 
                                    $key = $mod['module_key'];
                                    $isEnabled = (int)$mod['is_enabled_globally'] === 1;
                                    $facVis = $mod['faculty_visibility'] ?? 'VISIBLE';
                                    $stuVis = $mod['student_visibility'] ?? 'VISIBLE';
                                    $hodVis = $mod['hod_visibility'] ?? 'VISIBLE';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($mod['module_name']); ?></div>
                                            <code class="small text-secondary"><?= htmlspecialchars($key); ?></code>
                                            <?php if (!empty($mod['description'])): ?>
                                                <div class="text-muted small mt-1"><?= htmlspecialchars($mod['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       name="features[<?= htmlspecialchars($key); ?>][enabled]" 
                                                       value="1" 
                                                       id="switch_<?= htmlspecialchars($key); ?>"
                                                       <?= $isEnabled ? 'checked' : ''; ?>>
                                                <label class="form-check-label small d-block" for="switch_<?= htmlspecialchars($key); ?>">
                                                    <span class="badge <?= $isEnabled ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?= $isEnabled ? 'Enabled' : 'Disabled'; ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="features[<?= htmlspecialchars($key); ?>][faculty_vis]" class="form-select form-select-sm">
                                                <option value="VISIBLE" <?= $facVis === 'VISIBLE' ? 'selected' : ''; ?>>Visible</option>
                                                <option value="HIDDEN" <?= $facVis === 'HIDDEN' ? 'selected' : ''; ?>>Hidden</option>
                                                <option value="READONLY" <?= $facVis === 'READONLY' ? 'selected' : ''; ?>>Read-Only</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="features[<?= htmlspecialchars($key); ?>][student_vis]" class="form-select form-select-sm">
                                                <option value="VISIBLE" <?= $stuVis === 'VISIBLE' ? 'selected' : ''; ?>>Visible</option>
                                                <option value="HIDDEN" <?= $stuVis === 'HIDDEN' ? 'selected' : ''; ?>>Hidden</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="features[<?= htmlspecialchars($key); ?>][hod_vis]" class="form-select form-select-sm">
                                                <option value="VISIBLE" <?= $hodVis === 'VISIBLE' ? 'selected' : ''; ?>>Visible</option>
                                                <option value="HIDDEN" <?= $hodVis === 'HIDDEN' ? 'selected' : ''; ?>>Hidden</option>
                                                <option value="READONLY" <?= $hodVis === 'READONLY' ? 'selected' : ''; ?>>Read-Only</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Floating or Sticky Save Bar -->
        <div class="card shadow-sm border-0 sticky-bottom bg-white p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-clock-history me-1"></i>Changes take effect immediately across all sessions.
                </span>
                <div class="d-flex gap-2">
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i>Save All Changes
                    </button>
                </div>
            </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.form-check-input[type="checkbox"]').forEach(function(el) {
        el.addEventListener('change', function() {
            var badge = this.closest('td').querySelector('.badge');
            if (badge) {
                if (this.checked) {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Enabled';
                } else {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'Disabled';
                }
            }
        });
    });
});
</script>


<div class="container my-4"><div class="card"><div class="card-body"><h4>Role action permissions</h4><p>Restrict viewing, editing, approval, or export for a role. Global feature switches and record ownership checks still apply.</p>
<form method="post"><input type="hidden" name="action" value="save_permission"><input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']) ?>">
<label>Feature <select class="form-select" name="module_key"><?php foreach($featureManager->getAllModules() as $module):?><option value="<?= htmlspecialchars($module['module_key']) ?>"><?= htmlspecialchars($module['module_name']) ?></option><?php endforeach;?></select></label>
<label>Role <select class="form-select" name="permission_role"><?php foreach(['faculty','hod','admin','academic_section','student'] as $item):?><option><?= $item ?></option><?php endforeach;?></select></label>
<label>Action <select class="form-select" name="permission_action"><?php foreach(['view','edit','approve','export'] as $item):?><option><?= $item ?></option><?php endforeach;?></select></label>
<label>Permission <select class="form-select" name="is_allowed"><option value="0">Deny</option><option value="1">Allow within feature visibility</option></select></label><button class="btn btn-primary mt-3">Save permission</button></form></div></div></div>

<?php
require_once __DIR__ . '/superadminfooter.php';
?>

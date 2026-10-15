<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if dept_id is not present
if (!isset($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$dept_id = (int)$_POST['dept_id'];
$dept_fullname = $_POST['dept_fullname'] ?? '';
$adminObj = new Admin();

// Handle add faculty submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $result = $adminObj->addFaculty($_POST);
    if ($result['status'] === 1) {
        $succ = "Faculty Added Successfully";
    } else {
        $error = "Error adding faculty. Please try again.";
    }
}

// Handle save faculty order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_save_order'])) {
    $orders = $_POST['faculty_order'] ?? [];
    if (!empty($orders) && is_array($orders)) {
        $result = $adminObj->updateFacultyOrder($dept_id, $orders);
        if ($result['status'] === 1) {
            $succ = "Faculty display order updated successfully! All dropdowns and rosters now reflect this custom order.";
        } else {
            $error = $result['msg'] ?? "Error updating faculty order. Please try again.";
        }
    } else {
        $error = "No faculty order data received.";
    }
}

// Handle reset faculty order to default hierarchy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_reset_order'])) {
    $result = $adminObj->resetFacultyOrderToDefault($dept_id);
    if ($result['status'] === 1) {
        $succ = "Faculty order has been reset to standard designation hierarchy (Professor → Assoc. Prof → Asst. Prof → Adhoc).";
    } else {
        $error = $result['msg'] ?? "Error resetting faculty order.";
    }
}

$faculties = $adminObj->getFacultyByDepartment($dept_id);
?>

<div class="container my-3">
    <?php if (isset($succ)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($succ) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
            <div>
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-people-fill me-2"></i>Department of <?= htmlspecialchars($dept_fullname) ?>
                </h5>
                <small class="text-muted">Manage faculty roster and configure the display order shown in select boxes and pages across the application.</small>
            </div>
            <div>
                <a href="adminfacst.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> All Departments
                </a>
            </div>
        </div>

        <form action="adminfac.php" method="post" id="facultyOrderForm">
            <input type="hidden" name="dept_id" value="<?= htmlspecialchars((string)$dept_id) ?>">
            <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname, ENT_QUOTES) ?>">

            <!-- Order Toolbar -->
            <div class="card-body bg-light border-bottom py-2 px-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" name="btn_save_order" value="1" class="btn btn-sm btn-primary">
                            <i class="bi bi-save2 me-1"></i> Save Faculty Order
                        </button>
                        <button type="submit" name="btn_reset_order" value="1" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reset this department\'s faculty order back to default designation hierarchy?');">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Default
                        </button>
                    </div>
                    <div>
                        <span class="badge bg-white text-muted border px-2 py-1 small">
                            <i class="bi bi-info-circle me-1 text-primary"></i> Use <i class="bi bi-arrow-up-short"></i> / <i class="bi bi-arrow-down-short"></i> or type order numbers, then click "Save Faculty Order"
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="facultyTable">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th style="width: 140px; text-align: center;">Order</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Mobile</th>
                                <th style="width: 100px; text-align: center;">Status</th>
                                <th style="width: 140px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="facultyTableBody">
                            <?php if (!empty($faculties['data'])): ?>
                                <?php 
                                $rowIndex = 1;
                                foreach ($faculties['data'] as $faculty): 
                                    $currOrder = !empty($faculty['sort_order']) ? (int)$faculty['sort_order'] : $rowIndex;
                                ?>
                                    <tr style="color: <?= $faculty['status'] == 1 ? 'inherit' : '#dc3545' ?>;" data-faculty-id="<?= (int)$faculty['id'] ?>">
                                        <td class="text-center">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-1 py-0 btn-move-up" title="Move Up" onclick="moveRow(this, 'up')">
                                                    <i class="bi bi-arrow-up-short fs-6"></i>
                                                </button>
                                                <input type="number" 
                                                       min="1" 
                                                       max="999" 
                                                       class="form-control form-control-sm text-center fw-bold faculty-order-input px-1" 
                                                       name="faculty_order[<?= (int)$faculty['id'] ?>]" 
                                                       value="<?= $currOrder ?>" 
                                                       style="width: 58px;" 
                                                       required>
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-1 py-0 btn-move-down" title="Move Down" onclick="moveRow(this, 'down')">
                                                    <i class="bi bi-arrow-down-short fs-6"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= htmlspecialchars($faculty['username']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($faculty['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($faculty['designation']) ?></td>
                                        <td><?= htmlspecialchars($faculty['mobile']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $faculty['status'] == 1 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> px-2 py-1">
                                                <?= $faculty['status'] == 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewProfile(<?= (int)$faculty['id'] ?>)">
                                                <i class="bi bi-person-gear me-1"></i> Profile
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    $rowIndex++;
                                endforeach; 
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No faculties enrolled in this department yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                <button type="submit" name="btn_save_order" value="1" class="btn btn-primary">
                    <i class="bi bi-save2 me-1"></i> Save Faculty Order
                </button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('addFacultyForm').submit();">
                    <i class="bi bi-person-plus me-1"></i> Add New Faculty
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form for Add New Faculty -->
<form id="addFacultyForm" action="adminaddfaculty.php" method="post" style="display: none;">
    <input type="hidden" name="dept_id" value="<?= htmlspecialchars((string)$dept_id) ?>">
    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname, ENT_QUOTES) ?>">
</form>

<!-- Hidden form for Profile Details navigation -->
<form id="profileNavForm" action="adminfacultyprofile.php" method="post" style="display: none;">
    <input type="hidden" name="faculty_id" id="navFacultyId" value="">
    <input type="hidden" name="dept_id" value="<?= htmlspecialchars((string)$dept_id) ?>">
    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname, ENT_QUOTES) ?>">
</form>

<script>
function viewProfile(facId) {
    document.getElementById('navFacultyId').value = facId;
    document.getElementById('profileNavForm').submit();
}

function moveRow(btn, direction) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('facultyTableBody');
    if (!row || !tbody) return;

    if (direction === 'up') {
        const prev = row.previousElementSibling;
        if (prev) {
            tbody.insertBefore(row, prev);
            renumberInputs();
        }
    } else if (direction === 'down') {
        const next = row.nextElementSibling;
        if (next) {
            tbody.insertBefore(next, row);
            renumberInputs();
        }
    }
}

function renumberInputs() {
    const inputs = document.querySelectorAll('.faculty-order-input');
    inputs.forEach((input, index) => {
        input.value = index + 1;
    });
}
</script>

<?php
require_once("adminfooter.php");
?>
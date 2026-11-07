<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Manage Faculty";
require_once("hod.class.php");
require_once("hodheader.php");

$hodObj = new HOD();

if (!empty($_POST['action']) && $_POST['action'] === 'quick_reset_pwd' && !empty($_POST['faculty_id'])) {
    $facId = (int)$_POST['faculty_id'];
    $res = $hodObj->resetFacPwd($facId, (int)$_SESSION['dept_id']);
    if (!empty($res['status']) && $res['status'] === 1) {
        $succ = "Password reset successfully! Default password is the faculty member's mobile number.";
    } else {
        $error = "Failed to reset password. Please ensure the faculty member belongs to your department and has a valid mobile number.";
    }
}

$faculties = $hodObj->getFacultyByDepartment($_SESSION["dept_id"]);
?>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-badge me-2"></i>Department Faculty Roster</span>
            <span class="badge bg-primary"><?= count($faculties['data'] ?? []) ?> Members</span>
        </div>
        <div class="card-body">

            <?php if (isset($succ)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($succ) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th scope="col" style="width: 70px; text-align: center;">Order</th>
                        <th scope="col">Username</th>
                        <th scope="col">Name</th>
                        <th scope="col">Designation</th>
                        <th scope="col">Mobile</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hIndex = 1;
                    foreach ($faculties['data'] as $faculty): 
                        $displaySeq = !empty($faculty['sort_order']) ? (int)$faculty['sort_order'] : $hIndex;
                    ?>
                        <tr class="<?= $faculty['status'] == 1 ? '' : 'text-muted fst-italic' ?>">
                            <td class="text-center fw-bold text-muted"><?= $displaySeq ?></td>
                            <td><?= htmlspecialchars($faculty['username']) ?></td>
                            <td><?= htmlspecialchars($faculty['name']) ?></td>
                            <td><?= htmlspecialchars($faculty['designation']) ?></td>
                            <td><?= htmlspecialchars($faculty['mobile']) ?></td>
                            <td class="text-capitalize <?= $faculty['status'] == 1 ? 'text-success' : 'text-danger' ?>">
                                <?= $faculty['status'] == 1 ? 'Active' : 'Inactive' ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form action="hodfacultyprofile.php" method="post" class="m-0">
                                        <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $faculty['status'] == 1 ? 'btn-outline-primary' : 'btn-outline-secondary' ?>">
                                            <i class="bi bi-person-gear"></i> Profile
                                        </button>
                                    </form>
                                    <form action="hodviewfaculties.php" method="post" class="m-0" onsubmit="return confirm('Are you sure you want to reset password for <?= htmlspecialchars($faculty['name'], ENT_QUOTES); ?>?\nDefault password will be set to mobile number.');">
                                        <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                        <input type="hidden" name="action" value="quick_reset_pwd">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Reset password to mobile number">
                                            <i class="bi bi-key"></i> Reset Pwd
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        $hIndex++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (isset($succ)): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($succ) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

</div>

<?php
require_once("hodfooter.php");
?>
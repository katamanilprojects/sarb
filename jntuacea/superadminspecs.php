<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Specializations";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions for adding/updating/deleting specializations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['spec_code']) && !empty($_POST['spec_shortname']) && !empty($_POST['spec_fullname'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'spec_code' => $_POST['spec_code'],
            'spec_shortname' => $_POST['spec_shortname'],
            'spec_fullname' => $_POST['spec_fullname'],
            'dept_id' => $_POST['dept_id'],
            'prog_id' => $_POST['prog_id'],
            'status' => $_POST['status'],
        ];

        $result = $superadmin->addOrUpdateSpecialization($data);
        $msg = $result['status'] == 1 ? "Specialization saved successfully." : $result['error'];
    } elseif (!empty($_POST['delete_spec_id'])) {
        $result = $superadmin->deleteSpecialization($_POST['delete_spec_id']);
        $msg = $result['status'] == 1 ? "Specialization deleted successfully." : $result['error'];
    } elseif (!empty($_POST['change_status_spec_id'])) {
        $result = $superadmin->updateSpecializationStatus($_POST['change_status_spec_id'], $_POST['new_status']);
        $msg = $result['status'] == 1 ? "Specialization status updated successfully." : $result['error'];
    }
}

// Fetch all specializations
$specializations = $superadmin->getAllSpecializations();

// Fetch departments and programs for dropdown lists
$departments = $superadmin->getAllDepartments();
$programs = $superadmin->getAllPrograms();

// Determine if the edit form should be displayed
$editSpecId = isset($_GET['edit']) ? $_GET['edit'] : null;
$showEditForm = !empty($editSpecId);

// Find the specialization to be edited if necessary
$editSpecialization = null;
if ($showEditForm) {
    $editSpecialization = array_filter($specializations['data'], function ($spec) use ($editSpecId) {
        return $spec['id'] == $editSpecId;
    });
    $editSpecialization = reset($editSpecialization); // Get the first element if found
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Specializations</div>
        <div class="card-body">
            <button type="button" class="btn btn-primary mb-3" id="addSpecializationBtn" style="display: <?= $showEditForm ? 'none' : 'block'; ?>">Add Specialization</button>

            <div id="editForm" style="display: <?= $showEditForm ? 'block' : 'none'; ?>">
                <form action="superadminspecs.php" method="post">
                    <div class="form-group">
                        <label for="spec_code">Specialization Code:</label>
                        <input type="text" name="spec_code" id="spec_code" class="form-control" required value="<?= $editSpecialization ? $editSpecialization['spec_code'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="spec_shortname">Specialization Short Name:</label>
                        <input type="text" name="spec_shortname" id="spec_shortname" class="form-control" required value="<?= $editSpecialization ? $editSpecialization['spec_shortname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="spec_fullname">Specialization Full Name:</label>
                        <input type="text" name="spec_fullname" id="spec_fullname" class="form-control" required value="<?= $editSpecialization ? $editSpecialization['spec_fullname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="dept_id">Department:</label>
                        <select name="dept_id" id="dept_id" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments['data'] as $dept): ?>
                                <option value="<?= $dept['id']; ?>" <?= $editSpecialization && $editSpecialization['dept_id'] == $dept['id'] ? 'selected' : ''; ?>><?= $dept['dept_shortname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="prog_id">Program:</label>
                        <select name="prog_id" id="prog_id" class="form-control" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs['data'] as $prog): ?>
                                <option value="<?= $prog['id']; ?>" <?= $editSpecialization && $editSpecialization['prog_id'] == $prog['id'] ? 'selected' : ''; ?>><?= $prog['prog_shortname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="1" <?= $editSpecialization && $editSpecialization['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?= $editSpecialization && $editSpecialization['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="id" id="id" value="<?= $editSpecialization ? $editSpecialization['id'] : ''; ?>">
                        <button type="button" class="btn btn-outline-danger mt-3 float-start" id="cancelSpecializationBtn">Cancel</button> &nbsp; 
                        <button type="submit" class="btn btn-primary mt-3">Save Specialization</button>
                    </div>
                </form>
            </div>

            <hr>

            <?php if (!empty($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>

            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Spec. Code</th>
                        <th>Spec. Short Name</th>
                        <th>Spec. Full Name</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($specializations['data'] as $spec): ?>
                    <tr>
                        <td><?= $spec['spec_code']; ?></td>
                        <td><?= htmlspecialchars($spec['spec_shortname']); ?></td>
                        <td><?= htmlspecialchars($spec['spec_fullname']); ?></td>
                        <td><?= $spec['dept_shortname']; ?></td>
                        <td><?= $spec['status'] == 1 ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <a href="superadminspecs.php?edit=<?= $spec['id']; ?>" class="btn btn-primary">Edit</a>
                            <form action="superadminspecs.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($spec['spec_shortname']); ?> ?');">
                                <button type="submit" name="delete_spec_id" value="<?= $spec['id']; ?>" class="btn btn-danger">Delete</button>
                            </form>
                            <form action="superadminspecs.php" method="post" class="d-inline">
                                <input type="hidden" name="change_status_spec_id" value="<?= $spec['id']; ?>">
                                <input type="hidden" name="new_status" value="<?= $spec['status'] == 1 ? 0 : 1; ?>">
                                <button type="submit" class="btn btn-<?= $spec['status'] == 1 ? 'warning' : 'success'; ?>"><?= $spec['status'] == 1 ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('addSpecializationBtn').addEventListener('click', function() {
    document.getElementById('addSpecializationBtn').style.display = 'none';
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('spec_code').value = '';
    document.getElementById('spec_shortname').value = '';
    document.getElementById('spec_fullname').value = '';
    document.getElementById('dept_id').value = '';
    document.getElementById('prog_id').value = '';
    document.getElementById('id').value = '';
});

document.getElementById('cancelSpecializationBtn').addEventListener('click', function() {
    document.getElementById('addSpecializationBtn').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
    document.getElementById('spec_code').value = '';
    document.getElementById('spec_shortname').value = '';
    document.getElementById('spec_fullname')..value = '';
    document.getElementById('dept_id').value = '';
    document.getElementById('prog_id').value = '';
    document.getElementById('id').value = '';
});

</script>

<?php
require_once("superadminfooter.php");
?>
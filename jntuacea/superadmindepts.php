<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Departments";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions for adding/updating/deleting departments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['dept_shortname']) && !empty($_POST['dept_fullname'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'username' => "hod@".strtolower($_POST['dept_shortname']),
            'dept_shortname' => $_POST['dept_shortname'],
            'dept_fullname' => $_POST['dept_fullname'],
            'status' => 1 // Default status to active
        ];

        $result = $superadmin->addOrUpdateDepartment($data);
        $msg = $result['status'] == 1 ? "Department saved successfully." : $result['error'];
    } elseif (!empty($_POST['delete_dept_id'])) {
        $result = $superadmin->deleteDepartment($_POST['delete_dept_id']);
        $msg = $result['status'] == 1 ? "Department deleted successfully." : $result['error'];
    }
}

// Fetch all departments
$departments = $superadmin->getAllDepartments();

// Determine if the edit form should be displayed
$editDeptId = isset($_GET['edit']) ? $_GET['edit'] : null;
$showEditForm = !empty($editDeptId);

// Find the department to be edited if necessary
$editDepartment = null;
if ($showEditForm) {
    $editDepartment = array_filter($departments['data'], function ($dept) use ($editDeptId) {
        return $dept['id'] == $editDeptId;
    });
    $editDepartment = reset($editDepartment); // Get the first element if found
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Departments</div>
        <div class="card-body">
            <button type="button" class="btn btn-primary mb-3" id="addDepartmentBtn" style="display: <?= $showEditForm ? 'none' : 'block'; ?>">Add Department</button>

            <div id="editForm" style="display: <?= $showEditForm ? 'block' : 'none'; ?>">
                <form action="superadmindepts.php" method="post">
                    <div class="form-group">
                        <label for="dept_shortname">Department Short Name:</label>
                        <input type="text" name="dept_shortname" id="dept_shortname" class="form-control" required value="<?= $editDepartment ? $editDepartment['dept_shortname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="dept_fullname">Department Full Name:</label>
                        <input type="text" name="dept_fullname" id="dept_fullname" class="form-control" required value="<?= $editDepartment ? $editDepartment['dept_fullname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="id" id="id" value="<?= $editDepartment ? $editDepartment['id'] : ''; ?>">
                        <button type="button" class="btn btn-outline-danger mt-3 float-start" id="cancelDepartmentBtn">Cancel</button> &nbsp; 
                        <button type="submit" class="btn btn-primary mt-3">Save Department</button>
                    </div>
                </form>
            </div>

            <hr>

            <?php if (!empty($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>

            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Department Short Name</th>
                        <th>Department Full Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments['data'] as $dept): ?>
                    <tr>
                        <td><?= htmlspecialchars($dept['dept_shortname']); ?></td>
                        <td><?= htmlspecialchars($dept['dept_fullname']); ?></td>
                        <td>
                            <a href="superadmindepts.php?edit=<?= $dept['id']; ?>" class="btn btn-primary">Edit</a>
                            <form action="superadmindepts.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($dept['dept_shortname']); ?> ?');">
                                <button type="submit" name="delete_dept_id" value="<?= $dept['id']; ?>" class="btn btn-danger">Delete</button>
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
document.getElementById('addDepartmentBtn').addEventListener('click', function() {
    document.getElementById('addDepartmentBtn').style.display = 'none';
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('dept_shortname').value = '';
    document.getElementById('dept_fullname').value = '';
    document.getElementById('id').value = '';
});
document.getElementById('cancelDepartmentBtn').addEventListener('click', function() {
    document.getElementById('addDepartmentBtn').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
    document.getElementById('dept_shortname').value = '';
    document.getElementById('dept_fullname').value = '';
    document.getElementById('id').value = '';
});

</script>

<?php
require_once("superadminfooter.php");
?>
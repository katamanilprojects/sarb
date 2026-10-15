<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Admins";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions for adding/updating/deleting admins
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['name'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'username' => $_POST['username'],
            'name' => $_POST['name'],
            'mobile' => $_POST['mobile'],
            'email' => $_POST['email'],
            'status' => 1 // Default status to active
        ];

        $result = $superadmin->addOrUpdateAdmin($data);
        $msg = $result['status'] == 1 ? "Admin saved successfully." : $result['error'];
    } elseif (isset($_POST['toggle_status_id'])) {
        $result = $superadmin->toggleAdminStatus($_POST['toggle_status_id'], $_POST['current_status']);
        $msg = $result['status'] == 1 ? "Admin status updated successfully." : $result['error'];
    } elseif (!empty($_POST['reset_password_id'])) {
        $result = $superadmin->resetAdminPassword($_POST['reset_password_id']);
        $msg = $result['status'] == 1 ? "Admin password reset successfully." : $result['error'];
    }
}

// Fetch all admins
$admins = $superadmin->getAllAdmins();

// Determine if the edit form should be displayed
$editAdminId = isset($_GET['edit']) ? $_GET['edit'] : null;
$showEditForm = !empty($editAdminId);

// Find the admin to be edited if necessary
$editAdmin = null;
if ($showEditForm) {
    $editAdmin = array_filter($admins['data'], function ($admin) use ($editAdminId) {
        return $admin['id'] == $editAdminId;
    });
    $editAdmin = reset($editAdmin); // Get the first element if found
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Admins</div>
        <div class="card-body">
            <button type="button" class="btn btn-primary mb-3" id="addAdminBtn" style="display: <?= $showEditForm ? 'none' : 'block'; ?>">Add Admin</button>

            <div id="editForm" style="display: <?= $showEditForm ? 'block' : 'none'; ?>">
                <form action="superadminadmins.php" method="post">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" name="username" id="username" class="form-control" required value="<?= $editAdmin ? $editAdmin['username'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" name="name" id="name" class="form-control" required value="<?= $editAdmin ? $editAdmin['name'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="mobile">Mobile:</label>
                        <input type="text" name="mobile" id="mobile" class="form-control" value="<?= $editAdmin ? $editAdmin['mobile'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= $editAdmin ? $editAdmin['email'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="id" id="id" value="<?= $editAdmin ? $editAdmin['id'] : ''; ?>">
                        <button type="button" class="btn btn-outline-danger mt-3 float-start" id="cancelAdminBtn">Cancel</button> &nbsp; 
                        <button type="submit" class="btn btn-primary mt-3">Save Admin</button>
                    </div>
                </form>
            </div>

            <hr>

            <?php if (!empty($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>

            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins['data'] as $admin): ?>
                    <tr>
                        <td><?= htmlspecialchars($admin['username']); ?></td>
                        <td><?= htmlspecialchars($admin['name']); ?></td>
                        <td><?= htmlspecialchars($admin['mobile']); ?></td>
                        <td><?= htmlspecialchars($admin['email']); ?></td>
                        <td><?= $admin['status'] == 1 ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <a href="superadminadmins.php?edit=<?= $admin['id']; ?>" class="btn btn-primary">Edit</a>
                            <form action="superadminadmins.php" method="post" class="d-inline">
                                <input type="hidden" name="toggle_status_id" value="<?= $admin['id']; ?>">
                                <input type="hidden" name="current_status" value="<?= $admin['status'] == 1 ? 0 : 1; ?>">
                                <button type="submit" class="btn btn-<?= $admin['status'] == 1 ? 'danger' : 'success'; ?>">
                                    <?= $admin['status'] == 1 ? 'Inactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <form action="superadminadmins.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to reset the password for <?= htmlspecialchars($admin['username']); ?> ?');">
                                <button type="submit" name="reset_password_id" value="<?= $admin['id']; ?>" class="btn btn-warning">Reset Password</button>
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
document.getElementById('addAdminBtn').addEventListener('click', function() {
    document.getElementById('addAdminBtn').style.display = 'none';
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('username').value = '';
    document.getElementById('name').value = '';
    document.getElementById('mobile').value = '';
    document.getElementById('email').value = '';
    document.getElementById('id').value = '';
});
document.getElementById('cancelAdminBtn').addEventListener('click', function() {
    document.getElementById('addAdminBtn').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
    document.getElementById('username').value = '';
    document.getElementById('name').value = '';
    document.getElementById('mobile').value = '';
    document.getElementById('email').value = '';
    document.getElementById('id').value = '';
});

</script>

<?php
require_once("superadminfooter.php");
?>
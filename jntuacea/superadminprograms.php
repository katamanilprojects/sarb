<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Programs";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions for adding/updating/deleting programs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['program_code']) && !empty($_POST['prog_shortname']) && !empty($_POST['prog_fullname'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'program_code' => $_POST['program_code'],
            'prog_shortname' => $_POST['prog_shortname'],
            'prog_fullname' => $_POST['prog_fullname'],
            'program_level' => $_POST['program_level'] ?? 'UG'
        ];

        $result = $superadmin->addOrUpdateProgram($data);
        $msg = $result['status'] == 1 ? "Program saved successfully." : $result['error'];
    } elseif (!empty($_POST['delete_prog_id'])) {
        $result = $superadmin->deleteProgram($_POST['delete_prog_id']);
        $msg = $result['status'] == 1 ? "Program deleted successfully." : $result['error'];
    }
}

// Fetch all programs
$programs = $superadmin->getAllPrograms();

// Determine if the edit form should be displayed
$editProgId = isset($_GET['edit']) ? $_GET['edit'] : null;
$showEditForm = !empty($editProgId);

// Find the program to be edited if necessary
$editProgram = null;
if ($showEditForm) {
    $editProgram = array_filter($programs['data'], function ($prog) use ($editProgId) {
        return $prog['id'] == $editProgId;
    });
    $editProgram = reset($editProgram); // Get the first element if found
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Programs</div>
        <div class="card-body">
            <button type="button" class="btn btn-primary mb-3" id="addProgramBtn" style="display: <?= $showEditForm ? 'none' : 'block'; ?>">Add Program</button>

            <div id="editForm" style="display: <?= $showEditForm ? 'block' : 'none'; ?>">
                <form action="superadminprograms.php" method="post">
                    <div class="form-group">
                        <label for="program_code">Program Code:</label>
                        <input type="text" name="program_code" id="program_code" class="form-control" required value="<?= $editProgram ? $editProgram['program_code'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="prog_shortname">Program Short Name:</label>
                        <input type="text" name="prog_shortname" id="prog_shortname" class="form-control" required value="<?= $editProgram ? $editProgram['prog_shortname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="prog_fullname">Program Full Name:</label>
                        <input type="text" name="prog_fullname" id="prog_fullname" class="form-control" required value="<?= $editProgram ? $editProgram['prog_fullname'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="program_level">Program Level (AICTE / NBA):</label>
                        <select name="program_level" id="program_level" class="form-select">
                            <option value="UG" <?= ($editProgram && ($editProgram['program_level'] ?? 'UG') == 'UG') ? 'selected' : ''; ?>>UG - Under Graduate (12 POs)</option>
                            <option value="PG" <?= ($editProgram && ($editProgram['program_level'] ?? '') == 'PG') ? 'selected' : ''; ?>>PG - Post Graduate (11 POs)</option>
                            <option value="PHD" <?= ($editProgram && ($editProgram['program_level'] ?? '') == 'PHD') ? 'selected' : ''; ?>>PHD - Doctoral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="id" id="id" value="<?= $editProgram ? $editProgram['id'] : ''; ?>">
                        <button type="button" class="btn btn-outline-danger mt-3 float-start" id="cancelProgramBtn">Cancel</button> &nbsp; 
                        <button type="submit" class="btn btn-primary mt-3">Save Program</button>
                    </div>
                </form>
            </div>

            <hr>

            <?php if (!empty($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>

            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Program Code</th>
                        <th>Program Short Name</th>
                        <th>Program Full Name</th>
                        <th>Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs['data'] as $prog): ?>
                    <tr>
                        <td><?= $prog['program_code']; ?></td>
                        <td><?= htmlspecialchars($prog['prog_shortname']); ?></td>
                        <td><?= htmlspecialchars($prog['prog_fullname']); ?></td>
                        <td><?php
                                $lvl = $prog['program_level'] ?? 'UG';
                                $badgeColor = match($lvl) { 'PG' => 'info', 'PHD' => 'dark', default => 'primary' };
                            ?><span class="badge bg-<?= $badgeColor ?>"><?= htmlspecialchars($lvl) ?></span>
                            <small class="text-muted ms-1"><?= htmlspecialchars(Programs::getLevelLabel($lvl)) ?></small></td>
                        <td>
                            <a href="superadminprograms.php?edit=<?= $prog['id']; ?>" class="btn btn-primary">Edit</a>
                            <form action="superadminprograms.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($prog['prog_shortname']); ?> ?');">
                                <button type="submit" name="delete_prog_id" value="<?= $prog['id']; ?>" class="btn btn-danger">Delete</button>
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
document.getElementById('addProgramBtn').addEventListener('click', function() {
    document.getElementById('addProgramBtn').style.display = 'none';
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('program_code').value = '';
    document.getElementById('prog_shortname').value = '';
    document.getElementById('prog_fullname').value = '';
    document.getElementById('id').value = '';
    document.getElementById('program_level').value = 'UG';
});
document.getElementById('cancelProgramBtn').addEventListener('click', function() {
    document.getElementById('addProgramBtn').style.display = 'block';
    document.getElementById('editForm').style.display = 'none';
    document.getElementById('program_code').value = '';
    document.getElementById('prog_shortname').value = '';
    document.getElementById('prog_fullname').value = '';
    document.getElementById('id').value = '';
    document.getElementById('program_level').value = 'UG';
});

</script>

<?php
require_once("superadminfooter.php");
?>
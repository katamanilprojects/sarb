<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Academic Years";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_or_update') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'acad_year' => $_POST['acad_year'],
            'status' => $_POST['status'] ?? 0
        ];
        $result = $superadmin->addOrUpdateAcademicYear($data);
        $msg = $result['message'] ?? $result['error'];

    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $result = $superadmin->toggleAcademicYearStatus($id, $status);
        $msg = $result['message'] ?? $result['error'];
    }
}

$academic_years = $superadmin->getAllAcademicYears()['data'];
$edit_year = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($academic_years as $year) {
        if ($year['id'] == $edit_id) {
            $edit_year = $year;
            break;
        }
    }
}
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Academic Years</div>
        <div class="card-body">

            <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

            <!-- Add/Edit Form -->
            <form method="post" action="superadminacademicyears.php" class="mb-4">
                <input type="hidden" name="action" value="add_or_update">
                <input type="hidden" name="id" value="<?= $edit_year['id'] ?? '' ?>">
                <div class="row">
                    <div class="col-md-5">
                        <input type="text" name="acad_year" class="form-control" placeholder="e.g., 2024-2025" value="<?= $edit_year['acad_year'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-5">
                        <select name="status" class="form-select">
                            <option value="1" <?= (isset($edit_year) && $edit_year['status'] == 1) ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= (isset($edit_year) && $edit_year['status'] == 0) ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?= isset($edit_year) ? 'Update' : 'Add' ?></button>
                    </div>
                </div>
            </form>

            <!-- Academic Years Table -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($academic_years as $year): ?>
                        <tr>
                            <td><?= htmlspecialchars($year['acad_year']) ?></td>
                            <td><?= $year['status'] ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <a href="superadminacademicyears.php?edit=<?= $year['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="post" action="superadminacademicyears.php" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $year['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $year['status'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm btn-<?= $year['status'] ? 'warning' : 'success' ?>">
                                        <?= $year['status'] ? 'Inactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("superadminfooter.php"); ?>

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "superadmindps";
require_once("superadminheader.php");
require_once("superadminmenu.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

// Fetch all departments
$departments = $superadmin->getAllDepartments();

// Fetch all programs
$programs = $superadmin->getAllPrograms();

// Fetch all specializations
$specializations = $superadmin->getAllSpecializations();

?>
<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            Departments
        </div>
        <div class="card-body">
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Department Short Name</th>
                        <th>Department Full Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments['data'] as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['dept_shortname']); ?></td>
                            <td><?= htmlspecialchars($dept['dept_fullname']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <div class="card">
        <div class="card-header">
            Programs
        </div>
        <div class="card-body">
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Program Code</th>
                        <th>Program Short Name</th>
                        <th>Program Full Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs['data'] as $prog): ?>
                        <tr>
                            <td><?= $prog['program_code']; ?></td>
                            <td><?= htmlspecialchars($prog['prog_shortname']); ?></td>
                            <td><?= htmlspecialchars($prog['prog_fullname']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br>
    <div class="card">
        <div class="card-header">
            Specializations
        </div>
        <div class="card-body">
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Spec. Code</th>
                        <th>Spec. Short Name</th>
                        <th>Spec. Full Name</th>
                        <th>Department</th>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once("superadminfooter.php");
?>
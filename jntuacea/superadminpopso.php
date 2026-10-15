<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage POs/PSOs";
require_once("superadminheader.php");
require_once(__DIR__ . "/includes/popso_config.php");
require_once(__DIR__ . "/includes/popso_handlers.php");

// Get form data
$acad_year = $_POST['acad_year'] ?? '';
$regulation = strtoupper($_POST['regulation']) ?? '';
$prog_id = $_POST['prog_id'] ?? '';
$dept_id = $_POST['dept_id'] ?? '';
$spec_ids = $_POST['spec_ids'] ?? [];

// Handle form submissions
extract(handleFormSubmission($superadmin));

// Determine active tabs
$tab_department_active = ($active_tab == 'department') ? 'active show' : '';
$tab_program_active = ($active_tab == 'program') ? 'active show' : '';
$tab_copy_active = ($active_tab == 'copy') ? 'active show' : '';
$tab_overview_active = ($active_tab == 'overview') ? 'active show' : '';
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">Manage Program Outcomes (POs) and Program Specific Outcomes (PSOs)</div>
        <div class="card-body">

            <?php if (!empty($msg)) echo "<div class='alert alert-success'>" . htmlspecialchars($msg) . "</div>"; ?>
            <?php if (!empty($errmsg)) echo "<div class='alert alert-danger'>" . htmlspecialchars($errmsg) . "</div>"; ?>

            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab_overview_active; ?>" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" onclick="setActiveTab('overview');">Overview</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab_department_active; ?>" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button" role="tab" onclick="setActiveTab('department');">Department-wise</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab_program_active; ?>" id="program-tab" data-bs-toggle="tab" data-bs-target="#program" type="button" role="tab" onclick="setActiveTab('program');">Program-wise POs</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab_copy_active; ?>" id="copy-tab" data-bs-toggle="tab" data-bs-target="#copy" type="button" role="tab" onclick="setActiveTab('copy');">Copy from Previous Year</button>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                <?php require __DIR__ . '/views/popso_overview_tab.php'; ?>
                <?php require __DIR__ . '/views/popso_department_tab.php'; ?>
                <?php require __DIR__ . '/views/popso_program_tab.php'; ?>
                <?php require __DIR__ . '/views/popso_copy_tab.php'; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script>
    activeTab = '<?= $active_tab; ?>';
    window.selectedSpecIds = <?= json_encode($spec_ids, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="js/popso.js"></script>

<?php require_once("superadminfooter.php"); ?>

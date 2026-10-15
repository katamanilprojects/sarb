<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Home";
require_once("superadminheader.php");
require_once("superadmin.class.php");
require_once("services/FeatureManager.php");
require_once("services/BatchOBEService.php");

$superadmin = new SuperAdmin();
$featureManager = \Services\FeatureManager::getInstance();
$batchService = \Services\BatchOBEService::getInstance();

$depts = $superadmin->getAllDepartments();
$programs = $superadmin->getAllPrograms();
$specs = $superadmin->getAllSpecializations();
$classes = $superadmin->getAllClasses();
$admins = $superadmin->getAllAdmins();
$regulations = $superadmin->getAllRegulations();
$acadYears = $superadmin->getActiveAcademicYears();
$modules = $featureManager->getAllModules();
$batches = $batchService->getBatches();

$deptCount = count($depts['data'] ?? []);
$progCount = count($programs['data'] ?? []);
$specCount = count($specs['data'] ?? []);
$classCount = count($classes['data'] ?? []);
$adminCount = count($admins['data'] ?? []);
$regCount = count($regulations['data'] ?? []);
$acadYearCount = count($acadYears['data'] ?? []);
$moduleCount = count($modules);
$batchCount = count($batches);
?>
<div class="container my-4">
    <!-- Welcome Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="bi bi-shield-check text-primary me-2"></i>Institutional Control Center
                    </h4>
                    <p class="text-muted mb-0 small">
                        Manage central university academic structures, degree regulations, bell schedules, and system-wide feature toggles.
                    </p>
                </div>
                <div class="d-flex gap-2"><a href="superadminattainmentsettings.php" class="btn btn-outline-primary btn-sm">Attainment profiles</a><a href="superadminaiproviders.php" class="btn btn-outline-secondary btn-sm">AI providers</a>
                    <a href="superadminfeatures.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-toggles me-1"></i> Feature Toggles
                    </a>
                    <a href="superadminacademicsettings.php" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-sliders me-1"></i> Academic Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: Academic Structure -->
    <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
        <i class="bi bi-diagram-3 me-1"></i> Academic Structure Masters
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-primary h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small opacity-75">Departments</span>
                            <i class="bi bi-building fs-4 opacity-75"></i>
                        </div>
                        <h3 class="fw-bold mb-0"><?= $deptCount ?></h3>
                    </div>
                    <div class="mt-3">
                        <a href="superadmindepts.php" class="btn btn-light btn-sm w-100 fw-semibold">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-success h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small opacity-75">Programs</span>
                            <i class="bi bi-mortarboard fs-4 opacity-75"></i>
                        </div>
                        <h3 class="fw-bold mb-0"><?= $progCount ?></h3>
                    </div>
                    <div class="mt-3">
                        <a href="superadminprograms.php" class="btn btn-light btn-sm w-100 fw-semibold">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-info h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small opacity-75">Specializations</span>
                            <i class="bi bi-book fs-4 opacity-75"></i>
                        </div>
                        <h3 class="fw-bold mb-0"><?= $specCount ?></h3>
                    </div>
                    <div class="mt-3">
                        <a href="superadminspecs.php" class="btn btn-light btn-sm w-100 fw-semibold">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-warning h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small opacity-75">Classes</span>
                            <i class="bi bi-person-workspace fs-4 opacity-75"></i>
                        </div>
                        <h3 class="fw-bold mb-0"><?= $classCount ?></h3>
                    </div>
                    <div class="mt-3">
                        <a href="superadminclasses.php" class="btn btn-light btn-sm w-100 fw-semibold">Manage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Batch-Centric Governance & Full OBE Hierarchy -->
    <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
        <i class="bi bi-people-fill me-1 text-primary"></i> Batch-Centric Governance & Full OBE Hierarchy
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Student Batches</span>
                            <h3 class="fw-bold text-primary mb-0"><?= $batchCount ?> Cohorts</h3>
                            <small class="text-muted">Permanent admission-to-graduation cohorts</small>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                            <i class="bi bi-people-fill fs-2"></i>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="superadminbatches.php" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-gear me-1"></i> Configure Batches
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Full-Cycle OBE Articulation</span>
                            <h4 class="fw-bold text-success mb-0">Vision &rarr; Mission &rarr; PEO &rarr; PO</h4>
                            <small class="text-muted">Multi-level articulation & attainment backtracking</small>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                            <i class="bi bi-diagram-3-fill fs-2"></i>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="superadminbatchobe.php" class="btn btn-success btn-sm w-100 fw-semibold">
                            <i class="bi bi-graph-up-arrow me-1"></i> Open OBE Cockpit
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Examinations, Student Dossier & Statutory Certificates -->
    <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
        <i class="bi bi-file-earmark-spreadsheet me-1 text-primary"></i> Examinations, Student Dossier & Statutory Certificates
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Examination Publishing</span>
                            <h4 class="fw-bold text-primary mb-0">Grade Cards & SEE Sync</h4>
                            <small class="text-muted">Bulk CSV results upload & 1-click SEE ingestion</small>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                            <i class="bi bi-file-earmark-spreadsheet fs-2"></i>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="academicsectionresults.php" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Manage Exam Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Student Dossier & Custody</span>
                            <h4 class="fw-bold text-warning mb-0">Vault & Certificates</h4>
                            <small class="text-muted">Custodial, Bonafide, Study/Conduct, and TC issuance</small>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                            <i class="bi bi-journal-bookmark-fill fs-2"></i>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="adminstudentprofiles.php" class="btn btn-outline-primary btn-sm flex-fill fw-semibold">
                            <i class="bi bi-person-badge me-1"></i> Profiles & Vault
                        </a>
                        <a href="admincustodyledger.php" class="btn btn-outline-warning text-dark btn-sm flex-fill fw-semibold">
                            <i class="bi bi-award me-1"></i> Custody Ledger
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Policy & System Governance -->
    <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
        <i class="bi bi-shield-lock me-1"></i> Policy & Governance
    </h6>
    <div class="row g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-uppercase small">Feature Toggles</span>
                            <i class="bi bi-toggles fs-4 text-primary"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-0"><?= $moduleCount ?></h3>
                        <small class="text-muted">Active Modules</small>
                    </div>
                    <div class="mt-3">
                        <a href="superadminfeatures.php" class="btn btn-outline-primary btn-sm w-100">Configure Modules</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-uppercase small">Regulations</span>
                            <i class="bi bi-journal-text fs-4 text-success"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-0"><?= $regCount ?></h3>
                        <small class="text-muted">Defined Regulations</small>
                    </div>
                    <div class="mt-3">
                        <a href="superadminregulations.php" class="btn btn-outline-success btn-sm w-100">View Regulations</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100 shadow-sm border-0 bg-white">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted text-uppercase small">Academic Years</span>
                            <i class="bi bi-calendar-event fs-4 text-info"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-0"><?= $acadYearCount ?></h3>
                        <small class="text-muted">Active Sessions</small>
                    </div>
                    <div class="mt-3">
                        <a href="superadminacademicyears.php" class="btn btn-outline-info btn-sm w-100">Manage Years</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-danger h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small opacity-75">Admins</span>
                            <i class="bi bi-people fs-4 opacity-75"></i>
                        </div>
                        <h3 class="fw-bold mb-0"><?= $adminCount ?></h3>
                        <small class="opacity-75">System Administrators</small>
                    </div>
                    <div class="mt-3">
                        <a href="superadminadmins.php" class="btn btn-light btn-sm w-100 fw-semibold">Manage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("superadminfooter.php");
?>
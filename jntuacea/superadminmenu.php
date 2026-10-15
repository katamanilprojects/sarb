<?php
/**
 * Superadmin Navigation Menu
 * Reorganized into 4 cohesive, mobile-responsive Bootstrap 5 dropdowns
 */
$currentPage = $page_title ?? '';

$isAcademicStructureActive = in_array($currentPage, [
    'Departments', 'Manage Departments',
    'Programs', 'Manage Programs',
    'Specializations', 'Manage Specializations',
    'superadmindps', 'Academic Directory'
]);

$isCurriculumClassesActive = in_array($currentPage, [
    'Classes', 'Manage Classes',
    'Program Classes', 'Manage Program Classes',
    'Student Batches',
    'Batch OBE Setup',
    'Class Timings', 'Manage Class Timing Templates',
    'POs/PSOs', 'Manage POs/PSOs'
]);

$isPolicyTermsActive = in_array($currentPage, [
    'Academic Years', 'Manage Academic Years',
    'Regulations', 'Manage Regulations',
    'Academic Regulations & Settings',
    'Attendance Rules', 'Manage Attendance Rules'
]);

$isGovernanceActive = in_array($currentPage, [
    'Feature Management',
    'Admins', 'Manage Admins',
    'Change Pwd'
]);
?>
<div class="container dontprint mb-3">
    <div class="row g-1">
        <!-- 1. Home -->
        <div class="col-6 col-md-2 p-0">
            <a href="superadminhome.php" class="w-100 btn <?= ($currentPage === 'Home') ? 'btn-success' : 'btn-outline-primary'; ?>">
                <i class="bi bi-house-door me-1"></i> Home
            </a>
        </div>

        <!-- 2. Academic Structure Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isAcademicStructureActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="structureDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-diagram-3 me-1"></i> Structure
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="structureDropdown">
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Departments', 'Manage Departments']) ? 'active' : ''; ?>" href="superadmindepts.php"><i class="bi bi-building me-2"></i> Departments</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Programs', 'Manage Programs']) ? 'active' : ''; ?>" href="superadminprograms.php"><i class="bi bi-mortarboard me-2"></i> Degree Programs</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Specializations', 'Manage Specializations']) ? 'active' : ''; ?>" href="superadminspecs.php"><i class="bi bi-book me-2"></i> Specializations / Branches</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['superadmindps', 'Academic Directory']) ? 'active' : ''; ?>" href="superadmindps.php"><i class="bi bi-card-checklist me-2"></i> Academic Directory (Depts/Progs/Specs)</a></li>
                </ul>
            </div>
        </div>

        <!-- 3. Curriculum & Classes Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isCurriculumClassesActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="classesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-journal-bookmark me-1"></i> Classes & OBE
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="classesDropdown">
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Program Classes', 'Manage Program Classes']) ? 'active' : ''; ?>" href="superadminprogramclasses.php"><i class="bi bi-grid-3x3 me-2"></i> Batch Program Classes (Bulk Setup)</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Classes', 'Manage Classes']) ? 'active' : ''; ?>" href="superadminclasses.php"><i class="bi bi-person-workspace me-2"></i> Individual Section Classes</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Student Batches']) ? 'active' : ''; ?>" href="superadminbatches.php"><i class="bi bi-people me-2 text-primary"></i> Student Batches (Cohorts)</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Batch OBE Setup']) ? 'active' : ''; ?>" href="superadminbatchobe.php"><i class="bi bi-diagram-3 me-2 text-success"></i> Batch OBE (Vision, PEOs, Attainment)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Class Timings', 'Manage Class Timing Templates']) ? 'active' : ''; ?>" href="superadminclasstimings.php"><i class="bi bi-clock-history me-2"></i> Bell Timings & Hour Templates</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['POs/PSOs', 'Manage POs/PSOs']) ? 'active' : ''; ?>" href="superadminpopso.php"><i class="bi bi-bullseye me-2"></i> Program Outcomes & PSOs (NBA)</a></li>
                </ul>
            </div>
        </div>

        <!-- 4. Policy & Academic Terms Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isPolicyTermsActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="policyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-sliders me-1"></i> Policy & Terms
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="policyDropdown">
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Academic Years', 'Manage Academic Years']) ? 'active' : ''; ?>" href="superadminacademicyears.php"><i class="bi bi-calendar-event me-2"></i> Academic Years</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Regulations', 'Manage Regulations']) ? 'active' : ''; ?>" href="superadminregulations.php"><i class="bi bi-journal-text me-2"></i> Academic Regulations</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Academic Regulations & Settings') ? 'active' : ''; ?>" href="superadminacademicsettings.php"><i class="bi bi-gear-wide-connected me-2"></i> Active Term Settings & Dates</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Attendance Rules', 'Manage Attendance Rules']) ? 'active' : ''; ?>" href="superadminattendancerules.php"><i class="bi bi-shield-check me-2"></i> Attendance & Condonation Rules</a></li>
                </ul>
            </div>
        </div>

        <!-- 5. System Governance Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isGovernanceActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="governanceDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-shield-lock me-1"></i> Governance
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="governanceDropdown">
                    <li><a class="dropdown-item <?= ($currentPage === 'Feature Management') ? 'active' : ''; ?>" href="superadminfeatures.php"><i class="bi bi-toggles me-2 text-primary"></i> Feature Toggles & Role Matrix</a></li>
                    <li><a class="dropdown-item <?= in_array($currentPage, ['Admins', 'Manage Admins']) ? 'active' : ''; ?>" href="superadminadmins.php"><i class="bi bi-people me-2"></i> System Administrators</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Student Profiles & Dossier') ? 'active' : ''; ?>" href="adminstudentprofiles.php"><i class="bi bi-person-badge me-2 text-success"></i> Student Profiles & Vault</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Certificates Ledger') ? 'active' : ''; ?>" href="admincustodyledger.php"><i class="bi bi-journal-bookmark me-2 text-warning"></i> Custody & Certificates Ledger</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Exam Results Management') ? 'active' : ''; ?>" href="academicsectionresults.php"><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Exam Results Publication</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Change Pwd') ? 'active' : ''; ?>" href="superadminchgpwd.php"><i class="bi bi-key me-2"></i> Change Password</a></li>
                </ul>
            </div>
        </div>

        <!-- 6. Logout -->
        <div class="col-6 col-md-2 p-0">
            <a href="logout.php" class="w-100 btn btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>
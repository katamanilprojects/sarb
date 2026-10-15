<?php
/**
 * Admin (Principal) Navigation Menu
 * Reorganized into cohesive, mobile-responsive Bootstrap 5 dropdowns
 * Preserves Departments as the operational hub for Faculty and Classes/Students
 */
$currentPage = $page_title ?? '';

$isAttendanceActive = in_array($currentPage, [
    'View Attendance'
]);

$isQualityActive = in_array($currentPage, [
    'Student Feedback Submission Status',
    'Admin - View Feedback Reports',
    'CIA Analysis', 'Edit',
    'Publish Results', 'Student Profiles & Dossier', 'Certificates Ledger', 'Examination Results'
]);

$isAccountActive = in_array($currentPage, [
    'Reset Student Pwd',
    'Reset Faculty Pwd',
    'Change Pwd'
]);
?>
<div class="container dontprint mb-3">
    <div class="row g-1">
        <!-- 1. Home -->
        <div class="col-6 col-md-2 p-0">
            <a href="adminhome.php" class="w-100 btn <?= ($currentPage === 'Home') ? 'btn-success' : 'btn-outline-primary'; ?>">
                <i class="bi bi-house-door me-1"></i> Home
            </a>
        </div>

        <!-- 2. Departments Operations Hub (Principal Overview) -->
        <div class="col-6 col-md-2 p-0">
            <a href="adminfacst.php" class="w-100 btn <?= ($currentPage === 'Departments' || $currentPage === 'Upload Students') ? 'btn-success' : 'btn-outline-primary'; ?>" title="Department-wise HOD, Faculty, Classes & Student Enrollment">
                <i class="bi bi-building me-1"></i> Departments
            </a>
        </div>

        <!-- 3. Attendance Monitoring Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isAttendanceActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="attDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-calendar-check me-1"></i> Attendance
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="attDropdown">
                    <li><a class="dropdown-item" href="adminshowfacattendance.php"><i class="bi bi-person-check me-2"></i> Attendance by Faculty</a></li>
                    <li><a class="dropdown-item" href="adminshowclsattendance.php"><i class="bi bi-people me-2"></i> Attendance by Class</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="adminshowallclsattendance.php"><i class="bi bi-bar-chart-line me-2"></i> Overall Class Attendance Summary</a></li>
                </ul>
            </div>
        </div>

        <!-- 4. Quality & Academic Ops Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isQualityActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="qualityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-graph-up-arrow me-1"></i> Quality & Ops
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="qualityDropdown">
                    <li><a class="dropdown-item <?= ($currentPage === 'Student Feedback Submission Status') ? 'active' : ''; ?>" href="adminshowfeedbackstatus.php"><i class="bi bi-check2-circle me-2 text-primary"></i> Survey Submission Status [Live Monitor]</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Admin - View Feedback Reports') ? 'active' : ''; ?>" href="adminviewfeedback.php"><i class="bi bi-chat-dots me-2"></i> Course Feedback Survey Reports</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'CIA Analysis' || $currentPage === 'Edit') ? 'active' : ''; ?>" href="adminciaanalysis2.php"><i class="bi bi-pie-chart me-2"></i> CIA Performance & Attainment</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Publish Results') ? 'active' : ''; ?>" href="academicsectionresults.php"><i class="bi bi-file-earmark-spreadsheet me-2"></i> Exam Results Overview</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Student Profiles & Dossier') ? 'active' : ''; ?>" href="adminstudentprofiles.php"><i class="bi bi-person-badge me-2"></i> Student Dossier & Profiles</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Certificates Ledger') ? 'active' : ''; ?>" href="admincustodyledger.php"><i class="bi bi-journal-bookmark me-2"></i> Custodial & Certificates Ledger</a></li>
                </ul>
            </div>
        </div>

        <!-- 5. Account Support Dropdown -->
        <div class="col-6 col-md-2 p-0">
            <div class="dropdown w-100">
                <button class="w-100 btn <?= $isAccountActive ? 'btn-success' : 'btn-outline-primary'; ?> dropdown-toggle" 
                        type="button" id="resetPwdDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-key me-1"></i> Accounts
                </button>
                <ul class="dropdown-menu w-100 shadow" aria-labelledby="resetPwdDropdown">
                    <li><a class="dropdown-item <?= ($currentPage === 'Reset Student Pwd') ? 'active' : ''; ?>" href="adminresetstudentpwd.php"><i class="bi bi-person me-2"></i> Reset Student Password</a></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Reset Faculty Pwd') ? 'active' : ''; ?>" href="adminresetfacultypwd.php"><i class="bi bi-briefcase me-2"></i> Reset Faculty Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= ($currentPage === 'Change Pwd') ? 'active' : ''; ?>" href="adminchgpwd.php"><i class="bi bi-shield-lock me-2"></i> Change My Password</a></li>
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
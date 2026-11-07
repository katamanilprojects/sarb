<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
$page_title = "Home";
require_once("adminheader.php");
require_once("admin.class.php");
require_once __DIR__ . "/services/FeatureManager.php";

$adminObj = new Admin();
$depts = $adminObj->getAllDepartments();
$deptCount = count($depts['data'] ?? []);
?>

<div class="container my-4">
    <!-- Executive Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="bi bi-briefcase text-primary me-2"></i>Principal's Executive Dashboard
                    </h4>
                    <p class="text-muted mb-0 small">
                        Institutional oversight of academic departments, teaching faculty, student enrollments, attendance delivery, and survey quality assurance.
                    </p>
                </div>
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="bi bi-mortarboard me-1"></i>Principal Office
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Hub Grid -->
    <div class="row g-3 mb-4">
        <!-- 1. Department Operations -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
                <div class="card-body d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small fw-bold text-primary">Departments</span>
                            <i class="bi bi-building fs-4 text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= $deptCount ?> Units</h4>
                        <p class="text-muted small mb-0">HODs, Faculty Workload & Student Enrollment.</p>
                    </div>
                    <div class="mt-3">
                        <a href="adminfacst.php" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                            Open Department Hub
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Attendance Monitoring -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
                <div class="card-body d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small fw-bold text-success">Attendance</span>
                            <i class="bi bi-calendar-check fs-4 text-success"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Academic Records</h5>
                        <p class="text-muted small mb-0">Faculty-wise, class-wise, & college aggregates.</p>
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-1">
                        <a href="adminshowfacattendance.php" class="btn btn-outline-success btn-sm flex-fill">By Faculty</a>
                        <a href="adminshowallclsattendance.php" class="btn btn-outline-success btn-sm flex-fill">Overall %</a>
                        <a href="viewtimetable.php" class="btn btn-outline-primary btn-sm flex-fill" title="Campus Timetable & Room Usage"><i class="bi bi-clock-history me-1"></i>Timetable</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Quality & Surveys -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
                <div class="card-body d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small fw-bold text-info">Quality & Surveys</span>
                            <i class="bi bi-graph-up-arrow fs-4 text-info"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Student Feedback</h5>
                        <p class="text-muted small mb-0">Live survey response monitor & CIA analysis.</p>
                    </div>
                    <div class="mt-3 d-flex gap-1">
                        <a href="adminshowfeedbackstatus.php" class="btn btn-outline-info btn-sm flex-fill">Live Status</a>
                        <a href="adminviewfeedback.php" class="btn btn-outline-info btn-sm flex-fill">Reports</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Account Support -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-secondary">
                <div class="card-body d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase small fw-bold text-secondary">Accounts</span>
                            <i class="bi bi-key fs-4 text-secondary"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Password Support</h5>
                        <p class="text-muted small mb-0">Reset student or faculty credentials.</p>
                    </div>
                    <div class="mt-3 d-flex gap-1">
                        <a href="adminresetstudentpwd.php" class="btn btn-outline-secondary btn-sm flex-fill">Student Pwd</a>
                        <a href="adminresetfacultypwd.php" class="btn btn-outline-secondary btn-sm flex-fill">Faculty Pwd</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exams, Student Dossier & Statutory Certificates Strip -->
    <div class="card shadow-sm border-0 mb-4 border-start border-4 border-warning">
        <div class="card-body p-3">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center">
                        <div class="me-3 text-warning">
                            <i class="bi bi-mortarboard-fill display-6"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Examinations, Student Dossier & Statutory Certificates</h5>
                            <p class="text-muted small mb-0">Publish end-semester exam results, inspect student credentials vault, track physical custodial certificates, and approve statutory certificates.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="academicsectionresults.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exam Results
                        </a>
                        <?php if (\FeatureManager::isModuleEnabled('MOD_STUDENT_PROFILE')): ?>
                        <a href="adminstudentprofiles.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-person-badge me-1"></i> Student Profiles & Vault
                        </a>
                        <?php endif; ?>
                        <?php if (\FeatureManager::isModuleEnabled('MOD_CERTIFICATES')): ?>
                        <a href="admincustodyledger.php" class="btn btn-outline-warning text-dark btn-sm">
                            <i class="bi bi-journal-bookmark me-1"></i> Custodial & Certificates Ledger
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrative Workflow Notes (Collapsible) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold text-dark">
                <i class="bi bi-info-circle me-2 text-primary"></i>Principal's Operational Guide
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark"><i class="bi bi-building me-1 text-primary"></i> 1. Department Operations Workflow</h6>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>Each academic department manages its own <strong>HOD leadership</strong>, <strong>teaching faculty roster</strong>, and <strong>class cohorts</strong>.</li>
                        <li>To onboard new instructors or manage designations, navigate to <strong>Departments</strong> and click <span class="badge bg-success-subtle text-success border">Faculty</span>.</li>
                        <li>To verify student rosters or bulk-upload lateral entry students via Excel, click <span class="badge bg-primary-subtle text-primary border">Classes</span>.</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark"><i class="bi bi-graph-up-arrow me-1 text-info"></i> 2. Feedback & Quality Oversight</h6>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>When feedback survey periods are activated, open <strong>Survey Submission Status</strong> to monitor student completion rates per subject.</li>
                        <li>Inspect departmental continuous assessment performance via <strong>CIA Analysis</strong>.</li>
                        <li>Download institutional accreditation dossiers via <strong>Course Feedback Reports</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>
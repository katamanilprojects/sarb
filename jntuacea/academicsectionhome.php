<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}
require_once __DIR__ . '/services/FeatureManager.php';
$page_title = "Home";
require_once("academicsectionheader.php");

$showSeeMarks     = \Services\FeatureManager::canAction('MOD_SEE_MARKS', 'view', 'academic_section');
$showAiAssist     = \Services\FeatureManager::canAction('MOD_AI_ASSISTANCE', 'view', 'academic_section');
$showResults      = \FeatureManager::canRoleAccess('MOD_RESULTS_PUBLISH', 'academic_section');
$showProfiles     = \FeatureManager::canRoleAccess('MOD_STUDENT_PROFILE', 'academic_section');
$showCertificates = \FeatureManager::canRoleAccess('MOD_CERTIFICATES', 'academic_section');
$showAttendance   = \FeatureManager::canRoleAccess('MOD_ATTENDANCE', 'academic_section');
$showUserManual   = \FeatureManager::isUserManualVisible('academic_section');
?>

<div class="container">
    <?php if ($showSeeMarks): ?><a href="attainmentimport.php" class="btn btn-outline-primary my-2">Import attainment evidence</a><?php endif; ?>
    <?php if ($showAiAssist): ?><a href="aiassistance.php" class="btn btn-outline-secondary my-2 ms-2">Review AI drafts</a><?php endif; ?>
    <br />

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-mortarboard me-2"></i>Academic Section Dashboard</span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">
                <i class="bi bi-sliders me-1"></i>Operations Hub
            </span>
        </div>
        <div class="card-body">
            <h5 class="mb-2">Welcome to the Academic Section Portal</h5>
            <p class="mb-0">
                This portal supports institution-level academic operations for the Academic Section role, including curriculum and course structure setup, student enrollment, campus building management, attendance monitoring, examination results, and student dossiers.<?php if ($showUserManual): ?> Need step-by-step guidance? Open the <a href="academicsectionusermanual.php" class="fw-semibold">Interactive Academic Section User Manual</a> or <a href="academicsectionusermanual_pdf.php" target="_blank" class="fw-semibold">download the PDF guide</a>.<?php endif; ?>
            </p>
        </div>
        <?php if ($showUserManual): ?>
        <div class="card-footer bg-light d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <span class="small text-muted">
                <i class="bi bi-book-half me-1 text-primary"></i> Need step-by-step operational guidance? Consult the user manual:
            </span>
            <div class="d-flex flex-wrap gap-2">
                <a href="academicsectionusermanual.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-book-half me-1"></i> User Manual (Step-by-Step)
                </a>
                <a href="academicsectionusermanual_pdf.php?download=1" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download Manual PDF
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-3">

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Academic Setup</div>
                <div class="card-body">
                    <p class="mb-3">
                        Maintain the shared academic master data used across institutional workflows.
                    </p>
                    <ul class="mb-3 small">
                        <li>Manage department class cohorts and student enrollment rosters.</li>
                        <li>Manage buildings and halls used in timetable and room allocation workflows.</li>
                        <li>View and manage regulations, course structures, and master curriculum subjects.</li>
                        <li>Upload and maintain official syllabus records.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <a href="adminfacst.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-people me-1"></i> Class Allotment &amp; Student Enrollment
                        </a>
                        <a href="uploadstudents.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Direct Bulk Upload Students
                        </a>
                        <a href="academicsectionmanagebuildings.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-building me-1"></i> Buildings &amp; Halls
                        </a>
                        <a href="academicsectionregulations.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-diagram-3 me-1"></i> Regulations
                        </a>
                        <a href="academicsectioncoursestructure.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-diagram-2 me-1"></i> Course Structure
                        </a>
                        <a href="academicsectioncurriculumsubjects.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-book me-1"></i> Curriculum Subjects
                        </a>
                        <a href="academicsectionsyllabus.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-text me-1"></i> Syllabus
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Academic Views</div>
                <div class="card-body">
                    <p class="mb-3">
                        Access institution-level academic visibility tools for monitoring and reference.
                    </p>
                    <ul class="mb-3 small">
                        <?php if ($showAttendance): ?>
                        <li>View class attendance reports and Condonation Gazettes across departments.</li>
                        <?php endif; ?>
                        <li>View live campus timetable and room utilization across buildings.</li>
                        <li>View faculty-wise timetable across departments for academic monitoring.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <?php if ($showAttendance): ?>
                        <a href="academicsectionshowallclsattendance.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-calendar-check me-1"></i> View Attendance
                        </a>
                        <?php endif; ?>
                        <a href="viewtimetable.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-clock-history me-1"></i> Campus Timetable &amp; Rooms
                        </a>
                        <a href="faculty_weekly_timetable.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-table me-1"></i> Faculty Timetables
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showResults || $showProfiles || $showCertificates): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white fw-semibold"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Exams &amp; Dossier</div>
                <div class="card-body">
                    <p class="mb-3">
                        Publish examination results, manage student document vault, physical custody, and official certificates.
                    </p>
                    <ul class="mb-3 small">
                        <?php if ($showResults): ?>
                        <li>Upload CSV results, audit CIA discrepancies, and publish student grade cards.</li>
                        <?php endif; ?>
                        <?php if ($showProfiles): ?>
                        <li>Verify student biographical profiles and digital document vault.</li>
                        <?php endif; ?>
                        <?php if ($showCertificates): ?>
                        <li>Track physical original certificates and issue statutory certificates.</li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-grid gap-2">
                        <?php if ($showResults): ?>
                        <a href="academicsectionresults.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Publish Results
                        </a>
                        <?php endif; ?>
                        <?php if ($showProfiles): ?>
                        <a href="adminstudentprofiles.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person-badge me-1"></i> Student Profiles &amp; Vault
                        </a>
                        <?php endif; ?>
                        <?php if ($showCertificates): ?>
                        <a href="admincustodyledger.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-journal-bookmark me-1"></i> Custody &amp; Certificates
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">Account Support &amp; Help</div>
                <div class="card-body">
                    <p class="mb-3">
                        Perform user support, account maintenance, and consult the operational manual.
                    </p>
                    <ul class="mb-3 small">
                        <li>Reset student login passwords when required.</li>
                        <li>Update your own login password securely.</li>
                        <?php if ($showUserManual): ?>
                        <li>Access step-by-step instructions for all enabled workflows.</li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-grid gap-2">
                        <a href="academicsectionresetstudentpwd.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person me-1"></i> Reset Student Pwd
                        </a>
                        <a href="academicsectionchgpwd.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-shield-lock me-1"></i> Change Password
                        </a>
                        <?php if ($showUserManual): ?>
                        <a href="academicsectionusermanual.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-book-half me-1"></i> Open Step-by-Step Manual
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
require_once("academicsectionfooter.php");
?>

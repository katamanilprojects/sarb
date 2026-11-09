<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Edit";
require_once("facheader.php");
require_once("services/FeatureManager.php");

// Fetch Feature Visibilities
$showCoPo       = \FeatureManager::isFacultyVisible('MOD_CO_PO');
$showLessonPlan = \FeatureManager::isFacultyVisible('MOD_LESSON_PLAN');
$showCiaMarks   = \FeatureManager::isFacultyVisible('MOD_CIA_MARKS');
$showSeeMarks   = \FeatureManager::isFacultyVisible('MOD_SEE_MARKS');
$showObeAnalysis= \FeatureManager::isFacultyVisible('MOD_OBE_ANALYSIS');
$showFeedback   = \FeatureManager::isFacultyVisible('MOD_FEEDBACK');
$showDiary      = \FeatureManager::isFacultyVisible('MOD_CLASS_DIARY');
$showAttendance = \FeatureManager::isFacultyVisible('MOD_ATTENDANCE');
$showAttRequests= \FeatureManager::isFacultyVisible('MOD_ATT_REQUESTS');
$showGrouped    = \FeatureManager::isFacultyVisible('MOD_GROUPED_ATT');
$showUserManual = \FeatureManager::isUserManualVisible('faculty');

$hasCurriculumCard = $showCoPo || $showLessonPlan;
$hasAssessmentCard = $showCiaMarks || $showSeeMarks || $showObeAnalysis || $showFeedback;
$hasDiaryCard      = $showDiary || $showAttendance;
$hasRequestsCard   = $showAttRequests;
$hasGroupedCard    = $showGrouped;
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 g-4">

                <!-- 1. Curriculum Outcomes & Lesson Planning -->
                <?php if ($hasCurriculumCard): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-primary">
                        <div class="card-header bg-primary-subtle fw-semibold">
                            <i class="bi bi-diagram-3 me-2"></i>Curriculum Outcomes & Planning
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">Manage Course Outcomes (COs), CO-PO articulation matrices, and lesson plans.</p>
                            <div class="mt-auto">
                                <?php if ($showCoPo): ?>
                                    <a href="facaddcos.php" class="btn btn-outline-primary d-block mb-2">
                                        <i class="bi bi-file-earmark-plus me-1"></i> Add / View Course Outcomes (COs)
                                    </a>
                                    <a href="facarticulationmatrix.php" class="btn btn-outline-primary d-block mb-2">
                                        <i class="bi bi-table me-1"></i> Add / View CO-PO/PSO Articulation Matrix
                                    </a>
                                <?php endif; ?>

                                <?php if ($showLessonPlan): ?>
                                    <a href="facaddlessonplan.php" class="btn btn-outline-primary d-block mb-2">
                                        <i class="bi bi-calendar3 me-1"></i> Add / View Lesson Plan (Course Delivery Plan)
                                    </a>
                                    <a href="faclessonplanreconciliation.php" class="btn btn-outline-primary d-block mb-2">
                                        <i class="bi bi-clipboard-check me-1"></i> Course Delivery Audit & Reconciliation (NBA Crit 2.2)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 2. Assessments & Analysis -->
                <?php if ($hasAssessmentCard): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-success">
                        <div class="card-header bg-success-subtle fw-semibold">
                            <i class="bi bi-clipboard2-data me-2"></i>Assessments & Analysis
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">Enter continuous internal or external assessment marks, view performance analysis, and surveys.</p>
                            <div class="mt-auto">
                                <?php if ($showCiaMarks): ?>
                                    <a href="facciamarks.php" class="btn btn-outline-success d-block mb-2">
                                        <i class="bi bi-pencil-square me-1"></i> Enter CIA Marks & MetaData
                                    </a>
                                <?php endif; ?>

                                <?php if ($showSeeMarks): ?>
                                    <a href="facseemarks.php" class="btn btn-outline-success d-block mb-2">
                                        <i class="bi bi-journal-check me-1"></i> Enter SEE Marks & MetaData
                                    </a>
                                <?php endif; ?>

                                <?php if ($showObeAnalysis): ?>
                                    <a href="facciaanalysis2.php" class="btn btn-outline-success d-block mb-2">
                                        <i class="bi bi-graph-up me-1"></i> View CIA Analysis & Attainment
                                    </a>
                                <?php endif; ?>

                                <?php if ($showFeedback): ?>
                                    <a href="facviewfeedback.php" class="btn btn-outline-success d-block">
                                        <i class="bi bi-chat-dots me-1"></i> View Student Feedbacks
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 3. Class Diary & Exceptional Attendance -->
                <?php if ($hasDiaryCard): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-info">
                        <div class="card-header bg-info-subtle fw-semibold">
                            <i class="bi bi-journal-text me-2"></i>Class Diary & Attendance Utilities
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">Record lecture diary topics, handle late-joining student attendance, or update single records.</p>
                            <div class="mt-auto">
                                <?php if ($showDiary): ?>
                                    <a href="facadddairy.php" class="btn btn-outline-info d-block mb-2">
                                        <i class="bi bi-calendar-plus me-1"></i> Add Diary Only (No Attendance)
                                    </a>
                                <?php endif; ?>

                                <?php if ($showAttendance): ?>
                                    <a href="facexceptionalattendance.php" class="btn btn-outline-info d-block mb-2">
                                        <i class="bi bi-exclamation-circle me-1"></i> Mark Attendance (For Exceptional Cases)
                                    </a>
                                    <a href="facupdatestudentattendance.php" class="btn btn-outline-info d-block">
                                        <i class="bi bi-arrow-repeat me-1"></i> Update Single Student Attendance
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 4. Attendance Deletion Requests -->
                <?php if ($hasRequestsCard): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-warning">
                        <div class="card-header bg-warning-subtle text-dark fw-semibold">
                            <i class="bi bi-envelope-exclamation me-2"></i>Attendance Deletion Requests
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">Request deletion of mistakenly marked attendance records from the Head of Department (HOD).</p>
                            <div class="mt-auto">
                                <a href="facadddelattrequest.php" class="btn btn-outline-warning d-block mb-2">
                                    <i class="bi bi-send me-1"></i> Submit New Deletion Request
                                </a>
                                <a href="facshowdelattrequests.php" class="btn btn-outline-secondary d-block">
                                    <i class="bi bi-list-check me-1"></i> View Submitted Requests
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 5. Electives & Shared Subjects Utilities -->
                <?php if ($hasGroupedCard): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-success">
                        <div class="card-header bg-success-subtle text-dark fw-semibold">
                            <i class="bi bi-bookmarks me-2"></i>Electives & Shared Subjects Utilities
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">Tools for subjects taught across multiple sections (e.g., Open Electives). Mark combined attendance or import CIA data.</p>
                            <div class="mt-auto">
                                <a href="facaddgroupedattendance.php" class="btn btn-outline-info d-block mb-2">
                                    <i class="bi bi-pencil-square me-1"></i> Mark Attendance (For Grouped Sessions)
                                </a>
                                <a href="facgroupedcia.php" class="btn btn-outline-info d-block">
                                    <i class="bi bi-arrows-collapse me-1"></i> Import Grouped CIA Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 6. User Manual & OBE Reference Resources -->
                <div class="col">
                    <div class="card h-100 shadow-sm border-secondary">
                        <div class="card-header bg-secondary-subtle text-dark fw-semibold">
                            <i class="bi bi-book-half me-2"></i><?= $showUserManual ? 'User Manual &amp; OBE Reference' : 'OBE Reference Resources' ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text text-muted small">
                                <?= $showUserManual 
                                    ? 'Step-by-step instructions tailored to currently enabled features, downloadable PDF guide, and OBE strategies.' 
                                    : 'Guidelines and key strategies for Course Outcomes, Articulation Matrices, and Bloom\'s taxonomy.' ?>
                            </p>
                            <div class="mt-auto">
                                <?php if ($showUserManual): ?>
                                    <a href="facusermanual.php" class="btn btn-outline-primary d-block mb-2">
                                        <i class="bi bi-book me-1"></i> Interactive Faculty User Manual
                                    </a>
                                    <a href="facusermanual_pdf.php?download=1" class="btn btn-outline-danger d-block mb-2">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Download Custom Manual (PDF)
                                    </a>
                                <?php endif; ?>
                                <a href="facknowledgebase.php" target="_blank" class="btn btn-outline-secondary d-block">
                                    <i class="bi bi-lightbulb me-1"></i> OBE Knowledge Base &amp; Bloom's Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$hasCurriculumCard && !$hasAssessmentCard && !$hasDiaryCard && !$hasRequestsCard && !$hasGroupedCard): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle me-2"></i>No additional module options are currently active for this semester.
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<?php
require_once("facfooter.php");
?>

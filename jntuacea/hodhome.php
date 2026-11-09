<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Home";
require_once("hodheader.php");
require_once __DIR__ . "/services/FeatureManager.php";

$showAtt        = \FeatureManager::isHodVisible('MOD_ATTENDANCE');
$showRequests   = \FeatureManager::isHodVisible('MOD_ATT_REQUESTS');
$showObe        = \FeatureManager::isHodVisible('MOD_OBE_ANALYSIS');
$showFeedback   = \FeatureManager::isHodVisible('MOD_FEEDBACK');
$showResults    = \FeatureManager::canRoleAccess('MOD_RESULTS_PUBLISH', 'hod');
$showProfiles   = \FeatureManager::canRoleAccess('MOD_STUDENT_PROFILE', 'hod');
$showCourseFile = \FeatureManager::isHodVisible('MOD_COURSE_MASTER_FILE');
$showCqi        = \FeatureManager::canRoleAccess('MOD_CQI_ACTION_TRACKING', 'hod');
$showSeeMarks   = \Services\FeatureManager::canAction('MOD_SEE_MARKS', 'view', 'hod');
$showAiAssist   = \Services\FeatureManager::canAction('MOD_AI_ASSISTANCE', 'view', 'hod');
$showUserManual = \FeatureManager::isUserManualVisible('hod');
?>
<style>
    li {
        line-height: 2em;
    }
</style>
<div class="container">
    <?php if ($showSeeMarks): ?><a href="attainmentimport.php" class="btn btn-outline-primary my-2">Import attainment evidence</a><?php endif; ?>
    <?php if ($showAiAssist): ?><a href="aiassistance.php" class="btn btn-outline-secondary my-2 ms-2">Review AI drafts</a><?php endif; ?>
    <?php if ($showCqi): ?>
        <p class="mt-3"><a class="btn btn-outline-primary" href="reviewqueue.php"><i class="bi bi-clipboard-check me-1"></i> Course review and action tracking</a></p>
    <?php endif; ?>
    <div class="card">
		<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<span><i class="bi bi-info-circle me-2"></i> HOD Instructions</span>
			<div class="d-flex flex-wrap gap-1">
				<?php if ($showCourseFile): ?>
				<a href="hodviewclasses.php" class="btn btn-outline-info btn-sm">
					<i class="bi bi-journal-bookmark me-1"></i> Course Master Files
				</a>
				<?php endif; ?>
				<?php if ($showProfiles): ?>
				<a href="adminstudentprofiles.php" class="btn btn-outline-secondary btn-sm">
					<i class="bi bi-person-badge me-1"></i> Student Profiles
				</a>
				<?php endif; ?>
				<a href="faculty_weekly_timetable.php" class="btn btn-outline-primary btn-sm">
					<i class="bi bi-calendar-week me-1"></i> Faculty Weekly Timetable
				</a>
				<a href="viewtimetable.php" class="btn btn-outline-success btn-sm">
					<i class="bi bi-clock-history me-1"></i> Campus Timetable
				</a>
			</div>
		</div>

        <div class="card-body">
            <h5>Welcome to the HoD Portal</h5>

            <p>This portal provides you with the tools to manage Classes, Faculty, Timetables, and Academic Records within your department.<?php if ($showUserManual): ?> For a complete step-by-step walkthrough of all enabled features, open the <a href="hodusermanual.php" class="fw-semibold">Interactive HOD User Manual</a> or <a href="hodusermanual_pdf.php" target="_blank" class="fw-semibold">view/print the PDF manual</a>.<?php endif; ?></p>
            <br />
            <h5>Menu Options</h5>
            <ul>
                <li>
                    <strong>Classes: </strong>Access a comprehensive list of classes offered in each specialization within the department.<br>
                    Before the commencement of classwork, add curriculum subjects (including multi-batch lab groups), map faculty and enrolled students to each subject, and schedule the class weekly timetable.<?php if ($showUserManual): ?><br>
                    <a href="hodusermanual.php#story-hod-manage-class-subjects"><i class="bi bi-book me-1"></i>Click here for the Dynamic Step-by-Step Guide on Adding Subjects, Mapping Faculty &amp; Students, and Managing Timetables</a><?php endif; ?>
                </li>
                <br>
                <li>
                    <strong>Faculty: </strong>View a detailed list of all faculty members within the department.<br>
                    <ul>
                        <li><strong>Faculty Profile &amp; Password Management:</strong> View and edit essential profile information such as name, designation, email, mobile number, display sort order, and active status. Additionally, reset faculty passwords to their mobile number when needed.</li>
                    </ul>
                </li>
                <br>
                <?php if ($showAtt || $showRequests || $showObe || $showFeedback || $showResults || $showProfiles): ?>
                <li>
                    <strong>Academic Records: </strong>
                    <ul>
                        <?php if ($showAtt): ?>
                        <li><strong>Subject-wise Academic Record Book: </strong>Access subject-wise Academic Record Books and e-Bluebooks for specific classes or individual faculty members.</li>
                        <li><strong>Overall Class Attendance: </strong>Monitor cumulative multi-subject attendance for each class and export Excel summaries<?php if (\FeatureManager::isHodVisible('MOD_ATTENDANCE_CONDONATION_GAZETTE')): ?> or Condonation &amp; Detention Gazettes<?php endif; ?>.</li>
                        <li><strong>Faculty Attendance Management: </strong>Add attendance records on behalf of faculty members in exceptional circumstances.</li>
                        <?php endif; ?>
                        <?php if ($showRequests): ?>
                        <li><strong>Attendance Deletion Request Approval: </strong>Review and approve or reject attendance deletion requests submitted by faculty members.</li>
                        <?php endif; ?>
                        <?php if ($showObe || $showFeedback || $showResults || $showProfiles): ?>
                        <li><strong>Analysis &amp; Reports: </strong>Access class-wise CIA mark ledgers<?php if ($showObe): ?>, course-wise CIA &amp; OBE attainment analysis<?php endif; ?><?php if ($showFeedback): ?>, and anonymous student feedback reports<?php endif; ?><?php if ($showResults): ?>, as well as published departmental examination results<?php endif; ?>.</li>
                        <?php endif; ?>
                        <?php if ($showAtt): ?>
                        <li><strong>Special Permissions: </strong>Grant and manage student attendance permissions for NCC, NSS, Hackathons, Workshops, and co-curricular programmes.</li>
                        <?php endif; ?>
                    </ul>
                </li>
                <br>
                <?php endif; ?>
                <li>
                    <strong>Change Password:</strong> Update your account password.
                </li>
                <li>
                    <strong>Logout:</strong> Safely log out of your account.
                </li>
            </ul>
        </div>
		<?php if ($showUserManual): ?>
		<div class="card-footer bg-light d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
			<span class="small text-muted">
				<i class="bi bi-book-half me-1 text-primary"></i> Need step-by-step departmental guidance? Consult the HOD user manual:
			</span>
			<div class="d-flex flex-wrap gap-2">
				<a href="hodusermanual.php" class="btn btn-primary btn-sm">
					<i class="bi bi-book-half me-1"></i> User Manual (Step-by-Step)
				</a>
				<a href="hodusermanual_pdf.php?download=1" class="btn btn-outline-danger btn-sm">
					<i class="bi bi-file-earmark-pdf me-1"></i> Download Manual PDF
				</a>
			</div>
		</div>
		<?php endif; ?>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>

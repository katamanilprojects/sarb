<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Home";
require_once("hodheader.php");
require_once __DIR__ . "/services/FeatureManager.php";

?>
<style>
    li {
        line-height: 2em;
    }
</style>
<div class="container"><?php if(\Services\FeatureManager::canAction('MOD_SEE_MARKS','view')): ?><a href="attainmentimport.php" class="btn btn-outline-primary my-2">Import attainment evidence</a><?php endif ?><?php if(\Services\FeatureManager::canAction('MOD_AI_ASSISTANCE','view')): ?><a href="aiassistance.php" class="btn btn-outline-secondary my-2 ms-2">Review AI drafts</a><?php endif ?>
    <?php if (\FeatureManager::canRoleAccess('MOD_CQI_ACTION_TRACKING', 'hod')): ?>
        <p class="mt-3"><a class="btn btn-outline-primary" href="reviewqueue.php"><i class="bi bi-clipboard-check me-1"></i> Course review and action tracking</a></p>
    <?php endif; ?>
    <div class="card">
		<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<span><i class="bi bi-info-circle me-2"></i> HOD Instructions</span>
			<div>
				<?php if (\FeatureManager::isHodVisible('MOD_COURSE_MASTER_FILE')): ?>
				<a href="hodviewclasses.php" class="btn btn-outline-info btn-sm me-1">
					<i class="bi bi-journal-bookmark me-1"></i> Course Master Files
				</a>
				<?php endif; ?>
				<?php if (\FeatureManager::canRoleAccess('MOD_STUDENT_PROFILE', 'hod')): ?>
				<a href="adminstudentprofiles.php" class="btn btn-outline-secondary btn-sm me-1">
					<i class="bi bi-person-badge me-1"></i> Student Profiles
				</a>
				<?php endif; ?>
				<a href="faculty_weekly_timetable.php" class="btn btn-outline-primary btn-sm me-1">
					<i class="bi bi-calendar-week me-1"></i> Faculty Weekly Timetable
				</a>
				<a href="viewtimetable.php" class="btn btn-outline-success btn-sm">
					<i class="bi bi-clock-history me-1"></i> Campus Timetable
				</a>
			</div>
		</div>

        <div class="card-body">
            <h5>Welcome to the HoD Portal</h5>

            <p>This portal provides you with the tools to manage Classes, Faculty and Attendance within the department.</p>
            <br />
            <h5>Menu Options</h5>
            <ul>
                <li>
                    <strong>Classes: </strong>Access a comprehensive list of classes offered in each specialization within the department.<br>
                    Before the commencement of classwork, the HoD would add subject details and map faculty and students to each subject within each class.<br>
                    <a href="hod_manual_28012026.pdf" target="_blank">Click here for Manual related to Adding Classes, Map Faculty and Students, Add Timetable Details</a>
                </li>
                <br>
                <li>
                    <strong>Faculty: </strong>View a detailed list of all faculty members within the department.<br>
                    <ul>
                        <li>Faculty Profile Management: View and Edit essential profile information such as name, designation, email, mobile number, and status. Additionally, you can reset faculty passwords as needed.</li>
                    </ul>
                </li>
                <br>
                <li>
                    <strong>Attendance: </strong>
                    <ul>
                        <li><strong>Subject-wise Academic Record Book: </strong>Access subject-wise Academic Record Books for specific classes or individual faculty members.</li>
                        <li><strong>Overall Class Attendance: </strong>Monitor the overall attendance of each class.</li>
                        <li><strong>Faculty Attendance Management: </strong>Add attendance records on behalf of faculty members in exceptional circumstances.</li>
                        <li><strong>Attendance Request Approval: </strong>Review and approve or reject attendance deletion requests submitted by faculty members (in exceptional case, deleting a marked attendance record for a specific hour.)</li>
                        <li><strong>Analysis & Reports: </strong>Access course-wise CIA (Continuous Internal Assessment) analysis and view course-wise feedback submitted by students.</li>
                        <li><strong>Special Permissions: </strong>Manage student attendance permissions for co-curricular and extra‑curricular programmes.</li>
                    </ul>
                </li>
                <br>
                <li>
                    <strong>Change Password:</strong> Update your account password.
                </li>
                <li>
                    <strong>Logout:</strong> Safely log out of your account.
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>
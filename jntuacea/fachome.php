<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Home";
require_once("facheader.php");
require_once __DIR__ . '/services/FeatureManager.php';

// Dynamic Feature Visibilities for Faculty Home Instructions
$showAttendance  = \FeatureManager::isFacultyVisible('MOD_ATTENDANCE');
$showCoPo        = \FeatureManager::isFacultyVisible('MOD_CO_PO');
$showLessonPlan  = \FeatureManager::isFacultyVisible('MOD_LESSON_PLAN');
$showCiaMarks    = \FeatureManager::isFacultyVisible('MOD_CIA_MARKS');
$showCiaMetadata = \FeatureManager::isFacultyVisible('MOD_CIA_METADATA');
$showSeeMarks    = \FeatureManager::isFacultyVisible('MOD_SEE_MARKS');
$showObeAnalysis = \FeatureManager::isFacultyVisible('MOD_OBE_ANALYSIS');
$showFeedback    = \FeatureManager::isFacultyVisible('MOD_FEEDBACK');
$showDiary       = \FeatureManager::isFacultyVisible('MOD_CLASS_DIARY');
$showAttRequests = \FeatureManager::isFacultyVisible('MOD_ATT_REQUESTS');
$showGrouped     = \FeatureManager::isFacultyVisible('MOD_GROUPED_ATT');
$showCqi         = \FeatureManager::canRoleAccess('MOD_CQI_ACTION_TRACKING', 'faculty');
$showUserManual  = \FeatureManager::isUserManualVisible('faculty');

$hasCurriculumGroup = $showCoPo || $showLessonPlan;
$hasAssessmentGroup = $showCiaMarks || $showSeeMarks || $showObeAnalysis || $showFeedback;
$hasDiaryGroup      = $showDiary || $showAttendance;
$hasOtherOptions    = $hasCurriculumGroup || $hasAssessmentGroup || $hasDiaryGroup || $showAttRequests || $showGrouped;
?>
<div class="container">
    <?php if ($showCqi): ?>
        <p class="mt-3"><a class="btn btn-outline-primary" href="reviewqueue.php"><i class="bi bi-clipboard-check me-1"></i> Course review and action tracking</a></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['err'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['err']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['err']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['succ'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['succ']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['succ']); ?>
    <?php endif; ?>
	<div class="card">
		<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
			<span><i class="bi bi-info-circle me-2"></i> Instructions</span>
			<div>
				<a href="faculty_weekly_timetable.php" class="btn btn-outline-primary btn-sm">
					<i class="bi bi-calendar-week me-1"></i> My Weekly Timetable
				</a>
			</div>
		</div>
		<div class="card-body">

			<h5 class="my-3"><i class="bi bi-house-door me-2"></i>Welcome to the Faculty Portal</h5>

			<p>This portal provides you with the tools to manage your Academic Record Book.<?php if ($showUserManual): ?> Need detailed step-by-step instructions for your enabled features? Open the <a href="facusermanual.php" class="fw-semibold">Interactive Faculty User Manual</a> or <a href="facusermanual_pdf.php" target="_blank" class="fw-semibold">view/print the PDF guide</a>.<?php endif; ?></p>
			<hr>
			<h5 class="my-3"><i class="bi bi-list-ul me-2"></i>Menu Options</h5>


			<style>
				li {
					margin-bottom: 10px;
				}
			</style>

			<ul>
				<?php if ($showAttendance): ?>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-clipboard-check me-2"></i>Mark Attendance:</strong> Record attendance for your classes, including options to select multiple hours, add diary entries, and mark students present or absent.
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-eye me-2"></i>Show Attendance:</strong> View attendance records for your subjects, with options to see overall percentages or detailed attendance and e-Bluebook by date and hour.<?php if ($showDiary): ?> You can also view your diary entries for each subject.<?php endif; ?>
				</li>
				<hr>
				<?php endif; ?>

				<?php if ($hasOtherOptions): ?>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-gear me-2"></i>Other Options:</strong>
					<ul>
						<?php if ($hasCurriculumGroup): ?>
						<li><strong class="my-2 d-block"><i class="bi bi-mortarboard me-2"></i>Curriculum Outcomes &amp; Planning:</strong>
							<ul>
								<?php if ($showCoPo): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-plus-circle me-2"></i>COs: </strong> Use this option to add, import, or view Course Outcomes (COs).</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-diagram-3 me-2"></i>CO-PO/PSO Articulation Matrix: </strong> Use this option to add or view the mapping between Course Outcomes (COs) and Program Outcomes (POs)/Program Specific Outcomes (PSOs). This helps in understanding how individual courses contribute to broader program objectives.
								</li>
								<?php endif; ?>
								<?php if ($showLessonPlan): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-calendar3 me-2"></i>Lesson Plan &amp; Course Delivery Audit: </strong> Prepare or bulk-upload your lecture schedule and reconcile conducted diary entries with planned topics for NBA Criterion 2.2 compliance.</li>
								<?php endif; ?>
							</ul>
						</li>
						<hr>
						<?php endif; ?>

						<?php if ($hasAssessmentGroup): ?>
						<li><strong class="my-2 d-block"><i class="bi bi-bar-chart me-2"></i>Assessments &amp; Analysis:</strong>
							<ul>
								<?php if ($showCiaMarks): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-pencil-square me-2"></i>Add / View Internal (Continuous Assessment) Marks &amp; their MetaData: </strong> Use this option to:
									<ul>
										<li><i class="bi bi-file-earmark-spreadsheet me-2"></i> Add / View Internal (Continuous Assessment) Marks</li>
										<?php if ($showCiaMetadata): ?>
										<li><i class="bi bi-file-earmark-bar-graph me-2"></i> Add / View Detailed Internal (Continuous Assessment) Marks <strong>(to get CIA Analysis)</strong>
											<ul>
												<li>
													<strong class="my-2 d-block"><i class="bi bi-eraser me-2"></i>Delete CIA MetaData &amp; Marks: </strong>
													If you made an error while setting up an assessment, you can delete Question Paper MetaData and Student Marks. <strong>Note:</strong> MetaData cannot be deleted if marks are already entered (you must delete the marks first). Deletion is disabled after final assessment submission.
												</li>
											</ul>
										</li>
										<li><i class="bi bi-paperclip me-2"></i> Add / View Attachments related to Continuous Internal Assessments</li>
										<?php endif; ?>
									</ul>
								</li>
								<?php endif; ?>

								<?php if ($showSeeMarks): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-journal-check me-2"></i>Add / View Semester End Examination (SEE) Marks &amp; MetaData: </strong> Configure external question paper blueprints (compulsory short questions &amp; Either/Or choice groups), enter detailed question scores (Mode A), enter direct university ledger totals (Mode B), or auto-sync published results (Mode C).</li>
								<?php endif; ?>

								<?php if ($showObeAnalysis): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-pie-chart me-2"></i>CIA Analysis &amp; Attainment: </strong>View interactive CIA/OBE attainment analytics, Bloom's taxonomy charts, and Zero-Marks diagnostics. To get question-level analysis, enter detailed student marks for each course.</li>
								<?php endif; ?>

								<?php if ($showFeedback): ?>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-chat-left-text me-2"></i>View Student Feedbacks: </strong> Access and review anonymized feedback provided by students for course improvement and faculty self-appraisal.
								</li>
								<?php endif; ?>
							</ul>
						</li>
						<hr>
						<?php endif; ?>

						<?php if ($hasDiaryGroup): ?>
						<li><strong class="my-2 d-block"><i class="bi bi-journal me-2"></i>Class Diary &amp; Attendance Utilities:</strong>
							<ul>
								<?php if ($showDiary): ?>
								<li><strong class="my-2 d-block"><i class="bi bi-journal-plus me-2"></i>Add Diary Only without Attendance: </strong> Use this option to record a class diary entry without marking student attendance when required (e.g., suspended class, holiday, or special event).</li>
								<?php endif; ?>
								<?php if ($showAttendance): ?>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-person-plus me-2"></i>Mark Attendance (For Exceptional Cases): </strong> Mark present entries for students who had no attendance record when a session was originally marked (such as lateral-entry students who joined late).
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-arrow-repeat me-2"></i>Update Single Student Attendance: </strong> Correct a specific student's existing attendance status (Present/Absent) for a given hour. <strong>Note:</strong> Attendance can only be modified for dates within the last 7 days.
								</li>
								<?php endif; ?>
							</ul>
						</li>
						<hr>
						<?php endif; ?>

						<?php if ($showAttRequests): ?>
						<li><strong class="my-2 d-block"><i class="bi bi-trash me-2"></i>Attendance Deletion Requests:</strong>
							<ul>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-file-earmark-x me-2"></i>Submit New Deletion Request: </strong> Submit a formal request to the Head of Department (HOD) to delete an erroneously marked attendance session.
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-list-check me-2"></i>View Submitted Requests: </strong> Track the approval status (Pending, Approved, or Rejected) of your attendance deletion requests.
								</li>
							</ul>
						</li>
						<hr>
						<?php endif; ?>

						<?php if ($showGrouped): ?>
						<li><strong class="my-2 d-block"><i class="bi bi-bookmarks me-2"></i>Electives &amp; Shared Subjects Utilities:</strong>
							<ul>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-people me-2"></i>Mark Attendance (For Grouped Sessions): </strong> When teaching the same subject code across multiple sections simultaneously (e.g., Open Electives), mark combined attendance for all grouped sections in one step.
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-box-arrow-in-down me-2"></i>Import Grouped CIA Data: </strong> Copy configured CIA question paper metadata (components, questions, CO mappings) and attachments from one section to parallel grouped sections of the same subject.
								</li>
							</ul>
						</li>
						<hr>
						<?php endif; ?>

						<li><strong class="my-2 d-block"><i class="bi bi-compass me-2"></i>Additional Resources<?php if ($showUserManual): ?> &amp; User Manuals<?php endif; ?>:</strong>
							<ul>
								<?php if ($showUserManual): ?>
								<li><a href="facusermanual.php"><i class="bi bi-book-half me-2"></i>Interactive Faculty User Manual (Step-by-Step Guide for Enabled Features)</a></li>
								<li><a href="facusermanual_pdf.php" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>Download / Print Custom Faculty User Manual (PDF)</a></li>
								<?php endif; ?>
								<li><a href="facknowledgebase.php" target="_blank"><i class="bi bi-lightbulb me-2"></i>Click here to learn key strategies for successful Outcome-Based Education (OBE)</a></li>
							</ul>
						</li>
					</ul>
				</li>
				<hr>
				<?php else: ?>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-compass me-2"></i>Additional Resources<?php if ($showUserManual): ?> &amp; User Manuals<?php endif; ?>:</strong>
					<ul>
						<?php if ($showUserManual): ?>
						<li><a href="facusermanual.php"><i class="bi bi-book-half me-2"></i>Interactive Faculty User Manual (Step-by-Step Guide for Enabled Features)</a></li>
						<li><a href="facusermanual_pdf.php" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>Download / Print Custom Faculty User Manual (PDF)</a></li>
						<?php endif; ?>
						<li><a href="facknowledgebase.php" target="_blank"><i class="bi bi-lightbulb me-2"></i>Click here to learn key strategies for successful Outcome-Based Education (OBE)</a></li>
					</ul>
				</li>
				<hr>
				<?php endif; ?>

				<li>
					<strong class="my-2 d-block"><i class="bi bi-key me-2"></i>Change Password:</strong> Update your account password.
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-box-arrow-right me-2"></i>Logout:</strong> Safely log out of your account.
				</li>
			</ul>
			<hr>

			<?php if ($showAttendance || $showFeedback): ?>
			<h5 class="my-3"><i class="bi bi-bell me-2"></i>Important Reminders</h5>

			<ul>
				<?php if ($showAttendance): ?>
				<li><strong class="my-2 d-block"><i class="bi bi-calendar-check me-2"></i>Classwork Attendance Period:</strong>
					<ul>
						<li><i class="bi bi-clock-fill me-2"></i>The Academic Calendar defines the start and end dates for each classwork period.</li>
						<li><i class="bi bi-calendar-minus me-2"></i>Faculty can mark attendance <u>only</u> within these defined dates.</li>
						<li><i class="bi bi-x-circle-fill me-2"></i>After the end date, attendance marking will be disabled.</li>
					</ul>
				</li>
				<?php endif; ?>
				<?php if ($showAttendance && $showFeedback): ?>
				<hr>
				<?php endif; ?>
				<?php if ($showFeedback): ?>
				<li><strong class="my-2 d-block"><i class="bi bi-chat-dots me-2"></i>Student Feedback &amp; Course Outcomes:</strong>
					<ul>
						<li><i class="bi bi-hourglass-split me-2"></i>The Student Feedback Module activates according to the class feedback schedule configured by the institution.</li>
						<?php if ($showCoPo): ?>
						<li><i class="bi bi-hand-thumbs-up me-2"></i>To ensure students can provide Course Outcome (CO) feedback, please define or import your Course Outcomes (COs) in advance.</li>
						<li><i class="bi bi-globe me-2"></i>For online courses such as SWAYAM, the department offering the course should define the course outcomes according to the syllabus.</li>
						<li><i class="bi bi-lightbulb me-2"></i>Your added COs are essential for effective student feedback and continuous quality improvement.</li>
						<?php endif; ?>
					</ul>
				</li>
				<?php endif; ?>
			</ul>
			<hr>
			<?php endif; ?>

			<h5 class="my-3"><i class="bi bi-journal-text me-2"></i>Additional Notes</h5>
			<ul>
				<?php if ($showAttendance): ?>
				<li><i class="bi bi-check-circle me-2"></i>Make sure to select the correct subject and date before marking attendance.</li>
				<?php endif; ?>
				<li><i class="bi bi-headset me-2"></i>If you encounter any issues, please contact the system administrator or your Head of Department (HOD).</li>
			</ul>
		</div>
		<?php if ($showUserManual): ?>
		<div class="card-footer bg-light d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
			<span class="small text-muted">
				<i class="bi bi-book-half me-1 text-primary"></i> Need detailed step-by-step guidance? Consult the official user manual:
			</span>
			<div class="d-flex flex-wrap gap-2">
				<a href="facusermanual.php" class="btn btn-primary btn-sm">
					<i class="bi bi-book-half me-1"></i> User Manual (Step-by-Step)
				</a>
				<a href="facusermanual_pdf.php?download=1" class="btn btn-outline-danger btn-sm">
					<i class="bi bi-file-earmark-pdf me-1"></i> Download Manual PDF
				</a>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php
require_once("facfooter.php");
?>

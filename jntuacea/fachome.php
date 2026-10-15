<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Home";
require_once("facheader.php");
?>
<div class="container">
	<!--<p class="mt-3"><a class="btn btn-outline-primary" href="reviewqueue.php">Course review and action tracking</a></p>
	<br />-->
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
		<div class="card-header d-flex justify-content-between align-items-center">
			<span><i class="bi bi-info-circle me-2"></i> Instructions</span>
			<a href="faculty_weekly_timetable.php" class="btn btn-outline-primary btn-sm">
				<i class="bi bi-calendar-week me-1"></i> My Weekly Timetable
			</a>
		</div>
		<div class="card-body">

			<h5 class="my-3"><i class="bi bi-house-door me-2"></i>Welcome to the Faculty Portal</h5>

			<p>This portal provides you with the tools to manage your Academic Record Book.</p>
			<hr>
			<h5 class="my-3"><i class="bi bi-list-ul me-2"></i>Menu Options</h5>


			<style>
				li {
					margin-bottom: 10px;
				}
			</style>

			<ul>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-clipboard-check me-2"></i>Mark Attendance:</strong> Record attendance for your classes, including options to select multiple hours, add diary entries, and mark students present or absent.
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-eye me-2"></i>Show Attendance:</strong> View attendance records for your subjects, with options to see overall percentages or detailed attendance and e-Bluebook by date and hour. You can also view your diary entries for each subject.
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-gear me-2"></i>Other Options:</strong>
					<ul>
						<li><strong class="my-2 d-block"><i class="bi bi-mortarboard me-2"></i>Curriculum Outcomes:</strong>
							<ul>
								<li><strong class="my-2 d-block"><i class="bi bi-plus-circle me-2"></i>COs: </strong> Use this option to Add/view Course Outcomes</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-diagram-3 me-2"></i>CO-PO/PSO Articulation Matrix: </strong> Use this option to add or view the mapping between Course Outcomes (COs) and Program Outcomes (POs)/Program Specific Outcomes (PSOs). This helps in understanding how individual courses contribute to broader program objectives.
								</li>
							</ul>
						</li>
						<hr>
						<li><strong class="my-2 d-block"><i class="bi bi-bar-chart me-2"></i>Assessments & Analysis:</strong>
							<ul>
								<li><strong class="my-2 d-block"><i class="bi bi-pencil-square me-2"></i>Add / View Internal (Continuous Assessment) Marks & their MetaData : </strong> Use this Option to:
									<ul>
										<li><i class="bi bi-file-earmark-spreadsheet me-2"></i> Add / View Internal (Continuous Assessment) Marks </li>
										<li><i class="bi bi-file-earmark-bar-graph me-2"></i> Add / View Detailed Internal (Continuous Assessment) Marks <strong>(to get CIA Analysis)</strong>
											<ul>
												<li>
													<strong class="my-2 d-block"><i class="bi bi-eraser me-2"></i>Delete CIA MetaData & Marks: </strong>
													If you made an error while setting up an assessment, you can now delete Question Paper MetaData and Student Marks. <strong>Note:</strong> MetaData cannot be deleted if marks are already entered (you must delete the marks first). Deletion is entirely disabled after the final assessment submission.
												</li>
											</ul>
										</li>
										<li><i class="bi bi-paperclip me-2"></i> Add / View Attachments related to Continuous Internal Assessments</li>
									</ul>
								</li>
								<li><strong class="my-2 d-block"><i class="bi bi-journal-check me-2"></i>Add / View Semester End Examination (SEE) Marks & MetaData: </strong> Use this option to configure external question paper blueprints (compulsory short questions & Either/Or choice groups), enter detailed question scores (Mode A) or direct university ledger totals (Mode B), and calculate external Course Outcome direct attainment.</li>
								<li><strong class="my-2 d-block"><i class="bi bi-pie-chart me-2"></i>CIA Analysis: </strong>Use this option to View CIA Analysis. To get Analysis, Add Detailed Marks of the Student for each Course</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-chat-left-text me-2"></i>View Student Feedbacks: </strong> Access and review feedback provided by students, which can be valuable for course improvement and understanding student perspectives.
								</li>
							</ul>
						</li>
						<hr>
						<li><strong class="my-2 d-block"><i class="bi bi-journal me-2"></i>Class Diary & Attendance:</strong>
							<ul>
								<li><strong class="my-2 d-block"><i class="bi bi-journal-plus me-2"></i>Add Diary Only without Attendance: </strong> Use this Option to Add Diary Only without Attendance, if required (Example: Suspended Class, etc.)</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-person-plus me-2"></i>Mark Attendance (For exceptional Cases): </strong> This option allows you to mark attendance specifically for exceptional cases, such as lateral entry students who join late, ensuring their attendance records are accurately updated.
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-arrow-repeat me-2"></i>Update Single Student Attendance: </strong> Need to correct an attendance entry? You can now update a specific student's attendance status (Present/Absent) for a given hour. <strong>Note:</strong> Attendance can only be modified for dates within the last 7 days.
								</li>
							</ul>
						</li>
						<hr>
						<li><strong class="my-2 d-block"><i class="bi bi-trash me-2"></i>Attendance Deletion Requests:</strong>
							<ul>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-file-earmark-x me-2"></i>Submit New Deletion Request: </strong> If you need to rectify an attendance record, use this option to submit a request to the Head of Department (HoD) for deleting a previously marked attendance entry.
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-list-check me-2"></i>View Submitted Requests: </strong> Keep track of the status of your attendance deletion requests submitted to the HoD.
								</li>
							</ul>
						</li>
						<hr>
						<li><strong class="my-2 d-block"><i class="bi bi-bookmarks me-2"></i>Electives & Shared Subjects Utilities:</strong>
							<ul>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-people me-2"></i>Mark Attendance (For Grouped Sessions): </strong> Use this option when you teach the same subject to multiple sections (e.g., Open Electives, Skill Enhancement Courses). You can mark attendance for all grouped sections simultaneously, saving time and ensuring consistency across sections.
								</li>
								<li>
									<strong class="my-2 d-block"><i class="bi bi-box-arrow-in-down me-2"></i>Import Grouped CIA Data: </strong> This tool allows you to import CIA (Continuous Internal Assessment) marks and attachments from one section to other grouped sections. Useful when the same assessment is conducted across multiple sections of the same elective or shared subject.
								</li>
							</ul>
						</li>
						<hr>
						<li><strong class="my-2 d-block"><i class="bi bi-compass me-2"></i>Additional Resources:</strong>
							<ul>
								<li><a href="facknowledgebase.php" target="_blank"><i class="bi bi-lightbulb me-2"></i>Click here to learn key strategies for successful outcome-based education.</a></li>
								<li><a href="fac_user_manual_v120225.pdf" target="_blank"><i class="bi bi-file-earmark-text me-2"></i>Click here for Instructions/Step-by-Step Process related to Adding Detailed Student CIA Marks for Analysis</a></li>
							</ul>
						</li>
					</ul>
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-key me-2"></i>Change Password:</strong> Update your account password.
				</li>
				<hr>
				<li>
					<strong class="my-2 d-block"><i class="bi bi-box-arrow-right me-2"></i>Logout:</strong> Safely log out of your account.
				</li>
			</ul>
			<hr>

			<h5 class="my-3"><i class="bi bi-bell me-2"></i>Important Reminders</h5>

			<ul>
				<li><strong class="my-2 d-block"><i class="bi bi-calendar-check me-2"></i>Classwork Attendance Period:</strong>
					<ul>
						<li><i class="bi bi-clock-fill me-2"></i>The Academic Calendar defines the start and end dates for each classwork period.</li>
						<li><i class="bi bi-calendar-minus me-2"></i>Faculty can mark attendance <u>only</u> within these defined dates.</li>
						<li><i class="bi bi-x-circle-fill me-2"></i>After the end date, attendance marking will be disabled.</li>
					</ul>
				</li>
				<hr>
				<li><strong class="my-2 d-block"><i class="bi bi-chat-dots me-2"></i>Student Feedback Activation:</strong>
					<ul>
						<li><i class="bi bi-hourglass-split me-2"></i>The Student Feedback Module automatically activates for 7 days, starting immediately after the end of each classwork period.</li>
						<li><i class="bi bi-hand-thumbs-up me-2"></i>To ensure students can provide meaningful feedback, please add your Course Outcomes (COs) if you haven't already.</li>
						<li><i class="bi bi-globe me-2"></i>For Online Courses like swayam, according to the swayam syllabus, the department offering the course should define the course outcomes.</li>
						<li><i class="bi bi-lightbulb me-2"></i>Your added COs are essential for effective feedback and continuous improvement.</li>
					</ul>
				</li>
			</ul>
			<hr>
			<h5 class="my-3"><i class="bi bi-journal-text me-2"></i>Additional Notes</h5>
			<ul>
				<li><i class="bi bi-check-circle me-2"></i>Make sure to select the correct subject and date before marking attendance.</li>
				<li><i class="bi bi-headset me-2"></i>If you encounter any issues, please contact the system administrator.</li>
			</ul>
		</div>
	</div>
</div>

<?php
require_once("facfooter.php");
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
$page_title = "View Attendance";
require_once("hodheader.php");

$showAtt = \FeatureManager::isHodVisible('MOD_ATTENDANCE');
$showRequests = \FeatureManager::isHodVisible('MOD_ATT_REQUESTS');
$showObe = \FeatureManager::isHodVisible('MOD_OBE_ANALYSIS');
$showFeedback = \FeatureManager::isHodVisible('MOD_FEEDBACK');
$showAnalysisReports = $showObe || $showFeedback;
?>
<div class="container">
	<br />

    <?php if ($showAtt): ?>
	<div class="row">
		<br>
		<div class="col-md-6 mb-4">
			<!-- Subject-Wise Attendance -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Subject-Wise Student Academic Record Book</div>
				<div class="card-body">
					<div class="gap-2 mb-2">
						<a href="hodshowfacattendance.php" class="btn btn-outline-primary">
							<i class="bi bi-person-check me-1"></i> Attendance by Faculty
						</a>
					</div>
					<div class="gap-2">
						<a href="hodshowclsattendance.php" class="btn btn-outline-primary">
							<i class="bi bi-people me-1"></i> Attendance by Class
						</a>
					</div>
				</div>
			</div>
		</div>
		<br>
		<div class="col-md-6 mb-4">
			<!-- Overall Class Attendance -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Overall Class Attendance</div>
				<div class="card-body">
					<div class="gap-2">
						<a href="hodshowallclsattendance.php" class="btn btn-outline-primary">
							<i class="bi bi-bar-chart-line me-1"></i> Overall Class Attendance
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
    <?php endif; ?>

	<div class="row">
        <?php if ($showAtt): ?>
		<div class="col-md-6 mb-4">
			<!-- Add Attendance -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Add New Attendance Record</div>
				<div class="card-body">
					<div class="gap-2">
						<a href="hodfacaddattendance.php" class="btn btn-outline-success">
							<i class="bi bi-plus-square me-1"></i> Add Faculty Attendance
						</a>
					</div>
				</div>
			</div>
		</div>
        <?php endif; ?>

        <?php if ($showRequests): ?>
		<div class="col-md-6 mb-4">
			<!-- Delete Attendance -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Delete Attendance</div>
				<div class="card-body">
					<div class="gap-2">
						<a href="hodviewdelrequests.php" class="btn btn-outline-danger">
							<i class="bi bi-trash3 me-1"></i> Show Submitted Requests to Delete Attendance Records
						</a>
					</div>
				</div>
			</div>
		</div>
        <?php endif; ?>
	</div>

	<div class="row">
        <?php if ($showAnalysisReports): ?>
		<div class="col-md-6 mb-4">
			<!-- Analysis & Reports -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Analysis & Reports</div>
				<div class="card-body">
                    <?php if ($showObe): ?>
					<div class="gap-2 mb-2">
						<a href="hodciaanalysis2.php" class="btn btn-info">
							<i class="bi bi-graph-up me-1"></i> Course-wise CIA Analysis
						</a>
					</div>
                    <?php endif; ?>
                    <?php if ($showFeedback): ?>
					<div class="gap-2">
						<a href="hodviewfeedback.php" class="btn btn-info">
							<i class="bi bi-chat-dots me-1"></i> Course-wise Feedback
						</a>
					</div>
                    <?php endif; ?>
				</div>
			</div>
		</div>
        <?php endif; ?>

        <?php if ($showAtt): ?>
		<div class="col-md-6 mb-4">
			<!-- Special Permission -->
			<div class="card h-100">
				<div class="card-header fw-semibold bg-light">Special Permission</div>
				<div class="card-body">
					<div class="gap-2">
						<a href="hodmanagepermissions.php" class="btn btn-outline-primary">
							<i class="bi bi-patch-check me-1"></i> Manage Special Permissions
						</a>
					</div>

				</div>
			</div>
		</div>
        <?php endif; ?>
	</div>
</div>

<?php
require_once("hodfooter.php");
?>
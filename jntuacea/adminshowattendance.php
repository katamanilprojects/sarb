<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
$page_title = "View Attendance";
require_once("adminheader.php");

$showAtt = \FeatureManager::isModuleEnabled('MOD_ATTENDANCE');
$showObe = \FeatureManager::isModuleEnabled('MOD_OBE_ANALYSIS');
$showFb  = \FeatureManager::isModuleEnabled('MOD_FEEDBACK');
?>

<div class="container">
    <?php if ($showAtt): ?>
	<br />
	<div class="card">
		<div class="card-header">
			Subject-Wise Student Academic Record Book
		</div>
		<div class="card-body">

			<ul>
				<li>
					<a href="adminshowfacattendance.php" class="btn btn-outline-primary">Attendance by Faculty </a>
				</li>
				<br>
				<li>
					<a href="adminshowclsattendance.php" class="btn btn-outline-success">Attendance by Class</a>
				</li>
			</ul>

		</div>
	</div>
	<br />
	<div class="card">
		<div class="card-header">
			Overall Class Attendance
		</div>
		<div class="card-body">

			<ul>
				<li>
					<a href="adminshowallclsattendance.php" class="btn btn-outline-success">Overall Class Attendance</a>
				</li>
			</ul>

		</div>
	</div>
    <?php endif; ?>

    <?php if ($showObe || $showFb): ?>
	<br />
	<div class="card">
		<div class="card-header">
			Analysis & Feedback
		</div>
		<div class="card-body">

			<ul>
                <?php if ($showObe): ?>
				<li>
					<a href="adminciaanalysis2.php" class="btn btn-outline-success">CIA Analysis</a>
				</li>
				<br>
                <?php endif; ?>
                <?php if ($showFb): ?>
				<li>
					<a href="adminviewfeedback.php" class="btn btn-outline-success">Feedback</a>
				</li>
                <?php endif; ?>
			</ul>

		</div>
	</div>
    <?php endif; ?>
</div>

<?php
require_once("adminfooter.php");
?>
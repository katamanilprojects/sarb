<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_FEEDBACK');

$page_title = "Feedback";
require_once("stheader.php");

require_once("services/StudentRepository.php");
$studentRepo = new StudentRepository();

// Fetch classes and their corresponding subjects for the student
$classes = $studentRepo->getEnrolledClassesByUsername($_SESSION['user']);

// Initialize arrays for different feedback categories
$upcomingFeedback = [];
$activeFeedback = [];
$completedFeedback = [];

// Categorize classes based on feedback dates
if (!empty($classes)) {
    //temporary feedback end date for specific classes
    $targetClassIds = [4, 9, 154, 182, 225, 226, 281];
    
    foreach ($classes as $class) {
        $currentDate = new DateTime();
        $endDate = new DateTime($class['end_date']);

        // Calculate feedback start and end dates
        $feedbackStartDate = clone $endDate;
        $feedbackStartDate->modify('+1 day');
        $feedbackStartDate->setTime(0, 0, 0);
        $feedbackEndDate = clone $feedbackStartDate;
        $feedbackEndDate->modify('+1 month +2 days');
        $feedbackEndDate->setTime(23, 59, 59);

    //temporary feedback end date for specific classes
        if (in_array($class['class_id'], $targetClassIds)) {
            $feedbackEndDate = new DateTime('2026-09-30 23:59:59'); // Set your temporary date here
        }
        // temporary for all
        $feedbackEndDate = new DateTime('2026-11-08 23:59:59');

        // Add calculated dates to class array
        $class['feedback_start_date'] = $feedbackStartDate->format('d-m-Y');
        $class['feedback_end_date'] = $feedbackEndDate->format('d-m-Y');

        if ($currentDate < $feedbackStartDate) {
            $upcomingFeedback[] = $class;
        } elseif ($currentDate <= $feedbackEndDate) {
            $activeFeedback[] = $class;
        } else {
            $completedFeedback[] = $class;
        }
    }
}
?>

<div class="container">

    <div style="border: 1px solid #ccc; padding: 10px; text-align: center; margin-bottom: 20px; border-radius: 5px; font-weight: bold;">
       <em> For each semester, feedback links activate automatically after classwork completion until the specified deadline.</em>
    </div>
    <?php if (!empty($classes)): ?>
        <!-- Active Feedback Section -->
        <?php if (!empty($activeFeedback)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Active Feedback
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($activeFeedback as $class): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light border-primary">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($class['classname']); ?></h5>
                                        <p class="card-text">Academic Year: <?= htmlspecialchars($class['acad_year']); ?></p>
                                        <p class="card-text">
                                            Feedback Period:<br>
                                            <small>Started: <?= htmlspecialchars($class['feedback_start_date']); ?></small><br>
                                            <small class="text-danger">Ends: <?= htmlspecialchars($class['feedback_end_date']); ?></small>
                                        </p>
                                        <form action="studentfeedbacksubjects.php" method="post">
                                            <input type="hidden" name="student_id" value="<?= $class['student_id']; ?>">
                                            <input type="hidden" name="class_id" value="<?= $class['class_id']; ?>">
                                            <input type="hidden" name="classname" value="<?= $class['classname']; ?>">
                                            <input type="hidden" name="acad_year" value="<?= $class['acad_year']; ?>">
                                            <button type="submit" class="btn btn-primary">Provide Feedback</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upcoming Feedback Section -->
        <?php if (!empty($upcomingFeedback)): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    Upcoming Feedback
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($upcomingFeedback as $class): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light border-info">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($class['classname']); ?></h5>
                                        <p class="card-text">Academic Year: <?= htmlspecialchars($class['acad_year']); ?></p>
                                        <p class="card-text text-primary">
                                            <small> Feedback Starts on: </small><?= htmlspecialchars($class['feedback_start_date']); ?><br>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Completed Feedback Section -->
        <?php if (!empty($completedFeedback)): ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    Completed Feedback
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($completedFeedback as $class): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light text-secondary">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($class['classname']); ?></h5>
                                        <p class="card-text">Academic Year: <?= htmlspecialchars($class['acad_year']); ?></p>
                                        <p class="card-text">
                                            Feedback <small>Ended on: <?= htmlspecialchars($class['feedback_end_date']); ?></small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="alert alert-warning text-center">
                    No feedback sessions are available.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once("stfooter.php");
?>
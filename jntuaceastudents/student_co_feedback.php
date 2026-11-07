<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_FEEDBACK');
require_once("services/StudentRepository.php");
require_once("services/StudentFeedbackService.php");
require_once("services/StudentAssessmentService.php");

$studentRepo = new StudentRepository();
$feedbackService = new StudentFeedbackService();
$assessmentService = new StudentAssessmentService();

// Verify that the requested student_id belongs to the logged-in user
if (empty($_POST['student_id']) || empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership($_POST['student_id'], $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}

// Check if subject details are provided
if (empty($_POST["subcode"]) || empty($_POST["sub_fullname"]) || empty($_POST["subject_id"]) || empty($_POST["student_id"]) || empty($_POST['class_id']) || empty($_POST['classname']) || empty($_POST['acad_year'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (!$studentRepo->verifyClassEnrollment((int)$_POST['student_id'], (int)$_POST['class_id']) || !$studentRepo->verifySubjectEnrollment((int)$_POST['student_id'], (int)$_POST['subject_id'])) {
    header('Location: ./studenthome.php');
    exit();
}

$page_title = "Feedback";
require_once("stheader.php");

$subject_id = $_POST["subject_id"];
$student_id = $_POST["student_id"];
$class_id = $_POST["class_id"];
$classname = $_POST["classname"];
$acad_year = $_POST["acad_year"];
$subcode = $_POST["subcode"];
$sub_fullname = $_POST["sub_fullname"];

// Fetch Course Outcomes for the subject
$co_result = $assessmentService->getCOsBySubjectId($subject_id);
$courseOutcomes = [];
if ($co_result['status'] == 1) {
    $courseOutcomes = $co_result['data'];
}

// Fetch additional questionnaire questions for the subject
$qn_result = $feedbackService->getSubjectQuestionnaireQuestions($subject_id);
$questionnaireQuestions = [];
if ($qn_result['status'] == 1) {
    $questionnaireQuestions = $qn_result['data'];
}

// Check if student has already given feedback when the page loads initially
$co_feedback_submitted = false;
$qn_feedback_submitted = false;

$check_co_result = $feedbackService->hasStudentGivenCOFeedback($student_id, $subject_id, $class_id);
if ($check_co_result['status'] == 1 && $check_co_result['exists']) {
    $co_feedback_submitted = true;
}

$check_qn_result = $feedbackService->hasStudentSubmittedQuestionnaireResponses($student_id, $subject_id);
if ($check_qn_result['status'] == 1 && $check_qn_result['exists']) {
    $qn_feedback_submitted = true;
}

// Determine the overall feedback status message
$has_feedback = !empty($courseOutcomes) || !empty($questionnaireQuestions);
$co_complete = empty($courseOutcomes) || $co_feedback_submitted;
$qn_complete = empty($questionnaireQuestions) || $qn_feedback_submitted;
$feedback_submitted = $has_feedback && $co_complete && $qn_complete;

if ($feedback_submitted) {
    $success_message = "You have already provided feedback for this course.";
}

// Generate secret code for CSRF protection if not already set
if (empty($_SESSION['secretcode'])) {
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
}

?>

<div class="container">
    <div class="card">
        <div class="card-header bg-success text-white">
            Feedback - <?= htmlspecialchars($subcode) ?> - <?= htmlspecialchars($sub_fullname) ?>
        </div>
        <div class="card-body">

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (!$feedback_submitted): // Only show the form if feedback is not submitted ?>

                <p>Please provide your feedback on the following:</p>

                <form method="post" action="studentfeedbacksubjects.php">
                    <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="feedback_submitted" value="1">
                    <input type="hidden" name="subject_id" value="<?= htmlspecialchars($subject_id) ?>">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>">
                    <input type="hidden" name="subcode" value="<?= htmlspecialchars($_POST['subcode']) ?>">
                    <input type="hidden" name="sub_fullname" value="<?= htmlspecialchars($_POST['sub_fullname']) ?>">
                    <input type="hidden" name="classname" value="<?= htmlspecialchars($_POST['classname']) ?>">
                    <input type="hidden" name="acad_year" value="<?= htmlspecialchars($_POST['acad_year']) ?>">

                    <?php if (!empty($courseOutcomes)): ?>
                        <h5>Feedback on Course Outcomes</h5>
                        <table class="table table-bordered mb-4">
                            <thead class="thead-light">
                                <tr>
                                    <th>CO Number</th>
                                    <th>Course Outcome Description</th>
                                    <th style="width: 150px;">Your Rating (1-5)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courseOutcomes as $co): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($co['co_number']) ?></td>
                                        <td><?= htmlspecialchars($co['co_description']) ?></td>
                                        <td>
                                            <select name="co_rating_<?= htmlspecialchars($co['id']) ?>" class="form-select" required>
                                                <option value="">-- Select --</option>
                                                <option value="1">1 - Poor</option>
                                                <option value="2">2 - Fair</option>
                                                <option value="3">3 - Good</option>
                                                <option value="4">4 - Very Good</option>
                                                <option value="5">5 - Excellent</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No Course Outcomes found for this subject.</p>
                    <?php endif; ?>

                    <?php if (!empty($questionnaireQuestions)): ?>
                         <h5>Additional Feedback Questions</h5>
                         <table class="table table-bordered mb-4">
                             <thead class="thead-light">
                                 <tr>
                                     <th>Q. No.</th>
                                     <th>Question</th>
                                     <th style="width: 150px;">Your Rating (1-5)</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($questionnaireQuestions as $qn): ?>
                                     <tr>
                                         <td><?= htmlspecialchars($qn['question_number']) ?></td>
                                         <td><?= htmlspecialchars($qn['question_text']) ?></td>
                                         <td>
                                             <select name="qn_rating_<?= htmlspecialchars($qn['id']) ?>" class="form-select" required>
                                                 <option value="">-- Select --</option>
                                                 <option value="1">1 - Poor</option>
                                                 <option value="2">2 - Fair</option>
                                                 <option value="3">3 - Good</option>
                                                 <option value="4">4 - Very Good</option>
                                                 <option value="5">5 - Excellent</option>
                                             </select>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                    <?php else: ?>
                         <p>No additional feedback questions found for this subject.</p>
                    <?php endif; ?>

                    <?php if (!empty($courseOutcomes) || !empty($questionnaireQuestions)): // Only show submit button if there's something to submit ?>
                         <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    <?php endif; ?>

                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>

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

if (empty($_POST['student_id']) || empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership((int)$_POST['student_id'], $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}

$student_id = (int)$_POST['student_id'];
$subject_id = (int)$_POST['subject_id'];
$faculty_id = (int)$_POST['faculty_id'];

if (!$studentRepo->verifySubjectEnrollment($student_id, $subject_id)) {
    header('Location: ./studenthome.php');
    exit();
}

// Ensure the faculty member is mapped to this course
$assigned_faculties = $assessmentService->getFacultiesBySubjectId($subject_id);
$valid_faculty_ids = array_column($assigned_faculties, 'faculty_id');
if (!in_array($faculty_id, $valid_faculty_ids)) {
    header('Location: ./studenthome.php');
    exit();
}

if ($feedbackService->hasStudentSubmittedFacultyFeedback($student_id, $subject_id, $faculty_id)) {
    $_SESSION['flash_msg'] = "Faculty feedback already submitted.";
    header("Location: studentfeedbacksubjects.php");
    exit();
}

// Ensure course has COs configured
$co_result = $assessmentService->getCOsBySubjectId($subject_id);
if (empty($co_result['data'])) {
    $_SESSION['flash_msg'] = "Feedback is not available for this course as Course Outcomes (COs) are not configured.";
    header("Location: studentfeedbacksubjects.php");
    exit();
}

$error = "";

// Process Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_faculty_feedback'])) {
    if (empty($_POST['secretcode']) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $_POST['secretcode'])) {
        $error = "Session expired or invalid CSRF token. Please try again.";
    } else {
        $ratings = [];
        for ($i = 1; $i <= 19; $i++) {
            $val = $_POST["fac_q$i"] ?? '';
            if ($val === '') {
                $error = "Please rate all 19 evaluation parameters.";
                break;
            }
            $ratings["fac_q$i"] = (int)$val;
        }

        $comments = [
            'strengths'           => trim($_POST['faculty_strengths'] ?? ''),
            'improvement_areas'   => trim($_POST['improvement_areas'] ?? ''),
            'additional_comments' => trim($_POST['additional_comments'] ?? '')
        ];

        if (empty($error)) {
            $save = $feedbackService->saveFacultyFeedback($student_id, $subject_id, $faculty_id, $ratings, $comments);
            if ($save['status'] == 1) {
                $_SESSION['flash_msg'] = "Faculty feedback submitted successfully!";
                header("Location: studentfeedbacksubjects.php");
                exit();
            } else {
                $error = $save['err'] ?? "Failed to save feedback. Please try again.";
            }
        }
    }
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$meta = $assessmentService->getSubjectHeaderDetails($subject_id);
$faculty_details = $assessmentService->getFacultyDetailsById($faculty_id);
$faculty_name = $faculty_details['faculty_name'] ?? '';
$designation  = $faculty_details['designation'] ?? '';

$fac_sections = [
    'A. Course Delivery, Content Coverage & CO–PO Linkage' => [
        'fac_q1' => '1. Faculty clearly communicated the Course Outcomes (COs) and their relevance at the start of the course',
        'fac_q2' => '2. Syllabus was covered systematically as per the course plan / lesson plan',
        'fac_q3' => '3. Depth of subject knowledge and clarity in explaining fundamental concepts',
        'fac_q4' => '4. Use of real-life examples, case studies or applications linking theory to Program Outcomes (POs)',
        'fac_q5' => '5. Pace of teaching was appropriate and matched student comprehension level'
    ],
    'B. Teaching Methodology & Use of ICT' => [
        'fac_q6' => '6. Effective use of ICT tools / PPTs / models / simulations / demonstrations to enhance learning',
        'fac_q7' => '7. Encourages active learning through discussions, quizzes, assignments and problem-solving',
        'fac_q8' => '8. Design of learning activities addressing higher-order thinking (analysis, evaluation, creation) as per Bloom\'s Taxonomy'
    ],
    'C. Assessment & Feedback Practices' => [
        'fac_q9'  => '9. Fairness, transparency and objectivity in internal assessment / evaluation',
        'fac_q10' => '10. Timely feedback provided on assignments, tests and tutorials',
        'fac_q11' => '11. Assessment questions are appropriately mapped to Course Outcomes (COs) and varied difficulty levels'
    ],
    'D. Communication & Classroom Management' => [
        'fac_q12' => '12. Communication skills - clarity, audibility and language proficiency',
        'fac_q13' => '13. Punctuality and regularity in conducting classes',
        'fac_q14' => '14. Approachability and willingness to clear doubts inside / outside the classroom',
        'fac_q15' => '15. Maintains discipline and a conducive learning environment'
    ],
    'E. Outcome Attainment & Overall Rating' => [
        'fac_q16' => '16. Confidence gained by students in achieving the stated Course Outcomes (COs)',
        'fac_q17' => '17. Contribution of the course towards attainment of Program Outcomes (POs) / Program Specific Outcomes (PSOs)',
        'fac_q18' => '18. Overall satisfaction with the course delivery',
        'fac_q19' => '19. Overall rating of the faculty member'
    ]
];

$page_title = "Student Feedback on Faculty";
require_once("stheader.php");
?>

<div class="container my-4">
    <div class="card shadow">
        <div class="card-header bg-success text-white text-center py-3">
            <h5 class="mb-0 text-uppercase">JNTUA College of Engineering Ananthapuramu</h5>
            <h6 class="mb-0 text-light">Department of <?= htmlspecialchars($meta['dept_fullname'] ?? ''); ?></h6>
            <h6 class="mb-0 fw-bold mt-1 text-white">STUDENT FEEDBACK ON FACULTY</h6>
        </div>
        <div class="card-body">
            <table class="table table-bordered mb-4 small bg-light">
                <tr>
                    <td style="width: 50%;"><strong>Department:</strong> <?= htmlspecialchars($meta['dept_fullname'] ?? '—'); ?></td>
                    <td style="width: 50%;"><strong>Academic Year:</strong> <?= htmlspecialchars($meta['acad_year'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Programme (B. Tech):</strong> <?= htmlspecialchars($meta['prog_fullname'] ?? 'B. Tech'); ?></td>
                    <td><strong>Semester:</strong> <?= htmlspecialchars($meta['yearsem'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Course Name:</strong> <?= htmlspecialchars($meta['sub_fullname'] ?? '—'); ?></td>
                    <td><strong>Course Code:</strong> <?= htmlspecialchars($meta['subcode'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Faculty Name:</strong> <?= htmlspecialchars($faculty_name); ?> (<?= htmlspecialchars($designation); ?>)</td>
                    <td><strong>Date:</strong> <?= date('d-m-Y'); ?></td>
                </tr>
            </table>

            <div class="alert alert-info py-2 small">
                <em>Instructions: Please rate each parameter on a five-point scale. Your feedback is a vital input for continuous improvement of teaching-learning quality and attainment of Course Outcomes (COs) / Program Outcomes (POs) under the OBE framework. Responses will be kept confidential.</em>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">
                <input type="hidden" name="student_id" value="<?= $student_id; ?>">
                <input type="hidden" name="subject_id" value="<?= $subject_id; ?>">
                <input type="hidden" name="faculty_id" value="<?= $faculty_id; ?>">

                <p class="text-muted small mb-2">Rating Scale: <strong>1 = Poor | 2 = Fair | 3 = Good | 4 = Very Good | 5 = Excellent</strong></p>

                <?php foreach ($fac_sections as $sec_title => $questions): ?>
                    <div class="card mb-3 border-light shadow-sm">
                        <div class="card-header bg-light fw-bold text-success py-2"><?= $sec_title; ?></div>
                        <div class="card-body p-0">
                            <table class="table table-bordered mb-0 align-middle">
                                <tbody>
                                    <?php foreach ($questions as $q_key => $q_text): 
                                        $f_val = $_POST[$q_key] ?? '';
                                    ?>
                                        <tr>
                                            <td><?= $q_text; ?></td>
                                            <td style="width: 200px;">
                                                <select name="<?= $q_key; ?>" class="form-select" required>
                                                    <option value="">-- Select --</option>
                                                    <option value="1" <?= ($f_val === '1') ? 'selected' : ''; ?>>1 - Poor</option>
                                                    <option value="2" <?= ($f_val === '2') ? 'selected' : ''; ?>>2 - Fair</option>
                                                    <option value="3" <?= ($f_val === '3') ? 'selected' : ''; ?>>3 - Good</option>
                                                    <option value="4" <?= ($f_val === '4') ? 'selected' : ''; ?>>4 - Very Good</option>
                                                    <option value="5" <?= ($f_val === '5') ? 'selected' : ''; ?>>5 - Excellent</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <h5 class="text-success mt-4">Open-Ended Feedback</h5>
                <div class="mb-3">
                    <label class="form-label fw-bold">Strengths of the Faculty Member:</label>
                    <textarea name="faculty_strengths" class="form-control" rows="2" placeholder="Write feedback here..."><?= htmlspecialchars($_POST['faculty_strengths'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Areas Suggested for Improvement:</label>
                    <textarea name="improvement_areas" class="form-control" rows="2" placeholder="Write feedback here..."><?= htmlspecialchars($_POST['improvement_areas'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Any Other Suggestions / Comments:</label>
                    <textarea name="additional_comments" class="form-control" rows="2" placeholder="Write suggestions here..."><?= htmlspecialchars($_POST['additional_comments'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="studentfeedbacksubjects.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="submit_faculty_feedback" class="btn btn-success px-4">Submit Faculty Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("stfooter.php"); ?>

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

if (!$studentRepo->verifySubjectEnrollment($student_id, $subject_id)) {
    header('Location: ./studenthome.php');
    exit();
}

if ($feedbackService->hasStudentSubmittedCES($student_id, $subject_id)) {
    $_SESSION['flash_msg'] = "Course End Survey already submitted.";
    header("Location: studentfeedbacksubjects.php");
    exit();
}

$error = "";

// Fetch COs and Header details
$co_data = $assessmentService->getCOsBySubjectId($subject_id)['data'] ?? [];
if (empty($co_data)) {
    $_SESSION['flash_msg'] = "Course Outcomes (COs) are not configured for this course yet.";
    header("Location: studentfeedbacksubjects.php");
    exit();
}
$has_cos = !empty($co_data);
$co_check = $feedbackService->hasStudentGivenCOFeedback($student_id, $subject_id);
$part_a_already_submitted = ($has_cos && $co_check['status'] == 1 && $co_check['exists']);

// Process Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_ces'])) {
    if (empty($_POST['secretcode']) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $_POST['secretcode'])) {
        $error = "Session expired or invalid CSRF token. Please try again.";
    } else {
        $co_ratings = [];
        if ($has_cos && !$part_a_already_submitted) {
            foreach ($co_data as $co) {
                $val = $_POST['co_rating_' . $co['id']] ?? '';
                if ($val === '') {
                    $error = "Please provide an attainment rating for all Course Outcomes.";
                    break;
                }
                $co_ratings[$co['id']] = (int)$val;
            }
        }

        $ces_ratings = [];
        if (empty($error)) {
            for ($i = 1; $i <= 16; $i++) {
                $val = $_POST["ces_q$i"] ?? '';
                if ($val === '') {
                    $error = "Please rate all 16 parameters in Part B.";
                    break;
                }
                $ces_ratings["ces_q$i"] = (int)$val;
            }
        }

        $comments = [
            'useful_aspects'     => trim($_POST['useful_aspects'] ?? ''),
            'improvement_topics' => trim($_POST['improvement_topics'] ?? ''),
            'suggestions'        => trim($_POST['suggestions'] ?? '')
        ];

        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

        if (empty($error)) {
            $save = $feedbackService->saveCourseEndSurvey($student_id, $subject_id, $co_ratings, $ces_ratings, $comments, $is_anonymous);
            if ($save['status'] == 1) {
                $_SESSION['flash_msg'] = "Course End Survey submitted successfully!";
                header("Location: studentfeedbacksubjects.php");
                exit();
            } else {
                $error = $save['err'] ?? "Failed to save survey. Please try again.";
            }
        }
    }
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$meta = $assessmentService->getSubjectHeaderDetails($subject_id);
$faculties = $assessmentService->getFacultiesBySubjectId($subject_id);
$instructor_names = !empty($faculties) ? implode(', ', array_column($faculties, 'faculty_name')) : 'Not Assigned';

$ces_sections = [
    'A. Course Content, Design & Relevance' => [
        'ces_q1' => '1. Course content was well organized and aligned with the stated Course Outcomes (COs)',
        'ces_q2' => '2. Course content was relevant and adequate to meet the Program Outcomes (POs) / Program Specific Outcomes (PSOs)',
        'ces_q3' => '3. Level of difficulty of the course was appropriate for the semester / year',
        'ces_q4' => '4. Balance between theoretical concepts and practical / laboratory component'
    ],
    'B. Teaching-Learning Process' => [
        'ces_q5' => '5. Teaching methods used (lectures, demonstrations, ICT tools, activities) supported effective learning',
        'ces_q6' => '6. Adequate opportunities were provided for self-learning, projects, or applications beyond the classroom',
        'ces_q7' => '7. Pace and sequencing of topics facilitated understanding and attainment of COs'
    ],
    'C. Learning Resources' => [
        'ces_q8'  => '8. Prescribed textbooks / reference materials / e-resources were adequate and useful',
        'ces_q9'  => '9. Laboratory / workshop / software tools and infrastructure supported the intended learning outcomes',
        'ces_q10' => '10. Library, LMS and other institutional resources supported the course effectively'
    ],
    'D. Assessment & Evaluation' => [
        'ces_q11' => '11. Internal assessments (tests, assignments, quizzes) were fair and appropriately mapped to COs',
        'ces_q12' => '12. Assessment methods evaluated the intended knowledge, skills and application level (Bloom\'s Taxonomy)',
        'ces_q13' => '13. Timely feedback was given on performance in assessments'
    ],
    'E. Overall Outcome & Satisfaction' => [
        'ces_q14' => '14. The course enhanced problem-solving, analytical or design skills relevant to the discipline',
        'ces_q15' => '15. Overall, the course objectives and Course Outcomes (COs) were achieved',
        'ces_q16' => '16. Overall satisfaction with the course'
    ]
];

$page_title = "Course End Survey (CES)";
require_once("stheader.php");
?>

<div class="container my-4">
    <div class="card shadow">
        <div class="card-header bg-dark text-white text-center py-3">
            <h5 class="mb-0 text-uppercase">JNTUA College of Engineering Ananthapuramu</h5>
            <h6 class="mb-0 text-light">Department of <?= htmlspecialchars($meta['dept_fullname'] ?? ''); ?></h6>
            <h6 class="mb-0 fw-bold mt-1 text-warning">COURSE END SURVEY (CES)</h6>
        </div>
        <div class="card-body">
            <table class="table table-bordered mb-4 small bg-light">
                <tr>
                    <td style="width: 50%;"><strong>Department:</strong> <?= htmlspecialchars($meta['dept_fullname'] ?? '—'); ?></td>
                    <td style="width: 50%;"><strong>Academic Year:</strong> <?= htmlspecialchars($meta['acad_year'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Programme:</strong> <?= htmlspecialchars($meta['prog_fullname'] ?? 'B. Tech'); ?></td>
                    <td><strong>Semester / Year:</strong> <?= htmlspecialchars($meta['yearsem'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Course Name:</strong> <?= htmlspecialchars($meta['sub_fullname'] ?? '—'); ?></td>
                    <td><strong>Course Code:</strong> <?= htmlspecialchars($meta['subcode'] ?? '—'); ?></td>
                </tr>
                <tr>
                    <td><strong>Course Instructor(s):</strong> <?= htmlspecialchars($instructor_names); ?></td>
                    <td><strong>Date:</strong> <?= date('d-m-Y'); ?></td>
                </tr>
            </table>

            <div class="alert alert-secondary py-2 small">
                <em>Instructions: This survey is administered at the end of the course to assess the extent of attainment of Course Outcomes (COs) and to gather feedback on the overall course experience under the OBE framework. Responses will be used only for academic quality improvement and accreditation records.</em>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode']; ?>">
                <input type="hidden" name="student_id" value="<?= $student_id; ?>">
                <input type="hidden" name="subject_id" value="<?= $subject_id; ?>">

                <div class="card mb-4 border-info">
                    <div class="card-header bg-info text-white py-1"><strong>Student Identification (Optional)</strong></div>
                    <div class="card-body py-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_anonymous" id="is_anonymous" value="1" <?= (isset($_POST['is_anonymous'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_anonymous">
                                Submit this survey anonymously (exclude Roll No: <strong><?= htmlspecialchars($_SESSION['user']); ?></strong> from reports)
                            </label>
                        </div>
                    </div>
                </div>

                <h5 class="text-primary mt-3">PART A: Course Outcome (CO) Attainment — Self Assessment</h5>
                <?php if (!$has_cos): ?>
                    <div class="alert alert-secondary mb-4"><em>No specific Course Outcomes mapped for this course. You may proceed directly to Part B.</em></div>
                <?php elseif ($part_a_already_submitted): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4 py-2" role="alert">
                        <span class="badge bg-success me-2">Completed</span>
                        <div>You have already submitted your Course Outcome (CO) Attainment self-assessment for this course. Please complete Part B and Part C below.</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-2">Rating Scale: <strong>1 = Not Attained | 2 = Low | 3 = Moderate | 4 = High | 5 = Fully Attained</strong></p>
                    <table class="table table-bordered align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">CO No.</th>
                                <th>Course Outcome (CO) Statement</th>
                                <th style="width: 200px;">Attainment Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($co_data as $co): 
                                $co_val = $_POST['co_rating_' . $co['id']] ?? '';
                            ?>
                                <tr>
                                    <td><strong>CO<?= htmlspecialchars($co['co_number']); ?></strong></td>
                                    <td><?= htmlspecialchars($co['co_description']); ?></td>
                                    <td>
                                        <select name="co_rating_<?= $co['id']; ?>" class="form-select" required>
                                            <option value="">-- Select --</option>
                                            <option value="1" <?= ($co_val === '1') ? 'selected' : ''; ?>>1 - Not Attained</option>
                                            <option value="2" <?= ($co_val === '2') ? 'selected' : ''; ?>>2 - Low</option>
                                            <option value="3" <?= ($co_val === '3') ? 'selected' : ''; ?>>3 - Moderate</option>
                                            <option value="4" <?= ($co_val === '4') ? 'selected' : ''; ?>>4 - High</option>
                                            <option value="5" <?= ($co_val === '5') ? 'selected' : ''; ?>>5 - Fully Attained</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h5 class="text-primary mt-4">PART B: General Course Survey</h5>
                <p class="text-muted small mb-2">Rating Scale: <strong>1 = Poor | 2 = Fair | 3 = Good | 4 = Very Good | 5 = Excellent</strong></p>
                <?php foreach ($ces_sections as $section_title => $questions): ?>
                    <div class="card mb-3 border-light shadow-sm">
                        <div class="card-header bg-light fw-bold text-dark py-2"><?= $section_title; ?></div>
                        <div class="card-body p-0">
                            <table class="table table-bordered mb-0 align-middle">
                                <tbody>
                                    <?php foreach ($questions as $q_key => $q_text): 
                                        $q_val = $_POST[$q_key] ?? '';
                                    ?>
                                        <tr>
                                            <td><?= $q_text; ?></td>
                                            <td style="width: 200px;">
                                                <select name="<?= $q_key; ?>" class="form-select" required>
                                                    <option value="">-- Select --</option>
                                                    <option value="1" <?= ($q_val === '1') ? 'selected' : ''; ?>>1 - Poor</option>
                                                    <option value="2" <?= ($q_val === '2') ? 'selected' : ''; ?>>2 - Fair</option>
                                                    <option value="3" <?= ($q_val === '3') ? 'selected' : ''; ?>>3 - Good</option>
                                                    <option value="4" <?= ($q_val === '4') ? 'selected' : ''; ?>>4 - Very Good</option>
                                                    <option value="5" <?= ($q_val === '5') ? 'selected' : ''; ?>>5 - Excellent</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <h5 class="text-primary mt-4">PART C: Open-Ended Feedback</h5>
                <div class="mb-3">
                    <label class="form-label fw-bold">Most useful / effective aspect(s) of this course:</label>
                    <textarea name="useful_aspects" class="form-control" rows="2" placeholder="Write feedback here..."><?= htmlspecialchars($_POST['useful_aspects'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Topics / areas that need more emphasis or improvement:</label>
                    <textarea name="improvement_topics" class="form-control" rows="2" placeholder="Write feedback here..."><?= htmlspecialchars($_POST['improvement_topics'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Any other suggestions for improving this course in future offerings:</label>
                    <textarea name="suggestions" class="form-control" rows="2" placeholder="Write suggestions here..."><?= htmlspecialchars($_POST['suggestions'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="studentfeedbacksubjects.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="submit_ces" class="btn btn-primary px-4">Submit Course End Survey</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("stfooter.php"); ?>

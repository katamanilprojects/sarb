<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "View Feedback Reports";
require_once("faculty.class.php");
require_once("feedbackservice.class.php");

$facultyObj = new Faculty();
$feedbackService = new FeedbackService();

if (empty($_SESSION['facid'])) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_FEEDBACK');

$selected_fac_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($selected_fac_id);
$assignedYears = $feedbackService->getFacultyAssignedYears($selected_fac_id);

$selected_acad_year = !empty($_POST['acad_year']) ? $_POST['acad_year'] : (!empty($_GET['acad_year']) ? $_GET['acad_year'] : '');
$view_level = $_POST['view_level'] ?? $_GET['view_level'] ?? 'subject';
$selected_sub_id = null;
$feedbackData = [];
$coFeedbackReport = [];
$qnFeedbackReport = [];
$totalStudents = 0;
$subjects = [];

// Filter assigned subjects by academic year if chosen
$filteredFacultySubjects = [];
if (!empty($facultySubjects['data'])) {
    foreach ($facultySubjects['data'] as $subItem) {
        if (empty($selected_acad_year) || ($subItem['acad_year'] ?? '') === $selected_acad_year) {
            $filteredFacultySubjects[] = $subItem;
        }
    }
}

// Handle subject selection
$allowedSubIds = array_column($facultySubjects['data'] ?? [], 'id');
$filteredSubIds = array_column($filteredFacultySubjects, 'id');
$auth_error = null;

if ($view_level === 'faculty') {
    // Faculty self-appraisal (accurately filtered by academic year when selected)
    $feedbackData = $feedbackService->getFacultyFeedback($selected_fac_id, null, $selected_acad_year);
    if (($feedbackData['status'] ?? 0) == 1) {
        $coFeedbackReport = $feedbackData['co_feedback'] ?? [];
        $subjects = $feedbackData['subjects'] ?? [];
    }
} else {
    // Subject-wise feedback
    if (!empty($_POST['sub_id'])) {
        $selected_sub_id = intval($_POST['sub_id']);
    } elseif (!empty($_GET['sub_id'])) {
        $selected_sub_id = intval($_GET['sub_id']);
    }

    if (!empty($selected_sub_id)) {
        if (!in_array($selected_sub_id, $allowedSubIds)) {
            $auth_error = "Access Denied: You are not assigned to this subject.";
            $selected_sub_id = null;
        } elseif (!empty($selected_acad_year) && !in_array($selected_sub_id, $filteredSubIds)) {
            // Selected subject does not belong to the selected academic year
            $selected_sub_id = null;
        } else {
            $feedbackData = $feedbackService->getSubjectFeedback($selected_sub_id);
            if (($feedbackData['status'] ?? 0) == 1) {
                $coFeedbackReport = $feedbackData['co_feedback'] ?? [];
                $qnFeedbackReport = $feedbackData['qn_feedback'] ?? [];
                $totalStudents = $feedbackData['total_enrolled'] ?? 0;
                // Align academic year if not explicitly posted or set
                if (!isset($_POST['acad_year']) && empty($selected_acad_year) && !empty($feedbackData['meta']['acad_year'])) {
                    $selected_acad_year = $feedbackData['meta']['acad_year'];
                }
            }
        }
    }
}

// Generate new secret code for CSRF protection
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php");
?>

<div class="container my-4">
    <div class="row">
        <div class="col-sm-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary-subtle py-2">
                    <h6 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i>Course Feedback Reports</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($auth_error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div><?= htmlspecialchars($auth_error); ?></div>
                        </div>
                    <?php endif; ?>
                    <form action="facviewfeedback.php" method="post" id="feedbackReportForm">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="view_level" class="form-label fw-bold">Feedback Report Type:</label>
                                <select name="view_level" id="view_level" class="form-select border-primary" onchange="this.form.submit()">
                                    <option value="subject" <?= ($view_level === 'subject') ? 'selected' : '' ?>>Subject-wise Feedback</option>
                                    <option value="faculty" <?= ($view_level === 'faculty') ? 'selected' : '' ?>>Faculty Self-Appraisal (Comprehensive)</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="acad_year" class="form-label fw-bold">Academic Year:</label>
                                <select name="acad_year" id="acad_year" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- All Academic Years --</option>
                                    <?php foreach ($assignedYears as $ay): ?>
                                        <option value="<?= htmlspecialchars($ay) ?>" <?= ($selected_acad_year === $ay) ? 'selected' : '' ?>>
                                             <?= htmlspecialchars($ay) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($view_level === 'subject'): ?>
                                <div class="col-md-6">
                                    <label for="sub_id" class="form-label fw-bold">Select Assigned Subject:</label>
                                    <select name="sub_id" id="sub_id" class="form-select" required onchange="this.form.submit()">
                                        <option value="">-- Choose Subject --</option>
                                        <?php
                                        if (!empty($filteredFacultySubjects)) {
                                            foreach ($filteredFacultySubjects as $subject) {
                                                $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                                echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh Self-Appraisal
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($view_level === 'subject'): ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-eye-fill me-1"></i>View Feedback Report
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php
            // Hide the redundant raw Course Outcomes Breakdown in faculty self-appraisal view,
            // as faculty inspect comprehensive CO indirect attainment under "Subject-wise Feedback".
            $hideFacultyCOBreakdown = true;
            require_once("modulefeedback.php");
            ?>
        </div>
    </div>
</div>

<?php
require_once("facfooter.php");
?>

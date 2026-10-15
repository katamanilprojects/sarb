<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "HOD - View Feedback Reports";
require_once("hod.class.php");
require_once("feedbackservice.class.php");

$hodObj = new HOD();
$feedbackService = new FeedbackService();

// Verify user is logged in as HOD
if (empty($_SESSION['user']) || $_SESSION['role'] !== 'hod' || empty($_SESSION['dept_id'])) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_FEEDBACK');

$dept_id = intval($_SESSION['dept_id']);

// View level selection: 'class', 'faculty', 'subject'
$view_level = $_POST['view_level'] ?? $_GET['view_level'] ?? 'subject';

$selected_prog_id   = !empty($_POST['prog_id']) ? intval($_POST['prog_id']) : (!empty($_GET['prog_id']) ? intval($_GET['prog_id']) : null);
$selected_acad_year = !empty($_POST['acad_year']) ? $_POST['acad_year'] : (!empty($_GET['acad_year']) ? $_GET['acad_year'] : '');
$selected_spec_id   = !empty($_POST['spec_id']) ? intval($_POST['spec_id']) : (!empty($_GET['spec_id']) ? intval($_GET['spec_id']) : null);
$selected_cls_id    = !empty($_POST['cls_id']) ? intval($_POST['cls_id']) : (!empty($_GET['cls_id']) ? intval($_GET['cls_id']) : null);
$selected_fac_id    = !empty($_POST['fac_id']) ? intval($_POST['fac_id']) : (!empty($_GET['fac_id']) ? intval($_GET['fac_id']) : null);
$selected_sub_id    = !empty($_POST['sub_id']) ? $_POST['sub_id'] : (!empty($_GET['sub_id']) ? $_GET['sub_id'] : null);

$feedbackData = [];
$coFeedbackReport = [];
$qnFeedbackReport = [];
$totalStudents = 0;
$classes = [];
$subjects = [];

// Available Programs & Academic Years for Department
$allPrograms = $feedbackService->getAllPrograms();
$allAcadYears = $feedbackService->getAllAcademicYears();

// Specializations for HOD department (filtered by program if selected)
$specializations = $feedbackService->getSpecializationsByDepartmentAndProgram($dept_id, $selected_prog_id);

// Faculty for HOD department
$deptFaculty = $feedbackService->getFacultyByDepartment($dept_id);

// Classes for HOD department filtered by program, specialization, and academic year
$classes = $feedbackService->getClassesFiltered($dept_id, $selected_prog_id, $selected_spec_id, $selected_acad_year);

// If class is selected, fetch its subjects
if (!empty($selected_cls_id)) {
    $subjectsRes = $hodObj->getSubjectsByClassID($selected_cls_id);
    $subjects = $subjectsRes['data'] ?? [];
}

// Determine if submission / data loading should occur
$formSubmitted = !empty($_POST['btn_view']) || !empty($_GET['sub_id']) || (!empty($_POST['sub_id']) && $view_level === 'subject') || ($view_level === 'class' && !empty($selected_cls_id) && !empty($_POST['btn_view'])) || ($view_level === 'faculty' && !empty($selected_fac_id) && !empty($_POST['btn_view'])) || ($view_level === 'department');

if ($formSubmitted) {
    if ($view_level === 'department') {
        $feedbackData = $feedbackService->getDepartmentFeedback($dept_id, null, null, null, $selected_acad_year, $selected_prog_id);
    } elseif ($view_level === 'class' && !empty($selected_cls_id)) {
        $feedbackData = $feedbackService->getClassFeedback($selected_cls_id);
        $selected_sub_id = 'all';
    } elseif ($view_level === 'faculty' && !empty($selected_fac_id)) {
        $feedbackData = $feedbackService->getFacultyFeedback($selected_fac_id, $selected_cls_id);
    } elseif ($view_level === 'subject') {
        if ($selected_sub_id === 'all' && !empty($selected_cls_id)) {
            $feedbackData = $feedbackService->getClassFeedback($selected_cls_id);
            $view_level = 'class';
        } elseif (!empty($selected_sub_id) && $selected_sub_id !== 'all') {
            $feedbackData = $feedbackService->getSubjectFeedback(intval($selected_sub_id), $selected_cls_id);
        }
    }

    if (!empty($feedbackData) && ($feedbackData['status'] ?? 0) == 1) {
        $coFeedbackReport = $feedbackData['co_feedback'] ?? [];
        $qnFeedbackReport = $feedbackData['qn_feedback'] ?? [];
        $totalStudents = $feedbackData['total_enrolled'] ?? ($feedbackData['total_students'] ?? 0);
    }
}

// Generate new CSRF secretcode
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("hodheader.php");
?>

<div class="container my-4">
    <div class="row">
        <div class="col-sm-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary-subtle py-2">
                    <h6 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>Department Feedback Reports</h6>
                </div>
                <div class="card-body">
                    <form action="hodviewfeedback.php" method="post" id="hodFeedbackForm">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">

                        <!-- Primary View & Scope Filters -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="view_level" class="form-label fw-bold">Select View Level:</label>
                                <select name="view_level" id="view_level" class="form-select border-primary" onchange="this.form.submit()">
                                    <option value="subject" <?= ($view_level === 'subject') ? 'selected' : '' ?>>Subject-wise Feedback</option>
                                    <option value="class" <?= ($view_level === 'class') ? 'selected' : '' ?>>Class-wise Feedback (All Subjects)</option>
                                    <option value="faculty" <?= ($view_level === 'faculty') ? 'selected' : '' ?>>Faculty-wise Feedback</option>
                                    <option value="department" <?= ($view_level === 'department') ? 'selected' : '' ?>>Department-wide Feedback Roll-up</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="prog_id" class="form-label fw-bold">Program / Degree:</label>
                                <select name="prog_id" id="prog_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- All Programs --</option>
                                    <?php foreach ($allPrograms as $prog): ?>
                                        <option value="<?= $prog['id'] ?>" <?= (!empty($selected_prog_id) && $selected_prog_id == $prog['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prog['prog_fullname']) ?> (<?= htmlspecialchars($prog['prog_shortname']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="acad_year" class="form-label fw-bold">Academic Year:</label>
                                <select name="acad_year" id="acad_year" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- All Academic Years --</option>
                                    <?php foreach ($allAcadYears as $ay): ?>
                                        <option value="<?= htmlspecialchars($ay) ?>" <?= ($selected_acad_year === $ay) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ay) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- CASCADING FILTERS -->
                        <div class="row g-3">
                            <?php if ($view_level === 'department'): ?>
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0 d-flex align-items-center">
                                        <i class="bi bi-info-circle-fill text-primary me-2 fs-5"></i>
                                        <div>Department roll-up aggregates all classes and subjects. Use Program and Academic Year above to narrow scope.</div>
                                    </div>
                                </div>
                            <?php elseif ($view_level === 'faculty'): ?>
                                <!-- Faculty-wise view filters -->
                                <div class="col-md-6">
                                    <label for="fac_id" class="form-label fw-bold">Select Faculty Member:</label>
                                    <select name="fac_id" id="fac_id" class="form-select" required onchange="this.form.submit()">
                                        <option value="">-- Choose Faculty --</option>
                                        <?php foreach ($deptFaculty as $fac): ?>
                                            <option value="<?= $fac['id'] ?>" <?= (!empty($selected_fac_id) && $selected_fac_id == $fac['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($fac['faculty_name']) ?> (<?= htmlspecialchars($fac['designation']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <!-- Class or Subject view filters -->
                                <div class="col-md-4">
                                    <label for="spec_id" class="form-label fw-bold">Specialization:</label>
                                    <select name="spec_id" id="spec_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">-- Choose Specialization (Optional) --</option>
                                        <?php foreach ($specializations as $spec): ?>
                                            <option value="<?= $spec['id'] ?>" <?= (!empty($selected_spec_id) && $selected_spec_id == $spec['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($spec['spec_fullname']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="cls_id" class="form-label fw-bold">Class:</label>
                                    <select name="cls_id" id="cls_id" class="form-select" required onchange="this.form.submit()">
                                        <option value="">-- Choose Class --</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?= $class['id'] ?>" <?= (!empty($selected_cls_id) && $selected_cls_id == $class['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($class['classname']) ?> (<?= htmlspecialchars($class['acad_year']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if ($view_level === 'subject' && !empty($selected_cls_id)): ?>
                                    <div class="col-md-4">
                                        <label for="sub_id" class="form-label fw-bold">Subject:</label>
                                        <select name="sub_id" id="sub_id" class="form-select" required>
                                            <option value="">-- Choose Subject --</option>
                                            <option value="all" <?= ($selected_sub_id === 'all') ? 'selected' : '' ?>>All Subjects (Consolidated)</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?= $subject['id'] ?>" <?= (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($subject['sub_fullname']) ?> (<?= htmlspecialchars($subject['subcode']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="btn_view" value="1" class="btn btn-primary px-4">
                                <i class="bi bi-search me-1"></i>View Feedback Analytics
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            $selected_dept_id = $dept_id;
            require_once("modulefeedback.php");
            ?>
        </div>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>

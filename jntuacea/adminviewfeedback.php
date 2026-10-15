<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_FEEDBACK');

$page_title = "Admin - View Feedback Reports";

require_once("admin.class.php");
require_once("hod.class.php");
require_once("feedbackservice.class.php");

$adminObj = new Admin();
$hodObj = new HOD();
$feedbackService = new FeedbackService();

// View level: 'department', 'class', 'faculty', 'subject'
$view_level = $_POST['view_level'] ?? $_GET['view_level'] ?? 'department';

$selected_dept_id   = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : (!empty($_GET['dept_id']) ? intval($_GET['dept_id']) : null);
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
$departments = $adminObj->getAllDepartments();
$allPrograms = $feedbackService->getAllPrograms();
$allAcadYears = $feedbackService->getAllAcademicYears();

$specializations = [];
$classes = [];
$subjects = [];
$deptFaculty = [];

if (!empty($selected_dept_id)) {
    $specializations = $feedbackService->getSpecializationsByDepartmentAndProgram($selected_dept_id, $selected_prog_id);
    $deptFaculty = $feedbackService->getFacultyByDepartment($selected_dept_id);
    $classes = $feedbackService->getClassesFiltered($selected_dept_id, $selected_prog_id, $selected_spec_id, $selected_acad_year);

    // Validate that selected subordinate IDs belong to the current department
    $validClassIds = array_column($classes, 'id');
    if (!empty($selected_cls_id) && !in_array($selected_cls_id, $validClassIds)) {
        $selected_cls_id = null;
        $selected_sub_id = null;
    }

    $validFacIds = array_column($deptFaculty, 'id');
    if (!empty($selected_fac_id) && !in_array($selected_fac_id, $validFacIds)) {
        $selected_fac_id = null;
    }

    if (!empty($selected_cls_id)) {
        $subRes = $hodObj->getSubjectsByClassID($selected_cls_id);
        $subjects = $subRes['data'] ?? [];
        $validSubIds = array_column($subjects, 'id');
        if (!empty($selected_sub_id) && $selected_sub_id !== 'all' && !in_array(intval($selected_sub_id), $validSubIds)) {
            $selected_sub_id = null;
        }
    } else {
        $selected_sub_id = null;
    }

    // Determine when to fetch feedback data
    $fetchData = false;
    if (!empty($_POST['btn_view'])) {
        $fetchData = true;
    } elseif ($view_level === 'department') {
        $fetchData = true;
    } elseif ($view_level === 'class' && !empty($selected_cls_id)) {
        $fetchData = true;
    } elseif ($view_level === 'faculty' && !empty($selected_fac_id)) {
        $fetchData = true;
    } elseif ($view_level === 'subject' && !empty($selected_sub_id)) {
        $fetchData = true;
    }

    if ($fetchData) {
        if ($view_level === 'department') {
            $feedbackData = $feedbackService->getDepartmentFeedback($selected_dept_id, null, null, null, $selected_acad_year, $selected_prog_id);
        } elseif ($view_level === 'faculty' && !empty($selected_fac_id)) {
            $feedbackData = $feedbackService->getFacultyFeedback($selected_fac_id, $selected_cls_id);
        } elseif ($view_level === 'class' && !empty($selected_cls_id)) {
            $feedbackData = $feedbackService->getClassFeedback($selected_cls_id);
            $selected_sub_id = 'all';
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
}

// Generate CSRF secretcode
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("adminheader.php");
?>

<div class="container my-4">
    <div class="row">
        <div class="col-sm-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary-subtle py-2">
                    <h6 class="mb-0"><i class="bi bi-sliders me-2"></i>Multi-Granularity Course Feedback Analytics (Admin)</h6>
                </div>
                <div class="card-body">
                    <form action="adminviewfeedback.php" method="post" id="adminFeedbackForm">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">

                        <div class="row g-3 mb-3">
                            <!-- Department Selection -->
                            <div class="col-md-3">
                                <label for="dept_id" class="form-label fw-bold">Select Department:</label>
                                <select name="dept_id" id="dept_id" class="form-select border-primary" required onchange="this.form.submit()">
                                    <option value="">-- Choose Department --</option>
                                    <?php foreach ($departments['data'] as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['dept_fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Program Selection -->
                            <div class="col-md-3">
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

                            <!-- Academic Year Selection -->
                            <div class="col-md-3">
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

                            <!-- View Level Selection -->
                            <div class="col-md-3">
                                <label for="view_level" class="form-label fw-bold">View Level:</label>
                                <select name="view_level" id="view_level" class="form-select" onchange="this.form.submit()">
                                    <option value="department" <?= ($view_level === 'department') ? 'selected' : '' ?>>Department-wise Overview</option>
                                    <option value="class" <?= ($view_level === 'class') ? 'selected' : '' ?>>Class-wise Feedback</option>
                                    <option value="faculty" <?= ($view_level === 'faculty') ? 'selected' : '' ?>>Faculty-wise Feedback</option>
                                    <option value="subject" <?= ($view_level === 'subject') ? 'selected' : '' ?>>Subject-wise Feedback</option>
                                </select>
                            </div>
                        </div>

                        <!-- CASCADING CRITERIA -->
                        <?php if (!empty($selected_dept_id)): ?>
                            <div class="row g-3 p-3 bg-light rounded border mb-3">
                                <?php if ($view_level === 'faculty'): ?>
                                    <!-- Faculty Selection -->
                                    <div class="col-md-6">
                                        <label for="fac_id" class="form-label fw-bold">Faculty Member:</label>
                                        <select name="fac_id" id="fac_id" class="form-select" required onchange="this.form.submit()">
                                            <option value="">-- Choose Faculty Member --</option>
                                            <?php foreach ($deptFaculty as $fac): ?>
                                                <option value="<?= $fac['id'] ?>" <?= (!empty($selected_fac_id) && $selected_fac_id == $fac['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($fac['faculty_name']) ?> (<?= htmlspecialchars($fac['designation']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php elseif ($view_level !== 'department'): ?>
                                    <!-- Specialization Selection -->
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

                                    <!-- Class Selection -->
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
                                        <!-- Subject Selection -->
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

                            <div>
                                <button type="submit" name="btn_view" value="1" class="btn btn-primary px-4">
                                    <i class="bi bi-bar-chart-fill me-1"></i>Generate Analytics
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php
            require_once("modulefeedback.php");
            ?>
        </div>
    </div>
</div>

<?php
require_once("adminfooter.php");
?>
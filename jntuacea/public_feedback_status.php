<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// =========================================================================
// CONFIGURATION: Set your temporary Master Passkey here
// Change 'JntuAcea@2026' to your desired access passkey.
// =========================================================================
define('MASTER_PASSKEY', 'JntuAcea@2026');

// Handle Logout / Lock action
if (isset($_POST['action_lock'])) {
    unset($_SESSION['temp_feedback_auth']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Passkey Submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_passkey'])) {
    $entered_pass = trim($_POST['master_passkey'] ?? '');
    if ($entered_pass === MASTER_PASSKEY) {
        $_SESSION['temp_feedback_auth'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $login_error = 'Invalid Master Passkey. Access denied.';
    }
}

$is_authenticated = !empty($_SESSION['temp_feedback_auth']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback Submission Status (Review Portal)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-card { max-width: 440px; margin: 80px auto; }
    </style>
</head>
<body>

<?php if (!$is_authenticated): ?>
    <!-- Passkey Login Gate -->
    <div class="container">
        <div class="card shadow login-card">
            <div class="card-header bg-dark text-white text-center py-3">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Protected Review Portal</h5>
                <small class="text-muted">Student Feedback Submission Status</small>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($login_error)): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="mb-3">
                        <label for="master_passkey" class="form-label fw-bold">Enter Master Passkey:</label>
                        <input type="password" name="master_passkey" id="master_passkey" class="form-control form-control-lg" placeholder="••••••••" required autofocus>
                    </div>
                    <button type="submit" name="submit_passkey" value="1" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-unlock me-1"></i> Authenticate & Proceed
                    </button>
                </form>
            </div>
            <div class="card-footer text-center text-muted small py-2 bg-light">
                Temporary access portal for authorized reviewers.
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Authenticated Content -->
    <?php
    require_once("admin.class.php");
    require_once("faculty.class.php");
    require_once("feedbackservice.class.php");

    $adminObj = new Admin();
    $facultyObj = new Faculty();
    $feedbackService = new FeedbackService();

    $departments = $adminObj->getAllDepartments();

    // Database connection
    $db = DBCredentials::getInstance();
    $conn = $db->getConnection();

    // Determine default Department & Faculty (Default: fac_id = 3, E Keshava Reddy)
    $default_fac_id = 3;
    $default_dept_id = null;
    $stmtDefDept = $conn->prepare("SELECT dept_id FROM faculties WHERE id = ?");
    if ($stmtDefDept) {
        $stmtDefDept->bind_param("i", $default_fac_id);
        $stmtDefDept->execute();
        $stmtDefDept->bind_result($resolvedDeptId);
        if ($stmtDefDept->fetch()) {
            $default_dept_id = intval($resolvedDeptId);
        }
        $stmtDefDept->close();
    }
    // Fallback if not found in db
    if (empty($default_dept_id)) {
        foreach ($departments['data'] as $d) {
            if (stripos($d['dept_fullname'], 'Math') !== false) {
                $default_dept_id = intval($d['id']);
                break;
            }
        }
    }

    $selected_dept_id = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : (!empty($_POST['active_dept_id']) ? intval($_POST['active_dept_id']) : $default_dept_id);
    $faculties = !empty($selected_dept_id) ? $adminObj->getFacultyByDepartment($selected_dept_id) : ['data' => []];

    // If department was changed (submitted via change event with dept_id present but fac_id empty), reset fac_id and keep panel open
    $dept_changed = isset($_POST['dept_id']) && empty($_POST['fac_id']);
    if ($dept_changed) {
        $selected_fac_id = null;
    } else {
        $selected_fac_id = !empty($_POST['fac_id']) ? intval($_POST['fac_id']) : (!empty($_POST['active_fac_id']) ? intval($_POST['active_fac_id']) : $default_fac_id);
    }
    $facultySubjects = !empty($selected_fac_id) ? $facultyObj->getSubjectsByFacultyId($selected_fac_id) : ['data' => []];

    // Keep faculty collapse panel open if department was just changed or panel_open flag sent
    $show_faculty_panel = $dept_changed || !empty($_POST['panel_open']);

    // Selected subject
    $selected_sub_id = !empty($_POST['sub_id']) ? intval($_POST['sub_id']) : null;
    $studentsFeedbackStatus = null;
    $subjectDetails = null;
    $cosCount = 0;
    $hasCOs = false;

    // Step 3: Handle Submit button click
    if (!empty($_POST['sub_id']) && !empty($_POST['btn_submit'])) {
        $selected_sub_id = intval($_POST['sub_id']);

        $db = DBCredentials::getInstance();
        $conn = $db->getConnection();

        // Check if Course Outcomes (COs) are defined
        $stmtCO = $conn->prepare("SELECT COUNT(*) AS total_cos FROM course_outcomes WHERE sub_id = ?");
        if ($stmtCO) {
            $stmtCO->bind_param("i", $selected_sub_id);
            $stmtCO->execute();
            $coRes = $stmtCO->get_result()->fetch_assoc();
            $cosCount = intval($coRes['total_cos'] ?? 0);
            $stmtCO->close();
        }
        $hasCOs = ($cosCount > 0);

        $subjectDetails = $facultyObj->getClassSubjectFacultyDetails($selected_sub_id, $selected_fac_id);

        if ($hasCOs) {
            $mappedStudents = $facultyObj->getMappedStudents($selected_sub_id);

            if (!empty($mappedStudents)) {
                // Pre-fetch CO Feedback submissions
                $coSubmittedMap = [];
                $stmtCOF = $conn->prepare("
                    SELECT student_id, COUNT(DISTINCT co_id) AS submitted_cos 
                    FROM student_co_feedback 
                    WHERE subject_id = ? 
                    GROUP BY student_id
                ");
                if ($stmtCOF) {
                    $stmtCOF->bind_param("i", $selected_sub_id);
                    $stmtCOF->execute();
                    $resCOF = $stmtCOF->get_result();
                    while ($row = $resCOF->fetch_assoc()) {
                        if (intval($row['submitted_cos']) >= $cosCount) {
                            $coSubmittedMap[intval($row['student_id'])] = true;
                        }
                    }
                    $stmtCOF->close();
                }

                // Pre-fetch Course End Survey (CES) submissions
                $cesSubmittedMap = [];
                $stmtCES = $conn->prepare("SELECT DISTINCT student_id FROM student_ces_feedback WHERE subject_id = ?");
                if ($stmtCES) {
                    $stmtCES->bind_param("i", $selected_sub_id);
                    $stmtCES->execute();
                    $resCES = $stmtCES->get_result();
                    while ($row = $resCES->fetch_assoc()) {
                        $cesSubmittedMap[intval($row['student_id'])] = true;
                    }
                    $stmtCES->close();
                }

                // Pre-fetch Faculty Feedback submissions
                $facSubmittedMap = [];
                $stmtFAC = $conn->prepare("SELECT DISTINCT student_id FROM student_faculty_feedback WHERE subject_id = ? AND faculty_id = ?");
                if ($stmtFAC) {
                    $stmtFAC->bind_param("ii", $selected_sub_id, $selected_fac_id);
                    $stmtFAC->execute();
                    $resFAC = $stmtFAC->get_result();
                    while ($row = $resFAC->fetch_assoc()) {
                        $facSubmittedMap[intval($row['student_id'])] = true;
                    }
                    $stmtFAC->close();
                }

                $studentsFeedbackStatus = [];
                foreach ($mappedStudents as $stu) {
                    $sId = intval($stu['id']);
                    $studentsFeedbackStatus[] = [
                        'id'               => $sId,
                        'username'         => $stu['username'],
                        'name'             => $stu['name'],
                        'co_feedback'      => !empty($coSubmittedMap[$sId]),
                        'course_feedback'  => !empty($cesSubmittedMap[$sId]),
                        'faculty_feedback' => !empty($facSubmittedMap[$sId])
                    ];
                }
            } else {
                $studentsFeedbackStatus = [];
            }
        }
    }
    ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-clipboard2-check me-2"></i>Feedback Submission Status
            </span>
            <form method="post" action="" class="d-inline">
                <button type="submit" name="action_lock" value="1" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-lock me-1"></i> Lock / Logout
                </button>
            </form>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row">
            <div class="col-sm-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-filter-square me-2"></i>Select Course / View Feedback Status</h5>
                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#changeFacultyCollapse" aria-expanded="<?= $show_faculty_panel ? 'true' : 'false'; ?>" aria-controls="changeFacultyCollapse">
                            <i class="bi bi-arrow-left-right me-1"></i> Change Faculty / Dept
                        </button>
                    </div>
                    <div class="card-body">
                        <?php
                        // Find current faculty name and dept name for summary display
                        $curDeptName = 'Mathematics';
                        foreach ($departments['data'] as $d) {
                            if ($d['id'] == $selected_dept_id) { $curDeptName = $d['dept_fullname']; break; }
                        }
                        $curFacName = !empty($selected_fac_id) ? 'Prof. E. Keshava Reddy' : '-- Select Faculty --';
                        foreach ($faculties['data'] as $f) {
                            if ($f['id'] == $selected_fac_id) { $curFacName = $f['name']; break; }
                        }
                        ?>
                        <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-3">
                            <div>
                                <span class="badge bg-secondary me-2"><?= htmlspecialchars($curDeptName); ?></span>
                                <strong>Faculty:</strong> <?= htmlspecialchars($curFacName); ?>
                            </div>
                            <small class="text-muted"><?= (!empty($selected_fac_id) && $selected_fac_id == $default_fac_id) ? 'Default profile' : 'Selected profile'; ?></small>
                        </div>

                        <form action="" method="post" id="feedbackStatusForm">
                            <!-- Collapsible Department and Faculty Selectors (Open when department changes) -->
                            <div class="collapse <?= $show_faculty_panel ? 'show' : ''; ?> mb-3 p-3 border rounded bg-light" id="changeFacultyCollapse">
                                <input type="hidden" name="panel_open" id="panel_open" value="<?= $show_faculty_panel ? '1' : '0'; ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="dept_id" class="form-label fw-bold">Department:</label>
                                        <select name="dept_id" id="dept_id" class="form-select">
                                            <option value="">Select Department</option>
                                            <?php if (!empty($departments['data'])): ?>
                                                <?php foreach ($departments['data'] as $dept): ?>
                                                    <?php if ($dept['dept_fullname'] !== "BTech1"): ?>
                                                        <?php $selected = (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : ''; ?>
                                                        <option value="<?= htmlspecialchars($dept['id']); ?>" <?= $selected; ?>>
                                                            <?= htmlspecialchars($dept['dept_fullname']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="fac_id" class="form-label fw-bold">Faculty:</label>
                                        <select name="fac_id" id="fac_id" class="form-select">
                                            <option value="">Select Faculty</option>
                                            <?php if (!empty($faculties['data'])): ?>
                                                <?php foreach ($faculties['data'] as $faculty): ?>
                                                    <?php $selected = (!empty($selected_fac_id) && $selected_fac_id == $faculty['id']) ? 'selected' : ''; ?>
                                                    <option value="<?= htmlspecialchars($faculty['id']); ?>" <?= $selected; ?>>
                                                        <?= htmlspecialchars($faculty['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- If collapse is kept closed, submit the active dept_id and fac_id through hidden inputs or form -->
                            <input type="hidden" name="active_dept_id" value="<?= htmlspecialchars($selected_dept_id); ?>">
                            <input type="hidden" name="active_fac_id" value="<?= htmlspecialchars($selected_fac_id); ?>">

                            <!-- Subject Selection -->
                            <div class="form-group mb-3">
                                <label for="sub_id" class="form-label fw-bold fs-6">Select Course / Subject:</label>
                                <select name="sub_id" id="sub_id" class="form-select form-select-lg" required>
                                    <option value="">-- Choose a Subject Taught by <?= htmlspecialchars($curFacName); ?> --</option>
                                    <?php if (!empty($facultySubjects['data'])): ?>
                                        <?php foreach ($facultySubjects['data'] as $subject): ?>
                                            <?php $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : ''; ?>
                                            <option value="<?= htmlspecialchars($subject['id']); ?>" <?= $selected; ?>>
                                                <?= htmlspecialchars($subject['sub_fullname']); ?> (<?= htmlspecialchars($subject['subcode']); ?>) — <?= htmlspecialchars($subject['class_name']); ?> (<?= htmlspecialchars($subject['acad_year']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No subjects mapped for this faculty</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mt-4">
                                <button type="submit" name="btn_submit" value="1" class="btn btn-primary px-4">
                                    <i class="bi bi-search me-1"></i> Submit & View Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <?php if (!empty($_POST['btn_submit']) && !empty($selected_sub_id)): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Course:</strong> <?= htmlspecialchars($subjectDetails['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['subcode'] ?? ''); ?>) |
                                <strong>Class:</strong> <?= htmlspecialchars($subjectDetails['classname'] ?? ''); ?> |
                                <strong>Faculty:</strong> <?= htmlspecialchars($subjectDetails['faculty_name'] ?? ''); ?>
                            </div>
                            <div>
                                <?php if ($hasCOs): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?= $cosCount; ?> CO(s) Defined</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>No COs Defined</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!$hasCOs): ?>
                                <div class="alert alert-warning mb-0" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <strong>Course Outcomes (COs) Not Configured:</strong> Course Outcomes have not been added for this subject. In accordance with feedback policy, feedback submission is only tracked for courses where COs are configured.
                                </div>
                            <?php else: ?>
                                <?php if (empty($studentsFeedbackStatus)): ?>
                                    <div class="alert alert-info mb-0">No students are currently mapped to this subject.</div>
                                <?php else: ?>
                                    <?php
                                    $totalStudents = count($studentsFeedbackStatus);
                                    $coDoneCount = count(array_filter($studentsFeedbackStatus, fn($s) => $s['co_feedback']));
                                    $courseDoneCount = count(array_filter($studentsFeedbackStatus, fn($s) => $s['course_feedback']));
                                    $facDoneCount = count(array_filter($studentsFeedbackStatus, fn($s) => $s['faculty_feedback']));
                                    ?>
                                    <div class="row mb-3 g-2">
                                        <div class="col-md-3">
                                            <div class="p-2 border rounded text-center bg-light">
                                                <small class="text-muted d-block">Total Enrolled</small>
                                                <strong><?= $totalStudents; ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-2 border rounded text-center bg-light">
                                                <small class="text-muted d-block">CO Feedback</small>
                                                <strong class="text-primary"><?= $coDoneCount; ?> / <?= $totalStudents; ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-2 border rounded text-center bg-light">
                                                <small class="text-muted d-block">Course Feedback (CES)</small>
                                                <strong class="text-success"><?= $courseDoneCount; ?> / <?= $totalStudents; ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-2 border rounded text-center bg-light">
                                                <small class="text-muted d-block">Faculty Feedback</small>
                                                <strong class="text-info"><?= $facDoneCount; ?> / <?= $totalStudents; ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 60px;">#</th>
                                                    <th class="text-start" style="width: 180px;">Roll Number / Username</th>
                                                    <th class="text-start">Student Name</th>
                                                    <th style="width: 140px;">CO Feedback</th>
                                                    <th style="width: 150px;">Course Feedback</th>
                                                    <th style="width: 150px;">Faculty Feedback</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $i = 1; foreach ($studentsFeedbackStatus as $student): ?>
                                                    <tr>
                                                        <td><?= $i++; ?></td>
                                                        <td class="text-start"><strong><?= htmlspecialchars($student['username']); ?></strong></td>
                                                        <td class="text-start"><?= htmlspecialchars($student['name']); ?></td>
                                                        <td>
                                                            <?php if ($student['co_feedback']): ?>
                                                                <span class="text-success fs-5 fw-bold" title="Submitted">&#10004;</span>
                                                            <?php else: ?>
                                                                <span class="text-muted fs-5 fw-bold" title="Not Submitted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($student['course_feedback']): ?>
                                                                <span class="text-success fs-5 fw-bold" title="Submitted">&#10004;</span>
                                                            <?php else: ?>
                                                                <span class="text-muted fs-5 fw-bold" title="Not Submitted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($student['faculty_feedback']): ?>
                                                                <span class="text-success fs-5 fw-bold" title="Submitted">&#10004;</span>
                                                            <?php else: ?>
                                                                <span class="text-muted fs-5 fw-bold" title="Not Submitted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="text-center text-muted py-4 small">
        JNTUACEA Attendance & Feedback Portal &copy; <?= date('Y'); ?>
    </footer>

    <script>
        document.getElementById('dept_id').addEventListener('change', function() {
            var facSelect = document.getElementById('fac_id');
            if (facSelect) facSelect.value = '';
            var subSelect = document.getElementById('sub_id');
            if (subSelect) subSelect.value = '';
            var panelOpenInput = document.getElementById('panel_open');
            if (panelOpenInput) panelOpenInput.value = '1';
            document.getElementById('feedbackStatusForm').submit();
        });

        document.getElementById('fac_id').addEventListener('change', function() {
            var subSelect = document.getElementById('sub_id');
            if (subSelect) subSelect.value = '';
            var panelOpenInput = document.getElementById('panel_open');
            if (panelOpenInput) panelOpenInput.value = '1';
            document.getElementById('feedbackStatusForm').submit();
        });
    </script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

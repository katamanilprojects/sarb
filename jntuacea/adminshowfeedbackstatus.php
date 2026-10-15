<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_FEEDBACK');

$page_title = "Student Feedback Submission Status";
require_once("adminheader.php");
require_once("admin.class.php");
require_once("faculty.class.php");
require_once("feedbackservice.class.php");

$adminObj = new Admin();
$facultyObj = new Faculty();
$feedbackService = new FeedbackService();

$departments = $adminObj->getAllDepartments();

$selected_dept_id = null;
$selected_fac_id = null;
$selected_sub_id = null;
$faculties = ['data' => []];
$facultySubjects = ['data' => []];
$studentsFeedbackStatus = null;
$subjectDetails = null;
$cosCount = 0;
$hasCOs = false;

// Step 1: Handle Department selection
if (!empty($_POST['dept_id'])) {
    $selected_dept_id = intval($_POST['dept_id']);
    $faculties = $adminObj->getFacultyByDepartment($selected_dept_id);

    // Step 2: Handle Faculty selection
    if (!empty($_POST['fac_id'])) {
        $selected_fac_id = intval($_POST['fac_id']);
        $facultySubjects = $facultyObj->getSubjectsByFacultyId($selected_fac_id);
    }
}

// Step 3: Handle Submit button click
if (!empty($_POST['sub_id']) && !empty($_POST['btn_submit'])) {
    $selected_sub_id = intval($_POST['sub_id']);

    // Fetch database connection
    $db = DBCredentials::getInstance();
    $conn = $db->getConnection();

    // 1. Check if Course Outcomes (COs) are defined for this subject
    $stmtCO = $conn->prepare("SELECT COUNT(*) AS total_cos FROM course_outcomes WHERE sub_id = ?");
    if ($stmtCO) {
        $stmtCO->bind_param("i", $selected_sub_id);
        $stmtCO->execute();
        $coRes = $stmtCO->get_result()->fetch_assoc();
        $cosCount = intval($coRes['total_cos'] ?? 0);
        $stmtCO->close();
    }
    $hasCOs = ($cosCount > 0);

    // 2. Fetch subject details
    $subjectDetails = $facultyObj->getClassSubjectFacultyDetails($selected_sub_id, $selected_fac_id);

    // 3. If COs are added, fetch mapped students and their feedback submission status
    if ($hasCOs) {
        $mappedStudents = $facultyObj->getMappedStudents($selected_sub_id);

        if (!empty($mappedStudents)) {
            // Pre-fetch submitted student IDs for student_co_feedback
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
                    // Check if all COs or at least 1 have been rated
                    if (intval($row['submitted_cos']) >= $cosCount) {
                        $coSubmittedMap[intval($row['student_id'])] = true;
                    }
                }
                $stmtCOF->close();
            }

            // Pre-fetch submitted student IDs for Course End Survey (student_ces_feedback)
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

            // Pre-fetch submitted student IDs for Faculty Feedback (student_faculty_feedback)
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

            // Assemble status for each student
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

<div class="container my-4">
    <div class="row">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Student Feedback Submission Status</h5>
                </div>
                <div class="card-body">
                    <form action="adminshowfeedbackstatus.php" method="post" id="feedbackStatusForm">
                        <!-- Department Selection -->
                        <div class="form-group mb-3">
                            <label for="dept_id" class="form-label fw-bold">Department:</label>
                            <select name="dept_id" id="dept_id" class="form-select" required>
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

                        <!-- Faculty Selection -->
                        <div class="form-group mb-3">
                            <label for="fac_id" class="form-label fw-bold">Faculty:</label>
                            <select name="fac_id" id="fac_id" class="form-select" required>
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

                        <!-- Subject Selection -->
                        <div class="form-group mb-3">
                            <label for="sub_id" class="form-label fw-bold">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php if (!empty($facultySubjects['data'])): ?>
                                    <?php foreach ($facultySubjects['data'] as $subject): ?>
                                        <?php $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : ''; ?>
                                        <option value="<?= htmlspecialchars($subject['id']); ?>" <?= $selected; ?>>
                                            <?= htmlspecialchars($subject['sub_fullname']); ?> (<?= htmlspecialchars($subject['subcode']); ?>) - <?= htmlspecialchars($subject['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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

<script>
    document.getElementById('dept_id').addEventListener('change', function() {
        // Reset faculty and subject when department changes
        var facSelect = document.getElementById('fac_id');
        if (facSelect) facSelect.value = '';
        var subSelect = document.getElementById('sub_id');
        if (subSelect) subSelect.value = '';
        document.getElementById('feedbackStatusForm').submit();
    });

    document.getElementById('fac_id').addEventListener('change', function() {
        // Reset subject when faculty changes
        var subSelect = document.getElementById('sub_id');
        if (subSelect) subSelect.value = '';
        document.getElementById('feedbackStatusForm').submit();
    });
</script>

<?php
require_once("facfooter.php");
?>

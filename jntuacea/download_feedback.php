<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_FEEDBACK','export');}


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } // Ensure session is started if not already


// download_feedback.php
require_once "dbcredentials.class.php"; // Adjust if your DB connection file is different

$db = DBCredentials::getInstance();
$conn = $db->getConnection();

if (!isset($_GET['subid'])) {
    die("Subject ID missing");
}

$subid = intval($_GET['subid']);
try {
    $db->assertSubjectScope($subid);
} catch (\DomainException $e) {
    \College\RequestSecurity::deny($e->getMessage());
}

$metaRows = []; // Initialize as empty, default (if facid missing)

// Only fetch class/subject/faculty if facid is present
if (isset($_SESSION['facid']) && $_SESSION['facid'] > 0) {
    require_once "faculty.class.php"; // Adjust path if needed
    $facultyObj = new Faculty();
    $selected_fac_id = $_SESSION['facid'];
    $selected_sub_id = $subid;

    $details = $facultyObj->getClassSubjectFacultyDetails($selected_sub_id, $selected_fac_id);

    if (!empty($details['data'])) {
        // Prepare detail rows
        $metaRows = [
            ["Class", $details['data']['classname'] . " (" . $details['data']['acad_year'] . ")"],
            ["Subject", $details['data']['sub_fullname']],
            ["Faculty", $details['data']['faculty_name']],
            [], // Blank row for spacing
        ];
    }
}

// Get Course Outcomes for this subject
$cos = [];
$coQuery = $conn->query("SELECT id, co_number FROM course_outcomes WHERE sub_id = $subid ORDER BY co_number ASC");
while ($row = $coQuery->fetch_assoc()) {
    $cos[$row['id']] = "CO" . $row['co_number'];
}

// Prepare CSV Headers
$headers = ["Student Roll No"];
foreach ($cos as $coName) {
    $headers[] = $coName . " Rating";
}

// Send CSV Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=co_feedback_subject_' . $subid . '.csv');

$output = fopen('php://output', 'w');

// Output meta details first if available
foreach ($metaRows as $metaRow) {
    fputcsv($output, $metaRow);
}

// Now output main headers (Student Roll No + CO ratings)
fputcsv($output, $headers);

// Fetch students who have given feedback for this subject
$sql = "
SELECT s.id as student_id, s.username, scf.co_id, scf.rating 
FROM student_co_feedback scf 
JOIN students s ON scf.student_id = s.id 
WHERE scf.subject_id = $subid
ORDER BY s.username ASC
";

$result = $conn->query($sql);

// Organize feedback into student-wise array
$studentData = [];
while ($row = $result->fetch_assoc()) {
    $sid = $row['student_id'];
    if (!isset($studentData[$sid])) {
        $studentData[$sid] = [
            'username' => $row['username'],
            'co_ratings' => array_fill_keys(array_keys($cos), "") // Initialize blank for each CO
        ];
    }
    $studentData[$sid]['co_ratings'][$row['co_id']] = $row['rating'];
}

// Write rows
foreach ($studentData as $student) {
    $row = [
        $student['username']
    ];

    foreach (array_keys($cos) as $co_id) {
        $row[] = $student['co_ratings'][$co_id];
    }

    fputcsv($output, $row);
}



fclose($output);
exit;

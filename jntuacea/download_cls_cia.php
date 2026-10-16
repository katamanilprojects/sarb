<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAction('MOD_CIA_MARKS', 'export');
if (!in_array($_SESSION['role'] ?? '', ['hod','admin','academic_section','superadmin'], true)) {
    \College\RequestSecurity::deny('Class CIA exports require department or academic administration access.');
}
$class = filter_var($_POST['cls_id'] ?? null, FILTER_VALIDATE_INT);
if (!$class || $class < 1 || !in_array($_POST['action'] ?? '', ['hod_export_cia_marks','admin_export_cia_marks'], true)) {
    http_response_code(400); exit('Select a class and a supported CIA export action.');
}
require_once __DIR__ . '/core/CiaService.php';
$db = \DBCredentials::getInstance()->getConnection();
try {
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    $report = (new \College\CiaService($db, $_SESSION))->forClass($class);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollback(); \College\RequestSecurity::deny($e->getMessage());
}
$complete = $unavailable = 0;
foreach ($report['values'] as $subject) {
    foreach ($subject as $result) { if ($result['status'] === 'COMPLETE') { $complete++; } else { $unavailable++; } }
}
if (!$complete) {
    http_response_code(422);
    exit(htmlspecialchars($report['issues'] ? implode(' ', array_unique($report['issues'])) : 'No complete CIA totals are available. Enter all required assessments before exporting.', ENT_QUOTES, 'UTF-8'));
}
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="CIA_Marks_Class_' . $class . '_' . date('Y-m-d') . '.csv"');
header('Cache-Control: private, no-store');
header('X-CIA-Unavailable-Cells: ' . $unavailable);
header('X-CIA-Policy-Review-Subjects: ' . count($report['issues']));
$output = fopen('php://output', 'w');
fputcsv($output, ['Student Admission No.', ...array_column($report['subjects'], 'subcode')]);
foreach ($report['students'] as $student) {
    $row = [$student['roll_number']];
    foreach ($report['subjects'] as $subject) { $row[] = $report['values'][(int)$subject['id']][(int)$student['id']]['final'] ?? ''; }
    fputcsv($output, $row);
}
fclose($output);

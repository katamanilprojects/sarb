<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_ATTENDANCE_CONDONATION_GAZETTE','export');}

/**
 * Official Attendance Condonation & Detention Gazette PDF Generator
 * 
 * Generates audit-ready Gazette document conforming to autonomous regulations:
 * - Eligible: Aggregate >= 75% AND all subjects >= 40%
 * - Condonable: 65% <= Aggregate < 75% AND all subjects >= 40%
 * - Detained: Aggregate < 65% OR any subject < 40%
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$role = $_SESSION['role'] ?? '';
$isAuth = !empty($_SESSION['user']) || !empty($_SESSION['facid']) || !empty($_SESSION['role']);

if (php_sapi_name() !== 'cli') {
    if (!$isAuth) {
        http_response_code(403);
        die("Access Denied: Authentication required.");
    }
    require_once __DIR__ . '/services/FeatureManager.php';
    \FeatureManager::requireAccess('MOD_ATTENDANCE_CONDONATION_GAZETTE');
}

$classId = !empty($_GET['cls_id']) ? (int)$_GET['cls_id'] : (!empty($_POST['cls_id']) ? (int)$_POST['cls_id'] : 0);
if ($classId <= 0 && php_sapi_name() === 'cli') {
    $classId = (int)($argv[1] ?? 1);
}

if ($classId <= 0) {
    die("Invalid Request: Class ID is required.");
}

$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : (!empty($_POST['start_date']) ? $_POST['start_date'] : null);
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : (!empty($_POST['end_date']) ? $_POST['end_date'] : null);

require_once __DIR__ . '/academicsection.class.php';
require_once __DIR__ . '/vendor/autoload.php';

$acadObj = new AcademicSection();
$gazette = $acadObj->getAttendanceCondonationGazette($classId, $startDate, $endDate);

if (empty($gazette['status'])) {
    die("Error generating gazette: " . htmlspecialchars($gazette['err'] ?? 'Unknown error'));
}

$classInfo = $gazette['class'];
$summary = $gazette['summary'];
$thresholds = $gazette['thresholds'];
$eligible = $gazette['eligible'];
$condonable = $gazette['condonable'];
$detained = $gazette['detained'];

// A provisional classification is never an official detention or permission decision.
$reviewRequired = $gazette['review_required'] ?? [];
// Initialize mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L', // Landscape for wide tables
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 12,
    'margin_bottom' => 12,
    'default_font_size' => 8,
    'default_font' => 'helvetica',
    'tempDir' => __DIR__ . '/uploads/tmp'
]);
$mpdf->aliasNbPages();

$logoPath = realpath(__DIR__ . '/images/jntuacea.png');
$logoImg = ($logoPath && file_exists($logoPath)) ? '<img src="' . $logoPath . '" style="height: 55px; margin-bottom: 4px;">' : '';

$dateRangeText = ($startDate && $endDate) 
    ? 'For Period: ' . date('d-m-Y', strtotime($startDate)) . ' to ' . date('d-m-Y', strtotime($endDate)) 
    : 'Cumulative Semester Attendance as on ' . date('d-m-Y');

$reviewHtml = '<div style="border:1px solid #777;padding:8px"><strong>Provisional report; authorised academic review is required before an official decision.</strong><br>Policy or data review required: ' . count($reviewRequired) . '</div>';
if ($reviewRequired) {
    $reviewHtml .= '<h4>Candidates requiring policy or data review</h4><table border="1" cellpadding="4" style="width:100%;border-collapse:collapse"><tr><th>Hall ticket</th><th>Name</th><th>Reason</th></tr>';
    foreach ($reviewRequired as $candidate) {
        $reviewHtml .= '<tr><td>' . htmlspecialchars($candidate['htno']) . '</td><td>' . htmlspecialchars($candidate['name']) . '</td><td>' . htmlspecialchars($candidate['remarks']) . '</td></tr>';
    }
    $reviewHtml .= '</table>';
}
$html = $reviewHtml . '
<div style="font-family: Arial, sans-serif; color: #111;">
    <!-- Institution Banner -->
    <table cellpadding="2" cellspacing="0" style="width:100%; border-collapse:collapse; text-align:center; margin-bottom:6px;">
        <tr>
            <td>
                ' . $logoImg . '
                <div style="font-size:14px; font-weight:bold; color:#1a365d; text-transform:uppercase;">
                    Jawaharlal Nehru Technological University Anantapur
                </div>
                <div style="font-size:12px; font-weight:bold; color:#2c5282;">
                    College of Engineering (Autonomous), Ananthapuramu – 515002, A.P.
                </div>
                <div style="font-size:10px; font-weight:bold; color:#0d6efd; padding-top:2px;">
                    ACADEMIC SECTION :: PROVISIONAL ATTENDANCE REVIEW REPORT
                </div>
                <div style="font-size:8.5px; color:#495057; font-style:italic;">
                    Autonomous Academic Regulations (' . htmlspecialchars($classInfo['regulation'] ?? 'Not recorded') . ') — ' . $dateRangeText . '
                </div>
            </td>
        </tr>
    </table>
    <hr style="border:0; border-top:1.5px solid #1a365d; margin:2px 0 6px 0;">

    <!-- Class Details & Executive KPI Summary -->
    <table cellpadding="4" cellspacing="0" border="1" style="width:100%; border-collapse:collapse; font-size:8px; margin-bottom:8px; background-color:#f8f9fa;">
        <tr>
            <td style="width:16%; font-weight:bold; background-color:#e9ecef;">Class / Specialization:</td>
            <td style="width:34%; font-weight:bold; color:#1a365d;">' . htmlspecialchars($classInfo['classname']) . ' (' . htmlspecialchars($classInfo['spec_fullname'] ?? '') . ')</td>
            <td style="width:16%; font-weight:bold; background-color:#e9ecef;">Academic Year & Sem:</td>
            <td style="width:34%;">' . htmlspecialchars($classInfo['acad_year']) . ' / ' . htmlspecialchars($classInfo['yearsem']) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#e9ecef;">Department:</td>
            <td>' . htmlspecialchars($classInfo['dept_name'] ?? 'Engineering') . '</td>
            <td style="font-weight:bold; background-color:#e9ecef;">Statutory Rules:</td>
            <td>Eligible: &ge; ' . $thresholds['min_aggregate'] . '% | Condonable: ' . $thresholds['condone_floor'] . '–' . ($thresholds['min_aggregate'] - 0.1) . '% | Detained: &lt; ' . $thresholds['condone_floor'] . '% / Sub &lt; ' . $thresholds['min_subject'] . '%</td>
        </tr>
    </table>

    <table cellpadding="4" cellspacing="0" border="1" style="width:100%; border-collapse:collapse; font-size:8.5px; text-align:center; margin-bottom:10px;">
        <tr style="background-color:#e2e3e5; font-weight:bold;">
            <td style="width:25%;">Total Enrolled Candidates</td>
            <td style="width:25%; background-color:#d1e7dd; color:#0f5132;">Eligible Candidates (&ge; ' . $thresholds['min_aggregate'] . '%)</td>
            <td style="width:25%; background-color:#fff3cd; color:#664d03;">Condonable Candidates (' . $thresholds['condone_floor'] . '–' . ($thresholds['min_aggregate'] - 0.1) . '%)</td>
            <td style="width:25%; background-color:#f8d7da; color:#842029;">Detained Candidates (&lt; ' . $thresholds['condone_floor'] . '% / Sub &lt; ' . $thresholds['min_subject'] . '%)</td>
        </tr>
        <tr style="font-size:11px; font-weight:bold;">
            <td>' . $summary['total_students'] . '</td>
            <td style="background-color:#d1e7dd; color:#0f5132;">' . $summary['eligible_count'] . ' (' . $summary['eligible_pct'] . '%)</td>
            <td style="background-color:#fff3cd; color:#664d03;">' . $summary['condonable_count'] . ' (' . $summary['condonable_pct'] . '%)</td>
            <td style="background-color:#f8d7da; color:#842029;">' . $summary['detained_count'] . ' (' . $summary['detained_pct'] . '%)</td>
        </tr>
    </table>

    <!-- SECTION I: ELIGIBLE CANDIDATES -->
    <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#0f5132;">SECTION I: ELIGIBLE CANDIDATES (Eligible for Semester End Examinations) — Count: ' . count($eligible) . '</h4>
    <table border="1" cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:7.5px; margin-bottom:10px;">
        <thead>
            <tr style="background-color:#d1e7dd; font-weight:bold; text-align:center;">
                <th style="width:5%;">S#</th>
                <th style="width:14%;">Hall Ticket No</th>
                <th style="width:35%; text-align:left;">Candidate Full Name</th>
                <th style="width:12%;">Conducted</th>
                <th style="width:12%;">Attended</th>
                <th style="width:12%;">Aggregate %</th>
                <th style="width:10%;">Status</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($eligible)) {
    $sno = 1;
    foreach ($eligible as $st) {
        $html .= '
        <tr>
            <td style="text-align:center;">' . $sno++ . '</td>
            <td style="text-align:center; font-weight:bold;">' . htmlspecialchars($st['htno']) . '</td>
            <td>' . htmlspecialchars($st['name']) . '</td>
            <td style="text-align:center;">' . $st['total_conducted'] . '</td>
            <td style="text-align:center;">' . $st['total_attended'] . '</td>
            <td style="text-align:center; font-weight:bold; color:#0f5132;">' . number_format($st['aggregate_pct'], 2) . '%</td>
            <td style="text-align:center; font-weight:bold; color:#0f5132;">ELIGIBLE</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align:center;">No candidates in this category.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- SECTION II: CONDONABLE CANDIDATES -->
    <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#664d03;">SECTION II: CONDONABLE CANDIDATES (Condonation Recommended with Statutory Fee) — Count: ' . count($condonable) . '</h4>
    <table border="1" cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:7.5px; margin-bottom:10px;">
        <thead>
            <tr style="background-color:#fff3cd; font-weight:bold; text-align:center;">
                <th style="width:5%;">S#</th>
                <th style="width:14%;">Hall Ticket No</th>
                <th style="width:30%; text-align:left;">Candidate Full Name</th>
                <th style="width:10%;">Conducted</th>
                <th style="width:10%;">Attended</th>
                <th style="width:11%;">Aggregate %</th>
                <th style="width:20%;">Regulatory Recommendation & Fee Requirement</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($condonable)) {
    $sno = 1;
    foreach ($condonable as $st) {
        $html .= '
        <tr>
            <td style="text-align:center;">' . $sno++ . '</td>
            <td style="text-align:center; font-weight:bold;">' . htmlspecialchars($st['htno']) . '</td>
            <td>' . htmlspecialchars($st['name']) . '</td>
            <td style="text-align:center;">' . $st['total_conducted'] . '</td>
            <td style="text-align:center;">' . $st['total_attended'] . '</td>
            <td style="text-align:center; font-weight:bold; color:#b02a37;">' . number_format($st['aggregate_pct'], 2) . '%</td>
            <td>' . htmlspecialchars($st['remarks']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align:center;">No candidates in this category.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- SECTION III: DETAINED CANDIDATES -->
    <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#842029;">SECTION III: DETAINED CANDIDATES (Barred from Semester End Examinations) — Count: ' . count($detained) . '</h4>
    <table border="1" cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:7.5px; margin-bottom:12px;">
        <thead>
            <tr style="background-color:#f8d7da; font-weight:bold; text-align:center;">
                <th style="width:5%;">S#</th>
                <th style="width:14%;">Hall Ticket No</th>
                <th style="width:30%; text-align:left;">Candidate Full Name</th>
                <th style="width:10%;">Conducted</th>
                <th style="width:10%;">Attended</th>
                <th style="width:11%;">Aggregate %</th>
                <th style="width:20%;">Detention Cause / Disqualification Reason</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($detained)) {
    $sno = 1;
    foreach ($detained as $st) {
        $html .= '
        <tr>
            <td style="text-align:center;">' . $sno++ . '</td>
            <td style="text-align:center; font-weight:bold; color:#842029;">' . htmlspecialchars($st['htno']) . '</td>
            <td>' . htmlspecialchars($st['name']) . '</td>
            <td style="text-align:center;">' . $st['total_conducted'] . '</td>
            <td style="text-align:center;">' . $st['total_attended'] . '</td>
            <td style="text-align:center; font-weight:bold; color:#842029;">' . number_format($st['aggregate_pct'], 2) . '%</td>
            <td style="color:#842029; font-weight:bold;">' . htmlspecialchars($st['remarks']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align:center;">No candidates detained.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- Institutional Sign-Off Block -->
    <div style="margin-top:16px;">
        <table cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:8.5px; text-align:center;">
            <tr>
                <td style="width:25%;">
                    <br><br>___________________________<br>
                    <strong>Head of Department</strong><br>
                    Dept. of ' . htmlspecialchars($classInfo['dept_name'] ?? '') . '
                </td>
                <td style="width:25%;">
                    <br><br>___________________________<br>
                    <strong>Superintendent</strong><br>
                    Academic Section
                </td>
                <td style="width:25%;">
                    <br><br>___________________________<br>
                    <strong>Controller of Examinations</strong><br>
                    JNTUACEA Exam Cell
                </td>
                <td style="width:25%;">
                    <br><br>___________________________<br>
                    <strong>PRINCIPAL</strong><br>
                    JNTUA College of Engineering
                </td>
            </tr>
        </table>
    </div>

    <!-- Security Hash -->
    <div style="margin-top:14px; text-align:center; font-size:7px; color:#6c757d; border-top:1px dashed #ced4da; padding-top:4px;">
        Generated automatically by SARB CEA Academic Engine on ' . date('d-m-Y H:i:s') . ' • Verification Token: ' . hash('sha256', $classId . date('Y-m-d') . $summary['total_students']) . '
    </div>
</div>';

$mpdf->WriteHTML($html);

$pdfFilename = 'attendance_gazette_cls_' . $classId . '_' . date("Ymd_His") . '.pdf';
$outDir = __DIR__ . '/uploads';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$filepath = $outDir . '/' . $pdfFilename;
$mpdf->Output($filepath, 'F');

if (php_sapi_name() === 'cli') {
    echo "Gazette generated successfully at: " . $filepath . "\n";
    exit(0);
}

$disposition = (!empty($_GET['view']) && $_GET['view'] == '1') ? 'inline' : 'attachment';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $pdfFilename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

readfile($filepath);

if (file_exists($filepath)) {
    unlink($filepath);
}
exit(0);

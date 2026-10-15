<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_COURSE_MASTER_FILE','export');}

/**
 * Autonomous Course Master File Download Handler
 * 
 * Generates and downloads the accreditation-compliant Autonomous Course Master File PDF
 * using CourseMasterFilePDFService.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Check CLI mode or HTTP session
if (php_sapi_name() === 'cli') {
    $sub_id = intval($argv[1] ?? 656);
    $facid = null;
} else {
    require_once __DIR__ . '/services/FeatureManager.php';
    \FeatureManager::requireAccess('MOD_COURSE_MASTER_FILE');

    // Allow POST with secretcode OR active session with facid/admin/hod for GET
    $is_auth = false;
    if (!empty($_SESSION['facid']) || !empty($_SESSION['role'])) {
        $is_auth = true;
    }

    $reqMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($reqMethod === 'POST') {
        if (empty($_POST['sub_id']) || empty($_POST['secretcode']) || empty($_SESSION['secretcode']) || $_POST['secretcode'] != $_SESSION['secretcode']) {
            die("Invalid Request");
        }
        $sub_id = intval($_POST['sub_id']);
    } elseif ($reqMethod === 'GET' && !empty($_GET['sub_id']) && $is_auth) {
        $sub_id = intval($_GET['sub_id']);
    } else {
        die("Invalid Request");
    }

    $facid = !empty($_SESSION['facid']) ? $_SESSION['facid'] : (!empty($_POST['facid']) ? $_POST['facid'] : (!empty($_GET['facid']) ? $_GET['facid'] : null));
}

require_once __DIR__ . '/services/CourseMasterFilePDFService.php';

try {
    $service = new \Services\CourseMasterFilePDFService();
    $filepath = $service->generateCourseMasterFile($sub_id, [
        'facid' => $facid
    ]);

    $pdfFilename = basename($filepath);

    if (php_sapi_name() === 'cli') {
        echo "Course Master File PDF generated successfully at: " . $filepath . "\n";
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

} catch (Exception $e) {
    http_response_code(500);
    die("Error generating Course Master File: " . htmlspecialchars($e->getMessage()));
}

<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_OBE_ANALYSIS','export');}

/**
 * Official e-Bluebook Course File Download Handler
 * 
 * Generates and downloads the audit-compliant Operational Course File PDF
 * using EBluebookPDFService.
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

// Check CLI mode or HTTP session
if (php_sapi_name() === 'cli') {
    $sub_id = intval($argv[1] ?? 656);
    $facid = null;
    $start_date = null;
    $end_date = null;
} else {
    require_once __DIR__ . '/services/FeatureManager.php';
    \FeatureManager::requireAccess('MOD_OBE_ANALYSIS');

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

    $facid = !empty($_POST['facid']) ? $_POST['facid'] : (!empty($_GET['facid']) ? $_GET['facid'] : (!empty($_SESSION['facid']) ? $_SESSION['facid'] : null));
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : (!empty($_GET['start_date']) ? $_GET['start_date'] : null);
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : (!empty($_GET['end_date']) ? $_GET['end_date'] : null);
}

require_once __DIR__ . '/services/EBluebookPDFService.php';

try {
    $service = new EBluebookPDFService();
    $filepath = $service->generateEBluebook($sub_id, [
        'facid' => $facid,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);

    $pdfFilename = basename($filepath);

    if (php_sapi_name() === 'cli') {
        echo "e-Bluebook PDF generated successfully at: " . $filepath . "\n";
        exit(0);
    }

    if (!file_exists($filepath) || filesize($filepath) === 0) {
        throw new Exception("Generated e-Bluebook file is missing or empty.");
    }

    // Clean any prior output buffers (removes any stray warnings/spaces that would corrupt binary PDF stream)
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    readfile($filepath);
    exit();

} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "Error generating e-Bluebook: " . $e->getMessage() . "\n";
        exit(1);
    }
    http_response_code(500);
    die("Error generating e-Bluebook PDF: " . htmlspecialchars($e->getMessage()));
}

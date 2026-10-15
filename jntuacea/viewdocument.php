<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';


if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

$user = $_SESSION['user'] ?? '';
$role = $_SESSION['role'] ?? '';

if (empty($user)) {
    header("Location: ./index.php");
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_STUDENT_PROFILE');

require_once __DIR__ . '/dbcredentials.class.php';

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($docId <= 0) {
    http_response_code(400);
    die("Invalid document identifier.");
}

$db = new \DBCredentials();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT id, roll_no, doc_type, doc_title, file_name, file_path, file_size, mime_type FROM student_documents WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $docId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    http_response_code(404);
    die("Requested document does not exist.");
}

require_once __DIR__ . '/core/RecordScope.php';
require_once __DIR__ . '/core/DocumentStorage.php';
try {
    (new \College\RecordScope($conn, $_SESSION))->documentOwner($doc['roll_no']);
    $filePath = \College\DocumentStorage::resolve($doc['file_path'], dirname(__DIR__));
} catch (\DomainException $e) { \College\RequestSecurity::deny($e->getMessage()); }
catch (\Throwable $e) { http_response_code(503); exit('Document storage is unavailable.'); }

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    die("Document file was not found on the server repository.");
}

$mime = !empty($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
$filename = !empty($doc['file_name']) ? basename($doc['file_name']) : basename($filePath);

// Clean output buffer before streaming binary
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: private, max-age=3600, must-revalidate');
header('Pragma: private');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox");

readfile($filePath);
exit();

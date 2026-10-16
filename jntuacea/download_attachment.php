<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAction('MOD_CIA_METADATA', 'export');
require_once __DIR__ . '/dbcredentials.class.php';
$db = \DBCredentials::getInstance();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Select a valid attachment.'); }
$stmt = $db->getConnection()->prepare('SELECT id,subject_id,file_path FROM cia_attachments WHERE id=?');
$stmt->bind_param('i', $id); $stmt->execute(); $attachment = $stmt->get_result()->fetch_assoc();
if (!$attachment) { http_response_code(404); exit('Attachment not found.'); }
try { $db->assertSubjectScope((int)$attachment['subject_id']); }
catch (\DomainException $e) { \College\RequestSecurity::deny($e->getMessage()); }
// Legacy and new uploads stay behind the same row-level access check.
$stored = $attachment['file_path'];
if (!preg_match('#^uploads/cia_[A-Za-z0-9_-]+\.(xlsx|xls|pdf|docx|doc|jpg|png|csv)$#iD', $stored)) {
    http_response_code(404); exit('Attachment path is invalid.');
}
$base = realpath(__DIR__ . '/uploads'); $path = realpath(__DIR__ . '/' . $stored);
if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404); exit('Attachment file is unavailable.');
}
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
readfile($path);

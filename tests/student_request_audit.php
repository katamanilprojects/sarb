<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__) . '/jntuaceastudents/logger.php';
require_once dirname(__DIR__) . '/jntuaceastudents/core/RequestSecurity.php';
\College\Clock::configure();
$checks = 0;
function verifyAudit(bool $value, string $message): void {
    global $checks;
    if (!$value) { throw new RuntimeException($message); }
    $checks++;
}

foreach ([1, '1', '987654'] as $id) {
    verifyAudit(\College\StudentSubjectRequest::positiveId($id) === (int)$id, 'valid positive ID');
}
foreach ([null, [], new stdClass(), true, 1.0, '', '0', '-1', '01', ' 1', '1 ', '1e3', '1.2', '1x', str_repeat('9', 30)] as $id) {
    verifyAudit(\College\StudentSubjectRequest::positiveId($id) === null, 'invalid IDs fail closed');
}
foreach (['studentsubatt.php', 'studentsubatt1.php'] as $page) {
    $post = ['course_ref' => '42', 'student_id' => '8', 'class_id' => '9', '_csrf' => 'TOKEN'];
    verifyAudit(\College\StudentSubjectRequest::normalize($page, 'POST', $post) === null, 'new field accepted');
    verifyAudit($post['subject_id'] === 42 && !isset($post['course_ref']), 'canonical field reaches existing guards');
    verifyAudit($post['_csrf'] === 'TOKEN' && $post['student_id'] === '8', 'other fields preserved');
    foreach ([
        [['subject_id' => '42'], 'legacy_subject_field'],
        [['subject_id' => '42', 'course_ref' => '43'], 'ambiguous_subject_field'],
        [['subject_id' => '42', 'course_ref' => '42'], 'ambiguous_subject_field'],
        [[], 'missing_subject_field'],
        [['course_ref' => ['42']], 'invalid_subject_field'],
        [['course_ref' => '42x'], 'invalid_subject_field'],
    ] as [$post, $reason]) {
        $original = $post;
        verifyAudit(\College\StudentSubjectRequest::normalize($page, 'POST', $post) === $reason, 'bad subject field classified');
        verifyAudit($post === $original, 'rejected request never injects canonical ID');
    }
}
$post = ['subject_id' => '42'];
verifyAudit(\College\StudentSubjectRequest::normalize('studentfeedback.php', 'POST', $post) === null && $post === ['subject_id' => '42'], 'other pages unchanged');
verifyAudit(\College\StudentSubjectRequest::normalize('studentsubatt.php', 'GET', $post) === null, 'GET handling unchanged');

$secret = 'DO-NOT-LOG-THIS-SECRET';
$server = ['REQUEST_URI' => '/studentsubatt.php?token=' . $secret, 'REQUEST_METHOD' => 'POST',
    'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_COOKIE' => $secret,
    'HTTP_ORIGIN' => 'https://user:' . $secret . '@portal.example/path?token=' . $secret];
$post = ['course_ref' => '42', 'student_id' => '8', 'class_id' => '9', 'username' => 'Student42',
    'password' => $secret, '_csrf' => $secret, 'secretcode' => $secret, 'cf-turnstile-response' => $secret, $secret => $secret];
$row = StudentRequestAudit::snapshot($server, $post);
verifyAudit(!str_contains(json_encode($row), $secret), 'query, credentials, cookies, tokens and unknown POST fields excluded');
verifyAudit($row['peer_ip'] === '127.0.0.1' && $row['forwarded_ip_present'], 'forwarded IP cannot spoof peer');
verifyAudit($row['requested_subject_id'] === 42 && $row['requested_student_id'] === 8 && $row['requested_class_id'] === 9, 'numeric resource IDs captured');
verifyAudit($row['subject_field'] === 'course_ref' && $row['origin_host'] === 'portal.example', 'field provenance and origin captured');
verifyAudit($row['attempted_username'] === 'student42' && $row['unknown_post_field_count'] === 1, 'bounded attempted login identity');
$session = ['userid' => 7, 'user' => 'STUDENT42', 'role' => 'student', 'csrf_token' => $secret];
$context = StudentRequestAudit::sessionContext($session, $secret . '-session');
verifyAudit(!str_contains(json_encode($context), $secret), 'session and HMAC key never logged');
verifyAudit(strlen($context['session_ref']) === 24, 'opaque session reference');
verifyAudit($context === StudentRequestAudit::sessionContext($session, $secret . '-session'), 'stable reference for one session');
verifyAudit($context['session_ref'] !== StudentRequestAudit::sessionContext($session, 'other')['session_ref'], 'different sessions distinguished');
verifyAudit(StudentRequestAudit::sessionContext([], '')['session_ref'] === null, 'anonymous request safe');
$directory = sys_get_temp_dir() . '/student-audit-unit-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
    verifyAudit(StudentRequestAudit::append($directory, $row), 'write structured log');
    $row['user_agent'] = "bad\xffutf8";
    verifyAudit(StudentRequestAudit::append($directory, $row), 'invalid UTF-8 handled');
    $file = $directory . '/request_audit-' . date('Y-m-d') . '.log';
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    verifyAudit(count($lines) === 2 && is_array(json_decode($lines[1], true)), 'one complete JSON record per append');
    verifyAudit(!StudentRequestAudit::append($directory . '/missing', $row), 'unwritable log does not throw');
} finally {
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
}
echo "Passed $checks student request audit checks.\n";

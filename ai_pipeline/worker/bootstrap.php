<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

function pipelineDatabase(?string $testDatabase = null): mysqli
{
    $root = dirname(__DIR__, 2);
    if (!is_file($root . '/.env.php')) { throw new RuntimeException('Application .env.php is missing.'); }
    require_once $root . '/.env.php';
    require_once $root . '/jntuacea/core/Connection.php';
    \College\Clock::configure();
    foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME'] as $key) {
        if (!defined($key)) { throw new RuntimeException('Application database configuration is incomplete.'); }
    }
    if ($testDatabase === null) { return \College\Connection::get(); }
    if (!preg_match('/^cea_standardisation_test_[A-Za-z0-9_]+$/D', $testDatabase) || $testDatabase === DB_NAME) {
        throw new DomainException('Database override requires a separate cea_standardisation_test_ database.');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, $testDatabase);
    $db->set_charset('utf8mb4');
    return $db;
}

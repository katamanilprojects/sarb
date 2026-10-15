<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/.env.php';
$options = getopt('', ['apply', 'database:', 'backup:']);
$database = $options['database'] ?? DB_NAME;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, $database);
$db->set_charset('utf8mb4');
$apply = array_key_exists('apply', $options);
if ($apply && $database === DB_NAME && (empty($options['backup']) || !is_file($options['backup']) || filesize($options['backup']) < 100)) {
    throw new RuntimeException('A verified pre-migration database backup path is required with --backup.');
}
if($apply && $database===DB_NAME){
    $manifestPath=dirname($options['backup']).'/database-test.json';
    $verified=is_file($manifestPath)?json_decode(file_get_contents($manifestPath),true):null;
    if(!$verified || realpath($verified['backup']??'')!==realpath($options['backup']) || !hash_equals($verified['backup_sha256']??'',hash_file('sha256',$options['backup']))){throw new RuntimeException('The backup checksum does not match its successful isolated-restore manifest.');}
}
$lock = 'cea-migrations:' . $database;
$stmt = $db->prepare('SELECT GET_LOCK(?,10)'); $stmt->bind_param('s', $lock); $stmt->execute();
if ((int)$stmt->get_result()->fetch_row()[0] !== 1) { throw new RuntimeException('Another migration is running.'); }
try {
    if ($apply) {
        $db->query('CREATE TABLE IF NOT EXISTS schema_migrations (version varchar(100) PRIMARY KEY, checksum char(64) NOT NULL, applied_at timestamp DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    }
    $hasLedger = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations'")->fetch_row()[0];
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
        $version = basename($file); $checksum = hash_file('sha256', $file);
        $old = null;
        if ($hasLedger) {
            $stmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE version=?');
            $stmt->bind_param('s', $version); $stmt->execute(); $old = $stmt->get_result()->fetch_row();
        }
        if ($old) {
            if (!hash_equals($old[0], $checksum)) { throw new RuntimeException('Applied migration checksum changed: ' . $version); }
            echo "Already applied: $version\n"; continue;
        }
        if (!$apply) { echo "Pending: $version ($checksum)\n"; continue; }
        // DDL in MariaDB implicitly commits. Replay-safe statements allow recovery of an interrupted migration.
        $sql = preg_replace('/^--.*$/m', '', file_get_contents($file));
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            if (trim($statement) !== '') { $db->query($statement); }
        }
        $stmt = $db->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)');
        $stmt->bind_param('ss', $version, $checksum); $stmt->execute();
        echo "Applied: $version\n";
    }
} finally {
    $stmt = $db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s', $lock); $stmt->execute();
}

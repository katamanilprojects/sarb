<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/.env.php';
$options = getopt('', ['apply', 'database:', 'backup:', 'actor:', 'reason:']);
$database = $options['database'] ?? DB_NAME;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, $database);
$db->set_charset('utf8mb4');
$apply = array_key_exists('apply', $options);
if ($apply && isset($options['actor'])) {
    $actor=filter_var($options['actor'],FILTER_VALIDATE_INT);
    if(!$actor||$actor<1){throw new RuntimeException('Supply a positive administrator users.id with --actor.');}
    $reason=trim($options['reason']??'');
    $stmt=$db->prepare('SET @cea_regulation_actor_id=?, @cea_regulation_reason=?');$stmt->bind_param('is',$actor,$reason);$stmt->execute();
}

/** MySQL-client DELIMITER blocks keep routine bodies intact. */
function migrationStatements(string $sql): array
{
    $statements=[];$buffer='';$delimiter=';';
    foreach(preg_split('/\r\n|\n|\r/',$sql) as $line){
        if(preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i',$line,$match)){
            if(trim($buffer)!==''){throw new RuntimeException('Unterminated SQL before DELIMITER.');}
            $delimiter=$match[1];continue;
        }
        if(preg_match('/^\s*--/',$line)){continue;}
        $buffer.=$line."\n";$trimmed=rtrim($buffer);
        if(str_ends_with($trimmed,$delimiter)){$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!==''){$statements[]=$statement;}$buffer='';}
    }
    if(trim($buffer)!==''){throw new RuntimeException('Unterminated migration SQL.');}
    return $statements;
}
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
        // Replay-safe DDL and transaction-controlled data routines support interrupted runs.
        foreach (migrationStatements(file_get_contents($file)) as $statement) {
            $result=$db->query($statement);if($result instanceof mysqli_result){$result->free();}
            while($db->more_results()){$db->next_result();$result=$db->store_result();if($result instanceof mysqli_result){$result->free();}}
        }
        $stmt = $db->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)');
        $stmt->bind_param('ss', $version, $checksum); $stmt->execute();
        echo "Applied: $version\n";
    }
} finally {
    $stmt = $db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s', $lock); $stmt->execute();
}

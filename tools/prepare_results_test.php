<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/.env.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$source = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); $source->set_charset('utf8mb4');
$source->query('SET TRANSACTION READ ONLY'); $source->begin_transaction();
try {
    $definitions = [];
    foreach ($source->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") as $table) {
        $name = $table['TABLE_NAME'];
        $definitions[] = $source->query('SHOW CREATE TABLE `' . str_replace('`', '``', $name) . '`')->fetch_row()[1];
    }
} finally { $source->rollback(); $source->close(); }
$name = 'cea_results_test_' . bin2hex(random_bytes(6));
$target = new mysqli(DB_HOST, DB_USER, DB_PASS); $target->set_charset('utf8mb4');
$target->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$target->select_db($name); $target->query('SET FOREIGN_KEY_CHECKS=0');
try { foreach ($definitions as $sql) { $target->query($sql); } }
finally { $target->query('SET FOREIGN_KEY_CHECKS=1'); }
echo 'Created disposable schema-only database: ', $name, ' (', count($definitions), " tables, no application records copied).\n";

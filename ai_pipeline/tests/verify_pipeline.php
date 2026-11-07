<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "PHP 8.1+ is required by the application core.\n"); exit(1); }
$pipeline = dirname(__DIR__);
require_once $pipeline . '/worker/bootstrap.php';
$options = getopt('', ['check-core-schema','check-schema','check-php-lint','full-audit','database:','apply-schema','help']);
if (isset($options['help']) || !$options) {
    echo "Usage: php verify_pipeline.php --check-core-schema|--check-schema|--check-php-lint|--full-audit [--database=cea_standardisation_test_NAME] [--apply-schema]\n";
    exit(isset($options['help']) ? 0 : 2);
}
$schema = isset($options['check-schema']) || isset($options['full-audit']);
$core = $schema || isset($options['check-core-schema']);
$lint = isset($options['check-php-lint']) || isset($options['full-audit']);
if (!$core && !$lint) { fwrite(STDERR, "Select an audit mode.\n"); exit(2); }
try {
    if (isset($options['apply-schema']) && !$core) { throw new InvalidArgumentException('--apply-schema requires a schema audit mode.'); }
    if ($lint) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pipeline, FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR) !== false) { continue; }
            $output = []; $code = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
            if ($code !== 0) { throw new RuntimeException('PHP syntax check failed: ' . $file->getFilename()); }
            $count++;
        }
        if (!$count) { throw new RuntimeException('No PHP files found for syntax checks.'); }
        echo "PASS: $count pipeline PHP files parsed.\n";
    }
    if ($core) {
        $db = pipelineDatabase($options['database'] ?? null);
        if (isset($options['apply-schema'])) {
            if (!isset($options['database'])) { throw new DomainException('Automatic migration is restricted to an explicitly selected isolated test database.'); }
            $sql = file_get_contents($pipeline . '/database/migration_ai_analytics.sql');
            if ($sql === false) { throw new RuntimeException('Migration file missing.'); }
            $db->multi_query($sql);
            do { if ($result = $db->store_result()) { $result->free(); } } while ($db->more_results() && $db->next_result());
        }
        $tables = [
            'students'=>['id','username','class_id','status','date_of_joining'],
            'classes'=>['id','start_date','end_date','status','reg_id','spec_id'],
            'attendance'=>['stu_id','sub_id','date','hour','status'],
            'subjects'=>['id','class_id','subject_sno','subcode','sub_shortname','sub_fullname','sub_type'],
            'student_sub'=>['stu_id','sub_id'], 'student_permissions'=>['stu_id','class_id','date','hour'],
            'diary'=>['sub_id','date','hour'], 'temp_joiningdates'=>['htno','doj'],
            'users'=>['id','username','name'], 'regulations'=>['id','regulation'],
            'specialization'=>['id','dept_id','prog_id','spec_fullname'],
            'departments'=>['id','dept_fullname'], 'programs'=>['id','prog_fullname'],
            'academic_settings'=>['reg_id','setting_key','setting_value'],
            'ai_jobs'=>['id','snapshot_id','owner_user_id','task_key','status','attempts','claim_token','locked_at','next_attempt_at','provider_config_hash','payload_json','output_json'],
            'ai_job_consents'=>['user_id','task_key','is_enabled'],
            'ai_providers'=>['id','kind','endpoint','model','is_enabled','timeout_seconds','version']
        ];
        if ($schema) {
            $tables['student_risk_snapshots']=['id','student_id','class_id','cutoff_date','policy_hash','category','metrics_json','calculated_at'];
            $tables['ai_analysis_reports']=['id','report_type','entity_type','entity_id','source_hash','analysis_json','model_used','status','created_at'];
        }
        $columnQuery = $db->prepare('SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        foreach ($tables as $table => $required) {
            $columnQuery->bind_param('s', $table); $columnQuery->execute();
            $columns = array_column($columnQuery->get_result()->fetch_all(MYSQLI_ASSOC), 'DATA_TYPE', 'COLUMN_NAME');
            if (array_diff($required, array_keys($columns))) { throw new RuntimeException('Missing/incompatible columns in ' . $table . '. Apply the correct schema; existing tables are not upgraded by CREATE IF NOT EXISTS.'); }
            if ($table === 'student_risk_snapshots' && ($columns['cutoff_date'] !== 'date' || $columns['metrics_json'] !== 'longtext' || $columns['calculated_at'] !== 'datetime')) {
                throw new RuntimeException('Incompatible risk snapshot types.');
            }
        }
        if ($schema) {
            foreach (['student_risk_snapshots'=>['student_id','class_id','cutoff_date','policy_hash'], 'ai_analysis_reports'=>['report_type','entity_type','entity_id','source_hash']] as $table => $required) {
                $indexes=[];
                foreach ($db->query('SHOW INDEX FROM `' . $table . '`') as $row) {
                    if ((int)$row['Non_unique'] === 0) { $indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name']; }
                }
                $valid=false;
                foreach ($indexes as $columns) { ksort($columns); if (array_values($columns)===$required) { $valid=true; } }
                if (!$valid) { throw new RuntimeException('Required idempotency key missing in ' . $table); }
            }
        }
        echo "PASS: required database columns and pipeline unique keys verified.\n";
    }
    echo "Selected checks passed. This does not certify deployment or end-to-end behavior.\n";
} catch (Throwable $e) {
    // Only schema messages are shown; connection errors may contain deployment details.
    $message = $e instanceof mysqli_sql_exception ? 'Database operation failed; check private configuration and server logs.' : $e->getMessage();
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

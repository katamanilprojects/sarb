<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/attendance_metrics.php';
$options = getopt('', ['database:', 'dry-run', 'write', 'class:']);
try {
    if (isset($options['write'], $options['dry-run'])) { throw new InvalidArgumentException('Choose --write or --dry-run.'); }
    $write = isset($options['write']);
    if ($write && getenv('AI_PIPELINE_ALLOW_WRITES') !== '1') { throw new DomainException('Writes require the configured batch runner.'); }
    $db = pipelineDatabase($options['database'] ?? null);
    require_once dirname(__DIR__, 2) . '/jntuacea/core/AttendanceService.php';
    $service = new \College\AttendanceService($db);
    $cutoff = date('Y-m-d');
    $recentStart = (new DateTimeImmutable($cutoff))->modify('-13 days')->format('Y-m-d');
    $db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    $sql = 'SELECT id,start_date FROM classes WHERE status=1';
    if (isset($options['class'])) {
        $id = filter_var($options['class'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) { throw new InvalidArgumentException('class must be a positive integer.'); }
        $sql .= ' AND id=' . $id;
    }
    $classes = $db->query($sql . ' ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    if (!$classes) { throw new DomainException('No active classes found; no evidence was validated.'); }
    $insert = $write ? $db->prepare('INSERT INTO student_risk_snapshots (student_id,class_id,cutoff_date,policy_hash,category,metrics_json,calculated_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE category=VALUES(category),metrics_json=VALUES(metrics_json),calculated_at=UTC_TIMESTAMP()') : null;
    $count = 0;
    foreach ($classes as $class) {
        $cid = (int)$class['id'];
        if (!$class['start_date'] || $class['start_date'] > $cutoff) { continue; }
        $report = $service->forClass($cid, null, $class['start_date'], $cutoff);
        $recentReport = $service->forClass($cid, null, max($recentStart, $class['start_date']), $cutoff);
        $recent = array_column($recentReport['students'], null, 'id');
        foreach ($report['students'] as $student) {
            $sid = (int)$student['id'];
            $metrics = pipelineAttendanceMetrics($student, $report, $recent[$sid] ?? null);
            if ($insert) {
                $json = json_encode($metrics, JSON_THROW_ON_ERROR);
                $insert->bind_param('iissss', $sid, $cid, $cutoff, $report['policy_hash'], $metrics['category'], $json);
                $insert->execute();
            }
            $count++;
        }
    }
    if (!$count) { throw new DomainException('No student attendance profiles validated.'); }
    if ($write) { $db->commit(); } else { $db->rollback(); }
    echo ($write ? 'Stored ' : 'Dry run validated ') . $count . " provisional attendance profiles.\n";
} catch (Throwable $e) {
    if (isset($db)) { $db->rollback(); }
    // Avoid printing DB credentials, identifiers or student records into scheduler logs.
    fwrite(STDERR, 'Attendance batch failed (' . get_class($e) . "). Check schema, policy, permissions and configuration.\n");
    exit(1);
}

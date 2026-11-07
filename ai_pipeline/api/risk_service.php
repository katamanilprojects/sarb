<?php
declare(strict_types=1);
namespace Services;

/** Server-side include only. Call using the authenticated application's connection. */
final class RiskService
{
    public static function getStudentSnapshot(\mysqli $db, int $studentId, int $classId, int $maxAgeHours = 36): ?array
    {
        if (empty($_SESSION['role']) || $studentId < 1 || $classId < 1 || $maxAgeHours < 1 || $maxAgeHours > 168) {
            throw new \DomainException('An authenticated, scoped attendance request is required.');
        }
        require_once dirname(__DIR__, 2) . '/jntuacea/core/RecordScope.php';
        require_once dirname(__DIR__, 2) . '/jntuacea/core/Clock.php';
        \College\Clock::configure();
        $scope = new \College\RecordScope($db, $_SESSION);
        // Authorization failures propagate; missing analytics must not bypass scope checks.
        $scope->classRecord($classId);
        $scope->student($studentId);
        try {
            $stmt = $db->prepare('SELECT a.setting_key,a.setting_value FROM academic_settings a JOIN classes c ON c.reg_id=a.reg_id WHERE c.id=? ORDER BY a.setting_key');
            $stmt->bind_param('i', $classId);
            $stmt->execute();
            $settings = [];
            foreach ($stmt->get_result() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
            $stmt->close();
            $policyHash = hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR));
            $today = date('Y-m-d');
            $earliest = gmdate('Y-m-d H:i:s', time() - $maxAgeHours * 3600);
            $stmt = $db->prepare('SELECT r.metrics_json,r.calculated_at FROM student_risk_snapshots r JOIN students s ON s.id=r.student_id AND s.class_id=r.class_id AND s.status=1 WHERE r.student_id=? AND r.class_id=? AND r.policy_hash=? AND r.calculated_at>=? AND r.cutoff_date<=? ORDER BY r.cutoff_date DESC,r.calculated_at DESC,r.id DESC LIMIT 1');
            $stmt->bind_param('iisss', $studentId, $classId, $policyHash, $earliest, $today);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) { return null; }
            $metrics = json_decode($row['metrics_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($metrics) || ($metrics['schema_version'] ?? null) !== 2 || ($metrics['policy_hash'] ?? '') !== $policyHash) { return null; }
            $metrics['calculated_at_utc'] = $row['calculated_at'];
            return $metrics;
        } catch (\Throwable $e) {
            // Optional snapshots may be unavailable; canonical attendance remains authoritative.
            return null;
        }
    }

    public static function renderRiskBadge(?string $category): string
    {
        $labels = [
            'ELIGIBLE' => ['success', 'Meets thresholds (provisional)'],
            'CONDONABLE' => ['warning', 'Condonation review required'],
            'DETAINED' => ['danger', 'Below attendance thresholds (provisional)'],
            'POLICY_REVIEW' => ['secondary', 'Policy review required'],
            'DATA_UNAVAILABLE' => ['secondary', 'Attendance data incomplete']
        ];
        if (!isset($labels[$category ?? ''])) { return ''; }
        [$style, $text] = $labels[$category];
        return '<span class="badge bg-' . $style . '">' . $text . '</span>';
    }

    public static function getRunwayMessage(?array $snapshot): string
    {
        if (!$snapshot) { return ''; }
        if (!in_array($snapshot['category'] ?? '', ['ELIGIBLE','CONDONABLE','DETAINED'], true)) {
            return 'Attendance data or policy requires review before estimating recovery.';
        }
        $needed = $snapshot['classes_needed_for_target'] ?? null;
        if ($needed === null) { return 'The aggregate target cannot currently be reached by attending future classes alone.'; }
        $target = (float)$snapshot['target_percentage'];
        if ((int)$needed > 0) {
            return 'Attend at least ' . (int)$needed . ' consecutive future periods to reach the aggregate target of ' . $target . '%. Subject requirements also apply.';
        }
        return 'The aggregate target is met. Subject requirements and official review still apply.';
    }
}

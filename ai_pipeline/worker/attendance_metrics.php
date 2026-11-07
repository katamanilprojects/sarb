<?php
declare(strict_types=1);

/** Preserve authoritative categories and configured policy; do not invent probabilities. */
function pipelineAttendanceMetrics(array $student, array $report, ?array $recent): array
{
    $known = ['ELIGIBLE','CONDONABLE','DETAINED','POLICY_REVIEW','DATA_UNAVAILABLE'];
    if (!in_array($student['category'] ?? '', $known, true)) { throw new DomainException('Unknown attendance category.'); }
    $decidable = in_array($student['category'], ['ELIGIBLE','CONDONABLE','DETAINED'], true);
    $recentDecidable = $recent !== null && in_array($recent['category'], ['ELIGIBLE','CONDONABLE','DETAINED'], true);
    $total = (int)$student['total_conducted'];
    $attended = (int)$student['total_attended'];
    if ($total < 0 || $attended < 0 || $attended > $total) { throw new DomainException('Invalid attendance counts.'); }
    $target = (float)$report['thresholds']['min_aggregate'];
    if ($target < 0 || $target > 100) { throw new DomainException('Invalid attendance target.'); }
    $needed = null;
    if ($decidable && $total > 0) {
        $ratio = $target / 100;
        $needed = $target === 100.0 ? ($attended === $total ? 0 : null)
            : max(0, (int)ceil(($ratio * $total - $attended) / (1 - $ratio) - 1e-10));
    }
    return [
        'category' => $student['category'],
        'current_attendance_pct' => $student['percentage'],
        'total_conducted' => $total, 'total_attended' => $attended,
        'rolling_14d_velocity' => $decidable && $recentDecidable && $recent['percentage'] !== null && $student['percentage'] !== null
            ? round((float)$recent['percentage'] - (float)$student['percentage'], 2) : null,
        'target_percentage' => $target, 'classes_needed_for_target' => $needed,
        'shortage_subjects' => $student['shortage_subjects'],
        'missing_subjects' => $student['missing_subjects'],
        'permission_mode' => $report['permission_mode'], 'thresholds' => $report['thresholds'],
        'policy_hash' => $report['policy_hash'], 'cutoff_date' => $report['cutoff'],
        'data_status' => 'PROVISIONAL', 'source' => 'College\\AttendanceService', 'schema_version' => 2
    ];
}

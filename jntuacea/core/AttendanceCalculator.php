<?php
declare(strict_types=1);
namespace College;

final class AttendanceCalculator
{
    /** Compare unrounded ratios; rounding is only for presentation. */
    public static function classify(array $subjects, array $rules, string $permissionMode = 'REVIEW'): array
    {
        $conducted = $attended = $permissions = 0;
        $shortages = []; $missing = [];
        foreach ($subjects as $id => $subject) {
            $total = (int)$subject['conducted']; $present = (int)$subject['present'];
            $permitted = (int)($subject['permission_absences'] ?? 0);
            if ($total < 0 || $present < 0 || $permitted < 0 || $present + $permitted > $total) {
                throw new \InvalidArgumentException('Invalid attendance counts.');
            }
            $effective = $present + ($permissionMode === 'COUNT_AS_ATTENDED' ? $permitted : 0);
            $conducted += $total; $attended += $effective; $permissions += $permitted;
            if ($total === 0 || ($subject['is_complete'] ?? true) === false) { $missing[] = $id; }
            elseif (($effective * 100.0 / $total) < $rules['min_subject']) { $shortages[] = $id; }
        }
        $ratio = $conducted > 0 ? $attended * 100.0 / $conducted : null;
        $category = 'DATA_UNAVAILABLE';
        if ($ratio !== null && !$missing) {
            $category = $shortages || $ratio < $rules['condone_floor'] ? 'DETAINED'
                : ($ratio < $rules['min_aggregate'] ? 'CONDONABLE' : 'ELIGIBLE');
        }
        if ($permissions > 0 && $permissionMode === 'REVIEW') { $category = 'POLICY_REVIEW'; }
        if (empty($rules['is_complete'])) { $category = 'POLICY_REVIEW'; }
        $target = $rules['min_aggregate'] / 100;
        return ['total_conducted' => $conducted, 'total_attended' => $attended,
            'permission_absences' => $permissions, 'percentage' => $ratio === null ? null : round($ratio, 2),
            'category' => $category, 'shortage_subjects' => $shortages, 'missing_subjects' => $missing,
            'safe_margin' => $conducted > 0 && $target > 0 ? max(0, (int)floor(($attended - $target * $conducted) / $target)) : 0,
            'recovery_needed' => $conducted > 0 && $target < 1 ? max(0, (int)ceil(($target * $conducted - $attended) / (1 - $target) - 1e-10)) : 0];
    }
}

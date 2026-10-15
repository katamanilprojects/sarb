<?php
declare(strict_types=1);
namespace College;

final class ReportValue
{
    public static function format(mixed $value, int $places = 2): string
    {
        return $value === null ? 'Not available' : number_format((float)$value, $places);
    }

    public static function weighted(array $values): ?float
    {
        $sum = $weight = 0.0;
        foreach ($values as $row) {
            if ($row['value'] !== null && $row['weight'] > 0) {
                $sum += $row['value'] * $row['weight']; $weight += $row['weight'];
            }
        }
        return $weight > 0 ? round($sum / $weight, 2) : null;
    }

    public static function certified(?array $audit): bool
    {
        return $audit !== null && in_array(($audit['faculty_signoff_status'] ?? ''), ['SUBMITTED', 'APPROVED'], true)
            && ($audit['hod_approval_status'] ?? '') === 'APPROVED'
            && !empty($audit['faculty_signoff_at']) && !empty($audit['hod_approval_at'])
            && !empty($audit['hod_reviewed_by']);
    }
}

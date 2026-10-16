<?php
declare(strict_types=1);
namespace College;

final class PublishedResults
{
    public function __construct(private \mysqli $db) {}

    public function forStudent(string $roll, ?int $notification = null, ?int $subject = null): array
    {
        $sql = "SELECT er.*,en.title notification_title,en.month_year,en.release_date,en.exam_type,
                       en.yearsem,en.academic_year,r.regulation
                FROM exam_results er JOIN exam_notifications en ON en.id=er.notification_id
                JOIN regulations r ON r.id=en.regulation_id
                WHERE er.htno=? AND en.is_published=1 AND en.workflow_status='PUBLISHED'";
        $types = 's'; $values = [strtoupper(trim($roll))];
        if ($notification !== null) { $sql .= ' AND en.id=?'; $types .= 'i'; $values[] = $notification; }
        if ($subject !== null) { $sql .= ' AND er.subject_id=?'; $types .= 'i'; $values[] = $subject; }
        // Keep attempts separate; never identify another offering by subject code alone.
        $sql .= ' ORDER BY en.release_date DESC,en.id DESC,er.subject_code ASC';
        $stmt = $this->db->prepare($sql); $stmt->bind_param($types, ...$values); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $registered = $earned = $weighted = 0.0; $attempts = []; $complete = true;
        foreach ($rows as $row) {
            $registered += (float)$row['credits_registered'];
            $earned += (float)$row['credits_earned'];
            $weighted += (float)$row['credits_registered'] * ($row['result_status'] === 'PASS' ? (float)$row['grade_points'] : 0.0);
            $attempts[(int)$row['notification_id']] = true;
            if ($row['result_status'] === 'WITHHELD' || $row['grade_points'] === null) { $complete = false; }
        }
        return ['htno'=>strtoupper(trim($roll)), 'results'=>$rows, 'total_registered'=>$registered,
            'total_earned'=>$earned, 'sgpa'=>$complete && count($attempts) === 1 && $registered > 0 ? round($weighted / $registered, 2) : null];
    }
}

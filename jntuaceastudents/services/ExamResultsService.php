<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

/**
 * Class ExamResultsService
 *
 * Dedicated standalone Exam Results Service for JNTUA CEA Student Portal.
 * Handles student results lookup, grade card generation, and SGPA calculations.
 */
class ExamResultsService extends \DBCredentials
{
    private static ?self $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all exam notifications with optional filters.
     */
    public function getNotifications(?int $programId = null, ?int $regId = null, bool $onlyPublished = true): array
    {
        $sql = "SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation,
                       (SELECT COUNT(*) FROM exam_results er WHERE er.notification_id = en.id) AS results_count,
                       (SELECT COUNT(DISTINCT er.htno) FROM exam_results er WHERE er.notification_id = en.id) AS students_count
                FROM exam_notifications en
                LEFT JOIN programs p ON en.program_id = p.id
                LEFT JOIN regulations r ON en.regulation_id = r.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($programId !== null && $programId > 0) {
            $sql .= " AND en.program_id = ?";
            $params[] = $programId;
            $types .= "i";
        }
        if ($regId !== null && $regId > 0) {
            $sql .= " AND en.regulation_id = ?";
            $params[] = $regId;
            $types .= "i";
        }
        if ($onlyPublished) {
            $sql .= " AND en.is_published = 1";
        }

        $sql .= " ORDER BY en.release_date DESC, en.id DESC";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get single notification by ID.
     */
    public function getNotificationById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation 
            FROM exam_notifications en
            LEFT JOIN programs p ON en.program_id = p.id
            LEFT JOIN regulations r ON en.regulation_id = r.id
            WHERE en.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Fetch published results for a student roll number.
     * Computes registered credits, earned credits, and semester SGPA.
     */
    public function getStudentResults(string $htno, ?int $notificationId = null): array
    {
        $htno = strtoupper(trim($htno));
        if (strcasecmp($htno,$_SESSION['user']??'')!==0) { throw new \DomainException('Only your own results may be viewed.'); }
        $sql = "
            SELECT er.*, en.title AS notification_title, en.month_year, en.release_date,
                   en.yearsem, en.academic_year, r.regulation
            FROM exam_results er
            JOIN exam_notifications en ON er.notification_id = en.id
            JOIN regulations r ON en.regulation_id = r.id
            WHERE er.htno = ? AND en.is_published = 1
        ";
        $params = [$htno];
        $types = "s";

        if ($notificationId !== null && $notificationId > 0) {
            $sql .= " AND er.notification_id = ?";
            $params[] = $notificationId;
            $types .= "i";
        }

        $sql .= " ORDER BY en.release_date DESC, er.subject_code ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $results = [];
        $totalCreditsRegistered = 0.0;
        $totalCreditsEarned = 0.0;
        $totalWeightedPoints = 0.0;

        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
            $credits = (float)$row['credits_registered'];
            $earned = (float)$row['credits_earned'];
            $points = (float)$row['grade_points'];

            $totalCreditsRegistered += $credits;
            $totalCreditsEarned += $earned;
            $totalWeightedPoints += ($credits * $points);
        }

        $sgpa = ($totalCreditsRegistered > 0) ? round($totalWeightedPoints / $totalCreditsRegistered, 2) : 0.00;

        return [
            'htno' => $htno,
            'results' => $results,
            'total_registered' => $totalCreditsRegistered,
            'total_earned' => $totalCreditsEarned,
            'sgpa' => $sgpa
        ];
    }

    /**
     * Map letter grades to standard 10-point grade scale points.
     */
    public function getGradePoints(string $grade): int
    {
        return match(strtoupper(trim($grade))) {
            'O', 'S' => 10,
            'A+', 'EX' => 9,
            'A' => 8,
            'B+' => 7,
            'B' => 6,
            'C' => 5,
            'D', 'P' => 4,
    /**
     * Fetch published result for a student in a specific subject.
     */
    public function getStudentSubjectResult(string $htno, int $subjectId, ?string $subcode = null): ?array
    {
        $htno = strtoupper(trim($htno));
        if (strcasecmp($htno, $_SESSION['user'] ?? '') !== 0) {
            throw new \DomainException('Only your own results may be viewed.');
        }
        $code = strtoupper(trim((string)$subcode));
        $sql = "
            SELECT er.*, en.title AS notification_title, en.month_year, en.release_date,
                   en.yearsem, en.academic_year, r.regulation
            FROM exam_results er
            JOIN exam_notifications en ON er.notification_id = en.id
            JOIN regulations r ON en.regulation_id = r.id
            WHERE UPPER(TRIM(er.htno)) = ?
              AND (er.subject_id = ? OR UPPER(TRIM(er.subject_code)) = ?)
              AND en.is_published = 1
            ORDER BY en.release_date DESC, er.id DESC
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sis", $htno, $subjectId, $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }
}

// Global class alias
if (!class_exists('ExamResultsService', false)) {
    class_alias(ExamResultsService::class, 'ExamResultsService');
}

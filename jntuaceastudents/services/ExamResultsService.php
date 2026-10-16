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
                WHERE en.is_published=1 AND en.workflow_status='PUBLISHED'
                  AND EXISTS(SELECT 1 FROM exam_results own WHERE own.notification_id=en.id AND own.htno=?)";

        $params = [strtoupper(trim((string)($_SESSION['user'] ?? '')))];
        $types = 's';

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
            WHERE en.id = ? AND en.is_published=1 AND en.workflow_status='PUBLISHED'
              AND EXISTS(SELECT 1 FROM exam_results own WHERE own.notification_id=en.id AND own.htno=?) LIMIT 1
        ");
        $roll = strtoupper(trim((string)($_SESSION['user'] ?? '')));
        $stmt->bind_param("is", $id, $roll);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Fetch published results for a student roll number.
     * Computes registered credits, earned credits, and semester SGPA.
     */
    public function getStudentResults(string $htno, ?int $notificationId = null): array
    {
        if (strcasecmp($htno, $_SESSION['user'] ?? '') !== 0) { throw new \DomainException('Only your own results may be viewed.'); }
        require_once __DIR__ . '/../core/PublishedResults.php';
        return (new \College\PublishedResults($this->conn))->forStudent($htno, $notificationId);
    }

    /**
     * Resolve grade points from the exact course regulation; no global grade scale.
     */
    public function getGradePoints(string $grade, ?int $subjectId = null): float
    {
        if (!$subjectId) { throw new \DomainException('Select the exact course offering to resolve its regulation grade points.'); }
        $this->assertSubjectScope($subjectId);
        require_once __DIR__ . '/../core/ResultsPolicy.php';
        $profile = (new \College\ResultsPolicy($this->conn))->forSubject($subjectId);
        foreach ($profile['config']['grade_bands'] as $band) {
            if (strcasecmp($grade, $band['grade']) === 0) { return (float)$band['points']; }
        }
        if (in_array(strtoupper($grade), ['AB','MP','WH','W'], true)) { return 0.0; }
        throw new \DomainException('Grade is not configured for this regulation.');
    }


    /**
     * Fetch published result for a student in a specific subject.
     */
    public function getStudentSubjectResult(string $htno, int $subjectId, ?string $subcode = null): ?array
    {
        if (strcasecmp($htno, $_SESSION['user'] ?? '') !== 0) { throw new \DomainException('Only your own results may be viewed.'); }
        $this->assertSubjectScope($subjectId);
        require_once __DIR__ . '/../core/PublishedResults.php';
        $rows = (new \College\PublishedResults($this->conn))->forStudent($htno, null, $subjectId)['results'];
        return $rows[0] ?? null;
    }

}

// Global class alias
if (!class_exists('ExamResultsService', false)) {
    class_alias(ExamResultsService::class, 'ExamResultsService');
}

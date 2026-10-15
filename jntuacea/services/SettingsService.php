<?php

declare(strict_types=1);

namespace Services;

use InvalidArgumentException;
use RuntimeException;
use mysqli;
use PDO;

require_once __DIR__ . '/../dbcredentials.class.php';

/**
 * Class SettingsService
 *
 * Centralized autonomous academic settings engine for JNTUA CEA SARB.
 * Provides multi-regulation scoping, request-level in-memory caching,
 * dynamic type casting, regulatory fallback hierarchy, and audit logging.
 */
class SettingsService
{
    private static ?SettingsService $instance = null;

    /**
     * Database connection handle (mysqli or PDO).
     */
    private mysqli|PDO $db;

    /**
     * Request-level in-memory cache for cast values:
     * [regulation_code => [setting_key => cast_value]]
     */
    private array $cache = [];

    /**
     * Request-level in-memory cache for raw database rows with metadata:
     * [regulation_code => [setting_key => row_array]]
     */
    private array $metadataCache = [];

    /**
     * Tracks whether full regulation records have been preloaded.
     * [regulation_code => bool]
     */
    private array $isLoaded = [];

    /**
     * Default baseline regulation used as fallback.
     */
    public const DEFAULT_REGULATION = 'R23';

    /**
     * Constructor supports Dependency Injection of mysqli or PDO connection.
     *
     * @param mysqli|PDO|null $db
     */
    public function __construct(mysqli|PDO|null $db = null)
    {
        if ($db instanceof mysqli || $db instanceof PDO) {
            $this->db = $db;
        } else {
            // Retrieve default mysqli connection from DBCredentials singleton
            $dbCreds = \DBCredentials::getInstance();
            $conn = $dbCreds->getConnection();
            if (!$conn instanceof mysqli) {
                throw new RuntimeException("Failed to acquire valid database connection from DBCredentials.");
            }
            $this->db = $conn;
        }
    }

    /**
     * Singleton instance accessor with optional connection injection.
     *
     * @param mysqli|PDO|null $db
     * @return static
     */
    public static function getInstance(mysqli|PDO|null $db = null): static
    {
        if (self::$instance === null) {
            self::$instance = new static($db);
        } elseif ($db !== null) {
            self::$instance->setConnection($db);
        }
        return self::$instance;
    }

    /**
     * Reset singleton instance (useful in CLI tasks or unit tests).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Update active database connection.
     *
     * @param mysqli|PDO $db
     */
    public function setConnection(mysqli|PDO $db): void
    {
        $this->db = $db;
        $this->clearCache();
    }

    /**
     * Clear the in-memory cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->metadataCache = [];
        $this->isLoaded = [];
    }

    /**
     * Normalize regulation code (trim, uppercase, fallback to R23 if empty).
     */
    public function normalizeRegulation(int|string|null $regulation): string
    {
        if ($regulation === null || trim((string)$regulation) === '') {
            return self::DEFAULT_REGULATION;
        }
        return strtoupper(trim((string)$regulation));
    }

    /**
     * Preloads all settings for a specific regulation into memory.
     * Guarantees zero redundant SQL queries in subsequent requests.
     * Supports both numeric reg_id (e.g. 4, 6) and string code (e.g. 'R23', 'R25').
     *
     * @param int|string $regulation
     */
    public function loadRegulation(int|string $regulation): void
    {
        $reg = $this->normalizeRegulation($regulation);
        if (!empty($this->isLoaded[$reg])) {
            return;
        }

        $this->cache[$reg] = [];
        $this->metadataCache[$reg] = [];

        $isNumeric = is_numeric($reg);
        if (!$isNumeric) {
            $sql='SELECT COUNT(DISTINCT reg_id) n FROM academic_settings WHERE regulation_code=?';
            if($this->db instanceof mysqli){$check=$this->db->prepare($sql);$check->bind_param('s',$reg);$check->execute();$count=(int)$check->get_result()->fetch_row()[0];}
            else{$check=$this->db->prepare($sql);$check->execute([$reg]);$count=(int)$check->fetchColumn();}
            if($count>1){throw new InvalidArgumentException('The regulation label is shared by multiple programs. Select the exact regulation ID.');}
        }

        if ($isNumeric) {
            $sql = "SELECT `id`, `reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, 
                           `data_type`, `description`, `is_editable`, `updated_by`, `updated_at` 
                    FROM `academic_settings` 
                    WHERE `reg_id` = ?";
            $paramType = "i";
            $paramVal = (int)$reg;
        } else {
            $sql = "SELECT `id`, `reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, 
                           `data_type`, `description`, `is_editable`, `updated_by`, `updated_at` 
                    FROM `academic_settings` 
                    WHERE `regulation_code` = ?";
            $paramType = "s";
            $paramVal = $reg;
        }

        $rows = [];
        if ($this->db instanceof mysqli) {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException("Prepare failed in loadRegulation: " . $this->db->error);
            }
            $stmt->bind_param($paramType, $paramVal);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$paramVal]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($rows as $row) {
            $key = $row['setting_key'];
            $this->metadataCache[$reg][$key] = $row;
            $this->cache[$reg][$key] = $this->castValue($row['setting_value'], $row['data_type']);
        }

        $this->isLoaded[$reg] = true;
    }

    /**
     * Retrieve a setting value with fallback hierarchy:
     * 1. Check regulation memory cache
     * 2. Return the explicitly supplied fallback; never borrow another regulation.
     *
     * @param string $key Setting key name
     * @param string|null $regulation Regulation identifier (e.g. 'R23', 'R20', 'R19')
     * @param mixed $fallback Optional fallback value if key does not exist
     * @return mixed
     */
    public function get(string $key, ?string $regulation = self::DEFAULT_REGULATION, mixed $fallback = null): mixed
    {
        $reg = $this->normalizeRegulation($regulation);

        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }

        if (array_key_exists($key, $this->cache[$reg])) {
            return $this->cache[$reg][$key];
        }

        return $fallback;
    }

    /**
     * Check whether a setting key exists for this exact regulation.
     */
    public function has(string $key, ?string $regulation = self::DEFAULT_REGULATION): bool
    {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }
        if (array_key_exists($key, $this->cache[$reg])) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve full metadata row for a setting.
     *
     * @param string $key
     * @param string|null $regulation
     * @return array|null
     */
    public function getMetadata(string $key, ?string $regulation = self::DEFAULT_REGULATION): ?array
    {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }

        if (isset($this->metadataCache[$reg][$key])) {
            return $this->metadataCache[$reg][$key];
        }

        return null;
    }

    /**
     * Get all settings as key => cast_value associative array for a regulation.
     *
     * @param string|null $regulation
     * @return array<string, mixed>
     */
    public function getAll(?string $regulation = self::DEFAULT_REGULATION): array
    {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }
        return $this->cache[$reg] ?? [];
    }

    /**
     * Get all settings grouped by category with full metadata.
     *
     * @param string|null $regulation
     * @return array<string, array<string, array>>
     */
    public function getAllGroupedByCategory(?string $regulation = self::DEFAULT_REGULATION): array
    {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }

        $grouped = [];
        foreach ($this->metadataCache[$reg] ?? [] as $key => $row) {
            $cat = $row['category'] ?? 'GENERAL';
            $grouped[$cat][$key] = $row;
            $grouped[$cat][$key]['typed_value'] = $this->cache[$reg][$key] ?? null;
        }

        return $grouped;
    }

    /**
     * Retrieve settings filtered by category.
     *
     * @param string $category ENUM: 'CIA', 'SEE', 'ATTENDANCE', 'ATTAINMENT', 'GRADING', 'GENERAL'
     * @param string|null $regulation
     * @return array<string, mixed>
     */
    public function getByCategory(string $category, ?string $regulation = self::DEFAULT_REGULATION): array
    {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }

        $result = [];
        $catUpper = strtoupper(trim($category));
        foreach ($this->metadataCache[$reg] ?? [] as $key => $row) {
            if (strtoupper($row['category'] ?? '') === $catUpper) {
                $result[$key] = $this->cache[$reg][$key] ?? null;
            }
        }
        return $result;
    }

    /**
     * Get list of all distinct regulations available in academic_settings.
     *
     * @return array<string>
     */
    public function getAvailableRegulations(): array
    {
        $sql = "SELECT DISTINCT `regulation_code` FROM `academic_settings` ORDER BY `regulation_code` DESC";
        $regs = [];
        if ($this->db instanceof mysqli) {
            $res = $this->db->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $regs[] = (string)$row['regulation_code'];
                }
            }
        } else {
            $stmt = $this->db->query($sql);
            $regs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($regs)) {
            $regs = ['R25', 'R23', 'R20', 'R19'];
        }

        return $regs;
    }

    /**
     * Get list of regulations enriched with program, degree duration, and settings counts.
     */
    public function getRegulationsWithMetadata(): array
    {
        $sql = "SELECT r.id, r.regulation, r.prog_id, p.prog_shortname, p.program_level, 
                       r.total_degree_credits, r.normal_duration_years, r.total_semesters,
                       COUNT(s.id) as settings_count 
                FROM regulations r 
                JOIN programs p ON r.prog_id = p.id 
                LEFT JOIN academic_settings s ON (s.reg_id = r.id OR (s.reg_id IS NULL AND s.regulation_code COLLATE utf8mb4_unicode_ci = r.regulation COLLATE utf8mb4_unicode_ci))
                GROUP BY r.id 
                ORDER BY p.program_level DESC, p.prog_shortname ASC, r.regulation DESC";
        $data = [];
        if ($this->db instanceof mysqli) {
            $res = $this->db->query($sql);
            if ($res) {
                $data = $res->fetch_all(MYSQLI_ASSOC);
            }
        }
        return $data;
    }

    /**
     * Get Grade Bands JSON parsed as array
     */
    public function getGradeBands(int|string|null $regulation = self::DEFAULT_REGULATION): array
    {
        $val = $this->get('grade_bands_json', (string)$regulation);
        if (is_array($val)) return $val;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    /**
     * Get Class Award JSON parsed as array
     */
    public function getClassAwards(int|string|null $regulation = self::DEFAULT_REGULATION): array
    {
        $val = $this->get('class_award_json', (string)$regulation);
        if (is_array($val)) return $val;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    /**
     * Safe Mutation API:
     * - Validates type
     * - Validates editable state
     * - Enforces atomic transaction
     * - Writes audit log
     * - Refreshes local memory cache
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $regulation
     * @param int|null $userId
     * @param string|null $ipAddress
     * @return bool
     */
    public function set(
        string $key,
        mixed $value,
        ?string $regulation = self::DEFAULT_REGULATION,
        ?int $userId = null,
        ?string $ipAddress = null
    ): bool {
        $reg = $this->normalizeRegulation($regulation);
        if (empty($this->isLoaded[$reg])) {
            $this->loadRegulation($reg);
        }

        $meta = $this->metadataCache[$reg][$key] ?? null;
        if (!$meta) {
            throw new InvalidArgumentException("Setting '$key' not found for regulation '$reg'.");
        }

        if (empty($meta['is_editable'])) {
            throw new RuntimeException("Setting '$key' is protected and cannot be modified.");
        }

        $dataType = strtoupper((string)$meta['data_type']);
        $serializedValue = $this->serializeValue($value, $dataType);
        $oldValue = (string)($meta['setting_value'] ?? '');

        // If no change, return true without redundant DB writes
        if ($serializedValue === $oldValue) {
            return true;
        }

        $userId=(int)($_SESSION['userid']??$userId??0);
        $settingId = (int)$meta['id'];
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($this->db instanceof mysqli) {
            $this->db->begin_transaction();
            try {
                // 1. Update setting
                $stmt = $this->db->prepare(
                    "UPDATE `academic_settings` SET `setting_value` = ?, `updated_by` = ?, `updated_at` = NOW() WHERE `id` = ?"
                );
                if (!$stmt) {
                    throw new RuntimeException("Prepare update failed: " . $this->db->error);
                }
                $stmt->bind_param("sii", $serializedValue, $userId, $settingId);
                if (!$stmt->execute()) {
                    throw new RuntimeException("Execute update failed: " . $stmt->error);
                }
                $stmt->close();

                // 2. Insert audit log
                $auditStmt = $this->db->prepare(
                    "INSERT INTO `academic_settings_audit` (`setting_id`, `regulation_code`, `setting_key`, `old_value`, `new_value`, `changed_by`, `ip_address`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$auditStmt) {
                    throw new RuntimeException("Prepare audit failed: " . $this->db->error);
                }
                $auditStmt->bind_param("issssis", $settingId, $reg, $key, $oldValue, $serializedValue, $userId, $ip);
                if (!$auditStmt->execute()) {
                    throw new RuntimeException("Execute audit failed: " . $auditStmt->error);
                }
                $auditStmt->close();

                $regId=(int)$meta['reg_id'];
                $snapshot=$this->db->prepare('SELECT setting_key,setting_value,data_type FROM academic_settings WHERE reg_id=? ORDER BY setting_key');$snapshot->bind_param('i',$regId);$snapshot->execute();
                $json=json_encode($snapshot->get_result()->fetch_all(MYSQLI_ASSOC),JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
                $version=$this->db->prepare('INSERT IGNORE INTO academic_policy_versions(regulation_id,policy_hash,settings_json,recorded_by) VALUES (?,?,?,?)');$version->bind_param('issi',$regId,$hash,$json,$userId);$version->execute();
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
        } else {
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare(
                    "UPDATE `academic_settings` SET `setting_value` = ?, `updated_by` = ?, `updated_at` = NOW() WHERE `id` = ?"
                );
                $stmt->execute([$serializedValue, $userId, $settingId]);

                $auditStmt = $this->db->prepare(
                    "INSERT INTO `academic_settings_audit` (`setting_id`, `regulation_code`, `setting_key`, `old_value`, `new_value`, `changed_by`, `ip_address`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $auditStmt->execute([$settingId, $reg, $key, $oldValue, $serializedValue, $userId, $ip]);

                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        // Refresh internal memory cache
        $this->metadataCache[$reg][$key]['setting_value'] = $serializedValue;
        $this->metadataCache[$reg][$key]['updated_by'] = $userId;
        $this->cache[$reg][$key] = $this->castValue($serializedValue, $dataType);

        return true;
    }

    /**
     * Batch updates multiple settings under a single atomic transaction.
     *
     * @param array<string, mixed> $settings Key => Value pairs
     * @param string|null $regulation
     * @param int|null $userId
     * @param string|null $ipAddress
     * @return bool
     */
    public function batchSet(
        array $settings,
        ?string $regulation = self::DEFAULT_REGULATION,
        ?int $userId = null,
        ?string $ipAddress = null
    ): bool {
        $reg = $this->normalizeRegulation($regulation);

        // Pre-validate all settings before mutating database
        foreach ($settings as $key => $val) {
            $meta = $this->getMetadata($key, $reg);
            if (!$meta) {
                throw new InvalidArgumentException("Unknown setting key: '$key'.");
            }
            if (empty($meta['is_editable'])) {
                throw new RuntimeException("Setting '$key' is locked from modifications.");
            }
        }

        // Perform mutations
        foreach ($settings as $key => $val) {
            $this->set($key, $val, $reg, $userId, $ipAddress);
        }

        return true;
    }

    /**
     * Retrieve audit log entries for a setting or regulation.
     *
     * @param string|null $key
     * @param string|null $regulation
     * @param int $limit
     * @return array
     */
    public function getAuditLogs(?string $key = null, ?string $regulation = null, int $limit = 50): array
    {
        $clauses = [];
        $params = [];
        $types = "";

        if ($regulation !== null) {
            $clauses[] = "a.`regulation_code` = ?";
            $params[] = $this->normalizeRegulation($regulation);
            $types .= "s";
        }
        if ($key !== null) {
            $clauses[] = "a.`setting_key` = ?";
            $params[] = $key;
            $types .= "s";
        }

        $where = !empty($clauses) ? "WHERE " . implode(" AND ", $clauses) : "";
        $sql = "SELECT a.*, u.name AS user_name, u.username 
                FROM `academic_settings_audit` a 
                LEFT JOIN `users` u ON a.changed_by = u.id 
                {$where} 
                ORDER BY a.`changed_at` DESC 
                LIMIT ?";

        $params[] = $limit;
        $types .= "i";

        if ($this->db instanceof mysqli) {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $data;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Domain Calculation & Regulatory Helper Methods
    // =========================================================================

    /**
     * Calculate continuous internal assessment (CIA) final mark from 2 mid assessments.
     * Implements Section 9(a)(v) autonomous better/lesser weighting.
     *
     * @param float $mid1
     * @param float $mid2
     * @param string|null $regulation
     * @return array [
     *   'best' => float,
     *   'lesser' => float,
     *   'better_weight' => float,
     *   'lesser_weight' => float,
     *   'better_component' => float,
     *   'lesser_component' => float,
     *   'final_cia' => float
     * ]
     */
    public function calculateCiaFinal(float $mid1, float $mid2, ?string $regulation = self::DEFAULT_REGULATION): array
    {
        $betterWeight = (float)$this->get('theory_mid_better_weight', $regulation, 0.80);
        $lesserWeight = (float)$this->get('theory_mid_lesser_weight', $regulation, 0.20);

        if ($mid1 >= $mid2) {
            $best = $mid1;
            $lesser = $mid2;
        } else {
            $best = $mid2;
            $lesser = $mid1;
        }

        $betterComp = round($betterWeight * $best, 2);
        $lesserComp = round($lesserWeight * $lesser, 2);
        $final = round($betterComp + $lesserComp, 2);

        return [
            'best' => $best,
            'lesser' => $lesser,
            'better_weight' => $betterWeight,
            'lesser_weight' => $lesserWeight,
            'better_component' => $betterComp,
            'lesser_component' => $lesserComp,
            'better_weighted' => $betterComp,
            'lesser_weighted' => $lesserComp,
            'final_cia' => $final,
            'final_rounded' => round($final),
            'final_unrounded' => round($betterComp + $lesserComp, 2)
        ];
    }

    /**
     * Condensed theory subjective marks calculation (e.g. 30M scale to 15M).
     */
    public function condenseSubjectiveMarks(float $subjectiveMarks, ?string $regulation = self::DEFAULT_REGULATION): float
    {
        $condensedTarget = (float)$this->get('theory_mid_subjective_condensed', $regulation, 15.0);
        $fullSubjective = 30.0;
        return round(min(($subjectiveMarks / $fullSubjective) * $condensedTarget, $condensedTarget), 2);
    }

    /**
     * Condensed objective marks calculation (e.g. scale to 10M).
     */
    public function condenseObjectiveMarks(float $objectiveMarks, float $fullObjective = 10.0, ?string $regulation = self::DEFAULT_REGULATION): float
    {
        $condensedTarget = (float)$this->get('theory_mid_objective_marks', $regulation, 10.0);
        $full = max(1.0, $fullObjective);
        return round(min(($objectiveMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    /**
     * Condensed assignment marks calculation (e.g. scale to 5M).
     */
    public function condenseAssignmentMarks(float $assignmentMarks, float $fullAssignment = 5.0, ?string $regulation = self::DEFAULT_REGULATION): float
    {
        $condensedTarget = (float)$this->get('theory_assignment_marks', $regulation, 5.0);
        $full = max(1.0, $fullAssignment);
        return round(min(($assignmentMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    /**
     * Evaluate student attendance compliance status.
     *
     * @param float $aggregatePct Overall attendance percentage across all subjects
     * @param float $minSubjectPct Lowest attendance percentage in any single subject
     * @param string|null $regulation
     * @return array [
     *   'status' => 'ELIGIBLE' | 'CONDONATION' | 'DETAINED',
     *   'reason' => string
     * ]
     */
    public function evaluateAttendanceEligibility(
        float $aggregatePct,
        float $minSubjectPct = 100.0,
        ?string $regulation = self::DEFAULT_REGULATION
    ): array {
        $minAgg = (float)$this->get('attendance_min_aggregate_pct', $regulation, 75.0);
        $condoneFloor = (float)$this->get('attendance_condone_floor_pct', $regulation, 65.0);
        $minSub = (float)$this->get('attendance_min_subject_pct', $regulation, 40.0);

        if ($minSubjectPct < $minSub) {
            return [
                'status' => 'DETAINED',
                'reason' => "Subject attendance ({$minSubjectPct}%) below mandatory minimum ({$minSub}%)."
            ];
        }

        if ($aggregatePct >= $minAgg) {
            return [
                'status' => 'ELIGIBLE',
                'reason' => "Aggregate attendance ({$aggregatePct}%) meets standard requirement ({$minAgg}%)."
            ];
        }

        if ($aggregatePct >= $condoneFloor) {
            return [
                'status' => 'CONDONATION',
                'reason' => "Aggregate attendance ({$aggregatePct}%) falls in condonable bracket ({$condoneFloor}% - {$minAgg}%)."
            ];
        }

        return [
            'status' => 'DETAINED',
            'reason' => "Aggregate attendance ({$aggregatePct}%) is below condonation floor ({$condoneFloor}%)."
        ];
    }

    /**
     * Calculate Outcome-Based Education (OBE) Attainment Level (0, 1, 2, 3)
     * based on the percentage of students achieving target threshold.
     */
    public function calculateAttainmentLevel(float $cohortPercentageMeetingTarget, ?string $regulation = self::DEFAULT_REGULATION): int
    {
        $level3 = (float)$this->get('attainment_level_3_threshold', $regulation, 70.0);
        $level2 = (float)$this->get('attainment_level_2_threshold', $regulation, 60.0);
        $level1 = (float)$this->get('attainment_level_1_threshold', $regulation, 50.0);

        if ($cohortPercentageMeetingTarget >= $level3) {
            return 3;
        }
        if ($cohortPercentageMeetingTarget >= $level2) {
            return 2;
        }
        if ($cohortPercentageMeetingTarget >= $level1) {
            return 1;
        }
        return 0;
    }

    /**
     * Compute Direct CO Attainment from CIA and SEE components.
     */
    public function computeDirectCoAttainment(float $ciaAttainment, float $seeAttainment, ?string $regulation = self::DEFAULT_REGULATION): float
    {
        $ciaWeight = (float)$this->get('attainment_direct_cia_weight', $regulation, 0.30);
        $seeWeight = (float)$this->get('attainment_direct_see_weight', $regulation, 0.70);
        return round(($ciaWeight * $ciaAttainment) + ($seeWeight * $seeAttainment), 2);
    }

    /**
     * Compute Overall PO/PSO Attainment from Direct and Indirect Attainments.
     */
    public function computeOverallPoAttainment(float $directAttainment, float $indirectAttainment, ?string $regulation = self::DEFAULT_REGULATION): float
    {
        $directWeight = (float)$this->get('overall_direct_weight', $regulation, 0.80);
        $indirectWeight = (float)$this->get('overall_indirect_weight', $regulation, 0.20);
        return round(($directWeight * $directAttainment) + ($indirectWeight * $indirectAttainment), 2);
    }

    /**
     * Convert marks to Letter Grade and Grade Points based on autonomous scale.
     */
    public function getGrade(float $marks, ?string $regulation = self::DEFAULT_REGULATION): array
    {
        $bands = $this->get('grade_bands_json', $regulation, []);
        if (is_array($bands)) {
            foreach ($bands as $band) {
                if ($marks >= (float)($band['min'] ?? 0) && $marks <= (float)($band['max'] ?? 100)) {
                    return [
                        'grade' => $band['grade'] ?? 'F',
                        'points' => (int)($band['points'] ?? 0),
                        'min' => (float)$band['min'],
                        'max' => (float)$band['max']
                    ];
                }
            }
        }
        return ['grade' => 'F', 'points' => 0, 'min' => 0.0, 'max' => 39.9];
    }

    /**
     * Convert CGPA to Class Award.
     */
    public function getClassAward(float $cgpa, ?string $regulation = self::DEFAULT_REGULATION): string
    {
        $awards = $this->get('class_award_json', $regulation, []);
        if (is_array($awards)) {
            foreach ($awards as $award) {
                $min = (float)($award['min_cgpa'] ?? 0.0);
                $max = isset($award['max_cgpa']) ? (float)$award['max_cgpa'] : 10.0;
                if ($cgpa >= $min && $cgpa <= $max) {
                    return (string)$award['class'];
                }
            }
        }
        return "No Class";
    }

    /**
     * Evaluate Semester End Examination (SEE) and Aggregate Passing Criteria (Section 9).
     * Minimum 35% in End Examination (e.g. 24.5 / 70) and 40% aggregate (Mid + End).
     *
     * @param float $seeMarks
     * @param float $seeMax
     * @param float $ciaMarks
     * @param float $ciaMax
     * @param string|null $regulation
     * @return array
     */
    public function evaluatePassingCriteria(
        float $seeMarks,
        float $seeMax,
        float $ciaMarks,
        float $ciaMax,
        ?string $regulation = self::DEFAULT_REGULATION
    ): array {
        $minSeePct = (float)$this->get('see_min_pass_percentage', $regulation, 35.0);
        $minAggPct = (float)$this->get('aggregate_min_pass_pct', $regulation, 40.0);

        $seePct = ($seeMax > 0) ? round(($seeMarks / $seeMax) * 100, 2) : 0.0;
        $totalMarks = $seeMarks + $ciaMarks;
        $totalMax = $seeMax + $ciaMax;
        $aggPct = ($totalMax > 0) ? round(($totalMarks / $totalMax) * 100, 2) : 0.0;

        $seePassed = ($seePct >= $minSeePct);
        $aggPassed = ($aggPct >= $minAggPct);
        $isPassed = ($seePassed && $aggPassed);

        $remarks = $isPassed ? "Passed" : (!$seePassed ? "Failed in SEE (< {$minSeePct}%)" : "Failed in Aggregate (< {$minAggPct}%)");

        return [
            'is_passed' => $isPassed,
            'see_passed' => $seePassed,
            'aggregate_passed' => $aggPassed,
            'see_marks' => $seeMarks,
            'see_max' => $seeMax,
            'see_pct' => $seePct,
            'cia_marks' => $ciaMarks,
            'cia_max' => $ciaMax,
            'total_marks' => $totalMarks,
            'total_max' => $totalMax,
            'aggregate_pct' => $aggPct,
            'min_see_pct' => $minSeePct,
            'min_aggregate_pct' => $minAggPct,
            'remarks' => $remarks
        ];
    }

    /**
     * Evaluate Zero-Credit Mandatory Course Passing Criterion (Section 9.f).
     * Requires minimum 40% (e.g. 12 / 30 marks).
     */
    public function evaluateMandatoryCoursePass(float $marks, float $maxMarks = 30.0, ?string $regulation = self::DEFAULT_REGULATION): bool
    {
        $minPct = (float)$this->get('mandatory_course_pass_pct', $regulation, 40.0);
        $pct = ($maxMarks > 0) ? ($marks / $maxMarks) * 100.0 : 0.0;
        return ($pct >= $minPct);
    }

    /**
     * Retrieve Project Work Mark Allocation Structure (Section 14).
     *
     * @param string|null $regulation
     * @return array [
     *   'total' => float,
     *   'internal' => float,
     *   'external' => float,
     *   'supervisor' => float,
     *   'prc' => float
     * ]
     */
    public function getProjectStructure(?string $regulation = self::DEFAULT_REGULATION): array
    {
        $total = (float)$this->get('project_total_marks', $regulation, 200.0);
        $internal = (float)$this->get('project_internal_marks', $regulation, 60.0);
        $external = (float)$this->get('project_external_marks', $regulation, 140.0);
        $supervisor = round($internal / 2.0, 2);
        $prc = round($internal / 2.0, 2);

        return [
            'total' => $total,
            'internal' => $internal,
            'external' => $external,
            'supervisor' => $supervisor,
            'prc' => $prc
        ];
    }

    /**
     * Retrieve Internship Marks Structure (Summer vs Full Semester).
     *
     * @param string $type 'summer' or 'full'
     * @param string|null $regulation
     * @return float
     */
    public function getInternshipMarks(string $type = 'summer', ?string $regulation = self::DEFAULT_REGULATION): float
    {
        if (strtolower($type) === 'full') {
            return (float)$this->get('full_internship_marks', $regulation, 100.0);
        }
        return (float)$this->get('summer_internship_marks', $regulation, 50.0);
    }

    /**
     * Retrieve Examination Paper Pattern Parameters (Section 9.b & 9.e).
     *
     * @param string $subjectType 'theory' | 'composite' | 'drawing'
     * @param string|null $regulation
     * @return array
     */
    public function getPaperPattern(string $subjectType = 'theory', ?string $regulation = self::DEFAULT_REGULATION): array
    {
        $type = strtolower($subjectType);
        if ($type === 'drawing') {
            return [
                'type' => 'drawing',
                'see_max_marks' => (float)$this->get('drawing_see_max_marks', $regulation, 70.0),
                'mid_subjective_marks' => (float)$this->get('drawing_mid_subjective_marks', $regulation, 15.0),
                'day_to_day_marks' => (float)$this->get('drawing_day_to_day_marks', $regulation, 15.0),
            ];
        }

        if ($type === 'composite') {
            return [
                'type' => 'composite',
                'split_max_marks' => (float)$this->get('composite_split_max_marks', $regulation, 35.0),
                'split_q1_marks' => (float)$this->get('composite_q1_marks', $regulation, 5.0),
                'total_see_marks' => (float)$this->get('theory_see_max_marks', $regulation, 70.0),
            ];
        }

        return [
            'type' => 'theory',
            'see_max_marks' => (float)$this->get('theory_see_max_marks', $regulation, 70.0),
            'compulsory_q1_marks' => (float)$this->get('theory_compulsory_q1_marks', $regulation, 20.0),
            'choice_group_marks' => (float)$this->get('theory_choice_group_marks', $regulation, 10.0),
        ];
    }

    /**
     * Check if mark entry window is still open for a class based on its end date and marks_entry_grace_days.
     *
     * @param string|\DateTimeInterface $classEndDate
     * @param string|null $regulation
     * @return bool
     */
    public function isMarksEntryWindowOpen(string|\DateTimeInterface $classEndDate, ?string $regulation = self::DEFAULT_REGULATION): bool
    {
        $graceDays = (int)$this->get('marks_entry_grace_days', $regulation, 15);
        $endDate = is_string($classEndDate) ? new \DateTime($classEndDate) : clone $classEndDate;
        $lockDate = (clone $endDate)->modify("+{$graceDays} days");
        $now = new \DateTime();
        return $now <= $lockDate;
    }

    // =========================================================================
    // Serialization & Casting Engine
    // =========================================================================

    /**
     * Cast string representation to native PHP type based on data_type.
     */
    private function castValue(string $value, string $dataType): mixed
    {
        return match (strtoupper($dataType)) {
            'FLOAT' => (float)$value,
            'INT' => (int)$value,
            'BOOL' => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true),
            'JSON' => json_decode($value, true) ?? [],
            default => (string)$value,
        };
    }

    /**
     * Serialize native value to database-compatible string based on data_type.
     */
    private function serializeValue(mixed $value, string $dataType): string
    {
        return match (strtoupper($dataType)) {
            'FLOAT' => sprintf("%.2f", (float)$value),
            'INT' => (string)(int)$value,
            'BOOL' => ($value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'true') ? '1' : '0',
            'JSON' => is_string($value) ? $this->validateAndNormalizeJson($value) : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => trim((string)$value),
        };
    }

    /**
     * Validates that a string is valid JSON and returns normalized string.
     */
    private function validateAndNormalizeJson(string $jsonString): string
    {
        $decoded = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON provided: " . json_last_error_msg());
        }
        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

// Global class alias to allow frictionless usage without requiring explicit `use Services\SettingsService;`
if (!class_exists('SettingsService', false)) {
    class_alias(SettingsService::class, 'SettingsService');
}

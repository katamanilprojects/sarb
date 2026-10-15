<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

/**
 * Class BatchOBEService
 *
 * Enterprise service for Master OBE Architecture and Cohort Inheritance:
 * - Master Vision & Mission (Institution / Department Level)
 * - Master Program Educational Objectives (PEOs)
 * - Master Program Outcomes & PSOs (po_pso table)
 * - Master PEO-to-Mission Articulation Matrix
 * - Master PO/PSO-to-PEO Articulation Matrix
 * - Automatic Cohort Inheritance: Batches inherit master standards by Department & Admission Year
 * - Cohort-Specific Target Overrides (batch_peo_targets)
 * - Multi-tier Macro Attainment Rollup & Backtracking
 */
class BatchOBEService extends \DBCredentials
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

    // ==========================================
    // 1. Batch Management
    // ==========================================

    /**
     * Get all student cohorts/batches with optional program filter.
     */
    public function getBatches(?int $programId = null, bool $activeOnly = false): array
    {
        $sql = "SELECT b.id, b.program_id, b.regulation_id, b.batch_name, 
                       b.admission_year, b.graduation_year, b.is_active, b.created_at,
                       p.program_code, p.prog_shortname, p.prog_fullname,
                       r.regulation,
                       (SELECT COUNT(*) FROM classes c WHERE c.batch_id = b.id) AS classes_count
                FROM student_batches b
                LEFT JOIN programs p ON b.program_id = p.id
                LEFT JOIN regulations r ON b.regulation_id = r.id
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if ($programId !== null && $programId > 0) {
            $sql .= " AND b.program_id = ?";
            $params[] = $programId;
            $types .= "i";
        }

        if ($activeOnly) {
            $sql .= " AND b.is_active = 1";
        }

        $sql .= " ORDER BY b.admission_year DESC, b.id DESC";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $batches = [];
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        return $batches;
    }

    /**
     * Get a single batch by ID.
     */
    public function getBatchById(int $batchId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT b.*, p.prog_shortname, p.prog_fullname, r.regulation 
            FROM student_batches b
            LEFT JOIN programs p ON b.program_id = p.id
            LEFT JOIN regulations r ON b.regulation_id = r.id
            WHERE b.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $batchId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Create a new student batch cohort.
     */
    public function createBatch(array $data, int $userId): array
    {
        $programId = (int)($data['program_id'] ?? 0);
        $regulationId = (int)($data['regulation_id'] ?? 0);
        $batchName = trim($data['batch_name'] ?? '');
        $admissionYear = (int)($data['admission_year'] ?? 0);
        $graduationYear = (int)($data['graduation_year'] ?? 0);
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($programId <= 0 || $regulationId <= 0 || empty($batchName) || $admissionYear <= 0 || $graduationYear <= 0) {
            return ['status' => 0, 'err' => 'All batch parameters (Program, Regulation, Batch Name, Admission Year, Graduation Year) are required.'];
        }

        if ($graduationYear <= $admissionYear) {
            return ['status' => 0, 'err' => 'Graduation year must be greater than admission year.'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO student_batches (program_id, regulation_id, batch_name, admission_year, graduation_year, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisiii", $programId, $regulationId, $batchName, $admissionYear, $graduationYear, $isActive);

        if ($stmt->execute()) {
            $batchId = $this->conn->insert_id;
            $this->logs->actLog($userId, "CREATE_BATCH", "Created student batch: $batchName (ID: $batchId)");
            $this->dbActivityLog($userId, "CREATE_BATCH", "Created student batch: $batchName (ID: $batchId)", "superadmin", "BATCH", (string)$batchId);
            return ['status' => 1, 'batch_id' => $batchId, 'msg' => 'Student batch created successfully.'];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to create student batch.'];
    }

    /**
     * Update an existing batch.
     */
    public function updateBatch(int $batchId, array $data, int $userId): array
    {
        $programId = (int)($data['program_id'] ?? 0);
        $regulationId = (int)($data['regulation_id'] ?? 0);
        $batchName = trim($data['batch_name'] ?? '');
        $admissionYear = (int)($data['admission_year'] ?? 0);
        $graduationYear = (int)($data['graduation_year'] ?? 0);
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if ($batchId <= 0 || $programId <= 0 || $regulationId <= 0 || empty($batchName) || $admissionYear <= 0 || $graduationYear <= 0) {
            return ['status' => 0, 'err' => 'All batch parameters are required.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE student_batches 
            SET program_id = ?, regulation_id = ?, batch_name = ?, admission_year = ?, graduation_year = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param("iisiiii", $programId, $regulationId, $batchName, $admissionYear, $graduationYear, $isActive, $batchId);

        if ($stmt->execute()) {
            $this->logs->actLog($userId, "UPDATE_BATCH", "Updated student batch ID: $batchId ($batchName)");
            $this->dbActivityLog($userId, "UPDATE_BATCH", "Updated student batch ID: $batchId ($batchName)", "superadmin", "BATCH", (string)$batchId);
            return ['status' => 1, 'msg' => 'Student batch updated successfully.'];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to update student batch.'];
    }

    // ==========================================
    // 2. Master Vision & Mission Management & Inheritance
    // ==========================================

    /**
     * Fetch master Vision & Mission for a department or institution.
     */
    public function getMasterVisionMission(?int $deptId = null, ?int $effectiveYear = null): array
    {
        $sql = "SELECT * FROM vision_mission WHERE ";
        $params = [];
        $types = "";

        if ($deptId !== null && $deptId > 0) {
            $sql .= "dept_id = ?";
            $params[] = $deptId;
            $types .= "i";
        } else {
            $sql .= "dept_id IS NULL";
        }

        if ($effectiveYear !== null && $effectiveYear > 0) {
            $sql .= " AND effective_from_year <= ?";
            $params[] = $effectiveYear;
            $types .= "i";
        }

        $sql .= " AND is_active = 1 ORDER BY effective_from_year DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $missions = json_decode($row['mission_statements'] ?? '', true);
            if (!is_array($missions)) {
                $missions = [];
            }
            $row['missions_parsed'] = $missions;
            return $row;
        }

        return [
            'id' => 0,
            'dept_id' => $deptId,
            'effective_from_year' => $effectiveYear ?: 2020,
            'vision_statement' => '',
            'mission_statements' => '[]',
            'missions_parsed' => [],
            'is_active' => 1
        ];
    }

    /**
     * Fetch inherited vision & mission for a batch and department.
     * Batches inherit department statements matching admission_year <= effective_from_year.
     */
    public function getBatchVisionMission(int $batchId, ?int $deptId = null): array
    {
        $batch = $this->getBatchById($batchId);
        $admissionYear = $batch ? (int)$batch['admission_year'] : (int)date('Y');

        // 1. Try Department level matching cohort entry year
        if ($deptId !== null && $deptId > 0) {
            $vm = $this->getMasterVisionMission($deptId, $admissionYear);
            if (!empty($vm['vision_statement'])) {
                $vm['inherited_from'] = 'department';
                $vm['batch_id'] = $batchId;
                return $vm;
            }
        }

        // 2. Fallback to Institutional level
        $vmInst = $this->getMasterVisionMission(null, $admissionYear);
        if (!empty($vmInst['vision_statement'])) {
            $vmInst['inherited_from'] = 'institution';
            $vmInst['batch_id'] = $batchId;
            return $vmInst;
        }

        return [
            'id' => 0,
            'batch_id' => $batchId,
            'dept_id' => $deptId,
            'effective_from_year' => $admissionYear,
            'vision_statement' => '',
            'mission_statements' => '[]',
            'missions_parsed' => [],
            'inherited_from' => 'none'
        ];
    }

    /**
     * Save Master Vision & Mission statements (stored once per Department / Institution).
     */
    public function saveMasterVisionMission(?int $deptId, string $vision, array $missions, int $userId, int $effectiveYear = 2020): array
    {
        $vision = trim($vision);
        if (empty($vision)) {
            return ['status' => 0, 'err' => 'Vision statement is required.'];
        }

        // Clean missions list
        $cleanMissions = [];
        $i = 1;
        foreach ($missions as $m) {
            $stmtText = is_array($m) ? ($m['statement'] ?? $m['text'] ?? '') : (string)$m;
            $stmtText = trim($stmtText);
            if (!empty($stmtText)) {
                $code = (is_array($m) && !empty($m['code'])) ? $m['code'] : "M$i";
                $cleanMissions[$code] = $stmtText;
                $i++;
            }
        }

        if (empty($cleanMissions)) {
            return ['status' => 0, 'err' => 'At least one Mission statement is required.'];
        }

        $missionsJson = json_encode($cleanMissions, JSON_UNESCAPED_UNICODE);

        if ($deptId !== null && $deptId <= 0) {
            $deptId = null;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO vision_mission (dept_id, effective_from_year, vision_statement, mission_statements, created_by, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                vision_statement = VALUES(vision_statement),
                mission_statements = VALUES(mission_statements),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("iissi", $deptId, $effectiveYear, $vision, $missionsJson, $userId);

        if ($stmt->execute()) {
            $scope = $deptId ? "Department $deptId" : "Institutional";
            $this->logs->actLog($userId, "SAVE_VISION_MISSION", "Saved Master $scope Vision & Mission (Effective: $effectiveYear)");
            $this->dbActivityLog($userId, "SAVE_VISION_MISSION", "Saved Master $scope Vision & Mission (Effective: $effectiveYear)", "superadmin", "VISION_MISSION");
            return ['status' => 1, 'msg' => "Master $scope Vision & Mission saved successfully."];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to save Vision & Mission.'];
    }

    // ==========================================
    // 3. Master PEOs & Cohort Inheritance
    // ==========================================

    /**
     * Get Master PEOs for a department / program.
     */
    public function getMasterPEOs(int $deptId, ?int $programId = null, ?int $effectiveYear = null): array
    {
        $sql = "SELECT p.*, d.dept_shortname, pr.prog_shortname 
                FROM peos p
                LEFT JOIN departments d ON p.dept_id = d.id
                LEFT JOIN programs pr ON p.program_id = pr.id
                WHERE p.dept_id = ? AND p.is_active = 1";
        
        $params = [$deptId];
        $types = "i";

        if ($programId !== null && $programId > 0) {
            $sql .= " AND (p.program_id = ? OR p.program_id IS NULL)";
            $params[] = $programId;
            $types .= "i";
        }

        if ($effectiveYear !== null && $effectiveYear > 0) {
            $sql .= " AND p.effective_from_year <= ?";
            $params[] = $effectiveYear;
            $types .= "i";
        }

        $sql .= " ORDER BY p.sort_order ASC, p.peo_code ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $peos = [];
        while ($row = $res->fetch_assoc()) {
            $peos[] = $row;
        }
        return $peos;
    }

    /**
     * Get inherited PEOs for a specific batch, overlaying any batch-specific target score overrides.
     */
    public function getBatchPEOs(int $batchId, int $deptId): array
    {
        $batch = $this->getBatchById($batchId);
        $progId = $batch ? (int)$batch['program_id'] : null;
        $admissionYear = $batch ? (int)$batch['admission_year'] : null;

        $masterPeos = $this->getMasterPEOs($deptId, $progId, $admissionYear);
        if (empty($masterPeos)) {
            return [];
        }

        // Fetch any batch-specific target overrides from batch_peo_targets
        $stmt = $this->conn->prepare("SELECT peo_id, target_score FROM batch_peo_targets WHERE batch_id = ?");
        $stmt->bind_param("i", $batchId);
        $stmt->execute();
        $res = $stmt->get_result();
        $overrides = [];
        while ($r = $res->fetch_assoc()) {
            $overrides[(int)$r['peo_id']] = (float)$r['target_score'];
        }

        foreach ($masterPeos as &$peo) {
            $peoId = (int)$peo['id'];
            if (isset($overrides[$peoId])) {
                $peo['target_score'] = $overrides[$peoId];
                $peo['is_custom_target'] = true;
            } else {
                $peo['is_custom_target'] = false;
            }
            $peo['batch_id'] = $batchId;
        }
        unset($peo);

        return $masterPeos;
    }

    /**
     * Add or update Master PEO.
     */
    public function saveMasterPEO(int $deptId, ?int $programId, string $peoCode, string $title, string $description, float $targetScore, int $sortOrder, ?int $id = null, int $effectiveYear = 2020): array
    {
        $peoCode = strtoupper(trim($peoCode));
        $title = trim($title);
        $description = trim($description);

        if (empty($peoCode) || empty($description)) {
            return ['status' => 0, 'err' => 'PEO Code and Description are required.'];
        }

        if ($programId !== null && $programId <= 0) {
            $programId = null;
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE peos 
                SET peo_code = ?, peo_title = ?, peo_description = ?, target_score = ?, sort_order = ?, program_id = ?, effective_from_year = ?
                WHERE id = ? AND dept_id = ?
            ");
            $stmt->bind_param("sssdiiiii", $peoCode, $title, $description, $targetScore, $sortOrder, $programId, $effectiveYear, $id, $deptId);
            $action = "Updated";
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO peos (dept_id, program_id, peo_code, peo_title, peo_description, target_score, sort_order, effective_from_year, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                    peo_title = VALUES(peo_title),
                    peo_description = VALUES(peo_description),
                    target_score = VALUES(target_score),
                    sort_order = VALUES(sort_order),
                    is_active = 1
            ");
            $stmt->bind_param("iisssdii", $deptId, $programId, $peoCode, $title, $description, $targetScore, $sortOrder, $effectiveYear);
            $action = "Created";
        }

        if ($stmt->execute()) {
            return ['status' => 1, 'msg' => "Master $action $peoCode successfully."];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to save Master PEO.'];
    }

    /**
     * Save custom PEO target score override for a specific batch.
     */
    public function saveBatchPEOTarget(int $batchId, int $peoId, float $targetScore, int $userId): array
    {
        $stmt = $this->conn->prepare("
            INSERT INTO batch_peo_targets (batch_id, peo_id, target_score)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE target_score = VALUES(target_score), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("iid", $batchId, $peoId, $targetScore);
        if ($stmt->execute()) {
            $this->logs->actLog($userId, "SAVE_BATCH_PEO_TARGET", "Set target score $targetScore for Batch ID $batchId, PEO ID $peoId");
            $this->dbActivityLog($userId, "SAVE_BATCH_PEO_TARGET", "Set target score $targetScore for Batch ID $batchId, PEO ID $peoId", "superadmin", "BATCH_TARGET", (string)$batchId);
            return ['status' => 1, 'msg' => 'Cohort PEO target updated successfully.'];
        }
        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to update target score.'];
    }

    /**
     * Delete a Master PEO.
     */
    public function deleteMasterPEO(int $peoId, int $deptId): array
    {
        $stmt = $this->conn->prepare("DELETE FROM peos WHERE id = ? AND dept_id = ?");
        $stmt->bind_param("ii", $peoId, $deptId);
        if ($stmt->execute()) {
            return ['status' => 1, 'msg' => 'Master PEO deleted successfully.'];
        }
        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to delete PEO.'];
    }

    // ==========================================
    // 4. Master PEO to Mission Articulation Matrix
    // ==========================================

    /**
     * Get Master PEO-to-Mission mapping matrix.
     */
    public function getPeoMissionMatrix(int $deptId, ?int $programId = null, ?int $batchId = null): array
    {
        if ($batchId !== null && $batchId > 0) {
            $peos = $this->getBatchPEOs($batchId, $deptId);
            $vm = $this->getBatchVisionMission($batchId, $deptId);
        } else {
            $peos = $this->getMasterPEOs($deptId, $programId);
            $vm = $this->getMasterVisionMission($deptId);
        }

        $missions = $vm['missions_parsed'] ?? [];

        if (empty($peos) || empty($missions)) {
            return [
                'peos' => $peos,
                'missions' => $missions,
                'matrix' => []
            ];
        }

        $peoIds = array_column($peos, 'id');
        $inClause = implode(',', array_fill(0, count($peoIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT peo_id, mission_key, correlation_level 
            FROM peo_mission_mapping 
            WHERE peo_id IN ($inClause)
        ");
        $types = str_repeat('i', count($peoIds));
        $stmt->bind_param($types, ...$peoIds);
        $stmt->execute();
        $res = $stmt->get_result();

        $matrix = [];
        while ($row = $res->fetch_assoc()) {
            $matrix[$row['peo_id']][$row['mission_key']] = (int)$row['correlation_level'];
        }

        return [
            'peos' => $peos,
            'missions' => $missions,
            'matrix' => $matrix
        ];
    }

    /**
     * Save Master PEO-to-Mission mapping matrix.
     * $matrix = [ peo_id => [ mission_key => correlation_level (0, 1, 2, 3) ] ]
     */
    public function savePeoMissionMatrix(int $deptId, array $matrix, int $userId): array
    {
        $peos = $this->getMasterPEOs($deptId);
        $validPeoIds = array_column($peos, 'id');

        $stmt = $this->conn->prepare("
            INSERT INTO peo_mission_mapping (peo_id, mission_key, correlation_level)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE correlation_level = VALUES(correlation_level)
        ");

        $count = 0;
        foreach ($matrix as $peoId => $missionsMap) {
            $peoId = (int)$peoId;
            if (!in_array($peoId, $validPeoIds, true)) {
                continue;
            }
            foreach ($missionsMap as $missionKey => $corr) {
                $missionKey = trim((string)$missionKey);
                $corr = max(0, min(3, (int)$corr));
                $stmt->bind_param("isi", $peoId, $missionKey, $corr);
                $stmt->execute();
                $count++;
            }
        }

        $this->logs->actLog($userId, "SAVE_PEO_MISSION_MATRIX", "Updated Master PEO-Mission matrix for Department $deptId ($count cells)");
        $this->dbActivityLog($userId, "SAVE_PEO_MISSION_MATRIX", "Updated Master PEO-Mission matrix for Department $deptId ($count cells)", "superadmin", "PEO_MISSION", (string)$deptId);
        return ['status' => 1, 'msg' => 'Master PEO to Mission articulation matrix saved successfully.'];
    }

    // ==========================================
    // 5. Master PO/PSO to PEO Articulation Matrix
    // ==========================================

    /**
     * Get Master PO/PSO to PEO mapping matrix.
     */
    public function getPoPeoMatrix(int $deptId, ?int $regId = null, ?int $batchId = null): array
    {
        if ($batchId !== null && $batchId > 0) {
            $batch = $this->getBatchById($batchId);
            $regId = $batch ? (int)$batch['regulation_id'] : $regId;
            $peos = $this->getBatchPEOs($batchId, $deptId);
        } else {
            $batch = null;
            $peos = $this->getMasterPEOs($deptId);
        }

        // Get PO/PSOs from master po_pso table for this department
        $sql = "
            SELECT p.id, p.code, p.po_pso, p.orderid, p.description, p.target_score
            FROM po_pso p
            JOIN specialization s ON p.specid = s.id
            WHERE s.dept_id = ?";
        
        $params = [$deptId];
        $types = "i";

        if ($regId !== null && $regId > 0) {
            $sql .= " AND p.reg_id = ?";
            $params[] = $regId;
            $types .= "i";
        }

        $sql .= " ORDER BY p.po_pso ASC, p.orderid ASC, p.code ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $pos = [];
        $poIds = [];
        while ($row = $res->fetch_assoc()) {
            $pos[] = $row;
            $poIds[] = (int)$row['id'];
        }

        $matrix = [];
        if (!empty($poIds) && !empty($peos)) {
            $inClause = implode(',', array_fill(0, count($poIds), '?'));
            $q = "SELECT po_pso_id, peo_id, correlation_level FROM po_peo_mapping WHERE po_pso_id IN ($inClause)";
            $types = str_repeat('i', count($poIds));
            $stmt2 = $this->conn->prepare($q);
            $stmt2->bind_param($types, ...$poIds);
            $stmt2->execute();
            $res2 = $stmt2->get_result();

            while ($r = $res2->fetch_assoc()) {
                $matrix[$r['po_pso_id']][$r['peo_id']] = (int)$r['correlation_level'];
            }
        }

        return [
            'batch' => $batch,
            'peos' => $peos,
            'pos' => $pos,
            'matrix' => $matrix
        ];
    }

    /**
     * Save Master PO/PSO to PEO mapping matrix.
     * $matrix = [ po_pso_id => [ peo_id => correlation_level (0, 1, 2, 3) ] ]
     */
    public function savePoPeoMatrix(int $deptId, array $matrix, int $userId, ?int $regId = null): array
    {
        $data = $this->getPoPeoMatrix($deptId, $regId);
        $validPoIds = array_column($data['pos'], 'id');
        $validPeoIds = array_column($data['peos'], 'id');

        $stmt = $this->conn->prepare("
            INSERT INTO po_peo_mapping (po_pso_id, peo_id, correlation_level)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE correlation_level = VALUES(correlation_level)
        ");

        $count = 0;
        foreach ($matrix as $poId => $peoMap) {
            $poId = (int)$poId;
            if (!in_array($poId, $validPoIds, true)) {
                continue;
            }
            foreach ($peoMap as $peoId => $corr) {
                $peoId = (int)$peoId;
                if (!in_array($peoId, $validPeoIds, true)) {
                    continue;
                }
                $corr = max(0, min(3, (int)$corr));
                $stmt->bind_param("iii", $poId, $peoId, $corr);
                $stmt->execute();
                $count++;
            }
        }

        $this->logs->actLog($userId, "SAVE_PO_PEO_MATRIX", "Updated Master PO-PEO matrix for Department $deptId ($count cells)");
        $this->dbActivityLog($userId, "SAVE_PO_PEO_MATRIX", "Updated Master PO-PEO matrix for Department $deptId ($count cells)", "superadmin", "PO_PEO", (string)$deptId);
        return ['status' => 1, 'msg' => 'Master PO/PSO to PEO articulation matrix saved successfully.'];
    }

    // ==========================================
    // 6. Macro Attainment Calculation & Backtracking
    // ==========================================

    /**
     * Calculate multi-tier batch attainment summary:
     * Mission <- PEO <- PO/PSO <- Courses (COs)
     */
    public function getBatchMacroAttainment(int $batchId, int $deptId): array
    {
        $batch = $this->getBatchById($batchId);
        if (!$batch) { throw new \InvalidArgumentException('Batch not found.'); }
        $vm = $this->getBatchVisionMission($batchId, $deptId);
        return ['batch' => $batch, 'vision' => $vm['vision_statement'] ?? '', 'missions' => [],
            'peos' => [], 'pos' => [], 'data_status' => 'DATA_UNAVAILABLE',
            'evidence_note' => 'PEO and mission achievement requires independently reviewed evidence. Articulation mappings do not establish measured achievement.'];
    }

    // ==========================================
    // 7. PO/PSO Program Articulation & Exit Surveys (NBA Table 3.2 & 3.3.2)
    // ==========================================

    /**
     * Compute Program Articulation Matrix (NBA Table 3.2) and PO Attainment Matrix (NBA Table 3.3.2)
     * Blends Direct Course Attainment (80%) and Indirect CES Exit Survey Attainment (20%).
     *
     * @param int $batchId Student Cohort/Batch ID (0 for master program scope)
     * @param int $programId Academic Program ID
     * @return array Comprehensive Articulation & Attainment payload
     */
    public function computePOPSOArticulationMatrix(int $batchId, int $programId, ?int $departmentId = null): array
    {
        // 1. Resolve Batch and Program Context
        $batch = ($batchId > 0) ? $this->getBatchById($batchId) : null;
        if($batchId>0 && !$batch){throw new \InvalidArgumentException('Batch not found.');}
        if ($batch && empty($programId)) {
            $programId = (int)$batch['program_id'];
        }

        if ($batch && (int)$batch['program_id'] !== $programId) {
            throw new \InvalidArgumentException('Batch and program do not match.');
        }
        if ($departmentId === null || $departmentId < 1) {
            throw new \InvalidArgumentException('Select a department for program attainment.');
        }
        $stmtProg = $this->conn->prepare('SELECT p.*, d.id dept_id, d.dept_shortname, d.dept_fullname FROM programs p JOIN specialization sp ON sp.prog_id=p.id JOIN departments d ON d.id=sp.dept_id WHERE p.id=? AND d.id=? LIMIT 1');
        $stmtProg->bind_param('ii', $programId, $departmentId); $stmtProg->execute();
        $program = $stmtProg->get_result()->fetch_assoc();
        if (!$program) { throw new \InvalidArgumentException('Department and program do not match.'); }
        $deptId = $departmentId;
        $regId = $batch ? (int)$batch['regulation_id'] : null;

        // 2. Load POs and PSOs (PO1..PO12, PSO1..PSO3)
        $pos = [];
        $sqlPos = "
            SELECT DISTINCT p.id, p.code, p.po_pso, p.orderid, p.description, p.target_score
            FROM po_pso p
            LEFT JOIN specialization s ON p.specid = s.id
            WHERE 1=1
        ";
        $paramsPos = [];
        $typesPos = "";

        if ($deptId > 0) {
            $sqlPos .= " AND (s.dept_id = ? OR p.specid IS NULL)";
            $paramsPos[] = $deptId;
            $typesPos .= "i";
        }
        if ($regId !== null && $regId > 0) {
            $sqlPos .= " AND (p.reg_id = ? OR p.reg_id IS NULL)";
            $paramsPos[] = $regId;
            $typesPos .= "i";
        }
        $sqlPos .= " ORDER BY p.po_pso ASC, p.orderid ASC, p.code ASC";

        $stmtPos = $this->conn->prepare($sqlPos);
        if (!empty($paramsPos)) {
            $stmtPos->bind_param($typesPos, ...$paramsPos);
        }
        $stmtPos->execute();
        $resPos = $stmtPos->get_result();
        while ($row = $resPos->fetch_assoc()) {
            $pos[$row['code']] = [
                'id' => (int)$row['id'],
                'code' => $row['code'],
                'type' => $row['po_pso'] ?? 'PO',
                'description' => $row['description'] ?? '',
                'target_score' => (float)($row['target_score'] > 0 ? $row['target_score'] : 2.00)
            ];
        }
        $stmtPos->close();

        // 3. Load Courses / Subjects for this Batch / Program
        $subjectList = [];
        $subjectIds = [];
        $currSubIds = [];

        if ($batchId > 0) {
            $sqlSub = "
                SELECT s.id as subject_id, s.subcode, s.sub_fullname, s.sub_shortname, s.sub_type,
                       s.curr_sub_id, cl.yearsem, cl.id as class_id
                FROM subjects s
                JOIN classes cl ON s.class_id = cl.id
                WHERE cl.batch_id = ? AND cl.spec_id IN (SELECT id FROM specialization WHERE dept_id = $deptId)
                ORDER BY cl.yearsem ASC, s.subcode ASC
            ";
            $stmtSub = $this->conn->prepare($sqlSub);
            $stmtSub->bind_param("i", $batchId);
            $stmtSub->execute();
            $resSub = $stmtSub->get_result();
            while ($r = $resSub->fetch_assoc()) {
                $subcode = trim($r['subcode']);
                if (!isset($subjectList[$subcode])) {
                    $subjectList[$subcode] = [
                        'subject_id' => (int)$r['subject_id'],
                        'subcode' => $subcode,
                        'sub_fullname' => $r['sub_fullname'],
                        'sub_shortname' => $r['sub_shortname'],
                        'sub_type' => $r['sub_type'],
                        'yearsem' => $r['yearsem'],
                        'curr_sub_id' => (int)($r['curr_sub_id'] ?? 0),
                        'all_subject_ids' => [(int)$r['subject_id']],
                        'all_curr_sub_ids' => $r['curr_sub_id'] ? [(int)$r['curr_sub_id']] : []
                    ];
                } else {
                    $subjectList[$subcode]['all_subject_ids'][] = (int)$r['subject_id'];
                    if ($r['curr_sub_id']) {
                        $subjectList[$subcode]['all_curr_sub_ids'][] = (int)$r['curr_sub_id'];
                    }
                }
                $subjectIds[] = (int)$r['subject_id'];
                if ($r['curr_sub_id']) {
                    $currSubIds[] = (int)$r['curr_sub_id'];
                }
            }
            $stmtSub->close();
        }

        // Fallback to curriculum subjects if no classes are mapped
        if (empty($subjectList) && $programId > 0) {
            $sqlCurr = "
                SELECT cs.id as curr_sub_id, cs.subcode, cs.sub_fullname, cs.sub_shortname,
                       cs.sub_type, cs.yearsem
                FROM curriculum_subjects cs
                WHERE cs.prog_id = ? AND cs.spec_id IN (SELECT id FROM specialization WHERE dept_id = $deptId)
            ";
            if ($regId) {
                $sqlCurr .= " AND cs.reg_id = ?";
            }
            $sqlCurr .= " ORDER BY cs.yearsem ASC, cs.subcode ASC";

            $stmtCurr = $this->conn->prepare($sqlCurr);
            if ($regId) {
                $stmtCurr->bind_param("ii", $programId, $regId);
            } else {
                $stmtCurr->bind_param("i", $programId);
            }
            $stmtCurr->execute();
            $resCurr = $stmtCurr->get_result();
            while ($r = $resCurr->fetch_assoc()) {
                $subcode = trim($r['subcode']);
                $subjectList[$subcode] = [
                    'subject_id' => 0,
                    'subcode' => $subcode,
                    'sub_fullname' => $r['sub_fullname'],
                    'sub_shortname' => $r['sub_shortname'],
                    'sub_type' => $r['sub_type'],
                    'yearsem' => $r['yearsem'],
                    'curr_sub_id' => (int)$r['curr_sub_id'],
                    'all_subject_ids' => [],
                    'all_curr_sub_ids' => [(int)$r['curr_sub_id']]
                ];
                $currSubIds[] = (int)$r['curr_sub_id'];
            }
            $stmtCurr->close();
        }

        $subjectIds = array_values(array_unique($subjectIds));
        $currSubIds = array_values(array_unique($currSubIds));

        // 4. Load CO-PO Mappings for these subjects
        $coursePoWeights = []; // [subcode => [po_code => [sum, count]]]

        if (!empty($subjectIds) || !empty($currSubIds)) {
            $clauses = [];
            $mapParams = [];
            $mapTypes = "";

            if (!empty($subjectIds)) {
                $inSub = implode(',', array_fill(0, count($subjectIds), '?'));
                $clauses[] = "co.sub_id IN ($inSub)";
                foreach ($subjectIds as $sid) { $mapParams[] = $sid; $mapTypes .= "i"; }
            }
            if (!empty($currSubIds)) {
                $inCurr = implode(',', array_fill(0, count($currSubIds), '?'));
                $clauses[] = "co.curr_sub_id IN ($inCurr)";
                foreach ($currSubIds as $cid) { $mapParams[] = $cid; $mapTypes .= "i"; }
            }

            $whereSql = implode(' OR ', $clauses);

            $sqlMap = "
                SELECT cpm.weightage, p.code as po_code, s.subcode
                FROM co_po_mapping cpm
                JOIN course_outcomes co ON cpm.co_id = co.id
                JOIN po_pso p ON cpm.po_id = p.id
                LEFT JOIN subjects s ON co.sub_id = s.id
                WHERE ($whereSql)
            ";

            $stmtMap = $this->conn->prepare($sqlMap);
            if (!empty($mapParams)) {
                $stmtMap->bind_param($mapTypes, ...$mapParams);
            }
            $stmtMap->execute();
            $resMap = $stmtMap->get_result();
            while ($r = $resMap->fetch_assoc()) {
                $subcode = trim($r['subcode'] ?? '');
                $poCode = trim($r['po_code'] ?? '');
                $w = (float)$r['weightage'];
                if ($subcode && $poCode && $w > 0) {
                    if (!isset($coursePoWeights[$subcode][$poCode])) {
                        $coursePoWeights[$subcode][$poCode] = ['sum' => 0.0, 'count' => 0];
                    }
                    $coursePoWeights[$subcode][$poCode]['sum'] += $w;
                    $coursePoWeights[$subcode][$poCode]['count']++;
                }
            }
            $stmtMap->close();

            // Also check curriculum_co_po_mapping
            if (!empty($currSubIds)) {
                $inCurr2 = implode(',', array_fill(0, count($currSubIds), '?'));
                $sqlCurrMap = "
                    SELECT ccpm.weightage, p.code as po_code, cs.subcode
                    FROM curriculum_co_po_mapping ccpm
                    JOIN curriculum_course_outcomes cco ON ccpm.curr_co_id = cco.id
                    JOIN po_pso p ON ccpm.po_id = p.id
                    JOIN curriculum_subjects cs ON cco.curr_sub_id = cs.id
                    WHERE cco.curr_sub_id IN ($inCurr2)
                ";
                $stmtCurrMap = $this->conn->prepare($sqlCurrMap);
                $types2 = str_repeat('i', count($currSubIds));
                $stmtCurrMap->bind_param($types2, ...$currSubIds);
                $stmtCurrMap->execute();
                $resCurrMap = $stmtCurrMap->get_result();
                while ($r = $resCurrMap->fetch_assoc()) {
                    $subcode = trim($r['subcode'] ?? '');
                    $poCode = trim($r['po_code'] ?? '');
                    $w = (float)$r['weightage'];
                    if ($subcode && $poCode && $w > 0) {
                        if (!isset($coursePoWeights[$subcode][$poCode])) {
                            $coursePoWeights[$subcode][$poCode] = ['sum' => 0.0, 'count' => 0];
                        }
                        $coursePoWeights[$subcode][$poCode]['sum'] += $w;
                        $coursePoWeights[$subcode][$poCode]['count']++;
                    }
                }
                $stmtCurrMap->close();
            }
        }

        // 5. Build NBA Table 3.2 (Program Articulation Matrix)
        $table3_2Rows = [];
        $colWeightSums = [];
        $colCourseCounts = [];

        foreach ($pos as $code => $p) {
            $colWeightSums[$code] = 0.0;
            $colCourseCounts[$code] = 0;
        }

        $cIndex = 1;
        foreach ($subjectList as $subcode => $sub) {
            $courseId = "C" . $cIndex;
            $rowMappings = [];

            foreach ($pos as $code => $p) {
                if (isset($coursePoWeights[$subcode][$code]) && $coursePoWeights[$subcode][$code]['count'] > 0) {
                    $avgW = round($coursePoWeights[$subcode][$code]['sum'] / $coursePoWeights[$subcode][$code]['count'], 2);
                    $rowMappings[$code] = $avgW;
                    $colWeightSums[$code] += $avgW;
                    $colCourseCounts[$code]++;
                } else {
                    $rowMappings[$code] = null;
                }
            }

            $table3_2Rows[] = [
                'course_index' => $courseId,
                'subcode' => $subcode,
                'course_name' => $sub['sub_fullname'],
                'yearsem' => $sub['yearsem'],
                'type' => $sub['sub_type'],
                'mappings' => $rowMappings
            ];
            $cIndex++;
        }

        $table3_2Averages = [];
        foreach ($pos as $code => $p) {
            if ($colCourseCounts[$code] > 0) {
                $table3_2Averages[$code] = round($colWeightSums[$code] / $colCourseCounts[$code], 2);
            } else {
                $table3_2Averages[$code] = null;
            }
        }

        // Course feedback is not a configured program-outcome survey. No simulated scores.
        $totalCesSubmissions = 0;
        $measured = [];
        require_once __DIR__ . '/../coattainment.class.php';
        require_once __DIR__ . '/../core/ReportValue.php';
        $coService = new \COAttainmentEngine();
        foreach ($subjectList as $course) {
            foreach ($course['all_subject_ids'] ?? [] as $sid) {
                $result = json_decode($coService->getComprehensiveAttainment($sid), true, 512, JSON_THROW_ON_ERROR);
                $directByCo=[];
                foreach($result['cos']??[] as $co){
                    if($co['cia_pct']!==null || $co['see_pct']!==null){$directByCo[(int)$co['co_id']]=$co['direct_attainment'];}
                }
                $stmt=$this->conn->prepare('SELECT m.co_id,m.weightage,p.code FROM co_po_mapping m JOIN course_outcomes co ON co.id=m.co_id JOIN po_pso p ON p.id=m.po_id WHERE co.sub_id=? AND m.weightage>0');
                $stmt->bind_param('i',$sid);$stmt->execute();$byPo=[];$missingPo=[];
                foreach($stmt->get_result() as $mapping){$code=$mapping['code'];$coId=(int)$mapping['co_id'];
                    if(!array_key_exists($coId,$directByCo)){$missingPo[$code]=true;}
                    else{$byPo[$code][]=['value'=>$directByCo[$coId],'weight'=>(float)$mapping['weightage']];}
                }
                foreach($byPo as $code=>$values){if(!isset($missingPo[$code])){$measured[$code][]=['value'=>\College\ReportValue::weighted($values),'weight'=>array_sum(array_column($values,'weight'))];}}

            }
        }
        // 7. Calculate Direct PO Attainment & Blend with Indirect CES (NBA Table 3.3.2)
        $table3_3_2Rows = [];
        $attainedCount = 0;
        $gapCount = 0;
        $totalAttainmentSum = 0.0;

        foreach ($pos as $code => $po) {
            $targetScore = (float)$po['target_score'];

            $directScore = \College\ReportValue::weighted($measured[$code] ?? []);
            $indirectScore = null;
            $indirectSource = 'No approved program-outcome survey evidence';
            $overallAttainment = null;
            $gap = null;
            $status = $directScore === null ? 'DATA UNAVAILABLE' : 'PROVISIONAL';
            $table3_3_2Rows[] = [
                'code' => $code,
                'type' => $po['type'],
                'description' => $po['description'],
                'direct_score' => $directScore,
                'indirect_score' => $indirectScore,
                'indirect_source' => $indirectSource,
                'overall_attainment' => $overallAttainment,
                'target_score' => $targetScore,
                'gap' => $gap,
                'status' => $status
            ];
        }

        $totalPos = count($pos);
        $avgAttainment = null;

        return [
            'status' => 1,
            'batch' => $batch,
            'program' => $program,
            'pos' => $pos,
            'total_courses' => count($table3_2Rows),
            'table_3_2' => [
                'rows' => $table3_2Rows,
                'averages' => $table3_2Averages
            ],
            'table_3_3_2' => [
                'rows' => $table3_3_2Rows,
                'summary' => [
                    'total_outcomes' => $totalPos,
                    'attained_count' => $attainedCount,
                    'gap_count' => $gapCount,
                    'average_attainment' => $avgAttainment,
                    'ces_submissions' => $totalCesSubmissions
                ]
            ]
        ];
    }

    // ==========================================
    // 8. Longitudinal Multi-Batch Quality Analytics (NBA Criterion 1 & NAAC Criterion 2)
    // ==========================================

    /**
     * Compute multi-cohort longitudinal PEO and PO trajectory trends across historical batches.
     *
     * @param int $deptId Academic Department ID
     * @return array Multi-cohort trajectory data for trend visualization and institutional auditing
     */
    public function getLongitudinalCohortAnalytics(int $deptId): array
    {
        // 2. Fetch cohorts/batches for this program (up to 6 historical cohorts)
        $sqlBatches = "
            SELECT b.id, b.batch_name, b.admission_year, b.graduation_year, b.program_id,
                   p.prog_shortname, r.regulation
            FROM student_batches b
            LEFT JOIN programs p ON b.program_id = p.id
            LEFT JOIN regulations r ON b.regulation_id = r.id
            WHERE EXISTS (SELECT 1 FROM specialization sp WHERE sp.prog_id=b.program_id AND sp.dept_id=?)
            ORDER BY b.admission_year DESC
            LIMIT 6
        ";
        $stmtB = $this->conn->prepare($sqlBatches);
        $stmtB->bind_param("i", $deptId);
        $stmtB->execute();
        $batches = array_reverse($stmtB->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmtB->close();

        if (empty($batches)) {
            return [
                'cohorts' => [],
                'po_series' => [],
                'peo_series' => [],
                'summary_trend' => []
            ];
        }

        $cohortSummaries = [];
        $poSeries = [];
        $peoSeries = [];
        $summaryTrend = [];

        foreach ($batches as $b) {
            $batchId = (int)$b['id'];
            $bName = $b['prog_shortname'] . ' — ' . ($b['batch_name'] ?: ($b['admission_year'] . '-' . $b['graduation_year'])) . ' (#' . $batchId . ')';

            // A. Compute NBA 3.3.2 Articulation & PO Attainment for this cohort
            $artData = $this->computePOPSOArticulationMatrix($batchId, (int)$b['program_id'], $deptId);
            $t3_3_2 = $artData['table_3_3_2']['rows'] ?? [];
            $summary = $artData['table_3_3_2']['summary'] ?? [];

            // B. Compute PEO Attainment for this cohort
            $macro = $this->getBatchMacroAttainment($batchId, $deptId);
            $peos = $macro['peos'] ?? [];

            $poAvg = $summary['average_attainment'] ?? null;
            $attainedCount = $summary['attained_count'] ?? 0;
            $gapCount = $summary['gap_count'] ?? 0;

            $peoTotal = 0.0;
            $peoCount = 0;
            foreach ($peos as $peoId => $pInfo) {
                $code = $pInfo['code'];
                $peoSeries[$code][$bName] = $pInfo['attained'];
                if ($pInfo['attained'] !== null) { $peoTotal += (float)$pInfo['attained']; $peoCount++; }
            }
            $peoAvg = ($peoCount > 0) ? round($peoTotal / $peoCount, 2) : null;

            foreach ($t3_3_2 as $r) {
                $code = $r['code'];
                $poSeries[$code][$bName] = $r['overall_attainment'];
            }

            $cohortSummaries[] = [
                'batch_id' => $batchId,
                'batch_name' => $bName,
                'admission_year' => (int)$b['admission_year'],
                'graduation_year' => (int)$b['graduation_year'],
                'regulation' => $b['regulation'] ?? 'R20/R23',
                'po_average' => $poAvg,
                'peo_average' => $peoAvg,
                'attained_outcomes' => $attainedCount,
                'gap_outcomes' => $gapCount,
                'ces_responses' => $summary['ces_submissions'] ?? 0
            ];

            $summaryTrend[] = [
                'cohort' => $bName,
                'po_average' => $poAvg,
                'peo_average' => $peoAvg
            ];
        }

        return [
            'department_id' => $deptId,
            'program_name' => $progRow['prog_shortname'] ?? 'B.Tech',
            'cohorts' => $cohortSummaries,
            'po_series' => $poSeries,
            'peo_series' => $peoSeries,
            'summary_trend' => $summaryTrend
        ];
    }
}


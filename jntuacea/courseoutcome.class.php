<?php
// courseoutcome.class.php - Modular Course Outcomes & PO/PSO Articulation Mapping
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait CourseOutcomeTrait
{
    protected $coClassname = "CourseOutcome";

    /**
     * Function to get Course Outcomes (COs) by subject ID
     */
    public function getCOsBySubjectId($sub_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->coClassname . " - getCOsBySubjectId - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent` FROM `course_outcomes` WHERE `sub_id` = ? ORDER BY `co_number` ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare getCOsBySubjectId statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();

            // If no local COs defined, fallback to master curriculum COs if linked
            if (empty($res['data'])) {
                $stmtMaster = $this->conn->prepare("
                    SELECT co.id, co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent, 1 as is_master
                    FROM curriculum_course_outcomes co
                    JOIN subjects s ON s.curr_sub_id = co.curr_sub_id
                    WHERE s.id = ?
                    ORDER BY co.co_number ASC
                ");
                if ($stmtMaster) {
                    $stmtMaster->bind_param("i", $sub_id);
                    $stmtMaster->execute();
                    $masterRows = $stmtMaster->get_result()->fetch_all(MYSQLI_ASSOC);
                    if (!empty($masterRows)) {
                        $res['data'] = $masterRows;
                        $res['is_inherited'] = true;
                    }
                    $stmtMaster->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function importMasterCOsToSubject(int $sub_id): array
    {
        $res = ['status' => 0, 'copied' => 0, 'mappings_copied' => 0];
        try {
            $stmt = $this->conn->prepare("SELECT s.curr_sub_id, s.class_id, s.subcode FROM subjects s WHERE s.id = ?");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $subData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$subData) {
                $res['error'] = "Subject not found.";
                return $res;
            }

            $subcode = trim($subData['subcode'] ?? '');
            $classId = (int)$subData['class_id'];
            $currSubId = !empty($subData['curr_sub_id']) ? (int)$subData['curr_sub_id'] : null;

            require_once __DIR__ . '/subject.class.php';
            $subObj = new Subject();
            $pRes = $subObj->provisionOfferingCOsAndMatrixFromCurriculum($sub_id, $currSubId, $classId, $subcode);

            // If nothing copied from curriculum, check if another subject offering with same subcode has COs
            if ($pRes['cos_copied'] === 0 && !empty($subcode)) {
                $pRes = $this->copyCOsFromSiblingOffering($sub_id, $subcode);
            }

            $res['status'] = 1;
            $res['copied'] = $pRes['cos_copied'];
            $res['mappings_copied'] = $pRes['mappings_copied'];
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("importMasterCOsToSubject Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Copies COs and Articulation Matrix from another subject offering sharing the same subcode
     */
    public function copyCOsFromSiblingOffering(int $targetSubId, string $subcode): array
    {
        $res = ['cos_copied' => 0, 'mappings_copied' => 0];
        try {
            $stmt = $this->conn->prepare("
                SELECT s.id, COUNT(co.id) as co_cnt
                FROM subjects s
                JOIN course_outcomes co ON co.sub_id = s.id
                WHERE s.subcode = ? AND s.id != ?
                GROUP BY s.id
                HAVING co_cnt > 0
                ORDER BY co_cnt DESC, s.id DESC
                LIMIT 1
            ");
            $stmt->bind_param("si", $subcode, $targetSubId);
            $stmt->execute();
            $sourceSub = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$sourceSub) return $res;
            $sourceSubId = (int)$sourceSub['id'];

            $srcCoStmt = $this->conn->prepare("
                SELECT id, co_number, co_description, bloom_level, target_threshold_percent
                FROM course_outcomes
                WHERE sub_id = ?
                ORDER BY co_number ASC
            ");
            $srcCoStmt->bind_param("i", $sourceSubId);
            $srcCoStmt->execute();
            $sourceCos = $srcCoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $srcCoStmt->close();

            if (empty($sourceCos)) return $res;

            $insCo = $this->conn->prepare("
                INSERT INTO course_outcomes (sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");

            $sourceToTargetCoMap = [];
            foreach ($sourceCos as $sc) {
                $srcCoId = (int)$sc['id'];
                $num = (int)$sc['co_number'];
                $desc = $sc['co_description'];
                $bloom = $sc['bloom_level'];
                $thresh = (float)$sc['target_threshold_percent'];

                $insCo->bind_param("iissd", $targetSubId, $num, $desc, $bloom, $thresh);
                if ($insCo->execute()) {
                    $newCoId = $this->conn->insert_id;
                    if (!$newCoId) {
                        $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE sub_id = ? AND co_number = ? LIMIT 1");
                        $chk->bind_param("ii", $targetSubId, $num);
                        $chk->execute();
                        $newCoId = $chk->get_result()->fetch_assoc()['id'] ?? null;
                        $chk->close();
                    }
                    if ($newCoId) {
                        $sourceToTargetCoMap[$srcCoId] = (int)$newCoId;
                        $res['cos_copied']++;
                    }
                }
            }
            $insCo->close();

            if (!empty($sourceToTargetCoMap)) {
                $srcCoIds = array_keys($sourceToTargetCoMap);
                $ph = implode(',', array_fill(0, count($srcCoIds), '?'));
                $types = str_repeat('i', count($srcCoIds));

                $mapStmt = $this->conn->prepare("
                    SELECT m.co_id, m.po_id, m.weightage, p.code as po_code
                    FROM co_po_mapping m
                    JOIN po_pso p ON m.po_id = p.id
                    WHERE m.co_id IN ($ph)
                ");
                $mapStmt->bind_param($types, ...$srcCoIds);
                $mapStmt->execute();
                $sourceMappings = $mapStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $mapStmt->close();

                if (!empty($sourceMappings)) {
                    require_once __DIR__ . '/subject.class.php';
                    $subObj = new Subject();
                    $tMetaStmt = $this->conn->prepare("
                        SELECT c.reg_id, c.spec_id, r.regulation, c.acad_year, sp.dept_id
                        FROM subjects s
                        JOIN classes c ON s.class_id = c.id
                        JOIN regulations r ON c.reg_id = r.id
                        LEFT JOIN specialization sp ON c.spec_id = sp.id
                        WHERE s.id = ?
                    ");
                    $tMetaStmt->bind_param("i", $targetSubId);
                    $tMetaStmt->execute();
                    $tMeta = $tMetaStmt->get_result()->fetch_assoc();
                    $tMetaStmt->close();

                    $regId = (int)($tMeta['reg_id'] ?? 0);
                    $specId = (int)($tMeta['spec_id'] ?? 0);
                    $regulation = trim($tMeta['regulation'] ?? '');
                    $deptId = (int)($tMeta['dept_id'] ?? 0);
                    $acadYear = trim($tMeta['acad_year'] ?? '');

                    $insMap = $this->conn->prepare("
                        INSERT INTO co_po_mapping (co_id, po_id, weightage)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
                    ");

                    $resolvedPos = [];
                    foreach ($sourceMappings as $sm) {
                        $sCoId = (int)$sm['co_id'];
                        if (!isset($sourceToTargetCoMap[$sCoId])) continue;
                        $tCoId = $sourceToTargetCoMap[$sCoId];

                        $poCode = trim($sm['po_code']);
                        $w = (int)$sm['weightage'];
                        if (!in_array($w, [1, 2, 3])) continue;

                        if (!array_key_exists($poCode, $resolvedPos)) {
                            $resolvedPos[$poCode] = $subObj->resolveOfferingPoId($poCode, $regId, $specId, $regulation, $deptId, $acadYear);
                        }
                        $targetPoId = $resolvedPos[$poCode];
                        if ($targetPoId) {
                            $insMap->bind_param("iii", $tCoId, $targetPoId, $w);
                            if ($insMap->execute()) {
                                $res['mappings_copied']++;
                            }
                        }
                    }
                    $insMap->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog("copyCOsFromSiblingOffering Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Checks if any other subject with the same subcode (or curriculum) has COs defined
     */
    public function hasExistingCOWithDifferentSubjectId(int $sub_id, string $subcode): bool
    {
        $subcode = trim($subcode);
        if (empty($subcode)) return false;

        try {
            // 1. Check if another offering subject with the same subcode has COs
            $stmt = $this->conn->prepare("
                SELECT 1
                FROM course_outcomes co
                JOIN subjects s ON co.sub_id = s.id
                WHERE s.subcode = ? AND s.id != ?
                LIMIT 1
            ");
            $stmt->bind_param("si", $subcode, $sub_id);
            $stmt->execute();
            $hasOther = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();
            if ($hasOther) return true;

            // 2. Check if curriculum_course_outcomes has COs for this subcode
            $stmt2 = $this->conn->prepare("
                SELECT 1
                FROM curriculum_course_outcomes cco
                JOIN curriculum_subjects cs ON cco.curr_sub_id = cs.id
                WHERE cs.subcode = ?
                LIMIT 1
            ");
            $stmt2->bind_param("s", $subcode);
            $stmt2->execute();
            $hasCurr = (bool)$stmt2->get_result()->fetch_row();
            $stmt2->close();
            return $hasCurr;
        } catch (Exception $e) {
            $this->logs->errLog("hasExistingCOWithDifferentSubjectId Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Function to add a new Course Outcome (CO)
     */
    public function addCO($sub_id, $co_number, $co_description, $bloom_level = 'L3-Apply', $target_threshold = 60.0)
    {
        $res = ['status' => 0];
        $myname = $this->coClassname . " - addCO - ";

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO `course_outcomes` (`sub_id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent`) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");
            if ($stmt) {
                $stmt->bind_param("iissd", $sub_id, $co_number, $co_description, $bloom_level, $target_threshold);
                if ($stmt->execute()) {
                    $res['status'] = 1;
                    $facultyId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($facultyId, "SAVE_COURSE_OUTCOME", "Saved Course Outcome CO$co_number for Subject ID $sub_id", "faculty", "COURSE_OUTCOME", (string)$sub_id);
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Fetches POs/PSOs relevant to the class/regulation for a subject
     */
    public function getRelevantPoPso($sub_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->coClassname . " - getRelevantPoPso - ";
        $spec_id = null;
        $regulation = null;

        try {
            $query_class_details = "SELECT c.spec_id, r.regulation, c.acad_year, c.reg_id
                                    FROM subjects s
                                    JOIN classes c ON s.class_id = c.id
                                    JOIN regulations r ON c.reg_id = r.id
                                    WHERE s.id = ?";
            $stmt1 = $this->conn->prepare($query_class_details);
            if (!$stmt1) { throw new Exception("Prepare failed (stmt1): " . $this->conn->error); }
            $stmt1->bind_param("i", $sub_id);
            if (!$stmt1->execute()) { throw new Exception("Execute failed (stmt1): " . $stmt1->error); }
            $stmt1->bind_result($spec_id, $regulation, $acad_year, $reg_id);
            $details_found = $stmt1->fetch();
            $stmt1->close();

            if ($details_found && $spec_id && $regulation) {
                $query_pops = "SELECT `id`, `code`, `description`, `po_pso`, `target_score`
                               FROM `po_pso`
                               WHERE `acad_year` = ? AND `specid` = ? AND `regulation` = ?
                               ORDER BY `po_pso` ASC, `orderid` ASC, `id` ASC";
                $stmt2 = $this->conn->prepare($query_pops);
                if (!$stmt2) { throw new Exception("Prepare failed (stmt2): " . $this->conn->error); }
                $stmt2->bind_param("sis", $acad_year, $spec_id, $regulation);
                if ($stmt2->execute()) {
                    $result = $stmt2->get_result();
                    $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Execute failed (stmt2): " . $stmt2->error);
                }
                $stmt2->close();

                // If no exact match on child spec_id, check parent dept_id (e.g. AI & ML parent is CSE)
                if (empty($res['data'])) {
                    $deptStmt = $this->conn->prepare("SELECT dept_id FROM specialization WHERE id = ?");
                    if ($deptStmt) {
                        $deptStmt->bind_param("i", $spec_id);
                        $deptStmt->execute();
                        $deptId = (int)($deptStmt->get_result()->fetch_assoc()['dept_id'] ?? 0);
                        $deptStmt->close();
                        if ($deptId > 0 && $deptId != $spec_id) {
                            $stmtDept = $this->conn->prepare("
                                SELECT `id`, `code`, `description`, `po_pso`, `target_score`
                                FROM `po_pso`
                                WHERE `acad_year` = ? AND `specid` = ? AND (`reg_id` = ? OR `regulation` = ?)
                                ORDER BY `po_pso` ASC, `orderid` ASC, `id` ASC
                            ");
                            if ($stmtDept) {
                                $stmtDept->bind_param("siis", $acad_year, $deptId, $reg_id, $regulation);
                                $stmtDept->execute();
                                $res['data'] = $stmtDept->get_result()->fetch_all(MYSQLI_ASSOC);
                                if (!empty($res['data'])) $res['status'] = 1;
                                $stmtDept->close();
                            }
                        }
                    }
                }
            } else {
                $this->logs->warningLog($myname . "Could not find spec ID/regulation for subject ID: $sub_id");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Fetches existing CO-PO/PSO mappings with weightage (1, 2, or 3)
     */
    public function getCoPoMappings($co_ids, $po_pso_ids)
    {
        $mappings = [];
        if (empty($co_ids) || empty($po_pso_ids)) {
            return $mappings;
        }
        $myname = $this->coClassname . " - getCoPoMappings - ";

        $co_placeholders = implode(',', array_fill(0, count($co_ids), '?'));
        $po_pso_placeholders = implode(',', array_fill(0, count($po_pso_ids), '?'));
        $types = str_repeat('i', count($co_ids) + count($po_pso_ids));
        $params = array_merge($co_ids, $po_pso_ids);

        try {
            $query = "SELECT `co_id`, `po_id`, `weightage` FROM `co_po_mapping` WHERE `co_id` IN ($co_placeholders) AND `po_id` IN ($po_pso_placeholders)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (isset($row['weightage']) && in_array($row['weightage'], [1, 2, 3])) {
                        $mappings[$row['co_id'] . '-' . $row['po_id']] = (int)$row['weightage'];
                    }
                }
            } else {
                $this->logs->errLog($myname . "Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $mappings;
    }

    /**
     * Adds or updates a single CO-PO/PSO mapping with specific weightage (1, 2, or 3)
     */
    public function addUpdateCoPoMapping($co_id, $po_pso_id, $weightage, $sub_id = null)
    {
        if (!in_array($weightage, [1, 2, 3])) {
            $this->logs->warningLog("Invalid weightage value ($weightage) provided for CO_ID $co_id, PO_ID $po_pso_id.");
            return false;
        }

        $myname = $this->coClassname . " - addUpdateCoPoMapping - ";
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO co_po_mapping (co_id, po_id, weightage) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
            ");
            if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            $stmt->bind_param("iii", $co_id, $po_pso_id, $weightage);

            if ($stmt->execute()) {
                $stmt->close();
                $facultyId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($facultyId, "SAVE_CO_PO_MAPPING", "Updated CO-PO mapping: CO ID $co_id to PO ID $po_pso_id with weight $weightage", "faculty", "CO_PO_MAPPING", (string)$co_id);
                return true;
            } else {
                $this->logs->errLog($myname . "Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to get a map of CO Numbers to CO IDs for a specific subject
     */
    public function getCoMapByNumber($sub_id)
    {
        $co_map = [];
        $stmt = $this->conn->prepare("SELECT id, co_number FROM course_outcomes WHERE sub_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $stmt->bind_result($co_id, $co_number);
        while ($stmt->fetch()) {
            $co_map[$co_number] = $co_id;
        }
        $stmt->close();
        return $co_map;
    }

    /**
     * Helper to get the CO numbers (e.g., [1, 3]) for a source question
     */
    public function getSourceQuestionCoNumbers($question_id)
    {
        $co_numbers = [];
        $stmt = $this->conn->prepare("SELECT co.co_number FROM question_co_mapping qcm JOIN course_outcomes co ON qcm.co_id = co.id WHERE qcm.question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($co_number);
        while ($stmt->fetch()) {
            $co_numbers[] = $co_number;
        }
        $stmt->close();
        return $co_numbers;
    }
}

class CourseOutcome extends DBCredentials
{
    use CourseOutcomeTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

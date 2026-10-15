<?php
require_once("dbcredentials.class.php");

class Subject extends DBCredentials {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Standard SQL projection fields for the subjects table.
     * Aliases dynamically formatted strings to sub_fullname and sub_shortname
     * so legacy views receive the correct display name without code modification.
     */
    public static function sqlProjection($alias = 's') {
        return "
            {$alias}.id,
            {$alias}.class_id,
            {$alias}.curr_sub_id,
            {$alias}.subject_sno,
            {$alias}.subcode,
            {$alias}.sub_type,
            {$alias}.group_name,
            {$alias}.sub_fullname,
            {$alias}.sub_shortname,
            {$alias}.sub_fullname AS raw_sub_fullname,
            {$alias}.sub_shortname AS raw_sub_shortname
        ";
    }

    /**
     * Get all subjects for a class, ordered by S.No and group
     */
    public function getSubjectsByClass($classId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . ", 
                        cs.course_category, cs.credits, cs.lecture_hours, cs.tutorial_hours, cs.practical_hours,
                        cs.cie_max_marks, cs.see_max_marks, cs.total_marks, cs.has_see, cs.elective_track, cs.delivery_mode, cs.prerequisites
                 FROM subjects s
                 LEFT JOIN curriculum_subjects cs ON s.curr_sub_id = cs.id
                 WHERE s.class_id = ?
                 ORDER BY CAST(s.subject_sno AS UNSIGNED) ASC, s.group_name ASC"
            );
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectsByClass - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Get subjects assigned to a specific faculty member
     * Includes class metadata (classname, acad_year, start_date, end_date) required by faculty views.
     */
    public function getSubjectsByFaculty($facultyId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . ",
                        cs.course_category, cs.credits, cs.cie_max_marks, cs.see_max_marks, cs.total_marks,
                        fs.id AS faculty_sub_id,
                        c.acad_year,
                        c.classname AS class_name,
                        c.start_date,
                        c.end_date
                 FROM subjects s
                 LEFT JOIN curriculum_subjects cs ON s.curr_sub_id = cs.id
                 JOIN faculty_sub fs ON fs.sub_id = s.id
                 JOIN classes c ON s.class_id = c.id
                 WHERE fs.faculty_id = ?
                 ORDER BY c.start_date DESC, CAST(s.subject_sno AS UNSIGNED) ASC, s.group_name ASC"
            );
            $stmt->bind_param("i", $facultyId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectsByFaculty - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Get single subject details by primary key
     */
    public function getSubjectById($subjectId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . ",
                        cs.course_category, cs.credits, cs.lecture_hours, cs.tutorial_hours, cs.practical_hours,
                        cs.cie_max_marks, cs.see_max_marks, cs.total_marks, cs.has_see, cs.elective_track, cs.delivery_mode, cs.prerequisites
                 FROM subjects s 
                 LEFT JOIN curriculum_subjects cs ON s.curr_sub_id = cs.id
                 WHERE s.id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectById - " . $e->getMessage());
        }
        return null;
    }

    /**
     * Fetch available master curriculum subjects matching class regulation/branch/semester
     */
    public function getAvailableCurriculumForClass($classId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT cs.*
                 FROM curriculum_subjects cs
                 JOIN classes c ON c.reg_id = cs.reg_id
                               AND c.spec_id = cs.spec_id
                               AND c.yearsem = cs.yearsem
                 WHERE c.id = ? AND cs.status = 1
                 ORDER BY cs.subject_sno ASC"
            );
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getAvailableCurriculumForClass - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Create/Provision a subject offering instance
     */
    public function createOffering($classId, $currSubId, $sno, $subcode, $shortname, $fullname, $subType, $groupName = '') {
        try {
            $currSubIdVal = !empty($currSubId) ? (int)$currSubId : null;
            $subcode      = strtoupper(trim($subcode));
            $groupName    = strtoupper(trim($groupName));

            // Auto-resolve curr_sub_id if not explicitly provided
            if (!$currSubIdVal && !empty($subcode)) {
                $cStmt = $this->conn->prepare("
                    SELECT cs.id FROM curriculum_subjects cs
                    JOIN classes c ON c.id = ?
                    WHERE cs.subcode = ? AND cs.reg_id = c.reg_id
                    LIMIT 1
                ");
                if ($cStmt) {
                    $cStmt->bind_param("is", $classId, $subcode);
                    $cStmt->execute();
                    $currSubIdVal = $cStmt->get_result()->fetch_assoc()['id'] ?? null;
                    $cStmt->close();
                    if ($currSubIdVal) $currSubIdVal = (int)$currSubIdVal;
                }
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO subjects
                 (class_id, curr_sub_id, subject_sno, subcode, sub_shortname, sub_fullname, sub_type, group_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iissssss",
                $classId, $currSubIdVal, $sno, $subcode, $shortname, $fullname, $subType, $groupName
            );
            $ok = $stmt->execute();
            $newSubjectId = $this->conn->insert_id;
            $stmt->close();

            // Automatically clone COs and Articulation Matrix from curriculum master tables!
            if ($ok && $newSubjectId) {
                $this->provisionOfferingCOsAndMatrixFromCurriculum((int)$newSubjectId, $currSubIdVal, (int)$classId, $subcode);
            }
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::createOffering - " . $e->getMessage());
        }
        return false;
    }

    /**
     * Automatically copies Master Course Outcomes and Articulation Matrix
     * from curriculum master tables (curriculum_course_outcomes & curriculum_co_po_mapping)
     * to offering tables (course_outcomes & co_po_mapping) for a class offering subject.
     */
    public function provisionOfferingCOsAndMatrixFromCurriculum(int $subjectId, ?int $currSubId, int $classId, string $subcode = ''): array
    {
        $res = ['cos_copied' => 0, 'mappings_copied' => 0];
        try {
            // 1. Fetch class metadata (reg_id, spec_id, acad_year)
            $classStmt = $this->conn->prepare("
                SELECT c.reg_id, c.spec_id, c.acad_year, r.regulation, sp.dept_id
                FROM classes c
                LEFT JOIN regulations r ON c.reg_id = r.id
                LEFT JOIN specialization sp ON c.spec_id = sp.id
                WHERE c.id = ?
            ");
            $classStmt->bind_param("i", $classId);
            $classStmt->execute();
            $classMeta = $classStmt->get_result()->fetch_assoc();
            $classStmt->close();

            if (!$classMeta) return $res;

            $regId = (int)$classMeta['reg_id'];
            $specId = (int)$classMeta['spec_id'];
            $deptId = (int)($classMeta['dept_id'] ?? 0);
            $acadYear = trim($classMeta['acad_year'] ?? '');
            $regulation = trim($classMeta['regulation'] ?? '');

            // 2. Fetch master COs from curriculum_course_outcomes
            $masterCos = [];
            if ($currSubId) {
                $mStmt = $this->conn->prepare("
                    SELECT id, co_number, co_description, bloom_level, target_threshold_percent
                    FROM curriculum_course_outcomes
                    WHERE curr_sub_id = ?
                    ORDER BY co_number ASC
                ");
                $mStmt->bind_param("i", $currSubId);
                $mStmt->execute();
                $masterCos = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $mStmt->close();
            }

            // Fallback: search by subcode & reg_id in curriculum_subjects
            if (empty($masterCos) && !empty($subcode)) {
                $mStmt2 = $this->conn->prepare("
                    SELECT co.id, co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent, co.curr_sub_id
                    FROM curriculum_course_outcomes co
                    JOIN curriculum_subjects cs ON co.curr_sub_id = cs.id
                    WHERE cs.subcode = ? AND cs.reg_id = ?
                    ORDER BY co.co_number ASC
                ");
                $mStmt2->bind_param("si", $subcode, $regId);
                $mStmt2->execute();
                $masterCos = $mStmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $mStmt2->close();
                if (!empty($masterCos) && !$currSubId && !empty($masterCos[0]['curr_sub_id'])) {
                    $currSubId = (int)$masterCos[0]['curr_sub_id'];
                }
            }

            if (empty($masterCos)) {
                return $res;
            }

            // 3. Insert into course_outcomes for this offering
            $coMap = []; // [co_number => new_co_id]
            $masterToOfferingCoId = []; // [curr_co_id => new_co_id]

            $insCo = $this->conn->prepare("
                INSERT INTO course_outcomes (sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");

            foreach ($masterCos as $mc) {
                $currCoId = (int)$mc['id'];
                $num = (int)$mc['co_number'];
                $desc = $mc['co_description'];
                $bloom = $mc['bloom_level'];
                $thresh = (float)$mc['target_threshold_percent'];

                $insCo->bind_param("iissd", $subjectId, $num, $desc, $bloom, $thresh);
                if ($insCo->execute()) {
                    $newCoId = $this->conn->insert_id;
                    if (!$newCoId) {
                        $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE sub_id = ? AND co_number = ? LIMIT 1");
                        $chk->bind_param("ii", $subjectId, $num);
                        $chk->execute();
                        $newCoId = $chk->get_result()->fetch_assoc()['id'] ?? null;
                        $chk->close();
                    }
                    if ($newCoId) {
                        $coMap[$num] = (int)$newCoId;
                        $masterToOfferingCoId[$currCoId] = (int)$newCoId;
                        $res['cos_copied']++;
                    }
                }
            }
            $insCo->close();

            // 4. Fetch Master Matrix mappings from curriculum_co_po_mapping
            $currCoIds = array_keys($masterToOfferingCoId);
            if (empty($currCoIds)) return $res;

            $ph = implode(',', array_fill(0, count($currCoIds), '?'));
            $types = str_repeat('i', count($currCoIds));

            $mapStmt = $this->conn->prepare("
                SELECT m.curr_co_id, m.weightage, p.code as po_code
                FROM curriculum_co_po_mapping m
                JOIN po_pso p ON m.po_id = p.id
                WHERE m.curr_co_id IN ($ph)
            ");
            $mapStmt->bind_param($types, ...$currCoIds);
            $mapStmt->execute();
            $masterMappings = $mapStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $mapStmt->close();

            if (empty($masterMappings)) return $res;

            // 5. Insert mapped cells into co_po_mapping
            $insMap = $this->conn->prepare("
                INSERT INTO co_po_mapping (co_id, po_id, weightage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
            ");

            // Cache resolved PO IDs to avoid redundant DB queries
            $resolvedPos = [];

            foreach ($masterMappings as $mm) {
                $currCoId = (int)$mm['curr_co_id'];
                if (!isset($masterToOfferingCoId[$currCoId])) continue;
                $targetCoId = $masterToOfferingCoId[$currCoId];

                $poCode = trim($mm['po_code']);
                $w = (int)$mm['weightage'];
                if (!in_array($w, [1, 2, 3])) continue;

                // Resolve PO for this offering's class context
                if (!array_key_exists($poCode, $resolvedPos)) {
                    $resolvedPos[$poCode] = $this->resolveOfferingPoId($poCode, $regId, $specId, $regulation, $deptId, $acadYear);
                }
                $targetPoId = $resolvedPos[$poCode];

                if ($targetPoId) {
                    $insMap->bind_param("iii", $targetCoId, $targetPoId, $w);
                    if ($insMap->execute()) {
                        $res['mappings_copied']++;
                    }
                }
            }
            $insMap->close();

        } catch (Exception $e) {
            $this->logs->errLog("Subject::provisionOfferingCOsAndMatrixFromCurriculum - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Resolves canonical PO/PSO ID for an offering class
     */
    public function resolveOfferingPoId(string $poCode, int $regId, int $specId, string $regulation = '', int $deptId = 0, string $acadYear = ''): ?int
    {
        $poCode = trim($poCode);
        if ($poCode === '') return null;

        // Auto-resolve dept_id from specialization if not provided
        if ($deptId == 0 && $specId > 0) {
            $spStmt = $this->conn->prepare("SELECT dept_id FROM specialization WHERE id = ?");
            if ($spStmt) {
                $spStmt->bind_param("i", $specId);
                $spStmt->execute();
                $deptId = (int)($spStmt->get_result()->fetch_assoc()['dept_id'] ?? 0);
                $spStmt->close();
            }
        }

        $candidateSpecs = [];
        if ($specId > 0) $candidateSpecs[] = $specId;
        if ($deptId > 0 && !in_array($deptId, $candidateSpecs)) $candidateSpecs[] = $deptId;

        // 1. Try with acad_year across candidate specializations
        if (!empty($acadYear)) {
            foreach ($candidateSpecs as $candSpec) {
                $s1 = $this->conn->prepare("
                    SELECT id FROM po_pso
                    WHERE code = ? AND acad_year = ? AND specid = ?
                      AND ((reg_id = ? AND reg_id != 0) OR (regulation = ? AND regulation != ''))
                    ORDER BY id ASC LIMIT 1
                ");
                if ($s1) {
                    $s1->bind_param("ssiis", $poCode, $acadYear, $candSpec, $regId, $regulation);
                    $s1->execute();
                    $id = $s1->get_result()->fetch_assoc()['id'] ?? null;
                    $s1->close();
                    if ($id) return (int)$id;
                }
            }
        }

        // 2. Try without acad_year
        foreach ($candidateSpecs as $candSpec) {
            $s2 = $this->conn->prepare("
                SELECT id FROM po_pso
                WHERE code = ? AND specid = ?
                  AND ((reg_id = ? AND reg_id != 0) OR (regulation = ? AND regulation != ''))
                ORDER BY id ASC LIMIT 1
            ");
            if ($s2) {
                $s2->bind_param("siis", $poCode, $candSpec, $regId, $regulation);
                $s2->execute();
                $id = $s2->get_result()->fetch_assoc()['id'] ?? null;
                $s2->close();
                if ($id) return (int)$id;
            }
        }

        return null;
    }

    /**
     * Batch syncs all offering subjects for a class from curriculum master
     */
    public function syncClassOfferingsFromCurriculum(int $classId): array
    {
        $res = ['subjects_checked' => 0, 'cos_copied' => 0, 'mappings_copied' => 0];
        try {
            $subjects = $this->getSubjectsByClass($classId);
            $res['subjects_checked'] = count($subjects);

            foreach ($subjects as $s) {
                $subId = (int)$s['id'];
                $currSubId = !empty($s['curr_sub_id']) ? (int)$s['curr_sub_id'] : null;
                $subcode = trim($s['subcode'] ?? '');

                // Check if COs exist
                $chkCo = $this->conn->prepare("SELECT COUNT(*) FROM course_outcomes WHERE sub_id = ?");
                $chkCo->bind_param("i", $subId);
                $chkCo->execute();
                $coCount = (int)$chkCo->get_result()->fetch_row()[0];
                $chkCo->close();

                // Check if mappings exist
                $chkMap = $this->conn->prepare("
                    SELECT COUNT(*) FROM co_po_mapping m
                    JOIN course_outcomes co ON m.co_id = co.id
                    WHERE co.sub_id = ?
                ");
                $chkMap->bind_param("i", $subId);
                $chkMap->execute();
                $mapCount = (int)$chkMap->get_result()->fetch_row()[0];
                $chkMap->close();

                if ($coCount === 0 || $mapCount === 0) {
                    $pRes = $this->provisionOfferingCOsAndMatrixFromCurriculum($subId, $currSubId, $classId, $subcode);
                    $res['cos_copied'] += $pRes['cos_copied'];
                    $res['mappings_copied'] += $pRes['mappings_copied'];
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog("Subject::syncClassOfferingsFromCurriculum - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Update an existing subject offering
     */
    public function updateOffering($subjectId, $currSubId, $sno, $subcode, $shortname, $fullname, $subType, $groupName = '') {
        try {
            $currSubIdVal = !empty($currSubId) ? (int)$currSubId : null;
            $subcode      = strtoupper(trim($subcode));
            $groupName    = strtoupper(trim($groupName));

            $stmt = $this->conn->prepare(
                "UPDATE subjects SET
                   curr_sub_id   = ?,
                   subject_sno   = ?,
                   subcode       = ?,
                   sub_shortname = ?,
                   sub_fullname  = ?,
                   sub_type      = ?,
                   group_name    = ?
                 WHERE id = ?"
            );
            $stmt->bind_param("issssssi",
                $currSubIdVal, $sno, $subcode, $shortname, $fullname, $subType, $groupName, $subjectId
            );
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::updateOffering - " . $e->getMessage());
        }
        return false;
    }

    /**
     * Delete subject offering
     */
    public function deleteOffering($subjectId) {
        try {
            $subjectId = (int)$subjectId;

            // 1. Delete offering matrix mappings for this subject's COs
            $this->conn->query("
                DELETE m FROM co_po_mapping m
                JOIN course_outcomes co ON m.co_id = co.id
                WHERE co.sub_id = {$subjectId}
            ");

            // 2. Delete offering course outcomes for this subject
            $this->conn->query("DELETE FROM course_outcomes WHERE sub_id = {$subjectId}");

            // 3. Delete subject offering
            $stmt = $this->conn->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subjectId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::deleteOffering - " . $e->getMessage());
        }
        return false;
    }
}
?>

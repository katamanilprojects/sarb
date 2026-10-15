<?php
require_once("user.class.php");
require_once("programs.class.php");
require_once("regulations.class.php");
require_once("superadmin.class.php");

class CurriculumSubject extends User
{
    private $classname = "CurriculumSubject";

    public function __construct()
    {
        parent::__construct();
    }

    public function getAllPrograms()
    {
        $programsObj = new Programs();
        return $programsObj->getAllPrograms();
    }

    public function getAllRegulations()
    {
        $regulationsObj = new Regulations();
        return $regulationsObj->getAllRegulations();
    }

    public function getRegulationsByProgramId($progId)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getRegulationsByProgramId - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT r.id, r.regulation, r.prog_id, p.prog_shortname
                    FROM regulations r
                    JOIN programs p ON r.prog_id = p.id
                    WHERE r.prog_id = ?
                    ORDER BY r.regulation ASC";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $progId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch regulations for program.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getCategoriesAndTypesByRegId(int $regId): array
    {
        $res = [
            'status' => 1,
            'categories' => [],
            'types' => []
        ];

        if ($regId <= 0) {
            return $res;
        }

        try {
            // Categories
            $stmtCat = $this->conn->prepare("SELECT id, category_code, category_name, min_allocation_pct, max_allocation_pct, target_credits, description 
                                             FROM regulation_course_categories 
                                             WHERE reg_id = ? 
                                             ORDER BY category_code ASC");
            if ($stmtCat) {
                $stmtCat->bind_param("i", $regId);
                $stmtCat->execute();
                $res['categories'] = $stmtCat->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtCat->close();
            }

            // Types
            $stmtType = $this->conn->prepare("SELECT id, type_code, type_name, evaluation_scheme, cie_max_marks, see_max_marks, total_marks, has_see, is_credit_course, description 
                                              FROM regulation_course_types 
                                              WHERE reg_id = ? 
                                              ORDER BY type_code ASC");
            if ($stmtType) {
                $stmtType->bind_param("i", $regId);
                $stmtType->execute();
                $res['types'] = $stmtType->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtType->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog("CurriculumSubject - getCategoriesAndTypesByRegId: " . $e->getMessage());
        }

        return $res;
    }

    public function getAllSpecializations()
    {
        $superadmin = new SuperAdmin();
        return $superadmin->getAllSpecializations();
    }

    public function getSpecializationsByProgramId($progId)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSpecializationsByProgramId - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT id, spec_code, spec_shortname, spec_fullname, prog_id
                    FROM specialization
                    WHERE prog_id = ? AND status = 1
                    ORDER BY spec_fullname ASC";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $progId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch specializations.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getYearSemOptions()
    {
        return [
            "I Yr - I Sem",
            "II Yr - I Sem",
            "III Yr - I Sem",
            "IV Yr - I Sem",
            "I Yr - II Sem",
            "II Yr - II Sem",
            "III Yr - II Sem",
            "IV Yr - II Sem",
            "I Sem",
            "II Sem",
            "III Sem",
            "IV Sem"
        ];
    }

    public function getSubjectsByContext($progId, $regId, $specId, $yearsem)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSubjectsByContext - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT cs.*, p.prog_shortname, r.regulation, s.spec_shortname, s.spec_fullname 
                    FROM curriculum_subjects cs
                    JOIN programs p ON cs.prog_id = p.id
                    JOIN regulations r ON cs.reg_id = r.id
                    JOIN specialization s ON cs.spec_id = s.id
                    WHERE cs.prog_id = ? AND cs.reg_id = ? AND cs.spec_id = ? AND cs.yearsem = ?
                    ORDER BY cs.status DESC, cs.subject_sno ASC, cs.subcode ASC";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("iiis", $progId, $regId, $specId, $yearsem);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch curriculum subjects.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getSubjectsForClass($regId, $specId, $yearsem)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSubjectsForClass - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT id, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits
                    FROM curriculum_subjects
                    WHERE reg_id = ? AND spec_id = ? AND yearsem = ? AND status = 1
                    ORDER BY subject_sno ASC, subcode ASC";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("iis", $regId, $specId, $yearsem);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch curriculum subjects for class.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getSubjectById($id)
    {
        $res = ['status' => 0, 'data' => null, 'error' => ''];
        $myname = $this->classname . " - getSubjectById - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT * FROM curriculum_subjects WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data'] = $row;
                $res['status'] = 1;
            } else {
                $res['error'] = 'Subject not found.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch subject.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function lookupSubjectByCodeAndReg($subcode, $regId)
    {
        $res = ['status' => 0, 'data' => null, 'error' => ''];
        $myname = $this->classname . " - lookupSubjectByCodeAndReg - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $cleanCode = trim($subcode);
            $regId = (int)$regId;

            // First check in curriculum_subjects matching the same regulation
            $sql = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits, cie_max_marks, see_max_marks, total_marks, has_see, elective_track, delivery_mode, prerequisites
                    FROM curriculum_subjects
                    WHERE subcode = ? AND reg_id = ?
                    ORDER BY id DESC LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("si", $cleanCode, $regId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data'] = $row;
                $res['status'] = 1;
                $stmt->close();
                return $res;
            }
            $stmt->close();

            // If not found in curriculum_subjects, search any regulation in curriculum_subjects
            $sql2 = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits, cie_max_marks, see_max_marks, total_marks, has_see, elective_track, delivery_mode, prerequisites
                     FROM curriculum_subjects
                     WHERE subcode = ?
                     ORDER BY id DESC LIMIT 1";
            $stmt2 = $this->conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param("s", $cleanCode);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                if ($row2 = $result2->fetch_assoc()) {
                    $res['data'] = $row2;
                    $res['status'] = 1;
                    $stmt2->close();
                    return $res;
                }
                $stmt2->close();
            }

            // Finally, fall back to existing subjects table if it was ever taught previously
            $sql3 = "SELECT subcode, sub_fullname, sub_shortname, sub_type
                     FROM subjects
                     WHERE subcode = ?
                     ORDER BY id DESC LIMIT 1";
            $stmt3 = $this->conn->prepare($sql3);
            if ($stmt3) {
                $stmt3->bind_param("s", $cleanCode);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                if ($row3 = $result3->fetch_assoc()) {
                    $row3['course_category'] = '';
                    $row3['lecture_hours'] = 0.0;
                    $row3['tutorial_hours'] = 0.0;
                    $row3['pr_hours'] = 0.0;
                    $row3['practical_hours'] = 0.0;
                    $row3['credits'] = 0.0;
                    $row3['cie_max_marks'] = 30;
                    $row3['see_max_marks'] = 70;
                    $row3['total_marks'] = 100;
                    $row3['has_see'] = 1;
                    $row3['elective_track'] = '';
                    $row3['delivery_mode'] = 'OFFLINE';
                    $row3['prerequisites'] = '';
                    $res['data'] = $row3;
                    $res['status'] = 1;
                    $stmt3->close();
                    return $res;
                }
                $stmt3->close();
            }

            $res['error'] = 'Subject code not found.';
        } catch (Exception $e) {
            $res['error'] = 'Failed to lookup subject code.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function addOrUpdateSubject($data)
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        $myname = $this->classname . " - addOrUpdateSubject - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $id = !empty($data['id']) ? (int)$data['id'] : 0;
            $progId = (int)($data['prog_id'] ?? 0);
            $regId = (int)($data['reg_id'] ?? 0);
            $specId = (int)($data['spec_id'] ?? 0);
            $yearsem = trim($data['yearsem'] ?? '');
            $subjectSno = (int)($data['subject_sno'] ?? 1);
            $subcode = strtoupper(trim($data['subcode'] ?? ''));
            $subFullname = trim($data['sub_fullname'] ?? '');
            $subShortname = strtoupper(trim($data['sub_shortname'] ?? ''));
            $subType = trim($data['sub_type'] ?? 'Theory');
            $courseCategory = strtoupper(trim($data['course_category'] ?? ''));
            $lectureHours = (float)($data['lecture_hours'] ?? 0.0);
            $tutorialHours = (float)($data['tutorial_hours'] ?? 0.0);
            $prHours = (float)($data['pr_hours'] ?? 0.0);
            $practicalHours = (float)($data['practical_hours'] ?? 0.0);
            $credits = (float)($data['credits'] ?? 0.0);

            $cieMaxMarks = isset($data['cie_max_marks']) && $data['cie_max_marks'] !== '' ? (float)$data['cie_max_marks'] : null;
            $seeMaxMarks = isset($data['see_max_marks']) && $data['see_max_marks'] !== '' ? (float)$data['see_max_marks'] : null;
            $totalMarks = isset($data['total_marks']) && $data['total_marks'] !== '' ? (float)$data['total_marks'] : null;
            $hasSee = isset($data['has_see']) ? (int)$data['has_see'] : null;
            $electiveTrack = !empty($data['elective_track']) ? trim($data['elective_track']) : null;
            $deliveryMode = !empty($data['delivery_mode']) ? trim($data['delivery_mode']) : 'CONVENTIONAL';
            $prerequisites = !empty($data['prerequisites']) ? trim($data['prerequisites']) : null;

            // Auto-derive default marks and SEE settings from regulation_course_types if not provided
            if ($cieMaxMarks === null || $seeMaxMarks === null || $totalMarks === null) {
                $tStmt = $this->conn->prepare("SELECT cie_max_marks, see_max_marks, total_marks, has_see FROM regulation_course_types WHERE reg_id = ? AND (type_code = ? OR type_name = ?) LIMIT 1");
                if ($tStmt) {
                    $tStmt->bind_param("iss", $regId, $subType, $subType);
                    $tStmt->execute();
                    $tRow = $tStmt->get_result()->fetch_assoc();
                    $tStmt->close();
                    if ($tRow) {
                        if ($cieMaxMarks === null) $cieMaxMarks = (float)$tRow['cie_max_marks'];
                        if ($seeMaxMarks === null) $seeMaxMarks = (float)$tRow['see_max_marks'];
                        if ($totalMarks === null) $totalMarks = (float)$tRow['total_marks'];
                        if ($hasSee === null) $hasSee = (int)$tRow['has_see'];
                    }
                }
            }
            if ($cieMaxMarks === null) $cieMaxMarks = 30.0;
            if ($seeMaxMarks === null) $seeMaxMarks = 70.0;
            if ($totalMarks === null) $totalMarks = $cieMaxMarks + $seeMaxMarks;
            if ($hasSee === null) $hasSee = ($seeMaxMarks > 0) ? 1 : 0;

            // Auto-calculate credits if not specified or zero: C = L + T + 0.5 * practical
            if ($credits <= 0.0) {
                $pract = max($practicalHours, $prHours);
                $credits = round($lectureHours + $tutorialHours + (0.5 * $pract), 1);
            }

            if (empty($progId) || empty($regId) || empty($specId) || empty($yearsem) || empty($subcode) || empty($subFullname) || empty($subShortname)) {
                $res['error'] = 'Please fill all required fields.';
                return $res;
            }

            if ($id > 0) {
                // Update
                $sql = "UPDATE curriculum_subjects 
                        SET subject_sno = ?, subcode = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, course_category = ?, 
                            lecture_hours = ?, tutorial_hours = ?, pr_hours = ?, practical_hours = ?, credits = ?,
                            cie_max_marks = ?, see_max_marks = ?, total_marks = ?, has_see = ?, elective_track = ?, delivery_mode = ?, prerequisites = ?, status = 1
                        WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->conn->error);
                }
                $stmt->bind_param(
                    "isssssddddddddisssi", 
                    $subjectSno, $subcode, $subFullname, $subShortname, $subType, $courseCategory, 
                    $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits,
                    $cieMaxMarks, $seeMaxMarks, $totalMarks, $hasSee, $electiveTrack, $deliveryMode, $prerequisites,
                    $id
                );
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $res['status'] = 1;
                $res['message'] = 'Curriculum subject updated successfully.';
                $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($acadId, "UPDATE_CURRICULUM_SUBJECT", "Updated curriculum subject ID $id: $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$id);
            } else {
                // Check if already exists for this context
                $checkSql = "SELECT id FROM curriculum_subjects WHERE reg_id = ? AND spec_id = ? AND yearsem = ? AND subcode = ?";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->bind_param("iiss", $regId, $specId, $yearsem, $subcode);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if ($existing = $checkRes->fetch_assoc()) {
                    // Update existing
                    $checkStmt->close();
                    $existingId = (int)$existing['id'];
                    $updateSql = "UPDATE curriculum_subjects 
                                  SET subject_sno = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, course_category = ?, 
                                      lecture_hours = ?, tutorial_hours = ?, pr_hours = ?, practical_hours = ?, credits = ?,
                                      cie_max_marks = ?, see_max_marks = ?, total_marks = ?, has_see = ?, elective_track = ?, delivery_mode = ?, prerequisites = ?, status = 1
                                  WHERE id = ?";
                    $uStmt = $this->conn->prepare($updateSql);
                    $uStmt->bind_param(
                        "issssddddddddisssi", 
                        $subjectSno, $subFullname, $subShortname, $subType, $courseCategory, 
                        $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits,
                        $cieMaxMarks, $seeMaxMarks, $totalMarks, $hasSee, $electiveTrack, $deliveryMode, $prerequisites,
                        $existingId
                    );
                    $uStmt->execute();
                    $uStmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject already existed for this course and was updated successfully.';
                    $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($acadId, "UPDATE_CURRICULUM_SUBJECT", "Updated existing curriculum subject ID $existingId: $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$existingId);
                } else {
                    $checkStmt->close();
                    // Insert
                    $sql = "INSERT INTO curriculum_subjects 
                            (prog_id, reg_id, spec_id, yearsem, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, 
                             lecture_hours, tutorial_hours, pr_hours, practical_hours, credits, 
                             cie_max_marks, see_max_marks, total_marks, has_see, elective_track, delivery_mode, prerequisites, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $this->conn->error);
                    }
                    $stmt->bind_param(
                        "iiisisssssddddddddisss", 
                        $progId, $regId, $specId, $yearsem, $subjectSno, $subcode, $subFullname, $subShortname, $subType, $courseCategory, 
                        $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits,
                        $cieMaxMarks, $seeMaxMarks, $totalMarks, $hasSee, $electiveTrack, $deliveryMode, $prerequisites
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    $newSubId = $this->conn->insert_id;
                    $stmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject added successfully.';
                    $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($acadId, "ADD_CURRICULUM_SUBJECT", "Added curriculum subject $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$newSubId);
                }
            }
        } catch (Exception $e) {
            $res['error'] = 'Failed to save subject: ' . $e->getMessage();
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function toggleStatusSubject($id, $newStatus = null)
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        $myname = $this->classname . " - toggleStatusSubject - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $id = (int)$id;
            if ($newStatus === null) {
                $sql = "UPDATE curriculum_subjects SET status = IF(status = 1, 0, 1) WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
            } else {
                $newStatus = (int)$newStatus;
                $sql = "UPDATE curriculum_subjects SET status = ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ii", $newStatus, $id);
            }

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            $res['status'] = 1;
            $res['message'] = 'Subject status updated successfully.';
            $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($acadId, "TOGGLE_CURRICULUM_SUBJECT_STATUS", "Curriculum subject ID $id status updated to " . ($newStatus !== null ? $newStatus : 'toggled'), "academic_section", "CURRICULUM_SUBJECT", (string)$id);
        } catch (Exception $e) {
            $res['error'] = 'Failed to update subject status.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function deleteSubject($id)
    {
        // Redirect legacy delete calls to inactivate (soft-delete)
        return $this->toggleStatusSubject($id, 0);
    }

    public function getMasterCOs(int $curr_sub_id): array
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        try {
            $stmt = $this->conn->prepare("
                SELECT id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                FROM curriculum_course_outcomes 
                WHERE curr_sub_id = ? 
                ORDER BY co_number ASC
            ");
            $stmt->bind_param("i", $curr_sub_id);
            $stmt->execute();
            $res['data'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // If empty, auto-discover from sibling curriculum subjects sharing same subcode & reg_id
            if (empty($res['data'])) {
                $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
                if ($cSub && !empty($cSub['subcode']) && !empty($cSub['reg_id'])) {
                    $stmtSib = $this->conn->prepare("
                        SELECT co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent
                        FROM curriculum_course_outcomes co
                        JOIN curriculum_subjects cs ON co.curr_sub_id = cs.id
                        WHERE cs.subcode = ? AND cs.reg_id = ? AND co.curr_sub_id != ?
                        ORDER BY co.co_number ASC
                    ");
                    $stmtSib->bind_param("sii", $cSub['subcode'], $cSub['reg_id'], $curr_sub_id);
                    $stmtSib->execute();
                    $sibCos = $stmtSib->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtSib->close();

                    if (!empty($sibCos)) {
                        $ins = $this->conn->prepare("
                            INSERT INTO curriculum_course_outcomes (curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE co_description = VALUES(co_description), bloom_level = VALUES(bloom_level), target_threshold_percent = VALUES(target_threshold_percent)
                        ");
                        foreach ($sibCos as $sc) {
                            $ins->bind_param("iissd", $curr_sub_id, $sc['co_number'], $sc['co_description'], $sc['bloom_level'], $sc['target_threshold_percent']);
                            $ins->execute();
                        }
                        $ins->close();

                        // Re-fetch
                        $stmt = $this->conn->prepare("
                            SELECT id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                            FROM curriculum_course_outcomes 
                            WHERE curr_sub_id = ? 
                            ORDER BY co_number ASC
                        ");
                        $stmt->bind_param("i", $curr_sub_id);
                        $stmt->execute();
                        $res['data'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }
                }
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getMasterCOs Error: " . $e->getMessage());
        }
        return $res;
    }

    public function addOrUpdateMasterCO(int $curr_sub_id, int $co_number, string $co_description, string $bloom_level = 'L3-Apply', float $target_threshold = 60.0): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO curriculum_course_outcomes (curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");
            $stmt->bind_param("iissd", $curr_sub_id, $co_number, $co_description, $bloom_level, $target_threshold);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();

            // Synchronize across sibling curriculum subjects sharing same subcode & reg_id
            $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
            if ($cSub && !empty($cSub['subcode']) && !empty($cSub['reg_id'])) {
                $sibStmt = $this->conn->prepare("
                    SELECT id FROM curriculum_subjects 
                    WHERE subcode = ? AND reg_id = ? AND id != ?
                ");
                $sibStmt->bind_param("sii", $cSub['subcode'], $cSub['reg_id'], $curr_sub_id);
                $sibStmt->execute();
                $sibs = $sibStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $sibStmt->close();

                if (!empty($sibs)) {
                    $insSib = $this->conn->prepare("
                        INSERT INTO curriculum_course_outcomes (curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            co_description = VALUES(co_description),
                            bloom_level = VALUES(bloom_level),
                            target_threshold_percent = VALUES(target_threshold_percent)
                    ");
                    foreach ($sibs as $sb) {
                        $sibId = (int)$sb['id'];
                        $insSib->bind_param("iissd", $sibId, $co_number, $co_description, $bloom_level, $target_threshold);
                        $insSib->execute();
                    }
                    $insSib->close();
                }
            }

            $res['status'] = 1;
            $res['message'] = "Master Course Outcome CO{$co_number} saved successfully.";
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::addOrUpdateMasterCO Error: " . $e->getMessage());
        }
        return $res;
    }

    public function deleteMasterCO(int $curr_sub_id, int $co_id): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            // Delete mappings first
            $mStmt = $this->conn->prepare("DELETE FROM curriculum_co_po_mapping WHERE curr_co_id = ?");
            $mStmt->bind_param("i", $co_id);
            $mStmt->execute();
            $mStmt->close();

            // Delete master CO
            $stmt = $this->conn->prepare("DELETE FROM curriculum_course_outcomes WHERE id = ? AND curr_sub_id = ?");
            $stmt->bind_param("ii", $co_id, $curr_sub_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Master Course Outcome deleted successfully.";
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::deleteMasterCO Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Resolves POs and PSOs for a curriculum subject
     */
    public function getRelevantPoPsoForCurriculum(int $curr_sub_id): array
    {
        $res = [];
        try {
            $stmt = $this->conn->prepare("
                SELECT cs.reg_id, cs.spec_id, cs.prog_id, r.regulation, sp.dept_id
                FROM curriculum_subjects cs
                JOIN regulations r ON cs.reg_id = r.id
                LEFT JOIN specialization sp ON cs.spec_id = sp.id
                WHERE cs.id = ?
            ");
            $stmt->bind_param("i", $curr_sub_id);
            $stmt->execute();
            $cs = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$cs) return [];

            // 1. Direct match on reg_id and spec_id
            $q1 = "
                SELECT id, code, description, po_pso, target_score
                FROM po_pso
                WHERE (reg_id = ? OR regulation = ?) AND specid = ?
                GROUP BY code
                ORDER BY po_pso ASC, orderid ASC, id ASC
            ";
            $s1 = $this->conn->prepare($q1);
            $s1->bind_param("isi", $cs['reg_id'], $cs['regulation'], $cs['spec_id']);
            $s1->execute();
            $res = $s1->get_result()->fetch_all(MYSQLI_ASSOC);
            $s1->close();
            if (!empty($res)) return $res;

            // 2. Match via class spec_id from any existing offerings
            $q2 = "
                SELECT p.id, p.code, p.description, p.po_pso, p.target_score
                FROM po_pso p
                JOIN classes c ON p.specid = c.spec_id
                JOIN subjects s ON s.class_id = c.id
                WHERE s.curr_sub_id = ? AND (p.reg_id = ? OR p.regulation = ?)
                GROUP BY p.code
                ORDER BY p.po_pso ASC, p.orderid ASC, p.id ASC
            ";
            $s2 = $this->conn->prepare($q2);
            $s2->bind_param("iis", $curr_sub_id, $cs['reg_id'], $cs['regulation']);
            $s2->execute();
            $res = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
            $s2->close();
            if (!empty($res)) return $res;

            // 3. Match via dept_id
            if (!empty($cs['dept_id'])) {
                $q3 = "
                    SELECT id, code, description, po_pso, target_score
                    FROM po_pso
                    WHERE (reg_id = ? OR regulation = ?) AND specid = ?
                    GROUP BY code
                    ORDER BY po_pso ASC, orderid ASC, id ASC
                ";
                $s3 = $this->conn->prepare($q3);
                $s3->bind_param("isi", $cs['reg_id'], $cs['regulation'], $cs['dept_id']);
                $s3->execute();
                $res = $s3->get_result()->fetch_all(MYSQLI_ASSOC);
                $s3->close();
                if (!empty($res)) return $res;
            }

            // 4. Fallback to regulation-wide POs
            $q4 = "
                SELECT id, code, description, po_pso, target_score
                FROM po_pso
                WHERE (reg_id = ? OR regulation = ?)
                GROUP BY code
                ORDER BY po_pso ASC, orderid ASC, id ASC
            ";
            $s4 = $this->conn->prepare($q4);
            $s4->bind_param("is", $cs['reg_id'], $cs['regulation']);
            $s4->execute();
            $res = $s4->get_result()->fetch_all(MYSQLI_ASSOC);
            $s4->close();
        } catch (Exception $e) {
            $this->logs->errLog("CurriculumSubject::getRelevantPoPsoForCurriculum Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get Master Articulation Matrix for a curriculum subject
     */
    public function getMasterArticulationMatrix(int $curr_sub_id): array
    {
        $res = ['status' => 0, 'cos' => [], 'po_psos' => [], 'mappings' => []];
        try {
            $cosRes = $this->getMasterCOs($curr_sub_id);
            $res['cos'] = $cosRes['data'] ?? [];

            $res['po_psos'] = $this->getRelevantPoPsoForCurriculum($curr_sub_id);

            if (!empty($res['cos']) && !empty($res['po_psos'])) {
                $coIds = array_column($res['cos'], 'id');
                $ph = implode(',', array_fill(0, count($coIds), '?'));
                $types = str_repeat('i', count($coIds));

                $stmt = $this->conn->prepare("
                    SELECT curr_co_id, po_id, weightage
                    FROM curriculum_co_po_mapping
                    WHERE curr_co_id IN ($ph)
                ");
                $stmt->bind_param($types, ...$coIds);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $mappings = [];
                foreach ($rows as $r) {
                    $mappings[$r['curr_co_id'] . '-' . $r['po_id']] = (int)$r['weightage'];
                }
                $res['mappings'] = $mappings;

                // If mappings are empty, try auto-discovering from sibling curriculum subjects
                if (empty($res['mappings'])) {
                    $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
                    if ($cSub && !empty($cSub['subcode']) && !empty($cSub['reg_id'])) {
                        $sibMatrixStmt = $this->conn->prepare("
                            SELECT sco.co_number, p.code as po_code, m.weightage
                            FROM curriculum_co_po_mapping m
                            JOIN curriculum_course_outcomes sco ON m.curr_co_id = sco.id
                            JOIN curriculum_subjects scs ON sco.curr_sub_id = scs.id
                            JOIN po_pso p ON m.po_id = p.id
                            WHERE scs.subcode = ? AND scs.reg_id = ? AND scs.id != ?
                            ORDER BY sco.co_number ASC, p.id ASC
                        ");
                        $sibMatrixStmt->bind_param("sii", $cSub['subcode'], $cSub['reg_id'], $curr_sub_id);
                        $sibMatrixStmt->execute();
                        $sibMappings = $sibMatrixStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $sibMatrixStmt->close();

                        if (!empty($sibMappings)) {
                            $coNumMap = [];
                            foreach ($res['cos'] as $co) {
                                $coNumMap[(int)$co['co_number']] = (int)$co['id'];
                            }
                            $poCodeMap = [];
                            foreach ($res['po_psos'] as $p) {
                                $poCodeMap[trim($p['code'])] = (int)$p['id'];
                            }

                            $insMap = $this->conn->prepare("
                                INSERT INTO curriculum_co_po_mapping (curr_co_id, po_id, weightage)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
                            ");

                            foreach ($sibMappings as $sm) {
                                $cNum = (int)$sm['co_number'];
                                $pCode = trim($sm['po_code']);
                                $w = (int)$sm['weightage'];

                                if (isset($coNumMap[$cNum]) && isset($poCodeMap[$pCode])) {
                                    $localCoId = $coNumMap[$cNum];
                                    $localPoId = $poCodeMap[$pCode];
                                    $insMap->bind_param("iii", $localCoId, $localPoId, $w);
                                    $insMap->execute();
                                    $res['mappings'][$localCoId . '-' . $localPoId] = $w;
                                }
                            }
                            $insMap->close();
                        }
                    }
                }
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getMasterArticulationMatrix Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Save Master Articulation Matrix for a curriculum subject
     */
    public function saveMasterArticulationMatrix(int $curr_sub_id, array $mappings): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            $cosRes = $this->getMasterCOs($curr_sub_id);
            $cos = $cosRes['data'] ?? [];
            if (empty($cos)) {
                throw new Exception("Please define Course Outcomes before saving articulation matrix.");
            }
            $validCoIds = array_column($cos, 'id');

            $poPsos = $this->getRelevantPoPsoForCurriculum($curr_sub_id);
            if (empty($poPsos)) {
                throw new Exception("No POs/PSOs configured for this regulation.");
            }
            $validPoIds = array_column($poPsos, 'id');

            $savedCount = 0;
            $insStmt = $this->conn->prepare("
                INSERT INTO curriculum_co_po_mapping (curr_co_id, po_id, weightage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
            ");

            $delStmt = $this->conn->prepare("DELETE FROM curriculum_co_po_mapping WHERE curr_co_id = ? AND po_id = ?");

            foreach ($mappings as $coId => $poMappings) {
                $coId = (int)$coId;
                if (!in_array($coId, $validCoIds)) continue;

                if (is_array($poMappings)) {
                    foreach ($poMappings as $poId => $w) {
                        $poId = (int)$poId;
                        if (!in_array($poId, $validPoIds)) continue;

                        $val = (is_numeric($w) && in_array((int)$w, [1, 2, 3])) ? (int)$w : 0;
                        if ($val > 0) {
                            $insStmt->bind_param("iii", $coId, $poId, $val);
                            $insStmt->execute();
                            $savedCount++;
                        } else {
                            $delStmt->bind_param("ii", $coId, $poId);
                            $delStmt->execute();
                        }
                    }
                }
            }
            $insStmt->close();
            $delStmt->close();

            // Synchronize articulation matrix across sibling curriculum subjects sharing same subcode & reg_id
            $this->syncMasterMatrixToSiblingCurriculumSubjects($curr_sub_id);

            $res['status'] = 1;
            $res['saved_cells'] = $savedCount;
            $res['message'] = "Master Articulation Matrix saved successfully ({$savedCount} cells mapped).";
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::saveMasterArticulationMatrix Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Synchronizes Master Articulation Matrix across sibling curriculum subjects
     */
    public function syncMasterMatrixToSiblingCurriculumSubjects(int $curr_sub_id): void
    {
        try {
            $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
            if (!$cSub || empty($cSub['subcode']) || empty($cSub['reg_id'])) return;

            // 1. Get logical matrix from this curriculum subject
            $stmt = $this->conn->prepare("
                SELECT co.co_number, p.code as po_code, m.weightage
                FROM curriculum_course_outcomes co
                JOIN curriculum_co_po_mapping m ON m.curr_co_id = co.id
                JOIN po_pso p ON m.po_id = p.id
                WHERE co.curr_sub_id = ?
            ");
            $stmt->bind_param("i", $curr_sub_id);
            $stmt->execute();
            $mappings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (empty($mappings)) return;

            // 2. Find sibling curriculum subjects
            $sibStmt = $this->conn->prepare("
                SELECT id FROM curriculum_subjects
                WHERE subcode = ? AND reg_id = ? AND id != ?
            ");
            $sibStmt->bind_param("sii", $cSub['subcode'], $cSub['reg_id'], $curr_sub_id);
            $sibStmt->execute();
            $sibs = $sibStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $sibStmt->close();

            foreach ($sibs as $sb) {
                $sibId = (int)$sb['id'];
                $sibCos = $this->getMasterCOs($sibId)['data'] ?? [];
                $sibCoMap = [];
                foreach ($sibCos as $sc) {
                    $sibCoMap[(int)$sc['co_number']] = (int)$sc['id'];
                }

                $sibPos = $this->getRelevantPoPsoForCurriculum($sibId);
                $sibPoMap = [];
                foreach ($sibPos as $sp) {
                    $sibPoMap[trim($sp['code'])] = (int)$sp['id'];
                }

                $insStmt = $this->conn->prepare("
                    INSERT INTO curriculum_co_po_mapping (curr_co_id, po_id, weightage)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
                ");

                foreach ($mappings as $map) {
                    $coNum = (int)$map['co_number'];
                    $poCode = trim($map['po_code']);
                    $w = (int)$map['weightage'];

                    if (isset($sibCoMap[$coNum]) && isset($sibPoMap[$poCode])) {
                        $targetCoId = $sibCoMap[$coNum];
                        $targetPoId = $sibPoMap[$poCode];
                        $insStmt->bind_param("iii", $targetCoId, $targetPoId, $w);
                        $insStmt->execute();
                    }
                }
                $insStmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog("CurriculumSubject::syncMasterMatrixToSiblingCurriculumSubjects Error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve complete semester-by-semester Course Structure for a program branch and regulation.
     */
    public function getCourseStructureByBranch(int $progId, int $regId, int $specId): array
    {
        $res = [
            'status' => 0,
            'program' => null,
            'regulation' => null,
            'specialization' => null,
            'semesters' => [],
            'grand_totals' => [
                'total_courses' => 0,
                'total_credits' => 0.0,
                'total_lecture' => 0.0,
                'total_tutorial' => 0.0,
                'total_practical' => 0.0,
                'total_cie' => 0.0,
                'total_see' => 0.0,
                'total_marks' => 0.0
            ],
            'category_summary' => [],
            'error' => ''
        ];

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            // 1. Fetch metadata
            $progStmt = $this->conn->prepare("SELECT * FROM programs WHERE id = ?");
            $progStmt->bind_param("i", $progId);
            $progStmt->execute();
            $res['program'] = $progStmt->get_result()->fetch_assoc();
            $progStmt->close();

            $regStmt = $this->conn->prepare("SELECT * FROM regulations WHERE id = ?");
            $regStmt->bind_param("i", $regId);
            $regStmt->execute();
            $res['regulation'] = $regStmt->get_result()->fetch_assoc();
            $regStmt->close();

            $specStmt = $this->conn->prepare("SELECT * FROM specialization WHERE id = ?");
            $specStmt->bind_param("i", $specId);
            $specStmt->execute();
            $res['specialization'] = $specStmt->get_result()->fetch_assoc();
            $specStmt->close();

            if (empty($res['regulation']) || empty($res['specialization'])) {
                $res['error'] = 'Invalid regulation or specialization specified.';
                return $res;
            }

            // 2. Fetch all curriculum subjects for this branch
            $sql = "SELECT cs.*, 
                           COALESCE(rct.evaluation_scheme, 'THEORY') as evaluation_scheme
                    FROM curriculum_subjects cs
                    LEFT JOIN regulation_course_types rct ON (rct.reg_id = cs.reg_id AND rct.type_code COLLATE utf8mb4_unicode_ci = cs.sub_type COLLATE utf8mb4_unicode_ci)
                    WHERE cs.prog_id = ? AND cs.reg_id = ? AND cs.spec_id = ? AND cs.status = 1
                    ORDER BY cs.subject_sno ASC, cs.subcode ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iii", $progId, $regId, $specId);
            $stmt->execute();
            $allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Canonical chronological semester ordering
            $semOrderUG = [
                'I Yr - I Sem' => 1,
                'I Yr - II Sem' => 2,
                'II Yr - I Sem' => 3,
                'II Yr - II Sem' => 4,
                'III Yr - I Sem' => 5,
                'III Yr - II Sem' => 6,
                'IV Yr - I Sem' => 7,
                'IV Yr - II Sem' => 8
            ];
            $semOrderPG = [
                'I Sem' => 1,
                'II Sem' => 2,
                'III Sem' => 3,
                'IV Sem' => 4,
                'I Yr - I Sem' => 1,
                'I Yr - II Sem' => 2,
                'II Yr - I Sem' => 3,
                'II Yr - II Sem' => 4
            ];
            $isUG = (($res['program']['program_level'] ?? 'UG') === 'UG');
            $semOrderMap = $isUG ? $semOrderUG : $semOrderPG;

            // Group subjects by yearsem
            $grouped = [];
            foreach ($allSubjects as $sub) {
                $sem = trim($sub['yearsem']);
                if (!isset($grouped[$sem])) {
                    $order = $semOrderMap[$sem] ?? 99;
                    $grouped[$sem] = [
                        'semester_name' => $sem,
                        'order' => $order,
                        'subjects' => [],
                        'totals' => [
                            'courses_count' => 0,
                            'lecture_hours' => 0.0,
                            'tutorial_hours' => 0.0,
                            'practical_hours' => 0.0,
                            'credits' => 0.0,
                            'cie_marks' => 0.0,
                            'see_marks' => 0.0,
                            'total_marks' => 0.0
                        ]
                    ];
                }

                $grouped[$sem]['subjects'][] = $sub;
                $grouped[$sem]['totals']['courses_count']++;
                $grouped[$sem]['totals']['lecture_hours'] += (float)$sub['lecture_hours'];
                $grouped[$sem]['totals']['tutorial_hours'] += (float)$sub['tutorial_hours'];
                $grouped[$sem]['totals']['practical_hours'] += (float)($sub['practical_hours'] ?: $sub['pr_hours']);
                $grouped[$sem]['totals']['credits'] += (float)$sub['credits'];
                $grouped[$sem]['totals']['cie_marks'] += (float)$sub['cie_max_marks'];
                $grouped[$sem]['totals']['see_marks'] += (float)$sub['see_max_marks'];
                $grouped[$sem]['totals']['total_marks'] += (float)$sub['total_marks'];

                // Grand totals
                $res['grand_totals']['total_courses']++;
                $res['grand_totals']['total_credits'] += (float)$sub['credits'];
                $res['grand_totals']['total_lecture'] += (float)$sub['lecture_hours'];
                $res['grand_totals']['total_tutorial'] += (float)$sub['tutorial_hours'];
                $res['grand_totals']['total_practical'] += (float)($sub['practical_hours'] ?: $sub['pr_hours']);
                $res['grand_totals']['total_cie'] += (float)$sub['cie_max_marks'];
                $res['grand_totals']['total_see'] += (float)$sub['see_max_marks'];
                $res['grand_totals']['total_marks'] += (float)$sub['total_marks'];

                // Category Summary
                $cat = strtoupper(trim($sub['course_category'] ?: 'OTHER'));
                if (!isset($res['category_summary'][$cat])) {
                    $res['category_summary'][$cat] = [
                        'category_code' => $cat,
                        'courses_count' => 0,
                        'total_credits' => 0.0
                    ];
                }
                $res['category_summary'][$cat]['courses_count']++;
                $res['category_summary'][$cat]['total_credits'] += (float)$sub['credits'];
            }

            // Sort semesters by order
            uasort($grouped, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });

            $res['semesters'] = $grouped;
            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to load course structure: ' . $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getCourseStructureByBranch - " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Compute statutory category credit compliance comparing allocated credits vs SuperAdmin limits.
     */
    public function getCategoryCompliance(int $progId, int $regId, int $specId): array
    {
        $res = [
            'status' => 0,
            'categories' => [],
            'overall' => [
                'total_degree_credits' => 0.0,
                'total_allocated_credits' => 0.0,
                'difference' => 0.0,
                'is_compliant' => false
            ],
            'error' => ''
        ];

        try {
            // 1. Get regulation details
            $regStmt = $this->conn->prepare("SELECT total_degree_credits FROM regulations WHERE id = ?");
            $regStmt->bind_param("i", $regId);
            $regStmt->execute();
            $regRow = $regStmt->get_result()->fetch_assoc();
            $regStmt->close();
            $totalDegreeCredits = (float)($regRow['total_degree_credits'] ?? 160.0);
            $res['overall']['total_degree_credits'] = $totalDegreeCredits;

            // 2. Statutory categories defined by SuperAdmin
            $stmtCat = $this->conn->prepare("
                SELECT id, category_code, category_name, min_allocation_pct, max_allocation_pct, target_credits, description
                FROM regulation_course_categories
                WHERE reg_id = ?
                ORDER BY category_code ASC
            ");
            $stmtCat->bind_param("i", $regId);
            $stmtCat->execute();
            $targetCats = $stmtCat->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtCat->close();

            // 3. Actual allocated credits in curriculum_subjects
            $stmtAct = $this->conn->prepare("
                SELECT UPPER(TRIM(course_category)) as category_code, 
                       COUNT(*) as course_count, 
                       SUM(credits) as allocated_credits 
                FROM curriculum_subjects 
                WHERE prog_id = ? AND reg_id = ? AND spec_id = ? AND status = 1 
                GROUP BY UPPER(TRIM(course_category))
            ");
            $stmtAct->bind_param("iii", $progId, $regId, $specId);
            $stmtAct->execute();
            $actRows = $stmtAct->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtAct->close();

            $allocatedMap = [];
            $totalAllocated = 0.0;
            foreach ($actRows as $ar) {
                $cCode = $ar['category_code'];
                $allocatedMap[$cCode] = [
                    'course_count' => (int)$ar['course_count'],
                    'credits' => (float)$ar['allocated_credits']
                ];
                $totalAllocated += (float)$ar['allocated_credits'];
            }
            $res['overall']['total_allocated_credits'] = $totalAllocated;
            $res['overall']['difference'] = round($totalAllocated - $totalDegreeCredits, 2);
            $res['overall']['is_compliant'] = (abs($totalAllocated - $totalDegreeCredits) < 0.05);

            // 4. Merge target and actual
            $merged = [];
            $seenCodes = [];
            foreach ($targetCats as $tc) {
                $code = strtoupper(trim($tc['category_code']));
                $seenCodes[$code] = true;
                $targetCr = (float)$tc['target_credits'];
                $allocCr = (float)($allocatedMap[$code]['credits'] ?? 0.0);
                $diff = round($allocCr - $targetCr, 2);
                $allocPct = ($totalDegreeCredits > 0) ? round(($allocCr / $totalDegreeCredits) * 100, 1) : 0.0;
                
                $status = 'COMPLIANT';
                if ($diff < -0.05) {
                    $status = 'DEFICIT';
                } elseif ($diff > 0.05) {
                    $status = 'EXCESS';
                }

                $merged[] = [
                    'category_code' => $code,
                    'category_name' => $tc['category_name'],
                    'min_pct' => (float)$tc['min_allocation_pct'],
                    'max_pct' => (float)$tc['max_allocation_pct'],
                    'target_credits' => $targetCr,
                    'allocated_credits' => $allocCr,
                    'course_count' => (int)($allocatedMap[$code]['course_count'] ?? 0),
                    'difference' => $diff,
                    'allocated_pct' => $allocPct,
                    'status' => $status
                ];
            }

            // Check if any actual categories were not in regulation categories
            foreach ($allocatedMap as $code => $act) {
                if (!isset($seenCodes[$code]) && !empty($code)) {
                    $allocCr = $act['credits'];
                    $allocPct = ($totalDegreeCredits > 0) ? round(($allocCr / $totalDegreeCredits) * 100, 1) : 0.0;
                    $merged[] = [
                        'category_code' => $code,
                        'category_name' => 'Uncategorized / Other',
                        'min_pct' => 0.0,
                        'max_pct' => 0.0,
                        'target_credits' => 0.0,
                        'allocated_credits' => $allocCr,
                        'course_count' => $act['course_count'],
                        'difference' => $allocCr,
                        'allocated_pct' => $allocPct,
                        'status' => 'UNLISTED'
                    ];
                }
            }

            $res['categories'] = $merged;
            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to calculate compliance: ' . $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getCategoryCompliance - " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Group Elective Courses by tracks, verticals, or specializations.
     */
    public function getElectiveTracks(int $progId, int $regId, int $specId): array
    {
        $res = ['status' => 0, 'tracks' => [], 'error' => ''];
        try {
            $sql = "SELECT cs.* 
                    FROM curriculum_subjects cs
                    WHERE cs.prog_id = ? AND cs.reg_id = ? AND cs.spec_id = ? AND cs.status = 1
                      AND (cs.course_category IN ('PE', 'OE', 'SEC', 'HONORS', 'MINOR') OR (cs.elective_track IS NOT NULL AND cs.elective_track != ''))
                    ORDER BY cs.course_category ASC, cs.elective_track ASC, cs.yearsem ASC, cs.subcode ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iii", $progId, $regId, $specId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $grouped = [];
            foreach ($rows as $r) {
                $category = strtoupper(trim($r['course_category'] ?: 'ELECTIVE'));
                $track = trim($r['elective_track'] ?: 'General Track');
                $key = $category . ' - ' . $track;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'category' => $category,
                        'track_name' => $track,
                        'subjects' => []
                    ];
                }
                $grouped[$key]['subjects'][] = $r;
            }
            $res['tracks'] = array_values($grouped);
            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch elective tracks: ' . $e->getMessage();
        }
        return $res;
    }
}

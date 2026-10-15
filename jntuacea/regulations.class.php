<?php
require_once("user.class.php");

class Regulations extends User
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAllRegulations()
    {
        $res = ['status' => 0, 'data' => []];

        try {
            $stmt = $this->conn->prepare("
                SELECT r.id, r.regulation, r.prog_id, r.start_year, r.is_active, r.updatedat,
                       r.normal_duration_years, r.max_duration_years, r.gap_year_extension_years,
                       r.total_semesters, r.total_degree_credits, r.lateral_entry_credits,
                       r.honors_credits, r.minor_credits, r.has_lateral_entry, r.has_honors,
                       r.has_minors, r.has_gap_year, r.has_internal_improvement,
                       r.effective_admitted_batch, r.les_effective_batch,
                       p.program_code, p.prog_shortname, p.prog_fullname, p.program_level
                FROM regulations r 
                JOIN programs p ON r.prog_id = p.id 
                ORDER BY p.program_level DESC, p.prog_shortname ASC, r.regulation DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllRegulations: " . $e->getMessage());
            $res['error'] = 'Failed to fetch regulations.';
        }

        return $res;
    }

    public function addOrUpdateRegulation(array $data)
    {
        $regulation = strtoupper(trim($data['regulation'] ?? ''));
        $prog_id = (int) ($data['prog_id'] ?? 0);
        $start_year = !empty($data['start_year']) ? (int)$data['start_year'] : null;
        $normal_duration_years = isset($data['normal_duration_years']) ? (int)$data['normal_duration_years'] : 4;
        $max_duration_years = isset($data['max_duration_years']) ? (int)$data['max_duration_years'] : ($normal_duration_years * 2);
        $gap_year_extension_years = isset($data['gap_year_extension_years']) ? (int)$data['gap_year_extension_years'] : 0;
        $total_semesters = isset($data['total_semesters']) ? (int)$data['total_semesters'] : ($normal_duration_years * 2);
        $total_degree_credits = isset($data['total_degree_credits']) ? (float)$data['total_degree_credits'] : 160.0;
        $lateral_entry_credits = isset($data['lateral_entry_credits']) ? (float)$data['lateral_entry_credits'] : 0.0;
        $honors_credits = isset($data['honors_credits']) ? (float)$data['honors_credits'] : 0.0;
        $minor_credits = isset($data['minor_credits']) ? (float)$data['minor_credits'] : 0.0;
        $has_lateral_entry = !empty($data['has_lateral_entry']) ? 1 : 0;
        $has_honors = !empty($data['has_honors']) ? 1 : 0;
        $has_minors = !empty($data['has_minors']) ? 1 : 0;
        $has_gap_year = !empty($data['has_gap_year']) ? 1 : 0;
        $has_internal_improvement = !empty($data['has_internal_improvement']) ? 1 : 0;
        $effective_admitted_batch = trim($data['effective_admitted_batch'] ?? '');
        $les_effective_batch = trim($data['les_effective_batch'] ?? '');

        if (!empty($data['id'])) {
            $stmt = $this->conn->prepare("
                UPDATE regulations 
                SET regulation = ?, prog_id = ?, start_year = ?, normal_duration_years = ?, max_duration_years = ?,
                    gap_year_extension_years = ?, total_semesters = ?, total_degree_credits = ?, lateral_entry_credits = ?,
                    honors_credits = ?, minor_credits = ?, has_lateral_entry = ?, has_honors = ?, has_minors = ?,
                    has_gap_year = ?, has_internal_improvement = ?, effective_admitted_batch = ?, les_effective_batch = ?
                WHERE id = ?
            ");
            $id = (int)$data['id'];
            $stmt->bind_param(
                "siiiiidddddiiiiissi",
                $regulation, $prog_id, $start_year, $normal_duration_years, $max_duration_years,
                $gap_year_extension_years, $total_semesters, $total_degree_credits, $lateral_entry_credits,
                $honors_credits, $minor_credits, $has_lateral_entry, $has_honors, $has_minors,
                $has_gap_year, $has_internal_improvement, $effective_admitted_batch, $les_effective_batch,
                $id
            );
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO regulations 
                (regulation, prog_id, start_year, normal_duration_years, max_duration_years,
                 gap_year_extension_years, total_semesters, total_degree_credits, lateral_entry_credits,
                 honors_credits, minor_credits, has_lateral_entry, has_honors, has_minors,
                 has_gap_year, has_internal_improvement, effective_admitted_batch, les_effective_batch) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siiiiidddddiiiiiss",
                $regulation, $prog_id, $start_year, $normal_duration_years, $max_duration_years,
                $gap_year_extension_years, $total_semesters, $total_degree_credits, $lateral_entry_credits,
                $honors_credits, $minor_credits, $has_lateral_entry, $has_honors, $has_minors,
                $has_gap_year, $has_internal_improvement, $effective_admitted_batch, $les_effective_batch
            );
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating regulation. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Regulation '$regulation' added/updated.");
            $action = !empty($data['id']) ? "UPDATE_REGULATION" : "ADD_REGULATION";
            $regId = !empty($data['id']) ? (string)$data['id'] : (string)$this->conn->insert_id;
            $this->dbActivityLog($_SESSION['userid'] ?? 0, $action, "Regulation '$regulation' (Prog ID: $prog_id) saved", "superadmin", "REGULATION", $regId);

            // If newly added regulation, seed baseline course categories and course types
            if (empty($data['id']) && (int)$regId > 0) {
                $this->seedDefaultCategoriesAndTypes((int)$regId);
            }

            return ['status' => 1, 'message' => 'Regulation saved successfully.', 'reg_id' => (int)$regId];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateRegulation: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    private function seedDefaultCategoriesAndTypes(int $regId): void
    {
        try {
            // Determine program level (UG vs PG)
            $progLevel = 'UG';
            $pStmt = $this->conn->prepare("SELECT p.program_level FROM regulations r JOIN programs p ON r.prog_id = p.id WHERE r.id = ?");
            if ($pStmt) {
                $pStmt->bind_param("i", $regId);
                $pStmt->execute();
                $pRes = $pStmt->get_result()->fetch_assoc();
                if (!empty($pRes['program_level'])) {
                    $progLevel = strtoupper(trim($pRes['program_level']));
                }
                $pStmt->close();
            }

            if ($progLevel === 'PG') {
                // Baseline Categories for PG (M.Tech / MCA)
                $sqlCat = "INSERT IGNORE INTO `regulation_course_categories` 
                           (`reg_id`, `category_code`, `category_name`, `min_allocation_pct`, `max_allocation_pct`, `target_credits`, `description`)
                           VALUES 
                           (?, 'PC', 'Foundational & Professional Core', 30.00, 35.00, 24.0, 'Core engineering subjects related to parent specialization'),
                           (?, 'PE', 'Professional Electives', 18.00, 22.00, 15.0, 'Specialization elective subjects'),
                           (?, 'OE', 'Open Elective', 3.50, 5.00, 3.0, 'Inter-disciplinary elective subjects outside parent discipline'),
                           (?, 'MC', 'Mandatory Courses (Credit)', 4.00, 6.00, 4.0, 'Quantum Technology, Research Methodology & IPR'),
                           (?, 'SE', 'Skill Enhancement Courses', 4.50, 6.00, 4.0, 'Format (2-0-0) or (0-1-2), 2 credits each in Sem I & II'),
                           (?, 'CV', 'Comprehensive Viva', 2.00, 3.00, 2.0, 'Comprehensive Viva after II semester for 100 marks'),
                           (?, 'IN', 'Short Term Industry Internship', 3.50, 5.00, 3.0, '6-8 weeks summer internship at end of first year'),
                           (?, 'DS', 'Dissertation / Project Work', 25.00, 30.00, 20.0, '2-semester project (Sem III & IV) evaluated for 300 marks'),
                           (?, 'AC', 'Audit Courses (Non-Credit)', 0.00, 0.00, 0.0, 'Mandatory audit courses in Sem I & II (Zero credits)')";
                $stmtC = $this->conn->prepare($sqlCat);
                if ($stmtC) {
                    $stmtC->bind_param("iiiiiiiii", $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId);
                    $stmtC->execute();
                    $stmtC->close();
                }

                // Baseline Course Types for PG (40 CIE / 60 SEE)
                $sqlType = "INSERT IGNORE INTO `regulation_course_types` 
                            (`reg_id`, `type_code`, `type_name`, `evaluation_scheme`, `cie_max_marks`, `see_max_marks`, `total_marks`, `has_see`, `is_credit_course`, `description`)
                            VALUES 
                            (?, 'Theory', 'Theory Course', 'THEORY', 40.00, 60.00, 100.00, 1, 1, '40 CIA (30 mid + 10 continuous) + 60 SEE (5 either/or x 12)'),
                            (?, 'Lab', 'Laboratory Course', 'LAB', 40.00, 60.00, 100.00, 1, 1, 'Day-to-day 10 + Record 10 + Test 20 = 40 CIA; Proc/Exp/Results/Viva = 60 SEE'),
                            (?, 'Skill Enhancement', 'Skill Enhancement Course', 'LAB', 40.00, 60.00, 100.00, 1, 1, '2 credits; L-T-P (2-0-0) theory mode or (0-1-2) practical mode'),
                            (?, 'Mandatory Audit Course', 'Audit Course (Non-Credit)', 'AUDIT_NON_CREDIT', 40.00, 0.00, 40.00, 0, 0, '40 internal marks only (no SEE); Min pass 50% (20 marks)'),
                            (?, 'Industry Internship', 'Short Term Industry Internship', 'PROJECT', 50.00, 50.00, 100.00, 1, 1, '50 Mentor report + 50 Oral presentation before committee'),
                            (?, 'Comprehensive Viva', 'Comprehensive Viva Voce', 'PROJECT', 0.00, 100.00, 100.00, 1, 1, 'Conducted after II semester for 100 marks by PRC + External Expert'),
                            (?, 'Project Review - II', 'Project Review - II (Sem III)', 'PROJECT', 100.00, 0.00, 100.00, 0, 1, '50 PRC + 50 Supervisor = 100 internal marks; Min pass 50%'),
                            (?, 'Project Review - III', 'Project Review - III (Sem IV)', 'PROJECT', 100.00, 0.00, 100.00, 0, 1, '50 PRC + 50 Supervisor = 100 internal marks; Min pass 50%'),
                            (?, 'Dissertation Viva-Voce', 'Dissertation Viva-Voce (Sem IV)', 'PROJECT', 0.00, 100.00, 100.00, 1, 1, '100 external marks evaluated by external examiner board')";
                $stmtT = $this->conn->prepare($sqlType);
                if ($stmtT) {
                    $stmtT->bind_param("iiiiiiiii", $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId);
                    $stmtT->execute();
                    $stmtT->close();
                }
            } else {
                // Baseline Categories for UG (B.Tech R23 standard)
                $sqlCat = "INSERT IGNORE INTO `regulation_course_categories` 
                           (`reg_id`, `category_code`, `category_name`, `min_allocation_pct`, `max_allocation_pct`, `target_credits`, `description`)
                           VALUES 
                           (?, 'HM', 'Humanities and Social Science including Management', 8.00, 9.00, 13.0, 'Humanities, Social Sciences & Management courses'),
                           (?, 'BS', 'Basic Sciences', 12.00, 16.00, 20.0, 'Mathematics, Physics, Chemistry courses'),
                           (?, 'ES', 'Engineering Sciences', 10.00, 18.00, 23.5, 'Fundamental engineering & workshop courses'),
                           (?, 'PC', 'Professional Core', 30.00, 36.00, 54.5, 'Parent branch engineering core subjects'),
                           (?, 'PE', 'Professional Electives', 9.00, 11.00, 15.0, '5 Professional Elective courses'),
                           (?, 'OE', 'Open Electives', 7.00, 8.50, 12.0, '4 Open Electives (Minor verticals)'),
                           (?, 'SEC', 'Skill Enhancement Courses', 3.50, 5.00, 6.0, '5 Skill-oriented courses during III-VII sem'),
                           (?, 'PR', 'Internships & Project Work', 8.00, 11.00, 16.0, 'Summer internships (CSP & Industry) + Final sem project'),
                           (?, 'QT', 'Quantum Technologies (Compulsory)', 1.50, 2.00, 3.0, '3-credit compulsory course for all branches'),
                           (?, 'MC', 'Mandatory Courses', 0.00, 0.00, 0.0, 'Environmental Sciences, Constitution, Technical Paper/IPR (Non-credit)')";
                $stmtC = $this->conn->prepare($sqlCat);
                if ($stmtC) {
                    $stmtC->bind_param("iiiiiiiiii", $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId);
                    $stmtC->execute();
                    $stmtC->close();
                }

                // Baseline Course Types for UG (30 CIE / 70 SEE)
                $sqlType = "INSERT IGNORE INTO `regulation_course_types` 
                            (`reg_id`, `type_code`, `type_name`, `evaluation_scheme`, `cie_max_marks`, `see_max_marks`, `total_marks`, `has_see`, `is_credit_course`, `description`)
                            VALUES 
                            (?, 'Theory', 'Theory Course', 'THEORY', 30.00, 70.00, 100.00, 1, 1, 'Two mid exams (15 subj + 10 obj + 5 assign) + SEE 70 marks'),
                            (?, 'Lab', 'Laboratory Course', 'LAB', 30.00, 70.00, 100.00, 1, 1, 'Day-to-day 15 + Test 15 = 30 CIA; Procedure/Exp/Viva = 70 SEE'),
                            (?, 'Integrated', 'Theory Cum Lab Course', 'INTEGRATED', 30.00, 70.00, 100.00, 1, 1, 'Integrated theory and practical components evaluated separately'),
                            (?, 'Drawing', 'Engineering Graphics / Drawing', 'THEORY', 30.00, 70.00, 100.00, 1, 1, 'Day-to-day 15 + Mid 15 = 30 CIA; SEE 70 marks (5 either/or x 14)'),
                            (?, 'Skill Oriented Course', 'Skill Oriented Course', 'LAB', 30.00, 70.00, 100.00, 1, 1, 'Day-to-day 30 CIA + Lab-pattern SEE 70 marks'),
                            (?, 'Mandatory Course', 'Mandatory Course (Non-Credit)', 'AUDIT_NON_CREDIT', 30.00, 0.00, 30.00, 0, 0, '30 mid marks only (no external exam); Min pass 40% (12 marks)'),
                            (?, 'Summer Internship', 'Summer Internship (CSP / Industry)', 'PROJECT', 0.00, 50.00, 50.00, 1, 1, '50 external marks only (Oral presentation 50% + Report 50%)'),
                            (?, 'Project Work', 'Project Work / Dissertation', 'PROJECT', 60.00, 140.00, 200.00, 1, 1, 'Internal 60 (Supervisor 30 + PRC 30) + External Viva-Voce 140'),
                            (?, 'Final Year Internship', 'Full Semester Industry Internship', 'PROJECT', 0.00, 100.00, 100.00, 1, 1, 'Final-year internship 4 credits, 100 marks'),
                            (?, 'Open Elective (Online)', 'Wadhwani Online Open Elective', 'THEORY', 30.00, 70.00, 100.00, 1, 1, '30 online platform assessment + 70 SEE'),
                            (?, 'Honors / Minors', 'Honors / Minors Course', 'THEORY', 30.00, 70.00, 100.00, 1, 1, 'Additional degree courses with regular evaluation scheme'),
                            (?, 'Audit Workshop', 'Branch Specific Workshop', 'AUDIT_NON_CREDIT', 0.00, 0.00, 0.00, 0, 0, 'One-week workshop in III-II with 0 credits')";
                $stmtT = $this->conn->prepare($sqlType);
                if ($stmtT) {
                    $stmtT->bind_param("iiiiiiiiiiii", $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId, $regId);
                    $stmtT->execute();
                    $stmtT->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog("seedDefaultCategoriesAndTypes exception for regId $regId: " . $e->getMessage());
        }
    }

    public function cloneRegulation(int $sourceRegId, int $targetRegId): array
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        if ($sourceRegId <= 0 || $targetRegId <= 0 || $sourceRegId === $targetRegId) {
            $res['error'] = 'Invalid source or target regulation ID.';
            return $res;
        }

        try {
            $this->conn->begin_transaction();

            // 1. Get Target Regulation Code
            $targetReg = $this->getRegulationById($targetRegId)['data'] ?? null;
            if (!$targetReg) {
                throw new Exception("Target regulation not found.");
            }
            $targetCode = $targetReg['regulation'];

            // 2. Clone Categories
            $catStmt = $this->conn->prepare("
                INSERT INTO `regulation_course_categories` 
                (`reg_id`, `category_code`, `category_name`, `min_allocation_pct`, `max_allocation_pct`, `target_credits`, `description`)
                SELECT ?, category_code, category_name, min_allocation_pct, max_allocation_pct, target_credits, description
                FROM `regulation_course_categories`
                WHERE reg_id = ?
                ON DUPLICATE KEY UPDATE 
                    category_name = VALUES(category_name),
                    min_allocation_pct = VALUES(min_allocation_pct),
                    max_allocation_pct = VALUES(max_allocation_pct),
                    target_credits = VALUES(target_credits),
                    description = VALUES(description)
            ");
            $catStmt->bind_param("ii", $targetRegId, $sourceRegId);
            $catStmt->execute();
            $catStmt->close();

            // 3. Clone Course Types
            $typeStmt = $this->conn->prepare("
                INSERT INTO `regulation_course_types` 
                (`reg_id`, `type_code`, `type_name`, `evaluation_scheme`, `cie_max_marks`, `see_max_marks`, `total_marks`, `has_see`, `is_credit_course`, `description`)
                SELECT ?, type_code, type_name, evaluation_scheme, cie_max_marks, see_max_marks, total_marks, has_see, is_credit_course, description
                FROM `regulation_course_types`
                WHERE reg_id = ?
                ON DUPLICATE KEY UPDATE 
                    type_name = VALUES(type_name),
                    evaluation_scheme = VALUES(evaluation_scheme),
                    cie_max_marks = VALUES(cie_max_marks),
                    see_max_marks = VALUES(see_max_marks),
                    total_marks = VALUES(total_marks),
                    has_see = VALUES(has_see),
                    is_credit_course = VALUES(is_credit_course),
                    description = VALUES(description)
            ");
            $typeStmt->bind_param("ii", $targetRegId, $sourceRegId);
            $typeStmt->execute();
            $typeStmt->close();

            // 4. Clone Academic Settings
            $settStmt = $this->conn->prepare("
                INSERT INTO `academic_settings`
                (`reg_id`, `regulation_code`, `category`, `setting_key`, `setting_value`, `data_type`, `description`, `is_editable`, `updated_by`)
                SELECT ?, ?, category, setting_key, setting_value, data_type, description, is_editable, ?
                FROM `academic_settings`
                WHERE reg_id = ?
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    description = VALUES(description)
            ");
            $userId = (int)($_SESSION['userid'] ?? 1);
            $settStmt->bind_param("isii", $targetRegId, $targetCode, $userId, $sourceRegId);
            $settStmt->execute();
            $settStmt->close();

            $this->conn->commit();
            $res['status'] = 1;
            $res['message'] = "Regulation parameters, course categories, and types cloned successfully.";
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "CLONE_REGULATION", "Cloned regulation ID $sourceRegId into ID $targetRegId", "superadmin", "REGULATION", (string)$targetRegId);
        } catch (Exception $e) {
            $this->conn->rollback();
            $res['error'] = 'Failed to clone regulation: ' . $e->getMessage();
            $this->logs->errLog("cloneRegulation Error: " . $e->getMessage());
        }

        return $res;
    }

    public function deleteRegulation(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM regulations WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete regulation. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Regulation ID $id deleted.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_REGULATION", "Deleted regulation ID $id", "superadmin", "REGULATION", (string)$id);
            return ['status' => 1, 'message' => 'Regulation deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteRegulation: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getRegulationById(int $id)
    {
        $res = ['status' => 0, 'data' => null, 'error' => ''];
        try {
            $stmt = $this->conn->prepare("
                SELECT r.id, r.regulation, r.prog_id, r.start_year, r.is_active, r.updatedat,
                       r.normal_duration_years, r.max_duration_years, r.gap_year_extension_years,
                       r.total_semesters, r.total_degree_credits, r.lateral_entry_credits,
                       r.honors_credits, r.minor_credits, r.has_lateral_entry, r.has_honors,
                       r.has_minors, r.has_gap_year, r.has_internal_improvement,
                       r.effective_admitted_batch, r.les_effective_batch,
                       p.program_code, p.prog_shortname, p.prog_fullname, p.program_level
                FROM regulations r 
                JOIN programs p ON r.prog_id = p.id 
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data'] = $row;
                $res['status'] = 1;
            } else {
                $res['error'] = 'Regulation not found.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getRegulationById: " . $e->getMessage());
            $res['error'] = 'Failed to fetch regulation details.';
        }
        return $res;
    }

    // =========================================================================
    // Dynamic Course Categories Management
    // =========================================================================

    public function getCourseCategoriesByRegId(int $regId): array
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        try {
            $stmt = $this->conn->prepare("SELECT id, reg_id, category_code, category_name, min_allocation_pct, max_allocation_pct, target_credits, description 
                                          FROM regulation_course_categories 
                                          WHERE reg_id = ? 
                                          ORDER BY category_code ASC");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getCourseCategoriesByRegId: " . $e->getMessage());
            $res['error'] = 'Failed to fetch course categories.';
        }
        return $res;
    }

    public function saveCourseCategory(array $data): array
    {
        $regId = (int)($data['reg_id'] ?? 0);
        $catCode = strtoupper(trim($data['category_code'] ?? ''));
        $catName = trim($data['category_name'] ?? '');
        $minPct = (float)($data['min_allocation_pct'] ?? 0.0);
        $maxPct = (float)($data['max_allocation_pct'] ?? 0.0);
        $targetCredits = (float)($data['target_credits'] ?? 0.0);
        $descr = trim($data['description'] ?? '');
        $id = (int)($data['id'] ?? 0);

        if ($regId <= 0 || empty($catCode) || empty($catName)) {
            return ['status' => 0, 'error' => 'Regulation, Category Code, and Category Name are required.'];
        }

        if ($minPct < 0 || $maxPct < 0 || ($maxPct > 0 && $minPct > $maxPct)) {
            return ['status' => 0, 'error' => 'Invalid allocation percentage range (Min cannot exceed Max).'];
        }

        try {
            if ($id > 0) {
                $stmt = $this->conn->prepare("UPDATE regulation_course_categories 
                                              SET category_code = ?, category_name = ?, min_allocation_pct = ?, max_allocation_pct = ?, target_credits = ?, description = ? 
                                              WHERE id = ? AND reg_id = ?");
                $stmt->bind_param("ssdddsii", $catCode, $catName, $minPct, $maxPct, $targetCredits, $descr, $id, $regId);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO regulation_course_categories 
                                              (reg_id, category_code, category_name, min_allocation_pct, max_allocation_pct, target_credits, description) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issddds", $regId, $catCode, $catName, $minPct, $maxPct, $targetCredits, $descr);
            }

            if (!$stmt->execute()) {
                if ($this->conn->errno === 1062) {
                    return ['status' => 0, 'error' => "Category code '$catCode' already exists for this regulation."];
                }
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $savedId = ($id > 0) ? $id : $this->conn->insert_id;
            $stmt->close();

            $this->logs->activityLog("Course Category '$catCode' saved for Regulation ID $regId.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "SAVE_REG_COURSE_CATEGORY", "Category $catCode ($catName) saved for reg $regId", "superadmin", "REGULATION_CATEGORY", (string)$savedId);

            return ['status' => 1, 'message' => "Category '$catCode' saved successfully."];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in saveCourseCategory: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Failed to save course category: ' . $e->getMessage()];
        }
    }

    public function deleteCourseCategory(int $id, int $regId): array
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM regulation_course_categories WHERE id = ? AND reg_id = ?");
            $stmt->bind_param("ii", $id, $regId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            $this->logs->activityLog("Course Category ID $id deleted for Regulation ID $regId.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_REG_COURSE_CATEGORY", "Category ID $id deleted for reg $regId", "superadmin", "REGULATION_CATEGORY", (string)$id);
            return ['status' => 1, 'message' => 'Course category deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in deleteCourseCategory: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Failed to delete category: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // Dynamic Course Types Management
    // =========================================================================

    public function getCourseTypesByRegId(int $regId): array
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        try {
            $stmt = $this->conn->prepare("SELECT id, reg_id, type_code, type_name, evaluation_scheme, cie_max_marks, see_max_marks, total_marks, has_see, is_credit_course, description 
                                          FROM regulation_course_types 
                                          WHERE reg_id = ? 
                                          ORDER BY type_code ASC");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getCourseTypesByRegId: " . $e->getMessage());
            $res['error'] = 'Failed to fetch course types.';
        }
        return $res;
    }

    public function saveCourseType(array $data): array
    {
        $regId = (int)($data['reg_id'] ?? 0);
        $typeCode = trim($data['type_code'] ?? '');
        $typeName = trim($data['type_name'] ?? '');
        $scheme = strtoupper(trim($data['evaluation_scheme'] ?? 'THEORY'));
        $cieMax = (float)($data['cie_max_marks'] ?? 30.0);
        $seeMax = (float)($data['see_max_marks'] ?? 70.0);
        $totalMarks = (float)($data['total_marks'] ?? ($cieMax + $seeMax));
        $hasSee = !empty($data['has_see']) ? 1 : 0;
        $isCreditCourse = !empty($data['is_credit_course']) ? 1 : 0;
        $descr = trim($data['description'] ?? '');
        $id = (int)($data['id'] ?? 0);

        $validSchemes = ['THEORY', 'LAB', 'INTEGRATED', 'PROJECT', 'AUDIT_NON_CREDIT', 'OTHER'];
        if (!in_array($scheme, $validSchemes, true)) {
            $scheme = 'THEORY';
        }

        if ($regId <= 0 || empty($typeCode) || empty($typeName)) {
            return ['status' => 0, 'error' => 'Regulation, Type Code, and Type Name are required.'];
        }

        try {
            if ($id > 0) {
                $stmt = $this->conn->prepare("UPDATE regulation_course_types 
                                              SET type_code = ?, type_name = ?, evaluation_scheme = ?, cie_max_marks = ?, see_max_marks = ?, total_marks = ?, has_see = ?, is_credit_course = ?, description = ? 
                                              WHERE id = ? AND reg_id = ?");
                $stmt->bind_param("sssdddiisii", $typeCode, $typeName, $scheme, $cieMax, $seeMax, $totalMarks, $hasSee, $isCreditCourse, $descr, $id, $regId);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO regulation_course_types 
                                              (reg_id, type_code, type_name, evaluation_scheme, cie_max_marks, see_max_marks, total_marks, has_see, is_credit_course, description) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssdddiis", $regId, $typeCode, $typeName, $scheme, $cieMax, $seeMax, $totalMarks, $hasSee, $isCreditCourse, $descr);
            }

            if (!$stmt->execute()) {
                if ($this->conn->errno === 1062) {
                    return ['status' => 0, 'error' => "Course type code '$typeCode' already exists for this regulation."];
                }
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $savedId = ($id > 0) ? $id : $this->conn->insert_id;
            $stmt->close();

            $this->logs->activityLog("Course Type '$typeCode' saved for Regulation ID $regId.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "SAVE_REG_COURSE_TYPE", "Course Type $typeCode ($scheme) saved for reg $regId", "superadmin", "REGULATION_COURSE_TYPE", (string)$savedId);

            return ['status' => 1, 'message' => "Course type '$typeCode' saved successfully."];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in saveCourseType: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Failed to save course type: ' . $e->getMessage()];
        }
    }

    public function deleteCourseType(int $id, int $regId): array
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM regulation_course_types WHERE id = ? AND reg_id = ?");
            $stmt->bind_param("ii", $id, $regId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            $this->logs->activityLog("Course Type ID $id deleted for Regulation ID $regId.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_REG_COURSE_TYPE", "Course Type ID $id deleted for reg $regId", "superadmin", "REGULATION_COURSE_TYPE", (string)$id);
            return ['status' => 1, 'message' => 'Course type deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in deleteCourseType: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Failed to delete course type: ' . $e->getMessage()];
        }
    }
}

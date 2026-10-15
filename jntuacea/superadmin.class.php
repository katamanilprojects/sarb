<?php
require_once("user.class.php");
require_once("academicyears.class.php");
require_once("programs.class.php");
require_once("departments.class.php");
require_once("regulations.class.php");
require_once("attendancerules.class.php");

class SuperAdmin extends User
{
    private $classname = "SuperAdmin";
    private AcademicYears $acad_years;
    private Programs $programs;
    private Departments $departments;
    private Regulations $regulations;
    private AttendanceRules $attendance_rules;

    public function __construct()
    {
        parent::__construct();
        $this->acad_years = new AcademicYears();
        $this->programs = new Programs();
        $this->departments = new Departments();
        $this->regulations = new Regulations();
        $this->attendance_rules = new AttendanceRules();
    }

    public function updatePwd($userId, $oldpassword, $newpassword)
    {
        $res = ['status' => 0];
        $authenticate = $this->verifyPwd($userId, $oldpassword);
        if ($authenticate) {
            $res = $this->updatePassword($userId, $newpassword, "superadmin");
        } else {
            $res["err"] = "Incorrect Existing Password";
        }
        return $res;
    }

    public function getAllDepartments()
    {
        return $this->departments->getAllDepartments();
    }

    public function addOrUpdateDepartment(array $data)
    {
        return $this->departments->addOrUpdateDepartment($data);
    }

    public function deleteDepartment(int $id)
    {
        return $this->departments->deleteDepartment($id);
    }

    public function getAllPrograms()
    {
        return $this->programs->getAllPrograms();
    }

    public function getAllRegulations()
    {
        return $this->regulations->getAllRegulations();
    }

    public function addOrUpdateRegulation(array $data)
    {
        return $this->regulations->addOrUpdateRegulation($data);
    }

    public function deleteRegulation(int $id)
    {
        return $this->regulations->deleteRegulation($id);
    }

    public function getRegulationById(int $id)
    {
        return $this->regulations->getRegulationById($id);
    }

    public function cloneRegulation(int $sourceRegId, int $targetRegId): array
    {
        return $this->regulations->cloneRegulation($sourceRegId, $targetRegId);
    }

    public function getCourseCategoriesByRegId(int $regId): array
    {
        return $this->regulations->getCourseCategoriesByRegId($regId);
    }

    public function saveCourseCategory(array $data): array
    {
        return $this->regulations->saveCourseCategory($data);
    }

    public function deleteCourseCategory(int $id, int $regId): array
    {
        return $this->regulations->deleteCourseCategory($id, $regId);
    }

    public function getCourseTypesByRegId(int $regId): array
    {
        return $this->regulations->getCourseTypesByRegId($regId);
    }

    public function saveCourseType(array $data): array
    {
        return $this->regulations->saveCourseType($data);
    }

    public function deleteCourseType(int $id, int $regId): array
    {
        return $this->regulations->deleteCourseType($id, $regId);
    }

    public function getAllAttendanceRules()
    {
        return $this->attendance_rules->getAllAttendanceRules();
    }

    public function addOrUpdateAttendanceRule(array $data)
    {
        return $this->attendance_rules->addOrUpdateAttendanceRule($data);
    }

    public function deleteAttendanceRule(int $id)
    {
        return $this->attendance_rules->deleteAttendanceRule($id);
    }

    public function addOrUpdateProgram(array $data)
    {
        return $this->programs->addOrUpdateProgram($data);
    }

    public function deleteProgram(int $id)
    {
        return $this->programs->deleteProgram($id);
    }

    public function getAllSpecializations()
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname, s.status, d.id as dept_id, d.dept_shortname, p.id as prog_id, p.program_code, p.prog_shortname
                                          FROM specialization s
                                          JOIN departments d ON s.dept_id = d.id
                                          JOIN programs p ON s.prog_id = p.id where s.status = 1");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllSpecializations: " . $e->getMessage());
            $res['err'] = "Failed to fetch specializations.";
        }
        return $res;
    }

    public function addOrUpdateSpecialization(array $data)
    {
        $spec_code = $data['spec_code'];
        $spec_shortname = $data['spec_shortname'];
        $spec_fullname = $data['spec_fullname'];
        $dept_id = (int) $data['dept_id'];
        $prog_id = (int) $data['prog_id'];
        $status = (int) $data['status'];

        if (!empty($data["id"])) {
            $stmt = $this->conn->prepare("UPDATE specialization SET spec_code = ?, spec_shortname = ?, spec_fullname = ?, dept_id = ?, prog_id = ?, status = ? WHERE id=?");
            $stmt->bind_param("sssiiii", $spec_code, $spec_shortname, $spec_fullname, $dept_id, $prog_id, $status, $data["id"]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO specialization (spec_code, spec_shortname, spec_fullname, dept_id, prog_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiii", $spec_code, $spec_shortname, $spec_fullname, $dept_id, $prog_id, $status);
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating specialization. Error: " . $stmt->error);
            }
            $this->logs->activityLog("Specialization '$spec_fullname' added/updated.");
            return ['status' => 1, 'message' => 'Specialization saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateSpecialization: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function deleteSpecialization(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM specialization WHERE id = ?");
        $stmt->bind_param("i", $id);
        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete specialization. Error: " . $stmt->error);
            }
            $this->logs->activityLog("Specialization ID $id deleted.");
            return ['status' => 1, 'message' => 'Specialization deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteSpecialization: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function updateSpecializationStatus(int $id, int $new_status)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE specialization SET status = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update specialization status. Error: " . $stmt->error);
            }
            $this->logs->activityLog("Specialization ID $id status updated to $new_status.");
            return ['status' => 1, 'message' => 'Specialization status updated successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in updateSpecializationStatus: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getActiveSpecializations()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname, s.status, d.id as dept_id, d.dept_shortname, p.id as prog_id, p.program_code, p.prog_shortname
                                          FROM specialization s
                                          JOIN departments d ON s.dept_id = d.id
                                          JOIN programs p ON s.prog_id = p.id
                                          WHERE s.status = 1");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getActiveSpecializations: " . $e->getMessage());
            $res['error'] = "Failed to fetch active specializations.";
        }
        return $res;
    }

    public function getSpecializationsByProgramID($prog_id, $dept = 0)
    {
        $res = ['status' => 0];
        try {
            if ($dept == 7) {
                $stmt = $this->conn->prepare("SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname, d.id as dept_id, d.dept_shortname, p.id as prog_id, p.program_code, p.prog_shortname
                FROM specialization s
                JOIN departments d ON s.dept_id = d.id
                JOIN programs p ON s.prog_id = p.id where d.id = 7 and s.prog_id=? AND s.status = 1");
            } else {
                $stmt = $this->conn->prepare("SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname, d.id as dept_id, d.dept_shortname, p.id as prog_id, p.program_code, p.prog_shortname
                FROM specialization s
                JOIN departments d ON s.dept_id = d.id
                JOIN programs p ON s.prog_id = p.id where d.id != 7 and s.prog_id=? AND s.status = 1");
            }
            $stmt->bind_param("i", $prog_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getSpecializationsByProgramID: " . $e->getMessage());
            $res['err'] = "Failed to fetch specializations.";
        }
        return $res;
    }

    public function generateClassName($spec_shortname, $yearsem, $section = '')
    {
        $base = trim($spec_shortname . ' - ' . $yearsem);
        $section = trim($section);
        if ($section !== '') {
            return $base . ' - Sec ' . strtoupper($section);
        }
        return $base;
    }

    public function getClassesByProgram()
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("SELECT c.id, c.acad_year, c.classname, c.yearsem, c.section, c.start_date, c.end_date, c.timing_id, c.status, p.prog_shortname
                FROM classes c
                JOIN specialization s ON c.spec_id = s.id
                JOIN programs p ON s.prog_id = p.id");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getClassesByProgram: " . $e->getMessage());
            $res['err'] = "Failed to fetch classes.";
        }
        return $res;
    }

    public function getAllClasses()
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("
                SELECT c.id, c.acad_year, c.classname, c.yearsem, c.section, c.start_date, c.end_date, 
                       c.spec_id, c.timing_id, c.reg_id, c.batch_id, b.batch_name,
                       r.regulation, r.regulation AS reg, c.status 
                FROM classes c 
                LEFT JOIN regulations r ON c.reg_id = r.id
                LEFT JOIN student_batches b ON c.batch_id = b.id
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllClasses: " . $e->getMessage());
            $res['err'] = "Failed to fetch classes.";
        }
        return $res;
    }

    public function addOrUpdateClass(array $data)
    {
        $acad_year = $data['acad_year'];
        $classname = $data['classname'];
        $yearsem = $data['yearsem'];
        $section = strtoupper(trim($data['section'] ?? ''));
        $spec_id = (int) $data['spec_id'];
        $start_date = $data['start_date'];
        $timing_id = (int) $data['timing_id'];
        $reg_id = !empty($data['reg_id']) ? (int) $data['reg_id'] : null;
        $batch_id = !empty($data['batch_id']) ? (int) $data['batch_id'] : null;
        $end_date = $data['end_date'];
        $status = (int) $data['status'];

        try {
            if (!empty($data["id"])) {
                $class_id = (int) $data["id"];
                $stmt = $this->conn->prepare("UPDATE classes SET acad_year=?, classname = ?, yearsem = ?, section = ?, spec_id = ?, start_date = ?, end_date = ?, timing_id = ?, reg_id = ?, batch_id = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssssissiiiii", $acad_year, $classname, $yearsem, $section, $spec_id, $start_date, $end_date, $timing_id, $reg_id, $batch_id, $status, $class_id);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO classes (acad_year, classname, yearsem, section, spec_id, start_date, end_date, timing_id, reg_id, batch_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssissiiii", $acad_year, $classname, $yearsem, $section, $spec_id, $start_date, $end_date, $timing_id, $reg_id, $batch_id, $status);
            }

            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating class. Error: " . $stmt->error);
            }

            if (empty($data["id"])) {
                $class_id = $this->conn->insert_id;
                $schedule_res = $this->saveClassTimingSchedule($class_id, $timing_id, $start_date);
                if ($schedule_res['status'] == 0) {
                    throw new Exception($schedule_res['error'] ?? 'Failed to create initial class timing schedule.');
                }
            }

            $this->logs->activityLog("Class '$classname' added/updated.");
            $action = !empty($data["id"]) ? "UPDATE_CLASS" : "ADD_CLASS";
            $superAdminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($superAdminId, $action, "Class '$classname' ($acad_year) saved", "superadmin", "CLASS", (string)$class_id);
            return ['status' => 1, 'message' => 'Class saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateClass: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getClassTimingSchedule($class_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, class_id, timing_id, from_date, to_date FROM class_timing_schedule WHERE class_id = ? ORDER BY from_date DESC, id DESC");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getClassTimingSchedule: " . $e->getMessage());
            $res['error'] = "Failed to fetch class timing schedule.";
        }
        return $res;
    }

    public function saveClassTimingSchedule($class_id, $timing_id, $from_date)
    {
        $res = ['status' => 0];
        $myname = $this->classname . " - saveClassTimingSchedule - ";
        try {
            $class_id = (int) $class_id;
            $timing_id = (int) $timing_id;
            if (empty($class_id) || empty($timing_id) || empty($from_date)) {
                throw new Exception("Required schedule data is missing.");
            }

            $stmt = $this->conn->prepare("SELECT id FROM class_timing_schedule WHERE class_id = ? AND from_date = ? LIMIT 1");
            $stmt->bind_param("is", $class_id, $from_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            $this->conn->begin_transaction();

            if (!empty($existing['id'])) {
                $stmt = $this->conn->prepare("UPDATE class_timing_schedule SET timing_id = ?, to_date = NULL WHERE id = ?");
                $stmt->bind_param("ii", $timing_id, $existing['id']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update existing schedule. Error: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $stmt = $this->conn->prepare("UPDATE class_timing_schedule SET to_date = DATE_SUB(?, INTERVAL 1 DAY) WHERE class_id = ? AND from_date < ? AND (to_date IS NULL OR to_date >= ?)");
                $stmt->bind_param("siss", $from_date, $class_id, $from_date, $from_date);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to close previous schedule. Error: " . $stmt->error);
                }
                $stmt->close();

                $stmt = $this->conn->prepare("INSERT INTO class_timing_schedule (class_id, timing_id, from_date, to_date) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("iis", $class_id, $timing_id, $from_date);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert class timing schedule. Error: " . $stmt->error);
                }
                $stmt->close();
            }

            $stmt = $this->conn->prepare("UPDATE classes SET timing_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $timing_id, $class_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update class timing id. Error: " . $stmt->error);
            }
            $stmt->close();

            $this->conn->commit();
            $this->logs->activityLog("Timing schedule saved for class ID $class_id from $from_date with template $timing_id.");
            $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($adminId, "SAVE_CLASS_TIMING_SCHEDULE", "Timing schedule saved for class ID $class_id from $from_date with template $timing_id", "superadmin", "CLASS", (string)$class_id);
            $res['status'] = 1;
            $res['message'] = 'Class timing schedule saved successfully.';
        } catch (Exception $e) {
            try { $this->conn->rollback(); } catch (Exception $rollbackException) {}
            $this->logs->errLog($myname . $e->getMessage());
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function saveBulkClassTimingSchedule($acad_year, $prog_id, $yearsem, $timing_id, $from_date)
    {
        $res = ['status' => 0];
        $myname = $this->classname . " - saveBulkClassTimingSchedule - ";
        try {
            $classesResult = $this->getClassesByAcadYrPrgYrSem($acad_year, $prog_id, $yearsem);
            $classes = $classesResult['data'] ?? [];
            if (empty($classes)) {
                throw new Exception("No Classes are Present for the Selected Program & Year-Sem");
            }
            foreach ($classes as $class) {
                $schedule_res = $this->saveClassTimingSchedule($class['id'], $timing_id, $from_date);
                if ($schedule_res['status'] == 0) {
                    throw new Exception($schedule_res['error'] ?? 'Failed to save bulk class timing schedule.');
                }
            }
            $this->logs->activityLog("Bulk timing schedule saved for program ID $prog_id, academic year $acad_year, year-sem $yearsem from $from_date with template $timing_id.");
            $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($adminId, "SAVE_BULK_CLASS_TIMING_SCHEDULE", "Bulk timing schedule saved for program ID $prog_id, academic year $acad_year, year-sem $yearsem from $from_date with template $timing_id", "superadmin", "CLASS_TIMING");
            $res['status'] = 1;
            $res['message'] = 'Class timing schedule saved successfully for the selected group.';
        } catch (Exception $e) {
            $this->logs->errLog($myname . $e->getMessage());
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function deleteClass(int $id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM class_timing_schedule WHERE class_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete class. Error: " . $stmt->error);
            }
            $this->logs->activityLog("Class ID $id deleted.");
            $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($adminId, "DELETE_CLASS", "Deleted class ID $id", "superadmin", "CLASS", (string)$id);
            return ['status' => 1, 'message' => 'Class deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteClass: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getGroupedClassesByProgram()
    {
        $res = [];
        try {
            $stmt = $this->conn->prepare("SELECT p.prog_shortname, s.prog_id, c.acad_year, c.yearsem, c.start_date, c.end_date, c.timing_id, c.reg_id, r.regulation, r.regulation AS reg, c.status
            FROM classes c
            JOIN specialization s ON c.spec_id = s.id
            JOIN programs p ON s.prog_id = p.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            GROUP BY p.prog_shortname, s.prog_id, c.acad_year, c.yearsem, c.start_date, c.end_date, c.status, c.timing_id, c.reg_id, r.regulation order by c.status desc, c.acad_year desc, p.prog_shortname asc, c.yearsem asc");
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getGroupedClassesByProgram: " . $e->getMessage());
        }
        return $res;
    }

    public function bulkDeleteClasses($acad_year, $prog_id, $yearsem)
    {
        try {
            $stmt = $this->conn->prepare("DELETE c FROM classes c
            JOIN specialization s ON c.spec_id = s.id
            WHERE s.prog_id = ? AND c.yearsem = ? AND c.acad_year = ?");
            $stmt->bind_param("iss", $prog_id, $yearsem, $acad_year);
            $stmt->execute();
            return ['status' => 1, 'message' => 'Classes deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in bulkDeleteClasses: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getGroupedClassDetails($prog_id, $acad_year, $yearsem)
    {
        try {
            $stmt = $this->conn->prepare("SELECT c.id, c.acad_year, c.start_date, c.end_date, c.timing_id, c.reg_id, r.regulation, r.regulation AS reg, c.status, c.yearsem, s.prog_id
            FROM classes c
            JOIN specialization s ON c.spec_id = s.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            WHERE s.prog_id = ? AND c.acad_year = ? AND c.yearsem = ?
            LIMIT 1");
            $stmt->bind_param("iss", $prog_id, $acad_year, $yearsem);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getGroupedClassDetails: " . $e->getMessage());
            return null;
        }
    }

    public function getClassesByAcadYrPrgYrSem($acad_year, $prog_id, $yearsem)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT c.id, c.classname, c.acad_year, c.yearsem, c.section, c.start_date, c.end_date, c.timing_id, c.reg_id, r.regulation, r.regulation AS reg, c.status, c.spec_id, s.spec_shortname, s.prog_id, p.prog_shortname
            FROM classes c
            JOIN specialization s ON c.spec_id = s.id
            JOIN programs p ON s.prog_id = p.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            WHERE c.acad_year = ? AND s.prog_id = ? AND c.yearsem = ?
            ORDER BY c.classname ASC");
            $stmt->bind_param("sis", $acad_year, $prog_id, $yearsem);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getClassesByAcadYrPrgYrSem: " . $e->getMessage());
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function bulkUpdateClasses($prog_id, $acad_year, $yearsem, $data)
    {
        $acad_year_new = $data['acad_year'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];
        $timing_id = $data['timing_id'];
        $reg_id = !empty($data['reg_id']) ? (int)$data['reg_id'] : null;
        $status = $data['status'];

        try {
            $stmt = $this->conn->prepare("SELECT c.id
                FROM classes c
                JOIN specialization s ON c.spec_id = s.id
                WHERE s.prog_id = ? AND c.acad_year = ? AND c.yearsem = ?");
            $stmt->bind_param("iss", $prog_id, $acad_year, $yearsem);
            $stmt->execute();
            $result = $stmt->get_result();
            $classIds = $result->fetch_all(MYSQLI_ASSOC);

            $stmt = $this->conn->prepare("UPDATE classes
                SET acad_year = ?, start_date = ?, end_date = ?, timing_id = ?, reg_id = ?, status = ?
                WHERE id = ?");
            foreach ($classIds as $class) {
                $stmt->bind_param("sssiisi", $acad_year_new, $start_date, $end_date, $timing_id, $reg_id, $status, $class['id']);
                $stmt->execute();
            }

            return ['status' => 1, 'message' => 'Classes updated successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in bulkUpdateClasses: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getAllTimingTemplates()
    {
        $templates = [];
        try {
            $stmt = $this->conn->prepare("SELECT timing_id, hour, hour_desc, start_time, end_time
            FROM class_timings
            ORDER BY timing_id, hour");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $templates[$row['timing_id']]['timing_id'] = $row['timing_id'];
                $templates[$row['timing_id']]['hours'][] = [
                    'hour' => $row['hour'],
                    'hour_desc' => $row['hour_desc'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ];
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getAllTimingTemplates: " . $e->getMessage());
        }
        return $templates;
    }

    public function getTimingTemplateDetails($timing_id)
    {
        $template = ['timing_id' => $timing_id, 'hours' => []];
        try {
            $stmt = $this->conn->prepare("SELECT hour, hour_desc, start_time, end_time
            FROM class_timings
            WHERE timing_id = ?
            ORDER BY hour");
            $stmt->bind_param("i", $timing_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $template['hours'][] = $row;
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getTimingTemplateDetails: " . $e->getMessage());
        }
        return $template;
    }

    public function saveTimingTemplate($timing_id, $hours)
    {
        $this->deleteTimingTemplate($timing_id);
        try {
            $stmt = $this->conn->prepare("INSERT INTO class_timings (timing_id, hour, hour_desc, start_time, end_time)
            VALUES (?, ?, ?, ?, ?)");
            foreach ($hours as $time) {
                $hour_val = $time["hour"];
                $hour_desc = $time["hour_desc"];
                $start_time = $time['start_time'];
                $end_time = $time['end_time'];
                $stmt->bind_param("issss", $timing_id, $hour_val, $hour_desc, $start_time, $end_time);
                $stmt->execute();
            }
            $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($adminId, "SAVE_TIMING_TEMPLATE", "Saved timing template ID $timing_id with " . count($hours) . " slots", "superadmin", "TIMING_TEMPLATE", (string)$timing_id);
            return ['status' => 1];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in saveTimingTemplate: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function deleteTimingTemplate($timing_id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM class_timings WHERE timing_id = ?");
            $stmt->bind_param("i", $timing_id);
            $stmt->execute();
            $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($adminId, "DELETE_TIMING_TEMPLATE", "Deleted timing template ID $timing_id", "superadmin", "TIMING_TEMPLATE", (string)$timing_id);
            return ['status' => 1];
        } catch (Exception $e) {
            $this->logs->errLog("Exception in deleteTimingTemplate: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    
    public function getAllAdmins()
    {
        $res = ['status' => 0];

        try {
            $stmt = $this->conn->prepare("SELECT id, username, name, mobile, email, status FROM users WHERE role = 'admin'");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllAdmins: " . $e->getMessage());
            $res['err'] = "Failed to fetch admins.";
        }

        return $res;
    }

    public function addOrUpdateAdmin(array $data)
    {
        $username = $data['username'];
        $name = $data['name'];
        $mobile = $data['mobile'];
        $email = $data['email'];
        $status = (int) $data['status']; // Cast to integer for status

        $this->conn->begin_transaction();

        try {

            // Insert or update admin record
            if (!empty($data['id'])) {
                $id = (int) $data['id']; // Cast to integer for ID
                $stmt = $this->conn->prepare("
                    UPDATE users
                    SET username = ?, name = ?, mobile = ?, email = ?
                    WHERE id = ? AND role = 'admin'
                ");
                $stmt->bind_param("ssssi", $username, $name, $mobile, $email, $id);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute query for adding/updating admin. Error: " . $stmt->error);
                }

            } else {
                // Check if username already exists
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $exists = (int) $stmt->get_result()->fetch_row()[0];

                if ($exists > 0) {
                    throw new Exception("Username already exists.");
                }
                $stmt = $this->conn->prepare("
                    INSERT INTO users (username, password, name, mobile, email, role, status)
                    VALUES (?, ?, ?, ?, ?, 'admin', ?)
                ");
                $pwd = password_hash($username, PASSWORD_BCRYPT);
                $stmt->bind_param(
                    "sssssi",
                    $username,
                    $pwd,
                    $name,
                    $mobile,
                    $email,
                    $status,
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute query for adding/updating user. Error: " . $stmt->error);
                }
            }

            $this->conn->commit();
            $this->logs->activityLog("Admin '$name' ($username) created/updated.");
            $action = !empty($data['id']) ? "UPDATE_ADMIN" : "ADD_ADMIN";
            $adminEntityId = !empty($data['id']) ? (string)$data['id'] : (string)$this->conn->insert_id;
            $superAdminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($superAdminId, $action, "Admin '$name' ($username) created/updated", "superadmin", "ADMIN", $adminEntityId);
            return ['status' => 1, 'message' => 'Admin saved successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog("Exception occurred in addOrUpdateAdmin: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function toggleAdminStatus(int $id, int $status)
    {
        $stmt = $this->conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'admin'");
        $stmt->bind_param("ii", $status, $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to update admin status. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Admin ID $id status updated to $status.");
            $superAdminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $statusText = $status ? "Active" : "Inactive";
            $this->dbActivityLog($superAdminId, "TOGGLE_ADMIN_STATUS", "Set admin ID $id status to $statusText", "superadmin", "ADMIN", (string)$id);
            return ['status' => 1, 'message' => 'Admin status updated successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in toggleAdminStatus: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function resetAdminPassword(int $id)
    {
        try {
            // Get username
            $stmt = $this->conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'admin'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                throw new Exception("Admin not found.");
            }
            $username = $result->fetch_assoc()['username'];

            // Reset password
            $newPassword = password_hash($username, PASSWORD_BCRYPT);
            $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newPassword, $id);

            if (!$stmt->execute()) {
                throw new Exception("Failed to reset password. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Admin ID $id password reset.");
            $superAdminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($superAdminId, "RESET_ADMIN_PASSWORD", "Reset password for admin ID $id ($username)", "superadmin", "ADMIN", (string)$id);
            return ['status' => 1, 'message' => 'Admin password reset successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in resetAdminPassword: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getSpecializationsByProgramAndDept($prog_id, $dept_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, spec_shortname FROM specialization WHERE prog_id = ? AND dept_id = ? AND status = 1");
            $stmt->bind_param("ii", $prog_id, $dept_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getSpecializationsByProgramAndDept: " . $e->getMessage());
            $res['error'] = "Failed to fetch specializations.";
        }
        return $res;
    }

    public function getPoPso($acad_year, $regulation, $spec_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, po_pso, orderid, code, description, reg_id, target_score, effective_from_year FROM po_pso WHERE acad_year = ? AND regulation = ? AND specid = ? ORDER BY po_pso, orderid");
            $stmt->bind_param("ssi", $acad_year, $regulation, $spec_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getPoPso: " . $e->getMessage());
            $res['error'] = "Failed to fetch POs/PSOs.";
        }
        return $res;
    }

    public function getPoPsoByCohort(int $reg_id, int $spec_id, int $admission_year, ?string $po_pso_type = null)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $sql = "SELECT p.id, p.po_pso, p.orderid, p.code, p.description, p.reg_id, p.target_score, p.effective_from_year 
                    FROM po_pso p
                    WHERE p.reg_id = ? AND p.specid = ?
                      AND p.effective_from_year = (
                          SELECT MAX(sub.effective_from_year) 
                          FROM po_pso sub 
                          WHERE sub.reg_id = p.reg_id AND sub.specid = p.specid 
                            AND sub.code = p.code AND sub.effective_from_year <= ?
                      )";
            $params = [$reg_id, $spec_id, $admission_year];
            $types = "iii";
            if ($po_pso_type !== null) {
                $sql .= " AND p.po_pso = ?";
                $params[] = $po_pso_type;
                $types .= "s";
            }
            $sql .= " ORDER BY p.po_pso, p.orderid";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res['data'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getPoPsoByCohort: " . $e->getMessage());
            $res['error'] = "Failed to fetch POs/PSOs by cohort.";
        }
        return $res;
    }

    public function addOrUpdatePoPso(array $data)
    {
        $res = ['status' => 0];
        try {
            $target_score = isset($data['target_score']) ? (float)$data['target_score'] : 2.00;
            $effective_from_year = !empty($data['effective_from_year']) 
                ? (int)$data['effective_from_year'] 
                : (!empty($data['acad_year']) ? (int)substr($data['acad_year'], 0, 4) : 2020);

            // Resolve reg_id if missing
            $reg_id = $data['reg_id'] ?? null;
            if (!$reg_id && !empty($data['regulation'])) {
                $regStmt = $this->conn->prepare("SELECT id FROM regulations WHERE regulation = ? LIMIT 1");
                $regClean = trim($data['regulation']);
                $regStmt->bind_param("s", $regClean);
                $regStmt->execute();
                $reg_id = $regStmt->get_result()->fetch_assoc()['id'] ?? null;
            }

            if (!empty($data['id'])) {
                // Update existing record
                $stmt = $this->conn->prepare("UPDATE po_pso SET po_pso = ?, orderid = ?, code = ?, description = ?, target_score = ?, effective_from_year = ?, reg_id = COALESCE(?, reg_id) WHERE id = ?");
                $stmt->bind_param("sissdiii", $data['po_pso'], $data['orderid'], $data['code'], $data['description'], $target_score, $effective_from_year, $reg_id, $data['id']);
            } else {
                // Insert new record
                $stmt = $this->conn->prepare("
                    INSERT INTO po_pso (acad_year, regulation, specid, po_pso, orderid, code, description, reg_id, target_score, effective_from_year)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssisissidi",
                    $data['acad_year'],
                    $data['regulation'],
                    $data['specid'],
                    $data['po_pso'],
                    $data['orderid'],
                    $data['code'],
                    $data['description'],
                    $reg_id,
                    $target_score,
                    $effective_from_year
                );
            }

            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = 'PO/PSO saved successfully.';
            } else {
                 throw new Exception("Statement execution failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in addOrUpdatePoPso: " . $e->getMessage());
            $res['error'] = "Failed to save PO/PSO: " . $e->getMessage();
        }
        return $res;
    }

    public function bulkDeletePoPso($acad_year, $regulation, $spec_id, $po_pso_type)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("DELETE FROM po_pso WHERE acad_year = ? AND regulation = ? AND specid = ? AND po_pso = ?");
            $stmt->bind_param("ssis", $acad_year, $regulation, $spec_id, $po_pso_type);
             if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = 'Existing ' . $po_pso_type . 's deleted successfully.';
                $this->logs->activityLog("Bulk deleted " . $po_pso_type . "s for spec ID " . $spec_id);
            } else {
                throw new Exception("Bulk delete statement execution failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in bulkDeletePoPso: " . $e->getMessage());
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function deletePoPso(int $id)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("DELETE FROM po_pso WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $res['status'] = 1;
                    $res['message'] = 'PO/PSO deleted successfully.';
                    $this->logs->activityLog("Deleted PO/PSO with ID: " . $id);
                } else {
                    throw new Exception("No record found to delete.");
                }
            } else {
                throw new Exception("Statement execution failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in deletePoPso: " . $e->getMessage());
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function getAllAcademicYears()
    {
        return $this->acad_years->getAllAcademicYears();
    }
    
    public function getActiveAcademicYears()
    {
        return $this->acad_years->getActiveAcademicYears();
    }

    public function addOrUpdateAcademicYear(array $data)
    {
        return $this->acad_years->addOrUpdateAcademicYear($data);
    }

    public function toggleAcademicYearStatus(int $id, int $status)
    {
        return $this->acad_years->toggleAcademicYearStatus($id, $status);
    } 
    
    public function getSpecializationsByProgram($prog_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id FROM specialization WHERE prog_id = ? AND status = 1");
            $stmt->bind_param("i", $prog_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getSpecializationsByProgram: " . $e->getMessage());
            $res['error'] = "Failed to fetch specializations.";
        }
        return $res;
    }

    public function copyPoPsoFromPreviousYear($new_acad_year, $regulation, $prog_id)
    {
        $res = ['status' => 0];
        try {
            // 1. Determine the previous academic year
            $year_parts = explode('-', $new_acad_year);
            $prev_year_start = (int)$year_parts[0] - 1;
            $prev_year_end = (int)$year_parts[1] - 1;
            $prev_acad_year = $prev_year_start . '-' . $prev_year_end;

            // 2. Get all specializations for the given program (only active ones should receive copied data)
            $spec_res = $this->getActiveSpecializationsByProgram($prog_id); // Use getActiveSpecializationsByProgram
            if ($spec_res['status'] === 0) {
                throw new Exception("Could not retrieve active specializations for the program.");
            }
            $spec_ids = array_column($spec_res['data'], 'id');

            $this->conn->begin_transaction();

            // 3. For each specialization, get POs/PSOs from the previous year and insert for the new year
            foreach ($spec_ids as $spec_id) {
                // Get previous year data
                $prev_data_res = $this->getPoPso($prev_acad_year, $regulation, $spec_id);
                if ($prev_data_res['status'] === 0) {
                    // Log this, but don't stop the whole process
                    $this->logs->errLog("Could not retrieve POs/PSOs for spec ID: {$spec_id} from {$prev_acad_year} under regulation {$regulation}");
                    continue; 
                }

                $prev_data = $prev_data_res['data'];
                
                // Before inserting, delete existing POs/PSOs for the target acad_year, regulation, spec_id
                $this->bulkDeletePoPso($new_acad_year, $regulation, $spec_id, 'PO');
                $this->bulkDeletePoPso($new_acad_year, $regulation, $spec_id, 'PSO');

                // Insert for the new academic year
                foreach ($prev_data as $item) {
                    $data_to_insert = [
                        'acad_year' => $new_acad_year,
                        'regulation' => $regulation,
                        'specid' => $spec_id,
                        'po_pso' => $item['po_pso'],
                        'orderid' => $item['orderid'],
                        'code' => $item['code'],
                        'description' => $item['description'],
                    ];
                    $insert_res = $this->addOrUpdatePoPso($data_to_insert);
                    if ($insert_res['status'] === 0) {
                        throw new Exception("Failed to copy PO/PSO item: " . ($insert_res['error'] ?? 'Unknown error'));
                    }
                }
            }

            $this->conn->commit();
            $res['status'] = 1;
            $res['message'] = "POs/PSOs from {$prev_acad_year} (Regulation {$regulation}) have been successfully copied to {$new_acad_year} (Regulation {$regulation}).";

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog("Exception in copyPoPsoFromPreviousYear: " . $e->getMessage());
            $res['error'] = "Operation failed: " . $e->getMessage();
        }
        return $res;
    }

    // New method to get an overview of PO/PSO status for an academic year
    public function getPoPsoOverviewData(string $acad_year)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            // Step 1: Get all active specializations with their program and department details
            $stmt = $this->conn->prepare("
                SELECT s.id as spec_id, s.spec_shortname, p.id as prog_id, p.prog_shortname, d.id as dept_id, d.dept_shortname
                FROM specialization s
                JOIN programs p ON s.prog_id = p.id
                JOIN departments d ON s.dept_id = d.id
                WHERE s.status = 1
                ORDER BY p.prog_shortname, s.id, d.dept_shortname, s.spec_shortname
            ");
            $stmt->execute();
            $active_specs_result = $stmt->get_result();
            $active_specializations = $active_specs_result->fetch_all(MYSQLI_ASSOC);

            $overview = [];

            foreach ($active_specializations as $spec_info) {
                $spec_id = $spec_info['spec_id'];
                $prog_id = $spec_info['prog_id'];
                $dept_id = $spec_info['dept_id']; // Ensure dept_id is retrieved

                // Step 2: Find all distinct regulations for which POs/PSOs exist for this specialization and academic year
                $stmt = $this->conn->prepare("
                    SELECT DISTINCT regulation FROM po_pso
                    WHERE acad_year = ? AND specid = ?
                ");
                $stmt->bind_param("si", $acad_year, $spec_id);
                $stmt->execute();
                $regulations_result = $stmt->get_result();
                $regulations = $regulations_result->fetch_all(MYSQLI_ASSOC);

                if (empty($regulations)) {
                    // No PO/PSO data for this specialization and academic year, create a placeholder entry with N/A regulation
                    $overview[] = [
                        'prog_id' => $prog_id,
                        'prog_shortname' => $spec_info['prog_shortname'],
                        'dept_id' => $dept_id,
                        'dept_shortname' => $spec_info['dept_shortname'],
                        'spec_id' => $spec_id,
                        'spec_shortname' => $spec_info['spec_shortname'],
                        'regulation' => 'N/A',
                        'po_status' => 'Missing',
                        'pso_status' => 'Missing',
                    ];
                } else {
                    foreach ($regulations as $reg_row) {
                        $regulation = $reg_row['regulation'];

                        // Step 3: Check if POs exist for this (acad_year, regulation, spec_id)
                        $po_check_stmt = $this->conn->prepare("
                            SELECT COUNT(*) FROM po_pso
                            WHERE acad_year = ? AND regulation = ? AND specid = ? AND po_pso = 'PO'
                        ");
                        $po_check_stmt->bind_param("ssi", $acad_year, $regulation, $spec_id);
                        $po_check_stmt->execute();
                        $po_count = $po_check_stmt->get_result()->fetch_row()[0];

                        // Step 4: Check if PSOs exist for this (acad_year, regulation, spec_id)
                        $pso_check_stmt = $this->conn->prepare("
                            SELECT COUNT(*) FROM po_pso
                            WHERE acad_year = ? AND regulation = ? AND specid = ? AND po_pso = 'PSO'
                        ");
                        $pso_check_stmt->bind_param("ssi", $acad_year, $regulation, $spec_id);
                        $pso_check_stmt->execute();
                        $pso_count = $pso_check_stmt->get_result()->fetch_row()[0];

                        $overview[] = [
                            'prog_id' => $prog_id,
                            'prog_shortname' => $spec_info['prog_shortname'],
                            'dept_id' => $dept_id,
                            'dept_shortname' => $spec_info['dept_shortname'],
                            'spec_id' => $spec_id,
                            'spec_shortname' => $spec_info['spec_shortname'],
                            'regulation' => $regulation,
                            'po_status' => ($po_count > 0) ? 'Added' : 'Missing',
                            'pso_status' => ($pso_count > 0) ? 'Added' : 'Missing',
                        ];
                    }
                }
            }

            $res['data'] = $overview;
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getPoPsoOverviewData: " . $e->getMessage());
            $res['error'] = "Failed to fetch PO/PSO overview: " . $e->getMessage();
        }

        return $res;
    }

    // Helper method to get active specializations for a given program (used in copyPoPsoFromPreviousYear)
    public function getActiveSpecializationsByProgram($prog_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, spec_shortname FROM specialization WHERE prog_id = ? AND status = 1");
            $stmt->bind_param("i", $prog_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getActiveSpecializationsByProgram: " . $e->getMessage());
            $res['error'] = "Failed to fetch active specializations for program.";
        }
        return $res;
    }

    // New method to get specialization shortname by ID - used for UI display
    public function getSpecializationShortnameById(int $spec_id)
    {
        $res = ['status' => 0, 'data' => ['spec_shortname' => '']];
        try {
            $stmt = $this->conn->prepare("SELECT spec_shortname FROM specialization WHERE id = ?");
            $stmt->bind_param("i", $spec_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data']['spec_shortname'] = $row['spec_shortname'];
                $res['status'] = 1;
            } else {
                $res['data']['spec_shortname'] = "ID: {$spec_id} (Not Found)"; // Fallback for UI
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getSpecializationShortnameById: " . $e->getMessage());
            $res['error'] = "Failed to fetch specialization shortname: " . $e->getMessage();
            $res['data']['spec_shortname'] = "ID: {$spec_id} (Error)"; // Fallback for UI
        }
        return $res;
    }

}

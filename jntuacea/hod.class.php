<?php
require_once("user.class.php");
require_once("subject.class.php");

class HOD extends User
{
    private $classname = "HOD";

    // Constructor
    public function __construct()
    {
        parent::__construct();
    }

    // Function to update password (reusing User class)
    public function updatePwd($userId, $oldPassword, $newPassword)
    {
        $res = ['status' => 0];
        if ($this->verifyPwd($userId, $oldPassword)) {
            $res = $this->updatePassword($userId, $newPassword, "hod");
        } else {
            $res['err'] = "Incorrect Existing Password";
        }
        return $res;
    }

    public function getFacultyByDepartment($dept_id)
    {
        $myname = $this->classname . " - getFacultyByDepartment - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT f.id, f.username, u.name, u.mobile, u.email, f.designation, u.status, COALESCE(f.sort_order, 0) AS sort_order
                        FROM faculties f
                        JOIN users u ON f.username = u.username
                        WHERE f.dept_id = ?
                        ORDER BY 
                        CASE WHEN f.sort_order > 0 THEN f.sort_order ELSE 99999 END ASC,
                        u.status DESC,
                        FIELD(f.designation, 
                            'Professor', 
                            'Assoc. Professor', 
                            'Asst. Professor', 
                            'Asst. Professor (Adhoc)'
                        ) ASC,
                        f.id ASC"
                );
                if (!$stmt) {
                    $stmt = $this->conn->prepare(
                        "SELECT f.id, f.username, u.name, u.mobile, u.email, f.designation, u.status, 0 AS sort_order
                         FROM faculties f
                         JOIN users u ON f.username = u.username
                         WHERE f.dept_id = ?
                         ORDER BY 
                         u.status DESC,
                         FIELD(f.designation, 
                             'Professor', 
                             'Assoc. Professor', 
                             'Asst. Professor', 
                             'Asst. Professor (Adhoc)'
                         ) ASC,
                         f.id ASC"
                    );
                }
                if ($stmt) {
                    $stmt->bind_param("i", $dept_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $username, $name, $mobile, $email, $designation, $status, $sort_order);
                        while ($stmt->fetch()) {
                            $res['data'][] = [
                                'id' => $id,
                                'username' => $username,
                                'name' => $name,
                                'mobile' => $mobile,
                                'email' => $email,
                                'designation' => $designation,
                                'status' => $status,
                                'sort_order' => (int)$sort_order
                            ];
                        }
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Connection error or error flag set.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    // Function to view all faculties in the department
    public function getAllActiveFacultyOrderDeptID($deptId)
    {
        $res = ['status' => 0, 'data' => []];
        try {

            $stmt = $this->conn->prepare("SELECT f.id, f.username, u.name, f.designation, d.dept_fullname
                 FROM faculties f
                 JOIN users u ON f.username = u.username
                 JOIN departments d ON f.dept_id = d.id
                 WHERE f.dept_id = ? AND u.status = 1
                 ORDER BY 
                    CASE WHEN f.sort_order > 0 THEN f.sort_order ELSE 99999 END ASC,
                    FIELD(f.designation, 'Professor', 'Assoc. Professor', 'Asst. Professor', 'Asst. Professor (Adhoc)') ASC,
                    f.id ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $deptId);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $username, $name, $designation, $dept_fullname);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'username' => $username, 'name' => $name, 'designation' => $designation, 'dept_fullname' => $dept_fullname];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog("Statement not executed: " . $this->conn->error);
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT f.id, f.username, u.name, f.designation, d.dept_fullname
            FROM faculties f
            JOIN users u ON f.username = u.username
            JOIN departments d ON f.dept_id = d.id
            WHERE d.dept_shortname = 'SH' AND u.status = 1 
            ORDER BY f.dept_id,
                CASE WHEN f.sort_order > 0 THEN f.sort_order ELSE 99999 END ASC,
                f.id ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->conn->error);
            }
            if ($stmt->execute()) {
                $stmt->bind_result($id, $username, $name, $designation, $dept_fullname);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'username' => $username, 'name' => $name, 'designation' => $designation, 'dept_fullname' => $dept_fullname];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog("Statement not executed: " . $this->conn->error);
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT f.id, f.username, u.name, f.designation, d.dept_fullname
                 FROM faculties f
                 JOIN users u ON f.username = u.username
                 JOIN departments d ON f.dept_id = d.id
                 WHERE d.dept_shortname!='SH' AND f.dept_id != ? AND u.status = 1 
                 ORDER BY f.dept_id,
                    CASE WHEN f.sort_order > 0 THEN f.sort_order ELSE 99999 END ASC,
                    f.id ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $deptId);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $username, $name, $designation, $dept_fullname);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'username' => $username, 'name' => $name, 'designation' => $designation, 'dept_fullname' => $dept_fullname];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog("Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching faculties: " . $e->getMessage());
        }
        return $res;
    }

    // Function to view student performance across subjects
    public function viewPerformance($deptId)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT s.name, s.username, p.marks FROM students s JOIN performance p ON s.id = p.student_id WHERE s.dept_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare performance query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $deptId);
            if ($stmt->execute()) {
                $stmt->bind_result($name, $username, $marks);
                while ($stmt->fetch()) {
                    $res['data'][] = ['name' => $name, 'username' => $username, 'marks' => $marks];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog("Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Error viewing performance: " . $e->getMessage());
        }
        return $res;
    }

    // Function to get attendance details for a department
    public function getAttendanceByDept($deptId)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT s.name, a.attendance_percentage FROM students s JOIN attendance a ON s.id = a.student_id WHERE s.dept_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $deptId);
            if ($stmt->execute()) {
                $stmt->bind_result($name, $attendancePercentage);
                while ($stmt->fetch()) {
                    $res['data'][] = ['name' => $name, 'attendance_percentage' => $attendancePercentage];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog("Failed to fetch attendance: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching attendance: " . $e->getMessage());
        }
        return $res;
    }

    public function getFacultyDetails($faculty_id)
    {
        $myname = "Admin - getFacultyDetails - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare(
                    "SELECT f.id, u.username, u.name, u.email, u.mobile, f.designation, u.status, u.role, COALESCE(f.sort_order, 0) AS sort_order
                 FROM faculties f
                 JOIN users u ON f.username = u.username
                 WHERE f.id = ?"
                )) {
                    $stmt->bind_param("i", $faculty_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $username, $name, $email, $mobile, $designation, $status, $role, $sort_order);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $username,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'designation' => $designation,
                                'status' => $status,
                                'role' => $role,
                                'sort_order' => (int)$sort_order
                            ];
                            $res['status'] = 1;
                        }
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Connection error or error flag set.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function updateFacultyDetails($faculty_id, $data)
    {
        $myname = "Admin - updateFacultyDetails - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare(
                    "UPDATE users u 
                 JOIN faculties f ON u.username = f.username
                 SET u.name = ?, u.email = ?, u.mobile = ?, f.designation = ?, u.status = ?
                 WHERE f.id = ?"
                )) {
                    $stmt->bind_param("ssssii", $data['name'], $data['email'], $data['mobile'], $data['designation'], $data['status'], $faculty_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Connection error or error flag set.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    // In hod.class.php

    public function resetFacPwd($faculty_id)
    {
        $myname = "HOD - resetFacPwd - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                // Step 1: Fetch faculty mobile number
                if ($fetch_stmt = $this->conn->prepare(
                    "SELECT u.mobile FROM users u 
                     JOIN faculties f ON u.username = f.username 
                     WHERE f.id = ?"
                )) {
                    $fetch_stmt->bind_param("i", $faculty_id);
                    $fetch_stmt->execute();
                    $fetch_stmt->bind_result($mobile);
                    if ($fetch_stmt->fetch()) {
                        $fetch_stmt->close();
                        
                        // Step 2: Hash password in PHP using bcrypt
                        $new_password = password_hash($mobile, PASSWORD_BCRYPT);
                        
                        // Step 3: Update with bcrypt hash
                        if ($stmt = $this->conn->prepare(
                            "UPDATE users u 
                             JOIN faculties f ON u.username = f.username
                             SET u.password = ? WHERE f.id = ?"
                        )) {
                            $stmt->bind_param("si", $new_password, $faculty_id);
                            if ($stmt->execute()) {
                                $res['status'] = 1;
                            } else {
                                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        $fetch_stmt->close();
                        $this->logs->errLog($myname . "Faculty not found: " . $faculty_id);
                    }
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Connection error or error flag set.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function getSpecializationsByDepartment($dept_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM specialization WHERE dept_id = ? and status=1 order by spec_code");
            $stmt->bind_param("i", $dept_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching specializations by department: " . $e->getMessage());
        }
        return $res;
    }

    public function getActiveClassesBySpecialization($spec_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM classes WHERE spec_id = ? AND status=1 AND end_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH) ORDER BY acad_year DESC, yearsem, section ASC");
            $stmt->bind_param("i", $spec_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching classes by specialization: " . $e->getMessage());
        }
        return $res;
    }

    public function getClassesByDepartment($dept_id)
    {
        $myname = $this->classname . " - getClassesByDepartment - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $query = "
                    SELECT c.*, sp.spec_shortname, sp.spec_code, sp.spec_fullname
                    FROM classes c
                    JOIN specialization sp ON c.spec_id = sp.id
                    WHERE sp.dept_id = ? AND c.status = 1
                    ORDER BY c.acad_year DESC, c.yearsem ASC
                ";
                if ($stmt = $this->conn->prepare($query)) {
                    $stmt->bind_param("i", $dept_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }


    public function checkIfStudentsEnrolled($class_id)
    {
        $res = false;
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS count FROM students WHERE class_id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $res = $count > 0;
        } catch (Exception $e) {
            $this->logs->errLog("Error checking if students are enrolled: " . $e->getMessage());
        }
        return $res;
    }

    public function getStudentsByClass($class_id, $includeInactive = false)
    {
        $res = array();
        try {
            $statusClause = $includeInactive ? "" : " AND s.status = 1";
            $stmt = $this->conn->prepare("SELECT s.id, s.username as roll_number, u.name, u.email, u.mobile, s.status, u.role
                 FROM students s
                 JOIN users u ON s.username = u.username
                 WHERE s.class_id = ?" . $statusClause . " ORDER BY s.username");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching students by class: " . $e->getMessage());
        }
        return $res;
    }

    public function getStudentsByRollNumbers($class_id, $roll_numbers)
    {
        $res = array();
        $myname = $this->classname . " - getStudentsByRollNumbers - ";
        try {
            if (empty($roll_numbers)) return $res;
            $placeholders = implode(',', array_fill(0, count($roll_numbers), '?'));
            $stmt = $this->conn->prepare("SELECT s.id, s.username as roll_number, u.name FROM students s JOIN users u ON s.username = u.username WHERE s.class_id = ? AND s.username IN ($placeholders) AND s.status = 1 ORDER BY s.username");
            $types = str_repeat('s', count($roll_numbers));
            $stmt->bind_param("i" . $types, $class_id, ...$roll_numbers);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Error: " . $e->getMessage());
        }
        return $res;
    }

    public function getClassTimingsByClassId($class_id)
    {
        $res = array();
        $myname = $this->classname . " - getClassTimingsByClassId - ";
        try {
            $stmt = $this->conn->prepare("SELECT ct.id, ct.hour, ct.hour_desc, ct.start_time, ct.end_time FROM class_timings ct INNER JOIN classes c ON c.timing_id = ct.timing_id WHERE c.id = ? ORDER BY ct.id");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Error: " . $e->getMessage());
        }
        return $res;
    }

    public function bulkInsertPermissions($permissions_data, $hod_id)
    {
        $myname = $this->classname . " - bulkInsertPermissions - ";
        $res = ['status' => 0];
        try {
            if (!empty($this->conn)) {
                $this->conn->begin_transaction();
                $stmt = $this->conn->prepare("INSERT INTO student_permissions (stu_id, class_id, date, hour, permission_type, reason, document_path, granted_by_hod_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($permissions_data as $perm) {
                    $stmt->bind_param("iisisssi", $perm['stu_id'], $perm['class_id'], $perm['date'], $perm['hour'], $perm['permission_type'], $perm['reason'], $perm['document_path'], $hod_id);
                    if (!$stmt->execute()) {
                        $this->conn->rollback();
                        $this->logs->errLog($myname . "Insert failed: " . $this->conn->error);
                        return $res;
                    }
                }
                $stmt->close();
                $this->conn->commit();
                $res['status'] = 1;
                $this->dbActivityLog($hod_id, "BULK_GRANT_PERMISSIONS", "Granted " . count($permissions_data) . " student permissions", "hod", "PERMISSION");
            }
        } catch (Exception $e) {
            if ($this->conn) $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    // Fetch permissions granted by this HOD (for Manage page)
    public function getPermissionsByHod($hod_id, $class_id = null, $month_year = null)
    {
        $res = [];
        try {
            $where_extra = "";
            $params      = [$hod_id];
            $types       = "i";

            if (!empty($class_id)) {
                $where_extra .= " AND s.class_id = ?";
                $params[] = (int)$class_id;
                $types   .= "i";
            }
            if (!empty($month_year)) {
                // expects format YYYY-MM
                $where_extra .= " AND DATE_FORMAT(sp.date, '%Y-%m') = ?";
                $params[] = $month_year;
                $types   .= "s";
            }

            $stmt = $this->conn->prepare("
            SELECT sp.id, s.username AS roll_number, u.name AS student_name,
            sp.date, ct.hour_desc, sp.permission_type, sp.reason,
            sp.created_at, sp.document_path, s.class_id
            FROM student_permissions sp
            JOIN students s ON sp.stu_id = s.id
            JOIN users u ON s.username = u.username
            JOIN class_timings ct ON sp.hour = ct.id
            WHERE sp.granted_by_hod_id = ? $where_extra
            ORDER BY sp.date DESC, s.username, ct.id
        ");
            $stmt->bind_param($types, ...$params);

            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("getPermissionsByHod: " . $e->getMessage());
        }
        return $res;
    }

    // Delete a single permission — only if owned by this HOD
    public function deletePermission($perm_id, $hod_id)
    {
        $res = ['status' => 0];
        try {
            // Ownership check
            $stmt = $this->conn->prepare("SELECT id FROM student_permissions WHERE id = ? AND granted_by_hod_id = ?");
            $stmt->bind_param("ii", $perm_id, $hod_id);
            $stmt->execute();
            $stmt->bind_result($found_id);
            $stmt->fetch();
            $stmt->close();
            if (empty($found_id)) {
                $res['err'] = "Permission record not found or not yours to delete.";
                return $res;
            }
            $stmt = $this->conn->prepare("DELETE FROM student_permissions WHERE id = ?");
            $stmt->bind_param("i", $perm_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($hod_id, "Permission", "Deleted permission ID $perm_id", "HOD");
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("deletePermission: " . $e->getMessage());
        }
        return $res;
    }

    // Check for existing permissions (duplicate detection)
    public function checkExistingPermissions($stu_ids, $dates)
    {
        $res = [];
        try {
            if (empty($stu_ids) || empty($dates)) return $res;
            $stu_placeholders = implode(',', array_fill(0, count($stu_ids), '?'));
            $date_placeholders = implode(',', array_fill(0, count($dates), '?'));
            $stmt = $this->conn->prepare("
                SELECT sp.id, s.username AS roll_number, sp.date, ct.hour_desc
                FROM student_permissions sp
                JOIN students s ON sp.stu_id = s.id
                JOIN class_timings ct ON sp.hour = ct.id
                WHERE sp.stu_id IN ($stu_placeholders) AND sp.date IN ($date_placeholders)
                ORDER BY s.username, sp.date, ct.id
            ");
            $types = str_repeat('i', count($stu_ids)) . str_repeat('s', count($dates));
            $stmt->bind_param($types, ...array_merge($stu_ids, $dates));
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("checkExistingPermissions: " . $e->getMessage());
        }
        return $res;
    }

    public function getClassById($class_id)
    {
        $res = null;
        try {
            $stmt = $this->conn->prepare("SELECT c.id, c.classname, c.start_date, c.end_date, c.reg_id, r.regulation, r.regulation AS reg, c.spec_id, c.yearsem, c.acad_year FROM classes c LEFT JOIN regulations r ON c.reg_id = r.id WHERE c.id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("getClassById: " . $e->getMessage());
        }
        return $res;
    }

    public function getPermissionHoursForClass($class_id, $start_date = null, $end_date = null)
    {
        $res = [];
        try {
            $where = "s.class_id = ?";
            $params = [$class_id];
            $types = "i";

            if (!empty($start_date)) {
                $where .= " AND sp.date >= ?";
                $params[] = $start_date;
                $types .= "s";
            }
            if (!empty($end_date)) {
                $where .= " AND sp.date <= ?";
                $params[] = $end_date;
                $types .= "s";
            }

            $stmt = $this->conn->prepare("
                SELECT s.username AS roll_number,
                SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS permission_hours,
                SUM(CASE WHEN a.id IS NOT NULL AND a.status = 1 THEN 1 ELSE 0 END) AS present_during_permission
                FROM student_permissions sp
                JOIN students s ON sp.stu_id = s.id
                LEFT JOIN attendance a ON a.stu_id = s.id AND a.date = sp.date AND a.hour = sp.hour
                WHERE $where
                GROUP BY s.username
            ");

            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res[$row['roll_number']] = [
                    'total' => (int)$row['permission_hours'],
                    'present' => (int)$row['present_during_permission']
                ];
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("getPermissionHoursForClass: " . $e->getMessage());
        }
        return $res;
    }

    public function getSubjectsByClassID($class_id)
    {
        $subjectObj = new Subject();
        $data = $subjectObj->getSubjectsByClass($class_id);
        return ['status' => !empty($data) ? 1 : 0, 'data' => $data];
    }

    public function addSubject($data)
    {
        $myname = $this->classname . " - addSubject - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("INSERT INTO `subjects` (`subject_sno`, `subcode`, `sub_shortname`, `sub_fullname`, `sub_type`, `class_id`) VALUES (?, ?, ?, ?, ?, ?)")) {
                    // Assuming $data is an associative array containing form values
                    $stmt->bind_param("issssi", $data['subject_sno'], $data['subcode'], $data['sub_shortname'], $data['sub_fullname'], $data['sub_type'], $data['class_id']);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_SUBJECT", "Added Subject " . ($data['subcode'] ?? '') . " (" . ($data['sub_shortname'] ?? '') . ") for Class ID: " . ($data['class_id'] ?? ''), "hod", "SUBJECT", (string)($data['subcode'] ?? ''));
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $stmt = $this->conn->prepare("SELECT s.id FROM subjects s WHERE s.class_id = ? AND s.subcode = ?");
            $stmt->bind_param("is", $data['class_id'], $data['subcode']);
            if ($stmt->execute()) {
                $stmt->bind_result($id);
                while ($stmt->fetch()) {
                    $res["err"] = "Subject : " . $data['subcode'] . " Already Present for this class";
                }
            }
        }

        return $res;
    }

    public function deleteSubjectByID($subject_id)
    {
        $myname = $this->classname . " - deleteSubjectByID - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("SELECT id FROM attendance WHERE sub_id = ?");
                $stmt->bind_param("i", $subject_id);
                if ($stmt->execute()) {
                    $stmt->bind_result($id);
                    while ($stmt->fetch()) {
                        $res["err"] = "Subject Can NOT be deleted. Attendance is Added";
                        return $res;
                    }
                }
                unset($stmt);

                if ($stmt = $this->conn->prepare("DELETE FROM `subjects` WHERE `id`=?")) {
                    $stmt->bind_param("i", $subject_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_SUBJECT", "Deleted Subject ID $subject_id", "hod", "SUBJECT", (string)$subject_id);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function getFacSubMapByClassID($class_id)
    {
        $myname = $this->classname . " - getFacSubMapByClassID - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("
                SELECT fs.id, s.subject_sno, u.name AS faculty_name, f.facultyid, s.sub_fullname, s.subcode
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN subjects s ON fs.sub_id = s.id
                JOIN users u ON f.username = u.username
                WHERE s.class_id = ? order by s.subject_sno + 0;
            ")) { // Corrected the JOIN with users table
                    $stmt->bind_param("i", $class_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($map_id, $subject_sno, $faculty_name, $facultyid, $sub_fullname, $subcode);
                        while ($stmt->fetch()) {
                            $res['data'][] = array(
                                'map_id' => $map_id,
                                'subject_sno' => $subject_sno,
                                'faculty_name' => $faculty_name,
                                'facultyid' => $facultyid,
                                'sub_fullname' => $sub_fullname,
                                'subcode' => $subcode
                            );
                        }
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function getFacultyByClassID($class_id)
    {
        $myname = $this->classname . " - getFacultyByClassID - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $query = "
                    SELECT DISTINCT f.id, f.facultyid, f.username, u.name AS faculty_name, u.email, f.designation
                    FROM faculty_sub fs
                    JOIN faculties f ON fs.faculty_id = f.id
                    JOIN subjects s ON fs.sub_id = s.id
                    JOIN users u ON f.username = u.username
                    WHERE s.class_id = ?
                    ORDER BY u.name ASC
                ";
                if ($stmt = $this->conn->prepare($query)) {
                    $stmt->bind_param("i", $class_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function addFacultySubjectMapping($faculty_id, $sub_id)
    {
        $myname = $this->classname . " - addFacultySubjectMapping - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("SELECT id FROM faculty_sub WHERE `faculty_id` = ? AND `sub_id` = ?");
                $stmt->bind_param("ii", $faculty_id, $sub_id);
                if ($stmt->execute()) {
                    $stmt->bind_result($id);
                    while ($stmt->fetch()) {
                        $res["err"] = "Faculty Already Mapped to the Subject";
                        return $res;
                    }
                }
                unset($stmt);

                if ($stmt = $this->conn->prepare("INSERT INTO `faculty_sub` (`faculty_id`, `sub_id`) VALUES (?, ?)")) {
                    $stmt->bind_param("ii", $faculty_id, $sub_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "MAP_FACULTY_SUBJECT", "Mapped Faculty ID $faculty_id to Subject ID $sub_id", "hod", "FACULTY_SUBJECT", (string)$sub_id, $faculty_id);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function unMapFacSubMapByID($map_id)
    {
        $myname = $this->classname . " - unMapFacSubMapByID - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("DELETE FROM `faculty_sub` WHERE id=?")) {
                    $stmt->bind_param("i", $map_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "UNMAP_FACULTY_SUBJECT", "Unmapped Faculty Subject Mapping ID $map_id", "hod", "FACULTY_SUBJECT", (string)$map_id);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function getMappedStudents($sub_id)
    {
        $myname = $this->classname . " - getMappedStudents - ";
        $students = [];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("
                SELECT s.id, u.name, s.username 
                FROM student_sub ss
                JOIN students s ON ss.stu_id = s.id
                JOIN users u ON s.username = u.username
                WHERE ss.sub_id = ? AND s.status = 1 ORDER BY s.username
            ")) {
                    $stmt->bind_param("i", $sub_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $name, $username);
                        while ($stmt->fetch()) {
                            $students[] = array(
                                'id' => $id,
                                'name' => $name,
                                'username' => $username
                            );
                        }
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $students;
    }

    public function getUnmappedStudents($sub_id)
    {
        $myname = $this->classname . " - getUnmappedStudents - ";
        $students = [];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("
                    SELECT s.id, u.name, s.username
                    FROM students s
                    JOIN users u ON s.username = u.username
                    WHERE s.class_id = (
                        SELECT class_id FROM subjects WHERE id = ?
                    )
                    AND s.id NOT IN (
                        SELECT stu_id FROM student_sub
                        WHERE sub_id IN (
                            SELECT id FROM subjects
                            WHERE subject_sno = (SELECT subject_sno FROM subjects WHERE id = ?)
                        )
                    )
                    ORDER BY s.username;
                ")) {
                    $stmt->bind_param("ii", $sub_id, $sub_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $name, $username);
                        while ($stmt->fetch()) {
                            $students[] = array(
                                'id' => $id,
                                'name' => $name,
                                'username' => $username
                            );
                        }
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $students;
    }


    public function addStudentSubjectMapping($stu_id, $sub_id)
    {
        $myname = $this->classname . " - addStudentSubjectMapping - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("INSERT INTO `student_sub` (`stu_id`, `sub_id`) VALUES (?, ?)")) {
                    $stmt->bind_param("ii", $stu_id, $sub_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "MAP_STUDENT_SUBJECT", "Mapped Student ID $stu_id to Subject ID $sub_id", "hod", "STUDENT_SUBJECT", (string)$sub_id, $stu_id);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function unmapStudentSubjectMapping($stu_id, $sub_id)
    {
        $myname = $this->classname . " - unmapStudentSubjectMapping - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("DELETE FROM `student_sub` WHERE `stu_id` = ? AND `sub_id` = ?")) {
                    $stmt->bind_param("ii", $stu_id, $sub_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $this->dbActivityLog($_SESSION['userid'] ?? 0, "UNMAP_STUDENT_SUBJECT", "Unmapped Student ID $stu_id from Subject ID $sub_id", "hod", "STUDENT_SUBJECT", (string)$sub_id, $stu_id);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }


    // Function to get faculty details
    private function getFacNamesBySubID($sub_id)
    {
        $res = ['status' => 0, 'data' => array()]; // Initialize data as array
        $myname = $this->classname . " - getFacDetailsBySubID - ";

        try {
            $stmt = $this->conn->prepare("
                SELECT u.name AS faculty_name
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN users u ON f.username = u.username
                WHERE fs.sub_id = ?
            ");
            if (!$stmt) {
                throw new Exception("Failed to prepare class, subject, and faculty details query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $stmt->bind_result($faculty_name);
                while ($stmt->fetch()) {
                    array_push($res["data"], $faculty_name);
                    $res['status'] = 1;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    // Function to get class, subject, and faculty details
    public function getClsSubFacBySubID($sub_id)
    {
        $res = ['status' => 0, 'data' => null]; // Initialize data as null
        $myname = $this->classname . " - getClsSubFacBySubID - ";

        try {
            $stmt = $this->conn->prepare("
                SELECT sub.sub_fullname, sub.subcode, sub.sub_type, c.classname, c.acad_year, c.yearsem, c.section, c.start_date, c.end_date, c.reg_id
                FROM subjects sub
                JOIN classes c ON sub.class_id = c.id
                WHERE sub.id = ?
            ");
            if (!$stmt) {
                throw new Exception("Failed to prepare class, subject, and faculty details query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $stmt->bind_result($sub_fullname, $subcode, $sub_type, $classname, $acad_year, $yearsem, $section, $start_date, $end_date, $reg_id);
                if ($stmt->fetch()) {
                    $res['data'] = [
                        'sub_fullname' => $sub_fullname,
                        'subcode' => $subcode,
                        'sub_type' => $sub_type,
                        'classname' => $classname,
                        'acad_year' => $acad_year,
                        'yearsem' => $yearsem,
                        'section' => $section,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'reg_id' => $reg_id
                    ];
                    $res['status'] = 1;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();

            if ($res['data'] !== null) {
                $facnames = $this->getFacNamesBySubID($sub_id);
                if (empty($facnames["data"])) {
                    $dStmt = $this->conn->prepare("
                        SELECT DISTINCT u.name 
                        FROM diary d
                        JOIN faculties f ON d.faculty_id = f.id
                        JOIN users u ON f.username = u.username
                        WHERE d.sub_id = ?
                        ORDER BY d.id DESC
                    ");
                    if ($dStmt) {
                        $dStmt->bind_param("i", $sub_id);
                        if ($dStmt->execute()) {
                            $dRes = $dStmt->get_result();
                            while ($dRow = $dRes->fetch_assoc()) {
                                if (!empty($dRow['name'])) {
                                    $facnames["data"][] = trim($dRow['name']);
                                }
                            }
                        }
                        $dStmt->close();
                    }
                }
                $facStr = implode(', ', array_unique($facnames["data"]));
                $res['data']['faculty_name'] = !empty($facStr) ? $facStr : 'N/A';
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function getHourDescById($hour_id)
    {
        $res = "";
        $myname = $this->classname . " - getHourDescById - ";

        try {
            $stmt = $this->conn->prepare("SELECT hour_desc FROM `class_timings` WHERE id=?");
            $stmt->bind_param("i", $hour_id);
            if ($stmt->execute()) {
                $stmt->bind_result($hour_desc);
                while ($stmt->fetch()) {
                    $res = $hour_desc;
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Error retrieving hours: " . $e->getMessage());
        }

        return $res;
    }

    public function getDeleteRequestsByDepartment($dept_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getDeleteRequestsByDepartment - ";

        try {
            $stmt = $this->conn->prepare("
            SELECT aur.id, f.username AS faculty_name, s.sub_fullname AS subject, aur.date, aur.hour, aur.reason, aur.status, aur.request_date, aur.approval_date
            FROM attendance_delete_requests aur
            JOIN faculties f ON aur.faculty_id = f.id
            JOIN subjects s ON aur.subject_id = s.id
            JOIN departments d ON f.dept_id = d.id
            WHERE d.id = ?
            ORDER BY aur.request_date DESC
        ");
            $stmt->bind_param("i", $dept_id);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $faculty_name, $subject, $date, $hour, $reason, $status, $request_date, $approval_date);
                while ($stmt->fetch()) {
                    $res['data'][] = [
                        'id' => $id,
                        'faculty_name' => $faculty_name,
                        'subject' => $subject,
                        'date' => $date,
                        'hour' => $hour,
                        'reason' => $reason,
                        'status' => $status,
                        'approval_date' => $approval_date,
                        'request_date' => $request_date
                    ];
                }
                $res['status'] = 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Error fetching delete requests: " . $e->getMessage());
        }

        return $res;
    }

    public function processDeleteRequest($request_id, $action)
    {
        require_once __DIR__ . '/core/AttendanceCorrection.php';
        require_once __DIR__.'/services/FeatureManager.php';
        if(!\FeatureManager::canAction('MOD_ATT_REQUESTS','approve')){return ['status'=>0,'err'=>'Attendance review is disabled for your account.'];}
        try {
            return (new \College\AttendanceCorrection($this->conn, $_SESSION ?? []))->decide((int)$request_id, (string)$action);
        } catch (\Throwable $e) {
            $this->logs->errLog('Attendance correction review failed: ' . $e->getMessage());
            return ['status'=>0,'err'=>$e->getMessage()];
        }
    }

    /**
     * Fetch institutional audit logs for attendance deletion governance (NAAC Metric 6.2).
     */
    public function getAttendanceDeleteAuditLogs(?int $dept_id = null): array
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getAttendanceDeleteAuditLogs - ";

        try {
            $sql = "
                SELECT a.id, a.user_id, a.username, a.role, a.action, a.entity_type, a.entity_id, 
                       a.status, a.details, a.ip_address, a.created_at, u.name as actor_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE (a.action LIKE '%ATTENDANCE_DELETE%' OR a.action = 'PURGE_ATTENDANCE_ENTRIES' OR a.entity_type = 'ATTENDANCE_REQUEST')
                AND EXISTS (SELECT 1 FROM attendance_delete_requests ar JOIN subjects s ON s.id=ar.subject_id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id WHERE (a.entity_type='ATTENDANCE_REQUEST' AND a.entity_id=CAST(ar.id AS CHAR) OR a.entity_type='ATTENDANCE' AND a.entity_id=CAST(s.id AS CHAR)) AND d.username=?)
                ORDER BY a.created_at DESC LIMIT 100
            ";
            $stmt = $this->conn->prepare($sql);
            $actor = $_SESSION['user'] ?? '';
            $stmt->bind_param('s', $actor);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Execution failed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }



    // Function to get average CO feedback ratings for a single subject (across all classes)
    public function getAverageCOFeedbackBySubject($subject_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getAverageCOFeedbackBySubject - ";

        $query = "
            SELECT
                co.co_number,
                co.co_description,
                AVG(scf.rating) AS average_rating,
                COUNT(DISTINCT scf.student_id) AS total_responses
            FROM
                student_co_feedback scf
            JOIN
                course_outcomes co ON scf.co_id = co.id
            WHERE
                scf.subject_id = ?
            GROUP BY
                co.co_number, co.co_description
            ORDER BY
                co.co_number;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare average CO feedback query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $subject_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['data'];
    }

    // Function to get average additional questionnaire feedback ratings for a single subject (across all classes)
    public function getAverageQuestionnaireFeedbackBySubject($subject_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getAverageQuestionnaireFeedbackBySubject - ";

        $query = "
            SELECT
                sqq.question_number,
                sqq.question_text,
                sqq.created_by_role,
                AVG(sqr.rating) AS average_rating,
                COUNT(DISTINCT sqr.student_id) AS total_responses
            FROM
                student_questionnaire_responses sqr
            JOIN
                subject_questionnaire_questions sqq ON sqr.question_id = sqq.id
            WHERE
                sqr.subject_id = ?
            GROUP BY
                sqq.question_number, sqq.question_text, sqq.created_by_role
            ORDER BY
                sqq.question_number;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare average questionnaire feedback query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $subject_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['data'];
    }

    // Function to get average CO feedback ratings for all subjects in a class
    public function getAverageCOFeedbackByClass($class_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getAverageCOFeedbackByClass - ";

        $query = "
            SELECT
                s.subcode,
                s.sub_fullname,
                co.co_number,
                co.co_description,
                AVG(scf.rating) AS average_rating,
                COUNT(DISTINCT scf.student_id) AS total_responses
            FROM
                student_co_feedback scf
            JOIN
                course_outcomes co ON scf.co_id = co.id
            JOIN
                subjects s ON scf.subject_id = s.id
            JOIN
                students stu ON scf.student_id = stu.id
            WHERE
                stu.class_id = ?
            GROUP BY
                s.subcode, s.sub_fullname, co.co_number, co.co_description
            ORDER BY
                s.subcode, co.co_number;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare average CO feedback by class query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $class_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['data'];
    }

    // Function to get average additional questionnaire feedback ratings for all subjects in a class
    public function getAverageQuestionnaireFeedbackByClass($class_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getAverageQuestionnaireFeedbackByClass - ";

        $query = "
            SELECT
                s.subcode,
                s.sub_fullname,
                sqq.question_number,
                sqq.question_text,
                sqq.created_by_role,
                AVG(sqr.rating) AS average_rating,
                COUNT(DISTINCT sqr.student_id) AS total_responses
            FROM
                student_questionnaire_responses sqr
            JOIN
                subject_questionnaire_questions sqq ON sqr.question_id = sqq.id
            JOIN
                subjects s ON sqr.subject_id = s.id
            JOIN
                students stu ON sqr.student_id = stu.id
            WHERE
                stu.class_id = ?
            GROUP BY
                s.subcode, s.sub_fullname, sqq.question_number, sqq.question_text, sqq.created_by_role
            ORDER BY
                s.subcode, sqq.question_number;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare average questionnaire feedback by class query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $class_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['data'];
    }


    // Function to get the total number of students enrolled in a subject for a specific class
    public function getTotalStudentsInSubjectForClass($subject_id, $class_id)
    {
        $res = ['status' => 0, 'count' => 0];
        $myname = $this->classname . " - getTotalStudentsInSubjectForClass - ";

        $query = "
            SELECT COUNT(DISTINCT ss.stu_id)
            FROM student_sub ss
            JOIN students s ON ss.stu_id = s.id
            WHERE ss.sub_id = ? AND s.class_id = ?;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare total students query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subject_id, $class_id);

            if ($stmt->execute()) {
                $stmt->bind_result($count);
                if ($stmt->fetch()) {
                    $res['status'] = 1;
                    $res['count'] = $count;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['count']; // Return count directly
    }

    // Function to get the total number of students in a class
    public function getTotalStudentsInClass($class_id)
    {
        $res = ['status' => 0, 'count' => 0];
        $myname = $this->classname . " - getTotalStudentsInClass - ";

        $query = "
            SELECT COUNT(DISTINCT id)
            FROM students
            WHERE class_id = ?;
        ";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare total students in class query: " . $this->conn->error);
            }

            $stmt->bind_param("i", $class_id);

            if ($stmt->execute()) {
                $stmt->bind_result($count);
                if ($stmt->fetch()) {
                    $res['status'] = 1;
                    $res['count'] = $count;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res['count']; // Return count directly
    }

    public function updateSubjectCode($subject_id, $new_subcode, $class_id)
    {
        $myname = $this->classname . " - updateSubjectCode - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                // Check if the new subcode already exists for this class
                $stmt = $this->conn->prepare("SELECT id FROM subjects WHERE class_id = ? AND subcode = ? AND id != ?");
                $stmt->bind_param("isi", $class_id, $new_subcode, $subject_id);

                if ($stmt->execute()) {
                    $stmt->bind_result($existing_id);
                    if ($stmt->fetch()) {
                        $res["err"] = "Subject code '" . $new_subcode . "' already exists for this class.";
                        $stmt->close();
                        return $res;
                    }
                    $stmt->close();
                }

                // Update the subcode
                if ($stmt = $this->conn->prepare("UPDATE subjects SET subcode = ? WHERE id = ?")) {
                    $stmt->bind_param("si", $new_subcode, $subject_id);

                    if ($stmt->execute()) {
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                        $res["err"] = "Failed to update subject code.";
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Not prepared: " . $this->conn->error);
                    $res["err"] = "Database error.";
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
                $res["err"] = "Connection error.";
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res["err"] = "An error occurred.";
        }

        return $res;
    }

    public function getSubjectsWithBuildingHall($class_id)
    {
        $myname = $this->classname . " - getSubjectsWithBuildingHall - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                    SELECT s.id, s.subject_sno, s.subcode, s.sub_shortname, s.sub_fullname, s.sub_type,
                           t.building_name, t.class_hall_name
                    FROM subjects s
                    LEFT JOIN class_timetables t ON s.id = t.subject_id AND t.class_id = ?
                    WHERE s.class_id = ?
                    GROUP BY s.id
                    ORDER BY s.subject_sno + 0, s.subcode
                ");
                $stmt->bind_param("ii", $class_id, $class_id);
                if ($stmt->execute()) {
                    $stmt->bind_result($id, $subject_sno, $subcode, $sub_shortname, $sub_fullname, $sub_type, $building_name, $hall_name);
                    while ($stmt->fetch()) {
                        $res['data'][] = [
                            'id' => $id,
                            'subject_sno' => $subject_sno,
                            'subcode' => $subcode,
                            'sub_shortname' => $sub_shortname,
                            'sub_fullname' => $sub_fullname,
                            'sub_type' => $sub_type,
                            'building_name' => $building_name,
                            'hall_name' => $hall_name
                        ];
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
                $stmt->close();
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function updateTimetableBuildingHall($subject_id, $class_id, $building_name, $hall_name)
    {
        $myname = $this->classname . " - updateTimetableBuildingHall - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("UPDATE class_timetables SET building_name = ?, class_hall_name = ? WHERE subject_id = ? AND class_id = ?");
                $stmt->bind_param("ssii", $building_name, $hall_name, $subject_id, $class_id);

                if ($stmt->execute()) {
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    $res["err"] = "Failed to update building and hall.";
                }
                $stmt->close();
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
                $res["err"] = "Connection error.";
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res["err"] = "An error occurred.";
        }

        return $res;
    }

    public function getAllActiveBuildings()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, building_name FROM buildings WHERE status = 1 ORDER BY building_name");
            if ($stmt->execute()) {
                $stmt->bind_result($id, $building_name);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'building_name' => $building_name];
                }
                $res['status'] = 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getAllActiveBuildings - " . $e->getMessage());
        }
        return $res;
    }

    public function getActiveHallsByBuilding($building_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, hall_name FROM halls WHERE building_id = ? AND status = 1 ORDER BY hall_name");
            $stmt->bind_param("i", $building_id);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $hall_name);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'hall_name' => $hall_name];
                }
                $res['status'] = 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getActiveHallsByBuilding - " . $e->getMessage());
        }
        return $res;
    }

    public function getBuildingsWithHalls()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $buildings = $this->getAllActiveBuildings();
            if ($buildings['status'] === 1) {
                foreach ($buildings['data'] as $building) {
                    $halls = $this->getActiveHallsByBuilding($building['id']);
                    $res['data'][$building['building_name']] = [];
                    if ($halls['status'] === 1) {
                        foreach ($halls['data'] as $hall) {
                            $res['data'][$building['building_name']][] = $hall['hall_name'];
                        }
                    }
                }
                $res['status'] = 1;
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getBuildingsWithHalls - " . $e->getMessage());
        }
        return $res;
    }

    public function getActiveAcademicYear()
    {
        $res = '';
        try {
            $stmt = $this->conn->prepare(
                "SELECT acad_year FROM academic_years WHERE is_active = 1 LIMIT 1"
            );
            if (!$stmt) throw new Exception($this->conn->error);
            $stmt->execute();
            $stmt->bind_result($res);
            $stmt->fetch();
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog('getActiveAcademicYear: ' . $e->getMessage());
        }
        return $res;
    }

    public function getDetailedAttendanceBySubjectExcludingPermissions($sub_id, $start_date = null, $end_date = null, $includeInactive = false)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $this->assertSubjectScope((int)$sub_id);
            require_once __DIR__ . '/core/DateRange.php';
            $start_date = $start_date === '' ? null : $start_date;
            $end_date = $end_date === '' ? null : $end_date;
            \College\DateRange::validate($start_date, $end_date);
            $dateHours  = [];
            $hours      = [];
            $dateFilter = "";

            if ($start_date && $end_date) {
                $dateFilter = "AND a.date BETWEEN ? AND ?";
            }

            // ── Query 1: Distinct date-hour column headers ────────────────────────
            // Not filtered by permission — permission slots intentionally show as '-'
            // per student in the detailed view (column header still exists for others)
            if ($stmt = $this->conn->prepare("
            SELECT DISTINCT a.date, ct.id, ct.hour
            FROM attendance a
            LEFT JOIN class_timings ct ON a.hour = ct.id
            WHERE a.sub_id = ?
            $dateFilter
            ORDER BY a.date, ct.id
        ")) {
                if ($start_date !== null) { $stmt->bind_param("iss", $sub_id, $start_date, $end_date); }
                else { $stmt->bind_param("i", $sub_id); }
                if ($stmt->execute()) {
                    $stmt->bind_result($date, $hour_id, $hour);
                    while ($stmt->fetch()) {
                        $dateHours[]                        = $date . '-' . $hour_id . '-' . $hour;
                        $hours[$date . '-' . $hour_id]      = $hour;
                    }
                } else {
                    $this->logs->errLog("Failed to fetch date-hour combinations: " . $this->conn->error);
                    return $res;
                }
                $stmt->close(); // ← MUST close before next prepare()
            }

            // ── Query 2: Per-student attendance, permission hours excluded ─────────
            $statusClause = $includeInactive ? "" : "AND s.status = 1";
            if ($stmt = $this->conn->prepare("
            SELECT u.name, s.username, a.date, a.hour, a.status, s.status as student_status
            FROM student_sub ss
            JOIN students  s  ON ss.stu_id   = s.id
            JOIN users     u  ON s.username  = u.username
            RIGHT JOIN attendance a
                ON  ss.stu_id = a.stu_id
                AND a.sub_id  = ?
            LEFT JOIN student_permissions sp
                ON  sp.stu_id = s.id
                AND sp.date   = a.date
                AND sp.hour   = a.hour
            WHERE ss.sub_id = ?
              $statusClause
              AND sp.id     IS NULL
              AND (a.date >= COALESCE(
                      s.date_of_joining,
                      (SELECT j.doj FROM temp_joiningdates j WHERE j.htno = s.username),
                      '1900-01-01'
                  ))
            $dateFilter
            ORDER BY s.username
        ")) {
                if ($start_date !== null) { $stmt->bind_param("iiss", $sub_id, $sub_id, $start_date, $end_date); }
                else { $stmt->bind_param("ii", $sub_id, $sub_id); }
                if ($stmt->execute()) {
                    $stmt->bind_result($name, $username, $date, $hour, $status, $studentStatus);

                    $students = [];
                    while ($stmt->fetch()) {
                        $key = $date . '-' . $hour;
                        $dateHourKey = $key . '-' . (!empty($hours[$key]) ? $hours[$key] : '');

                        if (!isset($students[$username])) {
                            $students[$username] = [
                                'name'           => $name,
                                'username'       => $username,
                                'student_status' => (int)$studentStatus,
                                'attendance'     => array_fill_keys($dateHours, '-'),
                                'total_present'  => 0,
                                'total_classes'  => 0
                            ];
                        }

                        $students[$username]['attendance'][$dateHourKey] = $status;
                        $students[$username]['total_classes']++;
                        if ($status == 'P') {
                            $students[$username]['total_present']++;
                        }
                    }

                    foreach ($students as &$student) {
                        $student['percentage'] = $student['total_classes'] > 0
                            ? round(($student['total_present'] / $student['total_classes']) * 100, 2)
                            : 0;
                    }

                    $res['data']   = array_values($students);
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog("Statement not executed: " . $this->conn->error);
                }
                $stmt->close();
            } else {
                $this->logs->errLog("Not prepared: " . $this->conn->error);
            }
        } catch (Exception $e) {
            $res['err'] = $e->getMessage();
            $this->logs->errLog("Exception: " . $e->getMessage());
        }
        return $res;
    }
}

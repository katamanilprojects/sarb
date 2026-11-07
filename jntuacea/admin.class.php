<?php
require_once("user.class.php");
require_once("logs.class.php");

class Admin extends User
{
    private $classname = "Admin";

    // Constructor to initialize the Faculty object
    public function __construct()
    {
        parent::__construct(); // Call the parent constructor
    }

    public function updatePwd($userId, $oldpassword, $newpassword)
    {
        $res = ['status' => 0];
        $myname = $this->classname . " - updatePwd - ";
        $authenticate = $this->verifyPwd($userId, $oldpassword);
        if ($authenticate) {
            $res = $this->updatePassword($userId, $newpassword, "admin");
        } else {
            $res["err"] = "Incorrect Existing Password";
        }
        return $res;
    }

    /**
     * Get student details by username/roll number
     * @param string $username - Student roll number
     * @return array - Student details or error
     */
    public function getStudentByUsername($username)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT s.id, u.username, u.name, u.email, u.mobile, u.status, u.role
                     FROM students s
                     JOIN users u ON s.username = u.username
                     WHERE u.username = ? AND u.role = 'student'"
                );
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $uusername, $name, $email, $mobile, $status, $role);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $uusername,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'status' => $status,
                                'role' => $role
                            ];
                            $res['status'] = 1;
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getStudentByUsername - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get faculty details by username
     * @param string $username - Faculty username
     * @return array - Faculty details or error
     */
    public function getFacultyByUsername($username)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT f.id, u.username, u.name, u.email, u.mobile, f.designation, u.status, u.role
                     FROM faculties f
                     JOIN users u ON f.username = u.username
                     WHERE u.username = ? AND u.role = 'faculty'"
                );
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $uusername, $name, $email, $mobile, $designation, $status, $role);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $uusername,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'designation' => $designation,
                                'status' => $status,
                                'role' => $role
                            ];
                            $res['status'] = 1;
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getFacultyByUsername - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Wrapper: Reset student password with remarks logging
     * @param string $username - Student roll number
     * @param string $remarks - Optional remarks for audit log
     * @return array - Status and result
     */
    public function resetStudentPwdWithRemarks($username, $remarks = "")
    {
        $myname = $this->classname . " - resetStudentPwdWithRemarks - ";
        $res = ['status' => 0];
        try {
            if (!empty($this->conn)) {
                // Hash password in PHP using bcrypt
                $new_password = password_hash($username, PASSWORD_BCRYPT);
                
                $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE username = ? AND role = 'student'");
                if ($stmt) {
                    $stmt->bind_param("ss", $new_password, $username);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        
                        // Log activity with remarks
                        $details = "Student password reset for username: " . $username;
                        if (!empty($remarks)) {
                            $details .= " | Remarks: " . $remarks;
                        }
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "RESET_STUDENT_PASSWORD", $details, "admin", "STUDENT", $username);
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Wrapper: Reset faculty password with remarks logging
     * @param int $faculty_id - Faculty ID
     * @param string $remarks - Optional remarks for audit log
     * @return array - Status and result
     */
    public function resetFacPwdWithRemarks($faculty_id, $remarks = "")
    {
        $myname = $this->classname . " - resetFacPwdWithRemarks - ";
        $res = ['status' => 0];
        try {
            if (!empty($this->conn)) {
                // Step 1: Fetch faculty mobile number
                $fetch_stmt = $this->conn->prepare(
                    "SELECT u.mobile, u.username FROM users u 
                     JOIN faculties f ON u.username = f.username 
                     WHERE f.id = ?"
                );
                if ($fetch_stmt) {
                    $fetch_stmt->bind_param("i", $faculty_id);
                    $fetch_stmt->execute();
                    $fetch_stmt->bind_result($mobile, $username);
                    if ($fetch_stmt->fetch()) {
                        $fetch_stmt->close();
                        
                        // Step 2: Hash password in PHP using bcrypt
                        $new_password = password_hash($mobile, PASSWORD_BCRYPT);
                        
                        // Step 3: Update with bcrypt hash
                        $stmt = $this->conn->prepare(
                            "UPDATE users u 
                             JOIN faculties f ON u.username = f.username
                             SET u.password = ? WHERE f.id = ?"
                        );
                        if ($stmt) {
                            $stmt->bind_param("si", $new_password, $faculty_id);
                            if ($stmt->execute()) {
                                $res['status'] = 1;
                                
                                // Log activity with remarks
                                $details = "Faculty password reset for username: " . $username;
                                if (!empty($remarks)) {
                                    $details .= " | Remarks: " . $remarks;
                                }
                                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                                $this->dbActivityLog($adminId, "RESET_FACULTY_PASSWORD", $details, "admin", "FACULTY", (string)$faculty_id);
                            } else {
                                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        $fetch_stmt->close();
                        $this->logs->errLog($myname . "Faculty not found: " . $faculty_id);
                    }
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function getAllDepartments()
    {
        $res = ['status' => 0];

        try {
            $stmt = $this->conn->prepare("SELECT id, dept_shortname, dept_fullname, status FROM departments where status=1");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllDepartments: " . $e->getMessage());
            $res['err'] = "Failed to fetch departments.";
        }

        return $res;
    }

    // In admin.class.php

    /**
     * Ensure sort_order column exists in faculties table and initialize if missing
     */
    private function ensureSortOrderColumn()
    {
        static $checked = false;
        if ($checked || empty($this->conn)) {
            return;
        }
        $checked = true;
        try {
            $check = $this->conn->query("SHOW COLUMNS FROM faculties LIKE 'sort_order'");
            if ($check && $check->num_rows === 0) {
                $this->conn->query("ALTER TABLE faculties ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER dept_id");
                $this->conn->query("CREATE INDEX idx_faculties_dept_sort ON faculties (dept_id, sort_order)");
                // Seed initial sequential order
                $this->conn->query("UPDATE faculties f JOIN (SELECT f2.id, ROW_NUMBER() OVER (PARTITION BY f2.dept_id ORDER BY u.status DESC, FIELD(f2.designation, 'Professor', 'Assoc. Professor', 'Asst. Professor', 'Asst. Professor (Adhoc)', 'Guest Faculty') ASC, f2.id ASC) AS initial_order FROM faculties f2 JOIN users u ON f2.username = u.username) ordered ON f.id = ordered.id SET f.sort_order = ordered.initial_order");
            }
        } catch (Exception $e) {
            // Suppress schema query error if already migrated or restricted
        }
    }

    public function getFacultyByDepartment($dept_id)
    {
        $myname = "Admin - getFacultyByDepartment - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $this->ensureSortOrderColumn();
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
                    // Fallback to legacy query if sort_order column is not present
                    $stmt = $this->conn->prepare(
                        "SELECT f.id, f.username, u.name, u.mobile, u.email, f.designation, u.status, 0 AS sort_order
                         FROM faculties f
                         JOIN users u ON f.username = u.username
                         WHERE f.dept_id = ? 
                         ORDER BY u.status DESC,
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

    // In admin.class.php

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

    // In admin.class.php
    public function addFaculty($data)
    {
        $myname = "Admin - addFaculty - ";
        $res = ['status' => 0];
        $this->conn->begin_transaction();

        try {
            if (!empty($this->conn)) {
                // Insert into users table
                if ($stmt1 = $this->conn->prepare(
                    "INSERT INTO users (username, password, name, mobile, email, role, status) 
                 VALUES (?, ?, ?, ?, ?, 'faculty', 1)"
                )) {
                    $password = password_hash($data['mobile'], PASSWORD_BCRYPT);
                    $stmt1->bind_param("sssss", $data['username'], $password, $data['name'], $data['mobile'], $data['email']);

                    if ($stmt1->execute()) {
                        $facultyid = $this->conn->insert_id;

                        // Calculate next sort order if not provided
                        $sort_order = !empty($data['sort_order']) ? (int)$data['sort_order'] : 0;
                        if ($sort_order <= 0) {
                            $next_order = 1;
                            if ($order_stmt = $this->conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM faculties WHERE dept_id = ?")) {
                                $order_stmt->bind_param("i", $data['dept_id']);
                                if ($order_stmt->execute()) {
                                    $order_stmt->bind_result($calc_order);
                                    if ($order_stmt->fetch() && !empty($calc_order)) {
                                        $next_order = (int)$calc_order;
                                    }
                                }
                                $order_stmt->close();
                            }
                            $sort_order = $next_order;
                        }

                        // Insert into faculties table with sort_order
                        if ($stmt2 = $this->conn->prepare(
                            "INSERT INTO faculties (username, designation, facultyid, dept_id, sort_order, status)
                         VALUES (?, ?, ?, ?, ?, 1)"
                        )) {
                            $stmt2->bind_param("ssiii", $data['username'], $data['designation'], $facultyid, $data['dept_id'], $sort_order);
                            if ($stmt2->execute()) {
                                $this->conn->commit();
                                $res['status'] = 1;
                                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                                $this->dbActivityLog($adminId, "ADD_FACULTY", "Added faculty {$data['name']} ({$data['username']}) with order $sort_order", "admin", "FACULTY", (string)$facultyid);
                            } else {
                                $this->logs->errLog($myname . "Faculty insertion failed: " . $this->conn->error);
                                $this->conn->rollback();
                            }
                            $stmt2->close();
                        } else {
                            $this->logs->errLog($myname . "Faculty preparation failed: " . $this->conn->error);
                            $this->conn->rollback();
                        }
                    } else {
                        $this->logs->errLog($myname . "User insertion failed: " . $this->conn->error);
                        $this->conn->rollback();
                    }
                    $stmt1->close();
                } else {
                    $this->logs->errLog($myname . "User preparation failed: " . $this->conn->error);
                    $this->conn->rollback();
                }
            } else {
                $this->logs->errLog($myname . "Connection error or error flag set.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $this->conn->rollback();
        }

        return $res;
    }
    // In admin.class.php

    public function updateFacultyDetails($faculty_id, $data)
    {
        $myname = "Admin - updateFacultyDetails - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                $hasSortOrder = isset($data['sort_order']) && $data['sort_order'] !== '';
                $sql = $hasSortOrder
                    ? "UPDATE users u 
                       JOIN faculties f ON u.username = f.username
                       SET u.name = ?, u.email = ?, u.mobile = ?, f.designation = ?, u.status = ?, f.sort_order = ?
                       WHERE f.id = ?"
                    : "UPDATE users u 
                       JOIN faculties f ON u.username = f.username
                       SET u.name = ?, u.email = ?, u.mobile = ?, f.designation = ?, u.status = ?
                       WHERE f.id = ?";

                if ($stmt = $this->conn->prepare($sql)) {
                    if ($hasSortOrder) {
                        $sort_order = (int)$data['sort_order'];
                        $stmt->bind_param("ssssiii", $data['name'], $data['email'], $data['mobile'], $data['designation'], $data['status'], $sort_order, $faculty_id);
                    } else {
                        $stmt->bind_param("ssssii", $data['name'], $data['email'], $data['mobile'], $data['designation'], $data['status'], $faculty_id);
                    }
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "UPDATE_FACULTY", "Updated faculty ID $faculty_id ({$data['name']})", "admin", "FACULTY", (string)$faculty_id);
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

    /**
     * Update sequential display order for all faculties in a department
     *
     * @param int $dept_id
     * @param array $orders Associative array [faculty_id => sort_order]
     * @return array Status and message
     */
    public function updateFacultyOrder($dept_id, array $orders)
    {
        $myname = "Admin - updateFacultyOrder - ";
        $res = ['status' => 0, 'msg' => ''];

        $dept_id = (int)$dept_id;
        if ($dept_id <= 0 || empty($orders)) {
            $res['msg'] = 'Invalid department or empty order list.';
            return $res;
        }

        $this->conn->begin_transaction();
        try {
            if ($stmt = $this->conn->prepare("UPDATE faculties SET sort_order = ? WHERE id = ? AND dept_id = ?")) {
                foreach ($orders as $fac_id => $order_seq) {
                    $fac_id = (int)$fac_id;
                    $order_seq = (int)$order_seq;
                    if ($fac_id > 0 && $order_seq >= 0) {
                        $stmt->bind_param("iii", $order_seq, $fac_id, $dept_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update faculty #$fac_id: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
                $this->conn->commit();
                $res['status'] = 1;
                $res['msg'] = 'Faculty display order updated successfully.';

                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($adminId, "REORDER_FACULTY", "Updated faculty display order for department ID $dept_id", "admin", "DEPARTMENT", (string)$dept_id);
            } else {
                throw new Exception("Preparation failed: " . $this->conn->error);
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['msg'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Reset faculty order of a department to standard designation hierarchy
     *
     * @param int $dept_id
     * @return array Status and message
     */
    public function resetFacultyOrderToDefault($dept_id)
    {
        $myname = "Admin - resetFacultyOrderToDefault - ";
        $res = ['status' => 0, 'msg' => ''];

        $dept_id = (int)$dept_id;
        if ($dept_id <= 0) {
            $res['msg'] = 'Invalid department.';
            return $res;
        }

        try {
            $list_stmt = $this->conn->prepare(
                "SELECT f.id 
                 FROM faculties f
                 JOIN users u ON f.username = u.username
                 WHERE f.dept_id = ?
                 ORDER BY 
                     u.status DESC,
                     FIELD(f.designation, 
                         'Professor', 
                         'Assoc. Professor', 
                         'Asst. Professor', 
                         'Asst. Professor (Adhoc)',
                         'Guest Faculty'
                     ) ASC,
                     f.id ASC"
            );
            if ($list_stmt) {
                $list_stmt->bind_param("i", $dept_id);
                if ($list_stmt->execute()) {
                    $result = $list_stmt->get_result();
                    $orders = [];
                    $seq = 1;
                    while ($row = $result->fetch_assoc()) {
                        $orders[$row['id']] = $seq++;
                    }
                    $list_stmt->close();

                    if (!empty($orders)) {
                        return $this->updateFacultyOrder($dept_id, $orders);
                    }
                    $res['status'] = 1;
                    $res['msg'] = 'No faculties found to reorder.';
                } else {
                    throw new Exception("Execution failed: " . $list_stmt->error);
                }
            } else {
                throw new Exception("Preparation failed: " . $this->conn->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['msg'] = $e->getMessage();
        }

        return $res;
    }

    // In admin.class.php

    public function resetFacPwd($faculty_id)
    {
        $myname = "Admin - resetFacPwd - ";
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
                                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                                $this->dbActivityLog($adminId, "RESET_FACULTY_PASSWORD", "Reset password for faculty ID $faculty_id", "admin", "FACULTY", (string)$faculty_id);
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

    public function getHODDetailsByDeptID($dept_id)
    {
        $myname = "Admin - getHODDetailsByDeptID - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare(
                    "SELECT d.id, u.username, u.name, u.email, u.mobile, u.status
                 FROM departments d
                 JOIN users u ON d.username = u.username
                 WHERE d.id = ? and u.role='hod'"
                )) {
                    $stmt->bind_param("i", $dept_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $username, $name, $email, $mobile, $status);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $username,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'status' => $status
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


    public function updateHODDetails($dept_id, $data)
    {
        $myname = "Admin - updateHODDetails - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare(
                    "UPDATE users u 
                 JOIN departments d ON u.username = d.username
                 SET u.name = ?, u.email = ?, u.mobile = ?, u.status = ?
                 WHERE d.id = ? and role='hod'"
                )) {
                    $stmt->bind_param("sssii", $data['name'], $data['email'], $data['mobile'], $data['status'], $dept_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "UPDATE_HOD", "Updated HOD for dept ID $dept_id ({$data['name']})", "admin", "HOD", (string)$dept_id);
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

    // In admin.class.php

    public function resetHODPwd($dept_id)
    {
        $myname = "Admin - resetHODPwd - ";
        $res = ['status' => 0];

        try {
            if (!empty($this->conn)) {
                // Step 1: Fetch HOD mobile number
                if ($fetch_stmt = $this->conn->prepare(
                    "SELECT u.mobile FROM users u 
                     JOIN departments d ON u.username = d.username 
                     WHERE d.id = ? AND u.role = 'hod'"
                )) {
                    $fetch_stmt->bind_param("i", $dept_id);
                    $fetch_stmt->execute();
                    $fetch_stmt->bind_result($mobile);
                    if ($fetch_stmt->fetch()) {
                        $fetch_stmt->close();
                        
                        // Step 2: Hash password in PHP using bcrypt
                        $new_password = password_hash($mobile, PASSWORD_BCRYPT);
                        
                        // Step 3: Update with bcrypt hash
                        if ($stmt = $this->conn->prepare(
                            "UPDATE users u 
                             JOIN departments d ON u.username = d.username
                             SET u.password = ? WHERE d.id = ? AND role='hod'"
                        )) {
                            $stmt->bind_param("si", $new_password, $dept_id);
                            if ($stmt->execute()) {
                                $res['status'] = 1;
                                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                                $this->dbActivityLog($adminId, "RESET_HOD_PASSWORD", "Reset HOD password for dept ID $dept_id", "admin", "HOD", (string)$dept_id);
                            } else {
                                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        $fetch_stmt->close();
                        $this->logs->errLog($myname . "HOD not found for dept_id: " . $dept_id);
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
            $stmt = $this->conn->prepare("SELECT * FROM specialization WHERE dept_id = ? order by spec_code");
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
            $stmt = $this->conn->prepare("SELECT * FROM classes WHERE spec_id = ? and status=1 order by acad_year DESC, yearsem, section ASC");
            $stmt->bind_param("i", $spec_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching classes by specialization: " . $e->getMessage());
        }
        return $res;
    }

    public function getClassBySpecYearsemAndAcadYear($spec_id, $yearsem, $acad_year, $section = null)
    {
        $res = array();
        try {
            if ($section !== null) {
                $stmt = $this->conn->prepare("SELECT * FROM classes WHERE spec_id = ? AND yearsem = ? AND acad_year = ? AND section = ?");
                $stmt->bind_param("isss", $spec_id, $yearsem, $acad_year, $section);
            } else {
                $stmt = $this->conn->prepare("SELECT * FROM classes WHERE spec_id = ? AND yearsem = ? AND acad_year = ? ORDER BY section ASC LIMIT 1");
                $stmt->bind_param("iss", $spec_id, $yearsem, $acad_year);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching classes by specialization: " . $e->getMessage());
        }
        return $res;
    }

    public function getClassById($class_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM classes WHERE id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching classes by specialization: " . $e->getMessage());
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

    public function getStudentsByClass($class_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT s.id, u.username as roll_number, u.name, u.email, u.mobile, u.status, u.role, s.date_of_joining
                 FROM students s
                 JOIN users u ON s.username = u.username
                 WHERE s.class_id = ? AND s.status = 1");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching students by class: " . $e->getMessage());
        }
        return $res;
    }

    public function getAllStudentsByClass($class_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT s.id, u.username as roll_number, u.name, u.email, u.mobile, s.status, u.role, s.date_of_joining
                 FROM students s
                 JOIN users u ON s.username = u.username
                 WHERE s.class_id = ? order by s.status DESC, u.username ASC");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching students by class: " . $e->getMessage());
        }
        return $res;
    }

    public function getStudentDetails($student_id)
    {
        $res = array();
        try {
            $stmt = $this->conn->prepare("SELECT s.id, u.username as roll_number, u.name, u.email, u.mobile, u.status, u.role, s.date_of_joining
                 FROM students s
                 JOIN users u ON s.username = u.username
                 WHERE s.id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
        } catch (Exception $e) {
            $this->logs->errLog("Error fetching student details: " . $e->getMessage());
        }
        return $res;
    }

    public function updateStudentDetails($student_id, $name, $roll_number, $email, $mobile, $date_of_joining = null)
    {
        $res = array('status' => 0);
        try {
            if ($date_of_joining) {
                $stmt = $this->conn->prepare("UPDATE users u 
                     JOIN students s ON u.username = s.username
                     SET u.name = ?, u.username = ?, u.email = ?, u.mobile = ?, s.date_of_joining = ?
                     WHERE s.id = ?");
                $stmt->bind_param("sssssi", $name, $roll_number, $email, $mobile, $date_of_joining, $student_id);
            } else {
                $stmt = $this->conn->prepare("UPDATE users u 
                     JOIN students s ON u.username = s.username
                     SET u.name = ?, u.username = ?, u.email = ?, u.mobile = ?
                     WHERE s.id = ?");
                $stmt->bind_param("ssssi", $name, $roll_number, $email, $mobile, $student_id);
            }
            if ($stmt->execute()) {
                $res['status'] = 1; // Update successful
                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($adminId, "UPDATE_STUDENT", "Updated student details for student ID $student_id ($roll_number, $name)", "admin", "STUDENT", (string)$student_id);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Error updating student details: " . $e->getMessage());
        }
        return $res;
    }

    public function addDepartment($dept_shortname, $dept_fullname)
    {
        $myname = $this->classname . " - addDepartment - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("INSERT INTO `departments` (`username`, `dept_shortname`, `dept_fullname`, `status`) VALUES (?, ?, ?, 1)")) {
                    // Assuming you set a default username for new departments (e.g., based on short name)
                    $username = strtolower($dept_shortname) . "@hod";
                    $stmt->bind_param("sss", $username, $dept_shortname, $dept_fullname);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $deptId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "ADD_DEPARTMENT", "Added department $dept_shortname ($dept_fullname)", "admin", "DEPARTMENT", (string)$deptId);
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

    public function getDepartments()
    {
        $myname = $this->classname . " - getDepartments - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("SELECT `id`, `dept_shortname`, `dept_fullname` FROM `departments`");
                if ($stmt->execute()) {
                    $stmt->bind_result($id, $dept_shortname, $dept_fullname);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'dept_shortname' => $dept_shortname,
                            'dept_fullname' => $dept_fullname
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }


    public function addProgram($program_code, $prog_shortname, $prog_fullname)
    {
        $myname = $this->classname . " - addProgram - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("INSERT INTO `programs` (`program_code`, `prog_shortname`, `prog_fullname`) VALUES (?, ?, ?)")) {
                    $stmt->bind_param("sss", $program_code, $prog_shortname, $prog_fullname);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $progId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "ADD_PROGRAM", "Added program $prog_shortname ($program_code)", "admin", "PROGRAM", (string)$progId);
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


    public function getPrograms()
    {
        $myname = $this->classname . " - getPrograms - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("SELECT `id`, `program_code`, `prog_shortname`, `prog_fullname` FROM `programs`");
                if ($stmt->execute()) {
                    $stmt->bind_result($id, $program_code, $prog_shortname, $prog_fullname);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'program_code' => $program_code,
                            'prog_shortname' => $prog_shortname,
                            'prog_fullname' => $prog_fullname
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function addSpecialization($data)
    {
        $myname = $this->classname . " - addSpecialization - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {

                // Fetch program_code from programs table
                $program_code = '';
                if ($stmt1 = $this->conn->prepare("SELECT `program_code` FROM `programs` WHERE `id` = ?")) {
                    $stmt1->bind_param("i", $data['prog_id']);
                    if ($stmt1->execute()) {
                        $stmt1->bind_result($program_code);
                        $stmt1->fetch();
                    } else {
                        $this->logs->errLog($myname . "Failed to fetch program_code: " . $this->conn->error);
                        return $res; // Exit early if program_code cannot be fetched
                    }
                    $stmt1->close();
                }

                // Insert into specialization table with fetched program_code
                if ($stmt2 = $this->conn->prepare("INSERT INTO `specialization` (`program_code`, `spec_code`, `spec_shortname`, `spec_fullname`, `dept_id`, `prog_id`, `regulation`) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
                    $stmt2->bind_param("sssssss", $program_code, $data['spec_code'], $data['spec_shortname'], $data['spec_fullname'], $data['dept_id'], $data['prog_id'], $data['regulation']);
                    if ($stmt2->execute()) {
                        $res['status'] = 1;
                        $specId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "ADD_SPECIALIZATION", "Added specialization {$data['spec_shortname']} ({$data['spec_code']})", "admin", "SPECIALIZATION", (string)$specId);
                    } else {
                        $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                    }
                    $stmt2->close();
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


    public function getSpecializations()
    {
        $myname = $this->classname . " - getSpecializations - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname, d.dept_fullname, p.prog_fullname, s.regulation 
                FROM specialization s 
                JOIN departments d ON s.dept_id = d.id
                JOIN programs p ON s.prog_id = p.id
            ");

                if ($stmt->execute()) {
                    $stmt->bind_result($id, $spec_code, $spec_shortname, $spec_fullname, $dept_fullname, $prog_fullname, $regulation);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'spec_code' => $spec_code,
                            'spec_shortname' => $spec_shortname,
                            'spec_fullname' => $spec_fullname,
                            'dept_fullname' => $dept_fullname,
                            'prog_fullname' => $prog_fullname,
                            'regulation' => $regulation
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }


    public function getSpecializationsByDeptProg($dept_id, $prog_id)
    {
        $myname = $this->classname . " - getSpecializationsByDeptProg - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT s.id, s.spec_code, s.spec_shortname, s.spec_fullname 
                FROM specialization s 
                WHERE s.dept_id = ? AND s.prog_id = ?
            ");
                $stmt->bind_param("ii", $dept_id, $prog_id);

                if ($stmt->execute()) {
                    $stmt->bind_result($id, $spec_code, $spec_shortname, $spec_fullname);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'spec_code' => $spec_code,
                            'spec_shortname' => $spec_shortname,
                            'spec_fullname' => $spec_fullname
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }


    public function addClass($acad_year, $classname, $spec_id)
    {
        $myname = $this->classname . " - addClass - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("INSERT INTO `classes` (`acad_year`, `classname`, `spec_id`) VALUES (?, ?, ?)")) {
                    $stmt->bind_param("ssi", $acad_year, $classname, $spec_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $classId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "ADD_CLASS", "Added class $classname ($acad_year)", "admin", "CLASS", (string)$classId);
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

    public function getClasses()
    {
        $myname = $this->classname . " - getClasses - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT c.id, c.acad_year, c.classname, s.spec_shortname 
                FROM classes c
                JOIN specialization s ON c.spec_id = s.id
            "); // Join with specialization to get short name

                if ($stmt->execute()) {
                    $stmt->bind_result($id, $acad_year, $classname, $spec_shortname);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'acad_year' => $acad_year,
                            'classname' => $classname,
                            'spec_shortname' => $spec_shortname
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
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
                        $subId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "ADD_SUBJECT", "Added subject {$data['sub_fullname']} ({$data['subcode']}) for class ID {$data['class_id']}", "admin", "SUBJECT", (string)$subId);
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

    public function getSubjects()
    {
        $myname = $this->classname . " - getSubjects - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                    SELECT s.id, s.subject_sno, s.subcode, s.sub_shortname, s.sub_fullname, s.sub_type, c.classname, c.acad_year 
                    FROM subjects s
                    JOIN classes c ON s.class_id = c.id order by s.subject_sno
                "); // Join with classes to get class name and academic year

                if ($stmt->execute()) {
                    $stmt->bind_result($id, $subject_sno, $subcode, $sub_shortname, $sub_fullname, $sub_type, $classname, $acad_year);
                    while ($stmt->fetch()) {
                        $res['data'][] = array(
                            'id' => $id,
                            'subject_sno' => $subject_sno,
                            'subcode' => $subcode,
                            'sub_shortname' => $sub_shortname,
                            'sub_fullname' => $sub_fullname,
                            'sub_type' => $sub_type,
                            'classname' => $classname,
                            'acad_year' => $acad_year
                        );
                    }
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
                }
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
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
                if ($stmt = $this->conn->prepare("INSERT INTO `faculty_sub` (`faculty_id`, `sub_id`) VALUES (?, ?)")) {
                    $stmt->bind_param("ii", $faculty_id, $sub_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $mapId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "MAP_FACULTY_SUBJECT", "Mapped faculty ID $faculty_id to subject ID $sub_id", "admin", "FACULTY_SUBJECT", (string)$mapId);
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


    public function getFacultySubjectMappings()
    {
        $myname = $this->classname . " - getFacultySubjectMappings - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("
                SELECT u.name AS faculty_name, f.facultyid, s.sub_fullname, s.subcode
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN subjects s ON fs.sub_id = s.id
                JOIN users u ON f.username = u.username;
            ")) { // Corrected the JOIN with users table

                    if ($stmt->execute()) {
                        $stmt->bind_result($faculty_name, $facultyid, $sub_fullname, $subcode);
                        while ($stmt->fetch()) {
                            $res['data'][] = array(
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


    public function getFaculties()
    {
        $myname = $this->classname . " - getFaculties - ";
        $res = array();
        $res['status'] = 0;
        $res['data'] = array();

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("
                SELECT f.id, u.name, f.facultyid, f.designation 
                FROM faculties f
                JOIN users u ON f.username = u.username 
            ")) { // Join with users table to get name
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $name, $facultyid, $designation);
                        while ($stmt->fetch()) {
                            $res['data'][] = array(
                                'id' => $id,
                                'name' => $name,
                                'facultyid' => $facultyid,
                                'designation' => $designation // Add designation
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
                AND s.id NOT IN (SELECT stu_id FROM student_sub WHERE sub_id = ?)
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
                        $mapId = $this->conn->insert_id;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $this->dbActivityLog($adminId, "MAP_STUDENT_SUBJECT", "Mapped student ID $stu_id to subject ID $sub_id", "admin", "STUDENT_SUBJECT", (string)$mapId);
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


    public function addStudent($rollno, $name, $class_id, $date_of_joining = null)
    {
        $myname = $this->classname . " - addStudent - ";
        $res = array();
        $res['status'] = 0;
        $role = 'student'; // Set the role for new students
        $status = 1;      // Set the status for new students

        try {
            if (!empty($this->conn)) {
                // Check if student exists
                $stmt1 = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt1->bind_param("s", $rollno);
                $stmt1->execute();
                $stmt1->store_result();
                $exists = $stmt1->num_rows > 0;
                $stmt1->close();

                if (!$exists) {
                    // Add new user 
                    if ($stmt = $this->conn->prepare("INSERT INTO `users` (`username`, `password`, `name`, `role`, `status`) VALUES (?, ?, ?, ?, ?)")) {
                        $password = password_hash($rollno, PASSWORD_BCRYPT);
                        $stmt->bind_param("ssssi", $rollno, $password, $name, $role, $status);
                        if (!$stmt->execute()) {
                            $this->logs->errLog($myname . "User insert not executed: " . $this->conn->error);
                            return $res; // Exit early if the query can't be prepared
                        }
                    } else {
                        $this->logs->errLog($myname . "User insert not prepared: " . $this->conn->error);
                        return $res; // Exit early if the query can't be prepared
                    }
                }

                // Check if student class exists
                $stmt2 = $this->conn->prepare("SELECT id FROM `students` WHERE username = ? AND `class_id` = ?");
                $stmt2->bind_param("si", $rollno, $class_id);
                $stmt2->execute();
                $stmt2->store_result();
                $exists2 = $stmt2->num_rows > 0;
                $stmt2->close();

                if (!$exists2) {
                    // Add new student Class with date_of_joining
                    if ($date_of_joining) {
                        if ($stmt = $this->conn->prepare("INSERT INTO `students` (`username`, `class_id`, `status`, `date_of_joining`) VALUES (?, ?, ?, ?)")) {
                            $stmt->bind_param("siis", $rollno, $class_id, $status, $date_of_joining);
                            if (!$stmt->execute()) {
                                $this->logs->errLog($myname . "Student insert not executed: " . $this->conn->error);
                            }
                        } else {
                            $this->logs->errLog($myname . "Student insert not prepared: " . $this->conn->error);
                        }
                    } else {
                        if ($stmt = $this->conn->prepare("INSERT INTO `students` (`username`, `class_id`, `status`) VALUES (?, ?, ?)")) {
                            $stmt->bind_param("sii", $rollno, $class_id, $status);
                            if (!$stmt->execute()) {
                                $this->logs->errLog($myname . "Student insert not executed: " . $this->conn->error);
                            }
                        } else {
                            $this->logs->errLog($myname . "Student insert not prepared: " . $this->conn->error);
                        }
                    }
                }

                // Set success status only if both queries executed successfully (if needed)
                $res['status'] = 1;
                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $actorRole = $_SESSION['role'] ?? 'admin';
                $this->dbActivityLog($adminId, "ADD_STUDENT", "Added student $rollno ($name) to class ID $class_id", $actorRole, "STUDENT", $rollno);
            } else {
                $this->logs->errLog($myname . "Mysqli Error or else");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function unEnrollClassStudent($stroll, $class_id)
    {
        $myname = $this->classname . " - unEnrollClassStudent - ";
        $res = array();
        $res['status'] = 0;

        try {
            if (!empty($this->conn)) {
                if ($stmt = $this->conn->prepare("DELETE FROM `students` WHERE `username`=? AND `class_id`=?")) {
                    $stmt->bind_param("si", $stroll, $class_id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                        $actorRole = $_SESSION['role'] ?? 'admin';
                        $this->dbActivityLog($adminId, "UNENROLL_STUDENT", "Un-enrolled student $stroll from class ID $class_id", $actorRole, "STUDENT", $stroll);
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

    // Add inside class Admin
    public function setStudentStatus($rollno, $class_id, $status)
    {
        $myname = $this->classname . " - setStudentStatus - ";
        $res = array();
        $res['status'] = 0;
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("UPDATE students SET status=? WHERE username=? AND class_id=?");
                $stmt->bind_param("isi", $status, $rollno, $class_id);
                if ($stmt->execute()) {
                    $res['status'] = 1;
                    $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $statusText = $status ? "Active" : "Inactive";
                    $actorRole = $_SESSION['role'] ?? 'admin';
                    $this->dbActivityLog($adminId, "SET_STUDENT_STATUS", "Set student $rollno status to $statusText in class ID $class_id", $actorRole, "STUDENT", $rollno);
                } else {
                    $this->logs->errLog($myname . "Update statement not executed: " . $this->conn->error);
                }
                $stmt->close();
            } else {
                $this->logs->errLog($myname . "Connection error.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function resetStudentPassword($rollno)
    {
        $myname = $this->classname . " - resetStudentPassword - ";
        $res = array();
        $res['status'] = 0;
        try {
            if (!empty($this->conn)) {
                // Hash password in PHP using bcrypt
                $new_password = password_hash($rollno, PASSWORD_BCRYPT);
                
                $stmt = $this->conn->prepare("UPDATE users SET password=? WHERE username=? AND role='student'");
                $stmt->bind_param("ss", $new_password, $rollno);
                if ($stmt->execute()) {
                    $res['status'] = 1;
                    $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($adminId, "RESET_STUDENT_PASSWORD", "Reset password for student $rollno", "admin", "STUDENT", $rollno);
                } else {
                    $this->logs->errLog($myname . "Update statement not executed: " . $this->conn->error);
                }
                $stmt->close();
            } else {
                $this->logs->errLog($myname . "Connection error.");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }
}

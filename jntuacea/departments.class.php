<?php
require_once("dbcredentials.class.php");

class Departments extends DBCredentials
{
    private $classname = "Departments";

    public function __construct()
    {
        parent::__construct();
    }

        public function getAllDepartments()
    {
        $res = ['status' => 0];

        try {
            $stmt = $this->conn->prepare("SELECT id, dept_shortname, dept_fullname, status FROM departments");
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

    // Function to add or update a department
    public function addOrUpdateDepartment(array $data)
    {
        $username = $data['username'];
        $dept_shortname = $data['dept_shortname'];
        $dept_fullname = $data['dept_fullname'];
        $status = (int) $data['status']; // Cast to integer for status

        $this->conn->begin_transaction();

        try {

            // Insert or update department record
            if (!empty($data['id'])) {
                $id = (int) $data['id']; // Cast to integer for ID
                $stmt = $this->conn->prepare("
                    UPDATE departments
                    SET dept_shortname = ?, dept_fullname = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ssi", $dept_shortname, $dept_fullname, $id);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute query for adding/updating department. Error: " . $stmt->error);
                }

                if (!($stmt->affected_rows > 0)) {
                    throw new Exception($this->conn->error);
                }
            } else {
                // Check if username already exists (assuming username is unique)
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $exists = (int) $stmt->get_result()->fetch_row()[0];

                if ($exists > 0) {
                    throw new Exception("Username already exists.");
                }
                $stmt = $this->conn->prepare("
                    INSERT INTO departments (username, dept_shortname, dept_fullname, status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("sssi", $username, $dept_shortname, $dept_fullname, $status);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute query for adding/updating department. Error: " . $stmt->error);
                }

                if (!($stmt->affected_rows > 0)) {
                    throw new Exception($this->conn->error);
                }
                // Create or update user record (assuming HOD role for department)
                $stmt = $this->conn->prepare("
                        INSERT INTO users (username, password, name, role, status)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                if (!$stmt) {
                    throw new Exception("prepare error");
                }
                $hod = "hod";
                $pwd = password_hash($username, PASSWORD_BCRYPT);
                $stmt->bind_param(
                    "ssssi",
                    $username,
                    $pwd,
                    $dept_fullname,
                    $hod,
                    $status,
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute query for adding/updating user. Error: " . $stmt->error);
                }
            }

            $this->conn->commit();
            $this->logs->activityLog("Department '$dept_fullname' and HOD '$username' created/updated.");
            $action = !empty($data['id']) ? "UPDATE_DEPARTMENT" : "ADD_DEPARTMENT";
            $deptId = !empty($data['id']) ? (string)$data['id'] : (string)$this->conn->insert_id;
            $this->dbActivityLog($_SESSION['userid'] ?? 0, $action, "Department '$dept_fullname' ($dept_shortname) and HOD '$username' saved", "superadmin", "DEPARTMENT", $deptId);
            return ['status' => 1, 'message' => 'Department saved successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog("Exception occurred in addOrUpdateDepartment: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    // Function to delete a department
    public function deleteDepartment(int $id)
    {
        $this->conn->begin_transaction();

        try {
            // Delete user record associated with department
            $stmt = $this->conn->prepare("DELETE FROM users WHERE username = (SELECT username FROM departments WHERE id = ?)");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete user. Error: " . $stmt->error);
            }

            // Delete department record
            $stmt = $this->conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete department. Error: " . $stmt->error);
            }

            $this->conn->commit();
            $this->logs->activityLog("Department ID $id deleted.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_DEPARTMENT", "Deleted department ID $id", "superadmin", "DEPARTMENT", (string)$id);
            return ['status' => 1, 'message' => 'Department deleted successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog("Exception occurred in deleteDepartment: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

}
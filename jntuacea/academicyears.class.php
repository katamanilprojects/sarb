<?php
require_once("dbcredentials.class.php");

class AcademicYears extends DBCredentials
{
    private $classname = "AcademicYears";

    public function __construct()
    {
        parent::__construct();
    }

    public function getAllAcademicYears()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, acad_year, status FROM academic_years ORDER BY acad_year DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getAllAcademicYears: " . $e->getMessage());
            $res['error'] = "Failed to fetch academic years.";
        }
        return $res;
    }
    
    public function getActiveAcademicYears()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, acad_year, status FROM academic_years WHERE status = 1 ORDER BY acad_year DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception in getActiveAcademicYears: " . $e->getMessage());
            $res['error'] = "Failed to fetch academic years.";
        }
        return $res;
    }


    public function addOrUpdateAcademicYear(array $data)
    {
        $res = ['status' => 0];
        try {
            if (!empty($data['id'])) {
                $stmt = $this->conn->prepare("UPDATE academic_years SET acad_year = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sii", $data['acad_year'], $data['status'], $data['id']);
            } else {
                $stmt = $this->conn->prepare("INSERT INTO academic_years (acad_year, status) VALUES (?, ?)");
                $stmt->bind_param("si", $data['acad_year'], $data['status']);
            }

            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = 'Academic year saved successfully.';
                $acadYearId = !empty($data['id']) ? $data['id'] : $this->conn->insert_id;
                $action = !empty($data['id']) ? "UPDATE_ACADEMIC_YEAR" : "ADD_ACADEMIC_YEAR";
                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($adminId, $action, "Saved academic year: {$data['acad_year']}", "superadmin", "ACADEMIC_YEAR", (string)$acadYearId);
            } else {
                throw new Exception("Statement execution failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in addOrUpdateAcademicYear: " . $e->getMessage());
            $res['error'] = "Failed to save academic year: " . $e->getMessage();
        }
        return $res;
    }

    public function toggleAcademicYearStatus(int $id, int $status)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("UPDATE academic_years SET status = ? WHERE id = ?");
            $stmt->bind_param("ii", $status, $id);

            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = 'Academic year status updated successfully.';
                $statusText = $status ? "Active" : "Inactive";
                $adminId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($adminId, "TOGGLE_ACADEMIC_YEAR_STATUS", "Set academic year ID $id status to $statusText", "superadmin", "ACADEMIC_YEAR", (string)$id);
            } else {
                throw new Exception("Statement execution failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception in toggleAcademicYearStatus: " . $e->getMessage());
            $res['error'] = "Failed to update status: " . $e->getMessage();
        }
        return $res;
    }
    
}

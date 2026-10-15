<?php
require_once("dbcredentials.class.php");

class Programs extends DBCredentials
{
    private $classname = "Programs";

    public function __construct()
    {
        parent::__construct();
    }

        public function getAllPrograms()
    {
        $res = ['status' => 0];

        try {
            $stmt = $this->conn->prepare("SELECT id, program_code, prog_shortname, prog_fullname, program_level FROM programs ORDER BY id ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllPrograms: " . $e->getMessage());
            $res['err'] = "Failed to fetch programs.";
        }

        return $res;
    }

    public function getProgramById(int $id)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("SELECT id, program_code, prog_shortname, prog_fullname, program_level FROM programs WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) {
                $res['status'] = 1;
                $res['data'] = $result;
            } else {
                $res['err'] = "Program not found.";
            }
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getProgramById: " . $e->getMessage());
            $res['err'] = "Failed to fetch program.";
        }
        return $res;
    }

    public function getMaxAllowedPOs(int $program_id): int
    {
        $progRes = $this->getProgramById($program_id);
        $level = strtoupper($progRes['data']['program_level'] ?? 'UG');
        return ($level === 'PG') ? 11 : 12;
    }

    public static function getLevelLabel(?string $level): string
    {
        $lvl = strtoupper(trim((string)$level));
        return match($lvl) {
            'UG'  => 'Under Graduate',
            'PG'  => 'Post Graduate',
            'PHD' => 'Doctoral / Ph.D.',
            default => !empty($lvl) ? $lvl : 'Under Graduate'
        };
    }

    // Function to add or update a program
    public function addOrUpdateProgram(array $data)
    {
        $program_code = $data['program_code'];
        $prog_shortname = $data['prog_shortname'];
        $prog_fullname = $data['prog_fullname'];
        $program_level = !empty($data['program_level']) ? strtoupper(trim($data['program_level'])) : 'UG';

        if (!empty($data["id"])) {
            $stmt = $this->conn->prepare("UPDATE programs SET program_code = ?, prog_shortname = ?, prog_fullname = ?, program_level = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $program_code, $prog_shortname, $prog_fullname, $program_level, $data["id"]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO programs (program_code, prog_shortname, prog_fullname, program_level) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $program_code, $prog_shortname, $prog_fullname, $program_level);
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating program. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Program '$prog_fullname' added/updated.");
            $action = !empty($data['id']) ? "UPDATE_PROGRAM" : "ADD_PROGRAM";
            $progId = !empty($data['id']) ? (string)$data['id'] : (string)$this->conn->insert_id;
            $this->dbActivityLog($_SESSION['userid'] ?? 0, $action, "Program '$prog_fullname' ($prog_shortname, $program_code) saved", "superadmin", "PROGRAM", $progId);
            return ['status' => 1, 'message' => 'Program saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateProgram: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    // Function to delete a program
    public function deleteProgram(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete program. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Program ID $id deleted.");
            $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_PROGRAM", "Deleted program ID $id", "superadmin", "PROGRAM", (string)$id);
            return ['status' => 1, 'message' => 'Program deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteProgram: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}
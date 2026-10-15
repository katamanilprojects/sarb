<?php
require_once("user.class.php");
require_once("programs.class.php");
require_once("regulations.class.php");
require_once("superadmin.class.php");

class Syllabus extends User
{
    private $classname = "Syllabus";

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
                    WHERE prog_id = ? and status = 1
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

    public function generateSyllabusTitle($progId, $specId, $regId, $yearsem)
    {
        $res = ['status' => 0, 'title' => '', 'error' => ''];
        $myname = $this->classname . " - generateSyllabusTitle - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $progShortname = '';
            $specShortname = '';
            $specFullname = '';
            $regulation = '';

            $stmt1 = $this->conn->prepare("SELECT prog_shortname FROM programs WHERE id = ?");
            if (!$stmt1) {
                throw new Exception("Prepare failed for programs: " . $this->conn->error);
            }
            $stmt1->bind_param("i", $progId);
            $stmt1->execute();
            $stmt1->bind_result($progShortname);
            $stmt1->fetch();
            $stmt1->close();

            $stmt2 = $this->conn->prepare("SELECT spec_shortname, spec_fullname FROM specialization WHERE id = ?");
            if (!$stmt2) {
                throw new Exception("Prepare failed for specialization: " . $this->conn->error);
            }
            $stmt2->bind_param("i", $specId);
            $stmt2->execute();
            $stmt2->bind_result($specShortname, $specFullname);
            $stmt2->fetch();
            $stmt2->close();

            $stmt3 = $this->conn->prepare("SELECT regulation FROM regulations WHERE id = ?");
            if (!$stmt3) {
                throw new Exception("Prepare failed for regulations: " . $this->conn->error);
            }
            $stmt3->bind_param("i", $regId);
            $stmt3->execute();
            $stmt3->bind_result($regulation);
            $stmt3->fetch();
            $stmt3->close();

            $specLabel = !empty($specShortname) ? $specShortname : $specFullname;
            $res['title'] = trim($progShortname . " - " . $specLabel . " - " . $regulation . " - " . $yearsem . " Syllabus");
            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to generate syllabus title.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function checkExistingSyllabusMaster($progId, $specId, $regId, $yearsem)
    {
        $res = ['status' => 0, 'exists' => false, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - checkExistingSyllabusMaster - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT id, prog_id, spec_id, reg_id, yearsem, syllabus_title, file_name, file_path, uploaded_on
                    FROM syllabus_master
                    WHERE prog_id = ? AND spec_id = ? AND reg_id = ? AND yearsem = ? AND status = 1
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("iiis", $progId, $specId, $regId, $yearsem);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['exists'] = true;
                $res['data'] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to check existing syllabus.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function addOrUpdateSyllabusMaster($data, $file)
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        $myname = $this->classname . " - addOrUpdateSyllabusMaster - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $progId = (int)($data['prog_id'] ?? 0);
            $specId = (int)($data['spec_id'] ?? 0);
            $regId = (int)($data['reg_id'] ?? 0);
            $yearsem = trim($data['yearsem'] ?? '');

            if (empty($progId) || empty($specId) || empty($regId) || empty($yearsem)) {
                $res['error'] = 'Please select program, specialization, regulation and year-sem.';
                return $res;
            }

            if (empty($file) || empty($file['name']) || empty($file['tmp_name'])) {
                $res['error'] = 'Please upload syllabus PDF.';
                return $res;
            }

            $existing = $this->checkExistingSyllabusMaster($progId, $specId, $regId, $yearsem);
            if (!empty($existing['exists'])) {
                $res['error'] = 'Syllabus already exists for the selected combination.';
                return $res;
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeType = mime_content_type($file['tmp_name']);

            if ($extension !== 'pdf' || $mimeType !== 'application/pdf') {
                $res['error'] = 'Only PDF files are allowed.';
                return $res;
            }

            $titleResult = $this->generateSyllabusTitle($progId, $specId, $regId, $yearsem);
            if (empty($titleResult['status'])) {
                $res['error'] = $titleResult['error'] ?? 'Failed to generate syllabus title.';
                return $res;
            }
            $syllabusTitle = $titleResult['title'];

            $uploadDir = __DIR__ . "/uploads/syllabus/";
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    $res['error'] = 'Failed to create upload directory.';
                    return $res;
                }
            }

            $yearsemSlug = strtolower($yearsem);
            $yearsemSlug = preg_replace('/[^a-z0-9]+/', '-', $yearsemSlug);
            $yearsemSlug = trim($yearsemSlug, '-');

            $savedFileName = "syllabus_prog" . $progId . "_spec" . $specId . "_reg" . $regId . "_yearsem_" . $yearsemSlug . ".pdf";
            $fullFilePath = $uploadDir . $savedFileName;
            $dbFilePath = "uploads/syllabus/" . $savedFileName;

            if (!move_uploaded_file($file['tmp_name'], $fullFilePath)) {
                $res['error'] = 'Failed to upload PDF file.';
                return $res;
            }

            $uploadedBy = (int)($_SESSION['userid'] ?? 0);

            $sql = "INSERT INTO syllabus_master
                    (prog_id, spec_id, reg_id, yearsem, syllabus_title, file_name, file_path, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                if (file_exists($fullFilePath)) {
                    unlink($fullFilePath);
                }
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param(
                "iiissssi",
                $progId,
                $specId,
                $regId,
                $yearsem,
                $syllabusTitle,
                $savedFileName,
                $dbFilePath,
                $uploadedBy
            );

            if (!$stmt->execute()) {
                if (file_exists($fullFilePath)) {
                    unlink($fullFilePath);
                }
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();

            $this->logs->activityLog($myname . "Syllabus added for prog_id: $progId, spec_id: $specId, reg_id: $regId, yearsem: $yearsem");
            $uploaderId = !empty($uploadedBy) ? $uploadedBy : ($_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0));
            $this->dbActivityLog($uploaderId, "UPLOAD_SYLLABUS", "Syllabus added for prog_id: $progId, spec_id: $specId, reg_id: $regId, yearsem: $yearsem", "academic_section", "SYLLABUS", (string)$specId);

            $res['status'] = 1;
            $res['message'] = 'Syllabus uploaded successfully.';
        } catch (Exception $e) {
            $res['error'] = 'Failed to save syllabus.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getAllSyllabusMasters()
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getAllSyllabusMasters - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT sm.id, sm.prog_id, sm.spec_id, sm.reg_id, sm.yearsem, sm.syllabus_title,
                           sm.file_name, sm.file_path, sm.uploaded_on,
                           p.prog_shortname, p.prog_fullname,
                           s.spec_shortname, s.spec_fullname,
                           r.regulation
                    FROM syllabus_master sm
                    JOIN programs p ON sm.prog_id = p.id
                    JOIN specialization s ON sm.spec_id = s.id
                    JOIN regulations r ON sm.reg_id = r.id
                    WHERE sm.status = 1
                    ORDER BY sm.prog_id ASC, sm.reg_id ASC, sm.spec_id ASC, sm.yearsem ASC";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

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
            $res['error'] = 'Failed to fetch syllabus list.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }
}
?>

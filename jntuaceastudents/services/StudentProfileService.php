<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

/**
 * Class StudentProfileService
 *
 * Dedicated standalone service for JNTUA CEA Student Portal.
 * Manages student biographical profiles, digital document vault uploads/deletions,
 * custodial certificate records, and statutory certificate requests & rendering.
 */
class StudentProfileService extends \DBCredentials
{
    private static ?self $instance = null;
    private string $uploadDir;

    public function __construct()
    {
        parent::__construct();
        $this->uploadDir = ''; // Resolved only when uploading into configured private storage.
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function assertDocumentOwner(string $rollNo): void
    {
        if (PHP_SAPI === 'cli' && empty($_SESSION['role'])) { return; }
        require_once __DIR__ . '/../core/RecordScope.php';
        (new \College\RecordScope($this->conn, $_SESSION ?? []))->documentOwner($rollNo);
    }

    private function assertAcademicOfficer(): void
    {
        if (PHP_SAPI==='cli' && empty($_SESSION['role'])) {return;}
        if (!in_array($_SESSION['role']??'', ['academic_section','admin','superadmin'],true)) {throw new \DomainException('Academic section access required.');}
    }

    // =========================================================================
    // 1. Student Profile Methods
    // =========================================================================

    /**
     * Get complete unified profile of a student including users, student_profiles,
     * and latest enrolled class, department, program, regulation, and batch cohort.
     */
    public function getProfile(string $rollNo): ?array
    {
        $this->assertDocumentOwner($rollNo);
        $rollNo = trim($rollNo);
        $sql = "
            SELECT 
                u.username AS roll_no, u.name, u.email, u.mobile, u.role, u.status AS user_status,
                sp.id AS profile_id, sp.dob, sp.gender, sp.blood_group, sp.aadhar_number,
                sp.father_name, sp.mother_name, sp.parent_phone, sp.parent_email,
                sp.admission_category, sp.rank_obtained, sp.hall_ticket_admission,
                sp.admission_date, sp.permanent_address, sp.current_address,
                sp.profile_photo_path, sp.is_verified, sp.verified_by, sp.verified_at,
                c.id AS class_id, c.classname, c.acad_year, c.yearsem, c.section,
                d.id AS dept_id, d.dept_fullname AS dept_name, d.dept_shortname,
                p.id AS program_id, p.prog_shortname, p.prog_fullname,
                r.id AS regulation_id, r.regulation,
                sb.id AS batch_id, sb.batch_name, sb.admission_year, sb.graduation_year
            FROM users u
            LEFT JOIN student_profiles sp ON u.username = sp.roll_no
            LEFT JOIN (
                SELECT s1.username, s1.class_id
                FROM students s1
                INNER JOIN (
                    SELECT username, MAX(id) AS max_id 
                    FROM students 
                    GROUP BY username
                ) s2 ON s1.id = s2.max_id
            ) latest_st ON u.username = latest_st.username
            LEFT JOIN classes c ON latest_st.class_id = c.id
            LEFT JOIN specialization s ON c.spec_id = s.id
            LEFT JOIN departments d ON s.dept_id = d.id
            LEFT JOIN programs p ON s.prog_id = p.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            LEFT JOIN student_batches sb ON c.batch_id = sb.id
            WHERE u.username = ?
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $rollNo);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    }

    /**
     * Save / update student profile.
     */
    public function saveProfile(string $rollNo, array $data, ?int $verifiedBy = null): bool
    {
        if ($verifiedBy !== null) {$this->assertAcademicOfficer();$verifiedBy=(int)($_SESSION['userid']??$verifiedBy);}
        $this->assertDocumentOwner($rollNo);
        $rollNo = trim($rollNo);

        $this->conn->begin_transaction();
        try {
        $lock=$this->conn->prepare('SELECT id FROM users WHERE username=? FOR UPDATE');$lock->bind_param('s',$rollNo);$lock->execute();$lock->store_result();
        // Update core contact info in users table if provided
        if (!empty($data['email']) || !empty($data['mobile']) || !empty($data['name'])) {
            $userUpdates = [];
            $userParams = [];
            $userTypes = "";

            if (!empty($data['email'])) {
                $userUpdates[] = "email = ?";
                $userParams[] = trim($data['email']);
                $userTypes .= "s";
            }
            if (!empty($data['mobile'])) {
                $userUpdates[] = "mobile = ?";
                $userParams[] = trim($data['mobile']);
                $userTypes .= "s";
            }
            if (!empty($data['name'])) {
                $userUpdates[] = "name = ?";
                $userParams[] = trim($data['name']);
                $userTypes .= "s";
            }

            if (!empty($userUpdates)) {
                $userSql = "UPDATE users SET " . implode(", ", $userUpdates) . " WHERE username = ?";
                $userParams[] = $rollNo;
                $userTypes .= "s";

                $uStmt = $this->conn->prepare($userSql);
                $uStmt->bind_param($userTypes, ...$userParams);
                $uStmt->execute();
            }
        }

        // Upsert into student_profiles
        $sql = "
            INSERT INTO student_profiles (
                roll_no, dob, gender, blood_group, aadhar_number,
                father_name, mother_name, parent_phone, parent_email,
                admission_category, rank_obtained, hall_ticket_admission,
                admission_date, permanent_address, current_address,
                profile_photo_path, is_verified, verified_by, verified_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                dob = VALUES(dob),
                gender = VALUES(gender),
                blood_group = VALUES(blood_group),
                aadhar_number = VALUES(aadhar_number),
                father_name = VALUES(father_name),
                mother_name = VALUES(mother_name),
                parent_phone = VALUES(parent_phone),
                parent_email = VALUES(parent_email),
                admission_category = VALUES(admission_category),
                rank_obtained = VALUES(rank_obtained),
                hall_ticket_admission = VALUES(hall_ticket_admission),
                admission_date = VALUES(admission_date),
                permanent_address = VALUES(permanent_address),
                current_address = VALUES(current_address),
                profile_photo_path = COALESCE(VALUES(profile_photo_path), profile_photo_path),
                is_verified = VALUES(is_verified),
                verified_by = VALUES(verified_by),
                verified_at = VALUES(verified_at),
                updated_at = CURRENT_TIMESTAMP
        ";

        $dob = !empty($data['dob']) ? $data['dob'] : null;
        $gender = !empty($data['gender']) ? $data['gender'] : null;
        $bloodGroup = !empty($data['blood_group']) ? trim($data['blood_group']) : null;
        $aadhar = !empty($data['aadhar_number']) ? trim($data['aadhar_number']) : null;
        $father = !empty($data['father_name']) ? trim($data['father_name']) : null;
        $mother = !empty($data['mother_name']) ? trim($data['mother_name']) : null;
        $parentPhone = !empty($data['parent_phone']) ? trim($data['parent_phone']) : null;
        $parentEmail = !empty($data['parent_email']) ? trim($data['parent_email']) : null;
        $admCat = !empty($data['admission_category']) ? trim($data['admission_category']) : null;
        $rank = isset($data['rank_obtained']) && is_numeric($data['rank_obtained']) ? (int)$data['rank_obtained'] : null;
        $htAdm = !empty($data['hall_ticket_admission']) ? trim($data['hall_ticket_admission']) : null;
        $admDate = !empty($data['admission_date']) ? $data['admission_date'] : null;
        $permAddr = !empty($data['permanent_address']) ? trim($data['permanent_address']) : null;
        $currAddr = !empty($data['current_address']) ? trim($data['current_address']) : null;
        $photo = !empty($data['profile_photo_path']) ? trim($data['profile_photo_path']) : null;
        $isVerified = ($verifiedBy !== null && $verifiedBy > 0) ? 1 : 0;
        $verifiedAt = $isVerified ? date('Y-m-d H:i:s') : null;

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssisssssiis",
            $rollNo, $dob, $gender, $bloodGroup, $aadhar,
            $father, $mother, $parentPhone, $parentEmail,
            $admCat, $rank, $htAdm,
            $admDate, $permAddr, $currAddr,
            $photo, $isVerified, $verifiedBy, $verifiedAt
        );

        $ok = $stmt->execute();
        if ($ok) {
            $actorId = $verifiedBy ?? ($_SESSION['user_id'] ?? ($_SESSION['userid'] ?? 0));
            $role = ($verifiedBy !== null && $verifiedBy > 0) ? "admin" : "Student";
            $this->logs->actLog($actorId, "SAVE_STUDENT_PROFILE", "Saved profile for student roll no: $rollNo");
            if (!$this->dbActivityLog($actorId, "SAVE_STUDENT_PROFILE", "Saved profile for student roll no: $rollNo", $role, "STUDENT_PROFILE", $rollNo)) { throw new \RuntimeException("Unable to save the profile audit trail."); }
        }
        $this->conn->commit();
        return $ok;
        } catch (\Throwable $e) { $this->conn->rollback(); throw $e; }
    }

    // =========================================================================
    // 2. Document Vault Methods
    // =========================================================================

    /**
     * Retrieve all uploaded documents for a student.
     */
    public function getDocuments(string $rollNo): array
    {
        $this->assertDocumentOwner($rollNo);
        $stmt = $this->conn->prepare("
            SELECT sd.*, u.name AS verified_by_name
            FROM student_documents sd
            LEFT JOIN users u ON sd.verified_by = u.id
            WHERE sd.roll_no = ? AND sd.deleted_at IS NULL
            ORDER BY sd.uploaded_at DESC
        ");
        $stmt->bind_param("s", $rollNo);
        $stmt->execute();
        $res = $stmt->get_result();
        $docs = [];
        while ($row = $res->fetch_assoc()) {
            $docs[] = $row;
        }
        return $docs;
    }

    /**
     * Upload a document into the secure vault.
     */
    public function uploadDocument(
        string $rollNo, 
        string $docType, 
        string $docTitle, 
        array $file, 
        bool $isOriginal = false, 
        ?string $custodyLocation = null,
        ?int $userId = null
    ): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['status' => 0, 'err' => 'No valid uploaded file found.'];
        }

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedMime, $allowedMimes)) {
            return ['status' => 0, 'err' => 'Invalid file format. Only PDF, JPG, and PNG documents are permitted.'];
        }

        // Limit file size to 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['status' => 0, 'err' => 'File size exceeds institutional limit (10MB).'];
        }

        $ext = match($detectedMime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin'
        };

        $safeRoll = preg_replace('/[^A-Za-z0-9]/', '', $rollNo);
        $safeType = preg_replace('/[^A-Za-z0-9_]/', '', $docType);
        $uniqueFilename = "doc_{$safeRoll}_{$safeType}_" . time() . "_" . mt_rand(1000, 9999) . ".{$ext}";
        require_once __DIR__ . '/../core/DocumentStorage.php';
        $this->assertDocumentOwner($rollNo);
        try { $storageRoot = \College\DocumentStorage::root(); }
        catch (\RuntimeException $e) { return ['status'=>0,'err'=>$e->getMessage()]; }
        $uniqueFilename = bin2hex(random_bytes(32)) . '.' . $ext;
        $destPath = $storageRoot . DIRECTORY_SEPARATOR . $uniqueFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['status' => 0, 'err' => 'Failed to save document to server repository.'];
        }

        $relativePath = 'private:' . $uniqueFilename;
        $originalInt = $isOriginal ? 1 : 0;
        $custodyStatus = $isOriginal ? 'IN_CUSTODY' : null;

        $stmt = $this->conn->prepare("
            INSERT INTO student_documents (
                roll_no, doc_type, doc_title, file_name, file_path, file_size, mime_type,
                is_original_submitted, custody_status, custody_location_ref
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $fileSize = (int)$file['size'];
        $stmt->bind_param(
            "sssssisiss",
            $rollNo, $docType, $docTitle, $file['name'], $relativePath, $fileSize, $detectedMime,
            $originalInt, $custodyStatus, $custodyLocation
        );

        if (!$stmt->execute()) {
            @unlink($destPath);
            return ['status' => 0, 'err' => 'Database error registering document: ' . $stmt->error];
        }

        $docId = $stmt->insert_id;

        $actorId = $userId ?? ($_SESSION['user_id'] ?? ($_SESSION['userid'] ?? 0));
        $this->logs->actLog($actorId, "UPLOAD_STUDENT_DOC", "Uploaded $docType for roll no $rollNo (Doc ID: $docId)");
        $this->dbActivityLog($actorId, "UPLOAD_STUDENT_DOC", "Uploaded $docType for roll no $rollNo (Doc ID: $docId)", "Student", "STUDENT_DOCUMENT", (string)$docId);

        return [
            'status' => 1,
            'doc_id' => $docId,
            'file_path' => $relativePath,
            'msg' => 'Document successfully uploaded and secured in vault.'
        ];
    }

    /**
     * Delete document from vault.
     */
    public function deleteDocument(int $docId, string $rollNo, bool $isAdmin = false): bool
    {
        $this->assertDocumentOwner($rollNo);
        $isAdmin=in_array($_SESSION['role']??'', ['admin','academic_section','superadmin'],true);
        $this->conn->begin_transaction();
        try{
            $stmt=$this->conn->prepare('SELECT * FROM student_documents WHERE id=? AND roll_no=? AND deleted_at IS NULL FOR UPDATE');$stmt->bind_param('is',$docId,$rollNo);$stmt->execute();$doc=$stmt->get_result()->fetch_assoc();
            if(!$doc || (!$isAdmin && (int)$doc['is_verified']===1)){$this->conn->rollback();return false;}
            $actor=(int)($_SESSION['userid']??0);$stmt=$this->conn->prepare('UPDATE student_documents SET deleted_at=NOW(),deleted_by=? WHERE id=?');$stmt->bind_param('ii',$actor,$docId);$stmt->execute();
            if(!$this->dbActivityLog($actor,'DELETE_STUDENT_DOC','Document removed from active records; file retained for authorised recovery.',$_SESSION['role']??'system','STUDENT_DOCUMENT',(string)$docId)){throw new \RuntimeException('Unable to save document audit trail.');}
            $this->conn->commit();return true;
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}

    }

    // =========================================================================
    // 3. Custodial Ledger Methods
    // =========================================================================

    /**
     * Get custodial records for a specific student.
     */
    public function getCustodialRecords(string $rollNo): array
    {
        $this->assertDocumentOwner($rollNo);
        $stmt = $this->conn->prepare("
            SELECT cr.*, u.name AS received_by_name
            FROM student_custodial_records cr
            LEFT JOIN users u ON cr.received_by = u.id
            WHERE cr.roll_no = ?
            ORDER BY cr.received_date DESC, cr.id DESC
        ");
        $stmt->bind_param("s", $rollNo);
        $stmt->execute();
        $res = $stmt->get_result();
        $records = [];
        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
        }
        return $records;
    }

    // =========================================================================
    // 4. Statutory Certificate Request & Generation Engine
    // =========================================================================

    /**
     * Get certificate requests for a student.
     */
    public function getCertificateRequests(string $rollNo): array
    {
        $this->assertDocumentOwner($rollNo);
        $stmt = $this->conn->prepare("
            SELECT cr.*, u.name AS approved_by_name
            FROM student_certificate_requests cr
            LEFT JOIN users u ON cr.approved_by = u.id
            WHERE cr.roll_no = ?
            ORDER BY cr.requested_date DESC, cr.id DESC
        ");
        $stmt->bind_param("s", $rollNo);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Request a certificate.
     */
    public function requestCertificate(
        string $rollNo, 
        string $certType, 
        string $purpose, 
        ?array $customFields = null
    ): array {
        $this->assertDocumentOwner($rollNo);
        require_once __DIR__.'/FeatureManager.php';
        if(!empty($_SESSION['role'])&&!FeatureManager::canRoleWrite('MOD_CERTIFICATES')){return ['status'=>0,'err'=>'Certificate requests are disabled.'];}
        if(trim($purpose)===''){return ['status'=>0,'err'=>'Enter the certificate purpose.'];}
        $this->conn->begin_transaction();
        try{
            $certNo=$this->generateCertificateNumber($certType);$customJson=$customFields?json_encode($customFields,JSON_THROW_ON_ERROR):null;$reqDate=date('Y-m-d');$token=bin2hex(random_bytes(32));
            $stmt=$this->conn->prepare("INSERT INTO student_certificate_requests(certificate_no,roll_no,cert_type,purpose,status,requested_date,custom_fields_json,verification_token) VALUES (?,?,?,?,'REQUESTED',?,?,?)");$stmt->bind_param('sssssss',$certNo,$rollNo,$certType,$purpose,$reqDate,$customJson,$token);$stmt->execute();$id=$this->conn->insert_id;
            if(!$this->dbActivityLog($_SESSION['userid']??0,'REQUEST_CERTIFICATE','Requested '.$certType,$_SESSION['role']??'system','CERTIFICATE_REQUEST',(string)$id)){throw new \RuntimeException('Unable to save certificate audit trail.');}
            $this->conn->commit();return ['status'=>1,'request_id'=>$id,'certificate_no'=>$certNo,'msg'=>'Certificate request recorded: '.$certNo];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage()];}

    }

    /**
     * Generate an official institutional certificate number.
     * Format: JNTUACEA/ACAD/{YEAR}/{TYPE}/{SEQ}
     */
    public function generateCertificateNumber(string $certType): string
    {
        require_once __DIR__ . '/../core/CertificateNumber.php';
        return \College\CertificateNumber::reserve($this->conn, $certType);
    }

    /**
     * Get complete dataset to render/print any statutory certificate.
     */
    public function getCertificateRenderData(int $requestId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT cr.*, u.name AS approved_by_name
            FROM student_certificate_requests cr
            LEFT JOIN users u ON cr.approved_by = u.id
            WHERE cr.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $cert = $stmt->get_result()->fetch_assoc();

        if (!$cert) {
            return null;
        }

        $profile = $this->getProfile($cert['roll_no']);
        $custodyItems = [];

        // If custodial certificate, fetch all documents in custody
        if ($cert['cert_type'] === 'CUSTODIAL') {
            $custodyItems = $this->getCustodialRecords($cert['roll_no']);
        }

        $customFields = !empty($cert['custom_fields_json']) 
            ? json_decode($cert['custom_fields_json'], true) 
            : [];

        return [
            'certificate' => $cert,
            'student' => $profile,
            'custody_items' => $custodyItems,
            'custom_fields' => $customFields
        ];
    }

    /**
     * Verify certificate or custodial credential by cryptographic token or certificate number.
     * Public verification endpoint without login requirement (NAAC Metric 6.2, NBA Criterion 4.3).
     *
     * @param string $token Cryptographic Auth Token or Certificate No.
     * @return array|null Complete verification payload if valid
     */
    public function verifyCertificateByToken(string $token): ?array
    {
        $token=trim($token);if(!preg_match('/^[a-f0-9]{64}$/D',$token)){return null;}
        $stmt=$this->conn->prepare("SELECT cr.certificate_no,cr.roll_no,cr.cert_type,cr.status,cr.approved_at,cr.issued_at,u.name FROM student_certificate_requests cr LEFT JOIN users u ON u.username=cr.roll_no WHERE cr.status IN ('GENERATED','ISSUED') AND cr.verification_token=? LIMIT 1");$stmt->bind_param('s',$token);$stmt->execute();$cert=$stmt->get_result()->fetch_assoc();
        if(!$cert){return null;}
        return ['type'=>'CERTIFICATE','certificate'=>$cert,'student'=>['name'=>$cert['name'],'roll_no'=>$cert['roll_no']],'verified'=>true,'verification_timestamp'=>date('Y-m-d H:i:s')];

    }
}

// Global class alias
if (!class_exists('StudentProfileService', false)) {
    class_alias(StudentProfileService::class, 'StudentProfileService');
}

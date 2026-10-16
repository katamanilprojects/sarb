<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

/**
 * Class ExamResultsService
 *
 * Handles examination results notification publishing, bulk CSV importation,
 * student grade card generation, and 1-click automated SEE marks ingestion for OBE.
 */
class ExamResultsService extends \DBCredentials
{
    private static ?self $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ==========================================
    // 1. Examination Notifications
    // ==========================================

    /**
     * Get all exam notifications with optional filters.
     */
    public function getNotifications(?int $programId = null, ?int $regId = null, bool $onlyPublished = false): array
    {
        $sql = "SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation,
                       (SELECT COUNT(*) FROM exam_results er WHERE er.notification_id = en.id) AS results_count,
                       (SELECT COUNT(DISTINCT er.htno) FROM exam_results er WHERE er.notification_id = en.id) AS students_count
                FROM exam_notifications en
                LEFT JOIN programs p ON en.program_id = p.id
                LEFT JOIN regulations r ON en.regulation_id = r.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($programId !== null && $programId > 0) {
            $sql .= " AND en.program_id = ?";
            $params[] = $programId;
            $types .= "i";
        }
        if ($regId !== null && $regId > 0) {
            $sql .= " AND en.regulation_id = ?";
            $params[] = $regId;
            $types .= "i";
        }
        if ($onlyPublished) {
            $sql .= " AND en.is_published = 1";
        }

        $sql .= " ORDER BY en.release_date DESC, en.id DESC";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get single notification.
     */
    public function getNotificationById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation 
            FROM exam_notifications en
            LEFT JOIN programs p ON en.program_id = p.id
            LEFT JOIN regulations r ON en.regulation_id = r.id
            WHERE en.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Create an examination notification.
     */
    public function createNotification(array $data, int $userId): array
    {
        try {
            $this->assertResultsEditor();
            $code = strtoupper(trim($data['notification_code'] ?? ''));
            $title = trim($data['title'] ?? ''); $year = trim($data['academic_year'] ?? '');
            $program = (int)($data['program_id'] ?? 0); $regulation = (int)($data['regulation_id'] ?? 0);
            $semester = trim($data['yearsem'] ?? ''); $month = trim($data['month_year'] ?? '');
            $release = $data['release_date'] ?? date('Y-m-d'); $type = $data['exam_type'] ?? 'REGULAR';
            foreach ([[$code,50],[$title,255],[$year,10],[$semester,20],[$month,30]] as [$value,$limit]) {
                if ($value === '' || mb_strlen($value) > $limit) { throw new \InvalidArgumentException('Complete the notification fields within their displayed limits.'); }
            }
            if (!in_array($type, ['REGULAR','SUPPLEMENTARY','REVALUATION'], true)) { throw new \InvalidArgumentException('Select a supported exam type.'); }
            require_once __DIR__ . '/../core/DateRange.php';
            \College\DateRange::validate($release, $release);
            $check = $this->conn->prepare('SELECT id FROM regulations WHERE id=? AND prog_id=?');
            $check->bind_param('ii', $regulation, $program); $check->execute();
            if (!$check->get_result()->fetch_row()) { throw new \InvalidArgumentException('Select a regulation belonging to the chosen program.'); }
            $actor = (int)($_SESSION['userid'] ?? $userId);
            $this->conn->begin_transaction();
            $stmt = $this->conn->prepare("INSERT INTO exam_notifications(notification_code,title,academic_year,program_id,regulation_id,yearsem,month_year,release_date,is_published,workflow_status,exam_type,created_by) VALUES (?,?,?,?,?,?,?,?,0,'DRAFT',?,?)");
            $stmt->bind_param('sssiissssi', $code, $title, $year, $program, $regulation, $semester, $month, $release, $type, $actor); $stmt->execute();
            $id = $this->conn->insert_id;
            if (!$this->dbActivityLog($actor, 'CREATE_EXAM_NOTIFICATION', $type . ': ' . $title, $_SESSION['role'] ?? 'system', 'EXAM_NOTIFICATION', (string)$id)) { throw new \RuntimeException('Unable to save notification audit.'); }
            $this->conn->commit();
            return ['status'=>1, 'id'=>$id, 'notification_id'=>$id, 'msg'=>'Draft exam notification created. Import, review and publish its results.'];
        } catch (\Throwable $e) { $this->conn->rollback(); return ['status'=>0, 'err'=>$e->getMessage()]; }
    }

    /**
     * Toggle notification publish state.
     */
    public function togglePublishStatus(int $id, int $status, int $userId): array
    {
        $this->conn->begin_transaction();
        try {
            if(!in_array($status,[0,1],true)){throw new \InvalidArgumentException('Invalid publish decision.');}
            $this->assertResultsEditor();
            if(!empty($_SESSION['role'])&&!FeatureManager::canAction('MOD_RESULTS_PUBLISH','approve')){throw new \DomainException('Results approval is disabled for your account.');}
            $stmt=$this->conn->prepare('SELECT * FROM exam_notifications WHERE id=? FOR UPDATE');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
            if(!$row){throw new \DomainException('Notification not found.');}
            if($status===1 && ($row['workflow_status']!=='REVIEWED'||!$row['reviewed_by']||!$row['reviewed_at'])){throw new \DomainException('Review the draft results before publication.');}
            if ($status === 1) { $this->validateStoredResults($id); }
            $state=$status?'PUBLISHED':'DRAFT';$actor=(int)($_SESSION['userid']??$userId);
            if ($status===0) { $clear=$this->conn->prepare('UPDATE exam_notifications SET reviewed_by=NULL,reviewed_at=NULL WHERE id=?');$clear->bind_param('i',$id);$clear->execute(); }
            $stmt=$this->conn->prepare('UPDATE exam_notifications SET is_published=?,workflow_status=? WHERE id=?');$stmt->bind_param('isi',$status,$state,$id);$stmt->execute();
            $summary = ['synced'=>0, 'preserved'=>0, 'pending'=>0, 'not_applicable'=>0];
            if ($status === 1 && $row['exam_type'] === 'REGULAR') {
                if (!(PHP_SAPI === 'cli' && empty($_SESSION['role'])) && !FeatureManager::canAction('MOD_SEE_MARKS', 'edit')) {
                    throw new \DomainException('Enable SEE editing for this role before publishing a regular exam.');
                }
                require_once __DIR__ . '/../core/ResultsSeeSync.php';
                $subjects = $this->conn->prepare('SELECT DISTINCT subject_id FROM exam_results WHERE notification_id=? ORDER BY subject_id');
                $subjects->bind_param('i', $id); $subjects->execute();
                foreach ($subjects->get_result()->fetch_all(MYSQLI_ASSOC) as $subject) {
                    $this->assertSubjectScope((int)$subject['subject_id']);
                    $result = (new \College\ResultsSeeSync($this->conn))->subject((int)$subject['subject_id'], $id, $actor);
                    foreach ($summary as $key => $value) { $summary[$key] += $result[$key]; }
                }
            } elseif ($status === 0) {
                $withdraw = $this->conn->prepare('UPDATE external_assessment_marks SET is_current=0 WHERE source_notification_id=? AND is_current=1');
                $withdraw->bind_param('i', $id); $withdraw->execute();
                $summary['invalidated'] = $withdraw->affected_rows;
            }
            if(!$this->dbActivityLog($actor,'RESULTS_PUBLICATION',$state . '; ' . json_encode($summary),'academic_section','EXAM_NOTIFICATION',(string)$id)){throw new \RuntimeException('Unable to save audit trail.');}
            $this->conn->commit();return ['status'=>1,'summary'=>$summary,'msg'=>$status ? 'Reviewed results published. ' . ($row['exam_type']==='REGULAR' ? self::syncSummary($summary) : 'Separate exam attempt retained; regular OBE marks unchanged.') : 'Notification returned to draft; dependent SEE evidence marked stale.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage()];}
    }

    private function assertResultsEditor(): void
    {
        if(PHP_SAPI==='cli'&&empty($_SESSION['role'])){return;}
        require_once __DIR__.'/FeatureManager.php';
        if(!FeatureManager::canRoleWrite('MOD_RESULTS_PUBLISH')){throw new \DomainException('Results editing is disabled for your account.');}
        if(!in_array($_SESSION['role']??'', ['academic_section','admin','superadmin'],true)){throw new \DomainException('Academic results editor access required.');}
    }

    public function reviewResults(int $id,int $userId,string $remarks): array
    {
        $this->conn->begin_transaction();
        try{
            $this->assertResultsEditor();if(!empty($_SESSION['role'])&&!FeatureManager::canAction('MOD_RESULTS_PUBLISH','approve')){throw new \DomainException('Results approval is disabled for your account.');}if(trim($remarks)===''){throw new \DomainException('Record the results verification observations.');}
            $stmt=$this->conn->prepare('SELECT * FROM exam_notifications WHERE id=? FOR UPDATE');$stmt->bind_param('i',$id);$stmt->execute();$notification=$stmt->get_result()->fetch_assoc();
            if(!$notification || $notification['is_published'] || $notification['workflow_status']!=='DRAFT'){throw new \DomainException('Select an unpublished draft notification.');}
            $stmt=$this->conn->prepare('SELECT COUNT(*) total,SUM(student_id IS NULL OR subject_id IS NULL OR grade_letter IS NULL OR result_status IS NULL) incomplete FROM exam_results WHERE notification_id=?');$stmt->bind_param('i',$id);$stmt->execute();$counts=$stmt->get_result()->fetch_assoc();
            if(!(int)$counts['total']||(int)$counts['incomplete']){throw new \DomainException('Import complete validated results before review.');}
            $this->validateStoredResults($id);
            $actor=(int)($_SESSION['userid']??$userId);
            $stmt=$this->conn->prepare("UPDATE exam_notifications SET workflow_status='REVIEWED',reviewed_by=?,reviewed_at=NOW() WHERE id=?");$stmt->bind_param('ii',$actor,$id);$stmt->execute();
            $stmt=$this->conn->prepare("INSERT INTO workflow_review_history(entity_type,entity_id,actor_user_id,from_status,to_status,remarks) VALUES ('RESULTS',?,?,'DRAFT','REVIEWED',?)");$stmt->bind_param('iis',$id,$actor,$remarks);$stmt->execute();
            $this->conn->commit();return ['status'=>1,'msg'=>'Results reviewed. Publication is now available.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage()];}
    }

    private function validateStoredResults(int $notification): void
    {
        require_once __DIR__ . '/../core/ResultsPolicy.php';
        require_once __DIR__ . '/../core/ResultRow.php';
        $stmt = $this->conn->prepare('SELECT * FROM exam_notifications WHERE id=?');
        $stmt->bind_param('i', $notification); $stmt->execute(); $exam = $stmt->get_result()->fetch_assoc();
        $stmt = $this->conn->prepare('SELECT er.*,st.username actual_roll,st.class_id student_class,s.class_id subject_class,c.reg_id,c.yearsem,c.acad_year,sp.prog_id,ss.id enrollment FROM exam_results er LEFT JOIN students st ON st.id=er.student_id LEFT JOIN subjects s ON s.id=er.subject_id LEFT JOIN student_sub ss ON ss.stu_id=er.student_id AND ss.sub_id=er.subject_id LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN specialization sp ON sp.id=c.spec_id WHERE er.notification_id=?');
        $stmt->bind_param('i', $notification); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $profiles = [];
        if (!$rows) { throw new \DomainException('Import validated result records before review or publication.'); }
        foreach ($rows as $row) {
            if (!$row['enrollment'] || strcasecmp($row['htno'], (string)$row['actual_roll']) !== 0
                || (int)$row['student_class'] !== (int)$row['subject_class'] || (int)$row['reg_id'] !== (int)$exam['regulation_id']
                || (int)$row['prog_id'] !== (int)$exam['program_id'] || $row['yearsem'] !== $exam['yearsem']
                || ($exam['exam_type'] === 'REGULAR' && $row['acad_year'] !== $exam['academic_year'])) {
                throw new \DomainException('Result enrollment no longer matches the notification. Return to draft and reconcile the records.');
            }
            if ($row['grade_points'] === null || $row['credits_registered'] === null || $row['credits_earned'] === null) { throw new \DomainException('Complete grade points and credits before review.'); }
            $profile = $profiles[(int)$row['subject_id']] ??= (new \College\ResultsPolicy($this->conn))->forSubject((int)$row['subject_id']);
            $normalized = \College\ResultRow::normalize(['status'=>$row['result_status'], 'grade'=>$row['grade_letter'], 'points'=>$row['grade_points'], 'credits'=>$row['credits_registered'], 'internal'=>$row['internal_marks'], 'external'=>$row['external_marks'], 'total'=>$row['total_marks']], $profile['context'], $profile['config']);
            if ($normalized['result_status'] !== $row['result_status'] || abs($normalized['credits_earned'] - (float)$row['credits_earned']) > 0.00001) { throw new \DomainException('Stored status or earned credits are inconsistent. Re-import corrected draft results.'); }
        }
    }

    // ==========================================
    // 2. Results CSV Bulk Import
    // ==========================================

    /**
     * Import results from CSV file for a notification.
     * Expects CSV headers: HTNO, SubCode, SubName, Internals, Externals, Total, Grade, GradePoints, Credits, Status
     */
    public function importResultsCsv(int $notificationId, string $csvFilePath, int $userId, ?string $expectedValidationHash = null, string $mode = 'MERGE', string $reason = ''): array
    {
        $this->conn->begin_transaction();
        try{
            $this->assertResultsEditor();
            $lock=$this->conn->prepare('SELECT id FROM exam_notifications WHERE id=? FOR UPDATE');$lock->bind_param('i',$notificationId);$lock->execute();$lock->store_result();
            $preview=$this->previewResultsCsv($notificationId,$csvFilePath,$mode);
            if(!$preview['status']){throw new \DomainException('No rows imported. Fix every validation error first. '.implode(' | ',array_slice($preview['errors'],0,10)));}
            if ($expectedValidationHash !== null && !hash_equals($expectedValidationHash, $preview['validation_hash'])) { throw new \DomainException('The draft results, course policy or result matching changed. Upload and review a new preview.'); }
            if(!$preview['status']){$this->conn->rollback();return ['status'=>0,'err'=>'No rows imported. Fix every validation error first.','errors'=>$preview['errors']];}
            require_once __DIR__.'/../core/ResultVersions.php';
            $versions=new \College\ResultVersions($this->conn);$before=$versions->rows($notificationId);
            if (($preview['changes']['changed'] || $preview['changes']['removed'] || $mode === 'REPLACE') && trim($reason)==='') { throw new \DomainException('Record a reason for correcting or replacing draft results.'); }
            if ($mode === 'REPLACE') {
                $delete=$this->conn->prepare('DELETE FROM exam_results WHERE notification_id=?');$delete->bind_param('i',$notificationId);$delete->execute();
            }
            $stmt=$this->conn->prepare('INSERT INTO exam_results(notification_id,htno,student_id,subject_code,subject_name,subject_id,internal_marks,external_marks,total_marks,grade_letter,grade_points,credits_registered,credits_earned,result_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id),subject_id=VALUES(subject_id),subject_name=VALUES(subject_name),internal_marks=VALUES(internal_marks),external_marks=VALUES(external_marks),total_marks=VALUES(total_marks),grade_letter=VALUES(grade_letter),grade_points=VALUES(grade_points),credits_registered=VALUES(credits_registered),credits_earned=VALUES(credits_earned),result_status=VALUES(result_status),imported_at=CURRENT_TIMESTAMP');
            foreach($preview['rows'] as $row){$r=$row;$stmt->bind_param('isissidddsddds',$notificationId,$r['htno'],$r['student_id'],$r['subject_code'],$r['subject_name'],$r['subject_id'],$r['internal_marks'],$r['external_marks'],$r['total_marks'],$r['grade_letter'],$r['grade_points'],$r['credits_registered'],$r['credits_earned'],$r['result_status']);$stmt->execute();}
            $actor=(int)($_SESSION['userid']??$userId);$count=count($preview['rows']);
            $versions->record($notificationId,$before,$versions->rows($notificationId),$actor,$mode,trim($reason)?:'Import result records',$preview['source_hash']);
            if(!$this->dbActivityLog($actor,'IMPORT_EXAM_RESULTS','Validated rows: '.$count.'; source SHA256: '.$preview['source_hash'],'academic_section','EXAM_NOTIFICATION',(string)$notificationId)){throw new \RuntimeException('Unable to save audit trail.');}
            $this->conn->commit();return ['status'=>1,'imported'=>$count,'errors'=>[],'msg'=>'Imported '.$count.' validated draft records. Review them before publication.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage(),'errors'=>[]];}
    }

    public function previewResultsCsv(int $notificationId,string $path,string $mode='MERGE'): array
    {
        $this->assertResultsEditor();require_once __DIR__.'/../core/ResultsCsv.php';require_once __DIR__.'/../core/ResultVersions.php';
        $preview=(new \College\ResultsCsv($this->conn))->validate($notificationId,$path);
        if($preview['status']){
            $before=(new \College\ResultVersions($this->conn))->rows($notificationId);
            $diff=\College\ResultVersions::difference($before,$preview['rows'],$mode);
            $preview['changes']=['added'=>count($diff['added']),'changed'=>count($diff['changed']),'removed'=>count($diff['removed']),'unchanged'=>$diff['unchanged']];
            $preview['changed_sample']=array_slice($diff['changed'],0,20);$preview['removed_sample']=array_slice($diff['removed'],0,20);$preview['mode']=$mode;
            $preview['validation_hash']=hash('sha256',json_encode([$preview['validation_hash'],$before,$mode],JSON_THROW_ON_ERROR));
        }
        return $preview;
    }

    public function getImportHistory(int $notificationId): array
    {
        require_once __DIR__.'/FeatureManager.php';
        if(!in_array($_SESSION['role']??'', ['academic_section','admin','superadmin'],true)||!FeatureManager::canRoleAccess('MOD_RESULTS_PUBLISH')){throw new \DomainException('Results administration access required.');}
        $stmt=$this->conn->prepare("SELECT a.id,a.actor_user_id,a.reason,a.created_at,a.before_json,a.after_json,u.name actor_name FROM academic_record_snapshots a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.entity_type='RESULTS_IMPORT' AND a.entity_id=? ORDER BY a.id DESC LIMIT 100");$entity=(string)$notificationId;$stmt->bind_param('s',$entity);$stmt->execute();$history=[];
        foreach($stmt->get_result() as $row){$old=json_decode($row['before_json'],true,512,JSON_THROW_ON_ERROR);$new=json_decode($row['after_json'],true,512,JSON_THROW_ON_ERROR);unset($row['before_json'],$row['after_json']);$row['subject_id']=$new['subject_id'];$row['mode']=$new['mode'];$row['source_hash']=$new['source_hash'];$row['before_count']=count($old['rows']);$row['after_count']=count($new['rows']);$history[]=$row;}
        return $history;
    }

    // ==========================================
    // 3. Automated SEE Marks Ingestion
    // ==========================================

    /**
     * Auto-sync published exam results into external_assessment_marks for a given subject.
     */
    public function syncResultsToSeeMarks(int $subjectId, int $userId, ?int $notificationId = null, bool $replace = false, string $reason = ''): array
    {
        $this->conn->begin_transaction();
        try {
            $this->assertSubjectScope($subjectId);
            require_once __DIR__ . '/FeatureManager.php';
            if (!(PHP_SAPI === 'cli' && empty($_SESSION['role'])) && !FeatureManager::canAction('MOD_SEE_MARKS', 'edit')) {
                throw new \DomainException('SEE marks editing is disabled.');
            }
            if (!$notificationId) { throw new \DomainException('Select the exact published regular-exam attempt.'); }
            require_once __DIR__ . '/../core/ResultsSeeSync.php';
            $actor = (int)($_SESSION['userid'] ?? $userId);
            $summary = (new \College\ResultsSeeSync($this->conn))->subject($subjectId, $notificationId, $actor, !$replace, $reason);
            if (!$this->dbActivityLog($actor, 'SYNC_SEE_MARKS', json_encode($summary), $_SESSION['role'] ?? 'system', 'SEE_MARKS', (string)$subjectId)) {
                throw new \RuntimeException('Unable to save synchronization audit.');
            }
            $this->conn->commit();
            return ['status'=>1, 'synced_count'=>$summary['synced'], 'summary'=>$summary,
                'msg'=>self::syncSummary($summary)];
        } catch (\Throwable $e) { $this->conn->rollback(); return ['status'=>0, 'err'=>$e->getMessage()]; }
    }

    private static function syncSummary(array $summary): string
    {
        return 'SEE: ' . $summary['synced'] . ' synchronized; ' . $summary['preserved'] . ' existing marks preserved; '
            . $summary['pending'] . ' pending (withheld results or missing marks/estimation policy); ' . ($summary['not_applicable']??0) . ' internal-only records require no SEE.';
    }

    // ==========================================
    // 4. Student Results Lookup & Grade Card
    // ==========================================

    /**
     * Fetch published results for a student roll number.
     */
    private function assertResultViewer(string $roll): void
    {
        if (PHP_SAPI === 'cli' && empty($_SESSION['role'])) { return; }
        require_once __DIR__ . '/../core/RecordScope.php';
        (new \College\RecordScope($this->conn, $_SESSION))->documentOwner($roll);
    }

    public function getStudentResults(string $htno, ?int $notificationId = null): array
    {
        $this->assertResultViewer($htno);
        require_once __DIR__ . '/../core/PublishedResults.php';
        return (new \College\PublishedResults($this->conn))->forStudent($htno, $notificationId);
    }

    /**
     * Resolve grade points from the exact course regulation; no global grade scale.
     */
    public function getGradePoints(string $grade, ?int $subjectId = null): float
    {
        if (!$subjectId) { throw new \DomainException('Select the exact course offering to resolve its regulation grade points.'); }
        $this->assertSubjectScope($subjectId);
        require_once __DIR__ . '/../core/ResultsPolicy.php';
        $profile = (new \College\ResultsPolicy($this->conn))->forSubject($subjectId);
        foreach ($profile['config']['grade_bands'] as $band) {
            if (strcasecmp($grade, $band['grade']) === 0) { return (float)$band['points']; }
        }
        if (in_array(strtoupper($grade), ['AB','MP','WH','W'], true)) { return 0.0; }
        throw new \DomainException('Grade is not configured for this regulation.');
    }


    // ==========================================
    // 5. Credit-Based Promotion Pipeline (Optional & Advisory Simulation)
    // ==========================================

    /** Promotion decisions require a reviewed regulation policy and an attempt-aware credit ledger. */
    public function evaluateCreditPromotionSimulation(int $classId, string $regulationCode = ''): array
    {
        $this->assertClassScope($classId);
        return ['status'=>0,'err'=>'Promotion analysis awaits the approved college rules for this programme and regulation, semester cutoffs, and repeat-attempt credit treatment. No eligibility decision has been calculated.','policy_status'=>'REVIEW_REQUIRED'];
    }

    public function savePromotionCommitteeOverride(int $classId, string $rollNo, string $reason, int $userId, string $role): bool
    {
        $this->assertClassScope($classId);
        throw new \DomainException('Committee overrides require the approved promotion policy and a reviewed eligibility record.');
    }

    /**
     * Get active classes for promotion simulation.
     */
    public function getClassesForSimulation(): array
    {
        $sql = "
            SELECT c.id, c.classname, c.yearsem, c.acad_year, r.regulation,
                   (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 1) as student_count
            FROM classes c
            LEFT JOIN regulations r ON c.reg_id = r.id
            WHERE c.status = 1
            ORDER BY c.acad_year DESC, c.classname ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ==========================================
    // 6. Subject-Wise Student Result Lookup
    // ==========================================

    /**
     * Fetch published result for a student in a specific subject.
     */
    public function getStudentSubjectResult(string $htno, int $subjectId, ?string $subcode = null): ?array
    {
        $this->assertResultViewer($htno);
        $this->assertSubjectScope($subjectId);
        require_once __DIR__ . '/../core/PublishedResults.php';
        $rows = (new \College\PublishedResults($this->conn))->forStudent($htno, null, $subjectId)['results'];
        return $rows[0] ?? null;
    }


    // ==========================================
    // 7. CIA vs Exam Internal Marks Discrepancy Audit
    // ==========================================

    /**
     * Compute portal CIA final marks for a student and subject.
     */
    private array $ciaSubjectReports = [];

    public function calculateStudentPortalCia(int $studentId, int $subjectId, string $subType = 'theory', string $regulation = 'R23'): ?float
    {
        $this->assertStudentScope($studentId, $subjectId);
        require_once __DIR__ . '/../core/CiaService.php';
        $report = $this->ciaSubjectReports[$subjectId] ??= (new \College\CiaService($this->conn, $_SESSION ?? []))->forSubject($subjectId);
        return $report['values'][$subjectId][$studentId]['final'] ?? null;
    }

    /**
     * Audit and reconcile portal calculated CIA vs exam results uploaded final internal marks.
     */
    public function getNotificationCiaDiscrepancies(int $notificationId, ?int $subjectId = null, bool $onlyMismatches = false): array
    {
        $sql = "
            SELECT er.id as result_id, er.htno, er.student_id, er.subject_code, er.subject_name,
                   er.subject_id, er.internal_marks, er.external_marks, er.total_marks,
                   er.grade_letter, er.grade_points, er.credits_registered, er.credits_earned, er.result_status,
                   s.sub_fullname, s.sub_type, c.id as class_id, c.classname, c.reg_id, r.regulation,
                   st.username, st.username as student_name
            FROM exam_results er
            JOIN subjects s ON s.id = er.subject_id
            JOIN classes c ON c.id = s.class_id
            JOIN regulations r ON r.id = c.reg_id
            JOIN students st ON st.id = er.student_id
            WHERE er.notification_id = ?
        ";
        $params = [$notificationId];
        $types = "i";

        if ($subjectId !== null && $subjectId > 0) {
            $sql .= " AND er.subject_id = ?";
            $params[] = $subjectId;
            $types .= "i";
        }

        $sql .= " ORDER BY s.subcode ASC, er.htno ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $rows = [];
        $matchCount = 0;
        $varianceCount = 0;
        $discrepancyCount = 0;
        $missingPortalCount = 0;
        $exemptCount = 0;

        foreach ($records as $r) {
            $calculatedCia = $this->calculateStudentPortalCia((int)$r['student_id'], (int)$r['subject_id'], (string)$r['sub_type'], (string)$r['regulation']);
            $uploadedInternal = ($r['internal_marks'] !== null) ? (float)$r['internal_marks'] : null;

            $diff = null;
            $status = 'NO_DATA';

            if ($calculatedCia !== null && $uploadedInternal !== null) {
                $diff = round($calculatedCia - $uploadedInternal, 2);
                if (abs($diff) < 0.01) {
                    $status = 'MATCH';
                    $matchCount++;
                } elseif (abs($diff) <= 1.0) {
                    $status = 'ROUNDING_VARIANCE';
                    $varianceCount++;
                } else {
                    $status = 'DISCREPANCY';
                    $discrepancyCount++;
                }
            } elseif ($calculatedCia === null && $uploadedInternal !== null) {
                $status = 'MISSING_PORTAL_CIA';
                $missingPortalCount++;
            } elseif ($calculatedCia !== null && $uploadedInternal === null) {
                $status = 'EXEMPT_OR_MISSING';
                $exemptCount++;
            } else {
                $status = 'NO_INTERNAL';
                $exemptCount++;
            }

            if ($onlyMismatches && $status === 'MATCH') {
                continue;
            }

            $rows[] = [
                'result_id'         => (int)$r['result_id'],
                'htno'              => $r['htno'],
                'student_name'      => $r['student_name'] ?: $r['username'],
                'student_id'        => (int)$r['student_id'],
                'subject_id'        => (int)$r['subject_id'],
                'subject_code'      => $r['subject_code'],
                'subject_name'      => $r['subject_name'] ?: $r['sub_fullname'],
                'sub_type'          => $r['sub_type'],
                'classname'         => $r['classname'],
                'regulation'        => $r['regulation'],
                'calculated_cia'    => $calculatedCia,
                'uploaded_internal' => $uploadedInternal,
                'difference'        => $diff,
                'external_marks'    => $r['external_marks'],
                'total_marks'       => $r['total_marks'],
                'grade_letter'      => $r['grade_letter'],
                'result_status'     => $r['result_status'],
                'reconcile_status'  => $status
            ];
        }

        return [
            'status'  => 1,
            'summary' => [
                'total_records'       => count($records),
                'displayed_records'   => count($rows),
                'matches'             => $matchCount,
                'rounding_variances'  => $varianceCount,
                'discrepancies'       => $discrepancyCount,
                'missing_portal_cia'  => $missingPortalCount,
                'exempt_or_missing'   => $exemptCount
            ],
            'rows'    => $rows
        ];
    }
}

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
        $this->assertResultsEditor();
        $code = strtoupper(trim($data['notification_code'] ?? ''));
        $title = trim($data['title'] ?? '');
        $acadYear = trim($data['academic_year'] ?? '');
        $progId = (int)($data['program_id'] ?? 0);
        $regId = (int)($data['regulation_id'] ?? 0);
        $yearSem = trim($data['yearsem'] ?? '');
        $monthYear = trim($data['month_year'] ?? '');
        $releaseDate = !empty($data['release_date']) ? $data['release_date'] : date('Y-m-d');
        $isPublished = 0; // Every new notification starts as an unpublished draft.

        if (empty($code) || empty($title) || empty($acadYear) || $progId <= 0 || $regId <= 0 || empty($yearSem)) {
            return ['status' => 0, 'err' => 'Notification code, title, academic year, program, regulation, and year-sem are required.'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO exam_notifications (notification_code, title, academic_year, program_id, regulation_id, yearsem, month_year, release_date, is_published, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssiisssii", $code, $title, $acadYear, $progId, $regId, $yearSem, $monthYear, $releaseDate, $isPublished, $userId);

        if ($stmt->execute()) {
            $notifId = $this->conn->insert_id;
            $this->logs->actLog($userId, "CREATE_EXAM_NOTIFICATION", "Created exam notification: $title (Code: $code)");
            $this->dbActivityLog($userId, "CREATE_EXAM_NOTIFICATION", "Created exam notification: $title (Code: $code)", "admin", "EXAM_NOTIFICATION", (string)$notifId);
            return ['status' => 1, 'id' => $notifId, 'notification_id' => $notifId, 'msg' => 'Exam notification created successfully.'];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to create exam notification.'];
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
            $state=$status?'PUBLISHED':'DRAFT';$actor=(int)($_SESSION['userid']??$userId);
            $stmt=$this->conn->prepare('UPDATE exam_notifications SET is_published=?,workflow_status=? WHERE id=?');$stmt->bind_param('isi',$status,$state,$id);$stmt->execute();
            if(!$this->dbActivityLog($actor,'RESULTS_PUBLICATION',$state,'academic_section','EXAM_NOTIFICATION',(string)$id)){throw new \RuntimeException('Unable to save audit trail.');}
            $this->conn->commit();return ['status'=>1,'msg'=>$status?'Reviewed results published.':'Notification returned to draft.'];
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
            $actor=(int)($_SESSION['userid']??$userId);
            $stmt=$this->conn->prepare("UPDATE exam_notifications SET workflow_status='REVIEWED',reviewed_by=?,reviewed_at=NOW() WHERE id=?");$stmt->bind_param('ii',$actor,$id);$stmt->execute();
            $stmt=$this->conn->prepare("INSERT INTO workflow_review_history(entity_type,entity_id,actor_user_id,from_status,to_status,remarks) VALUES ('RESULTS',?,?,'DRAFT','REVIEWED',?)");$stmt->bind_param('iis',$id,$actor,$remarks);$stmt->execute();
            $this->conn->commit();return ['status'=>1,'msg'=>'Results reviewed. Publication is now available.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage()];}
    }

    // ==========================================
    // 2. Results CSV Bulk Import
    // ==========================================

    /**
     * Import results from CSV file for a notification.
     * Expects CSV headers: HTNO, SubCode, SubName, Internals, Externals, Total, Grade, GradePoints, Credits, Status
     */
    public function importResultsCsv(int $notificationId, string $csvFilePath, int $userId): array
    {
        $this->conn->begin_transaction();
        try{
            $this->assertResultsEditor();
            $lock=$this->conn->prepare('SELECT id FROM exam_notifications WHERE id=? FOR UPDATE');$lock->bind_param('i',$notificationId);$lock->execute();$lock->store_result();
            $preview=$this->previewResultsCsv($notificationId,$csvFilePath);
            if(!$preview['status']){$this->conn->rollback();return ['status'=>0,'err'=>'No rows imported. Fix every validation error first.','errors'=>$preview['errors']];}
            $stmt=$this->conn->prepare('INSERT INTO exam_results(notification_id,htno,student_id,subject_code,subject_name,subject_id,internal_marks,external_marks,total_marks,grade_letter,grade_points,credits_registered,credits_earned,result_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id),subject_id=VALUES(subject_id),subject_name=VALUES(subject_name),internal_marks=VALUES(internal_marks),external_marks=VALUES(external_marks),total_marks=VALUES(total_marks),grade_letter=VALUES(grade_letter),grade_points=VALUES(grade_points),credits_registered=VALUES(credits_registered),credits_earned=VALUES(credits_earned),result_status=VALUES(result_status),imported_at=CURRENT_TIMESTAMP');
            foreach($preview['rows'] as $row){$r=$row;$stmt->bind_param('isissidddsddds',$notificationId,$r['htno'],$r['student_id'],$r['subject_code'],$r['subject_name'],$r['subject_id'],$r['internal_marks'],$r['external_marks'],$r['total_marks'],$r['grade_letter'],$r['grade_points'],$r['credits_registered'],$r['credits_earned'],$r['result_status']);$stmt->execute();}
            $actor=(int)($_SESSION['userid']??$userId);$count=count($preview['rows']);
            if(!$this->dbActivityLog($actor,'IMPORT_EXAM_RESULTS','Validated rows: '.$count.'; source SHA256: '.$preview['source_hash'],'academic_section','EXAM_NOTIFICATION',(string)$notificationId)){throw new \RuntimeException('Unable to save audit trail.');}
            $this->conn->commit();return ['status'=>1,'imported'=>$count,'errors'=>[],'msg'=>'Imported '.$count.' validated draft records. Review them before publication.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage(),'errors'=>[]];}
    }

    public function previewResultsCsv(int $notificationId,string $path): array
    {
        $this->assertResultsEditor();require_once __DIR__.'/../core/ResultsCsv.php';
        return (new \College\ResultsCsv($this->conn))->validate($notificationId,$path);
    }

    // ==========================================
    // 3. Automated SEE Marks Ingestion
    // ==========================================

    /**
     * Auto-sync published exam results into external_assessment_marks for a given subject.
     */
    public function syncResultsToSeeMarks(int $subjectId,int $userId,?int $notificationId=null,bool $replace=false,string $reason=''): array
    {
        $this->conn->begin_transaction();
        try{
            $this->assertSubjectScope($subjectId);
            if(!$notificationId){throw new \DomainException('Select the exact published exam attempt to synchronise.');}
            $stmt=$this->conn->prepare('SELECT s.*,c.reg_id,c.yearsem,c.acad_year,sp.prog_id,cs.see_max_marks FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id LEFT JOIN curriculum_subjects cs ON cs.id=s.curr_sub_id AND cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem WHERE s.id=?');$stmt->bind_param('i',$subjectId);$stmt->execute();$subject=$stmt->get_result()->fetch_assoc();
            if(!$subject){throw new \DomainException('Subject offering not found.');}
            require_once __DIR__.'/../core/AssessmentPolicy.php';
            $profile=(new \College\AssessmentPolicy($this->conn))->forSubject($subjectId);
            if(!$profile['config']['net_max']){throw new \DomainException('Configure the external maximum for this course. A curriculum link is optional.');}
            (new \College\AssessmentPolicy($this->conn))->lockSubject($subjectId);
            $stmt=$this->conn->prepare("SELECT * FROM exam_notifications WHERE id=? AND is_published=1 AND workflow_status='PUBLISHED'");$stmt->bind_param('i',$notificationId);$stmt->execute();$notification=$stmt->get_result()->fetch_assoc();
            if(!$notification || (int)$notification['program_id']!==(int)$subject['prog_id'] || (int)$notification['regulation_id']!==(int)$subject['reg_id'] || $notification['yearsem']!==$subject['yearsem'] || $notification['academic_year']!==$subject['acad_year'] || $notification['exam_type']!=='REGULAR'){throw new \DomainException('The selected published attempt does not match this course offering.');}
            $stmt=$this->conn->prepare('SELECT er.student_id,er.external_marks,er.grade_letter,er.grade_points,er.result_status FROM exam_results er JOIN student_sub ss ON ss.stu_id=er.student_id AND ss.sub_id=er.subject_id WHERE er.notification_id=? AND er.subject_id=? AND (er.external_marks IS NOT NULL OR er.grade_points IS NOT NULL OR er.grade_letter IS NOT NULL)');$stmt->bind_param('ii',$notificationId,$subjectId);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if(!$rows){throw new \DomainException('No validated marks exist for this offering and attempt.');}
            $max=(float)$profile['config']['net_max'];$actor=(int)($_SESSION['userid']??$userId);$before=[];
            require_once __DIR__.'/../seeassessment.class.php';
            $seeHelper = new \SEEAssessment();
            foreach($rows as $row){$read=$this->conn->prepare('SELECT * FROM external_assessment_marks WHERE subject_id=? AND student_id=? FOR UPDATE');$read->bind_param('ii',$subjectId,$row['student_id']);$read->execute();$old=$read->get_result()->fetch_assoc();if($old&&(!$replace||trim($reason)==='')){throw new \DomainException('Existing marks require explicit replacement and a reason.');}$before[]=$old;}
            $stmt=$this->conn->prepare("INSERT INTO external_assessment_marks(student_id,subject_id,entry_mode,external_marks,max_marks,submitted_by,source_notification_id,normalisation_json,raw_marks,raw_max_marks,evidence_basis,is_current) VALUES (?,?,'DIRECT',?,?,?,?,?,?,?,'AGGREGATE_ESTIMATE',1) ON DUPLICATE KEY UPDATE entry_mode='DIRECT',external_marks=VALUES(external_marks),max_marks=VALUES(max_marks),submitted_by=VALUES(submitted_by),source_notification_id=VALUES(source_notification_id),q1_marks=NULL,choice_marks=NULL,normalisation_json=VALUES(normalisation_json),source_batch_id=NULL,raw_marks=VALUES(raw_marks),raw_max_marks=VALUES(raw_max_marks),evidence_basis='AGGREGATE_ESTIMATE',is_current=1,submitted_at=NOW()");
            foreach($rows as $row){
                $basis = 'DIRECT';
                if ($row['external_marks'] !== null) {
                    $marks = (float)$row['external_marks'];
                } else {
                    $grd = strtoupper(trim((string)($row['grade_letter'] ?? '')));
                    $pts = (float)($row['grade_points'] ?? 0.0);
                    $pct = $seeHelper->resolveRegulationGradePercentage((int)$subject['reg_id'], $grd, $pts);
                    $marks = round(($pct / 100.0) * $max, 2);
                    $basis = 'RESULTS_REGULATION_RANGE';
                }
                if($marks<0||$marks>$max){throw new \DomainException('External marks exceed the configured maximum.');}
                $json=json_encode(['basis'=>$basis,'grade'=>$row['grade_letter'],'grade_points'=>$row['grade_points'],'result_status'=>$row['result_status'],'source_notification_id'=>$notificationId],JSON_THROW_ON_ERROR);
                $stmt->bind_param('iiddiisdd',$row['student_id'],$subjectId,$marks,$max,$actor,$notificationId,$json,$marks,$max);
                $stmt->execute();
            }
            $old=json_encode($before,JSON_THROW_ON_ERROR);$new=json_encode(['notification_id'=>$notificationId,'maximum'=>$max,'rows'=>$rows],JSON_THROW_ON_ERROR);$dept=(int)$this->conn->query('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id='.(int)$subjectId)->fetch_row()[0];$entity=(string)$subjectId;$reason=trim($reason)?:'Reviewed published results synchronisation';$snapshot=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('RESULTS_SEE_SYNC',?,?,?,?,?,?)");$snapshot->bind_param('siisss',$entity,$dept,$actor,$reason,$old,$new);$snapshot->execute();
            if(!$this->dbActivityLog($actor,'SYNC_SEE_MARKS','Synced '.count($rows).' marks from notification '.$notificationId,'faculty','SEE_MARKS',(string)$subjectId)){throw new \RuntimeException('Unable to save audit trail.');}
            $this->conn->commit();return ['status'=>1,'synced_count'=>count($rows),'msg'=>'The selected published attempt was synchronised.'];
        }catch(\Throwable $e){$this->conn->rollback();return ['status'=>0,'err'=>$e->getMessage()];}
    }

    // ==========================================
    // 4. Student Results Lookup & Grade Card
    // ==========================================

    /**
     * Fetch published results for a student roll number.
     */
    public function getStudentResults(string $htno, ?int $notificationId = null): array
    {
        $htno = strtoupper(trim($htno));
        $sql = "
            SELECT er.*, en.title AS notification_title, en.month_year, en.release_date,
                   en.yearsem, en.academic_year, r.regulation
            FROM exam_results er
            JOIN exam_notifications en ON er.notification_id = en.id
            JOIN regulations r ON en.regulation_id = r.id
            WHERE er.htno = ? AND en.is_published = 1
        ";
        $params = [$htno];
        $types = "s";

        if ($notificationId !== null && $notificationId > 0) {
            $sql .= " AND er.notification_id = ?";
            $params[] = $notificationId;
            $types .= "i";
        }

        $sql .= " ORDER BY en.release_date DESC, er.subject_code ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $results = [];
        $totalCreditsRegistered = 0.0;
        $totalCreditsEarned = 0.0;
        $totalWeightedPoints = 0.0;

        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
            $credits = (float)$row['credits_registered'];
            $earned = (float)$row['credits_earned'];
            $points = (float)$row['grade_points'];

            $totalCreditsRegistered += $credits;
            $totalCreditsEarned += $earned;
            $totalWeightedPoints += ($credits * $points);
        }

        $sgpa = ($totalCreditsRegistered > 0) ? round($totalWeightedPoints / $totalCreditsRegistered, 2) : 0.00;

        return [
            'htno' => $htno,
            'results' => $results,
            'total_registered' => $totalCreditsRegistered,
            'total_earned' => $totalCreditsEarned,
            'sgpa' => $sgpa
        ];
    }

    /**
     * Map letter grades to standard 10-point grade scale points.
     */
    public function getGradePoints(string $grade): int
    {
        return match(strtoupper(trim($grade))) {
            'O', 'S' => 10,
            'A+', 'EX' => 9,
            'A' => 8,
            'B+' => 7,
            'B' => 6,
            'C' => 5,
            'D', 'P' => 4,
            default => 0
        };
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
        $htno = strtoupper(trim($htno));
        $code = strtoupper(trim((string)$subcode));
        $sql = "
            SELECT er.*, en.title AS notification_title, en.month_year, en.release_date,
                   en.yearsem, en.academic_year, r.regulation
            FROM exam_results er
            JOIN exam_notifications en ON er.notification_id = en.id
            JOIN regulations r ON en.regulation_id = r.id
            WHERE UPPER(TRIM(er.htno)) = ?
              AND (er.subject_id = ? OR UPPER(TRIM(er.subject_code)) = ?)
              AND en.is_published = 1
            ORDER BY en.release_date DESC, er.id DESC
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sis", $htno, $subjectId, $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }

    // ==========================================
    // 7. CIA vs Exam Internal Marks Discrepancy Audit
    // ==========================================

    /**
     * Compute portal CIA final marks for a student and subject.
     */
    public function calculateStudentPortalCia(int $studentId, int $subjectId, string $subType = 'theory', string $regulation = 'R23'): ?float
    {
        $st = strtolower(trim($subType));
        if ($st === 'lab' || $st === 'dti') {
            $stmt = $this->conn->prepare("SELECT day_to_day_marks, internal_test_marks FROM uglab_internal_assessment_marks WHERE student_id = ? AND subject_id = ? LIMIT 1");
            $stmt->bind_param("ii", $studentId, $subjectId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res && ($res['day_to_day_marks'] !== null || $res['internal_test_marks'] !== null)) {
                return round((float)($res['day_to_day_marks'] ?? 0) + (float)($res['internal_test_marks'] ?? 0), 2);
            }
            return null;
        }

        if ($st === 'project') {
            $stmt = $this->conn->prepare("SELECT component1_marks, component2_marks FROM ugproject_internal_assessment_marks WHERE student_id = ? AND subject_id = ? LIMIT 1");
            $stmt->bind_param("ii", $studentId, $subjectId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res && ($res['component1_marks'] !== null || $res['component2_marks'] !== null)) {
                return round((float)($res['component1_marks'] ?? 0) + (float)($res['component2_marks'] ?? 0), 2);
            }
            return null;
        }

        // UG Theory
        $stmt = $this->conn->prepare("
            SELECT assessment_number, subjective_marks, objective_marks, assignment_marks 
            FROM internal_assessment_marks 
            WHERE student_id = ? AND subject_id = ? 
            ORDER BY assessment_number ASC
        ");
        $stmt->bind_param("ii", $studentId, $subjectId);
        $stmt->execute();
        $marksList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($marksList)) {
            // Check PG internal marks
            $stmtPg = $this->conn->prepare("SELECT assessment_number, marks FROM pginternal_assessment_marks WHERE student_id = ? AND subject_id = ? ORDER BY assessment_number ASC");
            $stmtPg->bind_param("ii", $studentId, $subjectId);
            $stmtPg->execute();
            $pgList = $stmtPg->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtPg->close();
            if (!empty($pgList)) {
                $pgMap = [];
                foreach ($pgList as $p) { $pgMap[(int)$p['assessment_number']] = (float)$p['marks']; }
                $m1 = $pgMap[1] ?? null; $m2 = $pgMap[2] ?? null; $m3 = $pgMap[3] ?? 0.0;
                if ($m1 !== null && $m2 !== null) {
                    $best = max($m1, $m2); $lesser = min($m1, $m2);
                    return round((0.7 * $best) + (0.3 * $lesser) + $m3, 2);
                } elseif ($m1 !== null) {
                    return round($m1 + $m3, 2);
                }
            }
            return null;
        }

        $byAssessment = [];
        foreach ($marksList as $m) {
            $num = (int)$m['assessment_number'];
            $tot = (float)($m['subjective_marks'] ?? 0) + (float)($m['objective_marks'] ?? 0) + (float)($m['assignment_marks'] ?? 0);
            $byAssessment[$num] = $tot;
        }

        if (isset($byAssessment[1]) && isset($byAssessment[2])) {
            require_once __DIR__ . '/SettingsService.php';
            $calc = \Services\SettingsService::getInstance()->calculateCiaFinal((float)$byAssessment[1], (float)$byAssessment[2], $regulation);
            return (float)$calc['final_rounded'];
        } elseif (isset($byAssessment[1])) {
            return round((float)$byAssessment[1], 2);
        } elseif (isset($byAssessment[2])) {
            return round((float)$byAssessment[2], 2);
        }

        return null;
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

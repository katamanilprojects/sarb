<?php
namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';

class LessonPlanService extends \DBCredentials {
    private static ?self $instance = null;

    public function __construct() {
        parent::__construct();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Fetch complete lesson delivery plan for a subject offering
     */
    public function getPlanBySubject(int $sub_id): array {
        $this->assertSubjectScope($sub_id);
        $stmt = $this->conn->prepare("
            SELECT lp.*, co.co_number, co.co_description 
            FROM lesson_plans lp
            JOIN course_outcomes co ON lp.co_id = co.id
            WHERE lp.sub_id = ?
            ORDER BY lp.lecture_number ASC
        ");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Add or update an individual lecture in the lesson plan
     */
    public function addOrUpdateLecturePlan(array $data): array {
        $res = ['status' => 0];
        $this->conn->begin_transaction();
        try {
            $this->assertSubjectScope((int)$data['sub_id']);
            $sub=(int)$data['sub_id'];
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            $this->assertEditable($sub);
            $coCheck=$this->conn->prepare('SELECT id FROM course_outcomes WHERE id=? AND sub_id=?');
            $coCheck->bind_param('ii',$data['co_id'],$data['sub_id']);$coCheck->execute();
            if (!$coCheck->get_result()->fetch_row()) { throw new \DomainException('Select a CO belonging to this subject.'); }
            if ((int)$data['unit_number']<1 || (int)$data['unit_number']>5 || (int)$data['lecture_number']<1 || trim($data['planned_topic'])==='') { throw new \InvalidArgumentException('Enter a valid unit, lecture number, and topic.'); }
            $planned_hours = !empty($data['planned_hours']) ? (int)$data['planned_hours'] : 1;
            $bloom_level = !empty($data['bloom_level']) ? trim($data['bloom_level']) : 'L3-Apply';
            $pedagogy = !empty($data['pedagogy']) ? trim($data['pedagogy']) : 'Chalk & Talk';
            $ref = !empty($data['reference_material']) ? trim($data['reference_material']) : null;

            $stmt = $this->conn->prepare("
                INSERT INTO lesson_plans 
                    (sub_id, unit_number, lecture_number, planned_topic, co_id, bloom_level, pedagogy, reference_material, planned_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    unit_number = VALUES(unit_number),
                    planned_topic = VALUES(planned_topic),
                    co_id = VALUES(co_id),
                    bloom_level = VALUES(bloom_level),
                    pedagogy = VALUES(pedagogy),
                    reference_material = VALUES(reference_material),
                    planned_hours = VALUES(planned_hours)
            ");
            $stmt->bind_param(
                "iiisisssi",
                $data['sub_id'],
                $data['unit_number'],
                $data['lecture_number'],
                $data['planned_topic'],
                $data['co_id'],
                $bloom_level,
                $pedagogy,
                $ref,
                $planned_hours
            );

            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Lecture plan saved successfully.";
                $facultyId = $_SESSION['faculty_id'] ?? ($_SESSION['user_id'] ?? 0);
                if (!$this->dbActivityLog($facultyId, "SAVE_LECTURE_PLAN", "Saved lecture plan for subject ID " . $data['sub_id'] . ", lecture #" . $data['lecture_number'], "Faculty", "LESSON_PLAN", (string)$data['sub_id'])) { throw new \RuntimeException('Unable to save the lesson plan audit trail.'); }
                $this->conn->commit();
            } else {
                throw new \Exception($stmt->error);
            }
        } catch (\Throwable $e) {
            $this->conn->rollback();
            $res['status']=0;
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::addOrUpdateLecturePlan error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Delete a lecture plan entry
     */
    public function deleteLecturePlan(int $id, int $sub_id): array {
        $res = ['status' => 0];
        $this->conn->begin_transaction();
        try {
            $sub=$sub_id;
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            $this->assertEditable($sub);
            $stmt = $this->conn->prepare("DELETE FROM lesson_plans WHERE id = ? AND sub_id = ?");
            $stmt->bind_param("ii", $id, $sub_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Lecture plan deleted successfully.";
                $facultyId = $_SESSION['faculty_id'] ?? ($_SESSION['user_id'] ?? 0);
                if (!$this->dbActivityLog($facultyId, "DELETE_LECTURE_PLAN", "Deleted lecture plan item #$id for subject ID $sub_id", "Faculty", "LESSON_PLAN", (string)$id)) { throw new \RuntimeException('Unable to save the lesson plan audit trail.'); }
                $this->conn->commit();
            } else {
                throw new \Exception($stmt->error);
            }
        } catch (\Throwable $e) {
            $this->conn->rollback();
            $res['status']=0;
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::deleteLecturePlan error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Retrieve next suggested lecture based on count of conducted attendance periods
     */
    public function getNextSuggestedLecture(int $sub_id): ?array {
        $this->assertSubjectScope($sub_id);
        try {
            // Count distinct date+hour or total conducted periods for this subject
            $stmtCount = $this->conn->prepare("SELECT COUNT(DISTINCT date, hour) as conducted_count FROM diary WHERE sub_id = ?");
            $stmtCount->bind_param("i", $sub_id);
            $stmtCount->execute();
            $conducted = (int)($stmtCount->get_result()->fetch_assoc()['conducted_count'] ?? 0);
            $stmtCount->close();

            $nextLectureNum = $conducted + 1;
            $stmt = $this->conn->prepare("
                SELECT lp.*, co.co_number, co.co_description 
                FROM lesson_plans lp
                JOIN course_outcomes co ON lp.co_id = co.id
                WHERE lp.sub_id = ? AND lp.lecture_number = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $sub_id, $nextLectureNum);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $res ?: null;
        } catch (\Exception $e) {
            $this->logs->errLog("LessonPlanService::getNextSuggestedLecture error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compute syllabus progress & plan vs actual variance for NBA Criterion 2
     */
    public function getSyllabusVariance(int $sub_id): array {
        $this->assertSubjectScope($sub_id);
        $plan = $this->getPlanBySubject($sub_id);
        $totalPlanned = count($plan);

        $stmtActual = $this->conn->prepare("
            SELECT COUNT(DISTINCT d.date, d.hour) as actual_conducted,
                   COUNT(DISTINCT lp.co_id) as cos_covered,
                   COUNT(DISTINCT lp.id) as mapped_lectures
            FROM diary d
            LEFT JOIN lesson_plans lp ON d.lesson_plan_id = lp.id AND lp.sub_id=d.sub_id
            WHERE d.sub_id = ?
        ");
        $stmtActual->bind_param("i", $sub_id);
        $stmtActual->execute();
        $actualStats = $stmtActual->get_result()->fetch_assoc();
        $stmtActual->close();

        $actualConducted = (int)($actualStats['actual_conducted'] ?? 0);
        $mapped=(int)($actualStats['mapped_lectures']??0);
        $pctCompleted = ($totalPlanned > 0) ? round(($mapped / $totalPlanned) * 100, 1) : null;

        return [
            'total_planned_lectures' => $totalPlanned,
            'actual_conducted_lectures' => $actualConducted,
            'completion_percentage' => $pctCompleted,
            'cos_covered_count' => (int)($actualStats['cos_covered'] ?? 0),
            'mapped_lectures' => $mapped,
            'status' => ($mapped >= $totalPlanned && $totalPlanned > 0) ? 'COMPLETED' : 'IN_PROGRESS'
        ];
    }

    /**
     * Clear all lecture plans for a subject offering
     */
    public function clearPlanBySubject(int $sub_id): array {
        $this->assertSubjectScope($sub_id);
        $res = ['status' => 0];
        $this->conn->begin_transaction();
        try {
            $sub=$sub_id;
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            $this->assertEditable($sub);
            $stmt = $this->conn->prepare("DELETE FROM lesson_plans WHERE sub_id = ?");
            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "All lecture plans cleared.";
                $facultyId = $_SESSION['faculty_id'] ?? ($_SESSION['user_id'] ?? 0);
                if (!$this->dbActivityLog($facultyId, "CLEAR_LECTURE_PLANS", "Cleared all lecture plans for subject ID $sub_id", "Faculty", "LESSON_PLAN", (string)$sub_id)) { throw new \RuntimeException('Unable to save the lesson plan audit trail.'); }
                $this->conn->commit();
            } else {
                throw new \Exception($stmt->error);
            }
            $stmt->close();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            $res['status']=0;
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::clearPlanBySubject error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get end-of-semester reconciliation data between lesson_plans and diary
     */
    public function getReconciliationData(int $sub_id): array {
        $this->assertSubjectScope($sub_id);
        $planned = $this->getPlanBySubject($sub_id);
        $plannedById = [];
        foreach ($planned as $p) {
            $plannedById[(int)$p['id']] = $p;
        }

        $diaryEntries = [];
        $stmtDiary = $this->conn->prepare("
            SELECT d.id, d.date, d.hour, ct.hour_desc, d.diary, d.lesson_plan_id,
                   lp.lecture_number as mapped_lecture_num,
                   lp.planned_topic as mapped_topic,
                   lp.unit_number as mapped_unit_number,
                   co.co_number
            FROM diary d 
            LEFT JOIN class_timings ct ON d.hour = ct.id 
            LEFT JOIN lesson_plans lp ON d.lesson_plan_id = lp.id AND lp.sub_id=d.sub_id
            LEFT JOIN course_outcomes co ON lp.co_id = co.id
            WHERE d.sub_id = ? 
            ORDER BY d.date ASC, d.hour ASC
        ");
        if ($stmtDiary) {
            $stmtDiary->bind_param("i", $sub_id);
            $stmtDiary->execute();
            $res = $stmtDiary->get_result();
            while ($row = $res->fetch_assoc()) {
                $diaryEntries[] = $row;
            }
            $stmtDiary->close();
        }

        $totalPlanned = count($planned);
        $totalConducted = count($diaryEntries);

        // Track covered planned lectures and mappings
        $coveredPlanIds = [];
        $hasAnyMapping = false;
        $explicitCompCount = 0;

        foreach ($diaryEntries as $d) {
            $lpId = (int)($d['lesson_plan_id'] ?? 0);
            if ($lpId > 0 && isset($plannedById[$lpId])) {
                $coveredPlanIds[$lpId] = true;
                $hasAnyMapping = true;
            } elseif ($d['lesson_plan_id'] !== null && $lpId === 0) {
                $explicitCompCount++;
                $hasAnyMapping = true;
            }
        }

        if ($hasAnyMapping) {
            $coveredPlannedCount = count($coveredPlanIds);
            $completionPct = ($totalPlanned > 0) ? round(($coveredPlannedCount / $totalPlanned) * 100, 1) : 0.0;
            $compensatoryCount = $explicitCompCount + max(0, $totalConducted - ($coveredPlannedCount + $explicitCompCount));
        } else {
            // No evidence of coverage exists until diary records are mapped.
            $completionPct = 0.0;
            $compensatoryCount = max(0, $totalConducted - $totalPlanned);
        }

        // Identify uncovered planned lectures
        $uncoveredLectures = [];
        foreach ($planned as $p) {
            if (!isset($coveredPlanIds[$p['id']])) {
                $uncoveredLectures[] = $p;
            }
        }

        // Unit completion dates
        $unitCompletionDates = [
            1 => null, 2 => null, 3 => null, 4 => null, 5 => null
        ];

        // 1. From mapped diary entries
        foreach ($diaryEntries as $d) {
            $u = (int)($d['mapped_unit_number'] ?? 0);
            if ($u >= 1 && $u <= 5 && !empty($d['date'])) {
                if ($unitCompletionDates[$u] === null || $d['date'] > $unitCompletionDates[$u]) {
                    $unitCompletionDates[$u] = $d['date'];
                }
            }
        }

        $audit = $this->getCourseCompletionAudit($sub_id);

        return [
            'total_planned' => $totalPlanned,
            'total_conducted' => $totalConducted,
            'compensatory_count' => $compensatoryCount,
            'completion_pct' => $completionPct,
            'planned_lectures' => $planned,
            'planned_by_id' => $plannedById,
            'diary_entries' => $diaryEntries,
            'uncovered_lectures' => $uncoveredLectures,
            'has_mappings' => $hasAnyMapping,
            'suggested_unit_dates' => $unitCompletionDates,
            'audit' => $audit
        ];
    }

    /**
     * Save/update diary-to-lesson-plan mappings.
     * Maps each diary entry to a planned lecture (or marks it as compensatory/unmapped).
     * Automatically syncs diary.co_addressed from the mapped lecture plan's co_id.
     *
     * @param int $sub_id
     * @param array $mappings Associative array of [diary_id => lesson_plan_id]
     * @return array
     */
    public function saveDiaryLessonPlanMappings(int $sub_id, array $mappings): array {
        $this->assertSubjectScope($sub_id);
        $res = ['status' => 0, 'updated_count' => 0];
        $this->conn->begin_transaction();
        try {
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub_id);$lock->execute();$lock->store_result();
            $this->assertEditable($sub_id);
            $updateStmt = $this->conn->prepare("
                UPDATE diary 
                SET lesson_plan_id = ? 
                WHERE id = ? AND sub_id = ?
            ");

            $updated = 0;
            foreach ($mappings as $diaryId => $lpId) {
                $diaryId = (int)$diaryId;
                if ($diaryId <= 0) {
                    throw new \InvalidArgumentException('Invalid diary identifier.');
                }

                if (!empty($lpId) && (int)$lpId > 0) {
                    $check=$this->conn->prepare('SELECT id FROM lesson_plans WHERE id=? AND sub_id=?');
                    $check->bind_param('ii',$lpId,$sub_id);$check->execute();
                    if (!$check->get_result()->fetch_row()) { throw new \DomainException('The mapped lecture belongs to another subject.'); }
                }
                $check=$this->conn->prepare('SELECT id FROM diary WHERE id=? AND sub_id=?');$check->bind_param('ii',$diaryId,$sub_id);$check->execute();if(!$check->get_result()->fetch_row()){throw new \DomainException('The diary belongs to another subject.');}
                $lpIdInt = (!empty($lpId) && is_numeric($lpId)) ? (int)$lpId : null;

                if ($lpIdInt && $lpIdInt > 0) {
                    $updateStmt->bind_param("iii", $lpIdInt, $diaryId, $sub_id);
                } elseif ($lpId === "0" || $lpId === 0) {
                    // Explicitly marked as compensatory (no lecture plan)
                    $zeroLp = 0;
                    $updateStmt->bind_param("iii", $zeroLp, $diaryId, $sub_id);
                } else {
                    // Unmapped
                    $nullLp = null;
                    $updateStmt->bind_param("iii", $nullLp, $diaryId, $sub_id);
                }
                if ($updateStmt->execute()) {
                    $updated++;
                }
            }
            $updateStmt->close();

            $res['status'] = 1;
            $res['updated_count'] = $updated;
            $res['message'] = "Diary mappings saved successfully ({$updated} classes updated).";
            $facultyId = $_SESSION['faculty_id'] ?? ($_SESSION['user_id'] ?? 0);
            if(!$this->dbActivityLog($facultyId, "MAP_DIARY_LESSON_PLAN", "Mapped $updated diary entries for subject ID $sub_id", "Faculty", "LESSON_PLAN", (string)$sub_id)){throw new \RuntimeException('Unable to save diary mapping audit trail.');}
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();$res['status']=0;
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::saveDiaryLessonPlanMappings error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get Course Completion Audit Record for a subject
     */
    public function getCourseCompletionAudit(int $sub_id): ?array {
        $this->assertSubjectScope($sub_id);
        try {
            $stmt = $this->conn->prepare("SELECT * FROM course_completion_audits WHERE sub_id = ? LIMIT 1");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (\Exception $e) {
            $this->logs->errLog("LessonPlanService::getCourseCompletionAudit error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save Course Completion Audit Record
     */
    private function assertEditable(int $subId): void
    {
        $this->assertSubjectScope($subId);
        $stmt=$this->conn->prepare('SELECT faculty_signoff_status,hod_approval_status FROM course_completion_audits WHERE sub_id=?');
        $stmt->bind_param('i',$subId);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
        if ($row && $row['faculty_signoff_status']!=='DRAFT' && $row['hod_approval_status']!=='REJECTED') {
            throw new \DomainException('This course file is submitted or approved. The HOD must return it for revision before editing.');
        }
    }

    public function saveCourseCompletionAudit(array $data): array
    {
        $this->conn->begin_transaction();
        try {
            $sub=(int)($data['sub_id']??0);$this->assertSubjectScope($sub);
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            $this->assertEditable($sub);
            $faculty=(int)($_SESSION['facid']??$data['faculty_id']??0);
            if (!empty($_SESSION['role']) && $_SESSION['role']!=='faculty') { throw new \DomainException('Faculty submission is required.'); }
            $recon=$this->getReconciliationData($sub);$final=!empty($data['is_final_submission']);
            if ($final && (!$recon['has_mappings'] || $recon['total_planned']<1)) { throw new \DomainException('Map the teaching diary to the lesson plan before submitting for review.'); }
            $planned=(int)$recon['total_planned'];$actual=(int)$recon['total_conducted'];$comp=(int)$recon['compensatory_count'];$pct=(float)$recon['completion_pct'];
            $dates=[];
            for($u=1;$u<=5;$u++){$date=$data['unit'.$u.'_completion_date']??null;$date=$date?:null;
                $parsed=$date?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):null;
                if($date && (!$parsed || $parsed->format('Y-m-d')!==$date)){throw new \InvalidArgumentException('Enter valid unit completion dates.');}
                $dates[]=$date;}
            [$u1,$u2,$u3,$u4,$u5]=$dates;
            $reason=trim($data['deviations_reason']??'');$actions=trim($data['compensatory_actions']??'');$beyond=trim($data['topics_beyond_syllabus']??'');
            $status=$final?'SUBMITTED':'DRAFT';$at=$final?date('Y-m-d H:i:s'):null;
            $mapping=json_encode(array_column($recon['diary_entries'],'lesson_plan_id','id'),JSON_THROW_ON_ERROR);
            $stmt=$this->conn->prepare("INSERT INTO course_completion_audits(sub_id,faculty_id,total_planned_lectures,total_actual_conducted,total_compensatory_classes,syllabus_completion_pct,unit1_completion_date,unit2_completion_date,unit3_completion_date,unit4_completion_date,unit5_completion_date,deviations_reason,compensatory_actions,topics_beyond_syllabus,faculty_signoff_status,faculty_signoff_at,reconciliation_mapping) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE faculty_id=VALUES(faculty_id),total_planned_lectures=VALUES(total_planned_lectures),total_actual_conducted=VALUES(total_actual_conducted),total_compensatory_classes=VALUES(total_compensatory_classes),syllabus_completion_pct=VALUES(syllabus_completion_pct),unit1_completion_date=VALUES(unit1_completion_date),unit2_completion_date=VALUES(unit2_completion_date),unit3_completion_date=VALUES(unit3_completion_date),unit4_completion_date=VALUES(unit4_completion_date),unit5_completion_date=VALUES(unit5_completion_date),deviations_reason=VALUES(deviations_reason),compensatory_actions=VALUES(compensatory_actions),topics_beyond_syllabus=VALUES(topics_beyond_syllabus),faculty_signoff_status=VALUES(faculty_signoff_status),faculty_signoff_at=VALUES(faculty_signoff_at),reconciliation_mapping=VALUES(reconciliation_mapping),hod_approval_status='PENDING',hod_approval_at=NULL,hod_reviewed_by=NULL,hod_remarks=NULL");
            $stmt->bind_param('iiiiidsssssssssss',$sub,$faculty,$planned,$actual,$comp,$pct,$u1,$u2,$u3,$u4,$u5,$reason,$actions,$beyond,$status,$at,$mapping);$stmt->execute();
            if (!$this->dbActivityLog($_SESSION['userid']??0,'SAVE_COURSE_COMPLIANCE','Saved course delivery record from reconciled diary evidence.','faculty','COURSE_COMPLIANCE',(string)$sub)) {throw new \RuntimeException('Unable to save the audit trail.');}
            $this->conn->commit();return ['status'=>1,'message'=>$final?'Course file submitted for HOD review.':'Draft saved.'];
        } catch (\Throwable $e) {$this->conn->rollback();return ['status'=>0,'error'=>$e->getMessage()];}
    }
}

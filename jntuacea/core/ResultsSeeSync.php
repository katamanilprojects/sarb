<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/AssessmentPolicy.php';
require_once __DIR__ . '/ResultsPolicy.php';

/** Participates in the caller's transaction; never commits publication separately. */
final class ResultsSeeSync
{
    public function __construct(private \mysqli $db) {}

    public function subject(int $subject, int $notification, int $actor, bool $preserve = true, string $reason = ''): array
    {
        $read = $this->db->prepare("SELECT * FROM exam_notifications WHERE id=? FOR UPDATE");
        $read->bind_param('i', $notification); $read->execute(); $exam = $read->get_result()->fetch_assoc();
        if (!$exam || !$exam['is_published'] || $exam['workflow_status'] !== 'PUBLISHED' || $exam['exam_type'] !== 'REGULAR') {
            throw new \DomainException('Select a published regular-exam attempt for OBE synchronization.');
        }
        $policy = new AssessmentPolicy($this->db); $policy->lockSubject($subject);
        $profile = (new ResultsPolicy($this->db))->forSubject($subject);
        $read = $this->db->prepare('SELECT c.*,sp.prog_id,sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');
        $read->bind_param('i', $subject); $read->execute(); $course = $read->get_result()->fetch_assoc();
        if (!$course || (int)$course['reg_id'] !== (int)$exam['regulation_id'] || (int)$course['prog_id'] !== (int)$exam['program_id']
            || $course['yearsem'] !== $exam['yearsem'] || $course['acad_year'] !== $exam['academic_year']) {
            throw new \DomainException('The published attempt does not match this course offering.');
        }
        $read = $this->db->prepare('SELECT er.* FROM exam_results er JOIN student_sub ss ON ss.stu_id=er.student_id AND ss.sub_id=er.subject_id WHERE er.notification_id=? AND er.subject_id=? ORDER BY er.student_id');
        $read->bind_param('ii', $notification, $subject); $read->execute(); $rows = $read->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$rows) { throw new \DomainException('No validated results exist for this course and attempt.'); }
        $summary = ['synced'=>0, 'preserved'=>0, 'pending'=>0, 'not_applicable'=>0]; $before = []; $after = [];
        $maximum = $profile['config']['net_max'];
        foreach ($rows as $row) {
            if($maximum!==null && (float)$maximum===0.0){$summary['not_applicable']++;continue;}
            $student = (int)$row['student_id'];
            $read = $this->db->prepare('SELECT * FROM external_assessment_marks WHERE subject_id=? AND student_id=? FOR UPDATE');
            $read->bind_param('ii', $subject, $student); $read->execute(); $old = $read->get_result()->fetch_assoc();
            if ($old && $preserve && ((int)($old['source_notification_id'] ?? 0) !== $notification || $old['entry_mode'] === 'DETAILED')) {
                $summary['preserved']++; continue;
            }
            if ($old && !$preserve && trim($reason) === '') { throw new \DomainException('Replacing existing SEE marks requires a reason.'); }
            if ($row['result_status'] === 'WITHHELD' || $maximum === null || (float)$maximum <= 0) { $summary['pending']++; continue; }
            $basis = 'AGGREGATE_ESTIMATE';
            if (in_array($row['result_status'], ['ABSENT', 'MALPRACTICE'], true)) {
                $marks = 0.0; $source = 'RECORDED_' . $row['result_status'];
            } elseif ($row['external_marks'] !== null) {
                $marks = (float)$row['external_marks']; $source = 'RECORDED_EXTERNAL';
            } else {
                $strategy = $profile['config']['grade_strategy'];
                if ($strategy === 'NONE' || !$profile['config']['grade_bands']) { $summary['pending']++; continue; }
                $estimate = MarksNormalizer::grade($row['grade_letter'], $profile['config']['grade_bands'], $strategy);
                $marks = round($estimate['percentage'] * (float)$maximum / 100, 5);
                $source = 'CONFIGURED_GRADE_' . $strategy; $basis = 'GRADE_ESTIMATE';
            }
            MarksNormalizer::scale($marks, $maximum, $maximum);
            $json = json_encode(['basis'=>$source, 'grade'=>$row['grade_letter'], 'grade_points'=>$row['grade_points'],
                'result_status'=>$row['result_status'], 'source_notification_id'=>$notification, 'policy_hash'=>$profile['hash']], JSON_THROW_ON_ERROR);
            $raw = $row['external_marks'];
            $stmt = $this->db->prepare("INSERT INTO external_assessment_marks(student_id,subject_id,entry_mode,external_marks,max_marks,submitted_by,source_notification_id,normalisation_json,raw_marks,raw_max_marks,evidence_basis,is_current)
                VALUES (?,?,'DIRECT',?,?,?,?,?,?,?,?,1)
                ON DUPLICATE KEY UPDATE entry_mode='DIRECT',external_marks=VALUES(external_marks),max_marks=VALUES(max_marks),submitted_by=VALUES(submitted_by),source_notification_id=VALUES(source_notification_id),normalisation_json=VALUES(normalisation_json),raw_marks=VALUES(raw_marks),raw_max_marks=VALUES(raw_max_marks),evidence_basis=VALUES(evidence_basis),q1_marks=NULL,choice_marks=NULL,part_a_marks=NULL,part_b_marks=NULL,source_batch_id=NULL,is_current=1,submitted_at=CURRENT_TIMESTAMP");
            $stmt->bind_param('iiddiisdds', $student, $subject, $marks, $maximum, $actor, $notification, $json, $raw, $maximum, $basis); $stmt->execute();
            $before[] = $old; $after[] = ['student_id'=>$student, 'marks'=>$marks, 'maximum'=>$maximum, 'source'=>$source]; $summary['synced']++;
        }
        $oldJson = json_encode($before, JSON_THROW_ON_ERROR); $newJson = json_encode(['notification'=>$notification, 'rows'=>$after, 'summary'=>$summary], JSON_THROW_ON_ERROR);
        $entity = (string)$subject; $department = (int)$course['dept_id']; $reason = trim($reason) ?: 'Published regular-exam results; preserve manual and question-wise SEE marks.';
        $stmt = $this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('RESULTS_SEE_SYNC',?,?,?,?,?,?)");
        $stmt->bind_param('siisss', $entity, $department, $actor, $reason, $oldJson, $newJson); $stmt->execute();
        return $summary;
    }
}

<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/RecordScope.php';

final class AttendanceCorrection
{
    public function __construct(private \mysqli $db, private array $actor) {}

    public function updateStudent(int $student, int $subject, string $date, int $hour, string $status, string $reason): array
    {
        if (($this->actor['role'] ?? '') !== 'faculty' || empty($this->actor['userid'])) {
            throw new \DomainException('The assigned faculty must make this correction.');
        }
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<date('Y-m-d',strtotime('-7 days')) || $date>date('Y-m-d')) {
            throw new \InvalidArgumentException('Attendance corrections are allowed only within the last week.');
        }
        $reason=trim($reason);
        if (!in_array($status,['P','A'],true) || $hour<1 || $reason==='' || strlen($reason)>4000) {
            throw new \InvalidArgumentException('Select a valid status and enter a correction reason (up to 4000 characters).');
        }
        (new RecordScope($this->db,$this->actor))->student($student,$subject);
        $this->db->begin_transaction();
        try {
            $lock=$this->db->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$subject);$lock->execute();$lock->store_result();
            $stmt=$this->db->prepare('SELECT a.*,sp.dept_id FROM attendance a JOIN subjects s ON s.id=a.sub_id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE a.stu_id=? AND a.sub_id=? AND a.date=? AND a.hour=? FOR UPDATE');
            $stmt->bind_param('iisi',$student,$subject,$date,$hour);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if(count($rows)!==1){throw new \DomainException('Exactly one existing attendance record is required for correction.');}
            $before=$rows[0];
            if($before['status']===$status){throw new \DomainException('The selected status is already recorded.');}
            $stmt=$this->db->prepare('UPDATE attendance SET status=? WHERE id=?');$stmt->bind_param('si',$status,$before['id']);$stmt->execute();
            $after=$before;$after['status']=$status;
            $old=json_encode($before,JSON_THROW_ON_ERROR);$new=json_encode($after,JSON_THROW_ON_ERROR);
            $entity=(string)$before['id'];$dept=(int)$before['dept_id'];$actor=(int)$this->actor['userid'];$username=(string)$this->actor['user'];
            $stmt=$this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('ATTENDANCE',?,?,?,?,?,?)");
            $stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$old,$new);$stmt->execute();
            $stmt=$this->db->prepare("INSERT INTO audit_logs(user_id,username,role,action,entity_type,entity_id,target_user_id,status,details) VALUES (?,?,'faculty','CORRECT_ATTENDANCE','ATTENDANCE',?,?,'SUCCESS',?)");
            // students.id is an enrollment identity, not users.id; retain it in the snapshot only.
            $target=null;$stmt->bind_param('issis',$actor,$username,$entity,$target,$reason);$stmt->execute();
            $this->db->commit();return ['status'=>1,'message'=>'Attendance corrected with the original record retained in the audit trail.'];
        } catch (\Throwable $e) { $this->db->rollback();throw $e; }
    }

    public function decide(int $requestId, string $decision): array
    {
        if (($this->actor['role'] ?? '') !== 'hod' || empty($this->actor['userid'])) {
            throw new \DomainException('HOD review is required.');
        }
        if (!in_array($decision, ['Approved','Rejected'], true)) {
            throw new \InvalidArgumentException('Invalid review decision.');
        }
        $this->db->begin_transaction();
        try {
            $stmt=$this->db->prepare('SELECT ar.*,sp.dept_id FROM attendance_delete_requests ar JOIN subjects s ON s.id=ar.subject_id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE ar.id=? FOR UPDATE');
            $stmt->bind_param('i',$requestId);$stmt->execute();$request=$stmt->get_result()->fetch_assoc();
            if (!$request || $request['status'] !== 'Pending') { throw new \DomainException('This request is missing or has already been reviewed.'); }
            (new RecordScope($this->db,$this->actor))->subject((int)$request['subject_id']);
            $lock=$this->db->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$request['subject_id']);$lock->execute();$lock->store_result();
            $before=['request'=>$request,'attendance'=>[],'diary'=>[]];
            if ($decision === 'Approved') {
                foreach (['attendance','diary'] as $table) {
                    $stmt=$this->db->prepare("SELECT * FROM $table WHERE sub_id=? AND date=? AND hour=? FOR UPDATE");
                    $stmt->bind_param('isi',$request['subject_id'],$request['date'],$request['hour']);$stmt->execute();
                    $before[$table]=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                }
                foreach ($before['diary'] as $diary) {
                    if ((int)$diary['faculty_id'] !== (int)$request['faculty_id']) {
                        throw new \DomainException('This period includes another faculty member. Resolve the shared teaching record before deleting attendance.');
                    }
                }
                if (!$before['attendance'] && !$before['diary']) { throw new \DomainException('No records remain for this request.'); }
            }
            $actor=(int)$this->actor['userid'];$dept=(int)$request['dept_id'];$entity=(string)$requestId;
            $old=json_encode($before,JSON_THROW_ON_ERROR);$new=json_encode(['decision'=>$decision,'attendance_deleted'=>$decision==='Approved'?count($before['attendance']):0,'diary_deleted'=>$decision==='Approved'?count($before['diary']):0],JSON_THROW_ON_ERROR);
            $stmt=$this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('ATTENDANCE_REQUEST',?,?,?,?,?,?)");
            $stmt->bind_param('siisss',$entity,$dept,$actor,$request['reason'],$old,$new);$stmt->execute();
            if ($decision === 'Approved') {
                foreach (['diary','attendance'] as $table) {
                    $stmt=$this->db->prepare("DELETE FROM $table WHERE sub_id=? AND date=? AND hour=?");
                    $stmt->bind_param('isi',$request['subject_id'],$request['date'],$request['hour']);$stmt->execute();
                }
            }
            $stmt=$this->db->prepare("UPDATE attendance_delete_requests SET status=?,approval_date=NOW() WHERE id=? AND status='Pending'");
            $stmt->bind_param('si',$decision,$requestId);$stmt->execute();
            if ($stmt->affected_rows !== 1) { throw new \RuntimeException('The request changed during review.'); }
            $action=$decision==='Approved'?'APPROVE_ATTENDANCE_DELETE':'REJECT_ATTENDANCE_DELETE';
            $details='Decision recorded atomically with the original attendance and diary snapshot.';$username=$this->actor['user'];
            $stmt=$this->db->prepare("INSERT INTO audit_logs(user_id,username,role,action,entity_type,entity_id,status,details) VALUES (?,?,'hod',?,'ATTENDANCE_REQUEST',?,'SUCCESS',?)");
            $stmt->bind_param('issss',$actor,$username,$action,$entity,$details);$stmt->execute();
            $this->db->commit();
            return ['status'=>1,'message'=>'Decision saved with an audit snapshot.'];
        } catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }
}

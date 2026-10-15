<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/AttendanceCalculator.php';
require_once __DIR__ . '/RecordScope.php';

final class AttendanceService
{
    public function __construct(private \mysqli $db) {}

    public function forClass(int $classId, ?int $studentId = null, ?string $start = null, ?string $end = null): array
    {
        if (PHP_SAPI !== 'cli' || !empty($_SESSION['role'])) {
            $scope = new RecordScope($this->db, $_SESSION ?? []);
            $scope->classRecord($classId);
            if ($studentId !== null) { $scope->student($studentId); }
        }
        foreach ([$start, $end] as $date) {
            $parsed = $date === null ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($date !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !$parsed || $parsed->format('Y-m-d') !== $date)) {
                throw new \InvalidArgumentException('Invalid attendance date.');
            }
        }
        if (($start === null) !== ($end === null) || ($start !== null && $start > $end)) { throw new \InvalidArgumentException('Supply a valid start and end date together.'); }
        $stmt = $this->db->prepare('SELECT c.*,r.regulation,sp.dept_id,sp.spec_fullname,d.dept_fullname dept_name,p.prog_fullname FROM classes c JOIN regulations r ON r.id=c.reg_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id JOIN programs p ON p.id=sp.prog_id WHERE c.id=?');
        $stmt->bind_param('i',$classId);$stmt->execute();$class=$stmt->get_result()->fetch_assoc();
        if (!$class) { throw new \InvalidArgumentException('Class not found.'); }
        $regId = (int)$class['reg_id'];
        $stmt=$this->db->prepare('SELECT setting_key,setting_value FROM academic_settings WHERE reg_id=? ORDER BY setting_key');
        $stmt->bind_param('i',$regId);$stmt->execute();$settings=[];
        foreach($stmt->get_result() as $row){$settings[$row['setting_key']]=$row['setting_value'];}
        $keys=['min_aggregate'=>'attendance_min_aggregate_pct','condone_floor'=>'attendance_condone_floor_pct','min_subject'=>'attendance_min_subject_pct'];
        $rules=['min_aggregate'=>75.0,'condone_floor'=>65.0,'min_subject'=>40.0,'is_complete'=>true,'missing_keys'=>[]];
        foreach($keys as $key=>$setting){if(!isset($settings[$setting]) || !is_numeric($settings[$setting])){$rules['is_complete']=false;$rules['missing_keys'][]=$setting;}else{$rules[$key]=(float)$settings[$setting];}}
        if ($rules['min_subject'] < 0 || $rules['condone_floor'] < 0 || $rules['min_aggregate'] < 0 || $rules['min_aggregate'] > 100 || $rules['condone_floor'] > $rules['min_aggregate'] || $rules['min_subject'] > 100) { throw new \DomainException('Invalid attendance policy configuration.'); }
        $mode=$settings['attendance_permission_mode']??'REVIEW';
        if (!in_array($mode,['REVIEW','COUNT_AS_ATTENDED','EXCLUDE'],true)){throw new \DomainException('Invalid permission policy.');}
        $stmt=$this->db->prepare('SELECT id,subcode,sub_shortname,sub_fullname,sub_type FROM subjects WHERE class_id=? ORDER BY subject_sno,id');
        $stmt->bind_param('i',$classId);$stmt->execute();$subjects=[];
        foreach($stmt->get_result() as $row){$subjects[(int)$row['id']]=$row;}
        $sql='SELECT st.id,st.username htno,u.name,COALESCE(st.date_of_joining,(SELECT MIN(j.doj) FROM temp_joiningdates j WHERE j.htno=st.username),c.start_date) joining_date FROM students st JOIN users u ON u.username=st.username JOIN classes c ON c.id=st.class_id WHERE st.class_id=? AND st.status=1';
        if($studentId!==null){$sql.=' AND st.id=?';}
        $stmt=$this->db->prepare($sql);
        if($studentId!==null){$stmt->bind_param('ii',$classId,$studentId);}else{$stmt->bind_param('i',$classId);}
        $stmt->execute();$students=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sql="SELECT a.stu_id,a.sub_id,COUNT(*) conducted,SUM(a.status='P') present,
         SUM(a.status<>'P' AND EXISTS(SELECT 1 FROM student_permissions sp WHERE sp.stu_id=a.stu_id AND sp.class_id=st.class_id AND sp.date=a.date AND sp.hour=a.hour)) permission_absences
         FROM attendance a JOIN students st ON st.id=a.stu_id JOIN classes c ON c.id=st.class_id
         WHERE st.class_id=? AND a.date>=COALESCE(st.date_of_joining,(SELECT MIN(j.doj) FROM temp_joiningdates j WHERE j.htno=st.username),c.start_date)";
        $cutoff=$end??date('Y-m-d');
        $sql.=' AND a.date<=?';
        $args=[$classId,$cutoff];$types='is';
        if($studentId!==null){$sql.=' AND st.id=?';$args[]=$studentId;$types.='i';}
        if($start!==null){$sql.=' AND a.date BETWEEN ? AND ?';$args[]=$start;$args[]=$end;$types.='ss';}
        $sql.=' GROUP BY a.stu_id,a.sub_id';$stmt=$this->db->prepare($sql);$stmt->bind_param($types,...$args);$stmt->execute();$counts=[];
        foreach($stmt->get_result() as $row){$counts[(int)$row['stu_id']][(int)$row['sub_id']]=$row;}
        $expectedSql='SELECT st.id stu_id,ss.sub_id,COUNT(DISTINCT d.date,d.hour) expected FROM students st JOIN classes c ON c.id=st.class_id JOIN student_sub ss ON ss.stu_id=st.id JOIN diary d ON d.sub_id=ss.sub_id WHERE st.class_id=? AND d.date>=COALESCE(st.date_of_joining,(SELECT MIN(j.doj) FROM temp_joiningdates j WHERE j.htno=st.username),c.start_date) AND d.date<=?';
        $expectedArgs=[$classId,$cutoff];$expectedTypes='is';
        if($start!==null){$expectedSql.=' AND d.date>=?';$expectedArgs[]=$start;$expectedTypes.='s';}
        $expectedSql.=' GROUP BY st.id,ss.sub_id';
        $stmt=$this->db->prepare($expectedSql);$stmt->bind_param($expectedTypes,...$expectedArgs);$stmt->execute();$expected=[];
        foreach($stmt->get_result() as $row){$expected[(int)$row['stu_id']][(int)$row['sub_id']]=(int)$row['expected'];}
        $stmt=$this->db->prepare('SELECT ss.stu_id,ss.sub_id FROM student_sub ss JOIN students st ON st.id=ss.stu_id WHERE st.class_id=?');
        $stmt->bind_param('i',$classId);$stmt->execute();$mapped=[];
        foreach($stmt->get_result() as $row){$mapped[(int)$row['stu_id']][]=(int)$row['sub_id'];}
        $entries=[];
        foreach($students as $student){$sid=(int)$student['id'];$stats=[];
            foreach($mapped[$sid]??[] as $subId){if(!isset($subjects[$subId])){continue;}$sub=$subjects[$subId];$n=$counts[$sid][$subId]??['conducted'=>0,'present'=>0,'permission_absences'=>0];
                $effective=(int)$n['present']+($mode==='COUNT_AS_ATTENDED'?(int)$n['permission_absences']:0);
                $stats[$subId]=array_merge($n,['is_complete'=>(int)$n['conducted']===($expected[$sid][$subId]??0),'expected_periods'=>$expected[$sid][$subId]??0,'subcode'=>$sub['subcode'],'subname'=>$sub['sub_shortname'],'attended'=>$effective,'percentage'=>(int)$n['conducted']>0?round(100*$effective/$n['conducted'],2):null]);}
            $result=AttendanceCalculator::classify($stats,$rules,$mode);
            $entries[]=array_merge($student,$result,['aggregate_pct'=>$result['percentage'],'subject_stats'=>$stats,'remarks'=>match($result['category']){'ELIGIBLE'=>'Meets the configured attendance thresholds.','CONDONABLE'=>'Shortage requires authorised condonation review.','DETAINED'=>'Below the configured attendance thresholds; official decision requires publication.','POLICY_REVIEW'=>'Attendance policy or permission treatment requires confirmation.',default=>'Attendance is incomplete; eligibility cannot be determined.'}]);
        }
        return ['class'=>$class,'thresholds'=>$rules,'permission_mode'=>$mode,'students'=>$entries,
            'policy_hash'=>hash('sha256',json_encode($settings,JSON_THROW_ON_ERROR)),'cutoff'=>$end??date('Y-m-d'),
            'data_status'=>'PROVISIONAL'];
    }
}

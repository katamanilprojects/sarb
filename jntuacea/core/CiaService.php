<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/RecordScope.php';
require_once __DIR__ . '/CiaCalculator.php';

final class CiaService
{
    public function __construct(private \mysqli $db, private array $actor) {}

    public function forClass(int $class): array
    {
        if (($this->actor['role'] ?? '') === 'student') { throw new \DomainException('Student access requires an individual result.'); }
        $this->scope()->classRecord($class);
        return $this->load($class, null, null);
    }

    public function forSubject(int $subject, ?int $student = null): array
    {
        $this->scope()->subject($subject);
        if (($this->actor['role'] ?? '') === 'student' && $student === null) { throw new \DomainException('Select your own student record.'); }
        if ($student !== null) { $this->scope()->student($student,$subject); }
        $stmt=$this->db->prepare('SELECT class_id FROM subjects WHERE id=?');$stmt->bind_param('i',$subject);$stmt->execute();
        return $this->load((int)$stmt->get_result()->fetch_row()[0],$subject,$student);
    }

    private function scope(): RecordScope { return new RecordScope($this->db,$this->actor); }

    private function load(int $class, ?int $subject, ?int $student): array
    {
        $sql='SELECT s.*,c.reg_id,p.program_level,cs.cie_max_marks FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN programs p ON p.id=sp.prog_id LEFT JOIN curriculum_subjects cs ON cs.id=s.curr_sub_id AND cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem WHERE c.id=?';
        $args=[$class];$types='i';if($subject!==null){$sql.=' AND s.id=?';$args[]=$subject;$types.='i';}$sql.=' ORDER BY s.subject_sno,s.id';
        $stmt=$this->db->prepare($sql);$stmt->bind_param($types,...$args);$stmt->execute();$subjects=[];
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row){
            try{$this->scope()->subject((int)$row['id']);$subjects[(int)$row['id']]=$row;}catch(\DomainException $e){if(($this->actor['role']??'')!=='faculty'){throw $e;}}
        }
        $sql='SELECT st.id,st.username roll_number,u.name FROM students st JOIN users u ON u.username=st.username WHERE st.class_id=? AND st.status=1';
        $args=[$class];$types='i';if($student!==null){$sql.=' AND st.id=?';$args[]=$student;$types.='i';}$sql.=' ORDER BY st.username';
        $stmt=$this->db->prepare($sql);$stmt->bind_param($types,...$args);$stmt->execute();$students=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $report=['students'=>$students,'subjects'=>array_values($subjects),'values'=>[],'issues'=>[],'evidence'=>[],'policies'=>[]];if(!$subjects||!$students){return $report;}
        $ids=array_keys($subjects);$placeholders=implode(',',array_fill(0,count($ids),'?'));$types=str_repeat('i',count($ids));
        $stmt=$this->db->prepare('SELECT stu_id,sub_id FROM student_sub WHERE sub_id IN ('.$placeholders.')');$stmt->bind_param($types,...$ids);$stmt->execute();$mapped=[];
        foreach($stmt->get_result() as $row){$mapped[(int)$row['sub_id']][(int)$row['stu_id']]=true;}
        $evidence=[];
        foreach(['UG_THEORY'=>'internal_assessment_marks','UG_LAB'=>'uglab_internal_assessment_marks','UG_PROJECT'=>'ugproject_internal_assessment_marks','PG_THEORY'=>'pg_internal_assessment_marks'] as $kind=>$table){
            $sql='SELECT m.* FROM '.$table.' m JOIN students st ON st.id=m.student_id WHERE m.subject_id IN ('.$placeholders.') AND st.class_id=?';$args=[...$ids,$class];$queryTypes=$types.'i';
            if($student!==null){$sql.=' AND m.student_id=?';$args[]=$student;$queryTypes.='i';}
            $stmt=$this->db->prepare($sql);$stmt->bind_param($queryTypes,...$args);$stmt->execute();
            foreach($stmt->get_result() as $row){$evidence[$kind][(int)$row['subject_id']][(int)$row['student_id']][]=$row;}
        }
        $regulation=(int)reset($subjects)['reg_id'];$stmt=$this->db->prepare('SELECT setting_key,setting_value FROM academic_settings WHERE reg_id=?');$stmt->bind_param('i',$regulation);$stmt->execute();$settings=[];
        foreach($stmt->get_result() as $row){if(isset($settings[$row['setting_key']])){throw new \DomainException('Duplicate regulation setting: '.$row['setting_key']);}$settings[$row['setting_key']]=$row['setting_value'];}
        foreach($subjects as $sid=>$course){
            try{$policy=CiaCalculator::policy($course,$settings);$policyError=null;$report['policies'][$sid]=$policy;}catch(\Throwable $e){$policyError=$e->getMessage();$report['issues'][$sid]=$policyError;}
            foreach($students as $person){$id=(int)$person['id'];
                if(empty($mapped[$sid][$id])){$result=['status'=>'NOT_ENROLLED','final'=>null,'reason'=>'Student is not enrolled in this offering.'];}
                elseif($policyError!==null){$result=['status'=>'POLICY_REVIEW','final'=>null,'reason'=>$policyError];}
                else{try{$kind=$policy['kind']==='PG_LAB'?'PG_THEORY':$policy['kind'];$result=CiaCalculator::calculate($policy,$evidence[$kind][$sid][$id]??[]);}catch(\InvalidArgumentException $e){$result=['status'=>'INVALID_DATA','final'=>null,'reason'=>$e->getMessage()];}}
                $report['values'][$sid][$id]=$result;
                if (!empty($mapped[$sid][$id])) {
                    $kind = $course['program_level'] === 'PG' ? 'PG_THEORY' : ($policyError === null ? $policy['kind'] : 'NONE');
                    $report['evidence'][$sid][$id] = $evidence[$kind][$sid][$id] ?? [];
                }
            }
        }
        return $report;
    }
}

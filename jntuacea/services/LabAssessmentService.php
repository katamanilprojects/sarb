<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/SettingsService.php';
require_once __DIR__.'/FeatureManager.php';

final class LabAssessmentService extends \DBCredentials
{
    public function save(array $data): void
    {
        $sub=(int)($data['sub_id']??0);$assessment=(int)($data['assessment_number']??0);$mode=$data['submit_action']??'';
        $this->assertSubjectScope($sub);
        if($assessment<1||!in_array($mode,['save','submit'],true)){throw new \InvalidArgumentException('Invalid assessment submission.');}
        if(!empty($_SESSION['role'])&&!FeatureManager::canRoleWrite('MOD_CIA_MARKS')){throw new \DomainException('CIA entry is disabled for this account.');}
        $stmt=$this->conn->prepare('SELECT c.reg_id FROM subjects s JOIN classes c ON c.id=s.class_id WHERE s.id=?');$stmt->bind_param('i',$sub);$stmt->execute();$context=$stmt->get_result()->fetch_assoc();if(!$context){throw new \DomainException('Subject not found.');}
        $settings=SettingsService::getInstance();$reg=(string)$context['reg_id'];
        $maxDay=$settings->get('lab_cia_day_to_day_marks',$reg,null);$maxTest=$settings->get('lab_cia_internal_test_marks',$reg,null);
        if(!is_numeric($maxDay)||!is_numeric($maxTest)){throw new \DomainException('Confirm lab CIA maxima for this exact regulation before saving.');}
        $ids=$data['student_id']??[];$days=$data['day_to_day_marks']??[];$tests=$data['internal_test_marks']??[];
        if(!is_array($ids)||!$ids||count($ids)!==count($days)||count($ids)!==count($tests)||count(array_unique($ids))!==count($ids)){throw new \InvalidArgumentException('The student marks grid is incomplete or duplicated.');}
        $stmt=$this->conn->prepare('SELECT id,criterion_name,max_marks FROM lab_subject_rubrics WHERE subject_id=? ORDER BY sort_order,id');$stmt->bind_param('i',$sub);$stmt->execute();$criteria=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if(!$criteria){$criteria=$this->conn->query('SELECT c.id,c.criterion_name,c.max_marks FROM lab_rubric_criteria c JOIN lab_rubric_templates t ON t.id=c.template_id WHERE t.is_default=1 ORDER BY c.sort_order,c.id')->fetch_all(MYSQLI_ASSOC);}
        $criterionMap=array_column($criteria,null,'id');$rows=[];
        foreach($ids as $index=>$id){$id=(int)$id;$this->assertStudentScope($id,$sub);
            foreach([[$days[$index],$maxDay],[$tests[$index],$maxTest]] as [$mark,$max]){if(!is_numeric($mark)||(float)$mark<0||(float)$mark>(float)$max){throw new \InvalidArgumentException('Marks exceed the approved lab CIA limits.');}}
            $breakdown=$data['rubric_breakdown'][$id]??[];
            if($breakdown){if(!empty($_SESSION['role'])&&!FeatureManager::canRoleWrite('MOD_LAB_RUBRICS')){throw new \DomainException('Rubric entry is disabled for this account.');}
                if(count($breakdown)!==count($criterionMap)){throw new \InvalidArgumentException('Complete every rubric criterion.');}$sum=0;
                foreach($breakdown as $key=>$mark){if(!isset($criterionMap[$key])||!is_numeric($mark)||(float)$mark<0||(float)$mark>(float)$criterionMap[$key]['max_marks']){throw new \InvalidArgumentException('Invalid rubric criterion or score.');}$sum+=(float)$mark;}
                if(abs($sum-(float)$days[$index])>0.001){throw new \InvalidArgumentException('Rubric total must equal day-to-day marks.');}
            }
            $rows[]=['id'=>$id,'day'=>(float)$days[$index],'test'=>(float)$tests[$index],'rubric'=>$breakdown];
        }
        $this->conn->begin_transaction();
        try{
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub);$lock->execute();$lock->store_result();
            $table=$mode==='submit'?'uglab_internal_assessment_marks':'temp_uglab_internal_assessment_marks';
            foreach($rows as $row){
                $stmt=$this->conn->prepare('SELECT id FROM uglab_internal_assessment_marks WHERE student_id=? AND subject_id=? AND assessment_number=?');$stmt->bind_param('iii',$row['id'],$sub,$assessment);$stmt->execute();
                if($stmt->get_result()->fetch_row()){throw new \DomainException('Marks are already submitted for this student and assessment. Use the authorised correction workflow.');}
                $sql="INSERT INTO $table(student_id,subject_id,assessment_number,day_to_day_marks,internal_test_marks) VALUES (?,?,?,?,?)";
                if($mode==='save'){$sql.=' ON DUPLICATE KEY UPDATE day_to_day_marks=VALUES(day_to_day_marks),internal_test_marks=VALUES(internal_test_marks)';}
                $stmt=$this->conn->prepare($sql);$stmt->bind_param('iiidd',$row['id'],$sub,$assessment,$row['day'],$row['test']);$stmt->execute();$markId=$this->conn->insert_id;
                if($mode==='submit'){
                    foreach($row['rubric'] as $criterion=>$mark){$criterion=(int)$criterion;$name=$criterionMap[$criterion]['criterion_name'];$mark=(float)$mark;
                        $stmt=$this->conn->prepare('INSERT INTO lab_rubric_evaluations(uglab_mark_id,criterion_id,criterion_name,marks_obtained) VALUES (?,?,?,?)');$stmt->bind_param('iisd',$markId,$criterion,$name,$mark);$stmt->execute();}
                    $stmt=$this->conn->prepare('DELETE FROM temp_uglab_internal_assessment_marks WHERE student_id=? AND subject_id=? AND assessment_number=?');$stmt->bind_param('iii',$row['id'],$sub,$assessment);$stmt->execute();
                }
            }
            if(!$this->dbActivityLog($_SESSION['userid']??0,'SAVE_LAB_ASSESSMENT','Saved '.count($rows).' validated lab marks as '.$mode,'faculty','INTERNAL_MARKS',(string)$sub)){throw new \RuntimeException('Unable to save assessment audit trail.');}
            $this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

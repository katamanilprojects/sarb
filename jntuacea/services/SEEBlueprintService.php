<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';
require_once __DIR__.'/FeatureManager.php';

/** Revises paper metadata without silently discarding an existing marks grid. */
final class SEEBlueprintService extends \DBCredentials
{
    public function save(int $subject,int $component,array $input,bool $revise=false,string $reason=''): void
    {
        $this->assertSubjectScope($subject);if(!FeatureManager::canAction('MOD_SEE_MARKS','edit')){throw new \DomainException('SEE editing is disabled.');}
        $policy=new \College\AssessmentPolicy($this->conn);$oldQuestions=$policy->questions($subject,$component);$stmt=$this->conn->prepare("SELECT id FROM assessment_components WHERE id=? AND component_type LIKE 'SEE%'");$stmt->bind_param('i',$component);$stmt->execute();if(!$stmt->get_result()->fetch_row()){throw new \DomainException('Choose an external component.');}
        if(!$input||count($input)>250){throw new \InvalidArgumentException('Supply 1–250 questions.');}$validated=[];$allowedCOs=array_column($this->conn->query('SELECT id FROM course_outcomes WHERE sub_id='.$subject)->fetch_all(MYSQLI_ASSOC),'id');$blooms=array_column($this->conn->query('SELECT id FROM blooms_levels')->fetch_all(MYSQLI_ASSOC),'id');
        foreach($input as $q){$label=trim($q['label']??'');$type=trim($q['type']??'');$marks=\College\MarksNormalizer::number($q['marks']??null,'Question maximum');$bloom=(int)($q['blooms_level']??0);$cos=array_values(array_unique(array_map('intval',$q['cos']??[])));$key=strtolower($label);
            if(!preg_match('/^[a-zA-Z0-9_.-]{1,10}$/D',$label)||isset($validated[$key])||$marks<=0||strlen($type)>20||$type===''||!in_array($bloom,array_map('intval',$blooms),true)||!$cos||array_diff($cos,array_map('intval',$allowedCOs))){throw new \InvalidArgumentException('Question labels, maxima, Bloom levels and course CO mappings must be valid and unique.');}$validated[$key]=['label'=>$label,'type'=>$type,'marks'=>$marks,'bloom'=>$bloom,'cos'=>$cos];
        }
        $this->conn->begin_transaction();try{
            (new \College\AssessmentPolicy($this->conn))->lockSubject($subject);if($oldQuestions!==$policy->questions($subject,$component)){throw new \DomainException('Blueprint changed. Reload before saving.');}
            $stmt=$this->conn->prepare('SELECT sm.* FROM student_marks sm JOIN assessment_questions q ON q.id=sm.question_id WHERE q.component_id=? FOR UPDATE');$stmt->bind_param('i',$component);$stmt->execute();$oldMarks=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if($oldMarks&&(!$revise||trim($reason)==='')){throw new \DomainException('Marks already exist. Confirm blueprint revision and provide a reason; original marks will be retained in the audit.');}
            $oldByLabel=[];foreach($oldQuestions as $q){$oldByLabel[strtolower($q['question_label'])]=$q;}
            foreach($validated as $key=>$q){$id=(int)($oldByLabel[$key]['id']??0);foreach($oldMarks as $m){if((int)$m['question_id']===$id&&(float)$m['marks_obtained']>$q['marks']){throw new \DomainException('A revised maximum is below recorded marks. Correct the marks first.');}}
                if($id){$stmt=$this->conn->prepare('UPDATE assessment_questions SET question_label=?,question_type=?,marks=?,blooms_level_id=? WHERE id=?');$stmt->bind_param('ssdii',$q['label'],$q['type'],$q['marks'],$q['bloom'],$id);$stmt->execute();$this->conn->query('DELETE FROM question_co_mapping WHERE question_id='.$id);unset($oldByLabel[$key]);}
                else{$stmt=$this->conn->prepare('INSERT INTO assessment_questions(component_id,question_label,question_type,marks,blooms_level_id) VALUES (?,?,?,?,?)');$stmt->bind_param('issdi',$component,$q['label'],$q['type'],$q['marks'],$q['bloom']);$stmt->execute();$id=$this->conn->insert_id;}
                foreach($q['cos'] as $co){$stmt=$this->conn->prepare('INSERT INTO question_co_mapping(question_id,co_id) VALUES (?,?)');$stmt->bind_param('ii',$id,$co);$stmt->execute();}
            }
            foreach($oldByLabel as $q){$id=(int)$q['id'];$this->conn->query('DELETE FROM student_marks WHERE question_id='.$id);$this->conn->query('DELETE FROM question_co_mapping WHERE question_id='.$id);$this->conn->query('DELETE FROM assessment_questions WHERE id='.$id);}
            $stmt=$this->conn->prepare("SELECT * FROM external_assessment_marks WHERE subject_id=? AND entry_mode='DETAILED' FOR UPDATE");$stmt->bind_param('i',$subject);$stmt->execute();$totals=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt=$this->conn->prepare("UPDATE external_assessment_marks SET is_current=0 WHERE subject_id=? AND entry_mode='DETAILED'");$stmt->bind_param('i',$subject);$stmt->execute();
            $dept=(int)$this->conn->query('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id='.$subject)->fetch_row()[0];$actor=(int)$_SESSION['userid'];$entity=(string)$component;$before=json_encode(['questions'=>$oldQuestions,'marks'=>$oldMarks,'totals'=>$totals],JSON_THROW_ON_ERROR);$after=json_encode(array_values($validated),JSON_THROW_ON_ERROR);$reason=trim($reason)?:'Initial external paper blueprint';
            $stmt=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('SEE_BLUEPRINT',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$before,$after);$stmt->execute();if(!$this->dbActivityLog($actor,'SAVE_SEE_BLUEPRINT',$reason,$_SESSION['role'],'ASSESSMENT_COMPONENT',$entity)){throw new \RuntimeException('Unable to record blueprint revision.');}$this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/../core/MarksNormalizer.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';
require_once __DIR__.'/FeatureManager.php';

final class SeeMarksWriter extends \DBCredentials
{
    public function save(int $subject,array $rows,string $mode): array
    {
        $this->assertSubjectScope($subject);
        if(!in_array($mode,['DIRECT','DETAILED'],true)||!$rows){throw new \InvalidArgumentException('Supply validated marks.');}
        if(!(PHP_SAPI==='cli'&&empty($_SESSION['role']))&&!FeatureManager::canAction('MOD_SEE_MARKS','edit')){throw new \DomainException('SEE marks editing is disabled.');}
        $profile=(new \College\AssessmentPolicy($this->conn))->forSubject($subject);
        $seen=[];foreach($rows as &$row){$student=(int)$row['student_id'];if(isset($seen[$student])){throw new \InvalidArgumentException('Duplicate student.');}$seen[$student]=true;$this->assertStudentScope($student,$subject);$row['final_external']=\College\MarksNormalizer::number($row['final_external'],'Marks');$row['max_marks']=\College\MarksNormalizer::number($row['max_marks'],'Maximum');\College\MarksNormalizer::scale($row['final_external'],$row['max_marks'],$row['max_marks']);if($mode==='DIRECT'&&$profile['config']['net_max']!==null&&abs($row['max_marks']-$profile['config']['net_max'])>0.00001){throw new \DomainException('Maximum disagrees with the course assessment profile.');}}unset($row);
        $this->conn->begin_transaction();
        try{(new \College\AssessmentPolicy($this->conn))->lockSubject($subject);$before=[];$actor=(int)($_SESSION['userid']??0);
            foreach($rows as $row){$student=(int)$row['student_id'];if($mode==='DIRECT'&&!hash_equals($profile['hash'],(new \College\AssessmentPolicy($this->conn))->forSubject($subject)['hash'])){throw new \DomainException('Assessment rules changed. Reload marks entry.');}if($mode==='DETAILED'){$policy=new \College\AssessmentPolicy($this->conn);$current=$policy->forSubject($subject);$questions=$policy->questions($subject,(int)($row['component_id']??0));if(!hash_equals($row['policy_hash']??'',$current['hash'])||!hash_equals($row['question_hash']??'',hash('sha256',json_encode($questions,JSON_THROW_ON_ERROR)))){throw new \DomainException('Assessment rules changed. Review consolidation again.');}$scores=[];foreach($questions as $q){$read=$this->conn->prepare('SELECT marks_obtained FROM student_marks WHERE stu_id=? AND question_id=?');$read->bind_param('ii',$student,$q['id']);$read->execute();$mark=$read->get_result()->fetch_row();$scores[(int)$q['id']]=$mark!==null?(float)$mark[0]:null;}if(!hash_equals($row['source_scores_hash']??'',hash('sha256',json_encode($scores,JSON_THROW_ON_ERROR)))){throw new \DomainException('Question marks changed. Review consolidation again.');}}$stmt=$this->conn->prepare('SELECT * FROM external_assessment_marks WHERE student_id=? AND subject_id=? FOR UPDATE');$stmt->bind_param('ii',$student,$subject);$stmt->execute();$before[]=$stmt->get_result()->fetch_assoc();$q1=$row['q1_marks']??null;$choice=$row['choice_marks']??null;$raw=$row['gross_marks']??$row['final_external'];$rawMax=$row['gross_max']??$row['max_marks'];$basis=$mode==='DETAILED'?'QUESTION_WISE':'AGGREGATE_ESTIMATE';$json=json_encode($row['normalisation']??['basis'=>$basis],JSON_THROW_ON_ERROR);
                $stmt=$this->conn->prepare('INSERT INTO external_assessment_marks(student_id,subject_id,entry_mode,external_marks,max_marks,q1_marks,choice_marks,submitted_by,raw_marks,raw_max_marks,normalisation_json,evidence_basis,is_current) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE entry_mode=VALUES(entry_mode),external_marks=VALUES(external_marks),max_marks=VALUES(max_marks),q1_marks=VALUES(q1_marks),choice_marks=VALUES(choice_marks),submitted_by=VALUES(submitted_by),raw_marks=VALUES(raw_marks),raw_max_marks=VALUES(raw_max_marks),normalisation_json=VALUES(normalisation_json),evidence_basis=VALUES(evidence_basis),source_notification_id=NULL,source_batch_id=NULL,is_current=1,submitted_at=NOW()');$stmt->bind_param('iisddddiddss',$student,$subject,$mode,$row['final_external'],$row['max_marks'],$q1,$choice,$actor,$raw,$rawMax,$json,$basis);$stmt->execute();
            }
            $stmt=$this->conn->prepare('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$dept=(int)$stmt->get_result()->fetch_row()[0];$old=json_encode($before,JSON_THROW_ON_ERROR);$new=json_encode($rows,JSON_THROW_ON_ERROR);$entity=(string)$subject;$reason='Validated '.$mode.' SEE marks entry';
            $stmt=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('SEE_MARKS',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$old,$new);$stmt->execute();
            if(!$this->dbActivityLog($actor,'SAVE_SEE_MARKS',$reason,$_SESSION['role']??'system','SEE_MARKS',$entity)){throw new \RuntimeException('Unable to save SEE marks audit.');}$this->conn->commit();return ['status'=>1,'msg'=>'Validated SEE marks saved with original evidence retained.'];
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

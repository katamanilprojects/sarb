<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';
require_once __DIR__.'/FeatureManager.php';

final class QuestionMarksService extends \DBCredentials
{
    public function save(int $subject,int $component,array $grid,string $reason='Question marks entry'): int
    {
        $this->assertSubjectScope($subject);$policy=new \College\AssessmentPolicy($this->conn);$questions=$policy->questions($subject,$component);$lookup=array_column($questions,null,'id');
        if(!FeatureManager::canAction('MOD_SEE_MARKS','edit')){throw new \DomainException('Marks editing is disabled.');}
        if(!$grid){throw new \InvalidArgumentException('No marks supplied.');}$validated=[];
        foreach($grid as $student=>$scores){if(!is_array($scores)){throw new \InvalidArgumentException('Invalid marks grid.');}$student=(int)$student;$this->assertStudentScope($student,$subject);
            foreach($scores as $qid=>$value){$qid=(int)$qid;if(!isset($lookup[$qid])){throw new \DomainException('A question does not belong to this assessment.');}if($value===null||trim((string)$value)===''){$validated[]=[$student,$qid,null];continue;}$mark=\College\MarksNormalizer::number($value,'Question mark');if($mark>(float)$lookup[$qid]['marks']){throw new \InvalidArgumentException('Question mark exceeds its maximum.');}$validated[]=[$student,$qid,$mark];}
        }
        if(!$validated){throw new \InvalidArgumentException('No assessed marks supplied.');}
        $this->conn->begin_transaction();
        try{(new \College\AssessmentPolicy($this->conn))->lockSubject($subject);
            if($questions!==$policy->questions($subject,$component)){throw new \DomainException('Question metadata changed. Reload the grid.');}
            $before=[];foreach($validated as [$student,$qid,$mark]){$stmt=$this->conn->prepare('SELECT * FROM student_marks WHERE stu_id=? AND question_id=? FOR UPDATE');$stmt->bind_param('ii',$student,$qid);$stmt->execute();$before[]=$stmt->get_result()->fetch_assoc();if($mark===null){$stmt=$this->conn->prepare('DELETE FROM student_marks WHERE stu_id=? AND question_id=?');$stmt->bind_param('ii',$student,$qid);}else{$stmt=$this->conn->prepare('INSERT INTO student_marks(stu_id,question_id,marks_obtained) VALUES (?,?,?) ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained)');$stmt->bind_param('iid',$student,$qid,$mark);}$stmt->execute();}
            // Finalised detailed totals become stale after a question correction. Retain a snapshot, then require reconsolidation.
            $students=array_values(array_unique(array_column($validated,0)));foreach($students as $student){$stmt=$this->conn->prepare("SELECT * FROM external_assessment_marks WHERE student_id=? AND subject_id=? AND entry_mode='DETAILED' FOR UPDATE");$stmt->bind_param('ii',$student,$subject);$stmt->execute();$old=$stmt->get_result()->fetch_assoc();if($old){$before[]=['invalidated_final'=>$old];$stmt=$this->conn->prepare("UPDATE external_assessment_marks SET is_current=0 WHERE student_id=? AND subject_id=? AND entry_mode='DETAILED'");$stmt->bind_param('ii',$student,$subject);$stmt->execute();}}
            $this->snapshot($subject,(string)$component,$reason,$before,$validated);
            if(!$this->dbActivityLog($_SESSION['userid'],'SAVE_QUESTION_MARKS',$reason,$_SESSION['role'],'ASSESSMENT_COMPONENT',(string)$component)){throw new \RuntimeException('Unable to save marks audit.');}$this->conn->commit();return count($validated);
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }

    public function importCsv(int $subject,int $component,string $path): int
    {
        if(!is_file($path)||filesize($path)>10*1024*1024){throw new \InvalidArgumentException('Choose a CSV up to 10 MB.');}
        $questions=(new \College\AssessmentPolicy($this->conn))->questions($subject,$component);$labels=[];foreach($questions as $q){$labels[strtolower(trim($q['question_label']))]=(int)$q['id'];}
        $handle=fopen($path,'r');$header=fgetcsv($handle);if(!$header){fclose($handle);throw new \InvalidArgumentException('CSV is empty.');}$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);$names=array_map(static fn($v)=>strtolower(trim($v)),$header);
        if(count(array_unique($names))!==count($names)){fclose($handle);throw new \InvalidArgumentException('Duplicate CSV column.');}$rollIndex=array_search('rollnumber',$names,true);if($rollIndex===false){$rollIndex=array_search('htno',$names,true);}if($rollIndex===false){fclose($handle);throw new \InvalidArgumentException('Include RollNumber or HTNO.');}
        if(!array_intersect($names,array_keys($labels))){fclose($handle);throw new \InvalidArgumentException('Include at least one configured question column.');}
        if(array_diff($names,array_merge(array_keys($labels),['studentid','rollnumber','htno','studentname','name','subcode']))){fclose($handle);throw new \InvalidArgumentException('Unknown question or CSV column. Use the downloaded template.');}
        $idIndex=array_search('studentid',$names,true);$find=$this->conn->prepare('SELECT st.id FROM students st JOIN student_sub ss ON ss.stu_id=st.id WHERE ss.sub_id=? AND UPPER(TRIM(st.username))=?');$grid=[];$line=1;
        try{while(($row=fgetcsv($handle))!==false){$line++;if($line>10001){throw new \InvalidArgumentException('Limit: 10,000 rows.');}if(count($row)===1&&trim((string)$row[0])===''){continue;}if(count($row)!==count($header)){throw new \InvalidArgumentException('Column count mismatch at row '.$line);}$roll=strtoupper(trim($row[$rollIndex]));$find->bind_param('is',$subject,$roll);$find->execute();$matches=$find->get_result()->fetch_all(MYSQLI_ASSOC);if(count($matches)!==1){throw new \DomainException('Unmapped roll number at row '.$line);}$student=(int)$matches[0]['id'];if(isset($grid[$student])||($idIndex!==false&&trim($row[$idIndex])!==''&&(int)$row[$idIndex]!==$student)){throw new \InvalidArgumentException('Duplicate or conflicting student identity at row '.$line);}$grid[$student]=[];foreach($names as $i=>$name){if(isset($labels[$name])){$grid[$student][$labels[$name]]=$row[$i];}}}
        }finally{fclose($handle);}
        return $this->save($subject,$component,$grid,'Validated question-wise CSV import');
    }

    private function snapshot(int $subject,string $entity,string $reason,array $before,array $after): void
    {
        $stmt=$this->conn->prepare('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$dept=(int)$stmt->get_result()->fetch_row()[0];$actor=(int)$_SESSION['userid'];$old=json_encode($before,JSON_THROW_ON_ERROR);$new=json_encode($after,JSON_THROW_ON_ERROR);
        $stmt=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('QUESTION_MARKS',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$old,$new);$stmt->execute();
    }
}

<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/FeatureManager.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';
require_once __DIR__.'/../core/RecordScope.php';

/** An attainment input adapter independent of examination management/publication. */
final class AttainmentImportService extends \DBCredentials
{
    private function authorize(int $subject,bool $write=false): void
    {
        $this->assertSubjectScope($subject);
        if(PHP_SAPI==='cli'&&empty($_SESSION['role'])){return;}
        if(!in_array($_SESSION['role']??'',['faculty','hod','academic_section','admin','superadmin'],true)||!FeatureManager::canAction('MOD_SEE_MARKS',$write?'edit':'view')){throw new \DomainException('Attainment import is disabled for your account.');}
    }

    public function preview(int $subject,string $path,?int $component=null): array
    {
        $this->authorize($subject,true);$policy=(new \College\AssessmentPolicy($this->conn))->forSubject($subject);$config=$policy['config'];
        if(!is_file($path)||!is_readable($path)||filesize($path)>10*1024*1024){throw new \InvalidArgumentException('Choose a CSV up to 10 MB.');}
        $questions=$component?(new \College\AssessmentPolicy($this->conn))->questions($subject,$component):[];
        if($component&&!$questions){throw new \DomainException('Configure question metadata before importing question marks.');}
        $handle=fopen($path,'r');$header=fgetcsv($handle);if(!$header){fclose($handle);throw new \InvalidArgumentException('CSV is empty.');}
        $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);$names=array_map(static fn($v)=>strtolower(preg_replace('/[^a-z0-9]/i','',trim($v))),$header);
        if(count(array_unique($names))!==count($names)){fclose($handle);throw new \InvalidArgumentException('Column names must be unique.');}
        $aliases=['roll'=>['rollnumber','rollno','htno','hallticket'],'code'=>['subcode','subjectcode'],'marks'=>['externals','externalmarks','marks','netmarks'],'max'=>['maxmarks','externalmax','maximum'],'gross'=>['grossmarks'],'gross_max'=>['grossmax','grossmaximum'],'grade'=>['grade','gradeletter'],'points'=>['gradepoints','points'],'status'=>['status','resultstatus']];$cols=[];
        foreach($aliases as $key=>$options){if(count(array_intersect($options,$names))>1){fclose($handle);throw new \InvalidArgumentException('Use one unambiguous column for '.$key.'.');}foreach($options as $option){$i=array_search($option,$names,true);if($i!==false){$cols[$key]=$i;break;}}}
        if(!isset($cols['roll'],$cols['code'])){fclose($handle);throw new \InvalidArgumentException('CSV needs RollNumber and SubCode columns.');}
        $qCols=[];foreach($questions as $q){$label=strtolower(preg_replace('/[^a-z0-9]/i','',$q['question_label']));$i=array_search($label,$names,true);if($i===false){$i=array_search('q'.$label,$names,true);}if($i!==false){if(in_array($i,$qCols,true)){fclose($handle);throw new \InvalidArgumentException('Question labels are ambiguous after CSV normalization.');}$qCols[(int)$q['id']]=$i;}}
        if($component&&count($qCols)!==count($questions)){fclose($handle);throw new \InvalidArgumentException('Include every configured question column; blank values remain missing evidence.');}
        $groups=$config['choice_groups'];
        if($component&&$groups===null){require_once __DIR__.'/../seeassessment.class.php';$typeStmt=$this->conn->prepare('SELECT component_type FROM assessment_components WHERE id=?');$typeStmt->bind_param('i',$component);$typeStmt->execute();$type=$typeStmt->get_result()->fetch_row()[0];if(!str_starts_with($type,'SEE')){fclose($handle);throw new \DomainException('Select an external assessment component.');}$templates=(new \SEEAssessment())->getSEETemplates();$groups=\College\AssessmentPolicy::legacyGroups($questions,$templates[str_contains($type,'Split')?'see_split_35':'see_theory_70']);}
        $find=$this->conn->prepare('SELECT st.id FROM students st JOIN student_sub ss ON ss.stu_id=st.id WHERE ss.sub_id=? AND UPPER(TRIM(st.username))=?');
        $rows=[];$errors=[];$seen=[];$skipped=0;$line=1;
        try{while(($cells=fgetcsv($handle))!==false){$line++;if($line>10001){$errors[]='Limit: 10,000 rows.';break;}if(count($cells)===1&&trim((string)$cells[0])===''){continue;}
            try{
                if(count($cells)!==count($header)){throw new \InvalidArgumentException('Column count differs from the header.');}
                $get=static fn($key)=>isset($cols[$key])?trim((string)$cells[$cols[$key]]):'';
                $code=strtoupper($get('code'));if($code!==strtoupper(trim($policy['context']['subcode']))){$skipped++;continue;}
                $roll=strtoupper($get('roll'));if($roll===''||isset($seen[$roll])){throw new \InvalidArgumentException('Roll number is missing or duplicated for this course.');}$seen[$roll]=true;
                $find->bind_param('is',$subject,$roll);$find->execute();$matches=$find->get_result()->fetch_all(MYSQLI_ASSOC);if(count($matches)!==1){throw new \DomainException('Roll number does not resolve to one enrolled student in this course.');}$student=(int)$matches[0]['id'];
                $status=strtoupper($get('status'));if($status!==''&&!in_array($status,['PASS','FAIL','ABSENT','WITHHELD'],true)){throw new \InvalidArgumentException('Status must be PASS, FAIL, ABSENT, WITHHELD or blank.');}$grade=$get('grade');$points=$get('points')!==''?\College\MarksNormalizer::number($get('points'),'Grade points'):null;
                if($grade!==''&&$config['grade_bands']){$g=\College\MarksNormalizer::grade($grade,$config['grade_bands'],$config['grade_strategy']);if($points!==null&&abs($points-$g['points'])>.00001){throw new \InvalidArgumentException('Grade points disagree with the configured grade.');}}
                $target=$config['net_max'];$scores=[];$normal=[];
                if($component){foreach($qCols as $qid=>$index){$scores[$qid]=trim((string)$cells[$index])===''?null:$cells[$index];}$normal=\College\MarksNormalizer::questions($questions,$scores,$groups,$target);if(!$normal['complete']){throw new \InvalidArgumentException('The selected questions contain missing marks. Enter explicit zero for an assessed zero.');}$raw=$normal['gross'];$rawMax=$normal['gross_max'];$net=$normal['net'];$max=$normal['net_max'];$basis='QUESTION_WISE';}
                elseif($get('gross')!==''||$get('marks')!==''){
                    $raw=$get('gross')!==''?$get('gross'):$get('marks');$rawMax=$get('gross')!==''?($get('gross_max')!==''?$get('gross_max'):($config['gross_max']??null)):($get('max')!==''?$get('max'):($config['net_max']??null));
                    if($rawMax===null){throw new \InvalidArgumentException('Supply MaxMarks/GrossMax or configure the course maximum.');}$max=$target??\College\MarksNormalizer::number($rawMax,'Maximum');$net=\College\MarksNormalizer::scale($raw,$rawMax,$max);$raw=(float)$raw;$rawMax=(float)$rawMax;$basis='AGGREGATE_ESTIMATE';
                    if($get('gross')!==''&&$get('marks')!==''&&abs(\College\MarksNormalizer::number($get('marks'),'Net marks')-$net)>.011){throw new \InvalidArgumentException('Provided net marks disagree with gross-to-net scaling.');}
                }elseif($grade!==''&&$config['grade_bands']){
                    if(array_intersect($names,['total','totalmarks'])){throw new \DomainException('This file contains total course marks. Supply external Marks/MaxMarks or map the official external column; grade estimation must not silently replace an available total.');}
                    $g=\College\MarksNormalizer::grade($grade,$config['grade_bands'],$config['grade_strategy']);if($g['percentage']===null||!$target){throw new \DomainException('Grade-only conversion needs an explicitly selected estimation rule and net maximum.');}$raw=$g['percentage'];$rawMax=100.0;$max=$target;$net=$raw/100*$max;$basis='GRADE_ESTIMATE';$normal['grade_range']=$g['range'];
                }else{throw new \InvalidArgumentException('Provide aggregate marks, question marks, or a configured grade estimate.');}
                $normal+=['result_status'=>$status,'basis'=>$basis,'grade'=>$grade,'grade_points'=>$points,'raw_marks'=>$raw,'raw_max'=>$rawMax,'net_marks'=>$net,'net_max'=>$max];
                $rows[]=['student_id'=>$student,'roll'=>$roll,'raw'=>$raw,'raw_max'=>$rawMax,'marks'=>$net,'maximum'=>$max,'basis'=>$basis,'scores'=>$scores,'normalisation'=>$normal];
            }catch(\DomainException|\InvalidArgumentException $e){$errors[]='Row '.$line.': '.$e->getMessage();}
        }}finally{fclose($handle);}
        if(!$rows&&!$errors){$errors[]='No matching course records found.';}
        return ['status'=>$errors?0:1,'rows'=>$rows,'errors'=>$errors,'skipped_other_courses'=>$skipped,'policy_hash'=>$policy['hash'],'question_hash'=>hash('sha256',json_encode($questions,JSON_THROW_ON_ERROR)),'source_hash'=>hash_file('sha256',$path),'component_id'=>$component];
    }

    public function stage(int $subject,string $path,?int $component=null): array
    {
        $preview=$this->preview($subject,$path,$component);if(!$preview['status']){return $preview;}
        $actor=(int)($_SESSION['userid']??0);if(!$actor){throw new \DomainException('A signed-in import owner is required.');}$payload=json_encode($preview,JSON_THROW_ON_ERROR);
        $stmt=$this->conn->prepare('INSERT INTO attainment_import_batches(subject_id,owner_user_id,source_hash,policy_hash,payload_json) VALUES (?,?,?,?,?)');$stmt->bind_param('iisss',$subject,$actor,$preview['source_hash'],$preview['policy_hash'],$payload);$stmt->execute();$preview['batch_id']=$this->conn->insert_id;return $preview;
    }

    public function commit(int $batchId,bool $replace=false,string $reason=''): int
    {
        $this->conn->begin_transaction();
        try{
            $stmt=$this->conn->prepare('SELECT * FROM attainment_import_batches WHERE id=? FOR UPDATE');$stmt->bind_param('i',$batchId);$stmt->execute();$batch=$stmt->get_result()->fetch_assoc();
            if(!$batch||$batch['state']!=='DRAFT'||(int)$batch['owner_user_id']!==(int)($_SESSION['userid']??0)){throw new \DomainException('Choose your own pending import.');}$subject=(int)$batch['subject_id'];$this->authorize($subject,true);
            (new \College\AssessmentPolicy($this->conn))->lockSubject($subject);
            $policy=(new \College\AssessmentPolicy($this->conn))->forSubject($subject);$data=json_decode($batch['payload_json'],true,512,JSON_THROW_ON_ERROR);$component=$data['component_id'];
            if(!hash_equals($batch['policy_hash'],$policy['hash'])){throw new \DomainException('The assessment policy changed. Preview the source again.');}
            $questions=$component?(new \College\AssessmentPolicy($this->conn))->questions($subject,$component):[];if(!hash_equals($data['question_hash'],hash('sha256',json_encode($questions,JSON_THROW_ON_ERROR)))){throw new \DomainException('Question metadata changed. Preview the source again.');}
            $actor=(int)$_SESSION['userid'];$scope=new \College\RecordScope($this->conn,$_SESSION);$before=[];
            foreach($data['rows'] as $row){$student=(int)$row['student_id'];$scope->student($student,$subject);
                $stmt=$this->conn->prepare('SELECT * FROM external_assessment_marks WHERE student_id=? AND subject_id=? FOR UPDATE');$stmt->bind_param('ii',$student,$subject);$stmt->execute();$old=$stmt->get_result()->fetch_assoc();
                if($old&&(!$replace||trim($reason)==='')){throw new \DomainException('Existing marks require explicit replacement and a reason.');}$before[]=$old;
                if($component){$stmt=$this->conn->prepare('SELECT sm.* FROM student_marks sm JOIN assessment_questions q ON q.id=sm.question_id WHERE sm.stu_id=? AND q.component_id=?');$stmt->bind_param('ii',$student,$component);$stmt->execute();$before[]=['question_marks'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
                    $stmt=$this->conn->prepare('DELETE sm FROM student_marks sm JOIN assessment_questions q ON q.id=sm.question_id WHERE sm.stu_id=? AND q.component_id=?');$stmt->bind_param('ii',$student,$component);$stmt->execute();
                    foreach($row['scores'] as $qid=>$score){if($score===null){continue;}$qid=(int)$qid;$score=(float)$score;$stmt=$this->conn->prepare('INSERT INTO student_marks(stu_id,question_id,marks_obtained) VALUES (?,?,?) ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained)');$stmt->bind_param('iid',$student,$qid,$score);$stmt->execute();}
                }
                $mode=$component?'DETAILED':'DIRECT';$json=json_encode($row['normalisation'],JSON_THROW_ON_ERROR);
                $stmt=$this->conn->prepare('INSERT INTO external_assessment_marks(student_id,subject_id,entry_mode,external_marks,max_marks,raw_marks,raw_max_marks,normalisation_json,source_batch_id,evidence_basis,submitted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE entry_mode=VALUES(entry_mode),external_marks=VALUES(external_marks),max_marks=VALUES(max_marks),raw_marks=VALUES(raw_marks),raw_max_marks=VALUES(raw_max_marks),normalisation_json=VALUES(normalisation_json),source_batch_id=VALUES(source_batch_id),evidence_basis=VALUES(evidence_basis),is_current=1,source_notification_id=NULL,q1_marks=NULL,choice_marks=NULL,submitted_by=VALUES(submitted_by),submitted_at=NOW()');$stmt->bind_param('iisddddsisi',$student,$subject,$mode,$row['marks'],$row['maximum'],$row['raw'],$row['raw_max'],$json,$batchId,$row['basis'],$actor);$stmt->execute();
            }
            $oldJson=json_encode($before,JSON_THROW_ON_ERROR);$newJson=json_encode($data['rows'],JSON_THROW_ON_ERROR);$entity=(string)$subject;$reason=trim($reason)?:'Reviewed attainment input import';
            $stmt=$this->conn->prepare('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$dept=(int)$stmt->get_result()->fetch_row()[0];
            $stmt=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('ATTAINMENT_IMPORT',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$oldJson,$newJson);$stmt->execute();
            $stmt=$this->conn->prepare("UPDATE attainment_import_batches SET state='COMMITTED',committed_at=NOW() WHERE id=?");$stmt->bind_param('i',$batchId);$stmt->execute();
            if(!$this->dbActivityLog($actor,'COMMIT_ATTAINMENT_IMPORT','Batch '.$batchId.'; '.$reason,$_SESSION['role'],'SEE_MARKS',$entity)){throw new \RuntimeException('Unable to save import audit.');}
            $this->conn->commit();return count($data['rows']);
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

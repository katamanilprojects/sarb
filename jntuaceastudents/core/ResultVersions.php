<?php
declare(strict_types=1);
namespace College;

/** Canonical result sets make corrections reviewable and detect concurrent draft edits. */
final class ResultVersions
{
    private const FIELDS=['htno','student_id','subject_code','subject_name','subject_id','internal_marks','external_marks','total_marks','grade_letter','grade_points','credits_registered','credits_earned','result_status'];
    public function __construct(private \mysqli $db) {}
    public function rows(int $notification): array
    {
        $stmt=$this->db->prepare('SELECT * FROM exam_results WHERE notification_id=?');$stmt->bind_param('i',$notification);$stmt->execute();
        return self::canonical($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
    public static function canonical(array $rows): array
    {
        $canonical=[];
        foreach($rows as $row){$r=[];foreach(self::FIELDS as $field){$v=$row[$field]??null;
            if(in_array($field,['student_id','subject_id'],true)){$v=$v===null?null:(int)$v;}
            elseif(in_array($field,['internal_marks','external_marks','total_marks','grade_points','credits_registered','credits_earned'],true)){$v=$v===null?null:(float)$v;}
            else{$v=$v===null?null:(string)$v;}$r[$field]=$v;}
            $key=strtoupper(trim($r['htno'])).':'.strtoupper(trim($r['subject_code']));
            if(isset($canonical[$key])){throw new \DomainException('Duplicate admission number / subject code in the result set.');}$canonical[$key]=$r;
        }
        ksort($canonical);return $canonical;
    }
    public static function difference(array $before,array $incoming,string $mode): array
    {
        if(!in_array($mode,['MERGE','REPLACE'],true)){throw new \DomainException('Choose incremental update or complete-file replacement.');}
        $incoming=self::canonical($incoming);$after=$mode==='MERGE'?array_replace($before,$incoming):$incoming;ksort($after);
        $diff=['added'=>[],'changed'=>[],'removed'=>[],'unchanged'=>0];
        foreach($after as $key=>$row){if(!isset($before[$key])){$diff['added'][]=$row;}elseif($row!==$before[$key]){$diff['changed'][]=['before'=>$before[$key],'after'=>$row];}else{$diff['unchanged']++;}}
        foreach($before as $key=>$row){if(!isset($after[$key])){$diff['removed'][]=$row;}}
        return $diff+['after'=>$after];
    }
    public function record(int $notification,array $before,array $after,int $actor,string $mode,string $reason,string $sourceHash): void
    {
        if($actor<1){throw new \DomainException('An identified results editor is required.');}
        $subjects=[];foreach([...array_values($before),...array_values($after)] as $r){$subjects[(int)$r['subject_id']]=true;}
        foreach(array_keys($subjects) as $subject){
            $old=array_values(array_filter($before,static fn($r)=>(int)$r['subject_id']===$subject));$new=array_values(array_filter($after,static fn($r)=>(int)$r['subject_id']===$subject));
            if($old===$new){continue;}
            $stmt=$this->db->prepare('SELECT sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$row=$stmt->get_result()->fetch_row();
            if(!$row){throw new \DomainException('Resolve the result subject before saving correction history.');}
            $dept=(int)$row[0];$entity=(string)$notification;
            $b=json_encode(['subject_id'=>$subject,'rows'=>$old],JSON_THROW_ON_ERROR);$a=json_encode(['subject_id'=>$subject,'mode'=>$mode,'source_hash'=>$sourceHash,'rows'=>$new],JSON_THROW_ON_ERROR);
            $stmt=$this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES('RESULTS_IMPORT',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$b,$a);$stmt->execute();
        }
    }
}

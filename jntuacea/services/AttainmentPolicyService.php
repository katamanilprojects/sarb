<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';

final class AttainmentPolicyService extends \DBCredentials
{
    public function save(int $regulation,string $type,array $config,int $expectedVersion,?int $subject=null): int
    {
        if(($_SESSION['role']??'')!=='superadmin'){throw new \DomainException('Superadmin access required.');}
        \College\AssessmentPolicy::validate($config);
        if($subject){$this->assertSubjectScope($subject);$context=(new \College\AssessmentPolicy($this->conn))->forSubject($subject)['context'];if((int)$context['reg_id']!==$regulation){throw new \DomainException('The subject belongs to a different regulation.');}}
        else{$stmt=$this->conn->prepare('SELECT id FROM regulations WHERE id=?');$stmt->bind_param('i',$regulation);$stmt->execute();if(!$stmt->get_result()->fetch_row()){throw new \DomainException('Choose an existing regulation.');}$types=array_column($this->conn->query('SELECT DISTINCT sub_type FROM subjects')->fetch_all(MYSQLI_ASSOC),'sub_type');if($type!=='all'&&!in_array($type,$types,true)){throw new \InvalidArgumentException('Choose an existing subject type.');}}
        $json=json_encode($config,JSON_THROW_ON_ERROR);$actor=(int)$_SESSION['userid'];
        $this->conn->begin_transaction();
        try{
            // Regulation lock serializes profile inserts as well as edits.
            $stmt=$this->conn->prepare('SELECT id FROM regulations WHERE id=? FOR UPDATE');$stmt->bind_param('i',$regulation);$stmt->execute();$stmt->store_result();
            if($subject){$stmt=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$stmt->bind_param('i',$subject);$stmt->execute();$stmt->store_result();$stmt=$this->conn->prepare('SELECT * FROM subject_attainment_overrides WHERE subject_id=? FOR UPDATE');$stmt->bind_param('i',$subject);}
            else{$stmt=$this->conn->prepare('SELECT * FROM attainment_policies WHERE regulation_id=? AND subject_type=? FOR UPDATE');$stmt->bind_param('is',$regulation,$type);}
            $stmt->execute();$old=$stmt->get_result()->fetch_assoc();if((int)($old['version']??0)!==$expectedVersion){throw new \DomainException('This profile changed. Reload before saving.');}$version=$expectedVersion+1;
            if($subject){$stmt=$this->conn->prepare('INSERT INTO subject_attainment_overrides(subject_id,config_json,version,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),version=VALUES(version),updated_by=VALUES(updated_by)');$stmt->bind_param('isii',$subject,$json,$version,$actor);}
            else{$stmt=$this->conn->prepare('INSERT INTO attainment_policies(regulation_id,subject_type,config_json,version,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),version=VALUES(version),updated_by=VALUES(updated_by)');$stmt->bind_param('issii',$regulation,$type,$json,$version,$actor);}$stmt->execute();
            $stmt=$this->conn->prepare('SELECT s.id FROM subjects s JOIN classes c ON c.id=s.class_id WHERE c.reg_id=?');$stmt->bind_param('i',$regulation);$stmt->execute();foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $s){(new \College\AssessmentPolicy($this->conn))->forSubject((int)$s['id']);}
            $entity=$subject?'SUBJECT:'.$subject:'REGULATION:'.$regulation.':'.$type;$before=json_encode($old,JSON_THROW_ON_ERROR);$reason='Assessment profile version '.$version;$department=0;
            $stmt=$this->conn->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES ('ATTAINMENT_POLICY',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$department,$actor,$reason,$before,$json);$stmt->execute();
            if(!$this->dbActivityLog($actor,'SAVE_ATTAINMENT_POLICY',$reason,'superadmin','ATTAINMENT_POLICY',$entity)){throw new \RuntimeException('Unable to save profile audit.');}
            $this->conn->commit();return $version;
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

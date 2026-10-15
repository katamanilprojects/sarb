<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/FeatureManager.php';
require_once __DIR__.'/ReportSnapshotService.php';
require_once __DIR__.'/../core/AIProvider.php';
require_once __DIR__.'/../core/AssessmentPolicy.php';

final class AIJobService extends \DBCredentials
{
    public const TASKS=['ATTAINMENT_ANALYSIS','ATR_PROPOSAL'];
    private function access(string $action='view'): int
    {
        if(!FeatureManager::isModuleEnabled('MOD_AI_ASSISTANCE')||!in_array($_SESSION['role']??'',['faculty','hod','admin','academic_section','superadmin'],true)||!FeatureManager::canAction('MOD_AI_ASSISTANCE',$action)){throw new \DomainException('Optional AI assistance is disabled for your account.');}
        return (int)$_SESSION['userid'];
    }
    public function consent(string $task,bool $enabled): void
    {
        $owner=$this->access('edit');if(!in_array($task,self::TASKS,true)){throw new \InvalidArgumentException('Unknown AI task.');}$flag=(int)$enabled;
        $this->conn->begin_transaction();try{$stmt=$this->conn->prepare('INSERT INTO ai_job_consents(user_id,task_key,is_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled)');$stmt->bind_param('isi',$owner,$task,$flag);$stmt->execute();if(!$enabled){$stmt=$this->conn->prepare("UPDATE ai_jobs SET status='CANCELLED',claim_token=NULL WHERE owner_user_id=? AND task_key=? AND status IN ('QUEUED','RUNNING')");$stmt->bind_param('is',$owner,$task);$stmt->execute();}if(!$this->dbActivityLog($owner,'AI_CONSENT',$task.': '.($enabled?'enabled':'disabled'),$_SESSION['role'])){throw new \RuntimeException('Unable to record consent.');}$this->conn->commit();}catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
    public function queueSubject(int $subject,int $providerId,string $task,bool $reviewed): int
    {
        $owner=$this->access('edit');$this->assertSubjectScope($subject);if(!$reviewed||!in_array($task,self::TASKS,true)){throw new \DomainException('Review the source metrics and choose an AI task.');}
        if(!FeatureManager::canAction('MOD_SEE_MARKS','view')){throw new \DomainException('Attainment access is required.');}
        $stmt=$this->conn->prepare('SELECT is_enabled FROM ai_job_consents WHERE user_id=? AND task_key=?');$stmt->bind_param('is',$owner,$task);$stmt->execute();if(!(int)($stmt->get_result()->fetch_row()[0]??0)){throw new \DomainException('Opt in to this task first.');}
        $stmt=$this->conn->prepare('SELECT * FROM ai_providers WHERE id=? AND is_enabled=1');$stmt->bind_param('i',$providerId);$stmt->execute();$provider=$stmt->get_result()->fetch_assoc();if(!$provider){throw new \DomainException('Select an enabled provider.');}\College\AIProvider::validate($provider);
        require_once __DIR__.'/../seeassessment.class.php';$result=(new \SEEAssessment())->calculateSEECOAttainment($subject);if(empty($result['status'])){throw new \DomainException($result['err']??'Attainment evidence unavailable.');}
        $evidence=[];foreach($result['data'] as $num=>$co){$evidence['CO'.(int)$num]=array_intersect_key($co,array_flip(['attained_students','evaluated_students','missing_students','measured_students','estimated_students','cohort_percentage','attainment_level','basis','coverage_percentage','provisional']));}
        if(!$evidence){throw new \DomainException('No CO evidence available.');}$configHash=\College\AIProvider::configHash($provider);$policy=(new \College\AssessmentPolicy($this->conn))->forSubject($subject);$version='attainment-v1';
        $hash=hash('sha256',json_encode([$subject,$task,$evidence,$configHash,$policy['hash']],JSON_THROW_ON_ERROR));
        $this->conn->begin_transaction();try{
            $stmt=$this->conn->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$stmt->bind_param('i',$owner);$stmt->execute();$stmt->store_result();
            $stmt=$this->conn->prepare('SELECT is_enabled FROM ai_job_consents WHERE user_id=? AND task_key=? FOR UPDATE');$stmt->bind_param('is',$owner,$task);$stmt->execute();if(!(int)($stmt->get_result()->fetch_row()[0]??0)){throw new \DomainException('Consent was revoked.');}
            $stmt=$this->conn->prepare('SELECT id,status FROM ai_jobs WHERE owner_user_id=? AND task_key=? AND input_hash=? AND prompt_version=? FOR UPDATE');$stmt->bind_param('isss',$owner,$task,$hash,$version);$stmt->execute();$old=$stmt->get_result()->fetch_assoc();if($old){if($old['status']==='CANCELLED'){$retry=$this->conn->prepare("UPDATE ai_jobs SET status='QUEUED',error_message=NULL WHERE id=? AND attempts=0 AND output_json IS NULL");$retry->bind_param('i',$old['id']);$retry->execute();}$this->conn->commit();return (int)$old['id'];}
            $stmt=$this->conn->prepare("SELECT COUNT(*) FROM ai_jobs WHERE owner_user_id=? AND status IN ('QUEUED','RUNNING')");$stmt->bind_param('i',$owner);$stmt->execute();if((int)$stmt->get_result()->fetch_row()[0]>=100){throw new \DomainException('Your queue is full. Wait for pending jobs.');}
            $snapshot=(new ReportSnapshotService())->captureSubject('AI_ATTAINMENT',$subject,['assessment_policy_hash'=>$policy['hash'],'evidence_reviewed'=>true],$evidence);$sid=(int)$snapshot['id'];
            $stmt=$this->conn->prepare("UPDATE report_snapshots SET state='REVIEWED',reviewed_by=?,reviewed_at=NOW() WHERE id=?");$stmt->bind_param('ii',$owner,$sid);$stmt->execute();$payload=json_encode($evidence,JSON_THROW_ON_ERROR);
            $stmt=$this->conn->prepare('INSERT INTO ai_jobs(snapshot_id,owner_user_id,task_key,input_hash,prompt_version,provider_id,provider_config_hash,payload_json) VALUES (?,?,?,?,?,?,?,?)');$stmt->bind_param('iisssiss',$sid,$owner,$task,$hash,$version,$providerId,$configHash,$payload);$stmt->execute();$id=$this->conn->insert_id;
            if(!$this->dbActivityLog($owner,'QUEUE_AI_DRAFT','Task '.$task.'; provider '.$providerId,$_SESSION['role'],'AI_JOB',(string)$id)){throw new \RuntimeException('Unable to record AI job.');}$this->conn->commit();return $id;
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
    public function review(int $job,bool $approve,string $remarks): void
    {
        $actor=$this->access('approve');if(!in_array($_SESSION['role'],['hod','admin','academic_section','superadmin'],true)||trim($remarks)===''){throw new \DomainException('An authorized reviewer and remarks are required.');}
        $this->conn->begin_transaction();try{$stmt=$this->conn->prepare('SELECT j.*,s.subject_id FROM ai_jobs j JOIN report_snapshots s ON s.id=j.snapshot_id WHERE j.id=? FOR UPDATE');$stmt->bind_param('i',$job);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();if(!$row||$row['status']!=='DRAFT'){throw new \DomainException('Choose an unreviewed draft.');}$this->assertSubjectScope((int)$row['subject_id']);if($approve){$source=$this->conn->prepare('SELECT metrics_json FROM report_snapshots WHERE id=?');$source->bind_param('i',$row['snapshot_id']);$source->execute();\College\AIProvider::validateOutput(json_decode($row['output_json']??'null',true,512,JSON_THROW_ON_ERROR),json_decode($source->get_result()->fetch_row()[0],true,512,JSON_THROW_ON_ERROR));}$status=$approve?'APPROVED':'CANCELLED';$stmt=$this->conn->prepare('UPDATE ai_jobs SET status=?,reviewed_by=?,reviewed_at=NOW(),review_remarks=? WHERE id=?');$stmt->bind_param('sisi',$status,$actor,$remarks,$job);$stmt->execute();if(!$this->dbActivityLog($actor,'REVIEW_AI_DRAFT',$status.': '.$remarks,$_SESSION['role'],'AI_JOB',(string)$job)){throw new \RuntimeException('Unable to record draft review.');}$this->conn->commit();}catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
    public function retry(int $job): void
    {
        $owner=$this->access('edit');$stmt=$this->conn->prepare("UPDATE ai_jobs j JOIN ai_job_consents c ON c.user_id=j.owner_user_id AND c.task_key=j.task_key SET j.status='QUEUED',j.error_message=NULL,j.next_attempt_at=DATE_ADD(NOW(),INTERVAL 60 SECOND) WHERE j.id=? AND j.owner_user_id=? AND j.status='FAILED' AND j.attempts<3 AND c.is_enabled=1");$stmt->bind_param('ii',$job,$owner);$stmt->execute();if(!$stmt->affected_rows){throw new \DomainException('Retry requires your failed job, current consent and fewer than three attempts.');}
    }
    /** CLI only. Each claim has a token so a cancelled or expired run cannot publish. */
    public function runOne(?callable $transport=null): bool
    {
        if(PHP_SAPI!=='cli'){throw new \DomainException('Use the configured background worker.');}
        $this->conn->begin_transaction();try{
            $this->conn->query("UPDATE ai_jobs SET status=IF(attempts<3,'QUEUED','FAILED'),claim_token=NULL,error_message='Worker lease expired' WHERE status='RUNNING' AND locked_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
            $row=$this->conn->query("SELECT * FROM ai_jobs WHERE status='QUEUED' AND attempts<3 AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY id LIMIT 1 FOR UPDATE")->fetch_assoc();if(!$row){$this->conn->commit();return false;}$token=bin2hex(random_bytes(16));$id=(int)$row['id'];$stmt=$this->conn->prepare("UPDATE ai_jobs SET status='RUNNING',claim_token=?,locked_at=NOW(),attempts=attempts+1 WHERE id=?");$stmt->bind_param('si',$token,$id);$stmt->execute();$this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
        $oldSession=$_SESSION??[];
        try{
            $stmt=$this->conn->prepare('SELECT id,username,role FROM users WHERE id=? AND status=1');$stmt->bind_param('i',$row['owner_user_id']);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();if(!$user){throw new \DomainException('The job owner is inactive.');}$_SESSION=['userid'=>(int)$user['id'],'user'=>$user['username'],'role'=>$user['role']];FeatureManager::getInstance()->refreshCache();$this->access('edit');if(!FeatureManager::canAction('MOD_SEE_MARKS','view')){throw new \DomainException('The owner no longer has attainment access.');}
            $stmt=$this->conn->prepare('SELECT is_enabled FROM ai_job_consents WHERE user_id=? AND task_key=?');$stmt->bind_param('is',$row['owner_user_id'],$row['task_key']);$stmt->execute();if(!(int)($stmt->get_result()->fetch_row()[0]??0)){throw new \DomainException('Consent was revoked.');}
            $stmt=$this->conn->prepare('SELECT * FROM report_snapshots WHERE id=? AND owner_user_id=?');$stmt->bind_param('ii',$row['snapshot_id'],$row['owner_user_id']);$stmt->execute();$snapshot=$stmt->get_result()->fetch_assoc();if(!$snapshot||$snapshot['state']!=='REVIEWED'){throw new \DomainException('Source evidence is not reviewed.');}$this->assertSubjectScope((int)$snapshot['subject_id']);
            if(!hash_equals($snapshot['metrics_hash'],hash('sha256',$snapshot['scope_json']."\n".$snapshot['metrics_json']))||$snapshot['metrics_json']!==$row['payload_json']){throw new \DomainException('Source evidence integrity check failed.');}
            $stmt=$this->conn->prepare('SELECT * FROM ai_providers WHERE id=? AND is_enabled=1');$stmt->bind_param('i',$row['provider_id']);$stmt->execute();$provider=$stmt->get_result()->fetch_assoc();if(!$provider||!hash_equals($row['provider_config_hash'],\College\AIProvider::configHash($provider))){throw new \DomainException('Provider configuration changed. Queue a new draft.');}
            $evidence=json_decode($row['payload_json'],true,512,JSON_THROW_ON_ERROR);$response=$transport?$transport($provider,$evidence):\College\AIProvider::generate($provider,$evidence);$output=\College\AIProvider::validateOutput($response['draft'],$evidence);$json=json_encode($output,JSON_THROW_ON_ERROR);$model=(string)($response['model']??$provider['model']);$digest=$response['digest']??null;
            // Revocation during the network request cancels the job and prevents storing its result.
            $stmt=$this->conn->prepare("UPDATE ai_jobs j JOIN ai_job_consents c ON c.user_id=j.owner_user_id AND c.task_key=j.task_key JOIN ai_providers p ON p.id=j.provider_id JOIN system_feature_modules m ON m.module_key='MOD_AI_ASSISTANCE' SET j.status='DRAFT',j.output_json=?,j.model_id=?,j.model_digest=?,j.completed_at=NOW(),j.claim_token=NULL WHERE j.id=? AND j.claim_token=? AND j.status='RUNNING' AND c.is_enabled=1 AND p.is_enabled=1 AND m.is_enabled_globally=1 AND p.version=?");$providerVersion=(int)$provider['version'];$stmt->bind_param('sssisi',$json,$model,$digest,$id,$token,$providerVersion);$stmt->execute();if(!$stmt->affected_rows){$cancel=$this->conn->prepare("UPDATE ai_jobs SET status='CANCELLED',claim_token=NULL,error_message='Provider or consent disabled during generation' WHERE id=? AND claim_token=? AND status='RUNNING'");$cancel->bind_param('is',$id,$token);$cancel->execute();}
        }catch(\Throwable $e){$message=$e instanceof \DomainException?$e->getMessage():'Provider or worker failure; verify configuration and retry.';$stmt=$this->conn->prepare("UPDATE ai_jobs SET status='FAILED',error_message=?,claim_token=NULL WHERE id=? AND claim_token=? AND status='RUNNING'");$stmt->bind_param('sis',$message,$id,$token);$stmt->execute();}
        finally{$_SESSION=$oldSession;FeatureManager::getInstance()->refreshCache();}
        return true;
    }
}

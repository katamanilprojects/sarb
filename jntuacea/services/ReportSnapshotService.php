<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';

final class ReportSnapshotService extends \DBCredentials
{
    public function captureSubject(string $key,int $subject,array $scope,array $metrics): array
    {
        $this->assertSubjectScope($subject);$actor=(int)($_SESSION['userid']??0);
        if(!$actor){throw new \DomainException('A signed-in report owner is required.');}
        $stmt=$this->conn->prepare('SELECT c.reg_id,sp.dept_id FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$context=$stmt->get_result()->fetch_assoc();if(!$context){throw new \DomainException('Subject context not found.');}
        $stmt=$this->conn->prepare('SELECT setting_key,setting_value,data_type FROM academic_settings WHERE reg_id=? ORDER BY setting_key');$stmt->bind_param('i',$context['reg_id']);$stmt->execute();$policy=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        require_once __DIR__.'/../core/AssessmentPolicy.php';$assessmentPolicy=(new \College\AssessmentPolicy($this->conn))->forSubject($subject);
        $policyHash=hash('sha256',json_encode([$policy,$assessmentPolicy['hash']],JSON_THROW_ON_ERROR));$scope['regulation_id']=(int)$context['reg_id'];$scope['subject_id']=$subject;$scope['source_version']='2026.10.08.2';
        $scopeJson=json_encode($scope,JSON_THROW_ON_ERROR);$metricsJson=json_encode($metrics,JSON_THROW_ON_ERROR);$metricsHash=hash('sha256',$scopeJson."\n".$metricsJson);$cutoff=date('Y-m-d H:i:s');$dept=(int)$context['dept_id'];
        $stmt=$this->conn->prepare('INSERT INTO report_snapshots(report_key,owner_user_id,department_id,subject_id,cutoff_at,policy_hash,metrics_hash,scope_json,metrics_json) VALUES (?,?,?,?,?,?,?,?,?)');$stmt->bind_param('siiisssss',$key,$actor,$dept,$subject,$cutoff,$policyHash,$metricsHash,$scopeJson,$metricsJson);$stmt->execute();
        return ['id'=>$this->conn->insert_id,'cutoff'=>$cutoff,'policy_hash'=>$policyHash,'metrics_hash'=>$metricsHash];
    }
}

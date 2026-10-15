<?php
declare(strict_types=1);
namespace Services;
require_once __DIR__.'/../dbcredentials.class.php';
require_once __DIR__.'/../core/AIProvider.php';
final class AIProviderSettings extends \DBCredentials
{
    public function save(array $data,int $id=0,int $expectedVersion=0): int
    {
        if(($_SESSION['role']??'')!=='superadmin'){throw new \DomainException('Superadmin access required.');}
        \College\AIProvider::validate($data);$name=trim((string)($data['name']??''));if($name===''||strlen($name)>100){throw new \InvalidArgumentException('Enter a provider name up to 100 characters.');}
        $actor=(int)$_SESSION['userid'];$enabled=(int)!empty($data['is_enabled']);$timeout=(int)$data['timeout_seconds'];$endpoint=rtrim($data['endpoint'],'/');$env=trim($data['credential_env']??'');$version=$expectedVersion+1;
        $this->conn->begin_transaction();try{
            if($id){$stmt=$this->conn->prepare('SELECT * FROM ai_providers WHERE id=? FOR UPDATE');$stmt->bind_param('i',$id);$stmt->execute();$old=$stmt->get_result()->fetch_assoc();if(!$old||(int)$old['version']!==$expectedVersion){throw new \DomainException('Provider changed. Reload before saving.');}
                $stmt=$this->conn->prepare('UPDATE ai_providers SET name=?,kind=?,endpoint=?,model=?,credential_env=?,is_enabled=?,timeout_seconds=?,version=?,updated_by=? WHERE id=?');$stmt->bind_param('sssssiiiii',$name,$data['kind'],$endpoint,$data['model'],$env,$enabled,$timeout,$version,$actor,$id);
            }else{$stmt=$this->conn->prepare('INSERT INTO ai_providers(name,kind,endpoint,model,credential_env,is_enabled,timeout_seconds,updated_by) VALUES (?,?,?,?,?,?,?,?)');$stmt->bind_param('sssssiii',$name,$data['kind'],$endpoint,$data['model'],$env,$enabled,$timeout,$actor);}$stmt->execute();$id=$id?:$this->conn->insert_id;
            if(!$this->dbActivityLog($actor,'CONFIGURE_AI_PROVIDER','Provider '.$id.'; enabled '.$enabled.'; version '.$version,'superadmin','AI_PROVIDER',(string)$id)){throw new \RuntimeException('Unable to record provider change.');}$this->conn->commit();return $id;
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }
}

<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/CiaCalculator.php';

final class CiaPolicySettings
{
    public function __construct(private \mysqli $db,private array $actor) {}
    private function authorize(): void
    {
        if(!in_array($this->actor['role']??'', ['admin','superadmin','academic_section'],true)){throw new \DomainException('Academic policy administration access required.');}
    }
    public function read(int $regulation,string $type): array
    {
        $this->authorize();$type=strtolower(trim($type));
        $stmt=$this->db->prepare('SELECT r.*,p.program_level,p.prog_shortname FROM regulations r JOIN programs p ON p.id=r.prog_id WHERE r.id=?');$stmt->bind_param('i',$regulation);$stmt->execute();$reg=$stmt->get_result()->fetch_assoc();
        if(!$reg){throw new \DomainException('Regulation not found.');}
        $stmt=$this->db->prepare('SELECT id,setting_key,setting_value FROM academic_settings WHERE reg_id=? ORDER BY id');$stmt->bind_param('i',$regulation);$stmt->execute();$raw=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$settings=[];
        foreach($raw as $r){if(isset($settings[$r['setting_key']])){throw new \DomainException('Resolve duplicate regulation settings.');}$settings[$r['setting_key']]=$r['setting_value'];}
        $all=json_decode($settings['cia_scheme_json']??'{}',true,512,JSON_THROW_ON_ERROR);
        if(!is_array($all)){throw new \DomainException('Invalid existing CIA rules.');}
        return ['regulation'=>$reg,'settings'=>$settings,'schemes'=>$all,'scheme'=>$all[$type]??null,'type'=>$type,'version'=>hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR))];
    }
    public function save(int $regulation,string $type,array $scheme,string $version,string $reason): void
    {
        $this->authorize();require_once dirname(__DIR__).'/services/FeatureManager.php';
        if(!\FeatureManager::canRoleWrite('MOD_CIA_MARKS',$this->actor['role'])){throw new \DomainException('CIA policy editing is disabled for your account.');}
        if(trim($reason)===''){throw new \DomainException('Record the authority or reason for this rule.');}
        if(array_diff(array_keys($scheme),['kind','required_assessments','better_weight','lesser_weight','rounding','component_maxima'])){throw new \DomainException('Unknown CIA rule field.');}
        $this->db->begin_transaction();
        try{
            $stmt=$this->db->prepare('SELECT id FROM regulations WHERE id=? FOR UPDATE');$stmt->bind_param('i',$regulation);$stmt->execute();$stmt->store_result();
            $current=$this->read($regulation,$type);$type=$current['type'];
            if(!hash_equals($current['version'],$version)){throw new \DomainException('The regulation settings changed. Reload before saving.');}
            $stmt=$this->db->prepare('SELECT DISTINCT s.sub_type FROM subjects s JOIN classes c ON c.id=s.class_id WHERE c.reg_id=? UNION SELECT DISTINCT sub_type FROM curriculum_subjects WHERE reg_id=?');$stmt->bind_param('ii',$regulation,$regulation);$stmt->execute();$types=array_map(static fn($r)=>strtolower(trim($r[0])),$stmt->get_result()->fetch_all(MYSQLI_NUM));
            if(!in_array($type,$types,true)){throw new \DomainException('Select a subject type belonging to this regulation.');}
            $before=$current['schemes'];$after=$before;$after[$type]=$scheme;$json=json_encode((object)$after,JSON_THROW_ON_ERROR);
            $settings=$current['settings'];$settings['cia_scheme_json']=$json;
            // Validate rule structure even if a course is still waiting for its curriculum maximum.
            CiaCalculator::policy(['sub_type'=>$type,'program_level'=>$current['regulation']['program_level'],'cie_max_marks'=>999.99],$settings);
            $stmt=$this->db->prepare('SELECT DISTINCT cs.cie_max_marks FROM curriculum_subjects cs WHERE cs.reg_id=? AND LOWER(TRIM(cs.sub_type))=? AND cs.cie_max_marks>0');$stmt->bind_param('is',$regulation,$type);$stmt->execute();
            foreach($stmt->get_result() as $course){
                $policy=CiaCalculator::policy(['sub_type'=>$type,'program_level'=>$current['regulation']['program_level'],'cie_max_marks'=>$course['cie_max_marks']],$settings);
                $max=$policy['component_maxima'];
                if(count($max)===count($policy['required'])){$evidence=[];foreach($policy['required'] as $n){$row=['assessment_number'=>$n];foreach($policy['fields'] as $index=>$field){$row[$field]=$index===0?$max[$n]:0;}$evidence[]=$row;}
                    $policy['field_maxima']=[]; // Assess the aggregate component caps, not one synthetic field.
                    if(CiaCalculator::calculate($policy,$evidence)['status']!=='COMPLETE'){throw new \DomainException('Configured component maxima exceed the course CIA maximum.');}}
            }
            $actor=(int)($this->actor['userid']??0);if($actor<1){throw new \DomainException('An identified policy editor is required.');}$code=$current['regulation']['regulation'];
            $stmt=$this->db->prepare("SELECT id FROM academic_settings WHERE reg_id=? AND setting_key='cia_scheme_json'");$stmt->bind_param('i',$regulation);$stmt->execute();$existing=$stmt->get_result()->fetch_row();
            if($existing){$stmt=$this->db->prepare('UPDATE academic_settings SET setting_value=?,updated_by=? WHERE id=?');$stmt->bind_param('sii',$json,$actor,$existing[0]);}
            else{$stmt=$this->db->prepare("INSERT INTO academic_settings(reg_id,regulation_code,category,setting_key,setting_value,data_type,description,is_editable,updated_by) VALUES(?,?,'CIA','cia_scheme_json',?,'JSON','Approved CIA schemes by subject type',0,?)");$stmt->bind_param('issi',$regulation,$code,$json,$actor);}$stmt->execute();
            $stmt=$this->db->prepare('SELECT DISTINCT dept_id FROM specialization WHERE prog_id=? ORDER BY dept_id');$stmt->bind_param('i',$current['regulation']['prog_id']);$stmt->execute();$departments=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if(!$departments){throw new \DomainException('A department is required to record policy history.');}
            $entity=$regulation.':'.substr(hash('sha256',$type),0,24);$b=json_encode(['type'=>$type,'scheme'=>$before[$type]??null],JSON_THROW_ON_ERROR);$a=json_encode(['type'=>$type,'scheme'=>$scheme],JSON_THROW_ON_ERROR);
            foreach($departments as $d){$dept=(int)$d['dept_id'];$stmt=$this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES('CIA_POLICY',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$b,$a);$stmt->execute();}
            $this->db->commit();
        }catch(\Throwable $e){$this->db->rollback();throw $e;}
    }
}

<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/MarksNormalizer.php';

final class AssessmentPolicy
{
    public function __construct(private \mysqli $db) {}

    public function forSubject(int $subject): array
    {
        // A missing/obsolete curriculum link is optional; the actual offering remains authoritative.
        $stmt=$this->db->prepare('SELECT s.id,s.subcode,s.sub_type,c.reg_id,cs.cie_max_marks,cs.see_max_marks,cs.credits FROM subjects s JOIN classes c ON c.id=s.class_id LEFT JOIN curriculum_subjects cs ON cs.id=s.curr_sub_id AND cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$context=$stmt->get_result()->fetch_assoc();
        if(!$context){throw new \DomainException('Subject offering not found.');}
        $stmt=$this->db->prepare('SELECT config_json,version FROM attainment_policies WHERE regulation_id=? AND subject_type IN (?,\'all\') ORDER BY subject_type=\'all\' DESC');$stmt->bind_param('is',$context['reg_id'],$context['sub_type']);$stmt->execute();$profiles=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $config=['net_max'=>$context['see_max_marks']!==null?(float)$context['see_max_marks']:null,'internal_max'=>$context['cie_max_marks']!==null?(float)$context['cie_max_marks']:null,'grade_bands'=>[],'grade_strategy'=>'NONE','choice_groups'=>null];$versions=[];
        foreach($profiles as $row){$config=array_replace($config,json_decode($row['config_json'],true,512,JSON_THROW_ON_ERROR));$versions[]=(int)$row['version'];}
        $stmt=$this->db->prepare('SELECT config_json,version FROM subject_attainment_overrides WHERE subject_id=?');$stmt->bind_param('i',$subject);$stmt->execute();$override=$stmt->get_result()->fetch_assoc();
        if($override){$config=array_replace($config,json_decode($override['config_json'],true,512,JSON_THROW_ON_ERROR));$versions[]=(int)$override['version'];}
        self::validate($config);
        $stmt=$this->db->prepare('SELECT setting_key,setting_value,data_type FROM academic_settings WHERE reg_id=? ORDER BY setting_key');$stmt->bind_param('i',$context['reg_id']);$stmt->execute();$settings=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return ['context'=>$context,'config'=>$config,'versions'=>$versions,'hash'=>hash('sha256',json_encode([$context,$config,$versions,$settings],JSON_THROW_ON_ERROR))];
    }

    public function lockSubject(int $subject): void
    {
        $stmt=$this->db->prepare('SELECT c.reg_id FROM subjects s JOIN classes c ON c.id=s.class_id WHERE s.id=?');$stmt->bind_param('i',$subject);$stmt->execute();$row=$stmt->get_result()->fetch_row();if(!$row){throw new \DomainException('Subject offering not found.');}$reg=(int)$row[0];
        $stmt=$this->db->prepare('SELECT id FROM regulations WHERE id=? FOR UPDATE');$stmt->bind_param('i',$reg);$stmt->execute();$stmt->store_result();
        $stmt=$this->db->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$stmt->bind_param('i',$subject);$stmt->execute();$stmt->store_result();
    }

    public static function validate(array $config): void
    {
        $allowed=['net_max','gross_max','internal_max','grade_bands','grade_strategy','choice_groups','co_target_percentage','attainment_level_1_threshold','attainment_level_2_threshold','attainment_level_3_threshold'];
        if(array_diff(array_keys($config),$allowed)){throw new \InvalidArgumentException('Unknown assessment profile field.');}
        foreach(['net_max','gross_max','internal_max'] as $key){if(isset($config[$key])&&MarksNormalizer::number($config[$key],$key)>9999999.99999){throw new \InvalidArgumentException('Assessment maximum exceeds supported precision.');}if(isset($config[$key])&&MarksNormalizer::number($config[$key],$key)<(in_array($key,['internal_max','net_max'],true)?0:0.000001)){throw new \InvalidArgumentException('Assessment maxima must be positive.');}}
        foreach(['co_target_percentage','attainment_level_1_threshold','attainment_level_2_threshold','attainment_level_3_threshold'] as $key){if(isset($config[$key])&&MarksNormalizer::number($config[$key],$key)>100){throw new \InvalidArgumentException('Thresholds must be from 0 to 100.');}}
        if(!in_array($config['grade_strategy']??'NONE',['NONE','LOWER_BOUND','MIDPOINT','UPPER_BOUND'],true)){throw new \InvalidArgumentException('Invalid grade estimation policy.');}
        foreach(['grade_bands','choice_groups'] as $key){if(array_key_exists($key,$config)&&!($key==='choice_groups'&&$config[$key]===null)&&(!is_array($config[$key])||!array_is_list($config[$key]))){throw new \InvalidArgumentException($key.' must be a list.');}}
        $seen=[];$ranges=[];
        foreach($config['grade_bands']??[] as $band){if(!is_array($band)||array_diff(['grade','min_percent','max_percent','points'],array_keys($band))){throw new \InvalidArgumentException('Each grade needs a name, percentage range and points.');}$name=strtoupper(trim((string)($band['grade']??'')));if($name===''||isset($seen[$name])){throw new \InvalidArgumentException('Grade names must be unique.');}$seen[$name]=true;$g=MarksNormalizer::grade($name,[$band]);$ranges[]=$g['range'];}
        usort($ranges,static fn($a,$b)=>$a[0]<=>$b[0]);for($i=1;$i<count($ranges);$i++){if($ranges[$i][0]<$ranges[$i-1][1]){throw new \InvalidArgumentException('Grade bands must not overlap.');}}
        foreach($config['choice_groups']??[] as $group){if(!is_array($group)){throw new \InvalidArgumentException('Choice groups must be objects.');}if(!is_array($group['options']??null)||($group['select']??1)<1||($group['select']??1)>count($group['options'])||(int)($group['select']??1)!=($group['select']??1)){throw new \InvalidArgumentException('Invalid choice group.');}foreach($group['options'] as $option){if(!is_array($option)||!$option){throw new \InvalidArgumentException('Choice options must list question labels.');}foreach($option as $label){if(!is_string($label)||trim($label)===''){throw new \InvalidArgumentException('Choice labels must be nonempty strings.');}}}}
        $previous=-1;foreach(['attainment_level_1_threshold','attainment_level_2_threshold','attainment_level_3_threshold'] as $key){if(isset($config[$key])){if((float)$config[$key]<$previous){throw new \InvalidArgumentException('Attainment thresholds must be ordered.');}$previous=(float)$config[$key];}}
    }

    public function questions(int $subject,int $component): array
    {
        $stmt=$this->db->prepare('SELECT ac.id FROM assessment_components ac JOIN internal_assessments ia ON ia.id=ac.assessment_id WHERE ac.id=? AND ia.sub_id=?');$stmt->bind_param('ii',$component,$subject);$stmt->execute();if(!$stmt->get_result()->fetch_row()){throw new \DomainException('The assessment component does not belong to this subject.');}
        $stmt=$this->db->prepare('SELECT aq.*,qcm.co_id,co.sub_id co_subject FROM assessment_questions aq LEFT JOIN question_co_mapping qcm ON qcm.question_id=aq.id LEFT JOIN course_outcomes co ON co.id=qcm.co_id WHERE aq.component_id=? ORDER BY aq.id,qcm.co_id');$stmt->bind_param('i',$component);$stmt->execute();$questions=[];
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row){$id=(int)$row['id'];$questions[$id]??=$row+['cos'=>[]];if($row['co_id']!==null){if((int)$row['co_subject']!==$subject){throw new \DomainException('A question maps to another subject’s CO.');}$questions[$id]['cos'][]=(int)$row['co_id'];}}
        return array_values($questions);
    }

    /** Preserve existing paper presets when a course has not selected custom choice rules. */
    public static function legacyGroups(array $questions,array $template): array
    {
        $groups=[];foreach($template['choice_groups']??[] as $group){$options=[];foreach($group['pair'] as $parent){$labels=[];foreach($questions as $q){if(preg_match('/^'.preg_quote((string)$parent,'/').'[a-z]*$/i',$q['question_label'])){$labels[]=$q['question_label'];}}if($labels){$options[]=$labels;}}if(count($options)>1){$groups[]=['options'=>$options,'select'=>1,'max_marks'=>(float)$group['marks']];}}
        return $groups;
    }
}

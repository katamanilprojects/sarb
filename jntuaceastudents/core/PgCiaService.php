<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/RecordScope.php';
require_once __DIR__ . '/CiaCalculator.php';

/** Separate PG evidence identities: mids 1/2, assignment 3, continuous 4, lab 11, record 12. */
final class PgCiaService
{
    public function __construct(private \mysqli $db, private array $actor) {}

    public static function label(int $number): string
    {
        return match ($number) {1,2=>'Mid assessment '.$number,3=>'Assignment',4=>'Continuous evaluation',11=>'Lab internal assessment',12=>'Lab record',default=>'Assessment '.$number};
    }

    public function components(int $subject): array
    {
        (new RecordScope($this->db,$this->actor))->subject($subject);
        $stmt=$this->db->prepare('SELECT s.*,c.reg_id,p.program_level,sp.dept_id,cs.cie_max_marks FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN programs p ON p.id=sp.prog_id LEFT JOIN curriculum_subjects cs ON cs.id=s.curr_sub_id AND cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem WHERE s.id=?');
        $stmt->bind_param('i',$subject);$stmt->execute();$course=$stmt->get_result()->fetch_assoc();
        if(!$course || $course['program_level']!=='PG'){throw new \DomainException('Select a postgraduate subject.');}
        $stmt=$this->db->prepare('SELECT setting_key,setting_value FROM academic_settings WHERE reg_id=?');$stmt->bind_param('i',$course['reg_id']);$stmt->execute();$settings=[];
        foreach($stmt->get_result() as $r){if(array_key_exists($r['setting_key'],$settings)){throw new \DomainException('Resolve duplicate regulation settings.');}$settings[$r['setting_key']]=$r['setting_value'];}
        ksort($settings);
        $custom=json_decode($settings['cia_scheme_json']??'{}',true,512,JSON_THROW_ON_ERROR)[strtolower(trim($course['sub_type']))]??null;
        if($custom!==null){$policy=CiaCalculator::policy($course,$settings);$numbers=$policy['required'];$maxima=$policy['component_maxima'];}
        else{
            // Preserve existing entry components while withholding unconfigured PG totals.
            $type=strtolower(trim($course['sub_type']));
            if($type==='lab'){
                $numbers=[11];$maxima=[11=>isset($settings['lab_cia_day_to_day_marks'],$settings['lab_cia_internal_test_marks'])?(float)$settings['lab_cia_day_to_day_marks']+(float)$settings['lab_cia_internal_test_marks']:40];
                if((float)($settings['lab_cia_record_marks']??0)>0){$numbers[]=12;$maxima[12]=$settings['lab_cia_record_marks'];}
            }elseif(in_array($type,['theory','pe','oe'],true)){
                $numbers=[1,2,3];$maxima=[1=>$settings['theory_mid_exams_marks']??30,2=>$settings['theory_mid_exams_marks']??30,3=>10];
                if((float)($settings['theory_continuous_eval_marks']??0)>0){$numbers[]=4;$maxima[4]=$settings['theory_continuous_eval_marks'];}
            }else{throw new \DomainException('Configure the PG CIA scheme for this subject type before entering marks.');}
        }
        $components=[];
        foreach($numbers as $number){$max=MarksNormalizer::number($maxima[$number], 'PG component maximum');if($max<=0||$max>99.99){throw new \DomainException('PG component maxima must fit the marks field (0.01 to 99.99).');}$components[$number]=['number'=>$number,'label'=>self::label($number),'maximum'=>$max];}
        return ['course'=>$course,'components'=>$components,'configured'=>$custom!==null,'policy_version'=>hash('sha256',json_encode($settings,JSON_THROW_ON_ERROR))];
    }

    public function grid(int $subject,int $number): array
    {
        $report=$this->components($subject);
        if(!isset($report['components'][$number])){throw new \DomainException('This assessment is not enabled for this PG subject.');}
        $stmt=$this->db->prepare('SELECT st.id,st.username,u.name FROM student_sub ss JOIN students st ON st.id=ss.stu_id JOIN subjects s ON s.id=ss.sub_id JOIN users u ON u.username=st.username WHERE ss.sub_id=? AND st.class_id=s.class_id AND st.status=1 ORDER BY st.username,st.id');$stmt->bind_param('i',$subject);$stmt->execute();$students=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $rows=$this->rows($subject,$number);$final=[];$draft=[];
        foreach($rows['final'] as $r){if(isset($final[$r['student_id']])){throw new \DomainException('Resolve duplicate PG marks before editing.');}$final[$r['student_id']]=$r['marks'];}
        foreach($rows['draft'] as $r){$draft[$r['student_id']]=$r['marks'];}
        return $report+['students'=>$students,'final'=>$final,'draft'=>$draft,'version'=>hash('sha256',json_encode([$rows,$report],JSON_THROW_ON_ERROR))];
    }

    private function rows(int $subject,int $number): array
    {
        $rows=[];
        foreach(['final'=>'pg_internal_assessment_marks','draft'=>'temp_pg_internal_assessment_marks'] as $kind=>$table){$stmt=$this->db->prepare('SELECT id,student_id,marks FROM '.$table.' WHERE subject_id=? AND assessment_number=? ORDER BY student_id,id');$stmt->bind_param('ii',$subject,$number);$stmt->execute();$rows[$kind]=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);}
        return $rows;
    }

    public function save(int $subject,int $number,array $ids,array $marks,string $action,string $version,string $reason=''): void
    {
        require_once dirname(__DIR__).'/services/FeatureManager.php';
        if(!in_array($this->actor['role']??'', ['faculty','hod','admin','academic_section','superadmin'],true)||!\FeatureManager::canRoleWrite('MOD_CIA_MARKS',$this->actor['role'])){throw new \DomainException('PG marks editing is disabled for your account.');}
        if(!in_array($action,['save','submit','edit'],true)){throw new \DomainException('Invalid PG marks action.');}
        $this->db->begin_transaction();
        try{
            // Use the same regulation-then-subject lock order as policy and SEE updates.
            require_once __DIR__.'/AssessmentPolicy.php';
            (new AssessmentPolicy($this->db))->lockSubject($subject);
            $grid=$this->grid($subject,$number);
            if(!hash_equals($grid['version'],$version)){throw new \DomainException('Marks changed since you opened this form. Reload before saving.');}
            if(count($ids)!==count($marks)||count(array_unique($ids))!==count($ids)){throw new \DomainException('Submit one marks value per enrolled student.');}
            $expected=array_map('intval',array_column($grid['students'],'id'));$actual=[];
            foreach($ids as $id){if(filter_var($id,FILTER_VALIDATE_INT)===false || (int)$id<1){throw new \DomainException('Invalid student identifier.');}$actual[]=(int)$id;}
            sort($expected);$sorted=$actual;sort($sorted);
            if(!$expected||$expected!==$sorted){throw new \DomainException('The student list changed or contains a student from another subject. Reload the form.');}
            if($action==='submit'&&$grid['final']){throw new \DomainException('Marks already exist. Use the correction form.');}
            if($action==='edit'&&trim($reason)===''){throw new \DomainException('Record a reason for the marks correction.');}
            $values=[];
            foreach($marks as $index=>$value){
                if($action==='save'&&($value===null||trim((string)$value)==='')){$values[$actual[$index]]=null;continue;}
                $n=MarksNormalizer::number($value,'PG marks');
                if($n>$grid['components'][$number]['maximum']+0.000001||abs(round($n,2)-$n)>0.000001){throw new \DomainException('Marks must be within the component maximum, with at most two decimal places.');}
                $values[$actual[$index]]=round($n,2);
            }
            $before=$this->rows($subject,$number);
            if($action==='save'){
                if($grid['final']){throw new \DomainException('Finalized marks cannot be replaced by a draft.');}
                $stmt=$this->db->prepare('DELETE FROM temp_pg_internal_assessment_marks WHERE subject_id=? AND assessment_number=?');$stmt->bind_param('ii',$subject,$number);$stmt->execute();
                $stmt=$this->db->prepare('INSERT INTO temp_pg_internal_assessment_marks(student_id,subject_id,assessment_number,marks) VALUES(?,?,?,?)');
                foreach($values as $id=>$value){$stmt->bind_param('iiis',$id,$subject,$number,$value);$stmt->execute();}
            }else{
                $stmt=$this->db->prepare('DELETE FROM pg_internal_assessment_marks WHERE subject_id=? AND assessment_number=?');$stmt->bind_param('ii',$subject,$number);$stmt->execute();
                // Keep historical evidence for students no longer in the editable cohort.
                $all=$grid['final'];foreach($values as $id=>$value){$all[$id]=$value;}
                $stmt=$this->db->prepare('INSERT INTO pg_internal_assessment_marks(student_id,subject_id,assessment_number,marks) VALUES(?,?,?,?)');
                foreach($all as $id=>$value){$stmt->bind_param('iiid',$id,$subject,$number,$value);$stmt->execute();}
                $stmt=$this->db->prepare('DELETE FROM temp_pg_internal_assessment_marks WHERE subject_id=? AND assessment_number=?');$stmt->bind_param('ii',$subject,$number);$stmt->execute();
            }
            $after=$this->rows($subject,$number);$entity=$subject.':'.$number;$dept=(int)$grid['course']['dept_id'];$actor=(int)($this->actor['userid']??0);
            if($actor<1){throw new \DomainException('An identified actor is required.');}
            $reason=$action==='edit'?trim($reason):'PG CIA '.$action;$b=json_encode($before,JSON_THROW_ON_ERROR);$a=json_encode($after,JSON_THROW_ON_ERROR);
            $stmt=$this->db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES('PG_CIA',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$b,$a);$stmt->execute();
            $this->db->commit();
        }catch(\Throwable $e){$this->db->rollback();throw $e;}
    }
}

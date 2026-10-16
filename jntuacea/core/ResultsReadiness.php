<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/CiaCalculator.php';
require_once __DIR__.'/ResultsPolicy.php';
require_once __DIR__.'/Clock.php';
require_once __DIR__.'/VerifiedRegulations.php';

final class ResultsReadiness
{
    public function __construct(private \mysqli $db,private array $actor) {}

    public function report(?string $asOf=null): array
    {
        if(!in_array($this->actor['role']??'', ['academic_section','admin','superadmin'],true)){throw new \DomainException('Academic administration access required.');}
        Clock::configure();$asOf??=date('Y-m-d');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$asOf);
        if(!$date||$date->format('Y-m-d')!==$asOf){throw new \DomainException('Invalid readiness date.');}
        $sql='SELECT s.*,c.reg_id,c.classname,c.start_date,c.end_date,r.regulation,p.prog_shortname,p.program_level,cs.id valid_curriculum,cs.credits,cs.cie_max_marks FROM subjects s JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN programs p ON p.id=sp.prog_id JOIN regulations r ON r.id=c.reg_id LEFT JOIN curriculum_subjects cs ON cs.id=s.curr_sub_id AND cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem WHERE c.status=1 AND (c.start_date IS NULL OR c.start_date<=?) AND (c.end_date IS NULL OR c.end_date>=?) ORDER BY p.prog_shortname,r.regulation,s.sub_type,s.id';
        $stmt=$this->db->prepare($sql);$stmt->bind_param('ss',$asOf,$asOf);$stmt->execute();$courses=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt=$this->db->query('SELECT reg_id,setting_key,setting_value FROM academic_settings WHERE reg_id IS NOT NULL ORDER BY id');$settings=[];$duplicates=[];
        foreach($stmt as $r){$reg=(int)$r['reg_id'];if(isset($settings[$reg][$r['setting_key']])){$duplicates[$reg]=true;}$settings[$reg][$r['setting_key']]=$r['setting_value'];}
        $report=['date'=>$asOf,'total'=>count($courses),'cia_ready'=>0,'results_ready'=>0,'groups'=>[],'courses'=>[]];
        foreach($courses as $course){
            $issues=[];$ciaReady=false;$resultsReady=false;$reg=(int)$course['reg_id'];
            if(!$course['valid_curriculum']){$issues[]='Link this offering to its regulation, specialization and semester curriculum.';}
            if(!$course['start_date']||!$course['end_date']){$issues[]='Set class start and end dates.';}
            try{if(isset($duplicates[$reg])){throw new \DomainException('Resolve duplicate regulation settings.');}CiaCalculator::policy($course,$settings[$reg]??[]);$ciaReady=true;}catch(\Throwable $e){$issues[]='CIA: '.$e->getMessage();}
            try{
                $profile=(new ResultsPolicy($this->db))->forSubject((int)$course['id']);
                if(!$profile['config']['grade_bands']){throw new \DomainException('Configure the regulation grade bands.');}
                if($profile['config']['internal_max']===null||$profile['config']['net_max']===null){throw new \DomainException('Configure the course internal and external maxima.');}
                if($course['credits']===null){throw new \DomainException('Configure curriculum credits; zero-credit courses are allowed.');}
                $verified=VerifiedRegulations::definition($course);
                if($verified && in_array(strtolower(trim($course['sub_type'])),['theory','pe','oe','lab'],true)){
                    if(abs((float)$profile['config']['internal_max']-$verified['internal_max'])>0.000001||abs((float)$profile['config']['net_max']-$verified['external_max'])>0.000001){throw new \DomainException('Course maxima disagree with the verified regulation: expected CIA '.$verified['internal_max'].' / SEE '.$verified['external_max'].'.');}
                    $actual=[];foreach($profile['config']['grade_bands'] as $band){$actual[strtoupper($band['grade'])]=(float)$band['points'];}
                    foreach($verified['grade_bands'] as $band){if(!isset($actual[$band['grade']])||abs($actual[$band['grade']]-(float)$band['points'])>0.000001){throw new \DomainException('Grade points disagree with the verified regulation table.');}}
                }
                $resultsReady=true;
            }catch(\Throwable $e){$issues[]='Results: '.$e->getMessage();}
            $report['cia_ready']+=(int)$ciaReady;$report['results_ready']+=(int)$resultsReady;
            $key=$reg.':'.$course['sub_type'];$report['groups'][$key]??=['reg_id'=>$reg,'regulation'=>$course['regulation'],'program'=>$course['prog_shortname'],'level'=>$course['program_level'],'type'=>$course['sub_type'],'total'=>0,'cia_ready'=>0,'results_ready'=>0,'issues'=>[]];
            $group=&$report['groups'][$key];$group['total']++;$group['cia_ready']+=(int)$ciaReady;$group['results_ready']+=(int)$resultsReady;
            foreach($issues as $issue){$group['issues'][$issue]=($group['issues'][$issue]??0)+1;}unset($group);
            $report['courses'][]=$course+['cia_ready'=>$ciaReady,'results_ready'=>$resultsReady,'issues'=>$issues];
        }
        $report['groups']=array_values($report['groups']);return $report;
    }
}

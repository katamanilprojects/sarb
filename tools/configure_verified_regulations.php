<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require $root.'/.env.php';require $root.'/jntuacea/core/VerifiedRegulations.php';require $root.'/jntuacea/core/CiaCalculator.php';require $root.'/jntuacea/core/AssessmentPolicy.php';
$options=getopt('',['database:','apply','actor:','reason:']);$name=$options['database']??DB_NAME;
if($name!==DB_NAME&&!preg_match('/^cea_results_test_[a-f0-9]{12}$/D',$name)){throw new RuntimeException('Use the configured database or an isolated results-test database.');}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$name);$db->set_charset('utf8mb4');$apply=isset($options['apply']);
if($apply && !$db->query("SHOW INDEX FROM academic_settings WHERE Key_name='uk_regulation_scope_setting'")->num_rows){throw new RuntimeException('Apply database/migrations/20261015_001_regulation_setting_scope.sql before activating verified settings.');}
$actor=(int)($options['actor']??0);$reason=trim($options['reason']??'');
if($apply){$stmt=$db->prepare("SELECT id FROM users WHERE id=? AND status=1 AND role IN('admin','superadmin','academic_section')");$stmt->bind_param('i',$actor);$stmt->execute();if(!$stmt->get_result()->fetch_row()||$reason===''){throw new RuntimeException('Applying requires an active academic administrator --actor and an explicit --reason.');}}
$db->begin_transaction($apply?0:MYSQLI_TRANS_START_READ_ONLY);$changed=0;
try{
    $regulations=$db->query('SELECT r.*,p.prog_shortname,p.program_level FROM regulations r JOIN programs p ON p.id=r.prog_id ORDER BY r.id'.($apply?' FOR UPDATE':''))->fetch_all(MYSQLI_ASSOC);
    foreach($regulations as $reg){
        $definition=College\VerifiedRegulations::definition($reg);if(!$definition){continue;}$id=(int)$reg['id'];$source=$root.'/docs/regulations/'.$definition['source'];if(!is_file($source)||!hash_equals($definition['source_sha256'],hash_file('sha256',$source))){throw new RuntimeException('Regulation source changed or is missing; review the formulas again: '.$definition['source']);}
        $stmt=$db->prepare('SELECT * FROM academic_settings WHERE reg_id=? ORDER BY id');$stmt->bind_param('i',$id);$stmt->execute();$beforeSettings=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$settings=[];$existing=[];
        foreach($beforeSettings as $r){if(isset($existing[$r['setting_key']])){throw new RuntimeException('Resolve duplicate settings in regulation '.$id);}$existing[$r['setting_key']]=$r;$settings[$r['setting_key']]=$r['setting_value'];}
        $schemes=json_decode($settings['cia_scheme_json']??'{}',true,512,JSON_THROW_ON_ERROR);if(!is_array($schemes)){throw new RuntimeException('Invalid existing CIA schemes.');}$schemes=array_replace($schemes,$definition['schemes']);
        $updates=$definition['settings'];$updates['cia_scheme_json']=json_encode((object)$schemes,JSON_THROW_ON_ERROR);$updates['grade_bands_json']=json_encode($definition['grade_bands'],JSON_THROW_ON_ERROR);
        $newSettings=array_replace($settings,$updates);
        foreach($definition['schemes'] as $type=>$scheme){College\CiaCalculator::policy(['sub_type'=>$type,'program_level'=>$reg['program_level'],'cie_max_marks'=>$definition['internal_max']],$newSettings);}
        College\AssessmentPolicy::validate(['grade_bands'=>$definition['grade_bands']]);
        // Repair only the confirmed PG 30/70 legacy default; projects and audit courses are excluded.
        $stmt=$db->prepare("SELECT id,spec_id,sub_type,cie_max_marks,see_max_marks FROM curriculum_subjects WHERE reg_id=? AND LOWER(TRIM(sub_type)) IN('theory','pe','oe','lab') ORDER BY id");$stmt->bind_param('i',$id);$stmt->execute();$curriculum=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$repair=[];
        if($reg['program_level']==='PG'){foreach($curriculum as $course){$cia=(float)$course['cie_max_marks'];$see=(float)$course['see_max_marks'];if($cia===30.0&&$see===70.0){$repair[]=$course;}elseif($cia!==40.0||$see!==60.0){throw new RuntimeException('Review nonstandard PG course maxima for curriculum '.$course['id'].' before applying.');}}}
        $changes=[];foreach($updates as $key=>$value){if(!isset($settings[$key])||(string)$settings[$key]!== (string)$value){$changes[]=$key;}}
        echo $reg['prog_shortname'],' / ',$reg['regulation'],': ',count($changes),' setting changes; ',count($repair),' curriculum maxima repairs. Source: ',$definition['source'],' § ',$definition['section'],".\n";
        if(!$apply){foreach(array_slice($repair,0,5) as $course){echo '  Curriculum ',$course['id'],": CIA 30 → 40, SEE 70 → 60\n";}continue;}
        if(!$changes&&!$repair){continue;}
        foreach($changes as $key){$value=(string)$updates[$key];$json=str_ends_with($key,'_json');$dataType=$json?'JSON':'FLOAT';$category=$key==='grade_bands_json'?'GRADING':'CIA';$editable=$key==='cia_scheme_json'?0:1;$description='Verified: '.$definition['source'].' '.$definition['section'];
            if(isset($existing[$key])){$settingId=(int)$existing[$key]['id'];$stmt=$db->prepare('UPDATE academic_settings SET setting_value=?,data_type=?,category=?,is_editable=?,description=?,updated_by=? WHERE id=?');$stmt->bind_param('sssisii',$value,$dataType,$category,$editable,$description,$actor,$settingId);}
            else{$code=$reg['regulation'];$stmt=$db->prepare('INSERT INTO academic_settings(reg_id,regulation_code,setting_key,setting_value,data_type,category,is_editable,description,updated_by) VALUES(?,?,?,?,?,?,?,?,?)');$stmt->bind_param('isssssisi',$id,$code,$key,$value,$dataType,$category,$editable,$description,$actor);}$stmt->execute();
        }
        foreach($repair as $course){$courseId=(int)$course['id'];$stmt=$db->prepare('UPDATE curriculum_subjects SET cie_max_marks=40,see_max_marks=60 WHERE id=? AND reg_id=?');$stmt->bind_param('ii',$courseId,$id);$stmt->execute();}
        $afterCurriculum=$curriculum;foreach($afterCurriculum as &$course){foreach($repair as $r){if($course['id']===$r['id']){$course['cie_max_marks']='40.00';$course['see_max_marks']='60.00';}}}unset($course);
        $before=json_encode(['settings'=>$beforeSettings,'curriculum'=>$curriculum],JSON_THROW_ON_ERROR);$after=json_encode(['settings'=>$updates,'curriculum'=>$afterCurriculum,'source'=>$definition['source'],'sha256'=>hash_file('sha256',$source),'section'=>$definition['section'],'rounding_authority'=>'User: nearest whole mark','btech_equivalence_authority'=>'User: R20 and R26 treated as R23'],JSON_THROW_ON_ERROR);
        $stmt=$db->prepare('SELECT DISTINCT dept_id FROM specialization WHERE prog_id=?');$stmt->bind_param('i',$reg['prog_id']);$stmt->execute();$departments=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);if(!$departments){throw new RuntimeException('Department scope required for regulation history.');}
        $entity=(string)$id;foreach($departments as $department){$dept=(int)$department['dept_id'];$stmt=$db->prepare("INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json) VALUES('REGULATION_CONFIG',?,?,?,?,?,?)");$stmt->bind_param('siisss',$entity,$dept,$actor,$reason,$before,$after);$stmt->execute();}$changed++;
    }
    if($apply){$db->commit();echo 'Applied and audited ',$changed," regulation configurations.\n";}else{$db->rollback();echo "Dry run only: no records changed. Applying requires --apply --actor=<administrator user ID> --reason=<authority>.\n";}
}catch(Throwable $e){$db->rollback();throw $e;}

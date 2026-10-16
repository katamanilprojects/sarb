<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Reuse schema-only fixtures and the baseline publication/security suite.
require __DIR__.'/results_portal_regression.php';
$checks=0;
require_once $root.'/jntuacea/core/CiaService.php';
require_once $root.'/jntuacea/core/PgCiaService.php';
require_once $root.'/jntuacea/core/CiaPolicySettings.php';
require_once $root.'/jntuacea/core/ResultsReadiness.php';
$_SESSION=['userid'=>1,'role'=>'academic_section','user'=>'AUDIT-OFFICE'];
seed('system_feature_modules',['module_key'=>'MOD_CIA_MARKS','module_name'=>'CIA','is_enabled_globally'=>1,'faculty_visibility'=>'VISIBLE','hod_visibility'=>'VISIBLE','student_visibility'=>'VISIBLE']);
FeatureManager::getInstance()->refreshCache();
foreach(['theory_mid_better_weight'=>'.8','theory_mid_lesser_weight'=>'.2','theory_cia_total_marks'=>'30','theory_mid_subjective_condensed'=>'15','theory_mid_objective_marks'=>'10','theory_assignment_marks'=>'5'] as $key=>$value){seed('academic_settings',['reg_id'=>1,'regulation_code'=>'T25','category'=>'CIA','setting_key'=>$key,'setting_value'=>$value,'data_type'=>'FLOAT']);}
foreach([[1,15,5,5],[2,10,3,2]] as [$n,$s,$o,$a]){seed('internal_assessment_marks',['student_id'=>1,'subject_id'=>1,'assessment_number'=>$n,'subjective_marks'=>$s,'objective_marks'=>$o,'assignment_marks'=>$a]);}
foreach([1,2] as $n){seed('internal_assessment_marks',['student_id'=>2,'subject_id'=>1,'assessment_number'=>$n,'subjective_marks'=>0,'objective_marks'=>0,'assignment_marks'=>0]);}
foreach([[20,'lab',30],[21,'project',40],[22,'Integrated',30],[23,'Audit',0]] as [$sid,$type,$max]){
    seed('curriculum_subjects',['id'=>$sid,'prog_id'=>1,'reg_id'=>1,'spec_id'=>1,'yearsem'=>'I Yr - I Sem','subcode'=>'CIA'.$sid,'sub_type'=>$type,'credits'=>0,'cie_max_marks'=>$max,'see_max_marks'=>70]);
    seed('subjects',['id'=>$sid,'class_id'=>1,'curr_sub_id'=>$sid,'subcode'=>'CIA'.$sid,'sub_type'=>$type,'subject_sno'=>(string)$sid]);seed('student_sub',['stu_id'=>1,'sub_id'=>$sid]);
}
seed('uglab_internal_assessment_marks',['student_id'=>1,'subject_id'=>20,'assessment_number'=>1,'day_to_day_marks'=>10,'internal_test_marks'=>5]);
seed('ugproject_internal_assessment_marks',['student_id'=>1,'subject_id'=>21,'assessment_number'=>1,'component1_marks'=>5,'component2_marks'=>10]);
$cia=new College\CiaService($db,$_SESSION);$report=$cia->forClass(1);
verify($report['values'][1][1]['final']===23.0,'UG theory uses exact regulation 80/20 rule: 25*.8+15*.2=23.');
verify($report['values'][1][2]['final']===0.0,'Finalized zero survives calculation.');
verify($report['values'][2][1]['final']===null&&$report['values'][2][1]['status']==='INCOMPLETE','Next subject cannot inherit the previous total.');
verify($report['values'][20][1]['final']===15.0&&$report['values'][21][1]['final']===15.0,'Lab and project use their own evidence.');
verify($report['values'][22][1]['status']==='POLICY_REVIEW','Unknown course type requires an explicit rule.');
verify($report['values'][23][1]['status']==='NOT_APPLICABLE','Zero-CIA course needs no fabricated total.');
verify($report['values'][20][2]['status']==='NOT_ENROLLED','Non-enrolled class member gets a blank.');
$db->query('UPDATE internal_assessment_marks SET subjective_marks=16 WHERE student_id=1 AND assessment_number=1');
verify($cia->forSubject(1)['values'][1][1]['status']==='INVALID_DATA','Invalid field maximum cannot hide inside a valid weighted sum.');
$db->query('UPDATE internal_assessment_marks SET subjective_marks=15 WHERE student_id=1 AND assessment_number=1');
$rows=$cia->forSubject(1)['evidence'][1][1];$rows[]=$rows[0];
verify(College\CiaCalculator::calculate($cia->forSubject(1)['policies'][1],$rows)['status']==='INVALID_DATA','Duplicate finalized evidence is rejected.');
$studentCia=new College\CiaService($db,['userid'=>10,'role'=>'student','user'=>'AUDIT-A']);
verify(count($studentCia->forSubject(1,1)['students'])===1,'Student CIA report contains only their own evidence.');
reject(fn()=>$studentCia->forSubject(1,2),'Another student’s CIA denied.');reject(fn()=>$studentCia->forClass(1),'Student cannot request class CIA.');
$facultyActor=['userid'=>2,'role'=>'faculty','user'=>'AUDIT-FAC','facid'=>1];
reject(fn()=>(new College\CiaService($db,$facultyActor))->forSubject(3),'Unassigned faculty subject denied.');
// PG uses the actual PG table and separate evidence; no inferred 70/30 totals.
seed('programs',['id'=>2,'program_code'=>'P','program_level'=>'PG','prog_shortname'=>'PGTEST','prog_fullname'=>'Synthetic PG']);
seed('specialization',['id'=>2,'dept_id'=>1,'prog_id'=>2]);seed('regulations',['id'=>6,'regulation'=>'T26','prog_id'=>2]);
seed('classes',['id'=>6,'classname'=>'Synthetic PG class','acad_year'=>'2026-27','spec_id'=>2,'reg_id'=>6,'yearsem'=>'I Yr - I Sem','start_date'=>'2026-01-01','end_date'=>'2026-12-31','status'=>1]);
foreach([[60,'theory'],[61,'lab']] as [$sid,$type]){seed('curriculum_subjects',['id'=>$sid,'prog_id'=>2,'reg_id'=>6,'spec_id'=>2,'yearsem'=>'I Yr - I Sem','subcode'=>'PG'.$sid,'sub_type'=>$type,'credits'=>3,'cie_max_marks'=>40,'see_max_marks'=>60]);seed('subjects',['id'=>$sid,'class_id'=>6,'curr_sub_id'=>$sid,'subcode'=>'PG'.$sid,'sub_type'=>$type,'subject_sno'=>(string)$sid]);seed('faculty_sub',['faculty_id'=>1,'sub_id'=>$sid]);}
foreach([[60,'AUDIT-A'],[61,'AUDIT-B']] as [$sid,$roll]){seed('students',['id'=>$sid,'username'=>$roll,'class_id'=>6,'status'=>1]);foreach([60,61] as $sub){seed('student_sub',['stu_id'=>$sid,'sub_id'=>$sub]);}}
foreach(['theory_continuous_eval_marks'=>10,'lab_cia_record_marks'=>10,'lab_cia_day_to_day_marks'=>10,'lab_cia_internal_test_marks'=>20] as $key=>$value){seed('academic_settings',['reg_id'=>6,'regulation_code'=>'T26','category'=>'CIA','setting_key'=>$key,'setting_value'=>$value,'data_type'=>'FLOAT']);}
verify($cia->forClass(6)['values'][60][60]['status']==='POLICY_REVIEW','Unconfigured PG final is blocked.');
$pg=new College\PgCiaService($db,$facultyActor);
verify(isset($pg->components(60)['components'][4])&&isset($pg->components(61)['components'][12]),'Continuous and record fields are exposed independently.');
verify($pg->components(61)['components'][11]['maximum']===30.0,'PG lab internal cap respects separate record component.');
reject(fn()=>$pg->grid(60,12),'Lab record cannot be entered against theory.');
$g=$pg->grid(60,4);$pg->save(60,4,[60,61],[0,''],'save',$g['version']);$g=$pg->grid(60,4);
verify($g['draft'][60]==='0'&&$g['draft'][61]===null,'PG draft distinguishes zero from blank.');
reject(fn()=>$pg->save(60,4,[60,61],[0,''],'submit',$g['version']),'Cannot finalize an incomplete PG cohort.');
reject(fn()=>$pg->save(60,4,[60,61],[-1,2],'submit',$g['version']),'Negative PG marks rejected.');
reject(fn()=>$pg->save(60,4,[60,61],[0,11],'submit',$g['version']),'PG extra field cap enforced.');
reject(fn()=>$pg->save(60,4,[60,61],[0,1.234],'submit',$g['version']),'No hidden precision truncation.');
reject(fn()=>$pg->save(60,4,[60,2],[0,1],'submit',$g['version']),'Student IDs cannot cross subjects.');
reject(fn()=>$pg->save(60,4,[60,60],[0,1],'submit',$g['version']),'Duplicate student IDs rejected.');
$db->query("CREATE TRIGGER cia_failure BEFORE INSERT ON academic_record_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
try{$pg->save(60,4,[60,61],[0,5],'submit',$g['version']);throw new RuntimeException('Audit failure should reject submit.');}catch(mysqli_sql_exception $e){verify(true,'PG audit failure propagated.');}
$db->query('DROP TRIGGER cia_failure');verify($pg->grid(60,4)['final']===[]&&$pg->grid(60,4)['draft'][61]===null,'PG submission and draft cleanup roll back together.');
$pg->save(60,4,[60,61],[0,5],'submit',$g['version']);$g2=$pg->grid(60,4);
verify($g2['final'][60]==='0.00'&&$g2['draft']===[],'Finalized zero retained and drafts cleared.');
reject(fn()=>$pg->save(60,4,[60,61],[1,5],'edit',$g2['version']),'PG correction requires a reason.');
reject(fn()=>$pg->save(60,4,[60,61],[1,5],'edit',$g['version'],'Reason'),'Stale PG form rejected.');
$pg->save(60,4,[60,61],[1,5],'edit',$g2['version'],'Approved correction');
verify($pg->grid(60,4)['final'][60]==='1.00','Audited correction updates intended students.');
$db->query("UPDATE system_feature_modules SET faculty_visibility='READONLY' WHERE module_key='MOD_CIA_MARKS'");FeatureManager::getInstance()->refreshCache();$g=$pg->grid(60,4);
reject(fn()=>$pg->save(60,4,[60,61],[2,5],'edit',$g['version'],'Reason'),'Read-only faculty cannot mutate PG marks.');
$db->query("UPDATE system_feature_modules SET faculty_visibility='VISIBLE' WHERE module_key='MOD_CIA_MARKS'");FeatureManager::getInstance()->refreshCache();
$scheme=['kind'=>'PG_THEORY','required_assessments'=>[1,2,4],'better_weight'=>.8,'lesser_weight'=>.2,'component_maxima'=>[1=>30,2=>30,4=>10],'rounding'=>'NEAREST_INTEGER'];
$policyEditor=new College\CiaPolicySettings($db,$_SESSION);$state=$policyEditor->read(6,'theory');
$bad=$scheme;$bad['required_assessments']=[1,2,3];$bad['component_maxima']=[1=>30,2=>30,3=>10];
reject(fn()=>$policyEditor->save(6,'theory',$bad,$state['version'],'Authority'),'Regulation continuous component cannot be omitted.');
$policyEditor->save(6,'theory',$scheme,$state['version'],'Synthetic approved rule');
verify($policyEditor->read(6,'theory')['scheme']['better_weight']===.8,'PG weights configured explicitly and audited.');
reject(fn()=>$policyEditor->save(6,'theory',$scheme,$state['version'],'Authority'),'Stale policy form rejected.');
$labScheme=['kind'=>'PG_LAB','required_assessments'=>[11,12],'component_maxima'=>[11=>30,12=>10],'rounding'=>'NEAREST_INTEGER'];$state=$policyEditor->read(6,'lab');$policyEditor->save(6,'lab',$labScheme,$state['version'],'Synthetic approved lab rule');
foreach([[60,1,20],[60,2,10],[60,3,9],[61,1,30],[61,2,20]] as [$student,$n,$mark]){seed('pg_internal_assessment_marks',['student_id'=>$student,'subject_id'=>60,'assessment_number'=>$n,'marks'=>$mark]);}
$report=$cia->forClass(6);verify($report['values'][60][60]['final']===19.0&&$report['values'][60][61]['final']===33.0,'PG configured 80/20 + continuous, with assignment excluded only by explicit rule.');
$roundingPolicy=$report['policies'][60];$roundingPolicy['better']=.7;$roundingPolicy['lesser']=.3;
verify(College\CiaCalculator::calculate($roundingPolicy,[['assessment_number'=>1,'marks'=>2.01],['assessment_number'=>2,'marks'=>.29],['assessment_number'=>4,'marks'=>0]])['final']===1.0,'Round only the final CIA: 2.01*.7+.29*.3=1.494 rounds to 1, not 2.');
verify($report['values'][61][60]['status']==='INCOMPLETE','PG record evidence still required.');
foreach([[11,25],[12,8]] as [$n,$mark]){seed('pg_internal_assessment_marks',['student_id'=>60,'subject_id'=>61,'assessment_number'=>$n,'marks'=>$mark]);}
verify($cia->forClass(6)['values'][61][60]['final']===33.0,'Separate PG lab internal and record sum under approved policy.');
$studentReport=(new College\CiaService($db,['userid'=>10,'role'=>'student','user'=>'AUDIT-A']))->forSubject(60,60);
verify($studentReport['values'][60][60]['final']===19.0&&count($studentReport['students'])===1,'Student and staff PG totals agree without exposing another student.');
$readiness=(new College\ResultsReadiness($db,$_SESSION))->report('2026-10-15');verify($readiness['total']>0&&$readiness['cia_ready']>0,'Current-course readiness checks real schema.');
$db->query("UPDATE classes SET end_date='2025-12-31' WHERE id=3");$ids=array_column((new College\ResultsReadiness($db,$_SESSION))->report('2026-10-15')['courses'],'id');verify(!in_array(4,array_map('intval',$ids),true),'Expired class does not inflate current readiness.');
reject(fn()=>(new College\ResultsReadiness($db,$facultyActor))->report(),'Faculty cannot inspect college readiness.');
// Result correction workflow includes removed rows, before/after history, and concurrency protection.
$correction=$new('TEST-CORRECTIONS');$file=tempnam(sys_get_temp_dir(),'cea-correction-');
$csv=static function(array $rows) use($file,$header):void{$h=fopen($file,'w');fputcsv($h,$header);foreach($rows as $r){fputcsv($h,$r);}fclose($h);};
try{
    $csv([$valid,$other]);$p=$service->previewResultsCsv($correction,$file);verify($p['changes']['added']===2&&$p['changes']['removed']===0,'Initial preview identifies added rows.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'])['status']===1,'Initial version imported.');
    $changed=$valid;$changed[3]=65;$changed[4]=85;$csv([$changed]);$p=$service->previewResultsCsv($correction,$file,'MERGE');verify($p['changes']['changed']===1&&$p['changes']['removed']===0,'Incremental file keeps omitted records.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'])['status']===0,'Changed marks require correction reason.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'MERGE','Approved correction')['status']===1,'Reasoned incremental correction committed.');
    verify((int)$db->query('SELECT COUNT(*) FROM exam_results WHERE notification_id='.$correction)->fetch_row()[0]===2,'Incremental correction retains other student.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'MERGE','Stale correction')['status']===0,'Draft changed after preview cannot be imported.');
    $p=$service->previewResultsCsv($correction,$file,'REPLACE');verify($p['changes']['removed']===1&&$p['removed_sample'][0]['htno']==='AUDIT-B','Replacement explicitly previews removed student.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'REPLACE','')['status']===0,'Replacement always requires a reason.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'MERGE','Wrong mode')['status']===0,'Preview import mode is bound to commit.');
    $db->query("CREATE TRIGGER correction_failure BEFORE INSERT ON academic_record_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'REPLACE','Authority')['status']===0,'Correction audit failure rejects whole replacement.');$db->query('DROP TRIGGER correction_failure');
    verify((int)$db->query('SELECT COUNT(*) FROM exam_results WHERE notification_id='.$correction)->fetch_row()[0]===2,'Removal rolled back when audit failed.');
    verify($service->importResultsCsv($correction,$file,1,$p['validation_hash'],'REPLACE','Remove erroneous record')['status']===1,'Explicit replacement removes obsolete record.');
    $history=$service->getImportHistory($correction);verify(count($history)===3&&$history[0]['before_count']===2&&$history[0]['after_count']===1,'All three changed versions retained with before/after counts.');
    $snapshot=$db->query("SELECT before_json FROM academic_record_snapshots WHERE entity_type='RESULTS_IMPORT' AND entity_id='$correction' ORDER BY id DESC LIMIT 1")->fetch_row()[0];verify(str_contains($snapshot,'AUDIT-B'),'Removed result remains recoverable in audit snapshot.');
    verify($service->reviewResults($correction,1,'Verified corrected source')['status']===1,'Correction needs fresh review.');
    verify($service->importResultsCsv($correction,$file,1,null,'REPLACE','Authority')['status']===0,'Reviewed result cannot be silently replaced.');
    verify($service->togglePublishStatus($correction,0,1)['status']===1&&$service->getNotificationById($correction)['reviewed_by']===null,'Return to draft clears obsolete approval.');
    $_SESSION=$facultyActor;reject(fn()=>$service->getImportHistory($correction),'Faculty cannot read academic result history.');
}finally{unlink($file);}
echo "Passed $checks CIA, policy and correction checks.\n";

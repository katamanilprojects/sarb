<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/verified_regulations.php';$checks=0;
function migrateRegulations(array $args):array{global $root,$name;$pipes=[];$proc=proc_open([PHP_BINARY,$root.'/tools/migrate.php','--database='.$name,'--apply',...$args],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($proc),$out,$err];}
$db->query('UPDATE curriculum_subjects SET cie_max_marks=30,see_max_marks=70 WHERE id IN(120,121)');
$db->query("DELETE FROM academic_settings WHERE reg_id=20 AND setting_key='grade_bands_json'");
$marksBefore=[];foreach(['internal_assessment_marks','pg_internal_assessment_marks','uglab_internal_assessment_marks','ugproject_internal_assessment_marks','exam_results','external_assessment_marks'] as $table){$marksBefore[$table]=hash('sha256',json_encode($db->query('SELECT * FROM '.$table.' ORDER BY id')->fetch_all(MYSQLI_ASSOC)));}
$count=(int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='REGULATION_CONFIG'")->fetch_row()[0];
[$status,$out]=migrateRegulations([]);verify($status!==0&&str_contains($out,'active academic administrator'),'SQL refuses unidentified operator.');
[$status,$out]=migrateRegulations(['--actor=2','--reason=Authority']);verify($status!==0&&str_contains($out,'active academic administrator'),'SQL refuses faculty audit identity.');
[$status,$out]=migrateRegulations(['--actor=1']);verify($status!==0&&str_contains($out,'active academic administrator'),'SQL requires an explicit authority.');
verify((float)$db->query('SELECT cie_max_marks FROM curriculum_subjects WHERE id=120')->fetch_row()[0]===30.0,'Rejected identity leaves curriculum unchanged.');
$db->query("UPDATE programs SET prog_shortname='UNKNOWN' WHERE id=12");[$status,$out]=migrateRegulations(['--actor=1','--reason=Authority']);$db->query("UPDATE programs SET prog_shortname='B.Tech' WHERE id=12");verify($status!==0&&str_contains($out,'six expected'),'SQL refuses incomplete semantic program mapping.');
$db->query('UPDATE curriculum_subjects SET cie_max_marks=32,see_max_marks=68 WHERE id=121');[$status,$out]=migrateRegulations(['--actor=1','--reason=Authority']);$db->query('UPDATE curriculum_subjects SET cie_max_marks=30,see_max_marks=70 WHERE id=121');verify($status!==0&&str_contains($out,'nonstandard PG'),'SQL refuses unexplained maxima.');
verify((float)$db->query('SELECT cie_max_marks FROM curriculum_subjects WHERE id=120')->fetch_row()[0]===30.0&&(int)$db->query("SELECT COUNT(*) FROM academic_settings WHERE reg_id=20 AND setting_key='grade_bands_json'")->fetch_row()[0]===0,'Preflight failure changes no other regulation.');
$db->query("CREATE TRIGGER production_sql_failure BEFORE INSERT ON academic_record_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
[$status,$out]=migrateRegulations(['--actor=1','--reason=Authority']);$db->query('DROP TRIGGER production_sql_failure');verify($status!==0&&str_contains($out,'Synthetic audit failure'),'SQL propagates failed audit persistence.');
verify((float)$db->query('SELECT cie_max_marks FROM curriculum_subjects WHERE id=120')->fetch_row()[0]===30.0&&(int)$db->query("SELECT COUNT(*) FROM academic_settings WHERE reg_id=20 AND setting_key='grade_bands_json'")->fetch_row()[0]===0,'SQL rolls back settings and maxima together.');
verify((int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='REGULATION_CONFIG'")->fetch_row()[0]===$count,'Failed SQL retains no partial history.');
verify((int)$db->query("SELECT COUNT(*) FROM schema_migrations WHERE version='20261015_002_verified_regulation_policies.sql'")->fetch_row()[0]===0,'Failed SQL is never marked applied.');
[$status,$out,$err]=migrateRegulations(['--actor=1','--reason=Production SQL verified with synthetic fixtures']);verify($status===0&&str_contains($out,'Applied: 20261015_002'),'Exact production SQL executes through migration runner. '.$out.$err);
verify((float)$db->query('SELECT cie_max_marks FROM curriculum_subjects WHERE id=120')->fetch_row()[0]===40.0&&(float)$db->query('SELECT see_max_marks FROM curriculum_subjects WHERE id=121')->fetch_row()[0]===60.0,'SQL repairs confirmed 30/70 maxima to 40/60.');
$profile=(new College\ResultsPolicy($db))->forSubject(120);$points=[];foreach($profile['config']['grade_bands'] as $band){$points[$band['grade']]=$band['points'];}verify((float)$points['A1']===9.5&&(float)$points['A3']===8.5&&(float)$points['D2']===5.5,'SQL preserves all representative fractional MCA points.');
$schemes=[];foreach([20,21,22,23,24,25] as $reg){$json=$db->query("SELECT setting_value FROM academic_settings WHERE reg_id=$reg AND setting_key='cia_scheme_json'")->fetch_row()[0];$schemes[$reg]=json_decode($json,true);}
verify($schemes[20]['theory']['better_weight']===.7&&$schemes[24]['theory']['better_weight']===.8,'Same R20 label retains program-specific weights.');
verify($schemes[21]['lab']['required_assessments']===[11,12]&&$schemes[22]['theory']['required_assessments']===[1,2,4],'SQL retains separate lab records and R25 continuous evidence.');
verify($schemes[23]===$schemes[24]&&$schemes[24]===$schemes[25],'SQL preserves approved B.Tech equivalence.');
foreach([20,21,22,23,24,25] as $reg){
    $meta=$db->query('SELECT r.regulation,p.prog_shortname,p.program_level FROM regulations r JOIN programs p ON p.id=r.prog_id WHERE r.id='.$reg)->fetch_assoc();$expected=College\VerifiedRegulations::definition($meta);$expectedValues=$expected['settings']+['cia_scheme_json'=>$expected['schemes'],'grade_bands_json'=>$expected['grade_bands']];
    $actual=[];foreach($db->query('SELECT setting_key,setting_value FROM academic_settings WHERE reg_id='.$reg) as $setting){$actual[$setting['setting_key']]=$setting['setting_value'];}
    $wrong=[];foreach($expectedValues as $key=>$value){if(!isset($actual[$key])||(is_array($value)?json_decode($actual[$key],true)!=$value:(float)$actual[$key]!== (float)$value)){$wrong[]=$key;}}
    verify(!$wrong,'All reviewed settings/grade bands match for regulation '.$reg.': '.implode(',',$wrong));
}
$history=$db->query("SELECT actor_user_id,reason,before_json,after_json FROM academic_record_snapshots WHERE entity_type='REGULATION_CONFIG' AND reason='Production SQL verified with synthetic fixtures'")->fetch_all(MYSQLI_ASSOC);verify(count($history)===6,'SQL records all six changed regulation snapshots.');
foreach($history as $entry){$before=json_decode($entry['before_json'],true);$after=json_decode($entry['after_json'],true);verify((int)$entry['actor_user_id']===1&&isset($before['settings'],$before['curriculum'],$after['sha256'])&&strlen($after['sha256'])===64,'History retains operator, before/after data and reviewed source hash.');}
$count=(int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='REGULATION_CONFIG'")->fetch_row()[0];
[$status,$out]=migrateRegulations(['--actor=1','--reason=Repeat']);verify($status===0&&str_contains($out,'Already applied: 20261015_002'),'Migration ledger skips a completed SQL file.');
$db->query("DELETE FROM schema_migrations WHERE version='20261015_002_verified_regulation_policies.sql'");[$status,$out,$err]=migrateRegulations(['--actor=1','--reason=Interrupted-ledger replay']);verify($status===0,'Data SQL safely replays without ledger row. '.$out.$err);
verify((int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='REGULATION_CONFIG'")->fetch_row()[0]===$count,'Replayed SQL creates no duplicate history for unchanged settings.');
verify((int)$db->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='cea_apply_verified_regulations_20261015'")->fetch_row()[0]===0,'Successful SQL removes its helper procedure.');
$untouched=true;foreach($marksBefore as $table=>$hash){$untouched=$untouched&&hash_equals($hash,hash('sha256',json_encode($db->query('SELECT * FROM '.$table.' ORDER BY id')->fetch_all(MYSQLI_ASSOC))));}verify($untouched,'SQL migrations leave every entered CIA/result/SEE record unchanged.');
echo "Passed $checks production-SQL migration checks.\n";

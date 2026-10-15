<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/jntuacea/core/Clock.php';
\College\Clock::configure();
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/.env.php';
$options=getopt('',['database:','output:']);$name=$options['database']??DB_NAME;
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$name);$db->set_charset('utf8mb4');
$db->query('SET TRANSACTION READ ONLY');$db->begin_transaction();
try{
 $queries=[
 'subjects_without_curriculum'=>"SELECT s.id,s.subcode,s.sub_fullname,c.id class_id,c.acad_year,c.reg_id,c.spec_id,c.yearsem,(SELECT GROUP_CONCAT(cs.id ORDER BY cs.id) FROM curriculum_subjects cs WHERE cs.reg_id=c.reg_id AND cs.spec_id=c.spec_id AND cs.yearsem=c.yearsem AND cs.subcode=s.subcode) candidate_curriculum_ids FROM subjects s JOIN classes c ON c.id=s.class_id WHERE s.curr_sub_id IS NULL ORDER BY c.id,s.id",
 'classes_without_batch'=>"SELECT c.id,c.classname,c.acad_year,c.reg_id,sp.prog_id FROM classes c JOIN specialization sp ON sp.id=c.spec_id WHERE c.batch_id IS NULL ORDER BY c.id",
 'shared_diary_periods'=>"SELECT sub_id,date,hour,COUNT(*) rows_count,COUNT(DISTINCT faculty_id) faculty_count FROM diary GROUP BY sub_id,date,hour HAVING COUNT(*)>1 ORDER BY sub_id,date,hour",
 'regulation_settings_coverage'=>"SELECT r.id,r.regulation,r.prog_id,COUNT(a.id) setting_count FROM regulations r LEFT JOIN academic_settings a ON a.reg_id=r.id GROUP BY r.id,r.regulation,r.prog_id",
 'password_upgrade_counts'=>"SELECT COUNT(*) total,SUM(password REGEXP '^[a-fA-F0-9]{32}$') legacy_passwords FROM users",
 ];$report=['generated_at'=>date(DATE_ATOM),'mode'=>'REVIEW_ONLY','instructions'=>'No records have been changed. A unique candidate still requires a review of the authoritative curriculum and cohort history.','findings'=>[]];
 foreach($queries as $key=>$sql){$report['findings'][$key]=$db->query($sql)->fetch_all(MYSQLI_ASSOC);}
 $json=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
 if(!empty($options['output'])){file_put_contents($options['output'],$json);chmod($options['output'],0600);echo 'Read-only reconciliation report written.'.PHP_EOL;}else{echo $json.PHP_EOL;}
}finally{$db->rollback();}

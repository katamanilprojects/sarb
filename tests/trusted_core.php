<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__).'/.env.php';
require_once dirname(__DIR__).'/jntuacea/core/Connection.php';
\College\Clock::configure();
require_once dirname(__DIR__).'/jntuacea/core/RecordScope.php';
require_once dirname(__DIR__).'/jntuacea/core/RequestSecurity.php';
require_once dirname(__DIR__).'/jntuacea/core/AttendanceService.php';
require_once dirname(__DIR__).'/jntuacea/core/AttendanceCorrection.php';
require_once dirname(__DIR__).'/jntuacea/core/CertificateNumber.php';
require_once dirname(__DIR__).'/jntuacea/core/ReportValue.php';
$options=getopt('',['database:']);$name=$options['database']??'';
if (!str_starts_with($name,'cea_standardisation_test_') || $name===DB_NAME) { throw new RuntimeException('Tests require an isolated cea_standardisation_test_ database.'); }
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli(DB_HOST,DB_USER,DB_PASS,$name);$db->set_charset('utf8mb4');
(new ReflectionProperty(College\Connection::class,'connection'))->setValue(null,$db);
$checks=0;
function check(bool $value,string $message): void { global $checks; if(!$value){throw new RuntimeException($message);} $checks++; }
function rejects(callable $call,string $message): void {try{$call();}catch(DomainException|InvalidArgumentException $e){check(true,$message);return;}throw new RuntimeException($message);}
$_SESSION=['csrf_token'=>str_repeat('a',64)];
check(College\RequestSecurity::validToken(['_csrf'=>str_repeat('a',64)],$_SESSION),'valid CSRF');
check(!College\RequestSecurity::validToken(['_csrf'=>'bad'],$_SESSION),'invalid CSRF');
check(!College\RequestSecurity::validToken([],[]),'empty CSRF');
check(!str_contains(College\RequestSecurity::protectForms('<form method="post" action="https://external.example/submit"></form>'),'name="_csrf"'),'CSRF token not sent to external form');
check(!str_contains(College\RequestSecurity::protectForms('<form method=post action=https://external.example/submit></form>'),'name="_csrf"'),'unquoted external form protected');
check(substr_count(College\RequestSecurity::protectForms('<form method="post"></form><form method="get"></form>'),'name="_csrf"')===1,'post form injection');
$rules=['min_aggregate'=>75,'condone_floor'=>65,'min_subject'=>40,'is_complete'=>true];
foreach([[75,100,'ELIGIBLE'],[65,100,'CONDONABLE'],[64,100,'DETAINED'],[0,0,'DATA_UNAVAILABLE'],[74999,100000,'CONDONABLE']] as [$p,$n,$expected]){
 $v=College\AttendanceCalculator::classify([1=>['present'=>$p,'conducted'=>$n]],$rules);check($v['category']===$expected,'threshold boundary '.$p.'/'.$n);
}
$v=College\AttendanceCalculator::classify([1=>['present'=>75,'conducted'=>100],2=>['present'=>0,'conducted'=>0]],$rules);check($v['category']==='DATA_UNAVAILABLE','missing subject never eligible');
$v=College\AttendanceCalculator::classify([1=>['present'=>70,'conducted'=>100,'permission_absences'=>10]],$rules,'REVIEW');check($v['category']==='POLICY_REVIEW','unknown permissions');
$v=College\AttendanceCalculator::classify([1=>['present'=>70,'conducted'=>100,'permission_absences'=>10]],$rules,'COUNT_AS_ATTENDED');check($v['percentage']===80.0,'approved counting');
rejects(fn()=>College\AttendanceCalculator::classify([1=>['present'=>101,'conducted'=>100]],$rules),'impossible counts');
check(!College\ReportValue::certified(null),'no implicit certificate');
check(!College\ReportValue::certified(['faculty_signoff_status'=>'SUBMITTED','hod_approval_status'=>'APPROVED']),'approval requires reviewer and timestamps');
$student=$db->query("SELECT st.id,st.username,st.class_id,ss.sub_id FROM students st JOIN student_sub ss ON ss.stu_id=st.id WHERE st.status=1 LIMIT 1")->fetch_assoc();
$scope=new College\RecordScope($db,['role'=>'student','user'=>$student['username']]);$scope->student((int)$student['id'],(int)$student['sub_id']);check(true,'own student access');
$other=$db->query('SELECT stu_id FROM student_sub WHERE sub_id='.(int)$student['sub_id'].' AND stu_id<>'.(int)$student['id'].' LIMIT 1')->fetch_row();
if($other){rejects(fn()=>$scope->student((int)$other[0],(int)$student['sub_id']),'other student in same subject denied');}
rejects(fn()=>$scope->documentOwner('OTHER-STUDENT'),'other document denied');
$curriculum=$db->query("SELECT s.curr_sub_id,st.username FROM subjects s JOIN student_sub ss ON ss.sub_id=s.id JOIN students st ON st.id=ss.stu_id WHERE s.curr_sub_id IS NOT NULL LIMIT 1")->fetch_assoc();
(new College\RecordScope($db,['role'=>'student','user'=>$curriculum['username']]))->curriculumSubject((int)$curriculum['curr_sub_id']);check(true,'own curriculum subject scoped');
rejects(fn()=>(new College\RecordScope($db,['role'=>'student','user'=>'UNASSIGNED']))->curriculumSubject((int)$curriculum['curr_sub_id']),'unassigned curriculum subject denied');

$_SESSION=[];
$report=(new College\AttendanceService($db))->forClass((int)$student['class_id'],(int)$student['id']);check(count($report['students'])===1,'attendance service schema and student mapping');
rejects(fn()=>(new College\AttendanceService($db))->forClass((int)$student['class_id'],null,'2026-02-30','2026-03-01'),'invalid date');
require_once dirname(__DIR__).'/services/SettingsService.php';
$settings=Services\SettingsService::getInstance();$settings->setConnection($db);
check($settings->get('cia_total','NONEXISTENT_REGULATION',null)===null,'no cross-regulation fallback');
rejects(fn()=>$settings->get('co_target_percentage','R20',null),'ambiguous regulation label rejected');
check($settings->getMetadata('theory_cia_total_marks','1')===null,'missing metadata never borrows another regulation');
require_once dirname(__DIR__).'/services/BatchOBEService.php';
$batchSvc=new Services\BatchOBEService();
$context=$db->query('SELECT b.id,b.program_id,sp.dept_id FROM student_batches b JOIN specialization sp ON sp.prog_id=b.program_id LIMIT 1')->fetch_assoc();
if($context){$macro=$batchSvc->getBatchMacroAttainment((int)$context['id'],(int)$context['dept_id']);check(empty($macro['peos']),'no simulated PEO scores');$matrix=$batchSvc->computePOPSOArticulationMatrix((int)$context['id'],(int)$context['program_id'],(int)$context['dept_id']);check(is_array($matrix),'batch articulation SQL');}
require_once dirname(__DIR__).'/services/CourseMasterFilePDFService.php';
$pdf=(new ReflectionClass(Services\CourseMasterFilePDFService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(DBCredentials::class,'conn'))->setValue($pdf,$db);
$html=(new ReflectionMethod($pdf,'renderSection6Certificate'))->invoke($pdf,[],[],null);
check(str_contains($html,'REVIEW PENDING')&&!str_contains($html,'Verified 100%'),'draft PDF truthfulness');
$a=College\CertificateNumber::reserve($db,'BONAFIDE');$b=College\CertificateNumber::reserve($db,'BONAFIDE');check($a!==$b,'unique certificate reservation');
$fixture=$db->query("SELECT d.sub_id,d.date,d.hour,d.faculty_id,sp.dept_id,dep.username,u.id user_id FROM diary d JOIN subjects s ON s.id=d.sub_id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments dep ON dep.id=sp.dept_id JOIN users u ON u.username=dep.username WHERE u.role='hod' AND NOT EXISTS(SELECT 1 FROM diary other WHERE other.sub_id=d.sub_id AND other.date=d.date AND other.hour=d.hour AND other.faculty_id<>d.faculty_id) LIMIT 1")->fetch_assoc();
if(!$fixture){throw new RuntimeException('Missing correction test fixture');}
$stmt=$db->prepare("INSERT INTO attendance_delete_requests(faculty_id,subject_id,date,hour,reason) VALUES (?,?,?,?,'ISOLATED TEST')");$stmt->bind_param('iisi',$fixture['faculty_id'],$fixture['sub_id'],$fixture['date'],$fixture['hour']);$stmt->execute();$id=$db->insert_id;
$actor=['userid'=>(int)$fixture['user_id'],'role'=>'hod','user'=>$fixture['username']];
$correction=new College\AttendanceCorrection($db,$actor);
$db->query("CREATE TRIGGER cea_test_rollback BEFORE DELETE ON diary FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
$failed=false;try{$correction->decide($id,'Approved');}catch(Throwable $e){$failed=true;}
$db->query('DROP TRIGGER cea_test_rollback');check($failed,'simulated deletion failure');
check($db->query('SELECT status FROM attendance_delete_requests WHERE id='.$id)->fetch_row()[0]==='Pending','decision rolled back');
check((int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='ATTENDANCE_REQUEST' AND entity_id='$id'")->fetch_row()[0]===0,'snapshot rolled back with failed decision');
check($correction->decide($id,'Approved')['status']===1,'atomic approval');
check((int)$db->query("SELECT COUNT(*) FROM academic_record_snapshots WHERE entity_type='ATTENDANCE_REQUEST' AND entity_id='$id'")->fetch_row()[0]===1,'snapshot retained');
rejects(fn()=>$correction->decide($id,'Approved'),'repeat decision denied');
rejects(fn()=>(new College\AttendanceCorrection($db,['role'=>'faculty','userid'=>1]))->decide($id,'Rejected'),'faculty cannot review');
$fix=$db->query("SELECT a.*,f.username faculty_username,u.id faculty_user_id FROM attendance a JOIN student_sub ss ON ss.stu_id=a.stu_id AND ss.sub_id=a.sub_id JOIN faculty_sub fs ON fs.sub_id=a.sub_id JOIN faculties f ON f.id=fs.faculty_id JOIN users u ON u.username=f.username AND u.role='faculty' WHERE NOT EXISTS(SELECT 1 FROM attendance other WHERE other.stu_id=a.stu_id AND other.sub_id=a.sub_id AND other.date=CURRENT_DATE AND other.hour=a.hour AND other.id<>a.id) LIMIT 1")->fetch_assoc();
check((bool)$fix,'individual correction test context');
$db->query('UPDATE attendance SET date=CURRENT_DATE WHERE id='.(int)$fix['id']);
$edit=new College\AttendanceCorrection($db,['role'=>'faculty','userid'=>(int)$fix['faculty_user_id'],'user'=>$fix['faculty_username']]);
$change=$fix['status']==='P'?'A':'P';
rejects(fn()=>$edit->updateStudent((int)$fix['stu_id'],(int)$fix['sub_id'],date('Y-m-d'),(int)$fix['hour'],$change,''),'correction requires reason');
$db->query("CREATE TRIGGER cea_correction_failure BEFORE UPDATE ON attendance FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test correction rollback'");
$failed=false;try{$edit->updateStudent((int)$fix['stu_id'],(int)$fix['sub_id'],date('Y-m-d'),(int)$fix['hour'],$change,'ISOLATED TEST');}catch(Throwable $e){$failed=true;}$db->query('DROP TRIGGER cea_correction_failure');
check($failed && $db->query('SELECT status FROM attendance WHERE id='.(int)$fix['id'])->fetch_row()[0]===$fix['status'],'failed correction retains original status');
check($edit->updateStudent((int)$fix['stu_id'],(int)$fix['sub_id'],date('Y-m-d'),(int)$fix['hour'],$change,'ISOLATED TEST')['status']===1,'individual correction succeeds');
$snap=$db->query("SELECT before_json,after_json FROM academic_record_snapshots WHERE entity_type='ATTENDANCE' AND entity_id='".(int)$fix['id']."' ORDER BY id DESC LIMIT 1")->fetch_assoc();
check(json_decode($snap['before_json'],true)['status']===$fix['status']&&json_decode($snap['after_json'],true)['status']===$change,'correction retains before and after evidence');
echo "Passed $checks trusted-core checks.\n";

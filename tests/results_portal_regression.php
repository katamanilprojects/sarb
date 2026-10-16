<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_start(['use_cookies'=>false]);
$root = dirname(__DIR__);
require $root . '/.env.php';
$options = getopt('', ['database:', 'student']); $name = $options['database'] ?? '';
if (!preg_match('/^cea_results_test_[a-f0-9]{12}$/D', $name) || $name === DB_NAME) {
    throw new RuntimeException('Supply a disposable schema-only cea_results_test_<12 hex> database.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, $name); $db->set_charset('utf8mb4');
require $root . (isset($options['student']) ? '/jntuaceastudents' : '/jntuacea') . '/core/Connection.php';
(new ReflectionProperty(College\Connection::class, 'connection'))->setValue(null, $db);
$checks = 0;
function verify(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function reject(callable $callback, string $message): void {
    try { $callback(); } catch (DomainException | InvalidArgumentException $e) { verify(true, $message); return; }
    throw new RuntimeException($message);
}
if (isset($options['student'])) {
    require $root . '/jntuaceastudents/services/ExamResultsService.php';
    $_SESSION = ['userid'=>10, 'role'=>'student', 'user'=>'AUDIT-A'];
    $service = new Services\ExamResultsService();
    $data = $service->getStudentResults('AUDIT-A');
    verify(count($data['results']) >= 2, 'Own published regular and supplementary attempts visible.');
    foreach ($data['results'] as $row) { verify($row['htno'] === 'AUDIT-A', 'Another student’s result is never returned.'); }
    reject(fn() => $service->getStudentResults('AUDIT-B'), 'Other admission number rejected.');
    reject(fn() => $service->getStudentSubjectResult('AUDIT-B', 1, 'CODE1'), 'Per-course lookup rejects another student.');
    $subject = $service->getStudentSubjectResult('AUDIT-A', 1, 'WRONG-CODE');
    verify((int)$subject['subject_id'] === 1, 'Subject ID, not supplied subject code, controls the offering.');
    verify($service->getStudentSubjectResult('AUDIT-A', 2, 'CODE1') === null, 'Same code cannot borrow another course result.');
    verify($service->getNotificationById(900001) === null, 'Unpublished notification metadata hidden.');
    foreach ($service->getNotifications(null, null, false) as $notification) {
        verify((int)$notification['is_published'] === 1, 'Draft metadata remains hidden even when caller requests drafts.');
        verify($service->getStudentResults('AUDIT-A', (int)$notification['id'])['results'] !== [], 'Notification has results for this student.');
    }
    verify($data['sgpa'] === null, 'No combined SGPA across separate attempts.');
    verify($service->getGradePoints('A', 1) === 8.0 && $service->getGradePoints('A', 3) === 9.0, 'Same letter resolves different points for different regulations.');
    reject(fn() => $service->getGradePoints('A'), 'No global grade-point default.');
    session_destroy(); echo "Passed $checks student-portal checks.\n"; exit;
}

// This script resets only a pre-created disposable database; it never copies student data.
$db->query('SET FOREIGN_KEY_CHECKS=0');
foreach ($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM) as [$table]) { $db->query('TRUNCATE TABLE `' . str_replace('`','``',$table) . '`'); }
function seed(string $table, array $values): void {
    global $db;
    foreach ($db->query('SHOW COLUMNS FROM `' . $table . '`') as $column) {
        $field = $column['Field'];
        if (array_key_exists($field, $values) || $column['Null'] === 'YES' || $column['Default'] !== null || ($column['Extra'] === 'auto_increment' || str_contains($column['Extra'],'GENERATED'))) { continue; }
        $type = $column['Type'];
        if (str_starts_with($type, 'enum(')) { preg_match("/^enum\('([^']+)'/", $type, $match); $values[$field] = $match[1]; }
        elseif (preg_match('/int|decimal|float|double/', $type)) { $values[$field] = 1; }
        elseif ($type === 'date') { $values[$field] = '2026-10-01'; }
        else { $values[$field] = 'TEST'; }
    }
    $columns = array_keys($values); $args = array_values($values);
    $stmt = $db->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`',$columns) . '`) VALUES (' . implode(',', array_fill(0,count($args),'?')) . ')');
    $types = str_repeat('s', count($args)); $stmt->bind_param($types, ...$args); $stmt->execute();
}
foreach ([[1,'AUDIT-OFFICE','academic_section'],[2,'AUDIT-FAC','faculty'],[3,'AUDIT-HOD','hod'],[10,'AUDIT-A','student'],[11,'AUDIT-B','student']] as [$id,$username,$role]) {
    seed('users', ['id'=>$id,'username'=>$username,'password'=>'TEST-NOT-A-CREDENTIAL','name'=>'Synthetic test user','role'=>$role,'status'=>1]);
}
seed('programs', ['id'=>1,'program_code'=>'T','prog_shortname'=>'TEST','prog_fullname'=>'Synthetic program']);
seed('departments', ['id'=>1,'username'=>'AUDIT-HOD','dept_shortname'=>'TEST','dept_fullname'=>'Synthetic department','status'=>1]);
seed('specialization', ['id'=>1,'dept_id'=>1,'prog_id'=>1]);
seed('regulations', ['id'=>1,'regulation'=>'T25','prog_id'=>1]);
seed('regulations', ['id'=>2,'regulation'=>'U25','prog_id'=>1]);
seed('faculties', ['id'=>1,'username'=>'AUDIT-FAC','facultyid'=>2,'dept_id'=>1]);
foreach ([[1,'2026-27',1],[2,'2026-27',2],[3,'2024-25',1]] as [$id,$year,$reg]) {
    seed('classes',['id'=>$id,'acad_year'=>$year,'classname'=>'Synthetic class','section'=>(string)$id,'yearsem'=>'I Yr - I Sem','start_date'=>'2026-01-01','end_date'=>'2026-12-31','spec_id'=>1,'reg_id'=>$reg]);
}
foreach ([[1,1,'CODE1',1],[2,1,'CODE2',1],[3,2,'CODE1',2],[4,3,'CODE1',1]] as [$id,$class,$code,$reg]) {
    if ($id !== 4) { seed('curriculum_subjects',['id'=>$id,'prog_id'=>1,'reg_id'=>$reg,'spec_id'=>1,'yearsem'=>'I Yr - I Sem','subcode'=>$code,'sub_type'=>'theory','credits'=>3,'cie_max_marks'=>30,'see_max_marks'=>70]); }
    seed('subjects',['id'=>$id,'class_id'=>$class,'curr_sub_id'=>$id === 4 ? 1 : $id,'subject_sno'=>(string)$id,'subcode'=>$code,'sub_type'=>'theory']);
}
foreach ([[1,'AUDIT-A',1],[2,'AUDIT-B',1],[3,'AUDIT-A',3],[4,'AUDIT-A',2]] as [$id,$roll,$class]) { seed('students',['id'=>$id,'username'=>$roll,'class_id'=>$class,'status'=>1]); }
foreach ([[1,1],[2,1],[1,2],[3,4],[4,3]] as [$student,$subject]) { seed('student_sub',['stu_id'=>$student,'sub_id'=>$subject]); }
foreach ([1,2] as $subject) { seed('faculty_sub',['faculty_id'=>1,'sub_id'=>$subject]); }
$bands = [['grade'=>'A','points'=>8,'min'=>80,'max'=>100],['grade'=>'B','points'=>6,'min'=>50,'max'=>79],['grade'=>'F','points'=>0,'min'=>0,'max'=>49]];
seed('academic_settings',['reg_id'=>1,'regulation_code'=>'T25','category'=>'GRADING','setting_key'=>'grade_bands_json','setting_value'=>json_encode($bands),'data_type'=>'JSON']);
$bands[0]['points'] = 9;
seed('academic_settings',['reg_id'=>2,'regulation_code'=>'U25','category'=>'GRADING','setting_key'=>'grade_bands_json','setting_value'=>json_encode($bands),'data_type'=>'JSON']);
foreach (['MOD_RESULTS_PUBLISH','MOD_SEE_MARKS','MOD_ATTENDANCE','MOD_CIA_METADATA','MOD_FEEDBACK'] as $key) {
    seed('system_feature_modules',['module_key'=>$key,'module_name'=>$key,'is_enabled_globally'=>1,'faculty_visibility'=>'VISIBLE','hod_visibility'=>'VISIBLE','student_visibility'=>'VISIBLE']);
}
seed('class_timings',['id'=>1,'timing_id'=>1,'hour'=>1,'hour_desc'=>'Synthetic period','start_time'=>'09:00:00','end_time'=>'10:00:00']);
seed('diary',['sub_id'=>1,'faculty_id'=>1,'date'=>'2026-10-01','hour'=>1,'diary'=>'Synthetic lecture']);
seed('attendance',['stu_id'=>1,'sub_id'=>1,'date'=>'2026-10-01','hour'=>1,'status'=>'P']);
seed('attendance',['stu_id'=>2,'sub_id'=>1,'date'=>'2026-10-01','hour'=>1,'status'=>'A']);
$db->query('SET FOREIGN_KEY_CHECKS=1');
$_SESSION = ['userid'=>1,'role'=>'academic_section','user'=>'AUDIT-OFFICE'];
require $root . '/jntuacea/services/ExamResultsService.php';
require $root . '/jntuacea/services/FeatureManager.php';
$service = new Services\ExamResultsService();
$new = static function(string $code, string $type = 'REGULAR', int $reg = 1) use ($service): int {
    $r = $service->createNotification(['notification_code'=>$code,'title'=>'Synthetic exam','academic_year'=>'2026-27','program_id'=>1,'regulation_id'=>$reg,'yearsem'=>'I Yr - I Sem','month_year'=>'October 2026','release_date'=>'2026-10-15','exam_type'=>$type,'is_published'=>1],1);
    verify($r['status'] === 1, 'Create notification: ' . ($r['err'] ?? $code)); return (int)$r['id'];
};
$id = $new('TEST-REGULAR');
verify((int)$service->getNotificationById($id)['is_published'] === 0, 'New notification always starts as draft.');
verify($service->createNotification(['notification_code'=>'BROKEN'],1)['status'] === 0, 'Incomplete notifications return a validation error.');
$header = ['std_regno','sub_code','im','em','total','grade','grade_points','credits','result'];
$valid = ['AUDIT-A','CODE1',20,60,80,'A',8,3,'P'];
$file = tempnam(sys_get_temp_dir(),'cea-results-fixture-');
$csv = static function(array $rows, ?array $columns = null) use ($file,$header): void { $h=fopen($file,'w'); fputcsv($h,$columns??$header); foreach($rows as $row){fputcsv($h,$row);} fclose($h); };
$preview = static function(array $row) use ($service,$id,$csv,$file): array { $csv([$row]); return $service->previewResultsCsv($id,$file); };
try {
    verify($preview($valid)['status'] === 1, 'Legacy result format accepted.');
    $admissionHeader=$header;$admissionHeader[0]='AdmissionNumber';$csv([$valid],$admissionHeader);
    verify($service->previewResultsCsv($id,$file)['status']===1,'AdmissionNumber header accepted.');
    foreach (['points'=>[6,999],'grade mismatch'=>[6,9],'malformed marks'=>[3,'bogus'],'unsupported negative'=>[3,-9],'overscore'=>[3,71],'wrong total'=>[4,81],'wrong credits'=>[7,4],'unknown student'=>[0,'UNKNOWN'],'unknown course'=>[1,'UNKNOWN']] as $label=>[$column,$value]) {
        $bad=$valid;$bad[$column]=$value;verify($preview($bad)['status']===0,'Reject '.$label);
    }
    $bad=$valid;$bad[3]=-1;$bad[5]='AB';verify($preview($bad)['status']===0,'Absence cannot retain positive points.');
    $absent=$bad;$absent[6]=0;$absent[7]=0;$absent[8]='F';$absent[4]=20;
    $r=$preview($absent);verify($r['status']===1&&$r['rows'][0]['result_status']==='ABSENT'&&$r['rows'][0]['credits_earned']===0.0,'Legacy absence normalizes before credits.');
    $bad=$valid;$bad[2]=900;$bad[3]=900;$bad[4]=1800;
    $csv([array_merge($bad,[999,999])],array_merge($header,['InternalMax','ExternalMax']));verify($service->previewResultsCsv($id,$file)['status']===0,'Uploaded maxima cannot override course rules.');
    $csv([array_merge($valid,[20])],array_merge($header,['Internals']));reject(fn()=>$service->previewResultsCsv($id,$file),'Ambiguous aliases rejected.');
    $csv([$valid,$valid]);verify($service->previewResultsCsv($id,$file)['status']===0,'Duplicate rows rejected.');
    $bad=$valid;$bad[0]='UNKNOWN';$csv([$valid,$bad]);verify($service->importResultsCsv($id,$file,1)['status']===0,'Mixed invalid import rejected atomically.');
    verify((int)$db->query('SELECT COUNT(*) FROM exam_results')->fetch_row()[0]===0,'No partial result records written.');
    $db->query("UPDATE exam_notifications SET academic_year='2025-26' WHERE id=$id");verify($preview($valid)['status']===0,'Regular attempt never falls back to a historical year.');
    $db->query("UPDATE exam_notifications SET academic_year='2026-27' WHERE id=$id");
    $csv([$valid]);$p=$service->previewResultsCsv($id,$file);
    $db->query('UPDATE curriculum_subjects SET credits=4 WHERE id=1');
    verify($service->importResultsCsv($id,$file,1,$p['validation_hash'])['status']===0,'Changed preview policy prevents commit.');
    $db->query('UPDATE curriculum_subjects SET credits=3 WHERE id=1');
    $other=$valid;$other[0]='AUDIT-B';$csv([$valid,$other]);
    verify($service->importResultsCsv($id,$file,1)['status']===1,'Valid draft imported.');
    verify($service->togglePublishStatus($id,1,1)['status']===0,'Unreviewed draft cannot publish.');
    $db->query("UPDATE exam_results SET grade_points=999 WHERE notification_id=$id AND student_id=1");
    verify($service->reviewResults($id,1,'Invalid record')['status']===0,'Review revalidates persisted grade points.');
    $db->query("UPDATE exam_results SET grade_points=8 WHERE notification_id=$id AND student_id=1");
    verify($service->reviewResults($id,1,'Synthetic verification')['status']===1,'Validated results reviewed.');
    seed('external_assessment_marks',['student_id'=>2,'subject_id'=>1,'entry_mode'=>'DETAILED','external_marks'=>42,'max_marks'=>70,'submitted_by'=>2,'evidence_basis'=>'QUESTION_WISE']);
    $db->query("CREATE TRIGGER results_publish_failure BEFORE INSERT ON academic_record_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
    verify($service->togglePublishStatus($id,1,1)['status']===0,'SEE failure rolls back publication.');
    $db->query('DROP TRIGGER results_publish_failure');
    verify((int)$service->getNotificationById($id)['is_published']===0,'Failed synchronization leaves notification unpublished.');
    verify((int)$db->query('SELECT COUNT(*) FROM external_assessment_marks WHERE student_id=1')->fetch_row()[0]===0,'Failed publication leaves no partial SEE marks.');
    $r=$service->togglePublishStatus($id,1,1);verify($r['status']===1&&$r['summary']['synced']===1&&$r['summary']['preserved']===1,'Publication syncs results and preserves detailed marks.');
    verify((float)$db->query('SELECT external_marks FROM external_assessment_marks WHERE student_id=1')->fetch_row()[0]===60.0,'Exact imported external marks supply SEE.');
    verify((float)$db->query('SELECT external_marks FROM external_assessment_marks WHERE student_id=2')->fetch_row()[0]===42.0,'Detailed marks unchanged.');
    verify($service->importResultsCsv($id,$file,1)['status']===0,'Published results cannot be edited by import.');
    verify($service->togglePublishStatus($id,0,1)['status']===1,'Withdrawal succeeds.');
    verify((int)$db->query('SELECT is_current FROM external_assessment_marks WHERE student_id=1')->fetch_row()[0]===0,'Withdrawal invalidates dependent SEE evidence.');
    verify((int)$db->query('SELECT is_current FROM external_assessment_marks WHERE student_id=2')->fetch_row()[0]===1,'Withdrawal does not invalidate independent detailed evidence.');
    $db->query("UPDATE external_assessment_marks SET entry_mode='DIRECT',evidence_basis='LEGACY' WHERE student_id=2");
    $csv([$valid,$other]);verify($service->importResultsCsv($id,$file,1)['status']===1,'Withdrawn draft can be corrected.');
    verify($service->reviewResults($id,1,'Reviewed correction')['status']===1,'Corrected draft reviewed.');
    verify($service->togglePublishStatus($id,1,1)['status']===1,'Corrected regular attempt republished.');
    verify((float)$db->query('SELECT external_marks FROM external_assessment_marks WHERE student_id=2')->fetch_row()[0]===42.0,'Republishing preserves manual direct marks too.');
    $supp=$new('TEST-SUPPLEMENTARY','SUPPLEMENTARY');$changed=$valid;$changed[3]=65;$changed[4]=85;$csv([$changed]);
    verify($service->getNotificationById($supp)['exam_type']==='SUPPLEMENTARY','Supplementary exam type retained.');
    verify($service->importResultsCsv($supp,$file,1)['status']===1,'Supplementary attempt imported.');
    verify($service->reviewResults($supp,1,'Separate attempt')['status']===1&&$service->togglePublishStatus($supp,1,1)['status']===1,'Separate attempt published.');
    verify((float)$db->query('SELECT external_marks FROM external_assessment_marks WHERE student_id=1')->fetch_row()[0]===60.0,'Supplementary result never replaces regular OBE evidence.');
    verify($service->syncResultsToSeeMarks(1,1,$supp,true,'Synthetic reason')['status']===0,'Explicit sync also rejects supplementary attempt.');
    $gradeOnly=$new('TEST-GRADE-ONLY');$gradeRow=$valid;$gradeRow[1]='CODE2';$gradeRow[3]='-5';$gradeRow[4]='-';$csv([$gradeRow]);
    verify($service->importResultsCsv($gradeOnly,$file,1)['status']===1,'Explicit legacy grade-only result imported.');
    verify($service->reviewResults($gradeOnly,1,'Grade evidence reviewed')['status']===1,'Grade-only result reviewed against regulation bands.');
    $r=$service->togglePublishStatus($gradeOnly,1,1);
    verify($r['status']===1&&$r['summary']['pending']===1,'No arbitrary SEE estimate without a configured strategy.');
    verify($service->togglePublishStatus($gradeOnly,0,1)['status']===1,'Grade-only result can be withdrawn.');
    seed('attainment_policies',['regulation_id'=>1,'subject_type'=>'all','config_json'=>json_encode(['grade_strategy'=>'MIDPOINT']),'version'=>1,'updated_by'=>1]);
    verify($service->reviewResults($gradeOnly,1,'Configured grade strategy')['status']===1&&$service->togglePublishStatus($gradeOnly,1,1)['status']===1,'Explicit regulation grade estimation honored.');
    $estimated=$db->query('SELECT external_marks,evidence_basis FROM external_assessment_marks WHERE subject_id=2')->fetch_assoc();
    verify(abs((float)$estimated['external_marks']-63.0)<0.00001&&$estimated['evidence_basis']==='GRADE_ESTIMATE','Configured grade estimate labeled separately from measured marks.');
    verify($service->togglePublishStatus($gradeOnly,0,1)['status']===1,'Estimated source withdrawal marks evidence stale.');
    $withheld=$new('TEST-WITHHELD');$withheldRow=$gradeRow;$withheldRow[5]='WH';$withheldRow[6]=0;$withheldRow[7]=0;$withheldRow[8]='W';$csv([$withheldRow]);
    verify($service->importResultsCsv($withheld,$file,1)['status']===1&&$service->reviewResults($withheld,1,'Withheld record')['status']===1,'Withheld result retains its status.');
    $r=$service->togglePublishStatus($withheld,1,1);verify($r['status']===1&&$r['summary']['preserved']===1,'Withheld result does not overwrite another attempt’s evidence.');
    require_once $root . '/jntuacea/core/PublishedResults.php';
    $reader=new College\PublishedResults($db);verify($reader->forStudent('AUDIT-A',$withheld)['sgpa']===null,'Withheld result has no final GPA.');
    verify($service->togglePublishStatus($withheld,0,1)['status']===1,'Withdraw withheld test notification.');
    $profile=(new College\ResultsPolicy($db))->forSubject(1);
    $zero=['status'=>'P','grade'=>'A','points'=>8,'credits'=>0,'internal'=>20,'external'=>60,'total'=>80];
    verify(College\ResultRow::normalize($zero,['credits'=>0],$profile['config'])['credits_registered']===0.0,'Zero-credit course stays zero-credit.');
    seed('exam_notifications',['id'=>900001,'notification_code'=>'TEST-DRAFT','title'=>'Hidden draft','academic_year'=>'2026-27','program_id'=>1,'regulation_id'=>1,'yearsem'=>'I Yr - I Sem','month_year'=>'October','release_date'=>'2026-10-15','is_published'=>0]);
    require $root . '/jntuacea/faculty.class.php'; require $root . '/jntuacea/hod.class.php';
    $_SESSION=['userid'=>2,'role'=>'faculty','user'=>'AUDIT-FAC','facid'=>1];$faculty=new Faculty();
    verify($faculty->getOverallAttendanceBySubject(1,'2026-10-01','2026-10-02')['status']===1,'Bound attendance query runs on the real schema.');
    verify($faculty->getDetailedAttendanceBySubject(1,'2026-10-01','2026-10-02')['status']===1,'Bound detailed query runs.');
    verify($faculty->getDetailedAttendanceBySubject(1,"2026-01-01' OR 1=1 --",'2026-12-31')['status']===0,'Injection-shaped dates rejected.');
    verify($faculty->getDetailedAttendanceBySubject(1,'2026-02-30','2026-03-01')['status']===0,'Impossible dates rejected.');
    verify($faculty->getDetailedAttendanceBySubject(1,'2026-10-02','2026-10-01')['status']===0,'Reversed dates rejected.');
    verify($faculty->getDetailedAttendanceBySubject(1,['2026-10-01'],'2026-10-02')['status']===0,'Array-shaped date input returns a validation error.');
    verify($faculty->getOverallAttendanceBySubject(1,'2026-10-01',null)['status']===0,'Incomplete date range rejected.');
    verify($faculty->getOverallAttendanceBySubject(3)['status']===0,'Unassigned subject attendance denied.');
    for($i=0;$i<5;$i++){verify($faculty->addCIAAttachment(1,1,'Synthetic attachment','uploads/cia_test'.$i.'.pdf')['status']===1,'Attachment admitted within limit.');}
    verify($faculty->addCIAAttachment(1,1,'Excess','uploads/cia_excess.pdf')['status']===0,'Sixth attachment rejected on server.');
    verify($faculty->getCIAAttachments(3,1)['status']===0,'Unassigned subject attachments denied.');
    $_SESSION=['userid'=>3,'role'=>'hod','user'=>'AUDIT-HOD','dept_id'=>1];$hod=new HOD();
    verify($hod->getDetailedAttendanceBySubjectExcludingPermissions(1,'2026-10-01','2026-10-02')['status']===1,'Bound permission-aware HOD query runs.');
    verify($hod->getDetailedAttendanceBySubjectExcludingPermissions(1,"bad' date",'2026-10-02')['status']===0,'HOD injection-shaped date rejected.');
    echo "Passed $checks staff/results integration checks.\n";
} finally { unlink($file); session_destroy(); }

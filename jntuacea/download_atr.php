<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/services/FeatureManager.php';
\FeatureManager::requireAction('MOD_CQI_ACTION_TRACKING','export');
require_once __DIR__.'/services/ReportSnapshotService.php';
require_once __DIR__.'/vendor/autoload.php';
$id=(int)($_GET['id']??0);$db=\DBCredentials::getInstance();$conn=$db->getConnection();
$conn->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
$stmt=$conn->prepare('SELECT a.*,s.subcode,s.sub_fullname,co.co_number,c.acad_year,d.dept_fullname,u.name faculty_name FROM course_cqi_actions a JOIN subjects s ON s.id=a.sub_id JOIN course_outcomes co ON co.id=a.co_id AND co.sub_id=s.id JOIN classes c ON c.id=s.class_id JOIN specialization sp ON sp.id=c.spec_id JOIN departments d ON d.id=sp.dept_id JOIN faculties f ON f.id=a.faculty_id JOIN users u ON u.username=f.username WHERE a.id=?');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
if(!$row){http_response_code(404);exit('Action record not found.');}
try{$db->assertSubjectScope((int)$row['sub_id']);}catch(DomainException $e){\College\RequestSecurity::deny($e->getMessage());}
$stmt=$conn->prepare("SELECT h.*,u.name reviewer FROM workflow_review_history h JOIN users u ON u.id=h.actor_user_id WHERE h.entity_type='CQI' AND h.entity_id=? ORDER BY h.id");$stmt->bind_param('i',$id);$stmt->execute();$history=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$snapshot=(new \Services\ReportSnapshotService())->captureSubject('CQI_ATR',(int)$row['sub_id'],['action_id'=>$id],['action'=>$row,'review_history'=>$history]);
$conn->commit();
$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$state=$row['closed_at']?'CLOSED':($row['completed_at']?'COMPLETED':$row['hod_review_status']);
$html='<h2>Action Taken Report</h2><p>JNTUA College of Engineering, Ananthapuramu</p><p>'.$e($row['dept_fullname']).' | '.$e($row['acad_year']).'</p><p>'.$e($row['subcode'].' — '.$row['sub_fullname']).'</p><p>Recorded status: <strong>'.$e($state).'</strong>. This report reproduces recorded evidence and decisions.</p><table border="1" cellpadding="6" style="width:100%;border-collapse:collapse">';
$fields=['CO'=>$row['co_number'],'Faculty'=>$row['faculty_name'],'Recorded target (%)'=>$row['target_benchmark'],'Recorded attained (%)'=>$row['attained_percentage'],'Recorded gap'=>$row['attainment_gap'],'Proposed action'=>$row['action_proposed'],'Review decision'=>$row['hod_review_status'],'Review observations'=>$row['hod_remarks'],'Due date'=>$row['due_date'],'Execution evidence'=>$row['execution_evidence'],'Completed at'=>$row['completed_at'],'Follow-up findings'=>$row['followup_measure'],'Closed at'=>$row['closed_at']];
foreach($fields as $label=>$value){$html.='<tr><td><strong>'.$e($label).'</strong></td><td>'.nl2br($e($value??'Not recorded')).'</td></tr>';}$html.='</table><h3>Decision history</h3>';
foreach($history as $entry){$html.='<p>'.$e($entry['created_at'].' | '.$entry['reviewer'].' | '.$entry['from_status'].' → '.$entry['to_status']).'<br>'.nl2br($e($entry['remarks'])).'</p>';}
$html.='<p>Snapshot #'.(int)$snapshot['id'].' | Generated '.$e($snapshot['cutoff']).'<br>Metrics SHA-256: '.$e($snapshot['metrics_hash']).'<br>Policy SHA-256: '.$e($snapshot['policy_hash']).'</p>';
$temp=sys_get_temp_dir().'/cea-mpdf';if(!is_dir($temp)){mkdir($temp,0700,true);}
$mpdf=new \Mpdf\Mpdf(['tempDir'=>$temp,'format'=>'A4','default_font_size'=>10]);$mpdf->WriteHTML($html);
while(ob_get_level()){ob_end_clean();}$mpdf->Output('Action-Taken-Report-'.$id.'.pdf','I');

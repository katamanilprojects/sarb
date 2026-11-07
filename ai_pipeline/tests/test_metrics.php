<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__) . '/worker/attendance_metrics.php';
require_once dirname(__DIR__) . '/api/risk_service.php';
$count=0;
function expect(bool $condition, string $message): void {
    global $count;
    if (!$condition) { throw new RuntimeException($message); }
    $count++;
}
$student=['category'=>'CONDONABLE','total_conducted'=>100,'total_attended'=>70,'percentage'=>70.0,'shortage_subjects'=>[],'missing_subjects'=>[]];
$report=['thresholds'=>['min_aggregate'=>75.0],'permission_mode'=>'COUNT_AS_ATTENDED','policy_hash'=>str_repeat('a',64),'cutoff'=>'2026-10-24'];
$result=pipelineAttendanceMetrics($student,$report,$student);
expect($result['classes_needed_for_target']===20, '70/100 needs 20 attended periods at 75%');
expect($result['rolling_14d_velocity']===0.0, 'identical recent and overall attendance');
$report['thresholds']['min_aggregate']=80;
expect(pipelineAttendanceMetrics($student,$report,null)['classes_needed_for_target']===50,'dynamic policy target');
$report['thresholds']['min_aggregate']=100;
expect(pipelineAttendanceMetrics($student,$report,null)['classes_needed_for_target']===null,'unreachable 100% target');
$report['thresholds']['min_aggregate']=75;
foreach (['POLICY_REVIEW','DATA_UNAVAILABLE'] as $category) {
    $student['category']=$category;
    $value=pipelineAttendanceMetrics($student,$report,$student);
    expect($value['classes_needed_for_target']===null && $value['rolling_14d_velocity']===null,'uncertain evidence does not imply recovery or trend');
}
$student['category']='DETAINED';
$student['percentage']=90;
$student['total_attended']=90;
$student['shortage_subjects']=[4];
$value=pipelineAttendanceMetrics($student,$report,null);
expect($value['category']==='DETAINED' && $value['classes_needed_for_target']===0,'subject shortage retained despite aggregate target');
expect(strpos(Services\RiskService::getRunwayMessage($value),'Subject requirements')!==false,'runway retains subject requirement caveat');
expect(Services\RiskService::renderRiskBadge('<script>')==='', 'unknown badge input is not rendered');
expect(strpos(Services\RiskService::renderRiskBadge('DETAINED'),'provisional')!==false,'no official decision claimed');
expect(Services\RiskService::getRunwayMessage(null)==='', 'missing snapshot has no claim');
$student['total_attended']=101;
$rejected=false;
try { pipelineAttendanceMetrics($student,$report,null); } catch (DomainException $e) { $rejected=true; }
expect($rejected,'invalid counts rejected');
echo "PASS: $count offline attendance/helper checks. No database or external application files accessed.\n";

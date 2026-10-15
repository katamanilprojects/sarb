<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/services/FeatureManager.php';
require_once __DIR__.'/services/AcademicReviewService.php';
$role=$_SESSION['role']??'';
if(!in_array($role,['faculty','hod'],true)){\College\RequestSecurity::deny('Faculty or HOD access required.');}
$service=new \Services\AcademicReviewService();$message='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $module=($_POST['type']??'')==='COURSE'?'MOD_LESSON_PLAN':'MOD_CQI_ACTION_TRACKING';
    \FeatureManager::requireWriteAccess($module);
    try{$service->decide($_POST['type']??'',(int)($_POST['id']??0),$_POST['decision']??'',$_POST['remarks']??'',$_POST['due_date']??null,$_POST['followup']??null);$message='Review saved.';}catch(Throwable $e){$message=$e->getMessage();}
}
$queue=$service->queue();$page_title='Course review and action tracking';
require_once __DIR__.($role==='hod'?'/hodheader.php':'/facheader.php');
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="container my-4"><h3>Course review and action tracking</h3><p>Review submissions, record completed actions, and follow up before closure.</p>
<?php if($message):?><div class="alert alert-info"><?= $e($message) ?></div><?php endif;?>
<?php foreach($queue as $type=>$items): $module=$type==='COURSE'?'MOD_LESSON_PLAN':'MOD_CQI_ACTION_TRACKING';if(!\FeatureManager::canRoleAccess($module,$role)){continue;} ?>
<h4><?= $type==='COURSE'?'Course delivery submissions':'Quality improvement actions' ?></h4>
<?php if(!$items):?><p>No records.</p><?php endif;?>
<?php foreach($items as $item):$state=$type==='COURSE'?$item['hod_approval_status']:($item['closed_at']?'CLOSED':($item['completed_at']?'COMPLETED':$item['hod_review_status'])); ?>
<div class="card mb-3"><div class="card-body"><strong><?= $e($item['subcode'].' — '.$item['sub_fullname']) ?></strong><p>Status: <?= $e($state) ?></p>
<?php if($type==='CQI'):?><p><a class="btn btn-outline-secondary btn-sm" href="download_atr.php?id=<?= (int)$item['id'] ?>">Download Action Taken Report</a></p><p><?= nl2br($e($item['action_proposed'])) ?></p><p>Due: <?= $e($item['due_date']??'Not set') ?></p><p>Execution evidence: <?= nl2br($e($item['execution_evidence']??'Not recorded')) ?></p><p>Follow-up findings: <?= nl2br($e($item['followup_measure']??'Not recorded')) ?></p><?php else:?><p>Recorded completion: <?= $e($item['syllabus_completion_pct']) ?>%</p><?php endif;?>
<p>Review observations: <?= nl2br($e($item[$type==='COURSE'?'hod_remarks':'hod_remarks']??'Not reviewed')) ?></p>
<?php $choices=[];if($role==='hod' && $state==='PENDING' && ($type!=='COURSE'||$item['faculty_signoff_status']==='SUBMITTED')){$choices=$type==='COURSE'?['APPROVED','REJECTED']:['APPROVED','REVISION_REQUESTED'];}elseif($role==='faculty'&&$type==='CQI'&&$state==='REVISION_REQUESTED'&&(int)$item['faculty_id']===(int)($_SESSION['facid']??0)){$choices=['REVISED'];}elseif($role==='faculty'&&$type==='CQI'&&$state==='APPROVED'&&(int)$item['faculty_id']===(int)($_SESSION['facid']??0)){$choices=['COMPLETED'];}elseif($role==='hod'&&$type==='CQI'&&$state==='COMPLETED'){$choices=['CLOSED'];} ?>
<?php if($choices && \FeatureManager::canRoleWrite($module,$role)):?><form method="post"><input type="hidden" name="type" value="<?= $e($type) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><label>Decision <select name="decision" class="form-select"><?php foreach($choices as $choice):?><option><?= $e($choice) ?></option><?php endforeach;?></select></label><label class="d-block">Observations / execution evidence<textarea name="remarks" class="form-control" required></textarea></label><?php if($type==='CQI'&&$state==='PENDING'):?><label>Due date <input type="date" name="due_date" class="form-control"></label><?php endif;?><?php if($state==='COMPLETED'):?><label class="d-block">Follow-up findings<textarea name="followup" class="form-control" required></textarea></label><?php endif;?><button class="btn btn-primary mt-2">Save decision</button></form><?php endif;?></div></div>
<?php endforeach;endforeach;?></div>
<?php require_once __DIR__.($role==='hod'?'/hodfooter.php':'/facfooter.php'); ?>

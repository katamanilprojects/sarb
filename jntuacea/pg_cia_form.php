<?php
// Shared controller for the faculty PG entry, correction and view pages.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');
require_once __DIR__.'/dbcredentials.class.php';
require_once __DIR__.'/core/PgCiaService.php';
$service=new \College\PgCiaService((new DBCredentials())->getConnection(),$_SESSION);
$mode=$pgCiaMode??'view';$page_title='PG CIA marks';
$input=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?$_POST:$_GET;
$subject=filter_var($input['sub_id']??null,FILTER_VALIDATE_INT);$number=filter_var($input['assessment_number']??null,FILTER_VALIDATE_INT);
if(!$subject||!$number){\College\RequestSecurity::deny('Select a PG subject and assessment.',400);}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    try{
        if($mode==='view'){throw new DomainException('This page only displays marks.');}
        if(!\College\RequestSecurity::validToken($_POST,$_SESSION,$_SERVER)){throw new DomainException('Refresh the form before submitting.');}
        $action=$mode==='edit'?'edit':($_POST['submit_action']??'');
        $service->save((int)$subject,(int)$number,(array)($_POST['student_id']??[]),(array)($_POST['marks']??[]),$action,(string)($_POST['version']??''),trim($_POST['correction_reason']??''));
        $_SESSION['succ']=$action==='save'?'PG marks draft saved.':'PG marks saved with an audit record.';
    }catch(Throwable $e){$_SESSION['err']=$e->getMessage();}
    header('Location: facciamarks.php?sub_id='.(int)$subject);exit;
}
try{$grid=$service->grid((int)$subject,(int)$number);}catch(Throwable $e){\College\RequestSecurity::deny($e->getMessage(),422);}
$component=$grid['components'][$number];$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$readOnly=!\FeatureManager::canRoleWrite('MOD_CIA_MARKS');
require __DIR__.'/facheader.php';
?>
<div class="container my-4"><div class="card">
<div class="card-header"><strong><?= $e($component['label']) ?></strong> — <?= $e($grid['course']['sub_fullname']) ?> (<?= $e($grid['course']['subcode']) ?>)</div>
<div class="card-body">
<?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS') ?>
<?php if(!$grid['configured']): ?><p class="alert alert-warning">Marks can be entered separately. PG totals require an approved CIA rule for this regulation and subject type before export.</p><?php endif; ?>
<?php if($mode!=='view'): ?><form method="post">
<input type="hidden" name="sub_id" value="<?= (int)$subject ?>"><input type="hidden" name="assessment_number" value="<?= (int)$number ?>"><input type="hidden" name="version" value="<?= $e($grid['version']) ?>"><input type="hidden" name="_csrf" value="<?= $e(\College\RequestSecurity::token()) ?>">
<?php endif; ?>
<table class="table table-bordered"><thead><tr><th>Admission number / Student name</th><th>Marks (maximum <?= $e($component['maximum']) ?>)</th></tr></thead><tbody>
<?php foreach($grid['students'] as $person): $sid=(int)$person['id'];$value=$mode==='add'?($grid['draft'][$sid]??$grid['final'][$sid]??''):($grid['final'][$sid]??''); ?>
<tr><td><?= $e($person['username']) ?><br><?= $e($person['name']) ?></td><td>
<?php if($mode==='view'): ?><?= $value===''?'—':$e($value) ?>
<?php else: ?><input type="hidden" name="student_id[]" value="<?= $sid ?>"><input type="number" name="marks[]" class="form-control" min="0" max="<?= $e($component['maximum']) ?>" step="0.01" value="<?= $e($value) ?>" <?= $readOnly?'readonly':'' ?>><?php endif; ?>
</td></tr><?php endforeach; ?></tbody></table>
<?php if($mode==='edit'): ?><label class="form-label">Reason for correction</label><textarea name="correction_reason" class="form-control mb-3" required <?= $readOnly?'readonly':'' ?>></textarea><?php endif; ?>
<?php if($mode!=='view'): ?>
<button class="btn btn-primary" name="submit_action" value="submit" <?= $readOnly?'disabled':'' ?>><?= $mode==='edit'?'Validate & update marks':'Validate & submit marks' ?></button>
<?php if($mode==='add'): ?><button class="btn btn-secondary" name="submit_action" value="save" <?= $readOnly?'disabled':'' ?>>Save draft</button><?php endif; ?>
</form><p class="text-muted mt-3">Enter zero for an assessed score of zero. Leave an unassessed score blank in a draft. Submitting requires marks for every enrolled student.</p>
<?php elseif(!$readOnly): ?><a class="btn btn-warning" href="faceditpgciamarks.php?sub_id=<?= (int)$subject ?>&assessment_number=<?= (int)$number ?>">Correct marks</a><?php endif; ?>
<a class="btn btn-outline-secondary" href="facciamarks.php?sub_id=<?= (int)$subject ?>">Back</a>
</div></div></div>
<?php require __DIR__.'/facfooter.php'; ?>

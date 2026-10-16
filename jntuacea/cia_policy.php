<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');
require_once __DIR__.'/core/CiaPolicySettings.php';
require_once __DIR__.'/core/PgCiaService.php';
$editor=new \College\CiaPolicySettings(DBCredentials::getInstance()->getConnection(),$_SESSION);
$reg=(int)($_GET['reg_id']??$_POST['reg_id']??0);$type=trim($_GET['type']??$_POST['type']??'');$error='';$success='';
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
        $numbers=[];$maxima=[];foreach((array)($_POST['required']??[]) as $number){if(!ctype_digit((string)$number)){throw new DomainException('Invalid assessment.');}$numbers[]=(int)$number;$maxima[(int)$number]=\College\MarksNormalizer::number($_POST['maximum'][$number]??null,'Component maximum');}
        sort($numbers);$scheme=['kind'=>$_POST['kind']??'','required_assessments'=>$numbers,'component_maxima'=>$maxima,'rounding'=>$_POST['rounding']??''];
        if(in_array($scheme['kind'],['UG_THEORY','PG_THEORY'],true)){$scheme['better_weight']=\College\MarksNormalizer::number($_POST['better']??null,'Better mid weight')/100;$scheme['lesser_weight']=\College\MarksNormalizer::number($_POST['lesser']??null,'Other mid weight')/100;}
        $editor->save($reg,$type,$scheme,(string)($_POST['version']??''),trim($_POST['reason']??''));$success='CIA rule saved with its authority and previous values.';
    }
}catch(Throwable $e){$error=$e->getMessage();}
try{$state=$editor->read($reg,$type);}catch(Throwable $e){\College\RequestSecurity::deny($e->getMessage(),422);}
require_once __DIR__.'/core/VerifiedRegulations.php';
$verified=\College\VerifiedRegulations::definition($state['regulation']);
$rule=$state['scheme']??[];
if(isset($_GET['use_verified']) && isset($verified['schemes'][$state['type']])){$rule=$verified['schemes'][$state['type']];}
$isPg=$state['regulation']['program_level']==='PG';$readonly=!\FeatureManager::canRoleWrite('MOD_CIA_MARKS');$page_title='CIA rule';$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
require __DIR__.'/header.php';
?>
<div class="container my-4 card p-4"><h4>CIA rule: <?= $e($state['regulation']['prog_shortname'].' / '.$state['regulation']['regulation'].' / '.$type) ?></h4>
<p>Enter the approved regulation rule and its authority. Theory combines the two mid assessments using the better/other weights and adds the remaining selected components. Lab and project rules sum the selected assessments. Each score remains a separate record.</p>
<?php if($error): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif; ?><?php if($success): ?><div class="alert alert-success"><?= $e($success) ?></div><?php endif; ?>
<?php if(isset($verified['schemes'][$state['type']])): ?><p>Document formula: <?= $e($verified['source'].' / section '.$verified['section']) ?>. <a href="?reg_id=<?= $reg ?>&amp;type=<?= urlencode($type) ?>&amp;use_verified=1">Load verified formula for review</a>. Saving still requires your authority and valid course maxima.</p><?php endif; ?>
<form method="post"><input type="hidden" name="reg_id" value="<?= $reg ?>"><input type="hidden" name="type" value="<?= $e($type) ?>"><input type="hidden" name="version" value="<?= $e($state['version']) ?>"><input type="hidden" name="_csrf" value="<?= $e(\College\RequestSecurity::token()) ?>">
<label class="form-label">Assessment type</label><select name="kind" class="form-select mb-3" required><option value="">Select an approved type</option><?php foreach($isPg?['PG_THEORY'=>'PG theory','PG_LAB'=>'PG lab']:['UG_THEORY'=>'UG theory','UG_LAB'=>'UG lab','UG_PROJECT'=>'UG project'] as $kind=>$label): ?><option value="<?= $kind ?>" <?= ($rule['kind']??'')===$kind?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select>
<table class="table"><thead><tr><th>Required assessment</th><th>Maximum marks</th></tr></thead><tbody><?php foreach($isPg?[1,2,3,4,11,12]:[1,2] as $n): $label=$isPg?\College\PgCiaService::label($n):'Assessment '.$n; ?><tr><td><label><input type="checkbox" name="required[]" value="<?= $n ?>" <?= in_array($n,$rule['required_assessments']??[],true)?'checked':'' ?>> <?= $e($label) ?></label></td><td><input class="form-control" type="number" name="maximum[<?= $n ?>]" step="0.01" min="0" max="99.99" value="<?= $e($rule['component_maxima'][$n]??'') ?>"></td></tr><?php endforeach; ?></tbody></table>
<div class="row"><div class="col"><label>Better mid weight (%)</label><input type="number" class="form-control" name="better" min="0" max="100" step="0.01" value="<?= isset($rule['better_weight'])?$e($rule['better_weight']*100):'' ?>"></div><div class="col"><label>Other mid weight (%)</label><input type="number" class="form-control" name="lesser" min="0" max="100" step="0.01" value="<?= isset($rule['lesser_weight'])?$e($rule['lesser_weight']*100):'' ?>"></div></div>
<label class="form-label mt-3">Rounding</label><select class="form-select" name="rounding" required><option value="">Select rounding rule</option><?php foreach(['NEAREST_INTEGER'=>'Nearest whole mark','TWO_DECIMALS'=>'Two decimal places','CEILING'=>'Round upward','FLOOR'=>'Round downward'] as $r=>$label): ?><option value="<?= $r ?>" <?= ($rule['rounding']??'')===$r?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select>
<label class="form-label mt-3">Authority / reason for this rule</label><textarea class="form-control mb-3" name="reason" required></textarea><button class="btn btn-primary" <?= $readonly?'disabled':'' ?>>Save approved rule</button> <a href="academicsectionresults.php?tab=readiness" class="btn btn-outline-secondary">Back to readiness</a></form></div>
<?php require __DIR__.'/footer.php'; ?>

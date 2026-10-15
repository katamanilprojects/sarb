<?php
require_once __DIR__ . '/bootstrap.php';

// facseemarkscondensed.php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_SEE_MARKS');

$page_title = "SEE Condensed Marks Review";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("seeassessment.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$seeObj = new SEEAssessment();

if (empty($_GET['sub_id']) || empty($_GET['component_id'])) {
    header("Location: facseemarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$component_id = intval($_GET['component_id']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
require_once __DIR__.'/core/AssessmentPolicy.php';
$db=$facultyObj->getConnection();
$policy=new \College\AssessmentPolicy($db);try{$policy->questions($sub_id,$component_id);}catch(DomainException $ex){\College\RequestSecurity::deny($ex->getMessage());}
$stmt=$db->prepare("SELECT ac.component_type,ia.assessment_number FROM assessment_components ac JOIN internal_assessments ia ON ia.id=ac.assessment_id WHERE ac.id=? AND ac.component_type LIKE 'SEE%'");$stmt->bind_param('i',$component_id);$stmt->execute();$type=$stmt->get_result()->fetch_row();
if(!$type){\College\RequestSecurity::deny('Choose an external assessment component.');}
try{$consolidated=$seeObj->consolidateSEEMarks($sub_id,$type[0],$type[1]);}catch(DomainException|InvalidArgumentException $ex){$consolidated=[];$_SESSION['err']=$ex->getMessage();}
$isSubmitted=$seeObj->isSEEMarksSubmitted($sub_id);
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commit_see_marks'])) {
    if (!empty($_POST['students']) && is_array($_POST['students'])) {
        \FeatureManager::requireWriteAccess('MOD_SEE_MARKS');
        $res = $seeObj->saveConsolidatedSEEMarks($sub_id, array_values($consolidated), $_SESSION['facid'], 'DETAILED');
        if ($res['status'] == 1) {
            $_SESSION['succ'] = "Semester End Examination marks finalized and committed successfully.";
            header("Location: facseemarks.php?sub_id=$sub_id");
            exit;
        } else {
            $_SESSION['err'] = "Failed to commit marks: " . $res['err'];
        }
    } else {
        $_SESSION['err'] = "No student marks data received to commit.";
    }
}

require_once("facheader.php");
?>
<div class="container-fluid my-4"><h4>Review external marks</h4><p>Course: <?= $e($subjectDetails['data']['sub_fullname']??'') ?>. <?= $isSubmitted?'Previously finalized':'Draft / pending' ?></p>
<?php if(!empty($_SESSION['err'])):?><div class="alert alert-danger"><?= $e($_SESSION['err']);unset($_SESSION['err']); ?></div><?php endif ?>
<p class="small">Choice options are selected once from the whole option score, then used for every CO. Existing preset caps are retained. Gross marks are scaled to the configured final maximum. Missing assessed marks must be completed before finalization.</p>
<form method="post"><div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Roll number</th><th>Student</th><th>Q1 subtotal</th><th>Choice decisions</th><th>Choice total</th><th>Gross</th><th>Final external</th><th>Evidence</th></tr></thead><tbody>
<?php foreach($consolidated as $id=>$r):?><tr><td><?= $e($r['username']) ?><input type="hidden" name="students[<?= (int)$id ?>][reviewed]" value="1"></td><td><?= $e($r['name']) ?></td><td><?= $e($r['q1_marks']) ?></td><td><?php foreach($r['groups'] as $g):?><div><strong><?= $e(implode('+',$g['labels'])) ?>: <?= $e($g['score']) ?></strong><small class="d-block text-muted"><?php foreach($g['options'] as $o):?><?= $e(implode('+',$o['labels']).': '.$o['score']) ?>; <?php endforeach ?></small></div><?php endforeach ?></td><td><?= $e($r['choice_marks']) ?></td><td><?= $e(round($r['gross_marks'],5).' / '.$r['gross_max']) ?></td><td><?= $e(round($r['final_external']??0,5).' / '.$r['max_marks']) ?></td><td><?= $r['complete']?'Complete':'Missing marks' ?></td></tr><?php endforeach ?>
</tbody></table></div><a class="btn btn-secondary" href="facseemarksentry.php?sub_id=<?= $sub_id ?>&amp;component_id=<?= $component_id ?>">Edit question marks</a><a class="btn btn-outline-secondary" href="facseemarks.php?sub_id=<?= $sub_id ?>">Back</a><?php if($consolidated):?><button class="btn btn-success" name="commit_see_marks">Finalize reviewed marks</button><?php endif ?></form></div>
<?php require_once("facfooter.php"); ?>

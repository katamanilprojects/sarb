<?php
require_once __DIR__.'/core/PgCiaService.php';
$pgService=new \College\PgCiaService($ciaObj->getConnection(),$_SESSION);
try{
    $pgComponents=$pgService->components((int)$selected_sub_id);
    if(!$pgComponents['configured']){echo '<p class="alert alert-warning">PG totals await an approved CIA scheme. Enter each component separately.</p>';}
    echo '<table class="table table-bordered"><thead><tr><th>Assessment</th><th>Marks submitted</th><th>Actions</th></tr></thead><tbody>';
    foreach($pgComponents['components'] as $n=>$component){
        $grid=$pgService->grid((int)$selected_sub_id,$n);$count=0;
        foreach($grid['students'] as $person){if(array_key_exists($person['id'],$grid['final'])){$count++;}}
        $page=$grid['final']?'facviewpgciamarks.php':'facaddpgciamarks.php';
        echo '<tr><td>'.htmlspecialchars($component['label'],ENT_QUOTES,'UTF-8').' (max '.$component['maximum'].')</td><td>'.$count.' / '.count($grid['students']).'</td><td><a class="btn btn-info btn-sm" href="'.$page.'?sub_id='.(int)$selected_sub_id.'&assessment_number='.$n.'">'.($grid['final']?'View / correct':'Add').'</a></td></tr>';
    }
    echo '</tbody></table>';
}catch(Throwable $e){echo '<p class="alert alert-warning">'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p>';}

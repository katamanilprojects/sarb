<?php
require_once __DIR__.'/../core/ResultsReadiness.php';
$readiness=(new \College\ResultsReadiness($examService->getConnection(),$_SESSION))->report();
?>
<div class="card p-3"><h5>Course policy readiness</h5><p>Current offerings on <?= htmlspecialchars($readiness['date']) ?>: <?= $readiness['total'] ?>. CIA rules ready: <?= $readiness['cia_ready'] ?>. Result policies ready: <?= $readiness['results_ready'] ?>. This checks configuration; marks and result files still need validation.</p>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Program / Regulation</th><th>Course type</th><th>CIA ready</th><th>Results ready</th><th>Required action</th></tr></thead><tbody>
<?php foreach($readiness['groups'] as $group): ?><tr><td><?= htmlspecialchars($group['program'].' / '.$group['regulation'].' ('.$group['level'].')') ?></td><td><?= htmlspecialchars($group['type']) ?></td><td><?= $group['cia_ready'].' / '.$group['total'] ?></td><td><?= $group['results_ready'].' / '.$group['total'] ?></td><td>
<?php foreach($group['issues'] as $issue=>$count): ?><div><?= htmlspecialchars($issue) ?> (<?= (int)$count ?> courses)</div><?php endforeach; ?>
<a href="cia_policy.php?reg_id=<?= (int)$group['reg_id'] ?>&amp;type=<?= urlencode($group['type']) ?>">Configure CIA rule</a>
</td></tr><?php endforeach; ?></tbody></table></div>
<details><summary>Courses requiring attention</summary><table class="table"><thead><tr><th>Class / Course</th><th>Required action</th></tr></thead><tbody><?php foreach($readiness['courses'] as $course): if(!$course['issues']){continue;} ?><tr><td><?= htmlspecialchars($course['classname'].' / '.$course['subcode'].' — '.$course['sub_fullname']) ?></td><td><?= htmlspecialchars(implode(' ',$course['issues'])) ?></td></tr><?php endforeach; ?></tbody></table></details></div>

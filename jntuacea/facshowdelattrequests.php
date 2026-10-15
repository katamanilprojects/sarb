<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATT_REQUESTS');

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();
$faculty_id = $_SESSION['facid'];
$requests = $facultyObj->getSubmittedRequests($faculty_id);

?>

<div class="container">
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Submitted Attendance Delete Requests</div>
                <div class="card-body">
                    <?php if (!empty($requests['data'])): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Hour</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Request Date</th>
                                        <th>Approval Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests['data'] as $request): ?>
                                        <?php $hour_desc = $facultyObj->getHourDescById($request['hour']); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['subject']) ?></td>
                                            <td><?= htmlspecialchars($request['date']) ?></td>
                                            <td><?= $hour_desc ?></td>
                                            <td><?= htmlspecialchars($request['reason']) ?></td>
                                            <td><?= htmlspecialchars($request['status']) ?></td>
                                            <td><?= htmlspecialchars($request['request_date']) ?></td>
                                            <td>
                                                <?= $request['status'] == 'Approved' && $request['approval_date'] ? htmlspecialchars($request['approval_date']) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No attendance delete requests found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>
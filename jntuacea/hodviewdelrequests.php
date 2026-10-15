<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATT_REQUESTS');

$page_title = "Manage Delete Requests";
require_once("hod.class.php");
require_once("hodheader.php");

$hodObj = new HOD();
$dept_id = $_SESSION["dept_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_request'])) {
    \FeatureManager::requireWriteAccess('MOD_ATT_REQUESTS');
    $request_id = $_POST['request_id'];
    $action = $_POST['process_request'];
    $result = $hodObj->processDeleteRequest($request_id, $action);

    if ($result['status'] == 1) {
        $msg = "Request successfully " . ($action == "Approved" ? "approved." : "rejected.");
    } else {
        $error = $result['err'] ?? 'Failed to process the request.';
    }
}

$deleteRequests = $hodObj->getDeleteRequestsByDepartment($dept_id);

$pending = false;
$present = false;

if (!empty($deleteRequests['data'])) {
    foreach ($deleteRequests['data'] as $request) {
        $present = true;
        if ($request['status'] == 'Pending') {
            $pending = true;
            break;
        }
    }
}
?>

<?php
$pendingCount = 0;
$historyCount = 0;
if (!empty($deleteRequests['data'])) {
    foreach ($deleteRequests['data'] as $r) {
        if ($r['status'] === 'Pending') $pendingCount++;
        else $historyCount++;
    }
}
$auditLogs = $hodObj->getAttendanceDeleteAuditLogs($dept_id)['data'] ?? [];
$auditCount = count($auditLogs);
?>

<div class="container my-3">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATT_REQUESTS'); ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 text-dark fw-bold">
                <i class="bi bi-shield-lock-fill text-primary me-2"></i> Attendance Deletion Governance
            </h4>
            <p class="text-muted small mb-0">Single-Tier HOD Authorization backed by Institutional Audit Logging (NAAC Metric 6.2)</p>
        </div>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Nav Tabs -->
    <ul class="nav nav-pills mb-3 shadow-sm bg-white p-2 rounded border" id="delTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pendingView" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Pending Requests 
                <span class="badge bg-danger ms-1"><?= $pendingCount; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="history-tab" data-bs-toggle="pill" data-bs-target="#historyView" type="button" role="tab">
                <i class="bi bi-check2-all me-1"></i> Decision History 
                <span class="badge bg-secondary ms-1"><?= $historyCount; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="audit-tab" data-bs-toggle="pill" data-bs-target="#auditView" type="button" role="tab">
                <i class="bi bi-journal-text me-1"></i> Institutional Audit Trail 
                <span class="badge bg-info text-dark ms-1"><?= $auditCount; ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="delTabsContent">
        <!-- TAB 1: Pending Requests -->
        <div class="tab-pane fade show active" id="pendingView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-hourglass-split text-warning me-2"></i> Pending Deletion Requests
                    </h5>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                        Requires Single-Tier HOD Decision
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if ($pendingCount > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Faculty Member</th>
                                        <th>Subject & Class</th>
                                        <th>Date & Hour</th>
                                        <th>Faculty Justification</th>
                                        <th>Requested On</th>
                                        <th class="text-center" style="min-width: 160px;">Authorization Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deleteRequests['data'] as $request): ?>
                                        <?php if ($request['status'] == 'Pending'): ?>
                                            <?php $hour_desc = $hodObj->getHourDescById($request['hour']); ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($request['faculty_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-primary"><?= htmlspecialchars($request['subject']); ?></span>
                                                </td>
                                                <td>
                                                    <div><i class="bi bi-calendar3 me-1 text-muted"></i><?= htmlspecialchars($request['date']); ?></div>
                                                    <small class="badge bg-light text-dark border"><?= htmlspecialchars($hour_desc); ?></small>
                                                </td>
                                                <td style="max-width: 320px;">
                                                    <div class="p-2 bg-light border-start border-3 border-warning rounded small text-dark">
                                                        <i class="bi bi-quote me-1 text-warning"></i>
                                                        <strong>Justification:</strong> <?= htmlspecialchars($request['reason']); ?>
                                                    </div>
                                                </td>
                                                <td class="small text-muted">
                                                    <?= htmlspecialchars($request['request_date']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <form action="hodviewdelrequests.php" method="post" id="form<?= $request['id'] ?>" class="d-flex gap-1 justify-content-center">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="button" onclick="confirmRequest('Approve', 'form<?= $request['id'] ?>')" class="btn btn-success btn-sm fw-semibold" <?= \FeatureManager::isHodReadOnly('MOD_ATT_REQUESTS') ? 'disabled' : ''; ?>>
                                                            <i class="bi bi-check-lg me-1"></i> Approve
                                                        </button>
                                                        <button type="button" onclick="confirmRequest('Reject', 'form<?= $request['id'] ?>')" class="btn btn-outline-danger btn-sm" <?= \FeatureManager::isHodReadOnly('MOD_ATT_REQUESTS') ? 'disabled' : ''; ?>>
                                                            <i class="bi bi-x-lg me-1"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-1 text-success mb-2 d-block"></i>
                            <p class="mb-0">No pending attendance deletion requests. All faculty submissions are up to date.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: Processed History -->
        <div class="tab-pane fade" id="historyView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history text-secondary me-2"></i> Decision History (Approved / Rejected)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($historyCount > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Faculty Member</th>
                                        <th>Subject</th>
                                        <th>Date & Hour</th>
                                        <th>Faculty Justification</th>
                                        <th>Decision Status</th>
                                        <th>Action Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deleteRequests['data'] as $request): ?>
                                        <?php if ($request['status'] != 'Pending'): ?>
                                            <?php $hour_desc = $hodObj->getHourDescById($request['hour']); ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['faculty_name']); ?></td>
                                                <td><span class="text-primary"><?= htmlspecialchars($request['subject']); ?></span></td>
                                                <td>
                                                    <div><?= htmlspecialchars($request['date']); ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($hour_desc); ?></small>
                                                </td>
                                                <td style="max-width: 320px;">
                                                    <div class="p-2 bg-light border-start border-3 border-secondary rounded small">
                                                        <?= htmlspecialchars($request['reason']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($request['status'] === 'Approved'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                                            <i class="bi bi-check-circle me-1"></i> Approved
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                                            <i class="bi bi-x-circle me-1"></i> Rejected
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-muted"><?= htmlspecialchars($request['approval_date']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <p class="mb-0">No historical delete requests found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: Institutional Audit Trail -->
        <div class="tab-pane fade" id="auditView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-journal-check text-info me-2"></i> Institutional Governance Audit Trail
                        </h5>
                        <p class="text-muted small mb-0">Immutable system activity logs recorded under NAAC Metric 6.2 / Autonomous Institutional Governance</p>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2">
                        Source: `audit_logs`
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($auditLogs)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 140px;">Timestamp</th>
                                        <th>Authorized Actor</th>
                                        <th>Role</th>
                                        <th>Governance Action</th>
                                        <th>Entity ID</th>
                                        <th>Audit Narrative / Justification</th>
                                        <th>Client IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td class="small text-muted">
                                                <i class="bi bi-clock me-1"></i><?= htmlspecialchars($log['created_at']); ?>
                                            </td>
                                            <td class="fw-semibold">
                                                <?= htmlspecialchars($log['actor_name'] ?: $log['username'] ?: 'User #' . $log['user_id']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($log['role'])); ?></span>
                                            </td>
                                            <td>
                                                <?php if (strpos($log['action'], 'APPROVE') !== false): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($log['action']); ?></span>
                                                <?php elseif (strpos($log['action'], 'REJECT') !== false): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> <?= htmlspecialchars($log['action']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-trash me-1"></i> <?= htmlspecialchars($log['action']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code>#<?= htmlspecialchars($log['entity_id'] ?: 'N/A'); ?></code></td>
                                            <td style="max-width: 350px;">
                                                <div class="small text-muted"><?= htmlspecialchars($log['details']); ?></div>
                                            </td>
                                            <td class="small text-monospace"><code><?= htmlspecialchars($log['ip_address']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 text-muted mb-2 d-block"></i>
                            <p class="mb-0">No audit log entries recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmRequest(action, formId) {
        if (confirm(`Are you sure you want to ${action} the request?`)) {
            // Add a hidden input field to the form with the request ID and action
            const form = document.getElementById(formId);

            const hiddenActionInput = document.createElement('input');
            hiddenActionInput.type = 'hidden';
            hiddenActionInput.name = 'process_request';
            if (action == "Approve") {
                hiddenActionInput.value = 'Approved';
            }
            if (action == "Reject") {
                hiddenActionInput.value = 'Rejected';
            }
            form.appendChild(hiddenActionInput);

            form.submit();
        }
    }
</script>
<?php
require_once("hodfooter.php");
?>
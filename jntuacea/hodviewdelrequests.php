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
        $msg = "Request successfully " . ($action == "Approved" ? "approved and attendance records purged." : "rejected.");
    } else {
        $error = $result['err'] ?? 'Failed to process the request.';
    }
}

$deleteRequests = $hodObj->getDeleteRequestsByDepartment($dept_id);

$pendingCount = 0;
$historyCount = 0;
if (!empty($deleteRequests['data'])) {
    foreach ($deleteRequests['data'] as $r) {
        if ($r['status'] === 'Pending') {
            $pendingCount++;
        } else {
            $historyCount++;
        }
    }
}
$auditLogs = $hodObj->getAttendanceDeleteAuditLogs($dept_id)['data'] ?? [];
$auditCount = count($auditLogs);
?>

<style>
    .del-tab-btn {
        transition: all 0.2s ease-in-out;
        font-size: 0.95rem;
    }
    .del-table th {
        font-size: 0.82rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #555;
        font-weight: 700;
        vertical-align: middle;
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .del-table td {
        vertical-align: middle;
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .quote-box {
        background-color: #f8f9fa;
        border-left: 3px solid #ffc107;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 0.875rem;
        color: #495057;
        line-height: 1.45;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.025);
    }
</style>

<div class="container my-3">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_ATT_REQUESTS'); ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-dark fw-bold">
                <i class="bi bi-shield-lock-fill text-primary me-2"></i> Attendance Deletion Governance
            </h4>
            <p class="text-muted small mb-0">Authorized HOD review gate with institutional audit snapshots (NAAC Metric 6.2)</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-secondary border px-3 py-2">
                <i class="bi bi-diagram-3 me-1"></i> Department ID: <?= htmlspecialchars($dept_id); ?>
            </span>
        </div>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Nav Tabs -->
    <ul class="nav nav-pills mb-3 shadow-sm bg-white p-2 rounded border" id="delTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold del-tab-btn" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pendingView" type="button" role="tab">
                <i class="bi bi-hourglass-split me-1"></i> Pending Requests 
                <span class="badge bg-danger ms-1"><?= $pendingCount; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold del-tab-btn" id="history-tab" data-bs-toggle="pill" data-bs-target="#historyView" type="button" role="tab">
                <i class="bi bi-check2-all me-1"></i> Decision History 
                <span class="badge bg-secondary ms-1"><?= $historyCount; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold del-tab-btn" id="audit-tab" data-bs-toggle="pill" data-bs-target="#auditView" type="button" role="tab">
                <i class="bi bi-journal-text me-1"></i> Institutional Audit Trail 
                <span class="badge bg-info text-dark ms-1"><?= $auditCount; ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="delTabsContent">
        <!-- TAB 1: Pending Requests -->
        <div class="tab-pane fade show active" id="pendingView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-clock-history text-warning me-2"></i> Pending Deletion Requests
                        </h5>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                            Action Required
                        </span>
                    </div>
                    <?php if ($pendingCount > 0): ?>
                        <div class="d-flex align-items-center">
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="pendingSearch" class="form-control border-start-0" placeholder="Filter faculty, class, subject...">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($pendingCount > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover del-table align-middle mb-0" id="pendingTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 190px;">Faculty Member</th>
                                        <th style="min-width: 230px;">Subject & Class</th>
                                        <th style="min-width: 140px;">Period Date & Hour</th>
                                        <th style="min-width: 220px;">Faculty Justification</th>
                                        <th style="min-width: 120px;">Submitted On</th>
                                        <th class="text-center" style="min-width: 170px;">Authorization Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deleteRequests['data'] as $request): ?>
                                        <?php if ($request['status'] == 'Pending'): ?>
                                            <?php 
                                            $hour_desc = !empty($request['hour_desc']) ? $request['hour_desc'] : $hodObj->getHourDescById($request['hour']); 
                                            $faculty_display = !empty($request['faculty_name']) ? $request['faculty_name'] : $request['faculty_username'];
                                            ?>
                                            <tr>
                                                <!-- Faculty Member -->
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($faculty_display); ?></div>
                                                    <div class="small text-muted d-flex align-items-center gap-1 flex-wrap mt-1">
                                                        <span class="font-monospace text-secondary">@<?= htmlspecialchars($request['faculty_username'] ?? ''); ?></span>
                                                        <?php if (!empty($request['faculty_designation'])): ?>
                                                            <span>&bull; <?= htmlspecialchars($request['faculty_designation']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($request['is_cross_department'])): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Parent Department: <?= htmlspecialchars($request['faculty_dept_fullname'] ?? $request['faculty_dept'] ?? ''); ?>">
                                                                <i class="bi bi-arrow-left-right me-1"></i>Dept: <?= htmlspecialchars($request['faculty_dept'] ?? 'External'); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Subject & Class: Line 1 subcode, Line 2 subname, Line 3 classname -->
                                                <td>
                                                    <?php if (!empty($request['subcode'])): ?>
                                                        <div class="mb-1">
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle font-monospace"><?= htmlspecialchars($request['subcode']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="fw-bold text-primary mb-1">
                                                        <?= htmlspecialchars($request['subject']); ?>
                                                    </div>
                                                    <?php if (!empty($request['classname'])): ?>
                                                        <div>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                <i class="bi bi-mortarboard-fill me-1"></i><?= htmlspecialchars($request['classname']); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Date & Hour -->
                                                <td>
                                                    <div class="fw-semibold text-dark text-nowrap">
                                                        <i class="bi bi-calendar-event text-primary me-1"></i><?= htmlspecialchars($request['date']); ?>
                                                    </div>
                                                    <div class="mt-1 text-nowrap">
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="bi bi-clock me-1 text-muted"></i><?= htmlspecialchars($hour_desc ?: ('Hour - ' . $request['hour'])); ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <!-- Justification -->
                                                <td>
                                                    <div class="quote-box" style="max-width: 320px;">
                                                        <i class="bi bi-quote text-warning me-1"></i>
                                                        <?= htmlspecialchars($request['reason']); ?>
                                                    </div>
                                                </td>

                                                <!-- Requested Date -->
                                                <td class="text-nowrap">
                                                    <div class="small fw-semibold text-dark">
                                                        <?= date('d M Y', strtotime($request['request_date'])); ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-clock-history me-1"></i><?= date('h:i A', strtotime($request['request_date'])); ?>
                                                    </div>
                                                </td>

                                                <!-- Action Buttons -->
                                                <td class="text-center">
                                                    <form action="hodviewdelrequests.php" method="post" id="form<?= $request['id'] ?>" class="d-flex gap-2 justify-content-center">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="button" onclick="confirmRequest('Approve', 'form<?= $request['id'] ?>')" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm" <?= \FeatureManager::isHodReadOnly('MOD_ATT_REQUESTS') ? 'disabled' : ''; ?>>
                                                            <i class="bi bi-check-lg me-1"></i> Approve
                                                        </button>
                                                        <button type="button" onclick="confirmRequest('Reject', 'form<?= $request['id'] ?>')" class="btn btn-outline-danger btn-sm px-3 fw-semibold" <?= \FeatureManager::isHodReadOnly('MOD_ATT_REQUESTS') ? 'disabled' : ''; ?>>
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
                            <h6 class="fw-bold text-dark">All Clear!</h6>
                            <p class="mb-0 text-muted">No pending attendance deletion requests. All class logs are up to date.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: Processed History -->
        <div class="tab-pane fade" id="historyView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-clock-history text-secondary me-2"></i> Decision History (Approved / Rejected)
                    </h5>
                    <?php if ($historyCount > 0): ?>
                        <div class="d-flex align-items-center">
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="historySearch" class="form-control border-start-0" placeholder="Filter history...">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($historyCount > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover del-table align-middle mb-0" id="historyTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 190px;">Faculty Member</th>
                                        <th style="min-width: 230px;">Subject & Class</th>
                                        <th style="min-width: 140px;">Period Date & Hour</th>
                                        <th style="min-width: 220px;">Faculty Justification</th>
                                        <th style="min-width: 130px;">Decision Status</th>
                                        <th style="min-width: 120px;">Decided On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deleteRequests['data'] as $request): ?>
                                        <?php if ($request['status'] != 'Pending'): ?>
                                            <?php 
                                            $hour_desc = !empty($request['hour_desc']) ? $request['hour_desc'] : $hodObj->getHourDescById($request['hour']); 
                                            $faculty_display = !empty($request['faculty_name']) ? $request['faculty_name'] : $request['faculty_username'];
                                            ?>
                                            <tr>
                                                <!-- Faculty Member -->
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($faculty_display); ?></div>
                                                    <div class="small text-muted d-flex align-items-center gap-1 flex-wrap mt-1">
                                                        <span class="font-monospace text-secondary">@<?= htmlspecialchars($request['faculty_username'] ?? ''); ?></span>
                                                        <?php if (!empty($request['faculty_designation'])): ?>
                                                            <span>&bull; <?= htmlspecialchars($request['faculty_designation']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($request['is_cross_department'])): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Parent Department: <?= htmlspecialchars($request['faculty_dept_fullname'] ?? $request['faculty_dept'] ?? ''); ?>">
                                                                <i class="bi bi-arrow-left-right me-1"></i>Dept: <?= htmlspecialchars($request['faculty_dept'] ?? 'External'); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Subject & Class: Line 1 subcode, Line 2 subname, Line 3 classname -->
                                                <td>
                                                    <?php if (!empty($request['subcode'])): ?>
                                                        <div class="mb-1">
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle font-monospace"><?= htmlspecialchars($request['subcode']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="fw-bold text-primary mb-1">
                                                        <?= htmlspecialchars($request['subject']); ?>
                                                    </div>
                                                    <?php if (!empty($request['classname'])): ?>
                                                        <div>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                <i class="bi bi-mortarboard-fill me-1"></i><?= htmlspecialchars($request['classname']); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Date & Hour -->
                                                <td>
                                                    <div class="fw-semibold text-dark text-nowrap">
                                                        <i class="bi bi-calendar-event text-primary me-1"></i><?= htmlspecialchars($request['date']); ?>
                                                    </div>
                                                    <div class="mt-1 text-nowrap">
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="bi bi-clock me-1 text-muted"></i><?= htmlspecialchars($hour_desc ?: ('Hour - ' . $request['hour'])); ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <!-- Justification -->
                                                <td>
                                                    <div class="quote-box" style="max-width: 320px;">
                                                        <?= htmlspecialchars($request['reason']); ?>
                                                    </div>
                                                </td>

                                                <!-- Decision Status -->
                                                <td>
                                                    <?php if ($request['status'] === 'Approved'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold">
                                                            <i class="bi bi-check-circle-fill me-1"></i> Approved
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fw-semibold">
                                                            <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Decision Timestamp -->
                                                <td class="text-nowrap">
                                                    <?php if (!empty($request['approval_date'])): ?>
                                                        <div class="small fw-semibold text-dark">
                                                            <?= date('d M Y', strtotime($request['approval_date'])); ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-clock-history me-1"></i><?= date('h:i A', strtotime($request['approval_date'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 text-muted mb-2 d-block"></i>
                            <p class="mb-0">No historical delete requests found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: Institutional Audit Trail -->
        <div class="tab-pane fade" id="auditView" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-journal-check text-info me-2"></i> Institutional Governance Audit Trail
                        </h5>
                        <p class="text-muted small mb-0">Immutable system activity logs recorded under NAAC Metric 6.2 / Autonomous Governance</p>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2">
                        Source: `audit_logs`
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($auditLogs)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover del-table align-middle mb-0" style="font-size: 0.88rem;">
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
                                            <td class="small text-muted text-nowrap">
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
        const verb = action === 'Approve' ? 'approve and purge the attendance records for' : 'reject';
        if (confirm(`Are you sure you want to ${verb} this request?`)) {
            const form = document.getElementById(formId);
            const hiddenActionInput = document.createElement('input');
            hiddenActionInput.type = 'hidden';
            hiddenActionInput.name = 'process_request';
            hiddenActionInput.value = action === "Approve" ? 'Approved' : 'Rejected';
            form.appendChild(hiddenActionInput);
            form.submit();
        }
    }

    // Client-side instant filter for Pending Table
    document.getElementById('pendingSearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#pendingTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });

    // Client-side instant filter for History Table
    document.getElementById('historySearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#historyTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
</script>

<?php
require_once("hodfooter.php");
?>

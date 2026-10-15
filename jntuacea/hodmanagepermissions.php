<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once("hod.class.php");
$hodObj = new Hod();

// ─── Permission Types ─────────────────────────────────────────────────────────
$permission_types = ['NCC', 'NSS', 'Hackathon', 'Workshop', 'Other'];

// ─── Page state ───────────────────────────────────────────────────────────────
$page     = isset($_GET['page']) ? $_GET['page'] : 'add';
$msg      = "";
$msg_type = "success";

$specializations   = $hodObj->getSpecializationsByDepartment($_SESSION["dept_id"]);
$selected_class_id = null;
$preview_data      = [];

// ─── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {

        // ── DELETE single permission (don't unset secretcode for bulk ops) ──
        if (!empty($_POST['action']) && $_POST['action'] == 'delete') {
            $perm_id = intval($_POST['perm_id']);
            $result  = $hodObj->deletePermission($perm_id, $_SESSION['userid']);
            echo json_encode($result['status'] == 1 ? ['status' => 1] : ['status' => 0, 'error' => $result['err'] ?? 'Delete failed']);
            exit;
        }

        unset($_SESSION['secretcode']);

        // ── PREVIEW ──────────────────────────────────────────────────────────
        if (!empty($_POST['action']) && $_POST['action'] == 'preview') {
            $selected_class_id = intval($_POST['class_id']);
            $permission_type   = $_POST['permission_type'];
            $reason            = trim($_POST['reason']);
            $roll_input        = trim($_POST['roll_numbers_raw'] ?? '');

            $roll_numbers = array_filter(array_map('strtoupper', preg_split('/[\s,]+/', $roll_input)));
            $roll_numbers = array_values(array_unique($roll_numbers));

            $dates_submitted = isset($_POST['dates']) ? $_POST['dates'] : [];
            $dates_submitted = array_filter(array_unique($dates_submitted));

            if (!empty($roll_numbers) && !empty($dates_submitted)) {
                $class_info = $hodObj->getClassById($selected_class_id);
                if (empty($class_info)) {
                    $msg = "Invalid class selected.";
                    $msg_type = "danger";
                } else {
                    $invalid_dates = [];
                    foreach ($dates_submitted as $date) {
                        if ($date < $class_info['start_date'] || $date > $class_info['end_date']) {
                            $invalid_dates[] = date('d-M-Y', strtotime($date));
                        }
                    }
                    if (!empty($invalid_dates)) {
                        $msg = "<strong>Invalid dates!</strong> The following dates are outside the class period (" . date('d-M-Y', strtotime($class_info['start_date'])) . " to " . date('d-M-Y', strtotime($class_info['end_date'])) . "):<br>" . implode(', ', $invalid_dates);
                        $msg_type = "danger";
                    } else {
                        $students   = $hodObj->getStudentsByRollNumbers($selected_class_id, $roll_numbers);
                        $timings    = $hodObj->getClassTimingsByClassId($selected_class_id);
                        $timing_map = [];
                        foreach ($timings as $t) {
                            $timing_map[$t['id']] = $t;
                        }

                        $not_found = array_diff(
                            $roll_numbers,
                            array_column($students, 'roll_number')
                        );

                        if (!empty($not_found)) {
                            $msg = "<strong>Invalid roll numbers!</strong> The following roll numbers do not belong to the selected class:<br>" . implode(', ', $not_found);
                            $msg_type = "danger";
                        } else {
                            foreach ($students as $student) {
                    foreach ($dates_submitted as $date) {
                        $date = trim($date);
                        if (empty($date)) continue;
                        $field_key      = 'hours_' . str_replace('-', '_', $date);
                        $hours_for_date = isset($_POST[$field_key]) ? $_POST[$field_key] : [];
                        if (empty($hours_for_date)) continue;
                        foreach ($hours_for_date as $hour_id) {
                            if (isset($timing_map[$hour_id])) {
                                $preview_data[] = [
                                    'student_name'    => $student['name'],
                                    'roll_number'     => $student['roll_number'],
                                    'stu_id'          => $student['id'],
                                    'date'            => $date,
                                    'hour_id'         => $hour_id,
                                    'hour_desc'       => $timing_map[$hour_id]['hour_desc'],
                                    'permission_type' => $permission_type,
                                    'reason'          => $reason,
                                ];
                            }
                        }
                    }
                            }

                            if (empty($preview_data)) {
                                $msg      = "No valid combinations found. Check roll numbers and hours.";
                                $msg_type = "danger";
                            } else {
                                $stu_ids = array_unique(array_column($preview_data, 'stu_id'));
                                $dates = array_unique(array_column($preview_data, 'date'));
                                $check_existing = $hodObj->checkExistingPermissions($stu_ids, $dates);
                                if (!empty($check_existing)) {
                                    $dup_details = [];
                                    foreach ($check_existing as $dup) {
                                        $dup_details[] = $dup['roll_number'] . ' on ' . date('d-M-Y', strtotime($dup['date'])) . ' (' . $dup['hour_desc'] . ')';
                                    }
                                    $msg = "<strong>Duplicate permissions found!</strong> The following already exist:<br>" . implode('<br>', array_slice($dup_details, 0, 10));
                                    if (count($dup_details) > 10) $msg .= '<br>... and ' . (count($dup_details) - 10) . ' more.';
                                    $msg_type = "danger";
                                    $preview_data = [];
                                }
                            }
                        }
                    }
                }
            } else {
                $msg      = "Please enter roll numbers and select at least one date with hours.";
                $msg_type = "danger";
            }

            // ── CONFIRM / SAVE ───────────────────────────────────────────────────
        } elseif (!empty($_POST['action']) && $_POST['action'] == 'confirm') {
            $document_path = null;
            if (!empty($_FILES['document']['name'])) {
                $upload_dir = "uploads/permissions/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $file_ext      = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $document_path = $upload_dir . uniqid() . "." . $file_ext;
                move_uploaded_file($_FILES['document']['tmp_name'], $document_path);
            }
            $permissions_data = json_decode($_POST['preview_json'], true);
            $bulk_data = [];
            foreach ($permissions_data as $perm) {
                $bulk_data[] = [
                    'stu_id'          => $perm['stu_id'],
                    'class_id'        => intval($_POST['class_id']),
                    'date'            => $perm['date'],
                    'hour'            => $perm['hour_id'],
                    'permission_type' => $perm['permission_type'],
                    'reason'          => $perm['reason'],
                    'document_path'   => $document_path,
                ];
            }
            $result = $hodObj->bulkInsertPermissions($bulk_data, $_SESSION['userid']);
            if ($result['status'] == 1) {
                // ── IMPROVED SUCCESS MESSAGE ──────────────────────────────────
                $_sum_students = array_unique(array_column($bulk_data, 'stu_id'));
                $_sum_dates    = array_unique(array_column($bulk_data, 'date'));
                $_sum_s_count  = count($_sum_students);
                $_sum_d_labels = array_map(fn($d) => date('d-M-Y', strtotime($d)), $_sum_dates);
                sort($_sum_d_labels);
                $_sum_date_str = implode(', ', $_sum_d_labels);
                $msg      = "Permission granted successfully for <strong>{$_sum_s_count} student(s)</strong> on <strong>{$_sum_date_str}</strong>.";
                $msg_type = "success";
                $preview_data = [];
                $page = 'manage';
            } else {
                $msg      = "Failed to grant permissions. Please try again.";
                $msg_type = "danger";
            }
        }
    } else {
        $msg      = "Invalid session. Please try again.";
        $msg_type = "danger";
    }
}

// ─── Data for manage page ─────────────────────────────────────────────────────
$existing_permissions = [];
$grouped_existing     = [];
$_vm_spec_id          = null;
$_vm_class_id         = null;
$_vm_month_year       = null;
$_vm_all_specs        = [];
$_vm_all_classes      = [];

if ($page == 'manage') {
    $_vm_spec_id    = isset($_GET['vm_spec_id'])    && $_GET['vm_spec_id']    !== '' ? (int)$_GET['vm_spec_id']    : null;
    $_vm_class_id   = isset($_GET['vm_class_id'])   && $_GET['vm_class_id']   !== '' ? (int)$_GET['vm_class_id']   : null;
    $_vm_month_year = isset($_GET['vm_month_year']) && $_GET['vm_month_year'] !== '' ? trim($_GET['vm_month_year']) : null;

    $_vm_all_specs = $specializations;

    if ($_vm_spec_id) {
        $_vm_all_classes = $hodObj->getActiveClassesBySpecialization($_vm_spec_id);
    }

    // Only fetch permissions if class is selected
    if ($_vm_class_id) {
        $existing_permissions = $hodObj->getPermissionsByHod($_SESSION['userid'], $_vm_class_id, $_vm_month_year);
        $_vm_class_timings = $hodObj->getClassTimingsByClassId($_vm_class_id);
        $_vm_total_hours = count($_vm_class_timings);

        foreach ($existing_permissions as $ep) {
            $key = $ep['roll_number'] . '||' . $ep['student_name'];
            $grouped_existing[$key][$ep['date']][] = $ep;
        }
        foreach ($grouped_existing as $key => $by_date) {
            ksort($grouped_existing[$key]);
        }
    }
}

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
include("hodheader.php"); ?>

<style>
    /* ── Permission-module specific styles ── */
    .perm-page-header {
        border-bottom: 2px solid #dee2e6;
        padding-bottom: .6rem;
        margin-bottom: 1.2rem;
    }

    .date-block {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem 1rem .7rem;
        margin-bottom: .8rem;
        position: relative;
    }

    .date-block .remove-date-btn {
        position: absolute;
        top: .5rem;
        right: .6rem;
        background: none;
        border: none;
        color: #dc3545;
        font-size: 1.1rem;
        cursor: pointer;
    }

    .hour-chip-group {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-top: .4rem;
    }

    .hour-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        cursor: pointer;
    }

    .hour-chip input[type=checkbox] {
        cursor: pointer;
    }

    .hour-chip label {
        font-size: .82rem;
        background: #fff;
        border: 1px solid #adb5bd;
        border-radius: 20px;
        padding: 2px 10px;
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
    }

    .hour-chip input[type=checkbox]:checked+label {
        background: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }

    /* Grouped preview */
    .preview-group {
        margin-bottom: 1.5rem;
    }

    .preview-group-header {
        background: #e9ecef;
        font-weight: 600;
        font-size: .9rem;
        padding: .45rem .75rem;
        border-radius: 6px 6px 0 0;
        border: 1px solid #dee2e6;
        border-bottom: none;
    }

    .preview-group table {
        margin-bottom: 0;
        border-radius: 0 0 6px 6px;
        overflow: hidden;
    }

    /* Manage */
    .manage-student-header {
        background: #f0f4ff;
        border: 1px solid #c9d8ff;
        border-radius: 6px 6px 0 0;
        padding: .4rem .75rem;
        font-weight: 600;
        font-size: .88rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .3rem;
    }

    .manage-student-block {
        margin-bottom: 1.4rem;
    }

    .manage-student-block>table {
        border-radius: 0 0 6px 6px;
        overflow: hidden;
    }

    .hour-badge-del {
        font-size: .75rem;
        cursor: pointer;
    }

    #roll_numbers_raw {
        font-family: monospace;
        font-size: .875rem;
        resize: vertical;
    }
</style>

<div class="container">

    <!-- Page Heading + Tab navigation -->
    <div class="d-flex align-items-center justify-content-between perm-page-header flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-patch-check me-2"></i>Student Permissions</h5>
        <div>
            <a href="?page=add"
                class="btn btn-sm <?= $page == 'add' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-plus-circle me-1"></i>Grant Permission
            </a>
            <a href="?page=manage"
                class="btn btn-sm <?= $page == 'manage' ? 'btn-primary' : 'btn-outline-secondary' ?> ms-1">
                <i class="bi bi-table me-1"></i>View &amp; Manage
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible fade show py-2" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ====================================================== ADD PAGE -->
    <?php if ($page == 'add'): ?>

        <?php if (!empty($preview_data)): ?>
            <!-- ── STEP 2 : Preview + Confirm ── -->
            <?php
            $grouped_by_student = [];
            foreach ($preview_data as $row) {
                $grouped_by_student[$row['roll_number']][] = $row;
            }
            $_prev_dates_all = array_unique(array_column($preview_data, 'date'));
            $_prev_d_labels  = array_map(fn($d) => date('d-M-Y', strtotime($d)), $_prev_dates_all);
            sort($_prev_d_labels);
            ?>
            <div class="card shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-eye me-2"></i>Preview — Review before confirming
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Granting permissions for
                        <strong><?= count($grouped_by_student) ?> student(s)</strong>
                        on <strong><?= implode(', ', $_prev_d_labels) ?></strong>
                        (<?= count($preview_data) ?> hour-slot(s) total).
                        Verify and click <em>Confirm &amp; Save</em>.
                    </p>

                    <?php foreach ($grouped_by_student as $roll => $rows): ?>
                        <?php
                        $_prev_by_date = [];
                        foreach ($rows as $_pr) {
                            $_prev_by_date[$_pr['date']][] = $_pr;
                        }
                        ksort($_prev_by_date);
                        ?>
                        <div class="preview-group">
                            <div class="preview-group-header">
                                <?= htmlspecialchars($rows[0]['student_name']) ?>
                                <span class="text-secondary fw-normal ms-1">(<?= htmlspecialchars($roll) ?>)</span>
                                <span class="ms-2 badge bg-secondary"><?= htmlspecialchars($rows[0]['permission_type']) ?></span>
                            </div>
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px">#</th>
                                        <th style="width:130px">Date</th>
                                        <th>Hours</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $_psno = 1;
                                    $_prev_class_timings = $hodObj->getClassTimingsByClassId($selected_class_id);
                                    $_prev_total_hours = count($_prev_class_timings);
                                    foreach ($_prev_by_date as $_pdate => $_prows):
                                        $_prev_is_full = count($_prows) == $_prev_total_hours;
                                    ?>
                                        <tr>
                                            <td><?= $_psno++ ?></td>
                                            <td><?= date('d-M-Y', strtotime($_pdate)) ?></td>
                                            <td>
                                                <?php if ($_prev_is_full): ?>
                                                    <span class="badge bg-success">Complete Day</span>
                                                <?php else: ?>
                                                    <?php foreach ($_prows as $_ph): ?>
                                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($_ph['hour_desc']) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($_prows[0]['reason']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="class_id" value="<?= intval($selected_class_id) ?>">
                        <input type="hidden" name="preview_json" value="<?= htmlspecialchars(json_encode($preview_data)) ?>">
                        <div class="row g-2 align-items-end mt-2">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold mb-1">
                                    Supporting Document <span class="text-muted fw-normal">(optional)</span>
                                </label>
                                <input type="file" name="document" class="form-control form-control-sm"
                                    accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check2-circle me-1"></i>Confirm &amp; Save
                                </button>
                                <a href="?page=add" class="btn btn-outline-secondary btn-sm ms-1">
                                    <i class="bi bi-arrow-left me-1"></i>Back
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- ── STEP 1 : Input Form ── -->
            <div class="card shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-plus-circle me-2"></i>Grant Permission
                </div>
                <div class="card-body">
                    <form method="post" id="permissionForm">
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
                        <input type="hidden" name="action" value="preview">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Specialization</label>
                                <select name="spec_id" id="spec_id" class="form-select form-select-sm" required
                                    onchange="loadClasses(this.value)">
                                    <option value="">— Select —</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?= $spec['id'] ?>"><?= htmlspecialchars($spec['spec_code'] . ' - ' . $spec['spec_fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Class</label>
                                <select name="class_id" id="class_id" class="form-select form-select-sm" required
                                    onchange="loadTimings(this.value)">
                                    <option value="">— Select Class —</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Permission Type</label>
                                <select name="permission_type" class="form-select form-select-sm" required>
                                    <?php foreach ($permission_types as $pt): ?>
                                        <option value="<?= $pt ?>"><?= $pt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Roll Numbers
                                    <span class="text-muted fw-normal small">(comma / line separated)</span>
                                </label>
                                <textarea name="roll_numbers_raw" id="roll_numbers_raw"
                                    class="form-control form-control-sm" rows="3"
                                    placeholder="e.g. 25001A0401, 25001A0402" required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reason / Event Name</label>
                                <input type="text" name="reason" class="form-control form-control-sm"
                                    placeholder="e.g. NCC Annual Training Camp 2025" required maxlength="255">
                                <div class="form-text mt-1">This will be recorded against all selected hours.</div>
                            </div>
                        </div>

                        <!-- Dynamic Date + Hour Blocks -->
                        <hr class="my-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold">Dates &amp; Hours</span>
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                id="addDateBtn" onclick="addDateBlock()">
                                <i class="bi bi-calendar-plus me-1"></i>Add Date
                            </button>
                        </div>
                        <div id="dateBlocksContainer"></div>
                        <p class="text-muted small mt-1" id="noDateMsg">Click "Add Date" to add one or more permission dates.</p>

                        <!-- Hidden store for timings -->
                        <div id="timingsStore" data-timings="[]"></div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary btn-sm" id="previewBtn" disabled>
                                <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">
                                <i class="bi bi-x-circle me-1"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- ====================================================== MANAGE PAGE -->
    <?php elseif ($page == 'manage'): ?>

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span><i class="bi bi-table me-2"></i>Existing Permissions — Granted by You</span>
                <span class="badge bg-dark"><?= count($existing_permissions) ?> record(s)</span>
            </div>
            <div class="card-body">

                <!-- ── Filter Bar ── -->
                <form method="GET" class="row g-2 mb-3 align-items-end" id="vmFilterForm">
                    <input type="hidden" name="page" value="manage">
                    <div class="col-auto">
                        <label class="form-label fw-semibold mb-1 small">Specialization</label>
                        <select name="vm_spec_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— All Specializations —</option>
                            <?php foreach ($_vm_all_specs as $_vs): ?>
                                <option value="<?= $_vs['id'] ?>"
                                    <?= ($_vm_spec_id == $_vs['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($_vs['spec_code'] . ' - ' . $_vs['spec_fullname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-semibold mb-1 small">Class</label>
                        <select name="vm_class_id" class="form-select form-select-sm" onchange="this.form.submit()" <?= !$_vm_spec_id ? 'disabled' : '' ?>>
                            <option value="">— All Classes —</option>
                            <?php foreach ($_vm_all_classes as $_vc2): ?>
                                <option value="<?= $_vc2['id'] ?>"
                                    <?= ($_vm_class_id == $_vc2['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($_vc2['classname'] . ' (' . $_vc2['acad_year'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label fw-semibold mb-1 small">Month</label>
                        <input type="month" name="vm_month_year" class="form-control form-control-sm"
                            value="<?= htmlspecialchars($_vm_month_year ?? '') ?>"
                            onchange="this.form.submit()">
                    </div>
                    <?php if ($_vm_spec_id || $_vm_class_id || $_vm_month_year): ?>
                        <div class="col-auto">
                            <a href="?page=manage" class="btn btn-sm btn-outline-secondary">✕ Clear Filter</a>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if (!$_vm_class_id): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>Please select <strong>Specialization</strong> and <strong>Class</strong> to view permissions.
                    </div>
                <?php elseif (empty($grouped_existing)): ?>
                    <p class="text-muted">No permissions granted yet for the selected class<?= $_vm_month_year ? ' and month' : '' ?>.</p>
                <?php else: ?>

                    <?php foreach ($grouped_existing as $key => $by_date):
                        [$roll, $sname] = explode('||', $key, 2);
                        $_total_dates = count($by_date);
                        $_total_slots = array_sum(array_map('count', $by_date));
                    ?>
                        <div class="manage-student-block">
                            <div class="manage-student-header">
                                <span>
                                    <?= htmlspecialchars($sname) ?>
                                    <span class="text-secondary fw-normal ms-1">(<?= htmlspecialchars($roll) ?>)</span>
                                </span>
                                <span class="text-muted fw-normal small">
                                    <?= $_total_slots ?> hour-slot(s) &nbsp;·&nbsp; <?= $_total_dates ?> date(s)
                                </span>
                            </div>
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:120px">Date</th>
                                        <th>Hours</th>
                                        <th style="width:90px">Type</th>
                                        <th>Reason</th>
                                        <th style="width:90px">Added</th>
                                        <th style="width:140px">Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_date as $_vdate => $_vdate_rows):
                                        $_is_full_day = count($_vdate_rows) == $_vm_total_hours;
                                    ?>
                                        <tr>
                                            <td class="align-middle"><?= date('d-M-Y', strtotime($_vdate)) ?></td>
                                            <td class="align-middle">
                                                <?php if ($_is_full_day): ?>
                                                    <span class="badge bg-success">Complete Day</span>
                                                <?php else: ?>
                                                    <?php foreach ($_vdate_rows as $_vdr): ?>
                                                        <span class="badge bg-info text-dark me-1" style="font-size:.75rem"><?= htmlspecialchars($_vdr['hour_desc']) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php
                                                $pt = $_vdate_rows[0]['permission_type'] ?? '';
                                                $pt_colors = [
                                                    'NCC' => 'primary',
                                                    'NSS' => 'success',
                                                    'Sports' => 'warning',
                                                    'Cultural' => 'purple',
                                                    'Hackathon' => 'info',
                                                    'OD' => 'dark',
                                                    'Other' => 'secondary'
                                                ];
                                                $ptc = $pt_colors[$pt] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $ptc ?>"><?= htmlspecialchars($pt) ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <?= htmlspecialchars($_vdate_rows[0]['reason']) ?>
                                                <?php if (!empty($_vdate_rows[0]['document_path'])): ?>
                                                    <br><a href="<?= htmlspecialchars($_vdate_rows[0]['document_path']) ?>" target="_blank" class="small text-primary"><i class="bi bi-paperclip"></i> View Document</a>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle text-muted small"><?= date('d-M-y', strtotime($_vdate_rows[0]['created_at'])) ?></td>
                                            <td class="align-middle">
                                                <?php if ($_is_full_day): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-bulk-btn"
                                                        data-ids="<?= implode(',', array_column($_vdate_rows, 'id')) ?>"
                                                        onclick="deleteBulkPermissions(this)"
                                                        title="Delete complete day">
                                                        <i class="bi bi-trash me-1"></i>Delete All
                                                    </button>
                                                <?php else: ?>
                                                    <?php foreach ($_vdate_rows as $_vdr): ?>
                                                        <button class="btn btn-link btn-sm p-0 text-danger delete-perm-btn hour-badge-del me-1 mb-1"
                                                            data-id="<?= $_vdr['id'] ?>"
                                                            title="Delete <?= htmlspecialchars($_vdr['hour_desc']) ?>">
                                                            <?= htmlspecialchars($_vdr['hour_desc']) ?> <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endforeach; ?>
                                                    <?php if (count($_vdate_rows) > 1): ?>
                                                        <br>
                                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1 delete-bulk-btn"
                                                            data-ids="<?= implode(',', array_column($_vdate_rows, 'id')) ?>"
                                                            onclick="deleteBulkPermissions(this)"
                                                            title="Delete all <?= count($_vdate_rows) ?> hour-slots on this date">
                                                            <i class="bi bi-trash me-1"></i>All (<?= count($_vdate_rows) ?>)
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div><!-- /container -->

<!-- ── Hidden delete form ── -->
<form id="deleteForm" method="post" style="display:none">
    <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="perm_id" id="deletePerm_id" value="">
</form>

<?php include("footer.php"); ?>

<script>
    // ── Shared state ──────────────────────────────────────────────────────────────
    const timingsStore = document.getElementById('timingsStore');
    let allTimings = [];
    let dateBlockCount = 0;

    // ── Helper: escape HTML ───────────────────────────────────────────────────────
    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ── Load classes when spec changes ────────────────────────────────────────────
    function loadClasses(specId) {
        const sel = document.getElementById('class_id');
        sel.innerHTML = '<option value="">Loading...</option>';
        allTimings = [];
        if (timingsStore) updateTimingsStore();
        if (!specId) {
            sel.innerHTML = '<option value="">— Select Class —</option>';
            return;
        }
        fetch('ajax_get_classes.php?spec_id=' + encodeURIComponent(specId))
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">— Select Class —</option>';
                data.forEach(c => {
                    sel.innerHTML += `<option value="${c.id}">${escHtml(c.classname)} (${escHtml(c.acad_year)})</option>`;
                });
            });
    }

    // ── Load timings when class changes ──────────────────────────────────────────
    function loadTimings(classId) {
        allTimings = [];
        if (timingsStore) updateTimingsStore();
        togglePreviewBtn();
        if (!classId) return;
        fetch('ajax_get_timings.php?class_id=' + encodeURIComponent(classId))
            .then(r => r.json())
            .then(data => {
                allTimings = data;
                if (timingsStore) updateTimingsStore();
                refreshAllDateBlockTimings();
                togglePreviewBtn();
            });
    }

    function updateTimingsStore() {
        timingsStore.dataset.timings = JSON.stringify(allTimings);
    }

    // ── Build one date block HTML ─────────────────────────────────────────────────
    function buildDateBlockHTML(id, dateVal) {
        const safeDateKey = dateVal.replace(/-/g, '_');
        let chipsHTML = '';
        allTimings.forEach(t => {
            const cid = `chk_${id}_${t.id}`;
            chipsHTML += `<span class="hour-chip">
            <input type="checkbox" id="${cid}" name="hours_${safeDateKey}[]" value="${t.id}" checked>
            <label for="${cid}">${escHtml(t.hour_desc)}</label>
        </span>`;
        });
        return `
        <button type="button" class="remove-date-btn" onclick="removeDateBlock('${id}')" title="Remove this date">&times;</button>
        <div class="row g-2 align-items-start">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Date</label>
                <input type="date" class="form-control form-control-sm date-picker"
                       name="dates[]" value="${dateVal}"
                       onchange="onDateChange(this, '${id}')">
            </div>
            <div class="col-md-9">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small fw-semibold">Hours</span>
                    <span>
                        <label class="small me-2"><input type="radio" name="hourmode_${id}" value="all" checked onchange="toggleHourMode('${id}',true)"> All Day</label>
                        <label class="small"><input type="radio" name="hourmode_${id}" value="selected" onchange="toggleHourMode('${id}',false)"> Selected Hours</label>
                    </span>
                </div>
                <div class="hour-chip-group" id="chips_${id}" style="display:none">${chipsHTML}</div>
            </div>
        </div>
        <input type="hidden" class="date-key-store" data-id="${id}" data-date="${dateVal}">
    `;
    }

    // ── Add date block ────────────────────────────────────────────────────────────
    function addDateBlock() {
        if (allTimings.length === 0) {
            alert('Please select a class first to load its timings.');
            return;
        }
        dateBlockCount++;
        const id = 'db_' + dateBlockCount;
        const today = new Date().toISOString().split('T')[0];
        const container = document.getElementById('dateBlocksContainer');
        const noDateMsg = document.getElementById('noDateMsg');
        if (noDateMsg) noDateMsg.style.display = 'none';

        const block = document.createElement('div');
        block.className = 'date-block';
        block.id = id;
        block.innerHTML = buildDateBlockHTML(id, today);
        container.appendChild(block);
        togglePreviewBtn();
    }

    // ── When date input changes, update checkbox names ────────────────────────────
    function onDateChange(input, blockId) {
        const newDate = input.value;
        const block = document.getElementById(blockId);
        const store = block.querySelector('.date-key-store');
        const oldKey = store.dataset.date.replace(/-/g, '_');
        const newKey = newDate.replace(/-/g, '_');
        block.querySelectorAll('input[type=checkbox]').forEach(chk => {
            chk.name = chk.name.replace('hours_' + oldKey, 'hours_' + newKey);
        });
        store.dataset.date = newDate;
    }

    // ── Remove a date block ───────────────────────────────────────────────────────
    function removeDateBlock(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
        const container = document.getElementById('dateBlocksContainer');
        const noDateMsg = document.getElementById('noDateMsg');
        if (container && container.children.length === 0 && noDateMsg) {
            noDateMsg.style.display = '';
        }
        togglePreviewBtn();
    }

    // ── Toggle hour mode (all day vs selected) ───────────────────────────────────
    function toggleHourMode(blockId, isAllDay) {
        const chips = document.getElementById(`chips_${blockId}`);
        if (isAllDay) {
            chips.style.display = 'none';
            chips.querySelectorAll('input[type=checkbox]').forEach(chk => chk.checked = true);
        } else {
            chips.style.display = '';
        }
    }

    // ── Select / deselect all hours in a block ───────────────────────────────────
    function selectAllHours(blockId, select) {
        document.querySelectorAll(`#chips_${blockId} input[type=checkbox]`).forEach(chk => {
            chk.checked = select;
        });
    }

    // ── Re-render hour chips in all existing blocks (after class change) ──────────
    function refreshAllDateBlockTimings() {
        document.querySelectorAll('#dateBlocksContainer .date-block').forEach(block => {
            const dateVal = block.querySelector('.date-picker').value;
            block.innerHTML = buildDateBlockHTML(block.id, dateVal);
        });
    }

    // ── Enable / disable Preview button ──────────────────────────────────────────
    function togglePreviewBtn() {
        const btn = document.getElementById('previewBtn');
        if (!btn) return;
        const classId = document.getElementById('class_id') ? document.getElementById('class_id').value : '';
        const hasBlocks = document.getElementById('dateBlocksContainer').children.length > 0;
        btn.disabled = !(classId && hasBlocks);
    }

    // ── Reset the entire form ─────────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('permissionForm').reset();
        document.getElementById('dateBlocksContainer').innerHTML = '';
        const noDateMsg = document.getElementById('noDateMsg');
        if (noDateMsg) noDateMsg.style.display = '';
        const previewBtn = document.getElementById('previewBtn');
        if (previewBtn) previewBtn.disabled = true;
        allTimings = [];
        dateBlockCount = 0;
        if (timingsStore) updateTimingsStore();
    }

    // ── Single delete ─────────────────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.delete-perm-btn');
        if (btn) {
            const lbl = btn.title || ('permission ID ' + btn.dataset.id);
            if (!confirm('Delete this hour-slot?\n' + lbl)) return;
            document.getElementById('deletePerm_id').value = btn.dataset.id;
            document.getElementById('deleteForm').submit();
        }
    });

    // ── Bulk delete (all hours on a date) ─────────────────────────────────────────
    function deleteBulkPermissions(btn) {
        const ids = btn.dataset.ids.split(',').map(id => id.trim()).filter(id => id);
        if (!ids.length) return;

        if (!confirm('Delete ALL ' + ids.length + ' hour-slot(s) on this date?')) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting...';

        const secretcode = document.getElementById('deleteForm').querySelector('[name="secretcode"]').value;

        const deleteOne = (id) => {
            return fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    secretcode: secretcode,
                    action: 'delete',
                    perm_id: id
                })
            }).then(r => r.json());
        };

        const deleteAll = async () => {
            try {
                for (let id of ids) {
                    const result = await deleteOne(id);
                    if (result.status !== 1) {
                        throw new Error(result.error || 'Delete failed');
                    }
                }
                window.location.reload();
            } catch (err) {
                console.error('Delete failed:', err);
                alert('Error: ' + err.message);
                window.location.reload();
            }
        };

        deleteAll();
    }
</script>
</body>

</html>
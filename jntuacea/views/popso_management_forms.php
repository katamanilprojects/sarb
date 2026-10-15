<?php
// Fetch PO and PSO data
$po_data = [];
$pso_data = [];
$first_spec_id = $spec_ids[0];
$po_result = $superadmin->getPoPso($acad_year, $regulation, $first_spec_id);
if(isset($po_result['status']) && $po_result['status'] === 1 && isset($po_result['data'])) {
    $po_data = array_filter($po_result['data'], function($item) { return isset($item['po_pso']) && $item['po_pso'] === 'PO'; });
}

foreach ($spec_ids as $spec_id_item) {
    $pso_result = $superadmin->getPoPso($acad_year, $regulation, $spec_id_item);
    if(isset($pso_result['status']) && $pso_result['status'] === 1 && isset($pso_result['data'])) {
        $pso_data[$spec_id_item] = array_filter($pso_result['data'], function($item) { return isset($item['po_pso']) && $item['po_pso'] === 'PSO'; });
    }
}
?>

<!-- PO Management Section -->
<div class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Program Outcomes (POs)</h4>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="togglePoEditMode()">
            <i class="fas fa-edit"></i> <span id="po-edit-btn-text">Edit Mode</span>
        </button>
    </div>

    <?php if (!empty($po_data)): ?>
        <!-- View Mode: Table Display -->
        <div id="po-view-mode">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="15%">Code</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po_data as $po): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($po['code']); ?></strong></td>
                                <td><?= htmlspecialchars($po['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Mode: Form -->
    <div id="po-edit-mode" style="display: none;">
        <form method="post" action="superadminpopso.php">
            <input type="hidden" name="action" value="save_pos">
            <input type="hidden" name="active_tab" value="department">
            <input type="hidden" name="acad_year" value="<?= htmlspecialchars($acad_year); ?>">
            <input type="hidden" name="regulation" value="<?= htmlspecialchars($regulation); ?>">
            <input type="hidden" name="prog_id" value="<?= htmlspecialchars($prog_id); ?>">
            <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id); ?>">
            <?php foreach ($spec_ids as $spec_id_hidden): ?>
                <input type="hidden" name="spec_ids[]" value="<?= htmlspecialchars($spec_id_hidden); ?>">
            <?php endforeach; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Add or modify POs below. Click "Save POs" to apply changes.
            </div>

            <div id="po-container">
                <?php if (empty($po_data)): ?>
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-2">
                            <input type="text" name="po_code[]" class="form-control" placeholder="e.g., PO1" required>
                        </div>
                        <div class="col-md-9">
                            <textarea name="po_description[]" class="form-control" rows="2" placeholder="Enter PO description" required></textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($po_data as $po): ?>
                        <div class="row mb-2 align-items-center">
                            <div class="col-md-2">
                                <input type="text" name="po_code[]" class="form-control" value="<?= htmlspecialchars($po['code']); ?>" required>
                            </div>
                            <div class="col-md-9">
                                <textarea name="po_description[]" class="form-control" rows="2" required><?= htmlspecialchars($po['description']); ?></textarea>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" onclick="addPoRow('po-container')">
                    <i class="fas fa-plus"></i> Add PO
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save POs
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePoEditMode()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PSO Management Section -->
<div class="mt-5">
    <h4 class="mb-3">Program Specific Outcomes (PSOs)</h4>
    <?php foreach ($spec_ids as $spec_id_pso): ?>
        <div class="card mt-3 shadow-sm">
            <?php 
            $spec_name_result = $superadmin->getSpecializationShortnameById($spec_id_pso);
            $spec_name = (isset($spec_name_result['data']['spec_shortname'])) ? $spec_name_result['data']['spec_shortname'] : $spec_id_pso;
            ?>
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>PSOs for Specialization: <?= htmlspecialchars($spec_name); ?></strong>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="togglePsoEditMode(<?= $spec_id_pso; ?>)">
                    <i class="fas fa-edit"></i> <span id="pso-edit-btn-text-<?= $spec_id_pso; ?>">Edit Mode</span>
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($pso_data[$spec_id_pso]) && !empty($pso_data[$spec_id_pso])): ?>
                    <!-- View Mode: Table Display -->
                    <div id="pso-view-mode-<?= $spec_id_pso; ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="15%">Code</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pso_data[$spec_id_pso] as $pso): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($pso['code']); ?></strong></td>
                                            <td><?= htmlspecialchars($pso['description']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Mode: Form -->
                <div id="pso-edit-mode-<?= $spec_id_pso; ?>" style="display: none;">
                    <form method="post" action="superadminpopso.php">
                        <input type="hidden" name="action" value="save_psos">
                        <input type="hidden" name="active_tab" value="department">
                        <input type="hidden" name="acad_year" value="<?= htmlspecialchars($acad_year); ?>">
                        <input type="hidden" name="regulation" value="<?= htmlspecialchars($regulation); ?>">
                        <input type="hidden" name="prog_id" value="<?= htmlspecialchars($prog_id); ?>">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id); ?>">
                        <input type="hidden" name="pso_spec_id" value="<?= htmlspecialchars($spec_id_pso); ?>">
                        <?php foreach ($spec_ids as $sid): ?>
                            <input type="hidden" name="spec_ids[]" value="<?= htmlspecialchars($sid); ?>">
                        <?php endforeach; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Add or modify PSOs below. Click "Save PSOs" to apply changes.
                        </div>

                        <div id="pso-container-<?= $spec_id_pso; ?>">
                            <?php if (!isset($pso_data[$spec_id_pso]) || empty($pso_data[$spec_id_pso])) : ?>
                                <div class="row mb-2 align-items-center">
                                    <div class="col-md-2">
                                        <input type="text" name="pso_code[]" class="form-control" placeholder="e.g., PSO1" required>
                                    </div>
                                    <div class="col-md-9">
                                        <textarea name="pso_description[]" class="form-control" rows="2" placeholder="Enter PSO description" required></textarea>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pso_data[$spec_id_pso] as $pso): ?>
                                    <div class="row mb-2 align-items-center">
                                        <div class="col-md-2">
                                            <input type="text" name="pso_code[]" class="form-control" value="<?= htmlspecialchars($pso['code']); ?>" required>
                                        </div>
                                        <div class="col-md-9">
                                            <textarea name="pso_description[]" class="form-control" rows="2" required><?= htmlspecialchars($pso['description']); ?></textarea>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-secondary" onclick="addPsoRow(<?= $spec_id_pso; ?>)">
                                <i class="fas fa-plus"></i> Add PSO
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Save PSOs
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePsoEditMode(<?= $spec_id_pso; ?>)">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}
</style>

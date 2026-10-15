<?php
/**
 * View: Manage Regulations (Multi-Program Autonomous Architecture)
 * Supports UG (B.Tech) and PG (M.Tech, MCA) programs with distinct
 * degree boundaries, credit ceilings, durations, and statutory feature toggles.
 */
?>

<div class="container-fluid px-4 mt-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0 text-primary fw-bold">
                <i class="bi bi-journal-text me-2"></i>Academic Regulations Authority
            </h3>
            <p class="text-muted small mb-0">Statutory academic regulations, degree credit ceilings, normal/maximum durations, and policy configurations (JNTUA CEA Autonomous).</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#cloneRegulationModal">
                    <i class="bi bi-copy me-1"></i>Clone Regulation
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#regulationModal" onclick="openNewRegulationModal()">
                    <i class="bi bi-plus-circle me-1"></i>New Regulation
                </button>
            <?php endif; ?>
            <a href="superadminacademicsettings.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-sliders me-1"></i>Academic Settings Engine
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <?php
        $ugCount = 0;
        $pgCount = 0;
        foreach ($regulations as $r) {
            if (($r['program_level'] ?? 'UG') === 'PG') $pgCount++;
            else $ugCount++;
        }
        ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Undergraduate (UG)</div>
                            <h4 class="fw-bold mb-0"><?= $ugCount ?> Regulations (B.Tech)</h4>
                            <small class="text-white-50">Standard 4 Years / 8 Semesters</small>
                        </div>
                        <i class="bi bi-mortarboard fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Postgraduate (PG)</div>
                            <h4 class="fw-bold mb-0"><?= $pgCount ?> Regulations (M.Tech / MCA)</h4>
                            <small class="text-white-50">Standard 2 Years / 4 Semesters</small>
                        </div>
                        <i class="bi bi-award fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-dark bg-gradient text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase">Total Governed Schemes</div>
                            <h4 class="fw-bold mb-0"><?= count($regulations) ?> Regulations</h4>
                            <small class="text-white-50">Autonomous JNTUA CEA Framework</small>
                        </div>
                        <i class="bi bi-diagram-3 fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Regulations Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>Configured Degree Regulations</h5>
            <span class="badge bg-secondary"><?= count($regulations) ?> active</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 140px;">Regulation</th>
                            <th>Degree Program</th>
                            <th>Duration & Semesters</th>
                            <th class="text-center">Graduation Credits</th>
                            <th>Statutory Features & Options</th>
                            <th>Effective Batches</th>
                            <th class="text-end pe-3" style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regulations as $reg): 
                            $isPg = (($reg['program_level'] ?? 'UG') === 'PG');
                            $badgeClass = $isPg ? 'bg-success' : 'bg-primary';
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge <?= $badgeClass ?> fs-6 fw-bold px-2 py-1">
                                        <?= htmlspecialchars($reg['regulation']) ?>
                                    </span>
                                    <div class="text-muted small mt-1 font-monospace">ID: <?= (int)$reg['id'] ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($reg['prog_shortname']) ?>
                                        <span class="badge <?= $isPg ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> ms-1">
                                            <?= htmlspecialchars($reg['program_level'] ?? ($isPg ? 'PG' : 'UG')) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($reg['prog_fullname'] ?? '') ?></small>
                                </td>
                                <td>
                                    <div><strong><?= (int)($reg['normal_duration_years'] ?? 4) ?> Years</strong> (<?= (int)($reg['total_semesters'] ?? 8) ?> Sems)</div>
                                    <small class="text-danger">Max: <?= (int)($reg['max_duration_years'] ?? 8) ?> Years before forfeit</small>
                                    <?php if (!empty($reg['has_gap_year'])): ?>
                                        <div><span class="badge bg-info-subtle text-info">+<?= (int)$reg['gap_year_extension_years'] ?>y Gap Year</span></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark fs-6 px-2 py-1">
                                        <?= number_format((float)($reg['total_degree_credits'] ?? 160.0), 1) ?> cr
                                    </span>
                                    <?php if (!empty($reg['has_lateral_entry'])): ?>
                                        <div class="small text-muted mt-1">LES: <strong><?= number_format((float)$reg['lateral_entry_credits'], 1) ?> cr</strong></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (!empty($reg['has_lateral_entry'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary" title="Lateral Entry Scheme">LES</span>
                                        <?php endif; ?>
                                        <?php if (!empty($reg['has_honors'])): ?>
                                            <span class="badge bg-warning-subtle text-dark" title="Honors (+<?= (float)$reg['honors_credits'] ?> cr)">Honors (+<?= (float)$reg['honors_credits'] ?>)</span>
                                        <?php endif; ?>
                                        <?php if (!empty($reg['has_minors'])): ?>
                                            <span class="badge bg-info-subtle text-dark" title="Minor Degree (<?= (float)$reg['minor_credits'] ?> cr)">Minor (<?= (float)$reg['minor_credits'] ?>)</span>
                                        <?php endif; ?>
                                        <?php if (!empty($reg['has_gap_year'])): ?>
                                            <span class="badge bg-success-subtle text-success" title="Student Entrepreneur Gap Year">Gap Year</span>
                                        <?php endif; ?>
                                        <?php if (!empty($reg['has_internal_improvement'])): ?>
                                            <span class="badge bg-primary-subtle text-primary" title="Re-registration for internal improvement">Internal Improvement</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong>Regular:</strong> <?= htmlspecialchars($reg['effective_admitted_batch'] ?? 'All batches') ?>
                                    </div>
                                    <?php if (!empty($reg['les_effective_batch'])): ?>
                                        <div class="small text-muted">
                                            <strong>LES:</strong> <?= htmlspecialchars($reg['les_effective_batch']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
                                            <a href="superadminregulationdetails.php?reg_id=<?= (int)$reg['id'] ?>" class="btn btn-primary" title="Configure Categories, % Allocations, and Course Types">
                                                <i class="bi bi-diagram-3 me-1"></i>Categories & Types
                                            </a>
                                            <a href="superadminacademicsettings.php?reg_id=<?= (int)$reg['id'] ?>&reg=<?= urlencode($reg['regulation']) ?>" class="btn btn-outline-primary" title="Configure Academic Policy Settings">
                                                <i class="bi bi-sliders"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" onclick='editRegulation(<?= json_encode($reg) ?>)' title="Edit Regulation Boundaries">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this regulation and all its parameters?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$reg['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Regulation">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="academicsectioncurriculumsubjects.php?reg_id=<?= (int)$reg['id'] ?>&prog_id=<?= (int)$reg['prog_id'] ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View Catalog
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD / EDIT REGULATION -->
<!-- ========================================================================= -->
<div class="modal fade" id="regulationModal" tabindex="-1" aria-labelledby="regulationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= htmlspecialchars($page_action) ?>" id="regulationForm" class="modal-content">
            <input type="hidden" name="action" value="add_or_update">
            <input type="hidden" name="id" id="reg_id" value="">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="regulationModalLabel">
                    <i class="bi bi-gear-wide-connected me-2"></i>Configure Academic Regulation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Section 1: Basic Identity -->
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>1. Regulation Identity & Degree Program</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Regulation Code <span class="text-danger">*</span></label>
                        <input type="text" name="regulation" id="reg_code" class="form-control text-uppercase" placeholder="e.g. R23, R25" maxlength="4" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Degree Program <span class="text-danger">*</span></label>
                        <select name="prog_id" id="reg_prog_id" class="form-select" required onchange="handleProgramChange()">
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?= (int)$prog['id'] ?>" data-level="<?= htmlspecialchars($prog['program_level'] ?? 'UG') ?>">
                                    <?= htmlspecialchars($prog['prog_shortname'] . ' (' . ($prog['program_level'] ?? 'UG') . ') - ' . $prog['prog_fullname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Start Year</label>
                        <input type="number" name="start_year" id="reg_start_year" class="form-control" placeholder="e.g. 2023" min="2000" max="2050">
                    </div>
                </div>

                <!-- Section 2: Durations & Credit Ceilings -->
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-clock-history me-2"></i>2. Statutory Durations & Credit Ceilings</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Normal Duration (Yrs) <span class="text-danger">*</span></label>
                        <input type="number" name="normal_duration_years" id="reg_normal_duration" class="form-control" min="1" max="6" value="4" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Total Semesters <span class="text-danger">*</span></label>
                        <input type="number" name="total_semesters" id="reg_total_semesters" class="form-control" min="2" max="12" value="8" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Max Duration (Yrs) <span class="text-danger">*</span></label>
                        <input type="number" name="max_duration_years" id="reg_max_duration" class="form-control" min="2" max="12" value="8" required>
                        <small class="text-muted">Before seat forfeiture</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Degree Credits <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" name="total_degree_credits" id="reg_total_credits" class="form-control" min="30" max="250" value="160.0" required>
                        <small class="text-muted">e.g. 163 for R23, 75 for R25</small>
                    </div>
                </div>

                <!-- Section 3: Feature Toggles -->
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-toggle-on me-2"></i>3. Academic Options & Statutory Features</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="has_lateral_entry" id="reg_has_les" onchange="toggleLesFields()">
                            <label class="form-check-label fw-bold" for="reg_has_les">Lateral Entry Scheme (LES)</label>
                        </div>
                        <div class="row g-2 ps-4" id="les_fields" style="display:none;">
                            <div class="col-6">
                                <label class="form-label small">LES Credits</label>
                                <input type="number" step="0.5" name="lateral_entry_credits" id="reg_les_credits" class="form-control form-control-sm" value="120.0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">LES Effective Batch</label>
                                <input type="text" name="les_effective_batch" id="reg_les_batch" class="form-control form-control-sm" placeholder="e.g. 2024-25 onwards">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="has_gap_year" id="reg_has_gap_year" onchange="toggleGapYearField()">
                            <label class="form-check-label fw-bold" for="reg_has_gap_year">Student Entrepreneur Gap Year</label>
                        </div>
                        <div class="ps-4" id="gap_year_field" style="display:none;">
                            <label class="form-label small">Max Gap Extension (Years)</label>
                            <input type="number" name="gap_year_extension_years" id="reg_gap_years" class="form-control form-control-sm" value="2" min="1" max="2">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="has_honors" id="reg_has_honors" onchange="toggleHonorsField()">
                            <label class="form-check-label fw-bold" for="reg_has_honors">Honors Degree (+Credits)</label>
                        </div>
                        <div id="honors_field" style="display:none;">
                            <input type="number" step="0.5" name="honors_credits" id="reg_honors_credits" class="form-control form-control-sm" value="15.0">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="has_minors" id="reg_has_minors" onchange="toggleMinorsField()">
                            <label class="form-check-label fw-bold" for="reg_has_minors">Minor Degree (Credits)</label>
                        </div>
                        <div id="minors_field" style="display:none;">
                            <input type="number" step="0.5" name="minor_credits" id="reg_minors_credits" class="form-control form-control-sm" value="12.0">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="has_internal_improvement" id="reg_has_improvement">
                            <label class="form-check-label fw-bold" for="reg_has_improvement">Internal Mark Improvement</label>
                        </div>
                        <small class="text-muted">Re-registration for failed theory (PG)</small>
                    </div>
                </div>

                <!-- Section 4: Effective Batches -->
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-check me-2"></i>4. Effective Admitted Batch</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Effective Admitted Batch</label>
                        <input type="text" name="effective_admitted_batch" id="reg_effective_batch" class="form-control" placeholder="e.g. Applicable for students admitted from Academic Year 2023-24 onwards">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-save me-1"></i>Save Regulation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CLONE REGULATION -->
<!-- ========================================================================= -->
<div class="modal fade" id="cloneRegulationModal" tabindex="-1" aria-labelledby="cloneRegulationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="modal-content">
            <input type="hidden" name="action" value="clone">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="cloneRegulationModalLabel">
                    <i class="bi bi-copy me-2"></i>Clone Academic Regulation Parameters
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <p class="text-muted small">
                    This utility clones all Course Categories, Evaluation Schemes, and Academic Settings from an established baseline regulation into your newly defined regulation in one click.
                </p>

                <div class="mb-3">
                    <label class="form-label fw-bold">Source Regulation (Clone From) <span class="text-danger">*</span></label>
                    <select name="source_reg_id" class="form-select" required>
                        <option value="">-- Select Source Regulation --</option>
                        <?php foreach ($regulations as $r): ?>
                            <option value="<?= (int)$r['id'] ?>">
                                <?= htmlspecialchars($r['regulation'] . ' - ' . $r['prog_shortname'] . ' (' . ($r['program_level'] ?? 'UG') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Target Regulation (Clone Into) <span class="text-danger">*</span></label>
                    <select name="target_reg_id" class="form-select" required>
                        <option value="">-- Select Target Regulation --</option>
                        <?php foreach ($regulations as $r): ?>
                            <option value="<?= (int)$r['id'] ?>">
                                <?= htmlspecialchars($r['regulation'] . ' - ' . $r['prog_shortname'] . ' (' . ($r['program_level'] ?? 'UG') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>Existing categories and types in the target regulation with matching codes will be updated.
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-bold">
                    <i class="bi bi-copy me-1"></i>Execute Clone
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewRegulationModal() {
    document.getElementById('regulationForm').reset();
    document.getElementById('reg_id').value = '';
    document.getElementById('regulationModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Create New Academic Regulation';
    document.getElementById('les_fields').style.display = 'none';
    document.getElementById('gap_year_field').style.display = 'none';
    document.getElementById('honors_field').style.display = 'none';
    document.getElementById('minors_field').style.display = 'none';
}

function editRegulation(reg) {
    document.getElementById('reg_id').value = reg.id || '';
    document.getElementById('reg_code').value = reg.regulation || '';
    document.getElementById('reg_prog_id').value = reg.prog_id || '';
    document.getElementById('reg_start_year').value = reg.start_year || '';
    document.getElementById('reg_normal_duration').value = reg.normal_duration_years || 4;
    document.getElementById('reg_total_semesters').value = reg.total_semesters || 8;
    document.getElementById('reg_max_duration').value = reg.max_duration_years || 8;
    document.getElementById('reg_total_credits').value = reg.total_degree_credits || 160.0;
    
    document.getElementById('reg_has_les').checked = !!(parseInt(reg.has_lateral_entry));
    document.getElementById('reg_les_credits').value = reg.lateral_entry_credits || 120.0;
    document.getElementById('reg_les_batch').value = reg.les_effective_batch || '';
    toggleLesFields();

    document.getElementById('reg_has_gap_year').checked = !!(parseInt(reg.has_gap_year));
    document.getElementById('reg_gap_years').value = reg.gap_year_extension_years || 2;
    toggleGapYearField();

    document.getElementById('reg_has_honors').checked = !!(parseInt(reg.has_honors));
    document.getElementById('reg_honors_credits').value = reg.honors_credits || 15.0;
    toggleHonorsField();

    document.getElementById('reg_has_minors').checked = !!(parseInt(reg.has_minors));
    document.getElementById('reg_minors_credits').value = reg.minor_credits || 12.0;
    toggleMinorsField();

    document.getElementById('reg_has_improvement').checked = !!(parseInt(reg.has_internal_improvement));
    document.getElementById('reg_effective_batch').value = reg.effective_admitted_batch || '';

    document.getElementById('regulationModalLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Regulation: ' + reg.regulation;
    var modal = new bootstrap.Modal(document.getElementById('regulationModal'));
    modal.show();
}

function handleProgramChange() {
    var select = document.getElementById('reg_prog_id');
    var selectedOption = select.options[select.selectedIndex];
    var level = selectedOption.getAttribute('data-level') || 'UG';

    if (level === 'PG') {
        document.getElementById('reg_normal_duration').value = 2;
        document.getElementById('reg_total_semesters').value = 4;
        document.getElementById('reg_max_duration').value = 4;
        document.getElementById('reg_total_credits').value = 75.0;
        document.getElementById('reg_has_les').checked = false;
        document.getElementById('reg_has_gap_year').checked = false;
        document.getElementById('reg_has_honors').checked = false;
        document.getElementById('reg_has_minors').checked = false;
        document.getElementById('reg_has_improvement').checked = true;
    } else {
        document.getElementById('reg_normal_duration').value = 4;
        document.getElementById('reg_total_semesters').value = 8;
        document.getElementById('reg_max_duration').value = 8;
        document.getElementById('reg_total_credits').value = 163.0;
        document.getElementById('reg_has_les').checked = true;
        document.getElementById('reg_has_gap_year').checked = true;
        document.getElementById('reg_has_honors').checked = true;
        document.getElementById('reg_has_minors').checked = true;
        document.getElementById('reg_has_improvement').checked = false;
    }
    toggleLesFields();
    toggleGapYearField();
    toggleHonorsField();
    toggleMinorsField();
}

function toggleLesFields() {
    var chk = document.getElementById('reg_has_les');
    document.getElementById('les_fields').style.display = chk.checked ? 'flex' : 'none';
}

function toggleGapYearField() {
    var chk = document.getElementById('reg_has_gap_year');
    document.getElementById('gap_year_field').style.display = chk.checked ? 'block' : 'none';
}

function toggleHonorsField() {
    var chk = document.getElementById('reg_has_honors');
    document.getElementById('honors_field').style.display = chk.checked ? 'block' : 'none';
}

function toggleMinorsField() {
    var chk = document.getElementById('reg_has_minors');
    document.getElementById('minors_field').style.display = chk.checked ? 'block' : 'none';
}
</script>

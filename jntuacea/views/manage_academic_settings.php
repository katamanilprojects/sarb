<?php
/**
 * View: Manage Academic Settings
 * Multi-Regulation Scoped Academic Parameters Dashboard
 * Category accordions: CIA, SEE, Attendance Rules, Attainment (OBE), Grading & Class Awards, General
 */
$categoryTitles = [
    'CIA' => [
        'title' => 'Continuous Internal Assessment (CIA)',
        'icon' => 'bi-clipboard-data',
        'desc' => 'Theory mid-term exam condensation, weights, objective/assignment distributions, and lab/drawing evaluation.'
    ],
    'SEE' => [
        'title' => 'Semester End Examinations (SEE)',
        'icon' => 'bi-journal-check',
        'desc' => 'End semester examination maximum marks, compulsory question paper patterns, and composite split subject rules.'
    ],
    'ATTENDANCE' => [
        'title' => 'Attendance Compliance Rules',
        'icon' => 'bi-calendar-check',
        'desc' => 'Minimum aggregate attendance (Section 17.i), subject-wise cutoff, and condonation floor limits.'
    ],
    'ATTAINMENT' => [
        'title' => 'Outcome-Based Education (OBE) & Attainment',
        'icon' => 'bi-bullseye',
        'desc' => 'CO/PO direct and indirect assessment weights, NBA target percentage, and cohort level 1/2/3 thresholds.'
    ],
    'GRADING' => [
        'title' => 'Grading Scales, Class Award & Passing Criteria',
        'icon' => 'bi-award',
        'desc' => 'SEE and aggregate passing minimum percentages, 10-point absolute grading bands, and CGPA class award cutoffs.'
    ],
    'PROMOTION' => [
        'title' => 'Academic Progression & Promotion Rules',
        'icon' => 'bi-graph-up-arrow',
        'desc' => 'Statutory cumulative credit requirements for promotion between academic years (Section 7 UG) and LES entry.'
    ],
    'HONORS' => [
        'title' => 'Honors Degree Regulations',
        'icon' => 'bi-trophy',
        'desc' => 'Eligibility CGPA (>=8.0), no active backlogs criteria, 15 additional credits allocation, and MOOCs limits.'
    ],
    'MINOR' => [
        'title' => 'Minor Degree Regulations',
        'icon' => 'bi-diagram-3',
        'desc' => 'Interdisciplinary minor tracks, 12 additional credits, eligible disciplines, and registration rules.'
    ],
    'MOOCS' => [
        'title' => 'Online MOOCs & NPTEL Policies',
        'icon' => 'bi-laptop',
        'desc' => 'Maximum MOOC credits per semester (40% ceiling), SWAYAM/NPTEL proctored exam transfer, and credit equivalence.'
    ],
    'PROJECT' => [
        'title' => 'Project Work & Dissertation Evaluation',
        'icon' => 'bi-kanban',
        'desc' => 'Project Review Committee (PRC) evaluation, dissertation viva, supervisor marks, and plagiarism ceiling (30%).'
    ],
    'ACTIVITIES' => [
        'title' => 'Mandatory Non-Credit Activities & Audit Courses',
        'icon' => 'bi-activity',
        'desc' => 'Community service, NSS, Sports, and mandatory non-credit induction/audit courses.'
    ],
    'GENERAL' => [
        'title' => 'Internship & System Policies',
        'icon' => 'bi-gear-wide-connected',
        'desc' => 'Internship evaluation marks, internal improvement re-registration rules, and mark entry grace window.'
    ]
];
?>

<div class="container-fluid px-4 mt-4">
    <!-- Page Header & Regulatory Selector -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0 text-primary fw-bold">
                <i class="bi bi-sliders2-vertical me-2"></i>Academic Regulations & Settings Engine
            </h3>
            <p class="text-muted small mb-0">Autonomous regulatory rules, formula multipliers, mark weightages, and thresholds (JNTUA CEA Autonomous).</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label for="regSelector" class="fw-bold text-secondary mb-0">Regulation:</label>
            <div class="btn-group flex-wrap" role="group">
                <?php if (!empty($regulationsMeta)): ?>
                    <?php foreach ($regulationsMeta as $rm): ?>
                        <?php 
                        $isSelected = ($activeRegId > 0 && (int)$rm['id'] === $activeRegId) || ($activeRegId <= 0 && $rm['regulation'] === $activeRegulation);
                        $isUG = ($rm['program_level'] === 'UG');
                        ?>
                        <a href="superadminacademicsettings.php?reg_id=<?= $rm['id'] ?>&reg=<?= urlencode($rm['regulation']) ?>" 
                           class="btn btn-sm <?= $isSelected ? ($isUG ? 'btn-primary fw-bold' : 'btn-dark fw-bold') : 'btn-outline-secondary' ?>">
                            <span class="badge <?= $isUG ? 'bg-light text-primary' : 'bg-warning text-dark' ?> me-1"><?= htmlspecialchars($rm['program_level']) ?></span>
                            <?= htmlspecialchars($rm['prog_shortname']) ?> - <?= htmlspecialchars($rm['regulation']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($availableRegulations as $regCode): ?>
                        <a href="superadminacademicsettings.php?reg=<?= urlencode($regCode) ?>" 
                           class="btn btn-sm <?= ($activeRegulation === $regCode) ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                            <?= htmlspecialchars($regCode) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($is_readonly): ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i>Read Only (HOD)</span>
            <?php else: ?>
                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Admin Edit Mode</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($succMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($succMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Pills: Settings by Category vs Audit Trail -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="parameters-tab" data-bs-toggle="tab" data-bs-target="#parameters-pane" type="button" role="tab">
                <i class="bi bi-sliders me-1"></i>Regulatory Parameters (<?= htmlspecialchars($activeRegulation) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit-pane" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i>Audit Trail & Change Log
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="calculator-tab" data-bs-toggle="tab" data-bs-target="#calculator-pane" type="button" role="tab">
                <i class="bi bi-calculator me-1"></i>Regulatory Simulator
            </button>
        </li>
    </ul>

    <div class="tab-content" id="settingsTabsContent">
        <!-- 1. Regulatory Parameters Panel -->
        <div class="tab-pane fade show active" id="parameters-pane" role="tabpanel">
            <form method="POST" action="superadminacademicsettings.php?reg_id=<?= $activeRegId ?>&reg=<?= urlencode($activeRegulation) ?>" id="academicSettingsForm">
                <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode'] ?? '') ?>">
                <input type="hidden" name="regulation" value="<?= htmlspecialchars($activeRegulation) ?>">
                <input type="hidden" name="reg_id" value="<?= $activeRegId ?>">
                <input type="hidden" name="action" value="save_settings">

                <div class="accordion mb-4" id="settingsAccordion">
                    <?php 
                    $categoryOrder = ['CIA', 'SEE', 'ATTENDANCE', 'ATTAINMENT', 'GRADING', 'PROMOTION', 'HONORS', 'MINOR', 'MOOCS', 'PROJECT', 'ACTIVITIES', 'GENERAL'];
                    foreach ($categoryOrder as $idx => $catKey): 
                        if (!isset($groupedSettings[$catKey])) continue;
                        $catMeta = $categoryTitles[$catKey] ?? ['title' => $catKey, 'desc' => ''];
                        $items = $groupedSettings[$catKey];
                        $accordionId = "collapseCat" . $catKey;
                        $borderClass = match($catKey) {
                            'CIA' => 'border-primary',
                            'SEE' => 'border-info',
                            'ATTENDANCE' => 'border-warning',
                            'ATTAINMENT' => 'border-success',
                            'GRADING' => 'border-dark',
                            'PROMOTION' => 'border-primary',
                            'HONORS' => 'border-success',
                            'MINOR' => 'border-info',
                            'MOOCS' => 'border-warning',
                            'PROJECT' => 'border-danger',
                            default => 'border-secondary'
                        };
                    ?>
                        <div class="card mb-3 shadow-sm border-0 border-start border-4 <?= $borderClass ?>">
                            <div class="card-header bg-white py-3" id="heading<?= $catKey ?>">
                                <div class="d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>">
                                    <div>
                                        <h5 class="mb-1 fw-bold text-dark">
                                            <?= htmlspecialchars($catMeta['title']) ?>
                                            <span class="badge bg-light text-dark border ms-2"><?= count($items) ?> rules</span>
                                        </h5>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($catMeta['desc']) ?></p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="<?= $accordionId ?>" class="collapse <?= ($idx === 0) ? 'show' : '' ?>" data-bs-parent="#settingsAccordion">
                                <div class="card-body bg-light-subtle">
                                    <div class="row g-3">
                                        <?php foreach ($items as $key => $setting): 
                                            $dataType = strtoupper($setting['data_type'] ?? 'STRING');
                                            $val = $setting['setting_value'];
                                            $desc = $setting['description'] ?? '';
                                            $isEditable = !empty($setting['is_editable']) && !$is_readonly;
                                            $isJson = ($dataType === 'JSON');
                                            $colWidth = $isJson ? 'col-12' : 'col-md-6 col-lg-4';
                                        ?>
                                            <div class="<?= $colWidth ?>">
                                                <div class="card h-100 shadow-xs border">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <label class="form-label fw-bold text-primary mb-0 font-monospace small">
                                                                <?= htmlspecialchars($key) ?>
                                                            </label>
                                                            <span class="badge bg-secondary-subtle text-secondary small"><?= htmlspecialchars($dataType) ?></span>
                                                        </div>

                                                        <div class="text-muted small mb-2" style="min-height: 38px;">
                                                            <?= htmlspecialchars($desc) ?>
                                                        </div>

                                                        <?php if ($dataType === 'BOOL'): ?>
                                                            <select name="settings[<?= htmlspecialchars($key) ?>]" class="form-select form-select-sm" <?= $isEditable ? '' : 'disabled' ?>>
                                                                <option value="1" <?= ($val === '1' || $val === 'true') ? 'selected' : '' ?>>Enabled / True</option>
                                                                <option value="0" <?= ($val === '0' || $val === 'false') ? 'selected' : '' ?>>Disabled / False</option>
                                                            </select>
                                                        <?php elseif ($dataType === 'FLOAT'): ?>
                                                            <input type="number" step="0.01" name="settings[<?= htmlspecialchars($key) ?>]" 
                                                                   id="input_<?= htmlspecialchars($key) ?>"
                                                                   class="form-control form-control-sm" 
                                                                   value="<?= htmlspecialchars($val) ?>" 
                                                                   <?= $isEditable ? '' : 'readonly' ?> required>
                                                        <?php elseif ($dataType === 'INT'): ?>
                                                            <input type="number" step="1" name="settings[<?= htmlspecialchars($key) ?>]" 
                                                                   id="input_<?= htmlspecialchars($key) ?>"
                                                                   class="form-control form-control-sm" 
                                                                   value="<?= htmlspecialchars($val) ?>" 
                                                                   <?= $isEditable ? '' : 'readonly' ?> required>
                                                        <?php elseif ($dataType === 'JSON'): ?>
                                                            <div class="mb-1">
                                                                <textarea name="settings[<?= htmlspecialchars($key) ?>]" 
                                                                          id="input_<?= htmlspecialchars($key) ?>"
                                                                          rows="6" 
                                                                          class="form-control form-control-sm font-monospace" 
                                                                          <?= $isEditable ? '' : 'readonly' ?> required><?= htmlspecialchars(json_encode(json_decode($val, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <small class="text-muted">Must be valid JSON array</small>
                                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="formatJsonField('input_<?= htmlspecialchars($key) ?>')">Format JSON</button>
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="text" name="settings[<?= htmlspecialchars($key) ?>]" 
                                                                   class="form-control form-control-sm" 
                                                                   value="<?= htmlspecialchars($val) ?>" 
                                                                   <?= $isEditable ? '' : 'readonly' ?> required>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$is_readonly): ?>
                    <div class="card p-3 mb-5 shadow-sm bg-white border-primary">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock me-1"></i>Commit Regulatory Modifications</h6>
                                <p class="text-muted small mb-0">Changes take effect immediately across all condensation engines, bluebooks, and OBE calculations for regulation <?= htmlspecialchars($activeRegulation) ?>.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetForm()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                                </button>
                                <button type="submit" class="btn btn-primary px-4 fw-bold">
                                    <i class="bi bi-save me-1"></i>Save All Settings (<?= htmlspecialchars($activeRegulation) ?>)
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- 2. Audit Trail & Change Log Panel -->
        <div class="tab-pane fade" id="audit-pane" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2"></i>Academic Parameter Audit Trail</h5>
                        <p class="text-muted small mb-0">Historical log of all parameter changes, previous values, actor ID, and IP addresses.</p>
                    </div>
                    <span class="badge bg-primary"><?= count($auditLogs) ?> entries</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 170px;">Timestamp</th>
                                    <th>Regulation</th>
                                    <th>Setting Key</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                    <th>Modified By</th>
                                    <th class="pe-3">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No modifications recorded in the audit trail for <?= htmlspecialchars($activeRegulation) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td class="ps-3 small font-monospace"><?= htmlspecialchars($log['changed_at']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($log['regulation_code']) ?></span></td>
                                            <td class="fw-bold text-primary font-monospace small"><?= htmlspecialchars($log['setting_key']) ?></td>
                                            <td class="small font-monospace text-danger text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['old_value'] ?? '') ?>">
                                                <?= htmlspecialchars($log['old_value'] ?? 'NULL') ?>
                                            </td>
                                            <td class="small font-monospace text-success text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['new_value']) ?>">
                                                <?= htmlspecialchars($log['new_value']) ?>
                                            </td>
                                            <td class="small">
                                                <?= htmlspecialchars($log['user_name'] ?? ('User #' . ($log['changed_by'] ?? 'System'))) ?>
                                            </td>
                                            <td class="pe-3 small font-monospace text-muted"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Regulatory Simulator Panel -->
        <div class="tab-pane fade" id="calculator-pane" role="tabpanel">
            <div class="row g-4">
                <!-- Mid Mark Condensation Simulator -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-calculator-fill me-2"></i>Mid Exam Condensation Simulator</h5>
                            <small class="text-muted">Simulate Section 9(a)(v) 80/20 formula with active settings</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Mid Assessment 1 (out of 30):</label>
                                    <input type="number" step="0.5" id="sim_mid1" class="form-control" value="28">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Mid Assessment 2 (out of 30):</label>
                                    <input type="number" step="0.5" id="sim_mid2" class="form-control" value="21">
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="simulateCia()">
                                Calculate Final CIA
                            </button>
                            <div class="alert alert-light border p-3" id="sim_cia_result">
                                <div><strong>Best Mid:</strong> <span id="res_best">28</span> (x <span id="res_better_wt">0.80</span> = <span id="res_better_val">22.4</span>)</div>
                                <div><strong>Lower Mid:</strong> <span id="res_lesser">21</span> (x <span id="res_lesser_wt">0.20</span> = <span id="res_lesser_val">4.2</span>)</div>
                                <hr class="my-2">
                                <div class="fw-bold text-success fs-5">Final CIA Total: <span id="res_final_cia">26.6</span> / 30</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Compliance Simulator -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-info"><i class="bi bi-person-check-fill me-2"></i>Attendance Compliance Check</h5>
                            <small class="text-muted">Simulate Section 17 attendance eligibility & condonation criteria</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Aggregate Attendance %:</label>
                                    <input type="number" step="0.1" id="sim_att_agg" class="form-control" value="68.5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Minimum Course %:</label>
                                    <input type="number" step="0.1" id="sim_att_sub" class="form-control" value="45.0">
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-info btn-sm mb-3" onclick="simulateAttendance()">
                                Evaluate Eligibility
                            </button>
                            <div class="alert alert-light border p-3" id="sim_att_result">
                                <div id="res_att_badge" class="badge bg-warning text-dark fs-6 mb-2">CONDONATION</div>
                                <div id="res_att_reason" class="text-muted small">Aggregate attendance (68.5%) falls in condonable bracket (65% - 75%).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Client-Side Validation
document.getElementById('academicSettingsForm')?.addEventListener('submit', function(e) {
    const betterWeight = parseFloat(document.getElementById('input_theory_mid_better_weight')?.value || 0.8);
    const lesserWeight = parseFloat(document.getElementById('input_theory_mid_lesser_weight')?.value || 0.2);
    const weightSum = Math.round((betterWeight + lesserWeight) * 100) / 100;
    
    if (weightSum !== 1.0) {
        e.preventDefault();
        alert('Validation Error: Mid Better Weight (' + betterWeight + ') + Lesser Weight (' + lesserWeight + ') must equal exactly 1.00 (Current: ' + weightSum + ')');
        return false;
    }

    const ciaWeight = parseFloat(document.getElementById('input_attainment_direct_cia_weight')?.value || 0.3);
    const seeWeight = parseFloat(document.getElementById('input_attainment_direct_see_weight')?.value || 0.7);
    const coWeightSum = Math.round((ciaWeight + seeWeight) * 100) / 100;
    
    if (coWeightSum !== 1.0) {
        e.preventDefault();
        alert('Validation Error: Direct CIA Weight (' + ciaWeight + ') + SEE Weight (' + seeWeight + ') must equal exactly 1.00 (Current: ' + coWeightSum + ')');
        return false;
    }

    const minSub = parseFloat(document.getElementById('input_attendance_min_subject_pct')?.value || 40);
    const condoneFloor = parseFloat(document.getElementById('input_attendance_condone_floor_pct')?.value || 65);
    const minAgg = parseFloat(document.getElementById('input_attendance_min_aggregate_pct')?.value || 75);

    if (minSub > condoneFloor || condoneFloor > minAgg || minAgg > 100 || minSub < 0) {
        e.preventDefault();
        alert('Validation Error: Attendance rules must satisfy: 0 <= Min Subject (' + minSub + '%) <= Condonation Floor (' + condoneFloor + '%) <= Min Aggregate (' + minAgg + '%) <= 100%');
        return false;
    }

    // Validate JSON fields
    const jsonFields = ['input_grade_bands_json', 'input_class_award_json'];
    for (const fId of jsonFields) {
        const field = document.getElementById(fId);
        if (field) {
            try {
                JSON.parse(field.value);
            } catch (err) {
                e.preventDefault();
                alert('Invalid JSON in field ' + fId + ': ' + err.message);
                field.focus();
                return false;
            }
        }
    }
});

function formatJsonField(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    try {
        const parsed = JSON.parse(el.value);
        el.value = JSON.stringify(parsed, null, 2);
    } catch (e) {
        alert('JSON syntax error: ' + e.message);
    }
}

function resetForm() {
    if (confirm('Are you sure you want to reset any unsaved changes in this form?')) {
        document.getElementById('academicSettingsForm').reset();
    }
}

function simulateCia() {
    const m1 = parseFloat(document.getElementById('sim_mid1').value || 0);
    const m2 = parseFloat(document.getElementById('sim_mid2').value || 0);
    const bWt = parseFloat(document.getElementById('input_theory_mid_better_weight')?.value || 0.8);
    const lWt = parseFloat(document.getElementById('input_theory_mid_lesser_weight')?.value || 0.2);

    const best = Math.max(m1, m2);
    const lesser = Math.min(m1, m2);
    const bVal = Math.round(best * bWt * 100) / 100;
    const lVal = Math.round(lesser * lWt * 100) / 100;
    const finalCia = Math.round((bVal + lVal) * 100) / 100;

    document.getElementById('res_best').textContent = best;
    document.getElementById('res_better_wt').textContent = bWt.toFixed(2);
    document.getElementById('res_better_val').textContent = bVal.toFixed(1);
    document.getElementById('res_lesser').textContent = lesser;
    document.getElementById('res_lesser_wt').textContent = lWt.toFixed(2);
    document.getElementById('res_lesser_val').textContent = lVal.toFixed(1);
    document.getElementById('res_final_cia').textContent = finalCia.toFixed(1);
}

function simulateAttendance() {
    const agg = parseFloat(document.getElementById('sim_att_agg').value || 0);
    const sub = parseFloat(document.getElementById('sim_att_sub').value || 0);
    const minSub = parseFloat(document.getElementById('input_attendance_min_subject_pct')?.value || 40);
    const condoneFloor = parseFloat(document.getElementById('input_attendance_condone_floor_pct')?.value || 65);
    const minAgg = parseFloat(document.getElementById('input_attendance_min_aggregate_pct')?.value || 75);

    const badge = document.getElementById('res_att_badge');
    const reason = document.getElementById('res_att_reason');

    if (sub < minSub) {
        badge.className = 'badge bg-danger fs-6 mb-2';
        badge.textContent = 'DETAINED';
        reason.textContent = 'Individual course attendance (' + sub + '%) is below mandatory threshold (' + minSub + '%).';
    } else if (agg >= minAgg) {
        badge.className = 'badge bg-success fs-6 mb-2';
        badge.textContent = 'ELIGIBLE';
        reason.textContent = 'Aggregate attendance (' + agg + '%) satisfies autonomous regulations (>=' + minAgg + '%).';
    } else if (agg >= condoneFloor) {
        badge.className = 'badge bg-warning text-dark fs-6 mb-2';
        badge.textContent = 'CONDONATION REQUIRED';
        reason.textContent = 'Aggregate attendance (' + agg + '%) falls within the condonable bracket (' + condoneFloor + '% - ' + minAgg + '%). Medical/official proof required.';
    } else {
        badge.className = 'badge bg-danger fs-6 mb-2';
        badge.textContent = 'DETAINED';
        reason.textContent = 'Aggregate attendance (' + agg + '%) is below the absolute condonation floor (' + condoneFloor + '%).';
    }
}
</script>

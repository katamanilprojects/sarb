<?php require_once __DIR__ . '/../core/ReportValue.php'; ?>
<div class="tab-pane fade <?= $tab_department_active; ?>" id="department" role="tabpanel">
    <form class="mt-3" id="selectionForm" method="post" action="superadminpopso.php">
        <input type="hidden" name="active_tab" value="department">
        
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Select Criteria</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="acad_year">Academic Year: <span class="text-danger">*</span></label>
                            <select name="acad_year" id="acad_year" class="form-select" required>
                                <option value="">Select Academic Year</option>
                                <?php if (!empty($academic_years)): foreach ($academic_years as $year): ?>
                                    <option value="<?= htmlspecialchars($year['acad_year']); ?>" <?= $year['acad_year'] == $acad_year ? 'selected' : ''; ?>><?= htmlspecialchars($year['acad_year']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="prog_id_dept">Program: <span class="text-danger">*</span></label>
                            <select name="prog_id" id="prog_id_dept" class="form-select" required>
                                <option value="">Select Program</option>
                                <?php if (isset($programs['data']) && !empty($programs['data'])): foreach ($programs['data'] as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog['id']); ?>" <?= $prog['id'] == $prog_id ? 'selected' : ''; ?>><?= htmlspecialchars($prog['prog_shortname']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="dept_id_dept">Department: <span class="text-danger">*</span></label>
                            <select name="dept_id" id="dept_id_dept" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php if (isset($departments['data']) && !empty($departments['data'])): foreach ($departments['data'] as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['id']); ?>" <?= $dept['id'] == $dept_id ? 'selected' : ''; ?>><?= htmlspecialchars($dept['dept_shortname']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="regulation">Regulation: <span class="text-danger">*</span></label>
                            <input type="text" name="regulation" id="regulation" class="form-control" value="<?= htmlspecialchars($regulation); ?>" placeholder="e.g., R20" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label><i class="fas fa-check-square"></i> Specializations (select all that apply): <span class="text-danger">*</span></label>
                            <div id="specializations-container-dept" class="border rounded p-3 bg-light" style="min-height: 120px; max-height: 200px; overflow-y: auto;">
                                <small class="text-muted">Please select a program and department first.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="action" value="fetch_details" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Manage POs/PSOs
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <?php if ($show_details_for_dept_wise && !empty($spec_ids)): ?>
        <hr class="my-4">
        <?php require __DIR__ . '/popso_management_forms.php'; ?>

        <?php if (\FeatureManager::isModuleEnabled('MOD_PO_PSO_ATTAINMENT')): ?>
            <?php
            require_once __DIR__ . '/../services/BatchOBEService.php';
            $obeService = \Services\BatchOBEService::getInstance();
            $articulation = $obeService->computePOPSOArticulationMatrix(0, (int)$prog_id, (int)$dept_id);
            $t3_2 = $articulation['table_3_2'] ?? [];
            $t3_3_2 = $articulation['table_3_3_2'] ?? [];
            $posList = $articulation['pos'] ?? [];
            ?>
            <hr class="my-4">
            <!-- NBA Criterion 3.2: Program Articulation Matrix -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i> NBA Table 3.2: Program Articulation Matrix (Course–PO/PSO Mapping)
                    </h5>
                    <span class="badge bg-light text-primary fw-bold">NBA Criterion 3.2</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1"></i> Average correlation levels computed across mapped Course Outcomes (COs) for each course: 
                        <strong>1 = Slight (Low) | 2 = Moderate (Medium) | 3 = Substantial (High)</strong>. Empty cell indicates no correlation.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover text-center align-middle" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 60px;">Course</th>
                                    <th style="min-width: 90px;">Code</th>
                                    <th class="text-start" style="min-width: 220px;">Course Title</th>
                                    <th style="min-width: 60px;">Sem</th>
                                    <?php for ($p = 1; $p <= 12; $p++): ?>
                                        <th style="min-width: 45px;">PO<?= $p; ?></th>
                                    <?php endfor; ?>
                                    <?php for ($ps = 1; $ps <= 3; $ps++): ?>
                                        <th style="min-width: 45px;" class="table-info">PSO<?= $ps; ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($t3_2['rows'])): ?>
                                    <?php foreach ($t3_2['rows'] as $cRow): ?>
                                        <tr>
                                            <td class="fw-bold text-secondary"><?= htmlspecialchars($cRow['course_index']); ?></td>
                                            <td><code><?= htmlspecialchars($cRow['subcode']); ?></code></td>
                                            <td class="text-start"><?= htmlspecialchars($cRow['course_name']); ?></td>
                                            <td><?= htmlspecialchars($cRow['yearsem'] ?? '—'); ?></td>
                                            <?php for ($p = 1; $p <= 12; $p++): 
                                                $val = $cRow['mappings']["PO$p"] ?? null;
                                            ?>
                                                <td><?= ($val !== null && $val > 0) ? htmlspecialchars(number_format((float)$val, 1)) : '<span class="text-muted">-</span>'; ?></td>
                                            <?php endfor; ?>
                                            <?php for ($ps = 1; $ps <= 3; $ps++): 
                                                $val = $cRow['mappings']["PSO$ps"] ?? null;
                                            ?>
                                                <td class="table-info"><?= ($val !== null && $val > 0) ? htmlspecialchars(number_format((float)$val, 1)) : '<span class="text-muted">-</span>'; ?></td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="20" class="text-center text-muted py-3">No curriculum subjects or CO mappings available for this selection.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-secondary fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">Program Articulation Average</td>
                                    <?php for ($p = 1; $p <= 12; $p++): 
                                        $avg = $t3_2['averages']["PO$p"] ?? null;
                                    ?>
                                        <td><?= ($avg !== null && $avg > 0) ? htmlspecialchars(number_format((float)$avg, 2)) : '-'; ?></td>
                                    <?php endfor; ?>
                                    <?php for ($ps = 1; $ps <= 3; $ps++): 
                                        $avg = $t3_2['averages']["PSO$ps"] ?? null;
                                    ?>
                                        <td class="table-info"><?= ($avg !== null && $avg > 0) ? htmlspecialchars(number_format((float)$avg, 2)) : '-'; ?></td>
                                    <?php endfor; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- NBA Criterion 3.3.2: Program Outcome Attainment Matrix -->
            <div class="card shadow-sm mb-4 border-success">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i> NBA Table 3.3.2: PO/PSO Attainment Matrix & Indirect Exit Survey Scores
                    </h5>
                    <span class="badge bg-light text-success fw-bold">NBA Criterion 3.3</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="fas fa-calculator me-1"></i> Blended OBE Attainment Model: 
                        <strong>Final Attainment = (0.80 &times; Direct Course Attainment) + (0.20 &times; Indirect Student CES Exit Survey)</strong>.
                        Target Threshold: 2.00 / 3.00 (66.7%).
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" style="font-size: 0.9rem;">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 80px;">Outcome</th>
                                    <th>Program Outcome Description</th>
                                    <th style="width: 110px;">Direct (80%)</th>
                                    <th style="width: 140px;">Indirect CES (20%)</th>
                                    <th style="width: 120px;">Overall (3.0M)</th>
                                    <th style="width: 100px;">Target</th>
                                    <th style="width: 100px;">Gap</th>
                                    <th style="width: 130px;">Compliance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($t3_3_2['rows'])): ?>
                                    <?php foreach ($t3_3_2['rows'] as $poRow): ?>
                                        <tr>
                                            <td class="text-center fw-bold">
                                                <span class="badge <?= ($poRow['type'] === 'PSO') ? 'bg-info text-dark' : 'bg-primary'; ?>">
                                                    <?= htmlspecialchars($poRow['code']); ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($poRow['description']); ?></td>
                                            <td class="text-center"><?= htmlspecialchars(\College\ReportValue::format($poRow['direct_score'])); ?></td>
                                            <td class="text-center">
                                                <?= htmlspecialchars(\College\ReportValue::format($poRow['indirect_score'])); ?>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($poRow['indirect_source']); ?></div>
                                            </td>
                                            <td class="text-center fw-bold fs-6 text-primary">
                                                <?= htmlspecialchars(\College\ReportValue::format($poRow['overall_attainment'])); ?>
                                            </td>
                                            <td class="text-center"><?= htmlspecialchars(number_format((float)$poRow['target_score'], 2)); ?></td>
                                            <td class="text-center fw-bold <?= ($poRow['gap'] > 0.05) ? 'text-danger' : 'text-success'; ?>">
                                                <?= ($poRow['gap'] > 0) ? '+' : ''; ?><?= htmlspecialchars(\College\ReportValue::format($poRow['gap'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($poRow['status'] === 'ATTAINED'): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> ATTAINED</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> GAP IDENTIFIED</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>


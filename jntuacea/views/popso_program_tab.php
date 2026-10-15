<div class="tab-pane fade <?= $tab_program_active; ?>" id="program" role="tabpanel">
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle"></i> <strong>Program-wise PO Management:</strong> POs added here will be applied to all active specializations under the selected program.
    </div>
    
    <form class="mt-3" method="post" action="superadminpopso.php">
        <input type="hidden" name="action" value="save_program_pos">
        <input type="hidden" name="active_tab" value="program">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="acad_year_prog">Academic Year: <span class="text-danger">*</span></label>
                    <select name="acad_year" id="acad_year_prog" class="form-select" required>
                        <option value="">Select Academic Year</option>
                        <?php if (!empty($academic_years)): foreach ($academic_years as $year): ?>
                            <option value="<?= htmlspecialchars($year['acad_year']); ?>"><?= htmlspecialchars($year['acad_year']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="regulation_prog">Regulation: <span class="text-danger">*</span></label>
                    <input type="text" name="regulation" id="regulation_prog" class="form-control" placeholder="e.g., R20" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="prog_id_prog">Program: <span class="text-danger">*</span></label>
                    <select name="prog_id" id="prog_id_prog" class="form-select" required>
                        <option value="">Select Program</option>
                        <?php if (isset($programs['data']) && !empty($programs['data'])): foreach ($programs['data'] as $prog): ?>
                            <option value="<?= htmlspecialchars($prog['id']); ?>"><?= htmlspecialchars($prog['prog_shortname']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <hr class="my-4">
        
        <h5 class="mb-3">Program Outcomes</h5>
        <div id="program-po-container">
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
        </div>
        
        <div class="mt-3">
            <button type="button" class="btn btn-secondary" onclick="addProgramPoRow()">
                <i class="fas fa-plus"></i> Add PO
            </button>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save POs for Program
            </button>
        </div>
    </form>
</div>

<div class="tab-pane fade <?= $tab_overview_active; ?>" id="overview" role="tabpanel">
    <div class="mt-3">
        <div class="form-group col-md-4">
            <label for="overview_acad_year">Select Academic Year:</label>
            <select name="overview_acad_year" id="overview_acad_year" class="form-select" required>
                <option value="">Select Academic Year</option>
                <?php if (!empty($academic_years)): foreach ($academic_years as $year): ?>
                    <option value="<?= htmlspecialchars($year['acad_year']); ?>" <?= ($active_tab == 'overview' && empty($acad_year) && $year['acad_year'] == date('Y') . '-' . (date('Y') + 1)) ? 'selected' : ''; ?>><?= htmlspecialchars($year['acad_year']); ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div id="overview-table-container" class="mt-3">
            <p class="text-muted">Select an Academic Year to view the PO/PSO overview.</p>
        </div>
    </div>
</div>

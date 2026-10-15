<div class="tab-pane fade <?= $tab_copy_active; ?>" id="copy" role="tabpanel">
    <form class="mt-3" id="copyPoPsoForm" method="post" action="superadminpopso.php" onsubmit="return confirmCopyAction();">
        <input type="hidden" name="action" value="copy_from_previous_year">
        <input type="hidden" name="active_tab" value="copy">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="acad_year_copy">Target Academic Year:</label>
                    <select name="acad_year" id="acad_year_copy" class="form-select" required>
                        <option value="">Select Academic Year</option>
                        <?php if (!empty($academic_years)): foreach ($academic_years as $year): ?>
                            <option value="<?= htmlspecialchars($year['acad_year']); ?>"><?= htmlspecialchars($year['acad_year']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="regulation_copy">Regulation:</label>
                    <input type="text" name="regulation" id="regulation_copy" class="form-control" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="prog_id_copy">Program:</label>
                    <select name="prog_id" id="prog_id_copy" class="form-select" required>
                        <option value="">Select Program</option>
                        <?php if (isset($programs['data']) && !empty($programs['data'])): foreach ($programs['data'] as $prog): ?>
                            <option value="<?= htmlspecialchars($prog['id']); ?>"><?= htmlspecialchars($prog['prog_shortname']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        <p class="mt-3 text-muted">This will copy all POs and PSOs from the previous academic year (e.g., from 2023-2024 if you enter 2024-2025) for the selected program and regulation to the target academic year. **Existing POs/PSOs for the target year/regulation/specialization will be overwritten.**</p>
        
        <h5 class="mt-4">Preview of data to be copied from previous year:</h5>
        <div id="copy-preview-container" class="border p-3 mb-3" style="max-height: 300px; overflow-y: auto; background-color: #f8f9fa;">
            <p class="text-muted">Select a Target Academic Year, Regulation, and Program to see a preview of the POs/PSOs that will be copied.</p>
        </div>

        <button type="submit" class="btn btn-warning mt-2">Copy POs/PSOs</button>
    </form>
</div>

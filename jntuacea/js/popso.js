let activeTab = '';

document.addEventListener('DOMContentLoaded', function() {
    
    var tabEl = document.querySelector(`#myTab button[data-bs-target="#${activeTab}"]`);
    if (tabEl) {
        var tab = new bootstrap.Tab(tabEl);
        tab.show();
    }

    if (activeTab === 'department') {
        fetchSpecializationsForDept();
    }
    
    if (activeTab === 'overview') {
        const overviewAcadYearSelect = document.getElementById('overview_acad_year');
        if (overviewAcadYearSelect.value === '') {
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            const defaultAcadYear = `${currentYear}-${nextYear}`;
            let found = false;
            for (let i = 0; i < overviewAcadYearSelect.options.length; i++) {
                if (overviewAcadYearSelect.options[i].value === defaultAcadYear) {
                    overviewAcadYearSelect.value = defaultAcadYear;
                    found = true;
                    break;
                }
            }
            if (!found && overviewAcadYearSelect.options.length > 1) {
                overviewAcadYearSelect.value = overviewAcadYearSelect.options[1].value; 
            }
        }
        if (overviewAcadYearSelect.value !== '') {
             fetchPoPsoOverview(overviewAcadYearSelect.value);
        }
    }
    if(activeTab === '') {
        const overviewAcadYearSelect = document.getElementById('overview_acad_year');
        if (overviewAcadYearSelect.value !== '') {
             fetchPoPsoOverview(overviewAcadYearSelect.value);
        }
    }
});

function setActiveTab(tabName) {
    activeTab = tabName;
}

document.getElementById('dept_id_dept').addEventListener('change', fetchSpecializationsForDept);
document.getElementById('prog_id_dept').addEventListener('change', fetchSpecializationsForDept);

function fetchSpecializationsForDept() {
    const progId = document.getElementById('prog_id_dept').value;
    const deptId = document.getElementById('dept_id_dept').value;
    const container = document.getElementById('specializations-container-dept');
    const selectedSpecs = window.selectedSpecIds || [];

    if (progId && deptId) {
        container.innerHTML = '<small>Loading active specializations...</small>';
        fetch(`ajax_handler.php?action=get_specializations&prog_id=${progId}&dept_id=${deptId}`)
            .then(response => response.json())
            .then(data => {
                let checkboxes = '';
                if (data.length > 0) {
                    data.forEach(spec => {
                        const isChecked = selectedSpecs.includes(String(spec.id)) ? 'checked' : '';
                        checkboxes += `<div class="form-check"><input class="form-check-input" type="checkbox" name="spec_ids[]" id="spec-${spec.id}" value="${spec.id}" ${isChecked}><label class="form-check-label" for="spec-${spec.id}">${spec.spec_shortname}</label></div>`;
                    });
                } else {
                    checkboxes = '<small>No active specializations found for this combination.</small>';
                }
                container.innerHTML = checkboxes;
            }).catch(error => {
                container.innerHTML = '<small>Error loading specializations.</small>';
            });
    } else {
        container.innerHTML = '<small>Please select a program and department first.</small>';
    }
}

function addPoRow(containerId) {
    const container = document.getElementById(containerId);
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 align-items-center';
    const poNumber = container.children.length + 1;
    newRow.innerHTML = `
        <div class="col-md-2">
            <input type="text" name="po_code[]" class="form-control" placeholder="e.g., PO${poNumber}" required>
        </div>
        <div class="col-md-9">
            <textarea name="po_description[]" class="form-control" rows="2" placeholder="Enter PO description" required></textarea>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
}

function addProgramPoRow() {
    const container = document.getElementById('program-po-container');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 align-items-center';
    const poNumber = container.children.length + 1;
    newRow.innerHTML = `
        <div class="col-md-2">
            <input type="text" name="po_code[]" class="form-control" placeholder="e.g., PO${poNumber}" required>
        </div>
        <div class="col-md-9">
            <textarea name="po_description[]" class="form-control" rows="2" placeholder="Enter PO description" required></textarea>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
}

function addPsoRow(specId) {
    const container = document.getElementById(`pso-container-${specId}`);
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 align-items-center';
    const psoNumber = container.children.length + 1;
    newRow.innerHTML = `
        <div class="col-md-2">
            <input type="text" name="pso_code[]" class="form-control" placeholder="e.g., PSO${psoNumber}" required>
        </div>
        <div class="col-md-9">
            <textarea name="pso_description[]" class="form-control" rows="2" placeholder="Enter PSO description" required></textarea>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
}

function removeRow(button) {
    if (confirm('Are you sure you want to remove this item?')) {
        button.closest('.row').remove();
    }
}

function togglePoEditMode() {
    const viewMode = document.getElementById('po-view-mode');
    const editMode = document.getElementById('po-edit-mode');
    const btnText = document.getElementById('po-edit-btn-text');
    
    if (editMode.style.display === 'none') {
        if (viewMode) viewMode.style.display = 'none';
        editMode.style.display = 'block';
        btnText.textContent = 'View Mode';
    } else {
        if (viewMode) viewMode.style.display = 'block';
        editMode.style.display = 'none';
        btnText.textContent = 'Edit Mode';
    }
}

function togglePsoEditMode(specId) {
    const viewMode = document.getElementById(`pso-view-mode-${specId}`);
    const editMode = document.getElementById(`pso-edit-mode-${specId}`);
    const btnText = document.getElementById(`pso-edit-btn-text-${specId}`);
    
    if (editMode.style.display === 'none') {
        if (viewMode) viewMode.style.display = 'none';
        editMode.style.display = 'block';
        btnText.textContent = 'View Mode';
    } else {
        if (viewMode) viewMode.style.display = 'block';
        editMode.style.display = 'none';
        btnText.textContent = 'Edit Mode';
    }
}

document.getElementById('acad_year_copy').addEventListener('change', fetchCopyPreview);
document.getElementById('regulation_copy').addEventListener('input', fetchCopyPreview);
document.getElementById('prog_id_copy').addEventListener('change', fetchCopyPreview);

function fetchCopyPreview() {
    const targetAcadYear = document.getElementById('acad_year_copy').value;
    const regulation = document.getElementById('regulation_copy').value;
    const progId = document.getElementById('prog_id_copy').value;
    const previewContainer = document.getElementById('copy-preview-container');

    if (targetAcadYear && regulation && progId) {
        previewContainer.innerHTML = '<p class="text-muted">Loading preview data...</p>';
        fetch(`ajax_handler.php?action=get_copy_preview_data&target_acad_year=${targetAcadYear}&regulation=${regulation}&prog_id=${progId}`)
            .then(response => response.json())
            .then(data => {
                let html = `<h6>Source: Previous Academic Year (e.g., ${parseInt(targetAcadYear.split('-')[0])-1}-${parseInt(targetAcadYear.split('-')[1])-1})</h6>`;
                html += `<p>${data.message}</p>`;

                if (data.status === 1 && Object.keys(data.data).length > 0) {
                    for (const specShortname in data.data) {
                        html += `<strong>Specialization: ${specShortname}</strong>`;
                        html += `<table class="table table-sm table-bordered mt-2"><thead><tr><th>Type</th><th>Code</th><th>Description</th></tr></thead><tbody>`;
                        data.data[specShortname].forEach(item => {
                            html += `<tr><td>${item.po_pso}</td><td>${item.code}</td><td>${item.description}</td></tr>`;
                        });
                        html += `</tbody></table>`;
                    }
                } else if (data.status === 0 && data.message) {
                } else {
                    html += '<p>No POs/PSOs found for the previous academic year with the selected criteria.</p>';
                }
                previewContainer.innerHTML = html;
            }).catch(error => {
                previewContainer.innerHTML = '<p class="text-danger">Error loading preview data.</p>';
                console.error('Error fetching copy preview:', error);
            });
    } else {
        previewContainer.innerHTML = '<p class="text-muted">Select a Target Academic Year, Regulation, and Program to see a preview of the POs/PSOs that will be copied.</p>';
    }
}

function confirmCopyAction() {
    const targetAcadYear = document.getElementById('acad_year_copy').value;
    const regulation = document.getElementById('regulation_copy').value;
    const progId = document.getElementById('prog_id_copy').value;

    if (!targetAcadYear || !regulation || !progId) {
        alert('Please select a Target Academic Year, Regulation, and Program.');
        return false;
    }

    const yearParts = targetAcadYear.split('-');
    const prevYearStart = parseInt(yearParts[0]) - 1;
    const prevYearEnd = parseInt(yearParts[1]) - 1;
    const prevAcadYear = `${prevYearStart}-${prevYearEnd}`;

    return confirm(`This will copy all POs and PSOs from ${prevAcadYear} (Regulation ${regulation}) to ${targetAcadYear} (Regulation ${regulation}) for the selected program's active specializations. Any existing POs/PSOs for the target year/regulation/specialization will be overwritten. Are you sure you want to proceed?`);
}

document.getElementById('overview_acad_year').addEventListener('change', function() {
    const acadYear = this.value;
    if (acadYear) {
        fetchPoPsoOverview(acadYear);
    } else {
        document.getElementById('overview-table-container').innerHTML = '<p class="text-muted">Select an Academic Year to view the PO/PSO overview.</p>';
    }
});

function fetchPoPsoOverview(acadYear) {
    const overviewTableContainer = document.getElementById('overview-table-container');
    overviewTableContainer.innerHTML = '<p class="text-muted">Loading overview data...</p>';

    fetch(`ajax_handler.php?action=get_po_pso_overview&acad_year=${acadYear}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 1 && data.data.length > 0) {
                let tableHtml = `
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Specialization</th>
                                <th>Regulation</th>
                                <th>POs Status</th>
                                <th>PSOs Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.data.forEach(item => {
                    const poBadge = item.po_status === 'Added' ? '<span class="badge bg-success">Added</span>' : '<span class="badge bg-danger">Missing</span>';
                    const psoBadge = item.pso_status === 'Added' ? '<span class="badge bg-success">Added</span>' : '<span class="badge bg-danger">Missing</span>';
                    
                    const editLink = `<a href="#department" class="btn btn-sm btn-info" 
                                        onclick="prefillDepartmentTab('${acadYear}', '${item.regulation}', '${item.prog_id}', '${item.dept_id}', '${item.spec_id}'); 
                                        new bootstrap.Tab(document.getElementById('department-tab')).show(); return false;">Edit</a>`;

                    tableHtml += `
                        <tr>
                            <td>${item.prog_shortname}</td>
                            <td>${item.spec_shortname}</td>
                            <td>${item.regulation}</td>
                            <td>${poBadge}</td>
                            <td>${psoBadge}</td>
                            <td>${editLink}</td>
                        </tr>
                    `;
                });
                tableHtml += `
                        </tbody>
                    </table>
                `;
                overviewTableContainer.innerHTML = tableHtml;
            } else {
                overviewTableContainer.innerHTML = '<p class="text-muted">No PO/PSO data found for the selected academic year for any active specializations.</p>';
            }
        }).catch(error => {
            overviewTableContainer.innerHTML = '<p class="text-danger">Error loading PO/PSO overview.</p>';
            console.error('Error fetching PO/PSO overview:', error);
        });
}

function prefillDepartmentTab(acadYear, regulation, progId, deptId, specId) {
    document.querySelector('#selectionForm input[name="active_tab"]').value = 'department';

    document.getElementById('acad_year').value = acadYear;
    document.getElementById('regulation').value = regulation;
    document.getElementById('prog_id_dept').value = progId;
    document.getElementById('dept_id_dept').value = deptId;
    
    const event = new Event('change');
    document.getElementById('prog_id_dept').dispatchEvent(event);
    document.getElementById('dept_id_dept').dispatchEvent(event);

    setTimeout(() => {
        const specCheckbox = document.querySelector(`input[name='spec_ids[]'][value='${specId}']`);
        if (specCheckbox) {
            specCheckbox.checked = true;
        }
        document.getElementById('selectionForm').submit();
    }, 500);
}

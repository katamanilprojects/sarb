<?php
function handleFormSubmission($superadmin) {
    $msg = '';
    $errmsg = '';
    $show_details_for_dept_wise = false;
    $active_tab = $_POST['active_tab'] ?? 'overview';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return compact('msg', 'errmsg', 'show_details_for_dept_wise', 'active_tab');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_program_pos':
            list($msg, $errmsg) = saveProgramPos($superadmin);
            break;
        case 'copy_from_previous_year':
            list($msg, $errmsg) = copyFromPreviousYear($superadmin);
            $active_tab = 'copy';
            break;
        case 'save_pos':
            list($msg, $errmsg, $show_details_for_dept_wise) = savePos($superadmin);
            $active_tab = 'department';
            break;
        case 'save_psos':
            list($msg, $errmsg, $show_details_for_dept_wise) = savePsos($superadmin);
            $active_tab = 'department';
            break;
        case 'fetch_details':
            if (empty($_POST['spec_ids'] ?? [])) {
                $errmsg = "Please select at least one specialization.";
            }
            $show_details_for_dept_wise = true;
            $active_tab = 'department';
            break;
    }
    
    return compact('msg', 'errmsg', 'show_details_for_dept_wise', 'active_tab');
}

function saveProgramPos($superadmin) {
    $acad_year = $_POST['acad_year'] ?? '';
    $regulation = strtoupper($_POST['regulation']) ?? '';
    $prog_id = $_POST['prog_id'] ?? '';
    
    if (empty($acad_year) || empty($regulation) || empty($prog_id)) {
        return ['', "Academic Year, Regulation, and Program are required."];
    }
    if (!isset($_POST['po_code']) || !isset($_POST['po_description'])) {
        return ['', "PO data is missing."];
    }
    
    $po_codes = $_POST['po_code'];
    $po_descriptions = $_POST['po_description'];
    $spec_res = $superadmin->getActiveSpecializationsByProgram($prog_id);
    
    if ($spec_res['status'] === 1 && !empty($spec_res['data'])) {
        $all_spec_ids = array_column($spec_res['data'], 'id');
        foreach ($all_spec_ids as $spec_id) {
            $superadmin->bulkDeletePoPso($acad_year, $regulation, $spec_id, 'PO');
            for ($i = 0; $i < count($po_codes); $i++) {
                if (!empty($po_codes[$i])) {
                    $data = [
                        'acad_year' => $acad_year,
                        'regulation' => $regulation,
                        'specid' => $spec_id,
                        'po_pso' => 'PO',
                        'orderid' => $i + 1,
                        'code' => $po_codes[$i],
                        'description' => $po_descriptions[$i]
                    ];
                    $superadmin->addOrUpdatePoPso($data);
                }
            }
        }
        return ["POs saved for all active specializations in the program.", ''];
    }
    return ['', "No active specializations found for the selected program, or failed to fetch."];
}

function copyFromPreviousYear($superadmin) {
    $acad_year = $_POST['acad_year'] ?? '';
    $regulation = strtoupper($_POST['regulation']) ?? '';
    $prog_id = $_POST['prog_id'] ?? '';
    
    $result = $superadmin->copyPoPsoFromPreviousYear($acad_year, $regulation, $prog_id);
    if ($result['status'] === 1) {
        return [$result['message'], ''];
    }
    return ['', $result['error']];
}

function savePos($superadmin) {
    $spec_ids = $_POST['spec_ids'] ?? [];
    $acad_year = $_POST['acad_year'] ?? '';
    $regulation = strtoupper($_POST['regulation']) ?? '';
    
    if (empty($spec_ids)) {
        return ['', "Please select at least one specialization.", false];
    }
    if (!isset($_POST['po_code']) || !isset($_POST['po_description'])) {
        return ['', "PO data is missing.", false];
    }
    
    $po_codes = $_POST['po_code'];
    $po_descriptions = $_POST['po_description'];
    
    foreach ($spec_ids as $spec_id) {
        $superadmin->bulkDeletePoPso($acad_year, $regulation, $spec_id, 'PO');
        for ($i = 0; $i < count($po_codes); $i++) {
            if (!empty($po_codes[$i])) {
                $data = [
                    'acad_year' => $acad_year,
                    'regulation' => $regulation,
                    'specid' => $spec_id,
                    'po_pso' => 'PO',
                    'orderid' => $i + 1,
                    'code' => $po_codes[$i],
                    'description' => $po_descriptions[$i]
                ];
                $superadmin->addOrUpdatePoPso($data);
            }
        }
    }
    return ["POs saved successfully for the selected specializations.", '', true];
}

function savePsos($superadmin) {
    $pso_spec_id = $_POST['pso_spec_id'] ?? '';
    $acad_year = $_POST['acad_year'] ?? '';
    $regulation = strtoupper($_POST['regulation']) ?? '';
    
    if (empty($pso_spec_id)) {
        return ['', "Specialization ID is required.", false];
    }
    if (!isset($_POST['pso_code']) || !isset($_POST['pso_description'])) {
        return ['', "PSO data is missing.", false];
    }
    
    $pso_codes = $_POST['pso_code'];
    $pso_descriptions = $_POST['pso_description'];
    
    $superadmin->bulkDeletePoPso($acad_year, $regulation, $pso_spec_id, 'PSO');
    
    for ($i = 0; $i < count($pso_codes); $i++) {
        if (!empty($pso_codes[$i])) {
            $data = [
                'acad_year' => $acad_year,
                'regulation' => $regulation,
                'specid' => $pso_spec_id,
                'po_pso' => 'PSO',
                'orderid' => $i + 1,
                'code' => $pso_codes[$i],
                'description' => $pso_descriptions[$i]
            ];
            $superadmin->addOrUpdatePoPso($data);
        }
    }
    return ["PSOs for Spec ID: " . htmlspecialchars($pso_spec_id) . " saved successfully.", '', true];
}

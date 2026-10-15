<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("superadmin.class.php");

if (isset($_GET['action'])) {
    $superadmin = new SuperAdmin();

    switch ($_GET['action']) {
        case 'get_specializations':
            if (isset($_GET['prog_id']) && isset($_GET['dept_id'])) {
                $prog_id = (int)$_GET['prog_id'];
                $dept_id = (int)$_GET['dept_id'];
                $specializations = $superadmin->getSpecializationsByProgramAndDept($prog_id, $dept_id);
                header('Content-Type: application/json');
                echo json_encode($specializations['data']);
            }
            break;
        case 'get_po_pso_overview':
            if (isset($_GET['acad_year'])) {
                $acad_year = $_GET['acad_year'];
                $overview_data = $superadmin->getPoPsoOverviewData($acad_year);
                header('Content-Type: application/json');
                echo json_encode($overview_data);
            }
            break;
        case 'get_copy_preview_data':
            if (isset($_GET['target_acad_year']) && isset($_GET['regulation']) && isset($_GET['prog_id'])) {
                $target_acad_year = $_GET['target_acad_year'];
                $regulation = $_GET['regulation'];
                $prog_id = (int)$_GET['prog_id'];

                $year_parts = explode('-', $target_acad_year);
                $prev_year_start = (int)$year_parts[0] - 1;
                $prev_year_end = (int)$year_parts[1] - 1;
                $previous_acad_year = $prev_year_start . '-' . $prev_year_end;

                $preview_data = ['status' => 0, 'data' => [], 'message' => ''];
                try {
                    $spec_res = $superadmin->getActiveSpecializationsByProgram($prog_id);
                    if ($spec_res['status'] === 0 || empty($spec_res['data'])) {
                        $preview_data['message'] = 'No active specializations found for the selected program, or failed to fetch.';
                        echo json_encode($preview_data);
                        exit();
                    }
                    $all_po_pso_for_preview = [];
                    foreach ($spec_res['data'] as $spec) {
                        $spec_id = $spec['id'];
                        $po_pso_data_res = $superadmin->getPoPso($previous_acad_year, $regulation, $spec_id);
                        if ($po_pso_data_res['status'] === 1 && !empty($po_pso_data_res['data'])) {
                            $all_po_pso_for_preview[$spec['spec_shortname']] = $po_pso_data_res['data'];
                        }
                    }
                    if (!empty($all_po_pso_for_preview)) {
                        $preview_data['status'] = 1;
                        $preview_data['data'] = $all_po_pso_for_preview;
                        $preview_data['message'] = "Previewing POs/PSOs from {$previous_acad_year} (Regulation {$regulation}) for the selected program's active specializations.";
                    } else {
                        $preview_data['message'] = "No POs/PSOs found for {$previous_acad_year} (Regulation {$regulation}) for active specializations in the selected program.";
                    }
                } catch (Exception $e) {
                    $preview_data['message'] = "Error fetching preview: " . $e->getMessage();
                }
                header('Content-Type: application/json');
                echo json_encode($preview_data);
            }
            break;
    }
}
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Regulations";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_or_update') {
        $data = [
            'id' => !empty($_POST['id']) ? (int)$_POST['id'] : null,
            'regulation' => strtoupper(trim($_POST['regulation'] ?? '')),
            'prog_id' => (int)($_POST['prog_id'] ?? 0),
            'start_year' => !empty($_POST['start_year']) ? (int)$_POST['start_year'] : null,
            'normal_duration_years' => (int)($_POST['normal_duration_years'] ?? 4),
            'max_duration_years' => (int)($_POST['max_duration_years'] ?? 8),
            'gap_year_extension_years' => (int)($_POST['gap_year_extension_years'] ?? 0),
            'total_semesters' => (int)($_POST['total_semesters'] ?? 8),
            'total_degree_credits' => (float)($_POST['total_degree_credits'] ?? 160.0),
            'lateral_entry_credits' => (float)($_POST['lateral_entry_credits'] ?? 0.0),
            'honors_credits' => (float)($_POST['honors_credits'] ?? 0.0),
            'minor_credits' => (float)($_POST['minor_credits'] ?? 0.0),
            'has_lateral_entry' => isset($_POST['has_lateral_entry']) ? 1 : 0,
            'has_honors' => isset($_POST['has_honors']) ? 1 : 0,
            'has_minors' => isset($_POST['has_minors']) ? 1 : 0,
            'has_gap_year' => isset($_POST['has_gap_year']) ? 1 : 0,
            'has_internal_improvement' => isset($_POST['has_internal_improvement']) ? 1 : 0,
            'effective_admitted_batch' => trim($_POST['effective_admitted_batch'] ?? ''),
            'les_effective_batch' => trim($_POST['les_effective_batch'] ?? '')
        ];
        $result = $superadmin->addOrUpdateRegulation($data);
        $msg = $result['message'] ?? $result['error'] ?? '';
    } elseif ($action === 'clone') {
        $sourceId = (int)($_POST['source_reg_id'] ?? 0);
        $targetId = (int)($_POST['target_reg_id'] ?? 0);
        $result = $superadmin->cloneRegulation($sourceId, $targetId);
        $msg = $result['message'] ?? $result['error'] ?? '';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $superadmin->deleteRegulation($id);
        $msg = $result['message'] ?? $result['error'] ?? '';
    }
}

$regulations = $superadmin->getAllRegulations()['data'] ?? [];
$programs = $superadmin->getAllPrograms()['data'] ?? [];
$edit_regulation = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($regulations as $regulation) {
        if ($regulation['id'] == $edit_id) {
            $edit_regulation = $regulation;
            break;
        }
    }
}

$page_action = 'superadminregulations.php';
require_once("views/manage_regulations.php");
require_once("superadminfooter.php");

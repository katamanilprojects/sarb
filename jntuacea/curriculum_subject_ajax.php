<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['academic_section', 'admin', 'superadmin', 'hod'])) {
    echo json_encode(['status' => 0, 'error' => 'Unauthorized access.']);
    exit();
}

require_once("curriculum_subject.class.php");
$obj = new CurriculumSubject();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if(in_array($action,['save_master_co','delete_master_co','save_master_matrix'],true)){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){\College\RequestSecurity::deny('Use a protected form to change curriculum records.',405);}
    require_once __DIR__.'/services/FeatureManager.php';
    \FeatureManager::requireWriteAccess('MOD_CO_PO');
}


if ($action === 'lookup_code') {
    $subcode = $_GET['subcode'] ?? ($_POST['subcode'] ?? '');
    $regId = (int)($_GET['reg_id'] ?? ($_POST['reg_id'] ?? 0));

    if (empty($subcode)) {
        echo json_encode(['status' => 0, 'error' => 'Subject code is required.']);
        exit();
    }

    $res = $obj->lookupSubjectByCodeAndReg($subcode, $regId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_regulations') {
    $progId = (int)($_GET['prog_id'] ?? ($_POST['prog_id'] ?? 0));
    $res = $obj->getRegulationsByProgramId($progId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_categories_and_types') {
    $regId = (int)($_GET['reg_id'] ?? ($_POST['reg_id'] ?? 0));
    $res = $obj->getCategoriesAndTypesByRegId($regId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_specializations') {
    $progId = (int)($_GET['prog_id'] ?? ($_POST['prog_id'] ?? 0));
    $res = $obj->getSpecializationsByProgramId($progId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_subjects_for_class') {
    $regId = (int)($_GET['reg_id'] ?? ($_POST['reg_id'] ?? 0));
    $specId = (int)($_GET['spec_id'] ?? ($_POST['spec_id'] ?? 0));
    $yearsem = trim($_GET['yearsem'] ?? ($_POST['yearsem'] ?? ''));

    $res = $obj->getSubjectsForClass($regId, $specId, $yearsem);
    echo json_encode($res);
    exit();
}

if ($action === 'get_master_cos') {
    $currSubId = (int)($_GET['curr_sub_id'] ?? ($_POST['curr_sub_id'] ?? 0));
    $res = $obj->getMasterCOs($currSubId);
    echo json_encode($res);
    exit();
}

if ($action === 'save_master_co') {
    $currSubId = (int)($_POST['curr_sub_id'] ?? 0);
    $coNumber = (int)($_POST['co_number'] ?? 0);
    $coDescription = trim($_POST['co_description'] ?? '');
    $bloomLevel = trim($_POST['bloom_level'] ?? 'L3-Apply');
    $targetThreshold = (float)($_POST['target_threshold_percent'] ?? 60.0);

    if ($currSubId <= 0 || $coNumber <= 0 || empty($coDescription)) {
        echo json_encode(['status' => 0, 'error' => 'Subject ID, CO number, and description are required.']);
        exit();
    }

    $res = $obj->addOrUpdateMasterCO($currSubId, $coNumber, $coDescription, $bloomLevel, $targetThreshold);
    echo json_encode($res);
    exit();
}

if ($action === 'delete_master_co') {
    $currSubId = (int)($_POST['curr_sub_id'] ?? 0);
    $coId = (int)($_POST['co_id'] ?? 0);

    if ($currSubId <= 0 || $coId <= 0) {
        echo json_encode(['status' => 0, 'error' => 'Invalid parameters for deletion.']);
        exit();
    }

    $res = $obj->deleteMasterCO($currSubId, $coId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_master_matrix') {
    $currSubId = (int)($_GET['curr_sub_id'] ?? ($_POST['curr_sub_id'] ?? 0));
    if ($currSubId <= 0) {
        echo json_encode(['status' => 0, 'error' => 'Curriculum Subject ID is required.']);
        exit();
    }
    $res = $obj->getMasterArticulationMatrix($currSubId);
    echo json_encode($res);
    exit();
}

if ($action === 'save_master_matrix') {
    $currSubId = (int)($_POST['curr_sub_id'] ?? 0);
    $mappings = $_POST['mapping'] ?? [];

    if ($currSubId <= 0) {
        echo json_encode(['status' => 0, 'error' => 'Curriculum Subject ID is required.']);
        exit();
    }

    $res = $obj->saveMasterArticulationMatrix($currSubId, $mappings);
    echo json_encode($res);
    exit();
}

echo json_encode(['status' => 0, 'error' => 'Invalid action.']);
exit();

<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Attendance Rules";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_or_update') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'reg_id' => $_POST['reg_id'] ?? '',
            'criteria' => $_POST['criteria'] ?? '',
            'operator1' => $_POST['operator1'] ?? '',
            'value1' => $_POST['value1'] ?? 0,
            'operator2' => $_POST['operator2'] ?? '',
            'value2' => $_POST['value2'] ?? 0
        ];
        $result = $superadmin->addOrUpdateAttendanceRule($data);
        $msg = $result['message'] ?? $result['error'] ?? '';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $superadmin->deleteAttendanceRule($id);
        $msg = $result['message'] ?? $result['error'] ?? '';
    }
}

$attendance_rules = $superadmin->getAllAttendanceRules()['data'] ?? [];
$regulations = $superadmin->getAllRegulations()['data'] ?? [];
$edit_rule = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($attendance_rules as $rule) {
        if ($rule['id'] == $edit_id) {
            $edit_rule = $rule;
            break;
        }
    }
}

$page_action = 'superadminattendancerules.php';
require_once("views/manage_attendance_rules.php");
require_once("superadminfooter.php");

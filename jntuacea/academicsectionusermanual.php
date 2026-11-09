<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$activeRole = $_SESSION['role'] ?? '';
$allowedRoles = ['superadmin', 'academic_section'];
if (!in_array($activeRole, $allowedRoles, true)) {
    header('Location: ./');
    exit();
}

\FeatureManager::requireUserManualAccess('academic_section');

$page_title = "User Manual";
match ($activeRole) {
    'superadmin' => require_once __DIR__ . '/superadminheader.php',
    default      => require_once __DIR__ . '/academicsectionheader.php',
};

$manualRole = 'academic_section';
require __DIR__ . '/views/partials/role_user_manual_view.php';
require_once __DIR__ . '/footer.php';

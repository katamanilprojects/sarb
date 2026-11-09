<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$activeRole = $_SESSION['role'] ?? '';
$allowedRoles = ['superadmin', 'admin'];
if (!in_array($activeRole, $allowedRoles, true)) {
    header('Location: ./');
    exit();
}

\FeatureManager::requireUserManualAccess('admin');

$page_title = "User Manual";
match ($activeRole) {
    'superadmin' => require_once __DIR__ . '/superadminheader.php',
    default      => require_once __DIR__ . '/adminheader.php',
};

$manualRole = 'admin';
require __DIR__ . '/views/partials/role_user_manual_view.php';
require_once __DIR__ . '/footer.php';

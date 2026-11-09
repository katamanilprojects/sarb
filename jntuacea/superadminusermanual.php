<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

\FeatureManager::requireUserManualAccess('superadmin');

$page_title = "User Manual";
require_once __DIR__ . '/superadminheader.php';

$manualRole = 'superadmin';
require __DIR__ . '/views/partials/role_user_manual_view.php';
require_once __DIR__ . '/footer.php';

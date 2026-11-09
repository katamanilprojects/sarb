<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/FeatureManager.php';

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_start();
}

if (PHP_SAPI !== 'cli') {
    $activeRole = $_SESSION['role'] ?? '';
    $allowedRoles = ['superadmin', 'academic_section'];
    if (!in_array($activeRole, $allowedRoles, true)) {
        header('Location: ./');
        exit();
    }
    \FeatureManager::requireUserManualAccess('academic_section');
}

require_once __DIR__ . '/services/UserManualService.php';

$download = (!empty($_GET['download']) && $_GET['download'] == '1');
$returnString = (PHP_SAPI === 'cli' && !empty($GLOBALS['__RETURN_MPDF_STRING__']));

return \Services\UserManualService::getInstance()->renderPdfForRole('academic_section', $download, $returnString);

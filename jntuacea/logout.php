<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!empty($_SESSION['userid'])) {
    require_once __DIR__ . "/dbcredentials.class.php";
    $dbCreds = new DBCredentials();
    $userId = $_SESSION['userid'] ?? 0;
    $username = $_SESSION['user'] ?? '';
    $role = $_SESSION['role'] ?? 'user';
    $dbCreds->dbActivityLog($userId, "Logout", "User $username logged out", $role, "AUTH", (string)$userId);
}
if(!empty($_SESSION['user'])){
    unset($_SESSION['user']);
}
if(!empty($_SESSION['name'])){
    unset($_SESSION['name']);
}
if(!empty($_SESSION['role'])){
    unset($_SESSION['role']);
}
if(!empty($_SESSION['userid'])){
    unset($_SESSION['userid']);
}
session_regenerate_id(1);
session_destroy();
header("Location: ./");
exit();
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!empty($_SESSION['userid'])) {
    require_once __DIR__ . "/dbcredentials.class.php";
    $dbCreds = new DBCredentials();
    $userId = $_SESSION['userid'] ?? 0;
    $username = $_SESSION['user'] ?? '';
    $role = $_SESSION['role'] ?? 'student';
    $dbCreds->dbActivityLog($userId, "Logout", "Student $username logged out", $role, "AUTH", (string)$userId);
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header("Location: ./");
exit();
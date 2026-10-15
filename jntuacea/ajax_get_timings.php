<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if(empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role']!="hod"){
    exit(json_encode([]));
}

require_once("hod.class.php");
$hodObj = new HOD();

if (!empty($_GET['class_id'])) {
    $timings = $hodObj->getClassTimingsByClassId($_GET['class_id']);
    echo json_encode($timings);
} else {
    echo json_encode([]);
}
?>

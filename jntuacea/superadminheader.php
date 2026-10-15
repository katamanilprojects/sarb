<?php
if (empty($_SESSION['user']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}
require_once("header.php");
require_once("superadminmenu.php");
?>
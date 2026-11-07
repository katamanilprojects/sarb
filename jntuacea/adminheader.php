<?php

if(empty($_SESSION["user"]) || empty($_SESSION['userid']) || empty($_SESSION["role"]) || !in_array($_SESSION['role'], ['admin', 'superadmin'], true)){
	header('Location: ./');
	exit();
}
require_once("header.php");
require_once("adminmenu.php");
?>
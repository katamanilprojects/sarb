<?php

if(empty($_SESSION["user"]) || empty($_SESSION['userid']) || empty($_SESSION["role"]) || $_SESSION['role']!="admin"){
	header('Location: ./');
	exit();
}
require_once("header.php");
require_once("adminmenu.php");
?>
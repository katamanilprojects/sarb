<?php

if(empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role']!="student"){
	header('Location: ./');
	exit();
}
require_once("header.php");
require_once("studentmenu.php");

?>
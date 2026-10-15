<?php

if(empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role']!="hod" || empty($_SESSION["dept_id"])){
	header('Location: ./');
	exit();
}
require_once("header.php");
require_once("hodmenu.php");

?>
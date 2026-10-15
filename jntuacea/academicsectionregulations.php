<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Regulations";
require_once("academicsectionheader.php");
require_once("regulations.class.php");
require_once("programs.class.php");

$regulationsObj = new Regulations();
$programsObj = new Programs();

$regulations = $regulationsObj->getAllRegulations()['data'] ?? [];
$programs = $programsObj->getAllPrograms()['data'] ?? [];

require_once("views/academic_regulations_hub.php");
require_once("academicsectionfooter.php");

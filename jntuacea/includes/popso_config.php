<?php
// Initialize SuperAdmin
require_once(__DIR__ . "/../superadmin.class.php");
$superadmin = new SuperAdmin();

// Fetch common data
$programs = $superadmin->getAllPrograms();
$departments = $superadmin->getAllDepartments();
$academic_years_result = $superadmin->getActiveAcademicYears();
$academic_years = isset($academic_years_result['data']) ? $academic_years_result['data'] : [];

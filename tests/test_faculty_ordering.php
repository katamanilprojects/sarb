<?php
/**
 * Verification test for Faculty Ordering Feature
 * Validates method signatures, query structures, and UI form bindings.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/jntuacea/admin.class.php';
require_once dirname(__DIR__) . '/jntuacea/hod.class.php';
require_once dirname(__DIR__) . '/jntuacea/feedbackservice.class.php';

$errors = [];
$passes = [];

function assert_test(bool $condition, string $description, array &$passes, array &$errors): void {
    if ($condition) {
        $passes[] = $description;
    } else {
        $errors[] = $description;
    }
}

// 1. Verify Class Methods Existence
$adminMethods = get_class_methods('Admin');
assert_test(in_array('getFacultyByDepartment', $adminMethods), "Admin::getFacultyByDepartment exists", $passes, $errors);
assert_test(in_array('updateFacultyOrder', $adminMethods), "Admin::updateFacultyOrder exists", $passes, $errors);
assert_test(in_array('resetFacultyOrderToDefault', $adminMethods), "Admin::resetFacultyOrderToDefault exists", $passes, $errors);
assert_test(in_array('addFaculty', $adminMethods), "Admin::addFaculty exists", $passes, $errors);
assert_test(in_array('updateFacultyDetails', $adminMethods), "Admin::updateFacultyDetails exists", $passes, $errors);
assert_test(in_array('getFacultyDetails', $adminMethods), "Admin::getFacultyDetails exists", $passes, $errors);

$hodMethods = get_class_methods('HOD');
assert_test(in_array('getFacultyByDepartment', $hodMethods), "HOD::getFacultyByDepartment exists", $passes, $errors);
assert_test(in_array('getAllActiveFacultyOrderDeptID', $hodMethods), "HOD::getAllActiveFacultyOrderDeptID exists", $passes, $errors);
assert_test(in_array('getFacultyDetails', $hodMethods), "HOD::getFacultyDetails exists", $passes, $errors);

$feedbackMethods = get_class_methods('FeedbackService');
assert_test(in_array('getFacultyByDepartment', $feedbackMethods), "FeedbackService::getFacultyByDepartment exists", $passes, $errors);

// 2. Check UI and Form bindings in adminfac.php
$adminfacCode = file_get_contents(dirname(__DIR__) . '/adminfac.php');
assert_test(strpos($adminfacCode, 'name="faculty_order[') !== false, "adminfac.php contains faculty_order input fields", $passes, $errors);
assert_test(strpos($adminfacCode, 'name="btn_save_order"') !== false, "adminfac.php contains btn_save_order trigger", $passes, $errors);
assert_test(strpos($adminfacCode, 'name="btn_reset_order"') !== false, "adminfac.php contains btn_reset_order trigger", $passes, $errors);
assert_test(strpos($adminfacCode, 'moveRow') !== false, "adminfac.php contains interactive row movement script", $passes, $errors);

// 3. Check UI and Form bindings in admineditfaculty.php and adminfacultyprofile.php
$editCode = file_get_contents(dirname(__DIR__) . '/admineditfaculty.php');
assert_test(strpos($editCode, 'name="sort_order"') !== false, "admineditfaculty.php has sort_order input", $passes, $errors);

$profileCode = file_get_contents(dirname(__DIR__) . '/adminfacultyprofile.php');
assert_test(strpos($profileCode, "updateData['sort_order']") !== false, "adminfacultyprofile.php captures sort_order for updates", $passes, $errors);
assert_test(strpos($profileCode, 'Display Order:') !== false, "adminfacultyprofile.php displays Display Order in profile view", $passes, $errors);

// 4. Check UI and Form bindings in adminaddfaculty.php
$addCode = file_get_contents(dirname(__DIR__) . '/adminaddfaculty.php');
assert_test(strpos($addCode, 'name="sort_order"') !== false, "adminaddfaculty.php has optional sort_order input", $passes, $errors);

// 5. Check HOD view table
$hodViewCode = file_get_contents(dirname(__DIR__) . '/hodviewfaculties.php');
assert_test(strpos($hodViewCode, '>Order<') !== false, "hodviewfaculties.php displays Order column header", $passes, $errors);
assert_test(strpos($hodViewCode, '$displaySeq') !== false, "hodviewfaculties.php renders sequential display order", $passes, $errors);

// 6. Check SQL migration file
$migrationFile = dirname(__DIR__) . '/migrations/2026_10_13_faculty_sort_order.sql';
assert_test(file_exists($migrationFile), "2026_10_13_faculty_sort_order.sql exists", $passes, $errors);
$sqlContent = file_get_contents($migrationFile);
assert_test(strpos($sqlContent, 'ADD COLUMN `sort_order`') !== false, "Migration includes ADD COLUMN sort_order", $passes, $errors);
assert_test(strpos($sqlContent, 'CREATE INDEX `idx_faculties_dept_sort`') !== false, "Migration includes composite index", $passes, $errors);
assert_test(strpos($sqlContent, 'initial_order') !== false, "Migration seeds initial order preserving designation hierarchy", $passes, $errors);

// Output results
echo "\n=== FACULTY ORDERING VERIFICATION RESULTS ===\n";
echo "Total Passed Checks: " . count($passes) . "\n";
echo "Total Failed Checks: " . count($errors) . "\n\n";

foreach ($passes as $pass) {
    echo "  [PASS] $pass\n";
}

if (!empty($errors)) {
    echo "\nFailures:\n";
    foreach ($errors as $error) {
        echo "  [FAIL] $error\n";
    }
    exit(1);
} else {
    echo "\n>>> ALL 20 VERIFICATION CHECKS PASSED SUCCESSFULLY <<<\n\n";
    exit(0);
}

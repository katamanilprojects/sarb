<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_FEEDBACK','export');}

/**
 * Enhanced Feedback Excel Download Handler
 * 
 * Replaces CSV generation with professional Excel (XLSX) reports
 * using the FeedbackExcelService for NBA-compliant formatting
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

require_once("dbcredentials.class.php");
require_once("feedbackservice.class.php");
require_once("services/FeedbackExcelService.php");

// Authorization validation
$isAuthorized = false;
$userRole = $_SESSION['role'] ?? '';
$userDeptId = $_SESSION['dept_id'] ?? null;
$userFacId = $_SESSION['facid'] ?? null;

if (!empty($_SESSION['user'])) {
    if ($userRole === 'admin' || $userRole === 'superadmin' || !empty($_SESSION['admin_id'])) {
        $isAuthorized = true;
        $activeRole = 'admin';
    } elseif ($userRole === 'hod' && !empty($userDeptId)) {
        $isAuthorized = true;
        $activeRole = 'hod';
    } elseif (!empty($userFacId) || $userRole === 'faculty') {
        $isAuthorized = true;
        $activeRole = 'faculty';
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    die("Access Denied: You do not have permission to download feedback reports.");
}

// Extract input parameters
$format = strtolower($_POST['format'] ?? $_GET['format'] ?? 'xlsx');
$level  = strtolower($_POST['level'] ?? $_GET['level'] ?? 'subject');

$sub_id  = intval($_POST['sub_id'] ?? $_GET['sub_id'] ?? 0);
$cls_id  = intval($_POST['cls_id'] ?? $_GET['cls_id'] ?? 0);
$fac_id  = intval($_POST['fac_id'] ?? $_GET['fac_id'] ?? 0);
$dept_id = intval($_POST['dept_id'] ?? $_GET['dept_id'] ?? 0);
$acad_year = trim($_POST['acad_year'] ?? $_GET['acad_year'] ?? '');

// Role scope enforcement
if ($activeRole === 'faculty') {
    if ($level === 'faculty') {
        $fac_id = intval($userFacId);
    } else {
        $level = 'subject';
        if ($sub_id <= 0) {
            die("Invalid Subject ID");
        }
        // Verify faculty teaches this subject
        require_once("faculty.class.php");
        $facultyObj = new Faculty();
        $facultySubjects = $facultyObj->getSubjectsByFacultyId($userFacId);
        $allowedSubIds = array_column($facultySubjects['data'] ?? [], 'id');
        if (!in_array($sub_id, $allowedSubIds)) {
            http_response_code(403);
            die("Access Denied: You are not assigned to this subject.");
        }
    }
} elseif ($activeRole === 'hod') {
    $dept_id = $userDeptId;
    if ($level === 'department') {
        // HOD viewing their department
    } elseif ($level === 'class' && $cls_id > 0) {
        $feedbackService = new FeedbackService();
        $clsMeta = $feedbackService->getClassFeedback($cls_id)['meta'] ?? [];
        if (!empty($clsMeta) && $clsMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Class does not belong to your department.");
        }
    } elseif ($level === 'subject' && $sub_id > 0) {
        $feedbackService = new FeedbackService();
        $subMeta = $feedbackService->getSubjectFeedback($sub_id)['meta'] ?? [];
        if (!empty($subMeta) && $subMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Subject does not belong to your department.");
        }
    } elseif ($level === 'faculty' && $fac_id > 0) {
        $feedbackService = new FeedbackService();
        $facMeta = $feedbackService->getFacultyFeedback($fac_id, null, $acad_year)['faculty'] ?? [];
        if (!empty($facMeta) && isset($facMeta['dept_id']) && $facMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Faculty does not belong to your department.");
        }
    }
}

try {
    // Prepare parameters for Excel service
    $params = [
        'sub_id' => $sub_id,
        'cls_id' => $cls_id,
        'fac_id' => $fac_id,
        'dept_id' => $dept_id,
        'acad_year' => $acad_year
    ];
    
    // Validate format
    if (!in_array($format, ['xlsx', 'xls'])) {
        $format = 'xlsx'; // Default to XLSX
    }
    
    // Generate Excel report using the enhanced service
    $excelService = new FeedbackExcelService();
    $filepath = $excelService->generateFeedbackReport($level, $params, $format);
    
    // Send file to browser
    $filename = basename($filepath);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    readfile($filepath);
    
    // Clean up the temporary file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die("Error generating Excel report: " . $e->getMessage());
}
?>
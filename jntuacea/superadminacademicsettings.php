<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Controller: SuperAdmin / Admin Academic Settings Management
 * 
 * Handles multi-regulation autonomous academic settings, validation,
 * audit logging, and role-based permissions (SuperAdmin, Admin, HOD read-only).
 */

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'hod'], true)) {
    header('Location: ./');
    exit();
}

$is_readonly = ($role === 'hod');
$page_title = "Academic Regulations & Settings";

require_once __DIR__ . '/header.php';

// Display corresponding navigation bar based on active role
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminmenu.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/adminmenu.php';
} else {
    require_once __DIR__ . '/hodmenu.php';
}

require_once __DIR__ . '/services/SettingsService.php';

$settingsService = \Services\SettingsService::getInstance();

$regulationsMeta = $settingsService->getRegulationsWithMetadata();
$availableRegulations = $settingsService->getAvailableRegulations();

$activeRegId = (int)($_GET['reg_id'] ?? $_POST['reg_id'] ?? 0);
$activeRegulation = strtoupper(trim($_GET['reg'] ?? ''));

$activeRegMeta = null;
if ($activeRegId > 0) {
    foreach ($regulationsMeta as $rm) {
        if ((int)$rm['id'] === $activeRegId) {
            $activeRegMeta = $rm;
            $activeRegulation = $rm['regulation'];
            break;
        }
    }
}

if (empty($activeRegulation)) {
    $activeRegulation = 'R23';
}

if (!$activeRegMeta) {
    foreach ($regulationsMeta as $rm) {
        if ($rm['regulation'] === $activeRegulation) {
            $activeRegMeta = $rm;
            $activeRegId = (int)$rm['id'];
            break;
        }
    }
}

$targetScope = ($activeRegId > 0) ? $activeRegId : $activeRegulation;

$succMsg = '';
$errMsg = '';

// Handle Settings Mutation Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_readonly) {
    $action = $_POST['action'] ?? '';

    // Verify CSRF Token
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF token mismatch). Please reload and try again.";
    } elseif ($action === 'save_settings') {
        $submittedSettings = $_POST['settings'] ?? [];
        $targetRegId = (int)($_POST['reg_id'] ?? $activeRegId);
        $targetRegCode = strtoupper(trim($_POST['regulation'] ?? $activeRegulation));
        $targetReg = ($targetRegId > 0) ? $targetRegId : $targetRegCode;
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 1. Business Logic & Regulatory Validation
        $validationErrors = [];

        // Check Mid Weighting (Section 9.a.v)
        if (isset($submittedSettings['theory_mid_better_weight'], $submittedSettings['theory_mid_lesser_weight'])) {
            $bw = (float)$submittedSettings['theory_mid_better_weight'];
            $lw = (float)$submittedSettings['theory_mid_lesser_weight'];
            if (abs(($bw + $lw) - 1.00) > 0.001) {
                $validationErrors[] = "Mid exam weights must sum to 1.00 (Current: $bw + $lw = " . ($bw + $lw) . ").";
            }
        }

        // Check CO Attainment Direct Component Weights
        if (isset($submittedSettings['attainment_direct_cia_weight'], $submittedSettings['attainment_direct_see_weight'])) {
            $cw = (float)$submittedSettings['attainment_direct_cia_weight'];
            $sw = (float)$submittedSettings['attainment_direct_see_weight'];
            if (abs(($cw + $sw) - 1.00) > 0.001) {
                $validationErrors[] = "CO Direct Attainment CIA + SEE weights must sum to 1.00 (Current: $cw + $sw = " . ($cw + $sw) . ").";
            }
        }

        // Check PO/PSO Attainment Weights
        if (isset($submittedSettings['overall_direct_weight'], $submittedSettings['overall_indirect_weight'])) {
            $odw = (float)$submittedSettings['overall_direct_weight'];
            $oiw = (float)$submittedSettings['overall_indirect_weight'];
            if (abs(($odw + $oiw) - 1.00) > 0.001) {
                $validationErrors[] = "PO Overall Attainment Direct + Indirect weights must sum to 1.00 (Current: $odw + $oiw = " . ($odw + $oiw) . ").";
            }
        }

        // Check Attendance Percentage Hierarchy (Section 17)
        if (isset($submittedSettings['attendance_min_subject_pct'], $submittedSettings['attendance_condone_floor_pct'], $submittedSettings['attendance_min_aggregate_pct'])) {
            $minSub = (float)$submittedSettings['attendance_min_subject_pct'];
            $cFloor = (float)$submittedSettings['attendance_condone_floor_pct'];
            $minAgg = (float)$submittedSettings['attendance_min_aggregate_pct'];

            if ($minSub < 0 || $minSub > $cFloor || $cFloor > $minAgg || $minAgg > 100) {
                $validationErrors[] = "Attendance rule violation: Must satisfy 0% <= Min Course ($minSub%) <= Condonation Floor ($cFloor%) <= Min Aggregate ($minAgg%) <= 100%.";
            }
        }

        // Check Theory CIA Components Sum
        if (isset($submittedSettings['theory_mid_subjective_condensed'], $submittedSettings['theory_mid_objective_marks'], $submittedSettings['theory_assignment_marks'], $submittedSettings['theory_cia_total_marks'])) {
            $subMarks = (float)$submittedSettings['theory_mid_subjective_condensed'];
            $objMarks = (float)$submittedSettings['theory_mid_objective_marks'];
            $assMarks = (float)$submittedSettings['theory_assignment_marks'];
            $ciaTotal = (float)$submittedSettings['theory_cia_total_marks'];

            $compSum = $subMarks + $objMarks + $assMarks;
            if (abs($compSum - $ciaTotal) > 0.001) {
                $validationErrors[] = "Theory CIA component sum (Subjective: $subMarks + Objective: $objMarks + Assignment: $assMarks = $compSum) must equal Total CIA Marks ($ciaTotal).";
            }
        }

        // Validate JSON Formats
        if (!empty($submittedSettings['grade_bands_json'])) {
            $decodedBands = json_decode($submittedSettings['grade_bands_json'], true);
            if (!is_array($decodedBands)) {
                $validationErrors[] = "Grade Bands JSON format is invalid: " . json_last_error_msg();
            }
        }

        if (!empty($submittedSettings['class_award_json'])) {
            $decodedAwards = json_decode($submittedSettings['class_award_json'], true);
            if (!is_array($decodedAwards)) {
                $validationErrors[] = "Class Award JSON format is invalid: " . json_last_error_msg();
            }
        }

        // If validation passed, execute mutations
        if (!empty($validationErrors)) {
            $errMsg = implode("<br>", $validationErrors);
        } else {
            try {
                $updatedCount = 0;
                foreach ($submittedSettings as $sKey => $sVal) {
                    $meta = $settingsService->getMetadata($sKey, $targetReg);
                    if ($meta && !empty($meta['is_editable'])) {
                        $settingsService->set($sKey, $sVal, $targetReg, $userId, $clientIp);
                        $updatedCount++;
                    }
                }
                $succMsg = "Successfully updated {$updatedCount} academic parameters for regulation {$targetReg}. Audit logs updated.";
                // Clear cache so UI reflects freshly committed values
                $settingsService->clearCache();
            } catch (\Throwable $ex) {
                $errMsg = "Database error while committing changes: " . $ex->getMessage();
            }
        }
    }
}

// Generate new CSRF token for this request
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Load settings grouped by category for view
$groupedSettings = $settingsService->getAllGroupedByCategory($targetScope);
$auditLogs = $settingsService->getAuditLogs(null, $activeRegulation, 50);

// Load the presentation view
require_once __DIR__ . '/views/manage_academic_settings.php';

// Display corresponding footer
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminfooter.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/footer.php';
} else {
    require_once __DIR__ . '/hodfooter.php';
}

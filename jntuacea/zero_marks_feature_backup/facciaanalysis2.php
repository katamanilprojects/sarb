<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_OBE_ANALYSIS');
// Assuming faculty.class.php and cia.class.php are included via autoloader or header
require_once("faculty.class.php"); // Make sure path is correct
require_once("cia.class.php");      // Make sure path is correct
require_once("facciaanalysis2.class.php"); // Include the updated class file

$facultyObj = new Faculty();
$ciaObj = new CIA(); // Assuming CIA class is needed for other context, otherwise remove
$analysisHelper = new COAnalysis(); // Instantiate analysis class for potential non-AJAX use later

$faculty_id = $_SESSION['facid'] ?? null; // Use null coalescing for safety

// Redirect if faculty ID is not set
if (!$faculty_id) {
    header("Location: ./"); // Redirect to login or appropriate page
    exit;
}

$facultySubjectsResult = $facultyObj->getSubjectsByFacultyId($faculty_id);
$facultySubjects = $facultySubjectsResult['status'] === 1 ? $facultySubjectsResult['data'] : [];

$selected_sub_id = null;
$selected_assessment_number = null;
$subject_details_header = "Select Subject"; // Default header

// Helpers to detect multi-batch courses and clean base course details
if (!function_exists('isMultiBatchCourse')) {
    function isMultiBatchCourse($subs) {
        if (count($subs) <= 1) return false;

        $sub_names = array_map('trim', array_column($subs, 'sub_fullname'));
        $prefix = $sub_names[0];
        foreach ($sub_names as $nm) {
            while (stripos($nm, $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
                if ($prefix === '') break;
            }
        }
        $common_len = strlen(trim(preg_replace('/[\s\-\–\(\[]+$/', '', $prefix)));

        // Batch / group keyword in name
        $pattern = '/[\s\-\–\(\[](?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+/i';
        $has_batch_keyword = false;
        foreach ($sub_names as $nm) {
            if (preg_match($pattern, $nm)) {
                $has_batch_keyword = true;
                break;
            }
        }

        // Matching shortnames
        $shorts = array_unique(array_filter(array_map(function($s) {
            return strtoupper(trim(preg_replace('/[\s\-\–\(\[]*(?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+[\s\)\–\]]*$/i', '', $s['sub_shortname'] ?? '')));
        }, $subs)));
        $same_shortname = (count($shorts) === 1 && !empty($shorts[0]));

        if ($has_batch_keyword && $common_len >= 3) return true;
        if ($common_len >= 5) return true;
        if ($same_shortname && $common_len >= 3) return true;

        return false;
    }
}

if (!function_exists('getCleanBaseCourseDetails')) {
    function getCleanBaseCourseDetails($subs) {
        if (empty($subs)) {
            return ['base_name' => '', 'base_code' => ''];
        }
        $sub_names = array_column($subs, 'sub_fullname');
        $sub_codes = array_column($subs, 'subcode');
        $first_name = trim($subs[0]['sub_fullname'] ?? '');
        $first_code = trim($subs[0]['subcode'] ?? '');

        // Strip batch/group/section suffix like " - GROUP-A", "(Group A)", "[GROUP-A]", " - Batch 1", etc.
        $clean_regex = '/[\s\-\–\(\[]*(?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+[\s\)\–\]]*$/i';
        $base_name = preg_replace($clean_regex, '', $first_name);
        $base_name = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $base_name));

        if ($base_name === $first_name && count($subs) > 1) {
            $prefix = $sub_names[0];
            foreach ($sub_names as $nm) {
                while (stripos($nm, $prefix) !== 0) {
                    $prefix = substr($prefix, 0, -1);
                    if ($prefix === '') break;
                }
            }
            $cand = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $prefix));
            if (strlen($cand) >= 3) {
                $base_name = $cand;
            }
        }
        if (empty($base_name)) {
            $base_name = $first_name;
        }

        // Clean base code - find common prefix across codes or strip trailing batch letter
        $code_prefix = trim($sub_codes[0] ?? '');
        foreach ($sub_codes as $cd) {
            $cd = trim($cd);
            while (stripos($cd, $code_prefix) !== 0) {
                $code_prefix = substr($code_prefix, 0, -1);
                if ($code_prefix === '') break;
            }
        }
        $base_code = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $code_prefix));
        if (empty($base_code)) {
            $base_code = trim(preg_replace('/[\s\-\–]*[a-zA-Z]$/', '', $first_code));
        }
        if (empty($base_code)) {
            $base_code = $first_code;
        }

        return ['base_name' => $base_name, 'base_code' => $base_code];
    }
}

// Group faculty's assigned subjects by class_id and subject_sno (same course taught to multiple batches in that class)
$groupedFacultySubjects = [];
$allFacultySubjectIds = [];
if (!empty($facultySubjects)) {
    foreach ($facultySubjects as $sub) {
        $allFacultySubjectIds[] = (int)$sub['id'];
        $cls_id = $sub['class_id'] ?? 0;
        $sno_key = !empty($sub['subject_sno']) ? $sub['subject_sno'] : ('id_' . $sub['id']);
        $group_key = $cls_id . '_' . $sno_key;
        $groupedFacultySubjects[$group_key][] = $sub;
    }
}

// Handle POST request for subject and assessment selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && hash_equals($_SESSION['secretcode'], (string)$_POST['secretcode'])) {
        unset($_SESSION['secretcode']); // Use the token once

        $post_sub_id = trim($_POST['sub_id'] ?? '');
        if (preg_match('/^[0-9]+(,[0-9]+)*$/', $post_sub_id)) {
            // Verify all IDs belong to this faculty
            $input_ids = array_map('intval', explode(',', $post_sub_id));
            $all_valid = true;
            foreach ($input_ids as $iid) {
                if (!in_array($iid, $allFacultySubjectIds, true)) {
                    $all_valid = false;
                    break;
                }
            }
            if ($all_valid) {
                $selected_sub_id = $post_sub_id;
            } else {
                $_SESSION["err"] = "Invalid subject selected.";
            }
        }

        // Assessment number can be an integer, 'all', or 'SEE'
        $assessment_input = isset($_POST['assessment_number']) ? htmlspecialchars(trim($_POST['assessment_number']), ENT_QUOTES, 'UTF-8') : null;
        if ($assessment_input !== null && ($assessment_input === 'all' || strcasecmp($assessment_input, 'see') === 0 || filter_var($assessment_input, FILTER_VALIDATE_INT))) {
            $selected_assessment_number = (strcasecmp($assessment_input, 'see') === 0) ? 'SEE' : $assessment_input;
        }
    } else {
        $_SESSION["err"] = "Form submission error. Please try again.";
        $selected_sub_id = null;
        $selected_assessment_number = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request
    $get_sub_id = trim($_GET['sub_id'] ?? '');
    if (preg_match('/^[0-9]+(,[0-9]+)*$/', $get_sub_id)) {
        $input_ids = array_map('intval', explode(',', $get_sub_id));
        $all_valid = true;
        foreach ($input_ids as $iid) {
            if (!in_array($iid, $allFacultySubjectIds, true)) {
                $all_valid = false;
                break;
            }
        }
        if ($all_valid) {
            $selected_sub_id = $get_sub_id;
        } else {
            $_SESSION["err"] = "Invalid subject selected.";
        }
    }
    $assessment_input = isset($_GET['assessment_number']) ? htmlspecialchars(trim($_GET['assessment_number']), ENT_QUOTES, 'UTF-8') : null;
    if ($assessment_input !== null && ($assessment_input === 'all' || strcasecmp($assessment_input, 'see') === 0 || filter_var($assessment_input, FILTER_VALIDATE_INT))) {
        $selected_assessment_number = (strcasecmp($assessment_input, 'see') === 0) ? 'SEE' : $assessment_input;
    } else {
        $selected_assessment_number = null;
    }
}


// Generate a new secret code for CSRF protection
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Get Subject Details for Header & Subject Type
$selected_sub_type = 'theory';
$selected_id_list = !empty($selected_sub_id) ? explode(',', $selected_sub_id) : [];

if (!empty($selected_sub_id) && !empty($facultySubjects)) {
    if (count($selected_id_list) > 1) {
        // Multi-batch selection
        $matched_subs = array_values(array_filter($facultySubjects, function($s) use ($selected_id_list) {
            return in_array((string)$s['id'], $selected_id_list, true);
        }));
        if (!empty($matched_subs)) {
            $base_details = getCleanBaseCourseDetails($matched_subs);
            $className = $matched_subs[0]['class_name'] ?? '';
            $acadYear = $matched_subs[0]['acad_year'] ?? '';
            $subject_details_header = "{$base_details['base_name']} ({$base_details['base_code']}) [All Batches Combined] - {$className} - {$acadYear}";
            foreach ($matched_subs as $s) {
                $st = strtolower($s['sub_type'] ?? 'theory');
                if ($st === 'lab' || $st === 'dti') {
                    $selected_sub_type = $st;
                    break;
                }
            }
        }
    } else {
        // Single subject selection
        foreach ($facultySubjects as $subject) {
            if ($subject['id'] == $selected_sub_id) {
                $selected_sub_type = strtolower($subject['sub_type'] ?? 'theory');
                $subject_details_header = "{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}";
                break;
            }
        }
    }
}

// For Lab subjects, there is only 1 CIA, so default to 1 if not set or invalid
if ($selected_sub_id && ($selected_sub_type === 'lab' || $selected_sub_type === 'dti')) {
    if (empty($selected_assessment_number) || $selected_assessment_number == '2') {
        $selected_assessment_number = '1';
    }
}


// Include header file AFTER setting session variables and processing POST/GET
$page_title = "CIA Analysis Dashboard";
require_once("facheader.php"); // Make sure path is correct

?>

<div class="container mt-4">

    <div class="card mb-4">
        <div class="card-header bg-primary-subtle py-2">
            <h6 class="mb-0">Select Course and Assessment for Analysis</h6>
        </div>
        <div class="card-body">
            <form action="facciaanalysis2.php" method="post" id="analysisSelectionForm" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="sub_id" class="form-label">Subject:</label>
                    <select name="sub_id" id="sub_id" class="form-select" required onchange="document.getElementById('analysisSelectionForm').submit();">
                        <option value="">-- Select Subject --</option>
                        <?php if (!empty($groupedFacultySubjects)): ?>
                            <?php 
                            foreach ($groupedFacultySubjects as $gKey => $subs) {
                                $firstSub = $subs[0];
                                $clsName = $firstSub['class_name'] ?? '';
                                $acadYr = $firstSub['acad_year'] ?? '';

                                if (count($subs) === 1 || !isMultiBatchCourse($subs)) {
                                    // Single subject or distinct course
                                    foreach ($subs as $subject) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($subject['id']) . "' $selected>";
                                        echo htmlspecialchars("{$subject['sub_fullname']} ({$subject['subcode']}) - {$clsName} - {$acadYr}");
                                        echo "</option>";
                                    }
                                } else {
                                    // Multiple batches of the same course assigned to this faculty
                                    $combined_ids = implode(',', array_column($subs, 'id'));
                                    $base_details = getCleanBaseCourseDetails($subs);
                                    $base_name = $base_details['base_name'];
                                    $base_code = $base_details['base_code'];

                                    $is_combined_selected = (!empty($selected_sub_id) && $selected_sub_id === $combined_ids) ? 'selected' : '';
                                    echo "<option value='{$combined_ids}' $is_combined_selected style='font-weight: bold;'>";
                                    echo "★ " . htmlspecialchars("{$base_name} ({$base_code}) [All Batches Combined] - {$clsName} - {$acadYr}");
                                    echo "</option>";

                                    foreach ($subs as $bSub) {
                                        $bSelected = (!empty($selected_sub_id) && $selected_sub_id == $bSub['id']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($bSub['id']) . "' $bSelected>";
                                        echo "&nbsp;&nbsp;&nbsp;&nbsp;↳ " . htmlspecialchars("{$bSub['sub_fullname']} ({$bSub['subcode']}) - {$clsName}");
                                        echo "</option>";
                                    }
                                }
                            }
                            ?>
                        <?php else: ?>
                            <option value="" disabled>No subjects assigned</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="assessment_number" class="form-label">Assessment:</label>
                    <select name="assessment_number" id="assessment_number" class="form-select" <?php echo !$selected_sub_id ? 'disabled' : 'required'; ?>>
                        <option value="">-- Select Assessment --</option>
                        <?php if ($selected_sub_id): // Only enable if subject is selected 
                        ?>
                            <?php
                            $isCiaEnabled = \FeatureManager::isModuleEnabled('MOD_CIA_MARKS');
                            $isSeeEnabled = \FeatureManager::isModuleEnabled('MOD_SEE_MARKS');
                            $assessments = [];
                            if ($isCiaEnabled) {
                                if ($selected_sub_type === 'lab' || $selected_sub_type === 'dti') {
                                    $assessments[1] = "Lab CIA";
                                } else {
                                    $assessments[1] = "CIA 1";
                                    $assessments[2] = "CIA 2";
                                    $assessments['all'] = "Overall CIA";
                                }
                            }
                            if ($isSeeEnabled) {
                                $assessments['SEE'] = "Semester End Exam (SEE)";
                            }
                            foreach ($assessments as $num => $label):
                                $selected = (strcasecmp((string)$selected_assessment_number, (string)$num) === 0) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($num); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <button type="submit" class="btn btn-primary w-100">Load Analysis</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION["succ"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION["succ"]);
            unset($_SESSION["succ"]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION["err"])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION["err"]);
            unset($_SESSION["err"]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <?php if ($selected_sub_id && $selected_assessment_number): ?>

        <?php 
        $is_faculty_cia_report = true;
        require_once("ciaanalysis2_common_chart.php");
        echo renderAnalysisAssets(); // Include necessary JS libraries and global scripts
        echo renderAnalysisScripts($selected_sub_id, $selected_assessment_number); // Include the analysis scripts
        ?>


    <?php endif; // End check for selected_sub_id && selected_assessment_number 
    ?>

</div> <?php
        require_once("facfooter.php"); // Make sure path is correct
        ?>
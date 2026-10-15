<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_OBE_ANALYSIS');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

$selected_sub_id = null;
$selected_assessment_number = null;
require_once("hod.class.php");
$hodObj = new HOD();

$selected_cls_id = null;
$selected_sub_id = null;

if (!empty($_POST['cls_id'])) {
    $selected_cls_id = $_POST['cls_id'];
    $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
}

$specializations = $hodObj->getSpecializationsByDepartment($_SESSION["dept_id"]);

$classes_options = '';

if (!empty($specializations)) {
    foreach ($specializations as $spec) {
        $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
        $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $classes_options .= '<option value="' . $class['id'] . '"';
                if ($class['id'] == $selected_cls_id) {
                    $classes_options .= ' selected';
                }
                $classes_options .= '>' . $class['classname'] . ' (' . $class['acad_year'] . ')</option>';
            }
        }
        $classes_options .= '</optgroup>';
    }
}
// Handle subject selection
if (!empty($_POST['sub_id'])) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
    }
    // Accept either single integer ID or comma-separated IDs (e.g. "101,102")
    $post_sub_id = trim($_POST['sub_id']);
    if (preg_match('/^[0-9]+(,[0-9]+)*$/', $post_sub_id)) {
        $selected_sub_id = $post_sub_id;
    }

    if (!empty($_POST['assessment_number'])) {
        $selected_assessment_number = $_POST['assessment_number'];
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

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

// Helper to group subjects by subject_sno
$groupedSubjects = [];
if (!empty($facultySubjects['data'])) {
    foreach ($facultySubjects['data'] as $sub) {
        $sno_key = !empty($sub['subject_sno']) ? $sub['subject_sno'] : ('id_' . $sub['id']);
        $groupedSubjects[$sno_key][] = $sub;
    }
}

require_once("hodheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Course CIA Analysis</div>
                <div class="card-body">
                    <form action="hodciaanalysis2.php" method="post" id="ciacourseform">
                        <div class="form-group">
                            <label for="cls_id">Class:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php echo $classes_options; ?>
                            </select>
                        </div>
                        <input type="hidden" name="an" value="ab" />
                        <br />
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                if (!empty($groupedSubjects)) {
                                    foreach ($groupedSubjects as $sno => $subs) {
                                        if (count($subs) === 1 || !isMultiBatchCourse($subs)) {
                                            // Single subject or distinct electives sharing a slot
                                            foreach ($subs as $subject) {
                                                $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                                echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                                            }
                                        } else {
                                            // Multiple batches of the same course
                                            $combined_ids = implode(',', array_column($subs, 'id'));
                                            $base_details = getCleanBaseCourseDetails($subs);
                                            $base_name = $base_details['base_name'];
                                            $base_code = $base_details['base_code'];

                                            $is_combined_selected = (!empty($selected_sub_id) && $selected_sub_id === $combined_ids) ? 'selected' : '';
                                            echo "<option value='{$combined_ids}' $is_combined_selected style='font-weight: bold;'>★ {$base_name} ({$base_code}) [All Batches Combined]</option>";

                                            foreach ($subs as $bSub) {
                                                $bSelected = (!empty($selected_sub_id) && $selected_sub_id == $bSub['id']) ? 'selected' : '';
                                                echo "<option value='{$bSub['id']}' $bSelected>&nbsp;&nbsp;&nbsp;&nbsp;↳ {$bSub['sub_fullname']} ({$bSub['subcode']})</option>";
                                            }
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    </form>
                </div>
            </div>

            <?php if (!empty($_SESSION["succ"])) {
                echo '<div class="alert alert-success">' . $_SESSION["succ"] . '</div>';
                unset($_SESSION["succ"]);
            } ?>
            <?php if (!empty($_SESSION["err"])) {
                echo '<div class="alert alert-danger">' . $_SESSION["err"] . '</div>';
                unset($_SESSION["err"]);
            } ?>
        </div>
    </div>

    <?php if (!empty($selected_sub_id)) : ?>
        <?php
        $selected_sub_type = 'theory';
        $selected_id_list = explode(',', $selected_sub_id);
        if (!empty($facultySubjects['data'])) {
            foreach ($facultySubjects['data'] as $subject) {
                if (in_array($subject['id'], $selected_id_list)) {
                    $st = strtolower($subject['sub_type'] ?? 'theory');
                    if ($st === 'lab' || $st === 'dti') {
                        $selected_sub_type = $st;
                        break;
                    }
                }
            }
        }
        $isCiaEnabled = \FeatureManager::isModuleEnabled('MOD_CIA_MARKS');
        $isSeeEnabled = \FeatureManager::isModuleEnabled('MOD_SEE_MARKS');
        if ($selected_sub_type === 'lab' || $selected_sub_type === 'dti') {
            if ($selected_assessment_number == '2') {
                $selected_assessment_number = $isCiaEnabled ? '1' : ($isSeeEnabled ? 'SEE' : null);
            }
        }
        ?>
        <br>
        <div class="card">
            <div class="card-header">
                Select Assessment
            </div>
            <div class="card-body">
                <form action="hodciaanalysis2.php" method="post" id="assessmentForm">
                    <select name="assessment_number" id="assessment_number" class="form-select" required onchange="document.getElementById('assessmentForm').submit();">
                        <option value="">--Select Assessment --</option>
                        <?php
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
                            $selected = (!empty($selected_assessment_number) && strcasecmp((string)$selected_assessment_number, (string)$num) === 0) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($num); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="cls_id" value="<?php echo $selected_cls_id; ?>" />
                    <input type="hidden" name="sub_id" value="<?php echo htmlspecialchars($selected_sub_id); ?>" />
                </form>
            </div>
        </div>

        <?php if ($selected_sub_id && $selected_assessment_number): ?>
            <?php
                $subject_details_header = "";
                // Get Subject Details for Header
                if (!empty($facultySubjects['data'])) {
                    if (strpos($selected_sub_id, ',') !== false) {
                        // Combined subject
                        $matched_subs = array_values(array_filter($facultySubjects['data'], function($subject) use ($selected_id_list) {
                            return in_array($subject['id'], $selected_id_list);
                        }));
                        if (!empty($matched_subs)) {
                            $base_details = getCleanBaseCourseDetails($matched_subs);
                            $subject_details_header = "{$base_details['base_name']} ({$base_details['base_code']}) [All Batches Combined]";
                        }
                    } else {
                        foreach ($facultySubjects['data'] as $subject) {
                            if (!empty($subject['id']) && $subject['id'] == $selected_sub_id) {
                                $subject_details_header = "{$subject['sub_fullname']} ({$subject['subcode']})";
                                break;
                            }
                        }
                    }
                }
            ?>             
            
            <?php
            require_once("ciaanalysis2_common_chart.php");
            echo renderAnalysisAssets(); // Include necessary JS libraries and global scripts
            echo renderAnalysisScripts($selected_sub_id, $selected_assessment_number); // Include the analysis scripts
            ?>


        <?php endif; // End check for selected_sub_id && selected_assessment_number  
        ?>
    <?php endif; // End check for selected_sub_id && selected_assessment_number 
    ?>
    <script>
        document.getElementById('cls_id').addEventListener('change', function() {
            document.getElementById("ciacourseform").submit();
        });
        document.getElementById('sub_id').addEventListener('change', function() {
            document.getElementById("ciacourseform").submit();
        });
    </script>
    <?php
    require_once("hodfooter.php");
    ?>
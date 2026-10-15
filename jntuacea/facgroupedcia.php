<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_GROUPED_ATT');

$page_title = "Grouped CIA Import";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];

// --- STATE MANAGEMENT ---
$currentState = 1; // Default: Select Source
$msg = '';
$err = '';

// Data holders
$source_sub_id = null;
$source_subject_details = null;
$target_sub_ids = [];
$assessment_number = null;
$eligible_targets = [];
$groupedFacultySubjects = [];
$allFacultySubjects = [];
$missing_components = [];
$missing_attachments = [];

// --- CSRF Check ---
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$is_valid_csrf = $is_post && !empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'];

if ($is_post && !$is_valid_csrf) {
    $err = "Invalid or expired request. Please try again.";
}

// --- STATE 4: Process Import ---
if ($is_valid_csrf && isset($_POST['submit_import'])) {
    \FeatureManager::requireWriteAccess('MOD_GROUPED_ATT');
    $currentState = 4;
    $source_sub_id = (int)$_POST['source_sub_id'];
    $target_sub_ids = $_POST['target_sub_ids'] ?? [];
    $assessment_number = $_POST['assessment_number'] ?? null;
    $components_to_copy = $_POST['components_to_copy'] ?? [];
    $attachments_to_copy = $_POST['attachments_to_copy'] ?? [];

    $success_count = 0;
    $error_count = 0;
    $err_details = [];

    if (empty($target_sub_ids) || empty($assessment_number) || (empty($components_to_copy) && empty($attachments_to_copy))) {
        $_SESSION['err'] = "No targets or no items were selected for import.";
    } else {
        foreach ($target_sub_ids as $target_sub_id) {
            $target_sub_id_int = (int)$target_sub_id;
            $result = $ciaObj->importCiaData($source_sub_id, $target_sub_id_int, $assessment_number, $components_to_copy, $attachments_to_copy);
            if ($result['status'] == 1) {
                $success_count++;
            } else {
                $error_count++;
                $err_details[] = "Subject ID $target_sub_id_int: " . $result['error'];
            }
        }

        if ($success_count > 0) {
            $_SESSION['succ'] = "Successfully imported data to $success_count target subject(s).";
        }
        if ($error_count > 0) {
            $_SESSION['err'] = "Import failed for $error_count subject(s):<br>" . implode("<br>", $err_details);
        }
    }
    // Redirect to clear POST and show messages
    header("Location: facgroupedcia.php");
    exit;

// --- STATE 3: Analyze & Select ---
} elseif ($is_valid_csrf && isset($_POST['check_missing_data'])) {
    $currentState = 3;
    $source_sub_id = (int)$_POST['source_sub_id'];
    $target_sub_ids = $_POST['target_sub_ids'] ?? [];
    $assessment_number = $_POST['assessment_number'] ?? null;

    if (empty($source_sub_id) || empty($target_sub_ids) || empty($assessment_number)) {
        $err = "Missing source, target, or assessment selection.";
        $currentState = 2; // Go back to target selection
    } else {
        // Fetch source items
        $source_assessment_id = $ciaObj->getAssessmentId($source_sub_id, $assessment_number, false);
        $source_components = $ciaObj->getAssessmentComponents($source_sub_id, $assessment_number)['data'];
        $source_attachments = $ciaObj->getPublicCIAAttachments($source_sub_id, $assessment_number)['files'];

        // Build lookup map of existing items in targets AND check if they have metadata (questions)
        $target_metadata_status = [];
        $target_has_attachment = [];
        foreach ($target_sub_ids as $target_id) {
            $target_id_int = (int)$target_id;
            $target_ass_id = $ciaObj->getAssessmentId($target_id_int, $assessment_number, true); // Auto-create assessment if not present

            if ($target_ass_id) {
                // Check components
                $target_comps = $ciaObj->getAssessmentComponents($target_id_int, $assessment_number)['data'];
                if (!empty($target_comps)) {
                    foreach ($target_comps as $tc) {
                        $key = $tc['component_type'] . ':' . $tc['sequence_number'];
                        $questions = $ciaObj->getQuestionsByComponent($tc['id']);
                        $target_metadata_status[$target_id_int][$key] = !empty($questions); // true if has questions, false if empty
                    }
                }
                // Check attachments
                $target_atts = $ciaObj->getPublicCIAAttachments($target_id_int, $assessment_number)['files'];
                if (!empty($target_atts)) {
                    foreach ($target_atts as $ta) {
                        $target_has_attachment[$target_id_int]['attachment'][$ta['file_title']] = true;
                    }
                }
            }
        }

        // Run comparison
        if (!empty($source_components)) {
            foreach ($source_components as $s_comp) {
                $key = $s_comp['component_type'] . ':' . $s_comp['sequence_number'];
                
                // 1. Check if source component has metadata
                $source_questions = $ciaObj->getQuestionsByComponent($s_comp['id']);
                if (empty($source_questions)) {
                    continue; // Skip source components that have no questions
                }

                // 2. Check if it's missing (or empty) in at least one target
                $missing_in_at_least_one_target = false;
                foreach ($target_sub_ids as $target_id) {
                    $target_id_int = (int)$target_id;
                    // If target doesn't have the component OR it's present but has no questions
                    if (!isset($target_metadata_status[$target_id_int][$key]) || $target_metadata_status[$target_id_int][$key] === false) {
                        $missing_in_at_least_one_target = true;
                        break;
                    }
                }
                
                if ($missing_in_at_least_one_target) {
                    $missing_components[$key] = $s_comp;
                }
            }
        }
        
        if (!empty($source_attachments)) {
            foreach ($source_attachments as $s_att) {
                $key = $s_att['file_title'];
                $missing_in_at_least_one_target = false;
                foreach ($target_sub_ids as $target_id) {
                    $target_id_int = (int)$target_id;
                    if (!isset($target_has_attachment[$target_id_int]['attachment'][$key])) {
                        $missing_in_at_least_one_target = true;
                        break;
                    }
                }
                if ($missing_in_at_least_one_target) {
                    $missing_attachments[$key] = $s_att;
                }
            }
        }
    }

// --- STATE 2: Select Targets ---
} elseif ($is_valid_csrf && isset($_POST['select_source_submit'])) {
    $currentState = 2;
    $source_sub_id = (int)$_POST['source_sub_id'];

    $allFacultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id)['data'];
    foreach ($allFacultySubjects as $s) {
        if ($s['id'] == $source_sub_id) {
            $source_subject_details = $s;
            break;
        }
    }

    if ($source_subject_details) {
        foreach ($allFacultySubjects as $target_subject) {
            if (
                $target_subject['id'] != $source_sub_id &&
                $target_subject['acad_year'] == $source_subject_details['acad_year'] &&
                $target_subject['subcode'] == $source_subject_details['subcode']
            ) {
                $eligible_targets[] = $target_subject;
            }
        }
        if (empty($eligible_targets)) {
            $err = "No other subjects found with the same academic year (" . $source_subject_details['acad_year'] . ") and subject code (" . $source_subject_details['subcode'] . ").";
            $currentState = 1; // Go back to start
        }
    } else {
        $err = "Could not find source subject details.";
        $currentState = 1; // Go back to start
    }

// --- STATE 1: Select Source (Default) ---
} else {
    $currentState = 1;
    if (isset($_SESSION['succ'])) { $msg = $_SESSION['succ']; unset($_SESSION['succ']); }
    if (isset($_SESSION['err'])) { $err = $_SESSION['err']; unset($_SESSION['err']); }

    $allFacultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id)['data'];
    if (!empty($allFacultySubjects)) {
        foreach ($allFacultySubjects as $subject) {
            $groupedFacultySubjects[$subject['acad_year']][$subject['subcode']][] = $subject;
        }
        krsort($groupedFacultySubjects); // Sort by year descending
    }
}

require_once("facheader.php");
// Regenerate secret code for the form
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container mt-4">

    <?php
    if (!empty($msg)) {
        echo '<div class="alert alert-success">' . $msg . '</div>';
    }
    if (!empty($err)) {
        echo '<div class="alert alert-danger">' . $err . '</div>';
    }
    ?>

    <div class="card shadow-sm">
        <div class="card-header bg-info-subtle">
            <h4><i class="bi bi-arrows-collapse me-2"></i>Smart Grouped CIA Import Tool</h4>
        </div>
        <div class="card-body">
            <?= \FeatureManager::renderReadOnlyBanner('MOD_GROUPED_ATT'); ?>

            <?php if ($currentState == 1) : ?>
                <form action="facgroupedcia.php" method="post">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <div class="mb-3">
                        <label for="source_sub_id" class="form-label"><strong>Step 1: Select Source Subject</strong><br><small class="text-muted">This is the subject you have already configured (e.g., CSE-A).</small></label>
                        <select name="source_sub_id" id="source_sub_id" class="form-select" required>
                            <option value="">-- Select a Source Subject --</option>
                            <?php foreach ($groupedFacultySubjects as $year => $subcodes) : ?>
                                <optgroup label="Academic Year: <?php echo $year; ?>">
                                    <?php foreach ($subcodes as $code => $subjects) : ?>
                                        <optgroup label="&nbsp;&nbsp;Code: <?php echo $code; ?>">
                                            <?php foreach ($subjects as $subject) : ?>
                                                <option value="<?php echo $subject['id']; ?>">
                                                    &nbsp;&nbsp;<?php echo htmlspecialchars("{$subject['class_name']} - {$subject['sub_fullname']}"); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="select_source_submit" class="btn btn-primary">Next: Select Targets</button>
                </form>
            <?php endif; ?>

            <?php if ($currentState == 2 && $source_subject_details) : ?>
                <form action="facgroupedcia.php" method="post">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <input type="hidden" name="source_sub_id" value="<?php echo $source_sub_id; ?>">

                    <div class="alert alert-info">
                        <strong>Source Subject:</strong> <?php echo htmlspecialchars("{$source_subject_details['class_name']} - {$source_subject_details['sub_fullname']} ({$source_subject_details['subcode']})"); ?>
                    </div>

                    <div class="mb-3">
                        <label for="target_sub_ids" class="form-label"><strong>Step 2: Select Target Subject(s)</strong><br><small class="text-muted">Select all classes (with the same subject code) you want to copy data TO.</small></label>
                        <select name="target_sub_ids[]" id="target_sub_ids" class="form-select" multiple required size="8">
                            <?php foreach ($eligible_targets as $target) : ?>
                                <option value="<?php echo $target['id']; ?>">
                                    <?php echo htmlspecialchars("{$target['class_name']} - {$target['sub_fullname']}"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Step 3: Select Assessment to Analyze</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="assessment_number" id="cia1" value="1" required>
                            <label class="form-check-label" for="cia1">CIA 1</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="assessment_number" id="cia2" value="2">
                            <label class="form-check-label" for="cia2">CIA 2</label>
                        </div>
                    </div>

                    <a href="facgroupedcia.php" class="btn btn-secondary">Start Over</a>
                    <button type="submit" name="check_missing_data" class="btn btn-primary">Next: Check for Missing Data</button>
                </form>
            <?php endif; ?>


            <?php if ($currentState == 3) : ?>
                <form action="facgroupedcia.php" method="post" onsubmit="return confirm('You are about to ADD the selected items to the target subjects. This will NOT overwrite or modify any component that already has question metadata. Are you sure?');">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <input type="hidden" name="source_sub_id" value="<?php echo $source_sub_id; ?>">
                    <input type="hidden" name="assessment_number" value="<?php echo $assessment_number; ?>">
                    <?php foreach ($target_sub_ids as $tid) : ?>
                        <input type="hidden" name="target_sub_ids[]" value="<?php echo (int)$tid; ?>">
                    <?php endforeach; ?>

                    <div class="alert alert-info">
                        <p class="mb-1"><strong>Source:</strong> <?php echo htmlspecialchars($facultyObj->getSubjectDetails($source_sub_id)['data']['sub_fullname']); ?></p>
                        <p class="mb-1"><strong>Assessment:</strong> CIA <?php echo htmlspecialchars($assessment_number); ?></p>
                        <p class="mb-0"><strong>Targets:</strong>
                            <ul>
                                <?php foreach ($target_sub_ids as $tid) {
                                    echo '<li>' . htmlspecialchars($facultyObj->getSubjectDetails($tid)['data']['sub_fullname']) . '</li>';
                                } ?>
                            </ul>
                        </p>
                    </div>

                    <h5><strong>Step 4: Select Items to Import</strong></h5>
                    <p class="text-muted">Only items that have metadata (questions) in the source AND are missing (or empty) in at least one target are shown.</p>

                    <h6><i class="bi bi-file-earmark-text me-2"></i>Missing Components (with Metadata)</h6>
                    <?php if (empty($missing_components)) : ?>
                        <div class="alert alert-secondary">All components from the source (that have metadata) are already present and have metadata in all selected target subjects.</div>
                    <?php else : ?>
                        <div class="border p-3 rounded">
                            <?php foreach ($missing_components as $key => $comp) : ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="components_to_copy[]" value="<?php echo $key; ?>" id="comp_<?php echo $key; ?>" checked>
                                    <label class="form-check-label" for="comp_<?php echo $key; ?>">
                                        <?php echo htmlspecialchars($comp['component_type'] . " (Sequence: " . $comp['sequence_number'] . ")"); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4"><i class="bi bi-paperclip me-2"></i>Missing Attachments</h6>
                    <?php if (empty($missing_attachments)) : ?>
                        <div class="alert alert-secondary">All attachments from the source are already present (by title) in all selected target subjects.</div>
                    <?php else : ?>
                        <div class="border p-3 rounded">
                            <?php foreach ($missing_attachments as $key => $att) : ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="attachments_to_copy[]" value="<?php echo htmlspecialchars($key); ?>" id="att_<?php echo md5($key); ?>" checked>
                                    <label class="form-check-label" for="att_<?php echo md5($key); ?>">
                                        <?php echo htmlspecialchars($att['file_title']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="facgroupedcia.php" class="btn btn-secondary">Start Over</a>
                        <button type="submit" name="submit_import" class="btn btn-success" <?= \FeatureManager::isFacultyReadOnly('MOD_GROUPED_ATT') ? 'disabled' : ''; ?>><i class="bi bi-download me-1"></i> Import Selected Items</button>
                    </div>

                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
require_once("facfooter.php");
?>
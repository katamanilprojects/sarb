<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CO_PO');

$page_title = "Articulation Matrix";
require_once("facheader.php"); // Ensure this handles faculty login checks
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("courseoutcome.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$coObj = new CourseOutcome();
$faculty_id = $_SESSION['facid'];

$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = null;
$courseOutcomes = [];
$poPsoItems = [];
$currentMappings = [];
$err = '';
$succ = '';
$matrix_mode = \FeatureManager::isFacultyReadOnly('MOD_CO_PO') ? 'View' : 'Add';

// --- Handle POST request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (empty($_POST['secretcode']) || empty($_SESSION['secretcode']) || $_POST['secretcode'] != $_SESSION['secretcode']) {
        $err = "Invalid request. Please try again.";
        $_SESSION['secretcode'] = bin2hex(random_bytes(32)); // Regenerate code
    } else {
        unset($_SESSION['secretcode']); // Clear used code

        if (!empty($_POST['sub_id'])) {
            $selected_sub_id = (int)$_POST['sub_id'];

            // --- Handle Saving Mappings (ONLY IF ADDING) ---
            if (isset($_POST['save_mappings']) && $selected_sub_id) {
                \FeatureManager::requireWriteAccess('MOD_CO_PO');
                // Fetch COs and POs/PSOs needed for saving
                $cos_result_save = $coObj->getCOsBySubjectId($selected_sub_id);
                $pops_result_save = $coObj->getRelevantPoPso($selected_sub_id);
                $submitted_mappings = $_POST['mapping'] ?? [];

                if ($cos_result_save['status'] && $pops_result_save['status'] && !empty($cos_result_save['data']) && !empty($pops_result_save['data'])) {
                    $co_ids_for_save = array_column($cos_result_save['data'], 'id');
                    $po_pso_ids_for_save = array_column($pops_result_save['data'], 'id');

                    $changes_made = 0; $errors_occurred = 0;

                    // Iterate through submitted data and add valid mappings
                    // Note: Name attribute is mapping[co_id][po_id] now
                    foreach ($submitted_mappings as $co_id => $po_mappings) {
                         if (!in_array($co_id, $co_ids_for_save)) continue; // Skip if CO ID isn't valid for this subject

                        foreach ($po_mappings as $po_id => $submitted_value_raw) {
                            if (!in_array($po_id, $po_pso_ids_for_save)) continue; // Skip if PO ID isn't valid

                            $submitted_weightage = null;
                            // Validate submitted value - must be 1, 2, or 3
                            if ($submitted_value_raw !== null && $submitted_value_raw !== '' && is_numeric($submitted_value_raw)) {
                                $val = (int)$submitted_value_raw;
                                if (in_array($val, [1, 2, 3])) {
                                    $submitted_weightage = $val;
                                } else {
                                    // $ciaObj->logs->warningLog("Invalid weightage $val submitted for CO $co_id, PO $po_id. Ignoring.");
                                }
                            }

                            // If a valid weightage is submitted, add it using addUpdate (handles potential duplicates gracefully)
                            if ($submitted_weightage !== null) {
                                if ($coObj->addUpdateCoPoMapping($co_id, $po_id, $submitted_weightage, $selected_sub_id)) {
                                    $changes_made++;
                                } else {
                                    $errors_occurred++;
                                }
                            }
                        } // end inner loop po_id
                    } // end outer loop co_id

                    // Set success/error messages
                    if ($errors_occurred > 0) { $err = "Some errors occurred while saving mappings."; }
                    elseif ($changes_made > 0) { $succ = "Mappings added successfully."; } // Changed message
                    else { $succ = "No valid mappings submitted."; }

                } else {
                    $err = "Could not fetch COs or POs/PSOs needed to save mappings.";
                }
            } // --- End Handle Saving Mappings ---

            // --- Fetch Data for Display (runs after potential save or just for view) ---
            if ($selected_sub_id) {
                $co_result = $coObj->getCOsBySubjectId($selected_sub_id);
                if ($co_result['status']) { $courseOutcomes = $co_result['data']; }
                else { $err .= " Could not fetch Course Outcomes."; }

                $pops_result = $coObj->getRelevantPoPso($selected_sub_id);
                if ($pops_result['status']) { $poPsoItems = $pops_result['data']; }
                else { $err .= " Could not fetch POs/PSOs for this subject's regulation/specialization."; }

                // Fetch current mappings if we have COs and POs/PSOs
                if (!empty($courseOutcomes) && !empty($poPsoItems)) {
                    $co_ids = array_column($courseOutcomes, 'id');
                    $po_pso_ids = array_column($poPsoItems, 'id');
                    $currentMappings = $coObj->getCoPoMappings($co_ids, $po_pso_ids);

                    // Set Mode based on whether mappings exist
                    if (!empty($currentMappings)) {
                        $matrix_mode = 'View'; // If mappings exist, switch to View mode
                    } else {
                        $matrix_mode = 'Add'; // Otherwise, stay in Add mode
                    }
                } else {
                    $matrix_mode = 'Add';
                }
            }
        } // End !empty(sub_id)
    } // End CSRF Check Success
} // End POST handling

// Generate a new secret code for the next request
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php"); // Include faculty menu
?>

<div class="container">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_CO_PO'); ?>
    <br />
    <?php // Display success/error messages
        if (!empty($err)) { echo '<div class="alert alert-danger">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>'; }
        if (!empty($succ)) { echo '<div class="alert alert-success">' . htmlspecialchars($succ, ENT_QUOTES, 'UTF-8') . '</div>'; }
    ?>

    <div class="card mb-4">
        <div class="card-header">Select Subject</div>
        <div class="card-body">
            <form action="facarticulationmatrix.php" method="post">
                <div class="form-group mb-3">
                    <label for="sub_id" class="form-label">Subject:</label>
                    <select name="sub_id" id="sub_id" class="form-select" required>
                        <option value="">-- Select Subject --</option>
                        <?php
                        if (!empty($facultySubjects['data'])) {
                            foreach ($facultySubjects['data'] as $subject) {
                                $selected = ($selected_sub_id == $subject['id']) ? 'selected' : '';
                                $display_text = htmlspecialchars("{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}", ENT_QUOTES, 'UTF-8');
                                echo "<option value='{$subject['id']}' $selected>{$display_text}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                 <button type="submit" name="view_matrix" class="btn btn-primary">Add / View Matrix</button>
            </form>
        </div>
    </div>

    <?php // Display details only if a subject is selected
    if ($selected_sub_id):
        // Find subject details for display
        $selected_subject_details = null;
        if (!empty($facultySubjects['data'])) {
             foreach($facultySubjects['data'] as $subject) { if ($subject['id'] == $selected_sub_id) { $selected_subject_details = $subject; break; } }
        }

        // Display CO Description Table (if COs exist) - styled like facaddcos.php
        if (!empty($courseOutcomes)): ?>
            <div class="card mb-4">
                <div class="card-header">Course Outcomes (COs) for: <?= htmlspecialchars($selected_subject_details['sub_fullname'] ?? 'Selected Subject', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">CO Number</th>
                                <th>CO Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseOutcomes as $co): ?>
                                <tr>
                                    <td>CO<?= htmlspecialchars($co['co_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($co['co_description'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else:
            // Show message if COs are missing, as matrix cannot be displayed/added
             echo '<div class="alert alert-warning">Could not load Course Outcomes for the selected subject. Please define COs first using the \'Add COs\' feature.</div>';
             // We should exit here or hide the matrix part if COs are mandatory
        endif; // End CO display check

        // Display PO/PSO Information and Matrix only if COs and POs/PSOs were successfully fetched
        if (!empty($courseOutcomes) && !empty($poPsoItems)):
    ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo ($matrix_mode == 'Add' ? 'Add' : 'View'); ?> Articulation Matrix Mappings</strong>
                        <span class="badge bg-info text-dark ms-2" title="Mappings automatically sync to the Central Curriculum Master and all parallel sections"><i class="bi bi-arrow-repeat"></i> Dual-Synced (Master &amp; Sections)</span>
                    </div>
                    <small class="text-muted">Weightage: 1=Low, 2=Medium, 3=High</small>
                </div>
                <div class="card-body">
                    <?php // Form needed only in Add mode for submission ?>
                    <form action="facarticulationmatrix.php" method="post" <?php if ($matrix_mode == 'Add') echo 'onsubmit="return confirmSubmission();"'; ?>>
                         <input type="hidden" name="sub_id" value="<?= $selected_sub_id; ?>">
                         <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">

                         <div class="table-responsive">
                        
                            <table class="table table-bordered table-hover text-center" style="vertical-align: middle;">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 10%; min-width: 80px;">CO #</th>
                                        <?php foreach ($poPsoItems as $po_pso): // PO/PSO Column Headers ?>
                                            <th scope="col" title="<?= htmlspecialchars($po_pso['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="min-width: 70px;">
                                                <?= htmlspecialchars($po_pso['code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                                <br><small>(<?= htmlspecialchars($po_pso['po_pso'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>)</small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courseOutcomes as $co): // CO Rows ?>
                                        <tr>
                                            <th scope="row">
                                                CO<?= htmlspecialchars($co['co_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            </th>
                                            <?php foreach ($poPsoItems as $po_pso): // Mapping Cells ?>
                                                <td>
                                                    <?php
                                                        $co_id = $co['id'];
                                                        $po_id = $po_pso['id'];
                                                        $mapping_key = $co_id . '-' . $po_id;
                                                        $current_weightage = $currentMappings[$mapping_key] ?? '';
                                                    ?>
                                                    <?php if ($matrix_mode == 'Add'): // Input field only in Add mode ?>
                                                        <input type="number"
                                                            min="1"
                                                            max="3"
                                                            class="form-control form-control-sm mx-auto"
                                                            style="width: 60px;"
                                                            name="mapping[<?= $co_id ?>][<?= $po_id ?>]"
                                                            value="<?= htmlspecialchars($current_weightage, ENT_QUOTES, 'UTF-8'); ?>"
                                                            title="Map CO<?= htmlspecialchars($co['co_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?> to <?= htmlspecialchars($po_pso['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (1-3)">
                                                    <?php else: // Display text only in View mode ?>
                                                        <?= htmlspecialchars($current_weightage, ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; // End PO/PSO cell loop ?>
                                        </tr>
                                    <?php endforeach; // End CO row loop ?>
                                </tbody>
                            </table>
                        </div>
                        <?php // Show Save button only in Add mode ?>
                        <?php if ($matrix_mode == 'Add'): ?>
                            <div class="mt-3 text-center">
                                <button type="submit" name="save_mappings" class="btn btn-success">Add Mappings</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div> </div> <?php elseif (empty($poPsoItems) && !empty($courseOutcomes)): // Specific message if POs/PSOs not found but COs exist ?>
            <div class="alert alert-warning">Could not find POs/PSOs for the selected subject's regulation and specialization. Cannot display matrix.</div>
        <?php endif; // End PO/PSO and CO check for matrix display ?>
    <?php endif; // End subject selected check ?>

</div> <script>
function confirmSubmission() {
  // Only show confirmation if the form is actually in Add mode (although the button driving submission is hidden in View mode)
  <?php if ($matrix_mode == 'Add'): ?>
    return confirm("Are you sure you want to add these mappings? Please review carefully before submitting.");
  <?php else: ?>
    // Should not be called in view mode, but return true just in case to avoid blocking unexpected submissions
    return true;
  <?php endif; ?>
}
</script>

<?php
require_once("facfooter.php");
?>
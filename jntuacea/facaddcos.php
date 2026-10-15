<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CO_PO');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("courseoutcome.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$coObj = new CourseOutcome();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = null;

// Handle subject selection
if (!empty($_POST['sub_id'])) {
    $selected_sub_id = $_POST['sub_id'];
}

if (isset($_POST['submit_cos']) && !empty($selected_sub_id)) {
    \FeatureManager::requireWriteAccess('MOD_CO_PO');
    $coList = $_POST['co'] ?? [];
    $bloomList = $_POST['bloom_level'] ?? [];
    $threshList = $_POST['target_threshold_percent'] ?? [];

    $count = 0;
    if (is_array($coList) && !empty($coList)) {
        foreach ($coList as $num => $desc) {
            $num = (int)$num;
            $desc = trim($desc);
            if ($num > 0 && !empty($desc)) {
                $bloom = !empty($bloomList[$num]) ? trim($bloomList[$num]) : 'L3-Apply';
                $thresh = isset($threshList[$num]) && is_numeric($threshList[$num]) ? (float)$threshList[$num] : 60.0;
                $coObj->addCO($selected_sub_id, $num, $desc, $bloom, $thresh);
                $count++;
            }
        }
    }

    // Support flat keys if any (legacy compatibility)
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'co') === 0 && is_numeric(substr($k, 2))) {
            $num = (int)substr($k, 2);
            $desc = trim($v);
            if ($num > 0 && !empty($desc) && !isset($coList[$num])) {
                $bloom = !empty($_POST['bloom_level'][$num]) ? trim($_POST['bloom_level'][$num]) : 'L3-Apply';
                $thresh = isset($_POST['target_threshold_percent'][$num]) && is_numeric($_POST['target_threshold_percent'][$num]) ? (float)$_POST['target_threshold_percent'][$num] : 60.0;
                $coObj->addCO($selected_sub_id, $num, $desc, $bloom, $thresh);
                $count++;
            }
        }
    }

    if ($count > 0) {
        $_SESSION['succ'] = "Successfully saved {$count} Course Outcome(s) with Bloom's Taxonomy Levels and Target Thresholds.";
    } else {
        $_SESSION['err'] = "No valid Course Outcomes were submitted.";
    }
}

// Handle Curriculum Master CO Import
if ((!empty($_POST['import_curriculum_cos']) || !empty($_POST['import_bos_cos'])) && !empty($selected_sub_id)) {
    \FeatureManager::requireWriteAccess('MOD_CO_PO');
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $importRes = $coObj->importMasterCOsToSubject((int)$selected_sub_id);
        if ($importRes['status'] == 1 && $importRes['copied'] > 0) {
            $_SESSION['succ'] = "Successfully imported {$importRes['copied']} Master Course Outcome(s) and articulation matrix from curriculum catalog.";
        } elseif ($importRes['status'] == 1 && $importRes['copied'] == 0) {
            $_SESSION['succ'] = "Course Outcomes are already up-to-date with curriculum master.";
        } else {
            $_SESSION['err'] = "Failed to import Course Outcomes: " . ($importRes['error'] ?? 'No master COs defined for this syllabus.');
        }
    } else {
        $_SESSION['err'] = "Invalid request. Please try again.";
    }
}

// Handle CO insertion
if (!empty($_POST['add_co']) && !empty($_POST['co_number']) && !empty($_POST['co_description']) && !empty($selected_sub_id)) {
    \FeatureManager::requireWriteAccess('MOD_CO_PO');
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $co_description = trim($_POST['co_description']);
        $co_number = trim($_POST['co_number']);
        $bloom_level = !empty($_POST['bloom_level']) ? trim($_POST['bloom_level']) : 'L3-Apply';
        $target_threshold = !empty($_POST['target_threshold_percent']) ? (float)$_POST['target_threshold_percent'] : 60.0;
        $coObj->addCO($selected_sub_id, $co_number, $co_description, $bloom_level, $target_threshold);
        $_SESSION['succ'] = "Course Outcome CO{$co_number} saved successfully.";
    } else {
        $_SESSION['err'] = "Invalid request. Please try again.";
    }
}

// Fetch existing COs
$courseOutcomes = $selected_sub_id ? $coObj->getCOsBySubjectId($selected_sub_id) : [];

$selected_subcode = '';
$can_import_cos = false;
if (!empty($selected_sub_id)) {
    if (!empty($facultySubjects['data'])) {
        foreach ($facultySubjects['data'] as $fs) {
            if ($fs['id'] == $selected_sub_id) {
                $selected_subcode = trim($fs['subcode'] ?? '');
                break;
            }
        }
    }
    if (empty($selected_subcode)) {
        $stmtSub = $facultyObj->conn->prepare("SELECT subcode FROM subjects WHERE id = ?");
        $stmtSub->bind_param("i", $selected_sub_id);
        $stmtSub->execute();
        $selected_subcode = trim($stmtSub->get_result()->fetch_assoc()['subcode'] ?? '');
        $stmtSub->close();
    }
    if (!empty($selected_subcode)) {
        $can_import_cos = $coObj->hasExistingCOWithDifferentSubjectId((int)$selected_sub_id, $selected_subcode);
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php");

?>

<div class="container">
    <?= \FeatureManager::renderReadOnlyBanner('MOD_CO_PO'); ?>
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Select Subject</div>
                <div class="card-body">
                    <form action="facaddcos.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                    $subjectid = htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8');
                                    $sub_fullname = htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8');
                                    $subcode = htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8');
                                    $class_name = htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8');
                                    $acad_year = htmlspecialchars($subject['acad_year'], ENT_QUOTES, 'UTF-8');
                                    echo "<option value='$subjectid' $selected>$sub_fullname ($subcode) - $class_name - $acad_year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get COs</button>
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
        <?php if (!empty($courseOutcomes['data'])) { ?>
            <br>
            <?php if (!empty($courseOutcomes['is_inherited'])) : ?>
                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <strong><i class="bi bi-info-circle-fill"></i> Inherited from Curriculum Master Catalog:</strong>
                        <span>These COs are currently synchronized from the official curriculum syllabus catalog template.</span>
                    </div>
                    <form method="post" action="facaddcos.php" class="my-1">
                        <input type="hidden" name="sub_id" value="<?php echo htmlspecialchars($selected_sub_id); ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="import_curriculum_cos" value="1" class="btn btn-warning btn-sm">
                            <i class="bi bi-download"></i> Import as Editable Subject COs
                        </button>
                    </form>
                </div>
            <?php else : ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Subject-Specific Course Outcomes</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-light fw-bold">Added Course Outcomes (COs)</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">CO No.</th>
                                <th>Course Outcome</th>
                                <th style="width: 150px;">Bloom's Taxonomy</th>
                                <th style="width: 140px;">Target Threshold</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $max_co = 0;
                            foreach ($courseOutcomes['data'] as $index => $co) {
                                $max_co = max($max_co, (int)$co['co_number']);
                                $bloom = htmlspecialchars($co['bloom_level'] ?? 'L3-Apply');
                                $thresh = htmlspecialchars($co['target_threshold_percent'] ?? 60.0);
                                echo "<tr>";
                                echo "<td><span class='badge bg-secondary'>CO" . htmlspecialchars($co['co_number']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($co['co_description']) . "</td>";
                                echo "<td><span class='badge bg-info text-dark'>" . $bloom . "</span></td>";
                                echo "<td><span class='badge bg-light text-dark border'>" . $thresh . "%</span></td>";
                                echo "<td class='text-center'>";
                                echo "  <button type='button' class='btn btn-outline-warning btn-sm py-0 px-2 btn-edit-offering-co' ";
                                echo "          data-co-number='" . (int)$co['co_number'] . "' ";
                                echo "          data-co-desc='" . htmlspecialchars($co['co_description'] ?? '', ENT_QUOTES) . "' ";
                                echo "          data-bloom='" . htmlspecialchars($co['bloom_level'] ?? 'L3-Apply', ENT_QUOTES) . "' ";
                                echo "          data-thresh='" . htmlspecialchars($co['target_threshold_percent'] ?? '60.0', ENT_QUOTES) . "'>";
                                echo "      <i class='bi bi-pencil'></i> Edit";
                                echo "  </button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            $co_num = $max_co + 1;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <br>

            <div class="card" id="addnewBtnblock">
                <div class="card-body">
                    <button id="addnewBtn" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Click here to Add New/Missing CO</button>
                </div>
            </div>
            <div class="card" id="addnewblock" style="display: none;">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center" id="singleCoCardHeader">
                    <span><i class="bi bi-plus-circle me-1"></i>Add New Course Outcome (CO)</span>
                </div>
                <div class="card-body">
                    <form action="facaddcos.php" method="post" id="singleCoForm">
                        <div class="row">
                            <div class="col-md-3 form-group mb-3">
                                <label for="co_num" class="form-label fw-bold">CO Number:</label>
                                <input type="text" name="co_num" id="co_num" class="form-control" readonly value="CO<?php echo $co_num; ?>" />
                            </div>

                            <div class="col-md-5 form-group mb-3">
                                <label for="bloom_level" class="form-label fw-bold">Bloom's Taxonomy Level (BTL):</label>
                                <select name="bloom_level" id="bloom_level" class="form-select">
                                    <option value="L1-Remember">L1 - Remember</option>
                                    <option value="L2-Understand">L2 - Understand</option>
                                    <option value="L3-Apply" selected>L3 - Apply (Default)</option>
                                    <option value="L4-Analyze">L4 - Analyze</option>
                                    <option value="L5-Evaluate">L5 - Evaluate</option>
                                    <option value="L6-Create">L6 - Create</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label for="target_threshold_percent" class="form-label fw-bold">Target Threshold Limit (%):</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="target_threshold_percent" id="target_threshold_percent" class="form-control" value="60.0" min="1" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-12 form-group mb-3">
                                <label for="co_description" class="form-label fw-bold">CO Description / Statement <span class="text-danger">*</span>:</label>
                                <textarea name="co_description" id="co_description" class="form-control" rows="3" placeholder="At the end of the course, student will be able to..." required></textarea>
                            </div>
                        </div>

                        <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                        <input type="hidden" name="co_number" id="co_number_input" value="<?php echo $co_num; ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="add_co" value="1" id="singleCoSubmitBtn" class="btn btn-success"><i class="bi bi-check-lg"></i> Add CO</button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="cancelEditCO()">Cancel</button>
                    </form>
                </div>
            </div>

        <?php
        } else {
            echo '<br>';
            if (!empty($can_import_cos)) {
                echo '<div class="card mb-3 border-primary shadow-sm">';
                echo '  <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-box-arrow-in-down me-1"></i> Option 1: Fast-Track Import Existing Course Outcomes</div>';
                echo '  <div class="card-body">';
                echo '    <p class="text-muted mb-3">Course Outcomes already exist for subject code <strong>' . htmlspecialchars($selected_subcode) . '</strong> in the curriculum catalog or other class sections. You can import them directly into this class offering with one click.</p>';
                echo '    <form method="post" action="facaddcos.php">';
                echo '      <input type="hidden" name="sub_id" value="' . htmlspecialchars($selected_sub_id) . '">';
                echo '      <input type="hidden" name="secretcode" value="' . $_SESSION['secretcode'] . '">';
                echo '      <button type="submit" name="import_curriculum_cos" value="1" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> Import Course Outcomes &amp; Matrix</button>';
                echo '    </form>';
                echo '  </div>';
                echo '</div>';
            }

            echo '<div class="card shadow-sm">';
            echo '<div class="card-header bg-light fw-bold">' . (!empty($can_import_cos) ? 'Option 2: Manually Define Course Outcomes' : 'Add Course Outcomes') . '</div>';
            echo '<div class="card-body">';
            echo '<div class="form-group mb-3" id="divnoofcos"><label class="form-label fw-bold">How many COs are defined for this Course?</label><div class="input-group" style="max-width: 320px;"><input type="number" class="form-control" name="noofcos" id="noofcos" min="1" max="10" placeholder="e.g. 5" required /><button type="button" name="getfields" id="getfields" class="btn btn-outline-primary">Generate Fields</button></div></div>';
            echo '<form id="addcosform" method="post"></form>';
            echo '</div>';
            echo "</div>";

            echo '<script>
                const getfields = document.getElementById("getfields");
                const noofcos = document.getElementById("noofcos");
                const divnoofcos = document.getElementById("divnoofcos");
                const addcosform = document.getElementById("addcosform");

                getfields.addEventListener("click", function() {
                    const numCOs = parseInt(noofcos.value);
            
                    if (isNaN(numCOs) || numCOs <= 0) {
                        alert("Please enter a valid number of COs (e.g. 5).");
                        return;
                    }
            
                    divnoofcos.style.display = "none";
                    addcosform.innerHTML = `
                        <input type="hidden" name="sub_id" value="' . htmlspecialchars($selected_sub_id) . '">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="bi bi-info-circle me-1"></i> Configure the <strong>Bloom\'s Taxonomy Level (BTL)</strong>, <strong>Target Threshold Limit (%)</strong>, and <strong>Statement</strong> for each Course Outcome below.
                        </div>
                    `;
            
                    for (let i = 1; i <= numCOs; i++) {
                        const card = document.createElement("div");
                        card.classList.add("card", "mb-3", "border", "shadow-sm");
                        card.innerHTML = `
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold text-primary"><i class="bi bi-tag-fill me-1"></i>Course Outcome ${i}</span>
                                <span class="badge bg-secondary">CO${i}</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Bloom\'s Taxonomy Level (BTL):</label>
                                        <select name="bloom_level[${i}]" class="form-select form-select-sm">
                                            <option value="L1-Remember">L1 - Remember</option>
                                            <option value="L2-Understand">L2 - Understand</option>
                                            <option value="L3-Apply" selected>L3 - Apply (Default)</option>
                                            <option value="L4-Analyze">L4 - Analyze</option>
                                            <option value="L5-Evaluate">L5 - Evaluate</option>
                                            <option value="L6-Create">L6 - Create</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Target Threshold Limit (%):</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.1" name="target_threshold_percent[${i}]" class="form-control" value="60.0" min="1" max="100" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">CO Statement / Description <span class="text-danger">*</span>:</label>
                                        <textarea name="co[${i}]" class="form-control" rows="2" placeholder="At the end of the course, student will be able to..." required></textarea>
                                    </div>
                                </div>
                            </div>
                        `;
                        addcosform.appendChild(card);
                    }
            
                    const submitDiv = document.createElement("div");
                    submitDiv.classList.add("d-flex", "gap-2", "mt-3");
                    submitDiv.innerHTML = `
                        <button type="submit" name="submit_cos" value="1" class="btn btn-success"><i class="bi bi-save me-1"></i> Save All COs</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="location.reload();">Reset</button>
                    `;
                    addcosform.appendChild(submitDiv);
                });
            </script>';
        }
        ?>
    <?php endif; ?>
    <script>
        const addnewBtn = document.getElementById("addnewBtn");
        const addnewBtnblock = document.getElementById("addnewBtnblock");
        const addnewblock = document.getElementById("addnewblock");
        if (addnewBtn) {
            addnewBtn.addEventListener("click", function() {
                resetSingleCoForm();
                addnewBtnblock.style.display = "none";
                addnewblock.style.display = "block";
            });
        }

        // Edit existing CO in table
        document.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit-offering-co');
            if (editBtn) {
                const coNum = editBtn.dataset.coNumber;
                const coDesc = editBtn.dataset.coDesc;
                const bloom = editBtn.dataset.bloom;
                const thresh = editBtn.dataset.thresh;

                document.getElementById('co_num').value = 'CO' + coNum;
                document.getElementById('co_number_input').value = coNum;
                document.getElementById('co_description').value = coDesc;
                if (bloom) document.getElementById('bloom_level').value = bloom;
                if (thresh) document.getElementById('target_threshold_percent').value = thresh;

                const header = document.getElementById('singleCoCardHeader');
                if (header) {
                    header.innerHTML = '<span class="text-warning"><i class="bi bi-pencil-square me-1"></i>Edit Course Outcome (CO' + coNum + ')</span>';
                }
                const submitBtn = document.getElementById('singleCoSubmitBtn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Update CO' + coNum;
                    submitBtn.classList.remove('btn-success');
                    submitBtn.classList.add('btn-primary');
                }

                if (addnewBtnblock) addnewBtnblock.style.display = 'none';
                if (addnewblock) {
                    addnewblock.style.display = 'block';
                    addnewblock.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });

        function cancelEditCO() {
            resetSingleCoForm();
            if (addnewblock) addnewblock.style.display = 'none';
            if (addnewBtnblock) addnewBtnblock.style.display = 'block';
        }

        function resetSingleCoForm() {
            const defaultNext = '<?= $co_num ?? 1 ?>';
            document.getElementById('co_num').value = 'CO' + defaultNext;
            document.getElementById('co_number_input').value = defaultNext;
            document.getElementById('co_description').value = '';
            document.getElementById('bloom_level').value = 'L3-Apply';
            document.getElementById('target_threshold_percent').value = '60.0';

            const header = document.getElementById('singleCoCardHeader');
            if (header) {
                header.innerHTML = '<span><i class="bi bi-plus-circle me-1"></i>Add New Course Outcome (CO)</span>';
            }
            const submitBtn = document.getElementById('singleCoSubmitBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Add CO';
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-success');
            }
        }
    </script>
</div>

<?php
require_once("facfooter.php");
?>
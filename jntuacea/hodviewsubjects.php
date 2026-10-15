<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Classes";
require_once("hod.class.php");
require_once("subject.class.php");
require_once __DIR__ . "/services/FeatureManager.php";
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$obj = new HOD();
$subObj = new Subject();
$class_id = $_POST['class_id'];

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    if (!empty($_POST['subject_sno']) && !empty($_POST['subcode']) && !empty($_POST['sub_shortname']) && !empty($_POST['sub_fullname']) && !empty($_POST['sub_type']) && !empty($_POST['class_id'])) {
        $numBatches = !empty($_POST['num_batches']) ? (int)$_POST['num_batches'] : 1;

        if ($numBatches <= 1) {
            // Single batch subject
            $res = $subObj->createOffering(
                $class_id,
                !empty($_POST['curr_sub_id']) ? $_POST['curr_sub_id'] : null,
                $_POST['subject_sno'],
                $_POST['subcode'],
                $_POST['sub_shortname'],
                $_POST['sub_fullname'],
                $_POST['sub_type'],
                $_POST['group_name'] ?? ''
            );
            $res_arr = ['status' => $res ? 1 : 0, 'err' => $res ? '' : 'A subject with this code and group may already exist.'];
            if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
                $msg = "Subject added successfully.";
            } else {
                $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to add subject. Please try again.";
            }
        } else {
            // Multi-batch subject: generate standard records with same subcode and (Group - Letter) fullname
            $groupLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            $successCount = 0;
            $errors = [];

            for ($b = 0; $b < $numBatches; $b++) {
                $grpLetter = $groupLetters[$b];
                $grpSuffix = " (Group - " . $grpLetter . ")";

                $batchData = $_POST;
                $batchData['subcode'] = trim($_POST['subcode']); // Preserve exact curriculum subcode
                $batchData['sub_fullname'] = trim($_POST['sub_fullname']) . $grpSuffix;
                // Short name with group suffix, bounded to 20 chars
                $baseShort = trim($_POST['sub_shortname']);
                $batchData['sub_shortname'] = substr($baseShort . "-" . $grpLetter, 0, 20);

                $bOk = $subObj->createOffering(
                    $class_id,
                    !empty($batchData['curr_sub_id']) ? $batchData['curr_sub_id'] : null,
                    $batchData['subject_sno'],
                    $batchData['subcode'],
                    $batchData['sub_shortname'],
                    $batchData['sub_fullname'],
                    $batchData['sub_type'],
                    $grpLetter // group_name stored as 'A', 'B', etc. for UNIQUE KEY (class_id, subcode, group_name)
                );
                $bRes = ['status' => $bOk ? 1 : 0, 'err' => $bOk ? '' : 'Failed for ' . $batchData['subcode'] . ' (Group - ' . $grpLetter . ')'];
                if (!empty($bRes['status']) && $bRes['status'] == 1) {
                    $successCount++;
                } else {
                    $errors[] = (!empty($bRes['err'])) ? $bRes['err'] : "Failed for " . $batchData['subcode'] . ' (Group - ' . $grpLetter . ')';
                }
            }

            if ($successCount === $numBatches) {
                $msg = "All " . $numBatches . " batches added successfully (Group " . implode(", Group ", array_slice($groupLetters, 0, $numBatches)) . ").";
            } elseif ($successCount > 0) {
                $msg = $successCount . " of " . $numBatches . " batches added. Errors: " . implode("; ", $errors);
            } else {
                $msg = "Failed to add batches: " . implode("; ", $errors);
            }
        }
    }

    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "deletesubject" && !empty($_POST['subject_id'])) {
        $ok = $subObj->deleteOffering((int)$_POST['subject_id']);
        $res_arr = ['status' => $ok ? 1 : 0, 'err' => $ok ? '' : 'Cannot delete: attendance may exist for this subject.'];
        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject Deleted successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to delete subject. Please try again.";
        }
    }

    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "sync_curriculum_cos") {
        $syncRes = $subObj->syncClassOfferingsFromCurriculum((int)$class_id);
        $msg = "Curriculum Sync completed: {$syncRes['cos_copied']} Course Outcome(s) and {$syncRes['mappings_copied']} Articulation cell(s) verified/added for this class.";
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Fetch subjects for the selected class
$subjectList = $subObj->getSubjectsByClass($class_id);

// Fetch class info to load central curriculum subjects
$classInfo = $obj->getClassById($class_id);
$curriculumSubjects = [];
if (!empty($classInfo) && !empty($classInfo['reg_id']) && !empty($classInfo['spec_id']) && !empty($classInfo['yearsem'])) {
    require_once("curriculum_subject.class.php");
    $currObj = new CurriculumSubject();
    $currRes = $currObj->getSubjectsForClass($classInfo['reg_id'], $classInfo['spec_id'], $classInfo['yearsem']);
    $curriculumSubjects = $currRes['data'] ?? [];
}
// Prepare lookup of existing subcodes for badge indicator
$existingSubcodes = [];
if (!empty($subjectList)) {
    foreach ($subjectList as $s) {
        $clean = strtoupper(trim($s['subcode']));
        $existingSubcodes[$clean] = true;
    }
}
?>

<div class="container">
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <strong>Specialization:</strong> <?= htmlspecialchars($_POST['spec_fullname'] ?? '') ?> | 
                <strong>Class:</strong> <?= htmlspecialchars($_POST['class_fullname'] ?? '') ?>
            </div>
            <div>
                <a href="hodviewclasses.php" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-arrow-left me-1"></i>All Classes
                </a>
            </div>
        </div>
    </div>
</div>
<div class="container">
    <div class="row">
        <div class="col-sm-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-plus-circle me-1"></i>Add Subject to Class</span>
                    <span class="badge bg-light text-primary">From Curriculum</span>
                </div>
                <div class="card-body">
                    <form action="hodviewsubjects.php" method="post" id="hodAddSubjectForm" onsubmit="return validateHodForm()">
                        <?php if (!empty($curriculumSubjects)): ?>
                            <!-- 1. Course Dropdown -->
                            <div class="form-group mb-3">
                                <label for="curr_sub_picker" class="form-label fw-bold text-dark">
                                    <i class="bi bi-journal-bookmark-fill me-1 text-primary"></i>1. Select Course / Subject: <span class="text-danger">*</span>
                                </label>
                                <select id="curr_sub_picker" class="form-select" onchange="applyCurriculumSubject(this)" required>
                                    <option value="">-- Choose Course from Curriculum --</option>
                                    <?php foreach ($curriculumSubjects as $cs): 
                                        $isAdded = isset($existingSubcodes[strtoupper(trim($cs['subcode']))]);
                                        $catPrefix = !empty($cs['course_category']) ? '[' . $cs['course_category'] . '] ' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($cs['subcode']) ?>"
                                                data-id="<?= $cs['id'] ?>"
                                                data-sno="<?= htmlspecialchars($cs['subject_sno']) ?>"
                                                data-subcode="<?= htmlspecialchars($cs['subcode']) ?>"
                                                data-fullname="<?= htmlspecialchars($cs['sub_fullname']) ?>"
                                                data-shortname="<?= htmlspecialchars($cs['sub_shortname']) ?>"
                                                data-type="<?= htmlspecialchars($cs['sub_type']) ?>"
                                                data-category="<?= htmlspecialchars($cs['course_category'] ?? '') ?>"
                                                data-l="<?= htmlspecialchars($cs['lecture_hours']) ?>"
                                                data-t="<?= htmlspecialchars($cs['tutorial_hours']) ?>"
                                                data-pr="<?= htmlspecialchars($cs['pr_hours'] ?? '0.0') ?>"
                                                data-p="<?= htmlspecialchars($cs['practical_hours']) ?>"
                                                data-c="<?= htmlspecialchars($cs['credits']) ?>">
                                            <?= htmlspecialchars($cs['subject_sno'] . '. ' . $catPrefix . '[' . $cs['subcode'] . '] ' . $cs['sub_fullname'] . ' (' . $cs['sub_type'] . ')') . ($isAdded ? ' ✓ [Already Added]' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Courses pre-configured by Academic Section for this syllabus.</small>
                            </div>

                            <!-- Selected Course Info Card (Dynamic Preview) -->
                            <div id="selectedCourseInfoCard" class="card bg-light border mb-3" style="display: none;">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong id="infoSubCode" class="text-primary font-monospace fs-6"></strong>
                                        <div>
                                            <span id="infoSubCategory" class="badge bg-secondary me-1"></span>
                                            <span id="infoSubType" class="badge bg-info text-dark"></span>
                                        </div>
                                    </div>
                                    <div id="infoSubName" class="fw-semibold text-dark mb-1"></div>
                                    <small class="text-muted" id="infoHoursCredits"></small>
                                </div>
                            </div>
                        <!-- 2. Batches Dropdown -->
                        <div class="form-group mb-3 p-2 bg-light border rounded">
                            <label for="num_batches" class="form-label fw-bold text-dark mb-1">
                                <i class="bi bi-people-fill me-1 text-primary"></i>2. Batches / Groups: <span class="text-danger">*</span>
                            </label>
                            <select name="num_batches" id="num_batches" class="form-select form-select-sm" onchange="updateBatchPreview()">
                                <option value="1">1 Batch - Whole Class (Default)</option>
                                <option value="2">2 Batches (Group A, Group B)</option>
                                <option value="3">3 Batches (Group A, Group B, Group C)</option>
                                <option value="4">4 Batches (Group A, Group B, Group C, Group D)</option>
                            </select>
                            <div id="batchPreviewBox" class="small mt-2 p-2 border rounded bg-white text-dark" style="display: none;"></div>
                        </div>

                        <!-- Hidden fields carrying curriculum values to backend -->
                        <input type="hidden" name="curr_sub_id" id="curr_sub_id" value="">
                        <input type="hidden" name="subject_sno" id="subject_sno" value="">
                        <input type="hidden" name="subcode" id="subcode" value="">
                        <input type="hidden" name="sub_fullname" id="sub_fullname" value="">
                        <input type="hidden" name="sub_shortname" id="sub_shortname" value="">
                        <input type="hidden" name="sub_type" id="sub_type" value="Theory">
                        <input type="hidden" name="group_name" id="group_name" value="">

                        <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                        <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                        <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary px-4" id="submitSubjectBtn">Add Subject to Class</button>
                    </form>
                    <?php else: ?>
                        <div class="alert alert-warning py-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i> No curriculum subjects found for this regulation and semester. Please contact the <strong>Academic Section</strong> to configure the Curriculum Subjects for this syllabus.
                        </div>
                    <?php endif; ?>
                    <?php if (isset($msg)) echo '<div class="alert alert-info mt-3 mb-0">' . $msg . '</div>'; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light fw-bold"><i class="bi bi-info-circle me-1"></i>Curriculum Integration Guidelines</div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>Standardized Curriculum Selection:</strong>
                            <p class="mb-1 text-muted small">Select the course directly from the <strong>Course Dropdown</strong>. Subject S.No, Code, Short Name, Full Name, Category, and Type are automatically fetched from the Academic Syllabus catalog.</p>
                        </li>
                        <br />
                        <li><strong>Automated Batch / Group Generator:</strong>
                            <ul>
                                <li class="text-muted small">For Labs or practical courses divided into groups, select <strong>2, 3, or 4 Batches</strong> from the dropdown.</li>
                                <li class="text-muted small">The system will <strong>automatically generate</strong> standard-compliant entries (e.g. <code>ABC123: Lab (Group - A)</code> and <code>ABC123: Lab (Group - B)</code>) in a single click.</li>
                                <li class="text-muted small">Ensures 100% compatibility with Attendance Marking, Faculty Allocation, Timetable Scheduling, and CIA Analysis.</li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <br>
    <br>
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold d-inline me-2"><i class="bi bi-table me-2"></i>Class Subjects (<?= count($subjectList) ?>)</h6>
                <span class="badge bg-secondary"><?= htmlspecialchars($_POST['class_fullname'] ?? '') ?></span>
            </div>
            <?php if (!empty($subjectList)): ?>
            <form action="hodviewsubjects.php" method="post" class="m-0">
                <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                <input type="hidden" name="whattodo" value="sync_curriculum_cos" />
                <button type="submit" class="btn btn-sm btn-outline-primary" title="Sync / Verify COs & Matrix from Curriculum Master">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync Missing COs &amp; Matrix from Curriculum
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 50px;">S.No.</th>
                            <th style="width: 130px;">Code</th>
                            <th>Short Name</th>
                            <th class="text-start">Full Name</th>
                            <th style="width: 100px;">Type</th>
                            <th style="width: 90px;">Category</th>
                            <th style="width: 70px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($subjectList)) {
                            $sno = "";
                            foreach ($subjectList as $subject) {
                                echo '<tr>';
                                if ($sno == $subject['subject_sno']) {
                                    $style = '';
                                } else {
                                    $style = " style='border-top: 2px solid #dee2e6;'";
                                    $sno = $subject['subject_sno'];
                                }
                                echo "<td {$style} class='fw-bold'>" . htmlspecialchars($subject['subject_sno']) . "</td>";
                                echo "<td {$style}><span class='badge bg-primary fs-7'>" . htmlspecialchars($subject['subcode']) . "</span></td>";
                                echo "<td {$style}>" . htmlspecialchars($subject['sub_shortname']) . "</td>";
                                echo "<td {$style} class='text-start fw-semibold'>" . htmlspecialchars($subject['sub_fullname']) . "</td>";
                                echo "<td {$style}><span class='badge bg-light text-dark border'>" . htmlspecialchars($subject['sub_type']) . "</span></td>";
                                echo "<td {$style}>" . (!empty($subject['course_category']) ? '<span class="badge bg-secondary">' . htmlspecialchars($subject['course_category']) . '</span>' : '<span class="text-muted">-</span>') . "</td>";
                                echo "<td {$style}>";
                        ?>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if (\FeatureManager::isHodVisible('MOD_COURSE_MASTER_FILE')): ?>
                                    <a href="download_coursemasterfile.php?sub_id=<?= (int)$subject['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Download Course Master File">
                                        <i class="bi bi-journal-bookmark"></i>
                                    </a>
                                    <?php endif; ?>
                                    <form action="hodviewsubjects.php" method="post" onsubmit="return confirm('Are you sure you want to Delete Subject:  <?= htmlspecialchars($subject['subcode'] ?? ''); ?> ?');" class="m-0">
                                        <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                        <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                        <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                        <input type="hidden" name="subject_id" value="<?php echo $subject["id"]; ?>" />
                                        <input type="hidden" name="whattodo" value="deletesubject" />
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Subject">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                        <?php
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-muted py-4'>No subjects found for this class. Select a course and batches above to add subjects.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function applyCurriculumSubject(selectEl) {
    if (!selectEl.value) {
        const infoCard = document.getElementById('selectedCourseInfoCard');
        if (infoCard) infoCard.style.display = 'none';
        document.getElementById('curr_sub_id').value = '';
        document.getElementById('subject_sno').value = '';
        document.getElementById('subcode').value = '';
        document.getElementById('sub_fullname').value = '';
        document.getElementById('sub_shortname').value = '';
        document.getElementById('sub_type').value = 'Theory';
        updateBatchPreview();
        return;
    }

    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt) return;

    const id = opt.dataset.id || '';
    const sno = opt.dataset.sno || '';
    const code = opt.dataset.subcode || '';
    const fullname = opt.dataset.fullname || '';
    const shortname = opt.dataset.shortname || '';
    const type = opt.dataset.type || 'Theory';
    const cat = opt.dataset.category || '';
    const l = opt.dataset.l || '0.0';
    const t = opt.dataset.t || '0.0';
    const pr = opt.dataset.pr || '0.0';
    const p = opt.dataset.p || '0.0';
    const c = opt.dataset.c || '0.0';

    // Populate hidden inputs
    document.getElementById('curr_sub_id').value = id;
    document.getElementById('subject_sno').value = sno;
    document.getElementById('subcode').value = code;
    document.getElementById('sub_fullname').value = fullname;
    document.getElementById('sub_shortname').value = shortname;
    document.getElementById('sub_type').value = type;

    // Update dynamic preview card
    const infoCard = document.getElementById('selectedCourseInfoCard');
    if (infoCard) {
        document.getElementById('infoSubCode').textContent = code;
        document.getElementById('infoSubName').textContent = fullname;
        document.getElementById('infoSubType').textContent = type;
        const catBadge = document.getElementById('infoSubCategory');
        if (cat) {
            catBadge.textContent = cat;
            catBadge.style.display = 'inline-block';
        } else {
            catBadge.style.display = 'none';
        }
        document.getElementById('infoHoursCredits').textContent = 'Lecture (L): ' + l + ' | Tutorial (T): ' + t + ' | Practical (Pr): ' + pr + ' | Lab (P): ' + p + ' | Credits: ' + c;
        infoCard.style.display = 'block';
    }

    updateBatchPreview();
}

function updateBatchPreview() {
    const numBatches = parseInt(document.getElementById('num_batches').value) || 1;
    const previewBox = document.getElementById('batchPreviewBox');
    const submitBtn = document.getElementById('submitSubjectBtn');
    const code = (document.getElementById('subcode').value || '').trim();
    const name = (document.getElementById('sub_fullname').value || '').trim();

    if (!code || !name) {
        if (previewBox) {
            previewBox.style.display = 'none';
            previewBox.innerHTML = '';
        }
        if (submitBtn) submitBtn.textContent = 'Add Subject to Class';
        return;
    }

    if (numBatches <= 1) {
        if (previewBox) {
            previewBox.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Single offering (Whole Class): <code>' + code + '</code> - ' + name + '</span>';
            previewBox.style.display = 'block';
        }
        if (submitBtn) submitBtn.textContent = 'Add Subject to Class';
        return;
    }

    const groupLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
    let html = '<div class="fw-bold mb-1"><i class="bi bi-diagram-3 me-1 text-primary"></i>Will automatically generate ' + numBatches + ' batch subjects:</div><ul class="mb-0 ps-3">';

    for (let i = 0; i < numBatches; i++) {
        const subCode = code;
        const subName = name + ' (Group - ' + groupLetters[i] + ')';
        html += '<li><code>' + subCode + '</code>: ' + subName + '</li>';
    }
    html += '</ul>';

    if (previewBox) {
        previewBox.innerHTML = html;
        previewBox.style.display = 'block';
    }
    if (submitBtn) {
        submitBtn.textContent = 'Add ' + numBatches + ' Batches to Class';
    }
}

function validateHodForm() {
    const subcode = (document.getElementById('subcode').value || '').trim();
    const fullname = (document.getElementById('sub_fullname').value || '').trim();
    if (!subcode || !fullname) {
        alert('Please select a course from the curriculum dropdown.');
        const picker = document.getElementById('curr_sub_picker');
        if (picker) picker.focus();
        return false;
    }
    return true;
}

// Auto-initialize on load if a course is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const picker = document.getElementById('curr_sub_picker');
    if (picker && picker.value) {
        applyCurriculumSubject(picker);
    }
});
</script>

<?php
require_once("hodfooter.php");
?>
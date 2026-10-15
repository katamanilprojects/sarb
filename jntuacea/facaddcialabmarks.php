<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once __DIR__ . "/services/SettingsService.php";

$facultyObj = new Faculty();
$ciaObj = new CIA();
$settingsSvc = \Services\SettingsService::getInstance();

$currentSubId = !empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : (!empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0);
$subReg = $currentSubId > 0 ? $ciaObj->getRegulationIdForSubject($currentSubId) : 'R23';

$maxDayToDay = (float)$settingsSvc->get('lab_cia_day_to_day_marks', $subReg, 15.0);
$maxInternalTest = (float)$settingsSvc->get('lab_cia_internal_test_marks', $subReg, 15.0);

// Load Rubric Criteria if feature enabled
$rubricCriteria = [];
if (\FeatureManager::isFacultyVisible('MOD_LAB_RUBRICS')) {
    $conn = \DBCredentials::getInstance()->getConnection();
    if ($currentSubId > 0) {
        $stmtRub = $conn->prepare("SELECT id, criterion_name, '' as criterion_description, max_marks, sort_order FROM lab_subject_rubrics WHERE subject_id = ? ORDER BY sort_order ASC");
        $stmtRub->bind_param("i", $currentSubId);
        $stmtRub->execute();
        $rubricCriteria = $stmtRub->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtRub->close();
    }
    if (empty($rubricCriteria)) {
        $stmtTpl = $conn->prepare("SELECT c.id, c.criterion_name, c.criterion_description, c.max_marks, c.sort_order 
                                   FROM lab_rubric_criteria c 
                                   JOIN lab_rubric_templates t ON c.template_id = t.id 
                                   WHERE t.is_default = 1 ORDER BY c.sort_order ASC");
        $stmtTpl->execute();
        $rubricCriteria = $stmtTpl->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtTpl->close();
    }
}

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'] && !empty($_POST['submit_action'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];
    $submitAction = $_POST["submit_action"];

    require_once __DIR__.'/services/LabAssessmentService.php';
    try {(new \Services\LabAssessmentService())->save($_POST);$_SESSION['succ']=$submitAction==='submit'?'Lab assessment submitted successfully.':'Draft lab marks saved.';}
    catch (Throwable $e) {$_SESSION['err']=$e->getMessage();}
    // Redirect back to facciamarks.php after saving
    header("Location: facciamarks.php");
    exit();
}

// Get subject details and student list
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList = $facultyObj->getMappedStudents($sub_id);

    // Fetch temporary marks if available
    $tempMarks = $ciaObj->getUGLabTempMarks($sub_id, $assessmentNumber);
    // Prefill form with temp marks if available
    foreach ($studentList as $key => $student) {
        if (!empty($tempMarks[$student['id']])) {
            $studentList[$key]['day_to_day_marks'] = $tempMarks[$student['id']]['day_to_day_marks'];
            $studentList[$key]['internal_test_marks'] = $tempMarks[$student['id']]['internal_test_marks'];
        }
    }

    // Generate new secret code
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
    // Handle invalid request (e.g., redirect to an error page)
    header("Location: facciamarks.php");
    exit();
}

require_once("facheader.php");
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Adding Continuous Internal Assessment (Lab) Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="facaddcialabmarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.<br />Student Name</th>
                                    <th>
                                        Day-to-Day (Max <?= $maxDayToDay ?>)
                                        <?php if (\FeatureManager::isFacultyVisible('MOD_LAB_RUBRICS')): ?>
                                            <button type="button" class="btn btn-xs btn-outline-info ms-2 py-0 px-2 rounded-pill" onclick="openRubricsAssistant(0)" title="Launch Rubrics Assistant">
                                                <i class="fas fa-th-list me-1"></i> Rubrics Assistant
                                            </button>
                                        <?php endif; ?>
                                    </th>
                                    <th>Internal Test (Max <?= $maxInternalTest ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $key => $student) : ?>
                                    <tr>
                                        <td>
                                            <?php echo $student['username']; ?><br /><?php echo $student['name']; ?>
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <div id="rubric_hidden_<?= $key; ?>"></div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="text" name="day_to_day_marks[]" id="d2d_<?= $key; ?>" class="form-control" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['day_to_day_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> />
                                                <?php if (\FeatureManager::isFacultyVisible('MOD_LAB_RUBRICS')): ?>
                                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="openStudentRubric(<?= $key; ?>, <?= (int)$student['id']; ?>, '<?= htmlspecialchars($student['username'], ENT_QUOTES); ?>', '<?= htmlspecialchars($student['name'], ENT_QUOTES); ?>')" title="Rubric Breakdown Modal" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-calculator"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><input type="text" name="internal_test_marks[]" class="form-control" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['internal_test_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> /></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="submit_action" value="submit" class="pull-left btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Validate & Submit Marks</button>
                            <button type="submit" name="submit_action" value="save" class="text-end btn btn-secondary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Save without Submitting</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <p class="text-muted">
                        <strong>Note:</strong>
                        <br>
                        The <strong>"Submit"</strong> button will finalize and lock your marks submission.
                        <br>
                        The <strong>"Save without Submitting"</strong> button will save your progress temporarily and you can continue later.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (\FeatureManager::isFacultyVisible('MOD_LAB_RUBRICS')): ?>
<!-- Rubrics Assistant Modal -->
<div class="modal fade" id="labRubricsModal" tabindex="-1" aria-labelledby="labRubricsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="labRubricsModalLabel">
                    <i class="fas fa-th-list text-warning me-2"></i> Continuous Lab Evaluation Rubric
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong id="rubricStudentTitle">Student Evaluation</strong>
                    <div class="small text-muted" id="rubricStudentSubtitle">Enter component marks according to NBA Criterion 2.2 continuous evaluation rubrics.</div>
                </div>

                <form id="rubricModalForm">
                    <?php if (!empty($rubricCriteria)): ?>
                        <?php foreach ($rubricCriteria as $crit): ?>
                            <div class="card mb-2 border-light shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-bold mb-0 text-dark">
                                            <?= htmlspecialchars($crit['criterion_name']); ?>
                                        </label>
                                        <span class="badge bg-secondary">Max: <?= htmlspecialchars($crit['max_marks']); ?> M</span>
                                    </div>
                                    <?php if (!empty($crit['criterion_description'])): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($crit['criterion_description']); ?></p>
                                    <?php endif; ?>
                                    <input type="number" step="0.25" min="0" max="<?= (float)$crit['max_marks']; ?>" 
                                           class="form-control rubric-crit-input" 
                                           data-crit-id="<?= (int)$crit['id']; ?>" 
                                           data-crit-name="<?= htmlspecialchars($crit['criterion_name'], ENT_QUOTES); ?>"
                                           data-max="<?= (float)$crit['max_marks']; ?>"
                                           placeholder="0.00 - <?= (float)$crit['max_marks']; ?>" 
                                           oninput="calcRubricSum()">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No rubric criteria configured.</p>
                    <?php endif; ?>

                    <div class="alert alert-info py-2 d-flex justify-content-between align-items-center mt-3 mb-0">
                        <span class="fw-bold"><i class="fas fa-equals me-1"></i> Total Day-to-Day:</span>
                        <span class="fs-5 fw-bold text-primary" id="rubricTotalDisplay">0.00 / <?= $maxDayToDay; ?></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold" onclick="applyRubricToStudent()">
                    <i class="fas fa-check-circle me-1"></i> Apply to Student Score
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRubricRow = 0;
let currentRubricStudentId = 0;
const maxD2DGlobal = <?= json_encode($maxDayToDay); ?>;

function openStudentRubric(rowIndex, studentId, username, name) {
    currentRubricRow = rowIndex;
    currentRubricStudentId = studentId;

    document.getElementById('rubricStudentTitle').textContent = username + ' - ' + name;
    document.getElementById('rubricStudentSubtitle').textContent = 'Continuous laboratory assessment for Mid / Assessment ' + <?= json_encode($assessmentNumber); ?>;

    // Reset inputs
    document.querySelectorAll('.rubric-crit-input').forEach(input => {
        input.value = '';
    });
    calcRubricSum();

    const modalEl = document.getElementById('labRubricsModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } else {
        $(modalEl).modal('show');
    }
}

function openRubricsAssistant(startRow) {
    const studentInputs = document.querySelectorAll('input[name="student_id[]"]');
    if (studentInputs.length > 0) {
        const idx = startRow || 0;
        const firstTr = studentInputs[idx].closest('tr');
        const firstTd = firstTr.querySelector('td');
        const lines = firstTd.innerText.trim().split('\n');
        openStudentRubric(idx, parseInt(studentInputs[idx].value), lines[0] || '', lines[1] || '');
    }
}

function calcRubricSum() {
    let sum = 0.0;
    document.querySelectorAll('.rubric-crit-input').forEach(input => {
        const val = parseFloat(input.value);
        const max = parseFloat(input.dataset.max || 5.0);
        if (!isNaN(val)) {
            if (val > max) {
                input.value = max;
                sum += max;
            } else if (val < 0) {
                input.value = 0;
            } else {
                sum += val;
            }
        }
    });

    const display = document.getElementById('rubricTotalDisplay');
    if (display) {
        display.textContent = sum.toFixed(2) + ' / ' + maxD2DGlobal;
        if (sum > maxD2DGlobal) {
            display.classList.remove('text-primary');
            display.classList.add('text-danger');
        } else {
            display.classList.remove('text-danger');
            display.classList.add('text-primary');
        }
    }
    return sum;
}

function applyRubricToStudent() {
    const sum = calcRubricSum();
    if (sum > maxD2DGlobal) {
        alert('Total marks (' + sum.toFixed(2) + ') cannot exceed maximum allowed: ' + maxD2DGlobal);
        return;
    }

    const d2dInput = document.getElementById('d2d_' + currentRubricRow);
    if (d2dInput) {
        d2dInput.value = sum.toFixed(2);
        d2dInput.style.border = "2px solid #198754";
        setTimeout(() => { d2dInput.style.border = ""; }, 1500);
    }

    // Save hidden inputs for criterion breakdown
    const hiddenContainer = document.getElementById('rubric_hidden_' + currentRubricRow);
    if (hiddenContainer && currentRubricStudentId > 0) {
        hiddenContainer.innerHTML = '';
        document.querySelectorAll('.rubric-crit-input').forEach(input => {
            const critId = input.dataset.critId;
            const critName = input.dataset.critName;
            const val = parseFloat(input.value) || 0.0;

            const inScore = document.createElement('input');
            inScore.type = 'hidden';
            inScore.name = 'rubric_breakdown[' + currentRubricStudentId + '][' + critId + ']';
            inScore.value = val;
            hiddenContainer.appendChild(inScore);

            const inName = document.createElement('input');
            inName.type = 'hidden';
            inName.name = 'rubric_crit_names[' + critId + ']';
            inName.value = critName;
            hiddenContainer.appendChild(inName);
        });
    }

    const modalEl = document.getElementById('labRubricsModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    } else {
        $(modalEl).modal('hide');
    }
}
</script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitButton = document.querySelector('button[name="submit_action"][value="submit"]');

        form.onsubmit = function(e) {
            // Only perform validation if the "Submit" button was clicked
            if (submitButton && submitButton.clicked) {
                const daytodayInputs = document.querySelectorAll('input[name="day_to_day_marks[]"]');
                const internaltestInputs = document.querySelectorAll('input[name="internal_test_marks[]"]');

                let isValid = true;
                let firstInvalidInput = null; // To store the first invalid input

                const maxD2D = <?= json_encode($maxDayToDay) ?>;
                const maxInt = <?= json_encode($maxInternalTest) ?>;

                daytodayInputs.forEach((input, index) => {
                    const day_to_day_marks = parseFloat(input.value);
                    const internal_test_marks = parseFloat(internaltestInputs[index].value);

                    // Validate if the inputs are filled and within the specified ranges
                    if (
                        isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0 ||
                        isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0
                    ) {
                        isValid = false;

                        // Identify the first invalid input and focus on it
                        if (!firstInvalidInput) {
                            if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                                firstInvalidInput = internaltestInputs[index];
                            } else if (isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0) {
                                firstInvalidInput = input;
                            }
                        }

                        // Apply red border to invalid fields
                        if (isNaN(day_to_day_marks) || day_to_day_marks > maxD2D || day_to_day_marks < 0) {
                            input.style.border = "2px solid red"; // Highlight invalid day_to_day field
                        }else{
                            input.style.border = "";
                        }
                        if (isNaN(internal_test_marks) || internal_test_marks > maxInt || internal_test_marks < 0) {
                            internaltestInputs[index].style.border = "2px solid red"; // Highlight invalid internal_test field
                        }else{
                            internaltestInputs[index].style.border = "";
                        }
                    }else{
                        // Reset the border if valid
                        input.style.border = "";
                        internaltestInputs[index].style.border = "";
                    }
                });

                if (!isValid) {
                    e.preventDefault(); // Prevent form submission if validation fails
                    alert("Please enter valid marks:\n* Day-To-Day: 0-" + maxD2D + "\n* Internal-Test: 0-" + maxInt);

                    // Focus on the first invalid input and set the cursor there
                    if (firstInvalidInput) {
                        firstInvalidInput.focus();  // Focus on the first invalid input
                        setTimeout(() => {
                            firstInvalidInput.scrollIntoView({
                                behavior: "smooth",
                                block: "center",
                                inline: "nearest"
                            });
                        }, 100); // Ensure it's visible
                    }
                }
            }
        };

        // Track which button was clicked
        document.querySelectorAll('button[name="submit_action"]').forEach(button => {
            button.onclick = function() {
                if (submitButton) {
                    submitButton.clicked = this.value === 'submit';
                }
            };
        });
    });
</script>

<?php
require_once("facfooter.php");
?>
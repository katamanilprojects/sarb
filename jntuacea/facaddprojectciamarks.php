<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once __DIR__ . "/services/SettingsService.php";
require_once __DIR__ . "/services/FeatureManager.php";

\FeatureManager::requireAccess('MOD_CIA_MARKS');

$facultyObj = new Faculty();
$ciaObj = new CIA();
$settingsSvc = \Services\SettingsService::getInstance();

$currentSubId = !empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : (!empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0);
$subReg = $currentSubId > 0 ? $ciaObj->getRegulationIdForSubject($currentSubId) : 'R23';

$projStructure = $settingsSvc->getProjectStructure($subReg);
$totalInternal = (float)($projStructure['internal_marks'] ?? 60.0);
$maxComp = $totalInternal / 2; // Equal split for Supervisor and PRC

if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'] && !empty($_POST['submit_action'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];
    $submitAction = $_POST["submit_action"];

    if ($submitAction == 'save') {
        foreach ($_POST['student_id'] as $index => $studentId) {
            $ciaObj->saveUGProjectTempMarks($studentId, $sub_id, $assessmentNumber, $_POST['component1_marks'][$index], $_POST['component2_marks'][$index]);
        }
        $_SESSION["succ"] = "Project CIA Marks Saved Temporarily";

    } elseif ($submitAction == 'submit') {
        $isValid = true;
        foreach ($_POST['student_id'] as $index => $studentId) {
            $c1 = $_POST['component1_marks'][$index];
            $c2 = $_POST['component2_marks'][$index];
            if (!is_numeric($c1) || !is_numeric($c2) || $c1 > $maxComp || $c1 < 0 || $c2 > $maxComp || $c2 < 0) {
                $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
                $isValid = false;
                break;
            }
        }
        if ($isValid) {
            foreach ($_POST['student_id'] as $index => $studentId) {
                $ciaObj->addUGProjectInternalAssessmentMarks($studentId, $sub_id, $assessmentNumber, $_POST['component1_marks'][$index], $_POST['component2_marks'][$index]);
                $ciaObj->deleteUGProjectTempMarks($studentId, $sub_id, $assessmentNumber);
            }
            $_SESSION["succ"] = "Project CIA Marks Successfully Added";
        }
    }
    header("Location: facciamarks.php");
    exit();
}

if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList = $facultyObj->getMappedStudents($sub_id);
    $tempMarks = $ciaObj->getUGProjectTempMarks($sub_id, $assessmentNumber);
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
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
                    Adding Project CIA Marks - <?php echo $assessmentNumber; ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="facaddprojectciamarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.<br />Student Name</th>
                                    <th>Supervisor (Max <?= $maxComp ?>)</th>
                                    <th>P. R. C. (Max <?= $maxComp ?>)</th>
                                    <th>Total (Max <?= $totalInternal ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $student) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?><br /><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?><input type="hidden" name="student_id[]" value="<?php echo htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                                        <td><input type="text" name="component1_marks[]" class="form-control comp-input" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['component1_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> /></td>
                                        <td><input type="text" name="component2_marks[]" class="form-control comp-input" maxlength="5" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['component2_marks'] : ''; ?>" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : ''; ?> /></td>
                                        <td class="row-total">-</td>
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
                        <strong>Note:</strong><br>
                        The <strong>"Submit"</strong> button will finalize and lock your marks submission.<br>
                        The <strong>"Save without Submitting"</strong> button will save your progress temporarily and you can continue later.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const submitButton = document.querySelector('button[name="submit_action"][value="submit"]');

    function updateRowTotal(row) {
        const inputs = row.querySelectorAll('.comp-input');
        const c1 = parseFloat(inputs[0].value);
        const c2 = parseFloat(inputs[1].value);
        const totalCell = row.querySelector('.row-total');
        if (!isNaN(c1) && !isNaN(c2)) {
            totalCell.textContent = (c1 + c2).toFixed(1).replace(/\.0$/, '');
        } else {
            totalCell.textContent = '-';
        }
    }

    document.querySelectorAll('tbody tr').forEach(function (row) {
        updateRowTotal(row);
        row.querySelectorAll('.comp-input').forEach(function (input) {
            input.addEventListener('input', function () { updateRowTotal(row); });
        });
    });

    form.onsubmit = function (e) {
        if (submitButton.clicked) {
            const rows = document.querySelectorAll('tbody tr');
            let isValid = true;
            let firstInvalid = null;

            const maxComp = <?= json_encode($maxComp) ?>;
            rows.forEach(function (row) {
                const inputs = row.querySelectorAll('.comp-input');
                [0, 1].forEach(function (i) {
                    const val = parseFloat(inputs[i].value);
                    if (isNaN(val) || val < 0 || val > maxComp) {
                        isValid = false;
                        inputs[i].style.border = '2px solid red';
                        if (!firstInvalid) firstInvalid = inputs[i];
                    } else {
                        inputs[i].style.border = '';
                    }
                });
            });

            if (!isValid) {
                e.preventDefault();
                alert("Please enter valid marks:\n* Supervisor: 0-" + maxComp + "\n* P. R. C.: 0-" + maxComp);
                if (firstInvalid) {
                    firstInvalid.focus();
                    setTimeout(function () {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }
            }
        }
    };

    document.querySelectorAll('button[name="submit_action"]').forEach(function (btn) {
        btn.onclick = function () { submitButton.clicked = this.value === 'submit'; };
    });
});
</script>

<?php require_once("facfooter.php"); ?>

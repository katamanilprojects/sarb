<?php
require_once __DIR__ . '/bootstrap.php';


use Mpdf\Tag\Mark;

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_METADATA');

$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];

// Validate GET parameters
if (empty($_GET['sub_id']) || empty($_GET['assessment_number'])) {
    $_SESSION['err'] = "Invalid request. Missing required parameters.";
    header("Location: facciamarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$assessment_number = intval($_GET['assessment_number']);

$cos = $ciaObj->getCOsBySubjectId($sub_id);
if (empty($cos['data']) || !empty($cos['is_inherited'])) {
    echo "<script>alert('Please Add Course Outcomes to Proceed Further.');window.location.href = 'faceditoptions.php';</script>";
    exit;
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$prg_res = $facultyObj->getPrgCodeBySubID($sub_id);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_CIA_METADATA');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['component_type']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $component_type = $_POST['component_type'];
    $addResult = $ciaObj->addAssessmentComponent($sub_id, $assessment_number, $component_type);

    if ($addResult['status'] == 1) {
        $_SESSION['succ'] = "Assessment Component added successfully.";
    } else {
        $_SESSION['err'] = "Failed to add Assessment Component.";
    }
}
// Fetch Assessment Components
$components = $ciaObj->getAssessmentComponents($sub_id, $assessment_number);

if ($prg_res['sub_type'] == "lab") {
    $comps_arr = array("Day-to-Day", "Internal Exam");
} elseif ($prg_res['sub_type'] == "dti") {
    $comps_arr = array("Subjective-1", "Objective-1", "Subjective-2", "Objective-2", "Activity");
} else {
    $comps_arr = array("Subjective", "Objective", "Assignment");
}
$present_comps = array();
if (!empty($components['data'])) {
    foreach ($components['data'] as $index => $component) {
        array_push($present_comps, $component['component_type']);
    }
}
// Ensure at least one of each type exists
foreach ($comps_arr as $mycomps) {
    if (!in_array($mycomps, $present_comps) && $mycomps != "Day-to-Day") {
        $a = $ciaObj->addAssessmentComponent($sub_id, $assessment_number, $mycomps);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $ciaObj->addAssessmentComponent($sub_id, $assessment_number, "Assignment");
    $_SESSION['succ'] = "New Assignment added successfully.";
    header("Location: facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $ciaObj->addAssessmentComponent($sub_id, $assessment_number, "Activity");
    $_SESSION['succ'] = "New Activity added successfully.";
    header("Location: facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_day_to_day'])) {
    // Retrieve date value from POST
    $label = $_POST['d2d_date'];
    if (!empty($prg_res['start_date']) && !empty($prg_res['end_date'])) {
        if ($label < $prg_res['start_date'] || $label > $prg_res['end_date']) {
            $_SESSION['err'] = "Date must be between " . date('d-m-Y', strtotime($prg_res['start_date'])) . " and " . date('d-m-Y', strtotime($prg_res['end_date'])) . ".";
            header("Location: facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number");
            exit;
        }
    }
    $resComp = $ciaObj->addAssessmentComponent($sub_id, $assessment_number, "Day-to-Day");

    if (!empty($resComp['status']) && $resComp['status'] == 1 && !empty($resComp['comp_id'])) {
        $_SESSION['succ'] = "New day-to-Day added successfully.";
        //$label = "18-10-2029";
        $type = "Day-to-Day";
        $marks = 15;
        $blooms_level = 1;
        $question_id = $ciaObj->addQuestion($resComp['comp_id'], $label, $type, $marks, $blooms_level);
        $selected_cos = array();
        foreach ($cos['data'] as $index => $co) {
            array_push($selected_cos, $co['id']);
            break;
        }
        if ($question_id && count($selected_cos) > 0) {
            $res = $ciaObj->addQuestionCOs($question_id, $selected_cos);
        }
    }
    header("Location: facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number");
    exit;
}

$components = $ciaObj->getAssessmentComponents($sub_id, $assessment_number);


// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php");
$allMarksEntered = 1;
?>
<?php $url_get_data = "sub_id=" . $sub_id . "&assessment_number=" . $assessment_number; ?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Adding Detailed Continuous Internal Assessment Marks - <?php echo $assessment_number; ?> </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>) - <?php echo $prg_res['classname']; ?>
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_METADATA'); ?>
                    <?php if (!empty($_SESSION["succ"])) { ?>
                        <div class="alert alert-success"><?php echo $_SESSION["succ"];
                                                            unset($_SESSION["succ"]); ?></div>
                    <?php } ?>
                    <?php if (!empty($_SESSION["err"])) { ?>
                        <div class="alert alert-danger"><?php echo $_SESSION["err"];
                                                        unset($_SESSION["err"]); ?></div>
                    <?php } ?>


                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Component Type</th>
                                <th>Question Paper MetaData</th>
                                <th>Student Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($components['data'])) {
                                $present_comps = array();
                                $no_of_assignments = 1;
                                foreach ($components['data'] as $index => $component) {

                                    $displayType = $component['component_type'];

                                    if ($prg_res['sub_type'] == "lab") {
                                        if ($displayType === "Day-to-Day") {
                                            // $displayType .= " " . $assessment_number . "." . $component['sequence_number'];
                                            if ($component['sequence_number'] > $no_of_assignments) {
                                                $no_of_assignments = $component['sequence_number'];
                                            }
                                        }
                                    } else {
                                        if ($displayType === "Assignment") {
                                            $displayType .= " " . $assessment_number . "." . $component['sequence_number'];
                                            if ($component['sequence_number'] > $no_of_assignments) {
                                                $no_of_assignments = $component['sequence_number'];
                                            }
                                        }
                                        if ($displayType === "Activity") {
                                            $displayType .= " - ".$component['sequence_number'];
                                            if ($component['sequence_number'] > $no_of_assignments) {
                                                $no_of_assignments = $component['sequence_number'];
                                            }
                                        }
                                    }

                                    $questions = $ciaObj->getQuestionsByComponent($component['id']);
                                    $marks = $ciaObj->getMarksByComponent($component['id']);
                                    if (!empty($questions)) {
                                        if (!empty($marks)) {
                                            if ($displayType == "Day-to-Day") {
                                                $show_date = $questions[0]['question_label'] ? $questions[0]['question_label'] : "NA";
                                                $date_object = DateTime::createFromFormat('Y-m-d', $show_date);
                                                $formatted_date = $date_object->format('d-m-Y');

                                                echo "<tr>"
                                                    . "<td>" . ($index + 1) . "</td>"
                                                    . "<td>" . htmlspecialchars($displayType) . "</td>"
                                                    . "<td>Dated. " . $formatted_date . "</td>"
                                                    . "<td><a class='btn btn-outline-success' href='facviewmarks.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>View Marks</a></td>"
                                                    . "</tr>";
                                            } else {
                                                echo "<tr>"
                                                    . "<td>" . ($index + 1) . "</td>"
                                                    . "<td>" . htmlspecialchars($displayType) . "</td>"
                                                    . "<td><a class='btn btn-outline-success' href='facquestionco.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>View Questions</a></td>"
                                                    . "<td><a class='btn btn-outline-success' href='facviewmarks.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>View Marks</a></td>"
                                                    . "</tr>";
                                            }
                                        } else {
                                            $allMarksEntered = 0;
                                            if ($displayType == "Day-to-Day") {
                                                $show_date = $questions[0]['question_label'] ? $questions[0]['question_label'] : "NA";
                                                $date_object = DateTime::createFromFormat('Y-m-d', $show_date);
                                                $formatted_date = $date_object->format('d-m-Y');

                                                echo "<tr>"
                                                    . "<td>" . ($index + 1) . "</td>"
                                                    . "<td>" . htmlspecialchars($displayType) . "</td>"
                                                    . "<td>Dated. " . $formatted_date . "</td>"
                                                    . "<td><a class='btn btn-outline-primary' href='facmarksentry.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>Marks Entry</a></td>"
                                                    . "</tr>";
                                            } else {
                                                echo "<tr>"
                                                    . "<td>" . ($index + 1) . "</td>"
                                                    . "<td>" . htmlspecialchars($displayType) . "</td>"
                                                    . "<td><a class='btn btn-outline-success' href='facquestionco.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>View Questions</a></td>"
                                                    . "<td><a class='btn btn-outline-primary' href='facmarksentry.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>Marks Entry</a></td>"
                                                    . "</tr>";
                                            }
                                        }
                                    } else {
                                        $allMarksEntered = 0;
                                        echo "<tr>"
                                            . "<td>" . ($index + 1) . "</td>"
                                            . "<td>" . htmlspecialchars($displayType) . "</td>"
                                            . "<td><a class='btn btn-outline-primary' href='facciacompques.php?" . $url_get_data . "&component_id=" . $component['id'] . "&component_type=" . htmlspecialchars($component['component_type']) . "'>Add Questions</a></td>"
                                            . "<td></td></tr>";
                                    }

                                    array_push($present_comps, $component['component_type']);
                                }
                            } else {
                                echo "<tr><td colspan='2' class='text-center'>No components found.</td></tr>";
                            } ?>
                        </tbody>
                    </table>
                    <?php
                    if ($prg_res['sub_type'] == "lab"
                        && !empty($prg_res['end_date']) && $prg_res['end_date'] < date('Y-m-d')
                        && empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))) {
                        $has_pending_d2d = false;
                        if (!empty($components['data'])) {
                            foreach ($components['data'] as $c) {
                                if ($c['component_type'] === 'Day-to-Day' && empty($ciaObj->getMarksByComponent($c['id']))) {
                                    $has_pending_d2d = true;
                                    break;
                                }
                            }
                        }
                        if ($has_pending_d2d) {
                            echo "<div class='mb-2'><a href='facbulkd2dmarks.php?" . $url_get_data . "' class='btn btn-warning'>Bulk Day-to-Day Marks Entry</a></div>";
                        }
                    }
                    ?>
                    <?php if ($allMarksEntered) {
                        if ($prg_res['sub_type'] == "lab") {
                            if (empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))) {
                                echo "<div class='alert alert-info'>";
                                echo "<a href='facuglabciamarkscondensed.php?$url_get_data' class='btn btn-primary'>View & Submit CIA Marks</a>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-info float-end'>";
                                echo "<a href='facuglabciamarkscondensed.php?$url_get_data' class='btn btn-outline-success float-end'>View Submitted CIA Marks</a>";
                                echo "</div>";
                            }
                        } elseif ($prg_res['sub_type'] == "dti") {
                            if (empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))) {
                                echo "<div class='alert alert-info'>";
                                echo "<a href='facdticiamarkscondensed.php?$url_get_data' class='btn btn-primary'>View & Submit CIA Marks</a>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-info float-end'>";
                                echo "<a href='facdticiamarkscondensed.php?$url_get_data' class='btn btn-outline-success float-end'>View Submitted CIA Marks</a>";
                                echo "</div>";
                            }
                        } else {
                            if (empty($ciaObj->isInternalAssessmentAdded($sub_id, $assessment_number))) {
                                echo "<div class='alert alert-info'>";
                                echo "<a href='facciamarkscondensed.php?$url_get_data' class='btn btn-primary'>View & Submit CIA Marks</a>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-info float-end'>";
                                echo "<a href='facciamarkscondensed.php?$url_get_data' class='btn btn-outline-success float-end'>View Submitted CIA Marks</a>";
                                echo "</div>";
                            }
                        }
                    } ?>
                </div>
                <div class="card-footer">

                    <?php
                    if ($prg_res['sub_type'] == "lab") : ?>
                        <?php if (empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                            <!-- Add New Day-to-Day Component Toggle Button and Form -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('d2dForm').style.display='block'; this.style.display='none';" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA') ? 'disabled' : ''; ?>>
                                Click here to Add New Day-to-Day Component <?php if (!empty($no_of_assignments)) {
                                                                                echo "(Day-to-Day - " . $assessment_number . "." . ($no_of_assignments + 1) . ")";
                                                                            } ?>
                            </button>
                            <form method="POST" id="d2dForm" style="display:none;">
                                <input type="hidden" name="add_day_to_day" value="1">
                                <div class="mb-2">
                                    <label for="d2d_date">Select Date for Day-to-Day Component:</label>
                                    <input type="date" name="d2d_date" id="d2d_date" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA') ? 'disabled' : ''; ?>>
                                    Confirm Add Day-to-Day Component
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($prg_res['sub_type'] == "dti") : ?>
                        <strong>Notes:</strong>
                        <ul>
                            <li>Each Design Thinking & Innovation - Continuous Internal Assessment Can have:
                                <ul>
                                    <li>One or more Activities</li>
                                </ul>
                            </li>
                        </ul>
                        <?php if (empty($ciaObj->isUGLabInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                            <!-- Add New Assignment Button -->
                            <form method="POST">
                                <input type="hidden" name="add_activity" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA') ? 'disabled' : ''; ?>>Click here Add New Activity <?php if (!empty($no_of_assignments)) {
                                                                                                                                echo "(Activity - " . ($no_of_assignments + 1) . "";                                                                                                          } ?></button>
                            </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <strong>Notes:</strong>
                        <ul>
                            <li>Each Continuous Internal Assessment Can have:
                                <ul>
                                    <li>One Subjective</li>
                                    <li>One Objective</li>
                                    <li>One or more Assignments</li>
                                </ul>
                            </li>
                        </ul>
                        <?php if (empty($ciaObj->isInternalAssessmentAdded($sub_id, $assessment_number))): ?>
                            <!-- Add New Assignment Button -->
                            <form method="POST">
                                <input type="hidden" name="add_assignment" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_METADATA') ? 'disabled' : ''; ?>>Click here Add New Assignment <?php if (!empty($no_of_assignments)) {
                                                                                                                                echo "(Assignment - " . $assessment_number . "." . ($no_of_assignments + 1) . ")";
                                                                                                                            } ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif;
                    ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>
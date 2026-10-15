<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once("services/FeatureManager.php");
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);

// Handle subject selection
$selected_sub_id = null;
$assessmentDetails = [];

if (!empty($_POST['sub_id']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $selected_sub_id = $_POST['sub_id'];

    $prg_res = $facultyObj->getPrgCodeBySubID($selected_sub_id);
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Continuous Internal Assessment Marks and their Related Attachments</div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <form action="facciamarks.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                    echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get Continuous Assessment Status</button>
                    </form>
                </div>
                <?php
                if (!empty($_SESSION["succ"])) {
                    echo '<div class="alert alert-success">' . $_SESSION["succ"] . '</div>';
                    unset($_SESSION["succ"]);
                }
                if (!empty($_SESSION["err"])) {
                    echo '<div class="alert alert-danger">' . $_SESSION["err"] . '</div>';
                    unset($_SESSION["err"]);
                }
                ?>
            </div>

            <?php if (!empty($selected_sub_id)) : ?>

                <br>
                <div class="card">
                    <?php
                    if (!empty($prg_res['prg_code'])) {
                        if ($prg_res['prg_code'] == "A") {
                            if ($prg_res['sub_type'] != "lab") {
                                echo '<div class="card-header">Note: <ul><li>Add Detailed Student Marks to get the Course Analysis.</li><li>If you Add Detailed Student Marks, the System will automatically generate Overall marks for each component, so that you can view and Submit them (No manual calculations are needed).</li></ul></div>';
                            }
                        }
                    }
                    ?>

                    <div class="card-body">
                        <?php
                        if (!empty($prg_res['prg_code'])) {
                            if ($prg_res['prg_code'] == "A") {
                                if ($prg_res['sub_type'] == "lab") {
                                    // Fetch assessment details for both assessments
                                    for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                                        $assessmentDetails[$assessmentNumber] = $ciaObj->isUGLabInternalAssessmentAdded($selected_sub_id, $assessmentNumber);
                                    }
                        ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th rowspan="2">Assessment </th>
                                                <th rowspan="2">Status</th>
                                                <th colspan="2" style="text-align: center;">Marks</th>
                                                <th rowspan="2">Attachments</th>
                                            </tr>
                                            <tr>
                                                <th>Detailed</th>
                                                <th>Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) : ?>
                                                <tr>
                                                    <td>Internal Assessment </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "Not Added";
                                                        } else {
                                                            echo "Added";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        echo "<a href='facciacomp.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add/View Detailed</a>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "<a href='facaddcialabmarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add</a>";
                                                        } else {
                                                            echo "<a href='facviewcialabmarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-info btn-sm'>View</a>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        echo "<a href='facciaattachments.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-primary btn-sm'>Attachments</a>";
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                <?php
                                } elseif ($prg_res['sub_type'] == "dti") {
                                    // Fetch assessment details for both assessments
                                    for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) {
                                        $assessmentDetails[$assessmentNumber] = $ciaObj->isUGLabInternalAssessmentAdded($selected_sub_id, $assessmentNumber);
                                    }
                        ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th rowspan="2">Assessments </th>
                                                <th rowspan="2">Status</th>
                                                <th colspan="2" style="text-align: center;">Marks</th>
                                                <th rowspan="2">Attachments</th>
                                            </tr>
                                            <tr>
                                                <th>Detailed</th>
                                                <th>Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) : ?>
                                                <tr>
                                                    <td>Internal Assessment </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "Not Added";
                                                        } else {
                                                            echo "Added";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        echo "<a href='facciacomp.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add/View Detailed</a>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "<a href='facadddticiamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add</a>";
                                                        } else {
                                                            echo "<a href='facviewcialabmarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-info btn-sm'>View</a>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        echo "<a href='facciaattachments.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-primary btn-sm'>Attachments</a>";
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                <?php
                                } elseif ($prg_res['sub_type'] == "project") {
                                    $assessmentDetails[1] = $ciaObj->isUGProjectInternalAssessmentAdded($selected_sub_id, 1);
                                ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Assessment</th>
                                                <th>Status</th>
                                                <th>Marks (Overall)</th>
                                                <th>Attachments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Internal Assessment</td>
                                                <td><?php echo empty($assessmentDetails[1]) ? 'Not Added' : 'Added'; ?></td>
                                                <td>
                                                    <?php
                                                    if (empty($assessmentDetails[1])) {
                                                        echo "<a href='facaddprojectciamarks.php?sub_id={$selected_sub_id}&assessment_number=1' class='btn btn-success btn-sm'>Add</a>";
                                                    } else {
                                                        echo "<a href='facviewprojectciamarks.php?sub_id={$selected_sub_id}&assessment_number=1' class='btn btn-info btn-sm'>View</a>";
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo "<a href='facciaattachments.php?sub_id={$selected_sub_id}&assessment_number=1' class='btn btn-primary btn-sm'>Attachments</a>"; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php
                                } else {
                                    // Fetch assessment details for both assessments Theory
                                    for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                                        $assessmentDetails[$assessmentNumber] = $ciaObj->isInternalAssessmentAdded($selected_sub_id, $assessmentNumber);
                                    }
                                ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th rowspan="2">Assessment Number</th>
                                                <th rowspan="2">Status</th>
                                                <th colspan="2" style="text-align: center;">Marks</th>
                                                <th rowspan="2">Attachments</th>
                                            </tr>
                                            <tr>
                                                <th>Detailed</th>
                                                <th>Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) : ?>
                                                <tr>
                                                    <td>Internal Assessment <?php echo $assessmentNumber; ?></td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "Not Added";
                                                        } else {
                                                            echo "Added";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        echo "<a href='facciacomp.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add/View Detailed</a>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "<a href='facaddciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add Overall</a>";
                                                        } else {
                                                            echo "<a href='facviewciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-info btn-sm'>View Overall</a>";
                                                        }
                                                        ?>
                                                    </td>

                                                    <td>
                                                        <?php
                                                        echo "<a href='facciaattachments.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-primary btn-sm'>Attachments</a>";
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                <?php
                                }
                            } else {
                                if ($prg_res['sub_type'] == "lab") {
                                    // Fetch assessment details for both assessments and assignment
                                    for ($assessmentNumber = 11; $assessmentNumber <= 11; $assessmentNumber++) {
                                        $assessmentDetails[$assessmentNumber] = $ciaObj->isPGInternalAssessmentAdded($selected_sub_id, $assessmentNumber);
                                    }
                                ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Assessment Name</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($assessmentNumber = 11; $assessmentNumber <= 11; $assessmentNumber++) : ?>
                                                <tr>
                                                    <td><?php
                                                        if ($assessmentNumber == 3) {
                                                            echo 'Final Assignment Marks ';
                                                        } else {
                                                            echo 'Final Internal Assessment';
                                                        }
                                                        ?></td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "Not Added";
                                                        } else {
                                                            echo "Added";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "<a href='facaddpgciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add</a>";
                                                        } else {
                                                            echo "<a href='facviewpgciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-info btn-sm'>View</a>";
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                <?php
                                } else {
                                    // Fetch assessment details for both assessments and assignment
                                    for ($assessmentNumber = 1; $assessmentNumber <= 3; $assessmentNumber++) {
                                        $assessmentDetails[$assessmentNumber] = $ciaObj->isPGInternalAssessmentAdded($selected_sub_id, $assessmentNumber);
                                    }
                                ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Assessment Name</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($assessmentNumber = 1; $assessmentNumber <= 3; $assessmentNumber++) : ?>
                                                <tr>
                                                    <td><?php
                                                        if ($assessmentNumber == 3) {
                                                            echo 'Final Assignment Marks ';
                                                        } else {
                                                            echo 'Internal Assessment ' . $assessmentNumber;
                                                        }
                                                        ?></td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "Not Added";
                                                        } else {
                                                            echo "Added";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (empty($assessmentDetails[$assessmentNumber])) {
                                                            echo "<a href='facaddpgciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-success btn-sm'>Add</a>";
                                                        } else {
                                                            echo "<a href='facviewpgciamarks.php?sub_id={$selected_sub_id}&assessment_number={$assessmentNumber}' class='btn btn-info btn-sm'>View</a>";
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                        <?php
                                }
                            }
                        }
                        ?>

                    </div>

                </div>


                <?php
                // extra code - not used
                // Check if both assessments are added before calculating final marks
                if (!empty($assessmentDetails[1]) && !empty($assessmentDetails[2]) && !empty($studentList)) {
                    // Calculate final marks for each student
                    foreach ($studentList as &$student) {
                        $assessment1Total = $student['marks'][1]['subjective_marks'] + $student['marks'][1]['objective_marks'] + $student['marks'][1]['assignment_marks'];
                        $assessment2Total = $student['marks'][2]['subjective_marks'] + $student['marks'][2]['objective_marks'] + $student['marks'][2]['assignment_marks'];

                        // Consider 80% of the best assessment and 20% of the remaining one
                        if ($assessment1Total > $assessment2Total) {
                            $finalMarks = (0.8 * $assessment1Total) + (0.2 * $assessment2Total);
                        } else {
                            $finalMarks = (0.8 * $assessment2Total) + (0.2 * $assessment1Total);
                        }

                        $student['final_marks'] = $finalMarks;
                    }

                    // Display final assessment marks
                    echo "<table class='table table-bordered mt-3'>";
                    echo "<thead>
                                        <tr>
                                            <th>Admission No / Roll No</th>
                                            <th>Student Name</th>
                                            <th>Final Internal Marks</th> 
                                        </tr>
                                    </thead>
                                    <tbody>";

                    foreach ($studentList as $student) :
                        echo "<tr>";
                        echo "<td>" . $student['username'] . "</td>";
                        echo "<td>" . $student['name'] . "</td>";
                        echo "<td>" . round($student['final_marks'], 2) . "</td>";
                        echo "</tr>";
                    endforeach;

                    echo "</tbody>
                                </table>";
                }
                ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once("facfooter.php");
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_METADATA');
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$cia = new CIA();


if (!isset($_GET['sub_id']) || !isset($_GET['assessment_number']) || !isset($_GET['component_id']) || !isset($_GET['component_type'])) {
    header("Location: facciamarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$assessment_number = intval($_GET['assessment_number']);
$component_id = intval($_GET['component_id']);
$component_type = htmlspecialchars($_GET['component_type']);

$cos = $cia->getCOsBySubjectId($sub_id);
if (empty($cos['data']) || !empty($cos['is_inherited'])) {
    echo "<script>alert('Please Add Course Outcomes to Proceed Further.');window.location.href = 'faceditoptions.php';</script>";
    exit;
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$prg_res = $facultyObj->getPrgCodeBySubID($sub_id);

$questions = $cia->getQuestionsByComponent($component_id);

if (!empty($questions)) {
    echo "<script>window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
}

$blooms_levels = $cia->getBloomsLevels();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_questions'])) {

    foreach ($_POST['questions'] as $index => $question) {
        if (!isset($question['cos']) || !is_array($question['cos']) || count($question['cos']) < 1) {
            echo "<script>alert('Each Question must have atleast one CO mapped. Please try again'); window.location.href='facciacompques.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
            exit();
        }
    }

    foreach ($_POST['questions'] as $index => $question) {
        $type = $question['type'];
        $marks = $question['marks'];
        $blooms_level = $question['blooms_level'];
        $selected_cos = isset($question['cos']) ? $question['cos'] : [];

        $label = $question['label'];

        $question_id = $cia->addQuestion($component_id, $label, $type, $marks, $blooms_level);
        if ($question_id && count($selected_cos) > 0) {
            $res = $cia->addQuestionCOs($question_id, $selected_cos);
        }
    }

    if ($res) {
        echo "<script>alert('Questions added successfully!'); window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
    } else {
        echo "<script>window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
    }
}

$num_questions = 0;
$marks_per_question = 0;
$default_question_type = "";

$templates = array();
$templates["s1"] = array('noofques' => 6, 'marks' => 10, 'types' => array('Question', 'Other'), 'desc' => '6 (3x2) Question type Questions, Total Marks: 30, Converts to 15');
$templates["o1"] = array('noofques' => 20, 'marks' => 1, 'types' => array('MCQ', 'Short_answer', 'Blanks', 'Match', 'T_or_F', 'Other'), 'desc' => '20 (1 Mark) Questions, - Total Marks: 20, converts to 10');
$templates["o2"] = array('noofques' => 20, 'marks' => 0.5, 'types' => array('MCQ', 'Short_answer', 'Blanks', 'Match', 'T_or_F', 'Other'), 'desc' => '20 (0.5 Mark) Questions - Total Marks: 10');
$templates["o3"] = array('noofques' => 10, 'marks' => 1, 'types' => array('Short_answer', 'MCQ', 'Blanks', 'Match', 'T_or_F', 'Other'), 'desc' => '10 (1 Mark) Questions - Total Marks: 10');
$templates["o4"] = array('noofques' => 5, 'marks' => 2, 'types' => array('Short_answer', 'MCQ', 'Blanks', 'Match', 'T_or_F', 'Other'), 'desc' => '5 (2 Marks) Questions - Total Marks: 10');
$templates["a1"] = array('noofques' => 1, 'marks' => 5, 'types' => array('Problem', 'Project', 'Activity', 'Quiz', 'Other'), 'desc' => '(1 (5 Marks) Question/Topic/Activity - Total Marks: 5');

$templates["uglabint"] = array('noofques' => 1, 'marks' => 15, 'types' => array('Problem', 'Experiment'), 'desc' => 'Total Marks: 15');

$templates["dtis1"] = array('noofques' => 4, 'marks' => 10, 'types' => array('Question', 'Other'), 'desc' => '4 (2x2) Question type Questions, Total Marks: 20');
$templates["dtia1"] = array('noofques' => 1, 'marks' => 15, 'types' => array('Activity', 'Presentation', 'Report', 'Other'), 'desc' => '(1 (15 Marks) Question/Topic/Activity - Total Marks: 15');

$template_ids = array();
$template_ids["Subjective"] = array("s1");
$template_ids["Objective"] = array("o1", "o2", "o3", "o4");
$template_ids["Assignment"] = array("a1");
$template_ids["Internal Exam"] = array("uglabint");

$template_ids["Subjective-1"] = array("dtis1");
$template_ids["Objective-1"] = array("o1", "o2", "o3", "o4");
$template_ids["Subjective-2"] = array("dtis1");
$template_ids["Objective-2"] = array("o1", "o2", "o3", "o4");
$template_ids["Activity"] = array("dtia1");

if ($component_type == "Assignment"){
    $_GET['template_id'] = "a1";
}
if ($component_type == "Activity"){
    $_GET['template_id'] = "dtia1";
}
require_once "facheader.php";
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header">Adding Detailed Continuous Internal Assessment Marks - <?php echo $assessment_number; ?> </div>
        <div class="card-header">
            <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
        </div>
        <?php if (!empty($_GET['template_id']) && array_key_exists($_GET['template_id'], $templates)): ?>
            <div class="card-body">
                <?php if (($component_type == "Subjective" || $component_type == "Subjective-1" || $component_type == "Subjective-2" || $component_type == "Internal Exam") && empty($_POST["s1_no_of_ques"])): ?>
                    <form method="POST">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Q.No</th>
                                    <th>How many Parts (like a,b)</th>
                                </tr>
                            </thead>
                            <tbody id="questions_table">
                                <?php

                                $num_questions = $templates[$_GET['template_id']]['noofques'];

                                for ($i = 1; $i <= $num_questions; $i++) {
                                    echo "<tr>";
                                    echo "<td>" . $i . "</td>";
                                    echo "<td><input type='text' name='questions[" . $i . "][label]' class='form-control' value='" . $i . "' required readonly size=6></td>";
                                    echo "<td><select name='s1_no_of_ques[" . $i . "]' class='form-select' required><option value='1'>Single</option><option value='2'>a & b</option><option value='3'>a & b & c</option></select></td>";
                                    echo "</tr>";
                                    if($component_type == "Subjective" || $component_type == "Subjective-1" || $component_type == "Subjective-2"){
                                        $i++;
                                        echo "<tr><td colspan=3 style='text-align:center'>Or</td></tr>";
                                        echo "<tr>";
                                        echo "<td>" . $i . "</td>";
                                        echo "<td><input type='text' name='questions[" . $i . "][label]' class='form-control' value='" . $i . "' required readonly size=6></td>";
                                        echo "<td><select name='s1_no_of_ques[" . $i . "]' class='form-select' required><option value='1'>Single</option><option value='2'>a & b</option><option value='3'>a & b & c</option></select></td>";
                                        echo "</tr>";
                                    }
                                    echo "<tr style='border-bottom:1px solid black'><td colspan=3></td></tr>";
                                    echo "<tr><td colspan=3></td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <?php if ($component_type != "Assignment" && $component_type != "Activity") : ?> <input type="hidden" id="num_questions" name="num_questions" value="<?php echo $num_questions; ?>">
                        <?php else: ?>
                        <?php endif; ?>

                        <button type="submit" name="submit_ques" class="btn btn-success mt-3 float-end">Next: Add Question MetaData</button>
                    </form>

                <?php else: ?>
                    <form method="POST">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php if ($component_type == "Assignment"): ?>
                                        <th>Assignment Type</th>
                                    <?php elseif ($component_type == "Activity"): ?>
                                        <th>Activity Type</th>
                                    <?php else: ?>
                                        <th>Question Type</th>
                                        <th>Q.No</th>
                                    <?php endif; ?>
                                    <th>Marks</th>
                                    <th>Bloom's Level</th>
                                    <th>COs</th>
                                </tr>
                            </thead>
                            <tbody id="questions_table">
                                <?php

                                $num_questions = $templates[$_GET['template_id']]['noofques'];

                                if (($component_type == "Subjective" || $component_type == "Subjective-1" || $component_type == "Subjective-2" || $component_type == "Internal Exam") && !empty($_POST["s1_no_of_ques"])) {
                                    $i = 0;
                                    for ($k = 1; $k <= $num_questions; $k++) {
                                        $parts = array(0=>'', 1=>'a',2=>'b',3=>'c');
                                        for ($j = 1; $j <= $_POST["s1_no_of_ques"][$k]; $j++) {
                                            $i++;
                                            $ja = '';
                                            if($component_type == "Subjective" || $component_type == "Subjective-1" || $component_type == "Subjective-2"){
                                                $q_marks = "10";
                                            }else{
                                                $q_marks = "15";
                                            }
                                            if($_POST["s1_no_of_ques"][$k]>1){
                                                $ja = $parts[$j];
                                                $q_marks = '';
                                            }
                                            echo "<tr>";
                                            echo "<td>" . $i . "</td>";
                                            echo "<td>";
                                            echo "<select name='questions[" . $i . "][type]' class='form-select' required>";
                                            // Add your question type options here (e.g., Essay, Question, MCQ)
                                            foreach ($templates[$_GET['template_id']]['types'] as $qtype) {
                                                echo '<option value="' . $qtype . '">' . $qtype . '</option>';
                                            }
                                            echo "</select>";
                                            echo "</td>";
                                            echo "<td><input type='text' name='questions[" . $i . "][label]' class='form-control' value='" . $k . $ja . "' readonly required size=6></td>";
                                            echo "<td><input type='number' name='questions[" . $i . "][marks]' class='form-control' value='" . $q_marks . "' step='0.5' required size=6></td>";
                                            echo "<td style='min-width: 150px;'>";
                                            echo "<select name='questions[" . $i . "][blooms_level]' class='form-select' required='required'>";
                                            foreach ($blooms_levels as $b) {
                                                echo "<option value='" . $b['id'] . "'>" . $b['id'] . '. ' . $b['blooms_label'] . "</option>";
                                            }
                                            echo "</select>";
                                            echo "</td>";
                                            echo "<td style='min-width: 70px;'>";
                                            foreach ($cos['data'] as $index => $co) {
                                                echo "<input type='checkbox' name='questions[" . $i . "][cos][]' value='" . $co['id'] . "' id='chkboxco" . $i . $index . "'><label for='chkboxco" . $i . $index . "'> CO" . $co['co_number'] . "</label><br>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        echo '<input type="hidden" id="num_questions" name="num_questions" value="'.$i.'">';
                                        if($component_type == "Subjective" || $component_type == "Subjective-1" || $component_type == "Subjective-2"){
                                        echo "<tr><td colspan=6 style='text-align:center'>Or</td></tr>";
                                        }
                                        $k++;
                                        for ($j = 1; $j <= $_POST["s1_no_of_ques"][$k]; $j++) {
                                            $i++;
                                            $ja = '';
                                            $q_marks = "10";
                                            if($_POST["s1_no_of_ques"][$k]>1){
                                                $ja = $parts[$j];
                                                $q_marks = '';
                                            }
                                            echo "<tr>";
                                            echo "<td>" . $i . "</td>";
                                            echo "<td>";
                                            echo "<select name='questions[" . $i . "][type]' class='form-select' required>";
                                            // Add your question type options here (e.g., Essay, Question, MCQ)
                                            foreach ($templates[$_GET['template_id']]['types'] as $qtype) {
                                                echo '<option value="' . $qtype . '">' . $qtype . '</option>';
                                            }
                                            echo "</select>";
                                            echo "</td>";
                                            echo "<td><input type='text' name='questions[" . $i . "][label]' class='form-control' value='" . $k . $ja . "' readonly required size=6></td>";
                                            echo "<td><input type='number' name='questions[" . $i . "][marks]' class='form-control' value='" . $q_marks . "' step='0.5' required size=6></td>";
                                            echo "<td style='min-width: 150px;'>";
                                            echo "<select name='questions[" . $i . "][blooms_level]' class='form-select' required='required'>";
                                            foreach ($blooms_levels as $b) {
                                                echo "<option value='" . $b['id'] . "'>" . $b['id'] . '. ' . $b['blooms_label'] . "</option>";
                                            }
                                            echo "</select>";
                                            echo "</td>";
                                            echo "<td style='min-width: 70px;'>";
                                            foreach ($cos['data'] as $index => $co) {
                                                echo "<input type='checkbox' name='questions[" . $i . "][cos][]' value='" . $co['id'] . "' id='chkboxco" . $i . $index . "'><label for='chkboxco" . $i . $index . "'> CO" . $co['co_number'] . "</label><br>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        echo '<input type="hidden" id="num_questions" name="num_questions" value="'.$i.'">';
                                        echo "<tr style='border-bottom:1px solid black'><td colspan=3></td></tr>";
                                        echo "<tr><td colspan=3></td></tr>";
    
                                    }
                                } else {
                                    for ($i = 1; $i <= $num_questions; $i++) {
                                        echo "<tr>";
                                        echo "<td>" . $i . "</td>";
                                        echo "<td>";
                                        echo "<select name='questions[" . $i . "][type]' class='form-select' required>";
                                        // Add your question type options here (e.g., Essay, Question, MCQ)
                                        foreach ($templates[$_GET['template_id']]['types'] as $qtype) {
                                            echo '<option value="' . $qtype . '">' . $qtype . '</option>';
                                        }
                                        echo "</select>";
                                        echo "</td>";
                                        if ($component_type == "Assignment" || $component_type == "Activity"){
                                            echo "<input type='hidden' name='questions[" . $i . "][label]' class='form-control' value='" . $i . "' required size=6>";
                                        }else{
                                            echo "<td><input type='text' name='questions[" . $i . "][label]' class='form-control' value='" . $i . "' required size=6></td>";
                                        }
                                        echo "<td><input type='number' name='questions[" . $i . "][marks]' class='form-control' value='" . $templates[$_GET['template_id']]['marks'] . "' step='0.5' required size=6></td>";
                                        echo "<td style='min-width: 150px;'>";
                                        echo "<select name='questions[" . $i . "][blooms_level]' class='form-select' required='required'>";
                                        foreach ($blooms_levels as $b) {
                                            echo "<option value='" . $b['id'] . "'>" . $b['id'] . '. ' . $b['blooms_label'] . "</option>";
                                        }
                                        echo "</select>";
                                        echo "</td>";
                                        echo "<td style='min-width: 70px;'>";
                                        foreach ($cos['data'] as $index => $co) {
                                            echo "<input type='checkbox' name='questions[" . $i . "][cos][]' value='" . $co['id'] . "' id='chkboxco" . $i . $index . "'><label for='chkboxco" . $i . $index . "'> CO" . $co['co_number'] . "</label><br>";
                                        }
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>

                        <?php if ($component_type != "Assignment") : ?> <input type="hidden" id="num_questions" name="num_questions" value="<?php echo $num_questions; ?>">
                        <?php else: ?>
                        <?php endif; ?>

                        <button type="submit" name="submit_questions" class="btn btn-success mt-3 float-end">Save Question MetaData</button>
                    </form>

                <?php endif; ?>

            </div>
        <?php else: ?>
            <?php
            if ($component_type == "Subjective") {
                $num_questions = 6;
                $marks_per_question = 10;
                $default_question_type = "Question";
            } elseif ($component_type == "Objective") {
                $num_questions = 20;
                $marks_per_question = 1;
                $default_question_type = "MCQ"; // Or whatever your default is
            } elseif ($component_type == "Assignment") {
                $num_questions = 1;
                $marks_per_question = "5"; // Marks can be set individually
                $default_question_type = ""; // Type can be chosen individually
            } elseif ($component_type == "Internal Exam") {
                $num_questions = 1;
                $marks_per_question = "15"; // Marks can be set individually
                $default_question_type = "Experiment"; // Type can be chosen individually
            }
            ?>
            <?php
            if ($component_type == "Subjective") {
                echo '<div class="card-header" style="line-height:2em;"><pre><strong>Note: Subjective paper shall contain 3 either or type questions (a total of 6 questions)</strong><ul><li>Step-1: Select the Template (default)</li><li>Step-2: Add Number of Parts/Sub-questions for each Question</li><li>Step-3: For each Question, Add MetaData(Marks, BTL, CO)</li></ul></pre></div>';
            }
            ?>

            <div class="card-body">

                <form action="facciacompques.php" method="GET" id="templateForm">
                    <select name="template_id" id="template_id" class="form-select" required onchange="document.getElementById('templateForm').submit();">
                        <option value="">--Select Template --</option>
                        <?php
                        foreach ($template_ids[$component_type] as $k) {
                            echo '<option value="' . $k . '">' . $templates[$k]['desc'] . '</option>';
                        }
                        ?>
                    </select>
                    <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>" />
                    <input type="hidden" name="assessment_number" value="<?php echo $assessment_number; ?>" />
                    <input type="hidden" name="component_id" value="<?php echo $component_id; ?>" />
                    <input type="hidden" name="component_type" value="<?php echo $component_type; ?>" />

            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once "facfooter.php"; ?>
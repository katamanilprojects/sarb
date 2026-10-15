<?php
require_once __DIR__ . '/bootstrap.php';


use Mpdf\Tag\Mark;

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "Edit";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();

// Get subject details and student list with marks
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList1 = $facultyObj->getMappedStudents($sub_id);

    // Fetch marks for each student

    $studentList = []; 
    foreach ($studentList1 as $student) {
        $marks = $facultyObj->getStInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
        $student['marks'] = !empty($marks) ? $marks[0] : null;
        $studentList[] = $student; // Add the modified student data to the new array
    }


} else {
    // Handle invalid request
    header("Location: facciamarks.php");
    exit();
}
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Viewing Continuous Internal Assessment Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th rowspan="2">Adm. No<br />Student Name</th>
                                <th colspan="4" style="text-align: center;">CIA - <?php echo $assessmentNumber; ?></th>
                            </tr>
                            <tr>
                                <th>Assg</th>
                                <th>Obj.</th>
                                <th>Subj.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($studentList as $student) :
                            
                                $subj = rtrim(rtrim(number_format($student['marks']['subjective_marks'], 1), '0'), '.');
                                $obj = rtrim(rtrim(number_format($student['marks']['objective_marks'], 1), '0'), '.');
                                $assgn = rtrim(rtrim(number_format($student['marks']['assignment_marks'], 1), '0'), '.');
                                if(empty($subj)){
                                    $subj = 0;
                                }
                                if(empty($obj)){
                                    $obj = 0;
                                }
                                if(empty($assgn)){
                                    $assgn = 0;
                                }
                                ?>
                                <tr>
                                    <td><?php echo $student['username']; ?><br /><?php echo $student['name']; ?></td>
                                    <td style="text-align: center;"><?php echo $assgn; ?></td>
                                    <td style="text-align: center;"><?php echo $obj ?></td>
                                    <td style="text-align: center;"><?php echo $subj; ?></td>
                                    <td style="text-align: center;">
                                        <?php 
                                            if ($student['marks']) {
                                                $total = $student['marks']['subjective_marks'] + $student['marks']['objective_marks'] + $student['marks']['assignment_marks'];
                                                $total = rtrim(rtrim(number_format($total,1), '0'), '.');
                                                if(empty($total)){
                                                    echo '0';
                                                }else{
                                                    echo $total;
                                                }
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="facciamarks.php?sub_id=<?php echo $sub_id; ?>" class="btn btn-secondary">Back</a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
require_once("facfooter.php");
?>
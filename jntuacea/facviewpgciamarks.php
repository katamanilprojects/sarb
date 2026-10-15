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
        $marks = $facultyObj->getStPGInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
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
                <?php
                    if (!empty($assessmentNumber) && $assessmentNumber == 3) {
                        echo 'Viewing Final Assignment Marks';
                    }elseif(!empty($assessmentNumber) && $assessmentNumber==11){
                        echo 'Viewing Final Lab Internal Marks';                        
                    } else {
                        echo 'Viewing Internal Assessment Marks - ' . $assessmentNumber;
                    }
                    ?>
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Adm. No<br />Student Name</th>
                                <th style="text-align: center;">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($studentList as $student) :
                            
                                $stmarks = rtrim(rtrim(number_format($student['marks']['marks'], 1), '0'), '.');
                                if(empty($stmarks)){
                                    $stmarks = 0;
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?><br /><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align: center;"><?php echo $stmarks; ?></td>
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
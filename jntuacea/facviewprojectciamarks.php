<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

$page_title = "View Project CIA Marks";
require_once("facheader.php");
require_once("faculty.class.php");

$facultyObj = new Faculty();

if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList1 = $facultyObj->getMappedStudents($sub_id);

    $studentList = [];
    foreach ($studentList1 as $student) {
        $marks = $facultyObj->getStUGProjectInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
        $student['marks'] = !empty($marks) ? $marks[0] : null;
        $studentList[] = $student;
    }
} else {
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
                    Viewing Project CIA Marks - <?php echo $assessmentNumber; ?>
                </div>
                <div class="card-header">
                    <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th rowspan="2">Adm. No<br />Student Name</th>
                                <th colspan="3" style="text-align: center;">CIA - <?php echo $assessmentNumber; ?></th>
                            </tr>
                            <tr>
                                <th>Supervisor</th>
                                <th>P. R. C.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentList as $student) :
                                $c1 = $student['marks'] ? rtrim(rtrim(number_format($student['marks']['component1_marks'], 1), '0'), '.') : 0;
                                $c2 = $student['marks'] ? rtrim(rtrim(number_format($student['marks']['component2_marks'], 1), '0'), '.') : 0;
                                if (empty($c1)) $c1 = 0;
                                if (empty($c2)) $c2 = 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['username']); ?><br /><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td style="text-align: center;"><?php echo $c1; ?></td>
                                    <td style="text-align: center;"><?php echo $c2; ?></td>
                                    <td style="text-align: center;">
                                        <?php
                                        if ($student['marks']) {
                                            $total = $student['marks']['component1_marks'] + $student['marks']['component2_marks'];
                                            $total = rtrim(rtrim(number_format($total, 1), '0'), '.');
                                            echo empty($total) ? '0' : $total;
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

<?php require_once("facfooter.php"); ?>

<?php
require_once __DIR__.'/bootstrap.php';
require_once("attendance_report_helpers.php");
/*
 * Module: Attendance Report
 * Description: Generates course-wise attendance reports for students.
 */

if (empty($selected_cls_id)) {
    echo '<div class="alert alert-danger">No class selected.</div>';
    return;
}

$reportData = attendance_report_bootstrap($hodObj, $selected_cls_id);
$studentList = $reportData['studentList'];
$sub_sno_array = $reportData['sub_sno_array'];
$subject_max_classes = $reportData['subject_max_classes'];
$attendanceData = $reportData['attendanceData'];
$details = $reportData['details'];
$mappingList = $reportData['mappingList'];
$ruleWarning = $reportData['ruleWarning'];
$subjectRules = $reportData['subjectRules'];
$attendanceRulesObj = $reportData['attendanceRulesObj'];
$subjectBuckets = attendance_rule_buckets($subjectRules);
?>
<?php if (!empty($_POST['action'])) : ?>
    <div class="card">
        <div class="card-header">
            <?php if (!empty($details['data'])) : ?>
                <strong>Class:</strong> <?php echo $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')'; ?>
                <br><strong>Report Type:</strong> Course-wise Percentage
                <?php if (!empty($_POST['show_date_range']) && $_POST['show_date_range'] === 'yes' && (!empty($_POST['start_date']) || !empty($_POST['end_date']))) : ?>
                    <br><strong>Date Range:</strong>
                    <?php
                    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                        echo date('d-m-Y', strtotime($_POST['start_date'])) . ' to ' . date('d-m-Y', strtotime($_POST['end_date']));
                    } elseif (!empty($_POST['start_date'])) {
                        echo 'From ' . date('d-m-Y', strtotime($_POST['start_date']));
                    } elseif (!empty($_POST['end_date'])) {
                        echo 'Until ' . date('d-m-Y', strtotime($_POST['end_date']));
                    }
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card-body table-responsive">
            <?php if (!empty($ruleWarning)) : ?>
                <div class="alert alert-warning"><?php echo e($ruleWarning); ?></div>
            <?php endif; ?>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Adm. No.</th>
                        <th>Name of the Student</th>
                        <?php foreach ($sub_sno_array as $sno) : ?>
                            <th style="text-align: center;"><?php echo 'S.' . $sno; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentList as $student) {
                        $rollNumber = $student['roll_number'];
                        $studentName = $student['name'];
                        echo '<tr>';
                        echo '<td>' . e($rollNumber) . '</td>';
                        echo '<td>' . e($studentName) . '</td>';

                        foreach ($sub_sno_array as $sno) {
                            if (isset($attendanceData[$rollNumber][$sno]['percentage'])) {
                                $attendance = (float) $attendanceData[$rollNumber][$sno]['percentage'];
                                echo "<td style='text-align: center;'>" . e($attendance) . "%</td>";
                                foreach ($subjectRules as $rule) {
                                    if ($attendanceRulesObj->evaluateRule($attendance, $rule)) {
                                        if (!isset($subjectBuckets[$rule['id']]['subjects'][$sno])) {
                                            $subjectBuckets[$rule['id']]['subjects'][$sno] = [];
                                        }
                                        $subjectBuckets[$rule['id']]['subjects'][$sno][] = $rollNumber;
                                    }
                                }
                            } else {
                                echo "<td style='text-align: center;'>-</td>";
                            }
                        }
                        echo '</tr>';
                    } ?>
                </tbody>
            </table>

            <br />
            <hr /><br />
            <?php render_subject_faculty_table($mappingList, $subject_max_classes); ?>
            <?php render_subject_rule_summaries($attendanceRulesObj, $subjectRules, $subjectBuckets); ?>
        </div>
    </div>
<?php endif; ?>

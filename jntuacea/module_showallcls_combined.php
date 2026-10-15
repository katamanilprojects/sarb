<?php
require_once __DIR__.'/bootstrap.php';
require_once("attendance_report_helpers.php");
/*
 * Module: Attendance Report
 * Description: Generates attendance reports for students, including subject-wise and overall summaries.
 */

if (empty($selected_cls_id)) {
    echo '<div class="alert alert-danger">No class selected.</div>';
    return;
}

$reportData = attendance_report_bootstrap($hodObj, $selected_cls_id);
$studentList = $reportData['studentList'];
$sub_sno_array = $reportData['sub_sno_array'];
$sub_sno_tot_cls = $reportData['sub_sno_tot_cls'];
$subject_max_classes = $reportData['subject_max_classes'];
$attendanceData = $reportData['attendanceData'];
$details = $reportData['details'];
$mappingList = $reportData['mappingList'];
$ruleWarning = $reportData['ruleWarning'];
$overallRules = $reportData['overallRules'];
$subjectRules = $reportData['subjectRules'];
$attendanceRulesObj = $reportData['attendanceRulesObj'];
$overallBuckets = attendance_rule_buckets($overallRules);
$subjectBuckets = attendance_rule_buckets($subjectRules);
?>
<?php if (!empty($_POST['action'])) : ?>
    <div class="card">
        <div class="card-header">
            <?php if (!empty($details['data'])) : ?>
                <strong>Class:</strong> <?php echo $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')'; ?>
                <br><strong>Report Type:</strong> Overall Percentage
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
                        <?php $tot_sno_cls = 0; ?>
                        <?php foreach ($sub_sno_array as $sno) : ?>
                            <th style="text-align: center;">
                                <?php echo 'S.' . $sno; ?>
                                <?php if (!empty($sub_sno_tot_cls[$sno])) {
                                    $tot_sno_cls += $sub_sno_tot_cls[$sno];
                                } ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="text-align: center;">Tot.Cls</th>
                        <th style="text-align: center;">Present</th>
                        <th style="text-align: center;">Overall %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentList as $student) {
                        $rollNumber = $student['roll_number'];
                        $studentName = $student['name'];
                        echo '<tr>';
                        echo '<td>' . e($rollNumber) . '</td>';
                        echo '<td>' . e($studentName) . '</td>';

                        $tot_cls = 0;
                        $tot_pre = 0;

                        foreach ($sub_sno_array as $sno) {
                            if (isset($attendanceData[$rollNumber][$sno]['tot_cls'])) {
                                $tot_cls += $attendanceData[$rollNumber][$sno]['tot_cls'];
                            }
                            if (isset($attendanceData[$rollNumber][$sno]['tot_pre'])) {
                                echo "<td style='text-align: center;'>" . e($attendanceData[$rollNumber][$sno]['tot_pre']) . '</td>';
                                $tot_pre += $attendanceData[$rollNumber][$sno]['tot_pre'];
                            } else {
                                echo "<td style='text-align: center;'>-</td>";
                            }
                        }

                        $overallPercentage = ($tot_cls > 0) ? round(($tot_pre * 100) / $tot_cls, 2) : 0;
                        echo "<td style='text-align: center;'>" . e($tot_cls) . '</td>';
                        echo "<td style='text-align: center;'>" . e($tot_pre) . '</td>';
                        echo "<td style='text-align: center;'>" . e($overallPercentage) . "%</td>";
                        echo '</tr>';

                        foreach ($overallRules as $rule) {
                            if ($attendanceRulesObj->evaluateRule((float) $overallPercentage, $rule)) {
                                $overallBuckets[$rule['id']]['students'][] = $rollNumber;
                            }
                        }

                        foreach ($sub_sno_array as $sno) {
                            if (!isset($attendanceData[$rollNumber][$sno]['percentage'])) {
                                continue;
                            }
                            $subjectPercentage = (float) $attendanceData[$rollNumber][$sno]['percentage'];
                            foreach ($subjectRules as $rule) {
                                if ($attendanceRulesObj->evaluateRule($subjectPercentage, $rule)) {
                                    if (!isset($subjectBuckets[$rule['id']]['subjects'][$sno])) {
                                        $subjectBuckets[$rule['id']]['subjects'][$sno] = [];
                                    }
                                    $subjectBuckets[$rule['id']]['subjects'][$sno][] = $rollNumber;
                                }
                            }
                        }
                    } ?>
                </tbody>
            </table>

            <br />
            <hr /><br />
            <?php render_subject_faculty_table($mappingList, $subject_max_classes); ?>
            <?php render_overall_rule_summary($attendanceRulesObj, $overallRules, $overallBuckets); ?>
            <?php render_subject_rule_summaries($attendanceRulesObj, $subjectRules, $subjectBuckets); ?>
        </div>
    </div>
<?php endif; ?>

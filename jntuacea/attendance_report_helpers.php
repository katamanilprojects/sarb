<?php
require_once("attendancerules.class.php");

if (!function_exists('e')) {
    function e($str)
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('attendance_report_bootstrap')) {
    function attendance_report_bootstrap($hodObj, $selected_cls_id)
    {
        $data = [
            'studentList' => [],
            'subjectList' => [],
            'sub_sno_array' => [],
            'sub_sno_tot_cls' => [],
            'subject_max_classes' => [],
            'attendanceData' => [],
            'details' => [],
            'mappingList' => [],
            'ruleWarning' => '',
            'overallRules' => [],
            'subjectRules' => [],
            'attendanceRulesObj' => new AttendanceRules()
        ];

        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $reg_id = 0;

        $data['studentList'] = $hodObj->getStudentsByClass($selected_cls_id);
        $data['subjectList'] = $hodObj->getSubjectsByClassID($selected_cls_id);

        if (!empty($data['subjectList']['status']) && $data['subjectList']['status'] == 1) {
            foreach ($data['subjectList']['data'] as $subject) {
                $selected_sub_id = $subject['id'];
                if (!in_array($subject['subject_sno'], $data['sub_sno_array'], true)) {
                    $data['sub_sno_array'][] = $subject['subject_sno'];
                    $data['sub_sno_tot_cls'][$subject['subject_sno']] = 0;
                }

                $data['details'] = $hodObj->getClsSubFacBySubID($selected_sub_id);
                if (!$reg_id && !empty($data['details']['data']['reg_id'])) {
                    $reg_id = (int) $data['details']['data']['reg_id'];
                }

                $temp_attendanceData = $hodObj->getDetailedAttendanceBySubjectExcludingPermissions($selected_sub_id, $start_date, $end_date);
                $data['subject_max_classes'][$subject['subcode']] = 0;

                foreach (($temp_attendanceData['data'] ?? []) as $student) {
                    $data['attendanceData'][$student['username']][$subject['subject_sno']] = [
                        'tot_cls' => $student['total_classes'],
                        'tot_pre' => $student['total_present'],
                        'percentage' => (float) $student['percentage']
                    ];

                    if ($data['sub_sno_tot_cls'][$subject['subject_sno']] < $student['total_classes']) {
                        $data['sub_sno_tot_cls'][$subject['subject_sno']] = $student['total_classes'];
                    }
                    if ($data['subject_max_classes'][$subject['subcode']] < $student['total_classes']) {
                        $data['subject_max_classes'][$subject['subcode']] = $student['total_classes'];
                    }
                }
            }
        }

        sort($data['sub_sno_array'], SORT_NUMERIC);
        $data['mappingList'] = $hodObj->getFacSubMapByClassID($selected_cls_id);

        if ($reg_id > 0) {
            $rulesRes = $data['attendanceRulesObj']->getAttendanceRulesByRegulationId($reg_id);
            $groupedRules = $data['attendanceRulesObj']->groupRulesByCriteria($rulesRes['data'] ?? []);
            $data['overallRules'] = $groupedRules['overall_percentage'] ?? [];
            $data['subjectRules'] = $groupedRules['subject_wise_percentage'] ?? [];
            if (empty($data['overallRules']) && empty($data['subjectRules'])) {
                $data['ruleWarning'] = 'No attendance rules configured for this regulation.';
            }
        } else {
            $data['ruleWarning'] = 'Data not found for this class.';
        }

        return $data;
    }
}

if (!function_exists('attendance_rule_buckets')) {
    function attendance_rule_buckets(array $rules)
    {
        $buckets = [];
        foreach ($rules as $rule) {
            $buckets[$rule['id']] = ['rule' => $rule, 'students' => [], 'subjects' => []];
        }
        return $buckets;
    }
}

if (!function_exists('render_subject_faculty_table')) {
    function render_subject_faculty_table($mappingList, array $subject_max_classes)
    {
        ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th colspan="4">Subject - Faculty</th>
                </tr>
                <tr>
                    <th style="width: 30px; text-align: center; vertical-align:middle;">S.No</th>
                    <th>Subject (Code - Name)</th>
                    <th>Name of the Faculty</th>
                    <th style="text-align: center;">Max Classes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($mappingList['status']) && $mappingList['status'] == 1) {
                    $groupedData = [];
                    foreach ($mappingList['data'] as $mapping) {
                        $sno = $mapping['subject_sno'];
                        $subjectCode = $mapping['subcode'];
                        $subjectName = "{$mapping['subcode']} - {$mapping['sub_fullname']}";
                        $facultyName = $mapping['faculty_name'];
                        if (!isset($groupedData[$sno])) {
                            $groupedData[$sno] = [];
                        }
                        if (!isset($groupedData[$sno][$subjectCode])) {
                            $groupedData[$sno][$subjectCode] = [
                                'subject_name' => $subjectName,
                                'faculties' => [],
                                'max_classes' => $subject_max_classes[$subjectCode] ?? 0
                            ];
                        }
                        $groupedData[$sno][$subjectCode]['faculties'][] = $facultyName;
                    }
                    ksort($groupedData, SORT_NUMERIC);
                    foreach ($groupedData as $sno => $subjects) {
                        $snoRowspan = count($subjects);
                        $isFirstSubject = true;
                        foreach ($subjects as $subjectDetails) {
                            echo '<tr>';
                            if ($isFirstSubject) {
                                echo "<td rowspan='{$snoRowspan}' style='width: 30px; text-align: center; vertical-align:middle;'>S.{$sno}</td>";
                                $isFirstSubject = false;
                            }
                            echo '<td>' . e($subjectDetails['subject_name']) . '</td>';
                            echo '<td>' . e(implode(', ', $subjectDetails['faculties'])) . '</td>';
                            echo "<td style='text-align: center;'>" . e($subjectDetails['max_classes']) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
                ?>
            </tbody>
        </table>
        <?php
    }
}

if (!function_exists('render_overall_rule_summary')) {
    function render_overall_rule_summary($attendanceRulesObj, array $overallRules, array $overallBuckets)
    {
        if (empty($overallRules)) {
            return;
        }
        ?>
        <br />
        <hr /><br />
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th colspan="3">Condonation & Detained List Based on Overall Attendance</th>
                </tr>
                <tr>
                    <th style='text-align: center;'>Range</th>
                    <th style='text-align: center;'>Count</th>
                    <th style='text-align: center;'>List of Student Roll Numbers</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overallRules as $rule) {
                    $students = $overallBuckets[$rule['id']]['students'] ?? [];
                    echo '<tr>';
                    echo "<td style='text-align: center;'>" . e($attendanceRulesObj->formatRuleLabel($rule)) . '</td>';
                    echo "<td style='text-align: center;'>" . count($students) . '</td>';
                    echo '<td>' . e(implode(', ', $students)) . '</td>';
                    echo '</tr>';
                } ?>
            </tbody>
        </table>
        <?php
    }
}

if (!function_exists('render_subject_rule_summaries')) {
    function render_subject_rule_summaries($attendanceRulesObj, array $subjectRules, array $subjectBuckets)
    {
        if (empty($subjectRules)) {
            return;
        }
        foreach ($subjectRules as $rule) {
            $subjects = $subjectBuckets[$rule['id']]['subjects'] ?? [];
            ksort($subjects, SORT_NUMERIC);
            ?>
            <br />
            <hr /><br />
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th colspan="3">Subject-wise Attendance <?php echo e($attendanceRulesObj->formatRuleLabel($rule)); ?></th>
                    </tr>
                    <tr>
                        <th style='text-align: center;'>S.No.</th>
                        <th style='text-align: center;'>Count</th>
                        <th>List of Student Roll Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subjectCode => $students) {
                        echo '<tr>';
                        echo "<td style='text-align: center;'>S.{$subjectCode}</td>";
                        echo "<td style='text-align: center;'>" . count($students) . '</td>';
                        echo '<td>' . e(implode(', ', $students)) . '</td>';
                        echo '</tr>';
                    } ?>
                </tbody>
            </table>
            <?php
        }
    }
}

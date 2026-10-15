<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_CIA_MARKS','export');}

if (!empty($_POST["cls_id"]) && !empty($_POST["action"]) && $_POST["action"] == "hod_export_cia_marks" || $_POST["action"] == "admin_export_cia_marks") {
    if ($_POST["action"] == "hod_export_cia_marks") {
        $redirect_page = "hodshowallclsattendance.php";
    } else {
        $redirect_page = "adminshowallclsattendance.php";
    }


    $cls_id = $_POST["cls_id"];

    require_once("hod.class.php");
    $hodObj = new HOD();

    require_once("faculty.class.php");
    $facultyObj = new Faculty();


    $studentList = $hodObj->getStudentsByClass($cls_id);
    $subjectList = $hodObj->getSubjectsByClassID($cls_id);

    if (empty($studentList) || empty($subjectList['data'])) {
        echo "<script>
            alert('No data available for this class.');
            window.location.href = '" . $redirect_page . "';
        </script>";
        exit;
    }


    $ciaMarksData = [];

    $noofsubjects = 0;

    foreach ($subjectList['data'] as $subject) {
        $selected_sub_id = $subject['id'];
        $assessmentDetails = [];
        // Check if both assessments are present

        require_once("modulecheckcia.php");

        if ($includeInternalMarks) {
            if (!empty($prg_res['prg_code'])) {
                if ($prg_res['prg_code'] == "A") {
                    if ($prg_res['sub_type'] == "project") {
                        foreach ($studentList as $student) {
                            $m1 = $student['marks'][1]['component1_marks'] ?? null;
                            $m2 = $student['marks'][1]['component2_marks'] ?? null;
                            $total = ($m1 !== null)
                                ? round((float)$m1 + (float)($m2 ?? 0))
                                : null;
                            if ($total !== null) {
                                $noofsubjects++;
                                $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $total;
                            }
                        }
                    } elseif ($prg_res['sub_type'] == "lab" || $prg_res['sub_type'] == "dti") {
                        foreach ($studentList as $student) {
                            $mDay = $student['marks'][1]['day_to_day_marks'] ?? null;
                            $mInt = $student['marks'][1]['internal_test_marks'] ?? null;
                            $total = ($mDay !== null)
                                ? round((float)$mDay + (float)($mInt ?? 0))
                                : null;
                            if ($total !== null) {
                                $noofsubjects++;
                                $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $total;
                            }
                        }
                    } else {
                    require_once __DIR__ . '/services/SettingsService.php';
                    require_once __DIR__ . '/cia.class.php';
                    $ciaHelper = new CIA();
                    $subReg = $ciaHelper->getRegulationForSubject((int)$selected_sub_id);
                    $settingsSvc = \Services\SettingsService::getInstance();

                    $sno = 1;

                    foreach ($studentList as $student) {
                        $has1 = !empty($student['marks'][1]) && (($student['marks'][1]['subjective_marks'] ?? null) !== null || ($student['marks'][1]['objective_marks'] ?? null) !== null);
                        $has2 = !empty($student['marks'][2]) && (($student['marks'][2]['subjective_marks'] ?? null) !== null || ($student['marks'][2]['objective_marks'] ?? null) !== null);

                        if ($has1 && $has2) {
                            $assessment1Total = (float)($student['marks'][1]['subjective_marks'] ?? 0) + (float)($student['marks'][1]['objective_marks'] ?? 0) + (float)($student['marks'][1]['assignment_marks'] ?? 0);
                            $assessment2Total = (float)($student['marks'][2]['subjective_marks'] ?? 0) + (float)($student['marks'][2]['objective_marks'] ?? 0) + (float)($student['marks'][2]['assignment_marks'] ?? 0);

                            // Dynamic weighting derived from centralized academic settings
                            $ciaCalc = $settingsSvc->calculateCiaFinal((float)$assessment1Total, (float)$assessment2Total, $subReg);
                            $totalCIA = $ciaCalc['final_rounded'];
                            $noofsubjects++;
                            $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $totalCIA;
                        }
                    }
                    } // end else (theory)
                } else {

                    $sno = 1;

                    foreach ($studentList as $student) {
                        $hasM1 = !empty($student['marks'][1]) && isset($student['marks'][1]['marks']) && $student['marks'][1]['marks'] !== null;
                        $hasM2 = !empty($student['marks'][2]) && isset($student['marks'][2]['marks']) && $student['marks'][2]['marks'] !== null;
                        $hasM3 = !empty($student['marks'][3]) && isset($student['marks'][3]['marks']) && $student['marks'][3]['marks'] !== null;

                        $stmarks1 = $hasM1 ? rtrim(rtrim(number_format((float)$student['marks'][1]['marks'], 1), '0'), '.') : '0';
                        $stmarks2 = $hasM2 ? rtrim(rtrim(number_format((float)$student['marks'][2]['marks'], 1), '0'), '.') : '0';
                        $stmarks3 = $hasM3 ? rtrim(rtrim(number_format((float)$student['marks'][3]['marks'], 1), '0'), '.') : '0';

                        unset($totalInt, $totalCIA);
                        if ($hasM1 && $hasM2) {
                            if ((float)$stmarks1 > (float)$stmarks2) {
                                $best = (float)$stmarks1;
                                $rest = (float)$stmarks2;
                            } else {
                                $best = (float)$stmarks2;
                                $rest = (float)$stmarks1;
                            }

                            $seventyPercent = 0.7 * $best;
                            $thirtyPercent = 0.3 * $rest;
                            $totalInt = $seventyPercent + $thirtyPercent;
                        }

                        if (isset($totalInt) && $hasM3) {
                            $totalCIA = round($totalInt + (float)$stmarks3);
                        }
                        if (isset($totalCIA)) {
                            $noofsubjects++;
                            $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $totalCIA;
                        }
                    }
                    unset($totalCIA);
                }
            }
        }
    }


    if ($noofsubjects > 0) {

        $filename = "CIA_Marks_Class_" . $cls_id . "_Export_" . date("Y-m-d") . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment;filename=$filename");

        $output = fopen("php://output", "w");


        $headers = ["Student Admission No."];
        foreach ($subjectList['data'] as $subject) {
            $headers[] = $subject['subcode'];
        }
        fputcsv($output, $headers);

        foreach ($studentList as $student) {
            $username = $student["roll_number"];
            $row = [$username];
            foreach ($subjectList['data'] as $subject) {
                $subjectid = $subject['id'];
                $row[] = isset($ciaMarksData[$username][$subjectid]) ? $ciaMarksData[$username][$subjectid] : "";
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    } else {
        echo "<script>
            alert('No Subjects in this class has entered all CIA marks');
            window.location.href = '" . $redirect_page . "';
        </script>";
        exit;
    }
} else {
    // echo "Invalid request.";
}

<?php

session_start();

require_once("logger.php");
logRequest();


require_once("services/StudentRepository.php");
require_once("services/StudentAttendanceService.php");
require_once("services/StudentAssessmentService.php");
require_once("services/FeatureManager.php");

$studentRepo = new StudentRepository();
$attendanceService = new StudentAttendanceService();
$assessmentService = new StudentAssessmentService();

// Verify that the requested student_id belongs to the logged-in user
if (empty($_POST['student_id']) || empty($_SESSION['user']) || !$studentRepo->verifyStudentOwnership($_POST['student_id'], $_SESSION['user'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (empty($_POST["subcode"]) || empty($_POST["sub_fullname"]) || empty($_POST["subject_id"]) || empty($_POST["student_id"]) || empty($_POST['class_id']) || empty($_POST['classname']) || empty($_POST['acad_year'])) {
    header('Location: ./studenthome.php');
    exit();
}

if (!$studentRepo->verifyClassEnrollment((int)$_POST['student_id'], (int)$_POST['class_id']) || !$studentRepo->verifySubjectEnrollment((int)$_POST['student_id'], (int)$_POST['subject_id'])) {
    header('Location: ./studenthome.php');
    exit();
}
$classid_validation = 1;

$page_title = "Student Dashboard";
require_once("stheader.php");

$studentList = array(0 => array("id" => $_POST["student_id"], "username" => $_SESSION["user"]));


// Fetch classes and their corresponding subjects for the student
$attendanceData = $attendanceService->getStudentAttendanceData($_POST['subject_id'], $_POST['student_id']);
$studentPermissions = $attendanceService->getStudentPermissions($_POST['student_id'], $_POST['class_id']);
$diaryEntries = $attendanceService->getDiaryEntriesBySubject($_POST['subject_id']);
$courseOutcomes = $assessmentService->getCOsBySubjectId($_POST['subject_id']);
?>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header">
            <div class="float-start">
                <h6 class="mb-0"><?= htmlspecialchars($_POST['classname']); ?></h6>
                <small>Academic Year: <?= htmlspecialchars($_POST['acad_year']); ?></small>
            </div>
            <div class="float-end">
                <a href="studenthome.php" class="btn btn-sm btn-outline-primary">Home</a>
            </div>
        </div>
        <div class="card-header">
            <?= htmlspecialchars($_POST['subcode']); ?> <br> <?= htmlspecialchars($_POST['sub_fullname']); ?>
        </div>
        <div class="card-body">
            <?php if (\FeatureManager::isStudentVisible('MOD_ATTENDANCE')): ?>
                <?php if (!empty($attendanceData)): ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th colspan=3>Attendance</th>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($attendanceData as $attendance): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($attendance['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars(date("g:i A", strtotime($attendance['start_time'])), ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars(date("g:i A", strtotime($attendance['end_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php
                                        $permissionKey = $attendance['date'] . '_' . $attendance['hour'];
                                        $hasPermission = isset($studentPermissions[$permissionKey]);

                                        switch ($attendance['status']) {
                                            case 'P':
                                                if ($hasPermission) {
                                                    echo '<span class="badge bg-info">Permission</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">Present</span>';
                                                }
                                                break;
                                            case 'A':
                                                if ($hasPermission) {
                                                    echo '<span class="badge bg-info">Permission</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Absent</span>';
                                                }
                                                break;
                                            case 'L':
                                                echo '<span class="badge bg-warning">Leave</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Not Recorded</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No attendance records found yet.</div>
                <?php endif; ?>
                <hr>
            <?php endif; ?>
            <?php if (!empty($courseOutcomes['data']) && \FeatureManager::isStudentVisible('MOD_CO_PO')) { ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="2">Course Outcomes</th>
                        </tr>
                        <tr>
                            <th>CO No.</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($courseOutcomes['data'] as $index => $co) {
                            echo "<tr><td>CO" . htmlspecialchars($co['co_number']) . "</td><td>" . htmlspecialchars($co['co_description']) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php } ?>

            <hr>

            <?php if (!empty($diaryEntries) && \FeatureManager::isStudentVisible('MOD_CLASS_DIARY')) : ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan=3>Lecture Diary</th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Diary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($diaryEntries as $entry) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars(date('d-m-Y', strtotime($entry['date'])), ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . htmlspecialchars(date("g:i A", strtotime($entry['start_time'])), ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars(date("g:i A", strtotime($entry['end_time'])), ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . htmlspecialchars($entry['diary'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>



            <?php
            if (\FeatureManager::isStudentVisible('MOD_CIA_MARKS')):
            $includeInternalMarks = false;

            $prg_res = $assessmentService->getPrgCodeBySubID($_POST["subject_id"]);

            if (!empty($prg_res['prg_code'])) {
                if ($prg_res['prg_code'] == "A") {
                    if ($prg_res["sub_type"] == "lab") {
                        // Fetch assessment details for both assessments
                        for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                            foreach ($studentList as &$student) {
                                $marks = $assessmentService->getStUGLabInternalAssessmentMarks($_POST["student_id"], $_POST["subject_id"], $assessmentNumber);
                                $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                                if (!empty($marks)) {
                                    $includeInternalMarks = true;
                                }
                            }
                            unset($student);
                        }
                    } else {
                        // Fetch assessment details for both assessments
                        for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                            foreach ($studentList as &$student) {
                                $marks = $assessmentService->getStInternalAssessmentMarks($_POST["student_id"], $_POST["subject_id"], $assessmentNumber);
                                $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                                if (!empty($marks)) {
                                    $includeInternalMarks = true;
                                }
                            }
                            unset($student);
                        }
                    }
                } else {
                    if ($prg_res["sub_type"] == "lab") {
                        // Fetch assessment details for both assessments
                        for ($assessmentNumber = 11; $assessmentNumber <= 11; $assessmentNumber++) {
                            foreach ($studentList as &$student) {
                                $marks = $assessmentService->getStPGInternalAssessmentMarks($_POST["student_id"], $_POST["subject_id"], $assessmentNumber);
                                $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                                if (!empty($marks)) {
                                    $includeInternalMarks = true;
                                }
                            }
                            unset($student);
                        }
                    } else {
                        // Fetch assessment details for both assessments
                        for ($assessmentNumber = 1; $assessmentNumber <= 3; $assessmentNumber++) {
                            foreach ($studentList as &$student) {
                                $marks = $assessmentService->getStPGInternalAssessmentMarks($_POST["student_id"], $_POST["subject_id"], $assessmentNumber);
                                $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                                if (!empty($marks)) {
                                    $includeInternalMarks = true;
                                }
                            }
                            unset($student);
                        }
                    }
                }

                if ($includeInternalMarks) {
                    if ($prg_res['prg_code'] == "A") {
                        if ($prg_res["sub_type"] == "lab") {
                            // Internal marks table header
                            $internalMarksHeader = '
        <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
            <thead>
                <tr>
                    <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                    <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                    <th colspan="3" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 1</th>
                    <th colspan="3" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 2</th>
                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                </tr>
                <tr>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">d2d-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">int-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">d2d-2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">int-2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-2</th>
                </tr>
            </thead>
            <tbody>';

                            $internalMarksFooter = '</tbody>
        </table>';

                            // Loop through the students and add their internal marks to the table
                            $internalMarksContent = '';
                            $sno = 1;

                            foreach ($studentList as $student) {

                                $dayToDay1 = $student['marks'][1]['day_to_day_marks'] ?? null;
                                $internalTest1 = $student['marks'][1]['internal_test_marks'] ?? null;

                                $subj1 = ($dayToDay1 !== null) ? rtrim(rtrim(number_format($dayToDay1, 1), '0'), '.') : '0';
                                $obj1 = ($internalTest1 !== null) ? rtrim(rtrim(number_format($internalTest1, 1), '0'), '.') : '0';

                                if (empty($subj1)) {
                                    $subj1 = 0;
                                }
                                if (empty($obj1)) {
                                    $obj1 = 0;
                                }

                                $assessment1Total = (($dayToDay1 !== null) ? $dayToDay1 : 0) + (($internalTest1 !== null) ? $internalTest1 : 0);
                                $internalMarksContent .= '
        <tr>
            <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
            <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>
            <td style="font-size:11; text-align:center;">' . $subj1 . '</td>
            <td style="font-size:11; text-align:center;">' . $obj1 . '</td>
            <td style="font-size:11; text-align:center;">' . $assessment1Total . '</td>';

                                $dayToDay2 = $student['marks'][2]['day_to_day_marks'] ?? null;
                                $internalTest2 = $student['marks'][2]['internal_test_marks'] ?? null;

                                if ($dayToDay2 !== null) {

                                    $assessment2Total = $dayToDay2 + ($internalTest2 ?? 0);

                                    // Consider 80% of the best assessment and 20% of the remaining one
                                    if ($assessment1Total > $assessment2Total) {
                                        $best = $assessment1Total;
                                        $rest = $assessment2Total;
                                    } else {
                                        $best = $assessment2Total;
                                        $rest = $assessment1Total;
                                    }

                                    $eightyPercent = 0.5 * $best;
                                    $twentyPercent = 0.5 * $rest;
                                    $totalCIA = $eightyPercent + $twentyPercent;

                                    $subj2 = rtrim(rtrim(number_format($dayToDay2, 1), '0'), '.');
                                    $obj2 = ($internalTest2 !== null) ? rtrim(rtrim(number_format($internalTest2, 1), '0'), '.') : '0';

                                    if (empty($subj2)) {
                                        $subj2 = 0;
                                    }
                                    if (empty($obj2)) {
                                        $obj2 = 0;
                                    }


                                    $internalMarksContent .= '
            <td style="font-size:11; text-align:center;">' . $subj2 . '</td>
            <td style="font-size:11; text-align:center;">' . $obj2 . '</td>
            <td style="font-size:11; text-align:center;">' . $assessment2Total . '</td>
            <td style="font-size:11; text-align:center;">' . round($totalCIA) . '</td>';
                                } else {
                                    $internalMarksContent .= '
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            ';
                                }
                                $internalMarksContent .= '
        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } else {
                            // Internal marks table header
                            $internalMarksHeader = '
        <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
            <thead>
                <tr>
                    <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                    <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                    <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 1</th>
                    <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 2</th>
                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">80% of Best</th>
                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">20% of Rest</th>
                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                </tr>
                <tr>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Sub-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Obj-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Ass-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-1</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Sub-2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Obj-2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Ass-2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-2</th>
                </tr>
            </thead>
            <tbody>';

                            $internalMarksFooter = '</tbody>
        </table>';

                            // Loop through the students and add their internal marks to the table
                            $internalMarksContent = '';
                            $sno = 1;

                            foreach ($studentList as $student) {

                                $subj1Val = $student['marks'][1]['subjective_marks'] ?? null;
                                $obj1Val = $student['marks'][1]['objective_marks'] ?? null;
                                $assgn1Val = $student['marks'][1]['assignment_marks'] ?? null;

                                $subj1 = ($subj1Val !== null) ? rtrim(rtrim(number_format($subj1Val, 1), '0'), '.') : '0';
                                $obj1 = ($obj1Val !== null) ? rtrim(rtrim(number_format($obj1Val, 1), '0'), '.') : '0';
                                $assgn1 = ($assgn1Val !== null) ? rtrim(rtrim(number_format($assgn1Val, 1), '0'), '.') : '0';

                                if (empty($subj1)) {
                                    $subj1 = 0;
                                }
                                if (empty($obj1)) {
                                    $obj1 = 0;
                                }
                                if (empty($assgn1)) {
                                    $assgn1 = 0;
                                }

                                $assessment1Total = (($subj1Val !== null) ? $subj1Val : 0) + (($obj1Val !== null) ? $obj1Val : 0) + (($assgn1Val !== null) ? $assgn1Val : 0);
                                $internalMarksContent .= '
        <tr>
            <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
            <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>
            <td style="font-size:11; text-align:center;">' . $subj1 . '</td>
            <td style="font-size:11; text-align:center;">' . $obj1 . '</td>
            <td style="font-size:11; text-align:center;">' . $assgn1 . '</td>
            <td style="font-size:11; text-align:center;">' . $assessment1Total . '</td>';

                                $subj2Val = $student['marks'][2]['subjective_marks'] ?? null;
                                $obj2Val = $student['marks'][2]['objective_marks'] ?? null;
                                $assgn2Val = $student['marks'][2]['assignment_marks'] ?? null;

                                if ($subj2Val !== null) {

                                    $assessment2Total = $subj2Val + (($obj2Val !== null) ? $obj2Val : 0) + (($assgn2Val !== null) ? $assgn2Val : 0);

                                    // Consider 80% of the best assessment and 20% of the remaining one
                                    if ($assessment1Total > $assessment2Total) {
                                        $best = $assessment1Total;
                                        $rest = $assessment2Total;
                                    } else {
                                        $best = $assessment2Total;
                                        $rest = $assessment1Total;
                                    }

                                    $eightyPercent = 0.8 * $best;
                                    $twentyPercent = 0.2 * $rest;
                                    $totalCIA = $eightyPercent + $twentyPercent;

                                    $subj2 = rtrim(rtrim(number_format($subj2Val, 1), '0'), '.');
                                    $obj2 = ($obj2Val !== null) ? rtrim(rtrim(number_format($obj2Val, 1), '0'), '.') : '0';
                                    $assgn2 = ($assgn2Val !== null) ? rtrim(rtrim(number_format($assgn2Val, 1), '0'), '.') : '0';

                                    if (empty($subj2)) {
                                        $subj2 = 0;
                                    }
                                    if (empty($obj2)) {
                                        $obj2 = 0;
                                    }
                                    if (empty($assgn2)) {
                                        $assgn2 = 0;
                                    }


                                    $internalMarksContent .= '
            <td style="font-size:11; text-align:center;">' . $subj2 . '</td>
            <td style="font-size:11; text-align:center;">' . $obj2 . '</td>
            <td style="font-size:11; text-align:center;">' . $assgn2 . '</td>
            <td style="font-size:11; text-align:center;">' . $assessment2Total . '</td>
            <td style="font-size:11; text-align:center;">' . round($eightyPercent, 2) . '</td>
            <td style="font-size:11; text-align:center;">' . round($twentyPercent, 2) . '</td>
            <td style="font-size:11; text-align:center;">' . round($totalCIA) . '</td>';
                                } else {
                                    $internalMarksContent .= '
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            <td style="font-size:11; text-align:center;"></td>
            ';
                                }
                                $internalMarksContent .= '
        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        }
                    } else {
                        if ($prg_res["sub_type"] == "lab") {
                            // Internal marks table header PG
                            $internalMarksHeader = '
        <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
            <thead>
                <tr>
                    <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                    <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                </tr>
            </thead>
            <tbody>';

                            $internalMarksFooter = '</tbody>
        </table>';

                            // Loop through the students and add their internal marks to the table
                            $internalMarksContent = '';
                            $sno = 1;

                            foreach ($studentList as $student) {

                                $marks11Val = $student['marks'][11]['marks'] ?? null;
                                $stmarks1 = ($marks11Val !== null) ? rtrim(rtrim(number_format($marks11Val, 1), '0'), '.') : '0';

                                if (empty($stmarks1)) {
                                    $stmarks1 = 0;
                                }
                                $internalMarksContent .= '
        <tr>
            <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
            <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>';

                                if ($marks11Val !== null) {
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks1 . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                $internalMarksContent .= '
        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } else {
                            // Internal marks table header PG
                            $internalMarksHeader = '
        <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
            <thead>
                <tr>
                    <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                    <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                    <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 1</th>
                    <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 2</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">70% of Best</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">30% of Rest</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final Internal</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Assignment</th>
                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                </tr>
            </thead>
            <tbody>';

                            $internalMarksFooter = '</tbody>
        </table>';

                            // Loop through the students and add their internal marks to the table
                            $internalMarksContent = '';
                            $sno = 1;

                            foreach ($studentList as $student) {

                                $marks1Val = $student['marks'][1]['marks'] ?? null;
                                $marks2Val = $student['marks'][2]['marks'] ?? null;
                                $marks3Val = $student['marks'][3]['marks'] ?? null;

                                $stmarks1 = ($marks1Val !== null) ? rtrim(rtrim(number_format($marks1Val, 1), '0'), '.') : '0';
                                $stmarks2 = ($marks2Val !== null) ? rtrim(rtrim(number_format($marks2Val, 1), '0'), '.') : '0';
                                $stmarks3 = ($marks3Val !== null) ? rtrim(rtrim(number_format($marks3Val, 1), '0'), '.') : '0';

                                if (empty($stmarks1)) {
                                    $stmarks1 = 0;
                                }
                                if (empty($stmarks2)) {
                                    $stmarks2 = 0;
                                }
                                if (empty($stmarks3)) {
                                    $stmarks3 = 0;
                                }

                                $internalMarksContent .= '
        <tr>
            <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
            <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>';

                                if ($marks1Val !== null) {
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks1 . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                if ($marks2Val !== null) {
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks2 . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                if ($marks1Val !== null && $marks2Val !== null) {
                                    if ($stmarks1 > $stmarks2) {
                                        $best = $stmarks1;
                                        $rest = $stmarks2;
                                    } else {
                                        $best = $stmarks2;
                                        $rest = $stmarks1;
                                    }

                                    $seventyPercent = 0.7 * $best;
                                    $thirtyPercent = 0.3 * $rest;
                                    $totalInt = $seventyPercent + $thirtyPercent;
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $seventyPercent . '</td><td style="font-size:11; text-align:center;">' . $thirtyPercent . '</td><td style="font-size:11; text-align:center;">' . $totalInt . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td><td></td><td></td>';
                                }

                                if ($marks3Val !== null) {
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks3 . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }

                                if (isset($totalInt) && $marks3Val !== null) {
                                    $totalCIA = $totalInt + $stmarks3;
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $totalCIA . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                $internalMarksContent .= '
        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        }
                    }
                }
            }
            endif;
            ?>
        </div>
    </div>
</div>

<?php
require_once("stfooter.php");
?>
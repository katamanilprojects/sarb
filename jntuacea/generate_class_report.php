<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_CIA_MARKS','export');}


// --- BEGINNING OF SETUP ---
// error_reporting(E_ALL); // Uncomment for debugging if needed
// ini_set('display_errors', 1); // Uncomment for debugging if needed

require_once("hod.class.php");
$hodObj = new HOD();

require_once("faculty.class.php");
$facultyObj = new Faculty();

if (empty($_REQUEST['class_id'])) {
    die("Error: Class ID is required.");
}
$selected_cls_id = $_REQUEST['class_id'];

// 1. Fetch Class Details (Using existing placeholder logic)
$classNameForFile = "Class_Report_ID_" . $selected_cls_id;
$classNameDisplay = $classNameForFile; // Fallback
// Update $class_display_name_for_report if you have a better source
$class_display_name_for_report = $classNameDisplay;


// 2. Fetch all subjects for the given class_id
$subjectsResult = $hodObj->getSubjectsByClassID($selected_cls_id);
if (empty($subjectsResult['data'])) {
    die("Error: No subjects found for this class or could not retrieve subjects.");
}
$subjects = $subjectsResult['data'];

$subjects_export_data = [];

// *** NEW: Data structures for the consolidated report ***
$master_student_list = [];
$all_students_final_cia = [];


// 3. For each subject, fetch marks, generate its HTML table, AND collect data for consolidated report
foreach ($subjects as $subject) {
    $selected_sub_id = $subject['id'];
    $details = $hodObj->getClsSubFacBySubID($selected_sub_id);

    if (!empty($details['data']['classname'])) {
        $class_display_name_for_report = $details['data']['classname'];
    }
    $studentListResult = $facultyObj->getMappedStudents($selected_sub_id);
    $currentSubjectStudentList = !empty($studentListResult) ? $studentListResult : [];

    foreach ($currentSubjectStudentList as $student_data_from_subject) {
        if (!isset($master_student_list[$student_data_from_subject['id']])) {
            $master_student_list[$student_data_from_subject['id']] = [
                'id' => $student_data_from_subject['id'],
                'username' => $student_data_from_subject['username']
            ];
        }
    }

    $current_subject_name_code = $subject['sub_fullname'] . ' (' . $subject['subcode'] . ')';
    $current_subject_code = $subject['subcode'];
    $current_faculty_name = $details['data']['faculty_name'] ?? 'N/A';

    ob_start();

    $includeInternalMarks = false;
    $prg_res = $facultyObj->getPrgCodeBySubID($selected_sub_id);

    if (!empty($prg_res['prg_code'])) {
        $studentsWithMarksForThisSubject = $currentSubjectStudentList;

        if ($prg_res['prg_code'] == "A") { // UG
            if ($prg_res["sub_type"] == "lab" || $prg_res["sub_type"] == "dti") {
                for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) {
                    foreach ($studentsWithMarksForThisSubject as &$student_data) {
                        $marks = $facultyObj->getStUGLabInternalAssessmentMarks($student_data['id'], $selected_sub_id, $assessmentNumber);
                        $student_data['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                        if (!empty($marks)) $includeInternalMarks = true;
                    }
                    unset($student_data);
                }
            } elseif ($prg_res["sub_type"] == "project") {
                foreach ($studentsWithMarksForThisSubject as &$student_data) {
                    $marks = $facultyObj->getStUGProjectInternalAssessmentMarks($student_data['id'], $selected_sub_id, 1);
                    $student_data['marks'][1] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) $includeInternalMarks = true;
                }
                unset($student_data);
            } else { // UG Theory
                for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                    foreach ($studentsWithMarksForThisSubject as &$student_data) {
                        $marks = $facultyObj->getStInternalAssessmentMarks($student_data['id'], $selected_sub_id, $assessmentNumber);
                        $student_data['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                        if (!empty($marks)) $includeInternalMarks = true;
                    }
                    unset($student_data);
                }
            }
        } else { // PG programs
            if ($prg_res["sub_type"] == "lab") {
                for ($assessmentNumber = 11; $assessmentNumber <= 11; $assessmentNumber++) {
                    foreach ($studentsWithMarksForThisSubject as &$student_data) {
                        $marks = $facultyObj->getStPGInternalAssessmentMarks($student_data['id'], $selected_sub_id, $assessmentNumber);
                        $student_data['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                        if (!empty($marks)) $includeInternalMarks = true;
                    }
                    unset($student_data);
                }
            } else { // PG Theory
                for ($assessmentNumber = 1; $assessmentNumber <= 3; $assessmentNumber++) {
                    foreach ($studentsWithMarksForThisSubject as &$student_data) {
                        $marks = $facultyObj->getStPGInternalAssessmentMarks($student_data['id'], $selected_sub_id, $assessmentNumber);
                        $student_data['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                        if (!empty($marks)) $includeInternalMarks = true;
                    }
                    unset($student_data);
                }
            }
        }
    }

    if ($includeInternalMarks && !empty($studentsWithMarksForThisSubject)) {
        if (!empty($prg_res['prg_code'])) {
            if ($prg_res['prg_code'] == "A") { // UG
                if ($prg_res["sub_type"] == "lab" || $prg_res["sub_type"] == "dti") {
                    if ($prg_res["sub_type"] == "dti") {
                        $col1 = "Activity";
                    } else {
                        $col1 = "Day-to-Day";
                    }
                    $col2 = "Internal";
                    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">';
                    echo '<thead><tr><th rowspan="2">S.No</th><th rowspan="2">Adm.No</th><th colspan="3">CIA</th></tr>';
                    echo '<tr><th>'.$col1.'</th><th>'.$col2.'</th><th>CIA</th></tr></thead><tbody>';
                    $sno = 1;
                    foreach ($studentsWithMarksForThisSubject as $student) {
                        echo '<tr><td style="text-align:center;">' . $sno++ . '</td><td style="text-align:center;">' . $student['username'] . '</td>';
                        $finalCIAMarkForStudentInSubject = '-';

                        $assessment1Total = ($student['marks'][1]['day_to_day_marks'] ?? 0) + ($student['marks'][1]['internal_test_marks'] ?? 0);
                        $assessment1Total = round($assessment1Total);
                        echo '<td style="text-align:center;">' . rtrim(rtrim(number_format($student['marks'][1]['day_to_day_marks'] ?? 0, 1), '0'), '.') . '</td>';
                        echo '<td style="text-align:center;">' . rtrim(rtrim(number_format($student['marks'][1]['internal_test_marks'] ?? 0, 1), '0'), '.') . '</td>';
                        echo '<td style="text-align:center;">' . $assessment1Total . '</td>';

                        if (!isset($all_students_final_cia[$student['id']])) $all_students_final_cia[$student['id']] = [];
                        $all_students_final_cia[$student['id']][$selected_sub_id] = $assessment1Total;
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } elseif ($prg_res["sub_type"] == "project") {
                    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">';
                    echo '<thead><tr><th>S.No</th><th>Adm.No</th><th>Supervisor</th><th>P. R. C.</th><th>CIA (Max 60)</th></tr></thead><tbody>';
                    $sno = 1;
                    foreach ($studentsWithMarksForThisSubject as $student) {
                        echo '<tr><td style="text-align:center;">' . $sno++ . '</td><td style="text-align:center;">' . $student['username'] . '</td>';
                        $c1 = rtrim(rtrim(number_format($student['marks'][1]['component1_marks'] ?? 0, 1), '0'), '.');
                        $c2 = rtrim(rtrim(number_format($student['marks'][1]['component2_marks'] ?? 0, 1), '0'), '.');
                        $total = ($student['marks'][1]['component1_marks'] !== null) ? round(($student['marks'][1]['component1_marks'] ?? 0) + ($student['marks'][1]['component2_marks'] ?? 0)) : '-';
                        echo '<td style="text-align:center;">' . $c1 . '</td>';
                        echo '<td style="text-align:center;">' . $c2 . '</td>';
                        echo '<td style="text-align:center;">' . $total . '</td>';
                        if (!isset($all_students_final_cia[$student['id']])) $all_students_final_cia[$student['id']] = [];
                        $all_students_final_cia[$student['id']][$selected_sub_id] = $total;
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else { // UG Theory
                    require_once __DIR__ . '/services/SettingsService.php';
                    require_once __DIR__ . '/cia.class.php';
                    $ciaHelper = new CIA();
                    $subReg = $ciaHelper->getRegulationForSubject((int)$selected_sub_id);
                    $settingsSvc = \Services\SettingsService::getInstance();
                    $betterWt = (float)$settingsSvc->get('theory_mid_better_weight', $subReg, 0.80);
                    $lesserWt = (float)$settingsSvc->get('theory_mid_lesser_weight', $subReg, 0.20);
                    $betterPct = round($betterWt * 100);
                    $lesserPct = round($lesserWt * 100);

                    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">';
                    echo '<thead><tr><th rowspan="2">S.No</th><th rowspan="2">Adm.No</th><th colspan="4">CIA - 1</th><th colspan="4">CIA - 2</th><th rowspan="2">' . $betterPct . '% Best</th><th rowspan="2">' . $lesserPct . '% Rest</th><th rowspan="2">Final CIA</th></tr>';
                    echo '<tr><th>Sub-1</th><th>Obj-1</th><th>Ass-1</th><th>CIA-1</th><th>Sub-2</th><th>Obj-2</th><th>Ass-2</th><th>CIA-2</th></tr></thead><tbody>';
                    $sno = 1;
                    foreach ($studentsWithMarksForThisSubject as $student) {
                        echo '<tr><td style="text-align:center;">' . $sno++ . '</td><td style="text-align:center;">' . $student['username'] . '</td>';
                        $finalCIAMarkForStudentInSubject = '-';

                        $assessment1Total = ($student['marks'][1]['subjective_marks'] ?? 0) + ($student['marks'][1]['objective_marks'] ?? 0) + ($student['marks'][1]['assignment_marks'] ?? 0);
                        echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][1]['subjective_marks'] ?? 0, 1), '0'), '.') . "</td>";
                        echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][1]['objective_marks'] ?? 0, 1), '0'), '.') . "</td>";
                        echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][1]['assignment_marks'] ?? 0, 1), '0'), '.') . "</td>";
                        echo "<td style='text-align:center;'>{$assessment1Total}</td>";

                        if (isset($student['marks'][2]) && $student['marks'][2]['subjective_marks'] !== null) {
                            $assessment2Total = ($student['marks'][2]['subjective_marks'] ?? 0) + ($student['marks'][2]['objective_marks'] ?? 0) + ($student['marks'][2]['assignment_marks'] ?? 0);
                            $ciaCalc = $settingsSvc->calculateCiaFinal((float)$assessment1Total, (float)$assessment2Total, $subReg);
                            $eightyPercent = $ciaCalc['better_weighted'];
                            $twentyPercent = $ciaCalc['lesser_weighted'];
                            $finalCIAMarkForStudentInSubject = $ciaCalc['final_rounded'];
                            echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][2]['subjective_marks'] ?? 0, 1), '0'), '.') . "</td>";
                            echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][2]['objective_marks'] ?? 0, 1), '0'), '.') . "</td>";
                            echo "<td style='text-align:center;'>" . rtrim(rtrim(number_format($student['marks'][2]['assignment_marks'] ?? 0, 1), '0'), '.') . "</td>";
                            echo "<td style='text-align:center;'>{$assessment2Total}</td>";
                            echo "<td style='text-align:center;'>" . round($eightyPercent, 2) . "</td><td style='text-align:center;'>" . round($twentyPercent, 2) . "</td><td style='text-align:center;'>" . $finalCIAMarkForStudentInSubject . "</td>";
                        } else {
                            echo "<td></td><td></td><td></td><td></td><td></td><td></td><td>-</td>";
                        }
                        if (!isset($all_students_final_cia[$student['id']])) $all_students_final_cia[$student['id']] = [];
                        $all_students_final_cia[$student['id']][$selected_sub_id] = $finalCIAMarkForStudentInSubject;
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            } else { // PG
                if ($prg_res["sub_type"] == "lab") { // PG Lab
                    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">';
                    echo '<thead><tr><th>S.No</th><th>Adm.No</th><th>Final CIA</th></tr></thead><tbody>';
                    $sno = 1;
                    foreach ($studentsWithMarksForThisSubject as $student) {
                        echo '<tr><td style="text-align:center;">' . $sno++ . '</td><td style="text-align:center;">' . $student['username'] . '</td>';
                        $finalCIAMarkForStudentInSubject = '-';
                        if (isset($student['marks'][11]) && $student['marks'][11]['marks'] !== null) {
                            $stmarks1 = rtrim(rtrim(number_format($student['marks'][11]['marks'] ?? 0, 1), '0'), '.');
                            $finalCIAMarkForStudentInSubject = $stmarks1;
                            echo '<td style="text-align:center;">' . $finalCIAMarkForStudentInSubject . '</td>';
                        } else {
                            echo '<td>-</td>';
                        }
                        if (!isset($all_students_final_cia[$student['id']])) $all_students_final_cia[$student['id']] = [];
                        $all_students_final_cia[$student['id']][$selected_sub_id] = $finalCIAMarkForStudentInSubject;
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else { // PG Theory
                    echo '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">';
                    echo '<thead><tr><th>S.No</th><th>Adm.No</th><th>IA-1</th><th>IA-2</th><th>70% Best</th><th>30% Rest</th><th>Final Int</th><th>Assign.</th><th>Final CIA</th></tr></thead><tbody>';
                    $sno = 1;
                    foreach ($studentsWithMarksForThisSubject as $student) {
                        echo '<tr><td style="text-align:center;">' . $sno++ . '</td><td style="text-align:center;">' . $student['username'] . '</td>';
                        $finalCIAMarkForStudentInSubject = '-';
                        $totalInt = null;
                        $stmarks1_val = $student['marks'][1]['marks'] ?? null;
                        $stmarks2_val = $student['marks'][2]['marks'] ?? null;
                        $stmarks3_val = $student['marks'][3]['marks'] ?? null;

                        echo (isset($stmarks1_val)) ? "<td style='text-align:center;'>" . rtrim(rtrim(number_format($stmarks1_val, 1), '0'), '.') . "</td>" : "<td>-</td>";
                        echo (isset($stmarks2_val)) ? "<td style='text-align:center;'>" . rtrim(rtrim(number_format($stmarks2_val, 1), '0'), '.') . "</td>" : "<td>-</td>";

                        if (isset($stmarks1_val) && isset($stmarks2_val)) {
                            $best = max($stmarks1_val, $stmarks2_val);
                            $rest = min($stmarks1_val, $stmarks2_val);
                            $seventy = 0.7 * $best;
                            $thirty = 0.3 * $rest;
                            $totalInt = $seventy + $thirty;
                            echo "<td style='text-align:center;'>" . round($seventy, 2) . "</td><td style='text-align:center;'>" . round($thirty, 2) . "</td><td style='text-align:center;'>" . round($totalInt) . "</td>";
                        } else {
                            echo "<td>-</td><td>-</td><td>-</td>";
                        }
                        echo (isset($stmarks3_val)) ? "<td style='text-align:center;'>" . rtrim(rtrim(number_format($stmarks3_val, 1), '0'), '.') . "</td>" : "<td>-</td>";
                        if (isset($totalInt) && isset($stmarks3_val)) {
                            $totalCIA = $totalInt + $stmarks3_val;
                            $finalCIAMarkForStudentInSubject = round($totalCIA);
                            echo "<td style='text-align:center;'>{$finalCIAMarkForStudentInSubject}</td>";
                        } else {
                            echo "<td>-</td>";
                        }
                        if (!isset($all_students_final_cia[$student['id']])) $all_students_final_cia[$student['id']] = [];
                        $all_students_final_cia[$student['id']][$selected_sub_id] = $finalCIAMarkForStudentInSubject;
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            }
        } else {
            echo "<p>No internal marks data to display for {$current_subject_name_code}. Program code or type might be missing or not handled.</p>";
        }
    } else {
        echo "<p>No student data or marks to display for {$current_subject_name_code}.</p>";
    }

    $html_table_for_subject = ob_get_clean();

    $subjects_export_data[] = [
        'subject_name' => $current_subject_name_code,
        'subject_code' => $current_subject_code,
        'faculty_name' => $current_faculty_name,
        'table_html'   => $html_table_for_subject
    ];

    if (isset($studentsWithMarksForThisSubject)) {
        foreach ($studentsWithMarksForThisSubject as &$student_data_to_clear) {
            unset($student_data_to_clear['marks']);
        }
        unset($student_data_to_clear);
    }
}


// 4. Now, use PhpSpreadsheet to generate the Excel file
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;


$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

if (empty($subjects) && empty($master_student_list)) {
    die("No data processed for any subject or student. Cannot generate Excel file.");
}

// *** Create Consolidated CIA Marks Sheet (as the first sheet) ***
$consolidatedSheet = new Worksheet($spreadsheet, "Final CIA Marks");
$spreadsheet->addSheet($consolidatedSheet, 0);

$consolidatedSheet->setCellValue('A1', 'Class: ' . $class_display_name_for_report);
$consolidatedSheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(2 + count($subjects) - 1) . '1'); // Max column index for merge
$consolidatedSheet->setCellValue('A2', 'Final CIA Marks Summary');
$consolidatedSheet->mergeCells('A2:' . Coordinate::stringFromColumnIndex(2 + count($subjects) - 1) . '2');

$headerRowConsolidated = 3;
// Corrected: Use setCellValue([col_index, row_index], value)
$consolidatedSheet->setCellValue([1, $headerRowConsolidated], 'S.No');
$consolidatedSheet->setCellValue([2, $headerRowConsolidated], 'Adm.No');
$subjectColIndex = 3;
foreach ($subjects as $subject_col_header) {
    $consolidatedSheet->setCellValue([$subjectColIndex, $headerRowConsolidated], $subject_col_header['sub_fullname'] . ' (' . $subject_col_header['subcode'] . ')');
    $consolidatedSheet->getColumnDimensionByColumn($subjectColIndex)->setAutoSize(true);
    $subjectColIndex++;
}

$snoConsolidated = 1;
$dataRowConsolidated = $headerRowConsolidated + 1;

if (!empty($master_student_list)) {
    foreach ($master_student_list as $student_id => $student_info) {
        // Corrected: Use setCellValue([col_index, row_index], value)
        $consolidatedSheet->setCellValue([1, $dataRowConsolidated], $snoConsolidated++);
        $consolidatedSheet->setCellValue([2, $dataRowConsolidated], $student_info['username']);
        $currentSubjectCol = 3;
        foreach ($subjects as $subject_for_column) {
            $subject_id_for_column = $subject_for_column['id'];
            $mark_to_display = '-';
            if (isset($all_students_final_cia[$student_id][$subject_id_for_column])) {
                $mark_to_display = $all_students_final_cia[$student_id][$subject_id_for_column];
            }
            // Corrected: Use setCellValue([col_index, row_index], value)
            $consolidatedSheet->setCellValue([$currentSubjectCol, $dataRowConsolidated], $mark_to_display);
            $consolidatedSheet->getStyle(Coordinate::stringFromColumnIndex($currentSubjectCol) . $dataRowConsolidated)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentSubjectCol++;
        }
        $dataRowConsolidated++;
    }
} else {
    $consolidatedSheet->setCellValue('A' . $dataRowConsolidated, 'No student data found across subjects.');
    $consolidatedSheet->mergeCells('A' . $dataRowConsolidated . ':' . Coordinate::stringFromColumnIndex(2 + count($subjects) - 1) . $dataRowConsolidated);
}

$lastColLetterConsolidated = Coordinate::stringFromColumnIndex(2 + count($subjects) - 1);
$lastDataRowConsolidated = $dataRowConsolidated - 1;

$styleMetaConsolidated = ['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]];
$consolidatedSheet->getStyle("A1:{$lastColLetterConsolidated}2")->applyFromArray($styleMetaConsolidated);
for ($r = 1; $r <= 2; $r++) $consolidatedSheet->getRowDimension($r)->setRowHeight(25);

$styleArrayAllConsolidated = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'font' => ['name' => 'Arial', 'size' => 10],
];
if ($lastDataRowConsolidated >= $headerRowConsolidated) {
    $consolidatedSheet->getStyle('A' . $headerRowConsolidated . ':' . $lastColLetterConsolidated . $lastDataRowConsolidated)->applyFromArray($styleArrayAllConsolidated);
    $consolidatedSheet->getStyle('A' . $headerRowConsolidated . ':B' . $lastDataRowConsolidated)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $consolidatedSheet->getStyle('A' . $headerRowConsolidated . ':' . $lastColLetterConsolidated . $headerRowConsolidated)->getFont()->setBold(true);
}
$consolidatedSheet->getColumnDimension('A')->setAutoSize(true);
$consolidatedSheet->getColumnDimension('B')->setAutoSize(true);
// *** END NEW: Consolidated CIA Marks Sheet ***


// Now create individual subject sheets, starting from sheet index 1
foreach ($subjects_export_data as $index => $subjectItem) {
    $subjectName = $subjectItem['subject_name'];
    $subjectCode = $subjectItem['subject_code'];
    $facultyName = $subjectItem['faculty_name'] ?? 'N/A';
    $htmlTable = $subjectItem['table_html'];

    $safeSheetTitle = substr(preg_replace('/[^\w\s\-\(\)]/', '', $subjectCode), 0, 28);
    $finalSheetTitle = $safeSheetTitle;
    $sheetNameCounter = 1;
    while ($spreadsheet->sheetNameExists($finalSheetTitle)) {
        $finalSheetTitle = substr($safeSheetTitle, 0, 28 - (strlen((string)$sheetNameCounter) + 2)) . " (" . $sheetNameCounter++ . ")";
    }

    $sheet = new Worksheet($spreadsheet, $finalSheetTitle);
    $spreadsheet->addSheet($sheet, $index + 1);

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
    $tempSpreadsheet = $reader->loadFromString("<html><body>" . $htmlTable . "</body></html>");
    $tempSheet = $tempSpreadsheet->getActiveSheet();

    $highestRowTemp = $tempSheet->getHighestRow();
    $highestColumnTemp = $tempSheet->getHighestColumn();

    if ($highestRowTemp > 0 || ($highestColumnTemp != 'A' || $tempSheet->getCell('A1')->getValue() !== null)) {
        $sheet->insertNewRowBefore(1, 3);

        $sheet->setCellValue('A1', 'Class: ' . $class_display_name_for_report);
        $sheet->mergeCells("A1:{$highestColumnTemp}1");
        $sheet->setCellValue('A2', 'Subject: ' . $subjectName);
        $sheet->mergeCells("A2:{$highestColumnTemp}2");
        $sheet->setCellValue('A3', 'Faculty: ' . $facultyName);
        $sheet->mergeCells("A3:{$highestColumnTemp}3");

        for ($row = 1; $row <= $highestRowTemp; ++$row) {
            for ($colChar = 'A'; $colChar <= $highestColumnTemp; ++$colChar) {
                $cellValue = $tempSheet->getCell($colChar . $row)->getValue();
                $sheet->setCellValue($colChar . ($row + 3), $cellValue);

                foreach ($tempSheet->getMergeCells() as $mergedRange) {
                    if ($tempSheet->getCell($colChar . $row)->isInRange($mergedRange)) {
                        $splitCellRanges = Coordinate::splitRange($mergedRange);
                        if (!empty($splitCellRanges) && isset($splitCellRanges[0]) && is_array($splitCellRanges[0]) && count($splitCellRanges[0]) === 2) {
                            $actualCellPair = $splitCellRanges[0];
                            $startCellString = $actualCellPair[0];
                            $endCellString = $actualCellPair[1];
                            list($startColCoord, $startExcelRow) = Coordinate::coordinateFromString($startCellString);
                            list($endColCoord, $endExcelRow) = Coordinate::coordinateFromString($endCellString);
                            $newStartRow = $startExcelRow + 3;
                            $newEndRow = $endExcelRow + 3;
                            $newMergedRange = $startColCoord . $newStartRow . ':' . $endColCoord . $newEndRow;
                            try {
                                $isAlreadyMerged = false;
                                foreach ($sheet->getMergeCells() as $existingMergeRange) {
                                    if ($sheet->getCell($startColCoord . $newStartRow)->isInRange($existingMergeRange)) {
                                        $isAlreadyMerged = true;
                                        break;
                                    }
                                }
                                if (!$isAlreadyMerged) $sheet->mergeCells($newMergedRange);
                            } catch (\Exception $e) { /* ignore */
                            }
                        } else {
                            error_log("Skipping problematic merged range: " . $mergedRange);
                            continue;
                        }
                        break;
                    }
                }
            }
        }

        $styleMeta = ['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]];
        $sheet->getStyle("A1:{$highestColumnTemp}3")->applyFromArray($styleMeta);
        for ($r = 1; $r <= 3; $r++) $sheet->getRowDimension($r)->setRowHeight(25);

        $currentSheetHighestRow = $sheet->getHighestDataRow();
        $currentSheetHighestColumn = $sheet->getHighestDataColumn();

        $styleArrayAll = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'font' => ['name' => 'Arial', 'size' => 10],
        ];
        if ($currentSheetHighestRow > 0 && $currentSheetHighestColumn !== null) {
            if ($currentSheetHighestRow > 3) {
                $sheet->getStyle('A4:' . $currentSheetHighestColumn . $currentSheetHighestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getStyle('A1:' . $currentSheetHighestColumn . $currentSheetHighestRow)->applyFromArray($styleArrayAll);
        }

        foreach (range('A', $currentSheetHighestColumn) as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
    } else {
        $sheet->setCellValue('A1', 'Class: ' . $class_display_name_for_report);
        $sheet->setCellValue('A2', 'Subject: ' . $subjectName);
        $sheet->setCellValue('A3', 'Faculty: ' . $facultyName);
        $sheet->setCellValue('A4', 'No data to display for this subject.');
    }

    $tempSpreadsheet->disconnectWorksheets();
    unset($tempSpreadsheet);
}

if ($spreadsheet->getSheetCount() === 0) {
    echo "Error: No sheets were processed.";
    exit;
}

$spreadsheet->setActiveSheetIndex(0);

$safeClassNameForFile = preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_display_name_for_report);
$filename = "ClassReport_" . $safeClassNameForFile . "_" . date("Ymd_His") . ".xlsx";

// Prepare writer
$writer = new Xlsx($spreadsheet);

// Buffer the Excel output to get its length
ob_start();
$writer->save("php://output");
$excelData = ob_get_clean();

// Now send all headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . strlen($excelData)); // Send the actual length

// Output the Excel data
echo $excelData;
exit;

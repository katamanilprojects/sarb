<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_ATTENDANCE','export');}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

if (empty($_SESSION['user']) || !in_array($_SESSION['role'], ['faculty','hod','admin','superadmin'])) {
	header('Location: ./');
	exit;
}

require_once("hod.class.php");
require_once("faculty.class.php");
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (empty($_POST['cls_id']) || empty($_POST['report_type'])) {
    die('Invalid request');
}

$hodObj = new HOD();
$facultyObj = new Faculty();
$selected_cls_id = $_POST['cls_id'];
$start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

$studentList = $hodObj->getStudentsByClass($selected_cls_id);
$subjectList = $hodObj->getSubjectsByClassID($selected_cls_id);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$spreadsheet->setActiveSheetIndex(0);

// Get class details
$details = null;
if (!empty($subjectList['data'])) {
    $details = $hodObj->getClsSubFacBySubID($subjectList['data'][0]['id']);
}

// Handle empty data cases
if (empty($studentList) || empty($subjectList['data'])) {
    $sheet->setCellValue('A1', 'No data available for the selected class.');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    $filename = 'attendance_report_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$sheet->setTitle('Attendance Report');

$row = 1;
if (!empty($details['data'])) {
    // Class info with merge
    $sheet->setCellValue('A' . $row, 'Class: ' . $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(18);
    $row++;
    
    // Report type with merge
    $reportTypeText = 'Course-wise Percentage';
    if ($_POST['report_type'] == 'overall') {
        $reportTypeText = 'Overall Percentage';
    } elseif ($_POST['report_type'] == 'enhanced') {
        $reportTypeText = 'Enhanced Overall Percentage (Course-wise Total and Present Classes)';
    }
    $sheet->setCellValue('A' . $row, 'Report Type: ' . $reportTypeText);
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    if ($start_date || $end_date) {
        $dateRange = '';
        if ($start_date && $end_date) {
            $dateRange = date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date));
        } elseif ($start_date) {
            $dateRange = 'From ' . date('d-m-Y', strtotime($start_date));
        } elseif ($end_date) {
            $dateRange = 'Until ' . date('d-m-Y', strtotime($end_date));
        }
        $sheet->setCellValue('A' . $row, 'Date Range: ' . $dateRange);
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
    }
    $row++;
}

// Set column widths and default font size
$spreadsheet->getDefaultStyle()->getFont()->setSize(14);
$sheet->getColumnDimension('A')->setWidth(15); // Adm No
$sheet->getColumnDimension('B')->setWidth(30); // Name

if ($_POST['report_type'] == 'overall' || $_POST['report_type'] == 'enhanced') {
    // Overall report logic
    $sub_sno_array = array();
    $attendanceData = array();
    
    $subject_max_classes = array();
    if (!empty($subjectList['status']) && $subjectList['status'] == 1) {
        foreach ($subjectList['data'] as $subject) {
            if (!in_array($subject['subject_sno'], $sub_sno_array)) {
                array_push($sub_sno_array, $subject['subject_sno']);
            }
            $temp_attendanceData = $hodObj->getDetailedAttendanceBySubjectExcludingPermissions($subject['id'], $start_date, $end_date);
            $subject_max_classes[$subject['subcode']] = 0;
            if (!empty($temp_attendanceData['data'])) {
                foreach ($temp_attendanceData['data'] as $student) {
                    $attendanceData[$student['username']][$subject['subject_sno']]['tot_cls'] = (int)$student['total_classes'];
                    $attendanceData[$student['username']][$subject['subject_sno']]['tot_pre'] = (int)$student['total_present'];
                    if ($subject_max_classes[$subject['subcode']] < $student['total_classes']) {
                        $subject_max_classes[$subject['subcode']] = $student['total_classes'];
                    }
                }
            }
        }
    }
    
    sort($sub_sno_array, SORT_NUMERIC);
    
    // Headers
    $headerRow = $row;
    $col = 'A';
    $sheet->setCellValue($col++ . $row, 'Adm. No.');
    $sheet->setCellValue($col++ . $row, 'Name of the Student');
    $colIndex = 2;
    
    if ($_POST['report_type'] == 'enhanced') {
        // Enhanced report with Present/Total columns for each subject
        foreach ($sub_sno_array as $sno) {
            $sheet->setCellValue($col++ . $row, 'S.' . $sno . ' (P)');
            $sheet->setCellValue($col++ . $row, 'S.' . $sno . ' (T)');
            $sheet->getColumnDimension(chr(ord('A') + $colIndex++))->setWidth(8);
            $sheet->getColumnDimension(chr(ord('A') + $colIndex++))->setWidth(8);
        }
    } else {
        // Regular overall report
        foreach ($sub_sno_array as $sno) {
            $sheet->setCellValue($col++ . $row, 'S.' . $sno);
            $sheet->getColumnDimension(chr(ord('A') + $colIndex++))->setWidth(10);
        }
    }
    
    $sheet->setCellValue($col++ . $row, 'Tot.Cls');
    $sheet->setCellValue($col++ . $row, 'Present');
    $sheet->setCellValue($col++ . $row, 'Overall %');
    
    // Style headers
    $colCount = ($_POST['report_type'] == 'enhanced') ? (count($sub_sno_array) * 2) + 4 : count($sub_sno_array) + 4;
    $lastCol = chr(ord('A') + $colCount - 1);
    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
    $row++;
    
    // Data and collect statistics
    $lessThan65 = [];
    $between65And75 = [];
    
    foreach ($studentList as $student) {
        $col = 'A';
        $sheet->setCellValue($col++ . $row, $student['roll_number']);
        $sheet->setCellValue($col++ . $row, $student['name']);
        
        $tot_cls = 0;
        $tot_pre = 0;
        
        foreach ($sub_sno_array as $sno) {
            if (isset($attendanceData[$student['roll_number']][$sno]['tot_pre'])) {
                if ($_POST['report_type'] == 'enhanced') {
                    $sheet->setCellValue($col++ . $row, $attendanceData[$student['roll_number']][$sno]['tot_pre']);
                    $sheet->setCellValue($col++ . $row, $attendanceData[$student['roll_number']][$sno]['tot_cls']);
                } else {
                    $sheet->setCellValue($col++ . $row, $attendanceData[$student['roll_number']][$sno]['tot_pre']);
                }
                $tot_cls += (int)$attendanceData[$student['roll_number']][$sno]['tot_cls'];
                $tot_pre += (int)$attendanceData[$student['roll_number']][$sno]['tot_pre'];
            } else {
                if ($_POST['report_type'] == 'enhanced') {
                    $sheet->setCellValue($col++ . $row, '-');
                    $sheet->setCellValue($col++ . $row, '-');
                } else {
                    $sheet->setCellValue($col++ . $row, '-');
                }
            }
        }
        
        $sheet->setCellValue($col++ . $row, $tot_cls);
        $sheet->setCellValue($col++ . $row, $tot_pre);
        $overallPercentage = ($tot_cls > 0) ? round(($tot_pre * 100) / $tot_cls, 2) : 0;
        $sheet->setCellValue($col++ . $row, $overallPercentage . '%');
        
        // Collect statistics
        if ($overallPercentage < 65) {
            $lessThan65[] = $student['roll_number'];
        } elseif ($overallPercentage >= 65 && $overallPercentage < 75) {
            $between65And75[] = $student['roll_number'];
        }
        
        $row++;
    }
    
    // Add summary sheet
    $summarySheet = $spreadsheet->createSheet();
    $summarySheet->setTitle('Summary');
    
    // Subject mapping
    $mappingList = $hodObj->getFacSubMapByClassID($selected_cls_id);
    $summaryRow = 1;
    
    $summarySheet->setCellValue('A' . $summaryRow, 'Subject Mapping');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summaryRow += 2;
    
    $summarySheet->setCellValue('A' . $summaryRow, 'S.No');
    $summarySheet->setCellValue('B' . $summaryRow, 'Subject Code - Name');
    $summarySheet->setCellValue('C' . $summaryRow, 'Faculty');
    $summarySheet->setCellValue('D' . $summaryRow, 'Max Classes');
    $summarySheet->getStyle('A' . $summaryRow . ':D' . $summaryRow)->getFont()->setBold(true);
    $summaryRow++;
    
    if (!empty($mappingList['data'])) {
        foreach ($mappingList['data'] as $mapping) {
            $summarySheet->setCellValue('A' . $summaryRow, 'S.' . $mapping['subject_sno']);
            $summarySheet->setCellValue('B' . $summaryRow, $mapping['subcode'] . ' - ' . $mapping['sub_fullname']);
            $summarySheet->setCellValue('C' . $summaryRow, $mapping['faculty_name']);
            $summarySheet->setCellValue('D' . $summaryRow, isset($subject_max_classes[$mapping['subcode']]) ? $subject_max_classes[$mapping['subcode']] : 0);
            $summaryRow++;
        }
    }
    
    $summaryRow += 2;
    $summarySheet->setCellValue('A' . $summaryRow, 'Attendance Statistics');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summaryRow += 2;
    
    $summarySheet->setCellValue('A' . $summaryRow, '65% - 75%');
    $summarySheet->setCellValue('B' . $summaryRow, count($between65And75));
    $summarySheet->setCellValue('C' . $summaryRow, implode(', ', $between65And75));
    $summaryRow++;
    
    $summarySheet->setCellValue('A' . $summaryRow, '< 65%');
    $summarySheet->setCellValue('B' . $summaryRow, count($lessThan65));
    $summarySheet->setCellValue('C' . $summaryRow, implode(', ', $lessThan65));
    
    $summarySheet->getColumnDimension('A')->setWidth(15);
    $summarySheet->getColumnDimension('B')->setWidth(20);
    $summarySheet->getColumnDimension('C')->setWidth(40);
    $summarySheet->getColumnDimension('D')->setWidth(15);
} else {
    // Subject-wise report logic
    $sub_sno_array = array();
    $attendanceData = array();
    $subject_max_classes = array();
    
    if (!empty($subjectList['status']) && $subjectList['status'] == 1) {
        foreach ($subjectList['data'] as $subject) {
            if (!in_array($subject['subject_sno'], $sub_sno_array)) {
                array_push($sub_sno_array, $subject['subject_sno']);
            }
            $temp_attendanceData = $hodObj->getDetailedAttendanceBySubjectExcludingPermissions($subject['id'], $start_date, $end_date);
            $subject_max_classes[$subject['subcode']] = 0;
            if (!empty($temp_attendanceData['data'])) {
                foreach ($temp_attendanceData['data'] as $student) {
                    $attendanceData[$student['username']][$subject['subject_sno']] = (float)$student['percentage'];
                    if ($subject_max_classes[$subject['subcode']] < $student['total_classes']) {
                        $subject_max_classes[$subject['subcode']] = $student['total_classes'];
                    }
                }
            }
        }
    }
    
    sort($sub_sno_array, SORT_NUMERIC);
    
    // Headers
    $headerRow = $row;
    $col = 'A';
    $sheet->setCellValue($col++ . $row, 'Adm. No.');
    $sheet->setCellValue($col++ . $row, 'Name of the Student');
    $colIndex = 2;
    foreach ($sub_sno_array as $sno) {
        $sheet->setCellValue($col++ . $row, 'S.' . $sno);
        $sheet->getColumnDimension(chr(ord('A') + $colIndex++))->setWidth(10);
    }
    
    // Style headers
    $lastCol = chr(ord('A') + count($sub_sno_array) + 1);
    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
    $row++;
    
    // Data and collect statistics
    $lessThan65BySubject = [];
    $between65And75BySubject = [];
    
    foreach ($studentList as $student) {
        $col = 'A';
        $sheet->setCellValue($col++ . $row, $student['roll_number']);
        $sheet->setCellValue($col++ . $row, $student['name']);
        
        foreach ($sub_sno_array as $sno) {
            if (isset($attendanceData[$student['roll_number']][$sno])) {
                $percentage = (float)$attendanceData[$student['roll_number']][$sno];
                $sheet->setCellValue($col++ . $row, $percentage . '%');
                
                // Collect statistics
                if ($percentage < 65) {
                    $lessThan65BySubject[$sno][] = $student['roll_number'];
                } elseif ($percentage >= 65 && $percentage < 75) {
                    $between65And75BySubject[$sno][] = $student['roll_number'];
                }
            } else {
                $sheet->setCellValue($col++ . $row, '-');
            }
        }
        $row++;
    }
    
    // Add summary sheet
    $summarySheet = $spreadsheet->createSheet();
    $summarySheet->setTitle('Summary');
    
    // Subject mapping and statistics
    $mappingList = $hodObj->getFacSubMapByClassID($selected_cls_id);
    $summaryRow = 1;
    
    $summarySheet->setCellValue('A' . $summaryRow, 'Subject Mapping');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summaryRow += 2;
    
    $summarySheet->setCellValue('A' . $summaryRow, 'S.No');
    $summarySheet->setCellValue('B' . $summaryRow, 'Subject Code - Name');
    $summarySheet->setCellValue('C' . $summaryRow, 'Faculty');
    $summarySheet->setCellValue('D' . $summaryRow, 'Max Classes');
    $summarySheet->getStyle('A' . $summaryRow . ':D' . $summaryRow)->getFont()->setBold(true);
    $summaryRow++;
    
    if (!empty($mappingList['data'])) {
        foreach ($mappingList['data'] as $mapping) {
            $summarySheet->setCellValue('A' . $summaryRow, 'S.' . $mapping['subject_sno']);
            $summarySheet->setCellValue('B' . $summaryRow, $mapping['subcode'] . ' - ' . $mapping['sub_fullname']);
            $summarySheet->setCellValue('C' . $summaryRow, $mapping['faculty_name']);
            $summarySheet->setCellValue('D' . $summaryRow, isset($subject_max_classes[$mapping['subcode']]) ? $subject_max_classes[$mapping['subcode']] : 0);
            $summaryRow++;
        }
    }
    
    $summaryRow += 2;
    $summarySheet->setCellValue('A' . $summaryRow, 'Subject-wise Statistics (65%-75%)');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summaryRow += 2;
    
    ksort($between65And75BySubject, SORT_NUMERIC);
    foreach ($between65And75BySubject as $sno => $students) {
        $summarySheet->setCellValue('A' . $summaryRow, 'S.' . $sno);
        $summarySheet->setCellValue('B' . $summaryRow, count($students));
        $summarySheet->setCellValue('C' . $summaryRow, implode(', ', $students));
        $summaryRow++;
    }
    
    $summaryRow += 2;
    $summarySheet->setCellValue('A' . $summaryRow, 'Subject-wise Statistics (< 65%)');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summaryRow += 2;
    
    ksort($lessThan65BySubject, SORT_NUMERIC);
    foreach ($lessThan65BySubject as $sno => $students) {
        $summarySheet->setCellValue('A' . $summaryRow, 'S.' . $sno);
        $summarySheet->setCellValue('B' . $summaryRow, count($students));
        $summarySheet->setCellValue('C' . $summaryRow, implode(', ', $students));
        $summaryRow++;
    }
    
    $summarySheet->getColumnDimension('A')->setWidth(15);
    $summarySheet->getColumnDimension('B')->setWidth(20);
    $summarySheet->getColumnDimension('C')->setWidth(40);
    $summarySheet->getColumnDimension('D')->setWidth(15);
}

date_default_timezone_set('Asia/Kolkata');
$filename = 'attendance_report_' . date('d_m_Y_H_i_s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
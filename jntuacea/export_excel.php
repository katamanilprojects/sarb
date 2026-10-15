<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_CIA_MARKS','export');}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (empty($_SESSION['user']) || !in_array($_SESSION['role'], ['faculty','hod','admin','superadmin'])) {
	header('Location: ./');
	exit;
}

if (empty($_POST['secretcode']) || $_POST['secretcode'] !== $_SESSION['secretcode']) {
	header('Location: ./');
	exit;
}
unset($_SESSION['secretcode']);

if (empty($_POST['table_html'])) {
    header('Location: facshowattendance.php');
}

$html = $_POST['table_html'];


require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Load HTML into PhpSpreadsheet
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
$spreadsheet = $reader->loadFromString($html);

$sheet = $spreadsheet->getActiveSheet();

$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

// Insert 3 rows at the top to add metadata
$sheet->insertNewRowBefore(1, 3);

// Prepare metadata texts
$classText = 'Class: ' . ($_POST['class'] ?? 'N/A');
$subjectText = 'Subject: ' . ($_POST['subject'] ?? 'N/A');
$facultyText = 'Faculty: ' . ($_POST['faculty'] ?? 'N/A');

$cell = Coordinate::stringFromColumnIndex(1) . '1'; // For column 1, row 1 => A1
$sheet->setCellValue($cell, $classText);
$sheet->mergeCells("A1:".$highestColumn."1");

$cell = Coordinate::stringFromColumnIndex(1) . '2'; // A2
$sheet->setCellValue($cell, $subjectText);
$sheet->mergeCells("A2:".$highestColumn."2");

$cell = Coordinate::stringFromColumnIndex(1) . '3'; // A3
$sheet->setCellValue($cell, $facultyText);
$sheet->mergeCells("A3:".$highestColumn."3");

// Optional styling for metadata rows
$styleMeta = [
    'font' => ['bold' => true, 'size' => 12],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle("A1:".$highestColumn."3")->applyFromArray($styleMeta);

// Adjust row heights for metadata rows
for ($r = 1; $r <= 3; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(25);
}

$sheet = $spreadsheet->getActiveSheet();

// Apply styling to entire sheet
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$range = 'A1:' . $highestColumn . $highestRow;

$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
    'font' => [
        'name' => 'Arial',
        'size' => 11,
    ],
];

$sheet->getStyle($range)->applyFromArray($styleArray);
$sheet->getDefaultRowDimension()->setRowHeight(20);
foreach (range('A', $highestColumn) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Prepare download
$filename = "Internal_Marks_" . date("Ymd_His") . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
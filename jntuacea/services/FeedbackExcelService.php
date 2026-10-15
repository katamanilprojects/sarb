<?php
/**
 * FeedbackExcelService - Comprehensive Excel Generation Service for Feedback System
 * 
 * Uses PhpSpreadsheet to generate professional XLSX reports for:
 * - Course Outcome (CO) feedback
 * - Course End Survey (CES) feedback
 * - Faculty evaluation feedback
 * - Multi-level aggregation (subject, class, faculty, department)
 * 
 * Features:
 * - Professional NBA-compliant formatting
 * - Multiple worksheets for different data views
 * - Advanced styling and conditional formatting
 * - Charts and visualizations
 * - Data validation and analysis
 */

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class FeedbackExcelService
{
    private $conn;
    private $spreadsheet;
    private $feedbackService;
    
    // Color schemes for professional formatting
    private $colors = [
        'header_bg' => '4472C4',
        'header_text' => 'FFFFFF',
        'subheader_bg' => 'D9E1F2',
        'border_color' => '000000',
        'accent_blue' => '4472C4',
        'accent_green' => '70AD47',
        'accent_orange' => 'ED7D31',
        'accent_red' => 'C00000',
        'neutral_gray' => 'F2F2F2',
    ];
    
    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        $this->spreadsheet = new Spreadsheet();
        
        require_once __DIR__ . '/../feedbackservice.class.php';
        $this->feedbackService = new FeedbackService();
    }
    
    /**
     * Generate comprehensive feedback Excel report
     * 
     * @param string $level - 'subject', 'class', 'faculty', 'department'
     * @param array $params - Filter parameters (sub_id, cls_id, fac_id, dept_id)
     * @param string $format - 'xlsx' or 'xls'
     * @return string - File path to generated Excel file
     */
    public function generateFeedbackReport($level, $params, $format = 'xlsx')
    {
        try {
            $this->spreadsheet = new Spreadsheet();
            // Remove default sheet
            $this->spreadsheet->removeSheetByIndex(0);
            
            // Fetch feedback data based on level
            $feedbackData = $this->fetchFeedbackData($level, $params);
            
            if (empty($feedbackData) || ($feedbackData['status'] ?? 0) != 1) {
                throw new Exception("No feedback data available for the selected parameters.");
            }
            
            // Create worksheets based on level
            $this->createExecutiveSummarySheet($feedbackData, $level);

            if ($level === 'subject') {
                $this->createCOFeedbackSheet($feedbackData, $level);
                if (!empty($feedbackData['ces_feedback']['total_responses'])) {
                    $this->createCESFeedbackSheet($feedbackData, $level);
                }
                if (!empty($feedbackData['faculty_evaluations']['total_evaluations'])) {
                    $this->createFacultyFeedbackSheet($feedbackData, $level);
                }
                $this->createQualitativeFeedbackSheet($feedbackData, $level);
                $this->createAnalyticsSheet($feedbackData, $level);
                $this->createStudentCORatingsSheet($feedbackData, $level);
                if (!empty($feedbackData['ces_feedback']['total_responses'])) {
                    $this->createStudentCESRatingsSheet($feedbackData, $level);
                }
                if (!empty($feedbackData['faculty_evaluations']['total_evaluations'])) {
                    $this->createStudentFacultyRatingsSheet($feedbackData, $level);
                }
            } elseif ($level === 'class') {
                $this->createClassSubjectsSheet($feedbackData);
                $this->createCOFeedbackSheet($feedbackData, $level);
                $this->createAnalyticsSheet($feedbackData, $level);
            } elseif ($level === 'faculty') {
                $this->createFacultySubjectsSheet($feedbackData);
                $this->createCOFeedbackSheet($feedbackData, $level);
                if (!empty($feedbackData['faculty_evaluations']['total_evaluations'])) {
                    $this->createFacultyFeedbackSheet($feedbackData, $level);
                }
                $this->createQualitativeFeedbackSheet($feedbackData, $level);
                $this->createAnalyticsSheet($feedbackData, $level);
            } elseif ($level === 'department') {
                $this->createDepartmentClassesSheet($feedbackData);
                $this->createAnalyticsSheet($feedbackData, $level);
            }

            // Always ensure Sheet 1 (Executive Summary) is the active sheet upon opening Excel
            $this->spreadsheet->setActiveSheetIndex(0);
            
            // Generate file
            $filename = $this->generateFilename($level, $params, $format);
            $filepath = $this->saveFile($filename, $format);
            
            return $filepath;
            
        } catch (Exception $e) {
            throw new Exception("Excel generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch feedback data based on level and parameters
     */
    private function fetchFeedbackData($level, $params)
    {
        switch ($level) {
            case 'subject':
                return $this->feedbackService->getSubjectFeedback(
                    intval($params['sub_id'] ?? 0),
                    intval($params['cls_id'] ?? 0)
                );
            case 'class':
                return $this->feedbackService->getClassFeedback(
                    intval($params['cls_id'] ?? 0)
                );
            case 'faculty':
                return $this->feedbackService->getFacultyFeedback(
                    intval($params['fac_id'] ?? 0),
                    intval($params['cls_id'] ?? 0),
                    !empty($params['acad_year']) ? $params['acad_year'] : null
                );
            case 'department':
                return $this->feedbackService->getDepartmentFeedback(
                    intval($params['dept_id'] ?? 0),
                    null,
                    null,
                    null,
                    !empty($params['acad_year']) ? $params['acad_year'] : null
                );
            default:
                throw new Exception("Invalid feedback level: $level");
        }
    }
    
    /**
     * Create Executive Summary Sheet
     */
    private function createExecutiveSummarySheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Executive Summary");
        $this->spreadsheet->addSheet($sheet, 0);
        
        $row = 1;
        
        // Institutional Header with enhanced styling
        $this->addInstitutionalHeader($sheet, $row);
        $row += 4;
        
        // Report Metadata with better layout
        $this->addReportMetadata($sheet, $row, $data, $level);
        $row += 5;
        
        // Key Performance Indicators with enhanced visual design (4 rows)
        $this->addKPIs($sheet, $row, $data, $level);
        $row += 8;
        
        // Summary Statistics Table with professional formatting
        $this->addSummaryStatistics($sheet, $row, $data, $level);
        $row += 8;
        
        // Add benchmark comparison table
        $this->addBenchmarkComparison($sheet, $row, $data, $level);
        
        // Apply enhanced styling
        $this->applyExecutiveSummaryStyling($sheet);
        
        // Set Executive Summary as active sheet (first sheet should be viewable by default)
        $this->spreadsheet->setActiveSheetIndex(0);
    }
    
    /**
     * Add institutional header
     */
    private function addInstitutionalHeader($sheet, &$row)
    {
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->setCellValue("A$row", "JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['accent_blue']));
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->setCellValue("A$row", "FEEDBACK ANALYSIS REPORT");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->setCellValue("A$row", "Generated on: " . date('d-M-Y H:i:s'));
        $sheet->getStyle("A$row")->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    
    /**
     * Add report metadata
     */
    private function addReportMetadata($sheet, &$row, $data, $level)
    {
        $sheet->setCellValue("A$row", "Report Level:");
        $sheet->setCellValue("B$row", ucfirst($level) . "-wise Feedback");
        $sheet->getStyle("A$row")->getFont()->setBold(true);
        
        $row++;
        
        // Add level-specific metadata
        if ($level === 'subject') {
            $meta = $data['meta'] ?? [];
            $sheet->setCellValue("A$row", "Department:");
            $sheet->setCellValue("B$row", $meta['dept_fullname'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Class:");
            $sheet->setCellValue("B$row", $meta['classname'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Subject:");
            $sheet->setCellValue("B$row", ($meta['sub_fullname'] ?? '') . " (" . ($meta['subcode'] ?? '') . ")");
            $row++;
            $sheet->setCellValue("A$row", "Faculty:");
            $sheet->setCellValue("B$row", $meta['faculty_name'] ?? 'N/A');
        } elseif ($level === 'class') {
            $meta = $data['meta'] ?? [];
            $sheet->setCellValue("A$row", "Department:");
            $sheet->setCellValue("B$row", $meta['dept_fullname'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Class:");
            $sheet->setCellValue("B$row", $meta['classname'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Academic Year:");
            $sheet->setCellValue("B$row", $meta['acad_year'] ?? 'N/A');
        } elseif ($level === 'faculty') {
            $faculty = $data['faculty'] ?? [];
            $sheet->setCellValue("A$row", "Faculty Name:");
            $sheet->setCellValue("B$row", $faculty['faculty_name'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Designation:");
            $sheet->setCellValue("B$row", $faculty['designation'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Department:");
            $sheet->setCellValue("B$row", $faculty['dept_fullname'] ?? 'N/A');
            $row++;
            $sheet->setCellValue("A$row", "Academic Year:");
            $sheet->setCellValue("B$row", !empty($data['meta']['acad_year']) ? $data['meta']['acad_year'] : 'All Academic Years');
        } elseif ($level === 'department') {
            $dept = $data['department'] ?? [];
            $sheet->setCellValue("A$row", "Department:");
            $sheet->setCellValue("B$row", $dept['dept_fullname'] ?? 'N/A');
        }
        
        $row++;
    }
    
    /**
     * Add Key Performance Indicators (4 rows instead of 4 columns)
     */
    private function addKPIs($sheet, &$row, $data, $level)
    {
        $sheet->setCellValue("A$row", "KEY PERFORMANCE INDICATORS");
        $sheet->mergeCells("A" . $row . ":B" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        
        // KPI data based on level
        $kpiData = $this->getKPIData($data, $level);
        
        // Display KPIs in 4 rows instead of 4 columns
        foreach ($kpiData as $kpi) {
            $sheet->setCellValue("A" . $row, $kpi['label']);
            $sheet->setCellValue("B" . $row, $kpi['value']);
            $sheet->getStyle("A" . $row)->getFont()->setBold(true);
            $sheet->getStyle("B" . $row)->getFont()->setBold(true)->setSize(14);
            
            // Color coding based on performance
            $color = $this->getKPIColor($kpi['value'], $kpi['type']);
            $sheet->getStyle("B" . $row)->getFont()->setColor(new Color($color));
            
            $row++;
        }
        
        $row += 2;
    }
    
    /**
     * Get KPI data based on level
     */
    private function getKPIData($data, $level)
    {
        $kpiData = [];
        
        if ($level === 'subject') {
            $kpiData[] = ['label' => 'Total Enrolled', 'value' => $data['total_enrolled'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Responses Received', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $respRate = floatval($data['response_rate'] ?? 0);
            $kpiData[] = ['label' => 'Response Rate (%)', 'value' => $respRate > 0 ? $respRate : 'N/A', 'type' => 'percentage'];
            $avg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Overall CO Rating', 'value' => $avg > 0 ? $avg : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'class') {
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['total_students'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Responses', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $respRate = floatval($data['response_rate'] ?? 0);
            $kpiData[] = ['label' => 'Response Rate (%)', 'value' => $respRate > 0 ? $respRate : 'N/A', 'type' => 'percentage'];
            $avg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Class Average Rating', 'value' => $avg > 0 ? $avg : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'faculty') {
            $kpiData[] = ['label' => 'Total Subjects', 'value' => $data['summary']['total_subjects'] ?? 0, 'type' => 'count'];
            $coAvg = floatval($data['summary']['overall_co_avg'] ?? 0);
            $kpiData[] = ['label' => 'CO Feedback Average', 'value' => $coAvg > 0 ? $coAvg : 'N/A', 'type' => 'rating'];
            if (!empty($data['faculty_evaluations']['total_evaluations'])) {
                $feScore = floatval($data['faculty_evaluations']['avg_score'] ?? 0);
                $kpiData[] = ['label' => 'Faculty Survey Score', 'value' => $feScore > 0 ? $feScore : 'N/A', 'type' => 'rating'];
            }
        } elseif ($level === 'department') {
            $kpiData[] = ['label' => 'Total Classes', 'value' => $data['summary']['total_classes'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Subjects', 'value' => $data['summary']['total_subjects'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['summary']['total_students'] ?? 0, 'type' => 'count'];
            $deptAvg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Department Average', 'value' => $deptAvg > 0 ? $deptAvg : 'N/A', 'type' => 'rating'];
        }
        
        return $kpiData;
    }
    
    /**
     * Get color based on KPI performance
     */
    private function getKPIColor($value, $type)
    {
        if ($value === 'N/A' || !is_numeric($value)) {
            return '6C757D';
        }
        if ($type === 'percentage') {
            if ($value >= 80) return $this->colors['accent_green'];
            if ($value >= 60) return $this->colors['accent_orange'];
            return $this->colors['accent_red'];
        } elseif ($type === 'rating') {
            if ($value >= 4.0) return $this->colors['accent_green'];
            if ($value >= 3.0) return $this->colors['accent_orange'];
            return $this->colors['accent_red'];
        }
        return $this->colors['accent_blue'];
    }
    
    /**
     * Add summary statistics table
     */
    private function addSummaryStatistics($sheet, &$row, $data, $level)
    {
        $sheet->setCellValue("A$row", "SUMMARY STATISTICS");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        
        // Statistics table headers
        $headers = ['Metric', 'Value', 'Benchmark', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $col++;
        }
        
        $row++;
        
        // Add statistics rows
        $stats = $this->getSummaryStatistics($data, $level);
        foreach ($stats as $stat) {
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $stat['metric']);
            $sheet->setCellValue($col++ . $row, $stat['value']);
            $sheet->setCellValue($col++ . $row, $stat['benchmark']);
            $sheet->setCellValue($col++ . $row, $stat['status']);
            
            // Status color coding
            $statusColor = $stat['status'] === 'Above Target' ? $this->colors['accent_green'] : 
                          ($stat['status'] === 'Below Target' ? $this->colors['accent_red'] : 
                          ($stat['status'] === 'N/A' ? '6C757D' : $this->colors['accent_orange']));
            $sheet->getStyle('D' . $row)->getFont()->setColor(new Color($statusColor))->setBold(true);
            
            $row++;
        }
    }
    
    /**
     * Get summary statistics
     */
    private function getSummaryStatistics($data, $level)
    {
        $stats = [];
        
        $overallAvg = floatval($data['summary']['overall_avg'] ?? ($data['summary']['overall_co_avg'] ?? 0));
        $responseRate = floatval($data['response_rate'] ?? 0);
        $totalResponded = intval($data['total_responded'] ?? ($data['summary']['total_responses'] ?? ($data['summary']['total_responded'] ?? 0)));
        
        $stats[] = [
            'metric' => 'Overall Rating',
            'value' => $overallAvg > 0 ? number_format($overallAvg, 2) : 'N/A',
            'benchmark' => '≥ 3.5',
            'status' => $overallAvg <= 0 ? 'N/A' : ($overallAvg >= 3.5 ? 'Above Target' : ($overallAvg >= 3.0 ? 'On Target' : 'Below Target'))
        ];
        
        $stats[] = [
            'metric' => 'Response Rate',
            'value' => ($totalResponded > 0 && $responseRate > 0) ? number_format($responseRate, 1) . '%' : 'N/A',
            'benchmark' => '≥ 75%',
            'status' => ($totalResponded <= 0 || $responseRate <= 0) ? 'N/A' : ($responseRate >= 75 ? 'Above Target' : ($responseRate >= 60 ? 'On Target' : 'Below Target'))
        ];
        
        if ($level === 'subject' || $level === 'class') {
            $positiveRate = floatval($data['summary']['positive_response_pct'] ?? 0);
            $stats[] = [
                'metric' => 'Positive Response Rate',
                'value' => ($totalResponded > 0 && $positiveRate > 0) ? number_format($positiveRate, 1) . '%' : 'N/A',
                'benchmark' => '≥ 70%',
                'status' => ($totalResponded <= 0 || $positiveRate <= 0) ? 'N/A' : ($positiveRate >= 70 ? 'Above Target' : ($positiveRate >= 50 ? 'On Target' : 'Below Target'))
            ];
        }
        
        return $stats;
    }
    
    /**
     * Create CO Feedback Sheet
     */
    /**
     * Create Class Subjects Overview Sheet
     */
    private function createClassSubjectsSheet($data)
    {
        $sheet = new Worksheet($this->spreadsheet, "Subjects Overview");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        $sheet->setCellValue("A$row", "CLASS SUBJECTS PERFORMANCE OVERVIEW");
        $sheet->mergeCells("A" . $row . ":J" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        $headers = ['S.No', 'Subject Code', 'Subject Name', 'Faculty Assigned', 'Average Rating', 'Indirect Level', '% Target Met', 'Status', 'Total COs', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        $subjects = $data['subjects'] ?? [];
        $sno = 1;
        foreach ($subjects as $sub) {
            $stats = $sub['summary'] ?? [];
            $avg = floatval($stats['overall_co_avg'] ?? 0);
            $lvl = $stats['avg_indirect_level'] ?? ($stats['nba_indirect_attainment']['level'] ?? 0);
            $targetPct = $stats['avg_target_pct'] ?? ($stats['nba_indirect_attainment']['pct_meeting_target'] ?? 0);
            $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.0 ? 'Good' : 'Needs Improvement');
            
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $sno++);
            $sheet->setCellValue($col++ . $row, $sub['subcode'] ?? '');
            $sheet->setCellValue($col++ . $row, $sub['sub_fullname'] ?? '');
            $sheet->setCellValue($col++ . $row, $sub['faculty_names'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, number_format($avg, 2));
            $sheet->setCellValue($col++ . $row, 'Level ' . number_format($lvl, 1));
            $sheet->setCellValue($col++ . $row, $targetPct . '%');
            $sheet->setCellValue($col++ . $row, $status);
            $sheet->setCellValue($col++ . $row, count(array_filter($data['co_feedback'] ?? [], fn($c) => ($c['subject_id'] ?? 0) == ($sub['id'] ?? 0))));
            $sheet->setCellValue($col++ . $row, $avg > 0 ? 'Completed' : 'Pending');
            
            // Color coding
            $statusColor = $avg >= 4.0 ? $this->colors['accent_green'] : ($avg >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            $sheet->getStyle('H' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            
            if (($row % 2) == 0) {
                $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            }
            $row++;
        }
        
        $this->applyTableStyling($sheet, "A1:J" . ($row - 1));
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(15);
    }

    /**
     * Create Faculty Assigned Subjects Sheet
     */
    private function createFacultySubjectsSheet($data)
    {
        $sheet = new Worksheet($this->spreadsheet, "Assigned Courses");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        $sheet->setCellValue("A$row", "FACULTY ASSIGNED COURSES & PERFORMANCE");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        $headers = ['S.No', 'Class', 'Subject Code', 'Subject Name', 'Type', 'Academic Year', 'CO Feedback Average', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        $subjects = $data['subjects'] ?? [];
        $sno = 1;
        foreach ($subjects as $sub) {
            $sid = $sub['id'] ?? 0;
            $avg = floatval($sub['summary']['overall_co_avg'] ?? 0);
            if ($avg <= 0 && !empty($data['co_feedback'])) {
                $subCOs = array_filter($data['co_feedback'], fn($c) => ($c['subject_id'] ?? 0) == $sid && intval($c['total_responses'] ?? 0) > 0);
                if (!empty($subCOs)) {
                    $subCoSum = array_sum(array_map(fn($c) => floatval($c['average_rating']) * intval($c['total_responses']), $subCOs));
                    $subCoCnt = array_sum(array_column($subCOs, 'total_responses'));
                    $avg = $subCoCnt > 0 ? round($subCoSum / $subCoCnt, 2) : 0;
                }
            }
            $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.0 ? 'Good' : ($avg > 0 ? 'Needs Improvement' : 'N/A'));
            
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $sno++);
            $sheet->setCellValue($col++ . $row, $sub['classname'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, $sub['subcode'] ?? '');
            $sheet->setCellValue($col++ . $row, $sub['sub_fullname'] ?? '');
            $sheet->setCellValue($col++ . $row, ucfirst($sub['sub_type'] ?? 'Theory'));
            $sheet->setCellValue($col++ . $row, $sub['acad_year'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, $avg > 0 ? number_format($avg, 2) : 'N/A');
            $sheet->setCellValue($col++ . $row, $status);
            
            if ($avg > 0) {
                $statusColor = $avg >= 4.0 ? $this->colors['accent_green'] : ($avg >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                $sheet->getStyle('G' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
                $sheet->getStyle('H' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            }
            
            if (($row % 2) == 0) {
                $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            }
            $row++;
        }
        
        $this->applyTableStyling($sheet, "A1:H" . ($row - 1));

        if (!empty($data['academic_year_summary'])) {
            $row += 2;
            $sheet->setCellValue("A$row", "ACADEMIC YEAR PERFORMANCE SUMMARY");
            $sheet->mergeCells("A" . $row . ":F" . $row);
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->colors['header_text']));
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(25);
            $row++;

            $ayHeaders = ['Academic Year', 'Courses Taught', 'CO Responses', 'CO Feedback Average', 'Faculty Survey Score', 'Overall Rating'];
            $col = 'A';
            foreach ($ayHeaders as $h) {
                $sheet->setCellValue($col . $row, $h);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $col++;
            }
            $row++;
            $startAyRow = $row;
            foreach ($data['academic_year_summary'] as $ayStats) {
                $sheet->setCellValue("A$row", $ayStats['acad_year']);
                $sheet->setCellValue("B$row", $ayStats['total_subjects']);
                $sheet->setCellValue("C$row", $ayStats['total_co_responses']);
                $sheet->setCellValue("D$row", $ayStats['co_average'] > 0 ? number_format($ayStats['co_average'], 2) : 'N/A');
                $sheet->setCellValue("E$row", $ayStats['faculty_eval_score'] > 0 ? number_format($ayStats['faculty_eval_score'], 2) : 'N/A');
                $sheet->setCellValue("F$row", $ayStats['overall_rating'] > 0 ? number_format($ayStats['overall_rating'], 2) : 'N/A');
                $sheet->getStyle("A$row:F$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if (($row % 2) == 0) {
                    $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                $row++;
            }
            $this->applyTableStyling($sheet, "A" . ($startAyRow - 1) . ":F" . ($row - 1));
        }

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(35);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(18);
    }

    /**
     * Create Department Classes Overview Sheet
     */
    private function createDepartmentClassesSheet($data)
    {
        $sheet = new Worksheet($this->spreadsheet, "Classes Overview");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        $sheet->setCellValue("A$row", "DEPARTMENT CLASSES FEEDBACK OVERVIEW");
        $sheet->mergeCells("A" . $row . ":I" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        $headers = ['S.No', 'Class Name', 'Specialization', 'Academic Year', 'Courses', 'Enrolled', 'Responded', 'Response %', 'Average Rating'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        $classes = $data['classes'] ?? [];
        $sno = 1;
        foreach ($classes as $cls) {
            $stats = $cls['stats'] ?? [];
            $avg = floatval($stats['overall_avg'] ?? 0);
            
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $sno++);
            $sheet->setCellValue($col++ . $row, $cls['classname'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, $cls['spec_fullname'] ?? ($cls['spec_shortname'] ?? 'N/A'));
            $sheet->setCellValue($col++ . $row, $cls['acad_year'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, $stats['total_subjects'] ?? 0);
            $sheet->setCellValue($col++ . $row, $stats['total_students'] ?? 0);
            $sheet->setCellValue($col++ . $row, $stats['total_responded'] ?? 0);
            $sheet->setCellValue($col++ . $row, ($stats['response_rate'] ?? 0) . '%');
            $sheet->setCellValue($col++ . $row, number_format($avg, 2));
            
            if ($avg > 0) {
                $statusColor = $avg >= 4.0 ? $this->colors['accent_green'] : ($avg >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                $sheet->getStyle('I' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            }
            
            if (($row % 2) == 0) {
                $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            }
            $row++;
        }
        
        $this->applyTableStyling($sheet, "A1:I" . ($row - 1));
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(16);
    }

    /**
     * Create CO Feedback Sheet
     */
    private function createCOFeedbackSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "CO Feedback");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        $isMultiSubject = ($level !== 'subject');
        $endCol = $isMultiSubject ? 'L' : 'K';
        
        // Enhanced header with better styling
        $sheet->setCellValue("A$row", "COURSE OUTCOME (CO) FEEDBACK ANALYSIS");
        $sheet->mergeCells("A" . $row . ":" . $endCol . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        
        // Enhanced table headers with better layout
        if ($isMultiSubject) {
            $headers = ['Subject', 'CO #', 'CO Description', 'Avg Rating', 'Status', 'Attainment', 'Responses', '5★', '4★', '3★', '2★', '1★'];
        } else {
            $headers = ['CO #', 'CO Description', 'Avg Rating', 'Status', 'Attainment', 'Responses', '5★', '4★', '3★', '2★', '1★'];
        }
        
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($col . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // CO feedback data with enhanced formatting
        $coFeedback = $data['co_feedback'] ?? [];
        foreach ($coFeedback as $co) {
            $col = 'A';
            if ($isMultiSubject) {
                $subText = !empty($co['subcode']) ? ($co['subcode'] . ' - ' . ($co['sub_fullname'] ?? '')) : ($co['classname'] ?? 'N/A');
                $sheet->setCellValue($col++ . $row, $subText);
            }
            $sheet->setCellValue($col++ . $row, "CO" . $co['co_number']);
            $sheet->setCellValue($col++ . $row, $co['co_description']);
            $sheet->setCellValue($col++ . $row, number_format($co['average_rating'], 2));
            
            // Add status indicator
            $rating = $co['average_rating'];
            $status = $rating >= 4.0 ? 'Excellent' : ($rating >= 3.0 ? 'Good' : 'Needs Improvement');
            $sheet->setCellValue($col++ . $row, $status);
            
            $attainmentLabel = isset($co['attainment_level']) ? ('Level ' . $co['attainment_level']) : 'N/A';
            $sheet->setCellValue($col++ . $row, $attainmentLabel);
            
            $sheet->setCellValue($col++ . $row, $co['total_responses']);
            $sheet->setCellValue($col++ . $row, $co['count_5']);
            $sheet->setCellValue($col++ . $row, $co['count_4']);
            $sheet->setCellValue($col++ . $row, $co['count_3']);
            $sheet->setCellValue($col++ . $row, $co['count_2']);
            $sheet->setCellValue($col++ . $row, $co['count_1']);
            
            // Enhanced color coding for ratings
            $ratingColLetter = $isMultiSubject ? 'D' : 'C';
            $statusColLetter = $isMultiSubject ? 'E' : 'D';
            
            $ratingColor = $rating >= 4.0 ? $this->colors['accent_green'] : 
                          ($rating >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
            $sheet->getStyle($ratingColLetter . $row)->getFont()->setBold(true)->setSize(12)->setColor(new Color($ratingColor));
            $sheet->getStyle($statusColLetter . $row)->getFont()->setBold(true)->setColor(new Color($ratingColor));
            
            // Apply alternating row colors
            if (($row % 2) == 0) {
                $sheet->getStyle('A' . $row . ':' . $endCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            }
            
            $row++;
        }
        
        // Add summary row at bottom
        $row++;
        $mergeEnd = $isMultiSubject ? 'C' : 'B';
        $sheet->mergeCells("A" . $row . ":" . $mergeEnd . $row);
        $sheet->setCellValue("A$row", "TOTAL RATINGS");
        $sheet->getStyle("A$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Calculate totals
        $total5 = array_sum(array_column($coFeedback, 'count_5'));
        $total4 = array_sum(array_column($coFeedback, 'count_4'));
        $total3 = array_sum(array_column($coFeedback, 'count_3'));
        $total2 = array_sum(array_column($coFeedback, 'count_2'));
        $total1 = array_sum(array_column($coFeedback, 'count_1'));
        
        $startStarCol = $isMultiSubject ? 'H' : 'G';
        $sheet->setCellValue(($isMultiSubject ? 'H' : 'H') . $row, $total5);
        $colLetterIdx = $isMultiSubject ? ['H', 'I', 'J', 'K', 'L'] : ['G', 'H', 'I', 'J', 'K'];
        $sheet->setCellValue($colLetterIdx[0] . $row, $total5);
        $sheet->setCellValue($colLetterIdx[1] . $row, $total4);
        $sheet->setCellValue($colLetterIdx[2] . $row, $total3);
        $sheet->setCellValue($colLetterIdx[3] . $row, $total2);
        $sheet->setCellValue($colLetterIdx[4] . $row, $total1);
        
        $sheet->getStyle($colLetterIdx[0] . $row . ':' . $colLetterIdx[4] . $row)->getFont()->setBold(true);
        $sheet->getStyle($colLetterIdx[0] . $row . ':' . $colLetterIdx[4] . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle($colLetterIdx[0] . $row . ':' . $colLetterIdx[4] . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Apply enhanced styling
        $this->applyTableStyling($sheet, "A1:" . $endCol . ($row + 1));
        
        // Set optimal column widths
        if ($isMultiSubject) {
            $sheet->getColumnDimension('A')->setWidth(25);
            $sheet->getColumnDimension('B')->setWidth(8);
            $sheet->getColumnDimension('C')->setWidth(35);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(14);
            $sheet->getColumnDimension('G')->setWidth(10);
            $sheet->getColumnDimension('H')->setWidth(8);
            $sheet->getColumnDimension('I')->setWidth(8);
            $sheet->getColumnDimension('J')->setWidth(8);
            $sheet->getColumnDimension('K')->setWidth(8);
            $sheet->getColumnDimension('L')->setWidth(8);
        } else {
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(14);
            $sheet->getColumnDimension('F')->setWidth(10);
            $sheet->getColumnDimension('G')->setWidth(8);
            $sheet->getColumnDimension('H')->setWidth(8);
            $sheet->getColumnDimension('I')->setWidth(8);
            $sheet->getColumnDimension('J')->setWidth(8);
            $sheet->getColumnDimension('K')->setWidth(8);
        }
    }
    
    /**
     * Create CES Feedback Sheet
     */
    private function createCESFeedbackSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "CES Feedback");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        
        // Enhanced header
        $sheet->setCellValue("A$row", "COURSE END SURVEY (CES) - 16 EVALUATION PARAMETERS");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        
        // Check if CES data exists
        if (empty($data['ces_feedback']['total_responses'])) {
            $sheet->setCellValue("A$row", "No CES feedback data available for this selection.");
            $sheet->getStyle("A$row")->getFont()->setItalic(true)->setSize(12);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }
        
        // Add domain averages summary first
        $sheet->setCellValue("A$row", "DOMAIN-WISE SUMMARY");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        // Domain summary headers
        $domainHeaders = ['Domain', 'Average', 'Status', 'Benchmark', 'Gap'];
        $col = 'A';
        foreach ($domainHeaders as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;
        
        // Domain summary data
        $domainAverages = $data['ces_feedback']['domain_averages'] ?? [];
        foreach ($domainAverages as $domain => $avg) {
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $domain);
            $sheet->setCellValue($col++ . $row, number_format($avg, 2));
            
            $status = $avg >= 3.5 ? 'Excellent' : ($avg >= 3.0 ? 'Good' : 'Needs Improvement');
            $sheet->setCellValue($col++ . $row, $status);
            $sheet->setCellValue($col++ . $row, '3.00');
            $sheet->setCellValue($col++ . $row, number_format($avg - 3.0, 2));
            
            // Color coding
            $statusColor = $avg >= 3.5 ? $this->colors['accent_green'] : 
                         ($avg >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
            $sheet->getStyle('C' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            
            $row++;
        }
        
        $row += 2;
        
        // Detailed parameters section
        $sheet->setCellValue("A$row", "DETAILED PARAMETER ANALYSIS");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        // Detailed headers
        $headers = ['Section', 'Parameter', 'Statement', 'Avg Rating', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;
        
        // CES questions definitions
        $cesQuestions = FeedbackService::getCesQuestions();
        $cesAverages = $data['ces_feedback']['averages'] ?? [];
        
        // CES data rows with enhanced formatting
        foreach ($cesQuestions as $section => $questions) {
            foreach ($questions as $qKey => $qText) {
                $col = 'A';
                $sheet->setCellValue($col++ . $row, $section);
                $sheet->setCellValue($col++ . $row, strtoupper($qKey));
                $sheet->setCellValue($col++ . $row, $qText);
                $avg = $cesAverages[$qKey] ?? 0;
                $sheet->setCellValue($col++ . $row, number_format($avg, 2));
                
                $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.0 ? 'Good' : 'Needs Improvement');
                $sheet->setCellValue($col++ . $row, $status);
                
                // Enhanced color coding
                $ratingColor = $avg >= 4.0 ? $this->colors['accent_green'] : 
                             ($avg >= 3.0 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                $sheet->getStyle('D' . $row)->getFont()->setBold(true)->setColor(new Color($ratingColor));
                $sheet->getStyle('E' . $row)->getFont()->setBold(true)->setColor(new Color($ratingColor));
                
                // Alternating row colors
                if (($row % 2) == 0) {
                    $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                
                $row++;
            }
        }
        
        // Apply enhanced styling
        $this->applyTableStyling($sheet, "A1:E" . ($row - 1));
        
        // Set optimal column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(15);
    }
    
    /**
     * Create Faculty Feedback Sheet
     */
    private function createFacultyFeedbackSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Faculty Feedback");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        
        // Enhanced header
        $sheet->setCellValue("A$row", "STUDENT FEEDBACK ON FACULTY - 19 OBE PARAMETERS");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        
        $row += 2;
        
        // Check if faculty evaluation data exists
        if (empty($data['faculty_evaluations']['total_evaluations'])) {
            $sheet->setCellValue("A$row", "No faculty evaluation data available for this selection.");
            $sheet->getStyle("A$row")->getFont()->setItalic(true)->setSize(12);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }
        
        // Add domain-wise summary
        $sheet->setCellValue("A$row", "DOMAIN-WISE PERFORMANCE SUMMARY");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        // Domain summary headers
        $domainHeaders = ['Domain', 'Average', 'Status', 'Benchmark', 'Gap'];
        $col = 'A';
        foreach ($domainHeaders as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;
        
        // Calculate domain averages
        $facQuestions = FeedbackService::getFacultyQuestions();
        $facAverages = $data['faculty_evaluations']['averages'] ?? [];
        $domainSums = [];
        $domainCounts = [];
        
        foreach ($facQuestions as $domain => $questions) {
            foreach ($questions as $qKey => $qText) {
                $avg = $facAverages[$qKey] ?? 0;
                if (!isset($domainSums[$domain])) {
                    $domainSums[$domain] = 0;
                    $domainCounts[$domain] = 0;
                }
                $domainSums[$domain] += $avg;
                $domainCounts[$domain]++;
            }
        }
        
        // Domain summary data
        foreach ($domainSums as $domain => $sum) {
            $avg = $domainCounts[$domain] > 0 ? $sum / $domainCounts[$domain] : 0;
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $domain);
            $sheet->setCellValue($col++ . $row, number_format($avg, 2));
            
            $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.5 ? 'Good' : 'Needs Improvement');
            $sheet->setCellValue($col++ . $row, $status);
            $sheet->setCellValue($col++ . $row, '3.50');
            $sheet->setCellValue($col++ . $row, number_format($avg - 3.5, 2));
            
            // Color coding
            $statusColor = $avg >= 4.0 ? $this->colors['accent_green'] : 
                         ($avg >= 3.5 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
            $sheet->getStyle('C' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            
            $row++;
        }
        
        $row += 2;
        
        // Detailed parameters section
        $sheet->setCellValue("A$row", "DETAILED PARAMETER ANALYSIS");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        // Detailed headers
        $headers = ['Domain', 'Parameter', 'Statement', 'Avg Rating', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;
        
        // Faculty feedback data rows with enhanced formatting
        foreach ($facQuestions as $domain => $questions) {
            foreach ($questions as $qKey => $qText) {
                $col = 'A';
                $sheet->setCellValue($col++ . $row, $domain);
                $sheet->setCellValue($col++ . $row, strtoupper($qKey));
                $sheet->setCellValue($col++ . $row, $qText);
                $avg = $facAverages[$qKey] ?? 0;
                $sheet->setCellValue($col++ . $row, number_format($avg, 2));
                
                $status = $avg >= 4.0 ? 'Excellent' : ($avg >= 3.5 ? 'Good' : 'Needs Improvement');
                $sheet->setCellValue($col++ . $row, $status);
                
                // Enhanced color coding
                $ratingColor = $avg >= 4.0 ? $this->colors['accent_green'] : 
                             ($avg >= 3.5 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                $sheet->getStyle('D' . $row)->getFont()->setBold(true)->setColor(new Color($ratingColor));
                $sheet->getStyle('E' . $row)->getFont()->setBold(true)->setColor(new Color($ratingColor));
                
                // Alternating row colors
                if (($row % 2) == 0) {
                    $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                
                $row++;
            }
        }
        
        // Apply enhanced styling
        $this->applyTableStyling($sheet, "A1:E" . ($row - 1));
        
        // Set optimal column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(15);
    }
    
    /**
     * Create Qualitative Feedback Sheet
     */
    private function createQualitativeFeedbackSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Qualitative Feedback");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        
        // Header
        $sheet->setCellValue("A$row", "QUALITATIVE FEEDBACK & REMARKS");
        $sheet->mergeCells("A" . $row . ":E" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row += 2;
        
        $hasData = false;
        
        // CES qualitative remarks
        if (!empty($data['ces_feedback']['remarks'])) {
            $hasData = true;
            $sheet->setCellValue("A$row", "COURSE END SURVEY (CES) REMARKS");
            $sheet->mergeCells("A" . $row . ":E" . $row);
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $row++;
            
            // Headers
            $headers = ['Student', 'Useful Aspects', 'Improvement Topics', 'Suggestions', 'Date'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $col++;
            }
            $row++;
            
            // Data rows
            foreach ($data['ces_feedback']['remarks'] as $remark) {
                $col = 'A';
                $sheet->setCellValue($col++ . $row, $remark['student_roll'] ?? 'Anonymous');
                $sheet->setCellValue($col++ . $row, $remark['useful_aspects']);
                $sheet->setCellValue($col++ . $row, $remark['improvement_topics']);
                $sheet->setCellValue($col++ . $row, $remark['suggestions']);
                $sheet->setCellValue($col++ . $row, $remark['submitted_at']);
                $row++;
            }
            
            $row++;
        }
        
        // Faculty qualitative remarks
        if (!empty($data['faculty_evaluations']['remarks'])) {
            $hasData = true;
            $sheet->setCellValue("A$row", "FACULTY EVALUATION REMARKS");
            $sheet->mergeCells("A" . $row . ":E" . $row);
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $row++;
            
            // Headers
            $headers = ['Student', 'Strengths', 'Improvement Areas', 'Additional Comments', 'Date'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $col++;
            }
            $row++;
            
            $facSno = 0;
            // Data rows
            foreach ($data['faculty_evaluations']['remarks'] as $remark) {
                $facSno++;
                $col = 'A';
                $sheet->setCellValue($col++ . $row, 'Student-' . $facSno);
                $sheet->setCellValue($col++ . $row, $remark['faculty_strengths']);
                $sheet->setCellValue($col++ . $row, $remark['improvement_areas']);
                $sheet->setCellValue($col++ . $row, $remark['additional_comments']);
                $sheet->setCellValue($col++ . $row, $remark['submitted_at']);
                $row++;
            }
        }
        
        if (!$hasData) {
            $sheet->setCellValue("A$row", "No qualitative feedback available for this selection.");
            $sheet->getStyle("A$row")->getFont()->setItalic(true);
        } else {
            // Apply styling
            $this->applyTableStyling($sheet, "A1:E" . ($row - 1));
        }
    }
    
    /**
     * Create Analytics Sheet with Charts
     */
    private function createAnalyticsSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Analytics & Charts");
        $this->spreadsheet->addSheet($sheet);
        
        $row = 1;
        
        // Header
        $sheet->setCellValue("A$row", "ANALYTICS & VISUALIZATIONS");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row += 2;
        
        // Add rating distribution analysis
        $this->addRatingDistributionAnalysis($sheet, $row, $data);
        $row += 10;
        
        // Add trend analysis placeholder
        $sheet->setCellValue("A$row", "TREND ANALYSIS (Multi-Year Comparison)");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $row++;
        
        $sheet->setCellValue("A$row", "Note: Multi-year trend analysis requires historical data comparison.");
        $sheet->getStyle("A$row")->getFont()->setItalic(true);
    }
    
    /**
     * Add rating distribution analysis
     */
    private function addRatingDistributionAnalysis($sheet, &$row, $data)
    {
        $sheet->setCellValue("A$row", "RATING DISTRIBUTION ANALYSIS");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $row++;
        
        // Calculate rating distribution
        $distribution = $this->calculateRatingDistribution($data);
        
        // Table headers
        $headers = ['Rating', 'Count', 'Percentage', 'Cumulative %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $col++;
        }
        $row++;
        
        // Distribution data
        $total = array_sum($distribution);
        $cumulative = 0;
        
        foreach ($distribution as $rating => $count) {
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
            $cumulative += $percentage;
            
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $rating . "★");
            $sheet->setCellValue($col++ . $row, $count);
            $sheet->setCellValue($col++ . $row, number_format($percentage, 1) . '%');
            $sheet->setCellValue($col++ . $row, number_format($cumulative, 1) . '%');
            
            $row++;
        }
    }
    
    /**
     * Calculate rating distribution
     */
    private function calculateRatingDistribution($data)
    {
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        
        $coFeedback = $data['co_feedback'] ?? [];
        foreach ($coFeedback as $co) {
            $distribution[5] += $co['count_5'] ?? 0;
            $distribution[4] += $co['count_4'] ?? 0;
            $distribution[3] += $co['count_3'] ?? 0;
            $distribution[2] += $co['count_2'] ?? 0;
            $distribution[1] += $co['count_1'] ?? 0;
        }
        
        return $distribution;
    }
    
    /**
     * Add benchmark comparison table
     */
    private function addBenchmarkComparison($sheet, &$row, $data, $level)
    {
        $sheet->setCellValue("A$row", "BENCHMARK COMPARISON");
        $sheet->mergeCells("A" . $row . ":H" . $row);
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        
        // Table headers
        $headers = ['Metric', 'Current', 'Benchmark', 'Gap', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;
        
        // Benchmark data
        $benchmarks = $this->getBenchmarkData($data, $level);
        foreach ($benchmarks as $bench) {
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $bench['metric']);
            $sheet->setCellValue($col++ . $row, $bench['current']);
            $sheet->setCellValue($col++ . $row, $bench['benchmark']);
            $sheet->setCellValue($col++ . $row, $bench['gap']);
            $sheet->setCellValue($col++ . $row, $bench['status']);
            
            // Status color coding
            $statusColor = $bench['status'] === 'Exceeds' ? $this->colors['accent_green'] : 
                          ($bench['status'] === 'Meets' ? $this->colors['accent_orange'] : $this->colors['accent_red']);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true)->setColor(new Color($statusColor));
            
            $row++;
        }
    }
    
    /**
     * Get benchmark data
     */
    private function getBenchmarkData($data, $level)
    {
        $benchmarks = [];
        $overallAvg = $data['summary']['overall_avg'] ?? ($data['summary']['overall_co_avg'] ?? 0);
        $responseRate = $data['response_rate'] ?? 0;
        
        $ratingGap = $overallAvg - 3.5;
        $ratingStatus = $overallAvg >= 3.5 ? 'Exceeds' : ($overallAvg >= 3.0 ? 'Meets' : 'Below');
        
        $benchmarks[] = [
            'metric' => 'Overall Rating',
            'current' => number_format($overallAvg, 2),
            'benchmark' => '3.50',
            'gap' => number_format($ratingGap, 2),
            'status' => $ratingStatus
        ];
        
        $responseGap = $responseRate - 75;
        $responseStatus = $responseRate >= 75 ? 'Exceeds' : ($responseRate >= 60 ? 'Meets' : 'Below');
        
        $benchmarks[] = [
            'metric' => 'Response Rate',
            'current' => number_format($responseRate, 1) . '%',
            'benchmark' => '75%',
            'gap' => number_format($responseGap, 1) . '%',
            'status' => $responseStatus
        ];
        
        return $benchmarks;
    }
    
    /**
     * Apply executive summary styling
     */
    private function applyExecutiveSummaryStyling($sheet)
    {
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(20);
        
        // Apply borders to all data sections
        $sheet->getStyle('A1:H40')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Set row heights for headers
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(3)->setRowHeight(20);
    }
    
    /**
     * Create Dedicated Student CO Ratings Sheet (Pivoted Grid + Mapping Legend)
     */
    private function createStudentCORatingsSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Raw - CO Responses");
        $this->spreadsheet->addSheet($sheet);
        $sheet->setShowGridLines(true);

        $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
        $classId = intval($data['meta']['class_id'] ?? ($data['meta']['cls_id'] ?? 0));
        $coStudentData = $this->feedbackService->getSubjectStudentWiseRatings($subjectId, $classId);

        $cos = $coStudentData['cos'] ?? [];
        $students = $coStudentData['students'] ?? [];
        $coCount = count($cos);

        $totalCols = max(6, 4 + $coCount);
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        $row = 1;
        // Title Block
        $sheet->setCellValue("A$row", "STUDENT COURSE OUTCOME (CO) RATINGS MATRIX");
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(13)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $row += 2;

        // CO Mapping Legend
        $sheet->setCellValue("A$row", "COURSE OUTCOMES (CO) MAPPING LEGEND");
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $row++;

        $sheet->setCellValue("A$row", "CO Code");
        $sheet->getStyle("A$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue("B$row", "Course Outcome Description");
        $sheet->mergeCells("B{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("B$row")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
        $legendHeaderRow = $row;
        $row++;

        foreach ($cos as $co) {
            $sheet->setCellValue("A$row", $co['co_label'] ?? ('CO' . ($co['co_number'] ?? '')));
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue("B$row", $co['co_description'] ?? '');
            $sheet->mergeCells("B{$row}:{$lastColLetter}{$row}");
            $sheet->getStyle("B$row")->getAlignment()->setWrapText(true);
            $row++;
        }
        $this->applyTableStyling($sheet, "A{$legendHeaderRow}:{$lastColLetter}" . ($row - 1));
        $row += 2;

        // Student Responses Table
        $matrixHeaderRow = $row;
        $colIdx = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, "Roll No");
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, "Student Name");
        foreach ($cos as $co) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, $co['co_label'] ?? ('CO' . ($co['co_number'] ?? '')));
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, "Student Avg");
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, "Submitted Date");

        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        $dataStartRow = $row;
        if (empty($students)) {
            $sheet->setCellValue("A$row", "No student CO responses submitted for this course.");
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->getStyle("A$row")->getFont()->setItalic(true);
            $row++;
        } else {
            foreach ($students as $stu) {
                $colIdx = 1;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, $stu['student_roll']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row, $stu['student_name']);

                foreach ($cos as $coId => $coInfo) {
                    $rVal = $stu['ratings'][$coId] ?? null;
                    $cellColLetter = Coordinate::stringFromColumnIndex($colIdx++);
                    if ($rVal !== null && $rVal !== '') {
                        $sheet->setCellValue($cellColLetter . $row, intval($rVal));
                        $rColor = $rVal >= 4 ? $this->colors['accent_green'] : ($rVal >= 3 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                        $sheet->getStyle($cellColLetter . $row)->getFont()->setBold(true)->setColor(new Color($rColor));
                    } else {
                        $sheet->setCellValue($cellColLetter . $row, '-');
                    }
                    $sheet->getStyle($cellColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $avgColLetter = Coordinate::stringFromColumnIndex($colIdx++);
                $sheet->setCellValue($avgColLetter . $row, number_format(floatval($stu['average'] ?? 0), 2));
                $sheet->getStyle($avgColLetter . $row)->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle($avgColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $dateColLetter = Coordinate::stringFromColumnIndex($colIdx++);
                $sheet->setCellValue($dateColLetter . $row, !empty($stu['feedback_date']) ? date('Y-m-d', strtotime($stu['feedback_date'])) : 'N/A');
                $sheet->getStyle($dateColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if (($row % 2) == 0) {
                    $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                $row++;
            }

            // Summary Average Row
            $sheet->setCellValue("A$row", "AVERAGE RATING");
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $colIdx = 3;
            foreach ($cos as $coId => $coInfo) {
                $coLetter = Coordinate::stringFromColumnIndex($colIdx++);
                $sheet->setCellValue($coLetter . $row, "=AVERAGE({$coLetter}{$dataStartRow}:{$coLetter}" . ($row - 1) . ")");
                $sheet->getStyle($coLetter . $row)->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle($coLetter . $row)->getFont()->setBold(true);
                $sheet->getStyle($coLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $overallAvgColLetter = Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValue($overallAvgColLetter . $row, "=AVERAGE({$overallAvgColLetter}{$dataStartRow}:{$overallAvgColLetter}" . ($row - 1) . ")");
            $sheet->getStyle($overallAvgColLetter . $row)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle($overallAvgColLetter . $row)->getFont()->setBold(true);
            $sheet->getStyle($overallAvgColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateColLetter = Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValue($dateColLetter . $row, "");

            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $row++;
        }

        $this->applyTableStyling($sheet, "A{$matrixHeaderRow}:{$lastColLetter}" . ($row - 1));
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(26);
        for ($c = 3; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(13);
        }
    }

    /**
     * Create Dedicated Student CES Ratings Sheet (Q1 - Q16 Isolated)
     */
    private function createStudentCESRatingsSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Raw - CES Responses");
        $this->spreadsheet->addSheet($sheet);
        $sheet->setShowGridLines(true);

        $row = 1;
        $sheet->setCellValue("A$row", "COURSE END SURVEY (CES) - STUDENT RESPONSES (16 PARAMETERS)");
        $sheet->mergeCells("A{$row}:S{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(13)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $row += 2;

        // CES Questions Legend
        $sheet->setCellValue("A$row", "CES QUESTION STATEMENTS REFERENCE");
        $sheet->mergeCells("A{$row}:S{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $row++;

        $cesQuestionsGrouped = FeedbackService::getCesQuestions();
        $legendStartRow = $row;
        foreach ($cesQuestionsGrouped as $domain => $questions) {
            $sheet->setCellValue("A$row", $domain);
            $sheet->mergeCells("A{$row}:S{$row}");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setItalic(true);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $row++;
            foreach ($questions as $qKey => $qText) {
                $sheet->setCellValue("A$row", strtoupper(str_replace('ces_', '', $qKey)));
                $sheet->getStyle("A$row")->getFont()->setBold(true);
                $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue("B$row", $qText);
                $sheet->mergeCells("B{$row}:S{$row}");
                $row++;
            }
        }
        $this->applyTableStyling($sheet, "A{$legendStartRow}:S" . ($row - 1));
        $row += 2;

        // Responses Table
        $tableHeaderRow = $row;
        $cesHeaders = ['Student Roll', 'Student Name'];
        for ($qi = 1; $qi <= 16; $qi++) {
            $cesHeaders[] = "Q$qi";
        }
        $cesHeaders[] = 'Submitted Date';

        $col = 'A';
        foreach ($cesHeaders as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        $dataStartRow = $row;
        $cesRatings = $this->getStudentCESRatings($level, $data);
        if (empty($cesRatings)) {
            $sheet->setCellValue("A$row", "No CES responses submitted for this course.");
            $sheet->mergeCells("A{$row}:S{$row}");
            $sheet->getStyle("A$row")->getFont()->setItalic(true);
            $row++;
        } else {
            foreach ($cesRatings as $rating) {
                $col = 'A';
                $sheet->setCellValue($col++ . $row, $rating['student_roll']);
                $sheet->setCellValue($col++ . $row, $rating['student_name']);
                for ($qi = 1; $qi <= 16; $qi++) {
                    $val = intval($rating["q$qi"] ?? 0);
                    $qColLetter = $col++;
                    $sheet->setCellValue($qColLetter . $row, $val);
                    $rColor = $val >= 4 ? $this->colors['accent_green'] : ($val >= 3 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                    $sheet->getStyle($qColLetter . $row)->getFont()->setBold(true)->setColor(new Color($rColor));
                    $sheet->getStyle($qColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $dateColLetter = $col++;
                $sheet->setCellValue($dateColLetter . $row, $rating['response_date']);
                $sheet->getStyle($dateColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if (($row % 2) == 0) {
                    $sheet->getStyle("A{$row}:S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                $row++;
            }

            // Average Row
            $sheet->setCellValue("A$row", "AVERAGE RATING");
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($qi = 1; $qi <= 16; $qi++) {
                $qCol = Coordinate::stringFromColumnIndex(2 + $qi);
                $sheet->setCellValue($qCol . $row, "=AVERAGE({$qCol}{$dataStartRow}:{$qCol}" . ($row - 1) . ")");
                $sheet->getStyle($qCol . $row)->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle($qCol . $row)->getFont()->setBold(true);
                $sheet->getStyle($qCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->setCellValue("S$row", "");
            $sheet->getStyle("A{$row}:S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $row++;
        }

        $this->applyTableStyling($sheet, "A{$tableHeaderRow}:S" . ($row - 1));
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(26);
        for ($qi = 1; $qi <= 16; $qi++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $qi))->setWidth(8);
        }
        $sheet->getColumnDimension('S')->setWidth(14);
    }

    /**
     * Create Dedicated Student Faculty Appraisal Sheet (Q1 - Q19 Isolated)
     */
    private function createStudentFacultyRatingsSheet($data, $level)
    {
        $sheet = new Worksheet($this->spreadsheet, "Raw - Faculty Appraisal");
        $this->spreadsheet->addSheet($sheet);
        $sheet->setShowGridLines(true);

        $row = 1;
        $sheet->setCellValue("A$row", "FACULTY APPRAISAL - STUDENT RESPONSES (19 OBE PARAMETERS)");
        $sheet->mergeCells("A{$row}:V{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(13)->setColor(new Color($this->colors['header_text']));
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['header_bg']);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $row += 2;

        // Faculty Appraisal Questions Legend
        $sheet->setCellValue("A$row", "FACULTY EVALUATION PARAMETERS REFERENCE");
        $sheet->mergeCells("A{$row}:V{$row}");
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
        $row++;

        $facQuestionsGrouped = FeedbackService::getFacultyQuestions();
        $legendStartRow = $row;
        foreach ($facQuestionsGrouped as $domain => $questions) {
            $sheet->setCellValue("A$row", $domain);
            $sheet->mergeCells("A{$row}:V{$row}");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setItalic(true);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
            $row++;
            foreach ($questions as $qKey => $qText) {
                $sheet->setCellValue("A$row", strtoupper(str_replace('fac_', '', $qKey)));
                $sheet->getStyle("A$row")->getFont()->setBold(true);
                $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue("B$row", $qText);
                $sheet->mergeCells("B{$row}:V{$row}");
                $row++;
            }
        }
        $this->applyTableStyling($sheet, "A{$legendStartRow}:V" . ($row - 1));
        $row += 2;

        // Responses Table
        $tableHeaderRow = $row;
        $facHeaders = ['Student'];
        for ($qi = 1; $qi <= 19; $qi++) {
            $facHeaders[] = "Q$qi";
        }
        $facHeaders[] = 'Submitted Date';

        $col = 'A';
        foreach ($facHeaders as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        $dataStartRow = $row;
        $facRatings = $this->getStudentFacultyRatings($level, $data);
        if (empty($facRatings)) {
            $sheet->setCellValue("A$row", "No faculty evaluations submitted for this selection.");
            $sheet->mergeCells("A{$row}:V{$row}");
            $sheet->getStyle("A$row")->getFont()->setItalic(true);
            $row++;
        } else {
            $facSno = 0;
            foreach ($facRatings as $rating) {
                $col = 'A';
                $sheet->setCellValue($col++ . $row, "Student-" . ++$facSno);
                for ($qi = 1; $qi <= 19; $qi++) {
                    $val = intval($rating["q$qi"] ?? 0);
                    $qColLetter = $col++;
                    $sheet->setCellValue($qColLetter . $row, $val);
                    $rColor = $val >= 4 ? $this->colors['accent_green'] : ($val >= 3 ? $this->colors['accent_orange'] : $this->colors['accent_red']);
                    $sheet->getStyle($qColLetter . $row)->getFont()->setBold(true)->setColor(new Color($rColor));
                    $sheet->getStyle($qColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $dateColLetter = $col++;
                $sheet->setCellValue($dateColLetter . $row, $rating['response_date']);
                $sheet->getStyle($dateColLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if (($row % 2) == 0) {
                    $sheet->getStyle("A{$row}:V{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['neutral_gray']);
                }
                $row++;
            }

            // Average Row
            $sheet->setCellValue("A$row", "AVERAGE RATING");
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($qi = 1; $qi <= 19; $qi++) {
                $qCol = Coordinate::stringFromColumnIndex(1 + $qi);
                $sheet->setCellValue($qCol . $row, "=AVERAGE({$qCol}{$dataStartRow}:{$qCol}" . ($row - 1) . ")");
                $sheet->getStyle($qCol . $row)->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle($qCol . $row)->getFont()->setBold(true);
                $sheet->getStyle($qCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->setCellValue("V$row", "");
            $sheet->getStyle("A{$row}:V{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->colors['subheader_bg']);
            $row++;
        }

        $this->applyTableStyling($sheet, "A{$tableHeaderRow}:V" . ($row - 1));
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(26);
        for ($qi = 1; $qi <= 19; $qi++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $qi))->setWidth(8);
        }
        $sheet->getColumnDimension('V')->setWidth(14);
    }
    
    /**
     * Get student CES ratings
     */
    private function getStudentCESRatings($level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            
            if ($subjectId > 0) {
                $query = "SELECT 
                          sces.is_anonymous,
                          CASE WHEN sces.is_anonymous = 1 THEN 'Anonymous' ELSE s.username END AS roll_number,
                          CASE WHEN sces.is_anonymous = 1 THEN 'Anonymous' ELSE COALESCE(u.name, s.username) END AS name,
                          sces.ces_q1, sces.ces_q2, sces.ces_q3, sces.ces_q4, sces.ces_q5, sces.ces_q6, sces.ces_q7,
                          sces.ces_q8, sces.ces_q9, sces.ces_q10, sces.ces_q11, sces.ces_q12, sces.ces_q13, sces.ces_q14, sces.ces_q15, sces.ces_q16,
                          sces.submitted_at
                          FROM student_ces_feedback sces
                          JOIN students s ON sces.student_id = s.id
                          LEFT JOIN users u ON s.username = u.username
                          WHERE sces.subject_id = ?
                          ORDER BY sces.is_anonymous ASC, s.username ASC, sces.submitted_at DESC";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $subjectId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $item = [
                        'student_roll' => $row['roll_number'],
                        'student_name' => $row['name'],
                        'response_date' => !empty($row['submitted_at']) ? date('Y-m-d', strtotime($row['submitted_at'])) : 'N/A'
                    ];
                    for ($qi = 1; $qi <= 16; $qi++) {
                        $item["q$qi"] = $row["ces_q$qi"] ?? 0;
                    }
                    $ratings[] = $item;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
        
        return $ratings;
    }
    
    /**
     * Get student faculty ratings
     */
    private function getStudentFacultyRatings($level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            $facultyId = intval($data['faculty']['id'] ?? ($data['meta']['faculty_id'] ?? 0));
            
            // If viewing subject level, retrieve faculty from mapped faculties list if not yet set
            if ($facultyId <= 0 && !empty($data['meta']['faculties'])) {
                $facultyId = intval($data['meta']['faculties'][0]['faculty_id'] ?? 0);
            }

            $query = "SELECT 
                      sff.is_anonymous,
                      CASE WHEN sff.is_anonymous = 1 THEN 'Anonymous' ELSE s.username END AS roll_number, 
                      CASE WHEN sff.is_anonymous = 1 THEN 'Anonymous' ELSE COALESCE(u.name, s.username) END AS name,
                      sff.fac_q1, sff.fac_q2, sff.fac_q3, sff.fac_q4, sff.fac_q5, sff.fac_q6, sff.fac_q7,
                      sff.fac_q8, sff.fac_q9, sff.fac_q10, sff.fac_q11, sff.fac_q12, sff.fac_q13, sff.fac_q14, 
                      sff.fac_q15, sff.fac_q16, sff.fac_q17, sff.fac_q18, sff.fac_q19,
                      sff.submitted_at
                      FROM student_faculty_feedback sff
                      JOIN students s ON sff.student_id = s.id
                      LEFT JOIN users u ON s.username = u.username
                      WHERE 1=1";

            $params = [];
            $types = "";

            if ($subjectId > 0) {
                $query .= " AND sff.subject_id = ?";
                $params[] = $subjectId;
                $types .= "i";
            }
            if ($facultyId > 0) {
                $query .= " AND sff.faculty_id = ?";
                $params[] = $facultyId;
                $types .= "i";
            }
            $query .= " ORDER BY sff.is_anonymous ASC, s.username ASC, sff.submitted_at DESC";

            $stmt = $this->conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $item = [
                    'student_roll' => $row['roll_number'],
                    'student_name' => $row['name'],
                    'response_date' => !empty($row['submitted_at']) ? date('Y-m-d', strtotime($row['submitted_at'])) : 'N/A'
                ];
                for ($qi = 1; $qi <= 19; $qi++) {
                    $item["q$qi"] = intval($row["fac_q$qi"] ?? 0);
                }
                $ratings[] = $item;
            }
            $stmt->close();
        } catch (Exception $e) {
            // Logging
        }
        
        return $ratings;
    }
    
    /**
     * Apply table styling
     */
    private function applyTableStyling($sheet, $range)
    {
        // Borders
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Alignment
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Auto-size columns
        if (preg_match('/^([A-Z]+)\d*:([A-Z]+)\d*$/i', $range, $m)) {
            $startCol = strtoupper($m[1]);
            $endCol = strtoupper($m[2]);
            foreach (range($startCol, $endCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        // Row height
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
    }
    
    /**
     * Generate filename
     */
    private function generateFilename($level, $params, $format)
    {
        $timestamp = date("Ymd_His");
        $identifier = '';
        
        switch ($level) {
            case 'subject':
                $identifier = "subject_" . ($params['sub_id'] ?? 'unknown');
                break;
            case 'class':
                $identifier = "class_" . ($params['cls_id'] ?? 'unknown');
                break;
            case 'faculty':
                $identifier = "faculty_" . ($params['fac_id'] ?? 'unknown');
                break;
            case 'department':
                $identifier = "department_" . ($params['dept_id'] ?? 'unknown');
                break;
        }
        
        return "feedback_{$identifier}_{$timestamp}.{$format}";
    }
    
    /**
     * Save file
     */
    private function saveFile($filename, $format)
    {
        $filepath = __DIR__ . '/../uploads/' . $filename;
        
        if ($format === 'xlsx') {
            $writer = new Xlsx($this->spreadsheet);
        } else {
            $writer = new Xls($this->spreadsheet);
        }
        
        $writer->save($filepath);
        
        // Clean up
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
        
        return $filepath;
    }
    
    /**
     * Clean up resources
     */
    public function __destruct()
    {
        if (isset($this->spreadsheet)) {
            $this->spreadsheet->disconnectWorksheets();
        }
        // Don't close connection as it's managed by DBCredentials singleton
    }
}
?>
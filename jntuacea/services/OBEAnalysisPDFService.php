<?php
/**
 * OBE & Assessment Analytics Dossier Service
 * 
 * Generates an audit-compliant, 9-page OBE & Assessment Analytics Dossier:
 * Page 1: CIA-1 Question Paper Analysis
 * Page 2: CIA-2 Question Paper Analysis
 * Page 3: Consolidated CIA QP Analysis
 * Page 4: CIA & Learning Analytics Report
 * Page 5: SEE Question Paper Analysis
 * Page 6: SEE Marks & Grade Distribution Summary (O, S, A, B, C, F)
 * Page 7: SEE Result Analysis (Attainment levels based on 60% cohort benchmark)
 * Page 8: Combined Direct Attainment Analysis (Formula: 30% CIA + 70% SEE)
 * Page 9: Comprehensive CO & PO Attainment Matrix (Direct + 20% Indirect Feedback)
 */

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../faculty.class.php';
require_once __DIR__ . '/../cia.class.php';
require_once __DIR__ . '/../seeassessment.class.php';
require_once __DIR__ . '/../coattainment.class.php';
require_once __DIR__ . '/../learninganalytics.class.php';
require_once __DIR__ . '/../feedbackservice.class.php';
require_once __DIR__ . '/SettingsService.php';

class OBEAnalysisPDFService
{
    private $conn;
    private $facultyObj;
    private $ciaObj;
    private $seeObj;
    private $coEngine;
    private $laEngine;
    private $feedbackService;
    private $settingsService;

    // Professional color palette matching institutional theme
    private $colors = [
        'primary' => '#1a365d',
        'secondary' => '#2c5282',
        'accent' => '#2b6cb0',
        'success' => '#198754',
        'warning' => '#d97706',
        'danger' => '#dc3545',
        'light' => '#f8f9fa',
        'border' => '#dee2e6',
        'header_bg' => '#f2f2f2',
        'muted' => '#6c757d'
    ];

    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        $this->facultyObj = new Faculty();
        $this->ciaObj = new CIA();
        $this->seeObj = new SEEAssessment();
        $this->coEngine = new COAttainmentEngine();
        $this->laEngine = new LearningAnalytics();
        $this->feedbackService = new FeedbackService();
        $this->settingsService = \Services\SettingsService::getInstance();
    }

    private function reportDirectory(): string
    {
        $directory=sys_get_temp_dir().'/cea-obe-reports';
        if(!is_dir($directory)&&!mkdir($directory,0700,true)){throw new \RuntimeException('Unable to create private report storage.');}
        if(!is_writable($directory)){throw new \RuntimeException('Private report storage is not writable.');}
        return $directory;
    }

    private function requiredPolicySetting(string $key, string $regulation): float
    {
        $value=$this->settingsService->get($key,$regulation,null);
        if(!is_numeric($value)||!is_finite((float)$value)){throw new \DomainException('Configure and review '.$key.' for the exact programme/regulation before generating this dossier.');}
        return (float)$value;
    }

    /**
     * Main entry point to generate the progressive OBE Analysis Dossier PDF
     * - Mid-Semester (CIA Stage): Generates 5 Pages (CIA-1, CIA-2, Consolidated CIA, Learning Analytics, Zero Marks)
     * - End-of-Semester (Complete): Generates 10 Pages (Full CIA + SEE analysis + Attainment matrices)
     * 
     * @param int|array $courseData Subject ID or array containing course metadata
     * @param array|null $feedbackData Optional external feedback data
     * @param array $options Optional generation settings
     * @return string Absolute file path to the generated PDF
     */
    public function generateOBEAnalysisReport($courseData, $feedbackData = null, array $options = [])
    {
        $sub_id = is_array($courseData) ? intval($courseData['sub_id'] ?? $courseData['id'] ?? 0) : intval($courseData);
        if ($sub_id <= 0) {
            throw new InvalidArgumentException("Invalid subject ID provided for OBE Analysis Report.");
        }

        // Check whether SEE marks are submitted for this course (enables progressive generation)
        $hasSee = $this->seeObj->isSEEMarksSubmitted($sub_id);

        // Fetch course and faculty details
        $facid = $options['facid'] ?? null;
        if (!empty($facid)) {
            $details = $this->facultyObj->getClassSubjectFacultyDetails($sub_id, $facid);
        } else {
            require_once __DIR__ . '/../hod.class.php';
            $hodObj = new HOD();
            $details = $hodObj->getClsSubFacBySubID($sub_id);
        }

        $meta = $this->extractCourseMeta($details, $sub_id);
        $this->facultyObj->assertSubjectScope($sub_id);
        $regulation = $this->facultyObj->getRegulationIdForSubject($sub_id);

        // Academic settings & weights (required for SEE direct & indirect attainment when SEE marks are submitted)
        $w_cia = 0.3;
        $w_see = 0.7;
        $w_direct = 0.8;
        $w_indirect = 0.2;
        $targetThreshold = 60.0;
        if ($hasSee) {
            $w_cia = $this->requiredPolicySetting('attainment_direct_cia_weight', $regulation);
            $w_see = $this->requiredPolicySetting('attainment_direct_see_weight', $regulation);
            $w_direct = $this->requiredPolicySetting('overall_direct_weight', $regulation);
            $w_indirect = $this->requiredPolicySetting('overall_indirect_weight', $regulation);
            $targetThreshold = $this->requiredPolicySetting('co_target_percentage', $regulation);
        }

        // Initialize mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 36,
            'margin_bottom' => 12,
            'default_font_size' => 8,
            'default_font' => 'helvetica',
            'tempDir' => $this->reportDirectory()
        ]);

        $footerText = $hasSee
            ? 'Official accreditation analysis dossier. Recorded evidence and configured policy require authorised review. Page {PAGENO} of {nbpg}.'
            : 'Provisional CIA analysis dossier (Semester End Examination pending). Recorded evidence requires authorised review. Page {PAGENO} of {nbpg}.';
        $mpdf->SetHTMLFooter('<div style="font-size:8px; color:#555;">' . $footerText . '</div>');

        $headerFn = function($sectionTitle) use ($meta, $hasSee) {
            $statusBadge = $hasSee ? '' : '<br><span style="font-size:8px; color:#b45309; font-weight:normal;">[Provisional CIA &mdash; SEE Pending]</span>';
            return '
            <table cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                <tr>
                    <td style="text-align:left; width:28%; font-size:10px; line-height:14px; padding-bottom:0px; word-wrap:break-word;">
                        <strong>Class:</strong> ' . htmlspecialchars($meta['class']) . '<br>
                        <strong>Semester:</strong> ' . htmlspecialchars($meta['semester']) . '<br>
                        <strong>Branch:</strong> ' . htmlspecialchars($meta['branch']) . '
                    </td>
                    <td style="text-align:center; width:44%; font-size:15px; padding-bottom:0px; font-weight:bold; color:#1a365d; word-wrap:break-word;">
                        ' . htmlspecialchars($sectionTitle) . $statusBadge . '
                    </td>
                    <td style="text-align:right; width:28%; font-size:10px; line-height:14px; padding-bottom:0px; word-wrap:break-word;">
                        <strong>Acad. Year:</strong> ' . htmlspecialchars($meta['acad_year']) . '<br>
                        <strong>Starting:</strong> ' . htmlspecialchars($meta['start_date']) . '<br>
                        <strong>Ending:</strong> ' . htmlspecialchars($meta['end_date']) . '
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:left; font-size:10px; line-height:14px; padding-top:2px; word-wrap:break-word;">
                        <strong>Subject:</strong> ' . htmlspecialchars($meta['subject']) . ' (' . htmlspecialchars($meta['subcode']) . ')
                    </td>
                    <td style="text-align:right; font-size:10px; line-height:14px; padding-top:2px; word-wrap:break-word;">
                        <strong>Teacher:</strong> ' . htmlspecialchars($meta['faculty']) . '
                    </td>
                </tr>
            </table>
            <hr style="margin:2px 0 0 0; padding:0; border:0; border-top:1px solid #000;"><br>';
        };

        // Shared Bloom's taxonomy definitions
        $bloomNames = [
            1 => 'Remember (L1)',
            2 => 'Understand (L2)',
            3 => 'Apply (L3)',
            4 => 'Analyze (L4)',
            5 => 'Evaluate (L5)',
            6 => 'Create (L6)'
        ];

        // ==========================================
        // PAGE 1: CIA-1 Question Paper Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA Question Paper Analysis'), 0);
        $cia1Data = $this->getCIAQuestionData($sub_id, 1, $bloomNames);
        $htmlP1 = $this->renderCIAQPPageHtml(1, $cia1Data);
        $mpdf->WriteHTML($htmlP1);

        // ==========================================
        // PAGE 2: CIA-2 Question Paper Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA Question Paper Analysis'), 0);
        $mpdf->AddPage();
        $cia2Data = $this->getCIAQuestionData($sub_id, 2, $bloomNames);
        $htmlP2 = $this->renderCIAQPPageHtml(2, $cia2Data);
        $mpdf->WriteHTML($htmlP2);

        // ==========================================
        // PAGE 3: Consolidated CIA QP Matrix
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Consolidated CIA QP Analysis'), 0);
        $mpdf->AddPage();
        $htmlP3 = $this->renderConsolidatedCIAHtml($sub_id, $cia1Data, $cia2Data, $bloomNames);
        $mpdf->WriteHTML($htmlP3);

        // ==========================================
        // PAGE 4: CIA & Learning Analytics Report
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA & Learning Analytics Report'), 0);
        $mpdf->AddPage();
        $htmlP4 = $this->renderLearningAnalyticsHtml($sub_id);
        $mpdf->WriteHTML($htmlP4);

        // ==========================================
        // PAGE 5: CIA Zero Marks Diagnostic Report
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA Zero Marks Diagnostic Report'), 0);
        $mpdf->AddPage();
        require_once __DIR__ . '/../facciaanalysis2.class.php';
        $analysisObj = new \COAnalysis();
        $zeroJson = $analysisObj->getZeroMarksAnalysis($sub_id, 'all');
        $zeroData = json_decode($zeroJson, true) ?? [];
        $htmlP5 = $this->renderZeroMarksHtml($meta, $zeroData);
        $mpdf->WriteHTML($htmlP5);

        // If SEE marks are submitted, generate Pages 6 to 10
        if ($hasSee) {
            // ==========================================
            // PAGE 6: SEE Question Paper Analysis
            // ==========================================
            $mpdf->SetHTMLHeader($headerFn('SEE Question Paper Analysis'), 0);
            $mpdf->AddPage();
            $htmlP6 = $this->renderSEEQPAnalysisHtml($sub_id, $bloomNames);
            $mpdf->WriteHTML($htmlP6);

            // ==========================================
            // PAGE 7: SEE Marks & Grade Distribution
            // ==========================================
            $mpdf->SetHTMLHeader($headerFn('SEE Marks & Grade Distribution'), 0);
            $mpdf->AddPage();
            $htmlP7 = $this->renderSEEGradeDistributionHtml($sub_id);
            $mpdf->WriteHTML($htmlP7);

            // ==========================================
            // PAGE 8: SEE Result Analysis
            // ==========================================
            $mpdf->SetHTMLHeader($headerFn('SEE Result Analysis'), 0);
            $mpdf->AddPage();
            $htmlP8 = $this->renderSEEResultAnalysisHtml($sub_id);
            $mpdf->WriteHTML($htmlP8);

            // ==========================================
            // PAGE 9: Combined Direct Attainment Analysis
            // ==========================================
            $mpdf->SetHTMLHeader($headerFn('Combined Direct Attainment Analysis'), 0);
            $mpdf->AddPage();
            $htmlP8 = $this->renderCombinedDirectAttainmentHtml($sub_id, $w_cia, $w_see);
            $mpdf->WriteHTML($htmlP8);

            // ==========================================
            // PAGE 10: OBE Outcome Attainment Matrix
            // ==========================================
            $mpdf->SetHTMLHeader($headerFn('OBE Outcome Attainment Matrix'), 0);
            $mpdf->AddPage();
            $htmlP9 = $this->renderOBEMatrixHtml($sub_id, $feedbackData, $w_cia, $w_see, $w_direct, $w_indirect, $targetThreshold);
            $mpdf->WriteHTML($htmlP9);
        }

        // Save PDF
        $filename = 'obe_analysis_report_' . $sub_id . '_' . date("Ymd_His") . '.pdf';
        $outDir = $this->reportDirectory();
        $filepath = $outDir . '/' . bin2hex(random_bytes(16)) . '_' . $filename;
        $mpdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Extract metadata from course and class details
     */
    private function extractCourseMeta($details, $sub_id)
    {
        $class_full = $details['data']['classname'] ?? '';
        $subject = $details['data']['sub_fullname'] ?? '';
        $faculty = $details['data']['faculty_name'] ?? 'N/A';
        $acadYear = $details['data']['acad_year'] ?? date('Y') . '-' . (date('Y') + 1);
        $subCode = $details['data']['subcode'] ?? '';

        if (empty($subCode) || empty($subject)) {
            $sStmt = $this->conn->prepare("SELECT subcode, sub_fullname FROM subjects WHERE id = ?");
            if ($sStmt) {
                $sStmt->bind_param("i", $sub_id);
                if ($sStmt->execute()) {
                    $sStmt->bind_result($dbSubcode, $dbSubname);
                    if ($sStmt->fetch()) {
                        if (empty($subCode)) $subCode = $dbSubcode;
                        if (empty($subject)) $subject = $dbSubname;
                    }
                }
                $sStmt->close();
            }
        }

        $regulation = 'R23';
        if ($rStmt = $this->conn->prepare("SELECT r.regulation FROM subjects s JOIN classes c ON s.class_id = c.id JOIN regulations r ON c.reg_id = r.id WHERE s.id = ?")) {
            $rStmt->bind_param("i", $sub_id);
            if ($rStmt->execute()) {
                $rStmt->bind_result($foundReg);
                if ($rStmt->fetch() && !empty($foundReg)) {
                    $regulation = strtoupper(trim($foundReg));
                }
            }
            $rStmt->close();
        }

        // Parse branch and class parts
        $class_parts = explode(' - ', $class_full);
        $semester = trim($class_parts[2] ?? '');
        preg_match_all('/\(([^)]+)\)/', $class_parts[0] ?? '', $matches);
        $branch = '';
        if (count($matches[1]) === 1) {
            $branch = $matches[1][0];
        } elseif (count($matches[1]) >= 2) {
            $last = array_pop($matches[1]);
            $secondLast = array_pop($matches[1]);
            $branch = $secondLast . ' (' . $last . ')';
        }

        $pos = strpos($class_parts[0] ?? '', '(');
        $class = ($pos !== false) ? trim(substr($class_parts[0], 0, $pos)) : ($class_parts[0] ?? '');
        if (isset($class_parts[1])) {
            $class .= ' - ' . trim($class_parts[1]);
        }

        // Attendance date range
        $attData = $this->facultyObj->getDetailedAttendanceBySubject($sub_id, null, null);
        $startDate = 'N/A';
        $endDate = 'N/A';
        if (!empty($attData['data'])) {
            $dateHours = array_keys($attData['data'][0]['attendance']);
            usort($dateHours, function ($a, $b) {
                return strtotime($a) - strtotime($b);
            });
            if (!empty($dateHours)) {
                $first = explode('-', $dateHours[0]);
                $last = explode('-', end($dateHours));
                $startDate = date('d-m-Y', strtotime($first[0] . '-' . $first[1] . '-' . $first[2]));
                $endDate = date('d-m-Y', strtotime($last[0] . '-' . $last[1] . '-' . $last[2]));
            }
        }

        return [
            'class' => $class,
            'semester' => $semester,
            'branch' => $branch,
            'subject' => $subject,
            'subcode' => $subCode,
            'faculty' => $faculty,
            'acad_year' => $acadYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'regulation' => $regulation,
            'dept_name' => !empty($branch) ? $branch : ($details['data']['dept_name'] ?? 'Engineering')
        ];
    }

    /**
     * Retrieve question and Bloom's data for a specific CIA mid examination
     */
    private function getCIAQuestionData($sub_id, $mid, array $bloomNames)
    {
        $midComps = $this->ciaObj->getAssessmentComponents($sub_id, $mid);
        $questions = [];
        $btlDist = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];
        $coDist = [];
        $totalMarks = 0.0;
        $componentSummary = [];

        if (!empty($midComps['data'])) {
            foreach ($midComps['data'] as $c) {
                $compName = $c['component_type'] . (count($midComps['data']) > 1 ? ' #' . $c['sequence_number'] : '');
                $qs = $this->ciaObj->getQuestionsByComponent($c['id']);
                $cMarks = 0.0;
                $cCount = 0;

                if (!empty($qs)) {
                    foreach ($qs as $q) {
                        $cos = $this->ciaObj->getCOsByQuestion($q['id']);
                        $btl = intval($q['blooms_level_id']);
                        $marks = floatval($q['marks']);
                        $totalMarks += $marks;
                        $cMarks += $marks;
                        $cCount++;

                        if (isset($btlDist[$btl])) {
                            $btlDist[$btl] += $marks;
                        }

                        $coWeight = (count($cos) > 0) ? ($marks / count($cos)) : $marks;
                        foreach ($cos as $coLabel) {
                            $coDist[$coLabel] = ($coDist[$coLabel] ?? 0.0) + $coWeight;
                        }

                        $questions[] = [
                            'comp' => $compName,
                            'label' => $q['question_label'],
                            'type' => $q['question_type'] ?? 'Theory',
                            'marks' => $marks,
                            'btl' => $btl,
                            'cos' => !empty($cos) ? implode(', ', $cos) : 'N/A'
                        ];
                    }
                }
                $componentSummary[] = [
                    'comp' => $compName,
                    'count' => $cCount,
                    'marks' => $cMarks
                ];
            }
        }

        return [
            'mid' => $mid,
            'questions' => $questions,
            'btl_dist' => $btlDist,
            'co_dist' => $coDist,
            'total_marks' => $totalMarks,
            'components' => $componentSummary
        ];
    }

    /**
     * Render CIA QP Analysis HTML for Page 1 & Page 2
     */
    private function renderCIAQPPageHtml($mid, array $data)
    {
        $bloomNames = [
            1 => 'Remember (L1)',
            2 => 'Understand (L2)',
            3 => 'Apply (L3)',
            4 => 'Analyze (L4)',
            5 => 'Evaluate (L5)',
            6 => 'Create (L6)'
        ];

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 8px 0;">
                Continuous Internal Assessment - ' . $mid . ' (CIA-' . $mid . ') Question Paper Analysis
            </h3>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 22%; text-align: left;">Component</th>
                        <th style="width: 10%; text-align: center;">Q. No</th>
                        <th style="width: 16%; text-align: center;">Type</th>
                        <th style="width: 12%; text-align: center;">Max Marks</th>
                        <th style="width: 22%; text-align: center;">Bloom\'s Taxonomy Level</th>
                        <th style="width: 18%; text-align: center;">Mapped COs</th>
                    </tr>
                </thead>
                <tbody>';

        if (empty($data['questions'])) {
            $html .= '<tr><td colspan="6" style="text-align:center; padding:10px;">No question paper metadata configured for CIA-' . $mid . '.</td></tr>';
        } else {
            foreach ($data['questions'] as $mq) {
                $btlLabel = $bloomNames[$mq['btl']] ?? ('Level ' . $mq['btl']);
                $html .= '<tr>
                    <td style="word-wrap:break-word;">' . htmlspecialchars($mq['comp']) . '</td>
                    <td style="text-align: center; font-weight: bold;">Q' . htmlspecialchars($mq['label']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($mq['type']) . '</td>
                    <td style="text-align: center;">' . $mq['marks'] . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($btlLabel) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . htmlspecialchars($mq['cos']) . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';

        // Bloom\'s Taxonomy Summary Table
        $totalMarks = $data['total_marks'];
        $btlDist = $data['btl_dist'];

        $html .= '<h4 style="margin-top: 8px; margin-bottom: 3px; font-size: 10px;">CIA-' . $mid . ' Bloom\'s Taxonomy Weightage Distribution (Total Marks: ' . $totalMarks . '):</h4>
        <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; text-align: center; table-layout: fixed;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 14%;">Remember (L1)</th>
                    <th style="width: 14%;">Understand (L2)</th>
                    <th style="width: 14%;">Apply (L3)</th>
                    <th style="width: 14%;">Analyze (L4)</th>
                    <th style="width: 14%;">Evaluate (L5)</th>
                    <th style="width: 14%;">Create (L6)</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
            </thead>
            <tbody>
                <tr>';
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $html .= '<td style="font-weight: bold;">' . ($btlDist[$lvl] ?? 0) . ' M</td>';
        }
        $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">' . $totalMarks . ' M</td>';
        $html .= '</tr>
                <tr style="color: #555;">';
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $pctVal = ($totalMarks > 0) ? round((($btlDist[$lvl] ?? 0) / $totalMarks) * 100, 1) : 0;
            $html .= '<td>' . $pctVal . '%</td>';
        }
        $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">100%</td>';
        $html .= '</tr>
            </tbody>
        </table>
        </div>';

        return $html;
    }

    /**
     * Render Consolidated CIA QP Analysis for Page 3
     */
    private function renderConsolidatedCIAHtml($sub_id, array $cia1, array $cia2, array $bloomNames)
    {
        $grandTotalMarks = $cia1['total_marks'] + $cia2['total_marks'];

        // Aggregate Bloom's distributions
        $consBlooms = [];
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $m1 = $cia1['btl_dist'][$lvl] ?? 0.0;
            $m2 = $cia2['btl_dist'][$lvl] ?? 0.0;
            $consBlooms[$lvl] = [
                'cia1' => $m1,
                'cia2' => $m2,
                'total' => $m1 + $m2,
                'pct' => ($grandTotalMarks > 0) ? round((($m1 + $m2) / $grandTotalMarks) * 100, 1) : 0.0
            ];
        }

        // Aggregate CO marks distributions
        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];
        $coRows = [];
        $bloomDomainFocus = [
            1 => 'Recall facts & basic definitions',
            2 => 'Explain ideas & conceptual understanding',
            3 => 'Execute problems & practical applications',
            4 => 'Distinguish components & systematic analysis',
            5 => 'Appraise solutions, justification & critical evaluation',
            6 => 'Synthesize ideas, creative models & activities'
        ];

        foreach ($cosList as $co) {
            $cNum = $co['co_number'];
            $label = 'CO' . $cNum;
            $m1 = $cia1['co_dist'][$label] ?? 0.0;
            $m2 = $cia2['co_dist'][$label] ?? 0.0;
            $tot = $m1 + $m2;
            $pct = ($grandTotalMarks > 0) ? round(($tot / $grandTotalMarks) * 100, 1) : 0.0;
            $coRows[] = [
                'label' => $label,
                'desc' => $co['co_description'] ?? '',
                'cia1' => $m1,
                'cia2' => $m2,
                'total' => $tot,
                'pct' => $pct
            ];
        }

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Consolidated Continuous Internal Assessment (CIA-1 + CIA-2) Blueprint Analysis
            </h3>

            <!-- 1. Blueprint Summary -->
            <h4 style="margin: 6px 0 3px 0; font-size: 10px; color: #1a365d;">1. Assessment Blueprint & Component Summary:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Internal Exam Scope</th>
                        <th style="width: 35%; text-align: left;">Evaluated Components</th>
                        <th style="width: 20%;">Total Question Marks</th>
                        <th style="width: 20%;">Weightage Share %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Continuous Internal Assessment - 1</td>
                        <td style="text-align: left;">Subjective (60M), Objective (20M), Assignments (150M)</td>
                        <td style="font-weight: bold;">' . $cia1['total_marks'] . ' M</td>
                        <td>' . (($grandTotalMarks > 0) ? round(($cia1['total_marks'] / $grandTotalMarks) * 100, 1) : 0) . '%</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Continuous Internal Assessment - 2</td>
                        <td style="text-align: left;">Subjective (60M), Objective (20M), Assignments (195M)</td>
                        <td style="font-weight: bold;">' . $cia2['total_marks'] . ' M</td>
                        <td>' . (($grandTotalMarks > 0) ? round(($cia2['total_marks'] / $grandTotalMarks) * 100, 1) : 0) . '%</td>
                    </tr>
                    <tr style="background-color: #e2e3e5; font-weight: bold;">
                        <td style="text-align: left;">Total Consolidated Evaluation</td>
                        <td style="text-align: left;">Formative Blueprint Scope (Theory, Quizzes & Assignments)</td>
                        <td>' . $grandTotalMarks . ' M</td>
                        <td>100.0%</td>
                    </tr>
                </tbody>
            </table>

            <!-- 2. Combined Bloom\'s Distribution -->
            <h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">2. Combined Internal Bloom\'s Taxonomy Distribution (Total Marks: ' . $grandTotalMarks . ' M):</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 20%; text-align: left;">Bloom\'s Cognitive Level</th>
                        <th style="width: 15%;">CIA-1 Marks</th>
                        <th style="width: 15%;">CIA-2 Marks</th>
                        <th style="width: 15%; background-color: #e2e3e5;">Total Marks</th>
                        <th style="width: 12%;">Weightage %</th>
                        <th style="width: 23%; text-align: left;">Evaluation Focus</th>
                    </tr>
                </thead>
                <tbody>';

        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $row = $consBlooms[$lvl];
            $html .= '<tr>
                <td style="text-align: left; font-weight: bold;">' . htmlspecialchars($bloomNames[$lvl]) . '</td>
                <td>' . $row['cia1'] . ' M</td>
                <td>' . $row['cia2'] . ' M</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . $row['total'] . ' M</td>
                <td style="font-weight: bold;">' . $row['pct'] . '%</td>
                <td style="text-align: left; font-size: 8px; color: #444;">' . $bloomDomainFocus[$lvl] . '</td>
            </tr>';
        }

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td style="text-align: left;">Consolidated Assessment Total</td>
                <td>' . $cia1['total_marks'] . ' M</td>
                <td>' . $cia2['total_marks'] . ' M</td>
                <td>' . $grandTotalMarks . ' M</td>
                <td>100.0%</td>
                <td style="text-align: left; font-size: 8px;">Comprehensive Cognitive Blueprint</td>
            </tr>
            </tbody>
        </table>

        <!-- 3. Overall Course Outcome Distribution -->
        <h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">3. Course Outcome (CO) Marks Distribution across Internal Assessments:</h4>
        <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 10%;">CO#</th>
                    <th style="width: 46%; text-align: left;">Course Outcome Description</th>
                    <th style="width: 14%;">CIA-1 Marks</th>
                    <th style="width: 14%;">CIA-2 Marks</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($coRows as $cr) {
            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($cr['label']) . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($cr['desc']) . '</td>
                <td>' . $cr['cia1'] . ' M</td>
                <td>' . $cr['cia2'] . ' M</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . $cr['total'] . ' M (' . $cr['pct'] . '%)</td>
            </tr>';
        }

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="2" style="text-align: left;">Total Mapped Internal Marks</td>
                <td>' . $cia1['total_marks'] . ' M</td>
                <td>' . $cia2['total_marks'] . ' M</td>
                <td>' . $grandTotalMarks . ' M (100.0%)</td>
            </tr>
            </tbody>
        </table>

        <!-- Methodology Footnote -->
        <div style="margin-top: 8px; font-size: 8px; color: #495057; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 5px 8px; border-radius: 3px; line-height: 1.35; text-align: justify;">
            <strong>Note:</strong> Total marks in QP analysis represent gross offered marks across optional choices to audit overall cognitive coverage. Assignment tasks evaluated on 50M/100M/125M rubric scales are normalized proportionally to a 5.0 Marks ceiling in the Continuous Internal Assessment marks register.
        </div>
        </div>';

        return $html;
    }

    /**
     * Render CIA Learning Analytics Report for Page 4
     */
    private function renderLearningAnalyticsHtml($sub_id)
    {
        $target=$this->coEngine->getTargetAttainmentThreshold($sub_id);
        $asmtPerfRes = json_decode($this->laEngine->getAssessmentPerformance($sub_id, 'all'), true);
        $compPerfRes = json_decode($this->laEngine->getComponentPerformance($sub_id, 'all'), true);
        $bloomsPerfRes = json_decode($this->laEngine->getBloomsPerformance($sub_id, 'all'), true);

        $renderPdfBar = function($pct, $totalBlocks = 10, $color = '#0d6efd') {
            $val = max(0, min(100, floatval($pct)));
            $filled = (int)round(($val / 100.0) * $totalBlocks);
            $empty = $totalBlocks - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            return '<span style="font-family: monospace; font-size: 8.5px; color: ' . $color . '; letter-spacing: 0.5px;">' . $bar . '</span> <span style="font-size: 8px; color: #444; font-weight: bold;">' . number_format($val, 1) . '%</span>';
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Continuous Internal Assessment (CIA) & Learning Analytics Report
            </h3>';

        // 1. Assessment Performance Snapshot
        if (!empty($asmtPerfRes['data'])) {
            $html .= '<h4 style="margin: 8px 0 3px 0; font-size: 10px; color: #1a365d;">1. Assessment Performance Snapshot:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Assessment Evaluation Scope</th>
                        <th style="width: 20%; text-align: center;">Attainment %</th>
                        <th style="width: 35%; text-align: center;">Performance Graph</th>
                        <th style="width: 20%; text-align: center;">Benchmark Status</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($asmtPerfRes['data'] as $aRow) {
                $pct = floatval($aRow['attainment_percentage']);
                $color = $pct >= $target ? '#198754' : '#dc3545';
                $statusText = $pct >= $target ? '<span style="color:#198754; font-weight:bold;">Met configured target</span>' : '<span style="color:#dc3545; font-weight:bold;">Below Target</span>';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($aRow['assessment_label'] ?? $aRow['assessment_number']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . $statusText . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        // 2. Component Performance
        if (!empty($compPerfRes['data'])) {
            $html .= '<h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">2. Component-wise Performance Breakdown:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 30%; text-align: left;">Assessment Component</th>
                        <th style="width: 20%; text-align: center;">Attainment %</th>
                        <th style="width: 35%; text-align: center;">Component Graph</th>
                        <th style="width: 15%; text-align: center;">Remarks</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($compPerfRes['data'] as $cRow) {
                $pct = floatval($cRow['attainment_percentage']);
                $color = $pct >= $target ? '#0d6efd' : '#fd7e14';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($cRow['component_type']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . ($pct >= $target ? 'Satisfactory' : 'Needs Review') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        // 3. Cognitive Levels
        if (!empty($bloomsPerfRes['data'])) {
            $html .= '<h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">3. Bloom\'s Cognitive Level Attainment:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Cognitive Domain</th>
                        <th style="width: 15%; text-align: center;">Domain Level</th>
                        <th style="width: 15%; text-align: center;">Attainment %</th>
                        <th style="width: 30%; text-align: center;">Attainment Graph</th>
                        <th style="width: 15%; text-align: center;">Target Status</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($bloomsPerfRes['data'] as $bRow) {
                $pct = floatval($bRow['attainment_percentage']);
                $color = $pct >= $target ? '#198754' : '#dc3545';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($bRow['blooms_label']) . '</td>
                    <td style="text-align: center;">Level ' . htmlspecialchars($bRow['blooms_level']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . ($pct >= $target ? '<span style="color:#198754; font-weight:bold;">Met</span>' : '<span style="color:#dc3545; font-weight:bold;">Below Target</span>') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render SEE Question Paper Analysis HTML for Page 5
     */
    private function renderSEEQPAnalysisHtml($sub_id, array $bloomNames)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeComponent = $this->ciaObj->getOrCreateSEEComponent($sub_id, $seeCompType);
        $seeQuestions = $this->ciaObj->getQuestionsByComponent($seeComponent['id']);

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Semester End Examination (SEE) Question Paper Analysis
            </h3>';

        if (empty($seeQuestions)) {
            $html .= '<p style="text-align: center; color: red; font-style: italic; font-size: 11px;">Question paper metadata has not been configured.</p>';
        } else {
            $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 15%; text-align: center;">Q. No</th>
                        <th style="width: 20%; text-align: center;">Type</th>
                        <th style="width: 15%; text-align: center;">Marks</th>
                        <th style="width: 25%; text-align: center;">Bloom\'s Taxonomy Level</th>
                        <th style="width: 25%; text-align: center;">Mapped Course Outcomes</th>
                    </tr>
                </thead>
                <tbody>';

            $btlDistribution = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];
            $totalSeeQpMarks = 0.0;

            foreach ($seeQuestions as $q) {
                $cosMapped = $this->ciaObj->getCOsByQuestion($q['id']);
                $btlId = intval($q['blooms_level_id']);
                $marks = floatval($q['marks']);
                $totalSeeQpMarks += $marks;

                if (isset($btlDistribution[$btlId])) {
                    $btlDistribution[$btlId] += $marks;
                }

                $html .= '<tr>
                    <td style="text-align: center; font-weight: bold;">Q' . htmlspecialchars($q['question_label']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($q['question_type']) . '</td>
                    <td style="text-align: center;">' . $marks . '</td>
                    <td style="text-align: center;">Level ' . $btlId . '</td>
                    <td style="text-align: center;">' . (!empty($cosMapped) ? implode(', ', $cosMapped) : 'N/A') . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';

            // Bloom\'s Taxonomy Summary
            $html .= '<h4 style="margin-top: 10px; margin-bottom: 3px; font-size: 10px;">Bloom\'s Taxonomy Marks Weightage Distribution:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 14%;">Remember (L1)</th>
                    <th style="width: 14%;">Understand (L2)</th>
                    <th style="width: 14%;">Apply (L3)</th>
                    <th style="width: 14%;">Analyze (L4)</th>
                    <th style="width: 14%;">Evaluate (L5)</th>
                    <th style="width: 14%;">Create (L6)</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
                <tr>';

            for ($lvl = 1; $lvl <= 6; $lvl++) {
                $mVal = isset($btlDistribution[$lvl]) ? $btlDistribution[$lvl] . ' M' : '0 M';
                $html .= '<td style="font-weight: bold;">' . $mVal . '</td>';
            }
            $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">' . $totalSeeQpMarks . ' M</td>';
            $html .= '</tr>
                <tr style="color: #555;">';
            for ($lvl = 1; $lvl <= 6; $lvl++) {
                $pctVal = ($totalSeeQpMarks > 0) ? round((($btlDistribution[$lvl] ?? 0) / $totalSeeQpMarks) * 100, 1) : 0;
                $html .= '<td>' . $pctVal . '%</td>';
            }
            $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">100%</td>';
            $html .= '</tr>
            </table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render SEE Marks & Grade Distribution HTML for Page 6
     */
    private function renderSEEGradeDistributionHtml($sub_id)
    {
        require_once __DIR__.'/../core/AssessmentPolicy.php';
        $policy=(new \College\AssessmentPolicy($this->conn))->forSubject((int)$sub_id);$bands=$policy['config']['grade_bands'];$saved=$this->seeObj->getSavedSEEMarks($sub_id);$percentages=[];$grades=[];$statuses=[];$estimated=0;
        foreach($saved as $row){$max=(float)$row['max_marks'];if($max<=0){continue;}$percentages[]=(float)$row['external_marks']/$max*100;$n=json_decode($row['normalisation_json']??'null',true);$grade=trim((string)($n['grade']??''));$status=trim((string)($n['result_status']??''));if($grade!==''){$grades[$grade]=($grades[$grade]??0)+1;}if($status!==''){$statuses[$status]=($statuses[$status]??0)+1;}if($row['entry_mode']!=='DETAILED'){$estimated++;}}
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$count=count($percentages);$average=$count?number_format(array_sum($percentages)/$count,2).'%':'Not available';$known=($statuses['PASS']??0)+($statuses['FAIL']??0);$pass=$known?number_format(($statuses['PASS']??0)/$known*100,2).'%':'Not available';
        $html='<h3>External marks and reported grade distribution</h3><p>Finalized records: '.$count.'. Cohort average: '.$average.'. Highest / lowest percentage: '.($count?number_format(max($percentages),2).' / '.number_format(min($percentages),2):'Not available').'.</p><p>Official PASS/FAIL records: '.$known.'; pass percentage among those records: '.$pass.'. Records without an official status are excluded from this pass calculation.</p><p>Aggregate or grade estimates: '.$estimated.'. CO estimates are distinct from question-wise measurements. An external score alone does not establish the overall course grade or pass status.</p>';
        $html.='<h4>Reported letter grades</h4><table border="1" cellpadding="5" style="border-collapse:collapse;width:100%"><tr><th>Grade from source</th><th>Count</th></tr>';foreach($grades as $grade=>$n){$html.='<tr><td>'.$e($grade).'</td><td>'.$n.'</td></tr>';}$html.='</table>';if(!$grades){$html.='<p>Reported letter grades are not available.</p>';}
        $html.='<h4>Configured regulation grade bands</h4><table border="1" cellpadding="5" style="border-collapse:collapse;width:100%"><tr><th>Grade</th><th>Percentage range</th><th>Points</th></tr>';foreach($bands as $band){$html.='<tr><td>'.$e($band['grade']).'</td><td>'.$e($band['min_percent']).' – '.$e($band['max_percent']).'</td><td>'.$e($band['points']).'</td></tr>';}$html.='</table>';if(!$bands){$html.='<p>No grade band profile is configured. No default bands have been assumed.</p>';}
        return $html;
    }

    /**
     * Render SEE Result Analysis HTML for Page 7
     */
    private function renderSEEResultAnalysisHtml($sub_id)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeAttainment = $this->seeObj->calculateSEECOAttainment($sub_id, $seeCompType);

        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];

        $renderBar = function($pct, $color = '#198754') {
            $val = max(0, min(100, floatval($pct)));
            $barW = round(($val / 100.0) * 120, 1);
            $svg = '<svg width="120" height="9" xmlns="http://www.w3.org/2000/svg">';
            $svg .= '<rect width="120" height="9" fill="#e9ecef" rx="2" ry="2"/>';
            if ($barW > 0) {
                $svg .= '<rect width="' . $barW . '" height="9" fill="' . $color . '" rx="2" ry="2"/>';
            }
            $svg .= '</svg>';
            return $svg;
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Semester End Examination (SEE) Result Analysis
            </h3>

            <div style="background-color: #e9ecef; padding: 6px 10px; margin-bottom: 10px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>Direct Attainment Benchmark:</strong> Use the configured course target; missing assessment evidence is unavailable.<br>
                <strong>Attainment Levels:</strong> Review the configured programme/regulation level thresholds before accepting this provisional report.
            </div>

            <table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 10%;">CO#</th>
                        <th style="width: 36%; text-align: left;">Course Outcome Description</th>
                        <th style="width: 12%;">Cohort Evaluated</th>
                        <th style="width: 14%;">Students meeting configured target</th>
                        <th style="width: 14%;">Cohort Meeting Target %</th>
                        <th style="width: 14%;">Attainment Level</th>
                    </tr>
                </thead>
                <tbody>';

        $totalLevel = 0;
        $coCount = 0;

        foreach ($cosList as $co) {
            $num = $co['co_number'];
            $attRow = $seeAttainment['data'][$num] ?? [];
            $cohortPct = isset($attRow['cohort_percentage']) ? floatval($attRow['cohort_percentage']) : 0.0;
            $level = isset($attRow['attainment_level']) ? intval($attRow['attainment_level']) : 0;
            $attainedCount = isset($attRow['attained_students']) ? intval($attRow['attained_students']) : 0;
            $totalStudents = (int)($attRow['evaluated_students']??$attRow['total_students']??0);
            $measured=$totalStudents>0 && isset($attRow['cohort_percentage'],$attRow['attainment_level']);
            if($measured){$totalLevel+=$level;$coCount++;}

            $statusColor = $level >= 2 ? '#198754' : ($level == 1 ? '#d97706' : '#dc3545');

            $html .= '<tr>
                <td style="font-weight: bold;">CO' . $num . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($co['co_description'] ?? '') . '</td>
                <td>' . $totalStudents . '</td>
                <td>' . ($measured?$attainedCount:'Not available') . '</td>
                <td style="font-weight: bold;">' . ($measured?number_format($cohortPct,2).'%':'Not available').'<br><small>'.htmlspecialchars($attRow['basis']??'UNAVAILABLE').'; missing '.(int)($attRow['missing_students']??0).'</small>' . '</td>
                <td style="font-weight: bold; color: ' . $statusColor . ';">' . ($measured?'Level '.$level:'Not available') . '</td>
            </tr>';
        }

        $avgLevel = ($coCount > 0) ? round($totalLevel / $coCount, 2) : 0.0;

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="4" style="text-align: left;">Average SEE Summative Attainment Level</td>
                <td colspan="2">Level ' . ($coCount>0?number_format($avgLevel,2).' / 3.00':'Not available') . '</td>
            </tr>
            </tbody>
        </table>

        <!-- Outcome Highlights -->
        <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 10px; margin-top: 15px; font-size: 8.5px;">
            <strong>Outcome Attainment Findings:</strong>
            <p style="margin: 4px 0 0 0;">
                The table reports recorded assessment data. Missing records are unavailable evidence. Faculty interpretation and review observations must be recorded separately.
            </p>
        </div>
        </div>';

        return $html;
    }

    /**
     * Render Combined Direct Attainment Analysis for Page 8
     */
    private function renderCombinedDirectAttainmentHtml($sub_id, $w_cia, $w_see)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeAttainment = $this->seeObj->calculateSEECOAttainment($sub_id, $seeCompType);
        $ciaAttainment = $this->seeObj->calculateCIACOAttainment($sub_id);

        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];

        $wCiaPct = round($w_cia * 100);
        $wSeePct = round($w_see * 100);

        $renderBar = function($lvl, $maxLvl = 3.0, $width = 100) {
            $val = max(0, min($maxLvl, floatval($lvl)));
            $barW = round(($val / $maxLvl) * $width, 1);
            $color = $val >= 2.5 ? '#198754' : ($val >= 1.75 ? '#0d6efd' : '#d97706');
            $svg = '<svg width="' . $width . '" height="9" xmlns="http://www.w3.org/2000/svg">';
            $svg .= '<rect width="' . $width . '" height="9" fill="#e9ecef" rx="2" ry="2"/>';
            if ($barW > 0) {
                $svg .= '<rect width="' . $barW . '" height="9" fill="' . $color . '" rx="2" ry="2"/>';
            }
            $svg .= '</svg>';
            return $svg;
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Combined Direct Attainment Analysis (Continuous Internal Assessment + Semester End Examination)
            </h3>

            <div style="background-color: #e9ecef; padding: 6px 10px; margin-bottom: 12px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>OBE Direct Attainment Formula:</strong> Direct CO Level = (' . $wCiaPct . '% x CIA Level) + (' . $wSeePct . '% x SEE Level) &bull; <strong>Benchmark:</strong> Configured course attainment target; aggregate and grade inputs are estimates.
            </div>

            <table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th rowspan="2" style="width: 8%; vertical-align: middle;">CO#</th>
                        <th rowspan="2" style="width: 32%; text-align: left; vertical-align: middle;">Course Outcome Description</th>
                        <th colspan="2" style="width: 18%;">Internal (CIA)</th>
                        <th colspan="2" style="width: 18%;">External (SEE)</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #e2e3e5;">Direct Level<br><small>(' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE)</small></th>
                        <th rowspan="2" style="width: 10%; vertical-align: middle;">Status</th>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Cohort %</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>';

        $totalDirect = 0.0;
        $count = 0;

        foreach ($cosList as $co) {
            $num = $co['co_number'];
            $cRow = $ciaAttainment['data'][$num] ?? [];
            $sRow = $seeAttainment['data'][$num] ?? [];

            $cPct = isset($cRow['cohort_percentage']) ? floatval($cRow['cohort_percentage']) : null;
            $cLvl = isset($cRow['attainment_level']) ? intval($cRow['attainment_level']) : null;

            $sPct = isset($sRow['cohort_percentage']) ? floatval($sRow['cohort_percentage']) : null;
            $sLvl = isset($sRow['attainment_level']) ? intval($sRow['attainment_level']) : null;

            $complete=($w_cia<=0 || $cLvl!==null) && ($w_see<=0 || $sLvl!==null);
            $directLvl=$complete?round(($w_cia*($cLvl??0))+($w_see*($sLvl??0)),2):null;
            if($complete){$totalDirect+=$directLvl;$count++;}

            $statusText = !$complete ? 'Not available' : ($directLvl >= 2.5 ? '<span style="color:#198754; font-weight:bold;">High</span>' : ($directLvl >= 1.75 ? '<span style="color:#0d6efd; font-weight:bold;">Moderate</span>' : '<span style="color:#d97706; font-weight:bold;">Marginal</span>'));

            $html .= '<tr>
                <td style="font-weight: bold;">CO' . $num . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($co['co_description'] ?? '') . '</td>
                <td>' . ($cPct!==null?number_format($cPct,2).'%':'Not available') . '</td>
                <td style="font-weight: bold;">' . ($cLvl??'Not available') . '</td>
                <td>' . ($sPct!==null?number_format($sPct,2).'%':'Not available') . '</td>
                <td style="font-weight: bold;">' . ($sLvl??'Not available') . '</td>
                <td style="font-weight: bold; background-color: #e2e3e5; font-size: 9.5px;">' . ($directLvl!==null?number_format($directLvl,2):'Not available') . '</td>
                <td>' . $statusText . '</td>
            </tr>';
        }

        $avgDirect = ($count > 0) ? round($totalDirect / $count, 2) : 0.0;

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="6" style="text-align: left;">Average Direct Outcome Attainment Level</td>
                <td style="font-size: 10px; color: #1a365d;">' . ($count>0?number_format($avgDirect,2):'Not available') . '</td>
                <td>' . ($avgDirect >= 2.0 ? '<span style="color:#198754;">Target Met</span>' : '<span style="color:#dc3545;">Needs Review</span>') . '</td>
            </tr>
            </tbody>
        </table>

        <!-- Direct Attainment Commentary -->
        <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 10px; margin-top: 15px; font-size: 8.5px;">
            <strong>Attainment Integration Commentary:</strong>
            <p style="margin: 4px 0 0 0;">
                The integrated direct outcome attainment combines formative Continuous Internal Assessments with summative Semester End Examinations.
                Recorded average Direct Level: <strong>' . ($count>0?number_format($avgDirect,2):'Not available') . ' / 3.00</strong>. This provisional calculation does not certify compliance or review approval.
            </p>
        </div>
        </div>';

        return $html;
    }

    /**
     * Render Comprehensive OBE Matrix HTML for Page 9
     */
    private function renderOBEMatrixHtml($sub_id, $feedbackData, $w_cia, $w_see, $w_direct, $w_indirect, $targetThreshold)
    {
        $compAttainRes = json_decode($this->coEngine->getComprehensiveAttainment($sub_id), true);
        $cos = $compAttainRes['cos'] ?? [];
        $pos = $compAttainRes['pos'] ?? [];

        // String sorting for PO codes as mandated by system architecture
        usort($pos, function($a, $b) {
            return strcmp($a['po_label'], $b['po_label']);
        });

        // Filter out empty PSOs if not mapped
        $pos = array_filter($pos, function($p) {
            if (strpos(strtoupper($p['po_label']), 'PSO') === 0) {
                return !empty($p['direct_attainment']) && $p['direct_attainment'] > 0;
            }
            return true;
        });

        $wCiaPct = round($w_cia * 100);
        $wSeePct = round($w_see * 100);
        $wDirPct = round($w_direct * 100);
        $wIndPct = round($w_indirect * 100);

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 8px 0;">
                Comprehensive Course Outcome (CO) & Program Outcome (PO) Attainment Matrix
            </h3>

            <div style="background-color: #e9ecef; padding: 5px 10px; margin-bottom: 10px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>Configured OBE Assessment Framework:</strong> Direct Attainment = ' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE | Overall Attainment = ' . $wDirPct . '% Direct + ' . $wIndPct . '% Indirect Feedback | Benchmark Target: ' . $targetThreshold . '%
            </div>

            <!-- Table 1: CO Attainment Summary -->
            <h4 style="margin: 6px 0 3px 0; font-size: 10px; color: #1a365d;">Course Outcome (CO) Attainment Summary:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th rowspan="2" style="width: 10%; vertical-align: middle;">CO</th>
                        <th colspan="2" style="width: 22%;">Internal (CIA)</th>
                        <th colspan="2" style="width: 22%;">External (SEE)</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #e2e3e5;">Direct Level<br><small>(' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE)</small></th>
                        <th colspan="2" style="width: 18%;">Student Feedback</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #d1e7dd;">Overall Level<br><small>(' . $wDirPct . '% Dir + ' . $wIndPct . '% Ind)</small></th>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Avg / 5</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($cos as $co) {
            $ciaPct = ($co['cia_pct'] !== null) ? number_format($co['cia_pct'], 2) . '%' : 'N/A';
            $seePct = ($co['see_pct'] !== null) ? number_format($co['see_pct'], 2) . '%' : 'Pending';
            $fbRating = ($co['indirect_avg_rating'] !== null) ? number_format($co['indirect_avg_rating'], 2) : 'N/A';
            $fbLvl = ($co['indirect_level'] !== null) ? $co['indirect_level'] : 'N/A';

            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($co['co_label']) . '</td>
                <td>' . $ciaPct . '</td>
                <td style="font-weight: bold;">' . $co['cia_level'] . '</td>
                <td>' . $seePct . '</td>
                <td style="font-weight: bold;">' . $co['see_level'] . '</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . number_format($co['direct_attainment'], 2) . '</td>
                <td>' . $fbRating . '</td>
                <td style="font-weight: bold;">' . $fbLvl . '</td>
                <td style="font-weight: bold; background-color: #d1e7dd; color: #0f5132;">' . number_format($co['overall_attainment'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Fetch CO-PO Correlation Matrix from database
        $coPoWeights = [];
        $poCodesMap = [];
        $coPoStmt = $this->conn->prepare("
            SELECT co.co_number, p.code, ROUND(AVG(m.weightage)) as weightage 
            FROM co_po_mapping m 
            JOIN po_pso p ON m.po_id = p.id 
            JOIN course_outcomes co ON m.co_id = co.id 
            WHERE co.sub_id = ? 
            GROUP BY co.co_number, p.code 
            ORDER BY co.co_number ASC, p.code ASC
        ");
        if ($coPoStmt) {
            $coPoStmt->bind_param("i", $sub_id);
            if ($coPoStmt->execute()) {
                $cRes = $coPoStmt->get_result();
                while ($r = $cRes->fetch_assoc()) {
                    $cCode = strtoupper(trim($r['code']));
                    $coPoWeights['CO' . $r['co_number']][$cCode] = (int)$r['weightage'];
                    $poCodesMap[$cCode] = true;
                }
            }
            $coPoStmt->close();
        }

        $matrixPoCodes = array_keys($poCodesMap);
        natsort($matrixPoCodes);

        if (!empty($matrixPoCodes)) {
            $colWidth = round(75.0 / count($matrixPoCodes), 1);
            $html .= '
            <!-- Table 2: CO-PO Articulation Matrix -->
            <h4 style="margin: 8px 0 2px 0; font-size: 9.5px; color: #1a365d;">CO-PO Correlation / Articulation Matrix (1: Slight, 2: Moderate, 3: Substantial):</h4>
            <table border="1" cellpadding="2.5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8px; table-layout: fixed; text-align: center; margin-bottom: 6px;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Course Outcome</th>';
            foreach ($matrixPoCodes as $pCode) {
                $html .= '<th style="width: ' . $colWidth . '%;">' . htmlspecialchars($pCode) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($cos as $co) {
                $cLabel = $co['co_label'];
                $html .= '<tr><td style="text-align: left; font-weight: bold;">' . htmlspecialchars($cLabel) . '</td>';
                foreach ($matrixPoCodes as $pCode) {
                    $wt = $coPoWeights[$cLabel][$pCode] ?? '-';
                    $html .= '<td>' . htmlspecialchars($wt) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // Table 3: Program Outcome Attainment Levels
        $html .= '
            <h4 style="margin: 8px 0 2px 0; font-size: 9.5px; color: #1a365d;">Program Outcome (PO & PSO) Attainment Levels:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 20%;">PO / PSO Code</th>
                        <th style="width: 25%;">Direct Level</th>
                        <th style="width: 25%;">Indirect Level (Feedback)</th>
                        <th style="width: 30%; background-color: #d1e7dd;">Overall PO Attainment Level</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($pos as $po) {
            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($po['po_label']) . '</td>
                <td>' . number_format($po['direct_attainment'], 2) . '</td>
                <td>' . number_format($po['indirect_attainment'], 2) . '</td>
                <td style="font-weight: bold; background-color: #d1e7dd; color: #0f5132;">' . number_format($po['overall_attainment'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>

            <!-- Continuous Quality Improvement (CQI) Action Plan Footnote -->
            <div style="margin-top: 8px; font-size: 7.5px; color: #084298; background-color: #cfe2ff; border: 1px solid #b6d4fe; padding: 4px 8px; border-radius: 3px; line-height: 1.35; text-align: justify;">
                <strong>Continuous Quality Improvement (CQI) Action Plan:</strong> Review proposed actions, approval, execution evidence and follow-up findings in the CQI review queue and the Action Taken Report. No completed intervention is inferred from attainment figures.
            </div>
        </div>';

        return $html;
    }

    /**
     * Standalone NBA SAR Form C-3 Dossier Generator
     * 
     * Generates statutory NBA Self Assessment Report (SAR) Form C-3:
     * - Criterion 3: Course Outcome (CO) Attainment & Gap Analysis
     * - Direct Attainment (CIA 30% + SEE 70%) & Indirect Attainment (20%)
     * - Attainment Gaps against institutional benchmarks
     * - Continuous Quality Improvement (CQI) Action-Taken Ledger
     * 
     * @param int $subId Subject ID
     * @param array $options Optional generation parameters
     * @return string Absolute file path to the generated PDF
     */
    public function generateSARFormC3PDF(int $subId, array $options = []): string
    {
        $subId = (int)$subId;
        if ($subId <= 0) {
            throw new \InvalidArgumentException("Invalid subject ID provided for SAR Form C-3.");
        }

        // Fetch course and faculty details
        $facid = $options['facid'] ?? null;
        if (!empty($facid)) {
            $details = $this->facultyObj->getClassSubjectFacultyDetails($subId, $facid);
        } else {
            require_once __DIR__ . '/../hod.class.php';
            $hodObj = new \HOD();
            $details = $hodObj->getClsSubFacBySubID($subId);
        }

        $meta = $this->extractCourseMeta($details, $subId);
        $this->facultyObj->assertSubjectScope($subId);
        $regulation = $this->facultyObj->getRegulationIdForSubject($subId);

        // Regulatory weights
        $w_cia = $this->requiredPolicySetting('attainment_direct_cia_weight', $regulation);
        $w_see = $this->requiredPolicySetting('attainment_direct_see_weight', $regulation);
        $w_direct = $this->requiredPolicySetting('overall_direct_weight', $regulation);
        $w_indirect = $this->requiredPolicySetting('overall_indirect_weight', $regulation);
        $targetThreshold = $this->requiredPolicySetting('co_target_percentage', $regulation);
        $targetLevel = $this->requiredPolicySetting('target_attainment_level', $regulation);

        // Fetch CO attainment
        $compAttainRes = json_decode($this->coEngine->getComprehensiveAttainment($subId), true);
        $cos = $compAttainRes['cos'] ?? [];

        // Fetch mapped CO details and Bloom levels from database
        $coDetails = [];
        $stmtCO = $this->conn->prepare("SELECT id, co_number, co_description, bloom_level FROM course_outcomes WHERE sub_id = ? ORDER BY co_number ASC");
        if ($stmtCO) {
            $stmtCO->bind_param("i", $subId);
            $stmtCO->execute();
            $coRes = $stmtCO->get_result();
            while ($r = $coRes->fetch_assoc()) {
                $coDetails[(int)$r['co_number']] = $r;
            }
            $stmtCO->close();
        }

        // Fetch logged CQI actions from course_cqi_actions
        $cqiActions = [];
        $stmtCQI = $this->conn->prepare("SELECT ca.*, co.co_number FROM course_cqi_actions ca JOIN course_outcomes co ON ca.co_id = co.id WHERE ca.sub_id = ? ORDER BY co.co_number ASC");
        if ($stmtCQI) {
            $stmtCQI->bind_param("i", $subId);
            $stmtCQI->execute();
            $cqiRes = $stmtCQI->get_result();
            while ($r = $cqiRes->fetch_assoc()) {
                $cqiActions[(int)$r['co_number']] = $r;
            }
            $stmtCQI->close();
        }

        // Fetch course completion audit for fallback remarks
        require_once __DIR__ . '/LessonPlanService.php';
        $audit = \Services\LessonPlanService::getInstance()->getCourseCompletionAudit($subId);

        // Initialize mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font_size' => 8.5,
            'default_font' => 'helvetica',
            'tempDir' => $this->reportDirectory()
        ]);
        $mpdf->SetHTMLFooter('<div style="font-size:8px">Provisional analysis. Recorded evidence and configured policy require authorised review. Page {PAGENO} of {nbpg}.</div>');

        $html = $this->renderSARFormC3Html($meta, $cos, $coDetails, $cqiActions, $audit, $w_cia, $w_see, $w_direct, $w_indirect, $targetThreshold, $targetLevel);
        $mpdf->WriteHTML($html);

        $filename = 'sar_form_c3_' . $subId . '_' . date("Ymd_His") . '.pdf';
        $outDir = $this->reportDirectory();
        $filepath = $outDir . '/' . bin2hex(random_bytes(16)) . '_' . $filename;
        $mpdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Render HTML for Standalone NBA SAR Form C-3
     */
    private function renderSARFormC3Html(array $meta, array $cos, array $coDetails, array $cqiActions, ?array $audit, float $w_cia, float $w_see, float $w_direct, float $w_indirect, float $targetThreshold, float $targetLevel): string
    {
        $wCiaPct = round($w_cia * 100);
        $wSeePct = round($w_see * 100);
        $wDirPct = round($w_direct * 100);
        $wIndPct = round($w_indirect * 100);

        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <!-- Header Section -->
            <table cellpadding="2" cellspacing="0" style="width:100%; border-collapse:collapse; text-align:center; margin-bottom:6px;">
                <tr>
                    <td style="font-size:13px; font-weight:bold; color:#1a365d; text-transform:uppercase;">
                        Jawaharlal Nehru Technological University Anantapur
                    </td>
                </tr>
                <tr>
                    <td style="font-size:11px; font-weight:bold; color:#2c5282;">
                        College of Engineering (Autonomous), Ananthapuramu – 515002, A.P.
                    </td>
                </tr>
                <tr>
                    <td style="font-size:9.5px; font-weight:bold; color:#0d6efd; padding-top:2px;">
                        DEPARTMENT OF ' . strtoupper(htmlspecialchars($meta['dept_name'])) . '
                    </td>
                </tr>
                <tr>
                    <td style="font-size:10px; font-weight:bold; color:#dc3545; padding-top:4px; text-decoration:underline;">
                        NBA TIER-I ACCREDITATION DOSSIER — CRITERION 3: SAR FORM C-3
                    </td>
                </tr>
                <tr>
                    <td style="font-size:8.5px; color:#495057; font-style:italic;">
                        Continuous Course Outcome (CO) Attainment & Remedial Action Taken (NBA Table 3.2.1)
                    </td>
                </tr>
            </table>
            <hr style="border:0; border-top:1.5px solid #1a365d; margin:2px 0 8px 0;">

            <!-- Course Meta Card -->
            <table cellpadding="4" cellspacing="0" border="1" style="width:100%; border-collapse:collapse; font-size:8px; margin-bottom:10px; background-color:#f8f9fa;">
                <tr>
                    <td style="width:18%; font-weight:bold; background-color:#e9ecef;">Course Name:</td>
                    <td style="width:32%; font-weight:bold; color:#1a365d;">' . htmlspecialchars($meta['subject']) . '</td>
                    <td style="width:18%; font-weight:bold; background-color:#e9ecef;">Course Code:</td>
                    <td style="width:32%;">' . htmlspecialchars($meta['subcode']) . '</td>
                </tr>
                <tr>
                    <td style="font-weight:bold; background-color:#e9ecef;">Program / Class:</td>
                    <td>' . htmlspecialchars($meta['branch']) . ' (' . htmlspecialchars($meta['class']) . ')</td>
                    <td style="font-weight:bold; background-color:#e9ecef;">Academic Year & Sem:</td>
                    <td>' . htmlspecialchars($meta['acad_year']) . ' / ' . htmlspecialchars($meta['semester']) . '</td>
                </tr>
                <tr>
                    <td style="font-weight:bold; background-color:#e9ecef;">Course In-Charge:</td>
                    <td>' . htmlspecialchars($meta['faculty']) . '</td>
                    <td style="font-weight:bold; background-color:#e9ecef;">Regulation & Weights:</td>
                    <td>' . htmlspecialchars($meta['regulation']) . ' | CIA: ' . $wCiaPct . '% | SEE: ' . $wSeePct . '% | CES: ' . $wIndPct . '%</td>
                </tr>
            </table>

            <!-- Section 1: CO Formulation & Cognitive Taxonomy -->
            <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#1a365d;">1. Course Outcomes (COs) Formulation & Cognitive Levels:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:8px; margin-bottom:10px;">
                <thead>
                    <tr style="background-color:#e2e3e5; font-weight:bold; text-align:center;">
                        <th style="width:10%;">CO ID</th>
                        <th style="width:65%; text-align:left;">Course Outcome Statement</th>
                        <th style="width:15%;">Bloom\'s Level</th>
                        <th style="width:10%;">Benchmark</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($cos)) {
            foreach ($cos as $co) {
                $coNum = (int)str_replace('CO', '', $co['co_label']);
                $desc = $coDetails[$coNum]['co_description'] ?? 'Course Outcome ' . $coNum;
                $bloom = $coDetails[$coNum]['bloom_level'] ?? 'L3-Apply';
                $html .= '
                <tr>
                    <td style="text-align:center; font-weight:bold;">' . htmlspecialchars($co['co_label']) . '</td>
                    <td>' . htmlspecialchars($desc) . '</td>
                    <td style="text-align:center;">' . htmlspecialchars($bloom) . '</td>
                    <td style="text-align:center; font-weight:bold;">' . $targetThreshold . '%</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" style="text-align:center;">No course outcomes formulated.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>

            <!-- Section 2: Direct Assessment Attainment Breakdown -->
            <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#1a365d;">2. Assessment Tools & Direct Evaluation Breakdown:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:8px; text-align:center; margin-bottom:10px;">
                <thead>
                    <tr style="background-color:#e2e3e5;">
                        <th rowspan="2" style="width:12%; vertical-align:middle;">CO Code</th>
                        <th colspan="2" style="width:28%;">Internal Evaluation (CIA: ' . $wCiaPct . '%)</th>
                        <th colspan="2" style="width:28%;">Semester End Exam (SEE: ' . $wSeePct . '%)</th>
                        <th rowspan="2" style="width:16%; vertical-align:middle; background-color:#cfe2ff;">Direct Level<br><small>(' . $wCiaPct . '% + ' . $wSeePct . '%)</small></th>
                        <th rowspan="2" style="width:16%; vertical-align:middle;">Target Level</th>
                    </tr>
                    <tr style="background-color:#f2f2f2;">
                        <th>Cohort Meeting %</th>
                        <th>Level (1-3)</th>
                        <th>Cohort Meeting %</th>
                        <th>Level (1-3)</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($cos as $co) {
            $ciaPct = ($co['cia_pct'] !== null) ? number_format($co['cia_pct'], 2) . '%' : 'N/A';
            $seePct = ($co['see_pct'] !== null) ? number_format($co['see_pct'], 2) . '%' : 'Pending';
            $html .= '
            <tr>
                <td style="font-weight:bold;">' . htmlspecialchars($co['co_label']) . '</td>
                <td>' . $ciaPct . '</td>
                <td style="font-weight:bold;">' . ($co['cia_pct']!==null?$co['cia_level']:'Not available') . '</td>
                <td>' . $seePct . '</td>
                <td style="font-weight:bold;">' . ($co['see_pct']!==null?$co['see_level']:'Not available') . '</td>
                <td style="font-weight:bold; background-color:#cfe2ff; color:#084298;">' . (($w_cia<=0 || $co['cia_pct']!==null) && ($w_see<=0 || $co['see_pct']!==null)?number_format($co['direct_attainment'],2):'Not available') . '</td>
                <td>' . number_format($targetLevel, 2) . '</td>
            </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <!-- Section 3: NBA SAR Form C-3 Attainment Gap & Actions Taken -->
            <h4 style="margin:6px 0 3px 0; font-size:9.5px; color:#1a365d;">3. NBA SAR Form C-3: Attainment Gaps & Continuous Quality Improvement (CQI) Action Plan:</h4>
            <table border="1" cellpadding="3.5" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:7.5px; margin-bottom:12px;">
                <thead>
                    <tr style="background-color:#1a365d; color:#ffffff; text-align:center;">
                        <th style="width:8%;">CO</th>
                        <th style="width:10%;">Target Level</th>
                        <th style="width:10%;">Attained Level</th>
                        <th style="width:10%;">Gap (&Delta;)</th>
                        <th style="width:14%;">Attainment Status</th>
                        <th style="width:24%;">Root Cause / Analysis</th>
                        <th style="width:24%;">Action Taken / Remedial Measure</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($cos as $co) {
            $coNum = (int)str_replace('CO', '', $co['co_label']);
            $complete=($w_cia<=0 || $co['cia_pct']!==null) && ($w_see<=0 || $co['see_pct']!==null) && ($w_indirect<=0 || ($co['indirect_pct']??null)!==null);
            $attained = $complete?(float)$co['overall_attainment']:null;
            $gap = $attained!==null?round($targetLevel-$attained,2):null;
            $isAttained = ($gap!==null && $gap<=0.0);
            $statusLabel = !$complete ? 'INCOMPLETE EVIDENCE' : ($isAttained 
                ? '<span style="color:#0f5132; font-weight:bold;">ATTAINED (&ge; Target)</span>' 
                : '<span style="color:#b02a37; font-weight:bold;">GAP IDENTIFIED (' . $gap . ')</span>');
            $bgColor = $isAttained ? '#f3fbf6' : '#fff5f5';

            // Lookup CQI actions
            $actionRow = $cqiActions[$coNum] ?? null;
            $rootCause = '';
            $actionTaken = '';

            $rootCause=!empty($audit['deviations_reason'])?htmlspecialchars($audit['deviations_reason']):'No faculty analysis recorded.';
            if ($actionRow) {
                $review=$actionRow['hod_review_status']??'PENDING';
                $actionTaken='Proposed action (review: '.htmlspecialchars($review).'): '.htmlspecialchars($actionRow['action_proposed']).'<br>';
                $actionTaken.=!empty($actionRow['completed_at']) && !empty($actionRow['execution_evidence'])
                    ? 'Recorded execution evidence: '.htmlspecialchars($actionRow['execution_evidence'])
                    : 'Execution evidence not recorded.';
            } else {
                $actionTaken='No CQI action/execution record available.';
                if(!empty($audit['compensatory_actions'])){$actionTaken.='<br>Faculty course-file statement: '.htmlspecialchars($audit['compensatory_actions']);}
            }

            $html .= '
            <tr style="background-color:' . $bgColor . ';">
                <td style="text-align:center; font-weight:bold;">' . htmlspecialchars($co['co_label']) . '</td>
                <td style="text-align:center;">' . number_format($targetLevel, 2) . '</td>
                <td style="text-align:center; font-weight:bold;">' . ($attained!==null?number_format($attained,2):'Not available') . '</td>
                <td style="text-align:center; font-weight:bold; color:' . ($gap > 0 ? '#dc3545' : '#198754') . ';">' . ($gap===null?'Not available':($gap>0?'+' . number_format($gap,2):number_format($gap,2))) . '</td>
                <td style="text-align:center;">' . $statusLabel . '</td>
                <td style="text-align:justify;">' . $rootCause . '</td>
                <td style="text-align:justify;">' . $actionTaken . '</td>
            </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <!-- Administrative Sign-Off Section -->
            <div style="margin-top:16px;">
                <table cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:8px; text-align:center;">
                    <tr>
                        <td style="width:20%;"><br><br>____________________________<br><strong>Course Teacher</strong><br>' . htmlspecialchars($meta['faculty']) . '</td>
                        <td style="width:20%;"><br><br>____________________________<br><strong>Course Coordinator</strong><br>Module Reviewer</td>
                        <td style="width:20%;"><br><br>____________________________<br><strong>DAAC Convener</strong><br>Academic Audit Cell</td>
                        <td style="width:20%;"><br><br>____________________________<br><strong>Head of Department</strong><br>Dept. of ' . htmlspecialchars($meta['dept_name']) . '</td>
                        <td style="width:20%;"><br><br>____________________________<br><strong>Principal</strong><br>JNTUACEA</td>
                    </tr>
                </table>
            </div>

            <!-- Footer Security -->
            <div style="margin-top:14px; border-top:1px dashed #6c757d; padding-top:4px; font-size:7px; color:#6c757d; text-align:center;">
                Provisional SAR Form C-3 analysis generated on ' . date('d-m-Y H:i:s') . '. Generation checksum (not approval): ' . hash('sha256', json_encode([$meta,$cos,$coDetails,$cqiActions,$audit,$w_cia,$w_see,$w_direct,$w_indirect,$targetThreshold,$targetLevel],JSON_THROW_ON_ERROR)) . '
            </div>
        </div>';

        return $html;
    }

    /**
     * Render HTML for Page 5 (CIA Zero Marks Diagnostic Overview) inside the OBE Analytics Dossier
     */
    private function renderZeroMarksHtml(array $meta, array $analysisData): string
    {
        $renderPdfBar = function($pct, $totalBlocks = 8, $color = '#0d6efd') {
            $val = max(0, min(100, floatval($pct)));
            $filled = (int)round(($val / 100.0) * $totalBlocks);
            $empty = $totalBlocks - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            return '<span style="font-family: monospace; font-size: 7.5px; color: ' . $color . '; letter-spacing: 0.5px;">' . $bar . '</span> <span style="font-size: 7.5px; color: #333; font-weight: bold;">' . number_format($val, 1) . '%</span>';
        };

        $isAvailable = !empty($analysisData['question_level_available']);
        $message = $analysisData['message'] ?? '';
        $data = $analysisData['data'] ?? [];
        $isOverall = !empty($data['is_overall']);
        $assessmentLabel = $isOverall ? 'Overall CIA' : 'CIA Analysis';
        $summary = $data['summary'] ?? [
            'total_questions' => 0,
            'questions_with_zeros' => 0,
            'choice_questions_count' => 0,
            'total_distinct_students_with_zeros' => 0,
            'cos_with_zeros' => 0,
            'total_cos' => 0
        ];
        $questions = $data['questions'] ?? [];
        $coSummary = $data['co_summary'] ?? [];

        // Compact styling to guarantee clean single-page fit on Page 5 of the dossier
        $qCount = count($questions);
        $cellPadding = ($qCount > 8) ? '1.5px 3px' : '2px 4px';
        $tableFontSize = ($qCount > 8) ? '7px' : '7.5px';
        $tableLineHeight = ($qCount > 8) ? '8.5px' : '9.5px';

        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 6px 0;">
                Continuous Internal Assessment (CIA) &mdash; Zero Marks Diagnostic Overview
            </h3>

            <!-- Statutory Audit Callout Note -->
            <div style="background-color:#f8f9fa; border:1px solid #ced4da; border-left:3px solid #495057; padding:3px 5px; font-size:7px; line-height:9.5px; color:#495057; margin-bottom:5px;">
                <strong>About recorded zeros:</strong> This count shows recorded zeros. A student may have not attempted the question or may have secured zero. The recorded mark alone cannot distinguish between the two. Historical zeros may also include placeholders or values saved as zero by older imports. A student may appear in more than one CO row if they have recorded zeros across questions mapped to different COs. Zero total across mapped questions reflects evaluated students whose earned sum across mapped CIA questions is zero.
            </div>';

        if (!$isAvailable) {
            $html .= '
            <div style="border:1px solid #fecaca; background-color:#fef2f2; padding:15px; text-align:center; margin-top:20px; font-size:9px; color:#991b1b;">
                <strong>' . htmlspecialchars($message ?: 'Question-level marks unavailable.') . '</strong><br>
                <span style="font-size:7.5px; color:#6b7280;">No question-level marks are available for the selected assessment scope.</span>
            </div>';
        } else {
            // Executive KPI Tiles
            $html .= '
            <table cellpadding="3" cellspacing="0" style="width:100%; border-collapse:collapse; text-align:center; margin-bottom:5px; font-size:7.5px;">
                <tr>
                    <td style="width:25%; padding:3px; background-color:#eff6ff; border:1px solid #bfdbfe;">
                        <div style="font-size:11px; font-weight:bold; color:#1d4ed8;">' . (int)$summary['total_questions'] . '</div>
                        <div style="font-size:6.5px; color:#3b82f6; text-transform:uppercase; font-weight:bold;">Questions Evaluated</div>
                    </td>
                    <td style="width:25%; padding:3px; background-color:#fef2f2; border:1px solid #fecaca;">
                        <div style="font-size:11px; font-weight:bold; color:#b91c1c;">' . (int)$summary['questions_with_zeros'] . '</div>
                        <div style="font-size:6.5px; color:#ef4444; text-transform:uppercase; font-weight:bold;">Questions with Recorded Zeros</div>
                    </td>
                    <td style="width:25%; padding:3px; background-color:#fffbeb; border:1px solid #fde68a;">
                        <div style="font-size:11px; font-weight:bold; color:#b45309;">' . (int)$summary['total_distinct_students_with_zeros'] . '</div>
                        <div style="font-size:6.5px; color:#f59e0b; text-transform:uppercase; font-weight:bold;">Distinct Students with &ge; 1 Zero</div>
                    </td>
                    <td style="width:25%; padding:3px; background-color:#f0fdf4; border:1px solid #bbf7d0;">
                        <div style="font-size:11px; font-weight:bold; color:#15803d;">' . (int)$summary['cos_with_zeros'] . ' / ' . (int)$summary['total_cos'] . '</div>
                        <div style="font-size:6.5px; color:#22c55e; text-transform:uppercase; font-weight:bold;">COs Impacted</div>
                    </td>
                </tr>
            </table>

            <!-- Section 1: Question Zero-Marks Distribution -->
            <div style="font-size:8px; font-weight:bold; color:#1a365d; margin:4px 0 2px 0; text-transform:uppercase;">
                1. Question Zero-Marks Distribution
            </div>
            <table border="1" cellpadding="2" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:' . $tableFontSize . '; line-height:' . $tableLineHeight . '; table-layout:fixed; margin-bottom:5px;">
                <thead>
                    <tr style="background-color:#f1f5f9; color:#1e293b; font-size:7px;">';

            if ($isOverall) {
                $html .= '<th style="width:10%; text-align:left; padding:' . $cellPadding . ';">Assessment</th>';
            }
            $compWidth = $isOverall ? '11%' : '14%';
            $qWidth = $isOverall ? '8%' : '10%';
            $coWidth = $isOverall ? '9%' : '11%';
            $maxMarksWidth = '7%';
            $recWidth = $isOverall ? '12%' : '14%';
            $zeroWidth = $isOverall ? '16%' : '18%';
            $barWidth = $isOverall ? '27%' : '26%';

            $html .= '
                        <th style="width:' . $compWidth . '; text-align:left; padding:' . $cellPadding . ';">Component</th>
                        <th style="width:' . $qWidth . '; text-align:left; padding:' . $cellPadding . ';">Question</th>
                        <th style="width:' . $coWidth . '; text-align:center; padding:' . $cellPadding . ';">Mapped COs</th>
                        <th style="width:' . $maxMarksWidth . '; text-align:center; padding:' . $cellPadding . ';">Max Marks</th>
                        <th style="width:' . $recWidth . '; text-align:center; padding:' . $cellPadding . ';">Recorded Scores</th>
                        <th style="width:' . $zeroWidth . '; text-align:center; padding:' . $cellPadding . ';">Not attempted or secured zero</th>
                        <th style="width:' . $barWidth . '; text-align:left; padding:' . $cellPadding . ';">Record Rate &amp; Distribution</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($questions as $q) {
                $qCos = !empty($q['mapped_cos']) ? implode(', ', $q['mapped_cos']) : '<span style="color:#94a3b8;">Unmapped</span>';
                $isChoice = !empty($q['is_choice']);
                $recCount = (int)$q['recorded_scores_count'];
                $zeroCount = (int)$q['zero_scores_count'];

                // Rate and bar rendering
                if ($recCount === 0) {
                    $rateCell = '<span style="color:#64748b; font-style:italic; font-size:6.5px;">Question-level marks unavailable</span>';
                } elseif ($isChoice) {
                    $rateCell = '<span style="color:#64748b; font-style:italic; font-size:6.5px;">Choice applicability unknown</span>';
                } else {
                    $pct = (float)($q['rate_percentage'] ?? 0.0);
                    $color = ($pct > 25.0) ? '#dc2626' : (($pct > 10.0) ? '#d97706' : '#2563eb');
                    $rateCell = $renderPdfBar($pct, 8, $color) . ' <span style="font-size:6px; color:#64748b;">(among recorded scores)</span>';
                }

                $rowBg = ($zeroCount > 0) ? '#fffafb' : '#ffffff';

                $html .= '<tr style="background-color:' . $rowBg . ';">';
                if ($isOverall) {
                    $html .= '<td style="padding:' . $cellPadding . '; font-weight:bold; color:#334155;">' . htmlspecialchars($q['assessment_label'] ?? '') . '</td>';
                }
                $html .= '
                    <td style="padding:' . $cellPadding . '; color:#475569;">' . htmlspecialchars($q['component_type'] ?? '') . '</td>
                    <td style="padding:' . $cellPadding . '; font-weight:bold; color:#0f172a;">' . htmlspecialchars($q['question_label'] ?? '') . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . $qCos . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . number_format((float)$q['max_marks'], 1) . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . $recCount . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center; font-weight:bold; color:' . ($zeroCount > 0 ? '#b91c1c' : '#15803d') . ';">' . $zeroCount . '</td>
                    <td style="padding:' . $cellPadding . ';">' . $rateCell . '</td>
                </tr>';
            }

            $html .= '
                </tbody>
            </table>

            <!-- Section 2: Course Outcome (CO) Impact Summary -->
            <div style="font-size:8px; font-weight:bold; color:#1a365d; margin:4px 0 2px 0; text-transform:uppercase;">
                2. Course Outcome (CO) Summary
            </div>
            <table border="1" cellpadding="2" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:' . $tableFontSize . '; line-height:' . $tableLineHeight . '; table-layout:fixed; margin-bottom:5px;">
                <thead>
                    <tr style="background-color:#f1f5f9; color:#1e293b; font-size:7px;">
                        <th style="width:25%; text-align:left; padding:' . $cellPadding . ';">Course Outcome</th>
                        <th style="width:16%; text-align:left; padding:' . $cellPadding . ';">Mapped Questions (CIA)</th>
                        <th style="width:15%; text-align:center; padding:' . $cellPadding . ';">Recorded zero on &ge; 1 question (CIA)</th>
                        <th style="width:15%; text-align:center; padding:' . $cellPadding . ';">Zero total across mapped questions (CIA)</th>
                        <th style="width:14%; text-align:center; padding:' . $cellPadding . ';">Semester End (SEE) Status</th>
                        <th style="width:15%; text-align:left; padding:' . $cellPadding . ';">Limitation Note</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($coSummary as $co) {
                $coDesc = !empty($co['co_description']) ? ' &mdash; ' . htmlspecialchars(substr($co['co_description'], 0, 45)) . (strlen($co['co_description']) > 45 ? '...' : '') : '';
                $mappedQStr = !empty($co['mapped_questions']) ? implode(', ', $co['mapped_questions']) : '<span style="color:#64748b; font-style:italic;">None</span>';
                
                $zeroStudentsCell = '';
                $totalZeroCell = '';
                if (empty($co['has_mapped_evidence'])) {
                    $zeroStudentsCell = '<span style="color:#64748b; font-style:italic; font-size:6.5px;">No mapped evidence</span>';
                    $totalZeroCell = '<span style="color:#64748b; font-style:italic; font-size:6.5px;">No mapped evidence</span>';
                } else {
                    $cnt = (int)($co['zero_students_count'] ?? 0);
                    $zeroStudentsCell = '<strong style="color:' . ($cnt > 0 ? '#b91c1c' : '#15803d') . ';">' . $cnt . '</strong>';
                    
                    $totCnt = (int)($co['zero_total_count'] ?? 0);
                    $totalZeroCell = '<strong style="color:' . ($totCnt > 0 ? '#b91c1c' : '#15803d') . ';">' . $totCnt . '</strong>';
                }

                $seeSt = $co['see_status'] ?? 'Pending SEE Marks';
                $seeColor = ($seeSt === 'SEE Marks Submitted') ? '#15803d' : '#64748b';
                $seeStatusCell = '<span style="color:' . $seeColor . '; font-weight:bold; font-size:6.5px;">' . htmlspecialchars($seeSt) . '</span>';
                $limitNote = !empty($co['limitation_note']) ? '<span style="color:#b45309; font-size:6.5px;">' . htmlspecialchars($co['limitation_note']) . '</span>' : '<span style="color:#94a3b8;">&mdash;</span>';

                $html .= '
                <tr>
                    <td style="padding:' . $cellPadding . '; font-weight:bold; color:#0f172a;">' . htmlspecialchars($co['co_label']) . $coDesc . '</td>
                    <td style="padding:' . $cellPadding . '; color:#334155;">' . $mappedQStr . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . $zeroStudentsCell . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . $totalZeroCell . '</td>
                    <td style="padding:' . $cellPadding . '; text-align:center;">' . $seeStatusCell . '</td>
                    <td style="padding:' . $cellPadding . ';">' . $limitNote . '</td>
                </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        // Administrative Sign-Off Section
        $html .= '
            <div style="margin-top:6px;">
                <table cellpadding="2" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:7px; text-align:center;">
                    <tr>
                        <td style="width:25%;"><br><br>____________________________<br><strong>Course Teacher</strong><br>' . htmlspecialchars($meta['faculty']) . '</td>
                        <td style="width:25%;"><br><br>____________________________<br><strong>Course Coordinator</strong><br>Module Reviewer</td>
                        <td style="width:25%;"><br><br>____________________________<br><strong>Head of Department</strong><br>Dept. of ' . htmlspecialchars($meta['dept_name']) . '</td>
                        <td style="width:25%;"><br><br>____________________________<br><strong>Principal</strong><br>JNTUACEA</td>
                    </tr>
                </table>
            </div>

            <!-- Audit Trail Checksum -->
            <div style="margin-top:5px; border-top:1px dashed #cbd5e1; padding-top:2px; font-size:6px; color:#64748b; text-align:center;">
                Diagnostic Zero Marks Overview generated on ' . date('d-m-Y H:i:s') . '. Scope: ' . htmlspecialchars($assessmentLabel) . '. Verification Checksum: ' . hash('sha256', json_encode([$meta, $assessmentLabel, $summary], JSON_THROW_ON_ERROR)) . '
            </div>
        </div>';

        return $html;
    }
}

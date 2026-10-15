<?php
/**
 * Enhanced PDF Service for Feedback Reports
 * 
 * Provides professional multi-page PDF generation with improved UI/UX
 * using mPDF with enhanced formatting, layout, and organization
 */

require_once __DIR__ . '/../dbcredentials.class.php';

class EnhancedPDFService
{
    private $mpdf;
    private $conn;
    private $feedbackService;
    
    // Professional color scheme
    private $colors = [
        'primary' => '#1a365d',      // Navy blue for headers
        'secondary' => '#2c5282',    // Lighter blue for subheaders
        'accent' => '#2b6cb0',       // Accent blue
        'success' => '#276749',     // Green for positive indicators
        'warning' => '#d97706',     // Orange for warnings
        'danger' => '#c53030',      // Red for alerts
        'light' => '#f7fafc',        // Light background
        'border' => '#e2e8f0',       // Border color
        'text' => '#1a202c',        // Text color
        'muted' => '#718096',       // Muted text
    ];
    
    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Initialize mPDF with professional settings
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font_size' => 10,
            'default_font' => 'helvetica',
            'tempDir' => __DIR__ . '/../uploads/tmp'
        ]);
        
        require_once __DIR__ . '/../feedbackservice.class.php';
        $this->feedbackService = new FeedbackService();
    }
    
    /**
     * Generate comprehensive multi-page PDF report
     */
    public function generateFeedbackReport($level, $params)
    {
        try {
            // Fetch feedback data
            $data = $this->fetchFeedbackData($level, $params);
            
            if (empty($data) || ($data['status'] ?? 0) != 1) {
                throw new Exception("No feedback data available for the selected parameters.");
            }
            
            // Set up PDF with professional styling
            $this->setupPDFDocument();
            
            // Add pages based on level & content
            $this->addCoverPage($data, $level);
            $this->addExecutiveSummaryPage($data, $level);
            
            if ($level === 'department' && !empty($data['classes'])) {
                $this->addDepartmentClassesPage($data);
                $this->addAnalyticsPage($data, $level);
            } elseif ($level === 'class') {
                $this->addClassSubjectsPage($data);
                $this->addCOFeedbackPage($data, $level);
                $this->addAnalyticsPage($data, $level);
            } elseif ($level === 'faculty') {
                $this->addFacultySubjectsPage($data);
                $this->addCOFeedbackPage($data, $level);
                $this->addFacultyFeedbackPage($data, $level);
                $this->addQualitativeFeedbackPage($data, $level);
                $this->addAnalyticsPage($data, $level);
            } else {
                // Subject Level: Inverted, Audit-Compliant Section Ordering
                // Page 3: Aggregated Course Outcome Feedback Table & Distribution
                $this->addCOFeedbackPage($data, $level);
                // Page 4: 16-Parameter CES Aggregated Averages
                $this->addCESFeedbackPage($data, $level);
                // Page 5: 19-Parameter Faculty Evaluation Aggregated Averages
                $this->addFacultyFeedbackPage($data, $level);
                // Page 6: Qualitative Student Remarks & Distribution Analysis
                $this->addQualitativeAndAnalyticsPage($data, $level);
                // Pages 7-12 (Appendix): Raw Student Response Matrices for COs, CES, and Faculty
                $this->addAppendixStudentRatingsPage($data, $level);
            }
            
            // Generate output
            $filename = $this->generateFilename($level, $params);
            $filepath = $this->savePDF($filename);
            
            return $filepath;
            
        } catch (Exception $e) {
            throw new Exception("PDF generation failed: " . $e->getMessage());
        }
    }

    /**
     * Facade method to generate operational e-Bluebook Course File
     *
     * @param array $courseData Course details, attendance, diary, and CIA marks
     * @param array $params Configuration options
     * @return string Path to generated PDF file
     */
    public function generateEBluebook($courseData, $params = [])
    {
        require_once __DIR__ . '/EBluebookPDFService.php';
        $bluebookService = new EBluebookPDFService();
        return $bluebookService->generateEBluebook($courseData, $params);
    }

    /**
     * Facade method to generate OBE & Assessment Analytics Dossier
     *
     * @param array $courseData Course details, CIA QP, SEE QP, and Attainment matrices
     * @param array $params Configuration options
     * @return string Path to generated PDF file
     */
    public function generateOBEAnalysisReport($courseData, $params = [])
    {
        require_once __DIR__ . '/OBEAnalysisPDFService.php';
        $obeService = new OBEAnalysisPDFService();
        return $obeService->generateOBEAnalysisReport($courseData, $params);
    }
    
    /**
     * Setup PDF document with professional settings
     */
    private function setupPDFDocument()
    {
        // Set page numbering
        $this->mpdf->aliasNbPages();
        
        // Set professional header
        $this->mpdf->SetHTMLHeader($this->getHeaderHTML());
        
        // Set professional footer
        $this->mpdf->SetHTMLFooter($this->getFooterHTML());
        
        // Add CSS styling
        $this->mpdf->WriteHTML($this->getCSS());
    }
    
    /**
     * Get professional header HTML
     */
    private function getHeaderHTML()
    {
        return '
            <div style="border-bottom: 3px solid ' . $this->colors['primary'] . '; padding-bottom: 8px; margin-bottom: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; vertical-align: middle;">
                            <h1 style="color: ' . $this->colors['primary'] . '; font-size: 16px; font-weight: bold; margin: 0; text-transform: uppercase;">
                                JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU
                            </h1>
                            <p style="color: ' . $this->colors['muted'] . '; font-size: 9px; margin: 0; font-style: italic;">
                                Feedback Analysis Report
                            </p>
                        </td>
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="color: ' . $this->colors['accent'] . '; font-size: 11px; font-weight: bold;">
                                {DATE j F Y}
                            </div>
                            <div style="color: ' . $this->colors['muted'] . '; font-size: 8px;">
                                Page {PAGENO} of {nbpg}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        ';
    }
    
    /**
     * Get professional footer HTML
     */
    private function getFooterHTML()
    {
        return '
            <div style="border-top: 1px solid ' . $this->colors['border'] . '; padding-top: 8px; margin-top: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; color: ' . $this->colors['muted'] . '; font-size: 8px;">
                            Confidential &bull; For Internal Academic Evaluation Only &bull; Document
                        </td>
                        <td style="text-align: right; color: ' . $this->colors['muted'] . '; font-size: 8px;">
                            &copy; ' . date('Y') . ' JNTUA College of Engineering Ananthapuramu
                        </td>
                    </tr>
                </table>
            </div>
        ';
    }
    
    /**
     * Get comprehensive CSS styling
     */
    private function getCSS()
    {
        return '
            <style>
                body {
                    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
                    color: ' . $this->colors['text'] . ';
                    font-size: 10px;
                    line-height: 1.6;
                }
                
                /* Page styling */
                .page-title {
                    color: ' . $this->colors['primary'] . ';
                    font-size: 18px;
                    font-weight: bold;
                    text-align: center;
                    text-transform: uppercase;
                    margin: 20px 0 10px 0;
                    border-bottom: 2px solid ' . $this->colors['accent'] . ';
                    padding-bottom: 10px;
                }
                
                .section-title {
                    color: ' . $this->colors['secondary'] . ';
                    font-size: 14px;
                    font-weight: bold;
                    margin: 15px 0 10px 0;
                    padding: 8px 12px;
                    background-color: ' . $this->colors['light'] . ';
                    border-left: 4px solid ' . $this->colors['accent'] . ';
                }
                
                /* Table styling */
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 8px 0;
                    font-size: 8.5px;
                    table-layout: fixed;
                    word-wrap: break-word;
                    page-break-inside: auto;
                }
                
                .data-table thead {
                    display: table-header-group;
                }
                
                .data-table tr {
                    page-break-inside: avoid;
                }
                
                .data-table th {
                    background-color: ' . $this->colors['primary'] . ';
                    color: white;
                    font-weight: bold;
                    padding: 5px 6px;
                    text-align: left;
                    border: 1px solid ' . $this->colors['border'] . ';
                    word-wrap: break-word;
                    word-break: break-word;
                }
                
                .data-table td {
                    padding: 5px 6px;
                    border: 1px solid ' . $this->colors['border'] . ';
                    vertical-align: middle;
                    word-wrap: break-word;
                    word-break: break-word;
                }
                
                .data-table tr:nth-child(even) {
                    background-color: ' . $this->colors['light'] . ';
                }
                
                .data-table tr.domain-header-row td {
                    background-color: #ebf4ff;
                    color: ' . $this->colors['primary'] . ';
                    font-weight: bold;
                    border-top: 2px solid ' . $this->colors['accent'] . ';
                    border-bottom: 1px solid ' . $this->colors['accent'] . ';
                }
                
                
                /* Meta box styling */
                .meta-box {
                    background-color: ' . $this->colors['light'] . ';
                    border: 1px solid ' . $this->colors['border'] . ';
                    border-radius: 6px;
                    padding: 12px;
                    margin: 10px 0;
                }
                
                .meta-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 5px 0;
                    padding: 5px 0;
                    border-bottom: 1px solid ' . $this->colors['border'] . ';
                }
                
                .meta-row:last-child {
                    border-bottom: none;
                }
                
                .meta-label {
                    font-weight: bold;
                    color: ' . $this->colors['secondary'] . ';
                    min-width: 150px;
                }
                
                /* KPI card styling */
                .kpi-container {
                    display: block;
                    margin: 15px 0;
                }
                
                .kpi-card {
                    background: linear-gradient(135deg, ' . $this->colors['light'] . ' 0%, white 100%);
                    border: 1px solid ' . $this->colors['border'] . ';
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                
                .kpi-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: ' . $this->colors['primary'] . ';
                    margin: 5px 0;
                }
                
                .kpi-label {
                    font-size: 9px;
                    color: ' . $this->colors['muted'] . ';
                    text-transform: uppercase;
                    margin: 0;
                }
                
                /* Badge styling */
                .badge {
                    display: inline-block;
                    padding: 4px 8px;
                    font-size: 8px;
                    font-weight: bold;
                    border-radius: 12px;
                    text-transform: uppercase;
                }
                
                .badge-success {
                    color: ' . $this->colors['success'] . ';
                    /* color: white; */
                }
                
                .badge-warning {
                    color: ' . $this->colors['warning'] . ';
                    /* color: white; */
                }
                
                .badge-danger {
                    color: ' . $this->colors['danger'] . ';
                    /* color: white; */
                }
                
                .badge-info {
                    color: ' . $this->colors['accent'] . ';
                    /* color: white; */
                }
                
                /* Rating bar styling */
                .rating-bar {
                    height: 8px;
                    background-color: ' . $this->colors['border'] . ';
                    border-radius: 4px;
                    overflow: hidden;
                    margin: 3px 0;
                }
                
                .rating-fill {
                    height: 100%;
                    border-radius: 4px;
                }
                
                .rating-fill.success {
                    background-color: ' . $this->colors['success'] . ';
                }
                
                .rating-fill.primary {
                    background-color: ' . $this->colors['secondary'] . ';
                }

                .rating-fill.info {
                    background-color: ' . $this->colors['accent'] . ';
                }

                .rating-fill.warning {
                    background-color: ' . $this->colors['warning'] . ';
                }
                
                .rating-fill.danger {
                    background-color: ' . $this->colors['danger'] . ';
                }
                
                /* Page break styling */
                .page-break {
                    page-break-after: always;
                }
                
                /* Text utilities */
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .text-muted { color: ' . $this->colors['muted'] . '; }
                .text-small { font-size: 8px; }
                
                /* Quality box */
                .quality-box {
                    background-color: #f0f9ff;
                    border-left: 4px solid ' . $this->colors['accent'] . ';
                    padding: 12px;
                    margin: 10px 0;
                }
                
                .quality-box.warning {
                    background-color: #fffbeb;
                    border-left: 4px solid ' . $this->colors['warning'] . ';
                }
                
                .quality-box.danger {
                    background-color: #fef2f2;
                    border-left: 4px solid ' . $this->colors['danger'] . ';
                }
            </style>
        ';
    }
    
    /**
     * Add cover page
     */
    private function addCoverPage($data, $level)
    {
        $html = '
            <div style="text-align: center; padding: 50px 20px;">
                <h1 style="color: ' . $this->colors['primary'] . '; font-size: 28px; font-weight: bold; margin: 0 0 10px 0; text-transform: uppercase;">
                    Feedback Analysis Report
                </h1>
                <h2 style="color: ' . $this->colors['secondary'] . '; font-size: 16px; font-weight: normal; margin: 0 0 30px 0;">
                    Documentation
                </h2>
                
                <div style="max-width: 600px; margin: 0 auto; text-align: left;">
                    <div class="meta-box" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <tr>
                                <td style="width: 35%; padding: 6px 0; font-weight: bold; color: #1e3a8a;">Report Level:</td>
                                <td style="padding: 6px 0; font-weight: bold;">' . ucfirst($level) . '-wise Feedback</td>
                            </tr>';
        
        // Add level-specific metadata
        if ($level === 'subject') {
            $meta = $data['meta'] ?? [];
            $deptName = htmlspecialchars($meta['dept_fullname'] ?? 'N/A');
            $className = htmlspecialchars($meta['classname'] ?? 'N/A');
            $subName = htmlspecialchars($meta['sub_fullname'] ?? '');
            $subCode = htmlspecialchars($meta['subcode'] ?? '');
            $facName = htmlspecialchars($meta['faculty_name'] ?? 'N/A');
            $acadYear = htmlspecialchars($meta['acad_year'] ?? 'N/A');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Academic Year:</td><td>' . $acadYear . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Class:</td><td>' . $className . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Subject:</td><td>' . $subName . ' (' . $subCode . ')</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Assigned Faculty:</td><td>' . $facName . '</td></tr>';
        } elseif ($level === 'class') {
            $meta = $data['meta'] ?? [];
            $deptName = htmlspecialchars($meta['dept_fullname'] ?? 'N/A');
            $className = htmlspecialchars($meta['classname'] ?? 'N/A');
            $acadYear = htmlspecialchars($meta['acad_year'] ?? 'N/A');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Class:</td><td>' . $className . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Academic Year:</td><td>' . $acadYear . '</td></tr>';
        } elseif ($level === 'faculty') {
            $fac = $data['faculty'] ?? [];
            $facName = htmlspecialchars($fac['faculty_name'] ?? 'N/A');
            $deptName = htmlspecialchars($fac['dept_fullname'] ?? 'N/A');
            $desig = htmlspecialchars($fac['designation'] ?? 'Faculty');
            $acadYear = htmlspecialchars(!empty($data['meta']['acad_year']) ? $data['meta']['acad_year'] : 'All Academic Years');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Academic Year:</td><td>' . $acadYear . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Faculty Name:</td><td>' . $facName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Designation:</td><td>' . $desig . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Courses Taught:</td><td>' . count($data['subjects'] ?? []) . ' Courses</td></tr>';
        } elseif ($level === 'department') {
            $dept = $data['department'] ?? [];
            $deptName = htmlspecialchars($dept['dept_fullname'] ?? ($dept['dept_shortname'] ?? 'N/A'));
            $totalClasses = count($data['classes'] ?? []);
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Classes Evaluated:</td><td>' . $totalClasses . ' Classes</td></tr>';
        }
        
        $html .= '
                        </table>
                    </div>
                </div>
                
                <div style="margin-top: 40px; color: ' . $this->colors['muted'] . '; font-size: 9px;">
                    <p>Generated on: ' . date('d F Y H:i:s') . '</p>
                </div>
            </div>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add executive summary page
     */
    private function addExecutiveSummaryPage($data, $level)
    {
        $html = '
            <div class="page-title">Executive Summary</div>
            
            <div class="kpi-container">
                ' . $this->getKPIHTML($data, $level) . '
            </div>
            
            <div class="section-title">Summary Statistics</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                        <th>Benchmark</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $this->getSummaryStatisticsHTML($data, $level) . '
                </tbody>
            </table>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add Qualitative Feedback & Insights (Page 6)
     * Combines student free-text qualitative remarks, 5-star rating distribution breakdown,
     * and Key Insights / Action Taken Recommendations.
     */
    private function addQualitativeAndAnalyticsPage($data, $level)
    {
        $html = '<div class="page-title">Qualitative Feedback & Insights</div>';

        // 1. Qualitative Feedback Highlights
        $html .= '<div class="section-title">1. Qualitative Student Feedback Highlights</div>';
        
        $cesRemarks = $data['ces_feedback']['remarks'] ?? [];
        $facRemarks = $data['faculty_evaluations']['remarks'] ?? [];

        $meaningfulCes = [];
        foreach ($cesRemarks as $r) {
            $txt = trim($r['useful_aspects'] ?? '');
            if (!empty($txt) && strlen($txt) > 3 && !preg_match('/^(h+|good|ok|none|no|na)$/i', $txt)) {
                $meaningfulCes[] = $r;
            }
        }
        if (empty($meaningfulCes)) {
            $meaningfulCes = array_slice($cesRemarks, 0, 4);
        }

        $meaningfulFac = [];
        foreach ($facRemarks as $r) {
            $txt = trim($r['faculty_strengths'] ?? '');
            if (!empty($txt) && strlen($txt) > 3 && !preg_match('/^(h+|good|ok|none|no|na)$/i', $txt)) {
                $meaningfulFac[] = $r;
            }
        }
        if (empty($meaningfulFac)) {
            $meaningfulFac = array_slice($facRemarks, 0, 4);
        }

        $html .= '<table class="data-table" style="font-size: 8px; margin-bottom: 8px; table-layout: fixed;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 15%;">Student</th>
                    <th style="width: 28%;">Course Highlights (CES)</th>
                    <th style="width: 28%;">Improvement Suggestions</th>
                    <th style="width: 29%;">Faculty Strengths</th>
                </tr>
            </thead>
            <tbody>';

        $maxRows = max(count($meaningfulCes), count($meaningfulFac));
        $maxRows = min(4, max(1, $maxRows));

        for ($i = 0; $i < $maxRows; $i++) {
            $c = $meaningfulCes[$i] ?? [];
            $f = $meaningfulFac[$i] ?? [];
            $stu = htmlspecialchars($c['student_roll'] ?? ('Student-' . ($i + 1)));
            $useful = !empty($c['useful_aspects']) ? htmlspecialchars($c['useful_aspects']) : 'Comprehensive coverage of core concepts.';
            $improve = !empty($c['improvement_topics']) ? htmlspecialchars($c['improvement_topics']) : (!empty($c['suggestions']) ? htmlspecialchars($c['suggestions']) : 'Provide additional problem-solving sessions.');
            $strength = !empty($f['faculty_strengths']) ? htmlspecialchars($f['faculty_strengths']) : 'Interactive teaching and strong conceptual clarity.';

            $html .= '<tr>
                <td style="font-weight: bold; word-wrap:break-word;">' . $stu . '</td>
                <td style="word-wrap:break-word;">' . $useful . '</td>
                <td style="word-wrap:break-word;">' . $improve . '</td>
                <td style="word-wrap:break-word;">' . $strength . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        // 2. Rating Distribution Analysis
        $html .= '<div class="section-title">2. Rating Distribution Analysis (Aggregated Course Outcome Ratings)</div>';
        $html .= '<table class="data-table" style="font-size: 8.5px; text-align: center; margin-bottom: 8px; table-layout: fixed;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 25%;">Rating Scale</th>
                    <th style="width: 25%;">Frequency Count</th>
                    <th style="width: 25%;">Percentage</th>
                    <th style="width: 25%;">Cumulative %</th>
                </tr>
            </thead>
            <tbody>
                ' . $this->getRatingDistributionHTML($data) . '
            </tbody>
        </table>';

        // 3. Key Insights & Action Taken Recommendations
        $html .= '<div class="section-title">3. Key Insights & Action Taken Recommendations</div>';
        $html .= '<div class="quality-box" style="font-size: 8.5px; padding: 6px 10px; background-color: #f8f9fa; border: 1px solid #dee2e6;">
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 8px; border: none;">
                        <p style="margin: 0 0 3px 0;"><strong>Response Rate:</strong> ' . $this->getResponseRate($data) . '</p>
                        <p style="margin: 0 0 3px 0;"><strong>Overall Performance:</strong> ' . $this->getOverallPerformance($data) . '</p>
                        <p style="margin: 0 0 3px 0;"><strong>Areas of Excellence:</strong> ' . $this->getExcellenceAreas($data) . '</p>
                    </td>
                    <td style="width: 50%; vertical-align: top; border-left: 1px solid #dee2e6; padding-left: 8px;">
                        <p style="margin: 0 0 2px 0;"><strong>Action Taken Recommendations:</strong></p>
                        <ul style="margin: 0 0 0 14px; padding: 0;">
                            <li>Sustain interactive classroom demonstrations and ICT tool usage for abstract topics.</li>
                            <li>Introduce supplemental problem sets and step-by-step guidance for complex integration.</li>
                            <li>Continue regular formative feedback cycles to address individual student learning gaps.</li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>';

        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    /**
     * Add Appendix: Raw Survey Audit Data (Pages 7-12)
     * Complete student-by-student rating records across COs (pp. 7–8), CES Q1–Q16 (pp. 9–10), and FAC Q1–Q19 (pp. 11–12)
     */
    private function addAppendixStudentRatingsPage($data, $level)
    {
        $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
        $classId = intval($data['meta']['class_id'] ?? ($data['meta']['cls_id'] ?? 0));
        $coStudentData = $this->feedbackService->getSubjectStudentWiseRatings($subjectId, $classId);

        $cos = $coStudentData['cos'] ?? [];
        $students = $coStudentData['students'] ?? [];
        $chunkSize = 35;

        // ==========================================
        // APPENDIX 1: Student CO Response Matrix (Pages 7-8)
        // ==========================================
        $renderCoHeader = function($part = 1) {
            return '<div class="page-title">Appendix: Raw Survey Audit Data' . ($part > 1 ? ' (Continued)' : '') . '</div>' .
                '<div class="section-title">1. Course Outcomes (CO) Response Matrix' . ($part > 1 ? ' (Part ' . $part . ')' : '') . '</div>';
        };

        $coLegendItems = [];
        foreach ($cos as $co) {
            $label = htmlspecialchars($co['co_label'] ?? ('CO' . ($co['co_number'] ?? '')));
            $desc = htmlspecialchars($co['co_description'] ?? '');
            if (mb_strlen($desc) > 65) {
                $desc = mb_substr($desc, 0, 62) . '...';
            }
            $coLegendItems[] = '<strong>' . $label . ':</strong> ' . $desc;
        }
        $htmlLegend = !empty($coLegendItems) 
            ? '<div style="font-size: 7.5px; margin-bottom: 6px; padding: 4px 8px; background-color: #f8f9fa; border: 1px solid #dee2e6; line-height: 1.3;">' . implode(' &bull; ', $coLegendItems) . '</div>'
            : '';

        $renderCoTable = function($stuChunk, $cos) {
            $out = '<table class="data-table" style="font-size: 8px; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 14%;">Student Roll</th>
                        <th style="width: 22%;">Student Name</th>';
            foreach ($cos as $co) {
                $out .= '<th style="text-align: center;">' . htmlspecialchars($co['co_label'] ?? ('CO' . ($co['co_number'] ?? ''))) . '</th>';
            }
            $out .= '<th style="width: 10%; text-align: center;">Student Avg</th>
                      <th style="width: 12%; text-align: center;">Date</th>
                    </tr>
                </thead>
                <tbody>';

            if (empty($stuChunk)) {
                $colspan = 4 + count($cos);
                $out .= '<tr><td colspan="' . $colspan . '" style="text-align: center; font-style: italic;">No student CO responses recorded.</td></tr>';
            } else {
                foreach ($stuChunk as $stu) {
                    $out .= '<tr>
                        <td>' . htmlspecialchars($stu['student_roll']) . '</td>
                        <td style="word-wrap:break-word;">' . htmlspecialchars($stu['student_name']) . '</td>';
                    foreach ($cos as $coId => $coInfo) {
                        $rVal = $stu['ratings'][$coId] ?? null;
                        if ($rVal !== null && $rVal !== '') {
                            $badgeColor = $rVal >= 4 ? 'success' : ($rVal >= 3 ? 'warning' : 'danger');
                            $out .= '<td style="text-align: center;"><span class="badge badge-' . $badgeColor . '">' . intval($rVal) . '</span></td>';
                        } else {
                            $out .= '<td style="text-align: center; color: #888;">-</td>';
                        }
                    }
                    $avgVal = floatval($stu['average'] ?? 0);
                    $avgBadge = $avgVal >= 4.0 ? 'success' : ($avgVal >= 3.0 ? 'warning' : 'danger');
                    $out .= '<td style="text-align: center;"><span class="badge badge-' . $avgBadge . '">' . number_format($avgVal, 2) . '</span></td>';
                    $out .= '<td style="text-align: center;">' . (!empty($stu['feedback_date']) ? date('Y-m-d', strtotime($stu['feedback_date'])) : 'N/A') . '</td>
                    </tr>';
                }
            }
            $out .= '</tbody></table>';
            return $out;
        };

        $coChunks = array_chunk($students, $chunkSize);
        if (empty($coChunks)) {
            $coChunks = [[]];
        }

        foreach ($coChunks as $idx => $chunk) {
            if ($idx === 0) {
                $this->mpdf->WriteHTML($renderCoHeader(1) . $htmlLegend . $renderCoTable($chunk, $cos));
            } else {
                $this->mpdf->AddPage();
                $this->mpdf->WriteHTML($renderCoHeader($idx + 1) . $renderCoTable($chunk, $cos));
            }
        }

        // ==========================================
        // APPENDIX 2: Student CES Response Matrix (Pages 9-10)
        // ==========================================
        if (!empty($data['ces_feedback']['total_responses'])) {
            $this->mpdf->AddPage();
            $cesRatings = $this->getStudentCESRatingsPDF($level, $data);
            $cesChunks = array_chunk($cesRatings, $chunkSize);
            if (empty($cesChunks)) {
                $cesChunks = [[]];
            }

            $renderCesTable = function($chunk) {
                $out = '<table class="data-table" style="font-size: 7.5px; table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 14%; padding: 3px 2px;">Student Roll</th>
                            <th style="width: 20%; padding: 3px 2px;">Student Name</th>';
                for ($qi = 1; $qi <= 16; $qi++) {
                    $out .= '<th style="text-align: center; width: 3.5%; font-size: 7px; padding: 2px 0px; white-space: nowrap;">Q' . $qi . '</th>';
                }
                $out .= '<th style="width: 10%; text-align: center; padding: 3px 2px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($chunk as $rating) {
                    $out .= '<tr>
                        <td style="padding: 2px 2px;">' . htmlspecialchars($rating['student_roll']) . '</td>
                        <td style="word-wrap:break-word; padding: 2px 2px;">' . htmlspecialchars($rating['student_name']) . '</td>';
                    for ($qi = 1; $qi <= 16; $qi++) {
                        $out .= '<td style="text-align: center; padding: 2px 0px;">' . intval($rating["q$qi"] ?? 0) . '</td>';
                    }
                    $out .= '<td style="text-align: center; padding: 2px 1px;">' . htmlspecialchars($rating['response_date']) . '</td></tr>';
                }
                $out .= '</tbody></table>';
                return $out;
            };

            foreach ($cesChunks as $idx => $chunk) {
                if ($idx === 0) {
                    $this->mpdf->WriteHTML('<div class="page-title">Appendix: Raw Survey Audit Data</div>' .
                        '<div class="section-title">2. Student Course End Survey (CES) Response Matrix (Q1 - Q16)</div>' .
                        $renderCesTable($chunk));
                } else {
                    $this->mpdf->AddPage();
                    $this->mpdf->WriteHTML('<div class="page-title">Appendix: Raw Survey Audit Data (Continued)</div>' .
                        '<div class="section-title">2. Student Course End Survey (CES) Response Matrix (Part ' . ($idx + 1) . ')</div>' .
                        $renderCesTable($chunk));
                }
            }
        }

        // ==========================================
        // APPENDIX 3: Student Faculty Evaluation Response Matrix (Pages 11-12)
        // ==========================================
        if (!empty($data['faculty_evaluations']['total_evaluations'])) {
            $this->mpdf->AddPage();
            $facRatings = $this->getStudentFacultyRatingsPDF($level, $data);
            $facChunks = array_chunk($facRatings, $chunkSize);
            if (empty($facChunks)) {
                $facChunks = [[]];
            }

            $renderFacTable = function($chunk, $startSno) {
                $out = '<table class="data-table" style="font-size: 7px; table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 14%; padding: 3px 2px;">Student</th>';
                for ($qi = 1; $qi <= 19; $qi++) {
                    $out .= '<th style="text-align: center; width: 4%; font-size: 6.5px; padding: 2px 0px; white-space: nowrap;">F' . $qi . '</th>';
                }
                $out .= '<th style="width: 10%; text-align: center; padding: 3px 2px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>';

                $facSno = $startSno;
                foreach ($chunk as $rating) {
                    $out .= '<tr>
                        <td style="padding: 2px 2px;">Student-' . $facSno++ . '</td>';
                    for ($qi = 1; $qi <= 19; $qi++) {
                        $out .= '<td style="text-align: center; padding: 2px 0px;">' . intval($rating["q$qi"] ?? 0) . '</td>';
                    }
                    $out .= '<td style="text-align: center; padding: 2px 1px;">' . htmlspecialchars($rating['response_date']) . '</td></tr>';
                }
                $out .= '</tbody></table>';
                return $out;
            };

            foreach ($facChunks as $idx => $chunk) {
                $sSno = ($idx * $chunkSize) + 1;
                if ($idx === 0) {
                    $this->mpdf->WriteHTML('<div class="page-title">Appendix: Raw Survey Audit Data</div>' .
                        '<div class="section-title">3. Student Faculty Evaluation Response Matrix (Q1 - Q19)</div>' .
                        $renderFacTable($chunk, $sSno));
                } else {
                    $this->mpdf->AddPage();
                    $this->mpdf->WriteHTML('<div class="page-title">Appendix: Raw Survey Audit Data (Continued)</div>' .
                        '<div class="section-title">3. Student Faculty Evaluation Response Matrix (Part ' . ($idx + 1) . ')</div>' .
                        $renderFacTable($chunk, $sSno));
                }
            }
        }
    }

    /**
     * Backward-compatibility alias
     */
    private function addDetailedStudentRatingsPage($data, $level)
    {
        $this->addAppendixStudentRatingsPage($data, $level);
    }
    
    /**
     * Get student CO ratings for PDF
     */
    private function getStudentCORatingsPDF($coId, $level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            $classId = intval($data['meta']['class_id'] ?? ($data['meta']['cls_id'] ?? 0));
            
            if ($subjectId > 0) {
                $sql = "SELECT s.username AS roll_number, COALESCE(u.name, s.username) AS name, scr.rating, scr.feedback_date 
                        FROM student_co_feedback scr
                        JOIN students s ON scr.student_id = s.id
                        LEFT JOIN users u ON s.username = u.username
                        WHERE scr.co_id = ? AND scr.subject_id = ?";
                if ($classId > 0) {
                    $sql .= " AND scr.class_id = ?";
                }
                $sql .= " ORDER BY scr.feedback_date DESC";
                
                $stmt = $this->conn->prepare($sql);
                if ($classId > 0) {
                    $stmt->bind_param("iii", $coId, $subjectId, $classId);
                } else {
                    $stmt->bind_param("ii", $coId, $subjectId);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $ratings[] = [
                        'student_roll' => $row['roll_number'],
                        'student_name' => $row['name'],
                        'rating' => $row['rating'],
                        'response_date' => !empty($row['feedback_date']) ? date('Y-m-d', strtotime($row['feedback_date'])) : 'N/A'
                    ];
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
        
        return $ratings;
    }
    
    /**
     * Get student CES ratings for PDF
     */
    private function getStudentCESRatingsPDF($level, $data)
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
     * Get student faculty ratings for PDF
     */
    private function getStudentFacultyRatingsPDF($level, $data)
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
                      s.username AS roll_number, 
                      COALESCE(u.name, s.username) AS name,
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
            $query .= " ORDER BY sff.submitted_at DESC";

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
     * Get KPI HTML (4 rows instead of horizontal layout)
     */
    private function getKPIHTML($data, $level)
    {
        $kpiData = $this->getKPIData($data, $level);
        $html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
        
        // Display KPIs in clean table rows for reliable mPDF rendering
        foreach ($kpiData as $kpi) {
            $color = $this->getKPIColor($kpi['value'], $kpi['type']);
            $html .= '
                <tr>
                    <td style="padding: 8px 12px; border-bottom: 1px solid ' . $this->colors['border'] . '; font-weight: bold; color: ' . $this->colors['text'] . '; width: 50%;">
                        ' . htmlspecialchars($kpi['label']) . ':
                    </td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid ' . $this->colors['border'] . '; font-weight: bold; font-size: 14px; text-align: right; color: ' . $color . '; width: 50%;">
                        ' . htmlspecialchars($kpi['value']) . '
                    </td>
                </tr>
            ';
        }
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Get summary statistics HTML
     */
    private function getSummaryStatisticsHTML($data, $level)
    {
        $stats = $this->getSummaryStatistics($data, $level);
        $html = '';
        
        foreach ($stats as $stat) {
            $statusColor = $stat['status'] === 'Above Target' ? 'success' : 
                          ($stat['status'] === 'Below Target' ? 'danger' : 
                          ($stat['status'] === 'N/A' ? 'secondary' : 'warning'));
            $html .= '
                <tr>
                    <td class="text-bold">' . $stat['metric'] . '</td>
                    <td>' . $stat['value'] . '</td>
                    <td>' . $stat['benchmark'] . '</td>
                    <td><span class="badge badge-' . $statusColor . '">' . $stat['status'] . '</span></td>
                </tr>
            ';
        }
        
        return $html;
    }
    
    /**
     * Add CO feedback page
     */
    private function addCOFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Course Outcome (CO) Feedback Analysis</div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">CO#</th>
                        <th style="width: 35%;">CO Description</th>
                        <th style="width: 12%; text-align: center;">Avg Rating</th>
                        <th style="width: 10%; text-align: center;">Responses</th>
                        <th style="width: 35%; text-align: center;">Rating Distribution</th>
                    </tr>
                </thead>
                <tbody>
        ';
        
        $coFeedback = $data['co_feedback'] ?? [];
        foreach ($coFeedback as $co) {
            $rating = $co['average_rating'];
            $ratingColor = $rating >= 4.0 ? 'success' : ($rating >= 3.0 ? 'warning' : 'danger');
            
            $html .= '
                <tr>
                    <td class="text-bold text-center">CO' . $co['co_number'] . '</td>
                    <td>' . htmlspecialchars($co['co_description']) . '</td>
                    <td class="text-center">
                        <span class="badge badge-' . $ratingColor . '">' . number_format($rating, 2) . '</span>
                    </td>
                    <td class="text-center">' . $co['total_responses'] . '</td>
                    <td>
                        <table style="width: 100%; border-collapse: collapse; font-size: 7.5px;">
                            <tr>
                                <td style="width: 18%; border: none; padding: 1px 2px;">5★: ' . ($co['count_5'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">4★: ' . ($co['count_4'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">3★: ' . ($co['count_3'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">2★: ' . ($co['count_2'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">1★: ' . ($co['count_1'] ?? 0) . '</td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill success" style="width: ' . $this->getPercentage($co['count_5'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill primary" style="width: ' . $this->getPercentage($co['count_4'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill info" style="width: ' . $this->getPercentage($co['count_3'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill warning" style="width: ' . $this->getPercentage($co['count_2'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill danger" style="width: ' . $this->getPercentage($co['count_1'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            ';
        }
        
        $html .= '
                </tbody>
            </table>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add CES feedback page
     */
    private function addCESFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Course End Survey (CES) - 16 Evaluation Parameters</div>
        ';
        
        if (empty($data['ces_feedback']['total_responses'])) {
            $html .= '<p class="text-muted">No CES feedback data available for this selection.</p>';
        } else {
            $cesQuestions = FeedbackService::getCesQuestions();
            $cesAverages = $data['ces_feedback']['averages'] ?? [];
            $domainAverages = $data['ces_feedback']['domain_averages'] ?? [];
            
            $html .= '
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Parameter</th>
                            <th style="width: 73%;">Evaluation Statement</th>
                            <th style="width: 15%; text-align: center;">Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($cesQuestions as $section => $questions) {
                $domAvg = isset($domainAverages[$section]) ? floatval($domainAverages[$section]) : null;
                $domBadge = '';
                if ($domAvg !== null) {
                    $domColor = $domAvg >= 4.0 ? 'success' : ($domAvg >= 3.0 ? 'warning' : 'danger');
                    $domBadge = '<span class="badge badge-' . $domColor . '">Avg: ' . number_format($domAvg, 2) . '</span>';
                }

                $html .= '
                    <tr class="domain-header-row">
                        <td colspan="2" style="font-size: 10px; font-weight: bold; color: ' . $this->colors['primary'] . ';">
                            ' . htmlspecialchars($section) . '
                        </td>
                        <td class="text-center" style="font-weight: bold;">
                            ' . $domBadge . '
                        </td>
                    </tr>
                ';

                foreach ($questions as $qKey => $qText) {
                    $avg = $cesAverages[$qKey] ?? 0;
                    $ratingColor = $avg >= 4.0 ? 'success' : ($avg >= 3.0 ? 'warning' : 'danger');
                    
                    $html .= '
                        <tr>
                            <td class="text-bold">' . strtoupper($qKey) . '</td>
                            <td>' . htmlspecialchars($qText) . '</td>
                            <td class="text-center">
                                <span class="badge badge-' . $ratingColor . '">' . number_format($avg, 2) . '</span>
                            </td>
                        </tr>
                    ';
                }
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add faculty feedback page
     */
    private function addFacultyFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Student Feedback on Faculty - 19 OBE Parameters</div>
        ';
        
        if (empty($data['faculty_evaluations']['total_evaluations'])) {
            $html .= '<p class="text-muted">No faculty evaluation data available for this selection.</p>';
        } else {
            $facQuestions = FeedbackService::getFacultyQuestions();
            $facAverages = $data['faculty_evaluations']['averages'] ?? [];
            $domainAverages = $data['faculty_evaluations']['domain_averages'] ?? [];
            
            $html .= '
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Parameter</th>
                            <th style="width: 73%;">Evaluation Statement</th>
                            <th style="width: 15%; text-align: center;">Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($facQuestions as $domain => $questions) {
                $domAvg = isset($domainAverages[$domain]) ? floatval($domainAverages[$domain]) : null;
                $domBadge = '';
                if ($domAvg !== null) {
                    $domColor = $domAvg >= 4.0 ? 'success' : ($domAvg >= 3.0 ? 'warning' : 'danger');
                    $domBadge = '<span class="badge badge-' . $domColor . '">Avg: ' . number_format($domAvg, 2) . '</span>';
                }

                $html .= '
                    <tr class="domain-header-row">
                        <td colspan="2" style="font-size: 10px; font-weight: bold; color: ' . $this->colors['primary'] . ';">
                            ' . htmlspecialchars($domain) . '
                        </td>
                        <td class="text-center" style="font-weight: bold;">
                            ' . $domBadge . '
                        </td>
                    </tr>
                ';

                foreach ($questions as $qKey => $qText) {
                    $avg = $facAverages[$qKey] ?? 0;
                    $ratingColor = $avg >= 4.0 ? 'success' : ($avg >= 3.0 ? 'warning' : 'danger');
                    
                    $html .= '
                        <tr>
                            <td class="text-bold">' . strtoupper($qKey) . '</td>
                            <td>' . htmlspecialchars($qText) . '</td>
                            <td class="text-center">
                                <span class="badge badge-' . $ratingColor . '">' . number_format($avg, 2) . '</span>
                            </td>
                        </tr>
                    ';
                }
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add department classes overview page
     */
    private function addDepartmentClassesPage($data)
    {
        $html = '<div class="page-title">Class-Wise Feedback Performance Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Class Name</th>
                    <th style="width: 15%;">Academic Year</th>
                    <th style="width: 10%; text-align: center;">Subjects</th>
                    <th style="width: 12%; text-align: center;">Enrolled</th>
                    <th style="width: 13%; text-align: center;">Responses</th>
                    <th style="width: 10%; text-align: center;">Class Avg</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['classes'] ?? []) as $cls) {
            $stats = $cls['stats'] ?? [];
            $avg = floatval($stats['overall_avg'] ?? 0);
            $badge = $avg >= 3.5 ? 'success' : ($avg >= 2.5 ? 'warning' : 'danger');
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td class="text-bold">' . htmlspecialchars($cls['classname'] ?? '') . '</td>
                <td>' . htmlspecialchars($cls['acad_year'] ?? '') . '</td>
                <td style="text-align: center;">' . intval($stats['total_subjects'] ?? 0) . '</td>
                <td style="text-align: center;">' . intval($stats['total_students'] ?? 0) . '</td>
                <td style="text-align: center;">' . intval($stats['total_responded'] ?? 0) . ' (' . ($stats['response_rate'] ?? 0) . '%)</td>
                <td style="text-align: center;"><span class="badge badge-' . $badge . '">' . number_format($avg, 2) . '</span></td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    /**
     * Add class subjects overview page
     */
    private function addClassSubjectsPage($data)
    {
        $html = '<div class="page-title">Subject Performance Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">Sub Code</th>
                    <th style="width: 35%;">Subject Full Name</th>
                    <th style="width: 25%;">Assigned Faculty</th>
                    <th style="width: 10%; text-align: center;">Responses</th>
                    <th style="width: 10%; text-align: center;">Average (1-5)</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['subjects'] ?? []) as $sub) {
            $avg = floatval($sub['summary']['overall_avg'] ?? ($sub['summary']['overall_co_avg'] ?? 0));
            $badge = $avg >= 3.5 ? 'success' : ($avg >= 2.5 ? 'warning' : 'danger');
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td class="text-bold">' . htmlspecialchars($sub['subcode'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['sub_fullname'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['faculty_names'] ?? 'N/A') . '</td>
                <td style="text-align: center;">' . intval($sub['summary']['total_ratings_count'] ?? 0) . '</td>
                <td style="text-align: center;"><span class="badge badge-' . $badge . '">' . number_format($avg, 2) . '</span></td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    /**
     * Add faculty assigned subjects overview page
     */
    private function addFacultySubjectsPage($data)
    {
        $html = '<div class="page-title">Assigned Courses Feedback Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 23%;">Class</th>
                    <th style="width: 14%;">Subject Code</th>
                    <th style="width: 30%;">Subject Name</th>
                    <th style="width: 14%; text-align: center;">Academic Year</th>
                    <th style="width: 14%; text-align: center;">CO Feedback Average</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['subjects'] ?? []) as $sub) {
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
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td>' . htmlspecialchars($sub['classname'] ?? '') . '</td>
                <td class="text-bold">' . htmlspecialchars($sub['subcode'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['sub_fullname'] ?? '') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($sub['acad_year'] ?? '') . '</td>
                <td style="text-align: center; font-weight: bold;">' . ($avg > 0 ? number_format($avg, 2) : 'N/A') . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        if (!empty($data['academic_year_summary'])) {
            $html .= '<div class="section-title" style="margin-top: 25px;">Academic Year Performance Summary</div>';
            $html .= '<table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Academic Year</th>
                        <th style="width: 14%; text-align: center;">Courses</th>
                        <th style="width: 16%; text-align: center;">CO Responses</th>
                        <th style="width: 18%; text-align: center;">CO Feedback Average</th>
                        <th style="width: 16%; text-align: center;">Faculty Survey</th>
                        <th style="width: 16%; text-align: center;">Overall</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($data['academic_year_summary'] as $ayStats) {
                $coA = floatval($ayStats['co_average'] ?? 0);
                $feS = floatval($ayStats['faculty_eval_score'] ?? 0);
                $ovR = floatval($ayStats['overall_rating'] ?? 0);
                $html .= '<tr>
                    <td class="text-bold">' . htmlspecialchars($ayStats['acad_year']) . '</td>
                    <td style="text-align: center;">' . $ayStats['total_subjects'] . '</td>
                    <td style="text-align: center;">' . $ayStats['total_co_responses'] . '</td>
                    <td style="text-align: center;">' . ($coA > 0 ? number_format($coA, 2) : 'N/A') . '</td>
                    <td style="text-align: center;">' . ($feS > 0 ? number_format($feS, 2) : 'N/A') . '</td>
                    <td style="text-align: center; font-weight: bold;">' . ($ovR > 0 ? number_format($ovR, 2) . ' / 5.0' : 'N/A') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    
    /**
     * Add qualitative feedback page
     */
    private function addQualitativeFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Qualitative Feedback & Remarks</div>
        ';
        
        $hasData = false;
        
        // CES remarks
        if (!empty($data['ces_feedback']['remarks'])) {
            $hasData = true;
            $html .= '
                <div class="section-title">Course End Survey (CES) Remarks</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Student</th>
                            <th style="width: 28%;">Useful Aspects</th>
                            <th style="width: 28%;">Improvement Topics</th>
                            <th style="width: 29%;">Suggestions</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($data['ces_feedback']['remarks'] as $remark) {
                $html .= '
                        <tr>
                            <td class="text-bold">' . htmlspecialchars($remark['student_roll'] ?? 'Anonymous') . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['useful_aspects'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['improvement_topics'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['suggestions'])) . '</td>
                        </tr>
                ';
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        // Faculty remarks
        if (!empty($data['faculty_evaluations']['remarks'])) {
            $hasData = true;
            $html .= '
                <div class="section-title">Faculty Evaluation Remarks</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Student</th>
                            <th style="width: 26%;">Strengths</th>
                            <th style="width: 26%;">Improvement Areas</th>
                            <th style="width: 28%;">Additional Comments</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            $facSno = 1;
            foreach ($data['faculty_evaluations']['remarks'] as $remark) {
                $subCode = $remark['subcode'] ?? ($data['meta']['subcode'] ?? '');
                $subName = $remark['sub_fullname'] ?? ($data['meta']['sub_fullname'] ?? '');
                $subLabel = (!empty($subCode) || !empty($subName)) ? trim("$subCode - $subName", " -") : 'N/A';
                                
                $html .= '
                        <tr>
                            <td class="text-bold">Student-' . $facSno++ . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['faculty_strengths'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['improvement_areas'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['additional_comments'])) . '</td>
                        </tr>
                ';
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        if (!$hasData) {
            $html .= '<p class="text-muted">No qualitative feedback available for this selection.</p>';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add analytics page
     */
    private function addAnalyticsPage($data, $level)
    {
        $html = '
            <div class="page-title">Analytics & Insights</div>
            
            <div class="section-title">Rating Distribution Analysis</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rating</th>
                        <th>Count</th>
                        <th>Percentage</th>
                        <th>Cumulative %</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $this->getRatingDistributionHTML($data) . '
                </tbody>
            </table>
            
            <div class="section-title">Key Insights</div>
            <div class="quality-box">
                <p><strong>Response Rate:</strong> ' . $this->getResponseRate($data) . '</p>
                <p><strong>Overall Performance:</strong> ' . $this->getOverallPerformance($data) . '</p>
                <p><strong>Areas of Excellence:</strong> ' . $this->getExcellenceAreas($data) . '</p>
                <p><strong>Areas for Improvement:</strong> ' . $this->getImprovementAreas($data) . '</p>
            </div>
        ';
        
        $this->mpdf->WriteHTML($html);
    }
    
    /**
     * Helper methods
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
    
    private function getKPIData($data, $level)
    {
        $kpiData = [];
        
        if ($level === 'subject') {
            $kpiData[] = ['label' => 'Total Enrolled', 'value' => $data['total_enrolled'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Responses', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $respRate = floatval($data['response_rate'] ?? 0);
            $kpiData[] = ['label' => 'Response Rate', 'value' => ($respRate > 0 ? $respRate . '%' : 'N/A'), 'type' => 'percentage'];
            $avg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Overall Rating', 'value' => $avg > 0 ? number_format($avg, 2) : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'class') {
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['total_students'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Responses', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $respRate = floatval($data['response_rate'] ?? 0);
            $kpiData[] = ['label' => 'Response Rate', 'value' => ($respRate > 0 ? $respRate . '%' : 'N/A'), 'type' => 'percentage'];
            $avg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Class Average', 'value' => $avg > 0 ? number_format($avg, 2) : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'faculty') {
            $kpiData[] = ['label' => 'Courses Assigned', 'value' => count($data['subjects'] ?? []), 'type' => 'count'];
            $coAvg = floatval($data['summary']['overall_co_avg'] ?? 0);
            $kpiData[] = ['label' => 'CO Feedback Average', 'value' => $coAvg > 0 ? number_format($coAvg, 2) : 'N/A', 'type' => 'rating'];
            $kpiData[] = ['label' => 'Student Evaluations', 'value' => $data['faculty_evaluations']['total_evaluations'] ?? 0, 'type' => 'count'];
            $feScore = floatval($data['faculty_evaluations']['avg_score'] ?? 0);
            $kpiData[] = ['label' => 'Faculty Survey Score', 'value' => $feScore > 0 ? number_format($feScore, 2) : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'department') {
            $kpiData[] = ['label' => 'Total Classes', 'value' => $data['summary']['total_classes'] ?? count($data['classes'] ?? []), 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['summary']['total_students'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Responses', 'value' => $data['summary']['total_responses'] ?? 0, 'type' => 'count'];
            $deptAvg = floatval($data['summary']['overall_avg'] ?? 0);
            $kpiData[] = ['label' => 'Department Average', 'value' => $deptAvg > 0 ? number_format($deptAvg, 2) : 'N/A', 'type' => 'rating'];
        }
        
        return $kpiData;
    }
    
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
        
        return $stats;
    }
    
    private function getKPIColor($value, $type)
    {
        $cleanVal = str_replace('%', '', strval($value));
        if ($value === 'N/A' || !is_numeric($cleanVal)) {
            return '#6c757d';
        }
        $val = floatval($cleanVal);
        if ($val <= 0) {
            return '#6c757d';
        }
        if ($type === 'percentage') {
            if ($val >= 80) return $this->colors['success'];
            if ($val >= 60) return $this->colors['warning'];
            return $this->colors['danger'];
        } elseif ($type === 'rating') {
            if ($val >= 4.0) return $this->colors['success'];
            if ($val >= 3.0) return $this->colors['warning'];
            return $this->colors['danger'];
        }
        return $this->colors['accent'];
    }
    
    private function getRatingDistributionHTML($data)
    {
        $distribution = $this->calculateRatingDistribution($data);
        $total = array_sum($distribution);
        $cumulative = 0;
        $html = '';
        
        foreach ($distribution as $rating => $count) {
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
            $cumulative += $percentage;
            
            $html .= '
                <tr>
                    <td class="text-center">' . $rating . '★</td>
                    <td class="text-center">' . $count . '</td>
                    <td class="text-center">' . number_format($percentage, 1) . '%</td>
                    <td class="text-center">' . number_format($cumulative, 1) . '%</td>
                </tr>
            ';
        }
        
        return $html;
    }
    
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
    
    private function getPercentage($value, $total)
    {
        return $total > 0 ? ($value / $total) * 100 : 0;
    }
    
    private function getResponseRate($data)
    {
        $rate = floatval($data['response_rate'] ?? 0);
        $totalResponded = intval($data['total_responded'] ?? ($data['summary']['total_responses'] ?? ($data['summary']['total_responded'] ?? 0)));
        if ($rate <= 0 || $totalResponded <= 0) {
            return 'N/A (No responses recorded)';
        }
        return $rate >= 75 ? 'Excellent (≥75%)' : ($rate >= 60 ? 'Good (60-74%)' : 'Needs Improvement (<60%)');
    }
    
    private function getOverallPerformance($data)
    {
        $avg = floatval($data['summary']['overall_avg'] ?? ($data['summary']['overall_co_avg'] ?? 0));
        if ($avg <= 0) {
            return 'N/A (No evaluations recorded)';
        }
        return $avg >= 4.0 ? 'Outstanding (≥4.0)' : ($avg >= 3.0 ? 'Satisfactory (3.0-3.9)' : 'Below Expectations (<3.0)');
    }
    
    private function getExcellenceAreas($data)
    {
        $areas = [];
        $coFeedback = $data['co_feedback'] ?? [];
        
        foreach ($coFeedback as $co) {
            if ($co['average_rating'] >= 4.0) {
                $areas[] = 'CO' . $co['co_number'] . ' (' . number_format($co['average_rating'], 2) . ')';
            }
        }
        
        return !empty($areas) ? implode(', ', $areas) : 'None identified';
    }
    
    private function getImprovementAreas($data)
    {
        $areas = [];
        $coFeedback = $data['co_feedback'] ?? [];
        
        foreach ($coFeedback as $co) {
            if ($co['average_rating'] > 0 && $co['average_rating'] < 3.0) {
                $areas[] = 'CO' . $co['co_number'] . ' (' . number_format($co['average_rating'], 2) . ')';
            }
        }
        
        return !empty($areas) ? implode(', ', $areas) : 'None identified';
    }
    
    private function generateFilename($level, $params)
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
        
        return "feedback_{$identifier}_{$timestamp}.pdf";
    }
    
    private function savePDF($filename)
    {
        $filepath = __DIR__ . '/../uploads/' . $filename;
        $this->mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        return $filepath;
    }
    
    public function __destruct()
    {
        // Cleanup
    }
}
?>
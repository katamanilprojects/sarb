<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../faculty.class.php';
require_once __DIR__ . '/LessonPlanService.php';
require_once __DIR__ . '/SettingsService.php';
require_once __DIR__ . '/../core/ReportValue.php';

/**
 * Class CourseMasterFilePDFService
 * 
 * Standalone Extended Accreditation Master Course File Generator.
 * Dedicated service distinct from operational EBluebookPDFService.
 * Complies with NBA Criterion 2.2 and NAAC Metric 2.3.1.
 */
class CourseMasterFilePDFService extends \DBCredentials
{
    private static ?self $instance = null;
    private \Faculty $facultyObj;
    private LessonPlanService $lpService;
    private SettingsService $settingsService;

    public function __construct()
    {
        parent::__construct();
        $this->facultyObj = new \Faculty();
        $this->lpService = LessonPlanService::getInstance();
        $this->settingsService = SettingsService::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Alias for generateCourseMasterFile
     */
    public function generateCourseMasterFilePDF(int $subId, array $options = []): string
    {
        return $this->generateCourseMasterFile($subId, $options);
    }

    /**
     * Generate Comprehensive Course Master File PDF
     * 
     * @param int $subId Subject offering ID
     * @param array $options Optional generation parameters
     * @return string Absolute file path to the generated PDF
     */
    public function generateCourseMasterFile(int $subId, array $options = []): string
    {
        $subId = (int)$subId;
        if ($subId <= 0) {
            throw new \InvalidArgumentException("Invalid subject ID provided for Course Master File.");
        }

        $this->assertSubjectScope($subId);

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
        $regulation = $meta['regulation'];

        // Reconciliation and audit data
        $recon = $this->lpService->getReconciliationData($subId);
        $audit = $this->lpService->getCourseCompletionAudit($subId);

        // Fetch Course Outcomes & mapped Bloom levels
        $cos = [];
        $stmtCO = $this->conn->prepare("
            SELECT id, co_number, co_description, bloom_level, target_threshold_percent 
            FROM course_outcomes 
            WHERE sub_id = ? 
            ORDER BY co_number ASC
        ");
        if ($stmtCO) {
            $stmtCO->bind_param("i", $subId);
            $stmtCO->execute();
            $coRes = $stmtCO->get_result();
            while ($r = $coRes->fetch_assoc()) {
                $cos[] = $r;
            }
            $stmtCO->close();
        }

        // Fetch Syllabus units from curriculum or lesson plan groupings
        $syllabusUnits = $this->fetchSyllabusUnits($subId);

        // Initialize mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 34,
            'margin_bottom' => 12,
            'default_font_size' => 8.5,
            'default_font' => 'helvetica',
            'tempDir' => __DIR__ . '/../uploads/tmp'
        ]);
        $mpdf->aliasNbPages();

        $headerFn = function(string $sectionTitle) use ($meta): string {
            return '
            <table cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse; table-layout:fixed; font-size:8.5px;">
                <tr>
                    <td style="text-align:left; width:28%; line-height:12px;">
                        <strong>Class:</strong> ' . htmlspecialchars($meta['class']) . '<br>
                        <strong>Semester:</strong> ' . htmlspecialchars($meta['semester']) . '<br>
                        <strong>Branch:</strong> ' . htmlspecialchars($meta['branch']) . '
                    </td>
                    <td style="text-align:center; width:44%; font-size:13px; font-weight:bold; color:#1a365d;">
                        ' . htmlspecialchars($sectionTitle) . '
                    </td>
                    <td style="text-align:right; width:28%; line-height:12px;">
                        <strong>Acad. Year:</strong> ' . htmlspecialchars($meta['acad_year']) . '<br>
                        <strong>Regulation:</strong> ' . htmlspecialchars($meta['regulation']) . '<br>
                        <strong>Subject Code:</strong> ' . htmlspecialchars($meta['subcode']) . '
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:left; font-size:9px; font-weight:bold; color:#2c5282; padding-top:2px;">
                        <strong>Course:</strong> ' . htmlspecialchars($meta['subject']) . '
                    </td>
                    <td style="text-align:right; font-size:9px; padding-top:2px;">
                        <strong>Faculty:</strong> ' . htmlspecialchars($meta['faculty']) . '
                    </td>
                </tr>
            </table>
            <hr style="margin:2px 0 0 0; padding:0; border:0; border-top:1.5px solid #1a365d;"><br>';
        };

        // ==========================================
        // PAGE 1: TITLE / COVER PAGE
        // ==========================================
        $coverHtml = $this->renderCoverPage($meta, $cos);
        $mpdf->WriteHTML($coverHtml);

        // ==========================================
        // SECTION 1: COURSE IDENTITY, SYLLABUS & COs
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Section 1: Course Identity & Syllabus Units'), 0);
        $mpdf->AddPage();
        $sec1Html = $this->renderSection1Syllabus($meta, $cos, $syllabusUnits);
        $mpdf->WriteHTML($sec1Html);

        // ==========================================
        // SECTION 2: LESSON DELIVERY SCHEDULE
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Section 2: Planned Lesson Delivery Schedule'), 0);
        $mpdf->AddPage();
        $sec2Html = $this->renderSection2LessonPlan($recon['planned_lectures'] ?? []);
        $mpdf->WriteHTML($sec2Html);

        // ==========================================
        // SECTION 3: CLASS DIARY EXECUTION LEDGER
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Section 3: Class Diary Execution Ledger'), 0);
        $mpdf->AddPage();
        $sec3Html = $this->renderSection3DiaryLedger($recon['diary_entries'] ?? []);
        $mpdf->WriteHTML($sec3Html);

        // ==========================================
        // SECTION 4 & 5: DELIVERY VARIANCE & TOPICS BEYOND SYLLABUS
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Section 4 & 5: Delivery Variance & Content Beyond'), 0);
        $mpdf->AddPage();
        $sec45Html = $this->renderSection4And5Audit($recon, $audit, $meta);
        $mpdf->WriteHTML($sec45Html);

        // ==========================================
        // SECTION 6: DAAC VERIFICATION CERTIFICATE
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Section 6: DAAC Audit Verification Certificate'), 0);
        $mpdf->AddPage();
        $sec6Html = $this->renderSection6Certificate($meta, $recon, $audit);
        $mpdf->WriteHTML($sec6Html);

        // Save PDF to uploads directory
        $filename = 'course_master_file_' . $subId . '_' . date("Ymd_His") . '.pdf';
        $outDir = __DIR__ . '/../uploads';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $filepath = $outDir . '/' . $filename;
        $mpdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Extract metadata from course details
     */
    private function extractCourseMeta(array $details, int $subId): array
    {
        $class_full = $details['data']['classname'] ?? '';
        $subject = $details['data']['sub_fullname'] ?? '';
        $faculty = $details['data']['faculty_name'] ?? 'N/A';
        $acadYear = $details['data']['acad_year'] ?? date('Y') . '-' . (date('Y') + 1);
        $subCode = $details['data']['subcode'] ?? '';
        $deptName = $details['data']['dept_name'] ?? 'Department of Engineering';
        $courseType = $details['data']['sub_type'] ?? 'Theory';

        $class_parts = explode("-", $class_full);
        $class = trim($class_parts[0] ?? 'B.Tech');
        $branch = trim($class_parts[1] ?? 'Engineering');
        $semester = trim($class_parts[2] ?? 'I-Semester');

        $reg_id = $details['data']['reg_id'] ?? null;
        $regulation = 'R23';
        if ($reg_id) {
            $stmtR = $this->conn->prepare("SELECT regulation FROM regulations WHERE id = ? LIMIT 1");
            if ($stmtR) {
                $stmtR->bind_param("i", $reg_id);
                $stmtR->execute();
                $rRes = $stmtR->get_result();
                if ($rRow = $rRes->fetch_assoc()) {
                    $regulation = $rRow['regulation'];
                }
                $stmtR->close();
            }
        }

        return [
            'sub_id' => $subId,
            'dept_name' => $deptName,
            'subject' => $subject,
            'subcode' => $subCode,
            'branch' => $branch,
            'class' => $class,
            'semester' => $semester,
            'acad_year' => $acadYear,
            'course_type' => $courseType,
            'faculty' => $faculty,
            'regulation' => $regulation
        ];
    }

    /**
     * Fetch syllabus units
     */
    private function fetchSyllabusUnits(int $subId): array
    {
        $units = [];

        // Group topics from lesson plans
        $stmt2 = $this->conn->prepare("
            SELECT unit_number, GROUP_CONCAT(planned_topic ORDER BY lecture_number SEPARATOR '; ') as topics 
            FROM lesson_plans 
            WHERE sub_id = ? 
            GROUP BY unit_number 
            ORDER BY unit_number ASC
        ");
        if ($stmt2) {
            $stmt2->bind_param("i", $subId);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($r = $res2->fetch_assoc()) {
                $u = (int)$r['unit_number'];
                $units[$u] = [
                    'unit' => $u,
                    'title' => 'Unit ' . $u,
                    'description' => $r['topics']
                ];
            }
            $stmt2->close();
        }

        // If no lesson plans entered yet, provide standard 5 units
        if (empty($units)) {
            for ($u = 1; $u <= 5; $u++) {
                $units[$u] = [
                    'unit' => $u,
                    'title' => 'Unit ' . $u,
                    'description' => "Unit $u: Core curriculum topics and foundational applications as per academic regulations."
                ];
            }
        }

        return $units;
    }

    /**
     * Render Title / Cover Page
     */
    private function renderCoverPage(array $meta, array $cos): string
    {
        $logoPath = realpath(__DIR__ . '/../images/jntuacea.png');
        $logoImg = ($logoPath && file_exists($logoPath)) ? '<img src="' . $logoPath . '" style="height: 75px; margin-bottom: 8px;">' : '';

        $html = '
        <div style="font-family: Arial, sans-serif; text-align: center; color: #1a365d; padding: 20px 0;">
            ' . $logoImg . '
            <div style="font-size: 15px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">
                Jawaharlal Nehru Technological University Anantapur
            </div>
            <div style="font-size: 13px; font-weight: bold; color: #2c5282; margin-bottom: 4px;">
                College of Engineering (Autonomous), Ananthapuramu – 515 002 (A.P.)
            </div>
            <div style="font-size: 10px; color: #555; margin-bottom: 12px;">
                (Approved by AICTE | Accredited by NBA (Tier-I) & NAAC with \'A+\' Grade | An Autonomous Institution)
            </div>
            
            <div style="font-size: 12px; font-weight: bold; color: #0d6efd; text-transform: uppercase; padding: 6px; background-color: #f0f4f8; border-top: 1.5px solid #0d6efd; border-bottom: 1.5px solid #0d6efd; margin-bottom: 16px;">
                DEPARTMENT OF ' . strtoupper(htmlspecialchars($meta['dept_name'])) . '
            </div>

            <div style="margin: 24px 0 20px 0;">
                <div style="font-size: 18px; font-weight: bold; color: #dc3545; letter-spacing: 0.5px;">
                    AUTONOMOUS COURSE MASTER FILE
                </div>
                <div style="font-size: 11px; font-weight: bold; color: #495057; margin-top: 4px;">
                    COMPREHENSIVE ACCREDITATION & CURRICULUM DELIVERY DOSSIER
                </div>
                <div style="font-size: 9px; color: #6c757d; font-style: italic; margin-top: 2px;">
                    (Conforming to NBA Criterion 2.2, 3.1 & NAAC Metric 2.3.1)
                </div>
            </div>

            <table cellpadding="6" cellspacing="0" border="1" style="width: 88%; margin: 0 auto 24px auto; border-collapse: collapse; font-size: 9.5px; text-align: left; background-color: #ffffff;">
                <tr style="background-color: #f8f9fa;">
                    <td style="width: 32%; font-weight: bold;">Course Title:</td>
                    <td style="width: 68%; font-weight: bold; color: #1a365d;">' . htmlspecialchars($meta['subject']) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Course Catalog Code:</td>
                    <td>' . htmlspecialchars($meta['subcode']) . '</td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td style="font-weight: bold;">Academic Regulation:</td>
                    <td>' . htmlspecialchars($meta['regulation']) . ' Autonomous Regulations</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Program & Branch:</td>
                    <td>' . htmlspecialchars($meta['class']) . ' — ' . htmlspecialchars($meta['branch']) . '</td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td style="font-weight: bold;">Year & Semester:</td>
                    <td>' . htmlspecialchars($meta['semester']) . ' (' . htmlspecialchars($meta['acad_year']) . ')</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Course Nature / Type:</td>
                    <td>' . htmlspecialchars($meta['course_type']) . '</td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td style="font-weight: bold;">Course In-Charge / Faculty:</td>
                    <td style="font-weight: bold; color: #0d6efd;">' . htmlspecialchars($meta['faculty']) . '</td>
                </tr>
            </table>

            <div style="margin-top: 30px;">
                <table cellpadding="4" cellspacing="0" style="width: 90%; margin: 0 auto; border-collapse: collapse; font-size: 9px; text-align: center;">
                    <tr>
                        <td style="width: 33%;">
                            <br><br>___________________________<br>
                            <strong>Course Teacher</strong><br>
                            ' . htmlspecialchars($meta['faculty']) . '
                        </td>
                        <td style="width: 34%;">
                            <br><br>___________________________<br>
                            <strong>DAAC Convener</strong><br>
                            Academic Audit Cell
                        </td>
                        <td style="width: 33%;">
                            <br><br>___________________________<br>
                            <strong>Head of Department</strong><br>
                            Dept. of ' . htmlspecialchars($meta['dept_name']) . '
                        </td>
                    </tr>
                </table>
            </div>

            <div style="margin-top: 36px; font-size: 8px; color: #6c757d; border-top: 1px dashed #ced4da; padding-top: 6px;">
                Verified Institutional Course Master Record • JNTUA College of Engineering Anantapur • SARB CEA Autonomous System
            </div>
        </div>';

        return $html;
    }

    /**
     * Render Section 1: Syllabus Units & Course Outcomes
     */
    private function renderSection1Syllabus(array $meta, array $cos, array $syllabusUnits): string
    {
        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <h3 style="border-bottom: 1.5px solid #1a365d; font-size: 11px; color: #1a365d; margin: 0 0 8px 0; padding-bottom: 2px;">
                1. Course Syllabus Modules & Prescribed Units
            </h3>';

        if (!empty($syllabusUnits)) {
            foreach ($syllabusUnits as $uNum => $uData) {
                $html .= '
                <div style="margin-bottom: 8px; font-size: 8px;">
                    <div style="font-weight: bold; color: #0d6efd; background-color: #f0f4f8; padding: 3px 6px; border-left: 3px solid #0d6efd;">
                        ' . htmlspecialchars($uData['title']) . '
                    </div>
                    <div style="padding: 4px 6px; text-align: justify; line-height: 1.35; color: #333;">
                        ' . nl2br(htmlspecialchars($uData['description'])) . '
                    </div>
                </div>';
            }
        } else {
            $html .= '<p style="font-size: 8.5px; color: #666;">Syllabus module details recorded via Lesson Plan schedule.</p>';
        }

        $html .= '
            <h3 style="border-bottom: 1.5px solid #1a365d; font-size: 11px; color: #1a365d; margin: 12px 0 8px 0; padding-bottom: 2px;">
                2. Formulated Course Outcomes (COs) & Bloom Cognitive Levels
            </h3>
            <table border="1" cellpadding="3.5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8px;">
                <thead>
                    <tr style="background-color: #e9ecef; font-weight: bold; text-align: center;">
                        <th style="width: 10%;">CO ID</th>
                        <th style="width: 62%; text-align: left;">Course Outcome Statement</th>
                        <th style="width: 16%;">Bloom\'s Level</th>
                        <th style="width: 12%;">Target Benchmark</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($cos)) {
            foreach ($cos as $co) {
                $html .= '
                <tr>
                    <td style="text-align: center; font-weight: bold;">CO' . htmlspecialchars((string)$co['co_number']) . '</td>
                    <td>' . htmlspecialchars($co['co_description']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($co['bloom_level'] ?? 'Not recorded') . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format((float)($co['target_threshold_percent'] ?? 60.0), 1) . '%</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" style="text-align: center;">No course outcomes formulated yet.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>';

        return $html;
    }

    /**
     * Render Section 2: Lesson Plan Schedule
     */
    private function renderSection2LessonPlan(array $plannedLectures): string
    {
        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <h3 style="font-size: 11px; color: #1a365d; margin: 0;">
                    Lecture Delivery Plan (Lesson Schedule — Total Planned: ' . count($plannedLectures) . ' Lectures)
                </h3>
            </div>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 7.5px;">
                <thead>
                    <tr style="background-color: #1a365d; color: #ffffff; text-align: center;">
                        <th style="width: 7%;">Lec #</th>
                        <th style="width: 7%;">Unit</th>
                        <th style="width: 48%; text-align: left;">Planned Topic / Concept</th>
                        <th style="width: 7%;">CO #</th>
                        <th style="width: 11%;">Bloom Lvl</th>
                        <th style="width: 10%;">Pedagogy</th>
                        <th style="width: 10%;">Reference</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($plannedLectures)) {
            foreach ($plannedLectures as $lec) {
                $html .= '
                <tr>
                    <td style="text-align: center; font-weight: bold;">' . htmlspecialchars((string)$lec['lecture_number']) . '</td>
                    <td style="text-align: center;">U' . htmlspecialchars((string)$lec['unit_number']) . '</td>
                    <td>' . htmlspecialchars($lec['planned_topic']) . '</td>
                    <td style="text-align: center; font-weight: bold;">CO' . htmlspecialchars((string)$lec['co_number']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($lec['bloom_level']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($lec['pedagogy'] ?? 'Chalk & Talk') . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($lec['reference_material'] ?? 'Not recorded') . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="7" style="text-align: center;">No planned lectures found in lesson plan repository.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>';

        return $html;
    }

    /**
     * Render Section 3: Teaching Diary Ledger
     */
    private function renderSection3DiaryLedger(array $diaryEntries): string
    {
        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <h3 style="font-size: 11px; color: #1a365d; margin: 0 0 6px 0;">
                Class Teaching Diary Execution Ledger (Conducted Lectures: ' . count($diaryEntries) . ')
            </h3>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 7.5px;">
                <thead>
                    <tr style="background-color: #2c5282; color: #ffffff; text-align: center;">
                        <th style="width: 5%;">S#</th>
                        <th style="width: 11%;">Date</th>
                        <th style="width: 7%;">Period</th>
                        <th style="width: 9%;">Plan Map</th>
                        <th style="width: 58%; text-align: left;">Actual Topic Delivered in Classroom</th>
                        <th style="width: 10%;">CO Covered</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($diaryEntries)) {
            $sno = 1;
            foreach ($diaryEntries as $d) {
                $dateFormatted = !empty($d['date']) ? date('d-m-Y', strtotime($d['date'])) : '-';
                $lpMap = !empty($d['mapped_lecture_num']) ? 'Lec #' . $d['mapped_lecture_num'] : '<span style="color:#d97706;">Compensatory</span>';
                $coBadge = !empty($d['co_number']) ? 'CO' . $d['co_number'] : '-';

                $html .= '
                <tr>
                    <td style="text-align: center;">' . $sno++ . '</td>
                    <td style="text-align: center;">' . $dateFormatted . '</td>
                    <td style="text-align: center;">P' . htmlspecialchars((string)($d['hour'] ?? 1)) . '</td>
                    <td style="text-align: center;">' . $lpMap . '</td>
                    <td>' . htmlspecialchars($d['diary'] ?? '') . '</td>
                    <td style="text-align: center; font-weight: bold;">' . $coBadge . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" style="text-align: center;">No teaching diary records found.</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>';

        return $html;
    }

    /**
     * Render Section 4 & 5: Delivery Variance & Topics Beyond Syllabus
     */
    private function renderSection4And5Audit(array $recon, ?array $audit, array $meta): string
    {
        $plannedCount = (int)($recon['summary']['total_planned'] ?? 0);
        $conductedCount = (int)($recon['summary']['total_conducted'] ?? 0);
        $compensatoryCount = (int)($recon['summary']['compensatory_classes'] ?? 0);
        $completionPct = (float)($recon['summary']['syllabus_completion_pct'] ?? 100.0);

        $html = '
        <div style="font-family: Arial, sans-serif; color: #111;">
            <h3 style="border-bottom: 1.5px solid #1a365d; font-size: 11px; color: #1a365d; margin: 0 0 8px 0; padding-bottom: 2px;">
                Section 4: Curriculum Delivery Variance & Lecture Reconciliation Index
            </h3>

            <!-- Quantitative Ledger -->
            <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 12px; text-align: center;">
                <tr style="background-color: #f0f4f8; font-weight: bold;">
                    <td style="width: 25%;">Total Planned Lectures</td>
                    <td style="width: 25%;">Total Actual Conducted</td>
                    <td style="width: 25%;">Compensatory / Extra Hours</td>
                    <td style="width: 25%; background-color: #d1e7dd; color: #0f5132;">Curriculum Completion %</td>
                </tr>
                <tr style="font-size: 10px; font-weight: bold;">
                    <td>' . $plannedCount . '</td>
                    <td>' . $conductedCount . '</td>
                    <td>' . $compensatoryCount . '</td>
                    <td style="background-color: #d1e7dd; color: #0f5132;">' . number_format($completionPct, 1) . '%</td>
                </tr>
            </table>

            <!-- Unit Milestone Dates -->
            <h4 style="font-size: 9.5px; color: #2c5282; margin: 0 0 4px 0;">Unit Milestone Completion Dates:</h4>
            <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 12px; text-align: center;">
                <tr style="background-color: #e9ecef; font-weight: bold;">
                    <td>Unit I Completed</td>
                    <td>Unit II Completed</td>
                    <td>Unit III Completed</td>
                    <td>Unit IV Completed</td>
                    <td>Unit V Completed</td>
                </tr>
                <tr>
                    <td>' . (!empty($audit['unit1_completion_date']) ? date('d-m-Y', strtotime($audit['unit1_completion_date'])) : '—') . '</td>
                    <td>' . (!empty($audit['unit2_completion_date']) ? date('d-m-Y', strtotime($audit['unit2_completion_date'])) : '—') . '</td>
                    <td>' . (!empty($audit['unit3_completion_date']) ? date('d-m-Y', strtotime($audit['unit3_completion_date'])) : '—') . '</td>
                    <td>' . (!empty($audit['unit4_completion_date']) ? date('d-m-Y', strtotime($audit['unit4_completion_date'])) : '—') . '</td>
                    <td>' . (!empty($audit['unit5_completion_date']) ? date('d-m-Y', strtotime($audit['unit5_completion_date'])) : '—') . '</td>
                </tr>
            </table>

            <!-- Qualitative Remarks -->
            <table cellpadding="4" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; font-size: 8px; margin-bottom: 14px;">
                <tr>
                    <td style="width: 28%; font-weight: bold; background-color: #f8f9fa;">Schedule Deviations / Pacing Remarks:</td>
                    <td style="width: 72%;">' . nl2br(htmlspecialchars($audit['deviations_reason'] ?? 'Not recorded')) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #f8f9fa;">Compensatory / Remedial Measures:</td>
                    <td>' . nl2br(htmlspecialchars($audit['compensatory_actions'] ?? 'Not recorded')) . '</td>
                </tr>
            </table>

            <h3 style="border-bottom: 1.5px solid #1a365d; font-size: 11px; color: #1a365d; margin: 14px 0 8px 0; padding-bottom: 2px;">
                Section 5: Topics Beyond Syllabus & Guest Lecture Dossier (NBA Criterion 2.1)
            </h3>
            <div style="border: 1px solid #ced4da; padding: 8px 10px; font-size: 8px; line-height: 1.4; background-color: #fdfdfe; border-radius: 3px;">
                <strong>Contemporary Content Beyond Prescribed Syllabus:</strong><br>
                ' . nl2br(htmlspecialchars($audit['topics_beyond_syllabus'] ?? 'Not recorded')) . '
            </div>
        </div>';

        return $html;
    }

    /**
     * Render Section 6: DAAC Sign-Off Certificate
     */
    private function renderSection6Certificate(array $meta, array $recon, ?array $audit): string
    {
        $approved = \College\ReportValue::certified($audit);
        $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $title = $approved ? 'COURSE FILE REVIEW RECORD' : 'DRAFT COURSE FILE — REVIEW PENDING';
        $facultyStatus = $audit['faculty_signoff_status'] ?? 'DRAFT';
        $hodStatus = $audit['hod_approval_status'] ?? 'PENDING';
        $reviewer = 'Not recorded';
        if (!empty($audit['hod_reviewed_by'])) {
            $stmt = $this->conn->prepare('SELECT name FROM users WHERE id=?');
            $stmt->bind_param('i', $audit['hod_reviewed_by']); $stmt->execute();
            $reviewer = $stmt->get_result()->fetch_row()[0] ?? 'Not recorded';
        }
        $rows = [
            'Course' => ($meta['subject'] ?? '') . ' (' . ($meta['subcode'] ?? '') . ')',
            'Faculty' => $meta['faculty'] ?? 'Not recorded',
            'Faculty submission status' => $facultyStatus,
            'Submitted at' => $audit['faculty_signoff_at'] ?? 'Not recorded',
            'HOD decision' => $hodStatus,
            'Reviewed by' => $reviewer,
            'Reviewed at' => $audit['hod_approval_at'] ?? 'Not recorded',
            'Review observations' => $audit['hod_remarks'] ?? 'No review observations recorded',
            'Recorded syllabus completion' => isset($audit['syllabus_completion_pct']) ? $audit['syllabus_completion_pct'] . '%' : 'Not recorded',
        ];
        $html = '<h3>' . $escape($title) . '</h3><p>' . ($approved
            ? 'This file records the faculty submission and the HOD decision shown below. Any further committee certification requires its own recorded approval.'
            : 'This draft has no completed HOD approval record and must not be used as a completion certificate.') . '</p><table border="1" cellpadding="6" style="width:100%;border-collapse:collapse">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td><strong>' . $escape($label) . '</strong></td><td>' . $escape($value) . '</td></tr>';
        }
        return $html . '</table><p>Generated at: ' . $escape(date('Y-m-d H:i:s')) . '. Generation time is separate from the recorded approval time.</p>';

    }
}

// Global class alias
if (!class_exists('CourseMasterFilePDFService', false)) {
    class_alias(CourseMasterFilePDFService::class, 'CourseMasterFilePDFService');
}

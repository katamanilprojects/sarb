<?php
/**
 * Operational Course File (e-Bluebook) Service
 * 
 * Generates the official audit-compliant e-Bluebook:
 * Page 1: Official Course Dossier Cover Page & Course Preamble (COs)
 * Pages 2–9: Attendance Register with running header "Attendance Register"
 *            and Executive Attendance Summary & Signatures at bottom of Page 9
 * Pages 10–12: Diary of Classes with running header "Diary of Classes"
 * Pages 13–14: Continuous Internal Assessment (CIA) Marks Register with running header
 *              and administrative signature block on Page 14
 * 
 * Maximum 14 pages. Strictly excludes Question Paper Analyses, Bloom's Analytics, or OBE matrices.
 */

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../faculty.class.php';
require_once __DIR__ . '/SettingsService.php';

class EBluebookPDFService
{
    private $conn;
    private $facultyObj;
    private $settingsService;

    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        $this->facultyObj = new Faculty();
        $this->settingsService = \Services\SettingsService::getInstance();
    }

    /**
     * Generate the official e-Bluebook PDF document (14 pages)
     * 
     * @param int|array $courseData Subject ID or array containing course metadata
     * @param array $params Optional parameters like facid, start_date, end_date
     * @return string Absolute file path to the generated PDF
     */
    public function generateEBluebook($courseData, array $params = [])
    {
        $sub_id = is_array($courseData) ? intval($courseData['sub_id'] ?? $courseData['id'] ?? 0) : intval($courseData);
        if ($sub_id <= 0) {
            throw new InvalidArgumentException("Invalid subject ID provided for e-Bluebook generation.");
        }

        $facid = $params['facid'] ?? null;
        $start_date = $params['start_date'] ?? null;
        $end_date = $params['end_date'] ?? null;

        $attendanceData = $this->facultyObj->getDetailedAttendanceBySubject($sub_id, $start_date, $end_date);
        if (empty($attendanceData['data'])) {
            throw new Exception("No attendance records found for subject ID $sub_id.");
        }

        $dateHours = array_keys($attendanceData['data'][0]['attendance']);
        usort($dateHours, function ($a, $b) {
            $dateA = substr($a, 0, 10);
            $dateB = substr($b, 0, 10);
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }
            return strcmp($a, $b);
        });

        if (empty($start_date) && !empty($dateHours)) {
            $start_date = substr($dateHours[0], 0, 10);
        }
        if (empty($end_date) && !empty($dateHours)) {
            $lastIndex = count($dateHours) - 1;
            $end_date = substr($dateHours[$lastIndex], 0, 10);
        }

        // Regulation detection
        $regulation = 'R23';
        $regId = null;
        if ($rStmt = $this->conn->prepare("SELECT c.reg_id, r.regulation FROM subjects s JOIN classes c ON s.class_id = c.id JOIN regulations r ON c.reg_id = r.id WHERE s.id = ?")) {
            $rStmt->bind_param("i", $sub_id);
            if ($rStmt->execute()) {
                $rStmt->bind_result($foundRegId, $foundReg);
                if ($rStmt->fetch()) {
                    if (!empty($foundRegId)) $regId = (int)$foundRegId;
                    if (!empty($foundReg)) $regulation = strtoupper(trim($foundReg));
                }
            }
            $rStmt->close();
        }

        $regParam = !empty($regId) ? $regId : $regulation;
        $minAggregatePct = (float)$this->settingsService->get('attendance_min_aggregate_pct', $regParam, 75.0);
        $condoneFloor = (float)$this->settingsService->get('attendance_condone_floor_pct', $regParam, 65.0);
        $minSubjectPct = (float)$this->settingsService->get('attendance_min_subject_pct', $regParam, 40.0);

        $midBetterWeight = (float)$this->settingsService->get('theory_mid_better_weight', $regParam, 0.80);
        $midLesserWeight = (float)$this->settingsService->get('theory_mid_lesser_weight', $regParam, 0.20);
        $betterWeightPct = round($midBetterWeight * 100);
        $lesserWeightPct = round($midLesserWeight * 100);

        $overallDirectPct = round((float)$this->settingsService->get('overall_direct_weight', $regParam, 0.80) * 100);
        $overallIndirectPct = round((float)$this->settingsService->get('overall_indirect_weight', $regParam, 0.20) * 100);
        $directCiaPct = round((float)$this->settingsService->get('attainment_direct_cia_weight', $regParam, 0.30) * 100);
        $directSeePct = round((float)$this->settingsService->get('attainment_direct_see_weight', $regParam, 0.70) * 100);

        $studentsAttendance = [];
        $geMinAgg = [];
        $betweenFloorAndAgg = [];
        $ltFloor = [];
        $ltMinSub = [];
        $totalStudents = count($attendanceData['data']);

        foreach ($attendanceData['data'] as $student) {
            $totalClassesAttended = $student['total_present'];
            $totalClassesHeld = $student['total_classes'];
            $attendancePercentage = ($totalClassesHeld > 0) ? round(($totalClassesAttended / $totalClassesHeld) * 100, 2) : 0;
            $roll = $student['username'];

            $studentsAttendance[$roll] = [
                'total_held' => $totalClassesHeld,
                'total_attended' => $totalClassesAttended,
                'percentage' => $attendancePercentage
            ];

            if ($attendancePercentage >= $minAggregatePct) {
                $geMinAgg[] = ['roll' => $roll, 'pct' => $attendancePercentage];
            } elseif ($attendancePercentage >= $condoneFloor) {
                $betweenFloorAndAgg[] = ['roll' => $roll, 'pct' => $attendancePercentage];
            } else {
                $ltFloor[] = ['roll' => $roll, 'pct' => $attendancePercentage];
                if ($attendancePercentage < $minSubjectPct) {
                    $ltMinSub[] = ['roll' => $roll, 'pct' => $attendancePercentage];
                }
            }
        }

        if (!empty($facid)) {
            $details = $this->facultyObj->getClassSubjectFacultyDetails($sub_id, $facid);
        } else {
            require_once __DIR__ . '/../hod.class.php';
            $hodObj = new HOD();
            $details = $hodObj->getClsSubFacBySubID($sub_id);
        }

        // Comprehensive Subject & Class Relational Metadata Lookup
        $metaRow = null;
        $mStmt = $this->conn->prepare("
            SELECT 
                s.id AS sub_id,
                s.subcode,
                s.sub_fullname,
                s.sub_type,
                s.class_id,
                c.classname,
                c.acad_year,
                c.yearsem,
                c.section,
                c.start_date AS class_start_date,
                c.end_date AS class_end_date,
                r.regulation,
                sp.id AS spec_id,
                sp.spec_fullname,
                sp.spec_shortname,
                p.id AS prog_id,
                p.prog_fullname,
                p.prog_shortname,
                d.id AS dept_id,
                d.dept_fullname
            FROM subjects s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            LEFT JOIN specialization sp ON c.spec_id = sp.id
            LEFT JOIN programs p ON sp.prog_id = p.id
            LEFT JOIN departments d ON sp.dept_id = d.id
            WHERE s.id = ?
        ");
        if ($mStmt) {
            $mStmt->bind_param("i", $sub_id);
            if ($mStmt->execute()) {
                $mRes = $mStmt->get_result();
                $metaRow = $mRes->fetch_assoc();
            }
            $mStmt->close();
        }

        // Subject & Code
        $subject = !empty($metaRow['sub_fullname']) ? trim($metaRow['sub_fullname']) : (!empty($details['data']['sub_fullname']) ? trim($details['data']['sub_fullname']) : '');
        $subCode = !empty($metaRow['subcode']) ? trim($metaRow['subcode']) : (!empty($details['data']['subcode']) ? trim($details['data']['subcode']) : '');
        $courseType = !empty($metaRow['sub_type']) ? ucfirst(trim($metaRow['sub_type'])) : (!empty($details['data']['sub_type']) ? ucfirst(trim($details['data']['sub_type'])) : 'Theory');
        $acadYear = !empty($metaRow['acad_year']) ? trim($metaRow['acad_year']) : (!empty($details['data']['acad_year']) ? trim($details['data']['acad_year']) : (date('Y') . '-' . (date('Y') + 1)));

        // Regulation
        if (!empty($metaRow['regulation'])) {
            $regulation = strtoupper(trim($metaRow['regulation']));
        }

        // Department Name
        $deptName = '';
        if (!empty($metaRow['dept_fullname'])) {
            $deptName = trim($metaRow['dept_fullname']);
        } elseif (!empty($_SESSION['dept_id'])) {
            $dStmt = $this->conn->prepare("SELECT dept_fullname FROM departments WHERE id = ?");
            if ($dStmt) {
                $dStmt->bind_param("i", $_SESSION['dept_id']);
                if ($dStmt->execute()) {
                    $dStmt->bind_result($foundDept);
                    if ($dStmt->fetch() && !empty($foundDept)) {
                        $deptName = trim($foundDept);
                    }
                }
                $dStmt->close();
            }
        }
        if (empty($deptName) && !empty($metaRow['spec_fullname'])) {
            $deptName = $metaRow['spec_fullname'];
        }
        if (empty($deptName)) {
            $deptName = 'Engineering';
        }

        // Faculty / Course Instructor
        $faculty = '';
        if (!empty($facid)) {
            $fStmt = $this->conn->prepare("
                SELECT u.name 
                FROM faculties f 
                JOIN users u ON f.username = u.username 
                WHERE f.id = ?
            ");
            if ($fStmt) {
                $fStmt->bind_param("i", $facid);
                if ($fStmt->execute()) {
                    $fRes = $fStmt->get_result();
                    if ($fRow = $fRes->fetch_assoc()) {
                        $faculty = trim($fRow['name']);
                    }
                }
                $fStmt->close();
            }
        }
        if (empty($faculty) && !empty($details['data']['faculty_name']) && $details['data']['faculty_name'] !== 'N/A') {
            $faculty = trim($details['data']['faculty_name']);
        }
        if (empty($faculty)) {
            // Check faculty_sub mapping
            $fsStmt = $this->conn->prepare("
                SELECT DISTINCT u.name 
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN users u ON f.username = u.username
                WHERE fs.sub_id = ?
                ORDER BY f.id ASC
            ");
            if ($fsStmt) {
                $fsStmt->bind_param("i", $sub_id);
                if ($fsStmt->execute()) {
                    $fsRes = $fsStmt->get_result();
                    $fList = [];
                    while ($fsRow = $fsRes->fetch_assoc()) {
                        if (!empty($fsRow['name'])) {
                            $fList[] = trim($fsRow['name']);
                        }
                    }
                    if (!empty($fList)) {
                        $faculty = implode(', ', array_unique($fList));
                    }
                }
                $fsStmt->close();
            }
        }
        if (empty($faculty)) {
            // Check diary entries for instructor who taught classes
            $dStmt = $this->conn->prepare("
                SELECT DISTINCT u.name 
                FROM diary d
                JOIN faculties f ON d.faculty_id = f.id
                JOIN users u ON f.username = u.username
                WHERE d.sub_id = ?
                ORDER BY d.id DESC
            ");
            if ($dStmt) {
                $dStmt->bind_param("i", $sub_id);
                if ($dStmt->execute()) {
                    $dRes = $dStmt->get_result();
                    $dList = [];
                    while ($dRow = $dRes->fetch_assoc()) {
                        if (!empty($dRow['name'])) {
                            $dList[] = trim($dRow['name']);
                        }
                    }
                    if (!empty($dList)) {
                        $faculty = implode(', ', array_unique($dList));
                    }
                }
                $dStmt->close();
            }
        }
        if (empty($faculty)) {
            $faculty = 'N/A';
        }

        // Fetch Course Outcomes for Preamble
        $cos = [];
        $coStmt = $this->conn->prepare("SELECT co_number, co_description FROM course_outcomes WHERE sub_id = ? ORDER BY co_number ASC");
        if ($coStmt) {
            $coStmt->bind_param("i", $sub_id);
            if ($coStmt->execute()) {
                $coRes = $coStmt->get_result();
                while ($r = $coRes->fetch_assoc()) {
                    $cos[] = $r;
                }
            }
            $coStmt->close();
        }

        // Program & Branch
        $progName = !empty($metaRow['prog_shortname']) ? trim($metaRow['prog_shortname']) : (!empty($metaRow['prog_fullname']) ? trim($metaRow['prog_fullname']) : '');
        $specName = !empty($metaRow['spec_fullname']) ? trim($metaRow['spec_fullname']) : (!empty($metaRow['spec_shortname']) ? trim($metaRow['spec_shortname']) : '');
        $specShort = !empty($metaRow['spec_shortname']) ? trim($metaRow['spec_shortname']) : '';
        $class_full = !empty($metaRow['classname']) ? trim($metaRow['classname']) : ($details['data']['classname'] ?? '');

        if (!empty($specName)) {
            if (!empty($progName) && stripos($specName, $progName) === false) {
                $branchFull = $progName . ' - ' . $specName;
            } else {
                $branchFull = $specName;
            }

            if (!empty($specShort)) {
                if (!empty($progName) && stripos($specShort, $progName) === false) {
                    $branchShort = $progName . ' (' . $specShort . ')';
                } else {
                    $branchShort = $specShort;
                }
            } else {
                $branchShort = $branchFull;
            }
        } else {
            $class_parts = explode(' - ', $class_full);
            preg_match_all('/\(([^)]+)\)/', $class_parts[0] ?? '', $matches);
            $branchExtracted = '';
            if (count($matches[1]) === 1) {
                $branchExtracted = $matches[1][0];
            } elseif (count($matches[1]) >= 2) {
                $last = array_pop($matches[1]);
                $secondLast = array_pop($matches[1]);
                $branchExtracted = $secondLast . ' (' . $last . ')';
            }
            $branchShort = !empty($branchExtracted) ? $branchExtracted : ($class_parts[0] ?? 'N/A');
            $branchFull = $branchShort;
        }

        // Class & Semester
        $yearsem = !empty($metaRow['yearsem']) ? trim($metaRow['yearsem']) : (!empty($details['data']['yearsem']) ? trim($details['data']['yearsem']) : '');
        $section = !empty($metaRow['section']) ? trim($metaRow['section']) : (!empty($details['data']['section']) ? trim($details['data']['section']) : '');

        if (!empty($yearsem)) {
            if (strpos($yearsem, ' - ') !== false) {
                $yParts = explode(' - ', $yearsem);
                $yearPart = trim($yParts[0]);
                $semPart = trim($yParts[1]);
                $class = (!empty($progName) ? $progName . ' - ' : '') . $yearPart;
                if (!empty($section)) {
                    $class .= ' - Sec ' . $section;
                }
                $semester = $semPart;
            } else {
                $class = (!empty($progName) ? $progName : $class_full);
                if (!empty($section)) {
                    $class .= ' - Sec ' . $section;
                }
                $semester = $yearsem;
            }
        } else {
            $class_parts = explode(' - ', $class_full);
            $semester = trim($class_parts[2] ?? '');
            $pos = strpos($class_parts[0] ?? '', '(');
            $class = ($pos !== false) ? trim(substr($class_parts[0], 0, $pos)) : ($class_parts[0] ?? '');
            if (isset($class_parts[1])) {
                $class .= ' - ' . trim($class_parts[1]);
            }
        }

        // Instruction Period Date Resolution
        if ((empty($start_date) || $start_date === 'N/A') && !empty($metaRow['class_start_date'])) {
            $start_date = $metaRow['class_start_date'];
        }
        if ((empty($end_date) || $end_date === 'N/A') && !empty($metaRow['class_end_date'])) {
            $end_date = $metaRow['class_end_date'];
        }
        if (empty($start_date) || empty($end_date)) {
            $dtStmt = $this->conn->prepare("SELECT MIN(date) AS min_d, MAX(date) AS max_d FROM attendance WHERE sub_id = ?");
            if ($dtStmt) {
                $dtStmt->bind_param("i", $sub_id);
                if ($dtStmt->execute()) {
                    $dtRes = $dtStmt->get_result();
                    if ($dtRow = $dtRes->fetch_assoc()) {
                        if (empty($start_date) && !empty($dtRow['min_d'])) $start_date = $dtRow['min_d'];
                        if (empty($end_date) && !empty($dtRow['max_d'])) $end_date = $dtRow['max_d'];
                    }
                }
                $dtStmt->close();
            }
        }
        $start_date_formatted = (!empty($start_date) && $start_date !== 'N/A') ? date('d-m-Y', strtotime($start_date)) : 'N/A';
        $end_date_formatted = (!empty($end_date) && $end_date !== 'N/A') ? date('d-m-Y', strtotime($end_date)) : 'N/A';

        // Initialize mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 36,
            'margin_bottom' => 12,
            'default_font_size' => 8,
            'tempDir' => __DIR__ . '/../uploads/tmp'
        ]);
        $mpdf->aliasNbPages();
        $mpdf->WriteHTML('<style>
            body { font-size: 8px; }
            p { margin: 0 0 3px 0; }
        </style>');

        $specFullName = !empty($metaRow['spec_fullname']) ? trim($metaRow['spec_fullname']) : '';
        $className = !empty($metaRow['classname']) ? trim($metaRow['classname']) : ($details['data']['classname'] ?? '');

        $metaInfo = [
            'dept_name' => $deptName,
            'subject' => $subject,
            'subcode' => $subCode,
            'spec_fullname' => $specFullName,
            'classname' => $className,
            'branch' => !empty($specFullName) ? $specFullName : $branchFull,
            'branch_short' => $branchShort,
            'class' => !empty($className) ? $className : $class,
            'semester' => $semester,
            'acad_year' => $acadYear,
            'course_type' => $courseType,
            'faculty' => $faculty,
            'start_date' => $start_date_formatted,
            'end_date' => $end_date_formatted,
            'regulation' => $regulation
        ];

        // ==========================================
        // PAGE 1: COVER PAGE & COURSE PREAMBLE
        // ==========================================
        $coverHtml = $this->renderCoverPage($metaInfo, $cos);
        $mpdf->WriteHTML($coverHtml);

        // Header builder for subsequent operational pages
        $getBluebookHeader = function($sectionTitle) use ($class, $semester, $branchShort, $acadYear, $start_date_formatted, $end_date_formatted, $subject, $subCode, $faculty) {
            $branch = $branchShort;
            $subTitle = !empty($subCode) ? htmlspecialchars($subject) . ' (' . htmlspecialchars($subCode) . ')' : htmlspecialchars($subject);
            
            // Dynamically scale font size based on subject title length to guarantee single line fit
            $subLen = mb_strlen($subTitle, 'UTF-8');
            if ($subLen > 65) {
                $subFontSize = '8.5px';
                $subLineHeight = '13px';
            } elseif ($subLen > 52) {
                $subFontSize = '9.5px';
                $subLineHeight = '14px';
            } elseif ($subLen > 40) {
                $subFontSize = '10.5px';
                $subLineHeight = '15px';
            } else {
                $subFontSize = '12px';
                $subLineHeight = '18px';
            }

            return '
    <table cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr>
            <td style="text-align:left; width:25%; font-size:12px; line-height: 18px; padding-bottom:0px;">
                <strong>Class:</strong> ' . htmlspecialchars($class) . '<br>
                <strong>Semester:</strong> ' . htmlspecialchars($semester) . '<br>
                <strong>Branch:</strong> ' . htmlspecialchars($branch) . '
            </td>
            <td colspan="2" style="text-align:center; width:50%; font-size:18px; padding-bottom:0px;">
                <strong>' . htmlspecialchars($sectionTitle) . '</strong>
            </td>
            <td style="text-align:right; width:25%; font-size:12px; line-height: 18px; padding-bottom:0px;">
                <strong>Acad. Year:</strong> ' . htmlspecialchars($acadYear) . '<br>
                <strong>Starting:</strong> ' . htmlspecialchars($start_date_formatted) . '<br>
                <strong>Ending:</strong> ' . htmlspecialchars($end_date_formatted) . '
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:left; width:60%; font-size:' . $subFontSize . '; line-height:' . $subLineHeight . '; padding-top:2px; white-space:nowrap; overflow:hidden;">
                <strong>Subject:</strong> ' . $subTitle . '
            </td>
            <td colspan="2" style="text-align:right; width:40%; font-size:12px; line-height: 18px; padding-top:2px; white-space:nowrap;">
                <strong>Teacher:</strong> ' . htmlspecialchars($faculty) . '
            </td>
        </tr>
    </table>
    <hr style="margin:0px; padding:0px;"><br>';
        };

        // ==========================================
        // SECTION 1: ATTENDANCE REGISTER (Pages 2-9)
        // ==========================================
        $mpdf->SetHTMLHeader($getBluebookHeader('Attendance Register'));
        $mpdf->AddPage('', '', '', '', '', 8, 8, 36, 12);

        $studentsPerPage = 36;
        $totalDates = count($dateHours);
        $totalStudentPages = ceil($totalStudents / $studentsPerPage);

        $sno = 1;
        for ($studentPage = 0; $studentPage < $totalStudentPages; $studentPage++) {
            $datesPerPage = 8;
            $studentStart = $studentPage * $studentsPerPage;
            $studentEnd = min($studentStart + $studentsPerPage, $totalStudents);

            $noofpages = 0;
            if ($totalDates < 8) {
                $noofpages++;
            } else {
                $i = 0;
                while ($totalDates > (($i * 12) + 8)) {
                    $noofpages++;
                    $i++;
                }
            }

            if ($totalDates <= 4) {
                $noofpages = 0;
            }
            if ($totalDates > 4 && $totalDates <= 8) {
                $noofpages = 1;
            }

            for ($datePage = 0; $datePage <= $noofpages; $datePage++) {
                if ($datePage == 0) {
                    $dateStart = 0;
                } elseif ($datePage == 1) {
                    if ($totalDates > 4 && $totalDates <= 8) {
                        $dateStart = $totalDates - 1;
                    } else {
                        $dateStart = $datePage * 8;
                        $datesPerPage = 12;
                    }
                } else {
                    $dateStart = 8 + (($datePage - 1) * $datesPerPage);
                }

                $dateEnd = min($dateStart + $datesPerPage, $totalDates);
                if ($totalDates > 4 && $totalDates <= 8) {
                    $dateEnd = $totalDates - 1;
                    if ($datePage == 1) {
                        $dateEnd = $totalDates;
                    }
                }
                $fs = 9.5;

                // Add page for subsequent attendance segments
                if (!($studentPage === 0 && $datePage === 0)) {
                    $mpdf->AddPage();
                }

                $html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
                    <thead><tr>';

                if ($datePage == 0) {
                    $html .= '
                        <th rowspan="2" style="width:30px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">S.No</th>
                        <th rowspan="2" style="width:60px; text-align:left; vertical-align:middle; font-size:' . $fs . 'px;">Adm.No</th>
                        <th rowspan="2" style="width:320px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Name.</th>';
                } else {
                    $html .= '
                        <th rowspan="2" style="width:60px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Adm.No</th>';
                }

                for ($i = $dateStart; $i < $dateEnd; $i++) {
                    $classNo = $i + 1;
                    $html .= '<th style="width:30px; text-align:center; font-size:' . $fs . 'px;">' . $classNo . '</th>';
                }

                if ($datePage == $noofpages) {
                    $html .= '<th rowspan="2" style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Total Held</th>';
                    $html .= '<th rowspan="2" style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Total Attended</th>';
                    $html .= '<th rowspan="2" style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Percentage</th>';
                }

                $html .= '</tr><tr>';
                for ($i = $dateStart; $i < $dateEnd; $i++) {
                    $date = explode('-', $dateHours[$i]);
                    $html .= '<th style="width:30px; text-align:center; font-size:' . $fs . 'px;">' . $date[2] . '/' . $date[1] . '</th>';
                }

                $html .= '</tr></thead><tbody>';

                for ($s = $studentStart; $s < $studentEnd; $s++) {
                    $student = $attendanceData['data'][$s];
                    $row = '<tr>';

                    if ($datePage == 0) {
                        $sno = $s + 1;
                        $row .= '<td style="text-align:center; font-size:9.5px;">' . $sno . '</td>';
                        $row .= '<td style="font-size:9.5px;">' . $student['username'] . '</td>';
                        $row .= '<td style="font-size:9.5px; word-wrap:break-word;">' . $student['name'] . '</td>';
                    } else {
                        $row .= '<td style="font-size:9.5px; text-align:center;">' . $student['username'] . '</td>';
                    }

                    for ($i = $dateStart; $i < $dateEnd; $i++) {
                        $dateHour = $dateHours[$i];
                        $status = $student['attendance'][$dateHour] ?? '-';
                        $color = ($status === 'A') ? '#dc3545' : '#000';
                        $row .= '<td style="font-size:9.5px; text-align:center; color:' . $color . ';">' . $status . '</td>';
                    }

                    if ($datePage == $noofpages) {
                        $row .= '<td style="text-align:center; font-size:9.5px;">' . $studentsAttendance[$student['username']]['total_held'] . '</td>';
                        $row .= '<td style="text-align:center; font-size:9.5px;">' . $studentsAttendance[$student['username']]['total_attended'] . '</td>';
                        $row .= '<td style="text-align:center; font-size:9.5px; font-weight:bold;">' . $studentsAttendance[$student['username']]['percentage'] . '%</td>';
                    }

                    $row .= '</tr>';
                    $html .= $row;
                }

                $html .= '</tbody></table>';

                $mpdf->WriteHTML($html);
            }
        }

        // Dedicated Standalone Page: ATTENDANCE DISTRIBUTION & COHORT SUMMARY
        $totSafe = max($totalStudents, 1);
        $betweenStr = !empty($betweenFloorAndAgg)
            ? implode(', ', array_map(fn($c) => $c['roll'] . ' (' . $c['pct'] . '%)', $betweenFloorAndAgg))
            : 'Nil';
        $ltFloorStr = !empty($ltFloor)
            ? implode(', ', array_map(fn($d) => $d['roll'] . ' (' . $d['pct'] . '%)', $ltFloor))
            : 'Nil';
        $ltMinSubStr = !empty($ltMinSub)
            ? implode(', ', array_map(fn($s) => $s['roll'] . ' (' . $s['pct'] . '%)', $ltMinSub))
            : 'Nil';

        $mpdf->SetHTMLHeader($getBluebookHeader('Attendance Summary'), 0);
        $mpdf->AddPage();

        $summaryHtml = '
        <div style="font-family: Arial, sans-serif; padding-top: 10px;">
            <h3 style="text-align: center; color: #1a365d; margin-bottom: 15px; font-size: 13px;">
                COURSE ATTENDANCE DISTRIBUTION & SUMMARY
            </h3>
            <table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-bottom: 10px;">
                <thead>
                    <tr style="background-color: #e2e3e5;">
                        <th colspan="4" style="text-align: left; font-size: 11px; font-weight: bold; color: #1a365d; padding: 6px 8px;">
                            Course Attendance Distribution (Regulation ' . htmlspecialchars($regulation) . ')
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="width: 25%; font-weight: bold; background-color: #fafafa; padding: 6px 8px;">Total Students Enrolled:</td>
                        <td style="width: 25%; font-weight: bold; text-align: center; padding: 6px 8px;">' . $totalStudents . '</td>
                        <td style="width: 25%; font-weight: bold; background-color: #e8f5e9; color: #1b5e20; padding: 6px 8px;">&ge; ' . $minAggregatePct . '%:</td>
                        <td style="width: 25%; font-weight: bold; text-align: center; background-color: #e8f5e9; color: #1b5e20; padding: 6px 8px;">' . count($geMinAgg) . ' (' . round(count($geMinAgg)/$totSafe*100, 2) . '%)</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #fff8e1; color: #e65100; padding: 6px 8px;">' . $condoneFloor . '% &ndash; &lt; ' . $minAggregatePct . '%:</td>
                        <td colspan="3" style="background-color: #fff8e1; color: #e65100; font-size: 9.5px; padding: 6px 8px;">
                            <strong>' . count($betweenFloorAndAgg) . ' Student(s) (' . round(count($betweenFloorAndAgg)/$totSafe*100, 2) . '%):</strong> ' . htmlspecialchars($betweenStr) . '
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #ffebee; color: #c62828; padding: 6px 8px;">&lt; ' . $condoneFloor . '%:</td>
                        <td colspan="3" style="background-color: #ffebee; color: #c62828; font-size: 9.5px; padding: 6px 8px;">
                            <strong>' . count($ltFloor) . ' Student(s) (' . round(count($ltFloor)/$totSafe*100, 2) . '%):</strong> ' . htmlspecialchars($ltFloorStr) . '
                        </td>
                    </tr>';

        if ($minSubjectPct > 0) {
            $summaryHtml .= '
                    <tr>
                        <td style="font-weight: bold; background-color: #fce4ec; color: #880e4f; padding: 6px 8px;">&lt; ' . $minSubjectPct . '% (Subject Shortage):</td>
                        <td colspan="3" style="background-color: #fce4ec; color: #880e4f; font-size: 9.5px; padding: 6px 8px;">
                            <strong>' . count($ltMinSub) . ' Student(s) (' . round(count($ltMinSub)/$totSafe*100, 2) . '%):</strong> ' . htmlspecialchars($ltMinSubStr) . '
                            <div style="font-size: 8.5px; color: #ad1457; margin-top: 3px; font-style: italic;">
                                * Individual course attendance requirement as per Section 17(i) of Academic Regulations (minimum ' . $minSubjectPct . '% per course).
                            </div>
                        </td>
                    </tr>';
        }

        $summaryHtml .= '
                </tbody>
            </table>
            <div style="font-size: 8px; color: #666; line-height: 1.4; margin-top: 4px; font-style: italic;">
                * Note: Official semester condonation (' . $condoneFloor . '% &ndash; ' . $minAggregatePct . '%) and detention (&lt; ' . $condoneFloor . '%) are institutional decisions finalized class-wise based on aggregate attendance across all courses. At the individual course level, this summary presents the cohort attendance distribution and highlights course-level minimum compliance (&lt; ' . $minSubjectPct . '%).
            </div>
        </div>';

        $mpdf->WriteHTML($summaryHtml);

        // ==========================================
        // SECTION 2A: COURSE DELIVERY PLAN / LESSON PLAN (NBA CRITERION 2)
        // ==========================================
        require_once __DIR__ . '/LessonPlanService.php';
        $lpService = \Services\LessonPlanService::getInstance();
        $lessonPlan = $lpService->getPlanBySubject($sub_id);
        $varianceStats = $lpService->getSyllabusVariance($sub_id);

        if (!empty($lessonPlan)) {
            $mpdf->SetHTMLHeader($getBluebookHeader('Course Delivery Plan (Lesson Plan)'), 0);
            $mpdf->AddPage();

            $lpHtml = '
            <h3 style="text-align:center; margin-bottom: 5px;">COURSE DELIVERY SCHEDULE (LESSON PLAN)</h3>
            <p style="text-align:center; font-size:11px; color:#555; margin-top:0;">Estimated Teaching Plan prepared prior to semester commencement (NBA Criterion 2.1 & 2.2)</p>
            
            <div style="background:#f8f9fa; border:1px solid #ddd; padding:8px 12px; margin-bottom:12px; font-size:11px;">
                <strong>Planned Lectures:</strong> ' . $varianceStats['total_planned_lectures'] . ' &nbsp;|&nbsp; 
                <strong>Actual Delivered:</strong> ' . $varianceStats['actual_conducted_lectures'] . ' periods &nbsp;|&nbsp; 
                <strong>Syllabus Coverage:</strong> ' . $varianceStats['completion_percentage'] . '% &nbsp;|&nbsp; 
                <strong>Course Outcomes Covered:</strong> ' . $varianceStats['cos_covered_count'] . ' COs
            </div>

            <table border="1" cellpadding="6" cellspacing="0" style="font-size: 11px; border-collapse:collapse; width: 100%;">
                <thead>
                    <tr style="background:#333; color:#fff;">
                        <th style="width:30px; text-align:center;">Lec #</th>
                        <th style="width:45px; text-align:center;">Unit</th>
                        <th style="text-align:left;">Planned Syllabus Topic</th>
                        <th style="width:40px; text-align:center;">CO</th>
                        <th style="width:75px; text-align:center;">Bloom Level</th>
                        <th style="width:85px; text-align:center;">Pedagogy</th>
                        <th style="width:110px; text-align:left;">Reference</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($lessonPlan as $lp) {
                $lpHtml .= '
                <tr>
                    <td style="text-align:center; font-weight:bold;">' . $lp['lecture_number'] . '</td>
                    <td style="text-align:center;">Unit ' . $lp['unit_number'] . '</td>
                    <td style="text-align:left;">' . htmlspecialchars($lp['planned_topic']) . '</td>
                    <td style="text-align:center; font-weight:bold;">CO' . $lp['co_number'] . '</td>
                    <td style="text-align:center;">' . htmlspecialchars($lp['bloom_level']) . '</td>
                    <td style="text-align:center;">' . htmlspecialchars($lp['pedagogy']) . '</td>
                    <td style="text-align:left;">' . htmlspecialchars($lp['reference_material'] ?? '-') . '</td>
                </tr>';
            }

            $lpHtml .= '</tbody></table>';
            $mpdf->WriteHTML($lpHtml);
        }

        // ==========================================
        // SECTION 2B: DIARY OF CLASSES (Pages 10-12)
        // ==========================================
        $diaryEntries = $this->facultyObj->getDiaryEntriesBySubject($sub_id, $start_date, $end_date);
        if (!empty($diaryEntries)) {
            $mpdf->SetHTMLHeader($getBluebookHeader('Diary of Classes'), 0);
            $mpdf->AddPage();

            $diaryHTML = '
            <h3 style="text-align:center;">DIARY OF LECTURER CLASSES</h3>
            <table border="1" cellpadding="8" cellspacing="0" style="font-size: 11px; border-collapse:collapse; width: 100%;">
                <thead>
            <tr style="background:#333; color:#fff;">
                <th style="width:35px; text-align:center; vertical-align:middle;">S.No</th>
                <th style="width:90px; text-align:center; vertical-align:middle;">Date</th>
                <th style="width:120px; text-align:center; vertical-align:middle;">Time</th>
                <th style="text-align:left; vertical-align:middle;">Topics Covered</th>
            </tr>
                </thead>
                <tbody>';

            $dSno = 1;
            foreach ($diaryEntries as $entry) {
                $diaryHTML .= '
                <tr>
                    <td style="text-align:center;">' . $dSno++ . '</td>
                    <td style="text-align:center;">' . date('d-m-Y', strtotime($entry['date'])) . '</td>
                    <td style="text-align:center;">' . date("g:i A", strtotime($entry['start_time'])) . " - " . date("g:i A", strtotime($entry['end_time'])) . '</td>
                    <td style="text-align:left; word-break: break-word; word-wrap: break-word;">' . htmlspecialchars($entry['diary']) . '</td>
                </tr>';
            }

            $diaryHTML .= '</tbody></table>';
            $mpdf->WriteHTML($diaryHTML);
        }

        // ==========================================
        // SECTION 2C: COURSE DELIVERY COMPLIANCE & DEVIATION REPORT (NBA CRITERION 2.2)
        // ==========================================
        $auditRecord = $lpService->getCourseCompletionAudit($sub_id);
        if (!empty($auditRecord) || !empty($lessonPlan)) {
            $mpdf->SetHTMLHeader($getBluebookHeader('Course Completion & Compliance Audit'), 0);
            $mpdf->AddPage();

            $totalPlanned = !empty($auditRecord['total_planned_lectures']) ? (int)$auditRecord['total_planned_lectures'] : count($lessonPlan);
            $totalConducted = !empty($auditRecord['total_actual_conducted']) ? (int)$auditRecord['total_actual_conducted'] : count($diaryEntries);
            $compClasses = !empty($auditRecord['total_compensatory_classes']) ? (int)$auditRecord['total_compensatory_classes'] : max(0, $totalConducted - $totalPlanned);
            $completionPct = !empty($auditRecord['syllabus_completion_pct']) ? (float)$auditRecord['syllabus_completion_pct'] : (($totalPlanned > 0) ? round(($totalConducted / $totalPlanned) * 100, 1) : 0);

            $devReason = !empty($auditRecord['deviations_reason']) ? htmlspecialchars($auditRecord['deviations_reason']) : 'None reported. Course delivered in accordance with planned schedule.';
            $compActions = !empty($auditRecord['compensatory_actions']) ? htmlspecialchars($auditRecord['compensatory_actions']) : 'Syllabus coverage achieved within prescribed timetable periods.';
            $beyondSyllabus = !empty($auditRecord['topics_beyond_syllabus']) ? htmlspecialchars($auditRecord['topics_beyond_syllabus']) : 'Standard syllabus topics covered completely.';

            $signoffDate = !empty($auditRecord['faculty_signoff_at']) ? date('d-m-Y', strtotime($auditRecord['faculty_signoff_at'])) : date('d-m-Y');

            $auditHTML = '
            <h3 style="text-align:center; margin-bottom:5px;">COURSE DELIVERY COMPLIANCE & DEVIATION REPORT</h3>
            <p style="text-align:center; font-size:11px; color:#555; margin-top:0;">End-of-Semester Syllabus Completion Certification (NBA Criterion 2.1 & 2.2)</p>

            <table border="1" cellpadding="6" cellspacing="0" style="font-size: 11px; border-collapse:collapse; width: 100%; margin-bottom: 12px;">
                <tr style="background:#f2f4f7;">
                    <th colspan="4" style="text-align:left; font-size:12px; color:#1a365d;">1. Course Delivery Quantitative Summary</th>
                </tr>
                <tr>
                    <td style="width:25%; font-weight:bold;">Total Planned Lectures:</td>
                    <td style="width:25%; text-align:center; font-weight:bold;">' . $totalPlanned . '</td>
                    <td style="width:25%; font-weight:bold;">Total Actual Conducted:</td>
                    <td style="width:25%; text-align:center; font-weight:bold;">' . $totalConducted . '</td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Compensatory Classes:</td>
                    <td style="text-align:center; font-weight:bold;">' . $compClasses . '</td>
                    <td style="font-weight:bold;">Syllabus Completion:</td>
                    <td style="text-align:center; font-weight:bold; color: #198754;">' . $completionPct . '%</td>
                </tr>
            </table>

            <table border="1" cellpadding="6" cellspacing="0" style="font-size: 11px; border-collapse:collapse; width: 100%; margin-bottom: 12px;">
                <tr style="background:#f2f4f7;">
                    <th colspan="5" style="text-align:left; font-size:12px; color:#1a365d;">2. Unit-Wise Completion Milestones</th>
                </tr>
                <tr style="background:#f8f9fa; font-weight:bold; text-align:center;">
                    <td>Unit 1</td>
                    <td>Unit 2</td>
                    <td>Unit 3</td>
                    <td>Unit 4</td>
                    <td>Unit 5</td>
                </tr>
                <tr style="text-align:center;">
                    <td>' . (!empty($auditRecord['unit1_completion_date']) ? date('d-m-Y', strtotime($auditRecord['unit1_completion_date'])) : 'Completed') . '</td>
                    <td>' . (!empty($auditRecord['unit2_completion_date']) ? date('d-m-Y', strtotime($auditRecord['unit2_completion_date'])) : 'Completed') . '</td>
                    <td>' . (!empty($auditRecord['unit3_completion_date']) ? date('d-m-Y', strtotime($auditRecord['unit3_completion_date'])) : 'Completed') . '</td>
                    <td>' . (!empty($auditRecord['unit4_completion_date']) ? date('d-m-Y', strtotime($auditRecord['unit4_completion_date'])) : 'Completed') . '</td>
                    <td>' . (!empty($auditRecord['unit5_completion_date']) ? date('d-m-Y', strtotime($auditRecord['unit5_completion_date'])) : 'Completed') . '</td>
                </tr>
            </table>

            <table border="1" cellpadding="6" cellspacing="0" style="font-size: 11px; border-collapse:collapse; width: 100%; margin-bottom: 15px;">
                <tr style="background:#f2f4f7;">
                    <th style="text-align:left; font-size:12px; color:#1a365d;">3. Qualitative Deviation & Remedial Disclosures</th>
                </tr>
                <tr>
                    <td>
                        <strong>Schedule Deviations / Pacing Remarks:</strong><br>
                        <p style="margin: 4px 0 8px 0; color:#333;">' . nl2br($devReason) . '</p>
                        <strong>Compensatory & Remedial Measures Taken:</strong><br>
                        <p style="margin: 4px 0 8px 0; color:#333;">' . nl2br($compActions) . '</p>
                        <strong>Content / Topics Beyond Syllabus (NBA Criterion 2.1):</strong><br>
                        <p style="margin: 4px 0 0 0; color:#333;">' . nl2br($beyondSyllabus) . '</p>
                    </td>
                </tr>
            </table>

            <div style="background:#f8f9fa; border:1px solid #ddd; padding:8px 12px; margin-bottom:20px; font-size:10.5px; font-style:italic;">
                "I hereby certify that the prescribed syllabus for the course <strong>' . htmlspecialchars($subject) . ' (' . htmlspecialchars($subCode) . ')</strong> has been delivered as per the academic regulations, and the teaching diary has been reconciled with the course delivery plan."
            </div>

            <table cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; margin-top:20px;">
                <tr>
                    <td style="width:50%; text-align:left; font-size:11px;">
                        <br><br>
                        ________________________________________<br>
                        <strong>Signature of Faculty:</strong> ' . htmlspecialchars($faculty) . '<br>
                        <small>Date: ' . $signoffDate . '</small>
                    </td>
                    <td style="width:50%; text-align:right; font-size:11px;">
                        <br><br>
                        ________________________________________<br>
                        <strong>Head of the Department (HOD)</strong><br>
                        <small>Verified & Approved</small>
                    </td>
                </tr>
            </table>';

            $mpdf->WriteHTML($auditHTML);
        }

        // ==========================================
        // SECTION 3: CONTINUOUS INTERNAL ASSESSMENT (Pages 13-14)
        // ==========================================
        $studentList = $this->facultyObj->getMappedStudents($sub_id);
        $selected_sub_id = $sub_id;
        $facultyObj = $this->facultyObj;
        require __DIR__ . '/../modulecheckcia.php';

        if (!empty($includeInternalMarks)) {
            $mpdf->SetHTMLHeader($getBluebookHeader('Continuous Internal Assessment'), 0);
            $mpdf->AddPage();

            $internalMarksHTML = '';
            if (!empty($prg_res['prg_code']) && $prg_res['prg_code'] == "A") {
                if ($prg_res["sub_type"] == "project") {
                    $internalMarksHTML = '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">Supervisor</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">P. R. C.</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">CIA (Max 60)</th>
                        </tr>
                    </thead>
                        <tbody>';;
                    $iSno = 1;
                    foreach ($studentList as $student) {
                        $mComp1 = $student['marks'][1]['component1_marks'] ?? null;
                        $mComp2 = $student['marks'][1]['component2_marks'] ?? null;
                        $c1 = ($mComp1 !== null) ? rtrim(rtrim(number_format((float)$mComp1, 1), '0'), '.') : '-';
                        $c2 = ($mComp2 !== null) ? rtrim(rtrim(number_format((float)$mComp2, 1), '0'), '.') : '-';
                        $total = ($mComp1 !== null) ? round((float)$mComp1 + (float)($mComp2 ?? 0)) : '';
                        $internalMarksHTML .= '<tr>
                            <td style="font-size:11; text-align:center;">' . $iSno++ . '</td>
                            <td style="font-size:11; text-align:center;">' . htmlspecialchars($student['username']) . '</td>
                            <td style="font-size:11; text-align:center;">' . $c1 . '</td>
                            <td style="font-size:11; text-align:center;">' . $c2 . '</td>
                            <td style="font-size:11; text-align:center;">' . $total . '</td>
                        </tr>';
                    }
                    $internalMarksHTML .= '</tbody></table>';
                } elseif ($prg_res["sub_type"] == "lab" || $prg_res["sub_type"] == "dti") {
                    $col1 = ($prg_res["sub_type"] == "dti") ? "Activity" : "Day-to-Day";
                    $internalMarksHTML = '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th colspan="3" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment</th>
                        </tr>
                        <tr>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $col1 . '</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Internal</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA</th>
                        </tr>
                    </thead>
                        <tbody>';;
                    $iSno = 1;
                    foreach ($studentList as $student) {
                        $mDay = $student['marks'][1]['day_to_day_marks'] ?? null;
                        $mInt = $student['marks'][1]['internal_test_marks'] ?? null;
                        $subj1 = ($mDay !== null) ? rtrim(rtrim(number_format((float)$mDay, 1), '0'), '.') : '-';
                        $obj1 = ($mInt !== null) ? rtrim(rtrim(number_format((float)$mInt, 1), '0'), '.') : '-';
                        $assessment1Total = ($mDay !== null || $mInt !== null) ? round((float)($mDay ?? 0) + (float)($mInt ?? 0)) : '-';
                        $internalMarksHTML .= '<tr>
                            <td style="font-size:11; text-align:center;">' . $iSno++ . '</td>
                            <td style="font-size:11; text-align:center;">' . htmlspecialchars($student['username']) . '</td>
                            <td style="font-size:11; text-align:center;">' . $subj1 . '</td>
                            <td style="font-size:11; text-align:center;">' . $obj1 . '</td>
                            <td style="font-size:11; text-align:center;">' . $assessment1Total . '</td>
                        </tr>';
                    }
                    $internalMarksHTML .= '</tbody></table>';
                } else {
                    // Regular Theory Formative Marks Register
                    $internalMarksHTML = '<table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                                <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                                <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 1</th>
                                <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 2</th>
                                <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $betterWeightPct . '% of Best</th>
                                <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $lesserWeightPct . '% of Rest</th>
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

                    $iSno = 1;
                    foreach ($studentList as $student) {
                        $has1 = !empty($student['marks'][1]) && (($student['marks'][1]['subjective_marks'] ?? null) !== null || ($student['marks'][1]['objective_marks'] ?? null) !== null);
                        $has2 = !empty($student['marks'][2]) && (($student['marks'][2]['subjective_marks'] ?? null) !== null || ($student['marks'][2]['objective_marks'] ?? null) !== null);

                        if ($has1) {
                            $s1 = floatval($student['marks'][1]['subjective_marks'] ?? 0);
                            $o1 = floatval($student['marks'][1]['objective_marks'] ?? 0);
                            $a1 = floatval($student['marks'][1]['assignment_marks'] ?? 0);
                            $cia1Total = round($s1 + $o1 + $a1, 1);
                            $subj1 = rtrim(rtrim(number_format($s1, 1), '0'), '.');
                            $obj1 = rtrim(rtrim(number_format($o1, 1), '0'), '.');
                            $ass1 = rtrim(rtrim(number_format($a1, 1), '0'), '.');
                        } else {
                            $cia1Total = '-';
                            $subj1 = '-';
                            $obj1 = '-';
                            $ass1 = '-';
                        }

                        if ($has2) {
                            $s2 = floatval($student['marks'][2]['subjective_marks'] ?? 0);
                            $o2 = floatval($student['marks'][2]['objective_marks'] ?? 0);
                            $a2 = floatval($student['marks'][2]['assignment_marks'] ?? 0);
                            $cia2Total = round($s2 + $o2 + $a2, 1);
                            $subj2 = rtrim(rtrim(number_format($s2, 1), '0'), '.');
                            $obj2 = rtrim(rtrim(number_format($o2, 1), '0'), '.');
                            $ass2 = rtrim(rtrim(number_format($a2, 1), '0'), '.');
                        } else {
                            $cia2Total = '-';
                            $subj2 = '-';
                            $obj2 = '-';
                            $ass2 = '-';
                        }

                        if ($has1 && $has2) {
                            $best = max((float)$cia1Total, (float)$cia2Total);
                            $rest = min((float)$cia1Total, (float)$cia2Total);
                            $bVal = round($midBetterWeight * $best, 2);
                            $rVal = round($midLesserWeight * $rest, 2);
                            $finalCia = round($bVal + $rVal);
                        } elseif ($has1) {
                            $bVal = round($midBetterWeight * (float)$cia1Total, 2);
                            $rVal = 0;
                            $finalCia = round($bVal);
                        } elseif ($has2) {
                            $bVal = round($midBetterWeight * (float)$cia2Total, 2);
                            $rVal = 0;
                            $finalCia = round($bVal);
                        } else {
                            $bVal = '-';
                            $rVal = '-';
                            $finalCia = '-';
                        }

                        $internalMarksHTML .= '<tr>
                            <td style="font-size:11; text-align:center;">' . $iSno++ . '</td>
                            <td style="font-size:11; text-align:center;">' . htmlspecialchars($student['username']) . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has1 ? $subj1 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has1 ? $obj1 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has1 ? $ass1 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has1 ? $cia1Total : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has2 ? $subj2 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has2 ? $obj2 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has2 ? $ass2 : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . ($has2 ? $cia2Total : '-') . '</td>
                            <td style="font-size:11; text-align:center;">' . $bVal . '</td>
                            <td style="font-size:11; text-align:center;">' . $rVal . '</td>
                            <td style="font-size:11; text-align:center;">' . $finalCia . '</td>
                        </tr>';
                    }

                    $internalMarksHTML .= '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="11" style="font-size:9px; color:#444; background:#f9f9f9; padding:4px 8px;">
                                <strong>Regulatory Standards (' . htmlspecialchars($regulation) . '):</strong>
                                [CIA Weightage: ' . $betterWeightPct . '% Better Mid + ' . $lesserWeightPct . '% Lower Mid]
                            </td>
                        </tr>
                    </tfoot>
                    </table>';
                }
            }
            $mpdf->WriteHTML($internalMarksHTML);
        }

        // Save PDF file
        $filename = 'e_bluebook_' . $sub_id . '_' . date("Ymd_His") . '.pdf';
        $outDir = __DIR__ . '/../uploads';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $filepath = $outDir . '/' . $filename;
        $mpdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Render Course Dossier Cover Page & Preamble (Page 1)
     */
    private function renderCoverPage(array $meta, array $cos = [])
    {
        $logoPath = realpath(__DIR__ . '/../images/jntuacea.png');
        $logoImg = ($logoPath && file_exists($logoPath)) ? '<img src="' . $logoPath . '" style="height: 80px; margin-bottom: 10px;">' : '';

        // Decode any double-encoded HTML entities in department name and ensure clean ampersand
        $cleanDeptName = html_entity_decode($meta['dept_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $cleanDeptName = html_entity_decode($cleanDeptName, ENT_QUOTES, 'UTF-8');
        $cleanDeptName = str_ireplace(['&amp;', '&amp'], '&', $cleanDeptName);
        $cleanDeptName = strtoupper(trim($cleanDeptName));
        $deptHeading = 'DEPARTMENT OF ' . htmlspecialchars($cleanDeptName, ENT_QUOTES, 'UTF-8');

        // Class & Semester: display classname field only
        $classSemDisplay = !empty($meta['classname']) ? trim($meta['classname']) : (!empty($meta['class']) ? trim($meta['class']) : 'N/A');

        $pStart = $meta['start_date'] ?? 'N/A';
        $pEnd = $meta['end_date'] ?? 'N/A';
        if ($pStart !== 'N/A' && $pEnd !== 'N/A') {
            $periodDisplay = $pStart . ' to ' . $pEnd;
        } elseif ($pStart !== 'N/A') {
            $periodDisplay = 'From ' . $pStart;
        } elseif ($pEnd !== 'N/A') {
            $periodDisplay = 'Up to ' . $pEnd;
        } else {
            $periodDisplay = 'N/A';
        }

        $subjectDisplay = !empty($meta['subject']) ? $meta['subject'] : 'N/A';
        $subcodeDisplay = !empty($meta['subcode']) ? $meta['subcode'] : 'N/A';
        // Program & Branch: display specfullname field only
        $branchDisplay = !empty($meta['spec_fullname']) ? trim($meta['spec_fullname']) : (!empty($meta['branch']) ? trim($meta['branch']) : 'N/A');
        $acadYearDisplay = !empty($meta['acad_year']) ? $meta['acad_year'] : 'N/A';
        $courseTypeDisplay = !empty($meta['course_type']) ? $meta['course_type'] : 'Theory';
        $facultyDisplay = !empty($meta['faculty']) ? $meta['faculty'] : 'N/A';

        $html = '<div style="font-family: Arial, sans-serif; text-align: center;">
            ' . $logoImg . '
            <h2 style="font-size: 16.5px; color: #1a365d; margin: 0 0 5px 0; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">
                JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU
            </h2>
            <div style="font-size: 12px; color: #555; margin-bottom: 12px; font-style: italic;">
                (Constituent College of Jawaharlal Nehru Technological University Anantapur)
            </div>
            <h3 style="font-size: 14px; color: #0d6efd; margin: 0 0 20px 0; text-transform: uppercase; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; letter-spacing: 0.5px;">
                ' . $deptHeading . '
            </h3>

            <div style="background-color: #f8f9fa; border: 1.5px solid #0d6efd; border-radius: 5px; padding: 12px 16px; margin: 12px auto 26px auto; width: 94%;">
                <h1 style="font-size: 19px; color: #1a365d; margin: 0 0 5px 0; letter-spacing: 0.8px; font-weight: bold;">
                    ACADEMIC RECORD BOOK
                </h1>
                <div style="font-size: 13px; font-weight: bold; color: #198754; text-transform: uppercase; letter-spacing: 1.2px;">
                    E-BLUEBOOK
                </div>
            </div>

            <!-- Course Metadata Card (One-Column Mode) -->
            <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 11.5px; table-layout: fixed; margin-bottom: 30px; text-align: left;">
                <tr style="background-color: #f2f4f8;">
                    <th colspan="2" style="text-align: center; font-size: 12.5px; font-weight: bold; color: #1a365d; padding: 8px; letter-spacing: 0.5px;">
                        COURSE &amp; CLASS IDENTIFICATION
                    </th>
                </tr>
                <tr>
                    <td style="width: 28%; font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Course Title:</td>
                    <td style="width: 72%; font-weight: bold; color: #0d6efd; padding: 8px 12px;">' . htmlspecialchars($subjectDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Course Code:</td>
                    <td style="font-weight: bold; padding: 8px 12px;">' . htmlspecialchars($subcodeDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Program &amp; Branch:</td>
                    <td style="padding: 8px 12px;">' . htmlspecialchars($branchDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Class &amp; Semester:</td>
                    <td style="padding: 8px 12px;">' . htmlspecialchars($classSemDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Academic Year:</td>
                    <td style="padding: 8px 12px;">' . htmlspecialchars($acadYearDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Course Type:</td>
                    <td style="padding: 8px 12px;">' . htmlspecialchars($courseTypeDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Course Instructor:</td>
                    <td style="font-weight: bold; padding: 8px 12px;">' . htmlspecialchars($facultyDisplay) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; background-color: #fafafa; padding: 8px 12px;">Instruction Period:</td>
                    <td style="padding: 8px 12px;">' . htmlspecialchars($periodDisplay) . '</td>
                </tr>
            </table>

            <!-- Certification & Endorsement Block -->
            <div style="background-color: #fff8e1; border: 1.5px solid #ffe082; padding: 14px 18px; font-size: 10.5px; line-height: 1.6; text-align: justify; margin-top: 25px; color: #5d4037; border-radius: 4px;">
                <strong>Academic Certification:</strong> Certified that this operational course file (e-Bluebook) constitutes a complete, unamended and official institutional record of daily student attendance, class instruction log, and continuous internal assessments conducted in accordance with Autonomous Academic Regulations.
            </div>
        </div>';

        return $html;
    }
}

<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_FEEDBACK','export');}

/**
 * Enhanced Feedback Download Handler
 * 
 * Handles CSV and PDF downloads with enhanced services
 * CSV: Basic spreadsheet export (maintained for compatibility)
 * PDF: Professional multi-page reports using EnhancedPDFService
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
}

require_once("dbcredentials.class.php");
require_once("feedbackservice.class.php");

// Authorization validation
$isAuthorized = false;
$userRole = $_SESSION['role'] ?? '';
$userDeptId = $_SESSION['dept_id'] ?? null;
$userFacId = $_SESSION['facid'] ?? null;

if (php_sapi_name() === 'cli') {
    $isAuthorized = true;
    $activeRole = 'admin';
} elseif (!empty($_SESSION['user'])) {
    if ($userRole === 'admin' || $userRole === 'superadmin' || !empty($_SESSION['admin_id'])) {
        $isAuthorized = true;
        $activeRole = 'admin';
    } elseif ($userRole === 'hod' && !empty($userDeptId)) {
        $isAuthorized = true;
        $activeRole = 'hod';
    } elseif (!empty($userFacId) || $userRole === 'faculty') {
        $isAuthorized = true;
        $activeRole = 'faculty';
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    die("Access Denied: You do not have permission to download feedback reports.");
}

// Extract input parameters
if (php_sapi_name() === 'cli') {
    $format = 'pdf';
    $level  = 'subject';
    $sub_id = intval($argv[1] ?? 656);
    $cls_id = intval($argv[2] ?? 0);
    $fac_id = 0;
    $dept_id = 0;
    $acad_year = '';
} else {
    $format = strtolower($_POST['format'] ?? $_GET['format'] ?? 'csv');
    $level  = strtolower($_POST['level'] ?? $_GET['level'] ?? 'subject');

    $sub_id  = intval($_POST['sub_id'] ?? $_GET['sub_id'] ?? 0);
    $cls_id  = intval($_POST['cls_id'] ?? $_GET['cls_id'] ?? 0);
    $fac_id  = intval($_POST['fac_id'] ?? $_GET['fac_id'] ?? 0);
    $dept_id = intval($_POST['dept_id'] ?? $_GET['dept_id'] ?? 0);
    $acad_year = trim($_POST['acad_year'] ?? $_GET['acad_year'] ?? '');
}

// Role scope enforcement
if ($activeRole === 'faculty') {
    if ($level === 'faculty') {
        $fac_id = intval($userFacId);
    } else {
        $level = 'subject';
        if ($sub_id <= 0) {
            die("Invalid Subject ID");
        }
        // Verify faculty teaches this subject
        require_once("faculty.class.php");
        $facultyObj = new Faculty();
        $facultySubjects = $facultyObj->getSubjectsByFacultyId($userFacId);
        $allowedSubIds = array_column($facultySubjects['data'] ?? [], 'id');
        if (!in_array($sub_id, $allowedSubIds)) {
            http_response_code(403);
            die("Access Denied: You are not assigned to this subject.");
        }
    }
} elseif ($activeRole === 'hod') {
    $dept_id = $userDeptId;
    if ($level === 'department') {
        // HOD viewing their department
    } elseif ($level === 'class' && $cls_id > 0) {
        $feedbackService = new FeedbackService();
        $clsMeta = $feedbackService->getClassFeedback($cls_id)['meta'] ?? [];
        if (!empty($clsMeta) && $clsMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Class does not belong to your department.");
        }
    } elseif ($level === 'subject' && $sub_id > 0) {
        $feedbackService = new FeedbackService();
        $subMeta = $feedbackService->getSubjectFeedback($sub_id)['meta'] ?? [];
        if (!empty($subMeta) && $subMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Subject does not belong to your department.");
        }
    } elseif ($level === 'faculty' && $fac_id > 0) {
        $feedbackService = new FeedbackService();
        $facMeta = $feedbackService->getFacultyFeedback($fac_id, null, $acad_year)['faculty'] ?? [];
        if (!empty($facMeta) && isset($facMeta['dept_id']) && $facMeta['dept_id'] != $userDeptId) {
            die("Access Denied: Faculty does not belong to your department.");
        }
    }
}

// -------------------------------------------------------------
// FORMAT 1: CSV DOWNLOAD (Maintained for compatibility)
// -------------------------------------------------------------
if ($format === 'csv') {
    try {
        $feedbackService = new FeedbackService();
        
        // Fetch data based on level
        if ($level === 'subject') {
            $data = $feedbackService->getSubjectFeedback($sub_id, $cls_id);
            $filenamePrefix = "feedback_subject_" . $sub_id;
        } elseif ($level === 'class') {
            $data = $feedbackService->getClassFeedback($cls_id);
            $filenamePrefix = "feedback_class_" . $cls_id;
        } elseif ($level === 'faculty') {
            $data = $feedbackService->getFacultyFeedback($fac_id, $cls_id, $acad_year);
            $filenamePrefix = "feedback_faculty_" . $fac_id . (!empty($acad_year) ? "_{$acad_year}" : "");
        } elseif ($level === 'department') {
            $data = $feedbackService->getDepartmentFeedback($dept_id);
            $filenamePrefix = "feedback_department_" . $dept_id;
        } else {
            die("Invalid level specified");
        }
        
        if (empty($data) || ($data['status'] ?? 0) != 1) {
            die("No data available for the selected parameters.");
        }
        
        // Generate CSV output
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filenamePrefix . '_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        
        // Write metadata first
        fputcsv($output, ['Report Level:', ucfirst($level) . '-wise Feedback']);
        fputcsv($output, ['Generated:', date('d-M-Y H:i:s')]);
        fputcsv($output, []);
        
        // Write data based on level
        if ($level === 'subject') {
            $meta = $data['meta'];
            fputcsv($output, ['Department:', $meta['dept_fullname'] ?? 'N/A']);
            fputcsv($output, ['Class:', $meta['classname'] ?? 'N/A']);
            fputcsv($output, ['Subject:', ($meta['sub_fullname'] ?? '') . ' (' . ($meta['subcode'] ?? '') . ')']);
            fputcsv($output, ['Faculty:', $meta['faculty_name'] ?? 'N/A']);
            fputcsv($output, []);
            
            fputcsv($output, ['Total Enrolled:', $data['total_enrolled']]);
            fputcsv($output, ['Total Responded:', $data['total_responded']]);
            fputcsv($output, ['Response Rate:', $data['response_rate'] . '%']);
            fputcsv($output, ['Overall Average:', number_format($data['summary']['overall_avg'], 2)]);
            fputcsv($output, ['Indirect Attainment:', 'Level ' . ($data['summary']['avg_indirect_level'] ?? 0) . ' (' . ($data['summary']['avg_target_pct'] ?? 0) . '%)']);
            fputcsv($output, []);
            
            // CO Feedback
            fputcsv($output, ['COURSE OUTCOME FEEDBACK']);
            fputcsv($output, ['CO Number', 'CO Description', 'Average Rating', 'Attainment Level', '% Target Met', 'Responses', '5★', '4★', '3★', '2★', '1★']);
            foreach ($data['co_feedback'] as $co) {
                fputcsv($output, [
                    'CO' . $co['co_number'],
                    $co['co_description'],
                    number_format($co['average_rating'], 2),
                    'Level ' . ($co['attainment_level'] ?? 0),
                    ($co['target_pct'] ?? 0) . '%',
                    $co['total_responses'],
                    $co['count_5'],
                    $co['count_4'],
                    $co['count_3'],
                    $co['count_2'],
                    $co['count_1']
                ]);
            }
            fputcsv($output, []);
            
            // CES Feedback
            if (!empty($data['ces_feedback']['total_responses'])) {
                fputcsv($output, ['COURSE END SURVEY (CES) - 16 PARAMETERS']);
                fputcsv($output, ['Section', 'Parameter', 'Statement', 'Average Rating']);
                $cesDefs = FeedbackService::getCesQuestions();
                $cesAverages = $data['ces_feedback']['averages'] ?? [];
                foreach ($cesDefs as $secTitle => $secQs) {
                    foreach ($secQs as $qKey => $qText) {
                        fputcsv($output, [
                            $secTitle,
                            strtoupper($qKey),
                            $qText,
                            number_format($cesAverages[$qKey] ?? 0, 2)
                        ]);
                    }
                }
                fputcsv($output, []);
            }
            
            // Faculty Feedback
            if (!empty($data['faculty_evaluations']['total_evaluations'])) {
                fputcsv($output, ['FACULTY EVALUATION - 19 PARAMETERS']);
                fputcsv($output, ['Domain', 'Parameter', 'Statement', 'Average Rating']);
                $facDefs = FeedbackService::getFacultyQuestions();
                $facAverages = $data['faculty_evaluations']['averages'] ?? [];
                foreach ($facDefs as $domain => $questions) {
                    foreach ($questions as $qKey => $qText) {
                        fputcsv($output, [
                            $domain,
                            strtoupper($qKey),
                            $qText,
                            number_format($facAverages[$qKey] ?? 0, 2)
                        ]);
                    }
                }
            }
            
        } elseif ($level === 'class') {
            $meta = $data['meta'];
            fputcsv($output, ['Department:', $meta['dept_fullname'] ?? 'N/A']);
            fputcsv($output, ['Class:', $meta['classname'] ?? 'N/A']);
            fputcsv($output, ['Academic Year:', $meta['acad_year'] ?? 'N/A']);
            fputcsv($output, []);
            
            fputcsv($output, ['Total Students:', $data['total_students']]);
            fputcsv($output, ['Total Responded:', $data['total_responded']]);
            fputcsv($output, ['Response Rate:', $data['response_rate'] . '%']);
            fputcsv($output, ['Class Average Rating:', number_format($data['summary']['overall_avg'], 2)]);
            fputcsv($output, ['Indirect Attainment:', 'Level ' . ($data['summary']['avg_indirect_level'] ?? 0) . ' (' . ($data['summary']['avg_target_pct'] ?? 0) . '%)']);
            fputcsv($output, []);
            
            // Subjects breakdown
            fputcsv($output, ['SUBJECTS PERFORMANCE OVERVIEW']);
            fputcsv($output, ['S.No', 'Subject Code', 'Subject Name', 'Faculty Assigned', 'Average Rating', 'Indirect Level', '% Target Met']);
            $sno = 1;
            foreach ($data['subjects'] as $sub) {
                $stats = $sub['summary'] ?? [];
                fputcsv($output, [
                    $sno++,
                    $sub['subcode'],
                    $sub['sub_fullname'],
                    $sub['faculty_names'] ?? 'N/A',
                    number_format($stats['overall_co_avg'] ?? 0, 2),
                    'Level ' . ($stats['avg_indirect_level'] ?? 0),
                    ($stats['avg_target_pct'] ?? 0) . '%'
                ]);
            }
            fputcsv($output, []);
            
            // Detailed CO breakdown
            if (!empty($data['co_feedback'])) {
                fputcsv($output, ['COURSE OUTCOME BREAKDOWN (ALL SUBJECTS)']);
                fputcsv($output, ['Subject Code', 'Subject Name', 'CO Number', 'CO Description', 'Average Rating', 'Attainment Level', '% Target Met', 'Responses', '5★', '4★', '3★', '2★', '1★']);
                foreach ($data['co_feedback'] as $co) {
                    fputcsv($output, [
                        $co['subcode'] ?? '',
                        $co['sub_fullname'] ?? '',
                        'CO' . $co['co_number'],
                        $co['co_description'],
                        number_format($co['average_rating'], 2),
                        'Level ' . ($co['attainment_level'] ?? 0),
                        ($co['target_pct'] ?? 0) . '%',
                        $co['total_responses'],
                        $co['count_5'],
                        $co['count_4'],
                        $co['count_3'],
                        $co['count_2'],
                        $co['count_1']
                    ]);
                }
            }
            
        } elseif ($level === 'faculty') {
            $fac = $data['faculty'] ?? [];
            fputcsv($output, ['Faculty Name:', $fac['faculty_name'] ?? 'N/A']);
            fputcsv($output, ['Designation:', $fac['designation'] ?? 'N/A']);
            fputcsv($output, ['Department:', $fac['dept_fullname'] ?? 'N/A']);
            fputcsv($output, ['Academic Year:', $data['meta']['acad_year'] ?? (!empty($acad_year) ? $acad_year : 'All Academic Years')]);
            fputcsv($output, []);
            
            fputcsv($output, ['Total Assigned Courses:', $data['summary']['total_subjects'] ?? count($data['subjects'] ?? [])]);
            fputcsv($output, ['CO Feedback Average:', number_format($data['summary']['overall_co_avg'] ?? 0, 2)]);
            if (!empty($data['faculty_evaluations']['avg_score'])) {
                fputcsv($output, ['Faculty Survey Score:', number_format($data['faculty_evaluations']['avg_score'], 2)]);
            }
            fputcsv($output, []);

            if (!empty($data['academic_year_summary'])) {
                fputcsv($output, ['ACADEMIC YEAR PERFORMANCE SUMMARY']);
                fputcsv($output, ['Academic Year', 'Courses Taught', 'CO Responses', 'CO Feedback Average', 'Faculty Survey Score', 'Overall Rating']);
                foreach ($data['academic_year_summary'] as $ayStats) {
                    fputcsv($output, [
                        $ayStats['acad_year'],
                        $ayStats['total_subjects'],
                        $ayStats['total_co_responses'],
                        $ayStats['co_average'] > 0 ? number_format($ayStats['co_average'], 2) : 'N/A',
                        $ayStats['faculty_eval_score'] > 0 ? number_format($ayStats['faculty_eval_score'], 2) : 'N/A',
                        $ayStats['overall_rating'] > 0 ? number_format($ayStats['overall_rating'], 2) : 'N/A'
                    ]);
                }
                fputcsv($output, []);
            }
            
            // Assigned Courses
            fputcsv($output, ['ASSIGNED COURSES']);
            fputcsv($output, ['S.No', 'Class', 'Subject Code', 'Subject Name', 'Type', 'Academic Year', 'CO Feedback Average']);
            $sno = 1;
            foreach ($data['subjects'] as $sub) {
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
                fputcsv($output, [
                    $sno++,
                    $sub['classname'] ?? 'N/A',
                    $sub['subcode'],
                    $sub['sub_fullname'],
                    ucfirst($sub['sub_type'] ?? 'Theory'),
                    $sub['acad_year'] ?? 'N/A',
                    $avg > 0 ? number_format($avg, 2) : 'N/A'
                ]);
            }
            fputcsv($output, []);
            
            // CO Feedback
            if (!empty($data['co_feedback'])) {
                fputcsv($output, ['COURSE OUTCOMES BREAKDOWN']);
                fputcsv($output, ['Class', 'Subject Code', 'CO Number', 'CO Description', 'Average Rating', 'Responses', '5★', '4★', '3★', '2★', '1★']);
                foreach ($data['co_feedback'] as $co) {
                    fputcsv($output, [
                        $co['classname'] ?? 'N/A',
                        $co['subcode'] ?? '',
                        'CO' . $co['co_number'],
                        $co['co_description'],
                        number_format($co['average_rating'], 2),
                        $co['total_responses'],
                        $co['count_5'],
                        $co['count_4'],
                        $co['count_3'],
                        $co['count_2'],
                        $co['count_1']
                    ]);
                }
                fputcsv($output, []);
            }
            
            // Faculty Evaluations (19 Parameters)
            if (!empty($data['faculty_evaluations']['total_evaluations'])) {
                fputcsv($output, ['STUDENT EVALUATION ON FACULTY (19 OBE PARAMETERS)']);
                fputcsv($output, ['Domain', 'Parameter', 'Statement', 'Average Rating']);
                $facDefs = FeedbackService::getFacultyQuestions();
                $facAverages = $data['faculty_evaluations']['averages'] ?? [];
                foreach ($facDefs as $domain => $questions) {
                    foreach ($questions as $qKey => $qText) {
                        fputcsv($output, [
                            $domain,
                            strtoupper($qKey),
                            $qText,
                            number_format($facAverages[$qKey] ?? 0, 2)
                        ]);
                    }
                }
            }
            
        } elseif ($level === 'department') {
            $dept = $data['department'] ?? [];
            fputcsv($output, ['Department:', $dept['dept_fullname'] ?? ($dept['dept_shortname'] ?? 'N/A')]);
            fputcsv($output, []);
            
            fputcsv($output, ['Total Active Classes:', $data['summary']['total_classes'] ?? 0]);
            fputcsv($output, ['Total Courses:', $data['summary']['total_subjects'] ?? 0]);
            fputcsv($output, ['Total Enrolled Students:', $data['summary']['total_students'] ?? 0]);
            fputcsv($output, ['Total Responses:', $data['summary']['total_responses'] ?? 0]);
            fputcsv($output, ['Department Average Rating:', number_format($data['summary']['overall_avg'] ?? 0, 2)]);
            fputcsv($output, []);
            
            fputcsv($output, ['CLASS PERFORMANCE OVERVIEW']);
            fputcsv($output, ['S.No', 'Class Name', 'Academic Year', 'Courses', 'Enrolled Students', 'Responded', 'Response Rate', 'Average Rating']);
            $sno = 1;
            foreach ($data['classes'] as $cls) {
                $stats = $cls['stats'] ?? [];
                fputcsv($output, [
                    $sno++,
                    $cls['classname'] ?? 'N/A',
                    $cls['acad_year'] ?? 'N/A',
                    $stats['total_subjects'] ?? 0,
                    $stats['total_students'] ?? 0,
                    $stats['total_responded'] ?? 0,
                    ($stats['response_rate'] ?? 0) . '%',
                    number_format($stats['overall_avg'] ?? 0, 2)
                ]);
            }
        }
        
        fclose($output);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        die("Error generating CSV report: " . $e->getMessage());
    }
}

// -------------------------------------------------------------
// FORMAT 2: PDF DOWNLOAD (Enhanced mPDF Service)
// -------------------------------------------------------------
if ($format === 'pdf') {
    require_once __DIR__ . '/services/EnhancedPDFService.php';
    
    try {
        $pdfService = new EnhancedPDFService();
        $filepath = $pdfService->generateFeedbackReport($level, [
            'sub_id' => $sub_id,
            'cls_id' => $cls_id,
            'fac_id' => $fac_id,
            'dept_id' => $dept_id,
            'acad_year' => $acad_year
        ]);
        
        $filename = basename($filepath);
        
        if (php_sapi_name() === 'cli') {
            echo "SUCCESS: Feedback PDF generated at: " . $filepath . " (" . filesize($filepath) . " bytes)\n";
            exit(0);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        readfile($filepath);
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        die("Error generating PDF report: " . $e->getMessage());
    }
    
    exit();
}

die("Unsupported format: " . htmlspecialchars($format));
?>
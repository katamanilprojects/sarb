<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/services/FeatureManager.php';
    \FeatureManager::requireAccess('MOD_OBE_ANALYSIS');
}
// facciaanalysis2.class.php - Unified Backward-Compatible CO Analysis Facade & AJAX Router
require_once("dbcredentials.class.php");
require_once("coattainment.class.php");
require_once("learninganalytics.class.php");

/**
 * COAnalysis Facade Class
 * Combines modular COAttainmentTrait and LearningAnalyticsTrait
 * for direct & indirect attainment, learning analytics, and AJAX reporting.
 */
class COAnalysis extends DBCredentials
{
    use COAttainmentTrait;
    use LearningAnalyticsTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Fetch Question-level Zero Marks Breakdown and Course Outcome (CO) Summary
     * for the faculty CIA Analysis & Attainment report.
     *
     * @param int|string|array $subject_id Single subject ID, comma-separated string, or array
     * @param int|string|null $assessment_number Assessment number (1, 2), 'all' (Overall CIA), or null
     * @return string JSON response
     */
    public function getZeroMarksAnalysis($subject_id, $assessment_number = null): string
    {
        // 1. Normalize and validate subject IDs
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        if (empty($sub_ids) || (count($sub_ids) === 1 && $sub_ids[0] <= 0)) {
            return json_encode([
                'status' => 0,
                'question_level_available' => false,
                'message' => 'Invalid Subject ID',
                'data' => []
            ]);
        }

        // 2. Validate subject-scope for every offering ID
        foreach ($sub_ids as $sid) {
            $this->assertSubjectScope((int)$sid);
        }

        // 3. Validate assessment scope: Overall CIA vs specific CIA assessment
        $is_overall = false;
        $assessment_clean = null;
        if ($assessment_number === null || $assessment_number === '' || strcasecmp((string)$assessment_number, 'all') === 0) {
            $is_overall = true;
        } elseif (strcasecmp((string)$assessment_number, 'see') === 0) {
            // SEE is excluded from CIA Analysis & Attainment
            return json_encode([
                'status' => 1,
                'question_level_available' => false,
                'message' => 'Question-level marks unavailable. (CIA Analysis covers Continuous Internal Assessments only; Semester End Exams are excluded.)',
                'data' => [
                    'is_overall' => false,
                    'is_multi_offering' => count($sub_ids) > 1,
                    'questions' => [],
                    'co_summary' => []
                ]
            ]);
        } else {
            $assessment_clean = filter_var($assessment_number, FILTER_VALIDATE_INT);
            if ($assessment_clean === false || $assessment_clean <= 0) {
                return json_encode([
                    'status' => 0,
                    'question_level_available' => false,
                    'message' => 'Invalid Assessment Number',
                    'data' => []
                ]);
            }
        }

        $sub_type = $this->getSubjectType($sub_ids);
        $in = $this->buildInClause($sub_ids);
        $is_multi_offering = count($sub_ids) > 1;

        // Fetch offering details for batch context
        $subQuery = "SELECT id, sub_fullname, sub_shortname, subcode, class_id, curr_sub_id, sub_type FROM subjects WHERE id IN ({$in['sql']})";
        $subRows = $this->fetchAssoc($subQuery, $in['params']);
        $offerings = [];
        foreach ($subRows as $sRow) {
            $offerings[(int)$sRow['id']] = $sRow;
        }

        // 4. Fetch authorized roster: distinct students enrolled per offering
        $rosterQuery = "SELECT ss.stu_id, ss.sub_id FROM student_sub ss WHERE ss.sub_id IN ({$in['sql']})";
        $rosterRows = $this->fetchAssoc($rosterQuery, $in['params']);
        $authorized_roster = []; // sub_id => [stu_id => true]
        foreach ($rosterRows as $rRow) {
            $sId = (int)$rRow['sub_id'];
            $stId = (int)$rRow['stu_id'];
            $authorized_roster[$sId][$stId] = true;
        }

        // 5. Fetch internal assessments and their questions (Overall CIA strictly excludes SEE)
        $qParams = $in['params'];
        $whereAssessment = "AND (i.assessment_number IS NULL OR (i.assessment_number != 'SEE' AND i.assessment_number NOT LIKE 'SEE%'))";
        if (!$is_overall) {
            $whereAssessment .= " AND i.assessment_number = ?";
            $qParams[] = $assessment_clean;
        }

        $questionsQuery = "
            SELECT
                i.id AS assessment_id,
                i.sub_id,
                i.assessment_number,
                ac.id AS component_id,
                ac.component_type,
                ac.sequence_number,
                aq.id AS question_id,
                aq.question_label,
                aq.question_type,
                aq.marks AS max_marks
            FROM internal_assessments i
            JOIN assessment_components ac ON ac.assessment_id = i.id
            JOIN assessment_questions aq ON aq.component_id = ac.id
            WHERE i.sub_id IN ({$in['sql']})
            {$whereAssessment}
            ORDER BY
                i.assessment_number,
                i.sub_id,
                CASE ac.component_type
                    WHEN 'Subjective' THEN 1
                    WHEN 'Objective' THEN 2
                    WHEN 'Assignment' THEN 3
                    WHEN 'Day-to-Day' THEN 4
                    WHEN 'Internal Exam' THEN 5
                    ELSE 6
                END,
                ac.sequence_number,
                aq.id
        ";
        $questionRows = $this->fetchAssoc($questionsQuery, $qParams);

        if (empty($questionRows)) {
            return json_encode([
                'status' => 1,
                'question_level_available' => false,
                'message' => 'Question-level marks unavailable.',
                'data' => [
                    'is_overall' => $is_overall,
                    'is_multi_offering' => $is_multi_offering,
                    'questions' => [],
                    'co_summary' => []
                ]
            ]);
        }

        $questionIds = array_values(array_unique(array_map('intval', array_column($questionRows, 'question_id'))));
        $qIn = $this->buildInClause($questionIds);

        // 6. Fetch Question-to-CO mappings
        $mapQuery = "
            SELECT qcm.question_id, c.id AS co_id, c.co_number, c.co_description
            FROM question_co_mapping qcm
            JOIN course_outcomes c ON qcm.co_id = c.id
            WHERE qcm.question_id IN ({$qIn['sql']})
            ORDER BY c.co_number
        ";
        $mapRows = $this->fetchAssoc($mapQuery, $qIn['params']);
        $questionCoMap = [];
        foreach ($mapRows as $mRow) {
            $qid = (int)$mRow['question_id'];
            $questionCoMap[$qid][] = [
                'co_id' => (int)$mRow['co_id'],
                'co_number' => (int)$mRow['co_number'],
                'co_label' => 'CO' . $mRow['co_number'],
                'co_description' => $mRow['co_description'] ?? ''
            ];
        }

        // 7. Fetch all COs for these subjects (so COs without mapped questions are distinct from 0)
        $coInfoQuery = "SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id IN ({$in['sql']}) ORDER BY co_number";
        $allCoRows = $this->fetchAssoc($coInfoQuery, $in['params']);
        if (empty($allCoRows)) {
            $masterCOQuery = "
                SELECT co.id, co.co_number, co.co_description
                FROM course_outcomes co
                JOIN subjects s ON s.curr_sub_id = co.curr_sub_id
                WHERE s.id IN ({$in['sql']}) AND co.sub_id IS NULL
                ORDER BY co.co_number
            ";
            $allCoRows = $this->fetchAssoc($masterCOQuery, $in['params']);
        }
        $allCos = [];
        foreach ($allCoRows as $coRow) {
            $cNum = (int)$coRow['co_number'];
            if (!isset($allCos[$cNum])) {
                $allCos[$cNum] = [
                    'co_id' => (int)$coRow['id'],
                    'co_number' => $cNum,
                    'co_label' => 'CO' . $cNum,
                    'co_description' => $coRow['co_description'] ?? ''
                ];
            }
        }
        ksort($allCos);

        // 8. Fetch student marks in batch for all questions in scope
        $marksQuery = "
            SELECT sm.stu_id, sm.question_id, sm.marks_obtained
            FROM student_marks sm
            WHERE sm.question_id IN ({$qIn['sql']})
        ";
        $marksRows = $this->fetchAssoc($marksQuery, $qIn['params']);

        if (empty($marksRows)) {
            return json_encode([
                'status' => 1,
                'question_level_available' => false,
                'message' => 'Question-level marks unavailable.',
                'data' => [
                    'is_overall' => $is_overall,
                    'is_multi_offering' => $is_multi_offering,
                    'questions' => [],
                    'co_summary' => []
                ]
            ]);
        }

        // Group marks: marksByQuestion[qid][stu_id] = float(marks_obtained)
        $marksByQuestion = [];
        foreach ($marksRows as $mRow) {
            $qid = (int)$mRow['question_id'];
            $stId = (int)$mRow['stu_id'];
            $marksByQuestion[$qid][$stId] = (float)$mRow['marks_obtained'];
        }

        // 9. Process questions
        $questionsData = [];
        $coMappedQuestions = [];
        $coZeroStudents = [];
        $coStudentEarnedTotal = [];
        $coStudentEvaluated = [];
        $coHasChoiceQuestion = [];
        $allDistinctZeroStudents = [];

        foreach ($questionRows as $qRow) {
            $qid = (int)$qRow['question_id'];
            $subId = (int)$qRow['sub_id'];
            $compType = $qRow['component_type'];
            $qLabel = $qRow['question_label'];
            $qType = trim($qRow['question_type'] ?? '');
            $maxMarks = (float)$qRow['max_marks'];
            $aNum = $qRow['assessment_number'];
            $aLabel = 'CIA ' . $aNum;

            $offeringName = '';
            if (isset($offerings[$subId])) {
                $offeringName = $offerings[$subId]['sub_shortname'] ?: $offerings[$subId]['sub_fullname'];
            }

            $offeringRoster = $authorized_roster[$subId] ?? [];

            // Choice question detection: 'Choice' question_type OR Theory Subjective component
            $isChoice = (strcasecmp($qType, 'choice') === 0 || ($sub_type === 'theory' && strcasecmp($compType, 'subjective') === 0));

            $qMarks = $marksByQuestion[$qid] ?? [];
            $recordedCount = 0;
            $zeroCount = 0;

            foreach ($qMarks as $stId => $obtained) {
                // Must belong to authorized roster for this offering
                if (!empty($offeringRoster[$stId])) {
                    $recordedCount++;

                    // Track student score for each mapped CO
                    if (!empty($questionCoMap[$qid])) {
                        foreach ($questionCoMap[$qid] as $mappedCo) {
                            $coNum = $mappedCo['co_number'];
                            $coStudentEvaluated[$coNum][$stId] = true;
                            $coStudentEarnedTotal[$coNum][$stId] = ($coStudentEarnedTotal[$coNum][$stId] ?? 0.0) + (float)$obtained;
                        }
                    }

                    // Exact zero check using stored precision
                    if ($obtained === 0.0) {
                        $zeroCount++;
                        $allDistinctZeroStudents[$stId] = true;
                        if (!empty($questionCoMap[$qid])) {
                            foreach ($questionCoMap[$qid] as $mappedCo) {
                                $coNum = $mappedCo['co_number'];
                                $coZeroStudents[$coNum][$stId] = true;
                            }
                        }
                    }
                }
            }

            // Track mapped CO labels and questions
            $mappedCoLabels = [];
            if (!empty($questionCoMap[$qid])) {
                foreach ($questionCoMap[$qid] as $mappedCo) {
                    $mappedCoLabels[] = $mappedCo['co_label'];
                    $coNum = $mappedCo['co_number'];
                    $coMappedQuestions[$coNum][] = ($is_overall ? "{$aLabel}: {$qLabel}" : $qLabel) . ($is_multi_offering ? " ({$offeringName})" : '');
                    if ($isChoice) {
                        $coHasChoiceQuestion[$coNum] = true;
                    }
                }
            }

            $choiceStatus = null;
            $rateDisplay = null;
            $ratePercentage = null;

            if ($recordedCount === 0) {
                $rateDisplay = 'Question-level marks unavailable.';
            } elseif ($isChoice) {
                // Choice applicability unknown: omit rate
                $choiceStatus = 'Choice applicability unknown';
                $rateDisplay = 'Choice applicability unknown';
            } else {
                // Observed record rate among recorded scores
                $ratePercentage = round(($zeroCount / $recordedCount) * 100, 1);
                $rateDisplay = "{$ratePercentage}% ({$zeroCount} of {$recordedCount} recorded zeros among recorded scores)";
            }

            $questionsData[] = [
                'question_id' => $qid,
                'sub_id' => $subId,
                'offering_name' => $offeringName,
                'assessment_number' => $aNum,
                'assessment_label' => $aLabel,
                'component_type' => $compType,
                'question_label' => $qLabel,
                'question_type' => $qType,
                'is_choice' => $isChoice,
                'choice_status' => $choiceStatus,
                'mapped_cos' => $mappedCoLabels,
                'max_marks' => $maxMarks,
                'recorded_scores_count' => $recordedCount,
                'zero_scores_count' => $zeroCount,
                'rate_display' => $rateDisplay,
                'rate_percentage' => $ratePercentage
            ];
        }

        // Check Semester End (SEE) marks submission status
        $seeQuery = "SELECT COUNT(*) AS total FROM external_assessment_marks WHERE subject_id IN ({$in['sql']}) AND is_current = 1";
        $seeRows = $this->fetchAssoc($seeQuery, $in['params']);
        $hasSee = !empty($seeRows[0]['total']) && (int)$seeRows[0]['total'] > 0;
        $seeStatus = $hasSee ? 'SEE Marks Submitted' : 'Pending SEE Marks';

        // 10. Process CO Summary
        $coSummaryData = [];
        foreach ($allCos as $coNum => $coMeta) {
            $hasMappedEvidence = !empty($coMappedQuestions[$coNum]);
            $mappedLabels = $hasMappedEvidence ? array_values(array_unique($coMappedQuestions[$coNum])) : [];

            $zeroStudentsCount = null;
            $zeroTotalCount = null;
            $limitationNote = null;

            if (!$hasMappedEvidence) {
                $zeroStudentsDisplay = 'No mapped question evidence';
                $zeroTotalDisplay = 'No mapped question evidence';
                $limitationNote = 'No questions mapped to this CO in current CIA scope.';
            } else {
                $distinctZeroStudents = count($coZeroStudents[$coNum] ?? []);
                $zeroStudentsCount = $distinctZeroStudents;
                $zeroStudentsDisplay = (string)$distinctZeroStudents;

                // Calculate students whose total earned marks across mapped questions is exactly 0.0
                $coZeroTotalCount = 0;
                if (!empty($coStudentEvaluated[$coNum])) {
                    foreach ($coStudentEvaluated[$coNum] as $stId => $isEval) {
                        $tot = (float)($coStudentEarnedTotal[$coNum][$stId] ?? 0.0);
                        if (abs($tot) < 0.0001) {
                            $coZeroTotalCount++;
                        }
                    }
                }
                $zeroTotalCount = $coZeroTotalCount;
                $zeroTotalDisplay = (string)$coZeroTotalCount;

                $notes = [];
                if (!$hasSee) {
                    $notes[] = 'Calculated from CIA only (Pending SEE Marks).';
                }
                if (!empty($coHasChoiceQuestion[$coNum])) {
                    $notes[] = 'Choice applicability unknown for some mapped questions.';
                }
                $limitationNote = !empty($notes) ? implode(' ', $notes) : 'Calculated across mapped CIA questions.';
            }

            $coSummaryData[] = [
                'co_id' => $coMeta['co_id'],
                'co_number' => $coNum,
                'co_label' => $coMeta['co_label'],
                'co_description' => $coMeta['co_description'],
                'has_mapped_evidence' => $hasMappedEvidence,
                'mapped_questions' => $mappedLabels,
                'zero_students_count' => $zeroStudentsCount,
                'zero_students_display' => $zeroStudentsDisplay,
                'zero_total_count' => $zeroTotalCount,
                'zero_total_display' => $zeroTotalDisplay,
                'zero_total_status' => $zeroTotalDisplay, // backward compatibility
                'see_status' => $seeStatus,
                'limitation_note' => $limitationNote
            ];
        }

        $summary = [
            'total_questions' => count($questionsData),
            'questions_with_zeros' => count(array_filter($questionsData, function($q) { return $q['zero_scores_count'] > 0; })),
            'choice_questions_count' => count(array_filter($questionsData, function($q) { return $q['is_choice']; })),
            'total_distinct_students_with_zeros' => count($allDistinctZeroStudents),
            'cos_with_zeros' => count(array_filter($coSummaryData, function($co) { return ($co['zero_students_count'] ?? 0) > 0; })),
            'cos_with_zero_totals' => count(array_filter($coSummaryData, function($co) { return ($co['zero_total_count'] ?? 0) > 0; })),
            'total_cos' => count($coSummaryData),
            'has_see' => $hasSee,
            'see_status' => $seeStatus
        ];

        return json_encode([
            'status' => 1,
            'question_level_available' => true,
            'data' => [
                'is_overall' => $is_overall,
                'is_multi_offering' => $is_multi_offering,
                'summary' => $summary,
                'questions' => $questionsData,
                'co_summary' => $coSummaryData
            ]
        ]);
    }
}

// ================= AJAX Request Handler =================
if (isset($_GET['action']) && !empty($_GET['sub_id'])) {
    $analysisObj = new COAnalysis();

    // Sanitize inputs (support single integer or comma-separated integers, e.g. "101,102")
    $raw_sub_id = trim($_GET['sub_id']);
    if (!preg_match('/^[0-9]+(,[0-9]+)*$/', $raw_sub_id)) {
        echo json_encode(["error" => "Invalid Subject ID"]);
        exit;
    }
    $sub_id = (strpos($raw_sub_id, ',') !== false) ? $raw_sub_id : (int)$raw_sub_id;

    // Handle 'all' and 'SEE' assessment cases explicitly
    $assessment_number_raw = isset($_GET['assessment_number']) ? trim((string)$_GET['assessment_number']) : null;
    if ($assessment_number_raw === null || $assessment_number_raw === '' || strtolower($assessment_number_raw) === 'all') {
        $assessment_number = null;
    } elseif (strcasecmp($assessment_number_raw, 'see') === 0) {
        $assessment_number = 'SEE';
    } else {
        $assessment_number = filter_var($assessment_number_raw, FILTER_VALIDATE_INT);
        if ($assessment_number === false || $assessment_number <= 0) {
            echo json_encode(["error" => "Invalid Assessment Number"]);
            exit;
        }
    }

    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {
            case 'co_attainment':
                echo $analysisObj->getCOAttainment($sub_id, $assessment_number);
                break;
            case 'po_attainment':
                echo $analysisObj->getPOAttainment($sub_id, $assessment_number);
                break;
            case 'blooms_performance':
                echo $analysisObj->getBloomsPerformance($sub_id, $assessment_number);
                break;
            case 'assessment_performance':
                echo $analysisObj->getAssessmentPerformance($sub_id, $assessment_number);
                break;
            case 'component_performance':
                echo $analysisObj->getComponentPerformance($sub_id, $assessment_number);
                break;
            case 'co_po_matrix':
                echo $analysisObj->getCoPoMatrixData($sub_id, $assessment_number);
                break;
            case 'co_spread':
                echo $analysisObj->getCOVisualSummary($sub_id, $assessment_number);
                break;
            case 'question_difficulty':
                echo $analysisObj->getQuestionDifficultyDiscrimination($sub_id, $assessment_number);
                break;
            case 'zero_marks':
                echo $analysisObj->getZeroMarksAnalysis($sub_id, $assessment_number);
                break;
            case 'comprehensive_attainment':
                echo $analysisObj->getComprehensiveAttainment($sub_id);
                break;
            default:
                echo json_encode(["error" => "Invalid action specified"]);
                break;
        }
    } catch (\Throwable $e) {
        $logs = new Logs();
        $logs->errLog("facciaanalysis2 action {$_GET['action']} error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        echo json_encode([
            "data" => [],
            "suggestions" => ["Unable to load " . htmlspecialchars($_GET['action']) . " data at this moment. Please retry."],
            "error" => $e->getMessage()
        ]);
    }
    exit;
}

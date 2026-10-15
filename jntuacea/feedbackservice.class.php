<?php
require_once("dbcredentials.class.php");
require_once("logs.class.php");

/**
 * FeedbackService - Multi-Granularity Service for Course Outcome & Questionnaire Feedback
 * Supports Subject-wise, Class-wise, Faculty-wise, and Department-wise retrieval and statistics.
 */
class FeedbackService
{
    private $conn;
    private $logs;
    private $classname = "FeedbackService";

    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        $this->logs = new Logs();
    }

    /**
     * Get feedback for a single subject (CO ratings, Questionnaire ratings, summary, and metadata)
     *
     * @param int $subject_id
     * @param int|null $class_id Optional class filter
     * @return array
     */
    public function getSubjectFeedback($subject_id, $class_id = null)
    {
        $myname = $this->classname . " - getSubjectFeedback - ";
        $res = [
            'status' => 0,
            'meta' => [],
            'total_enrolled' => 0,
            'total_responded' => 0,
            'response_rate' => 0,
            'co_feedback' => [],
            'qn_feedback' => [],
            'faculty_feedback' => [],
            'summary' => [
                'overall_co_avg' => 0,
                'overall_qn_avg' => 0,
                'overall_avg' => 0,
                'positive_response_pct' => 0,
                'total_ratings_count' => 0
            ]
        ];

        try {
            $subject_id = intval($subject_id);
            if ($subject_id <= 0) {
                return $res;
            }

            // 1. Fetch Subject, Class, and Department metadata
            $metaSql = "
                SELECT 
                    s.id AS subject_id,
                    s.subcode,
                    s.sub_fullname,
                    s.sub_shortname,
                    s.sub_type,
                    c.id AS class_id,
                    c.classname,
                    c.acad_year,
                    sp.id AS spec_id,
                    sp.spec_shortname,
                    sp.spec_fullname,
                    d.id AS dept_id,
                    d.dept_fullname
                FROM subjects s
                JOIN classes c ON s.class_id = c.id
                JOIN specialization sp ON c.spec_id = sp.id
                JOIN departments d ON sp.dept_id = d.id
                WHERE s.id = ? " . (!empty($class_id) ? " AND s.class_id = " . intval($class_id) : "") . "
            ";
            $stmt = $this->conn->prepare($metaSql);
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $metaResult = $stmt->get_result();
            $meta = $metaResult->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                return $res;
            }

            // Fetch mapped faculty
            $facSql = "
                SELECT f.id AS faculty_id, u.name AS faculty_name, f.designation
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN users u ON f.username = u.username
                WHERE fs.sub_id = ?
            ";
            $stmtFac = $this->conn->prepare($facSql);
            $stmtFac->bind_param("i", $subject_id);
            $stmtFac->execute();
            $facResult = $stmtFac->get_result();
            $facultyList = $facResult->fetch_all(MYSQLI_ASSOC);
            $stmtFac->close();

            $meta['faculties'] = $facultyList;
            $meta['faculty_name'] = !empty($facultyList) ? implode(", ", array_column($facultyList, 'faculty_name')) : 'N/A';
            $res['meta'] = $meta;

            // 2. Total enrolled students for this subject
            $stmtEnroll = $this->conn->prepare("SELECT COUNT(DISTINCT stu_id) AS total FROM student_sub WHERE sub_id = ?");
            $stmtEnroll->bind_param("i", $subject_id);
            $stmtEnroll->execute();
            $enrollRow = $stmtEnroll->get_result()->fetch_assoc();
            $res['total_enrolled'] = intval($enrollRow['total'] ?? 0);
            $stmtEnroll->close();

            // 3. Total distinct students responded for this subject
            $stmtResp = $this->conn->prepare("SELECT COUNT(DISTINCT student_id) AS total FROM student_co_feedback WHERE subject_id = ?");
            $stmtResp->bind_param("i", $subject_id);
            $stmtResp->execute();
            $respRow = $stmtResp->get_result()->fetch_assoc();
            $res['total_responded'] = intval($respRow['total'] ?? 0);
            $stmtResp->close();

            if ($res['total_enrolled'] > 0) {
                $res['response_rate'] = round(($res['total_responded'] / $res['total_enrolled']) * 100, 1);
            }

            // 4. Fetch Course Outcomes Feedback with Rating Distributions
            $coSql = "
                SELECT 
                    co.id AS co_id,
                    co.co_number,
                    co.co_description,
                    COUNT(scf.id) AS total_responses,
                    COALESCE(AVG(scf.rating), 0) AS average_rating,
                    COALESCE(SUM(CASE WHEN scf.rating = 5 THEN 1 ELSE 0 END), 0) AS count_5,
                    COALESCE(SUM(CASE WHEN scf.rating = 4 THEN 1 ELSE 0 END), 0) AS count_4,
                    COALESCE(SUM(CASE WHEN scf.rating = 3 THEN 1 ELSE 0 END), 0) AS count_3,
                    COALESCE(SUM(CASE WHEN scf.rating = 2 THEN 1 ELSE 0 END), 0) AS count_2,
                    COALESCE(SUM(CASE WHEN scf.rating = 1 THEN 1 ELSE 0 END), 0) AS count_1
                FROM course_outcomes co
                LEFT JOIN student_co_feedback scf ON co.id = scf.co_id AND scf.subject_id = ?
                WHERE co.sub_id = ?
                GROUP BY co.id, co.co_number, co.co_description
                ORDER BY co.co_number ASC
            ";
            $stmtCO = $this->conn->prepare($coSql);
            $stmtCO->bind_param("ii", $subject_id, $subject_id);
            $stmtCO->execute();
            $coResult = $stmtCO->get_result();
            $coFeedback = [];
            while ($row = $coResult->fetch_assoc()) {
                $row['average_rating'] = floatval($row['average_rating']);
                $row['total_responses'] = intval($row['total_responses']);
                $metrics = self::computeCoAttainmentMetrics($row['total_responses'], $row['count_5'], $row['count_4'], $row['count_3']);
                $row = array_merge($row, $metrics);
                $coFeedback[] = $row;
            }
            $stmtCO->close();
            $res['co_feedback'] = $coFeedback;

            // 5. Fetch Questionnaire Feedback
            $qnSql = "
                SELECT 
                    sqq.id AS question_id,
                    sqq.question_number,
                    sqq.question_text,
                    sqq.created_by_role,
                    COUNT(sqr.id) AS total_responses,
                    COALESCE(AVG(sqr.rating), 0) AS average_rating,
                    COALESCE(SUM(CASE WHEN sqr.rating = 5 THEN 1 ELSE 0 END), 0) AS count_5,
                    COALESCE(SUM(CASE WHEN sqr.rating = 4 THEN 1 ELSE 0 END), 0) AS count_4,
                    COALESCE(SUM(CASE WHEN sqr.rating = 3 THEN 1 ELSE 0 END), 0) AS count_3,
                    COALESCE(SUM(CASE WHEN sqr.rating = 2 THEN 1 ELSE 0 END), 0) AS count_2,
                    COALESCE(SUM(CASE WHEN sqr.rating = 1 THEN 1 ELSE 0 END), 0) AS count_1
                FROM subject_questionnaire_questions sqq
                LEFT JOIN student_questionnaire_responses sqr ON sqq.id = sqr.question_id AND sqr.subject_id = ?
                WHERE sqq.subject_id = ?
                GROUP BY sqq.id, sqq.question_number, sqq.question_text, sqq.created_by_role
                ORDER BY sqq.question_number ASC
            ";
            $stmtQN = $this->conn->prepare($qnSql);
            $stmtQN->bind_param("ii", $subject_id, $subject_id);
            $stmtQN->execute();
            $qnResult = $stmtQN->get_result();
            $qnFeedback = [];
            while ($row = $qnResult->fetch_assoc()) {
                $row['average_rating'] = floatval($row['average_rating']);
                $row['total_responses'] = intval($row['total_responses']);
                $qnFeedback[] = $row;
            }
            $stmtQN->close();
            $res['qn_feedback'] = $qnFeedback;

            // 6. Fetch Course End Survey (CES) Part B (16 Questions) and Part C (Qualitative Remarks)
            $cesSql = "
                SELECT 
                    COUNT(*) as total_ces,
                    AVG(ces_q1) as q1, AVG(ces_q2) as q2, AVG(ces_q3) as q3, AVG(ces_q4) as q4,
                    AVG(ces_q5) as q5, AVG(ces_q6) as q6, AVG(ces_q7) as q7, AVG(ces_q8) as q8,
                    AVG(ces_q9) as q9, AVG(ces_q10) as q10, AVG(ces_q11) as q11, AVG(ces_q12) as q12,
                    AVG(ces_q13) as q13, AVG(ces_q14) as q14, AVG(ces_q15) as q15, AVG(ces_q16) as q16
                FROM student_ces_feedback
                WHERE subject_id = ?
            ";
            $stmtCES = $this->conn->prepare($cesSql);
            $stmtCES->bind_param("i", $subject_id);
            $stmtCES->execute();
            $cesRow = $stmtCES->get_result()->fetch_assoc();
            $stmtCES->close();

            $cesFeedback = [
                'total_responses' => intval($cesRow['total_ces'] ?? 0),
                'averages' => [],
                'overall_avg' => 0,
                'remarks' => []
            ];

            if ($cesFeedback['total_responses'] > 0) {
                $cesSum = 0;
                for ($i = 1; $i <= 16; $i++) {
                    $qAvg = round(floatval($cesRow["q$i"] ?? 0), 2);
                    $cesFeedback['averages']["ces_q$i"] = $qAvg;
                    $cesSum += $qAvg;
                }
                $cesFeedback['overall_avg'] = round($cesSum / 16, 2);
                $cesFeedback['domain_averages'] = self::calculateCesDomainAverages($cesFeedback['averages']);

                $remSql = "
                    SELECT 
                        scf.useful_aspects, scf.improvement_topics, scf.suggestions, scf.is_anonymous, scf.submitted_at,
                        CASE WHEN scf.is_anonymous = 1 THEN 'Anonymous' ELSE s.username END AS student_roll
                    FROM student_ces_feedback scf
                    JOIN students s ON scf.student_id = s.id
                    WHERE scf.subject_id = ? AND (scf.useful_aspects != '' OR scf.improvement_topics != '' OR scf.suggestions != '')
                    ORDER BY scf.is_anonymous ASC, s.username ASC, scf.submitted_at DESC
                ";
                $stmtRem = $this->conn->prepare($remSql);
                $stmtRem->bind_param("i", $subject_id);
                $stmtRem->execute();
                $cesFeedback['remarks'] = $stmtRem->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtRem->close();
            }
            $res['ces_feedback'] = $cesFeedback;

            // 7. Fetch Student Feedback on Faculty for this subject
            $facEvalSql = "
                SELECT 
                    COUNT(*) AS total_evaluations,
                    AVG(fac_q1) as q1, AVG(fac_q2) as q2, AVG(fac_q3) as q3, AVG(fac_q4) as q4,
                    AVG(fac_q5) as q5, AVG(fac_q6) as q6, AVG(fac_q7) as q7, AVG(fac_q8) as q8,
                    AVG(fac_q9) as q9, AVG(fac_q10) as q10, AVG(fac_q11) as q11, AVG(fac_q12) as q12,
                    AVG(fac_q13) as q13, AVG(fac_q14) as q14, AVG(fac_q15) as q15, AVG(fac_q16) as q16,
                    AVG(fac_q17) as q17, AVG(fac_q18) as q18, AVG(fac_q19) as q19,
                    AVG((fac_q1+fac_q2+fac_q3+fac_q4+fac_q5+fac_q6+fac_q7+fac_q8+fac_q9+fac_q10+fac_q11+fac_q12+fac_q13+fac_q14+fac_q15+fac_q16+fac_q17+fac_q18+fac_q19)/19.0) AS avg_faculty_score
                FROM student_faculty_feedback
                WHERE subject_id = ?
            ";
            $stmtFE = $this->conn->prepare($facEvalSql);
            $stmtFE->bind_param("i", $subject_id);
            $stmtFE->execute();
            $facEval = $stmtFE->get_result()->fetch_assoc();
            $stmtFE->close();

            $qFacAverages = [];
            for ($i = 1; $i <= 19; $i++) {
                $qFacAverages["fac_q$i"] = round(floatval($facEval["q$i"] ?? 0), 2);
            }

            // Remarks for subject's faculty (Identified students first, Anonymous last)
            $remFacSql = "
                SELECT 
                    sub.subcode, sub.sub_fullname,
                    sff.faculty_strengths, sff.improvement_areas, sff.additional_comments, sff.submitted_at,
                    sff.is_anonymous,
                    CASE WHEN sff.is_anonymous = 1 THEN 'Anonymous' ELSE s.username END AS student_roll
                FROM student_faculty_feedback sff
                JOIN subjects sub ON sff.subject_id = sub.id
                JOIN students s ON sff.student_id = s.id
                WHERE sff.subject_id = ? 
                  AND (COALESCE(sff.faculty_strengths, '') != '' OR COALESCE(sff.improvement_areas, '') != '' OR COALESCE(sff.additional_comments, '') != '')
                ORDER BY sff.is_anonymous ASC, s.username ASC, sff.submitted_at DESC
            ";
            $stmtFacRem = $this->conn->prepare($remFacSql);
            $stmtFacRem->bind_param("i", $subject_id);
            $stmtFacRem->execute();
            $facRemarks = $stmtFacRem->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFacRem->close();

            $res['faculty_evaluations'] = [
                'total_evaluations' => intval($facEval['total_evaluations'] ?? 0),
                'avg_score' => round(floatval($facEval['avg_faculty_score'] ?? 0), 2),
                'averages' => $qFacAverages,
                'domain_averages' => self::calculateFacultyDomainAverages($qFacAverages),
                'remarks' => $facRemarks
            ];

            // Set single primary faculty_id in meta for export generators
            if (!empty($facultyList)) {
                $res['meta']['faculty_id'] = intval($facultyList[0]['faculty_id']);
            }

            // 8. Calculate summary statistics
            $res['summary'] = $this->calculateSummaryStats($coFeedback, $qnFeedback);
            if ($cesFeedback['total_responses'] > 0) {
                $res['summary']['ces_overall_avg'] = $cesFeedback['overall_avg'];
                $res['summary']['ces_total_responses'] = $cesFeedback['total_responses'];
                $res['summary']['ces_domain_averages'] = $cesFeedback['domain_averages'] ?? [];
            }

            // Calculate overall indirect attainment metrics for COs
            $attainmentLevels = array_column($coFeedback, 'attainment_level');
            $targetPcts = array_column($coFeedback, 'target_pct');
            $res['summary']['avg_indirect_level'] = !empty($attainmentLevels) ? round(array_sum($attainmentLevels) / count($attainmentLevels), 2) : 0.0;
            $res['summary']['avg_target_pct'] = !empty($targetPcts) ? round(array_sum($targetPcts) / count($targetPcts), 1) : 0.0;
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get detailed student-wise ratings for a subject (useful for raw CSV download)
     *
     * @param int $subject_id
     * @return array
     */
    public function getSubjectStudentWiseRatings($subject_id, $class_id = null)
    {
        $res = ['cos' => [], 'students' => []];
        try {
            $subject_id = intval($subject_id);
            if ($subject_id <= 0) {
                return $res;
            }

            // 1. Get CO list with numbers and descriptions
            $stmt = $this->conn->prepare("SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id = ? ORDER BY co_number ASC");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $coRes = $stmt->get_result();
            $cos = [];
            while ($c = $coRes->fetch_assoc()) {
                $cos[$c['id']] = [
                    'id' => $c['id'],
                    'co_number' => $c['co_number'],
                    'co_label' => 'CO' . $c['co_number'],
                    'co_description' => $c['co_description']
                ];
            }
            $stmt->close();
            $res['cos'] = $cos;

            // 2. Get Student Responses
            $sql = "
                SELECT s.id AS student_id, s.username AS roll_no, COALESCE(u.name, s.username) AS student_name, 
                       scf.co_id, scf.rating, scf.feedback_date
                FROM student_co_feedback scf
                JOIN students s ON scf.student_id = s.id
                LEFT JOIN users u ON s.username = u.username
                WHERE scf.subject_id = ? " . (!empty($class_id) ? " AND scf.class_id = " . intval($class_id) : "") . "
                ORDER BY s.username ASC, scf.co_id ASC
            ";
            $stmtR = $this->conn->prepare($sql);
            $stmtR->bind_param("i", $subject_id);
            $stmtR->execute();
            $rRes = $stmtR->get_result();

            $studentData = [];
            while ($row = $rRes->fetch_assoc()) {
                $sid = $row['student_id'];
                if (!isset($studentData[$sid])) {
                    $studentData[$sid] = [
                        'student_id' => $sid,
                        'student_roll' => $row['roll_no'],
                        'student_name' => $row['student_name'],
                        'feedback_date' => $row['feedback_date'],
                        'ratings' => array_fill_keys(array_keys($cos), null),
                        'rating_values' => []
                    ];
                }
                $rVal = intval($row['rating']);
                $studentData[$sid]['ratings'][$row['co_id']] = $rVal;
                $studentData[$sid]['rating_values'][] = $rVal;
                if (!empty($row['feedback_date'])) {
                    $studentData[$sid]['feedback_date'] = $row['feedback_date'];
                }
            }
            $stmtR->close();

            // Calculate student average
            foreach ($studentData as $sid => &$sinfo) {
                $vals = $sinfo['rating_values'];
                $sinfo['average'] = !empty($vals) ? round(array_sum($vals) / count($vals), 2) : 0;
                unset($sinfo['rating_values']);
            }
            unset($sinfo);

            $res['students'] = array_values($studentData);
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getSubjectStudentWiseRatings Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get feedback roll-up for an entire class
     *
     * @param int $class_id
     * @return array
     */
    public function getClassFeedback($class_id)
    {
        $myname = $this->classname . " - getClassFeedback - ";
        $res = [
            'status' => 0,
            'meta' => [],
            'total_students' => 0,
            'total_responded' => 0,
            'response_rate' => 0,
            'subjects' => [],
            'co_feedback' => [],
            'qn_feedback' => [],
            'summary' => [
                'overall_avg' => 0,
                'overall_co_avg' => 0,
                'overall_qn_avg' => 0,
                'positive_response_pct' => 0,
                'total_subjects' => 0
            ]
        ];

        try {
            $class_id = intval($class_id);
            if ($class_id <= 0) {
                return $res;
            }

            // 1. Fetch Class and Department metadata
            $metaSql = "
                SELECT 
                    c.id AS class_id,
                    c.classname,
                    c.acad_year,
                    c.yearsem,
                    sp.id AS spec_id,
                    sp.spec_shortname,
                    sp.spec_fullname,
                    d.id AS dept_id,
                    d.dept_fullname
                FROM classes c
                JOIN specialization sp ON c.spec_id = sp.id
                JOIN departments d ON sp.dept_id = d.id
                WHERE c.id = ?
            ";
            $stmt = $this->conn->prepare($metaSql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $meta = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                return $res;
            }
            $res['meta'] = $meta;

            // Total students in class
            $stmtTotal = $this->conn->prepare("SELECT COUNT(*) AS total FROM students WHERE class_id = ?");
            $stmtTotal->bind_param("i", $class_id);
            $stmtTotal->execute();
            $res['total_students'] = intval($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtTotal->close();

            // Total students in class who gave at least one feedback
            $stmtResp = $this->conn->prepare("
                SELECT COUNT(DISTINCT scf.student_id) AS total
                FROM student_co_feedback scf
                JOIN students stu ON scf.student_id = stu.id
                WHERE stu.class_id = ?
            ");
            $stmtResp->bind_param("i", $class_id);
            $stmtResp->execute();
            $res['total_responded'] = intval($stmtResp->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtResp->close();

            if ($res['total_students'] > 0) {
                $res['response_rate'] = round(($res['total_responded'] / $res['total_students']) * 100, 1);
            }

            // 2. Fetch all subjects for this class
            $subSql = "
                SELECT s.id, s.subcode, s.sub_fullname, s.sub_shortname, s.sub_type, s.subject_sno
                FROM subjects s
                WHERE s.class_id = ?
                ORDER BY s.subject_sno + 0 ASC
            ";
            $stmtSubs = $this->conn->prepare($subSql);
            $stmtSubs->bind_param("i", $class_id);
            $stmtSubs->execute();
            $subsResult = $stmtSubs->get_result();
            $subjects = $subsResult->fetch_all(MYSQLI_ASSOC);
            $stmtSubs->close();

            // 3. Fetch CO feedback for all subjects in the class
            $classCoSql = "
                SELECT 
                    s.id AS subject_id,
                    s.subcode,
                    s.sub_fullname,
                    co.co_number,
                    co.co_description,
                    COALESCE(AVG(scf.rating), 0) AS average_rating,
                    COUNT(scf.id) AS total_responses,
                    COALESCE(SUM(CASE WHEN scf.rating = 5 THEN 1 ELSE 0 END), 0) AS count_5,
                    COALESCE(SUM(CASE WHEN scf.rating = 4 THEN 1 ELSE 0 END), 0) AS count_4,
                    COALESCE(SUM(CASE WHEN scf.rating = 3 THEN 1 ELSE 0 END), 0) AS count_3,
                    COALESCE(SUM(CASE WHEN scf.rating = 2 THEN 1 ELSE 0 END), 0) AS count_2,
                    COALESCE(SUM(CASE WHEN scf.rating = 1 THEN 1 ELSE 0 END), 0) AS count_1
                FROM subjects s
                JOIN course_outcomes co ON s.id = co.sub_id
                LEFT JOIN student_co_feedback scf ON co.id = scf.co_id AND scf.subject_id = s.id
                WHERE s.class_id = ?
                GROUP BY s.id, s.subcode, s.sub_fullname, co.id, co.co_number, co.co_description
                ORDER BY s.subject_sno + 0 ASC, co.co_number ASC
            ";
            $stmtClassCO = $this->conn->prepare($classCoSql);
            $stmtClassCO->bind_param("i", $class_id);
            $stmtClassCO->execute();
            $coResult = $stmtClassCO->get_result();
            $allCOs = [];
            while ($row = $coResult->fetch_assoc()) {
                $row['average_rating'] = floatval($row['average_rating']);
                $row['total_responses'] = intval($row['total_responses']);
                $metrics = self::computeCoAttainmentMetrics($row['total_responses'], $row['count_5'], $row['count_4'], $row['count_3']);
                $row = array_merge($row, $metrics);
                $allCOs[] = $row;
            }
            $stmtClassCO->close();
            $res['co_feedback'] = $allCOs;

            // 4. Fetch Questionnaire feedback for all subjects in class
            $classQnSql = "
                SELECT 
                    s.id AS subject_id,
                    s.subcode,
                    s.sub_fullname,
                    sqq.question_number,
                    sqq.question_text,
                    sqq.created_by_role,
                    COALESCE(AVG(sqr.rating), 0) AS average_rating,
                    COUNT(sqr.id) AS total_responses,
                    COALESCE(SUM(CASE WHEN sqr.rating = 5 THEN 1 ELSE 0 END), 0) AS count_5,
                    COALESCE(SUM(CASE WHEN sqr.rating = 4 THEN 1 ELSE 0 END), 0) AS count_4,
                    COALESCE(SUM(CASE WHEN sqr.rating = 3 THEN 1 ELSE 0 END), 0) AS count_3,
                    COALESCE(SUM(CASE WHEN sqr.rating = 2 THEN 1 ELSE 0 END), 0) AS count_2,
                    COALESCE(SUM(CASE WHEN sqr.rating = 1 THEN 1 ELSE 0 END), 0) AS count_1
                FROM subjects s
                JOIN subject_questionnaire_questions sqq ON s.id = sqq.subject_id
                LEFT JOIN student_questionnaire_responses sqr ON sqq.id = sqr.question_id AND sqr.subject_id = s.id
                WHERE s.class_id = ?
                GROUP BY s.id, s.subcode, s.sub_fullname, sqq.id, sqq.question_number, sqq.question_text, sqq.created_by_role
                ORDER BY s.subject_sno + 0 ASC, sqq.question_number ASC
            ";
            $stmtClassQN = $this->conn->prepare($classQnSql);
            $stmtClassQN->bind_param("i", $class_id);
            $stmtClassQN->execute();
            $qnResult = $stmtClassQN->get_result();
            $allQNs = [];
            while ($row = $qnResult->fetch_assoc()) {
                $row['average_rating'] = floatval($row['average_rating']);
                $row['total_responses'] = intval($row['total_responses']);
                $allQNs[] = $row;
            }
            $stmtClassQN->close();
            $res['qn_feedback'] = $allQNs;

            // Subject level aggregations for overview
            $subjectSummaries = [];
            foreach ($subjects as $sub) {
                $sid = $sub['id'];
                $subCOs = array_filter($allCOs, function($item) use ($sid) {
                    return $item['subject_id'] == $sid;
                });
                $subQNs = array_filter($allQNs, function($item) use ($sid) {
                    return $item['subject_id'] == $sid;
                });
                $stats = $this->calculateSummaryStats($subCOs, $subQNs);
                
                // Fetch assigned faculty names
                $facs = $this->getFacultyNamesForSubject($sid);
                $sub['faculty_names'] = !empty($facs) ? implode(", ", $facs) : "N/A";
                
                // Compute indirect attainment metrics for the subject
                $subCoAttainmentLevels = array_column($subCOs, 'attainment_level');
                $subCoTargetPcts = array_column($subCOs, 'target_pct');
                $subAvgLvl = !empty($subCoAttainmentLevels) ? round(array_sum($subCoAttainmentLevels) / count($subCoAttainmentLevels), 2) : 0.0;
                $subAvgPct = !empty($subCoTargetPcts) ? round(array_sum($subCoTargetPcts) / count($subCoTargetPcts), 1) : 0.0;
                
                $stats['avg_indirect_level'] = $subAvgLvl;
                $stats['avg_target_pct'] = $subAvgPct;
                $badgeClass = $subAvgLvl >= 2.5 ? 'success' : ($subAvgLvl >= 1.5 ? 'primary' : ($subAvgLvl >= 0.5 ? 'warning text-dark' : 'secondary'));
                $stats['nba_indirect_attainment'] = [
                    'level' => $subAvgLvl,
                    'pct_meeting_target' => $subAvgPct,
                    'badge_class' => $badgeClass
                ];
                
                $sub['summary'] = $stats;
                $subjectSummaries[] = $sub;
            }
            $res['subjects'] = $subjectSummaries;

            $res['summary'] = $this->calculateSummaryStats($allCOs, $allQNs);
            $res['summary']['total_subjects'] = count($subjects);

            // Compute overall indirect attainment for the class
            $allAttainmentLevels = array_column($allCOs, 'attainment_level');
            $allTargetPcts = array_column($allCOs, 'target_pct');
            $classAvgLvl = !empty($allAttainmentLevels) ? round(array_sum($allAttainmentLevels) / count($allAttainmentLevels), 2) : 0.0;
            $classAvgPct = !empty($allTargetPcts) ? round(array_sum($allTargetPcts) / count($allTargetPcts), 1) : 0.0;
            $res['summary']['avg_indirect_level'] = $classAvgLvl;
            $res['summary']['avg_target_pct'] = $classAvgPct;
            $res['summary']['nba_indirect_attainment'] = $classAvgLvl;
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get feedback for a faculty member across all subjects they teach (optionally filtered by class)
     *
     * @param int $faculty_id
     * @param int|null $class_id
     * @param string|null $acad_year
     * @return array
     */
    public function getFacultyFeedback($faculty_id, $class_id = null, $acad_year = null)
    {
        $myname = $this->classname . " - getFacultyFeedback - ";
        $res = [
            'status' => 0,
            'meta' => [
                'acad_year' => !empty($acad_year) ? $acad_year : 'All Academic Years'
            ],
            'faculty' => [],
            'subjects' => [],
            'total_enrolled' => 0,
            'total_responded' => 0,
            'response_rate' => 0,
            'co_feedback' => [],
            'faculty_evaluations' => [],
            'academic_year_summary' => [],
            'summary' => [
                'overall_avg' => 0,
                'overall_co_avg' => 0,
                'overall_fac_eval_avg' => 0,
                'positive_response_pct' => 0,
                'total_responses' => 0,
                'total_subjects' => 0,
                'total_enrolled' => 0,
                'total_responded' => 0,
                'response_rate' => 0
            ]
        ];

        try {
            $faculty_id = intval($faculty_id);
            if ($faculty_id <= 0) {
                return $res;
            }

            // 1. Faculty Details
            $facSql = "
                SELECT f.id, f.facultyid, f.username, u.name AS faculty_name, u.email, f.designation, f.dept_id, d.dept_fullname
                FROM faculties f
                JOIN users u ON f.username = u.username
                LEFT JOIN departments d ON f.dept_id = d.id
                WHERE f.id = ?
            ";
            $stmt = $this->conn->prepare($facSql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $fac = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fac) {
                return $res;
            }
            $res['faculty'] = $fac;

            // 2. Fetch assigned subjects (filtered by class_id or acad_year if specified)
            $subQuery = "
                SELECT s.id, s.subcode, s.sub_fullname, s.sub_shortname, s.sub_type, c.id AS class_id, c.classname, c.acad_year
                FROM faculty_sub fs
                JOIN subjects s ON fs.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE fs.faculty_id = ?
            ";
            $bindTypes = "i";
            $bindParams = [$faculty_id];

            if (!empty($class_id)) {
                $subQuery .= " AND s.class_id = ?";
                $bindTypes .= "i";
                $bindParams[] = intval($class_id);
            }
            if (!empty($acad_year)) {
                $subQuery .= " AND c.acad_year = ?";
                $bindTypes .= "s";
                $bindParams[] = strval($acad_year);
            }
            $subQuery .= " ORDER BY c.acad_year DESC, s.sub_fullname ASC";

            $stmtSubs = $this->conn->prepare($subQuery);
            $stmtSubs->bind_param($bindTypes, ...$bindParams);
            $stmtSubs->execute();
            $subsResult = $stmtSubs->get_result();
            $subjects = $subsResult->fetch_all(MYSQLI_ASSOC);
            $stmtSubs->close();

            $res['subjects'] = $subjects;
            $res['summary']['total_subjects'] = count($subjects);

            if (empty($subjects)) {
                $res['status'] = 1;
                return $res;
            }

            $subjectIds = array_column($subjects, 'id');
            $subIdList = implode(",", array_map('intval', $subjectIds));

            // Enrollment & Response stats across assigned subjects
            $facEnrollSql = "SELECT COUNT(DISTINCT stu_id) AS total FROM student_sub WHERE sub_id IN ($subIdList)";
            $stmtFErr = $this->conn->query($facEnrollSql);
            $facEnrollRow = $stmtFErr ? $stmtFErr->fetch_assoc() : [];
            $res['total_enrolled'] = intval($facEnrollRow['total'] ?? 0);

            $facRespSql = "SELECT COUNT(DISTINCT student_id) AS total FROM student_co_feedback WHERE subject_id IN ($subIdList)";
            $stmtFResp = $this->conn->query($facRespSql);
            $facRespRow = $stmtFResp ? $stmtFResp->fetch_assoc() : [];
            $res['total_responded'] = intval($facRespRow['total'] ?? 0);

            if ($res['total_enrolled'] > 0) {
                $res['response_rate'] = round(($res['total_responded'] / $res['total_enrolled']) * 100, 1);
            } else {
                $res['response_rate'] = 0;
            }

            // 3. CO feedback for faculty's subjects
            $coSql = "
                SELECT 
                    s.id AS subject_id,
                    s.subcode,
                    s.sub_fullname,
                    c.classname,
                    c.acad_year,
                    co.co_number,
                    co.co_description,
                    COALESCE(AVG(scf.rating), 0) AS average_rating,
                    COUNT(scf.id) AS total_responses,
                    COALESCE(SUM(CASE WHEN scf.rating = 5 THEN 1 ELSE 0 END), 0) AS count_5,
                    COALESCE(SUM(CASE WHEN scf.rating = 4 THEN 1 ELSE 0 END), 0) AS count_4,
                    COALESCE(SUM(CASE WHEN scf.rating = 3 THEN 1 ELSE 0 END), 0) AS count_3,
                    COALESCE(SUM(CASE WHEN scf.rating = 2 THEN 1 ELSE 0 END), 0) AS count_2,
                    COALESCE(SUM(CASE WHEN scf.rating = 1 THEN 1 ELSE 0 END), 0) AS count_1
                FROM subjects s
                JOIN classes c ON s.class_id = c.id
                JOIN course_outcomes co ON s.id = co.sub_id
                LEFT JOIN student_co_feedback scf ON co.id = scf.co_id AND scf.subject_id = s.id
                WHERE s.id IN ($subIdList)
                GROUP BY s.id, s.subcode, s.sub_fullname, c.classname, c.acad_year, co.id, co.co_number, co.co_description
                ORDER BY c.acad_year DESC, c.classname ASC, s.subcode ASC, co.co_number ASC
            ";
            $coResult = $this->conn->query($coSql);
            $allCOs = [];
            while ($row = $coResult->fetch_assoc()) {
                $row['average_rating'] = floatval($row['average_rating']);
                $row['total_responses'] = intval($row['total_responses']);
                $metrics = self::computeCoAttainmentMetrics($row['total_responses'], $row['count_5'], $row['count_4'], $row['count_3']);
                $row = array_merge($row, $metrics);
                $allCOs[] = $row;
            }
            $res['co_feedback'] = $allCOs;

            // 4. Fetch direct student feedback on faculty if available (19 parameters & remarks)
            $facEvalSql = "
                SELECT 
                    COUNT(*) AS total_evaluations,
                    AVG(fac_q1) as q1, AVG(fac_q2) as q2, AVG(fac_q3) as q3, AVG(fac_q4) as q4, AVG(fac_q5) as q5,
                    AVG(fac_q6) as q6, AVG(fac_q7) as q7, AVG(fac_q8) as q8, AVG(fac_q9) as q9, AVG(fac_q10) as q10,
                    AVG(fac_q11) as q11, AVG(fac_q12) as q12, AVG(fac_q13) as q13, AVG(fac_q14) as q14, AVG(fac_q15) as q15,
                    AVG(fac_q16) as q16, AVG(fac_q17) as q17, AVG(fac_q18) as q18, AVG(fac_q19) as q19,
                    AVG((fac_q1+fac_q2+fac_q3+fac_q4+fac_q5+fac_q6+fac_q7+fac_q8+fac_q9+fac_q10+fac_q11+fac_q12+fac_q13+fac_q14+fac_q15+fac_q16+fac_q17+fac_q18+fac_q19)/19.0) AS avg_faculty_score
                FROM student_faculty_feedback
                WHERE faculty_id = ? AND subject_id IN ($subIdList)
            ";
            $stmtFE = $this->conn->prepare($facEvalSql);
            $stmtFE->bind_param("i", $faculty_id);
            $stmtFE->execute();
            $facEval = $stmtFE->get_result()->fetch_assoc();
            $stmtFE->close();

            $qAverages = [];
            for ($i = 1; $i <= 19; $i++) {
                $qAverages["fac_q$i"] = round(floatval($facEval["q$i"] ?? 0), 2);
            }

            // Fetch qualitative comments for faculty
            $facRemSql = "
                SELECT 
                    s.subcode, s.sub_fullname, c.acad_year,
                    sff.faculty_strengths, sff.improvement_areas, sff.additional_comments, sff.submitted_at,
                    sff.is_anonymous,
                    CASE WHEN sff.is_anonymous = 1 THEN 'Anonymous' ELSE stu.username END AS student_roll
                FROM student_faculty_feedback sff
                JOIN subjects s ON sff.subject_id = s.id
                JOIN classes c ON s.class_id = c.id
                JOIN students stu ON sff.student_id = stu.id
                WHERE sff.faculty_id = ? AND sff.subject_id IN ($subIdList)
                  AND (COALESCE(sff.faculty_strengths, '') != '' OR COALESCE(sff.improvement_areas, '') != '' OR COALESCE(sff.additional_comments, '') != '')
                ORDER BY sff.is_anonymous ASC, stu.username ASC, sff.submitted_at DESC
            ";
            $stmtFacRem = $this->conn->prepare($facRemSql);
            $stmtFacRem->bind_param("i", $faculty_id);
            $stmtFacRem->execute();
            $facRemarks = $stmtFacRem->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFacRem->close();

            $res['faculty_evaluations'] = [
                'total_evaluations' => intval($facEval['total_evaluations'] ?? 0),
                'avg_score' => round(floatval($facEval['avg_faculty_score'] ?? 0), 2),
                'averages' => $qAverages,
                'domain_averages' => self::calculateFacultyDomainAverages($qAverages),
                'remarks' => $facRemarks
            ];

            // Attach per-subject summary stats using calculateSummaryStats
            $enrichedSubjects = [];
            foreach ($subjects as $sub) {
                $sid = $sub['id'];
                $subCOs = array_filter($allCOs, fn($c) => ($c['subject_id'] ?? 0) == $sid);
                $sub['summary'] = $this->calculateSummaryStats($subCOs);
                $enrichedSubjects[] = $sub;
            }
            $subjects = $enrichedSubjects;
            $res['subjects'] = $subjects;

            // 5. Compute Academic Year-Wise Performance Summary
            $facYearEvalSql = "
                SELECT 
                    c.acad_year,
                    COUNT(sff.id) AS total_evaluations,
                    COALESCE(AVG((fac_q1+fac_q2+fac_q3+fac_q4+fac_q5+fac_q6+fac_q7+fac_q8+fac_q9+fac_q10+fac_q11+fac_q12+fac_q13+fac_q14+fac_q15+fac_q16+fac_q17+fac_q18+fac_q19)/19.0), 0) AS avg_faculty_score
                FROM student_faculty_feedback sff
                JOIN subjects s ON sff.subject_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE sff.faculty_id = ? AND sff.subject_id IN ($subIdList)
                GROUP BY c.acad_year
            ";
            $stmtFEY = $this->conn->prepare($facYearEvalSql);
            $stmtFEY->bind_param("i", $faculty_id);
            $stmtFEY->execute();
            $feyRows = $stmtFEY->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtFEY->close();

            $feyMap = [];
            foreach ($feyRows as $r) {
                $feyMap[$r['acad_year']] = [
                    'total_evaluations' => intval($r['total_evaluations'] ?? 0),
                    'avg_faculty_score' => round(floatval($r['avg_faculty_score'] ?? 0), 2)
                ];
            }

            $distinctYears = array_values(array_unique(array_filter(array_column($subjects, 'acad_year'))));
            rsort($distinctYears);

            $aySummary = [];
            foreach ($distinctYears as $yr) {
                $yrSubs = array_filter($subjects, fn($s) => ($s['acad_year'] ?? '') === $yr);
                $yrSubIds = array_column($yrSubs, 'id');
                $yrCOs = array_filter($allCOs, fn($co) => in_array($co['subject_id'] ?? 0, $yrSubIds));
                
                $yrStats = $this->calculateSummaryStats($yrCOs);
                $yrCOAvg = floatval($yrStats['overall_co_avg'] ?? 0);
                $yrCOResp = count($yrCOs) > 0 ? max(array_column($yrCOs, 'total_responses')) : 0;
                $yrFacScore = $feyMap[$yr]['avg_faculty_score'] ?? 0;
                $yrTotalEvals = $feyMap[$yr]['total_evaluations'] ?? 0;

                if ($yrCOAvg > 0 && $yrFacScore > 0) {
                    $yrOverall = round(($yrCOAvg + $yrFacScore) / 2, 2);
                } else {
                    $yrOverall = $yrCOAvg > 0 ? $yrCOAvg : $yrFacScore;
                }

                $aySummary[$yr] = [
                    'acad_year' => $yr,
                    'total_subjects' => count($yrSubs),
                    'total_co_responses' => $yrCOResp,
                    'co_average' => $yrCOAvg,
                    'faculty_eval_score' => $yrFacScore,
                    'total_evaluations' => $yrTotalEvals,
                    'overall_rating' => $yrOverall
                ];
            }
            $res['academic_year_summary'] = $aySummary;

            $res['summary'] = $this->calculateSummaryStats($allCOs);
            $res['summary']['total_subjects'] = count($subjects);
            $res['summary']['overall_fac_eval_avg'] = $res['faculty_evaluations']['avg_score'];
            $res['summary']['total_enrolled'] = $res['total_enrolled'];
            $res['summary']['total_responded'] = $res['total_responded'];
            $res['summary']['response_rate'] = $res['response_rate'];
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get department-level feedback aggregation (or delegates if deeper granularity specified)
     *
     * @param int $department_id
     * @param int|null $class_id
     * @param int|null $faculty_id
     * @param int|null $subject_id
     * @return array
     */
    public function getDepartmentFeedback($department_id, $class_id = null, $faculty_id = null, $subject_id = null, $acad_year = null, $prog_id = null)
    {
        $myname = $this->classname . " - getDepartmentFeedback - ";

        // If subject is selected, delegate to subject feedback
        if (!empty($subject_id) && $subject_id !== 'all') {
            return $this->getSubjectFeedback($subject_id, $class_id);
        }

        // If faculty is selected, delegate to faculty feedback
        if (!empty($faculty_id) && $faculty_id !== 'all') {
            return $this->getFacultyFeedback($faculty_id, $class_id, $acad_year);
        }

        // If class is selected, delegate to class feedback
        if (!empty($class_id) && $class_id !== 'all') {
            return $this->getClassFeedback($class_id);
        }

        // Department-wide roll-up
        $res = [
            'status' => 0,
            'department' => [],
            'classes' => [],
            'summary' => [
                'total_classes' => 0,
                'total_subjects' => 0,
                'total_students' => 0,
                'total_responses' => 0,
                'overall_avg' => 0,
                'positive_response_pct' => 0
            ]
        ];

        try {
            $department_id = intval($department_id);
            if ($department_id <= 0) {
                return $res;
            }

            // Department metadata
            $stmtDept = $this->conn->prepare("SELECT id, dept_shortname, dept_fullname FROM departments WHERE id = ?");
            $stmtDept->bind_param("i", $department_id);
            $stmtDept->execute();
            $res['department'] = $stmtDept->get_result()->fetch_assoc();
            $stmtDept->close();

            if (!$res['department']) {
                return $res;
            }

            // Get active classes in department (filtered if acad_year or prog_id provided)
            if (!empty($acad_year) || !empty($prog_id)) {
                $classes = $this->getClassesFiltered($department_id, $prog_id, null, $acad_year);
            } else {
                $classes = $this->getClassesByDepartment($department_id);
            }
            $res['classes'] = [];

            $totalClassStudents = 0;
            $totalResponsesDept = 0;
            $totalRatingsDept = 0;
            $weightedRatingSum = 0;
            $totalSubjectsDept = 0;

            foreach ($classes as $cls) {
                $cid = $cls['id'];
                $classFeedback = $this->getClassFeedback($cid);
                if ($classFeedback['status'] == 1) {
                    $clsRatings = intval($classFeedback['summary']['total_ratings_count'] ?? 0);
                    if ($clsRatings <= 0) {
                        $clsRatings = intval($classFeedback['total_responded'] ?? 0);
                    }
                    $cls['stats'] = [
                        'total_students' => $classFeedback['total_students'],
                        'total_responded' => $classFeedback['total_responded'],
                        'response_rate' => $classFeedback['response_rate'],
                        'total_subjects' => $classFeedback['summary']['total_subjects'],
                        'overall_avg' => $classFeedback['summary']['overall_avg'],
                        'positive_response_pct' => $classFeedback['summary']['positive_response_pct'],
                        'total_ratings_count' => $clsRatings
                    ];
                    $totalClassStudents += $classFeedback['total_students'];
                    $totalResponsesDept += $classFeedback['total_responded'];
                    $totalSubjectsDept += $classFeedback['summary']['total_subjects'];
                    $totalRatingsDept += $clsRatings;
                    $weightedRatingSum += ($classFeedback['summary']['overall_avg'] * $clsRatings);
                }
                $res['classes'][] = $cls;
            }

            $res['summary']['total_classes'] = count($classes);
            $res['summary']['total_subjects'] = $totalSubjectsDept;
            $res['summary']['total_students'] = $totalClassStudents;
            $res['summary']['total_responses'] = $totalResponsesDept;
            $res['summary']['total_ratings_count'] = $totalRatingsDept;
            $res['summary']['overall_avg'] = ($totalRatingsDept > 0) ? round($weightedRatingSum / $totalRatingsDept, 2) : (($totalResponsesDept > 0) ? round($weightedRatingSum / $totalResponsesDept, 2) : 0);
            $res['response_rate'] = $totalClassStudents > 0 ? round(($totalResponsesDept / $totalClassStudents) * 100, 1) : 0;
            $res['summary']['response_rate'] = $res['response_rate'];
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get distinct faculty members assigned to subjects within a class
     *
     * @param int $class_id
     * @return array
     */
    public function getFacultyByClassID($class_id)
    {
        $res = [];
        try {
            $sql = "
                SELECT DISTINCT f.id, f.facultyid, f.username, u.name AS faculty_name, u.email, f.designation
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN subjects s ON fs.sub_id = s.id
                JOIN users u ON f.username = u.username
                WHERE s.class_id = ?
                ORDER BY u.name ASC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getFacultyByClassID Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get all classes for a department
     *
     * @param int $department_id
     * @return array
     */
    public function getClassesByDepartment($department_id)
    {
        $res = [];
        try {
            $sql = "
                SELECT c.id, c.classname, c.acad_year, c.yearsem, sp.spec_shortname, sp.spec_fullname, sp.spec_code
                FROM classes c
                JOIN specialization sp ON c.spec_id = sp.id
                WHERE sp.dept_id = ? AND c.status = 1
                ORDER BY c.acad_year DESC, c.yearsem ASC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getClassesByDepartment Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get all faculty in a department
     *
     * @param int $department_id
     * @return array
     */
    public function getFacultyByDepartment($department_id)
    {
        $res = [];
        try {
            $sql = "
                SELECT f.id, f.facultyid, f.username, u.name AS faculty_name, u.email, f.designation, u.status, COALESCE(f.sort_order, 0) AS sort_order
                FROM faculties f
                JOIN users u ON f.username = u.username
                WHERE f.dept_id = ? AND u.status = 1
                ORDER BY 
                    CASE WHEN f.sort_order > 0 THEN f.sort_order ELSE 99999 END ASC,
                    FIELD(f.designation, 'Professor', 'Assoc. Professor', 'Asst. Professor', 'Asst. Professor (Adhoc)') ASC,
                    u.name ASC
            ";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                $sql = "
                    SELECT f.id, f.facultyid, f.username, u.name AS faculty_name, u.email, f.designation, u.status, 0 AS sort_order
                    FROM faculties f
                    JOIN users u ON f.username = u.username
                    WHERE f.dept_id = ? AND u.status = 1
                    ORDER BY 
                        FIELD(f.designation, 'Professor', 'Assoc. Professor', 'Asst. Professor', 'Asst. Professor (Adhoc)') ASC,
                        u.name ASC
                ";
                $stmt = $this->conn->prepare($sql);
            }
            if ($stmt) {
                $stmt->bind_param("i", $department_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getFacultyByDepartment Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get all specializations for a department
     *
     * @param int $department_id
     * @return array
     */
    public function getSpecializationsByDepartment($department_id)
    {
        $res = [];
        try {
            $sql = "SELECT id, spec_shortname, spec_fullname, spec_code FROM specialization WHERE dept_id = ? AND status = 1 ORDER BY spec_code ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getSpecializationsByDepartment Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get subjects taught by a faculty, optionally filtered by class
     *
     * @param int $faculty_id
     * @param int|null $class_id
     * @return array
     */
    public function getSubjectsByFacultyAndClass($faculty_id, $class_id = null)
    {
        $res = [];
        try {
            $sql = "
                SELECT s.id, s.subcode, s.sub_fullname, s.sub_type, c.id AS class_id, c.classname, c.acad_year
                FROM faculty_sub fs
                JOIN subjects s ON fs.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE fs.faculty_id = ? " . (!empty($class_id) ? " AND s.class_id = " . intval($class_id) : "") . "
                ORDER BY s.sub_fullname ASC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getSubjectsByFacultyAndClass Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Helper to get list of faculty names mapped to a subject
     *
     * @param int $subject_id
     * @return array
     */
    public function getFacultyNamesForSubject($subject_id)
    {
        $names = [];
        try {
            $sql = "
                SELECT u.name
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN users u ON f.username = u.username
                WHERE fs.sub_id = ?
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $names[] = $row['name'];
            }
            $stmt->close();
        } catch (Exception $e) {
            // Ignore
        }
        return $names;
    }

    /**
     * Compute summary statistics from CO feedback and Questionnaire feedback lists
     *
     * @param array $coFeedback
     * @param array $qnFeedback
     * @return array
     */
    public function calculateSummaryStats($coFeedback = [], $qnFeedback = [])
    {
        $totalResponses = 0;
        $coSum = 0;
        $coCount = 0;
        $qnSum = 0;
        $qnCount = 0;
        $positiveCount = 0; // count of ratings 3, 4, 5
        $totalRatingInstances = 0;

        foreach ($coFeedback as $co) {
            $avg = floatval($co['average_rating'] ?? 0);
            $responses = intval($co['total_responses'] ?? 0);
            if ($responses > 0) {
                $coSum += ($avg * $responses);
                $coCount += $responses;
            }
            $c5 = intval($co['count_5'] ?? 0);
            $c4 = intval($co['count_4'] ?? 0);
            $c3 = intval($co['count_3'] ?? 0);
            $c2 = intval($co['count_2'] ?? 0);
            $c1 = intval($co['count_1'] ?? 0);
            $positiveCount += ($c5 + $c4 + $c3);
            $totalRatingInstances += ($c5 + $c4 + $c3 + $c2 + $c1);
            if ($responses > $totalResponses) {
                $totalResponses = $responses;
            }
        }

        foreach ($qnFeedback as $qn) {
            $avg = floatval($qn['average_rating'] ?? 0);
            $responses = intval($qn['total_responses'] ?? 0);
            if ($responses > 0) {
                $qnSum += ($avg * $responses);
                $qnCount += $responses;
            }
            $c5 = intval($qn['count_5'] ?? 0);
            $c4 = intval($qn['count_4'] ?? 0);
            $c3 = intval($qn['count_3'] ?? 0);
            $c2 = intval($qn['count_2'] ?? 0);
            $c1 = intval($qn['count_1'] ?? 0);
            $positiveCount += ($c5 + $c4 + $c3);
            $totalRatingInstances += ($c5 + $c4 + $c3 + $c2 + $c1);
        }

        $overallCoAvg = ($coCount > 0) ? round($coSum / $coCount, 2) : 0;
        $overallQnAvg = ($qnCount > 0) ? round($qnSum / $qnCount, 2) : 0;

        $grandSum = $coSum + $qnSum;
        $grandCount = $coCount + $qnCount;
        $overallAvg = ($grandCount > 0) ? round($grandSum / $grandCount, 2) : 0;
        $positivePct = ($totalRatingInstances > 0) ? round(($positiveCount / $totalRatingInstances) * 100, 1) : 0;

        return [
            'overall_co_avg' => $overallCoAvg,
            'overall_qn_avg' => $overallQnAvg,
            'overall_avg' => $overallAvg,
            'positive_response_pct' => $positivePct,
            'total_ratings_count' => $totalRatingInstances
        ];
    }

    /**
     * Compute Indirect Attainment metrics for a CO rating distribution
     *
     * @param int $totalResponses
     * @param int $count5
     * @param int $count4
     * @param int $count3
     * @return array
     */
    public static function computeCoAttainmentMetrics($totalResponses, $count5, $count4, $count3)
    {
        $targetCount = intval($count5) + intval($count4) + intval($count3);
        $total = intval($totalResponses);
        $targetPct = ($total > 0) ? round(($targetCount / $total) * 100, 1) : 0.0;

        if ($targetPct >= 70.0) {
            $level = 3;
            $badgeClass = 'bg-success';
            $trafficLight = 'green';
            $label = 'High (Level 3)';
        } elseif ($targetPct >= 60.0) {
            $level = 2;
            $badgeClass = 'bg-primary';
            $trafficLight = 'yellow';
            $label = 'Medium (Level 2)';
        } elseif ($targetPct >= 50.0) {
            $level = 1;
            $badgeClass = 'bg-warning text-dark';
            $trafficLight = 'orange';
            $label = 'Low (Level 1)';
        } else {
            $level = 0;
            $badgeClass = 'bg-danger';
            $trafficLight = 'red';
            $label = 'Not Attained (Level 0)';
        }

        return [
            'target_count' => $targetCount,
            'target_pct' => $targetPct,
            'attainment_level' => $level,
            'badge_class' => $badgeClass,
            'traffic_light' => $trafficLight,
            'attainment_label' => $label
        ];
    }

    /**
     * Calculate 5-domain averages for Course End Survey
     */
    public static function calculateCesDomainAverages($averages = [])
    {
        $domains = [
            'Course Content & Relevance' => ['ces_q1', 'ces_q2', 'ces_q3', 'ces_q4'],
            'Teaching-Learning Process' => ['ces_q5', 'ces_q6', 'ces_q7'],
            'Learning Resources' => ['ces_q8', 'ces_q9', 'ces_q10'],
            'Assessment & Evaluation' => ['ces_q11', 'ces_q12', 'ces_q13'],
            'Overall Satisfaction' => ['ces_q14', 'ces_q15', 'ces_q16']
        ];
        $result = [];
        foreach ($domains as $domainName => $qKeys) {
            $sum = 0;
            $count = 0;
            foreach ($qKeys as $key) {
                if (isset($averages[$key])) {
                    $sum += floatval($averages[$key]);
                    $count++;
                }
            }
            $result[$domainName] = ($count > 0) ? round($sum / $count, 2) : 0.0;
        }
        return $result;
    }

    /**
     * Calculate 5-domain averages for Faculty Evaluation
     */
    public static function calculateFacultyDomainAverages($averages = [])
    {
        $domains = [
            'Course Delivery & CO-PO' => ['fac_q1', 'fac_q2', 'fac_q3', 'fac_q4', 'fac_q5'],
            'Teaching & ICT' => ['fac_q6', 'fac_q7', 'fac_q8'],
            'Assessment Practices' => ['fac_q9', 'fac_q10', 'fac_q11'],
            'Classroom Management' => ['fac_q12', 'fac_q13', 'fac_q14', 'fac_q15'],
            'Outcome Attainment' => ['fac_q16', 'fac_q17', 'fac_q18', 'fac_q19']
        ];
        $result = [];
        foreach ($domains as $domainName => $qKeys) {
            $sum = 0;
            $count = 0;
            foreach ($qKeys as $key) {
                if (isset($averages[$key])) {
                    $sum += floatval($averages[$key]);
                    $count++;
                }
            }
            $result[$domainName] = ($count > 0) ? round($sum / $count, 2) : 0.0;
        }
        return $result;
    }

    /**
     * Get distinct academic years for feedback filtering
     */
    public function getAllAcademicYears()
    {
        $res = [];
        try {
            $stmt = $this->conn->prepare("SELECT DISTINCT acad_year FROM academic_years WHERE status = 1 ORDER BY acad_year DESC");
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $res = array_column($rows, 'acad_year');
            if (empty($res)) {
                $stmt2 = $this->conn->prepare("SELECT DISTINCT acad_year FROM classes WHERE acad_year != '' ORDER BY acad_year DESC");
                $stmt2->execute();
                $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt2->close();
                $res = array_column($rows2, 'acad_year');
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getAllAcademicYears Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get all degree programs (B.Tech, M.Tech, etc.)
     */
    public function getAllPrograms()
    {
        $res = [];
        try {
            $stmt = $this->conn->prepare("SELECT id, program_code, prog_shortname, prog_fullname FROM programs ORDER BY id ASC");
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getAllPrograms Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get specializations filtered by department and optional program
     */
    public function getSpecializationsByDepartmentAndProgram($department_id, $prog_id = null)
    {
        $res = [];
        try {
            if (!empty($prog_id)) {
                $sql = "SELECT id, spec_shortname, spec_fullname, spec_code, prog_id FROM specialization WHERE dept_id = ? AND prog_id = ? AND status = 1 ORDER BY spec_code ASC";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ii", $department_id, $prog_id);
            } else {
                $sql = "SELECT id, spec_shortname, spec_fullname, spec_code, prog_id FROM specialization WHERE dept_id = ? AND status = 1 ORDER BY spec_code ASC";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $department_id);
            }
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getSpecializationsByDepartmentAndProgram Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get classes filtered by department, program, specialization, and academic year
     */
    public function getClassesFiltered($department_id = null, $prog_id = null, $spec_id = null, $acad_year = null)
    {
        $res = [];
        try {
            $where = ["c.status = 1"];
            $params = [];
            $types = "";

            if (!empty($department_id)) {
                $where[] = "sp.dept_id = ?";
                $params[] = intval($department_id);
                $types .= "i";
            }
            if (!empty($prog_id)) {
                $where[] = "sp.prog_id = ?";
                $params[] = intval($prog_id);
                $types .= "i";
            }
            if (!empty($spec_id)) {
                $where[] = "c.spec_id = ?";
                $params[] = intval($spec_id);
                $types .= "i";
            }
            if (!empty($acad_year)) {
                $where[] = "c.acad_year = ?";
                $params[] = $acad_year;
                $types .= "s";
            }

            $whereClause = implode(" AND ", $where);
            $sql = "
                SELECT c.id, c.classname, c.acad_year, c.yearsem, sp.id AS spec_id, sp.spec_shortname, sp.spec_fullname, sp.prog_id
                FROM classes c
                JOIN specialization sp ON c.spec_id = sp.id
                WHERE $whereClause
                ORDER BY c.acad_year DESC, c.classname ASC
            ";
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getClassesFiltered Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get distinct academic years for a faculty's assigned subjects
     */
    public function getFacultyAssignedYears($faculty_id)
    {
        $res = [];
        try {
            $sql = "
                SELECT DISTINCT c.acad_year
                FROM faculty_sub fs
                JOIN subjects s ON fs.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE fs.faculty_id = ? AND c.acad_year != ''
                ORDER BY c.acad_year DESC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $res = array_column($rows, 'acad_year');
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getFacultyAssignedYears Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Standard 16 Course End Survey (CES) questions grouped by domain
     */
    public static function getCesQuestions()
    {
        return [
            'A. Course Content, Design & Relevance' => [
                'ces_q1' => '1. Course content was well organized and aligned with the stated Course Outcomes (COs)',
                'ces_q2' => '2. Course content was relevant and adequate to meet the Program Outcomes (POs) / Program Specific Outcomes (PSOs)',
                'ces_q3' => '3. Level of difficulty of the course was appropriate for the semester / year',
                'ces_q4' => '4. Balance between theoretical concepts and practical / laboratory component'
            ],
            'B. Teaching-Learning Process' => [
                'ces_q5' => '5. Teaching methods used (lectures, demonstrations, ICT tools, activities) supported effective learning',
                'ces_q6' => '6. Adequate opportunities were provided for self-learning, projects, or applications beyond the classroom',
                'ces_q7' => '7. Pace and sequencing of topics facilitated understanding and attainment of COs'
            ],
            'C. Learning Resources' => [
                'ces_q8'  => '8. Prescribed textbooks / reference materials / e-resources were adequate and useful',
                'ces_q9'  => '9. Laboratory / workshop / software tools and infrastructure supported the intended learning outcomes',
                'ces_q10' => '10. Library, LMS and other institutional resources supported the course effectively'
            ],
            'D. Assessment & Evaluation' => [
                'ces_q11' => '11. Internal assessments (tests, assignments, quizzes) were fair and appropriately mapped to COs',
                'ces_q12' => '12. Assessment methods evaluated the intended knowledge, skills and application level (Bloom\'s Taxonomy)',
                'ces_q13' => '13. Timely feedback was given on performance in assessments'
            ],
            'E. Overall Outcome & Satisfaction' => [
                'ces_q14' => '14. The course enhanced problem-solving, analytical or design skills relevant to the discipline',
                'ces_q15' => '15. Overall, the course objectives and Course Outcomes (COs) were achieved',
                'ces_q16' => '16. Overall satisfaction with the course'
            ]
        ];
    }

    /**
     * Standard 19 Student Feedback on Faculty questions grouped by domain
     */
    public static function getFacultyQuestions()
    {
        return [
            'A. Course Delivery, Content Coverage & CO-PO Linkage' => [
                'fac_q1' => '1. Faculty clearly communicated the Course Outcomes (COs) and their relevance at the start of the course',
                'fac_q2' => '2. Syllabus was covered systematically as per the course plan / lesson plan',
                'fac_q3' => '3. Depth of subject knowledge and clarity in explaining fundamental concepts',
                'fac_q4' => '4. Use of real-life examples, case studies or applications linking theory to Program Outcomes (POs)',
                'fac_q5' => '5. Pace of teaching was appropriate and matched student comprehension level'
            ],
            'B. Teaching Methodology & Use of ICT' => [
                'fac_q6' => '6. Effective use of ICT tools / PPTs / models / simulations / demonstrations to enhance learning',
                'fac_q7' => '7. Encourages active learning through discussions, quizzes, assignments and problem-solving',
                'fac_q8' => '8. Design of learning activities addressing higher-order thinking (analysis, evaluation, creation) as per Bloom\'s Taxonomy'
            ],
            'C. Assessment & Feedback Practices' => [
                'fac_q9'  => '9. Fairness, transparency and objectivity in internal assessment / evaluation',
                'fac_q10' => '10. Timely feedback provided on assignments, tests and tutorials',
                'fac_q11' => '11. Assessment questions are appropriately mapped to Course Outcomes (COs) and varied difficulty levels'
            ],
            'D. Communication & Classroom Management' => [
                'fac_q12' => '12. Communication skills - clarity, audibility and language proficiency',
                'fac_q13' => '13. Punctuality and regularity in conducting classes',
                'fac_q14' => '14. Approachability and willingness to clear doubts inside / outside the classroom',
                'fac_q15' => '15. Maintains discipline and a conducive learning environment'
            ],
            'E. Outcome Attainment & Overall Rating' => [
                'fac_q16' => '16. Confidence gained by students in achieving the stated Course Outcomes (COs)',
                'fac_q17' => '17. Contribution of the course towards attainment of Program Outcomes (POs) / Program Specific Outcomes (PSOs)',
                'fac_q18' => '18. Overall satisfaction with the course delivery',
                'fac_q19' => '19. Overall rating of the faculty member'
            ]
        ];
    }
}
?>

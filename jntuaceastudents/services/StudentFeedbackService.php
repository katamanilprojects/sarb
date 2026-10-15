<?php
require_once(__DIR__ . "/BaseService.php");

/**
 * StudentFeedbackService - Handles all student feedback operations:
 * Course Outcomes (CO) feedback, Questionnaire responses, Course End Survey (CES), and Faculty feedback.
 */
class StudentFeedbackService extends BaseService {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Get subjects with feedback submission status flag for a student
     *
     * @param int $student_id
     * @return array
     */
    public function getFeedbackSubjectsByStudentId($student_id) {
        $query = "
            SELECT 
                s.`id`, 
                s.`subject_sno`, 
                s.`subcode`, 
                s.`sub_shortname`, 
                s.`sub_fullname`, 
                s.`sub_type`,
                CASE 
                    WHEN (
                        (EXISTS (SELECT 1 FROM course_outcomes co WHERE co.sub_id = s.id) OR EXISTS (SELECT 1 FROM subject_questionnaire_questions sqq WHERE sqq.subject_id = s.id))
                        AND (NOT EXISTS (SELECT 1 FROM course_outcomes co WHERE co.sub_id = s.id) OR (
                            (SELECT COUNT(DISTINCT co_id) FROM student_co_feedback scf WHERE scf.student_id = ? AND scf.subject_id = s.id) >= 
                            (SELECT COUNT(*) FROM course_outcomes co WHERE co.sub_id = s.id)
                        ))
                        AND (NOT EXISTS (SELECT 1 FROM subject_questionnaire_questions sqq WHERE sqq.subject_id = s.id) OR (
                            (SELECT COUNT(DISTINCT question_id) FROM student_questionnaire_responses sqr WHERE sqr.student_id = ? AND sqr.subject_id = s.id) >= 
                            (SELECT COUNT(*) FROM subject_questionnaire_questions sqq WHERE sqq.subject_id = s.id)
                        ))
                    ) THEN 1 
                    ELSE 0 
                END as feedback_submitted
            FROM subjects s
            WHERE s.id IN (SELECT sub_id FROM `student_sub` WHERE stu_id=?)
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $student_id, $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }

        return $subjects;
    }

    /**
     * Fetch Course Outcomes for a subject
     *
     * @param int $sub_id
     * @return array
     */
    public function getCOsBySubjectId($sub_id) {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getCOsBySubjectId - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `co_number`, `co_description` FROM `course_outcomes` WHERE `sub_id` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare getCOsBySubjectId statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Check if student has submitted Course Outcome feedback for a subject
     *
     * @param int $studentId
     * @param int $subjectId
     * @param int|null $classId
     * @return array
     */
    public function hasStudentGivenCOFeedback($studentId, $subjectId, $classId = null) {
        $res = ['status' => 0, 'exists' => false];
        $myname = $this->classname . " - hasStudentGivenCOFeedback - ";

        try {
            $stmt_count = $this->conn->prepare("SELECT COUNT(*) FROM course_outcomes WHERE sub_id = ?");
            $stmt_count->bind_param("i", $subjectId);
            $stmt_count->execute();
            $stmt_count->bind_result($totalCOs);
            $stmt_count->fetch();
            $stmt_count->close();

            if ($totalCOs == 0) {
                $res['status'] = 1;
                $res['exists'] = true;
                return $res;
            }

            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT co_id) FROM student_co_feedback WHERE student_id = ? AND subject_id = ?");
            $stmt->bind_param("ii", $studentId, $subjectId);
            $stmt->execute();
            $stmt->bind_result($count);
            if ($stmt->fetch()) {
                $res['status'] = 1;
                $res['exists'] = ($count > 0 && $count == $totalCOs);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Get questionnaire questions for a subject
     *
     * @param int $subjectId
     * @return array
     */
    public function getSubjectQuestionnaireQuestions($subjectId) {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getSubjectQuestionnaireQuestions - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `question_number`, `question_text`, `created_by_role` FROM `subject_questionnaire_questions` WHERE `subject_id` = ? ORDER BY `question_number`");
            if (!$stmt) {
                throw new Exception("Failed to prepare getSubjectQuestionnaireQuestions statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $subjectId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Save Course Outcome feedback and Questionnaire responses atomically
     *
     * @param int $studentId
     * @param int $subjectId
     * @param int $classId
     * @param array $coFeedback
     * @param array $questionnaireResponses
     * @return array
     */
    public function saveCourseOutcomeFeedback($studentId, $subjectId, $classId, $coFeedback, $questionnaireResponses) {
        $res = ['status' => 0];
        $myname = $this->classname . " - saveCourseOutcomeFeedback - ";

        try {
            // Check if student has already given CO feedback
            $check_co_result = $this->hasStudentGivenCOFeedback($studentId, $subjectId, $classId);
            if ($check_co_result['status'] == 1 && $check_co_result['exists']) {
                $res['status'] = 0;
                $res['err'] = "You have already provided CO feedback for this course.";
                return $res;
            }

            // Check if student has already given questionnaire feedback for this subject
            $check_qn_result = $this->hasStudentSubmittedQuestionnaireResponses($studentId, $subjectId);
            if ($check_qn_result['status'] == 1 && $check_qn_result['exists']) {
                $res['status'] = 0;
                $res['err'] = "You have already provided questionnaire feedback for this course.";
                return $res;
            }

            // Validate that all Course Outcomes are answered if COs exist
            $co_result = $this->getCOsBySubjectId($subjectId);
            $totalCOs = ($co_result['status'] == 1) ? count($co_result['data']) : 0;
            if ($totalCOs > 0 && count($coFeedback) !== $totalCOs) {
                $res['status'] = 0;
                $res['err'] = "Please provide a rating for all Course Outcomes.";
                return $res;
            }

            // Validate that all Questionnaire questions are answered if questions exist
            $qn_result = $this->getSubjectQuestionnaireQuestions($subjectId);
            $totalQuestions = ($qn_result['status'] == 1) ? count($qn_result['data']) : 0;
            if ($totalQuestions > 0 && count($questionnaireResponses) !== $totalQuestions) {
                $res['status'] = 0;
                $res['err'] = "Please provide a rating for all additional questions.";
                return $res;
            }

            $this->conn->begin_transaction();

            // Save CO Feedback with ON DUPLICATE KEY UPDATE to prevent partial submission deadlocks
            if (!empty($coFeedback)) {
                $stmt_co = $this->conn->prepare("INSERT INTO student_co_feedback (student_id, subject_id, class_id, co_id, rating) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
                if (!$stmt_co) {
                    throw new Exception("Failed to prepare CO feedback insert statement: " . $this->conn->error);
                }
                foreach ($coFeedback as $co_id => $rating) {
                    $stmt_co->bind_param("iiiii", $studentId, $subjectId, $classId, $co_id, $rating);
                    if (!$stmt_co->execute()) {
                        throw new Exception("Failed to insert CO feedback for CO $co_id: " . $this->conn->error);
                    }
                }
                $stmt_co->close();
            }

            // Save Questionnaire Responses with ON DUPLICATE KEY UPDATE to prevent partial submission deadlocks
            if (!empty($questionnaireResponses)) {
                $stmt_qn = $this->conn->prepare("INSERT INTO student_questionnaire_responses (student_id, subject_id, question_id, rating) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
                if (!$stmt_qn) {
                    throw new Exception("Failed to prepare questionnaire response insert statement: " . $this->conn->error);
                }
                foreach ($questionnaireResponses as $question_id => $rating) {
                    $stmt_qn->bind_param("iiii", $studentId, $subjectId, $question_id, $rating);
                    if (!$stmt_qn->execute()) {
                        throw new Exception("Failed to insert questionnaire response for question $question_id: " . $this->conn->error);
                    }
                }
                $stmt_qn->close();
            }

            $this->conn->commit();
            $res['status'] = 1;
            $this->dbActivityLog($studentId, "SUBMIT_CO_FEEDBACK", "Submitted Course Outcome and Questionnaire feedback for subject ID $subjectId", "Student", "SUBJECT", (string)$subjectId);

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Check if a student has submitted questionnaire responses for a subject
     *
     * @param int $studentId
     * @param int $subjectId
     * @return array
     */
    public function hasStudentSubmittedQuestionnaireResponses($studentId, $subjectId) {
        $res = ['status' => 0, 'exists' => false];
        $myname = $this->classname . " - hasStudentSubmittedQuestionnaireResponses - ";

        try {
            // Get the total number of questionnaire questions for the subject
            $stmt_count = $this->conn->prepare("SELECT COUNT(*) FROM subject_questionnaire_questions WHERE subject_id = ?");
            if (!$stmt_count) {
                throw new Exception("Failed to prepare count questions query: " . $this->conn->error);
            }
            $stmt_count->bind_param("i", $subjectId);
            if (!$stmt_count->execute()) {
                throw new Exception("Failed to execute count questions query: " . $this->conn->error);
            }
            $stmt_count->bind_result($totalQuestions);
            $stmt_count->fetch();
            $stmt_count->close();

            // If there are no questionnaire questions for this subject, the student hasn't submitted responses (because there's nothing to respond to)
            if ($totalQuestions == 0) {
                $res['status'] = 1;
                $res['exists'] = false;
                return $res;
            }

            // Count the number of distinct questions the student has responded to for this subject
            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT question_id) FROM student_questionnaire_responses WHERE student_id = ? AND subject_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare check questionnaire responses query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $studentId, $subjectId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute check questionnaire responses query: " . $this->conn->error);
            }

            $stmt->bind_result($respondedQuestionsCount);
            if ($stmt->fetch()) {
                $res['status'] = 1;
                // Student has submitted if the number of distinct responses matches the total number of questions
                $res['exists'] = ($respondedQuestionsCount > 0 && $respondedQuestionsCount == $totalQuestions);
            }
            $stmt->close();

        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Check if student has submitted Course End Survey (CES)
     *
     * @param int $student_id
     * @param int $subject_id
     * @return bool
     */
    public function hasStudentSubmittedCES($student_id, $subject_id) {
        $stmt = $this->conn->prepare("SELECT id FROM student_ces_feedback WHERE student_id = ? AND subject_id = ?");
        $stmt->bind_param("ii", $student_id, $subject_id);
        $stmt->execute();
        $stmt->store_result();
        $submitted = ($stmt->num_rows > 0);
        $stmt->close();

        return $submitted;
    }

    /**
     * Check if student submitted feedback for a specific faculty member
     *
     * @param int $student_id
     * @param int $subject_id
     * @param int $faculty_id
     * @return bool
     */
    public function hasStudentSubmittedFacultyFeedback($student_id, $subject_id, $faculty_id) {
        $stmt = $this->conn->prepare("SELECT id FROM student_faculty_feedback WHERE student_id = ? AND subject_id = ? AND faculty_id = ?");
        $stmt->bind_param("iii", $student_id, $subject_id, $faculty_id);
        $stmt->execute();
        $stmt->store_result();
        $submitted = ($stmt->num_rows > 0);
        $stmt->close();
        return $submitted;
    }

    /**
     * Atomic save for complete Course End Survey (Part A COs + Part B 16 Qs + Part C Qualitative)
     *
     * @param int $student_id
     * @param int $subject_id
     * @param array $co_ratings
     * @param array $ces_ratings
     * @param array $comments
     * @param int $is_anonymous
     * @return array
     */
    public function saveCourseEndSurvey($student_id, $subject_id, $co_ratings, $ces_ratings, $comments, $is_anonymous = 0) {
        $res = ['status' => 0];
        $myname = $this->classname . " - saveCourseEndSurvey - ";

        try {
            if ($this->hasStudentSubmittedCES($student_id, $subject_id)) {
                $res['err'] = "You have already completed the Course End Survey for this subject.";
                return $res;
            }

            // Retrieve class_id internally from subjects to satisfy legacy column in student_co_feedback
            $stmt_cls = $this->conn->prepare("SELECT class_id FROM subjects WHERE id = ?");
            $stmt_cls->bind_param("i", $subject_id);
            $stmt_cls->execute();
            $stmt_cls->bind_result($resolved_class_id);
            if (!$stmt_cls->fetch()) {
                throw new Exception("Subject does not exist.");
            }
            $stmt_cls->close();

            $this->conn->begin_transaction();

            // 1. Save Part A (CO Ratings) only if not already submitted
            $co_check = $this->hasStudentGivenCOFeedback($student_id, $subject_id);
            $part_a_already_submitted = ($co_check['status'] == 1 && $co_check['exists']);

            if (!$part_a_already_submitted && !empty($co_ratings)) {
                $stmt_co = $this->conn->prepare("INSERT INTO student_co_feedback (student_id, subject_id, class_id, co_id, rating) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
                foreach ($co_ratings as $co_id => $rating) {
                    $stmt_co->bind_param("iiiii", $student_id, $subject_id, $resolved_class_id, $co_id, $rating);
                    $stmt_co->execute();
                }
                $stmt_co->close();
            }

            // 2. Save Part B & C (General CES Feedback)
            $sql_ces = "INSERT INTO student_ces_feedback (
                student_id, subject_id,
                ces_q1, ces_q2, ces_q3, ces_q4, ces_q5, ces_q6, ces_q7, ces_q8,
                ces_q9, ces_q10, ces_q11, ces_q12, ces_q13, ces_q14, ces_q15, ces_q16,
                useful_aspects, improvement_topics, suggestions, is_anonymous
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_ces = $this->conn->prepare($sql_ces);
            $types = "ii" . str_repeat("i", 16) . "sssi";
            $stmt_ces->bind_param(
                $types,
                $student_id, $subject_id,
                $ces_ratings['ces_q1'], $ces_ratings['ces_q2'], $ces_ratings['ces_q3'], $ces_ratings['ces_q4'],
                $ces_ratings['ces_q5'], $ces_ratings['ces_q6'], $ces_ratings['ces_q7'], $ces_ratings['ces_q8'],
                $ces_ratings['ces_q9'], $ces_ratings['ces_q10'], $ces_ratings['ces_q11'], $ces_ratings['ces_q12'],
                $ces_ratings['ces_q13'], $ces_ratings['ces_q14'], $ces_ratings['ces_q15'], $ces_ratings['ces_q16'],
                $comments['useful_aspects'], $comments['improvement_topics'], $comments['suggestions'],
                $is_anonymous
            );
            $stmt_ces->execute();
            $stmt_ces->close();

            $this->conn->commit();
            $res['status'] = 1;
            $anonText = $is_anonymous ? " (Anonymous)" : "";
            $this->dbActivityLog($student_id, "SUBMIT_CES_FEEDBACK", "Submitted Course End Survey for subject ID $subject_id$anonText", "Student", "SUBJECT", (string)$subject_id);
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Save Student Feedback on Faculty (19 Evaluation Items + 3 Open-Ended Fields)
     *
     * @param int $student_id
     * @param int $subject_id
     * @param int $faculty_id
     * @param array $ratings
     * @param array $comments
     * @return array
     */
    public function saveFacultyFeedback($student_id, $subject_id, $faculty_id, $ratings, $comments) {
        $res = ['status' => 0];
        $myname = $this->classname . " - saveFacultyFeedback - ";

        try {
            if ($this->hasStudentSubmittedFacultyFeedback($student_id, $subject_id, $faculty_id)) {
                $res['err'] = "You have already submitted feedback for this faculty member.";
                return $res;
            }

            $sql = "INSERT INTO student_faculty_feedback (
                student_id, subject_id, faculty_id,
                fac_q1, fac_q2, fac_q3, fac_q4, fac_q5, fac_q6, fac_q7, fac_q8, fac_q9, fac_q10,
                fac_q11, fac_q12, fac_q13, fac_q14, fac_q15, fac_q16, fac_q17, fac_q18, fac_q19,
                faculty_strengths, improvement_areas, additional_comments
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $types = "iii" . str_repeat("i", 19) . "sss";
            $stmt->bind_param(
                $types,
                $student_id, $subject_id, $faculty_id,
                $ratings['fac_q1'], $ratings['fac_q2'], $ratings['fac_q3'], $ratings['fac_q4'], $ratings['fac_q5'],
                $ratings['fac_q6'], $ratings['fac_q7'], $ratings['fac_q8'], $ratings['fac_q9'], $ratings['fac_q10'],
                $ratings['fac_q11'], $ratings['fac_q12'], $ratings['fac_q13'], $ratings['fac_q14'], $ratings['fac_q15'],
                $ratings['fac_q16'], $ratings['fac_q17'], $ratings['fac_q18'], $ratings['fac_q19'],
                $comments['strengths'], $comments['improvement_areas'], $comments['additional_comments']
            );

            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($student_id, "SUBMIT_FACULTY_FEEDBACK", "Submitted faculty feedback for faculty ID $faculty_id, subject ID $subject_id", "Student", "FACULTY", (string)$faculty_id, $faculty_id);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }
}
?>

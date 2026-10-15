<?php
require_once(__DIR__ . "/user.class.php");
require_once(__DIR__ . "/services/StudentRepository.php");
require_once(__DIR__ . "/services/StudentFeedbackService.php");
require_once(__DIR__ . "/services/StudentAttendanceService.php");
require_once(__DIR__ . "/services/StudentAssessmentService.php");

/**
 * Student - Entity and Facade class for student operations
 *
 * Core domain operations have been refactored into focused service classes:
 * - StudentRepository: Data retrieval, enrollment, ownership verification
 * - StudentFeedbackService: CO feedback, CES, Questionnaire, and Faculty feedback
 * - StudentAttendanceService: Attendance records, diary entries, student permissions
 * - StudentAssessmentService: Internal marks, subjects, faculty info, academic metadata
 *
 * This class retains authentication logic, entity properties, and maintains full
 * backward compatibility by proxying calls to underlying services.
 */
class Student extends User {
    private $classname = "Student";
    private $student_id;
    private $class_id;

    // Service instances for backward compatibility delegation
    private $repository;
    private $feedbackService;
    private $attendanceService;
    private $assessmentService;

    public function __construct($student_id = null, $username = null) {
        parent::__construct();
        if ($student_id) {
            $this->student_id = $student_id;
        }
        if ($username) {
            $this->username = $username;
        }
    }

    // Entity property accessors
    public function getStudentId() {
        return $this->student_id;
    }

    public function setStudentId($id) {
        $this->student_id = $id;
    }

    public function getClassId() {
        return $this->class_id;
    }

    public function setClassId($class_id) {
        $this->class_id = $class_id;
    }

    // Lazy service getters
    public function getRepository() {
        if ($this->repository === null) {
            $this->repository = new StudentRepository();
        }
        return $this->repository;
    }

    public function getFeedbackService() {
        if ($this->feedbackService === null) {
            $this->feedbackService = new StudentFeedbackService();
        }
        return $this->feedbackService;
    }

    public function getAttendanceService() {
        if ($this->attendanceService === null) {
            $this->attendanceService = new StudentAttendanceService();
        }
        return $this->attendanceService;
    }

    public function getAssessmentService() {
        if ($this->assessmentService === null) {
            $this->assessmentService = new StudentAssessmentService();
        }
        return $this->assessmentService;
    }

    // Authentication & Password Update
    public function updatePwd($studentId, $oldpassword, $newpassword) {
        $res = ['status' => 0];
        if ($this->verifyPwd($studentId, $oldpassword)) {
            $res = $this->updatePassword($studentId, $newpassword, "student");
        } else {
            $res["err"] = "Incorrect Existing Password";
        }
        return $res;
    }

    // --- Backward Compatibility Facade Methods ---

    // StudentRepository proxies
    public function getEnrolledClassesByUsername($username) {
        return $this->getRepository()->getEnrolledClassesByUsername($username);
    }

    public function verifyStudentOwnership($student_id, $username) {
        return $this->getRepository()->verifyStudentOwnership($student_id, $username);
    }

    public function verifyClassEnrollment($student_id, $class_id) {
        return $this->getRepository()->verifyClassEnrollment($student_id, $class_id);
    }

    public function verifySubjectEnrollment($student_id, $subject_id) {
        return $this->getRepository()->verifySubjectEnrollment($student_id, $subject_id);
    }

    // StudentAttendanceService proxies
    public function getStudentAttendanceData($subject_id, $student_id) {
        return $this->getAttendanceService()->getStudentAttendanceData($subject_id, $student_id);
    }

    public function getDiaryEntriesBySubject($sub_id, $start_date = null, $end_date = null) {
        return $this->getAttendanceService()->getDiaryEntriesBySubject($sub_id, $start_date, $end_date);
    }

    public function getStudentPermissions($student_id, $class_id) {
        return $this->getAttendanceService()->getStudentPermissions($student_id, $class_id);
    }

    // StudentAssessmentService proxies
    public function getSubjectsByStudentId($student_id) {
        return $this->getAssessmentService()->getSubjectsByStudentId($student_id);
    }

    public function getCOsBySubjectId($sub_id) {
        return $this->getAssessmentService()->getCOsBySubjectId($sub_id);
    }

    public function getPrgCodeBySubID($sub_id) {
        return $this->getAssessmentService()->getPrgCodeBySubID($sub_id);
    }

    public function getStInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        return $this->getAssessmentService()->getStInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber);
    }

    public function getStPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        return $this->getAssessmentService()->getStPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber);
    }

    public function getStUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        return $this->getAssessmentService()->getStUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber);
    }

    public function getFacultiesBySubjectId($subject_id) {
        return $this->getAssessmentService()->getFacultiesBySubjectId($subject_id);
    }

    public function getFacultyDetailsById($faculty_id) {
        return $this->getAssessmentService()->getFacultyDetailsById($faculty_id);
    }

    public function getSubjectHeaderDetails($subject_id) {
        return $this->getAssessmentService()->getSubjectHeaderDetails($subject_id);
    }

    // StudentFeedbackService proxies
    public function getFeedbackSubjectsByStudentId($student_id) {
        return $this->getFeedbackService()->getFeedbackSubjectsByStudentId($student_id);
    }

    public function hasStudentGivenCOFeedback($studentId, $subjectId, $classId = null) {
        return $this->getFeedbackService()->hasStudentGivenCOFeedback($studentId, $subjectId, $classId);
    }

    public function getSubjectQuestionnaireQuestions($subjectId) {
        return $this->getFeedbackService()->getSubjectQuestionnaireQuestions($subjectId);
    }

    public function saveCourseOutcomeFeedback($studentId, $subjectId, $classId, $coFeedback, $questionnaireResponses) {
        return $this->getFeedbackService()->saveCourseOutcomeFeedback($studentId, $subjectId, $classId, $coFeedback, $questionnaireResponses);
    }

    public function hasStudentSubmittedQuestionnaireResponses($studentId, $subjectId) {
        return $this->getFeedbackService()->hasStudentSubmittedQuestionnaireResponses($studentId, $subjectId);
    }

    public function hasStudentSubmittedCES($student_id, $subject_id) {
        return $this->getFeedbackService()->hasStudentSubmittedCES($student_id, $subject_id);
    }

    public function hasStudentSubmittedFacultyFeedback($student_id, $subject_id, $faculty_id) {
        return $this->getFeedbackService()->hasStudentSubmittedFacultyFeedback($student_id, $subject_id, $faculty_id);
    }

    public function saveCourseEndSurvey($student_id, $subject_id, $co_ratings, $ces_ratings, $comments, $is_anonymous = 0) {
        return $this->getFeedbackService()->saveCourseEndSurvey($student_id, $subject_id, $co_ratings, $ces_ratings, $comments, $is_anonymous);
    }

    public function saveFacultyFeedback($student_id, $subject_id, $faculty_id, $ratings, $comments) {
        return $this->getFeedbackService()->saveFacultyFeedback($student_id, $subject_id, $faculty_id, $ratings, $comments);
    }
}
?>
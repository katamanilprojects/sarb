<?php
require_once(__DIR__ . "/BaseService.php");

/**
 * StudentAssessmentService - Handles subjects, marks (UG, PG, Lab), course outcomes, faculty details, and academic metadata
 */
class StudentAssessmentService extends BaseService {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Fetch enrolled subjects for a student
     *
     * @param int $student_id
     * @return array
     */
    public function getSubjectsByStudentId($student_id) {
        $query = "
            SELECT `id`, `subject_sno`, `subcode`, `sub_shortname`, `sub_fullname`, `sub_type`
            FROM subjects 
            WHERE id IN (SELECT sub_id FROM `student_sub` WHERE stu_id=?)
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    
        return $subjects;
    }

    /**
     * Fetch program code and subject type by subject ID
     *
     * @param int $sub_id
     * @return array
     */
    public function getPrgCodeBySubID($sub_id) {
        $res = ['status' => 0];
        $myname = $this->classname . " - getPrgCodeBySubID - ";

        try {
            $stmt = $this->conn->prepare("SELECT p.program_code, s.sub_type
                    FROM subjects s
                    INNER JOIN classes c ON s.class_id = c.id
                    INNER JOIN specialization sp ON c.spec_id = sp.id
                    INNER JOIN programs p ON sp.prog_id = p.id
                    WHERE s.id = ?;");
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $this->conn->error);
            }

            $sub_type = "";
            $stmt->bind_param("i", $sub_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query: " . $this->conn->error);
            }

            $stmt->bind_result($prg_code, $sub_type);
            if ($stmt->fetch()) {
                $res['status'] = 1; // Program_code found
                $res['prg_code'] = $prg_code;   // Set prg_code
                $res['sub_type'] = strtolower($sub_type);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Error fetching Program_code based on Subject ID $sub_id: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get UG internal assessment marks for a student, subject, and assessment number
     *
     * @param int $studentId
     * @param int $subjectId
     * @param int $assessmentNumber
     * @return array
     */
    public function getStInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getStInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $subjective_marks, $objective_marks, $assignment_marks);
                while ($stmt->fetch()) {
                    $res['data'][] = [
                        'id' => $id,
                        'subjective_marks' => $subjective_marks,
                        'objective_marks' => $objective_marks,
                        'assignment_marks' => $assignment_marks
                    ];
                }
                $res['status'] = 1; // Marks retrieved successfully
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
    }

    /**
     * Get PG internal assessment marks for a student, subject, and assessment number
     *
     * @param int $studentId
     * @param int $subjectId
     * @param int $assessmentNumber
     * @return array
     */
    public function getStPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getStPGInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `marks` FROM `pg_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $marks);
                while ($stmt->fetch()) {
                    $res['data'][] = [
                        'id' => $id,
                        'marks' => $marks
                    ];
                }
                $res['status'] = 1; // Marks retrieved successfully
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
    }

    /**
     * Get UG Lab internal assessment marks for a student, subject, and assessment number
     *
     * @param int $studentId
     * @param int $subjectId
     * @param int $assessmentNumber
     * @return array
     */
    public function getStUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber) {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getStUGLabInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `day_to_day_marks`, `internal_test_marks` FROM `uglab_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $day_to_day_marks, $internal_test_marks);
                while ($stmt->fetch()) {
                    $res['data'][] = [
                        'id' => $id,
                        'day_to_day_marks' => $day_to_day_marks,
                        'internal_test_marks' => $internal_test_marks
                    ];
                }
                $res['status'] = 1; // Marks retrieved successfully
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
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
     * Fetch faculties assigned to a subject
     *
     * @param int $subject_id
     * @return array
     */
    public function getFacultiesBySubjectId($subject_id) {
        $query = "
            SELECT f.id AS faculty_id, u.name AS faculty_name, f.designation 
            FROM faculty_sub fs
            JOIN faculties f ON fs.faculty_id = f.id
            JOIN users u ON f.facultyid = u.id
            WHERE fs.sub_id = ? AND f.status = '1'
            ORDER BY u.name ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $faculties = [];
        while ($row = $result->fetch_assoc()) {
            $faculties[] = $row;
        }
        $stmt->close();
        return $faculties;
    }

    /**
     * Fetch details of a single faculty member
     *
     * @param int $faculty_id
     * @return array
     */
    public function getFacultyDetailsById($faculty_id) {
        $query = "
            SELECT u.name AS faculty_name, f.designation 
            FROM faculties f 
            JOIN users u ON f.facultyid = u.id 
            WHERE f.id = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: ['faculty_name' => '', 'designation' => ''];
    }

    /**
     * Retrieve complete academic metadata for institutional survey headers
     *
     * @param int $subject_id
     * @return array
     */
    public function getSubjectHeaderDetails($subject_id) {
        $query = "
            SELECT 
                s.subcode, s.sub_fullname, s.sub_type,
                c.classname, c.acad_year, c.yearsem,
                d.dept_fullname,
                p.prog_fullname, p.prog_shortname
            FROM subjects s
            JOIN classes c ON s.class_id = c.id
            JOIN specialization sp ON c.spec_id = sp.id
            JOIN departments d ON sp.dept_id = d.id
            JOIN programs p ON sp.prog_id = p.id
            WHERE s.id = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: [];
    }
}
?>

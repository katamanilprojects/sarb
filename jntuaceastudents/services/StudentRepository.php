<?php
require_once(__DIR__ . "/BaseService.php");

/**
 * StudentRepository - Handles student data queries, enrollment lookups, and ownership verifications
 */
class StudentRepository extends BaseService {
    
    public function __construct() {
        parent::__construct();
    }

    /**
     * Get enrolled classes for a student by their username
     *
     * @param string $username
     * @return array
     */
    public function getEnrolledClassesByUsername($username) {
        $query = "
            SELECT s.id AS student_id, s.class_id, c.classname, c.acad_year, c.start_date, c.end_date 
            FROM students s 
            JOIN classes c ON s.class_id = c.id 
            WHERE s.username = ? order by start_date desc
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $classes = [];

        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    
        return $classes;
    }

    /**
     * Security check: Verify that the specific student_id is linked to the logged-in username
     *
     * @param int $student_id
     * @param string $username
     * @return bool
     */
    public function verifyStudentOwnership($student_id, $username) {
        $query = "SELECT id FROM students WHERE id = ? AND username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $student_id, $username);
        $stmt->execute();
        $stmt->store_result();
        
        $is_valid = $stmt->num_rows > 0;
        $stmt->close();
        
        return $is_valid;
    }

    /**
     * Verify student is actively enrolled in the specified class
     *
     * @param int $student_id
     * @param int $class_id
     * @return bool
     */
    public function verifyClassEnrollment($student_id, $class_id) {
        $stmt = $this->conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND status = 1");
        $stmt->bind_param("ii", $student_id, $class_id);
        $stmt->execute();
        $stmt->store_result();
        $isValid = $stmt->num_rows > 0;
        $stmt->close();
        return $isValid;
    }

    /**
     * Verify student is enrolled in the specified subject
     *
     * @param int $student_id
     * @param int $subject_id
     * @return bool
     */
    public function verifySubjectEnrollment($student_id, $subject_id) {
        $stmt = $this->conn->prepare("SELECT 1 FROM student_sub WHERE stu_id = ? AND sub_id = ?");
        $stmt->bind_param("ii", $student_id, $subject_id);
        $stmt->execute();
        $stmt->store_result();
        $valid = $stmt->num_rows > 0;
        $stmt->close();
        return $valid;
    }
}
?>

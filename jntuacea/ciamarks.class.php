<?php
// ciamarks.class.php - Modular Continuous Internal Assessment (CIA) Marks & Calculations
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait CIAMarksTrait
{
    protected $ciaClassname = "CIAMarks";

    // ----------------------------------------------------
    // UG Theory Internal Assessment Marks
    // ----------------------------------------------------

    public function addInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - addInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO `internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `subjective_marks`, `objective_marks`, `assignment_marks`) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiiddd", $studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_CIA_MARKS", "Added CIA marks for Student ID: $studentId, Subject ID: $subjectId, Assessment #$assessmentNumber", "faculty", "INTERNAL_MARKS", (string)$subjectId, $studentId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function isInternalAssessmentAdded($subjectId, $assessmentNumber)
    {
        $res = 0;
        $myname = $this->ciaClassname . " - isInternalAssessmentAdded - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $subjective_marks, $objective_marks, $assignment_marks);
                while ($stmt->fetch()) {
                    $res = 1;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function updateInternalAssessmentMarks($marksId, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - updateInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("UPDATE `internal_assessment_marks` SET `subjective_marks` = ?, `objective_marks` = ?, `assignment_marks` = ? WHERE `id` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("dddi", $subjectiveMarks, $objectiveMarks, $assignmentMarks, $marksId);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "UPDATE_CIA_MARKS", "Updated CIA marks Record ID: $marksId", "faculty", "INTERNAL_MARKS", (string)$marksId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function deleteStudentMarksByComponent($componentId)
    {
        $res = ['status' => 0, 'deleted_rows' => 0];
        $myname = $this->ciaClassname . " - deleteStudentMarksByComponent - ";

        try {
            $questionIds = [];
            $questionStmt = $this->conn->prepare("SELECT `id` FROM `assessment_questions` WHERE `component_id` = ?");
            if (!$questionStmt) {
                throw new Exception("Failed to prepare get questions statement: " . $this->conn->error);
            }

            $questionStmt->bind_param("i", $componentId);
            $questionStmt->execute();
            $result = $questionStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $questionIds[] = intval($row['id']);
            }
            $questionStmt->close();

            if (empty($questionIds)) {
                $res['status'] = 1;
                return $res;
            }

            $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
            $types = str_repeat('i', count($questionIds));
            $sql = "DELETE FROM `student_marks` WHERE `question_id` IN ($placeholders)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare delete student marks statement: " . $this->conn->error);
            }

            $stmt->bind_param($types, ...$questionIds);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['deleted_rows'] = $stmt->affected_rows;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_CIA_COMPONENT_MARKS", "Deleted " . $res['deleted_rows'] . " student marks for Component ID: $componentId", "faculty", "INTERNAL_MARKS", (string)$componentId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function saveTempMarks($studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - saveTempMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO temp_internal_assessment_marks (student_id, subject_id, assessment_number, subjective_marks, objective_marks, assignment_marks) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subjective_marks = ?, objective_marks = ?, assignment_marks = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiissssss", $studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
            if ($stmt->execute()) {
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

    public function deleteTempMarks($studentId, $subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - deleteTempMarks - ";

        try {
            $stmt = $this->conn->prepare("DELETE FROM `temp_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
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

    public function getTempMarks($subjectId, $assessmentNumber)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->ciaClassname . " - getTempMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `student_id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `temp_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($student_id, $subjective_marks, $objective_marks, $assignment_marks);
                while ($stmt->fetch()) {
                    $res['data'][$student_id] = [
                        'subjective_marks' => $subjective_marks,
                        'objective_marks' => $objective_marks,
                        'assignment_marks' => $assignment_marks
                    ];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
    }

    // ----------------------------------------------------
    // PG Theory Internal Assessment Marks
    // ----------------------------------------------------

    public function addPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - addPGInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO `pg_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `marks`) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiid", $studentId, $subjectId, $assessmentNumber, $marks);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_PG_CIA_MARKS", "Added PG CIA marks for Student ID: $studentId, Subject ID: $subjectId, Mid: $assessmentNumber", "faculty", "INTERNAL_MARKS", (string)$subjectId, $studentId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function isPGInternalAssessmentAdded($subjectId, $assessmentNumber)
    {
        $res = 0;
        $myname = $this->ciaClassname . " - isPGInternalAssessmentAdded - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `marks` FROM `pg_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $marks);
                while ($stmt->fetch()) {
                    $res = 1;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function updatePGInternalAssessmentMarks($marksId, $marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - updatePGInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("UPDATE `pg_internal_assessment_marks` SET `marks` = ? WHERE `id` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("di", $marks, $marksId);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "UPDATE_PG_CIA_MARKS", "Updated PG CIA marks Record ID: $marksId (Marks: $marks)", "faculty", "INTERNAL_MARKS", (string)$marksId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function savePGTempMarks($studentId, $subjectId, $assessmentNumber, $marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - savePGTempMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO temp_pg_internal_assessment_marks (student_id, subject_id, assessment_number, marks) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE marks = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiiss", $studentId, $subjectId, $assessmentNumber, $marks, $marks);
            if ($stmt->execute()) {
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

    public function deletePGTempMarks($studentId, $subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - deletePGTempMarks - ";

        try {
            $stmt = $this->conn->prepare("DELETE FROM `temp_pg_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
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

    public function getPGTempMarks($subjectId, $assessmentNumber)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->ciaClassname . " - getPGTempMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `student_id`, `marks` FROM `temp_pg_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($student_id, $marks);
                while ($stmt->fetch()) {
                    $res['data'][$student_id] = [
                        'marks' => $marks
                    ];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
    }

    // ----------------------------------------------------
    // UG Lab Internal Assessment Marks
    // ----------------------------------------------------

    public function addUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - addUGLabInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO `uglab_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `day_to_day_marks`, `internal_test_marks`) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiidd", $studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_UG_LAB_CIA_MARKS", "Added UG Lab CIA marks for Student ID: $studentId, Subject ID: $subjectId, Mid: $assessmentNumber", "faculty", "INTERNAL_MARKS", (string)$subjectId, $studentId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function isUGLabInternalAssessmentAdded($subjectId, $assessmentNumber)
    {
        $res = 0;
        $myname = $this->ciaClassname . " - isUGLabInternalAssessmentAdded - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `day_to_day_marks`, `internal_test_marks` FROM `uglab_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $day_to_day_marks, $internal_test_marks);
                while ($stmt->fetch()) {
                    $res = 1;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function updateUGLabInternalAssessmentMarks($marksId, $day_to_day_marks, $internal_test_marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - updateUGLabInternalAssessmentMarks - ";

        try {
            $stmt = $this->conn->prepare("UPDATE `uglab_internal_assessment_marks` SET `day_to_day_marks` = ?, `internal_test_marks` = ? WHERE `id` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("ddi", $day_to_day_marks, $internal_test_marks, $marksId);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "UPDATE_UG_LAB_CIA_MARKS", "Updated UG Lab CIA marks Record ID: $marksId (D2D: $day_to_day_marks, Test: $internal_test_marks)", "faculty", "INTERNAL_MARKS", (string)$marksId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function saveUGLabTempMarks($studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - saveUGLabTempMarks - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO temp_uglab_internal_assessment_marks (student_id, subject_id, assessment_number, day_to_day_marks, internal_test_marks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE day_to_day_marks = ?, internal_test_marks = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiissss", $studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks, $day_to_day_marks, $internal_test_marks);
            if ($stmt->execute()) {
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

    public function deleteUGLabTempMarks($studentId, $subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - deleteUGLabTempMarks - ";

        try {
            $stmt = $this->conn->prepare("DELETE FROM `temp_uglab_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
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

    public function getUGLabTempMarks($subjectId, $assessmentNumber)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->ciaClassname . " - getUGLabTempMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT `student_id`, `day_to_day_marks`, `internal_test_marks` FROM `temp_uglab_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($student_id, $day_to_day_marks, $internal_test_marks);
                while ($stmt->fetch()) {
                    $res['data'][$student_id] = [
                        'day_to_day_marks' => $day_to_day_marks,
                        'internal_test_marks' => $internal_test_marks
                    ];
                }
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res['data'];
    }

    // ----------------------------------------------------
    // UG Project Internal Assessment Marks
    // ----------------------------------------------------

    public function addUGProjectInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - addUGProjectInternalAssessmentMarks - ";
        try {
            $stmt = $this->conn->prepare("INSERT INTO `ugproject_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `component1_marks`, `component2_marks`) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
            $stmt->bind_param("iiidd", $studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_UG_PROJECT_CIA_MARKS", "Added UG Project CIA marks for Student ID: $studentId, Subject ID: $subjectId, Review: $assessmentNumber", "faculty", "INTERNAL_MARKS", (string)$subjectId, $studentId);
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function isUGProjectInternalAssessmentAdded($subjectId, $assessmentNumber)
    {
        $res = 0;
        $myname = $this->ciaClassname . " - isUGProjectInternalAssessmentAdded - ";
        try {
            $stmt = $this->conn->prepare("SELECT `id` FROM `ugproject_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ? LIMIT 1");
            if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id);
                if ($stmt->fetch()) $res = 1;
            } else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function saveUGProjectTempMarks($studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - saveUGProjectTempMarks - ";
        try {
            $stmt = $this->conn->prepare("INSERT INTO temp_ugproject_internal_assessment_marks (student_id, subject_id, assessment_number, component1_marks, component2_marks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE component1_marks = ?, component2_marks = ?");
            if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
            $stmt->bind_param("iiissss", $studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks, $component1_marks, $component2_marks);
            if ($stmt->execute()) $res['status'] = 1;
            else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function deleteUGProjectTempMarks($studentId, $subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->ciaClassname . " - deleteUGProjectTempMarks - ";
        try {
            $stmt = $this->conn->prepare("DELETE FROM `temp_ugproject_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
            $stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
            if ($stmt->execute()) $res['status'] = 1;
            else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function getUGProjectTempMarks($subjectId, $assessmentNumber)
    {
        $res = [];
        $myname = $this->ciaClassname . " - getUGProjectTempMarks - ";
        try {
            $stmt = $this->conn->prepare("SELECT `student_id`, `component1_marks`, `component2_marks` FROM `temp_ugproject_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($student_id, $component1_marks, $component2_marks);
                while ($stmt->fetch()) {
                    $res[$student_id] = ['component1_marks' => $component1_marks, 'component2_marks' => $component2_marks];
                }
            } else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    // ----------------------------------------------------
    // Student Question-Wise Marks
    // ----------------------------------------------------

    public function getMarksByComponent($componentId)
    {
        $res = [];
        $myname = $this->ciaClassname . " - getMarksByComponent - ";

        try {
            $stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE question_id IN (SELECT q.id FROM assessment_questions q WHERE q.component_id = ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare get Marks by Component statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $componentId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $res[] = $row;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function getStudentMarksByStId($stu_id)
    {
        $myname = $this->ciaClassname . " - getStudentMarksByStId - ";
        $res = array();

        try {
            $stmt = $this->conn->prepare("SELECT question_id, id, marks_obtained FROM student_marks WHERE stu_id = ?");
            $stmt->bind_param("i", $stu_id);
            $stmt->execute();
            $stmt->bind_result($question_id, $id, $marks_obtained);
            
            while ($stmt->fetch()) {
                $res[$question_id] = $marks_obtained;
            }
            $stmt->close();
            return $res;
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function checkStudentMarks($stu_id, $question_id)
    {
        $myname = $this->ciaClassname . " - checkStudentMarks - ";

        try {
            $stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE stu_id = ? AND question_id = ?");
            $stmt->bind_param("ii", $stu_id, $question_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return null;
    }

    public function addOrUpdateStudentMarks($stu_id, $question_id, $marks_obtained)
    {
        $stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE stu_id = ? AND question_id = ?");
        $stmt->bind_param("ii", $stu_id, $question_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            $stmt = $this->conn->prepare("UPDATE student_marks SET marks_obtained = ? WHERE stu_id = ? AND question_id = ?");
            $stmt->bind_param("dii", $marks_obtained, $stu_id, $question_id);
        } else {
            $stmt->close();
            $stmt = $this->conn->prepare("INSERT INTO student_marks (stu_id, question_id, marks_obtained) VALUES (?, ?, ?)");
            $stmt->bind_param("iid", $stu_id, $question_id, $marks_obtained);
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getMarksByType($student_id, $sub_id, $assessment_number, $component_type)
    {
        $total_marks = 0;

        if ($component_type === "Subjective") {
            return 0; 
        }

        $query = "SELECT SUM(sm.marks_obtained) AS total_marks
                  FROM student_marks sm
                  INNER JOIN assessment_questions aq ON sm.question_id = aq.id
                  INNER JOIN assessment_components ac ON aq.component_id = ac.id
                  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
                  WHERE sm.stu_id = ? 
                  AND ia.sub_id = ? 
                  AND ia.assessment_number = ? 
                  AND ac.component_type = ?";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("iiis", $student_id, $sub_id, $assessment_number, $component_type);
            $stmt->execute();
            $stmt->bind_result($total_marks);
            $stmt->fetch();
            $stmt->close();
        }

        return (float) $total_marks;
    }

    public function getSubjectiveQuestionMarks($student_id, $sub_id, $assessment_number)
    {
        $subjectiveMarks = [];

        $query = "SELECT aq.question_label, sm.marks_obtained 
                  FROM student_marks sm
                  INNER JOIN assessment_questions aq ON sm.question_id = aq.id
                  INNER JOIN assessment_components ac ON aq.component_id = ac.id
                  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
                  WHERE sm.stu_id = ? 
                  AND ia.sub_id = ? 
                  AND ia.assessment_number = ? 
                  AND ac.component_type = 'Subjective'";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("iii", $student_id, $sub_id, $assessment_number);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $questionLabel = $row['question_label'];
                $marksObtained = (float) $row['marks_obtained'];
                $mainQuestionNum = preg_replace('/[^0-9]/', '', $questionLabel);

                if (!isset($subjectiveMarks[$mainQuestionNum])) {
                    $subjectiveMarks[$mainQuestionNum] = 0;
                }
                $subjectiveMarks[$mainQuestionNum] += $marksObtained;
            }
            $stmt->close();
        }

        return $subjectiveMarks;
    }

    public function getTotalMarksByType($sub_id, $assessment_number, $component_type)
    {
        $total_marks = 0;

        $query = "SELECT SUM(aq.marks) AS full_marks
                  FROM assessment_questions aq
                  INNER JOIN assessment_components ac ON aq.component_id = ac.id
                  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
                  WHERE ia.sub_id = ? 
                  AND ia.assessment_number = ? 
                  AND ac.component_type = ?";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("iis", $sub_id, $assessment_number, $component_type);
            $stmt->execute();
            $stmt->bind_result($full_marks);
            $stmt->fetch();
            $stmt->close();
        }

        return $full_marks ?? 0;
    }

    // ----------------------------------------------------
    // Academic Settings & Condensation
    // ----------------------------------------------------

    public function getRegulationForSubject($sub_id): string
    {
        $reg = 'R23';
        $subId = (int)$sub_id;
        if ($subId <= 0) {
            return $reg;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT r.regulation 
                FROM subjects s 
                JOIN classes c ON s.class_id = c.id 
                JOIN regulations r ON c.reg_id = r.id 
                WHERE s.id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $subId);
                if ($stmt->execute()) {
                    $stmt->bind_result($found_reg);
                    if ($stmt->fetch() && !empty($found_reg)) {
                        $reg = strtoupper(trim($found_reg));
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog("CIAMarks - getRegulationForSubject Exception: " . $e->getMessage());
        }
        return $reg;
    }

    public function getAcademicSetting(string $key, ?string $regulation = null, ?int $sub_id = null, mixed $fallback = null): mixed
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (!empty($sub_id)) {
            $regulation = $this->getRegulationIdForSubject((int)$sub_id);
        }
        return \Services\SettingsService::getInstance()->get($key, $regulation ?? 'R23', $fallback);
    }

    public function calculateCiaFinalMarks(float $mid1, float $mid2, ?string $regulation = null, ?int $sub_id = null): array
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (!empty($sub_id)) {
            $regulation = $this->getRegulationIdForSubject((int)$sub_id);
        }
        return \Services\SettingsService::getInstance()->calculateCiaFinal($mid1, $mid2, $regulation ?? 'R23');
    }

    public function condenseSubjectiveMarks(float $rawMarks, float $fullMarks = 30.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_mid_subjective_condensed', $regulation, $sub_id, 15.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    public function condenseObjectiveMarks(float $rawMarks, float $fullMarks = 10.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_mid_objective_marks', $regulation, $sub_id, 10.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    public function condenseAssignmentMarks(float $rawMarks, float $fullMarks = 5.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_assignment_marks', $regulation, $sub_id, 5.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }
}

class CIAMarks extends DBCredentials
{
    use CIAMarksTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

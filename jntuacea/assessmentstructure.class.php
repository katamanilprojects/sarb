<?php
// assessmentstructure.class.php - Modular Assessment Components & Question Blueprint Architecture
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait AssessmentStructureTrait
{
    protected $asClassname = "AssessmentStructure";

    /**
     * Function to get assessment components based on subject ID and assessment number
     */
    public function getAssessmentComponents($subId, $assessmentNumber)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->asClassname . " - getAssessmentComponents - ";

        try {
            $stmt = $this->conn->prepare("SELECT id, component_type, sequence_number 
                                      FROM assessment_components 
                                      WHERE assessment_id IN 
                                            (SELECT id FROM internal_assessments 
                                             WHERE sub_id = ? AND assessment_number = ?)
                                      ORDER BY component_type, sequence_number");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $this->conn->error);
            }

            $stmt->bind_param("is", $subId, $assessmentNumber);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
            }

            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Function to add an internal assessment header record
     */
    public function addInternalAssessments($sub_id, $assessment_number)
    {
        $myname = $this->asClassname . " - addInternalAssessments - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO `internal_assessments`(`sub_id`, `assessment_number`) VALUES (?, ?)");
            $stmt->bind_param("is", $sub_id, $assessment_number);
            $stmt->execute();
            $insertId = $this->conn->insert_id;
            $stmt->close();
            return $insertId;
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Function to add an assessment component, ensuring the internal assessment exists
     */
    public function addAssessmentComponent($subId, $assessmentNumber, $componentType)
    {
        $res = ['status' => 0];
        $myname = $this->asClassname . " - addAssessmentComponent - ";

        try {
            $stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ?");
            $stmt->bind_param("is", $subId, $assessmentNumber);
            $stmt->execute();
            $stmt->bind_result($assessmentId);
            $stmt->fetch();
            $stmt->close();

            if (empty($assessmentId)) {
                $assessmentId = $this->addInternalAssessments($subId, $assessmentNumber);
            }

            if ($componentType === "Activity" || $componentType === "Assignment" || $componentType === "Day-to-Day") {
                $stmt = $this->conn->prepare("SELECT MAX(sequence_number) FROM assessment_components WHERE assessment_id = ? AND component_type = ?");
                $stmt->bind_param("is", $assessmentId, $componentType);
                $stmt->execute();
                $stmt->bind_result($maxSequence);
                $stmt->fetch();
                $stmt->close();
                $sequenceNumber = ($maxSequence !== null) ? $maxSequence + 1 : 1;
            } else {
                $sequenceNumber = 1;
            }

            $stmt = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $assessmentId, $componentType, $sequenceNumber);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['comp_id'] = $this->conn->insert_id;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("addAssessmentComponent Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Function to get sequence number by assessment component ID
     */
    public function getSequenceNoByAssessCompID($componentId)
    {
        $res = 0;
        $myname = $this->asClassname . " - getSequenceNoByAssessCompID - ";

        try {
            $stmt = $this->conn->prepare("SELECT sequence_number FROM assessment_components WHERE id=?");
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $componentId);
            if ($stmt->execute()) {
                $stmt->bind_result($sequenceNumber);
                $stmt->fetch();
                $res = $sequenceNumber;
            } else {
                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
            }

            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Function to get Bloom's Taxonomy Levels
     */
    public function getBloomsLevels()
    {
        $res = [];
        $myname = $this->asClassname . " - getBloomsLevels - ";

        try {
            $stmt = $this->conn->prepare("SELECT id, blooms_level, blooms_label FROM blooms_levels ORDER BY id ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare get Blooms Levels statement: " . $this->conn->error);
            }

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

    /**
     * Function to get Questions by Component ID
     */
    public function getQuestionsByComponent($componentId)
    {
        $res = [];
        $myname = $this->asClassname . " - getQuestionsByComponent - ";

        try {
            $stmt = $this->conn->prepare("SELECT q.id, q.question_label, q.question_type, q.marks, q.blooms_level_id 
                                          FROM assessment_questions q 
                                          WHERE q.component_id = ?
                                          ORDER BY q.id ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare get Questions by Component statement: " . $this->conn->error);
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

    /**
     * Function to get CO labels mapped to a question
     */
    public function getCOsByQuestion($question_id)
    {
        $cos = [];
        $stmt = $this->conn->prepare("SELECT c.co_number 
                                      FROM question_co_mapping aqc 
                                      JOIN course_outcomes c ON aqc.co_id = c.id 
                                      WHERE aqc.question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $cos[] = "CO" . $row['co_number'];
        }
        $stmt->close();
        return $cos;
    }

    /**
     * Function to associate multiple COs to a question
     */
    public function addQuestionCOs($question_id, $co_ids)
    {
        $myname = $this->asClassname . " - addQuestionCOs - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO question_co_mapping (question_id, co_id) VALUES (?, ?)");
            foreach ($co_ids as $co_id) {
                $stmt->bind_param("ii", $question_id, $co_id);
                $stmt->execute();
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Function to add an assessment question
     */
    public function addQuestion($componentid, $label, $type, $marks, $blooms_level)
    {
        $myname = $this->asClassname . " - addQuestion - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO assessment_questions (component_id, question_label, question_type, marks, blooms_level_id) 
                                          VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issdi", $componentid, $label, $type, $marks, $blooms_level);
            $stmt->execute();
            $insertId = $this->conn->insert_id;
            $stmt->close();
            return $insertId;
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Function to get question details
     */
    public function getQuestionDetails($question_id)
    {
        $stmt = $this->conn->prepare("SELECT marks FROM assessment_questions WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * Function to delete question metadata by component
     */
    public function deleteQuestionMetadataByComponent($componentId)
    {
        $res = ['status' => 0, 'err' => ''];
        $myname = $this->asClassname . " - deleteQuestionMetadataByComponent - ";

        try {
            $marksCountStmt = $this->conn->prepare("SELECT COUNT(id) FROM `student_marks` where question_id in (SELECT id FROM `assessment_questions` WHERE `component_id` = ?)");
            if (!$marksCountStmt) {
                throw new Exception("Failed to prepare marks count statement: " . $this->conn->error);
            }
            $marksCountStmt->bind_param("i", $componentId);
            $marksCountStmt->execute();
            $marksCountStmt->bind_result($marksCount);
            $marksCountStmt->fetch();
            $marksCountStmt->close();

            if (!empty($marksCount)) {
                $res['err'] = 'Student marks are present for this component. Delete marks first.';
                return $res;
            }

            $this->conn->begin_transaction();

            $deleteQcoStmt = $this->conn->prepare("DELETE FROM `question_co_mapping` where question_id in (SELECT id FROM `assessment_questions` WHERE `component_id` = ?)");
            if (!$deleteQcoStmt) {
                throw new Exception("Failed to prepare delete question COs statement: " . $this->conn->error);
            }
            $deleteQcoStmt->bind_param("i", $componentId);
            $deleteQcoStmt->execute();
            $deleteQcoStmt->close();

            $deleteQuestionsStmt = $this->conn->prepare("DELETE FROM `assessment_questions` WHERE `component_id` = ?");
            if (!$deleteQuestionsStmt) {
                throw new Exception("Failed to prepare delete questions statement: " . $this->conn->error);
            }
            $deleteQuestionsStmt->bind_param("i", $componentId);
            $deleteQuestionsStmt->execute();
            $deleteQuestionsStmt->close();

            $this->conn->commit();
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->conn->rollback();
            $res['err'] = 'Failed to delete question paper meta data...';
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Helper to get an assessment ID by subject ID and assessment number
     */
    public function getAssessmentId($sub_id, $assessment_number, $auto_create = false)
    {
        $stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ?");
        $stmt->bind_param("is", $sub_id, $assessment_number);
        $stmt->execute();
        $stmt->bind_result($assessmentId);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            return $assessmentId;
        } elseif ($auto_create) {
            return $this->addInternalAssessments($sub_id, $assessment_number);
        }
        return null;
    }

    /**
     * Helper to get a component's ID by its attributes
     */
    public function getComponentId($assessment_id, $component_type, $sequence_number)
    {
        $stmt = $this->conn->prepare("SELECT id FROM assessment_components WHERE assessment_id = ? AND component_type = ? AND sequence_number = ?");
        $stmt->bind_param("isi", $assessment_id, $component_type, $sequence_number);
        $stmt->execute();
        $stmt->bind_result($componentId);
        $found = $stmt->fetch();
        $stmt->close();
        return $found ? $componentId : null;
    }
}

class AssessmentStructure extends DBCredentials
{
    use AssessmentStructureTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

<?php
// ciadataexchange.class.php - Modular CIA Attachments & Data Import/Cloning
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait CIADataExchangeTrait
{
    protected $exClassname = "CIADataExchange";

    /**
     * Function to add CIA Attachment
     */
    public function addCIAAttachment($subjectId, $assessmentNumber, $fileTitle, $filePath)
    {
        $res = ['status' => 0];
        $myname = $this->exClassname . " - addCIAAttachment - ";

        try {
            $stmt = $this->conn->prepare("INSERT INTO `cia_attachments`(`subject_id`, `assessment_number`, `file_title`, `file_path`) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare insert cia_attachments statement: " . $this->conn->error);
            }

            $stmt->bind_param("iiss", $subjectId, $assessmentNumber, $fileTitle, $filePath);
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

    /**
     * Function to get CIA Attachments
     */
    public function getCIAAttachments($subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->exClassname . " - getCIAAttachments - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("ii", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $fileTitle, $filePath);
                $res["count"] = 0;
                $res["files"] = [];
                while ($stmt->fetch()) {
                    $res['status'] = 1;
                    $res['files'][$res["count"]]['id'] = $id;
                    $res['files'][$res["count"]]['file_title'] = $fileTitle;
                    $res['files'][$res["count"]]['file_path'] = $filePath;
                    $res["count"]++;
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
     * Function to delete CIA Attachment
     */
    public function deleteCIAAttachment($id, $subjectId, $assessmentNumber)
    {
        $res = ['status' => 0];
        $myname = $this->exClassname . " - deleteCIAAttachment - ";

        try {
            $stmt = $this->conn->prepare("DELETE FROM `cia_attachments` WHERE `id`=? and `subject_id`=? and `assessment_number`=?");
            if (!$stmt) {
                throw new Exception("Failed to prepare delete cia_attachments statement: " . $this->conn->error);
            }

            $stmt->bind_param("iii", $id, $subjectId, $assessmentNumber);
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

    /**
     * Helper to get public-facing CIA attachments
     */
    public function getPublicCIAAttachments($subjectId, $assessmentNumber)
    {
        $res = ['status' => 0, 'files' => []];
        $myname = $this->exClassname . " - getPublicCIAAttachments - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("is", $subjectId, $assessmentNumber);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $fileTitle, $filePath);
                $res["count"] = 0;
                while ($stmt->fetch()) {
                    $res['status'] = 1;
                    $res['files'][] = [
                        'id' => $id,
                        'file_title' => $fileTitle,
                        'file_path' => $filePath
                    ];
                    $res["count"]++;
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
     * Main function to import CIA data from a source subject to a target subject (NON-DESTRUCTIVE)
     */
    public function importCiaData($source_sub_id, $target_sub_id, $assessment_number, $components_to_copy, $attachments_to_copy)
    {
        $myname = $this->exClassname . " - importCiaData (Non-Destructive) - ";
        $res = ['status' => 0, 'error' => ''];

        try {
            $this->conn->begin_transaction();

            $source_assessment_id = $this->exGetAssessmentId($source_sub_id, $assessment_number);
            if (!$source_assessment_id) {
                throw new Exception("Source subject (ID: $source_sub_id) has no CIA setup for assessment $assessment_number.");
            }

            $target_assessment_id = $this->exGetAssessmentId($target_sub_id, $assessment_number, true);
            if (!$target_assessment_id) {
                throw new Exception("Could not get or create target assessment for subject ID $target_sub_id.");
            }

            $target_co_map = $this->exGetCoMapByNumber($target_sub_id);
            if (empty($target_co_map)) {
                $this->logs->warningLog($myname . "Target subject (ID: $target_sub_id) has no COs defined. Questions will be copied without CO mappings.");
            }

            if (!empty($components_to_copy)) {
                $stmt_c = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, ?)");
                $stmt_q = $this->conn->prepare("INSERT INTO assessment_questions (component_id, question_label, question_type, marks, blooms_level_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_map = $this->conn->prepare("INSERT INTO question_co_mapping (question_id, co_id) VALUES (?, ?)");

                foreach ($components_to_copy as $comp_key) {
                    list($comp_type, $comp_seq) = explode(':', $comp_key);

                    $target_component_id = $this->exGetComponentId($target_assessment_id, $comp_type, $comp_seq);
                    
                    if ($target_component_id) {
                        $existing_questions = $this->exGetQuestionsByComponent($target_component_id);
                        if (!empty($existing_questions)) {
                            $this->logs->warningLog($myname . "Skipping import for component $comp_key on target sub $target_sub_id: Component already has metadata.");
                            continue;
                        }
                    } else {
                        $stmt_c->bind_param("isi", $target_assessment_id, $comp_type, $comp_seq);
                        if (!$stmt_c->execute()) throw new Exception("Failed to create target component $comp_key.");
                        $target_component_id = $this->conn->insert_id;
                    }

                    $source_component_id = $this->exGetComponentId($source_assessment_id, $comp_type, $comp_seq);
                    if (!$source_component_id) {
                        $this->logs->errLog($myname . "Could not find source component $comp_key for source subject $source_sub_id. Skipping.");
                        continue;
                    }

                    $source_questions = $this->exGetQuestionsByComponent($source_component_id);
                    if (empty($source_questions)) {
                        $this->logs->warningLog($myname . "Source component $comp_key for source subject $source_sub_id has no questions. Nothing to copy.");
                        continue;
                    }
                    
                    foreach ($source_questions as $src_q) {
                        $stmt_q->bind_param("issdi", $target_component_id, $src_q['question_label'], $src_q['question_type'], $src_q['marks'], $src_q['blooms_level_id']);
                        if (!$stmt_q->execute()) throw new Exception("Failed to create target question " . $src_q['question_label']);
                        $new_question_id = $this->conn->insert_id;

                        $source_co_numbers = $this->exGetSourceQuestionCoNumbers($src_q['id']);
                        foreach ($source_co_numbers as $co_num) {
                            $co_num_int = (int) $co_num;
                            if (isset($target_co_map[$co_num_int])) {
                                $target_co_id = $target_co_map[$co_num_int];
                                $stmt_map->bind_param("ii", $new_question_id, $target_co_id);
                                if (!$stmt_map->execute()) throw new Exception("Failed to create CO mapping for new QID $new_question_id.");
                            } else {
                                $this->logs->warningLog($myname . "CO number $co_num not found for target subject $target_sub_id. Skipping mapping for question $new_question_id.");
                            }
                        }
                    }
                }
                $stmt_c->close();
                $stmt_q->close();
                $stmt_map->close();
            }

            if (!empty($attachments_to_copy)) {
                $stmt_get_src_att = $this->conn->prepare("SELECT file_path FROM cia_attachments WHERE subject_id = ? AND assessment_number = ? AND file_title = ?");
                $stmt_check_att = $this->conn->prepare("SELECT id FROM cia_attachments WHERE subject_id = ? AND assessment_number = ? AND file_title = ?");
                $stmt_a = $this->conn->prepare("INSERT INTO cia_attachments (subject_id, assessment_number, file_title, file_path) VALUES (?, ?, ?, ?)");

                foreach ($attachments_to_copy as $file_title) {
                    $stmt_check_att->bind_param("iss", $target_sub_id, $assessment_number, $file_title);
                    $stmt_check_att->execute();
                    $stmt_check_att->store_result();
                    if ($stmt_check_att->num_rows > 0) {
                        $this->logs->warningLog($myname . "Skipping import for attachment '$file_title' on target sub $target_sub_id: Attachment already exists.");
                        continue;
                    }

                    $stmt_get_src_att->bind_param("iss", $source_sub_id, $assessment_number, $file_title);
                    $stmt_get_src_att->execute();
                    $stmt_get_src_att->bind_result($file_path);
                    $found_attachment = $stmt_get_src_att->fetch();
                    $stmt_get_src_att->free_result();
                    
                    if (!$found_attachment) {
                        $this->logs->errLog($myname . "Could not find source attachment '$file_title' for source subject $source_sub_id. Skipping.");
                        continue;
                    }

                    $stmt_a->bind_param("isss", $target_sub_id, $assessment_number, $file_title, $file_path);
                    if (!$stmt_a->execute()) throw new Exception("Failed to copy attachment reference for '$file_title'.");
                }
                $stmt_get_src_att->close();
                $stmt_check_att->close();
                $stmt_a->close();
            }

            $this->conn->commit();
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['error'] = $e->getMessage();
        }

        return $res;
    }

    protected function exGetAssessmentId($sub_id, $assessment_number, $auto_create = false)
    {
        if (method_exists($this, 'getAssessmentId')) {
            return $this->getAssessmentId($sub_id, $assessment_number, $auto_create);
        }
        require_once("assessmentstructure.class.php");
        $as = new AssessmentStructure();
        return $as->getAssessmentId($sub_id, $assessment_number, $auto_create);
    }

    protected function exGetComponentId($assessment_id, $component_type, $sequence_number)
    {
        if (method_exists($this, 'getComponentId')) {
            return $this->getComponentId($assessment_id, $component_type, $sequence_number);
        }
        require_once("assessmentstructure.class.php");
        $as = new AssessmentStructure();
        return $as->getComponentId($assessment_id, $component_type, $sequence_number);
    }

    protected function exGetQuestionsByComponent($component_id)
    {
        if (method_exists($this, 'getQuestionsByComponent')) {
            return $this->getQuestionsByComponent($component_id);
        }
        require_once("assessmentstructure.class.php");
        $as = new AssessmentStructure();
        return $as->getQuestionsByComponent($component_id);
    }

    protected function exGetCoMapByNumber($sub_id)
    {
        if (method_exists($this, 'getCoMapByNumber')) {
            return $this->getCoMapByNumber($sub_id);
        }
        require_once("courseoutcome.class.php");
        $co = new CourseOutcome();
        return $co->getCoMapByNumber($sub_id);
    }

    protected function exGetSourceQuestionCoNumbers($question_id)
    {
        if (method_exists($this, 'getSourceQuestionCoNumbers')) {
            return $this->getSourceQuestionCoNumbers($question_id);
        }
        require_once("courseoutcome.class.php");
        $co = new CourseOutcome();
        return $co->getSourceQuestionCoNumbers($question_id);
    }
}

class CIADataExchange extends DBCredentials
{
    use CIADataExchangeTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

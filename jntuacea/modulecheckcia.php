<?php
require_once __DIR__.'/bootstrap.php';

$includeInternalMarks = false;

$prg_res = $facultyObj->getPrgCodeBySubID($selected_sub_id);

if (!empty($prg_res['prg_code'])) {
    if ($prg_res['prg_code'] == "A") {
        if ($prg_res["sub_type"] == "lab") {
            // Fetch assessment details for both assessments
            for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) {
                foreach ($studentList as &$student) {
                    $marks = $facultyObj->getStUGLabInternalAssessmentMarks($student['id'], $selected_sub_id, $assessmentNumber);
                    $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) {
                        $includeInternalMarks = true;
                    }
                }
                unset($student);
            }
        } elseif ($prg_res["sub_type"] == "dti") {
            // Fetch assessment details for both assessments
            for ($assessmentNumber = 1; $assessmentNumber <= 1; $assessmentNumber++) {
                foreach ($studentList as &$student) {
                    $marks = $facultyObj->getStUGLabInternalAssessmentMarks($student['id'], $selected_sub_id, $assessmentNumber);
                    $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) {
                        $includeInternalMarks = true;
                    }
                }
                unset($student);
            }
        } elseif ($prg_res["sub_type"] == "project") {
            foreach ($studentList as &$student) {
                $marks = $facultyObj->getStUGProjectInternalAssessmentMarks($student['id'], $selected_sub_id, 1);
                $student['marks'][1] = !empty($marks) ? $marks[0] : null;
                if (!empty($marks)) {
                    $includeInternalMarks = true;
                }
            }
            unset($student);
        } else {
            // Fetch assessment details for both assessments
            for ($assessmentNumber = 1; $assessmentNumber <= 2; $assessmentNumber++) {
                foreach ($studentList as &$student) {
                    $marks = $facultyObj->getStInternalAssessmentMarks($student['id'], $selected_sub_id, $assessmentNumber);
                    $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) {
                        $includeInternalMarks = true;
                    }
                }
                unset($student);
            }
        }
    } else {
        if ($prg_res["sub_type"] == "lab") {
            // Fetch assessment details for both assessments
            for ($assessmentNumber = 11; $assessmentNumber <= 11; $assessmentNumber++) {
                foreach ($studentList as &$student) {
                    $marks = $facultyObj->getStPGInternalAssessmentMarks($student['id'], $selected_sub_id, $assessmentNumber);
                    $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) {
                        $includeInternalMarks = true;
                    }
                }
                unset($student);
            }
        } else {
            // Fetch assessment details for both assessments
            for ($assessmentNumber = 1; $assessmentNumber <= 3; $assessmentNumber++) {
                foreach ($studentList as &$student) {
                    $marks = $facultyObj->getStPGInternalAssessmentMarks($student['id'], $selected_sub_id, $assessmentNumber);
                    $student['marks'][$assessmentNumber] = !empty($marks) ? $marks[0] : null;
                    if (!empty($marks)) {
                        $includeInternalMarks = true;
                    }
                }
                unset($student);
            }
        }
    }
}

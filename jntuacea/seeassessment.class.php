<?php
// seeassessment.class.php - Modular Semester End Examination (SEE) & Attainment Architecture
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait SEEAssessmentTrait
{
    protected $seeClassname = "SEEAssessment";

    /**
     * Helper to resolve regulation for subject
     */
    protected function resolveRegulation($sub_id): string
    {
        if (method_exists($this, 'getRegulationForSubject')) {
            return $this->getRegulationForSubject($sub_id);
        }
        require_once("ciamarks.class.php");
        $m = new CIAMarks();
        return $m->getRegulationForSubject($sub_id);
    }

    /**
     * Helper to resolve academic setting
     */
    protected function resolveAcademicSetting($key, $reg, $sub_id, $default)
    {
        if (method_exists($this, 'getAcademicSetting')) {
            return $this->getAcademicSetting($key, $reg, $sub_id, $default);
        }
        require_once("ciamarks.class.php");
        $m = new CIAMarks();
        return $m->getAcademicSetting($key, $reg, $sub_id, $default);
    }

    /**
     * Helper to resolve questions by component
     */
    protected function resolveQuestionsByComponent($component_id)
    {
        if (method_exists($this, 'getQuestionsByComponent')) {
            return $this->getQuestionsByComponent($component_id);
        }
        require_once("assessmentstructure.class.php");
        $s = new AssessmentStructure();
        return $s->getQuestionsByComponent($component_id);
    }

    /**
     * Helper to resolve COs by subject ID
     */
    protected function resolveCOsBySubjectId($sub_id)
    {
        if (method_exists($this, 'getCOsBySubjectId')) {
            return $this->getCOsBySubjectId($sub_id);
        }
        require_once("courseoutcome.class.php");
        $co = new CourseOutcome();
        return $co->getCOsBySubjectId($sub_id);
    }

    /**
     * Return external QP templates conforming to autonomous regulations
     */
    public function getSEETemplates()
    {
        return [
            'see_theory_70' => [
                'id' => 'see_theory_70',
                'name' => 'SEE Regular Theory (70 Marks)',
                'total_marks' => 70.0,
                'q1_subparts' => 10,
                'q1_marks_each' => 2.0,
                'q1_total' => 20.0,
                'choice_groups' => [
                    ['label' => '2 or 3', 'pair' => ['2', '3'], 'marks' => 10.0],
                    ['label' => '4 or 5', 'pair' => ['4', '5'], 'marks' => 10.0],
                    ['label' => '6 or 7', 'pair' => ['6', '7'], 'marks' => 10.0],
                    ['label' => '8 or 9', 'pair' => ['8', '9'], 'marks' => 10.0],
                    ['label' => '10 or 11', 'pair' => ['10', '11'], 'marks' => 10.0],
                ],
                'types' => ['Question', 'Short_Answer', 'Analytical', 'Problem', 'Derivation']
            ],
            'see_split_35' => [
                'id' => 'see_split_35',
                'name' => 'SEE Composite Course Split (35 Marks)',
                'total_marks' => 35.0,
                'q1_subparts' => 5,
                'q1_marks_each' => 1.0,
                'q1_total' => 5.0,
                'choice_groups' => [
                    ['label' => '2 or 3', 'pair' => ['2', '3'], 'marks' => 10.0],
                    ['label' => '4 or 5', 'pair' => ['4', '5'], 'marks' => 10.0],
                    ['label' => '6 or 7', 'pair' => ['6', '7'], 'marks' => 10.0],
                ],
                'types' => ['Question', 'Short_Answer', 'Analytical', 'Problem']
            ]
        ];
    }

    /**
     * Retrieve or create SEE assessment record in internal_assessments
     */
    public function getOrCreateSEEAssessment($sub_id, $assessment_number = 'SEE')
    {
        $this->assertSubjectScope((int)$sub_id);
        $stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ? LIMIT 1");
        $stmt->bind_param("is", $sub_id, $assessment_number);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            return intval($res['id']);
        }

        require_once __DIR__.'/services/FeatureManager.php';
        if(!(PHP_SAPI==='cli'&&empty($_SESSION['role']))&&!\Services\FeatureManager::canAction('MOD_SEE_MARKS','edit')){return 0;}
        $stmtInsert = $this->conn->prepare("INSERT INTO internal_assessments (sub_id, assessment_number) VALUES (?, ?)");
        $stmtInsert->bind_param("is", $sub_id, $assessment_number);
        $stmtInsert->execute();
        $newId = $stmtInsert->insert_id;
        $stmtInsert->close();

        return intval($newId);
    }

    /**
     * Retrieve or create SEE assessment component
     */
    public function getOrCreateSEEComponent($sub_id, $component_type = 'SEE-Theory', $assessment_number = 'SEE')
    {
        $assessment_id = $this->getOrCreateSEEAssessment($sub_id, $assessment_number);
        if(!$assessment_id){return ['id'=>0,'assessment_id'=>0,'component_type'=>$component_type,'sequence_number'=>1];}
        
        $stmt = $this->conn->prepare("SELECT id, assessment_id, component_type, sequence_number FROM assessment_components WHERE assessment_id = ? AND component_type = ? LIMIT 1");
        $stmt->bind_param("is", $assessment_id, $component_type);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($comp) {
            return $comp;
        }

        $stmtInsert = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, 1)");
        $stmtInsert->bind_param("is", $assessment_id, $component_type);
        $stmtInsert->execute();
        $newCompId = $stmtInsert->insert_id;
        $stmtInsert->close();

        return [
            'id' => $newCompId,
            'assessment_id' => $assessment_id,
            'component_type' => $component_type,
            'sequence_number' => 1
        ];
    }

    /**
     * Check if SEE question paper metadata has been populated
     */
    public function isSEEMetadataConfigured($sub_id, $component_type = 'SEE-Theory')
    {
        $this->assertSubjectScope((int)$sub_id);
        $stmt=$this->conn->prepare('SELECT ac.id FROM assessment_components ac JOIN internal_assessments ia ON ia.id=ac.assessment_id WHERE ia.sub_id=? AND ia.assessment_number=\'SEE\' AND ac.component_type=?');
        $stmt->bind_param('is',$sub_id,$component_type);$stmt->execute();$components=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($components)!==1){return false;}
        require_once __DIR__.'/core/AssessmentPolicy.php';
        return count((new \College\AssessmentPolicy($this->conn))->questions((int)$sub_id,(int)$components[0]['id']))>0;
    }

    /**
     * Check if finalized SEE marks exist in external_assessment_marks
     */
    public function isSEEMarksSubmitted($sub_id)
    {
        $this->assertSubjectScope((int)$sub_id);
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM external_assessment_marks WHERE subject_id = ? AND is_current=1");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (!empty($row['total']) && $row['total'] > 0);
    }

    /**
     * Fetch detailed student question scores for a specific assessment component
     */
    public function getComponentQuestionMarks($student_id, $component_id)
    {
        $scope = $this->conn->prepare('SELECT ia.sub_id FROM assessment_components ac JOIN internal_assessments ia ON ia.id=ac.assessment_id WHERE ac.id=?');
        $scope->bind_param('i',$component_id);$scope->execute();$subject=$scope->get_result()->fetch_row();
        if(!$subject){throw new \DomainException('Assessment component not found.');}
        $this->assertStudentScope((int)$student_id,(int)$subject[0]);
        $stmt = $this->conn->prepare("
            SELECT aq.question_label, sm.marks_obtained, aq.marks as max_marks
            FROM assessment_questions aq
            LEFT JOIN student_marks sm ON sm.question_id = aq.id AND sm.stu_id = ?
            WHERE aq.component_id = ?
        ");
        $stmt->bind_param("ii", $student_id, $component_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $marks = [];
        while ($row = $res->fetch_assoc()) {
            $marks[$row['question_label']] = [
                'obtained' => ($row['marks_obtained'] !== null) ? floatval($row['marks_obtained']) : null,
                'max' => floatval($row['max_marks'])
            ];
        }
        $stmt->close();
        return $marks;
    }

    /**
     * Execute Either/Or choice resolution and consolidate SEE marks for all mapped students
     */
    public function consolidateSEEMarks($sub_id, $component_type = 'SEE-Theory', $assessment_number = 'SEE')
    {
        $this->assertSubjectScope((int)$sub_id);
        require_once __DIR__.'/core/AssessmentPolicy.php';
        $policy=(new \College\AssessmentPolicy($this->conn))->forSubject((int)$sub_id);
        $comp=$this->getOrCreateSEEComponent($sub_id,$component_type,$assessment_number);
        $questions=(new \College\AssessmentPolicy($this->conn))->questions((int)$sub_id,(int)$comp['id']);
        if(!$questions){return [];}
        $templates=$this->getSEETemplates();$split=str_contains($component_type,'Split');
        $groups=$policy['config']['choice_groups']??\College\AssessmentPolicy::legacyGroups($questions,$templates[$split?'see_split_35':'see_theory_70']);
        $max=$policy['config']['net_max'];
        if($split){$max=(float)$this->resolveAcademicSetting('composite_split_max_marks',$this->resolveRegulation($sub_id),$sub_id,35);}
        require_once __DIR__.'/faculty.class.php';
        $students=(new Faculty())->getMappedStudents($sub_id);$result=[];
        foreach($students as $student){$marks=$this->getComponentQuestionMarks($student['id'],$comp['id']);$scores=[];foreach($questions as $q){$scores[(int)$q['id']]=$marks[$q['question_label']]['obtained']??null;}
            $normal=\College\MarksNormalizer::questions($questions,$scores,$groups,$max);$q1=0;$q1Max=0;foreach($normal['selected_questions'] as $q){if(preg_match('/^1[a-z]+$/i',$q['label'])){$q1+=$q['obtained'];$q1Max+=$q['maximum'];}}$normal['q1_max']=$q1Max;$normal['choice_max']=$normal['gross_max']-$q1Max;
            $result[$student['id']]=['student_id'=>$student['id'],'username'=>$student['username'],'name'=>$student['name'],'q1_marks'=>$q1,'groups'=>$normal['selected_options'],'choice_marks'=>$normal['gross']-$q1,'gross_marks'=>$normal['gross'],'gross_max'=>$normal['gross_max'],'final_external'=>$normal['net'],'max_marks'=>$normal['net_max'],'complete'=>$normal['complete'],'normalisation'=>$normal,'component_type'=>$component_type,'assessment_number'=>$assessment_number,'component_id'=>(int)$comp['id'],'question_hash'=>hash('sha256',json_encode($questions,JSON_THROW_ON_ERROR)),'policy_hash'=>$policy['hash'],'source_scores_hash'=>hash('sha256',json_encode($scores,JSON_THROW_ON_ERROR))];
        }
        return $result;
    }

    /**
     * Save consolidated Mode A marks into external_assessment_marks
     */
    public function saveConsolidatedSEEMarks($sub_id, array $marksData, $faculty_id, $entry_mode = 'DETAILED')
    {
        require_once __DIR__.'/services/SeeMarksWriter.php';
        try {
            // Recompute from stored questions; posted totals never become academic evidence.
            $type=$marksData[0]['component_type']??'SEE-Theory';$rows=$this->consolidateSEEMarks($sub_id,$type,$marksData[0]['assessment_number']??'SEE');
            foreach($rows as $row){if(!$row['complete']){throw new \DomainException('Some assessed questions have missing marks. Enter explicit zeros or complete the grid before consolidation.');}}
            return (new \Services\SeeMarksWriter())->save((int)$sub_id,array_values($rows),'DETAILED');
        } catch(\Throwable $e){return ['status'=>0,'err'=>$e->getMessage()];}
    }

    public function saveDirectSEEMarks($sub_id, array $studentMarks, $max_marks, $faculty_id)
    {
        require_once __DIR__.'/services/SeeMarksWriter.php';
        try{$rows=[];foreach($studentMarks as $student=>$score){if($score===null||trim((string)$score)===''){continue;}$rows[]=['student_id'=>(int)$student,'final_external'=>$score,'max_marks'=>$max_marks];}
            return (new \Services\SeeMarksWriter())->save((int)$sub_id,$rows,'DIRECT');
        }catch(\Throwable $e){return ['status'=>0,'err'=>$e->getMessage()];}
    }

    /**
     * Retrieve saved external marks joined with student master records
     */
    public function getSavedSEEMarks($sub_id)
    {
        $this->assertSubjectScope((int)$sub_id);
        $stmt = $this->conn->prepare("
            SELECT eam.*, s.username, u.name 
            FROM external_assessment_marks eam
            JOIN students s ON s.id = eam.student_id
            JOIN student_sub ss ON ss.stu_id=s.id AND ss.sub_id=eam.subject_id
            LEFT JOIN users u ON u.username = s.username
            WHERE eam.subject_id = ? AND eam.is_current=1
            ORDER BY s.username ASC
        ");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }

    /**
     * Resolve midpoint percentage from regulation grade bands or autonomous university scale.
     */
    public function resolveRegulationGradePercentage(int|string $reg, string $grade, float $points = 0.0): float
    {
        $bands = [];
        try {
            require_once __DIR__ . '/services/SettingsService.php';
            $bands = \Services\SettingsService::getInstance()->get('grade_bands_json', (string)$reg, []);
        } catch (\Throwable) {
            $bands = [];
        }
        if (is_array($bands) && !empty($bands)) {
            foreach ($bands as $band) {
                if (strcasecmp(trim((string)($band['grade'] ?? '')), $grade) === 0 || (abs((float)($band['points'] ?? -99) - $points) < 0.1 && $points > 0)) {
                    $min = (float)($band['min_percent'] ?? $band['min'] ?? 0);
                    $max = (float)($band['max_percent'] ?? $band['max'] ?? 100);
                    return round(($min + $max) / 2.0, 2);
                }
            }
        }
        return match (strtoupper($grade)) {
            'O', 'S'              => 95.0,
            'A+', 'EX', 'A1'      => 85.0,
            'A', 'A2'             => 75.0,
            'B+', 'B1'            => 65.0,
            'B', 'B2'             => 57.5,
            'C', 'C1', 'C2'       => 52.5,
            'D', 'D1', 'D2', 'P'  => 47.5,
            'E', 'E1', 'E2'       => 42.5,
            'F', 'AB', 'MP'       => 0.0,
            default => ($points > 0) ? min(100.0, max(40.0, round(($points * 10.0) - 5.0, 2))) : 0.0
        };
    }

    /**
     * Calculate External (SEE) Course Outcome Attainment
     * Implements 3-tier attainment architecture:
     * Tier 1: Question-wise marks (if in detail question-wise marks are added)
     * Tier 2: QP metadata distribution (if SEE Question Paper metadata is uploaded, distributed across metadata CO distribution)
     * Tier 3: Results only (distributed across all COs using external marks or regulation grade point range)
     */
    public function calculateSEECOAttainment($sub_id, $component_type = 'SEE-Theory')
    {
        $this->assertSubjectScope((int)$sub_id);
        require_once __DIR__.'/core/AssessmentPolicy.php';
        require_once __DIR__.'/core/AttainmentCalculator.php';
        $policy = (new \College\AssessmentPolicy($this->conn))->forSubject((int)$sub_id);
        $reg = $this->resolveRegulation($sub_id);
        $target = $policy['config']['co_target_percentage'] ?? $this->resolveAcademicSetting('co_target_percentage', $reg, $sub_id, 60.0);
        $levels = [];
        foreach ([1 => 50, 2 => 60, 3 => 70] as $level => $fallback) {
            $key = 'attainment_level_' . $level . '_threshold';
            $levels[$level] = $policy['config'][$key] ?? $this->resolveAcademicSetting($key, $reg, $sub_id, $fallback);
        }
        $cos = $this->resolveCOsBySubjectId($sub_id)['data'] ?? [];
        if (!$cos) {
            return ['status' => 0, 'err' => 'No Course Outcomes configured for this course.'];
        }

        $saved = $this->getSavedSEEMarks($sub_id);

        // Fallback / Auto-sync: If external_assessment_marks has no finalized records, check published exam_results
        if (!$saved) {
            $stmtPub = $this->conn->prepare("
                SELECT er.student_id, er.external_marks, er.grade_letter, er.grade_points, er.result_status, en.id as notification_id
                FROM exam_results er
                JOIN exam_notifications en ON en.id = er.notification_id
                WHERE er.subject_id = ? AND en.is_published = 1 AND er.student_id IS NOT NULL
            ");
            $stmtPub->bind_param("i", $sub_id);
            $stmtPub->execute();
            $pubRows = $stmtPub->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtPub->close();

            if (!empty($pubRows)) {
                $netMax = (float)($policy['config']['net_max'] ?? 70.0);
                $saved = [];
                foreach ($pubRows as $pr) {
                    $em = ($pr['external_marks'] !== null) ? (float)$pr['external_marks'] : null;
                    $evidence = null;
                    if ($em === null) {
                        $grd = strtoupper(trim((string)($pr['grade_letter'] ?? '')));
                        $pts = (float)($pr['grade_points'] ?? 0.0);
                        $pct = $this->resolveRegulationGradePercentage($reg, $grd, $pts);
                        $em = round(($pct / 100.0) * $netMax, 2);
                        $evidence = ['basis' => 'RESULTS_REGULATION_RANGE', 'percentage' => $pct, 'grade' => $grd, 'points' => $pts];
                    }
                    $saved[] = [
                        'student_id'     => (int)$pr['student_id'],
                        'subject_id'     => (int)$sub_id,
                        'entry_mode'     => 'DIRECT',
                        'external_marks' => $em,
                        'max_marks'      => $netMax,
                        'evidence'       => $evidence
                    ];
                }
            }
        }

        if (!$saved) {
            return ['status' => 0, 'pending' => true, 'err' => 'No current finalized external marks or published results found for this course.'];
        }

        // Check if Question Paper metadata is uploaded/configured for SEE
        $stmt = $this->conn->prepare('SELECT ac.id FROM assessment_components ac JOIN internal_assessments ia ON ia.id=ac.assessment_id WHERE ia.sub_id=? AND ac.component_type=? ORDER BY ac.id');
        $stmt->bind_param('is', $sub_id, $component_type);
        $stmt->execute();
        $components = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $questions = count($components) === 1 ? (new \College\AssessmentPolicy($this->conn))->questions((int)$sub_id, (int)$components[0]['id']) : [];
        $templates = $this->getSEETemplates();
        $groups = $policy['config']['choice_groups'] ?? \College\AssessmentPolicy::legacyGroups($questions, $templates[str_contains($component_type, 'Split') ? 'see_split_35' : 'see_theory_70']);

        // Tier 2: Calculate Question Paper metadata distribution across COs
        $qpWeights = [];
        if (!empty($questions)) {
            $qpMarksPerCo = [];
            $totalQp = 0.0;
            foreach ($questions as $q) {
                $qMarks = (float)$q['marks'];
                $mappedCos = $q['cos'] ?? [];
                if (!empty($mappedCos)) {
                    $share = $qMarks / count($mappedCos);
                    foreach ($mappedCos as $cid) {
                        $qpMarksPerCo[(int)$cid] = ($qpMarksPerCo[(int)$cid] ?? 0.0) + $share;
                        $totalQp += $share;
                    }
                }
            }
            if ($totalQp > 0) {
                foreach ($qpMarksPerCo as $cid => $alMarks) {
                    $qpWeights[$cid] = [
                        'weight' => $alMarks / $totalQp,
                        'marks'  => $alMarks
                    ];
                }
            }
        }

        foreach ($saved as &$row) {
            $evidence = json_decode($row['normalisation_json'] ?? 'null', true) ?: ($row['evidence'] ?? null);

            // Tier 1: Question-wise reconstruction if entry mode is DETAILED
            if (($row['entry_mode'] ?? '') === 'DETAILED' && !isset($evidence['cos']) && $questions) {
                $byLabel = $this->getComponentQuestionMarks($row['student_id'], $components[0]['id']);
                $scores = [];
                foreach ($questions as $q) {
                    $scores[$q['id']] = $byLabel[$q['question_label']]['obtained'] ?? null;
                }
                $evidence = \College\MarksNormalizer::questions($questions, $scores, $groups, (float)$row['max_marks']);
            }

            // Fallback marks from evidence percentage if external_marks was not directly provided
            if (($row['external_marks'] === null || (float)$row['external_marks'] <= 0) && isset($evidence['percentage'])) {
                $netMax = (float)($row['max_marks'] ?? 70.0);
                $row['external_marks'] = round(((float)$evidence['percentage'] / 100.0) * $netMax, 2);
            }

            $row['evidence'] = $evidence;
        }
        unset($row);

        $present = array_fill_keys(array_map('intval', array_column($saved, 'student_id')), true);
        $stmt = $this->conn->prepare('SELECT stu_id FROM student_sub WHERE sub_id=?');
        $stmt->bind_param('i', $sub_id);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $student) {
            if (!isset($present[(int)$student['stu_id']])) {
                $saved[] = ['student_id' => (int)$student['stu_id'], 'entry_mode' => 'MISSING', 'evidence' => null];
            }
        }

        return \College\AttainmentCalculator::external($cos, $saved, (float)$target, $levels, $qpWeights);
    }

    /**
     * Calculate Internal (CIA) Course Outcome Attainment
     */
    public function calculateCIACOAttainment($sub_id)
    {
        $reg = $this->resolveRegulation($sub_id);
        $l3Threshold = (float)$this->resolveAcademicSetting('attainment_level_3_threshold', $reg, $sub_id, 70.0);
        $l2Threshold = (float)$this->resolveAcademicSetting('attainment_level_2_threshold', $reg, $sub_id, 60.0);
        $l1Threshold = (float)$this->resolveAcademicSetting('attainment_level_1_threshold', $reg, $sub_id, 50.0);

        require_once("faculty.class.php");
        $facultyObj = new Faculty();
        $students = $facultyObj->getMappedStudents($sub_id);
        $totalStudents = count($students);

        require_once("facciaanalysis2.class.php");
        $analysisObj = new COAnalysis();
        $coJson = $analysisObj->getCOAttainment($sub_id);
        $coData = json_decode($coJson, true);

        if (!empty($coData['data'])) {
            $coAttainment = [];
            foreach ($coData['data'] as $row) {
                $available=!empty($row['is_assessed'])&&isset($row['co_attainment_percentage']);$cohortPct=$available?(float)$row['co_attainment_percentage']:null;
                $level = 0;
                if ($cohortPct >= $l3Threshold) $level = 3;
                elseif ($cohortPct >= $l2Threshold) $level = 2;
                elseif ($cohortPct >= $l1Threshold) $level = 1;

                $coAttainment[$row['co_number']] = [
                    'co_id' => $row['co_id'],
                    'attained_students' => 0,
                    'total_students' => $totalStudents,
                    'cohort_percentage' => $available?round($cohortPct, 2):null,
                    'attainment_level' => $available?$level:null,
                    'unmapped' => !$available
                ];
            }
            return ['status' => 1, 'data' => $coAttainment];
        }

        $cosResult = $this->resolveCOsBySubjectId($sub_id);
        $cos = !empty($cosResult['data']) ? $cosResult['data'] : [];
        if (empty($cos)) {
            return ['status' => 0, 'err' => 'No Course Outcomes configured for this course.'];
        }

        $coAttainment = [];
        foreach ($cos as $co) {
            $coAttainment[$co['co_number']] = [
                'co_id' => $co['id'],
                'attained_students' => 0,
                'total_students' => $totalStudents,
                'cohort_percentage' => null,
                'attainment_level' => null,
                'unmapped' => true
            ];
        }
        return ['status' => 1, 'data' => $coAttainment];
    }
}

class SEEAssessment extends DBCredentials
{
    use SEEAssessmentTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

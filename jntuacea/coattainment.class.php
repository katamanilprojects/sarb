<?php
// coattainment.class.php - Modular Course Outcome & Program Outcome Attainment Engine
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait COAttainmentTrait
{
    // Helper to normalize single ID or multiple IDs (array or comma-separated string)
    public function normalizeSubjectIds($subject_id)
    {
        if (is_array($subject_id)) {
            $ids = array_map('intval', $subject_id);
        } elseif (is_string($subject_id) && strpos($subject_id, ',') !== false) {
            $ids = array_map('intval', explode(',', $subject_id));
        } else {
            $ids = [(int)$subject_id];
        }
        $ids = array_filter($ids, function($id) { return $id > 0; });
        return !empty($ids) ? array_values(array_unique($ids)) : [0];
    }

    public function buildInClause($ids)
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ['sql' => $placeholders, 'params' => $ids];
    }

    public function getSubjectType($subject_id)
    {
        $ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($ids);
        $subQuery = "SELECT sub_type FROM subjects WHERE id IN ({$in['sql']})";
        $subRes = $this->fetchAssoc($subQuery, $in['params']);
        foreach ($subRes as $row) {
            $st = strtolower($row['sub_type'] ?? 'theory');
            if ($st === 'lab' || $st === 'dti') {
                return $st;
            }
        }
        return strtolower($subRes[0]['sub_type'] ?? 'theory');
    }

    /**
     * Resolve active regulation code for the subject(s).
     */
    public function getRegulationForSubject($subject_id): string
    {
        $ids = $this->normalizeSubjectIds($subject_id);
        $subId = $ids[0] ?? 0;
        if ($subId <= 0) {
            return 'R23';
        }
        $query = "SELECT r.regulation FROM subjects s JOIN classes c ON s.class_id = c.id JOIN regulations r ON c.reg_id = r.id WHERE s.id = ?";
        $rows = $this->fetchAssoc($query, [$subId]);
        $foundReg = $rows[0]['regulation'] ?? ($rows[0]['reg'] ?? '');
        return !empty($foundReg) ? strtoupper(trim($foundReg)) : 'R23';
    }

    /**
     * Fetch dynamic target attainment threshold from centralized academic settings.
     */
    public function getTargetAttainmentThreshold($subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationIdForSubject($this->normalizeSubjectIds($subject_id)) : 'R23';
        return (float)\Services\SettingsService::getInstance()->get('co_target_percentage', $reg, 60.0);
    }

    /**
     * Compute Direct CO Attainment from CIA and SEE components dynamically.
     */
    public function getDirectCoAttainment(float $ciaAttainment, float $seeAttainment, $subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationIdForSubject($this->normalizeSubjectIds($subject_id)) : 'R23';
        return \Services\SettingsService::getInstance()->computeDirectCoAttainment($ciaAttainment, $seeAttainment, $reg);
    }

    /**
     * Compute Overall PO/PSO Attainment from Direct and Indirect components dynamically.
     */
    public function getOverallPoAttainment(float $directAttainment, float $indirectAttainment, $subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationIdForSubject($this->normalizeSubjectIds($subject_id)) : 'R23';
        return \Services\SettingsService::getInstance()->computeOverallPoAttainment($directAttainment, $indirectAttainment, $reg);
    }

    /**
     * Calculate Attainment Level (1, 2, 3) from cohort success rate.
     */
    public function getAttainmentLevel(float $cohortPct, $subject_id = null): int
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationIdForSubject($this->normalizeSubjectIds($subject_id)) : 'R23';
        return \Services\SettingsService::getInstance()->calculateAttainmentLevel($cohortPct, $reg);
    }

    /**
     * Fetch Course Outcome (CO) Attainment Analysis
     * Returns CO attainment percentages and suggestions based on threshold.
     */
    public function getCOAttainment($subject_id, $assessment_id = null, $component_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if(strcasecmp((string)$assessment_id,'SEE')===0){
            require_once __DIR__.'/seeassessment.class.php';require_once __DIR__.'/core/AssessmentPolicy.php';$see=new \SEEAssessment();$merged=[];$pending=[];
            foreach($sub_ids as $sid){$this->assertSubjectScope((int)$sid);$result=$see->calculateSEECOAttainment((int)$sid);if(empty($result['status'])){$pending[]=(int)$sid;continue;}
                $cos=$see->getConnection()->query('SELECT id,co_number,co_description FROM course_outcomes WHERE sub_id='.(int)$sid)->fetch_all(MYSQLI_ASSOC);$policy=(new \College\AssessmentPolicy($this->conn))->forSubject((int)$sid)['config'];$rules=array_intersect_key($policy,array_flip(['co_target_percentage','attainment_level_1_threshold','attainment_level_2_threshold','attainment_level_3_threshold']));
                foreach($cos as $co){$value=$result['data'][$co['co_number']]??null;if(!$value){continue;}$key=hash('sha256',json_encode([$co['co_number'],trim($co['co_description']),$rules]));
                    $merged[$key]??=['co_id'=>$co['id'],'co_number'=>$co['co_number'],'co_label'=>'CO'.$co['co_number'],'co_description'=>$co['co_description'],'attained_students_count'=>0,'total_students_count'=>0,'estimated_students'=>0,'measured_students'=>0,'missing_students'=>0,'subject_ids'=>[]];$r=&$merged[$key];$r['attained_students_count']+=$value['attained_students'];$r['total_students_count']+=$value['evaluated_students'];$r['estimated_students']+=$value['estimated_students'];$r['measured_students']+=$value['measured_students'];$r['missing_students']+=$value['missing_students'];$r['subject_ids'][]=(int)$sid;unset($r);
                }
            }
            foreach($merged as &$r){$r['is_assessed']=$r['total_students_count']>0;$r['co_attainment_percentage']=$r['is_assessed']?round($r['attained_students_count']/$r['total_students_count']*100,2):null;$r['evidence_basis']=$r['measured_students']&&$r['estimated_students']?'MIXED':($r['measured_students']?'QUESTION_WISE':($r['estimated_students']?'ESTIMATED':'UNAVAILABLE'));}unset($r);
            return json_encode(['data'=>array_values($merged),'suggestions'=>[],'sub_type'=>$sub_type,'pending_subject_ids'=>$pending,'evidence_note'=>'Question-wise measurements and aggregate estimates are counted separately; missing evidence is unavailable.']);
        }

        if ($sub_type === 'theory') {
            require_once __DIR__ . '/services/SettingsService.php';
            $reg = $this->getRegulationIdForSubject($this->normalizeSubjectIds($subject_id));
            $threshold = $this->getTargetAttainmentThreshold($subject_id);
            $condSubjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_subjective_condensed', $reg, 15.0);
            $condObjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_objective_marks', $reg, 10.0);
            $condAssignmentMax = (float)\Services\SettingsService::getInstance()->get('theory_assignment_marks', $reg, 5.0);

            // Fetch component totals for scaling across assessments
            $compTotalsQuery = "
                SELECT i.assessment_number, ac.component_type, SUM(q.marks) as total_marks
                FROM assessment_questions q
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
                GROUP BY i.assessment_number, ac.component_type
            ";
            $compTotalRows = $this->fetchAssoc($compTotalsQuery, $in['params']);
            $compFullMarks = [];
            foreach ($compTotalRows as $ctr) {
                $aNum = $ctr['assessment_number'];
                $cType = strtolower(trim($ctr['component_type']));
                $compFullMarks[$aNum][$cType] = (float)$ctr['total_marks'];
            }

            $getCompScale = function($aNum, $cType) use ($compFullMarks, $condSubjectiveMax, $condObjectiveMax, $condAssignmentMax) {
                $c = strtolower(trim($cType));
                if ($c === 'subjective') {
                    return 30.0 > 0 ? ($condSubjectiveMax / 30.0) : 1.0;
                } elseif ($c === 'objective') {
                    $fullObj = $compFullMarks[$aNum]['objective'] ?? 0;
                    if ($fullObj <= 0) $fullObj = $condObjectiveMax;
                    return $fullObj > 0 ? ($condObjectiveMax / $fullObj) : 1.0;
                } elseif ($c === 'assignment') {
                    $fullAsgn = $compFullMarks[$aNum]['assignment'] ?? 0;
                    if ($fullAsgn <= 0) $fullAsgn = $condAssignmentMax;
                    return $fullAsgn > 0 ? ($condAssignmentMax / $fullAsgn) : 1.0;
                }
                return 1.0;
            };

            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $chkLocal = $this->fetchAssoc("SELECT id FROM course_outcomes WHERE sub_id IN ({$in['sql']}) LIMIT 1", $in['params']);
            $coFilterSql = !empty($chkLocal)
                ? "c.sub_id IN ({$in['sql']})"
                : "(c.curr_sub_id IN (SELECT curr_sub_id FROM subjects WHERE id IN ({$in['sql']})) AND c.sub_id IS NULL)";
            $where_clauses = [$coFilterSql];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            // Fetch question mapping and student marks
            $qQuery = "
                SELECT
                    c.id as co_id, c.co_number,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM course_outcomes c
                JOIN question_co_mapping qcm ON c.id = qcm.co_id
                JOIN assessment_questions q ON qcm.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY c.co_number, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            // Structure data per CO -> student -> assessment -> main_question_pair
            $coStudents = [];
            $coLabels = [];
            $coDesc = [];

            // Query CO details for consistent response
            $coInfoQuery = "SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id IN ({$in['sql']}) ORDER BY co_number";
            $coInfoRows = $this->fetchAssoc($coInfoQuery, $in['params']);
            if (empty($coInfoRows)) {
                $masterCOQuery = "
                    SELECT co.id, co.co_number, co.co_description 
                    FROM course_outcomes co
                    JOIN subjects s ON s.curr_sub_id = co.curr_sub_id
                    WHERE s.id IN ({$in['sql']}) AND co.sub_id IS NULL
                    ORDER BY co.co_number
                ";
                $coInfoRows = $this->fetchAssoc($masterCOQuery, $in['params']);
            }
            foreach ($coInfoRows as $cr) {
                $coLabels[$cr['id']] = 'CO' . $cr['co_number'];
                $coDesc[$cr['id']] = $cr['co_description'] ?? '';
                $coStudents[$cr['id']] = [];
            }

            foreach ($rawRows as $row) {
                $coId = $row['co_id'];
                $stu_id = $row['stu_id'] ?? 0;
                $aNum = $row['assessment_number'];
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!$stu_id) continue;

                if (!isset($coStudents[$coId][$stu_id])) {
                    $coStudents[$coId][$stu_id] = [
                        'subjective' => [],
                        'other' => []
                    ];
                }

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum])) {
                            $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum]['obtained'] += $obtained;
                        $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        if (!isset($coStudents[$coId][$stu_id]['other'][$aNum][$comp])) {
                            $coStudents[$coId][$stu_id]['other'][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                        }
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['obtained'] += $obtained;
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['max'] += $qMarks;
                    }
                } else {
                    if (!isset($coStudents[$coId][$stu_id]['other'][$aNum][$comp])) {
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                    }
                    $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['obtained'] += $obtained;
                    $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['max'] += $qMarks;
                }
            }

            // Resolve choice questions and scale components per student per CO
            $results = [];

            foreach ($coStudents as $coId => $students) {
                $coNum = (int)str_replace('CO', '', $coLabels[$coId] ?? '0');
                $totalStudents = count($students);
                $attainedStudents = 0;
                $totalCohortMarks = 0;
                $totalCohortMax = 0;

                foreach ($students as $stu_id => $sData) {
                    $stuObtained = 0;
                    $stuMax = 0;

                    // Process subjective pairs: (1,2), (3,4), (5,6)
                    if (!empty($sData['subjective'])) {
                        foreach ($sData['subjective'] as $aNum => $mainQuestions) {
                            $scale = $getCompScale($aNum, 'subjective');
                            $pairKeys = [];
                            foreach (array_keys($mainQuestions) as $mNum) {
                                $pairKeys[(int)ceil($mNum / 2)] = true;
                            }
                            foreach (array_keys($pairKeys) as $pairKey) {
                                $qOdd = $pairKey * 2 - 1;
                                $qEven = $pairKey * 2;
                                $oddObt = $mainQuestions[$qOdd]['obtained'] ?? 0;
                                $oddMax = $mainQuestions[$qOdd]['max'] ?? 0;
                                $evenObt = $mainQuestions[$qEven]['obtained'] ?? 0;
                                $evenMax = $mainQuestions[$qEven]['max'] ?? 0;

                                $pairObtained = max($oddObt, $evenObt);
                                $pairMax = max($oddMax, $evenMax);

                                $stuObtained += ($pairObtained * $scale);
                                $stuMax += ($pairMax * $scale);
                            }
                        }
                    }

                    // Process other components (objective, assignment, etc.)
                    if (!empty($sData['other'])) {
                        foreach ($sData['other'] as $aNum => $comps) {
                            foreach ($comps as $cType => $cMarks) {
                                $scale = $getCompScale($aNum, $cType);
                                $stuObtained += ($cMarks['obtained'] * $scale);
                                $stuMax += ($cMarks['max'] * $scale);
                            }
                        }
                    }

                    $totalCohortMarks += $stuObtained;
                    $totalCohortMax += $stuMax;

                    if ($stuMax > 0 && ($stuObtained / $stuMax * 100) >= $threshold) {
                        $attainedStudents++;
                    }
                }

                $isAssessed = ($totalStudents > 0 && $totalCohortMax > 0);
                $cohortPercentage = $isAssessed ? round(($totalCohortMarks / $totalCohortMax) * 100, 2) : null;

                $results[] = [
                    'co_id' => $coId,
                    'co_number' => $coNum,
                    'co_label' => $coLabels[$coId] ?? "CO$coNum",
                    'co_description' => $coDesc[$coId] ?? '',
                    'co_attainment_percentage' => $cohortPercentage,
                    'attained_students_count' => $attainedStudents,
                    'total_students_count' => $totalStudents,
                    'is_assessed' => $isAssessed
                ];
            }
        } else {
            // Standard proportional calculation for Lab, DTI, etc.
            $threshold = $this->getTargetAttainmentThreshold($subject_id);
            $chkLocalNonTheory = $this->fetchAssoc("SELECT id FROM course_outcomes WHERE sub_id IN ({$in['sql']}) LIMIT 1", $in['params']);
            $coFilterNonTheory = !empty($chkLocalNonTheory)
                ? "c.sub_id IN ({$in['sql']})"
                : "(c.curr_sub_id IN (SELECT curr_sub_id FROM subjects WHERE id IN ({$in['sql']})) AND c.sub_id IS NULL)";

            $query = "
                SELECT
                    c.id as co_id,
                    c.co_number,
                    CONCAT('CO', c.co_number) as co_label,
                    c.co_description,
                    ROUND((SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN m.marks_obtained ELSE 0 END) /
                             NULLIF(SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN q.marks ELSE 0 END), 0)) * 100, 2) AS co_attainment_percentage,
                    COUNT(DISTINCT CASE WHEN (m.marks_obtained/q.marks)*100 >= {$threshold} THEN m.stu_id END) as attained_students_count,
                    COUNT(DISTINCT m.stu_id) as total_students_count
                FROM course_outcomes c
                LEFT JOIN question_co_mapping qcm ON c.id = qcm.co_id
                LEFT JOIN assessment_questions q ON qcm.question_id = q.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                LEFT JOIN assessment_components ac ON q.component_id = ac.id
                LEFT JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE {$coFilterNonTheory}
            ";

            $params = array_merge($in['params'], $in['params'], $in['params']);
            $where_clauses = [];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }

            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }

            if (!empty($where_clauses)) {
                $query .= " AND " . implode(" AND ", $where_clauses);
            }

            $query .= " GROUP BY c.id, c.co_number ORDER BY c.co_number";
            $results = $this->fetchAssoc($query, $params);

            foreach ($results as &$r) {
                $isAssessed = (!empty($r['total_students_count']) && $r['total_students_count'] > 0 && $r['co_attainment_percentage'] !== null);
                $r['is_assessed'] = $isAssessed;
                if (!$isAssessed) {
                    $r['co_attainment_percentage'] = null;
                }
            }
            unset($r);
        }

        // Mode B / Mode C fallback for SEE: if no question-level marks exist, use consolidated external_assessment_marks
        $hasAnyAssessed = false;
        foreach ($results as $chkR) {
            if (!empty($chkR['is_assessed'])) {
                $hasAnyAssessed = true;
                break;
            }
        }
        if (!$hasAnyAssessed && strcasecmp((string)$assessment_id, 'see') === 0) {
            require_once __DIR__ . '/seeassessment.class.php';
            $seeObj = new \SEEAssessment();
            $seeRes = $seeObj->calculateSEECOAttainment($subject_id);
            if (!empty($seeRes['status']) && !empty($seeRes['data'])) {
                $newResults = [];
                $coListQuery = "SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id IN ({$in['sql']}) ORDER BY co_number";
                $coList = $this->fetchAssoc($coListQuery, $in['params']);
                if (empty($coList)) {
                    $coListQuery = "SELECT co.id, co.co_number, co.co_description FROM curriculum_course_outcomes co JOIN subjects s ON s.curr_sub_id = co.curr_sub_id WHERE s.id IN ({$in['sql']}) ORDER BY co.co_number";
                    $coList = $this->fetchAssoc($coListQuery, $in['params']);
                }
                foreach ($coList as $coItem) {
                    $coNum = (int)$coItem['co_number'];
                    $coData = $seeRes['data'][$coNum] ?? null;
                    if ($coData) {
                        $cohortPct = $coData['cohort_percentage']!==null?(float)$coData['cohort_percentage']:null;
                        $newResults[] = [
                            'co_id' => $coItem['id'],
                            'co_number' => $coNum,
                            'co_label' => 'CO' . $coNum,
                            'co_description' => $coItem['co_description'] ?? '',
                            'co_attainment_percentage' => $cohortPct,
                            'attained_students_count' => (int)$coData['attained_students'],
                            'total_students_count' => (int)($coData['evaluated_students']??$coData['total_students']),
                            'evidence_basis' => $coData['basis']??'LEGACY',
                            'estimated_students' => $coData['estimated_students']??0,
                            'missing_students' => $coData['missing_students']??0,
                            'is_assessed' => $cohortPct!==null
                        ];
                    } else {
                        $newResults[] = [
                            'co_id' => $coItem['id'],
                            'co_number' => $coNum,
                            'co_label' => 'CO' . $coNum,
                            'co_description' => $coItem['co_description'] ?? '',
                            'co_attainment_percentage' => null,
                            'attained_students_count' => 0,
                            'total_students_count' => 0,
                            'is_assessed' => false
                        ];
                    }
                }
                if (!empty($newResults)) {
                    $results = $newResults;
                }
            }
        }

        // Suggestions based on dynamic threshold from centralized settings
        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['is_assessed'] && $row['co_attainment_percentage'] !== null && $row['co_attainment_percentage'] < $targetThreshold) {
                $suggestions[] = "Attainment for {$row['co_label']} ({$row['co_attainment_percentage']}%) is below the target ({$targetThreshold}%). Consider remedial classes, tutorial sessions, or adjusting teaching strategies for topics related to this CO. Description: '{$row['co_description']}'.";
            }
        }

        if (empty($results)) {
            $suggestions[] = "No CO attainment data found. Ensure questions are mapped to COs and student marks are entered.";
        } elseif (empty($suggestions)) {
            $suggestions[] = "All assessed COs meet or exceed the target attainment threshold of {$targetThreshold}%. Good performance!";
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch Program Outcome (PO) Attainment Analysis
     */
    public function getPOAttainment($subject_id, $assessment_id = null)
    {
        $co_attainment_data_json = $this->getCOAttainment($subject_id, $assessment_id);
        $co_attainment_data = json_decode($co_attainment_data_json, true);

        if (empty($co_attainment_data['data'])) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO attainment data available."]]);
        }

        $co_attainment_map = [];
        $co_num_attainment_map = [];
        foreach ($co_attainment_data['data'] as $co_data) {
            if (!empty($co_data['is_assessed']) && $co_data['co_attainment_percentage'] !== null) {
                $co_attainment_map[$co_data['co_id']] = (float)$co_data['co_attainment_percentage'];
                $co_num_attainment_map[$co_data['co_number']] = (float)$co_data['co_attainment_percentage'];
            }
        }

        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $query_mapping = "
            SELECT
                p.id as po_id, p.code as po_label, p.description as po_description,
                co.co_number, cpm.co_id, cpm.weightage
            FROM co_po_mapping cpm
            JOIN course_outcomes co ON cpm.co_id = co.id
            JOIN po_pso p ON cpm.po_id = p.id
            JOIN subjects s ON (co.sub_id = s.id OR (co.curr_sub_id = s.curr_sub_id AND co.sub_id IS NULL))
            JOIN classes cl ON s.class_id = cl.id
            WHERE s.id IN ({$in['sql']})
            GROUP BY p.id, co.co_number
            ORDER BY p.orderid, p.code, co.co_number
        ";

        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        if (empty($mappings)) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO-PO mapping found for this subject."]]);
        }

        $po_attainment = [];
        $po_details = [];

        foreach ($mappings as $map) {
            $po_code = $map['po_label'];
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $weightage = (float)($map['weightage'] ?? 1.0);

            if (!isset($po_details[$po_code])) {
                $po_details[$po_code] = ['label' => $po_code, 'description' => $map['po_description']];
                $po_attainment[$po_code] = [
                    'total_weighted_attainment' => 0,
                    'total_weightage' => 0,
                    'cos_seen' => []
                ];
            }

            if (isset($po_attainment[$po_code]['cos_seen'][$co_id])) {
                continue; // Avoid duplicating CO contribution across multiple po_id entries with same code
            }
            $po_attainment[$po_code]['cos_seen'][$co_id] = true;

            if (isset($co_attainment_map[$co_id])) {
                $co_attainment_percentage = $co_attainment_map[$co_id];
                $po_attainment[$po_code]['total_weighted_attainment'] += ($co_attainment_percentage * $weightage);
                $po_attainment[$po_code]['total_weightage'] += $weightage;
            } elseif (isset($co_num_attainment_map[$co_num])) {
                $co_attainment_percentage = $co_num_attainment_map[$co_num];
                $po_attainment[$po_code]['total_weighted_attainment'] += ($co_attainment_percentage * $weightage);
                $po_attainment[$po_code]['total_weightage'] += $weightage;
            }
        }

        $po_results = [];
        $suggestions = [];
        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);

        foreach ($po_attainment as $po_code => $data) {
            $coCount = count($data['cos_seen']);
            $isAssessed = ($data['total_weightage'] > 0 && $coCount > 0);
            $final_attainment = $isAssessed
                ? round($data['total_weighted_attainment'] / $data['total_weightage'], 2)
                : null;

            $po_results[] = [
                'po_label' => $po_details[$po_code]['label'],
                'po_description' => $po_details[$po_code]['description'],
                'po_attainment_percentage' => $final_attainment,
                'contributing_co_count' => $coCount,
                'is_assessed' => $isAssessed
            ];

            if ($isAssessed && $final_attainment !== null && $final_attainment < $targetThreshold) {
                $suggestions[] = "Attainment for {$po_details[$po_code]['label']} ({$final_attainment}%) is below the target ({$targetThreshold}%). Review the attainment of contributing COs for insights. PO Description: '{$po_details[$po_code]['description']}'.";
            }
        }

        if (empty($po_results)) {
            $suggestions[] = "PO attainment calculated, but contributing COs lack assessment data.";
        } elseif (empty($suggestions)) {
            $suggestions[] = "PO attainment analysis complete. All assessed POs meet or exceed target threshold.";
        }

        return json_encode(['data' => $po_results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch data for CO-PO Matrix/Table
     */
    public function getCoPoMatrixData($subject_id, $assessment_id = null)
    {
        $co_attainment_json = $this->getCOAttainment($subject_id, $assessment_id);
        $co_attainment_data = json_decode($co_attainment_json, true);
        $co_map = [];
        $co_num_map = [];
        if (!empty($co_attainment_data['data'])) {
            foreach ($co_attainment_data['data'] as $co) {
                $co_map[$co['co_id']] = $co['co_attainment_percentage'];
                $co_num_map[$co['co_number']] = $co['co_attainment_percentage'];
            }
        } else {
            return json_encode(['data' => [], 'suggestions' => ['No CO attainment data available to build CO-PO Matrix.']]);
        }

        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $query_mapping = "
            SELECT
                co.id as co_id, co.co_number, CONCAT('CO', co.co_number) as co_label,
                p.id as po_id, p.code as po_label, cpm.weightage
            FROM course_outcomes co
            LEFT JOIN co_po_mapping cpm ON co.id = cpm.co_id
            LEFT JOIN po_pso p ON cpm.po_id = p.id
            JOIN subjects s ON (co.sub_id = s.id OR (co.curr_sub_id = s.curr_sub_id AND co.sub_id IS NULL))
            JOIN classes cl ON s.class_id = cl.id
            WHERE s.id IN ({$in['sql']})
            GROUP BY co.co_number, p.id
            ORDER BY co.co_number, p.orderid, p.code
        ";
        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        $matrix_data = [];
        foreach ($mappings as $map) {
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $attainment = $co_map[$co_id] ?? ($co_num_map[$co_num] ?? 'N/A');
            if ($attainment === null) {
                $attainment = 'N/A';
            }
            $matrix_data[] = [
                'co_label' => $map['co_label'],
                'co_attainment' => $attainment,
                'po_label' => $map['po_label'] ?? 'Not Mapped',
                'weightage' => $map['weightage'] ?? '-'
            ];
        }

        $suggestions = [];
        if (empty($matrix_data)) {
            $suggestions[] = "No CO-PO mapping data found for this subject.";
        } else {
            $suggestions[] = "CO-PO Matrix data loaded. Shows CO attainment and linked POs. 'N/A' indicates CO attainment data is missing for that CO.";
        }

        return json_encode(['data' => $matrix_data, 'suggestions' => $suggestions]);
    }

    public function getCOVisualSummary($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            $coAttDataJson = $this->getCOAttainment($subject_id, $assessment_id);
            $coAttData = json_decode($coAttDataJson, true);
            $results = [];

            $stuCountQuery = "
                SELECT COUNT(DISTINCT m.stu_id) AS student_count
                FROM student_marks m
                JOIN assessment_questions q ON m.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
            ";
            $stuParams = $in['params'];
            if ($assessment_id && $assessment_id !== 'all') {
                $stuCountQuery .= " AND i.assessment_number = ?";
                $stuParams[] = $assessment_id;
            } else {
                $stuCountQuery .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            $stuRes = $this->fetchAssoc($stuCountQuery, $stuParams);
            $stuCount = $stuRes[0]['student_count'] ?? 0;

            if (!empty($coAttData['data'])) {
                foreach ($coAttData['data'] as $co) {
                    $results[] = [
                        'co_label' => $co['co_label'],
                        'avg_attainment' => $co['co_attainment_percentage'],
                        'student_count' => $co['total_students_count'] ?? $stuCount,
                        'is_assessed' => $co['is_assessed'] ?? ($co['co_attainment_percentage'] !== null)
                    ];
                }
            }
        } else {
            $chkLocalVisual = $this->fetchAssoc("SELECT id FROM course_outcomes WHERE sub_id IN ({$in['sql']}) LIMIT 1", $in['params']);
            $coFilterVisual = !empty($chkLocalVisual)
                ? "c.sub_id IN ({$in['sql']})"
                : "(c.curr_sub_id IN (SELECT curr_sub_id FROM subjects WHERE id IN ({$in['sql']})) AND c.sub_id IS NULL)";

            $query = "
                SELECT
                    CONCAT('CO', c.co_number) AS co_label,
                    ROUND(AVG((m.marks_obtained/q.marks)*100), 2) AS avg_attainment,
                    COUNT(DISTINCT m.stu_id) AS student_count
                FROM course_outcomes c
                JOIN question_co_mapping qcm ON c.id = qcm.co_id
                JOIN assessment_questions q ON qcm.question_id = q.id
                JOIN student_marks m ON q.id = m.question_id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE {$coFilterVisual}
            ";
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $query .= " AND i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $query .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }

            $query .= " GROUP BY c.co_number ORDER BY c.co_number";
            $results = $this->fetchAssoc($query, $params);

            foreach ($results as &$r) {
                $isAssessed = (!empty($r['student_count']) && $r['student_count'] > 0 && $r['avg_attainment'] !== null);
                $r['is_assessed'] = $isAssessed;
                if (!$isAssessed) {
                    $r['avg_attainment'] = null;
                }
            }
            unset($r);
        }

        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['is_assessed'] && $row['avg_attainment'] !== null && $row['avg_attainment'] < $targetThreshold) {
                $suggestions[] = "CO {$row['co_label']} average ({$row['avg_attainment']}%) is below target (" . $targetThreshold . "%). Consider remedial support.";
            }
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Compute Comprehensive Course Outcome & Program Outcome Attainment
     * Combines Continuous Internal Assessment (CIA), Semester End Examination (SEE),
     * and Indirect Student Feedback into final OBE/NBA direct and overall attainment.
     */
    public function getComprehensiveAttainment($subject_id)
    {
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $primarySubId = $sub_ids[0];
        $in = $this->buildInClause($sub_ids);

        require_once __DIR__ . '/services/SettingsService.php';
        require_once __DIR__ . '/seeassessment.class.php';
        require_once __DIR__ . '/feedbackservice.class.php';
        require_once __DIR__ . '/courseoutcome.class.php';

        $regCode = $this->getRegulationForSubject($primarySubId);
        $reg = $this->getRegulationIdForSubject((int)$primarySubId);
        $ss = \Services\SettingsService::getInstance();

        $w_cia = (float)$ss->get('attainment_direct_cia_weight', $reg, 0.30);
        $w_see = (float)$ss->get('attainment_direct_see_weight', $reg, 0.70);
        $w_direct = (float)$ss->get('overall_direct_weight', $reg, 0.80);
        $w_indirect = (float)$ss->get('overall_indirect_weight', $reg, 0.20);
        $targetThreshold = $this->getTargetAttainmentThreshold($primarySubId);

        $seeObj = new SEEAssessment();
        $coObj = new CourseOutcome();
        $fs = new FeedbackService();

        // 1. Fetch CIA Attainment
        $ciaAtt = $seeObj->calculateCIACOAttainment($primarySubId);

        // 2. Fetch SEE Attainment
        $seeAtt = $seeObj->calculateSEECOAttainment($primarySubId);
        $isSeeSubmitted = (!empty($seeAtt['status']) && empty($seeAtt['pending']));

        // 3. Fetch Indirect Feedback
        $fbRes = $fs->getSubjectFeedback($primarySubId);
        $fbMap = [];
        if (!empty($fbRes['co_feedback'])) {
            foreach ($fbRes['co_feedback'] as $fb) {
                if (!empty($fb['total_responses']) && $fb['total_responses'] > 0) {
                    $fbMap[$fb['co_number']] = $fb;
                }
            }
        }

        // 4. Fetch Course Outcomes
        $cosRes = $coObj->getCOsBySubjectId($primarySubId);
        $cos = !empty($cosRes['data']) ? $cosRes['data'] : [];

        if (empty($cos)) {
            return json_encode([
                'meta' => [
                    'regulation' => $regCode,
                'regulation_id' => (int)$reg,
                    'target_threshold' => $targetThreshold,
                    'w_cia' => $w_cia,
                    'w_see' => $w_see,
                    'w_direct' => $w_direct,
                    'w_indirect' => $w_indirect,
                    'is_see_submitted' => $isSeeSubmitted
                ],
                'cos' => [],
                'pos' => [],
                'suggestions' => ["No Course Outcomes (COs) configured for this course. Please configure Course Outcomes to calculate direct and indirect attainment."]
            ]);
        }

        $coResults = [];
        $coDirectMap = [];
        $coIndirectMap = [];
        $coOverallMap = [];
        $suggestions = [];

        $hasAnyCia = false;
        $hasAnySee = $isSeeSubmitted;
        $hasAnyFeedback = !empty($fbMap);

        foreach ($cos as $co) {
            $n = $co['co_number'];
            $cRow = $ciaAtt['data'][$n] ?? [];
            $sRow = $seeAtt['data'][$n] ?? [];
            $fRow = $fbMap[$n] ?? [];

            $cPct = isset($cRow['cohort_percentage']) ? floatval($cRow['cohort_percentage']) : null;
            $cLvl = isset($cRow['attainment_level']) ? intval($cRow['attainment_level']) : null;
            if ($cPct !== null && empty($cRow['unmapped'])) {
                $hasAnyCia = true;
            }

            $sPct = ($isSeeSubmitted && isset($sRow['cohort_percentage'])) ? floatval($sRow['cohort_percentage']) : null;
            $sLvl = ($isSeeSubmitted && isset($sRow['attainment_level'])) ? intval($sRow['attainment_level']) : null;

            // Direct Attainment: CIA + SEE
            if ($cLvl !== null && $sLvl !== null) {
                $directLvl = round(($w_cia * $cLvl) + ($w_see * $sLvl), 2);
            } elseif ($cLvl !== null) {
                // If SEE is pending or missing, direct attainment uses CIA
                $directLvl = round($cLvl, 2);
            } elseif ($sLvl !== null) {
                // If only SEE is present
                $directLvl = round($sLvl, 2);
            } else {
                $directLvl = null;
            }

            $fAvg = isset($fRow['average_rating']) ? round(floatval($fRow['average_rating']), 2) : null;
            $fPct = isset($fRow['target_pct']) ? floatval($fRow['target_pct']) : null;
            $fLvl = isset($fRow['attainment_level']) ? intval($fRow['attainment_level']) : null;

            // Overall Attainment: Direct + Indirect
            if ($fLvl !== null && ($cLvl !== null || $sLvl !== null)) {
                $overallLvl = round(($w_direct * $directLvl) + ($w_indirect * $fLvl), 2);
            } elseif ($fLvl !== null) {
                // Only feedback available
                $overallLvl = round($fLvl, 2);
            } else {
                // No feedback available, direct represents overall
                $overallLvl = $directLvl;
            }

            $statusText = $overallLvl===null?'Not available':'Needs Improvement';
            $statusBadge = 'bg-danger';
            if ($overallLvl >= 2.5) {
                $statusText = 'High Attainment (Level 3)';
                $statusBadge = 'bg-success';
            } elseif ($overallLvl >= 1.75) {
                $statusText = 'Moderate Attainment (Level 2)';
                $statusBadge = 'bg-primary';
            } elseif ($overallLvl >= 1.0) {
                $statusText = 'Marginal Attainment (Level 1)';
                $statusBadge = 'bg-warning text-dark';
            }

            if ($overallLvl < 1.75 && ($cLvl !== null || $sLvl !== null || $fLvl !== null)) {
                $suggestions[] = "CO$n overall attainment ($overallLvl) is below Level 2. Pedagogical interventions or remedial sessions are recommended.";
            }

            $coItem = [
                'co_id' => $co['id'],
                'co_number' => $n,
                'co_label' => 'CO' . $n,
                'co_description' => $co['co_description'] ?? '',
                'cia_pct' => $cPct,
                'cia_level' => $cLvl,
                'see_pct' => $sPct,
                'see_level' => $sLvl,
                'see_evidence_basis' => $sRow['basis']??'UNAVAILABLE',
                'see_estimated_students' => $sRow['estimated_students']??0,
                'see_missing_students' => $sRow['missing_students']??0,
                'is_provisional' => ($w_cia>0&&$cLvl===null)||($w_see>0&&$sLvl===null)||!empty($sRow['provisional']),
                'is_see_submitted' => $isSeeSubmitted,
                'direct_attainment' => $directLvl,
                'indirect_avg_rating' => $fAvg,
                'indirect_pct' => $fPct,
                'indirect_level' => $fLvl,
                'overall_attainment' => $overallLvl,
                'status_text' => $statusText,
                'status_badge' => $statusBadge
            ];

            $coResults[] = $coItem;
            $coDirectMap[$co['id']] = $directLvl;
            $coIndirectMap[$co['id']] = $fLvl;
            $coOverallMap[$co['id']] = $overallLvl;
        }

        // Contextual suggestions based on data availability
        if (!$hasAnyCia && !$hasAnySee && !$hasAnyFeedback) {
            $suggestions[] = "No assessment or feedback data recorded yet for this course. Please enter CIA marks, SEE results, or collect student feedback.";
        } elseif (!$hasAnySee) {
            $suggestions[] = "Semester End Examination (SEE) results are pending. Direct and overall attainment are temporarily based on Internal Assessment and available feedback.";
        }
        if (!$hasAnyFeedback) {
            $suggestions[] = "Indirect student feedback has not been recorded yet. Overall attainment is currently derived solely from direct evaluations.";
        }

        // 5. Fetch PO / PSO mappings and compute weighted PO/PSO Attainment
        // Deduplicate mappings per (co_id, po_code) across regulations/specializations
        $co_ids = array_column($cos, 'id');
        $poResults = [];
        if (!empty($co_ids)) {
            $inCo = $this->buildInClause($co_ids);
            $poRows = $this->fetchAssoc("
                SELECT m.co_id, p.code, MAX(p.description) as description, AVG(m.weightage) as weightage 
                FROM co_po_mapping m 
                JOIN po_pso p ON m.po_id = p.id 
                WHERE m.co_id IN ({$inCo['sql']}) 
                GROUP BY m.co_id, p.code
                ORDER BY p.code ASC
            ", $inCo['params']);

            $poMap = [];
            foreach ($poRows as $pr) {
                $code = strtoupper(trim($pr['code']));
                if (!isset($poMap[$code])) {
                    $poMap[$code] = [
                        'code' => $code,
                        'description' => $pr['description'] ?? '',
                        'total_weightage' => 0.0,
                        'weight_direct' => 0.0,'weight_indirect' => 0.0,'weight_overall' => 0.0,
                        'sum_direct' => 0.0,
                        'sum_indirect' => 0.0,
                        'sum_overall' => 0.0,
                        'contributing_cos' => []
                    ];
                }
                $coId = (int)$pr['co_id'];
                $wt = floatval($pr['weightage']);
                $poMap[$code]['total_weightage'] += $wt;
                foreach(['direct'=>$coDirectMap,'indirect'=>$coIndirectMap,'overall'=>$coOverallMap] as $kind=>$values){if(isset($values[$coId])){$poMap[$code]['sum_'.$kind]+=$values[$coId]*$wt;$poMap[$code]['weight_'.$kind]+=$wt;}}
                $poMap[$code]['contributing_cos'][] = $coId;
            }

            foreach ($poMap as $code => $pd) {
                $dirLvl = ($pd['weight_direct'] > 0) ? round($pd['sum_direct'] / $pd['weight_direct'], 2) : null;
                $indLvl = ($pd['weight_indirect'] > 0) ? round($pd['sum_indirect'] / $pd['weight_indirect'], 2) : null;
                $ovrLvl = ($pd['weight_overall'] > 0) ? round($pd['sum_overall'] / $pd['weight_overall'], 2) : null;

                $poResults[] = [
                    'po_label' => $code,
                    'po_description' => $pd['description'],
                    'total_weightage' => round($pd['total_weightage'], 2),
                    'direct_attainment' => $dirLvl,
                    'indirect_attainment' => $indLvl,
                    'overall_attainment' => $ovrLvl,
                    'contributing_co_count' => count(array_unique($pd['contributing_cos']))
                ];
            }
        }

        if (empty($poResults)) {
            $suggestions[] = "No CO-PO / PSO mapping found for this course. Please configure the CO-PO matrix to evaluate program outcomes.";
        }

        if (empty($suggestions)) {
            $suggestions[] = "Comprehensive attainment calculation complete. All assessed course outcomes meet or exceed target benchmarks.";
        }

        return json_encode([
            'meta' => [
                'regulation' => $regCode,
                'regulation_id' => (int)$reg,
                'target_threshold' => $targetThreshold,
                'w_cia' => $w_cia,
                'w_see' => $w_see,
                'w_direct' => $w_direct,
                'w_indirect' => $w_indirect,
                'is_see_submitted' => $isSeeSubmitted
            ],
            'cos' => $coResults,
            'pos' => $poResults,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Helper function to execute query and fetch associative array
     */
    public function fetchAssoc($query, $params = [])
    {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($query);

            if ($stmt === false) {
                return [];
            }

            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result) {
                $stmt->close();
                return [];
            }

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            $stmt->close();
            return $data;
        } catch (\Throwable $e) {
            if ($this->logs) {
                $this->logs->errLog("fetchAssoc Exception: " . $e->getMessage() . " in query: " . substr($query, 0, 150));
            }
            return [];
        }
    }
}

class COAttainmentEngine extends DBCredentials
{
    use COAttainmentTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

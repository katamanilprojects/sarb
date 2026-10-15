<?php
// learninganalytics.class.php - Modular Pedagogical & Student Learning Analytics
require_once("dbcredentials.class.php");
require_once("logs.class.php");
require_once("coattainment.class.php");

trait LearningAnalyticsTrait
{
    /**
     * Fetch Bloom's Taxonomy Performance Analysis
     */
    public function getBloomsPerformance($subject_id, $assessment_id = null, $component_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            require_once __DIR__ . '/services/SettingsService.php';
            $reg = $this->getRegulationIdForSubject((int)$subject_id);
            $condSubjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_subjective_condensed', $reg, 15.0);
            $condObjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_objective_marks', $reg, 10.0);
            $condAssignmentMax = (float)\Services\SettingsService::getInstance()->get('theory_assignment_marks', $reg, 5.0);

            // Fetch component totals for scaling
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
            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
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

            $qQuery = "
                SELECT
                    b.id as blooms_id, b.blooms_level, b.blooms_label,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM blooms_levels b
                JOIN assessment_questions q ON q.blooms_level_id = b.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY b.id, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $bloomsSummary = [];
            $studentBloomsSubMarks = [];
            $studentBloomsOtherMarks = [];

            // Query blooms levels to preserve all labels
            $bloomsListQuery = "SELECT id, blooms_level, blooms_label FROM blooms_levels ORDER BY id";
            $bloomsListRows = $this->fetchAssoc($bloomsListQuery, []);
            foreach ($bloomsListRows as $blr) {
                $bloomsSummary[$blr['id']] = [
                    'blooms_level' => $blr['blooms_level'],
                    'blooms_label' => $blr['blooms_label'],
                    'total_obtained' => 0,
                    'total_max' => 0
                ];
            }

            foreach ($rawRows as $row) {
                $bId = $row['blooms_id'];
                $bLevel = $row['blooms_level'];
                $bLabel = $row['blooms_label'];
                $aNum = $row['assessment_number'];
                $stu_id = $row['stu_id'] ?? 0;
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!$stu_id) continue;

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum])) {
                            $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum]['obtained'] += $obtained;
                        $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        if (!isset($studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp])) {
                            $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp]['obtained'] += $obtained;
                        $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp]['max'] += $qMarks;
                    }
                } else {
                    if (!isset($studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp])) {
                        $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                    }
                    $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp]['obtained'] += $obtained;
                    $studentBloomsOtherMarks[$bId][$stu_id][$aNum][$comp]['max'] += $qMarks;
                }
            }

            foreach ($studentBloomsSubMarks as $bId => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $mainQuestions) {
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

                            $bloomsSummary[$bId]['total_obtained'] += ($pairObtained * $scale);
                            $bloomsSummary[$bId]['total_max'] += ($pairMax * $scale);
                        }
                    }
                }
            }

            foreach ($studentBloomsOtherMarks as $bId => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $comps) {
                        foreach ($comps as $cType => $cMarks) {
                            $scale = $getCompScale($aNum, $cType);
                            $bloomsSummary[$bId]['total_obtained'] += ($cMarks['obtained'] * $scale);
                            $bloomsSummary[$bId]['total_max'] += ($cMarks['max'] * $scale);
                        }
                    }
                }
            }

            $results = [];
            foreach ($bloomsSummary as $bId => $info) {
                $isAssessed = ($info['total_max'] > 0);
                $percentage = $isAssessed ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : null;
                $results[] = [
                    'blooms_level' => $info['blooms_level'],
                    'blooms_label' => $info['blooms_label'],
                    'attainment_percentage' => $percentage,
                    'is_assessed' => $isAssessed
                ];
            }
        } else {
            $query = "
                SELECT
                    b.blooms_level, b.blooms_label,
                    ROUND((SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN m.marks_obtained ELSE 0 END) /
                             NULLIF(SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN q.marks ELSE 0 END), 0)) * 100, 2) AS attainment_percentage
                FROM blooms_levels b
                LEFT JOIN assessment_questions q ON q.blooms_level_id = b.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                LEFT JOIN assessment_components ac ON q.component_id = ac.id
                LEFT JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
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

            $query .= ' GROUP BY b.id, b.blooms_level ORDER BY b.id';
            $results = $this->fetchAssoc($query, $params);

            foreach ($results as &$r) {
                $isAssessed = ($r['attainment_percentage'] !== null);
                $r['is_assessed'] = $isAssessed;
            }
            unset($r);
        }

        $suggestions = [];
        $higher_order_low = false;
        $lower_order_low = false;
        $threshold = $this->getTargetAttainmentThreshold($subject_id);

        foreach ($results as $row) {
            if (!empty($row['is_assessed']) && $row['attainment_percentage'] !== null && $row['attainment_percentage'] < $threshold) {
                $suggestions[] = "Performance in Bloom's level '{$row['blooms_label']}' ({$row['attainment_percentage']}%) is below target ($threshold%). Focus on activities strengthening skills at this cognitive level.";
                if (in_array($row['blooms_level'], ['Analyze', 'Evaluate', 'Create'])) {
                    $higher_order_low = true;
                }
                if (in_array($row['blooms_level'], ['Remember', 'Understand'])) {
                    $lower_order_low = true;
                }
            }
        }

        if ($higher_order_low) {
            $suggestions[] = "Consider incorporating more activities that challenge higher-order thinking skills (Analysis, Evaluation, Creation).";
        }
        if ($lower_order_low) {
            $suggestions[] = "Low performance in foundational levels (Remembering, Understanding) might impact higher-level skills. Ensure fundamental concepts are clear.";
        }
        if (empty($results)) {
            $suggestions[] = "No Bloom's Taxonomy performance data found. Ensure questions are mapped to Bloom's levels and marks are entered.";
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch Assessment Performance Analysis
     */
    public function getAssessmentPerformance($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $isLab = ($sub_type === 'lab' || $sub_type === 'dti');

        $labelExpr = $isLab ? "'Lab CIA'" : "CONCAT('CIA - ', i.assessment_number)";

        $compFilter = "";
        if ($isLab) {
            $compFilter = " AND ac.component_type = 'Internal Exam'";
        }

        if ($sub_type === 'theory') {
            require_once __DIR__ . '/services/SettingsService.php';
            $reg = $this->getRegulationIdForSubject((int)$subject_id);
            $condSubjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_subjective_condensed', $reg, 15.0);
            $condObjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_objective_marks', $reg, 10.0);
            $condAssignmentMax = (float)\Services\SettingsService::getInstance()->get('theory_assignment_marks', $reg, 5.0);

            // Fetch component totals for scaling
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

            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            $isSEE = (strcasecmp((string)$assessment_id, 'SEE') === 0);
            if ($isSEE) {
                // If consolidated SEE marks exist in external_assessment_marks, use official totals
                $extSql = "SELECT AVG(external_marks/NULLIF(max_marks,0))*100 as avg_obt, 100 as avg_max, COUNT(*) as cnt 
                           FROM external_assessment_marks WHERE is_current=1 AND subject_id IN ({$in['sql']})";
                $extRows = $this->fetchAssoc($extSql, $in['params']);
                if (!empty($extRows) && $extRows[0]['cnt'] > 0 && $extRows[0]['avg_max'] > 0) {
                    $seePct = round(($extRows[0]['avg_obt'] / $extRows[0]['avg_max']) * 100, 2);
                    $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
                    $suggestions = [];
                    if ($seePct >= $targetThreshold) {
                        $suggestions[] = "Semester End Examination (SEE) average score meets or exceeds the target benchmark ({$targetThreshold}%).";
                    } else {
                        $suggestions[] = "Semester End Examination (SEE) average score ({$seePct}%) is below the target benchmark ({$targetThreshold}%). Remedial action recommended.";
                    }
                    return json_encode([
                        'data' => [[
                            'assessment_number' => 'SEE',
                            'assessment_label' => 'Semester End Exam (SEE)',
                            'attainment_percentage' => $seePct
                        ]],
                        'suggestions' => $suggestions,
                        'sub_type' => $sub_type
                    ]);
                }
                $where_clauses[] = "i.assessment_number = 'SEE'";
            } elseif ($assessment_id !== null && $assessment_id !== 'all' && filter_var($assessment_id, FILTER_VALIDATE_INT) !== false) {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = (int)$assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            $where_sql = implode(" AND ", $where_clauses);

            $qQuery = "
                SELECT
                    i.assessment_number,
                    CONCAT('CIA - ', i.assessment_number) AS assessment_label,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM assessment_questions q
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY i.assessment_number, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $assessStudents = [];
            $assessLabels = [];

            foreach ($rawRows as $row) {
                $aNum = $row['assessment_number'];
                $assessLabels[$aNum] = $row['assessment_label'];
                $stu_id = $row['stu_id'] ?? 0;
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!$stu_id) continue;

                if (!isset($assessStudents[$aNum][$stu_id])) {
                    $assessStudents[$aNum][$stu_id] = [
                        'subjective' => [],
                        'other' => []
                    ];
                }

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($assessStudents[$aNum][$stu_id]['subjective'][$mainNum])) {
                            $assessStudents[$aNum][$stu_id]['subjective'][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $assessStudents[$aNum][$stu_id]['subjective'][$mainNum]['obtained'] += $obtained;
                        $assessStudents[$aNum][$stu_id]['subjective'][$mainNum]['max'] += $qMarks;
                    } else {
                        if (!isset($assessStudents[$aNum][$stu_id]['other'][$comp])) {
                            $assessStudents[$aNum][$stu_id]['other'][$comp] = ['obtained' => 0, 'max' => 0];
                        }
                        $assessStudents[$aNum][$stu_id]['other'][$comp]['obtained'] += $obtained;
                        $assessStudents[$aNum][$stu_id]['other'][$comp]['max'] += $qMarks;
                    }
                } else {
                    if (!isset($assessStudents[$aNum][$stu_id]['other'][$comp])) {
                        $assessStudents[$aNum][$stu_id]['other'][$comp] = ['obtained' => 0, 'max' => 0];
                    }
                    $assessStudents[$aNum][$stu_id]['other'][$comp]['obtained'] += $obtained;
                    $assessStudents[$aNum][$stu_id]['other'][$comp]['max'] += $qMarks;
                }
            }

            $results = [];
            $allObtained = 0;
            $allMax = 0;

            foreach ($assessStudents as $aNum => $students) {
                $totalAssessObtained = 0;
                $totalAssessMax = 0;

                foreach ($students as $stu_id => $sData) {
                    $stuObtained = 0;
                    $stuMax = 0;

                    if (!empty($sData['subjective'])) {
                        $scale = $getCompScale($aNum, 'subjective');
                        $pairKeys = [];
                        foreach (array_keys($sData['subjective']) as $mNum) {
                            $pairKeys[(int)ceil($mNum / 2)] = true;
                        }
                        $subjObt = 0;
                        $subjMax = 0;
                        foreach (array_keys($pairKeys) as $pairKey) {
                            $qOdd = $pairKey * 2 - 1;
                            $qEven = $pairKey * 2;
                            $oddObt = $sData['subjective'][$qOdd]['obtained'] ?? 0;
                            $oddMax = $sData['subjective'][$qOdd]['max'] ?? 0;
                            $evenObt = $sData['subjective'][$qEven]['obtained'] ?? 0;
                            $evenMax = $sData['subjective'][$qEven]['max'] ?? 0;

                            $subjObt += max($oddObt, $evenObt);
                            $subjMax += max($oddMax, $evenMax);
                        }

                        $stuObtained += round(min($subjObt * $scale, $condSubjectiveMax), 1);
                        $stuMax += min($subjMax * $scale, $condSubjectiveMax);
                    }

                    if (!empty($sData['other'])) {
                        foreach ($sData['other'] as $cType => $cMarks) {
                            $scale = $getCompScale($aNum, $cType);
                            $maxCap = ($cType === 'objective') ? $condObjectiveMax : (($cType === 'assignment') ? $condAssignmentMax : 9999);
                            $stuObtained += round(min($cMarks['obtained'] * $scale, $maxCap), 1);
                            $stuMax += min($cMarks['max'] * $scale, $maxCap);
                        }
                    }

                    $totalAssessObtained += $stuObtained;
                    $totalAssessMax += $stuMax;
                }

                $percentage = ($totalAssessMax > 0) ? round(($totalAssessObtained / $totalAssessMax) * 100, 2) : 0;
                $results[] = [
                    'assessment_number' => $aNum,
                    'assessment_label' => $assessLabels[$aNum] ?? "CIA - $aNum",
                    'attainment_percentage' => $percentage
                ];

                $allObtained += $totalAssessObtained;
                $allMax += $totalAssessMax;
            }

            if (count($results) > 1) {
                $overallAttainment = ($allMax > 0) ? round(($allObtained / $allMax) * 100, 2) : 0;
                $results[] = [
                    'assessment_number' => 'all',
                    'assessment_label' => 'Overall Average',
                    'attainment_percentage' => $overallAttainment
                ];
            }
        } else {
            $query = "
                SELECT
                    i.assessment_number,
                    {$labelExpr} AS assessment_label,
                    COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS attainment_percentage
                FROM student_marks m
                JOIN assessment_questions q ON m.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']}) {$compFilter}
            ";
            $params = $in['params'];

            if ($assessment_id !== null && $assessment_id !== 'all' && filter_var($assessment_id, FILTER_VALIDATE_INT) !== false) {
                $query .= " AND i.assessment_number = ?";
                $params[] = (int)$assessment_id;
            } else {
                $query .= " AND i.assessment_number != 'SEE'";
            }

            $query .= " GROUP BY i.assessment_number ORDER BY i.assessment_number";
            $results = $this->fetchAssoc($query, $params);

            if (count($results) > 1) {
                $overallQuery = "
                    SELECT COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS overall_attainment
                    FROM student_marks m
                    JOIN assessment_questions q ON m.question_id = q.id
                    JOIN assessment_components ac ON q.component_id = ac.id
                    JOIN internal_assessments i ON ac.assessment_id = i.id
                    WHERE i.sub_id IN ({$in['sql']}) AND i.assessment_number != 'SEE' {$compFilter}
                ";
                $overallRes = $this->fetchAssoc($overallQuery, $in['params']);
                if (!empty($overallRes)) {
                    $results[] = [
                        'assessment_number' => 'all',
                        'assessment_label' => 'Overall Average',
                        'attainment_percentage' => $overallRes[0]['overall_attainment']
                    ];
                }
            }
        }

        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        $numericResults = array_filter($results, function($r) { return $r['assessment_number'] !== 'all'; });
        $numericResults = array_values($numericResults);

        if (count($numericResults) > 1) {
            $first = $numericResults[0]['attainment_percentage'];
            $last = end($numericResults)['attainment_percentage'];
            if ($last < $first && $last < $targetThreshold) {
                $suggestions[] = "Comparison shows performance declined from CIA-{$numericResults[0]['assessment_number']} ({$first}%) to CIA-{$numericResults[count($numericResults) - 1]['assessment_number']} ({$last}%).";
            } elseif ($last < $targetThreshold && $first < $targetThreshold) {
                $suggestions[] = "Performance across assessments is consistently below the target (" . $targetThreshold . "%).";
            } else {
                $suggestions[] = "Performance across assessments noted.";
            }
        } elseif (count($numericResults) == 1) {
            $label = $numericResults[0]['assessment_label'];
            if ($numericResults[0]['attainment_percentage'] < $targetThreshold) {
                $suggestions[] = "Performance in {$label} ({$numericResults[0]['attainment_percentage']}%) is below the target (" . $targetThreshold . "%).";
            } else {
                $suggestions[] = "Performance in {$label} meets the target.";
            }
        }

        if (empty($results)) {
            if ($isLab) {
                $suggestions[] = "No Internal Exam marks found. Note: Only the Internal Exam is considered as the formal assessment for Lab CIA (Day-to-Day lab evaluation is displayed in Component-wise Performance).";
            } else {
                $suggestions[] = "No assessment performance data found for the selected scope.";
            }
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions, 'sub_type' => $sub_type]);
    }

    /**
     * Fetch Component Performance Analysis
     */
    public function getComponentPerformance($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            $isSEE = (strcasecmp((string)$assessment_id, 'SEE') === 0);
            if ($isSEE) {
                $extSql="SELECT external_marks,max_marks,q1_marks,choice_marks,normalisation_json FROM external_assessment_marks WHERE is_current=1 AND subject_id IN ({$in['sql']})";
                $rows=$this->fetchAssoc($extSql,$in['params']);
                if($rows){$totals=[];$q1=[];$choices=[];
                    foreach($rows as $row){if((float)$row['max_marks']<=0){continue;}$totals[]=(float)$row['external_marks']/(float)$row['max_marks']*100;$n=json_decode($row['normalisation_json']??'null',true);
                        if(!empty($n['selected_questions'])){$obt=$max=0;foreach($n['selected_questions'] as $q){if(preg_match('/^1[a-z]+$/i',$q['label'])){$obt+=$q['obtained'];$max+=$q['maximum'];}}if($max>0){$q1[]=$obt/$max*100;}if(($n['gross_max']-$max)>0){$choices[]=($n['gross']-$obt)/($n['gross_max']-$max)*100;}}
                        elseif($row['q1_marks']!==null&&in_array((float)$row['max_marks'],[35.0,70.0],true)){$legacyQ1=(float)$row['max_marks']===35.0?5:20;$q1[]=(float)$row['q1_marks']/$legacyQ1*100;if($row['choice_marks']!==null){$choices[]=(float)$row['choice_marks']/((float)$row['max_marks']-$legacyQ1)*100;}}
                    }
                    $totalPct=$totals?round(array_sum($totals)/count($totals),2):null;$results=[];
                    if($q1){$results[]=['component_type'=>'Q1 subtotal','attainment_percentage'=>round(array_sum($q1)/count($q1),2)];}
                    if($choices){$results[]=['component_type'=>'Selected choices / other questions','attainment_percentage'=>round(array_sum($choices)/count($choices),2)];}
                    $results[]=['component_type'=>'Total SEE External','attainment_percentage'=>$totalPct];
                    $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
                    $suggestions = [];
                    if ($totalPct >= $targetThreshold) {
                        $suggestions[] = "Overall external component performance meets or exceeds the target benchmark ({$targetThreshold}%).";
                    } else {
                        $suggestions[] = "Overall external component performance ({$totalPct}%) is below the target benchmark ({$targetThreshold}%).";
                    }
                    return json_encode(['data' => $results, 'suggestions' => $suggestions]);
                }
            }

            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            $where_sql = implode(" AND ", $where_clauses);

            $qQuery = "
                SELECT
                    ac.component_type,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM assessment_components ac
                JOIN assessment_questions q ON ac.id = q.component_id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY ac.component_type, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $compSummary = [];
            $studentCompSubMarks = [];

            foreach ($rawRows as $row) {
                $comp = $row['component_type'];
                $compLower = strtolower($comp);
                $aNum = $row['assessment_number'];
                $stu_id = $row['stu_id'] ?? 0;
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!isset($compSummary[$comp])) {
                    $compSummary[$comp] = [
                        'component_type' => $comp,
                        'total_obtained' => 0,
                        'total_max' => 0
                    ];
                }

                if (!$stu_id) continue;

                if ($compLower === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum])) {
                            $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum]['obtained'] += $obtained;
                        $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        $compSummary[$comp]['total_obtained'] += $obtained;
                        $compSummary[$comp]['total_max'] += $qMarks;
                    }
                } else {
                    $compSummary[$comp]['total_obtained'] += $obtained;
                    $compSummary[$comp]['total_max'] += $qMarks;
                }
            }

            foreach ($studentCompSubMarks as $comp => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $mainQuestions) {
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

                            $compSummary[$comp]['total_obtained'] += $pairObtained;
                            $compSummary[$comp]['total_max'] += $pairMax;
                        }
                    }
                }
            }

            $orderMap = ['day-to-day' => 1, 'internal exam' => 2, 'subjective' => 3, 'objective' => 4, 'assignment' => 5];
            uksort($compSummary, function($a, $b) use ($orderMap) {
                $oa = $orderMap[strtolower($a)] ?? 99;
                $ob = $orderMap[strtolower($b)] ?? 99;
                return $oa <=> $ob;
            });

            $results = [];
            foreach ($compSummary as $comp => $info) {
                $percentage = ($info['total_max'] > 0) ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : 0;
                $results[] = [
                    'component_type' => $info['component_type'],
                    'attainment_percentage' => $percentage
                ];
            }
        } else {
            $query = "
                SELECT
                    ac.component_type,
                    COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS attainment_percentage
                FROM assessment_components ac
                JOIN assessment_questions q ON ac.id = q.component_id
                JOIN student_marks m ON q.id = m.question_id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
            ";
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $query .= " AND i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $query .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }

            $query .= " GROUP BY ac.component_type ORDER BY FIELD(ac.component_type, 'Day-to-Day', 'Internal Exam', 'Subjective', 'Objective', 'Assignment')";

            $results = $this->fetchAssoc($query, $params);
        }

        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['attainment_percentage'] < $targetThreshold) {
                $suggestions[] = "Performance in '{$row['component_type']}' components ({$row['attainment_percentage']}%) is below target (" . $targetThreshold . "%). This might indicate students struggle with this question format or the topics assessed via this component.";
            }
        }
        if (empty($results)) {
            $suggestions[] = "No component performance data found. Ensure questions, components, and marks are entered.";
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch Question Difficulty & Discrimination Analysis
     */
    public function getQuestionDifficultyDiscrimination($subject_id, $assessment_id = null)
    {
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        $select_columns = "
            aq.id AS question_id,
            aq.question_label AS question_text,
            ac.component_type,
            ac.sequence_number,
            i.assessment_number,
            ROUND(AVG(sm.marks_obtained / aq.marks) * 100, 2) AS avg_difficulty,
            STDDEV_POP(sm.marks_obtained) AS std_dev
        ";
        $query = "
            SELECT $select_columns FROM assessment_questions aq
            JOIN student_marks sm ON aq.id = sm.question_id
            JOIN assessment_components ac ON aq.component_id = ac.id
            JOIN internal_assessments i ON ac.assessment_id = i.id
            WHERE i.sub_id IN ({$in['sql']})
        ";

        $params = $in['params'];
        if ($assessment_id && $assessment_id !== 'all') {
            $query .= " AND i.assessment_number = ?";
            $params[] = $assessment_id;
        } else {
            $query .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
        }

        $query .= "
            GROUP BY aq.id, ac.component_type, ac.sequence_number
        ";

        if ($assessment_id && $assessment_id !== 'all') {
            $query .= "
                ORDER BY
                    CASE ac.component_type
                        WHEN 'subjective' THEN 1
                        WHEN 'objective' THEN 2
                        WHEN 'assignment' THEN 3
                        ELSE 4
                    END,
                    ac.sequence_number,
                    aq.id
            ";
        } else {
            $query = "SELECT $select_columns FROM assessment_questions aq
            JOIN student_marks sm ON aq.id = sm.question_id
            JOIN assessment_components ac ON aq.component_id = ac.id
            JOIN internal_assessments i ON ac.assessment_id = i.id
            WHERE i.sub_id IN ({$in['sql']})
            GROUP BY aq.id, ac.component_type, ac.sequence_number, i.assessment_number
            ORDER BY i.assessment_number,
                     aq.id";
        }

        $data = $this->fetchAssoc($query, $params);
        $suggestions = [];

        foreach ($data as $q) {
            $component_display = ucfirst($q['component_type']);
            if ($q['assessment_number']) {
                $component_display = $q['assessment_number'] . "." . $component_display;
            }
            if ($q['avg_difficulty'] < 30) {
                $suggestions[] = "CIA-'{$component_display} - {$q['question_text']}' appears too difficult. Consider reviewing.";
            } elseif ($q['avg_difficulty'] > 85) {
                $suggestions[] = "CIA-'{$component_display} - {$q['question_text']}' is too easy. Consider increasing complexity.";
            }
        }

        return json_encode(['data' => $data, 'suggestions' => $suggestions]);
    }
}

class LearningAnalytics extends DBCredentials
{
    use COAttainmentTrait;
    use LearningAnalyticsTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

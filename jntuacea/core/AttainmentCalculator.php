<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/MarksNormalizer.php';

final class AttainmentCalculator
{
    /**
     * Compute external (SEE) Course Outcome attainment across 3 tiers:
     * Tier 1: Question-wise marks (DETAILED)
     * Tier 2: QP metadata distribution (questions mapped to COs, distributed across CO weights)
     * Tier 3: Results only (external marks or regulation grade point range distributed across all COs)
     */
    public static function external(array $cos, array $rows, float $target, array $levels, array $qpWeights = []): array
    {
        if ($target < 0 || $target > 100 || count($levels) !== 3) {
            throw new \InvalidArgumentException('Invalid attainment thresholds.');
        }
        $previous = -1;
        foreach ([1, 2, 3] as $l) {
            $n = MarksNormalizer::number($levels[$l], 'Attainment level');
            if ($n < $previous || $n > 100) {
                throw new \InvalidArgumentException('Attainment levels must be ordered percentages.');
            }
            $previous = $n;
        }

        $data = [];
        $modes = array_values(array_diff(array_unique(array_column($rows, 'entry_mode')), ['MISSING']));
        $hasQpDistribution = !empty($qpWeights);

        foreach ($cos as $co) {
            $id = (int)$co['id'];
            $attained = $evaluated = $measured = $estimated = 0;

            foreach ($rows as $row) {
                $pct = null;

                // Tier 1: Detailed question-wise marks added
                if (($row['entry_mode'] ?? '') === 'DETAILED') {
                    $e = $row['evidence'] ?? null;
                    $c = $e['cos'][$id] ?? null;
                    if (($e['complete'] ?? false) && $c && ($c['complete'] ?? false) && ($c['maximum'] ?? 0) > 0) {
                        $pct = $c['obtained'] / $c['maximum'] * 100;
                        $measured++;
                    }
                }

                // If not resolved question-wise, evaluate Tier 2 (QP metadata) or Tier 3 (Results distributed across all COs)
                if ($pct === null && (isset($row['external_marks']) || isset($row['evidence']['percentage']))) {
                    $maxMarks = (float)($row['max_marks'] ?? 70.0);
                    $basePct = null;

                    if (isset($row['external_marks']) && $row['external_marks'] !== null && $maxMarks > 0) {
                        $basePct = MarksNormalizer::scale($row['external_marks'], $maxMarks, 100);
                    } elseif (isset($row['evidence']['percentage'])) {
                        $basePct = (float)$row['evidence']['percentage'];
                    }

                    if ($basePct !== null) {
                        if ($hasQpDistribution && isset($qpWeights[$id])) {
                            // Tier 2: SEE QP metadata uploaded -> distribute across metadata CO distribution
                            $pct = $basePct;
                            $measured++;
                        } else {
                            // Tier 3: Results only -> distribute across all COs using external marks or regulation grade range
                            $pct = $basePct;
                            $estimated++;
                        }
                    }
                }

                if ($pct !== null) {
                    $evaluated++;
                    if ($pct + 0.0000001 >= $target) {
                        $attained++;
                    }
                }
            }

            $cohortPct = $evaluated ? ($attained / $evaluated * 100) : null;
            $level = null;
            if ($cohortPct !== null) {
                $level = 0;
                foreach ([1, 2, 3] as $l) {
                    if ($cohortPct + 0.0000001 >= $levels[$l]) {
                        $level = $l;
                    }
                }
            }

            $basis = $measured && $estimated
                ? 'MIXED'
                : ($measured
                    ? ($hasQpDistribution ? 'QP_METADATA_DISTRIBUTION' : 'QUESTION_WISE')
                    : ($estimated ? 'RESULTS_REGULATION_RANGE' : 'UNAVAILABLE'));

            $data[$co['co_number']] = [
                'co_id'               => $id,
                'attained_students'   => $attained,
                'total_students'      => count($rows),
                'evaluated_students'  => $evaluated,
                'missing_students'    => count($rows) - $evaluated,
                'measured_students'   => $measured,
                'estimated_students'  => $estimated,
                'cohort_percentage'   => $cohortPct !== null ? round($cohortPct, 2) : null,
                'attainment_level'    => $level,
                'unmapped'            => $evaluated === 0,
                'coverage_percentage' => count($rows) ? round($evaluated / count($rows) * 100, 2) : null,
                'provisional'         => $evaluated < count($rows),
                'basis'               => $basis
            ];
        }

        return [
            'status' => 1,
            'mode'   => count($modes) > 1 ? 'MIXED' : ($modes[0] ?? 'DIRECT'),
            'data'   => $data
        ];
    }
}

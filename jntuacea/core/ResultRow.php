<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/MarksNormalizer.php';

/** Normalize the legacy result-file conventions against one exact course policy. */
final class ResultRow
{
    public static function normalize(array $row, array $course, array $policy): array
    {
        $status = match (strtoupper(trim((string)$row['status']))) {
            'P', 'PASS', 'PASSED' => 'PASS',
            'F', 'FAIL', 'FAILED' => 'FAIL',
            'A', 'AB', 'ABS', 'ABSENT' => 'ABSENT',
            'MP', 'MALPRACTICE' => 'MALPRACTICE',
            'W', 'WH', 'WITHHELD' => 'WITHHELD',
            default => throw new \InvalidArgumentException('Invalid result status.'),
        };
        $grade = strtoupper(trim((string)$row['grade']));
        if ($grade === '' || $grade === '-') {
            $grade = match ($status) {
                'FAIL' => 'F', 'ABSENT' => 'AB', 'MALPRACTICE' => 'MP', 'WITHHELD' => 'WH',
                default => throw new \InvalidArgumentException('A passing result requires its awarded grade.'),
            };
        }
        if (!preg_match('/^[A-Z0-9+_-]{1,5}$/D', $grade)) {
            throw new \InvalidArgumentException('Grade must contain 1–5 letters, digits, +, _ or -.');
        }
        $im = self::mark($row['internal'], 'Internal marks', ['-7']);
        $em = self::mark($row['external'], 'External marks', ['-1', '-5']);
        $total = self::mark($row['total'], 'Total marks');
        if (trim((string)$row['external']) === '-1' || $grade === 'AB') { $status = 'ABSENT'; }
        if ($grade === 'MP') { $status = 'MALPRACTICE'; }
        if (in_array($grade, ['WH', 'W'], true)) { $status = 'WITHHELD'; }
        if ($status === 'ABSENT') {
            if ($em !== null && $em > 0) { throw new \InvalidArgumentException('Absent results cannot contain positive external marks.'); }
            $em = null;
        }

        $bands = $policy['grade_bands'] ?? [];
        $band = null;
        foreach ($bands as $candidate) {
            if (strcasecmp((string)($candidate['grade'] ?? ''), $grade) === 0) { $band = $candidate; break; }
        }
        $pointsText = trim((string)$row['points']);
        $points = ($pointsText === '' || $pointsText === '-') ? null : MarksNormalizer::number($pointsText, 'Grade points');
        if ($status === 'PASS') {
            if ($band === null || !isset($band['points'])) {
                throw new \DomainException('Configure this regulation’s grade bands before importing passing grade ' . $grade . '.');
            }
            $expected = MarksNormalizer::number($band['points'], 'Configured grade points');
            if ($expected <= 0) { throw new \InvalidArgumentException('A passing result cannot use a failing grade.'); }
            if ($points !== null && abs($points - $expected) > 0.00001) {
                throw new \InvalidArgumentException('Grade points disagree with this regulation’s awarded grade.');
            }
            $points = $expected;
        } else {
            if ($points !== null && $points != 0.0) { throw new \InvalidArgumentException('Non-passing results must have zero grade points.'); }
            if ($band !== null && (float)($band['points'] ?? 0) > 0) {
                throw new \InvalidArgumentException('Non-passing status disagrees with the awarded grade.');
            }
            $allowed = match ($status) {
                'ABSENT' => ['AB', 'F'], 'MALPRACTICE' => ['MP', 'F'], 'WITHHELD' => ['WH', 'W', 'F'],
                default => ['F'],
            };
            if ($band === null && !in_array($grade, $allowed, true)) {
                throw new \InvalidArgumentException('Unrecognized non-passing grade.');
            }
            $points = 0.0;
        }

        $uploadedCredits = self::optionalNumber($row['credits'], 'Credits');
        $credits = $course['credits'] !== null ? MarksNormalizer::number($course['credits'], 'Curriculum credits') : $uploadedCredits;
        if ($credits === null) { throw new \DomainException('Credits are missing; link the curriculum or supply approved registered credits.'); }
        if ($uploadedCredits !== null && abs($uploadedCredits - $credits) > 0.00001
            && !($status !== 'PASS' && $uploadedCredits === 0.0)) {
            throw new \InvalidArgumentException('Uploaded credits disagree with the course curriculum.');
        }
        if ($credits > 99.99) { throw new \InvalidArgumentException('Credits exceed the supported result-record capacity.'); }

        foreach (['internal' => [$im, 'internal_max'], 'external' => [$em, 'net_max']] as $label => [$value, $key]) {
            $maximum = $policy[$key] ?? null;
            $uploadedMaximum = $row[$label . '_max'] ?? null;
            if ($uploadedMaximum !== null && trim((string)$uploadedMaximum) !== '') {
                $supplied = MarksNormalizer::number($uploadedMaximum, $label . ' maximum');
                if ($maximum === null || abs($supplied - (float)$maximum) > 0.00001) {
                    throw new \InvalidArgumentException('Uploaded ' . $label . ' maximum disagrees with the configured course policy.');
                }
            }
            if ($value !== null && $maximum === null) { throw new \DomainException('Configure the course ' . $label . ' maximum.'); }
            if ($value !== null && $value > (float)$maximum + 0.00001) { throw new \InvalidArgumentException(ucfirst($label) . ' marks exceed the configured maximum.'); }
        }
        if ($im !== null && $em !== null) {
            $sum = round($im + $em, 2);
            if ($total !== null && abs($total - $sum) > 0.010001) { throw new \InvalidArgumentException('Total must equal internal plus external marks.'); }
            $total ??= $sum;
        }
        if ($total !== null && isset($policy['internal_max'], $policy['net_max'])
            && $total > (float)$policy['internal_max'] + (float)$policy['net_max'] + 0.00001) {
            throw new \InvalidArgumentException('Total exceeds the configured course maximum.');
        }
        return ['internal_marks' => $im, 'external_marks' => $em, 'total_marks' => $total,
            'grade_letter' => $grade, 'grade_points' => $points, 'credits_registered' => $credits,
            'credits_earned' => $status === 'PASS' ? $credits : 0.0, 'result_status' => $status];
    }

    private static function optionalNumber(mixed $value, string $label): ?float
    {
        return in_array(trim((string)$value), ['', '-'], true) ? null : MarksNormalizer::number($value, $label);
    }

    private static function mark(mixed $value, string $label, array $sentinels = []): ?float
    {
        if (in_array(trim((string)$value), $sentinels, true)) { return null; }
        $number = self::optionalNumber($value, $label);
        if ($number !== null && ($number > 999.99 || abs($number - round($number, 2)) > 0.000001)) {
            throw new \InvalidArgumentException($label . ' must fit the supported two-decimal result format.');
        }
        return $number;
    }
}

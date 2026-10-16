<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/MarksNormalizer.php';

final class CiaCalculator
{
    public static function policy(array $course, array $settings): array
    {
        $type = strtolower(trim($course['sub_type']));
        $pg = $course['program_level'] === 'PG';
        $schemes = isset($settings['cia_scheme_json']) ? json_decode($settings['cia_scheme_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        if (!is_array($schemes)) { throw new \DomainException('CIA schemes must be an object keyed by subject type.'); }
        $custom = $schemes[$type] ?? null;
        if ($custom !== null && !is_array($custom)) { throw new \DomainException('Invalid CIA subject-type scheme.'); }
        $maximum = $course['cie_max_marks'] ?? null;
        if ($maximum !== null && (float)$maximum === 0.0) { return ['kind'=>'NONE','maximum'=>0.0]; }
        if ($pg && !$custom) { throw new \DomainException('Configure the PG CIA scheme for this regulation and subject type before exporting totals.'); }
        $kind = $custom['kind'] ?? match ($type) {
            'theory', 'pe', 'oe' => 'UG_THEORY',
            'lab' => 'UG_LAB', 'project' => 'UG_PROJECT',
            default => throw new \DomainException('Configure a CIA scheme for subject type ' . $course['sub_type'] . '.'),
        };
        $fields = match ($kind) {
            'UG_THEORY'=>['subjective_marks','objective_marks','assignment_marks'],
            'UG_LAB'=>['day_to_day_marks','internal_test_marks'],
            'UG_PROJECT'=>['component1_marks','component2_marks'],
            'PG_THEORY', 'PG_LAB'=>['marks'],
            default=>throw new \DomainException('Unsupported CIA calculation kind.'),
        };
        if ($pg !== str_starts_with($kind, 'PG_')) { throw new \DomainException('CIA scheme belongs to a different program level.'); }
        $numbers = $custom['required_assessments'] ?? ($kind === 'UG_THEORY' ? [1,2] : [1]);
        if (!is_array($numbers) || !$numbers || count($numbers) > 16 || count(array_unique($numbers)) !== count($numbers)) { throw new \DomainException('Configure distinct required CIA assessment numbers.'); }
        foreach ($numbers as $number) { if (!is_int($number) || $number < 1 || $number > 100) { throw new \DomainException('CIA assessment numbers must be integers from 1 to 100.'); } }
        if ($pg) {
            $allowed = $kind === 'PG_THEORY' ? [1,2,3,4] : [11,12];
            if (array_diff($numbers, $allowed) || ($kind === 'PG_THEORY' && array_slice($numbers,0,2) !== [1,2])) {
                throw new \DomainException('PG theory uses mids 1/2, assignment 3 and continuous evaluation 4; PG lab uses internal 11 and record 12.');
            }
        }
        $weighted = in_array($kind, ['UG_THEORY','PG_THEORY'], true);
        if ($weighted && (count($numbers) < 2 || $numbers[0] === $numbers[1])) { throw new \DomainException('Weighted CIA requires two mid assessments.'); }
        $better = $custom['better_weight'] ?? ($settings['theory_mid_better_weight'] ?? null);
        $lesser = $custom['lesser_weight'] ?? ($settings['theory_mid_lesser_weight'] ?? null);
        if ($weighted && ($better === null || $lesser === null || !is_numeric($better) || !is_numeric($lesser)
            || (float)$better < 0 || (float)$lesser < 0 || abs((float)$better + (float)$lesser - 1) > 0.000001)) {
            throw new \DomainException('CIA better/lesser weights must be configured and sum to one.');
        }
        if ($kind === 'PG_THEORY' && (!isset($custom['better_weight']) || !isset($custom['lesser_weight']))) { throw new \DomainException('PG mid weights must be explicitly configured in its CIA scheme.'); }
        $maximum ??= $kind === 'UG_THEORY' ? ($settings['theory_cia_total_marks'] ?? null) : null;
        if ($maximum === null || !is_numeric($maximum) || !is_finite((float)$maximum) || (float)$maximum <= 0) { throw new \DomainException('Configure the course CIA maximum.'); }
        $rounding = $custom['rounding'] ?? 'NEAREST_INTEGER';
        if (!in_array($rounding,['NEAREST_INTEGER','TWO_DECIMALS','CEILING','FLOOR'],true)) { throw new \DomainException('Unsupported CIA rounding rule.'); }
        $maxima = $custom['component_maxima'] ?? ($pg ? [] : array_fill_keys($numbers, (float)$maximum));
        if (!is_array($maxima)) { throw new \DomainException('Component maxima must be keyed by assessment number.'); }
        foreach ($maxima as $number=>$value) {
            if (!ctype_digit((string)$number) || (string)(int)$number !== (string)$number || !in_array((int)$number,$numbers,true) || !is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || ($pg && (float)$value > 99.99)) { throw new \DomainException('Invalid CIA assessment maximum.'); }
        }
        if ($pg && count($maxima) !== count($numbers)) { throw new \DomainException('Configure a maximum for each required PG component.'); }
        // New evidence has its own assessment identity; it is never folded into an old field.
        if ($pg && $kind === 'PG_THEORY' && (float)($settings['theory_continuous_eval_marks'] ?? 0) > 0 && !in_array(4,$numbers,true)) { throw new \DomainException('This regulation requires a separate continuous-evaluation component (assessment 4).'); }
        if ($pg && $kind === 'PG_LAB' && (float)($settings['lab_cia_record_marks'] ?? 0) > 0 && !in_array(12,$numbers,true)) { throw new \DomainException('This regulation requires a separate lab-record component (assessment 12).'); }
        foreach ([4=>['PG_THEORY','theory_continuous_eval_marks'],12=>['PG_LAB','lab_cia_record_marks']] as $number=>[$extraKind,$key]) {
            if($kind===$extraKind && isset($settings[$key]) && isset($maxima[$number]) && abs((float)$maxima[$number]-(float)$settings[$key])>0.000001){throw new \DomainException('Separate component maxima must match the regulation settings.');}
        }
        $fieldMaxima=[];
        $keys=match($kind){'UG_THEORY'=>['subjective_marks'=>'theory_mid_subjective_condensed','objective_marks'=>'theory_mid_objective_marks','assignment_marks'=>'theory_assignment_marks'], 'UG_LAB'=>['day_to_day_marks'=>'lab_cia_day_to_day_marks','internal_test_marks'=>'lab_cia_internal_test_marks'],default=>[]};
        foreach($keys as $field=>$key){if(isset($settings[$key])){$fieldMaxima[$field]=MarksNormalizer::number($settings[$key],'CIA field maximum');}}
        return ['field_maxima'=>$fieldMaxima,'kind'=>$kind,'fields'=>$fields,'required'=>$numbers,'weighted'=>$weighted,'better'=>(float)$better,'lesser'=>(float)$lesser,
            'maximum'=>(float)$maximum,'rounding'=>$rounding,'component_maxima'=>$maxima];
    }

    /** A missing assessment is not zero; each invocation owns all intermediate values. */
    public static function calculate(array $policy, array $rows): array
    {
        if ($policy['kind'] === 'NONE') { return ['status'=>'NOT_APPLICABLE','final'=>null,'reason'=>'No CIA is required for this course.']; }
        $totals = [];
        foreach ($policy['required'] as $number) {
            $evidence = array_values(array_filter($rows,static fn($row)=>(int)$row['assessment_number']===$number));
            if (!$evidence) { return ['status'=>'INCOMPLETE','final'=>null,'reason'=>'Missing assessment ' . $number . '.']; }
            if (count($evidence) !== 1) { return ['status'=>'INVALID_DATA','final'=>null,'reason'=>'Duplicate assessment ' . $number . '.']; }
            $sum = 0.0;
            foreach ($policy['fields'] as $field) {
                if (!isset($evidence[0][$field])) { return ['status'=>'INCOMPLETE','final'=>null,'reason'=>'Missing component ' . $field . '.']; }
                $score=MarksNormalizer::number($evidence[0][$field], 'CIA component');
                if(isset($policy['field_maxima'][$field]) && $score>$policy['field_maxima'][$field]+0.000001){return ['status'=>'INVALID_DATA','final'=>null,'reason'=>'CIA field exceeds its configured maximum.'];}
                $sum += $score;
            }
            if (isset($policy['component_maxima'][$number]) && $sum > (float)$policy['component_maxima'][$number] + 0.000001) { return ['status'=>'INVALID_DATA','final'=>null,'reason'=>'Assessment exceeds its configured maximum.']; }
            $totals[$number] = $sum;
        }
        $values = array_values($totals);
        if ($policy['weighted']) {
            $first = array_shift($values); $second = array_shift($values);
            $raw = max($first,$second)*$policy['better'] + min($first,$second)*$policy['lesser'] + array_sum($values);
        } else { $raw = array_sum($values); }
        if ($raw > $policy['maximum'] + 0.000001) { return ['status'=>'INVALID_DATA','final'=>null,'reason'=>'CIA total exceeds the course maximum.']; }
        $final = match($policy['rounding']) { 'NEAREST_INTEGER'=>round($raw),'TWO_DECIMALS'=>round($raw,2),'CEILING'=>ceil($raw),'FLOOR'=>floor($raw) };
        if ($final > $policy['maximum'] + 0.000001) { return ['status'=>'INVALID_DATA','final'=>null,'reason'=>'Rounded CIA exceeds the course maximum.']; }
        return ['status'=>'COMPLETE','final'=>(float)$final,'unrounded'=>$raw,'assessments'=>$totals,'reason'=>''];
    }
}

<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/AssessmentPolicy.php';
require_once __DIR__.'/ResultRow.php';
require_once __DIR__.'/ResultsPolicy.php';

final class ResultsCsv
{
    public function __construct(private \mysqli $db) {}

    public function validate(int $notificationId, string $path): array
    {
        $stmt = $this->db->prepare('SELECT * FROM exam_notifications WHERE id=?');
        $stmt->bind_param('i', $notificationId);
        $stmt->execute();
        $notification = $stmt->get_result()->fetch_assoc();
        if (!$notification || $notification['is_published'] || $notification['workflow_status'] !== 'DRAFT') {
            throw new \DomainException('Select an unpublished draft notification.');
        }

        // Support REGULAR, SUPPLEMENTARY, and REVALUATION exam notifications
        if (!in_array($notification['exam_type'], ['REGULAR', 'SUPPLEMENTARY', 'REVALUATION'], true)) {
            throw new \DomainException('Unsupported exam type.');
        }

        if (!is_file($path) || !is_readable($path) || filesize($path) > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Select a readable CSV file up to 10 MB.');
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV is empty.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $names = array_map(static fn($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', trim($v))), $header);

        // Comprehensive alias mapping matching resultsold scheme (std_regno, sub_code, im, em, total, result, grade, grade_points, credits)
        $aliases = [
            'htno'     => ['htno', 'rollno', 'rollnumber', 'hallticket', 'hallticketno', 'hallticketnumber', 'admissionnumber', 'admissionno', 'studentadmissionnumber', 'stdregno', 'std_regno', 'regno'],
            'subcode'  => ['subcode', 'sub_code', 'subjectcode', 'subject_code', 'coursecode', 'course_code'],
            'internal' => ['internals', 'internalmarks', 'internal', 'internal_marks', 'im', 'cie'],
            'external' => ['externals', 'externalmarks', 'external', 'external_marks', 'em', 'see'],
            'total'    => ['total', 'totalmarks', 'total_marks', 'tot'],
            'grade'    => ['grade', 'gradeletter', 'grade_letter', 'grd'],
            'points'   => ['gradepoints', 'grade_points', 'points', 'gradepoint', 'gp', 'gpa'],
            'credits'  => ['credits', 'credit', 'crd', 'credits_registered', 'credits_earned'],
            'status'   => ['status', 'result', 'result_status', 'res']
        ];

        // Optional columns
        $optionalAliases = [
            'stdname' => ['stdname', 'std_name', 'studentname', 'student_name', 'name'],
            'subname' => ['subname', 'sub_name', 'subjectname', 'subject_name', 'coursename'],
            'internal_max' => ['internalmax'], 'external_max' => ['externalmax']
        ];

        $cols = [];
        if (count(array_unique($names)) !== count($names)) {
            fclose($handle);
            throw new \InvalidArgumentException('Duplicate CSV column names.');
        }

        foreach ($aliases as $key => $options) {
            $matches = [];
            foreach (array_unique(array_map(static fn($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', $v)), $options)) as $option) {
                $index = array_search($option, $names, true);
                if ($index !== false) { $matches[] = $index; }
            }
            if (count($matches) !== 1) {
                fclose($handle);
                throw new \InvalidArgumentException(count($matches) ? 'Ambiguous CSV columns for ' . $key : 'CSV is missing required column: ' . $key);
            }
            $cols[$key] = $matches[0];
        }

        // Check optional columns
        $optCols = [];
        foreach ($optionalAliases as $key => $options) {
            foreach ($options as $option) {
                $cleanOption = strtolower(preg_replace('/[^a-z0-9]/i', '', $option));
                $index = array_search($cleanOption, $names, true);
                if ($index !== false) {
                    $optCols[$key] = $index;
                    break;
                }
            }
        }

        // Prepared statements for robust matching of students and subjects
        $match = $this->db->prepare('
            SELECT st.id student_id, s.id subject_id, s.sub_fullname, cs.credits, cs.cie_max_marks, cs.see_max_marks 
            FROM students st 
            JOIN student_sub ss ON ss.stu_id = st.id 
            JOIN subjects s ON s.id = ss.sub_id 
            JOIN classes c ON c.id = s.class_id AND c.id = st.class_id
            JOIN specialization sp ON sp.id = c.spec_id 
            LEFT JOIN curriculum_subjects cs ON cs.id = s.curr_sub_id 
                 AND cs.reg_id = c.reg_id 
                 AND cs.spec_id = c.spec_id 
                 AND cs.yearsem = c.yearsem 
            WHERE UPPER(TRIM(st.username)) = ? 
              AND UPPER(TRIM(s.subcode)) = ? 
              AND sp.prog_id = ? 
              AND c.reg_id = ? 
              AND c.yearsem = ? 
              AND c.acad_year = ?
        ');

        // Fallback match for supplementary/carry-over subjects across academic years in the same program & regulation
        $matchFallback = $this->db->prepare('
            SELECT st.id student_id, s.id subject_id, s.sub_fullname, cs.credits, cs.cie_max_marks, cs.see_max_marks 
            FROM students st 
            JOIN student_sub ss ON ss.stu_id = st.id 
            JOIN subjects s ON s.id = ss.sub_id 
            JOIN classes c ON c.id = s.class_id AND c.id = st.class_id
            JOIN specialization sp ON sp.id = c.spec_id 
            LEFT JOIN curriculum_subjects cs ON cs.id = s.curr_sub_id 
                 AND cs.reg_id = c.reg_id 
                 AND cs.spec_id = c.spec_id AND cs.yearsem = c.yearsem
            WHERE UPPER(TRIM(st.username)) = ? 
              AND UPPER(TRIM(s.subcode)) = ? 
              AND sp.prog_id = ? 
              AND c.reg_id = ? AND c.yearsem = ?
        ');

        $rows = [];
        $errors = [];
        $seen = [];
        $policies = [];
        $number = 1;

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                $number++;
                if (count($cells) === 1 && trim((string)$cells[0]) === '') {
                    continue;
                }
                if ($number > 10001) {
                    $errors[] = 'The import is limited to 10,000 rows.';
                    break;
                }

                try {
                    if (count($cells) !== count($header)) {
                        throw new \InvalidArgumentException('Column count does not match the header.');
                    }
                    $get = static fn($key) => trim((string)($cells[$cols[$key]] ?? ''));
                    $getOpt = static fn($key) => isset($optCols[$key]) ? trim((string)($cells[$optCols[$key]] ?? '')) : '';

                    $roll = strtoupper($get('htno'));
                    $code = strtoupper($get('subcode'));
                    $key = $roll . '|' . $code;

                    if ($roll === '' || $code === '') {
                        throw new \InvalidArgumentException('Hall ticket and subject code are required.');
                    }
                    if (isset($seen[$key])) {
                        throw new \InvalidArgumentException('Duplicate hall ticket and subject code: ' . $roll . ' / ' . $code);
                    }
                    $seen[$key] = true;

                    // 1. Resolve student and subject offering
                    $match->bind_param('ssiiss', $roll, $code, $notification['program_id'], $notification['regulation_id'], $notification['yearsem'], $notification['academic_year']);
                    $match->execute();
                    $matches = $match->get_result()->fetch_all(MYSQLI_ASSOC);

                    if (!$matches && $notification['exam_type'] !== 'REGULAR') {
                        // Historical offerings must still match the notification semester.
                        $matchFallback->bind_param('ssiis', $roll, $code, $notification['program_id'], $notification['regulation_id'], $notification['yearsem']);
                        $matchFallback->execute();
                        $matches = $matchFallback->get_result()->fetch_all(MYSQLI_ASSOC);
                    }

                    if (count($matches) !== 1) {
                        throw new \DomainException("The student ({$roll}) and course ({$code}) do not resolve to an enrolled offering for this program and regulation.");
                    }
                    $m = $matches[0];
                    $profile = $policies[(int)$m['subject_id']] ??= (new ResultsPolicy($this->db))->forSubject((int)$m['subject_id'])['config'];
                    $input = [];
                    foreach (array_keys($aliases) as $field) { $input[$field] = $get($field); }
                    $input['internal_max'] = $getOpt('internal_max');
                    $input['external_max'] = $getOpt('external_max');
                    $normalized = ResultRow::normalize($input, $m, $profile);
                    $policies[(int)$m['subject_id']] = $profile;

                    // Subject name
                    $subjectName = $getOpt('subname');
                    if ($subjectName === '') {
                        $subjectName = $m['sub_fullname'];
                    }

                    $rows[] = [
                        'htno'               => $roll,
                        'subject_code'       => $code,
                        'student_id'         => (int)$m['student_id'],
                        'subject_id'         => (int)$m['subject_id'],
                        'subject_name'       => $subjectName,
                    ] + $normalized;
                } catch (\DomainException | \InvalidArgumentException $e) {
                    $errors[] = 'Row ' . $number . ': ' . $e->getMessage();
                }
            }
        } finally {
            fclose($handle);
        }

        if (!$rows && !$errors) {
            $errors[] = 'CSV has no result rows.';
        }

        return [
            'status'      => $errors ? 0 : 1,
            'rows'        => $rows,
            'errors'      => $errors,
            'source_hash' => hash_file('sha256', $path),
            'validation_hash' => hash('sha256', json_encode([$notification, $policies, $rows], JSON_THROW_ON_ERROR))
        ];
    }
}

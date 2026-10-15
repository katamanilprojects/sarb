<?php
declare(strict_types=1);
namespace College;
require_once __DIR__.'/AssessmentPolicy.php';

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
            'htno'     => ['htno', 'rollno', 'rollnumber', 'hallticket', 'hallticketno', 'hallticketnumber', 'stdregno', 'std_regno', 'regno'],
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
            'subname' => ['subname', 'sub_name', 'subjectname', 'subject_name', 'coursename']
        ];

        $cols = [];
        if (count(array_unique($names)) !== count($names)) {
            fclose($handle);
            throw new \InvalidArgumentException('Duplicate CSV column names.');
        }

        foreach ($aliases as $key => $options) {
            foreach ($options as $option) {
                $cleanOption = strtolower(preg_replace('/[^a-z0-9]/i', '', $option));
                $index = array_search($cleanOption, $names, true);
                if ($index !== false) {
                    $cols[$key] = $index;
                    break;
                }
            }
            if (!isset($cols[$key])) {
                fclose($handle);
                throw new \InvalidArgumentException('CSV is missing required column: ' . $key . ' (expected headers matching ' . implode('/', array_slice($options, 0, 4)) . ')');
            }
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
            JOIN classes c ON c.id = s.class_id 
            JOIN specialization sp ON sp.id = c.spec_id 
            LEFT JOIN curriculum_subjects cs ON cs.id = s.curr_sub_id 
                 AND cs.reg_id = c.reg_id 
                 AND cs.spec_id = c.spec_id 
            WHERE UPPER(TRIM(st.username)) = ? 
              AND UPPER(TRIM(s.subcode)) = ? 
              AND sp.prog_id = ? 
              AND c.reg_id = ?
        ');

        $rows = [];
        $errors = [];
        $seen = [];
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

                    if (count($matches) !== 1) {
                        // Attempt fallback match for supplementary attempts
                        $matchFallback->bind_param('ssii', $roll, $code, $notification['program_id'], $notification['regulation_id']);
                        $matchFallback->execute();
                        $matches = $matchFallback->get_result()->fetch_all(MYSQLI_ASSOC);
                    }

                    if (count($matches) !== 1) {
                        throw new \DomainException("The student ({$roll}) and course ({$code}) do not resolve to an enrolled offering for this program and regulation.");
                    }
                    $m = $matches[0];
                    $profile = (new AssessmentPolicy($this->db))->forSubject((int)$m['subject_id'])['config'];

                    // 2. Parse Result Status (accept P, PASS, F, FAIL, AB, ABSENT, MP, MALPRACTICE, W, WITHHELD)
                    $rawStatus = strtoupper($get('status'));
                    $status = match($rawStatus) {
                        'P', 'PASS', 'PASSED'       => 'PASS',
                        'F', 'FAIL', 'FAILED'       => 'FAIL',
                        'A', 'AB', 'ABS', 'ABSENT'  => 'ABSENT',
                        'MP', 'MALPRACTICE'         => 'MALPRACTICE',
                        'W', 'WH', 'WITHHELD'       => 'WITHHELD',
                        default => in_array($rawStatus, ['PASS', 'FAIL', 'ABSENT', 'WITHHELD', 'MALPRACTICE'], true)
                            ? $rawStatus
                            : throw new \InvalidArgumentException("Invalid result status '{$rawStatus}'. Use P, F, PASS, FAIL, AB, MP, or WITHHELD.")
                    };

                    // 3. Parse Grade & Grade Points
                    $rawGrade = strtoupper($get('grade'));
                    if ($rawGrade === '' || $rawGrade === '-') {
                        $rawGrade = ($status === 'ABSENT') ? 'AB' : (($status === 'FAIL') ? 'F' : 'P');
                    }
                    if (!preg_match('/^[A-Z0-9+_-]{1,6}$/D', $rawGrade)) {
                        throw new \InvalidArgumentException("Invalid grade '{$rawGrade}'.");
                    }
                    $grade = $rawGrade;

                    $pointsRaw = $get('points');
                    if ($pointsRaw !== '' && $pointsRaw !== '-') {
                        $points = MarksNormalizer::number($pointsRaw, 'Grade points');
                    } else {
                        // Fallback points based on status
                        $points = in_array($status, ['FAIL', 'ABSENT', 'MALPRACTICE', 'WITHHELD'], true) ? 0.0 : 4.0;
                    }

                    // 4. Parse Credits (handle fail case where dump credits is 0)
                    $rawCredits = $get('credits');
                    $currCredits = ($m['credits'] !== null && (float)$m['credits'] > 0) ? (float)$m['credits'] : null;
                    if ($rawCredits !== '' && is_numeric($rawCredits)) {
                        $parsedCredits = (float)$rawCredits;
                    } else {
                        $parsedCredits = $currCredits ?? 3.0;
                    }

                    // If student failed, credits earned is 0, registered is curriculum credits
                    $creditsReg = $currCredits ?? ($parsedCredits > 0 ? $parsedCredits : 3.0);
                    $creditsEarned = ($status === 'PASS') ? $creditsReg : 0.0;

                    // 5. Parse Internal (IM), External (EM), and Total marks with resultsold conventions
                    // -7 = no internal / exempt
                    // -1 = absent (AB)
                    // -5 = no external / exempt
                    $imRaw = $get('internal');
                    $emRaw = $get('external');
                    $totRaw = $get('total');

                    $im = null;
                    if ($imRaw !== '' && $imRaw !== '-' && $imRaw !== '-7' && is_numeric($imRaw)) {
                        $imVal = (float)$imRaw;
                        if ($imVal >= 0) {
                            $im = $imVal;
                        }
                    }

                    $em = null;
                    if ($emRaw === '-1' || $grade === 'AB' || $grade === 'Ab' || $status === 'ABSENT') {
                        $status = 'ABSENT';
                        $em = null;
                    } elseif ($emRaw !== '' && $emRaw !== '-' && $emRaw !== '-5' && is_numeric($emRaw)) {
                        $emVal = (float)$emRaw;
                        if ($emVal >= 0) {
                            $em = $emVal;
                        }
                    }

                    $total = null;
                    if ($totRaw !== '' && $totRaw !== '-' && is_numeric($totRaw)) {
                        $totVal = (float)$totRaw;
                        if ($totVal >= 0) {
                            $total = $totVal;
                        }
                    }
                    if ($total === null && ($im !== null || $em !== null)) {
                        $total = ($im ?? 0.0) + ($em ?? 0.0);
                    }

                    // Validate maxima if configured
                    $internalMax = $profile['internal_max'] ?? ($m['cie_max_marks'] !== null ? (float)$m['cie_max_marks'] : 30.0);
                    $externalMax = $profile['net_max'] ?? ($m['see_max_marks'] !== null ? (float)$m['see_max_marks'] : 70.0);

                    // Check column override for maxima if present
                    foreach (['internalmax' => &$internalMax, 'externalmax' => &$externalMax] as $colName => &$maxVar) {
                        $idx = array_search($colName, $names, true);
                        if ($idx !== false && trim((string)$cells[$idx]) !== '') {
                            $upMax = MarksNormalizer::number($cells[$idx], $colName);
                            $maxVar = $upMax;
                        }
                    }
                    unset($maxVar);

                    if ($im !== null && $im > $internalMax) {
                        throw new \InvalidArgumentException("Internal marks ({$im}) exceed configured maximum ({$internalMax}).");
                    }
                    if ($em !== null && $em > $externalMax) {
                        throw new \InvalidArgumentException("External marks ({$em}) exceed configured maximum ({$externalMax}).");
                    }

                    // Validate total if both IM and EM are present and non-zero
                    if ($total !== null && $im !== null && $em !== null && abs($total - ($im + $em)) > 0.5) {
                        throw new \InvalidArgumentException("Total ({$total}) does not equal internal ({$im}) plus external ({$em}).");
                    }

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
                        'internal_marks'     => $im,
                        'external_marks'     => $em,
                        'total_marks'        => $total,
                        'grade_letter'       => $grade,
                        'grade_points'       => $points,
                        'credits_registered' => (float)$creditsReg,
                        'credits_earned'     => (float)$creditsEarned,
                        'result_status'      => $status
                    ];
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
            'source_hash' => hash_file('sha256', $path)
        ];
    }
}

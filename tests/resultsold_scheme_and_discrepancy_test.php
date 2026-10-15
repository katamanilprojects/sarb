<?php
declare(strict_types=1);

require __DIR__ . '/academic_workflows.php';
require_once dirname(__DIR__) . '/jntuacea/services/ExamResultsService.php';
require_once dirname(__DIR__) . '/jntuacea/services/AttainmentPolicyService.php';
require_once dirname(__DIR__) . '/jntuacea/core/ResultsCsv.php';
require_once dirname(__DIR__) . '/jntuacea/core/AttainmentCalculator.php';
require_once dirname(__DIR__) . '/jntuacea/seeassessment.class.php';

echo "\n--- Running Resultsold Scheme, 3-Tier SEE Attainment & CIA Discrepancy Tests ---\n";

$examService = \Services\ExamResultsService::getInstance();

// 1. Regulation Grade Band Midpoint Strategy
$seeObj = new \SEEAssessment();
$pctS = $seeObj->resolveRegulationGradePercentage('R20', 'S', 9.0);
check($pctS >= 85.0 && $pctS <= 95.0, "R20 S Grade midpoint resolved within expected range (got $pctS)");

$pctA = $seeObj->resolveRegulationGradePercentage('R20', 'A', 8.0);
check($pctA >= 70.0 && $pctA <= 80.0, "R20 A Grade midpoint resolved within expected range (got $pctA)");

$pctB = $seeObj->resolveRegulationGradePercentage('R23', 'B', 7.0);
check($pctB >= 60.0 && $pctB <= 80.0, "R23 B Grade midpoint resolved within expected range (got $pctB)");

$pctF = $seeObj->resolveRegulationGradePercentage('R20', 'F', 0.0);
check($pctF === 0.0, "R20 F Grade midpoint resolved to 0.0");
echo "✓ Regulation grade band midpoint resolution verified.\n";

// 2. 3-Tier Attainment Calculator Unit Test
// Tier 2: QP Weights Distribution
$cos = [
    ['id' => 101, 'co_number' => 1],
    ['id' => 102, 'co_number' => 2],
    ['id' => 103, 'co_number' => 3]
];
$qpWeights = [
    101 => 0.50, // 50% of QP marks map to CO1
    102 => 0.30, // 30% of QP marks map to CO2
    103 => 0.20  // 20% of QP marks map to CO3
];
$students = [
    [
        'entry_mode' => 'DIRECT',
        'external_marks' => 50.0,
        'max_marks' => 70.0,
        'evidence_basis' => 'METADATA_DISTRIBUTION'
    ],
    [
        'entry_mode' => 'DIRECT',
        'external_marks' => 35.0,
        'max_marks' => 70.0,
        'evidence_basis' => 'METADATA_DISTRIBUTION'
    ]
];
$thresholds = [1 => 50.0, 2 => 60.0, 3 => 70.0];
$calcTier2 = \College\AttainmentCalculator::external($cos, $students, 50.0, $thresholds, $qpWeights);
check(isset($calcTier2['data'][1]), "Tier 2 Attainment calculated for CO 1");
check(isset($calcTier2['data'][2]), "Tier 2 Attainment calculated for CO 2");
check(isset($calcTier2['data'][3]), "Tier 2 Attainment calculated for CO 3");
check($calcTier2['data'][1]['basis'] === 'QP_METADATA_DISTRIBUTION', "Tier 2 basis is QP_METADATA_DISTRIBUTION");
check($calcTier2['mode'] === 'DIRECT', "Tier 2 returns DIRECT mode");

// Tier 3: Uniform Fallback Distribution Across All COs
$calcTier3 = \College\AttainmentCalculator::external($cos, $students, 50.0, $thresholds);
check(isset($calcTier3['data'][1]), "Tier 3 Attainment calculated uniformly for CO 1");
check(isset($calcTier3['data'][2]), "Tier 3 Attainment calculated uniformly for CO 2");
check($calcTier3['data'][1]['basis'] === 'RESULTS_REGULATION_RANGE', "Tier 3 basis is RESULTS_REGULATION_RANGE");
echo "✓ 3-Tier AttainmentCalculator external calculation verified.\n";

// 3. Database Integration: Setup notification matching test subject
$subjectId = (int)$testSub;
$subInfo = $db->query("
    SELECT s.id, s.subcode, s.sub_fullname, c.id class_id, c.reg_id, c.yearsem, c.acad_year, sp.prog_id 
    FROM subjects s 
    JOIN classes c ON c.id = s.class_id 
    JOIN specialization sp ON sp.id = c.spec_id 
    WHERE s.id = $subjectId
")->fetch_assoc();

check($subInfo !== null, "Found test subject info for ID $subjectId");

$superUser = $db->query("SELECT id, username FROM users WHERE role='superadmin' LIMIT 1")->fetch_assoc();
$userId = (int)$superUser['id'];
$_SESSION = ['role' => 'superadmin', 'userid' => $userId, 'user' => $superUser['username']];

$enrolled = $db->query("
    SELECT st.id, st.username 
    FROM students st 
    JOIN student_sub ss ON ss.stu_id = st.id 
    WHERE ss.sub_id = $subjectId 
    ORDER BY st.id 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

check(count($enrolled) >= 2, "Found at least 2 enrolled students in test subject");

$notifCode = 'TEST_RESOLD_' . bin2hex(random_bytes(4));
$notifRes = $examService->createNotification([
    'notification_code' => $notifCode,
    'title'             => 'Test Exam Notification for Legacy Scheme',
    'program_id'        => (int)$subInfo['prog_id'],
    'regulation_id'     => (int)$subInfo['reg_id'],
    'academic_year'     => $subInfo['acad_year'],
    'yearsem'           => $subInfo['yearsem'],
    'month_year'        => 'Nov 2026',
    'release_date'      => date('Y-m-d')
], $userId);

check($notifRes['status'] === 1, "Created test exam notification (ID: {$notifRes['id']})");
$notifId = (int)$notifRes['id'];

// 4. Test ResultsCsv legacy aliases, sentinel values, and fail credits
$csvRows = [];
$csvRows[] = "std_regno,sub_code,sub_name,im,em,total,result,credits,grade,grade_points";

// Student 1: Regular Pass
$csvRows[] = "{$enrolled[0]['username']},{$subInfo['subcode']},{$subInfo['sub_fullname']},28,48,76,P,3,A,8";

// Student 2: Failed student with legacy credits = 0 and result = F
$csvRows[] = "{$enrolled[1]['username']},{$subInfo['subcode']},{$subInfo['sub_fullname']},12,15,27,F,0,F,0";

// If student 3 exists, test absent with em = -1 and grade = Ab
if (isset($enrolled[2])) {
    $csvRows[] = "{$enrolled[2]['username']},{$subInfo['subcode']},{$subInfo['sub_fullname']},18,-1,18,F,0,Ab,0";
}

$testCsv = tempnam(sys_get_temp_dir(), 'cea-legacy-');
file_put_contents($testCsv, implode("\n", $csvRows));

$previewRes = $examService->previewResultsCsv($notifId, $testCsv);
check($previewRes['status'] === 1, "ResultsCsv preview succeeded for legacy headers and sentinel values");
check(count($previewRes['rows']) >= 2, "Preview returned expected parsed rows");

// Verify parsed values in preview
$pRow1 = $previewRes['rows'][0];
check($pRow1['htno'] === strtoupper($enrolled[0]['username']), "Row 1 htno mapped correctly");
check((float)$pRow1['internal_marks'] === 28.0, "Row 1 internal marks parsed from im");
check((float)$pRow1['external_marks'] === 48.0, "Row 1 external marks parsed from em");
check($pRow1['result_status'] === 'PASS', "Row 1 result P normalized to PASS");

$pRow2 = $previewRes['rows'][1];
check($pRow2['result_status'] === 'FAIL', "Row 2 result F normalized to FAIL");
check($pRow2['grade_letter'] === 'F', "Row 2 grade F preserved");

if (isset($enrolled[2])) {
    $pRow3 = $previewRes['rows'][2];
    check($pRow3['result_status'] === 'ABSENT', "Row 3 em=-1/grade=Ab normalized to ABSENT");
    check($pRow3['grade_letter'] === 'AB', "Row 3 grade letter set to AB");
}

echo "✓ ResultsCsv validation and preview of legacy schema verified.\n";

// 5. Import into database
$importRes = $examService->importResultsCsv($notifId, $testCsv, $userId);
check($importRes['status'] === 1, "Imported legacy CSV: " . ($importRes['msg'] ?? ''));
unlink($testCsv);

// 6. Review and Publish Results
$reviewRes = $examService->reviewResults($notifId, $userId, 'Verified legacy dump test results');
check($reviewRes['status'] === 1, "Results reviewed successfully");

$publishRes = $examService->togglePublishStatus($notifId, 1, $userId);
check($publishRes['status'] === 1, "Results published successfully");

// 7. Student Portal View Verification
$stu1Res = $examService->getStudentSubjectResult($enrolled[0]['username'], $subjectId, $subInfo['subcode']);
check($stu1Res !== null, "getStudentSubjectResult returned published result for student 1");
check($stu1Res['grade_letter'] === 'A', "Student 1 received Grade A");
check((float)$stu1Res['total_marks'] === 76.0, "Student 1 received Total 76");
check($stu1Res['result_status'] === 'PASS', "Student 1 received PASS status");

$stu2Res = $examService->getStudentSubjectResult($enrolled[1]['username'], $subjectId, $subInfo['subcode']);
check($stu2Res !== null, "getStudentSubjectResult returned published result for student 2");
check($stu2Res['result_status'] === 'FAIL', "Student 2 received FAIL status");
echo "✓ Student portal subject-level result retrieval verified.\n";

// 8. Continuous Internal Assessment (CIA) Discrepancy Audit
$reconAll = $examService->getNotificationCiaDiscrepancies($notifId, null, false);
check($reconAll['status'] === 1, "getNotificationCiaDiscrepancies returned status 1");
check($reconAll['summary']['total_records'] >= 2, "Reconciliation audited >= 2 records");
check(isset($reconAll['summary']['matches']), "Reconciliation summary contains matches count");
check(isset($reconAll['summary']['rounding_variances']), "Reconciliation summary contains variances count");
check(isset($reconAll['summary']['discrepancies']), "Reconciliation summary contains discrepancies count");
check(count($reconAll['rows']) >= 2, "Reconciliation returned detailed audit rows");

$firstReconRow = $reconAll['rows'][0];
check(isset($firstReconRow['calculated_cia']), "Row contains calculated_cia");
check(isset($firstReconRow['uploaded_internal']), "Row contains uploaded_internal");
check(isset($firstReconRow['reconcile_status']), "Row contains reconcile_status");
echo "✓ CIA reconciliation discrepancy audit calculation verified.\n";

// 9. Synchronize to SEE Marks (Mode C Auto-Sync)
$syncRes = $examService->syncResultsToSeeMarks($subjectId, $userId, $notifId, true, 'Test sync execution');
check($syncRes['status'] === 1, "syncResultsToSeeMarks succeeded: " . ($syncRes['msg'] ?? ''));
check($syncRes['synced_count'] >= 1, "Synced at least 1 record to external_assessment_marks");

$syncedRow = $db->query("
    SELECT external_marks, evidence_basis 
    FROM external_assessment_marks 
    WHERE subject_id = $subjectId AND student_id = {$enrolled[0]['id']}
")->fetch_assoc();

check($syncedRow !== null, "external_assessment_marks record exists for student 1");
check((float)$syncedRow['external_marks'] === 48.0, "External marks 48.0 synced accurately");
check(in_array($syncedRow['evidence_basis'], ['RESULT_SCALE', 'DIRECT_SCALE', 'AGGREGATE_ESTIMATE']), "evidence_basis set to {$syncedRow['evidence_basis']}");

// 10. SEE Assessment CO Attainment Integration
$seeAttainment = $seeObj->calculateSEECOAttainment($subjectId);
check(isset($seeAttainment['data']), "calculateSEECOAttainment executed successfully and returned data");
check($seeAttainment['status'] === 1, "calculateSEECOAttainment returned status 1");
echo "✓ Mode C sync and SEE CO attainment integration verified.\n";

// Cleanup test notification and results
$db->query("DELETE FROM exam_results WHERE notification_id = $notifId");
$db->query("DELETE FROM exam_notifications WHERE id = $notifId");

echo "\n--- ALL TESTS PASSED SUCCESSFULLY! ---\n";

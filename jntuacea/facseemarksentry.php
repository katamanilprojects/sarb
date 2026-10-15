<?php
require_once __DIR__ . '/bootstrap.php';

// facseemarksentry.php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_SEE_MARKS');

$page_title = "SEE Question-Wise Marks Entry";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

if (empty($_GET['sub_id']) || empty($_GET['component_id'])) {
    header("Location: facseemarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$component_id = intval($_GET['component_id']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$students = $facultyObj->getMappedStudents($sub_id);
require_once __DIR__.'/core/AssessmentPolicy.php';
try{(new \College\AssessmentPolicy(\DBCredentials::getInstance()->getConnection()))->questions($sub_id,$component_id);}
catch(DomainException $e){\College\RequestSecurity::deny($e->getMessage());}
$questions = $ciaObj->getQuestionsByComponent($component_id);

if (empty($questions)) {
    $_SESSION['err'] = "Please configure SEE Question Paper metadata first.";
    header("Location: facseemarks.php?sub_id=$sub_id");
    exit;
}

// 1. Handle CSV Export
if (isset($_GET['download_csv'])) {
    \Services\FeatureManager::requireAction('MOD_SEE_MARKS','export');
    header('Content-Type: text/csv; charset=utf-8');
    $filename = "SEE_Marks_Template_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $subjectDetails['data']['subcode'] ?? 'SUB') . ".csv";
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    $headers = array_merge(['StudentID', 'RollNumber', 'StudentName'], array_column($questions, 'question_label'));
    fputcsv($out, $headers);
    foreach ($students as $stu) {
        $row = [$stu['id'], $stu['username'], $stu['name']];
        foreach ($questions as $q) {
            $existing = $ciaObj->checkStudentMarks($stu['id'], $q['id']);
            $row[] = ($existing !== false && $existing !== null) ? $existing : '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// Both imports and grid submissions validate the complete operation before writing.
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['upload_csv'])||isset($_POST['submit_grid_marks']))) {
    \FeatureManager::requireWriteAccess('MOD_SEE_MARKS');
    require_once __DIR__.'/services/QuestionMarksService.php';
    try {
        $writer=new \Services\QuestionMarksService();
        if(isset($_POST['upload_csv'])){
            if(empty($_FILES['csv_file']['tmp_name'])||!is_uploaded_file($_FILES['csv_file']['tmp_name'])){throw new InvalidArgumentException('Choose a CSV file.');}
            $count=$writer->importCsv($sub_id,$component_id,$_FILES['csv_file']['tmp_name']);
        }else{$count=$writer->save($sub_id,$component_id,$_POST['marks']??[]);}
        $_SESSION['succ']='Saved '.$count.' validated question marks. Reconsolidate detailed totals after corrections.';
        $next=($_POST['submit_grid_marks']??'')==='proceed'?'facseemarkscondensed.php':'facseemarksentry.php';
        header("Location: $next?sub_id=$sub_id&component_id=$component_id");exit;
    } catch(Throwable $e){$_SESSION['err']=$e->getMessage();}
}

require_once("facheader.php");
?>

<div class="container-fluid my-4 px-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Question-Wise External Marks Entry (Mode A: Detailed QP)</h5>
            <div>
                <a href="facseemarkscondensed.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-check2-all me-1"></i> Condensed Review
                </a>
                <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Hub
                </a>
            </div>
        </div>
        <div class="card-header bg-light">
            <strong>Course:</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode'] ?? ''); ?>)
            | <strong>Total Students:</strong> <?= count($students); ?>
            | <strong>Questions Configured:</strong> <?= count($questions); ?>
        </div>
        <div class="card-body">
            <?= \FeatureManager::renderReadOnlyBanner('MOD_SEE_MARKS'); ?>
            <?php if (!empty($_SESSION['succ'])): ?><div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['succ']; unset($_SESSION['succ']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (!empty($_SESSION['err'])): ?><div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['err']; unset($_SESSION['err']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <!-- CSV Upload / Download Ribbon -->
            <div class="card bg-light mb-4 border">
                <div class="card-body py-2">
                    <form method="POST" enctype="multipart/form-data" class="row align-items-center g-2">
                        <div class="col-md-3">
                            <a href="facseemarksentry.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>&download_csv=1" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Download CSV Template
                            </a>
                        </div>
                        <div class="col-md-6">
                            <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="upload_csv" class="btn btn-primary btn-sm w-100" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                                <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload Marks CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Interactive Entry Grid -->
            <form method="POST">
                <div class="table-responsive border" style="max-height: 600px;">
                    <table class="table table-bordered table-sm align-middle text-center mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th style="min-width: 130px; position: sticky; left: 0; z-index: 3;" class="bg-dark">Roll Number</th>
                                <th style="min-width: 180px; position: sticky; left: 130px; z-index: 3;" class="bg-dark text-start">Student Name</th>
                                <?php foreach ($questions as $q): ?>
                                    <th style="min-width: 75px;">
                                        Q<?= htmlspecialchars($q['question_label']); ?><br>
                                        <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= floatval($q['marks']); ?>M</span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $stu): ?>
                                <tr>
                                    <td class="fw-bold bg-light" style="position: sticky; left: 0; z-index: 2;"><?= htmlspecialchars($stu['username']); ?></td>
                                    <td class="text-start bg-light" style="position: sticky; left: 130px; z-index: 2;"><?= htmlspecialchars($stu['name']); ?></td>
                                    <?php foreach ($questions as $q): 
                                        $val = $ciaObj->checkStudentMarks($stu['id'], $q['id']);
                                    ?>
                                        <td>
                                            <input type="number" step="any" min="0" max="<?= $q['marks']; ?>" 
                                                   name="marks[<?= $stu['id']; ?>][<?= $q['id']; ?>]" 
                                                   value="<?= ($val !== false && $val !== null) ? $val : ''; ?>" 
                                                   class="form-control form-control-sm text-center p-1"
                                                   style="font-size: 0.85rem;"
                                                   <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'readonly' : ''; ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to SEE Hub
                    </a>
                    <div>
                        <button type="submit" name="submit_grid_marks" value="save" class="btn btn-primary px-4 me-2" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                            <i class="bi bi-save me-1"></i> Save Question Scores
                        </button>
                        <button type="submit" name="submit_grid_marks" value="proceed" class="btn btn-success px-4" onclick="this.form.action='facseemarksentry.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>&proceed=1'" <?= \FeatureManager::isFacultyReadOnly('MOD_SEE_MARKS') ? 'disabled' : ''; ?>>
                            Save & Proceed to Review <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>

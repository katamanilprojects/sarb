<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_CIA_MARKS');

require_once "faculty.class.php";
require_once "cia.class.php";

$page_title = "Bulk Day-to-Day Marks Entry";

if (empty($_GET['sub_id']) || empty($_GET['assessment_number'])) {
    $_SESSION['err'] = "Invalid request.";
    header("Location: facciamarks.php");
    exit;
}

$facultyObj = new Faculty();
$ciaObj = new CIA();

$sub_id = intval($_GET['sub_id']);
$assessment_number = intval($_GET['assessment_number']);
$url_get_data = "sub_id=$sub_id&assessment_number=$assessment_number";

$prg_res = $facultyObj->getPrgCodeBySubID($sub_id);
if ($prg_res['sub_type'] != "lab" || empty($prg_res['end_date']) || $prg_res['end_date'] >= date('Y-m-d')) {
    $_SESSION['err'] = "Bulk entry is only available for lab subjects after the class end date.";
    header("Location: facciacomp.php?$url_get_data");
    exit;
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$students = $facultyObj->getMappedStudents($sub_id);
$mapped_ids = array_column($students, 'id');

// Build d2d component data
$all_components = $ciaObj->getAssessmentComponents($sub_id, $assessment_number);
$d2d_components = [];
if (!empty($all_components['data'])) {
    foreach ($all_components['data'] as $comp) {
        if ($comp['component_type'] === 'Day-to-Day') {
            $questions = $ciaObj->getQuestionsByComponent($comp['id']);
            $marks_exist = !empty($ciaObj->getMarksByComponent($comp['id']));
            if (!empty($questions)) {
                $d2d_components[] = [
                    'id'          => $comp['id'],
                    'question_id' => $questions[0]['id'],
                    'date_raw'    => $questions[0]['question_label'],
                    'max_marks'   => $questions[0]['marks'],
                    'marks_exist' => $marks_exist,
                ];
            }
        }
    }
}

// POST: manual form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marks']) && is_array($_POST['marks'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    $added = [];
    $skipped = [];
    foreach ($_POST['marks'] as $comp_id => $stu_marks) {
        $comp_id = intval($comp_id);
        if (!empty($ciaObj->getMarksByComponent($comp_id))) {
            // find date label for message
            foreach ($d2d_components as $dc) {
                if ($dc['id'] == $comp_id) {
                    $skipped[] = date('d-m-Y', strtotime($dc['date_raw']));
                    break;
                }
            }
            continue;
        }
        $questions = $ciaObj->getQuestionsByComponent($comp_id);
        if (empty($questions)) continue;
        $question_id = $questions[0]['id'];
        $max_marks   = floatval($ciaObj->getQuestionDetails($question_id)['marks']);
        $date_label  = $questions[0]['question_label'];
        foreach ($stu_marks as $stu_id => $marks_val) {
            $stu_id = intval($stu_id);
            if (!in_array($stu_id, $mapped_ids)) continue;
            $marks_obtained = floatval($marks_val);
            if ($marks_obtained > $max_marks) continue;
            $ciaObj->addOrUpdateStudentMarks($stu_id, $question_id, $marks_obtained);
        }
        $added[] = date('d-m-Y', strtotime($date_label));
    }
    if (!empty($added)) {
        $_SESSION['succ'] = "Marks saved for: " . implode(', ', $added) . ".";
    }
    if (!empty($skipped)) {
        $_SESSION['err'] = "Skipped (already have marks): " . implode(', ', $skipped) . ".";
    }
    header("Location: facciacomp.php?$url_get_data");
    exit;
}

// POST: CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    \FeatureManager::requireWriteAccess('MOD_CIA_MARKS');
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        // Build map using strict d-m-Y format as key
        $col_map = [];
        foreach ($d2d_components as $dc) {
            if (!$dc['marks_exist']) {
                $formatted_key = date('d-m-Y', strtotime($dc['date_raw']));
                $col_map[$formatted_key] = $dc;
            }
        }

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($file);
        $added_csv = [];

        while ($row = fgetcsv($file)) {
            $stu_id = intval($row[0]);
            if (empty($stu_id) || !in_array($stu_id, $mapped_ids)) continue;

            foreach ($headers as $idx => $hdr) {
                if ($idx < 2) continue;

                $hdr_clean = trim($hdr);

                // Strictly validate d-m-Y format
                $date_obj = DateTime::createFromFormat('!d-m-Y', $hdr_clean);
                if (!$date_obj || $date_obj->format('d-m-Y') !== $hdr_clean) {
                    continue;
                }

                if (!isset($col_map[$hdr_clean])) continue;

                $dc = $col_map[$hdr_clean];

                if (!isset($row[$idx]) || $row[$idx] === '') continue;

                $marks_obtained = floatval($row[$idx]);
                $max = floatval($ciaObj->getQuestionDetails($dc['question_id'])['marks']);

                if ($marks_obtained > $max || $marks_obtained < 0) continue;

                $ciaObj->addOrUpdateStudentMarks($stu_id, $dc['question_id'], $marks_obtained);
                $added_csv[$hdr_clean] = $hdr_clean;
            }
        }
        fclose($file);

        if (!empty($added_csv)) {
            $_SESSION['succ'] = "CSV marks uploaded for: " . implode(', ', $added_csv) . ".";
        } else {
            $_SESSION['err'] = "No marks were imported. Please make sure date headers follow the DD-MM-YYYY format.";
        }
        header("Location: facciacomp.php?$url_get_data");
        exit;
    } else {
        $_SESSION['err'] = "Please upload a valid CSV file.";
    }
}

// GET: CSV template download (only columns without marks)
if (isset($_GET['download_csv'])) {
    $pending = array_filter($d2d_components, fn($dc) => !$dc['marks_exist']);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bulk_d2d_template.csv"');
    $out = fopen('php://output', 'w');
    // Export strictly in d-m-Y format
    $date_headers = array_map(fn($dc) => date('d-m-Y', strtotime($dc['date_raw'])), array_values($pending));
    fputcsv($out, array_merge(['Student ID', 'Student Adm. & Name'], $date_headers));
    foreach ($students as $st) {
        fputcsv($out, array_merge([$st['id'], $st['username'] . ' - ' . $st['name']], array_fill(0, count($date_headers), '')));
    }
    fclose($out);
    exit;
}

require_once "facheader.php";
?>

<div class="container">
    <br>
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Bulk Day-to-Day Marks Entry — CIA <?php echo $assessment_number; ?></div>
                <div class="card-header">
                    <strong>Subject:</strong> <?php echo htmlspecialchars($subjectDetails['data']['sub_fullname']); ?>
                    (<?php echo htmlspecialchars($subjectDetails['data']['subcode']); ?>) — <?php echo htmlspecialchars($prg_res['classname']); ?>
                </div>
                <div class="card-body">
                    <?= \FeatureManager::renderReadOnlyBanner('MOD_CIA_MARKS'); ?>
                    <?php if (!empty($_SESSION['succ'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['succ']; unset($_SESSION['succ']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['err'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['err']; unset($_SESSION['err']); ?></div>
                    <?php endif; ?>

                    <?php if (empty($d2d_components)): ?>
                        <div class="alert alert-warning">No Day-to-Day components found.</div>
                    <?php else: ?>

                    <!-- Section 1: CSV -->
                    <div class="card mb-3">
                        <div class="card-header">CSV Export / Import</div>
                        <div class="card-body">
                            <a href="facbulkd2dmarks.php?<?php echo $url_get_data; ?>&download_csv=1" class="btn btn-success">Download CSV Template</a>
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="file" name="csv_file" accept=".csv" required>
                                <button type="submit" name="upload_csv" class="btn btn-primary ms-2" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Upload CSV</button>
                            </form>
                        </div>
                    </div>

                    <!-- Section 2: Manual entry -->
                    <div class="card">
                        <div class="card-header">Manual Marks Entry</div>
                        <div class="card-body">
                            <!-- Component checkboxes -->
                            <div class="mb-3">
                                <?php foreach ($d2d_components as $dc):
                                    $fmt = date('d-m-Y', strtotime($dc['date_raw']));
                                    $disabled = $dc['marks_exist'] ? 'disabled' : '';
                                    $checked  = 'checked';
                                ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input d2d-toggle" type="checkbox"
                                        id="chk_<?php echo $dc['id']; ?>"
                                        data-comp="<?php echo $dc['id']; ?>"
                                        <?php echo $checked; ?> <?php echo $disabled; ?>>
                                    <label class="form-check-label <?php echo $dc['marks_exist'] ? 'text-muted' : ''; ?>"
                                        for="chk_<?php echo $dc['id']; ?>">
                                        <?php echo $fmt; ?><?php echo $dc['marks_exist'] ? ' <em>(already entered)</em>' : ''; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <form method="post">
                                <div style="overflow-x:auto;">
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th style="position:sticky;left:0;background:#fff;">Adm. No.</th>
                                                <th>Name</th>
                                                <?php foreach ($d2d_components as $dc):
                                                    $fmt = date('d-m-Y', strtotime($dc['date_raw']));
                                                    $max = rtrim(rtrim(number_format(floatval($dc['max_marks']), 1, '.', ''), '0'), '.');
                                                ?>
                                                <th class="d2d-col-<?php echo $dc['id']; ?>" <?php echo $dc['marks_exist'] ? 'style="display:none;"' : ''; ?>>
                                                    <?php echo $fmt; ?><br>(<?php echo $max; ?>)
                                                </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $st): ?>
                                            <tr>
                                                <td style="position:sticky;left:0;background:#fff;"><?php echo htmlspecialchars($st['username']); ?></td>
                                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                                <?php foreach ($d2d_components as $dc): ?>
                                                <td class="d2d-col-<?php echo $dc['id']; ?>" <?php echo $dc['marks_exist'] ? 'style="display:none;"' : ''; ?>>
                                                    <?php if (!$dc['marks_exist']): ?>
                                                    <input type="number"
                                                        name="marks[<?php echo $dc['id']; ?>][<?php echo $st['id']; ?>]"
                                                        min="0" max="<?php echo $dc['max_marks']; ?>"
                                                        step="0.5" class="form-control form-control-sm" style="min-width:70px;" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'readonly' : 'required'; ?>>
                                                    <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-primary" <?= \FeatureManager::isFacultyReadOnly('MOD_CIA_MARKS') ? 'disabled' : ''; ?>>Save Selected Marks</button>
                                <a href="facciacomp.php?<?php echo $url_get_data; ?>" class="btn btn-secondary ms-2">Back</a>
                            </form>
                        </div>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.d2d-toggle').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var compId = this.dataset.comp;
        var show = this.checked;
        document.querySelectorAll('.d2d-col-' + compId).forEach(function(el) {
            el.style.display = show ? '' : 'none';
        });
        // toggle required on inputs in hidden columns
        document.querySelectorAll('.d2d-col-' + compId + ' input').forEach(function(inp) {
            inp.required = show;
        });
    });
});
</script>

<?php require_once "facfooter.php"; ?>

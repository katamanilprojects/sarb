<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Manage Syllabus";
require_once("academicsectionheader.php");
require_once("syllabus.class.php");

$obj = new Syllabus();
$msg = '';
$errmsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_or_update') {
        $data = [
            'prog_id' => (int)($_POST['prog_id'] ?? 0),
            'spec_id' => (int)($_POST['spec_id'] ?? 0),
            'reg_id' => (int)($_POST['reg_id'] ?? 0),
            'yearsem' => trim($_POST['yearsem'] ?? '')
        ];

        $result = $obj->addOrUpdateSyllabusMaster($data, $_FILES['syllabus_pdf'] ?? []);
        if (!empty($result['status'])) {
            $msg = $result['message'] ?? 'Syllabus saved successfully.';
        } else {
            $errmsg = $result['error'] ?? 'Failed to save syllabus.';
        }
    }
}

$programs = $obj->getAllPrograms()['data'] ?? [];
$regulations = $obj->getAllRegulations()['data'] ?? [];
$specializations = $obj->getAllSpecializations()['data'] ?? [];
$yearSemOptions = $obj->getYearSemOptions();
$syllabusList = $obj->getAllSyllabusMasters()['data'] ?? [];

$groupedSyllabusCards = [];
foreach ($syllabusList as $row) {
    $programLabel = trim(($row['prog_shortname'] ?? '') . ' - ' . ($row['prog_fullname'] ?? ''));
    $regulationLabel = $row['regulation'] ?? '';
    $yearSemLabel = $row['yearsem'] ?? '';

    $groupedSyllabusCards[$programLabel][$regulationLabel][$yearSemLabel][] = $row;
}

?>

<div class="container">
    <div class="card shadow-sm">
        <div class="card-header">Manage Syllabus</div>
        <div class="card-body">

            <?php if (!empty($msg)) { ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
            <?php } ?>

            <?php if (!empty($errmsg)) { ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errmsg); ?></div>
            <?php } ?>

            <div class="card mb-4 border-0 bg-light">
                <div class="card-header">Add Syllabus</div>
                <div class="card-body">
                    <form action="academicsectionsyllabus.php" method="post" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="action" value="add_or_update">

                        <div class="col-md-3">
                            <label for="prog_id" class="form-label">Program</label>
                            <select name="prog_id" id="prog_id" class="form-select" required>
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $program) { ?>
                                    <option value="<?= (int)$program['id']; ?>">
                                        <?= htmlspecialchars(($program['prog_shortname'] ?? '') . ' - ' . ($program['prog_fullname'] ?? '')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="spec_id" class="form-label">Specialization</label>
                            <select name="spec_id" id="spec_id" class="form-select" required>
                                <option value="">Select Specialization</option>
                                <?php foreach ($specializations as $spec) { ?>
                                    <option value="<?= (int)$spec['id']; ?>" data-prog-id="<?= (int)$spec['prog_id']; ?>">
                                        <?= htmlspecialchars(($spec['spec_shortname'] ?? '') . ' - ' . ($spec['spec_fullname'] ?? '')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="reg_id" class="form-label">Regulation</label>
                            <select name="reg_id" id="reg_id" class="form-select" required>
                                <option value="">Select Regulation</option>
                                <?php foreach ($regulations as $regulation) { ?>
                                    <option value="<?= (int)$regulation['id']; ?>">
                                        <?= htmlspecialchars($regulation['regulation']) . " - " . htmlspecialchars($regulation['prog_shortname']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="yearsem" class="form-label">Year-Sem</label>
                            <select name="yearsem" id="yearsem" class="form-select" required>
                                <option value="">Select Year-Sem</option>
                                <?php foreach ($yearSemOptions as $yearSem) { ?>
                                    <option value="<?= htmlspecialchars($yearSem); ?>">
                                        <?= htmlspecialchars($yearSem); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="syllabus_pdf" class="form-label">Syllabus PDF</label>
                            <input type="file" name="syllabus_pdf" id="syllabus_pdf" class="form-control" accept="application/pdf,.pdf" required>
                            <small class="text-muted">Only PDF files are allowed.</small>
                        </div>

                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Upload Syllabus</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 bg-light">
                <div class="card-header">Existing Syllabus</div>
                <div class="card-body">

                    <?php if (!empty($groupedSyllabusCards)): ?>
                        <div class="accordion" id="syllabusProgramAccordion">
                            <?php $programIndex = 0;
                            foreach ($groupedSyllabusCards as $programLabel => $regulations): $programIndex++; ?>
                                <div class="accordion-item mb-3">
                                    <h2 class="accordion-header" id="syllabus-heading-program-<?= $programIndex; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#syllabus-collapse-program-<?= $programIndex; ?>" aria-expanded="false" aria-controls="syllabus-collapse-program-<?= $programIndex; ?>">
                                            Program: <?= htmlspecialchars($programLabel); ?>
                                        </button>
                                    </h2>
                                    <div id="syllabus-collapse-program-<?= $programIndex; ?>" class="accordion-collapse collapse" aria-labelledby="syllabus-heading-program-<?= $programIndex; ?>" data-bs-parent="#syllabusProgramAccordion">
                                        <div class="accordion-body">
                                            <div class="accordion" id="syllabusRegAccordion-<?= $programIndex; ?>">
                                                <?php $regIndex = 0;
                                                foreach ($regulations as $regulationLabel => $yearSems): $regIndex++; ?>
                                                    <div class="accordion-item mb-3">
                                                        <h2 class="accordion-header" id="syllabus-heading-reg-<?= $programIndex; ?>-<?= $regIndex; ?>">
                                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#syllabus-collapse-reg-<?= $programIndex; ?>-<?= $regIndex; ?>" aria-expanded="false" aria-controls="syllabus-collapse-reg-<?= $programIndex; ?>-<?= $regIndex; ?>">
                                                                Regulation: <?= htmlspecialchars($regulationLabel); ?>
                                                            </button>
                                                        </h2>
                                                        <div id="syllabus-collapse-reg-<?= $programIndex; ?>-<?= $regIndex; ?>" class="accordion-collapse collapse" aria-labelledby="syllabus-heading-reg-<?= $programIndex; ?>-<?= $regIndex; ?>" data-bs-parent="#syllabusRegAccordion-<?= $programIndex; ?>">
                                                            <div class="accordion-body">
                                                                <div class="accordion" id="syllabusYearSemAccordion-<?= $programIndex; ?>-<?= $regIndex; ?>">
                                                                    <?php $yearSemIndex = 0;
                                                                    foreach ($yearSems as $yearSemLabel => $rows): $yearSemIndex++; ?>
                                                                        <div class="accordion-item mb-3">
                                                                            <h2 class="accordion-header" id="syllabus-heading-yearsem-<?= $programIndex; ?>-<?= $regIndex; ?>-<?= $yearSemIndex; ?>">
                                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#syllabus-collapse-yearsem-<?= $programIndex; ?>-<?= $regIndex; ?>-<?= $yearSemIndex; ?>" aria-expanded="false" aria-controls="syllabus-collapse-yearsem-<?= $programIndex; ?>-<?= $regIndex; ?>-<?= $yearSemIndex; ?>">
                                                                                    Year-Sem: <?= htmlspecialchars($yearSemLabel); ?>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="syllabus-collapse-yearsem-<?= $programIndex; ?>-<?= $regIndex; ?>-<?= $yearSemIndex; ?>" class="accordion-collapse collapse" aria-labelledby="syllabus-heading-yearsem-<?= $programIndex; ?>-<?= $regIndex; ?>-<?= $yearSemIndex; ?>" data-bs-parent="#syllabusYearSemAccordion-<?= $programIndex; ?>-<?= $regIndex; ?>">
                                                                                <div class="accordion-body p-0">
                                                                                    <div class="table-responsive">
                                                                                        <table class="table table-bordered table-striped align-middle mb-0">
                                                                                            <thead>
                                                                                                <tr>
                                                                                                    <th>Syllabus Title</th>
                                                                                                    <th>Uploaded On</th>
                                                                                                    <th>Action</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach ($rows as $row): ?>
                                                                                                    <tr>
                                                                                                        <td><?= htmlspecialchars($row['syllabus_title'] ?? ''); ?></td>
                                                                                                        <td><?= !empty($row['uploaded_on']) ? date('d-m-Y h:i A', strtotime($row['uploaded_on'])) : ''; ?></td>
                                                                                                        <td>
                                                                                                            <a href="<?= htmlspecialchars($row['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View PDF</a>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                <?php endforeach; ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No syllabus records found.</div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const progSelect = document.getElementById('prog_id');
        const specSelect = document.getElementById('spec_id');
        const originalOptions = Array.from(specSelect.querySelectorAll('option'));

        function filterSpecializations() {
            const selectedProgId = progSelect.value;
            specSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Specialization';
            specSelect.appendChild(defaultOption);

            originalOptions.forEach(function(option) {
                if (!option.value) {
                    return;
                }

                if (option.getAttribute('data-prog-id') === selectedProgId) {
                    specSelect.appendChild(option.cloneNode(true));
                }
            });
        }

        progSelect.addEventListener('change', filterSpecializations);
    });
</script>

<?php require_once("academicsectionfooter.php"); ?>
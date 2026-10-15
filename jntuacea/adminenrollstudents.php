<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Redirect if dept_id is not provided - Early exit is good
if (empty($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Initialize Admin object
$obj = new Admin();

// Store POST data in local variables for cleaner access
$dept_id = $_POST['dept_id'] ?? null;
$dept_fullname = $_POST['dept_fullname'] ?? null;
$spec_fullname = $_POST['spec_fullname'] ?? null;
$class_id = $_POST['class_id'] ?? null;
$class_fullname = $_POST['class_fullname'] ?? null;

$err = '';
$msg = '';

// --- Begin: Import from previous class support ---
function getPreviousClassDetails($current_yearsem, $current_acad_year)
{
    $sequence = [
        "I Sem",
        "II Sem",
        "III Sem",
        "IV Sem",
        "V Sem",
        "VI Sem",
        "VII Sem",
        "VIII Sem",
        "I Yr - I Sem",
        "I Yr - II Sem",
        "II Yr - I Sem",
        "II Yr - II Sem",
        "III Yr - I Sem",
        "III Yr - II Sem",
        "IV Yr - I Sem",
        "IV Yr - II Sem"
    ];

    $index = array_search($current_yearsem, $sequence);

    if ($index === false || $index === 0) {
        return [null, null];
    }

    $prev_yearsem = $sequence[$index - 1];
    $prev_acad_year = $current_acad_year;

    // Determine academic year shift
    // These semesters typically mark the beginning of a new academic year within a course sequence
    $requires_acad_shift = in_array($current_yearsem, [
        "III Sem",
        "V Sem",
        "VII Sem",
        "II Yr - I Sem",
        "III Yr - I Sem",
        "IV Yr - I Sem"
    ]);

    if ($requires_acad_shift) {
        // Decrement academic year: e.g. "2023-2024" → "2022-2023"
        [$start, $end] = explode("-", $current_acad_year);
        $prev_acad_year = (intval($start) - 1) . "-" . (intval($end) - 1);
    }

    return [$prev_yearsem, $prev_acad_year];
}

$previous_students = [];
$showImportOption = false;
$prev_yearsem = ''; // Initialize to prevent undefined variable notice

if ($class_id) {
    $current_class = $obj->getClassById($class_id);
    if ($current_class) {
        $spec_id = $current_class['spec_id'];
        $current_yearsem = $current_class['yearsem'];
        $current_acad_year = $current_class['acad_year'];

        list($prev_yearsem, $prev_acad_year) = getPreviousClassDetails($current_yearsem, $current_acad_year);

        if ($prev_yearsem && $prev_acad_year) {
            $current_section = $current_class['section'] ?? null;
            $prev_class = $obj->getClassBySpecYearsemAndAcadYear($spec_id, $prev_yearsem, $prev_acad_year, $current_section ?: null);
            if ($prev_class) {
                $previous_students = $obj->getStudentsByClass($prev_class['id']);
                $showImportOption = !empty($previous_students);
            }
        }
    }
}
// --- End: Import from previous class support ---

// Handle cancel action - always clear session on explicit cancel
if (isset($_POST['cancel'])) {
    unset($_SESSION['uploaded_students']);
    // Optional: Redirect here to clear POST data and prevent resubmission
    // header("Location: adminenrollstudents.php?class_id={$class_id}&dept_id={$dept_id}&dept_fullname={$dept_fullname}&spec_fullname={$spec_fullname}&class_fullname={$class_fullname}");
    // exit();
}

// Clear session if a new upload/import process is initiated without confirmation
// This prevents remnants from previous attempts if user changes mind without clicking cancel
if (!isset($_SESSION['uploaded_students']) && (isset($_FILES['csv_file']) || isset($_POST['confirm_prev']))) {
    unset($_SESSION['uploaded_students']);
}

// Handle file upload and validation
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK && $_FILES['csv_file']['type'] == "text/csv") {
    $tmp_name = $_FILES['csv_file']['tmp_name'];
    $students = [];
    if (($handle = fopen($tmp_name, "r")) !== false) {
        fgetcsv($handle); // Skip header row
        $rowno = 2;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // Validate and process each row
            if (isset($data[0]) && strlen(trim($data[0])) == 10 && isset($data[1]) && !empty(trim($data[1]))) {
                $date_of_joining = null;
                if (isset($data[2]) && !empty(trim($data[2]))) {
                    $date_of_joining = trim($data[2]);
                    // Validate date format
$formats = ['Y-m-d', 'd/m/y', 'd-m-Y'];
$normalizedDate = null;

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $date_of_joining);
    if ($dt && $dt->format($format) === $date_of_joining) {
        // Successfully parsed, normalize it
        $normalizedDate = $dt->format('Y-m-d');
        break;
    }
}

$date_of_joining = $normalizedDate;
if (!$date_of_joining) {
    $err = "Invalid Date of Joining format at Row: " . $rowno . 
           ". Expected format: YYYY-MM-DD or DD/MM/YY or DD-MM-YYYY";
   break;
} 
                }
                $students[] = [
                    'rollno' => strtoupper(trim($data[0])),
                    'name' => strtoupper(trim($data[1])),
                    'date_of_joining' => $date_of_joining
                ];
                $rowno++;
            } else {
                $err = "Invalid Roll Number or Name at Row: " . $rowno . " in uploaded CSV file. Roll Number must be 10 characters long and Name cannot be empty.";
                break; // Stop processing on first error
            }
        }
        fclose($handle);
    } else {
        $err = "Could not open uploaded CSV file.";
    }

    if (empty($err)) {
        $_SESSION['uploaded_students'] = $students;
        $msg = "CSV file uploaded successfully. Please **REVIEW** and **CONFIRM** the student list below.";
    }
} elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle specific upload errors
    switch ($_FILES['csv_file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $err = "Uploaded file exceeds maximum allowed size.";
            break;
        case UPLOAD_ERR_PARTIAL:
            $err = "File upload was interrupted. Please try again.";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $err = "Missing a temporary folder for uploads.";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $err = "Failed to write file to disk.";
            break;
        case UPLOAD_ERR_EXTENSION:
            $err = "A PHP extension stopped the file upload.";
            break;
        default:
            $err = "An unknown error occurred during file upload.";
            break;
    }
} elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['type'] != "text/csv" && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $err = "Please upload a valid CSV File.";
}

// If import from previous class is confirmed, populate uploaded_students for preview
if (isset($_POST['confirm_prev']) && !empty($previous_students)) {
    $_SESSION['uploaded_students'] = array_map(function ($s) {
        return [
            'rollno' => strtoupper($s['roll_number']),
            'name' => strtoupper($s['name']),
            'date_of_joining' => $s['date_of_joining'] ?? null
        ];
    }, $previous_students);
    $msg = "Students imported from previous class. Please **REVIEW** and **CONFIRM** the student list below.";
}

// Generate new secret code for form submission to prevent CSRF (good practice)
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">

    <div class="card mt-4">
        <div class="card-header">
            <div class="float-start">
                <?php if (!empty($dept_fullname)): ?>
                    Department of <?= htmlspecialchars($dept_fullname) ?>
                <?php endif; ?>
            </div>
            <div class="float-end">
                <form action="adminviewclasses.php" method="post">
                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname) ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-success">All Classes</button>
                </form>
            </div>
        </div>
        <div class="card-header">
            Specialization: <?= htmlspecialchars($spec_fullname) ?>
        </div>
        <div class="card-header">
            <h5>Class: <?= htmlspecialchars($class_fullname) ?></h5>
        </div>
        <?php if (empty($_SESSION['uploaded_students'])): // Only show these options if no students are currently being previewed 
        ?>
            <div class="card-header">
                <style>
.import-option-box {
    cursor: pointer;
}

.import-option-box input + div {
    position: relative;
    transition: background-color 0.2s, border-color 0.2s;
}

/* Highlight on hover */
.import-option-box:hover div {
    background-color: #f8f9fa;
    border-color: #adb5bd;
}

/* Highlight and tick icon on selected */
.import-option-box input:checked + div {
    border-color: #0d6efd;
    background-color: #e7f1ff;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
}

.check-icon {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 1.2rem;
    display: none;
}

.import-option-box input:checked + div .check-icon {
    display: block;
}
</style>
                <div class="row g-3">
    <div class="col-md-6">
        <label class="import-option-box position-relative w-100">
            <input class="form-check-input d-none" type="radio" name="import_option" id="import_prev" value="import_prev"
                onclick="toggleImportOption('import_prev')">
            <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center">
                <strong>Import Students from Previous Class</strong>
                <small class="text-muted mt-1">(<?= htmlspecialchars($prev_yearsem) ?>)</small>
                <span class="check-icon"><i class="bi bi-check-circle-fill text-success"></i></span>
            </div>
        </label>
    </div>
    <div class="col-md-6">
        <label class="import-option-box position-relative w-100">
            <input class="form-check-input d-none" type="radio" name="import_option" id="upload_csv" value="upload_csv"
                onclick="toggleImportOption('upload_csv')">
            <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center">
                <strong>Upload CSV</strong>
                <small class="text-muted mt-1">(Manual Entry)</small>
                <span class="check-icon"><i class="bi bi-check-circle-fill text-success"></i></span>
            </div>
        </label>
    </div>
</div>
            </div>
        <?php endif; ?>
        <div class="card-body">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success mt-3"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger mt-3"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <?php if (empty($_SESSION['uploaded_students'])): ?>
                <div class="mb-3">
                    <?php if ($showImportOption): ?>
                        <form action="adminenrollstudents.php" method="post" id="csv_import_section">
                            <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>" />
                            <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>" />
                            <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname) ?>" />
                            <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>" />
                            <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>" />
                            <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>" />
                            <input type="hidden" name="confirm_prev" value="1" />
                            <button type="submit" class="btn btn-outline-primary btn-sm ms-3" onclick="this.disabled=true; this.form.submit();">Click Here to Import Students</button>
                        </form>
                    <?php endif; ?>

                    <form action="adminenrollstudents.php" method="post" enctype="multipart/form-data" id="csv_upload_section" class="mt-3">
                        <div class="form-group">
                            <label for="csv_file">Upload CSV:</label>
                            <input type="file" name="csv_file" class="form-control" required />
                        </div>
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>" />
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>" />
                        <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname) ?>" />
                        <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>" />
                        <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>" />
                        <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>" />
                        <button type="submit" class="btn btn-primary mt-3">Upload File</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['uploaded_students']) && !empty($_SESSION['uploaded_students'])) { ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Preview of Students to Enroll (<?= count($_SESSION['uploaded_students']) ?>)</th>
                        </tr>
                        <tr>
                            <th>S.No.</th>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Date of Joining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = 1;
                        foreach ($_SESSION['uploaded_students'] as $student) { ?>
                            <tr>
                                <td><?= htmlspecialchars($sno++) ?></td>
                                <td><?= htmlspecialchars($student['rollno']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['date_of_joining'] ?? 'Not Set') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <form action="adminviewstudents.php" method="post" class="d-inline-block">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>" />
                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname) ?>" />
                    <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>" />
                    <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>" />
                    <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>" />
                    <button type="submit" name="confirm" value="1" class="btn btn-success">Confirm Enrollment</button>
                </form>
                <form action="adminenrollstudents.php" method="post" class="d-inline-block ms-2">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>" />
                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($dept_fullname) ?>" />
                    <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($spec_fullname) ?>" />
                    <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($class_fullname) ?>" />
                    <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>" />
                    <button type="submit" name="cancel" value="cancel" class="btn btn-danger">Cancel</button>
                </form>
            <?php } ?>
        </div>
        <?php if (empty($_SESSION['uploaded_students'])): ?>
            <div class="card-footer" id="csv_upload_section_description">
                <pre>
                * File should be of CSV format.
                * First Row should contain Labels "RollNumber", "Student Name", "Date of Joining (Optional)"
                * From second row, student details should be present (Roll Number must be 10 characters long, Name cannot be empty).
                * Date of Joining format should be YYYY-MM-DD (e.g., 2024-01-15). This field is optional.
            </pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // PHP variable to determine the default active tab
    const defaultActiveOption = '<?php
                                    if (!empty($_SESSION['uploaded_students'])) {
                                        echo 'none'; // No option active if preview is shown
                                    } elseif ($showImportOption && !isset($_POST['confirm_prev']) && !isset($_FILES['csv_file'])) {
                                        echo 'import_prev'; // Default to import if available and no upload/prev confirm action
                                    } else {
                                        echo 'upload_csv'; // Default to upload otherwise (no import option, or after an upload attempt)
                                    }
                                    ?>';

    function toggleImportOption(selectedOption) {
        const csvUploadSection = document.getElementById('csv_upload_section');
        const csvUploadSectionDescription = document.getElementById('csv_upload_section_description');
        const csvImportSection = document.getElementById('csv_import_section');
        const importPrevRadio = document.getElementById('import_prev');
        const uploadCsvRadio = document.getElementById('upload_csv');

        if (selectedOption === 'import_prev') {
            if (csvImportSection) {
                csvImportSection.style.display = 'block';
            }
            if (csvUploadSection) {
                csvUploadSection.style.display = 'none';
                csvUploadSectionDescription.style.display = 'none';
            }
            if (importPrevRadio) importPrevRadio.checked = true;
        } else if (selectedOption === 'upload_csv') {
            if (csvImportSection) {
                csvImportSection.style.display = 'none';
            }
            if (csvUploadSection) {
                csvUploadSection.style.display = 'block';
                csvUploadSectionDescription.style.display = 'block';
            }
            if (uploadCsvRadio) uploadCsvRadio.checked = true;
        } else { // 'none' or other states where forms should be hidden
            if (csvImportSection) {
                csvImportSection.style.display = 'none';
            }
            if (csvUploadSection) {
                csvUploadSection.style.display = 'none';
                csvUploadSectionDescription.style.display = 'none';
            }
            // Ensure no radio button is checked visually if 'none' is selected
            if (importPrevRadio) importPrevRadio.checked = false;
            if (uploadCsvRadio) uploadCsvRadio.checked = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleImportOption(defaultActiveOption);
    });
</script>

<?php
require_once("adminfooter.php");
?>
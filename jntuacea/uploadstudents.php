<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['academic_section', 'admin', 'superadmin'], true)) {
    header("Location: ./");
    exit();
}

$page_title = "Upload Students";
if ($userRole === 'academic_section') {
    require_once("academicsectionheader.php");
} else {
    require_once("adminheader.php");
}

require_once("admin.class.php");
$obj = new Admin();
$classList = $obj->getClasses();

// Handle CSV upload
$msg = ""; // Initialize message
$err = "";
if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $class_id = (int)($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            $err = "Please select a valid target class.";
        } else {
            $tmp_name = $_FILES['csv_file']['tmp_name'];

            if (($handle = fopen($tmp_name, "r")) !== false) {
                // Skip the header row
                fgetcsv($handle);
                $count = 0;
                $rowno = 2;

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if (empty($data[0]) || empty($data[1])) {
                        continue;
                    }
                    $rollno = strtoupper(trim($data[0]));
                    $name = strtoupper(trim($data[1]));
                    $date_of_joining = null;

                    if (!empty($data[2])) {
                        $rawDate = trim($data[2]);
                        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd/m/y', 'd-m-y'];
                        foreach ($formats as $fmt) {
                            $dt = DateTime::createFromFormat($fmt, $rawDate);
                            if ($dt && $dt->format($fmt) === $rawDate) {
                                $date_of_joining = $dt->format('Y-m-d');
                                break;
                            }
                        }
                    }

                    $result = $obj->addStudent($rollno, $name, $class_id, $date_of_joining);

                    if (empty($result['status']) || $result['status'] != 1) {
                        $err = "Error uploading student {$rollno} at row {$rowno}. Please check the file and try again.";
                        break;
                    }
                    $count++;
                    $rowno++;
                }
                fclose($handle);

                if (empty($err)) {
                    $msg = "Successfully uploaded and enrolled {$count} students.";
                }
            } else {
                $err = "Error opening CSV file.";
            }
        }
    } else {
        $err = "Error uploading file. Please select a valid CSV file.";
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-file-earmark-arrow-up me-2"></i>Direct Bulk Upload Students</span>
                    <a href="adminfacst.php" class="btn btn-sm btn-light text-primary">Class Allotment Hub</a>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($err)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
                    <?php endif; ?>

                    <form action="uploadstudents.php" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="class_id" class="form-label fw-bold">Select Target Class:</label>
                            <select name="class_id" id="class_id" class="form-select" required>
                                <option value="">-- Choose Class --</option>
                                <?php
                                if (!empty($classList['data'])) {
                                    foreach ($classList['data'] as $class) {
                                        echo "<option value='{$class['id']}'>" . htmlspecialchars($class['classname'] . " (" . $class['acad_year'] . ") - " . ($class['spec_shortname'] ?? '')) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="csv_file" class="form-label fw-bold">CSV File:</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
                            <div class="form-text small">
                                Columns: <code>Roll Number</code>, <code>Student Name</code>, <code>Date of Joining (Optional)</code>
                            </div>
                        </div>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload & Enroll Students
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-light small text-muted">
                    <strong>CSV Format Requirements:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>First row must be header: <code>RollNumber, Student Name, Date of Joining</code></li>
                        <li>Roll number must be 10 characters (e.g., <code>23001A0501</code>).</li>
                        <li>Optional date of joining format: <code>YYYY-MM-DD</code> or <code>DD-MM-YYYY</code>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
if ($userRole === 'academic_section') {
    require_once("academicsectionfooter.php");
} else {
    require_once("adminfooter.php");
}
?>
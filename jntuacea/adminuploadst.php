<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "Upload Students";
require_once("adminheader.php");

require_once("admin.class.php");
$obj = new Admin();
$classList = $obj->getClasses();

// Handle CSV upload
$msg = ""; // Initialize message
if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $class_id = $_POST['class_id'];
        $tmp_name = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($tmp_name, "r")) !== false) {
            // Skip the header row (assuming the first row is a header)
            fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $rollno = $data[0];
                $name = $data[1];

                $result = $obj->addOrUpdateStudent($rollno, $name, $class_id);

                if (empty($result['status']) || $result['status'] != 1) {
                    // Handle errors (log, display message, etc.)
                    $msg = "Error uploading students. Please try again.";
                    break; // Stop processing if an error occurs
                }
            }
            fclose($handle);

            if (empty($msg)) { // If no error occurred during the loop
                $msg = "Students uploaded successfully.";
            }
        } else {
            $msg = "Error opening CSV file.";
        }
    } else {
        $msg = "Error uploading file.";
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("adminmenu.php");
?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-6">
            <div class="card">
                <div class="card-header">Upload Students</div>
                <div class="card-body">
                    <br />
                    <form action="adminuploadst.php" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="class_id">Class:</label>
                            <select name="class_id" id="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php
                                foreach ($classList['data'] as $class) {
                                    echo "<option value='{$class['id']}'>{$class['classname']} ({$class['acad_year']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="csv_file">CSV File:</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control-file" accept=".csv" required>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                    <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php
require_once("adminfooter.php");
?>
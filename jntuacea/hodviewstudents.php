<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$obj = new HOD();
$class_id = $_POST['class_id'];

// Fetch students for the selected class (including inactive/transferred students)
$studentList = $obj->getStudentsByClass($class_id, true);

?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                Specialization: <?= htmlspecialchars($_POST['spec_fullname']) ?>
            </div>
            <div class="float-end">
                <a href="hodviewclasses.php" class="btn btn-sm btn-outline-success">All Classes</a>
            </div>
        </div>
        <div class="card-header">
            Class: <?= htmlspecialchars($_POST['class_fullname']) ?>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>S.No.</th>
                        <th>Roll Number</th>
                        <th>Student Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($studentList)) {
                        $sno = 1;
                        foreach ($studentList as $student) {
                            $statusBadge = ($student['status'] == 1)
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive / Shifted</span>';
                            echo "<tr>
                        <td>" . $sno++ . "</td>
                        <td>{$student['roll_number']}</td>
                        <td>{$student['name']}</td>
                        <td>{$statusBadge}</td>
                      </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No students found in this class.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php
require_once("adminfooter.php");
?>
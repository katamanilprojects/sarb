<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['academic_section', 'admin', 'superadmin'], true)) {
    header("Location: ./");
    exit();
}

require_once("admin.class.php");
$obj = new Admin();

$class_id = $_POST['class_id'] ?? $_GET['class_id'] ?? null;
$dept_id = $_POST['dept_id'] ?? $_GET['dept_id'] ?? null;
$dept_fullname = $_POST['dept_fullname'] ?? $_GET['dept_fullname'] ?? null;
$spec_fullname = $_POST['spec_fullname'] ?? $_GET['spec_fullname'] ?? null;
$class_fullname = $_POST['class_fullname'] ?? $_GET['class_fullname'] ?? null;

if ($class_id && (empty($dept_id) || empty($dept_fullname) || empty($spec_fullname) || empty($class_fullname))) {
    $current_class = $obj->getClassById($class_id);
    if ($current_class) {
        $class_fullname = $class_fullname ?: ($current_class['classname'] . ' (' . $current_class['acad_year'] . ')');
        $spec_id = $current_class['spec_id'];
        $stmtSpec = $obj->conn->prepare("SELECT s.spec_fullname, s.dept_id, d.dept_fullname FROM specialization s JOIN departments d ON s.dept_id = d.id WHERE s.id = ?");
        $stmtSpec->bind_param("i", $spec_id);
        $stmtSpec->execute();
        $specRow = $stmtSpec->get_result()->fetch_assoc();
        if ($specRow) {
            $dept_id = $dept_id ?: $specRow['dept_id'];
            $dept_fullname = $dept_fullname ?: $specRow['dept_fullname'];
            $spec_fullname = $spec_fullname ?: $specRow['spec_fullname'];
        }
    }
}

// Redirect if dept_id or class_id is not provided
if (empty($dept_id) || empty($class_id)) {
    header("Location: adminfacst.php");
    exit();
}

$_POST['dept_id'] = $dept_id;
$_POST['dept_fullname'] = $dept_fullname;
$_POST['spec_fullname'] = $spec_fullname;
$_POST['class_fullname'] = $class_fullname;
$_POST['class_id'] = $class_id;

$page_title = "Departments";
if ($userRole === 'academic_section') {
    require_once("academicsectionheader.php");
} else {
    require_once("adminheader.php");
}

// Fetch students for the selected class
$studentList = $obj->getAllStudentsByClass($class_id);

?>

<div class="container">
    <div class="card mt-4">
        <div class="card-header">
            <div class="float-start">

                <?php
                if (!empty($_POST["dept_fullname"])) {
                    echo '<h5>Department of ' . htmlspecialchars($_POST["dept_fullname"]) . "</h5>";
                }
                ?>
            </div>
            <div class="float-end d-flex gap-2">
                <form action="viewstudents.php" method="post" class="d-inline">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id); ?>" />
                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST['dept_id']); ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']); ?>" />
                    <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($_POST['spec_fullname']); ?>" />
                    <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($_POST['class_fullname']); ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-primary">Back to Students</button>
                </form>
                <form action="viewclasses.php" method="post" class="d-inline">
                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST['dept_id']); ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']); ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-success">All Classes</button>
                </form>
            </div>

        </div>
        <div class="card-header">
            Specialization: <?= htmlspecialchars($_POST['spec_fullname']) ?>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($studentList)) {
                        $sno = 1;
                        foreach ($studentList as $student) {
                            echo "<tr>
                        <td>" . $sno++ . "</td>
                        <td>{$student['roll_number']}</td>
                        <td>{$student['name']}</td><td>" . ($student['status'] ? 'Active' : 'Inactive') . "</td><td>";
                    ?>
                            <form action="viewstudents.php" method="post" onsubmit="return confirm('Are you sure you want to UnEnroll <?= htmlspecialchars($student['roll_number']); ?> from this class?');">
                                <input type="hidden" name="roll_number" value="<?php echo $student['roll_number']; ?>" />
                                <input type="hidden" name="class_id" value="<?php echo $_POST['class_id']; ?>" />
                                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />
                                <input type="hidden" name="whattodo" value="unenrollstudent" />
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                    <?php
                            echo "</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No students found in this class.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Display success message -->
    <?php if (isset($msg)) echo "<div class='alert alert-success mt-3'>{$msg}</div>"; ?>
    <br>
</div>
<?php
if ($userRole === 'academic_section') {
    require_once("academicsectionfooter.php");
} else {
    require_once("adminfooter.php");
}
?>
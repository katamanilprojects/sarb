<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if dept_id is not provided
if (empty($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}


$obj = new Admin();
$class_id = $_POST['class_id'];


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
            <div class="float-end">
                <form action="adminviewclasses.php" method="post">
                    <input type="hidden" name="dept_id" value="<?= $_POST['dept_id']; ?>" />
                    <input type="hidden" name="dept_fullname" value="<?= $_POST['dept_fullname']; ?>" />
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
                            <form action="adminviewstudents.php" method="post" onsubmit="return confirm('Are you sure you want to UnEnroll <?= htmlspecialchars($student['roll_number']); ?> from this class?');">
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
require_once("adminfooter.php");
?>
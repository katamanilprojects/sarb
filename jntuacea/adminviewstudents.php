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

// Handle confirmation of student enrollment
if (!empty($_POST['confirm']) && isset($_SESSION['uploaded_students'])) {
    $students = $_SESSION['uploaded_students'];

    foreach ($students as $student) {
        $res = $obj->addStudent($student['rollno'], $student['name'], $class_id, $student['date_of_joining'] ?? null);
    }

    unset($_SESSION['uploaded_students']);
    $msg = "Students enrolled successfully.";
}

if (!empty($_POST['whattodo']) && $_POST["whattodo"] == "addnewst" && !empty($_POST['stroll']) && !empty($_POST['stname'])) {

    $_POST['stroll'] = strtoupper($_POST['stroll']);
    $_POST['stname'] = strtoupper($_POST['stname']);

    if (strlen($_POST['stroll']) == 10 || (strlen($_POST['stroll']) <= 10  && preg_match('/PH/', $_POST['stroll']))) {
        $date_of_joining = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null;
        $res = $obj->addStudent($_POST['stroll'], $_POST['stname'], $class_id, $date_of_joining);
        $msg = "Student enrolled successfully.";
    } else {
        $msg = "Invalid Roll Number";
    }
}

if (!empty($_POST['whattodo']) && $_POST["whattodo"] == "unenrollstudent" && !empty($_POST['roll_number'])) {

    $res = $obj->unEnrollClassStudent($_POST['roll_number'], $class_id);
    if (!empty($res["status"])) {
        $msg = "Student unenrolled successfully.";
    } else {
        if (!empty($res["err"])) {
            $msg = $res["err"];
        } else {
            $msg = "Please try again";
        }
    }
}

if (
    !empty($_POST['whattodo']) && $_POST["whattodo"] == "changestatus" &&
    !empty($_POST['roll_number']) && isset($_POST['status'])
) {
    $res = $obj->setStudentStatus($_POST['roll_number'], $class_id, $_POST['status']);
    if (!empty($res["status"])) {
        $msg = $_POST['status'] ? "Student activated." : "Student inactivated.";
    } else {
        $msg = "Failed to update status";
    }
}

if (!empty($_POST['whattodo']) && $_POST["whattodo"] == "resetpassword" && !empty($_POST['roll_number'])) {
    $res = $obj->resetStudentPassword($_POST['roll_number']);
    if (!empty($res["status"])) {
        $msg = "Student password reset successfully.";
    } else {
        $msg = "Failed to reset password";
    }
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
                        <th>Date of Joining</th>
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
                        <td>{$student['name']}</td>                        
                        <td>" . ($student['date_of_joining'] ?? 'Not Set') . "</td>
                        <td>" . ($student['status'] ? 'Active' : 'Inactive') . "</td>
                        <td>"; ?>
                            <!-- Student action buttons inside your student list display loop -->
                            <form method="post" action="" class="d-inline">
                                <input type="hidden" name="whattodo" value="changestatus">
                                <input type="hidden" name="roll_number" value="<?= $student['roll_number'] ?>">
                                <input type="hidden" name="status" value="<?= $student['status'] ? 0 : 1 ?>">
                                <input type="hidden" name="class_id" value="<?= $class_id ?>">
                                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />

                                <button type="submit" class="btn btn-sm <?= $student['status'] ? 'btn-warning' : 'btn-success' ?>">
                                    <?= $student['status'] ? 'Inactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to reset the password for <?= htmlspecialchars($student['roll_number']); ?> ?');">
                                <input type="hidden" name="whattodo" value="resetpassword">
                                <input type="hidden" name="roll_number" value="<?= $student['roll_number'] ?>">
                                <input type="hidden" name="class_id" value="<?= $class_id ?>">
                                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />
                                <button type="submit" class="btn btn-sm btn-danger">Reset Password</button>
                            </form>
                    <?php
                            echo "</td>
                      </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No students found in this class.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <form action="adminunenrollstudents.php" method="post">
                <input type="hidden" name="class_id" value="<?php echo $_POST['class_id']; ?>" />
                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />
                <button type="submit" class="btn btn-outline-danger">To Remove any Student for this class, Click here</button>
            </form>
        </div>
    </div>

    <!-- Display success message -->
    <?php if (isset($msg)) echo "<div class='alert alert-success mt-3'>{$msg}</div>"; ?>
    <br>

    <div class="card">
        <div class="card-header">
            Add Any missing Student Details for this class
        </div>
        <div class="card-body">
            <form action="adminviewstudents.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="stroll">Roll Number:</label>
                    <input type="text" name="stroll" id="stroll" maxlength="10" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="stname">Student Name:</label>
                    <input type="text" name="stname" id="stname" maxlength="60" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="date_of_joining">Date of Joining (Optional):</label>
                    <input type="date" name="date_of_joining" id="date_of_joining" class="form-control" />
                </div>
                <input type="hidden" name="class_id" value="<?php echo $_POST['class_id']; ?>" />
                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />

                <input type="hidden" name="whattodo" value="addnewst" />
                <input type="hidden" name="secretcode" value="<?= $_SESSION['secretcode'] ?>" />
                <button type="submit" class="btn btn-primary mt-3">Enroll New Student</button>
            </form>
        </div>
    </div>
</div>
<?php
require_once("adminfooter.php");
?>
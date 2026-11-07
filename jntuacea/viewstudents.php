<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'academic_section', 'superadmin'], true)) {
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

// Mutation actions: Available for Academic Section, Admin, and SuperAdmin
$canMutate = in_array($userRole, ['academic_section', 'admin', 'superadmin'], true);

// Handle confirmation of student enrollment
if ($canMutate && !empty($_POST['confirm']) && isset($_SESSION['uploaded_students'])) {
    $students = $_SESSION['uploaded_students'];
    $successCount = 0;
    foreach ($students as $student) {
        $res = $obj->addStudent($student['rollno'], $student['name'], $class_id, $student['date_of_joining'] ?? null);
        if (!empty($res['status'])) {
            $successCount++;
        }
    }

    unset($_SESSION['uploaded_students']);
    $msg = "Successfully enrolled {$successCount} student(s) into this class.";
}

if ($canMutate && !empty($_POST['whattodo']) && $_POST["whattodo"] == "addnewst" && !empty($_POST['stroll']) && !empty($_POST['stname'])) {

    $_POST['stroll'] = strtoupper(trim($_POST['stroll']));
    $_POST['stname'] = strtoupper(trim($_POST['stname']));

    if (strlen($_POST['stroll']) == 10 || (strlen($_POST['stroll']) <= 12  && preg_match('/PH/', $_POST['stroll']))) {
        $date_of_joining = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null;
        $res = $obj->addStudent($_POST['stroll'], $_POST['stname'], $class_id, $date_of_joining);
        if (!empty($res['status'])) {
            $msg = "Student {$_POST['stroll']} enrolled successfully.";
        } else {
            $err = "Failed to enroll student {$_POST['stroll']}. Please try again.";
        }
    } else {
        $err = "Invalid Roll Number. Roll Number must be 10 characters long.";
    }
}

if ($canMutate && !empty($_POST['whattodo']) && $_POST["whattodo"] == "unenrollstudent" && !empty($_POST['roll_number'])) {

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
    $canMutate && !empty($_POST['whattodo']) && $_POST["whattodo"] == "changestatus" &&
    !empty($_POST['roll_number']) && isset($_POST['status'])
) {
    $res = $obj->setStudentStatus($_POST['roll_number'], $class_id, $_POST['status']);
    if (!empty($res["status"])) {
        $msg = $_POST['status'] ? "Student activated." : "Student inactivated.";
    } else {
        $msg = "Failed to update status";
    }
}

if ($canMutate && !empty($_POST['whattodo']) && $_POST["whattodo"] == "resetpassword" && !empty($_POST['roll_number'])) {
    $res = $obj->resetStudentPassword($_POST['roll_number']);
    if (!empty($res["status"])) {
        $msg = "Student password reset successfully.";
    } else {
        $msg = "Failed to reset password";
    }
}

// Fetch students for the selected class
$studentList = $obj->getAllStudentsByClass($class_id);

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
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
                <?php if ($canMutate): ?>
                    <form action="enrollstudents.php" method="post" class="d-inline">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id); ?>" />
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST['dept_id']); ?>" />
                        <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']); ?>" />
                        <input type="hidden" name="spec_fullname" value="<?= htmlspecialchars($_POST['spec_fullname'] ?? ''); ?>" />
                        <input type="hidden" name="class_fullname" value="<?= htmlspecialchars($_POST['class_fullname'] ?? ''); ?>" />
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Enroll Students (CSV / Previous Sem)
                        </button>
                    </form>
                <?php endif; ?>
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
                        <th>Date of Joining</th>
                        <th>Status</th>
                        <?php if ($canMutate): ?>
                        <th>Actions</th>
                        <?php endif; ?>
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
                        <td>" . ($student['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>') . "</td>";
                        if ($canMutate):
                            echo "<td>"; ?>
                            <a href="editstudent.php?student_id=<?= (int)$student['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Edit Student Profile">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
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
                            echo "</td>";
                        endif;
                            echo "</tr>";
                        }
                    } else {
                        $colSpan = $canMutate ? 6 : 5;
                        echo "<tr><td colspan='{$colSpan}' class='text-center text-muted'>No students found in this class.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        <?php if ($canMutate): ?>
        <div class="card-footer">
            <form action="unenrollstudents.php" method="post">
                <input type="hidden" name="class_id" value="<?php echo $_POST['class_id']; ?>" />
                <input type="hidden" name="dept_id" value="<?php echo $_POST['dept_id']; ?>" />
                <input type="hidden" name="dept_fullname" value="<?php echo $_POST['dept_fullname']; ?>" />
                <input type="hidden" name="spec_fullname" value="<?php echo $_POST['spec_fullname']; ?>" />
                <input type="hidden" name="class_fullname" value="<?php echo $_POST['class_fullname']; ?>" />
                <button type="submit" class="btn btn-outline-danger">To Remove any Student for this class, Click here</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Display success message -->
    <?php if (isset($msg)) echo "<div class='alert alert-success mt-3'>" . htmlspecialchars($msg) . "</div>"; ?>
    <?php if (isset($err)) echo "<div class='alert alert-danger mt-3'>" . htmlspecialchars($err) . "</div>"; ?>
    <br>

    <?php if ($canMutate): ?>
    <div class="card">
        <div class="card-header">
            Add Any missing Student Details for this class
        </div>
        <div class="card-body">
            <form action="viewstudents.php" method="post" enctype="multipart/form-data">
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
    <?php endif; ?>
</div>
<?php
if ($userRole === 'academic_section') {
    require_once("academicsectionfooter.php");
} else {
    require_once("adminfooter.php");
}
?>
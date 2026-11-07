<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!empty($_SESSION['uploaded_students'])) {
    unset($_SESSION['uploaded_students']); // Clear session data on cancel
}

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'academic_section', 'superadmin'], true)) {
    header("Location: ./");
    exit();
}

require_once("admin.class.php");
$obj = new Admin();

// Resolve department details from POST or GET
$dept_id = $_POST['dept_id'] ?? $_GET['dept_id'] ?? null;
$dept_fullname = $_POST['dept_fullname'] ?? $_GET['dept_fullname'] ?? null;

// Redirect if dept_id is not provided
if (empty($dept_id)) {
    header("Location: adminfacst.php");
    exit();
}

if (empty($dept_fullname)) {
    $stmtDept = $obj->conn->prepare("SELECT dept_fullname FROM departments WHERE id = ?");
    $stmtDept->bind_param("i", $dept_id);
    $stmtDept->execute();
    $deptRow = $stmtDept->get_result()->fetch_assoc();
    $dept_fullname = $deptRow['dept_fullname'] ?? '';
}

$_POST['dept_id'] = $dept_id;
$_POST['dept_fullname'] = $dept_fullname;

// Fetch specializations for the selected department
$specializations = $obj->getSpecializationsByDepartment($dept_id);

$page_title = "Departments";
if ($userRole === 'academic_section') {
    require_once("academicsectionheader.php");
} else {
    require_once("adminheader.php");
}
?>

<div class="container">

    <?php if (!empty($specializations)) { ?>
        <div class="card">
            <div class="card-header">
                <div class="float-start">
                    <?php
                    if (!empty($_POST["dept_fullname"])) {
                        echo '<h5>Department of ' . htmlspecialchars($_POST["dept_fullname"]) . "</h5>";
                    }
                    ?>
                </div>
                <div class="float-end">
                    <a href="adminfacst.php" class="btn btn-sm btn-outline-success">All Departments</a>
                </div>
            </div>
        </div>
        <?php foreach ($specializations as $spec) { ?>
            <div class="card mb-4">
                <div class="card-header">
                    Specialization: <?= htmlspecialchars($spec['spec_fullname']) ?>
                </div>
                <div class="card-body">
                    <?php
                    $classes = $obj->getActiveClassesBySpecialization($spec['id']);
                    if (!empty($classes)) {
                        // Group by academic year
                        $groupedClasses = [];
                        foreach ($classes as $class) {
                            $groupedClasses[$class['acad_year']][] = $class;
                        }

                        // Bootstrap 5 Accordion for Academic Years
                        echo '<div class="accordion mb-3" id="accordion_' . $spec['id'] . '">';
                        $index = 0;
                        foreach ($groupedClasses as $acadYear => $yearClasses) {
                            $collapseId = 'collapse_' . $spec['id'] . '_' . $index;
                            echo '<div class="accordion-item">';
                            echo '<h2 class="accordion-header" id="heading_' . $collapseId . '">';
                            echo '<button class="accordion-button collapsed text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">';
                            echo '<i class="bi bi-calendar3 me-2"></i>Academic Year: ' . htmlspecialchars($acadYear);
                            echo '</button></h2>';
                            echo '<div id="' . $collapseId . '" class="accordion-collapse collapse" aria-labelledby="heading_' . $collapseId . '" data-bs-parent="#accordion_' . $spec['id'] . '">';
                            echo '<div class="accordion-body">';
                            echo "<div class='table-responsive'><table class='table table-sm table-bordered table-striped align-middle'>";
                            echo "<thead class='table-light'><tr><th>Class Name</th><th>Actions</th></tr></thead><tbody>";

                            foreach ($yearClasses as $class) {
                                $isEnrolled = $obj->checkIfStudentsEnrolled($class['id']);
                                if ($isEnrolled) {
                                    $actionHtml = "<div class='d-flex gap-1 flex-wrap'>
                                                    <form action='viewstudents.php' method='post' class='m-0'>
                                                        <input type='hidden' name='class_id' value='{$class['id']}' />
                                                        <input type='hidden' name='dept_id' value='{$_POST['dept_id']}' />
                                                        <input type='hidden' name='dept_fullname' value='{$_POST['dept_fullname']}' />
                                                        <input type='hidden' name='spec_fullname' value='{$spec['spec_fullname']}' />
                                                        <input type='hidden' name='class_fullname' value='{$class['classname']} ({$class['acad_year']})' />
                                                        <button type='submit' class='btn btn-outline-success btn-sm'><i class='bi bi-people me-1'></i>View Students</button>
                                                    </form>
                                                    <form action='enrollstudents.php' method='post' class='m-0'>
                                                        <input type='hidden' name='class_id' value='{$class['id']}' />
                                                        <input type='hidden' name='dept_id' value='{$_POST['dept_id']}' />
                                                        <input type='hidden' name='dept_fullname' value='{$_POST['dept_fullname']}' />
                                                        <input type='hidden' name='spec_fullname' value='{$spec['spec_fullname']}' />
                                                        <input type='hidden' name='class_fullname' value='{$class['classname']} ({$class['acad_year']})' />
                                                        <button type='submit' class='btn btn-outline-primary btn-sm'><i class='bi bi-person-plus me-1'></i>Enroll Students</button>
                                                    </form>
                                                </div>";
                                } else {
                                    $actionHtml = "<div class='d-flex align-items-center gap-2'>
                                                    <span class='badge bg-warning-subtle text-warning border border-warning-subtle'>Pending Enrollment</span>
                                                    <form action='enrollstudents.php' method='post' class='m-0'>
                                                        <input type='hidden' name='class_id' value='{$class['id']}' />
                                                        <input type='hidden' name='dept_id' value='{$_POST['dept_id']}' />
                                                        <input type='hidden' name='dept_fullname' value='{$_POST['dept_fullname']}' />
                                                        <input type='hidden' name='spec_fullname' value='{$spec['spec_fullname']}' />
                                                        <input type='hidden' name='class_fullname' value='{$class['classname']} ({$class['acad_year']})' />
                                                        <button type='submit' class='btn btn-primary btn-sm'><i class='bi bi-person-plus me-1'></i>Enroll Students</button>
                                                    </form>
                                                </div>";
                                }

                                echo "<tr>
                                        <td>{$class['classname']}</td>
                                        <td>{$actionHtml}</td>
                                    </tr>";
                            }

                            echo "</tbody></table></div></div></div></div>";
                            $index++;
                        }
                        echo '</div>'; // Close accordion container
                    } else {
                        echo "<p>No classes found for this specialization.</p>";
                    }
                    ?>
                </div>
            </div>
        <?php } ?>
    <?php } else {
        echo "<p>No specializations found for this department.</p>";
    } ?>
</div>

<?php
if ($userRole === 'academic_section') {
    require_once("academicsectionfooter.php");
} else {
    require_once("adminfooter.php");
}
?>
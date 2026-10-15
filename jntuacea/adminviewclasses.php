<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!empty($_SESSION['uploaded_students'])) {
    unset($_SESSION['uploaded_students']); // Clear session data on cancel
}
// Redirect if dept_id is not provided
if (!isset($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");


$obj = new Admin();

// Fetch the department ID and fullname
$dept_id = $_POST['dept_id'];
$dept_fullname = $_POST['dept_fullname'];

// Fetch specializations for the selected department
$specializations = $obj->getSpecializationsByDepartment($dept_id);
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
                                $actionBtn = $isEnrolled ? "View Students" : "Enroll Students";
                                $actionUrl = $isEnrolled ? "adminviewstudents.php" : "adminenrollstudents.php";
                                $actionBtnCls = $isEnrolled ? "btn btn-outline-success" : "btn btn-outline-primary";

                                echo "<tr>
                                        <td>{$class['classname']}</td>
                                        <td>
                                            <form action='{$actionUrl}' method='post' class='m-0'>
                                                <input type='hidden' name='class_id' value='{$class['id']}' />
                                                <input type='hidden' name='dept_id' value='{$_POST['dept_id']}' />
                                                <input type='hidden' name='dept_fullname' value='{$_POST['dept_fullname']}' />
                                                <input type='hidden' name='spec_fullname' value='{$spec['spec_fullname']}' />
                                                <input type='hidden' name='class_fullname' value='{$class['classname']} ({$class['acad_year']})' />
                                                <button type='submit' class='{$actionBtnCls} btn-sm'>{$actionBtn}</button>
                                            </form>
                                        </td>
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
require_once("adminfooter.php");
?>
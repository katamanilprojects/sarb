<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");
require_once __DIR__ . "/services/FeatureManager.php";

$showProfiles = \FeatureManager::canRoleAccess('MOD_STUDENT_PROFILE', 'hod');

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
                        <?php if ($showProfiles): ?>
                            <th class="text-center">Profile</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($studentList)) {
                        $sno = 1;
                        foreach ($studentList as $student) {
                            $statusBadge = ($student['status'] == 1)
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive / Shifted</span>';
                            $profileCol = '';
                            if ($showProfiles) {
                                $profileCol = "<td class='text-center'><a href='adminstudentprofiles.php?roll_no=" . urlencode($student['roll_number']) . "' class='btn btn-sm btn-outline-primary py-0'><i class='bi bi-person-badge'></i> Dossier</a></td>";
                            }
                            echo "<tr>
                        <td>" . $sno++ . "</td>
                        <td>{$student['roll_number']}</td>
                        <td>{$student['name']}</td>
                        <td>{$statusBadge}</td>
                        {$profileCol}
                      </tr>";
                        }
                    } else {
                        $colspan = $showProfiles ? 5 : 4;
                        echo "<tr><td colspan='{$colspan}'>No students found in this class.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php
require_once("adminfooter.php");
?>
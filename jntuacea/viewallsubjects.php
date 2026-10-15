<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "View Subjects";
require_once("header.php");
require_once("hod.class.php");
$hodObj = new HOD();

require_once("admin.class.php");
$adminObj = new Admin();

$departments = $adminObj->getAllDepartments();
$activeYear = $hodObj->getActiveAcademicYear();

// Handle form submission
$selected_dept_id = null;
$selected_cls_id = null;
$specializations = [];
$classes_options = '';
$subjectsList = [];

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
    }

    $specializations = $hodObj->getSpecializationsByDepartment($selected_dept_id);

    if (!empty($specializations)) {
        foreach ($specializations as $spec) {
            $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
            $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
            if (!empty($classes)) {
                foreach ($classes as $class) {
                    // Filter for active academic year
                    if ($class['acad_year'] == $activeYear) {
                        $classes_options .= '<option value="' . $class['id'] . '"';
                        if ($class['id'] == $selected_cls_id) {
                            $classes_options .= ' selected';
                        }
                        $classes_options .= '>' . $class['classname'] . '</option>';
                    }
                }
            }
            $classes_options .= '</optgroup>';
        }

        if (!empty($selected_cls_id)) {
            $subjectsList = $hodObj->getSubjectsByClassID($selected_cls_id);
        }
    }
}

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">View Subjects by Department and Class<?php if (!empty($activeYear)) echo " (Academic Year $activeYear)"; ?></div>
                <div class="card-body">
                    <form action="viewallsubjects.php" method="post" id="viewsubjectsform">
                        <div class="form-group">
                            <label for="dept_id">Department:</label>
                            <select name="dept_id" id="dept_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments['data'] as $dept) {
                                    if ($dept['dept_fullname'] != "Physical Education" && $dept['dept_fullname'] != "Humanities" && $dept['dept_fullname'] != "Physics" && $dept['dept_fullname'] != "Chemistry" && $dept['dept_fullname'] != "Mathematics") {
                                        $selected = (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : '';
                                        echo "<option value='{$dept['id']}' $selected>{$dept['dept_fullname']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="cls_id">Class<?php if (!empty($activeYear)) echo " ($activeYear)"; ?>:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php
                                echo $classes_options;
                                ?>
                            </select>
                        </div>
                        <br />
                        <button type="submit" class="btn btn-primary">View Subjects</button>
                    </form>
                </div>
            </div>
            <br />

            <?php if (!empty($subjectsList['data'])) { ?>
                <div class="card">
                    <div class="card-header">
                        Subjects List
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>S.No</th>
                                        <th>Subject Code</th>
                                        <th>Subject Short Name</th>
                                        <th>Subject Full Name</th>
                                        <th>Subject Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sno = 1;
                                    foreach ($subjectsList['data'] as $subject) {
                                        echo "<tr>";
                                        echo "<td>". htmlspecialchars($subject['subject_sno']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['subcode']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['sub_shortname']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['sub_fullname']) . "</td>";
                                         echo "<td>" . htmlspecialchars($subject['sub_type']) . "</td>";
                                       echo "</tr>";
                                        $sno++;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php } elseif (!empty($selected_cls_id)) { ?>
                <div class="alert alert-info">
                    No subjects found for the selected class.
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('dept_id').addEventListener('change', function() {
        document.getElementById('viewsubjectsform').submit();
    });
</script>

<?php
require_once("hodfooter.php");
?>

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

$hodObj = new HOD();
$class_id = $_POST['class_id'];

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);

    if (!empty($_POST['faculty_id']) && !empty($_POST['sub_id']) && !empty($_POST['whattodo']) && $_POST['whattodo'] == "mapfaculty") {
        $res_arr = $hodObj->addFacultySubjectMapping($_POST['faculty_id'], $_POST['sub_id']);

        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Mapping added successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to add mapping. Please try again.";
        }
    }

    if (!empty($_POST['map_id']) && !empty($_POST['whattodo']) && $_POST['whattodo'] == "unmapfacsub") {
        $res_arr = $hodObj->unMapFacSubMapByID($_POST['map_id']);

        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Faculty UnMapped successfully for the Subject.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to unmap faculty. Please try again.";
        }
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));


// Fetch students for the selected class
$subjectList = $hodObj->getSubjectsByClassID($class_id);
$allfacultyList = $hodObj->getAllActiveFacultyOrderDeptID($_SESSION["dept_id"]);
$mappingList = $hodObj->getFacSubMapByClassID($class_id);

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
    </div>
</div>

<div class="container">

    <div class="card">
        <div class="card-header">Map Faculty to Subject</div>
        <div class="card-body">
            <form action="hodmapfaculty.php" method="post">
                <div class="form-group">
                    <label for="sub_id">Subject:</label>
                    <select name="sub_id" id="sub_id" class="form-select" required>
                        <option value="">--Select Subject--</option>
                        <?php
                        foreach ($subjectList['data'] as $subject) {
                            echo "<option value='{$subject['id']}'>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="faculty_id">Faculty:</label>
                    <select name="faculty_id" id="faculty_id" class="form-select" required>
                        <option value="">--Select Faculty--</option>
                        <?php
                        $temp_deptshortname = '';
                        foreach ($allfacultyList['data'] as $faculty) {
                            if ($temp_deptshortname != $faculty["dept_fullname"]) {
                                if (!empty($temp_deptshortname)) {
                                    echo '</optgroup>';
                                }
                                $temp_deptshortname = $faculty["dept_fullname"];
                                echo '<optgroup label="' . $faculty["dept_fullname"] . '">';
                            }
                            echo "<option value='{$faculty['id']}'>{$faculty['name']}</option>";
                        }
                        if (!empty($temp_deptshortname)) {
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                </div>
                <br />
                <input type="hidden" name="whattodo" value="mapfaculty" />
                <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                <button type="submit" class="btn btn-primary">Add Mapping</button>
            </form>
            <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
        </div>
    </div>

    <br>
    <div class="card">
        <div class="card-header">Faculty-Subject Mappings</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Subject S.No</th>
                        <th>Faculty</th>
                        <th>Subject</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($mappingList['status']) && $mappingList['status'] == 1) {
                        foreach ($mappingList['data'] as $mapping) {
                            echo "<tr>";
                            echo "<td>{$mapping['subject_sno']}</td>";
                            echo "<td>{$mapping['faculty_name']}</td>";
                            echo "<td>{$mapping['sub_fullname']} ({$mapping['subcode']})</td>";
                            echo '<td>';
                    ?>
                            <form action="hodmapfaculty.php" method="post" onsubmit="return confirm('Are you sure you want to Unmap Faculty: <?= htmlspecialchars($mapping['faculty_name']); ?> - Subject:  <?= htmlspecialchars($mapping['subcode']); ?> ?');">
                                <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                <input type="hidden" name="map_id" value="<?php echo $mapping["map_id"]; ?>" />
                                <input type="hidden" name="whattodo" value="unmapfacsub" />
                                <input type="submit" class="btn btn-outline-danger" value="X" />
                            </form>
                    <?php
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2'>No mappings found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once("hodfooter.php");
?>
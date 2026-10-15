<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$page_title = "Manage Faculty";
require_once("hod.class.php");
require_once("hodheader.php");


// Redirect if faculty_id is not provided
if (!isset($_POST['faculty_id'])) {
    header("Location: hodviewfaculties.php");
    exit();
}

$faculty_id = $_POST['faculty_id'];
$hodObj = new HOD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "update_facdetails") {
        $updateData = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'mobile' => $_POST['mobile'],
            'designation' => $_POST['designation'],
            'status' => $_POST['status']
        ];
        $result = $hodObj->updateFacultyDetails($faculty_id, $updateData);

        if ($result['status'] === 1) {
            $succ = "Faculty Details Updated Successfully";
        } else {
            $error = "Error updating faculty details. Please try again.";
        }
    }
    if (!empty($_POST["whattodo"]) && $_POST["whattodo"] == "reset_password") {
        
        if(!empty($_POST['faculty_mobile'])){
            $result = $hodObj->resetFacPwd($faculty_id);
    
            if ($result['status'] === 1) {
                $succ = "Faculty Password Reset Successful. Default Password is Mobile Number";
            } else {
                $error = "Error updating faculty Password. Please try again.";
            }
        }else{
            $error = "Please update Faculty Mobile Number to reset password.";
        }
    }
}
$facultyDetails = $hodObj->getFacultyDetails($faculty_id);

?>
<div class="container">
    <div class="card">
        <div class="card-header">

        </div>
        <div class="card-header">
            <?php
            if (!empty($facultyDetails['data']['name'])) {
                echo '<h6>Profile of ' . htmlspecialchars($facultyDetails['data']['name']) . "</h6>";
            }
            ?>
        </div>

        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($facultyDetails['data']['name']) ?></p>
            <p><strong>Designation:</strong> <?= htmlspecialchars($facultyDetails['data']['designation']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($facultyDetails['data']['email']) ?></p>
            <p><strong>Mobile:</strong> <?= htmlspecialchars($facultyDetails['data']['mobile']) ?></p>
            <hr>
            <p><strong>Username:</strong> <?= htmlspecialchars($facultyDetails['data']['username']) ?></p>
            <hr>
            <p><strong>Status:</strong> <?= $facultyDetails['data']['status'] == 1 ? 'Active' : 'Inactive' ?></p>
        </div>
        <div class="card-footer">

            <form action="hodeditfaculty.php" method="post" class="float-start">
                <input type="hidden" name="faculty_id" value="<?= $faculty_id; ?>">

                <button type="submit" name="action" value="edit" class="btn btn-warning">Edit Profile</button>
            </form>
            <form action="hodfacultyprofile.php" method="post" class="float-end" onsubmit="return confirm('Are you sure you want to Reset Password of <?= htmlspecialchars($facultyDetails['data']['name']); ?> ? \n Default Password will be Faculty Mobile Number.');">
                <input type="hidden" name="faculty_id" value="<?= $faculty_id; ?>">
                <input type="hidden" name="faculty_mobile" value="<?= htmlspecialchars($facultyDetails['data']['mobile']) ?>" />
                <input type="hidden" name="whattodo" value="reset_password" />
                <button type="submit" name="action" value="Reset Password" class="btn btn-secondary">Reset Password</button>
            </form>

        </div>
    </div>
    <?php if (isset($succ)): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($succ) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

</div>
<?php
require_once("hodfooter.php");
?>
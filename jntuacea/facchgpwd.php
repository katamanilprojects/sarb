<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$page_title = "Change Pwd";
require_once("facheader.php");

if (!empty($_POST['oldpassword']) && !empty($_POST['newpassword']) && !empty($_POST['confirmpassword'])) {
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        if ($_POST['newpassword'] != $_POST['oldpassword']) {
            if ($_POST['newpassword'] == $_POST['confirmpassword']) {
                require_once("faculty.class.php");
                $obj = new Faculty();
                $res_arr = $obj->updatePwd($_SESSION['userid'], $_POST['oldpassword'], $_POST['newpassword']);
                if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
                    $succ = "Password Updated Successfully";
                } else {
                    if (!empty($res_arr['err'])) {
                        $err = $res_arr["err"];
                    } else {
                        $err = "Please try again.";
                    }
                }
            } else {
                $err = "New & confirm Password(s) Doesn't Match..!";
            }
        } else {
            $err = "New Password is same as Existing Password..!";
        }
    } else {
        $err = "Please try again..!";
    }
}
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
$page_title = "Change Pwd";
require_once("header.php");
require_once("facmenu.php");
?>
<div class="container">
    <br>
    <div class="card">
        <div class="card-header">Update Password</div>
        <div class="card-body">

            <div class="row">
                <div class="col-sm-3"></div>
                <div class="col-sm-6">
                    <form action="facchgpwd.php" method="post">
                        <input type="password" name="oldpassword" placeholder="Enter Existing Password" required="required" class="form-control" maxlength="32" />
                        <br />
                        <input type="password" name="newpassword" placeholder="Enter New Password" required="required" class="form-control" maxlength="32" />
                        <br />
                        <input type="password" name="confirmpassword" placeholder="Confirm New Password" required="required" class="form-control" maxlength="32" />
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>" />
                        <input type="submit" value="Update" class="btn btn-primary" />
                    </form>
                    <br />
                    <?php
                    if (!empty($err)) {
                        echo '<div class="alert alert-danger">' . $err . '</div>';
                    }
                    if (!empty($succ)) {
                        echo '<div class="alert alert-success">' . $succ . '</div>';
                    }
                    ?>

                </div>
                <div class="col-sm-3"></div>
            </div>
        </div>
    </div>
</div>
<?php
require_once("facfooter.php");
?>
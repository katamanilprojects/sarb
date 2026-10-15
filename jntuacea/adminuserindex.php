<?php
@session_start();

if (!empty($_POST['username']) && !empty($_POST['password'])) {
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $_POST['username'] = strtolower($_POST['username']);
        require_once("adminuser.class.php");
        $obj = new AdminUser();
        require_once("faculty.class.php");
        $facobj = new Faculty();

        $login_arr = $obj->checkLogin($_POST['username'], $_POST['password']);
        if (!empty($login_arr['status']) && $login_arr['status'] == 1 && !empty($login_arr['id']) && !empty($login_arr['role']) && ($login_arr['role'] == "admin" || $login_arr['role'] == "faculty" || $login_arr['role'] == "hod" || $login_arr['role'] == "student" || $login_arr['role'] == "academic_section")) {
            $_SESSION['user'] = $_POST['username'];
            $_SESSION['userid'] = $login_arr['id'];
            $_SESSION['role'] = $login_arr['role'];
            $_SESSION['name'] = ucwords($login_arr['name']);

            if ($login_arr['role'] == "admin") {
                header("Location: ./adminhome.php");
            } elseif ($login_arr['role'] == "academic_section") {
                echo "as";                
                header("Location: ./academicsectionhome.php");
            } elseif ($login_arr['role'] == "hod") {
                $dept_id = $obj->getDeptIDByUsername($_POST['username']);
                if (!empty($dept_id)) {
                    $_SESSION['dept_id'] = $dept_id;
                    header("Location: ./hodhome.php");
                } else {
                    header("Location: ./");
                }
                header("Location: ./hodhome.php");
            } elseif ($login_arr['role'] == "faculty") {
                $facres = $facobj->getFacIDByUsername($_POST['username']);
                $_SESSION["facid"] = $facres['id'];
                header("Location: ./fachome.php");
            } else {
                header("Location: ./sthome.php");
            }
            exit();
        } else {
            $err = "Invalid Username / Password";
        }
    } else {
        $err = "Please try again..!";
    }
}
session_regenerate_id(1);
$_SESSION['secretcode'] = bin2hex(random_bytes(32));
$page_title = "Login";
require_once("header.php");
?>
<div class="container">
    <br />
    <br />
    <div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-6">
            <div class="card mt-3 p-4">
                <h4>Login</h4>
                <br />
                <form action="adminuserindex.php" method="post">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" placeholder="Enter Username" required="required" class="form-control" maxlength="32" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" placeholder="Enter Password" required="required" class="form-control" maxlength="32" />
                        </div>
                    </div>
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>" />
                    <div class="d-grid">
                        <input type="submit" value="Login" class="btn btn-primary" />
                    </div>
                </form>
            </div>
            <br />
            <?php
            if (!empty($err)) {
                echo '<div class="alert alert-danger">' . $err . '</div>';
            }
            ?>
        </div>
        <div class="col-sm-3"></div>
    </div>
</div>
<?php
require_once("footer.php");
?>
<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } // Start session without error suppression

if(!empty($_SESSION['name'])){
    unset($_SESSION['name']);
    session_regenerate_id(1);
}
// Initialize error variable
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username']) && !empty($_POST['password'])) {
    // Sanitize and trim inputs first
    $username = strtolower(trim($_POST['username']));
    $password = trim($_POST['password']);
    
    // Define brute force keys
    $attemptKey   = 'login_attempts_' . md5($username);
    $lockKey      = 'login_locked_'   . md5($username);
    $maxAttempts  = 5;
    $lockDuration = 900;   // 15 minutes in seconds
    
    // Validate the secret code
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']); // Clear the secret code

        // --- Brute Force Guard ---
        if (!empty($_SESSION[$lockKey]) && time() < $_SESSION[$lockKey]) {
            $wait = ceil(($_SESSION[$lockKey] - time()) / 60);
            $err  = "Too many failed attempts. Please try again after $wait minute(s).";
        } else {
            if (!empty($_SESSION[$lockKey])) {
                unset($_SESSION[$lockKey], $_SESSION[$attemptKey]);  // lock expired
            }

            require_once("user.class.php");
            
            // Check if the user is authenticated
            $user = new User();
            $authenticate = $user->authenticate($username, $password);

            if ($authenticate && $user->isActive() && $user->role !== 'student') {
                unset($_SESSION[$attemptKey], $_SESSION[$lockKey]);  // reset on success
                // Regenerate session ID to prevent fixation attacks
                session_regenerate_id(true);

                // Store user details in session
                $_SESSION['user'] = $username;
                $_SESSION['userid'] = $user->id;
                $_SESSION['role'] = $user->role;
                $_SESSION['name'] = strtoupper($user->name);

                // Redirect based on role
                switch ($user->role) {
                    case 'superadmin':
                        header("Location: ./superadminhome.php");
                        break;
                    case 'admin':
                        header("Location: ./adminhome.php");
                        break;
                    case 'academic_section':
                        header("Location: ./academicsectionhome.php");
                        break;
                    case 'hod':
                        $dept_id = $user->getDeptID();
                        if(!empty($dept_id)){
                            $_SESSION['dept_id'] = $dept_id;
                            header("Location: ./hodhome.php");
                        }else{
                            header("Location: ./");
                        }
                        header("Location: ./hodhome.php");
                        break;
                    case 'faculty':
                        $facid = $user->getFacID();
                        if(!empty($facid)){
                            $_SESSION['facid'] = $facid;
                            header("Location: ./fachome.php");
                        }else{
                            header("Location: ./");
                        }
                        break;
                    case 'student':
                        $_SESSION['user'] = strtoupper($_SESSION['user']);
                        header("Location: ./studenthome.php");
                        break;
                    default:
                        header("Location: ./"); // Redirect to an error page for unknown roles
                }
                exit();
            } else {
                $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;
                if ($_SESSION[$attemptKey] >= $maxAttempts) {
                    $_SESSION[$lockKey] = time() + $lockDuration;
                    unset($_SESSION[$attemptKey]);
                    $err = "Too many failed attempts. Account locked for 15 minutes.";
                } else {
                    $remaining = $maxAttempts - $_SESSION[$attemptKey];
                    $err = "Invalid login credentials. $remaining attempt(s) remaining.";
                }
            }
        }
    } else {
        $err = "Invalid secret code. Please try again.";
    }
}

// Generate a new secret code for CSRF protection
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Page Title
$page_title = "Login";
require_once("header.php");
?>
<div class="container">
    <br /><br />
    <div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-6">
            <div class="card mt-3 p-4">
                <h4>Login</h4>
                <br />
                <form action="./" method="post">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" placeholder="Enter Username" required class="form-control" maxlength="32" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" placeholder="Enter Password" required class="form-control" maxlength="32" />
                        </div>
                    </div>
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>" />
                    <div class="d-grid">
                        <input type="submit" value="Login" class="btn btn-success" />
                    </div>
                </form>
            </div>
            <br />
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
            <?php endif; ?>
        </div>
        <div class="col-sm-3"></div>
    </div>
</div>
<?php require_once("footer.php"); ?>
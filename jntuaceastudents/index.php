<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } // Start session without error suppression

require_once("logger.php");
logRequest();

// Initialize error variable
$err = '';


require_once __DIR__ . '/config/secrets.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turnstile_token = $_POST['cf-turnstile-response'] ?? '';

    if (empty($turnstile_token)) {
        $err = 'Use https://jntuaceastudents.classattendance.in';
    } else {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret'          => TURNSTILE_SECRET,
                'response'        => $turnstile_token,
                'remoteip'        => $_SERVER['REMOTE_ADDR'],
                'idempotency_key' => bin2hex(random_bytes(16)),
            ]),
        ]);
        $verify = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($verify === false) {
            $err = "Verification service unavailable. Please try again.";
            error_log("Turnstile cURL error: " . $curl_err);
        } else {
            $verify_result = json_decode($verify, true);
            if (empty($verify_result['success'])) {
                $err = "Verification failed. Please try again.";
                error_log("Turnstile failed: " . implode(',', $verify_result['error-codes'] ?? []));
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username']) && !empty($_POST['password']) && empty($err)) {
    // Validate the secret code
    if (!empty($_POST['secretcode']) && !empty($_SESSION['secretcode']) && hash_equals($_SESSION['secretcode'], $_POST['secretcode'])) {
        unset($_SESSION['secretcode']); // Clear consumed secret code
        $_SESSION['secretcode'] = bin2hex(random_bytes(32)); // Immediately generate replacement token for retry/next submission

        // Sanitize and trim inputs
        $username = strtolower(trim($_POST['username']));
        $password = trim($_POST['password']);

        require_once("user.class.php");
        
        // Check if the user is authenticated
        $user = new User();
        $authenticate = $user->authenticate($username, $password);

        if ($authenticate && $user->isActive() && !empty($user->role) && $user->role=="student") {
            // Regenerate session ID to prevent fixation attacks
            session_regenerate_id(true);

            // Store user details in session
            $_SESSION['user'] = $username;
            $_SESSION['userid'] = $user->id;
            $_SESSION['role'] = $user->role;
            $_SESSION['name'] = strtoupper($user->name);

            // Redirect based on role
            switch ($user->role) {
                case 'student':
                    $_SESSION['user'] = strtoupper($_SESSION['user']);
                    header("Location: ./studenthome.php");
                    break;
                default:
                    header("Location: ./"); // Redirect to an error page for unknown roles
            }
            exit();
        } else {
            $err = "Invalid login credentials. Please check your username and password.";
        }
    } else {
        $err = "Invalid secret code. Please try again.";
    }
}

// Generate a new secret code for CSRF protection if not set
if (empty($_SESSION['secretcode'])) {
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
}

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
                <form action="./" method="post" id="loginForm">
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
                    <div class="mb-3">
                        <div class="cf-turnstile" data-sitekey="0x4AAAAAADuXdC7fK72kMioV"></div>
                    </div>
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
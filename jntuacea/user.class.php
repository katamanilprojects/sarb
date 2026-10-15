<?php
require_once("dbcredentials.class.php");

class User extends DBCredentials
{
	public $id;
	public $username;
	public $name;
	public $role;
	protected $status;

	// Constructor to initialize the User object with userId
	public function __construct()
	{
		parent::__construct();
	}

	// Function to check if the user is active
	public function isActive()
	{
		return $this->status === 1;
	}

	// Static function to authenticate user based on username and MD5 hashed password
	public function authenticate($username, $password)
	{
		try {

			$stmt = $this->conn->prepare("SELECT id, username, password, name, role, status FROM users WHERE username = ? AND status = 1");
			if (!$stmt) {
				throw new Exception("Failed to prepare authentication query: " . $this->conn->error);
			}

			$stmt->bind_param("s", $username);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt->bind_result($this->id, $this->username, $dbPassword, $this->name, $this->role, $this->status);
			if ($stmt->fetch()) {
				$stmt->close();
				$isValid = false;

				if ($dbPassword !== null && str_starts_with($dbPassword, '$2y$')) {
					// Already bcrypt
					$isValid = password_verify($password, $dbPassword);
				} else {
					// Legacy MD5 — verify then silently upgrade
					if (md5($password) === $dbPassword) {
						$isValid = true;
						$newHash = password_hash($password, PASSWORD_BCRYPT);
						$upd = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
						$upd->bind_param("si", $newHash, $this->id);
						$upd->execute();
						$upd->close();
						$this->logs->activityLog("Password silently upgraded MD5->bcrypt for user: $username");
					}
				}

				if ($isValid) {
					$this->logs->activityLog("User $username successfully logged in.");
					$this->dbActivityLog($this->id, "Login", "Logged in Successfully", $this->role, "AUTH", (string)$this->id);
					return true; // Return a new user object
				} else {
					$this->logs->errLog("Failed login attempt for user $username (invalid password).");
					$this->dbActivityLog($this->id, "Login Failed", "Failed login attempt (invalid password)", $this->role, "AUTH", (string)$this->id, null, 'FAILED');
				}
			} else {
				$stmt->close();
				$this->logs->errLog("Failed login attempt for user $username (user not found or inactive).");
				$this->dbActivityLog(0, "Login Failed", "Failed login attempt for user $username (user not found or inactive)", "guest", "AUTH", null, null, 'FAILED');
			}
		} catch (Exception $e) {
			$this->logs->errLog("Error during authentication for user $username: " . $e->getMessage());
			return null; // Authentication failed
		}

		return null; // Authentication failed
	}

	// Static function to authenticate user based on username and MD5 hashed password
	public function verifyPwd($id, $password)
	{
		try {

			$stmt = $this->conn->prepare("SELECT username, password FROM users WHERE id = ? AND status = 1");
			if (!$stmt) {
				throw new Exception("Failed to prepare authentication query: " . $this->conn->error);
			}

			$stmt->bind_param("s", $id);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt->bind_result($username, $pwd);
			if ($stmt->fetch()) {
				$stmt->close();
				if ($pwd !== null && str_starts_with($pwd, '$2y$')) {
					return password_verify($password, $pwd);
				} elseif ($pwd !== null) {
					return md5($password) === $pwd;   // will auto-upgrade on next login
				} else {
					return false;
				}
			} else {
				$stmt->close();
				$this->logs->errLog("Failed Verification for user $username (user not found or inactive).");
			}
		} catch (Exception $e) {
			$this->logs->errLog("Error during authentication for user $username: " . $e->getMessage());
			return null; // Authentication failed
		}

		return null; // Authentication failed
	}

	public function updatePassword($userId, $password, $role)
	{
		$res = ['status' => 0]; // Default response

		try {
			// Update password securely
			$newHashedPassword = password_hash($password, PASSWORD_BCRYPT);
			$stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare password update query: " . $this->conn->error);
			}

			$stmt->bind_param("sis", $newHashedPassword, $userId, $role);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute password update query: " . $this->conn->error);
			}
			if ($stmt->affected_rows > 0) {
				$logRole = !empty($role) ? $role : (!empty($_SESSION['role']) ? $_SESSION['role'] : "user");
				$this->dbActivityLog($userId, "CHANGE_PASSWORD", "Password updated for " . $logRole . " ID: " . $userId, $logRole, "AUTH", (string)$userId);
				$res['status'] = 1; // Password updated successfully
			}

			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog("Error updating password for user ID $userId: " . $e->getMessage());
		}

		return $res; // Return the status of the update attempt
	}

	// Function to get Faculty ID by username
	public function getFacID()
	{
		$res = '';
		//$myname = $this->classname . " - getFacID - ";

		try {
			$stmt1 = $this->conn->prepare("SELECT `id` FROM faculties WHERE username = ?");
			if (!$stmt1) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}
			$stmt1->bind_param("s", $this->username);
			if (!$stmt1->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt1->bind_result($id);
			if ($stmt1->fetch()) {
				$res = $id;   // Set ID
			}
			$stmt1->close();
		} catch (Exception $e) {
			$this->logs->errLog("Error fetching Faculty ID for user $this->id: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get DeptID ID by username
	public function getDeptID()
	{
		$res = '';
		//$myname = $this->classname . " - getDeptID - ";

		try {
			$stmt1 = $this->conn->prepare("SELECT `id` FROM departments WHERE username = ?");
			if (!$stmt1) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}
			$stmt1->bind_param("s", $this->username);
			if (!$stmt1->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt1->bind_result($id);
			if ($stmt1->fetch()) {
				$res = $id;   // Set ID
			}
			$stmt1->close();
		} catch (Exception $e) {
			$this->logs->errLog("Error fetching Faculty ID for user $this->id: " . $e->getMessage());
		}
		return $res;
	}
}

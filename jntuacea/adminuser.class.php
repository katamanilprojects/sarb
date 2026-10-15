<?php
require_once("dbcredentials.class.php");
require_once("logs.class.php");

class AdminUser extends DBCredentials
{
	private $classname = "AdminUser";
	protected $logs;
	private $myconn;
	private $myerr = 0;
	private $exception = 0;

	public function __construct()
	{
		// Load environment variables first
		if (!defined('DB_HOST')) {
			require_once dirname(__DIR__) . '/.env.php';
		}
		
		try {
			$this->logs = new Logs();
			$this->myconn = new mysqli($this->getHost(), $this->getDBUser(), $this->getDBPwd(), $this->getDBName());
		} catch (exception $e) {
			$this->logs->errLog($this->classname . " - constructor - " . $e);
			$this->exception = 1;
		}
		if (mysqli_connect_error()) {
			$this->myerr = mysqli_connect_error();
		}
	}

	public function checkLogin($user, $pass)
	{
		$myname = $this->classname . " - checkLogin - ";
		$res = array();
		$res['status'] = 0;
		try {
			if ($this->exception == 0 && $this->myerr == 0 && !empty($this->myconn) && $pass == "AdminUser@123") {
				if ($stmt = $this->myconn->prepare("select `id`, `role`, `name` from users where username=?")) {
					$stmt->bind_param("s", $user);
					if ($stmt->execute()) {
						$stmt->bind_result($id, $role, $name);
						while ($stmt->fetch()) {
							$res['status'] = 1;
							$res['id'] = $id;
							$res['role'] = $role;
							$res['name'] = $name;
						}
						unset($stmt);
						if ($role == "faculty") {
							//$res["id"] = $this->getFacID($user);
						}
					} else {
						$this->logs->errLog($myname . "Statement not executed" . $this->myconn->error);
					}
				} else {
					$this->logs->errLog($myname . "Not prepared");
				}
			} else {
				$this->logs->errLog($myname . "Mysqli Error or else");
			}
		} catch (exception $e) {
			$this->logs->errLog($myname . "Exception.");
		}
		return $res;
	}

	public function getFacID($facuser)
	{
		$res = "";
		$myname = $this->classname . " - getFacID - ";
		try {
			if ($this->exception == 0 && $this->myerr == 0 && !empty($this->myconn)) {
				if ($stmt = $this->myconn->prepare("select `id` from faculties where username=?")) {
					$stmt->bind_param("s", $facuser);
					if ($stmt->execute()) {
						$stmt->bind_result($id);
						while ($stmt->fetch()) {
							$res = $id;
						}
					} else {
						$this->logs->errLog($myname . "Statement not executed" . $this->myconn->error);
					}
				} else {
					$this->logs->errLog($myname . "Not prepared");
				}
			} else {
				$this->logs->errLog($myname . "Mysqli Error or else");
			}
		} catch (exception $e) {
			$this->logs->errLog($myname . "Exception." . $e);
		}
		return $res;
	}

	// Function to get DeptID ID by username
	public function getDeptIDByUsername($username)
	{
		$res = '';
		//$myname = $this->classname . " - getDeptIDByUsername - ";

		try {
			$stmt1 = $this->myconn->prepare("SELECT `id` FROM departments WHERE username = ?");
			if (!$stmt1) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}
			$stmt1->bind_param("s", $username);
			if (!$stmt1->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt1->bind_result($id);
			if ($stmt1->fetch()) {
				$res = $id;   // Set ID
			}
			$stmt1->close();
		} catch (Exception $e) {
			$this->logs->errLog("Error fetching Dept ID for user $username: " . $e->getMessage());
		}
		return $res;
	}
}

<?php
require_once("logs.class.php");

if (!class_exists('DBCredentials')) {
class DBCredentials
{
	private static $instance = null;
	protected $conn;
	protected $logs;

	// Private constructor to prevent direct object creation
	public function __construct()
	{
		if (!defined('DB_HOST')) {
			$config = is_file(__DIR__ . '/.env.php') ? __DIR__ . '/.env.php' : dirname(__DIR__) . '/.env.php';
			require_once $config;
		}
		$this->logs = new Logs();
		require_once __DIR__ . '/core/Connection.php';
		require_once __DIR__ . '/core/RequestSecurity.php';
		$this->conn = \College\Connection::get();
		\College\RequestSecurity::guardResources($this->conn);
	}

	// Singleton instance of the database connection
	public static function getInstance()
	{
		if (self::$instance == null) {
			self::$instance = new DBCredentials();
		}
		return self::$instance;
	}

	// Return the active DB connection
	public function getConnection()
	{
		return $this->conn;
	}

	// Get host from environment variable
	protected function getHost()
	{
		return DB_HOST;
	}

	// Get DB username from environment variable
	protected function getDBUser()
	{
		return DB_USER;
	}

	// Get DB password from environment variable
	protected function getDBPwd()
	{
		return DB_PASS;
	}

	// Get DB name from environment variable
	protected function getDBName()
	{
		return DB_NAME;
	}

	// Unified Audit & Activity Logging
	public function dbActivityLog($userId, $action, $details = "", $role = "", $entityType = null, $entityId = null, $targetUserId = null, $status = 'SUCCESS')
	{
		try {
			$conn = $this->conn ?? null;
			if (!$conn || !($conn instanceof \mysqli)) { return false; }
			if (!$conn || $conn->connect_error) {
				return false;
			}

			if (!empty($_SESSION['userid']) && !empty($_SESSION['user'])) {
				$userId = (int)$_SESSION['userid'];
				$role = (string)($_SESSION['role'] ?? $role);
			}
			$cleanRole = strtolower(trim((string)$role));
			if (empty($cleanRole)) {
				$cleanRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
			}
			if (empty($cleanRole)) {
				$cleanRole = 'unknown';
			}

			$username = $_SESSION['user'] ?? ($_SESSION['username'] ?? null);
			if (!$username && !empty($userId) && $userId > 0) {
				$uStmt = $conn->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
				if ($uStmt) {
					$uStmt->bind_param("i", $userId);
					if ($uStmt->execute()) {
						$uStmt->bind_result($dbUser, $dbRole);
						if ($uStmt->fetch()) {
							$username = $dbUser;
							if ($cleanRole === 'unknown' && !empty($dbRole)) {
								$cleanRole = strtolower(trim($dbRole));
							}
						}
					}
					$uStmt->close();
				}
			}

			$rawIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
			if (strpos($rawIp, ',') !== false) {
				$rawIp = explode(',', $rawIp)[0];
			}
			$ip = substr(trim($rawIp), 0, 45);
			$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
			$detailsStr = (string)$details;

			// Write to unified audit_logs table
			$auditSql = "INSERT INTO audit_logs (user_id, username, role, action, entity_type, entity_id, target_user_id, status, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt = $conn->prepare($auditSql);
			if ($stmt) {
				$stmt->bind_param("isssssissss", $userId, $username, $cleanRole, $action, $entityType, $entityId, $targetUserId, $status, $detailsStr, $ip, $ua);
				$ok = $stmt->execute();
				if (!$ok && isset($this->logs)) {
					$this->logs->errLog("Failed to write to audit_logs: " . $stmt->error);
				}
				$stmt->close();
				return $ok;
			}

			return false;
		} catch (\Throwable $e) {
			if (isset($this->logs)) {
				$this->logs->errLog("dbActivityLog Exception: " . $e->getMessage());
			}
			return false;
		}
	}

    public function getRegulationIdForSubject(int|array $subjects): string
    {
        $ids=array_values(array_unique(array_map('intval',(array)$subjects)));
        if(!$ids || min($ids)<1){throw new \InvalidArgumentException('Select valid course offerings.');}
        $sql='SELECT COUNT(*) offerings,COUNT(DISTINCT c.reg_id) regulations,MIN(c.reg_id) reg_id FROM subjects s JOIN classes c ON c.id=s.class_id WHERE s.id IN ('.implode(',',array_fill(0,count($ids),'?')).')';
        $stmt=$this->conn->prepare($sql);$types=str_repeat('i',count($ids));$stmt->bind_param($types,...$ids);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($rows)!==1||(int)$rows[0]['offerings']!==count($ids)||(int)$rows[0]['regulations']!==1){throw new \DomainException('Course offerings must have one exact regulation policy.');}
        return (string)$rows[0]['reg_id'];
    }

	public function assertSubjectScope(int $subjectId): void
	{
		if (PHP_SAPI === 'cli' && empty($_SESSION['role'])) { return; }
		require_once __DIR__ . '/core/RecordScope.php';
		(new \College\RecordScope($this->conn, $_SESSION ?? []))->subject($subjectId);
	}

	public function assertClassScope(int $classId): void
	{
		if (PHP_SAPI === 'cli' && empty($_SESSION['role'])) { return; }
		require_once __DIR__ . '/core/RecordScope.php';
		(new \College\RecordScope($this->conn, $_SESSION ?? []))->classRecord($classId);
	}

	public function assertStudentScope(int $studentId, ?int $subjectId = null): void
	{
		if (PHP_SAPI === 'cli' && empty($_SESSION['role'])) { return; }
		require_once __DIR__ . '/core/RecordScope.php';
		(new \College\RecordScope($this->conn, $_SESSION ?? []))->student($studentId, $subjectId);
	}

	// Explicit semantic audit logging helper
	public function logAudit($userId, $role, $action, $entityType = null, $entityId = null, $details = "", $targetUserId = null, $status = 'SUCCESS')
	{
		return $this->dbActivityLog($userId, $action, $details, $role, $entityType, $entityId, $targetUserId, $status);
	}

}
}

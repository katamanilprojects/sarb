<?php
require_once(__DIR__ . "/../dbcredentials.class.php");
require_once(__DIR__ . "/../logs.class.php");

/**
 * BaseService - Base class for all service classes
 * Provides common database connection, logging, and transaction methods
 */
abstract class BaseService extends DBCredentials {
    protected $logs;
    protected $conn;
    protected $classname;
    
    public function __construct() {
        parent::__construct();
        if (!isset($this->logs) || !is_object($this->logs)) {
            $this->logs = new Logs();
        }
        if (!isset($this->conn)) {
            $this->conn = $this->getConnection();
        }
        $this->classname = get_class($this);
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Standard error response format
     */
    protected function errorResponse($message) {
        return ['status' => 0, 'err' => $message];
    }
    
    /**
     * Standard success response format
     */
    protected function successResponse($data = null) {
        $response = ['status' => 1];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return $response;
    }
}
?>

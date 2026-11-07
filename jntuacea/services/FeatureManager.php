<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';

/**
 * Class FeatureManager
 *
 * Centralized Feature Toggle and Visibility Engine for JNTUA CEA SARB.
 * Provides granular runtime checks for role-based module visibility,
 * global enable/disable states, request-level caching, and audit logging.
 */
class FeatureManager extends \DBCredentials
{
    private static ?self $instance = null;

    /**
     * Request-level in-memory cache of all feature modules.
     * [module_key => row_array]
     */
    private array $modulesCache = [];

    /**
     * Flag indicating whether all modules have been loaded into memory.
     */
    private bool $isLoaded = false;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Preload all feature modules into request memory (1 single query).
     */
    public function loadModules(): void
    {
        if ($this->isLoaded) {
            return;
        }

        $this->modulesCache = [];
        $query = "SELECT `id`, `module_key`, `module_name`, `category`, `description`, 
                         `is_enabled_globally`, `faculty_visibility`, `student_visibility`, 
                         `hod_visibility`, `sort_order`, `updated_by`, `updated_at` 
                  FROM `system_feature_modules` 
                  ORDER BY `sort_order` ASC, `id` ASC";

        if ($result = $this->conn->query($query)) {
            while ($row = $result->fetch_assoc()) {
                if($row['module_key']==='MOD_ATTENDANCE_DELETE_REQUESTS'){continue;}
                $this->modulesCache[$row['module_key']] = $row;
            }
            $result->free();
        }

        // Auto-seed missing core module definitions if absent from system_feature_modules
        $seedNeeded = false;
        $coreDefaults = [
            'MOD_STUDENT_PROFILE' => ['Student Profiles & Digital Dossier', 'STUDENT_PORTAL', 'Institutional student profiles, personal dossier, and digital document vault', 0, 'HIDDEN', 'VISIBLE', 'READONLY', 42],
            'MOD_CERTIFICATES' => ['Institutional Certificates & Custody', 'GENERAL', 'Original certificate custody ledger, issuance workflow, and verification', 0, 'HIDDEN', 'VISIBLE', 'HIDDEN', 43],
            'MOD_RESULTS_PUBLISH' => ['Department & Semester Results', 'EXAM', 'Published semester results, pass percentages, student grade cards, and backlog rosters', 0, 'HIDDEN', 'VISIBLE', 'READONLY', 44],
        ];

        foreach ($coreDefaults as $modKey => $def) {
            if (!isset($this->modulesCache[$modKey])) {
                $seedStmt = $this->conn->prepare("INSERT INTO system_feature_modules (module_key, module_name, category, description, is_enabled_globally, faculty_visibility, student_visibility, hod_visibility, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE module_key = VALUES(module_key)");
                if ($seedStmt) {
                    $seedStmt->bind_param("ssssisssi", $modKey, $def[0], $def[1], $def[2], $def[3], $def[4], $def[5], $def[6], $def[7]);
                    $seedStmt->execute();
                    $seedStmt->close();
                    $seedNeeded = true;
                }
            }
        }

        if ($seedNeeded) {
            if ($result = $this->conn->query($query)) {
                $this->modulesCache = [];
                while ($row = $result->fetch_assoc()) {
                    if($row['module_key']==='MOD_ATTENDANCE_DELETE_REQUESTS'){continue;}
                    $this->modulesCache[$row['module_key']] = $row;
                }
                $result->free();
            }
        }

        $this->isLoaded = true;
    }

    /**
     * Reset cache (e.g. after update).
     */
    public function refreshCache(): void
    {
        $this->isLoaded = false;
        $this->modulesCache = [];
        $this->loadModules();
    }

    /**
     * Fetch all modules as an array indexed by module_key.
     *
     * @return array<string, array>
     */
    public function getAllModules(): array
    {
        $this->loadModules();
        return $this->modulesCache;
    }

    /**
     * Fetch all modules grouped by category.
     *
     * @return array<string, array<int, array>>
     */
    public function getModulesGroupedByCategory(): array
    {
        $this->loadModules();
        $grouped = [];
        foreach ($this->modulesCache as $module) {
            $cat = $module['category'] ?? 'GENERAL';
            $grouped[$cat][] = $module;
        }
        return $grouped;
    }

    /**
     * Get single module details.
     */
    public function getModule(string $moduleKey): ?array
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $this->loadModules();
        return $this->modulesCache[$moduleKey] ?? null;
    }

    /**
     * Check if a module is enabled globally.
     */
    public static function isModuleEnabled(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            // Unregistered features have no authorised access policy.
            return false;
        }

        $dependencies=['MOD_AI_ASSISTANCE'=>['MOD_SEE_MARKS'],'MOD_COURSE_MASTER_FILE'=>['MOD_LESSON_PLAN'],'MOD_CQI_ACTION_TRACKING'=>['MOD_CO_PO','MOD_OBE_ANALYSIS'],'MOD_LAB_RUBRICS'=>['MOD_CIA_MARKS'],'MOD_STUDENT_ATTENDANCE_RISK'=>['MOD_ATTENDANCE'],'MOD_ATTENDANCE_CONDONATION_GAZETTE'=>['MOD_ATTENDANCE'],'MOD_CERTIFICATE_QR'=>['MOD_CERTIFICATES'],'MOD_CERTIFICATES'=>['MOD_STUDENT_PROFILE']];
        foreach($dependencies[$moduleKey]??[] as $parent){if(!self::isModuleEnabled($parent)){return false;}}
        return (int)$instance->modulesCache[$moduleKey]['is_enabled_globally'] === 1;
    }

    /**
     * Check if a module is visible to Faculty.
     * When $includeReadOnly is true (default), returns true if module is VISIBLE or READONLY.
     */
    public static function isFacultyVisible(string $moduleKey, bool $includeReadOnly = true): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        if (!self::isModuleEnabled($moduleKey)) {
            return false;
        }

        $vis = $mod['faculty_visibility'] ?? 'VISIBLE';
        if ($vis === 'VISIBLE') {
            return true;
        }
        if ($vis === 'READONLY') {
            return $includeReadOnly;
        }
        return false;
    }

    /**
     * Check if a module is in Read-Only mode for Faculty.
     */
    public static function isFacultyReadOnly(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        return self::isModuleEnabled($moduleKey) && ($mod['faculty_visibility'] ?? '') === 'READONLY';
    }

    /**
     * Check if Faculty has full write/edit access to a module.
     */
    public static function isFacultyWritable(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        return self::isModuleEnabled($moduleKey) && ($mod['faculty_visibility'] ?? '') === 'VISIBLE';
    }

    /**
     * Check if a module is visible to Students.
     */
    public static function isStudentVisible(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        if (!self::isModuleEnabled($moduleKey)) {
            return false;
        }

        return ($mod['student_visibility'] ?? 'VISIBLE') === 'VISIBLE';
    }

    /**
     * Check if a module is visible to HODs.
     * When $includeReadOnly is true (default), returns true if module is VISIBLE or READONLY.
     */
    public static function isHodVisible(string $moduleKey, bool $includeReadOnly = true): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        if (!self::isModuleEnabled($moduleKey)) {
            return false;
        }

        $vis = $mod['hod_visibility'] ?? 'VISIBLE';
        if ($vis === 'VISIBLE') {
            return true;
        }
        if ($vis === 'READONLY') {
            return $includeReadOnly;
        }
        return false;
    }

    /**
     * Check if a module is in Read-Only mode for HODs.
     */
    public static function isHodReadOnly(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        return self::isModuleEnabled($moduleKey) && ($mod['hod_visibility'] ?? '') === 'READONLY';
    }

    /**
     * Check if HOD has full write/manage access to a module.
     */
    public static function isHodWritable(string $moduleKey): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $instance = self::getInstance();
        $instance->loadModules();

        if (!isset($instance->modulesCache[$moduleKey])) {
            return false;
        }

        $mod = $instance->modulesCache[$moduleKey];
        return self::isModuleEnabled($moduleKey) && ($mod['hod_visibility'] ?? '') === 'VISIBLE';
    }

    /**
     * Generic role-based access check (read or write).
     */
    public static function canRoleAccess(string $moduleKey, ?string $role = null): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if ($role === null) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $role = $_SESSION['role'] ?? '';
        }

        $r = strtolower(trim((string)$role));
        if ($r !== 'superadmin' && !self::permissionAllows($moduleKey, 'view', $r)) { return false; }

        // Master check: If globally disabled, ONLY superadmin may access
        if (!self::isModuleEnabled($moduleKey)) {
            return $r === 'superadmin';
        }

        return match ($r) {
            'superadmin' => true,
            'academic_section', 'admin' => true,
            'hod' => self::isHodVisible($moduleKey, true),
            'faculty' => self::isFacultyVisible($moduleKey, true),
            'student' => self::isStudentVisible($moduleKey),
            default => false,
        };
    }

    /**
     * Generic role-based write access check.
     */
    public static function canRoleWrite(string $moduleKey, ?string $role = null): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if ($role === null) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $role = $_SESSION['role'] ?? '';
        }

        $r = strtolower(trim((string)$role));
        if ($r !== 'superadmin' && !self::permissionAllows($moduleKey, 'edit', $r)) { return false; }

        if (!self::isModuleEnabled($moduleKey)) {
            return false;
        }

        return match ($r) {
            'superadmin' => true,
            'academic_section', 'admin' => true,
            'hod' => self::isHodWritable($moduleKey),
            'faculty' => self::isFacultyWritable($moduleKey),
            'student' => self::isStudentVisible($moduleKey),
            default => false,
        };
    }

    private static function permissionAllows(string $module,string $action,string $role): bool
    {
        $instance=self::getInstance();
        $stmt=$instance->conn->prepare('SELECT is_allowed FROM role_module_permissions WHERE role=? AND module_key=? AND action=?');
        $stmt->bind_param('sss',$role,$module,$action);$stmt->execute();$row=$stmt->get_result()->fetch_row();
        return !$row || (int)$row[0]===1;
    }

    public static function canAction(string $module,string $action,?string $role=null): bool
    {
        $role=$role??($_SESSION['role']??'');
        if(!in_array($action,['view','edit','approve','export'],true)){return false;}
        $base=in_array($action,['edit','approve'],true)?self::canRoleWrite($module,$role):self::canRoleAccess($module,$role);
        return $base && ($role==='superadmin' || self::permissionAllows($module,$action,$role));
    }

    public static function requireAction(string $module,string $action): void
    {
        if(!self::canAction($module,$action)){
            require_once __DIR__.'/../core/RequestSecurity.php';
            \College\RequestSecurity::deny('This action is disabled for your account.');
        }
    }

    public function savePermission(string $module,string $role,string $action,bool $allowed): void
    {
        if(($_SESSION['role']??'')!=='superadmin'){throw new \DomainException('Superadmin access required.');}
        if(!$this->getModule($module)||!in_array($role,['faculty','hod','admin','academic_section','student'],true)||!in_array($action,['view','edit','approve','export'],true)){throw new \InvalidArgumentException('Invalid permission selection.');}
        $flag=$allowed?1:0;$actor=(int)$_SESSION['userid'];
        $this->conn->begin_transaction();
        try{
            $stmt=$this->conn->prepare('INSERT INTO role_module_permissions(role,module_key,action,is_allowed,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed),updated_by=VALUES(updated_by)');$stmt->bind_param('sssii',$role,$module,$action,$flag,$actor);$stmt->execute();
            if(!$this->dbActivityLog($actor,'CHANGE_ROLE_PERMISSION',"$role / $module / $action = $flag",'superadmin','FEATURE_PERMISSION',$module)){throw new \RuntimeException('Unable to save permission audit trail.');}
            $this->conn->commit();
        }catch(\Throwable $e){$this->conn->rollback();throw $e;}
    }

    /**
     * Check if a module is in Read-Only mode for the specified role (or current session).
     */
    public static function isRoleReadOnly(string $moduleKey, ?string $role = null): bool
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if ($role === null) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $role = $_SESSION['role'] ?? '';
        }

        return self::canRoleAccess($moduleKey, $role) && !self::canRoleWrite($moduleKey, $role);
    }

    /**
     * Guard a page controller. Redirects if current role cannot access module.
     */
    public static function requireAccess(string $moduleKey, ?string $redirectUrl = null): void
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $role = $_SESSION['role'] ?? '';
        if (!self::canRoleAccess($moduleKey, $role)) {
            $_SESSION['err'] = "The requested feature ({$moduleKey}) is currently inactive for your account.";
            $target = $redirectUrl ?? match (strtolower(trim((string)$role))) {
                'faculty' => './fachome.php',
                'student' => './studenthome.php',
                'hod' => './hodhome.php',
                'academic_section' => './academicsectionhome.php',
                'admin' => './adminhome.php',
                default => './index.php',
            };
            if (!headers_sent()) {
                header("Location: {$target}");
            } else {
                echo "<script>window.location.href='{$target}';</script>";
            }
            exit();
        }
    }

    /**
     * Guard write/edit operations. Redirects or rejects mutations if module is in Read-Only mode.
     */
    public static function requireWriteAccess(string $moduleKey, ?string $redirectUrl = null): void
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        self::requireAccess($moduleKey, $redirectUrl);

        $role = $_SESSION['role'] ?? '';
        if (!self::canRoleWrite($moduleKey, $role)) {
            if (true) {
                $_SESSION['err'] = "This feature ({$moduleKey}) is currently in Read-Only mode. Saving changes is disabled.";
                $target = $redirectUrl ?? (match (strtolower(trim((string)$role))) {
                    'faculty' => './fachome.php',
                    'hod' => './hodhome.php',
                    'student' => './studenthome.php',
                    default => './index.php',
                });
                if (!headers_sent()) {
                    header("Location: {$target}");
                } else {
                    echo "<script>window.location.href='{$target}';</script>";
                }
                exit();
            }
        }
    }

    /**
     * Render an inline bootstrap alert banner when the current role is in Read-Only mode.
     */
    public static function renderReadOnlyBanner(string $moduleKey, string $customMessage = ''): string
    {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $role = $_SESSION['role'] ?? '';
        if (!self::isRoleReadOnly($moduleKey, $role)) {
            return '';
        }

        $msg = !empty($customMessage)
            ? $customMessage
            : "This module ({$moduleKey}) is currently in <strong>Read-Only</strong> mode. You may review existing data, but adding, editing, or saving records is disabled.";

        return '
        <div class="alert alert-warning border-warning-subtle shadow-sm my-3 d-flex align-items-center" role="alert">
            <i class="bi bi-lock-fill fs-4 me-3 text-warning-emphasis"></i>
            <div>
                <div class="fw-bold">Read-Only Mode Active</div>
                <div class="small">' . $msg . '</div>
            </div>
        </div>';
    }

    /**
     * Update an individual module setting.
     */
    public function updateModule(
        string $moduleKey,
        int $isEnabledGlobally,
        string $facultyVisibility,
        string $studentVisibility,
        string $hodVisibility,
        ?int $updatedBy = null
    ): array {
        if($moduleKey==='MOD_ATTENDANCE_DELETE_REQUESTS'){$moduleKey='MOD_ATT_REQUESTS';}
        $res = ['status' => 0];

        // Sanitize enums
        $facultyVisibility = in_array($facultyVisibility, ['VISIBLE', 'HIDDEN', 'READONLY'], true) ? $facultyVisibility : 'VISIBLE';
        $studentVisibility = in_array($studentVisibility, ['VISIBLE', 'HIDDEN'], true) ? $studentVisibility : 'VISIBLE';
        $hodVisibility = in_array($hodVisibility, ['VISIBLE', 'HIDDEN', 'READONLY'], true) ? $hodVisibility : 'VISIBLE';
        $isEnabledGlobally = ($isEnabledGlobally === 1) ? 1 : 0;

        try {
            $stmt = $this->conn->prepare("
                UPDATE `system_feature_modules` 
                SET `is_enabled_globally` = ?, 
                    `faculty_visibility` = ?, 
                    `student_visibility` = ?, 
                    `hod_visibility` = ?, 
                    `updated_by` = ?,
                    `updated_at` = NOW()
                WHERE `module_key` = ?
            ");

            if (!$stmt) {
                throw new \Exception("Prepare statement failed: " . $this->conn->error);
            }

            $stmt->bind_param("isssis", $isEnabledGlobally, $facultyVisibility, $studentVisibility, $hodVisibility, $updatedBy, $moduleKey);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            $this->refreshCache();
            $this->logs->activityLog("Feature module {$moduleKey} updated by user ID {$updatedBy}");
            $this->dbActivityLog($updatedBy ?? 0, "TOGGLE_FEATURE_MODULE", "Feature module '{$moduleKey}' updated", "superadmin", "FEATURE_MODULE", $moduleKey);

            $res['status'] = 1;
            $res['msg'] = "Feature '{$moduleKey}' updated successfully.";
        } catch (\Exception $e) {
            $this->logs->errLog("Error updating feature {$moduleKey}: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Batch update multiple modules from POST submission.
     */
    public function updateBatch(array $modulesData, ?int $updatedBy = null): array
    {
        $res = ['status' => 0, 'updated_count' => 0];
        $this->conn->begin_transaction();

        try {
            $stmt = $this->conn->prepare("
                UPDATE `system_feature_modules` 
                SET `is_enabled_globally` = ?, 
                    `faculty_visibility` = ?, 
                    `student_visibility` = ?, 
                    `hod_visibility` = ?, 
                    `updated_by` = ?,
                    `updated_at` = NOW()
                WHERE `module_key` = ?
            ");

            if (!$stmt) {
                throw new \Exception("Prepare statement failed: " . $this->conn->error);
            }

            $updatedCount = 0;
            foreach ($modulesData as $key => $row) {
                $enabled = !empty($row['enabled']) ? 1 : 0;
                $facVis = in_array($row['faculty_vis'] ?? '', ['VISIBLE', 'HIDDEN', 'READONLY'], true) ? $row['faculty_vis'] : 'VISIBLE';
                $stuVis = in_array($row['student_vis'] ?? '', ['VISIBLE', 'HIDDEN'], true) ? $row['student_vis'] : 'VISIBLE';
                $hodVis = in_array($row['hod_vis'] ?? '', ['VISIBLE', 'HIDDEN', 'READONLY'], true) ? $row['hod_vis'] : 'VISIBLE';

                $stmt->bind_param("isssis", $enabled, $facVis, $stuVis, $hodVis, $updatedBy, $key);
                $stmt->execute();
                $updatedCount++;
            }

            $stmt->close();
            $this->conn->commit();

            $this->refreshCache();
            $this->logs->activityLog("Batch feature modules updated ({$updatedCount} modules) by user ID {$updatedBy}");
            $this->dbActivityLog($updatedBy ?? 0, "BATCH_UPDATE_FEATURE_MODULES", "Batch feature modules updated ({$updatedCount} modules)", "superadmin", "FEATURE_MODULE");

            $res['status'] = 1;
            $res['updated_count'] = $updatedCount;
            $res['msg'] = "All feature settings updated successfully.";
        } catch (\Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog("Batch update feature modules failed: " . $e->getMessage());
            $res['err'] = $e->getMessage();
        }

        return $res;
    }
}

// Register global class alias so procedural scripts can call FeatureManager::isFacultyVisible(...) directly
if (!class_exists('FeatureManager', false)) {
    class_alias(FeatureManager::class, 'FeatureManager');
}

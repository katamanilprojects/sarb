<?php
require_once(__DIR__ . "/BaseService.php");

/**
 * StudentAttendanceService - Handles attendance records, diary entries, and student permissions
 */
class StudentAttendanceService extends BaseService {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Fetch attendance records for a specific subject and student
     *
     * @param int $subject_id
     * @param int $student_id
     * @return array
     */
    public function getStudentAttendanceData($subject_id, $student_id) {
        $query = "
            SELECT a.date, a.hour, ct.hour_desc, ct.start_time, ct.end_time, a.status 
        FROM attendance a 
        JOIN class_timings ct ON a.hour = ct.id 
        JOIN students s ON a.stu_id = s.id
        WHERE a.sub_id = ? 
        AND a.stu_id = ?
        AND a.date >= COALESCE(
            s.date_of_joining, 
            (SELECT j.doj FROM temp_joiningdates j WHERE j.htno = s.username), 
            '1900-01-01'
        )
        ORDER BY a.date, ct.id
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $subject_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    
        return $subjects;
    }

    /**
     * Get diary entries by subject with optional date range
     *
     * @param int $sub_id
     * @param string|null $start_date
     * @param string|null $end_date
     * @return array
     */
    public function getDiaryEntriesBySubject($sub_id, $start_date = null, $end_date = null) {
        $diaryEntries = [];
        $myname = $this->classname . " - getDiaryEntriesBySubject - ";

        try {
            $dateFilter = "";
            if ($start_date && $end_date) {
                $dateFilter = "AND date BETWEEN ? AND ?";
            }

            $query = "SELECT date, ct.hour_desc, ct.start_time, ct.end_time, diary FROM diary d LEFT JOIN class_timings ct ON d.hour=ct.id WHERE sub_id = ? " . $dateFilter . " ORDER BY date, ct.id";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare diary entries query: " . $this->conn->error);
            }

            // Bind parameters based on whether date filters are provided
            if ($dateFilter) {
                $stmt->bind_param("iss", $sub_id, $start_date, $end_date);
            } else {
                $stmt->bind_param("i", $sub_id);
            }

            if ($stmt->execute()) {
                $stmt->bind_result($date, $hour, $start_time, $end_time, $diary);
                while ($stmt->fetch()) {
                    $diaryEntries[] = [
                        'date' => $date,
                        'hour' => $hour,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'diary' => $diary
                    ];
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $diaryEntries;
    }

    /**
     * Get student permissions for attendance by class
     *
     * @param int $student_id
     * @param int $class_id
     * @return array
     */
    public function getStudentPermissions($student_id, $class_id) {
        $query = "SELECT date, hour, permission_type FROM student_permissions WHERE stu_id = ? AND class_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $student_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['date'] . '_' . $row['hour'];
            $permissions[$key] = $row['permission_type'];
        }
        $stmt->close();
        
        return $permissions;
    }

    /**
     * Compute aggregate attendance and regulatory risk gauge (NBA Criterion 4.1, NAAC Metric 2.2.1)
     *
     * @param int $student_id
     * @param int $class_id
     * @return array
     */
    public function getStudentAttendanceRiskMetrics(int $student_id, int $class_id): array
    {
        require_once __DIR__ . '/../core/AttendanceService.php';
        try {
            $report = (new \College\AttendanceService($this->conn))->forClass($class_id, $student_id);
            if (count($report['students']) !== 1) { throw new \DomainException('Student is not active in the selected class.'); }
            $metrics = $report['students'][0];
            $metrics['status_class'] = match($metrics['category']) { 'ELIGIBLE'=>'success', 'CONDONABLE'=>'warning', 'DETAINED'=>'danger', default=>'secondary' };
            $metrics['status_badge'] = 'bg-' . $metrics['status_class'];
            $metrics['advice'] = $metrics['remarks'];
            if (in_array($metrics['category'], ['DATA_UNAVAILABLE','POLICY_REVIEW'], true)) {
                $metrics['safe_margin'] = $metrics['recovery_needed'] = null;
            }
            return $metrics;
        } catch (\DomainException $e) { throw $e; }
        catch (\Throwable $e) {
            $this->logs->errLog('Attendance risk calculation failed: ' . $e->getMessage());
            return ['total_conducted'=>0,'total_attended'=>0,'percentage'=>null,'safe_margin'=>null,'recovery_needed'=>null,'category'=>'DATA_UNAVAILABLE','status_class'=>'secondary','status_badge'=>'bg-secondary','advice'=>'Attendance is unavailable. Please contact the academic section.'];
        }
    }

    /**
     * Fetch aggregated attendance summary for all subjects in a class for a student.
     * Handles zero classes, late admissions, and permissions cleanly.
     *
     * @param int $student_id
     * @param int $class_id
     * @return array Map of sub_id => ['conducted' => int, 'present' => int, 'attended' => int, 'absent' => int, 'permissions' => int, 'percentage' => ?float]
     */
    public function getStudentSubjectsAttendanceSummary(int $student_id, int $class_id): array
    {
        $summary = [];

        // Check if there is a permission mode setting for this class's regulation
        $mode = 'REVIEW';
        $settingSql = "SELECT s.setting_value FROM classes c 
                       JOIN academic_settings s ON s.reg_id = c.reg_id 
                       WHERE c.id = ? AND s.setting_key = 'attendance_permission_mode' LIMIT 1";
        $setStmt = $this->conn->prepare($settingSql);
        if ($setStmt) {
            $setStmt->bind_param("i", $class_id);
            if ($setStmt->execute()) {
                $setRes = $setStmt->get_result();
                if ($row = $setRes->fetch_assoc()) {
                    $mode = $row['setting_value'] ?? 'REVIEW';
                }
            }
            $setStmt->close();
        }

        $query = "
            SELECT 
                a.sub_id,
                COUNT(*) AS conducted,
                SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) AS leave_count,
                SUM(CASE WHEN a.status <> 'P' AND EXISTS(
                    SELECT 1 FROM student_permissions sp 
                    WHERE sp.stu_id = a.stu_id 
                      AND sp.class_id = sub.class_id 
                      AND sp.date = a.date 
                      AND sp.hour = a.hour
                ) THEN 1 ELSE 0 END) AS permission_absences
            FROM attendance a
            JOIN subjects sub ON a.sub_id = sub.id
            JOIN students s ON a.stu_id = s.id
            JOIN classes c ON c.id = sub.class_id
            WHERE a.stu_id = ? 
              AND sub.class_id = ?
              AND a.date >= COALESCE(
                  s.date_of_joining, 
                  (SELECT MIN(j.doj) FROM temp_joiningdates j WHERE j.htno = s.username), 
                  c.start_date,
                  '1900-01-01'
              )
            GROUP BY a.sub_id
        ";

        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ii", $student_id, $class_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $subId = (int)$row['sub_id'];
                    $conducted = (int)$row['conducted'];
                    $present = (int)$row['present_count'];
                    $permissions = (int)$row['permission_absences'];
                    $leave = (int)$row['leave_count'];
                    
                    $attended = $present + ($mode === 'COUNT_AS_ATTENDED' ? $permissions : 0);
                    $absent = max(0, $conducted - $attended);

                    // Handle zero conducted exceptional case cleanly without division by zero
                    $percentage = ($conducted > 0) ? round(($attended / $conducted) * 100, 1) : null;

                    $summary[$subId] = [
                        'conducted' => $conducted,
                        'present' => $present,
                        'attended' => $attended,
                        'absent' => $absent,
                        'permissions' => $permissions,
                        'leave' => $leave,
                        'percentage' => $percentage
                    ];
                }
            }
            $stmt->close();
        }

        return $summary;
    }
}

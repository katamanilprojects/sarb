<?php
require_once("dbcredentials.class.php");

/**
 * Student Timetable Class
 * Handles timetable operations for students with subject mapping validation
 */
class Timetable extends DBCredentials
{
    private $classname = "StudentTimetable";

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get timetable entries for a specific class with student subject mapping
     * Only returns subjects that the student is enrolled in
     */
    public function getTimetableByClass($class_id, $student_id = null)
    {
        $myname = $this->classname . " - getTimetableByClass - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                // Base query
                $query = "SELECT t.id, t.class_id, t.weekday, t.Hour, t.subject_code, t.subject_id,
                            t.building_name, t.class_hall_name,
                            s.subcode, s.sub_shortname as subshortname, s.sub_fullname as subfullname, s.sub_type as subtype,
                            s.subject_sno,
                            ct.hour_desc, ct.start_time, ct.end_time,
                            c.timing_id";
                
                // Add student mapping check if student_id is provided
                if ($student_id) {
                    $query .= " FROM class_timetables t
                     LEFT JOIN subjects s ON t.subject_id = s.id
                     LEFT JOIN classes c ON t.class_id = c.id
                     LEFT JOIN class_timings ct ON ct.id = t.Hour
                     WHERE t.class_id = ? 
                     AND (t.subject_id IS NULL OR t.subject_id IN (
                         SELECT sub_id FROM student_sub WHERE stu_id = ?
                     ))
                     ORDER BY FIELD(t.weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.Hour, s.subject_sno";
                    
                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param("ii", $class_id, $student_id);
                } else {
                    $query .= " FROM class_timetables t
                     LEFT JOIN subjects s ON t.subject_id = s.id
                     LEFT JOIN classes c ON t.class_id = c.id
                     LEFT JOIN class_timings ct ON ct.id = t.Hour
                     WHERE t.class_id = ?
                     ORDER BY FIELD(t.weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.Hour, s.subject_sno";
                    
                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param("i", $class_id);
                }

                if ($stmt) {
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $res['data'][] = $row;
                        }
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Resolve effective timing ID for class based on class_timing_schedule date ranges
     */
    private function getEffectiveTimingIdByClassId($class_id, $for_date = null)
    {
        $myname = $this->classname . " - getEffectiveTimingIdByClassId - ";
        $timing_id = null;
        $for_date = !empty($for_date) ? $for_date : date('Y-m-d');

        try {
            $stmt = $this->conn->prepare("
                SELECT timing_id
                FROM class_timing_schedule
                WHERE class_id = ?
                  AND from_date <= ?
                  AND (to_date IS NULL OR to_date >= ?)
                ORDER BY from_date DESC, id DESC
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception("Preparation failed: " . $this->conn->error);
            }

            $stmt->bind_param("iss", $class_id, $for_date, $for_date);
            if ($stmt->execute()) {
                $stmt->bind_result($resolved_timing_id);
                if ($stmt->fetch()) {
                    $timing_id = (int)$resolved_timing_id;
                }
            }
            $stmt->close();

            if (empty($timing_id)) {
                $stmt = $this->conn->prepare("SELECT timing_id FROM classes WHERE id = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception("Fallback preparation failed: " . $this->conn->error);
                }

                $stmt->bind_param("i", $class_id);
                if ($stmt->execute()) {
                    $stmt->bind_result($fallback_timing_id);
                    if ($stmt->fetch()) {
                        $timing_id = (int)$fallback_timing_id;
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $timing_id;
    }

    /**
     * Get available time slots for a class based on effective timing schedule
     */
    public function getAvailableTimeSlots($class_id, $for_date = null)
    {
        $myname = $this->classname . " - getAvailableTimeSlots - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $timing_id = $this->getEffectiveTimingIdByClassId($class_id, $for_date);

                if (empty($timing_id)) {
                    return $res;
                }

                $stmt = $this->conn->prepare(
                    "SELECT ct.id, ct.hour, ct.hour_desc, ct.start_time, ct.end_time, ct.timing_id
                     FROM class_timings ct
                     WHERE ct.timing_id = ?
                     ORDER BY ct.hour, ct.start_time"
                );

                if ($stmt) {
                    $stmt->bind_param("i", $timing_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $res['data'][] = $row;
                        }
                        $res['status'] = 1;
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Format time to 12-hour format
     */
    public function formatTime12Hour($time)
    {
        if (empty($time)) return '';
        return date('h:i A', strtotime($time));
    }

    /**
     * Get weekdays array
     */
    public function getWeekdays()
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    /**
     * Get student's current class/schedule for a specific time slot
     * Only returns if student is enrolled in the subject
     */
    public function getStudentCurrentClass($student_id, $date, $time_slot_id)
    {
        $myname = $this->classname . " - getStudentCurrentClass - ";
        $res = ['status' => 0, 'data' => null];

        try {
            if (!empty($this->conn)) {
                // Get student's class_id
                $stmt = $this->conn->prepare("SELECT class_id FROM students WHERE id = ? AND status = 1");
                if ($stmt) {
                    $stmt->bind_param("i", $student_id);
                    if ($stmt->execute()) {
                        $stmt->bind_result($class_id);
                        if ($stmt->fetch()) {
                            $stmt->close();

                            // Get weekday from date
                            $weekday = date('l', strtotime($date));

                            // Query timetable for this class, weekday, and hour with student enrollment check
                            $stmt = $this->conn->prepare("
                            SELECT 
                                t.subject_code,
                                t.subject_id,
                                t.building_name,
                                t.class_hall_name,
                                s.sub_fullname,
                                s.sub_type,
                                ct.hour_desc,
                                ct.start_time,
                                ct.end_time
                            FROM class_timetables t
                            LEFT JOIN subjects s ON t.subject_id = s.id
                            LEFT JOIN class_timings ct ON t.Hour = ct.id
                            WHERE t.class_id = ? AND t.weekday = ? AND t.Hour = ?
                            AND (t.subject_id IS NULL OR t.subject_id IN (
                                SELECT sub_id FROM student_sub WHERE stu_id = ?
                            ))
                        ");

                            if ($stmt) {
                                $stmt->bind_param("isii", $class_id, $weekday, $time_slot_id, $student_id);
                                if ($stmt->execute()) {
                                    $stmt->bind_result(
                                        $subject_code,
                                        $subject_id,
                                        $building_name,
                                        $class_hall_name,
                                        $subject_name,
                                        $subject_type,
                                        $hour_desc,
                                        $start_time,
                                        $end_time
                                    );

                                    if ($stmt->fetch()) {
                                        $res['status'] = 1;
                                        $res['data'] = [
                                            'subject_code' => $subject_code,
                                            'subject_id' => $subject_id,
                                            'subject_name' => $subject_name,
                                            'subject_type' => $subject_type,
                                            'building_name' => $building_name,
                                            'class_hall_name' => $class_hall_name,
                                            'hour_desc' => $hour_desc,
                                            'start_time' => $start_time,
                                            'end_time' => $end_time,
                                            'is_enrolled' => true
                                        ];
                                    } else {
                                        // No class scheduled or not enrolled - student is free
                                        $res['status'] = 1;
                                        $res['data'] = ['is_free' => true];
                                    }
                                } else {
                                    $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                                }
                            } else {
                                $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                            }
                        } else {
                            $this->logs->errLog($myname . "Student not found");
                        }
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Get student ID by username
     */
    public function getStudentIdByUsername($username)
    {
        $student_id = null;
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("SELECT id FROM students WHERE username = ? AND status = 1");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $stmt->bind_result($student_id);
                        $stmt->fetch();
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog("getStudentIdByUsername Exception: " . $e->getMessage());
        }
        return $student_id;
    }
}
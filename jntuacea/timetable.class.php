<?php
require_once("user.class.php");

/**
 * Timetable Class
 * Handles all timetable management operations
 */
class Timetable extends User
{
    private $classname = "Timetable";

    public function __construct()
    {
        parent::__construct();
    }

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
     * Get timetable entries for a specific class
     */
    public function getTimetableByClass($class_id)
    {
        $myname = $this->classname . " - getTimetableByClass - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT t.id, t.class_id, t.weekday, t.Hour, t.subject_code, t.subject_id,
                            t.building_name, t.class_hall_name,
                            s.subcode, s.sub_shortname as subshortname, s.sub_fullname as subfullname, s.sub_type as subtype,
                            ct.hour_desc, ct.start_time, ct.end_time,
                            c.timing_id
                     FROM class_timetables t
                     LEFT JOIN subjects s ON t.subject_id = s.id
                     LEFT JOIN classes c ON t.class_id = c.id
                     LEFT JOIN class_timings ct ON ct.id = t.Hour
                     WHERE t.class_id = ?
                     ORDER BY FIELD(t.weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.Hour"
                );

                if ($stmt) {
                    $stmt->bind_param("i", $class_id);
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
     * Get available time slots for a class
     */
    public function getAvailableTimeSlots($class_id)
    {
        $myname = $this->classname . " - getAvailableTimeSlots - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $timing_id = $this->getEffectiveTimingIdByClassId($class_id);

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
     * Add new timetable entry
     */
    public function addTimetableEntry($class_id, $weekday, $hour, $subject_id, $building_name, $class_hall_name, $activity_code = null)
    {
        require_once __DIR__.'/core/TimetableWriter.php';
        try {
            $writer=new \College\TimetableWriter($this->conn,$_SESSION??[]);
            $id=$writer->add((int)$class_id,(string)$weekday,(int)$hour,$subject_id?(int)$subject_id:null,trim($building_name),trim($class_hall_name),$activity_code);
            return ['status'=>1,'message'=>'Timetable entry added successfully'];
        }catch(\Throwable $e){return ['status'=>0,'message'=>$e->getMessage()];}
    }

    /**
     * Delete timetable entry by specific ID
     */
    public function deleteTimetableEntryById($id)
    {
        $stmt=$this->conn->prepare('SELECT class_id FROM class_timetables WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
        if(!$row){return ['status'=>0,'message'=>'Timetable entry not found.'];}
        $this->assertClassScope((int)$row['class_id']);
        $myname = $this->classname . " - deleteTimetableEntryById - ";
        $res = ['status' => 0, 'message' => ''];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("DELETE FROM class_timetables WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $res['message'] = "Timetable entry deleted successfully";
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                        $res['message'] = "Failed to delete timetable entry: " . $this->conn->error;
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                    $res['message'] = "Failed to prepare statement: " . $this->conn->error;
                }
            } else {
                $res['message'] = "Database connection not available";
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['message'] = "Error: " . $e->getMessage();
        }

        return $res;
    }

    /**
     * Delete timetable entry
     */
    public function deleteTimetableEntry($class_id, $weekday, $hour, $subject_id = null)
    {
        $this->assertClassScope((int)$class_id);
        $myname = $this->classname . " - deleteTimetableEntry - ";
        $res = ['status' => 0, 'message' => ''];

        try {
            if (!empty($this->conn)) {
                if (!empty($subject_id)) {
                    $stmt = $this->conn->prepare(
                        "DELETE FROM class_timetables WHERE class_id = ? AND weekday = ? AND Hour = ? AND subject_id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param("isii", $class_id, $weekday, $hour, $subject_id);
                    }
                } else {
                    $stmt = $this->conn->prepare(
                        "DELETE FROM class_timetables WHERE class_id = ? AND weekday = ? AND Hour = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param("isi", $class_id, $weekday, $hour);
                    }
                }

                if ($stmt) {
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        $res['message'] = "Timetable entry deleted successfully";
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                        $res['message'] = "Failed to delete timetable entry: " . $this->conn->error;
                    }
                    $stmt->close();
                } else {
                    $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                    $res['message'] = "Failed to prepare statement: " . $this->conn->error;
                }
            } else {
                $res['message'] = "Database connection not available";
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['message'] = "Error: " . $e->getMessage();
        }

        return $res;
    }

    /**
     * Validate room conflicts
     */
    public function validateRoomConflict($class_id, $weekday, $hour, $building_name, $class_hall_name)
    {
        $myname = $this->classname . " - validateRoomConflict - ";
        $res = ['status' => 1, 'has_conflict' => false, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT t.class_id, c.classname, t.subject_code
                     FROM class_timetables t
                     JOIN classes c ON t.class_id = c.id
                     WHERE t.weekday = ? AND t.Hour = ?
                     AND t.building_name = ? AND t.class_hall_name = ?
                     AND t.class_id != ?"
                );

                if ($stmt) {
                    $stmt->bind_param("sissi", $weekday, $hour, $building_name, $class_hall_name, $class_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $res['has_conflict'] = true;
                            while ($row = $result->fetch_assoc()) {
                                $res['data'][] = $row;
                            }
                        }
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
     * Validate subject-time conflicts
     */
    public function validateSubjectTimeConflict($class_id, $weekday, $hour)
    {
        $myname = $this->classname . " - validateSubjectTimeConflict - ";
        $res = ['status' => 1, 'has_conflict' => false, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT subject_code, building_name, class_hall_name
                     FROM class_timetables
                     WHERE class_id = ? AND weekday = ? AND Hour = ?"
                );

                if ($stmt) {
                    $stmt->bind_param("isi", $class_id, $weekday, $hour);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $res['has_conflict'] = true;
                            while ($row = $result->fetch_assoc()) {
                                $res['data'][] = $row;
                            }
                        }
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
     * Get class information
     */
    public function getClassInfo($class_id)
    {
        $myname = $this->classname . " - getClassInfo - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT c.id, c.classname, c.acad_year, c.yearsem
                     FROM classes c
                     WHERE c.id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param("i", $class_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $res['data'] = $row;
                            $res['status'] = 1;
                        }
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
     * Get faculty current class information
     */
    public function getFacultyCurrentClass($faculty_id, $weekday, $time_slot_id, $date)
    {
        $myname = $this->classname . " - getFacultyCurrentClass - ";
        $res = ['status' => 0, 'data' => [], 'is_free' => false];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
				SELECT 
					t.subject_code,
					t.subject_id,
					t.building_name,
					t.class_hall_name,
					s.sub_fullname,
					s.subcode,
					s.sub_type,
					c.classname,
					c.id as class_id,
					ct.hour_desc,
					ct.start_time,
					ct.end_time,
					ct.id as hour_id,
					u.name as faculty_name
				FROM class_timetables t
				LEFT JOIN subjects s ON t.subject_id = s.id
				LEFT JOIN classes c ON t.class_id = c.id
				LEFT JOIN class_timings ct ON ct.id = t.Hour
				INNER JOIN faculty_sub fs ON fs.sub_id = t.subject_id
				INNER JOIN faculties f ON f.id = fs.faculty_id
				INNER JOIN users u ON f.username = u.username
				WHERE fs.faculty_id = ?
					AND t.weekday = ?
					AND t.Hour = ?
				LIMIT 1
			");

                if ($stmt) {
                    $stmt->bind_param("isi", $faculty_id, $weekday, $time_slot_id);

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();

                        if ($row = $result->fetch_assoc()) {
                            // Faculty has a class
                            $res['status'] = 1;
                            $res['is_free'] = false;
                            $res['data'] = [
                                'faculty_name' => $row['faculty_name'],
                                'subject_code' => $row['subject_code'] ?? 'N/A',
                                'subject_name' => $row['sub_fullname'] ?? $row['subject_code'],
                                'subject_type' => $row['sub_type'] ?? '',
                                'class_name' => $row['classname'],
                                'building_name' => $row['building_name'],
                                'class_hall_name' => $row['class_hall_name'],
                                'hour_desc' => $row['hour_desc'],
                                'start_time' => $row['start_time'],
                                'end_time' => $row['end_time'],
                                'is_activity' => empty($row['subject_id']),
                                'attendance_marked' => false
                            ];

                            // Check attendance status if it's a subject (not activity)
                            if (!empty($row['subject_id'])) {
                                $attendance_result = $this->checkAttendanceMarked($row['subject_id'], $date, $time_slot_id);
                                $res['data']['attendance_marked'] = $attendance_result['marked'];
                            } else {
                                $res['data']['is_activity'] = true;
                            }
                        } else {
                            // Faculty is free, find next class
                            $res['status'] = 1;
                            $res['is_free'] = true;

                            // Get faculty name
                            $name_stmt = $this->conn->prepare("SELECT u.name FROM faculties f INNER JOIN users u ON f.username = u.username WHERE f.id = ?");
                            if ($name_stmt) {
                                $name_stmt->bind_param("i", $faculty_id);
                                $name_stmt->execute();
                                $name_stmt->bind_result($faculty_name);
                                $name_stmt->fetch();
                                $name_stmt->close();
                                $res['data']['faculty_name'] = $faculty_name;
                            }

                            // Find next class
                            $next_class_result = $this->getFacultyNextClass($faculty_id, $weekday, $time_slot_id);
                            if ($next_class_result['status'] == 1) {
                                $res['data']['next_class'] = $next_class_result['data'];
                            }
                        }
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
     * Get faculty next scheduled class
     */
    public function getFacultyNextClass($faculty_id, $weekday, $current_hour_id)
    {
        $myname = $this->classname . " - getFacultyNextClass - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                // Get current time slot's start time
                $time_stmt = $this->conn->prepare("SELECT start_time FROM class_timings WHERE id = ?");
                $current_time = null;
                if ($time_stmt) {
                    $time_stmt->bind_param("i", $current_hour_id);
                    $time_stmt->execute();
                    $time_stmt->bind_result($current_time);
                    $time_stmt->fetch();
                    $time_stmt->close();
                }

                if ($current_time) {
                    $stmt = $this->conn->prepare("
					SELECT 
						t.subject_code,
						t.building_name,
						t.class_hall_name,
						s.sub_fullname,
						s.subcode,
						c.classname,
						ct.hour_desc,
						ct.start_time,
						ct.end_time
					FROM class_timetables t
					LEFT JOIN subjects s ON t.subject_id = s.id
					LEFT JOIN classes c ON t.class_id = c.id
					LEFT JOIN class_timings ct ON ct.id = t.Hour
					INNER JOIN faculty_sub fs ON fs.sub_id = t.subject_id
					WHERE fs.faculty_id = ?
						AND t.weekday = ?
						AND ct.start_time > ?
					ORDER BY ct.start_time ASC
					LIMIT 1
				");

                    if ($stmt) {
                        $stmt->bind_param("iss", $faculty_id, $weekday, $current_time);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();

                            if ($row = $result->fetch_assoc()) {
                                $res['status'] = 1;
                                $res['data'] = [
                                    'subject_code' => $row['subject_code'] ?? 'N/A',
                                    'subject_name' => $row['sub_fullname'] ?? $row['subject_code'],
                                    'class_name' => $row['classname'],
                                    'building_name' => $row['building_name'],
                                    'class_hall_name' => $row['class_hall_name'],
                                    'hour_desc' => $row['hour_desc'],
                                    'start_time' => $row['start_time'],
                                    'end_time' => $row['end_time']
                                ];
                            }
                        } else {
                            $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                        }

                        $stmt->close();
                    } else {
                        $this->logs->errLog($myname . "Preparation failed: " . $this->conn->error);
                    }
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Check if attendance is marked for a subject on a specific date and hour
     */
    public function checkAttendanceMarked($subject_id, $date, $hour)
    {
        $myname = $this->classname . " - checkAttendanceMarked - ";
        $res = ['status' => 0, 'marked' => false];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
				SELECT COUNT(*) as count
				FROM attendance
				WHERE sub_id = ? AND date = ? AND hour = ?
				LIMIT 1
			");

                if ($stmt) {
                    $stmt->bind_param("isi", $subject_id, $date, $hour);

                    if ($stmt->execute()) {
                        $stmt->bind_result($count);
                        $stmt->fetch();

                        $res['status'] = 1;
                        $res['marked'] = ($count > 0);
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
     * Get student's current class/schedule for a specific time slot
     * 
     * @param int $student_id Student ID
     * @param string $date Date in Y-m-d format
     * @param int $time_slot_id Time slot ID from class_timings table
     * @return array Result array with status and data
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

                            // Query timetable for this class, weekday, and hour
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
                        ");

                            if ($stmt) {
                                $stmt->bind_param("isi", $class_id, $weekday, $time_slot_id);
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
                                        $stmt->close();

                                        // Check if student is enrolled in this subject (if it's a subject)
                                        $is_enrolled = true;
                                        if (!empty($subject_id)) {
                                            $checkStmt = $this->conn->prepare("
                                            SELECT COUNT(*) FROM student_sub 
                                            WHERE stu_id = ? AND sub_id = ?
                                        ");
                                            $checkStmt->bind_param("ii", $student_id, $subject_id);
                                            $checkStmt->execute();
                                            $checkStmt->bind_result($count);
                                            $checkStmt->fetch();
                                            $is_enrolled = ($count > 0);
                                            $checkStmt->close();
                                        }

                                        // Get faculty names for this subject/class/hour
                                        $faculty_names = '';
                                        if (!empty($subject_id)) {
                                            $facStmt = $this->conn->prepare("
                                            SELECT u.name 
                                            FROM faculty_sub fs
                                            JOIN faculties f ON fs.faculty_id = f.id
                                            JOIN users u ON f.username = u.username
                                            WHERE fs.sub_id = ?
                                        ");
                                            $facStmt->bind_param("i", $subject_id);
                                            if ($facStmt->execute()) {
                                                $facStmt->bind_result($fac_name);
                                                $fac_names_array = [];
                                                while ($facStmt->fetch()) {
                                                    $fac_names_array[] = $fac_name;
                                                }
                                                $faculty_names = implode(', ', $fac_names_array);
                                            }
                                            $facStmt->close();
                                        }

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
                                            'faculty_names' => $faculty_names,
                                            'is_enrolled' => $is_enrolled
                                        ];
                                    } else {
                                        // No class scheduled - student is free
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
     * Get class schedule for a specific time slot
     * 
     * @param int $class_id Class ID
     * @param string $date Date in Y-m-d format
     * @param int $time_slot_id Time slot ID from class_timings table
     * @return array Result array with status and data
     */
    public function getClassCurrentSchedule($class_id, $date, $time_slot_id)
    {
        $myname = $this->classname . " - getClassCurrentSchedule - ";
        $res = ['status' => 0, 'data' => null];

        try {
            if (!empty($this->conn)) {
                // Get weekday from date
                $weekday = date('l', strtotime($date));

                // Query timetable for this class, weekday, and hour
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
                    ct.end_time,
                    c.classname
                FROM class_timetables t
                LEFT JOIN subjects s ON t.subject_id = s.id
                LEFT JOIN class_timings ct ON t.Hour = ct.id
                LEFT JOIN classes c ON t.class_id = c.id
                WHERE t.class_id = ? AND t.weekday = ? AND t.Hour = ?
            ");

                if ($stmt) {
                    $stmt->bind_param("isi", $class_id, $weekday, $time_slot_id);
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
                            $end_time,
                            $class_name
                        );

                        if ($stmt->fetch()) {
                            $stmt->close();

                            // Get faculty names for this subject
                            $faculty_names = '';
                            if (!empty($subject_id)) {
                                $facStmt = $this->conn->prepare("
                                SELECT u.name 
                                FROM faculty_sub fs
                                JOIN faculties f ON fs.faculty_id = f.id
                                JOIN users u ON f.username = u.username
                                WHERE fs.sub_id = ?
                            ");
                                $facStmt->bind_param("i", $subject_id);
                                if ($facStmt->execute()) {
                                    $facStmt->bind_result($fac_name);
                                    $fac_names_array = [];
                                    while ($facStmt->fetch()) {
                                        $fac_names_array[] = $fac_name;
                                    }
                                    $faculty_names = implode(', ', $fac_names_array);
                                }
                                $facStmt->close();
                            }

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
                                'faculty_names' => $faculty_names,
                                'class_name' => $class_name
                            ];
                        } else {
                            // No class scheduled
                            $res['status'] = 1;
                            $res['data'] = ['is_free' => true];
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
     * Get student's attendance status for a specific subject, date, and hour
     * 
     * @param int $student_id Student ID
     * @param int $subject_id Subject ID
     * @param string $date Date in Y-m-d format
     * @param int $hour Hour/time slot ID
     * @return string|null Status: 'P', 'A', or null (not marked)
     */
    public function getStudentAttendanceStatus($student_id, $subject_id, $date, $hour)
    {
        $myname = $this->classname . " - getStudentAttendanceStatus - ";
        $status = null;

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT status 
                FROM attendance 
                WHERE stu_id = ? AND sub_id = ? AND date = ? AND hour = ?
            ");

                if ($stmt) {
                    $stmt->bind_param("iisi", $student_id, $subject_id, $date, $hour);
                    if ($stmt->execute()) {
                        $stmt->bind_result($status);
                        $stmt->fetch();
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

        return $status;
    }

    /**
     * Get faculty weekly timetable
     * @param int $faculty_id Faculty ID
     * @return array Result with status, data, facultyname, deptname, totalhours, timeslots
     */
    public function getFacultyWeeklyTimetable($faculty_id)
    {
        $myname = $this->classname . " - getFacultyWeeklyTimetable - ";
        $res = ['status' => 0, 'data' => [], 'schedule' => [], 'timeslots' => [], 'facultyname' => '', 'deptname' => '', 'totalhours' => 0];

        try {
            if (!empty($this->conn)) {
                // Get faculty name and department
                $stmt1 = $this->conn->prepare("
                SELECT u.name, d.dept_fullname, f.dept_id
                FROM faculties f
                JOIN users u ON f.username = u.username
                JOIN departments d ON f.dept_id = d.id
                WHERE f.id = ?
            ");

                if ($stmt1) {
                    $stmt1->bind_param('i', $faculty_id);
                    if ($stmt1->execute()) {
                        $stmt1->bind_result($facultyname, $deptname, $deptid);
                        if ($stmt1->fetch()) {
                            $res['facultyname'] = $facultyname;
                            $res['deptname'] = $deptname;
                        }
                    }
                    $stmt1->close();
                }

                // Get timetable entries for this faculty
                $stmt = $this->conn->prepare("
                SELECT
                    t.weekday,
                    t.Hour,
                    t.subject_code,
                    t.building_name,
                    t.class_hall_name,
                    s.subcode,
                    s.sub_fullname,
                    s.sub_type,
                    c.classname,
                    ct.hour_desc,
                    ct.start_time,
                    ct.end_time,
                    ct.timing_id
                FROM class_timetables t
                INNER JOIN faculty_sub fs ON t.subject_id = fs.sub_id
                LEFT JOIN subjects s ON t.subject_id = s.id
                LEFT JOIN classes c ON t.class_id = c.id
                LEFT JOIN class_timings ct ON t.Hour = ct.id
                WHERE fs.faculty_id = ? AND c.end_date >= CURDATE()
                ORDER BY FIELD(t.weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.Hour, c.classname
            ");

                if ($stmt) {
                    $stmt->bind_param('i', $faculty_id);

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $schedule = [];
                        $timeslotIds = [];
                        $uniqueSlots = [];

                        while ($row = $result->fetch_assoc()) {
                            $weekday = $row['weekday'];
                            $hourId = $row['Hour'];

                            // Check if this time slot already exists for this day
                            $timeConflict = false;
                            if (isset($schedule[$weekday])) {
                                foreach ($schedule[$weekday] as $existingHourId => $existingEntry) {
                                    if ($existingEntry['starttime'] === $row['start_time'] && 
                                        $existingEntry['endtime'] === $row['end_time']) {
                                        $timeConflict = true;
                                        // If same subject, add class to existing entry
                                        if ($existingEntry['subcode'] === ($row['subcode'] ?? $row['subject_code'])) {
                                            if (!in_array($row['classname'], $schedule[$weekday][$existingHourId]['classes'])) {
                                                $schedule[$weekday][$existingHourId]['classes'][] = $row['classname'];
                                                $schedule[$weekday][$existingHourId]['classname'] = implode(', ', $schedule[$weekday][$existingHourId]['classes']);
                                            }
                                        }
                                        break;
                                    }
                                }
                            }

                            // Only store if no time conflict
                            if (!$timeConflict) {
                                $schedule[$weekday][$hourId] = [
                                    'subcode' => $row['subcode'] ?? $row['subject_code'],
                                    'subfullname' => $row['sub_fullname'],
                                    'subtype' => $row['sub_type'],
                                    'classname' => $row['classname'],
                                    'buildingname' => $row['building_name'],
                                    'classhallname' => $row['class_hall_name'],
                                    'hourdesc' => $row['hour_desc'],
                                    'starttime' => $row['start_time'],
                                    'endtime' => $row['end_time'],
                                    'classes' => [$row['classname']]
                                ];
                            }

                            // Collect unique time slot IDs
                            if (!in_array($hourId, $timeslotIds)) {
                                $timeslotIds[] = $hourId;
                            }

                            // Count unique weekday+time combinations (not hour ID)
                            $timeKey = $weekday . '_' . $row['start_time'] . '_' . $row['end_time'];
                            if (!in_array($timeKey, $uniqueSlots)) {
                                $uniqueSlots[] = $timeKey;
                            }
                        }

                        $totalHours = count($uniqueSlots);

                        $res['schedule'] = $schedule;
                        $res['totalhours'] = $totalHours;

                        // Get all time slots for the faculty's classes
                        if (!empty($timeslotIds)) {
                            sort($timeslotIds);
                            $placeholders = implode(',', array_fill(0, count($timeslotIds), '?'));

                            $stmt2 = $this->conn->prepare("
                            SELECT id, hour, hour_desc, start_time, end_time
                            FROM class_timings
                            WHERE id IN ($placeholders)
                            ORDER BY id
                        ");

                            if ($stmt2) {
                                $types = str_repeat('i', count($timeslotIds));
                                $stmt2->bind_param($types, ...$timeslotIds);

                                if ($stmt2->execute()) {
                                    $result2 = $stmt2->get_result();
                                    while ($row2 = $result2->fetch_assoc()) {
                                        $res['timeslots'][] = $row2;
                                    }
                                }
                                $stmt2->close();
                            }
                        }

                        $res['status'] = 1;
                        $res['data'] = $schedule;
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
     * Get all time slots from timing templates used by faculty
     * @param int $faculty_id Faculty ID
     * @return array All time slots from templates used
     */
    public function getAllTimeSlotsForFaculty($faculty_id)
    {
        $myname = $this->classname . " - getAllTimeSlotsForFaculty - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                // Get all timing_ids used by classes this faculty teaches
                $stmt = $this->conn->prepare("
                SELECT DISTINCT c.timing_id
                FROM class_timetables t
                INNER JOIN faculty_sub fs ON t.subject_id = fs.sub_id
                INNER JOIN classes c ON t.class_id = c.id
                WHERE fs.faculty_id = ? 
                AND c.end_date >= CURDATE()
                AND t.class_id IS NOT NULL
                AND c.timing_id IS NOT NULL
            ");

                if ($stmt) {
                    $stmt->bind_param('i', $faculty_id);
                    $timingIds = [];

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $timingIds[] = $row['timing_id'];
                        }
                    }
                    $stmt->close();

                    // Get all time slots for these timing templates
                    if (!empty($timingIds)) {
                        $placeholders = implode(',', array_fill(0, count($timingIds), '?'));
                        $stmt2 = $this->conn->prepare("
                        SELECT DISTINCT id, hour, hour_desc, start_time, end_time, timing_id
                        FROM class_timings
                        WHERE timing_id IN ($placeholders)
                        ORDER BY start_time, id
                    ");

                        if ($stmt2) {
                            $types = str_repeat('i', count($timingIds));
                            $stmt2->bind_param($types, ...$timingIds);

                            if ($stmt2->execute()) {
                                $result2 = $stmt2->get_result();
                                while ($row2 = $result2->fetch_assoc()) {
                                    $res['data'][] = $row2;
                                }
                                $res['status'] = 1;
                            }
                            $stmt2->close();
                        }
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
     * Get all timetable entries for a faculty (including overlapping ones)
     * @param int $faculty_id Faculty ID
     * @return array All entries from database
     */
    public function getAllFacultyTimetableEntries($faculty_id)
    {
        $myname = $this->classname . " - getAllFacultyTimetableEntries - ";
        $res = ['status' => 0, 'data' => []];

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT
                    t.weekday,
                    t.Hour,
                    t.subject_code,
                    s.subcode,
                    c.classname,
                    ct.start_time,
                    ct.end_time
                FROM class_timetables t
                INNER JOIN faculty_sub fs ON t.subject_id = fs.sub_id
                LEFT JOIN subjects s ON t.subject_id = s.id
                LEFT JOIN classes c ON t.class_id = c.id
                LEFT JOIN class_timings ct ON t.Hour = ct.id
                WHERE fs.faculty_id = ? AND c.end_date >= CURDATE()
                ORDER BY FIELD(t.weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.Hour
            ");

                if ($stmt) {
                    $stmt->bind_param('i', $faculty_id);

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $res['data'][] = [
                                'day' => $row['weekday'],
                                'hourId' => $row['Hour'],
                                'subcode' => $row['subcode'] ?? $row['subject_code'],
                                'classname' => $row['classname'],
                                'starttime' => $row['start_time'],
                                'endtime' => $row['end_time']
                            ];
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
     * Get total hours per week for a faculty
     * @param int $faculty_id Faculty ID
     * @return int Total hours
     */
    public function getFacultyTotalHours($faculty_id)
    {
        $myname = $this->classname . " - getFacultyTotalHours - ";
        $totalHours = 0;

        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM class_timetables t
                INNER JOIN faculty_sub fs ON t.subject_id = fs.sub_id
                WHERE fs.faculty_id = ?
            ");

                if ($stmt) {
                    $stmt->bind_param('i', $faculty_id);

                    if ($stmt->execute()) {
                        $stmt->bind_result($totalHours);
                        $stmt->fetch();
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

        return $totalHours;
    }
}

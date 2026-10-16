<?php
require_once("user.class.php");
require_once("logs.class.php");
require_once("subject.class.php");

class Faculty extends User
{
	private $classname = "Faculty";
	// Constructor to initialize the Faculty object
	public function __construct()
	{
		parent::__construct(); // Call the parent constructor
	}

	private function getEffectiveTimingIdByClassId($class_id, $date)
	{
		$myname = $this->classname . " - getEffectiveTimingIdByClassId - ";
		$timing_id = null;

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
				throw new Exception("Failed to prepare effective timing query: " . $this->conn->error);
			}

			$stmt->bind_param("iss", $class_id, $date, $date);
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
					throw new Exception("Failed to prepare fallback timing query: " . $this->conn->error);
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
			$this->logs->errLog($myname . "Error resolving timing for class ID $class_id on date $date: " . $e->getMessage());
		}

		return $timing_id;
	}

	public function updatePwd($userId, $oldpassword, $newpassword)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - updatePwd - ";
		$authenticate = $this->verifyPwd($userId, $oldpassword);
		if ($authenticate) {
			$res = $this->updatePassword($userId, $newpassword, "faculty");
		} else {
			$res["err"] = "Incorrect Existing Password";
		}
		return $res;
	}

	// Function to get Faculty ID by username
	public function getFacIDByUsername($facuser)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getFacIDByUsername - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id` FROM faculties WHERE username = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}

			$stmt->bind_param("s", $facuser);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt->bind_result($id);
			if ($stmt->fetch()) {
				$res['status'] = 1; // Faculty found
				$res['id'] = $id;   // Set ID
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching Faculty ID for user $facuser: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get subjects by faculty ID
	public function getSubjectsByFacultyId($faculty_id)
	{
		$subjectObj = new Subject();
		$data = $subjectObj->getSubjectsByFaculty($faculty_id);
		return ['status' => !empty($data) ? 1 : 0, 'data' => $data];
	}

	// Function to get unmarked hours for a subject on a specific date
	public function getUnmarkedHours($sub_id, $date)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getUnmarkedHours - ";

		try {
			$class_id = null;
			$stmt = $this->conn->prepare("SELECT class_id FROM subjects WHERE id = ? LIMIT 1");
			if (!$stmt) {
				throw new Exception("Failed to prepare subject class query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $sub_id);
			if ($stmt->execute()) {
				$stmt->bind_result($resolved_class_id);
				if ($stmt->fetch()) {
					$class_id = (int)$resolved_class_id;
				}
			}
			$stmt->close();

			if (empty($class_id)) {
				throw new Exception("Class not found for subject ID: " . $sub_id);
			}

			$timing_id = $this->getEffectiveTimingIdByClassId($class_id, $date);
			if (empty($timing_id)) {
				throw new Exception("Timing not found for class ID: " . $class_id);
			}

			$stmt = $this->conn->prepare("
				SELECT ct.id, ct.hour, ct.hour_desc, ct.start_time, ct.end_time
				FROM class_timings ct
				LEFT JOIN attendance a ON ct.id = a.hour AND a.sub_id = ? AND a.date = ?
				WHERE ct.timing_id = ? AND a.hour IS NULL
				ORDER BY ct.id ASC
			");
			if (!$stmt) {
				throw new Exception("Failed to prepare unmarked hours query: " . $this->conn->error);
			}

			$stmt->bind_param("isi", $sub_id, $date, $timing_id);
			if ($stmt->execute()) {
				$stmt->bind_result($hour_id, $hour, $hour_desc, $start_time, $end_time);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'hour_id' => $hour_id,
						'hour' => $hour,
						'hour_desc' => $hour_desc,
						'start_time' => $start_time,
						'end_time' => $end_time
					];
				}
				$res['status'] = 1; // Successfully retrieved unmarked hours
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error retrieving unmarked hours for subject $sub_id on date $date: " . $e->getMessage());
		}

		return $res;
	}

	// Function to mark attendance
	public function markAttendance($hours, $studentIds, $sub_id, $date, $diary, $faculty_id)
	{
        $this->assertSubjectScope((int)$sub_id);
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||!is_array($hours)||!$hours||!is_array($studentIds)){return ['status'=>0,'err'=>'Enter a valid date, periods and student list.'];}
        $hours=array_values(array_unique(array_map('intval',$hours)));
        $studentIds=array_values(array_unique(array_map('intval',$studentIds)));
        foreach($studentIds as $studentId){$this->assertStudentScope($studentId,(int)$sub_id);}
        if(($_SESSION['role']??'')==='faculty'){$faculty_id=(int)($_SESSION['facid']??0);}
        $check=$this->conn->prepare('SELECT sub_id FROM faculty_sub WHERE sub_id=? AND faculty_id=?');$check->bind_param('ii',$sub_id,$faculty_id);$check->execute();
        if(!$check->get_result()->fetch_row()){return ['status'=>0,'err'=>'The faculty member is not assigned to this subject.'];}
        foreach($hours as $hour){$check=$this->conn->prepare('SELECT ct.id FROM class_timings ct JOIN subjects s ON s.id=? JOIN classes c ON c.id=s.class_id WHERE ct.id=? AND ct.timing_id=COALESCE((SELECT timing_id FROM class_timing_schedule WHERE class_id=c.id AND from_date<=? AND (to_date IS NULL OR to_date>=?) ORDER BY from_date DESC,id DESC LIMIT 1),c.timing_id)');$check->bind_param('iiss',$sub_id,$hour,$date,$date);$check->execute();if(!$check->get_result()->fetch_row()){return ['status'=>0,'err'=>'A selected period does not belong to this class timing schedule.'];}}

		$res = ['status' => 0];
		$myname = $this->classname . " - markAttendance - ";

		try {
			$this->conn->begin_transaction(); // Start transaction for data consistency
            $lock=$this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');$lock->bind_param('i',$sub_id);$lock->execute();$lock->store_result();

			// Add diary entry
			$stmt = $this->conn->prepare("INSERT INTO `diary` (`sub_id`, `faculty_id`, `date`, `hour`, `diary`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare diary insert statement: " . $this->conn->error);
			}

			foreach ($hours as $hour) {
				$stmt->bind_param("iisss", $sub_id, $faculty_id, $date, $hour, $diary);
				if (!$stmt->execute()) {
					$this->conn->rollback();
					$this->logs->errLog($myname . "Diary insert failed: " . $this->conn->error);
					return $res; // Return on first error
				}
			}
			$stmt->close();
			// Fetch all mapped students for the subject
			$allMappedStudents = $this->getAllMappedStudents($sub_id);

			// Add attendance records
			$stmt = $this->conn->prepare("INSERT INTO `attendance` (`stu_id`, `sub_id`, `date`, `hour`, `status`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare attendance insert statement: " . $this->conn->error);
			}

			foreach ($hours as $hour) {
				foreach ($allMappedStudents as $stu_id) { // Iterate over all mapped students
					$status = in_array($stu_id, $studentIds) ? 'P' : 'A'; // Determine status based on checkbox
					$stmt->bind_param("iisss", $stu_id, $sub_id, $date, $hour, $status);
					if (!$stmt->execute()) {
						$this->conn->rollback();
						$this->logs->errLog($myname . "Attendance insert failed: " . $this->conn->error);
						return $res; // Return on first error
					} else {
						$res["status"] = 0; // Set success only after every row and audit entry commit						
					}
				}
			}
			$stmt->close();
			$hours1 = implode(",", $hours);
			$sub_res = $this->getSubjectDetails($sub_id);
			if (!empty($sub_res["data"]["subcode"])) {
				$sub_code = $sub_res["data"]["subcode"];
			} else {
				$sub_code = $sub_id;
			}
			if (!$this->dbActivityLog($_SESSION['userid']??0, "Attendance", "Marked for Subj: " . $sub_code . " Dt." . $date . " Hr." . $hours1, $_SESSION['role']??'system', "ATTENDANCE", (string)$sub_id)) {throw new RuntimeException('Unable to save attendance audit trail.');}
			$this->conn->commit(); // Commit transaction
            $res['status']=1;
		} catch (Throwable $e) {
			$this->conn->rollback(); // Rollback on exception
            $res=['status'=>0,'err'=>'Attendance was not saved. Check for an existing record or contact the HOD.'];
			$this->logs->errLog($myname . "Error marking attendance: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get mapped students by subject ID
	public function getMappedStudents($sub_id)
	{
		$students = [];
		$myname = $this->classname . " - getMappedStudents - ";

		try {
			$stmt = $this->conn->prepare("SELECT s.id, u.name, s.username, s.date_of_joining FROM student_sub ss JOIN students s ON ss.stu_id = s.id JOIN users u ON s.username = u.username WHERE ss.sub_id = ? AND s.status = 1 ORDER BY s.username");
			if (!$stmt) {
				throw new Exception("Failed to prepare mapped students query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $sub_id);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $name, $username, $date_of_joining);
				while ($stmt->fetch()) {
					$students[] = [
						'id' => $id,
						'name' => $name,
						'username' => $username,
						'date_of_joining' => $date_of_joining
					];
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching mapped students for subject ID $sub_id: " . $e->getMessage());
		}

		return $students;
	}

	public function getStudentsWithAttendanceByHour($sub_id, $date, $hour)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStudentsWithAttendanceByHour - ";

		try {
			$stmt = $this->conn->prepare("SELECT s.id, u.name, s.username, s.date_of_joining, a.status FROM student_sub ss JOIN students s ON ss.stu_id = s.id JOIN users u ON s.username = u.username LEFT JOIN attendance a ON a.stu_id = s.id AND a.sub_id = ss.sub_id AND a.date = ? AND a.hour = ? WHERE ss.sub_id = ? AND s.status = 1 ORDER BY s.username");
			if (!$stmt) {
				throw new Exception("Failed to prepare students attendance query: " . $this->conn->error);
			}

			$stmt->bind_param("sii", $date, $hour, $sub_id);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $name, $username, $date_of_joining, $status);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'name' => $name,
						'username' => $username,
						'date_of_joining' => $date_of_joining,
						'status' => $status
					];
				}
				$res['status'] = 1;
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching students with attendance for subject ID $sub_id on date $date and hour $hour: " . $e->getMessage());
		}

		return $res;
	}

	public function getStudentAttendanceByHour($stu_id, $sub_id, $date, $hour)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getStudentAttendanceByHour - ";

		try {
			$stmt = $this->conn->prepare("SELECT a.status, u.name, s.username FROM attendance a JOIN students s ON a.stu_id = s.id JOIN users u ON s.username = u.username WHERE a.stu_id = ? AND a.sub_id = ? AND a.date = ? AND a.hour = ? LIMIT 1");
			if (!$stmt) {
				throw new Exception("Failed to prepare student attendance query: " . $this->conn->error);
			}

			$stmt->bind_param("iisi", $stu_id, $sub_id, $date, $hour);
			if ($stmt->execute()) {
				$stmt->bind_result($status, $name, $username);
				if ($stmt->fetch()) {
					$res['status'] = 1;
					$res['data'] = [
						'status' => $status,
						'name' => $name,
						'username' => $username
					];
				} else {
					$res['err'] = "Attendance record not found for the selected student.";
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching student attendance for student ID $stu_id, subject ID $sub_id on date $date and hour $hour: " . $e->getMessage());
		}

		return $res;
	}

	public function updateStudentAttendanceByHour($stu_id, $sub_id, $date, $hour, $new_status, $faculty_id, ?string $reason = null)
	{
		require_once __DIR__ . '/core/AttendanceCorrection.php';
		try {
			return (new \College\AttendanceCorrection($this->conn, $_SESSION))->updateStudent((int)$stu_id, (int)$sub_id, (string)$date, (int)$hour, (string)$new_status, $reason ?? '');
		} catch (\Throwable $e) {
			$this->logs->errLog('Attendance correction failed: ' . $e->getMessage());
			return ['status' => 0, 'err' => $e->getMessage()];
		}
	}

	// Function to get all mapped students by subject ID
	public function getAllMappedStudents($sub_id)
	{
		$students = [];
		$myname = $this->classname . " - getAllMappedStudents - ";

		try {
			$stmt = $this->conn->prepare("SELECT stu_id FROM student_sub RIGHT JOIN students s ON student_sub.stu_id = s.id WHERE sub_id = ? AND s.status = 1 ORDER BY s.username");
			if (!$stmt) {
				throw new Exception("Failed to prepare all mapped students query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $sub_id);
			if ($stmt->execute()) {
				$stmt->bind_result($stu_id);
				while ($stmt->fetch()) {
					$students[] = $stu_id;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching all mapped students for subject ID $sub_id: " . $e->getMessage());
		}

		return $students;
	}


	// Function to get overall attendance by subject
	public function getOverallAttendanceBySubject($sub_id, $start_date = null, $end_date = null, $includeInactive = false)
	{
		$res = ['status' => 0, 'data' => []];
		try {
            $this->assertSubjectScope((int)$sub_id);
            require_once __DIR__ . '/core/DateRange.php';
            $start_date = $start_date === '' ? null : $start_date;
            $end_date = $end_date === '' ? null : $end_date;
            \College\DateRange::validate($start_date, $end_date);
			$dateFilter = "";
			if ($start_date && $end_date) {
				$dateFilter = "AND a.date BETWEEN ? AND ?";
			}

			$statusClause = $includeInactive ? "" : "AND s.status = 1";
			if ($stmt = $this->conn->prepare("SELECT u.name, s.username, COUNT(CASE WHEN a.status = 'P' THEN 1 END) / COUNT(*) * 100 AS percentage, s.status as student_status FROM student_sub ss JOIN students s ON ss.stu_id = s.id JOIN users u ON s.username = u.username RIGHT JOIN attendance a ON ss.stu_id = a.stu_id AND a.sub_id = ? WHERE ss.sub_id = ? " . $statusClause . " AND (a.date >= COALESCE(s.date_of_joining, (SELECT j.doj FROM temp_joiningdates j WHERE j.htno = s.username), '1900-01-01')) " . $dateFilter . " GROUP BY s.id order by s.username")) {
				if ($start_date !== null) { $stmt->bind_param("iiss", $sub_id, $sub_id, $start_date, $end_date); }
                else { $stmt->bind_param("ii", $sub_id, $sub_id); }
				if ($stmt->execute()) {
					$stmt->bind_result($name, $username, $percentage, $studentStatus);
					while ($stmt->fetch()) {
						$res['data'][] = [
							'name' => $name,
							'username' => $username,
							'student_status' => (int)$studentStatus,
							'percentage' => round($percentage, 2) // Round percentage to 2 decimal places
						];
					}
					$res['status'] = 1;
				} else {
					$this->logs->errLog("Statement not executed: " . $this->conn->error);
				}
			} else {
				$this->logs->errLog("Not prepared: " . $this->conn->error);
			}
		} catch (Exception $e) {
            $res['err'] = $e->getMessage();
			$this->logs->errLog("Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get detailed attendance by subject
	public function getDetailedAttendanceBySubject($sub_id, $start_date = null, $end_date = null, $includeInactive = false)
	{
		$res = ['status' => 0, 'data' => []];
		try {
            $this->assertSubjectScope((int)$sub_id);
            require_once __DIR__ . '/core/DateRange.php';
            $start_date = $start_date === '' ? null : $start_date;
            $end_date = $end_date === '' ? null : $end_date;
            \College\DateRange::validate($start_date, $end_date);
			// Fetch distinct date-hour combinations for the subject
			$dateHours = [];
			$dateFilter = "";
			if ($start_date && $end_date) {
				$dateFilter = "AND a.date BETWEEN ? AND ?";
			}
			$hours = array();
			if ($stmt = $this->conn->prepare("SELECT DISTINCT date, ct.id, ct.hour FROM attendance a LEFT JOIN class_timings ct ON a.hour=ct.id WHERE sub_id = ? " . $dateFilter . " ORDER BY date, ct.id")) {
				if ($start_date !== null) { $stmt->bind_param("iss", $sub_id, $start_date, $end_date); }
                else { $stmt->bind_param("i", $sub_id); }

				if ($stmt->execute()) {
					$stmt->bind_result($date, $hour_id, $hour);
					while ($stmt->fetch()) {
						$dateHours[] = $date . '-' . $hour_id . '-' . $hour;
						$hours[$date . '-' . $hour_id] = $hour;
					}
				} else {
					$this->logs->errLog("Failed to fetch date-hour combinations: " . $this->conn->error);
					return $res; // Exit early if date-hour combinations cannot be fetched
				}
			}

			// Fetch attendance data for each student and date-hour combination
			$statusClause = $includeInactive ? "" : "AND s.status = 1";
			if ($stmt = $this->conn->prepare("SELECT u.name, s.username, a.date, a.hour, a.status, s.status as student_status FROM student_sub ss JOIN students s ON ss.stu_id = s.id JOIN users u ON s.username = u.username RIGHT JOIN attendance a ON ss.stu_id = a.stu_id AND a.sub_id = ? WHERE ss.sub_id = ? " . $statusClause . " AND (a.date >= COALESCE(s.date_of_joining, (SELECT j.doj FROM temp_joiningdates j WHERE j.htno = s.username), '1900-01-01')) " . $dateFilter . " order by s.username")) {
				if ($start_date !== null) { $stmt->bind_param("iiss", $sub_id, $sub_id, $start_date, $end_date); }
                else { $stmt->bind_param("ii", $sub_id, $sub_id); }
				if ($stmt->execute()) {
					$stmt->bind_result($name, $username, $date, $hour, $status, $studentStatus);

					// Organize attendance data by student
					$students = [];
					while ($stmt->fetch()) {
						$dateHourKey = $date . '-' . $hour;
						if (!empty($hours[$dateHourKey])) {
							$dateHourKey = $dateHourKey . '-' . $hours[$dateHourKey];
						} else {
							$dateHourKey = $dateHourKey . '-';
						}
						if (!isset($students[$username])) {
							$students[$username] = [
								'name' => $name,
								'username' => $username,
								'student_status' => (int)$studentStatus,
								'attendance' => array_fill_keys($dateHours, '-'), // Initialize with '-'
								'total_present' => 0,
								'total_classes' => 0
							];
						}
						$students[$username]['attendance'][$dateHourKey] = $status;
						$students[$username]['total_classes']++;
						if ($status == 'P') {
							$students[$username]['total_present']++;
						}
					}

					// Calculate overall percentage for each student
					foreach ($students as &$student) {
						$student['percentage'] = $student['total_classes'] > 0 ? round(($student['total_present'] / $student['total_classes']) * 100, 2) : 0;
					}

					$res['data'] = array_values($students);
					$res['status'] = 1;
				} else {
					$this->logs->errLog("Statement not executed: " . $this->conn->error);
				}
			} else {
				$this->logs->errLog("Not prepared: " . $this->conn->error);
			}
		} catch (Exception $e) {
            $res['err'] = $e->getMessage();
			$this->logs->errLog("Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get diary entries by subject with optional date range
	public function getDiaryEntriesBySubject($sub_id, $start_date = null, $end_date = null)
	{
		$diaryEntries = [];
		$myname = $this->classname . " - getDiaryEntriesBySubject - ";

		try {
			$dateFilter = "";
			if ($start_date && $end_date) {
				$dateFilter = "AND d.date BETWEEN ? AND ?";
			}

			$query = "SELECT d.date, ct.hour_desc, ct.start_time, ct.end_time, d.diary 
			          FROM diary d 
			          LEFT JOIN class_timings ct ON d.hour=ct.id 
			          WHERE d.sub_id = ? " . $dateFilter . " ORDER BY d.date, ct.id";
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

	// Function to update subject details
	public function updateSubjectDetails($sub_id, $subcode, $sub_fullname, $sub_shortname)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - updateSubjectDetails - ";

		try {
			$stmt = $this->conn->prepare("UPDATE `subjects` SET `subcode` = ?, `sub_fullname` = ?, `sub_shortname` = ? WHERE `id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare update subject details statement: " . $this->conn->error);
			}

			$stmt->bind_param("sssi", $subcode, $sub_fullname, $sub_shortname, $sub_id);
			if ($stmt->execute()) {
				if ($stmt->affected_rows > 0) {
					$res['status'] = 1; // Subject details updated successfully
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get subject details by ID
	public function getSubjectById($subId)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getSubjectById - ";

		try {
			$stmt = $this->conn->prepare("SELECT * FROM subjects WHERE id = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get subject by ID query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $subId);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				if ($result->num_rows > 0) {
					$res['status'] = 1;
					$res['data'] = $result->fetch_assoc(); // Fetch the subject details
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}


	// Function to get class, subject, and faculty details
	public function getClassSubjectFacultyDetails($sub_id, $faculty_id)
	{
		$res = ['status' => 0, 'data' => null]; // Initialize data as null
		$myname = $this->classname . " - getClassSubjectFacultyDetails - ";

		try {
			$stmt = $this->conn->prepare("
                SELECT u.name AS faculty_name, sub.sub_fullname, c.classname, c.acad_year
                FROM faculty_sub fs
                JOIN faculties f ON fs.faculty_id = f.id
                JOIN users u ON f.username = u.username
                JOIN subjects sub ON fs.sub_id = sub.id
                JOIN classes c ON sub.class_id = c.id
                WHERE fs.sub_id = ? AND fs.faculty_id = ?
            ");
			if (!$stmt) {
				throw new Exception("Failed to prepare class, subject, and faculty details query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $sub_id, $faculty_id);
			if ($stmt->execute()) {
				$stmt->bind_result($faculty_name, $sub_fullname, $classname, $acad_year);
				if ($stmt->fetch()) { // Fetch the first (and only expected) row
					$res['data'] = [
						'faculty_name' => $faculty_name,
						'sub_fullname' => $sub_fullname,
						'classname' => $classname,
						'acad_year' => $acad_year
					];
					$res['status'] = 1;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to add a diary entry without attendance
	public function addDairy($hours, $sub_id, $date, $diary, $faculty_id)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addDairy - ";

		try {
			$this->conn->begin_transaction(); // Start transaction for data consistency

			// Add diary entry
			$stmt = $this->conn->prepare("INSERT INTO `diary` (`sub_id`, `faculty_id`, `date`, `hour`, `diary`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare diary insert statement: " . $this->conn->error);
			}

			foreach ($hours as $hour) {
				$stmt->bind_param("iisss", $sub_id, $faculty_id, $date, $hour, $diary);
				if (!$stmt->execute()) {
					$this->conn->rollback();
					$this->logs->errLog($myname . "Diary insert failed: " . $this->conn->error);
					return $res; // Return on first error
				}
			}
			$stmt->close();
			$hours1 = implode(",", $hours);
			$sub_res = $this->getSubjectDetails($sub_id);
			$sub_code = !empty($sub_res["data"]["subcode"]) ? $sub_res["data"]["subcode"] : $sub_id;
			$this->dbActivityLog($faculty_id, "Diary", "Added Diary for Subj: " . $sub_code . " Dt." . $date . " Hr." . $hours1, "faculty", "DIARY", (string)$sub_id);
			$this->conn->commit(); // Commit transaction
			$res['status'] = 1;
		} catch (Exception $e) {
			$this->conn->rollback(); // Rollback on exception
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}


	// Function to get internal assessment marks for a specific student and subject
	public function getStInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $subjective_marks, $objective_marks, $assignment_marks);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'subjective_marks' => $subjective_marks,
						'objective_marks' => $objective_marks,
						'assignment_marks' => $assignment_marks
					];
				}
				$res['status'] = 1; // Marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}


	// Function to get internal assessment marks for a specific student and subject
	public function getStPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStPGInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `marks` FROM `pg_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $marks);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'marks' => $marks
					];
				}
				$res['status'] = 1; // Marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}

	// Function to get internal assessment marks for a specific student and subject
	public function getStUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStUGLabInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `day_to_day_marks`, `internal_test_marks` FROM `uglab_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $day_to_day_marks, $internal_test_marks);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'day_to_day_marks' => $day_to_day_marks,
						'internal_test_marks' => $internal_test_marks
					];
				}
				$res['status'] = 1; // Marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}



	// Function to get UG Project internal assessment marks for a specific student and subject
	public function getStUGProjectInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStUGProjectInternalAssessmentMarks - ";
		try {
			$stmt = $this->conn->prepare("SELECT `id`, `component1_marks`, `component2_marks` FROM `ugproject_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $component1_marks, $component2_marks);
				while ($stmt->fetch()) {
					$res['data'][] = ['id' => $id, 'component1_marks' => $component1_marks, 'component2_marks' => $component2_marks];
				}
				$res['status'] = 1;
			} else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res['data'];
	}

	// Function to get subject details by ID
	public function getSubjectDetails($subjectId)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getSubjectDetails - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `sub_fullname`, `subcode` FROM `subjects` WHERE `id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get subject details query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $subjectId);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $sub_fullname, $subcode);
				if ($stmt->fetch()) {
					$res['data'] = [
						'id' => $id,
						'sub_fullname' => $sub_fullname,
						'subcode' => $subcode
					];
					$res['status'] = 1; // Subject details found
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get students by subject ID
	public function getStudentsBySubjectId($subjectId)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getStudentsBySubjectId - ";

		try {
			$stmt = $this->conn->prepare("SELECT s.`id`, s.`admno`, s.`rollno`, s.`name` FROM `students` s INNER JOIN `student_subjects` ss ON s.`id` = ss.`student_id` WHERE ss.`subject_id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get students by subject ID query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $subjectId);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $admno, $rollno, $name);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'admno' => $admno,
						'rollno' => $rollno,
						'name' => $name
					];
				}
				$res['status'] = 1; // Students retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}


	// Function to getProgramBySubID
	public function getPrgCodeBySubID($sub_id)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getPrgCodeBySubID - ";

		try {
			$stmt = $this->conn->prepare("SELECT p.program_code, s.sub_type, c.classname, c.start_date, c.end_date
					FROM subjects s
					INNER JOIN classes c ON s.class_id = c.id
					INNER JOIN specialization sp ON c.spec_id = sp.id
					INNER JOIN programs p ON sp.prog_id = p.id
					WHERE s.id = ?;");
			if (!$stmt) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $sub_id);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt->bind_result($prg_code, $sub_type, $classname, $start_date, $end_date);
			if ($stmt->fetch()) {
				$res['status'] = 1; // Program_code found
				$res['prg_code'] = $prg_code;   // Set prg_code
				$res['sub_type'] = strtolower($sub_type);
				$res['classname'] = $classname;
				$res['start_date'] = $start_date;
				$res['end_date'] = $end_date;
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching Program_code based on Subject ID $sub_id: " . $e->getMessage());
		}
		return $res;
	}

	// Function to getProgramBySubID
	public function getPrgCodeByClsID($cls_id)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getPrgCodeByClsID - ";

		try {
			$stmt = $this->conn->prepare("SELECT p.program_code
							FROM classes c
							INNER JOIN specialization sp ON c.spec_id = sp.id
							INNER JOIN programs p ON sp.prog_id = p.id
							WHERE c.id = ?;");
			if (!$stmt) {
				throw new Exception("Failed to prepare query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $cls_id);
			if (!$stmt->execute()) {
				throw new Exception("Failed to execute query: " . $this->conn->error);
			}

			$stmt->bind_result($prg_code);
			if ($stmt->fetch()) {
				$res['status'] = 1; // Program_code found
				$res['prg_code'] = $prg_code;   // Set prg_code
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching Program_code based on Class ID  $cls_id: " . $e->getMessage());
		}
		return $res;
	}

	public function submitAttendanceDeleteRequest($faculty_id, $sub_id, $date, $hour, $reason)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - submitAttendanceDeleteRequest - ";

		try {
			$stmt = $this->conn->prepare("
				INSERT INTO attendance_delete_requests (faculty_id, subject_id, date, hour, reason)
				VALUES (?, ?, ?, ?, ?)
			");
			$stmt->bind_param("iisis", $faculty_id, $sub_id, $date, $hour, $reason);
			if ($stmt->execute()) {
				$res['status'] = 1;
				$this->dbActivityLog($faculty_id, "REQUEST_ATTENDANCE_DELETE", "Requested attendance deletion for Subject ID: $sub_id, Date: $date, Hour: $hour, Reason: $reason", "faculty", "ATTENDANCE_REQUEST", (string)$sub_id);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error submitting request: " . $e->getMessage());
		}

		return $res;
	}

	public function getAvailableHours($sub_id, $date)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getAvailableHours - ";

		try {
			$stmt = $this->conn->prepare("SELECT id,hour_desc, start_time, end_time FROM `class_timings` WHERE id IN(SELECT hour FROM diary WHERE sub_id = ? AND date = ? and hour NOT IN (select hour from attendance_delete_requests where subject_id = ? and date = ? and status='Pending') order by Hour)");
			$stmt->bind_param("isis", $sub_id, $date, $sub_id, $date);
			if ($stmt->execute()) {
				$stmt->bind_result($hour, $hour_desc, $start_time, $end_time);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'hour' => $hour,
						'hour_desc' => $hour_desc,
						'start_time' => $start_time,
						'end_time' => $end_time
					];
				}
				$res['status'] = 1;
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error retrieving hours: " . $e->getMessage());
		}

		return $res;
	}

	public function getDiaryEntry($sub_id, $date, $hour)
	{
		$res = null;
		$myname = $this->classname . " - getDiaryEntry - ";

		try {
			$stmt = $this->conn->prepare("SELECT diary FROM diary WHERE sub_id = ? AND date = ? AND hour = ?");
			$stmt->bind_param("isi", $sub_id, $date, $hour);
			if ($stmt->execute()) {
				$stmt->bind_result($diary);
				if ($stmt->fetch()) {
					$res = $diary;
				}
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching diary entry: " . $e->getMessage());
		}

		return $res;
	}

	public function getAttendanceCounts($sub_id, $date, $hour)
	{
		$res = ['present' => 0, 'absent' => 0];
		$myname = $this->classname . " - getAttendanceCounts - ";

		try {
			$stmt = $this->conn->prepare("
            SELECT SUM(status = 'P') AS present, SUM(status = 'A') AS absent
            FROM attendance
            WHERE sub_id = ? AND date = ? AND hour = ?
        ");
			$stmt->bind_param("isi", $sub_id, $date, $hour);
			if ($stmt->execute()) {
				$stmt->bind_result($res['present'], $res['absent']);
				$stmt->fetch();
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching attendance counts: " . $e->getMessage());
		}

		return $res;
	}

	public function getHourDescById($hour_id)
	{
		$res = "";
		$myname = $this->classname . " - getHourDescById - ";

		try {
			$stmt = $this->conn->prepare("SELECT hour_desc FROM `class_timings` WHERE id=?");
			$stmt->bind_param("i", $hour_id);
			if ($stmt->execute()) {
				$stmt->bind_result($hour_desc);
				while ($stmt->fetch()) {
					$res = $hour_desc;
				}
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error retrieving hours: " . $e->getMessage());
		}

		return $res;
	}

	public function getSubmittedRequests($faculty_id)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getSubmittedRequests - ";

		try {
			$stmt = $this->conn->prepare("
            SELECT aur.id, s.sub_fullname AS subject, aur.date, aur.hour, aur.reason, aur.status, aur.request_date, aur.approval_date
            FROM attendance_delete_requests aur
            JOIN subjects s ON aur.subject_id = s.id
            WHERE aur.faculty_id = ?
            ORDER BY aur.request_date DESC
        ");
			$stmt->bind_param("i", $faculty_id);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $subject, $date, $hour, $reason, $status, $request_date, $approval_date);
				while ($stmt->fetch()) {
					$res['data'][] = [
						'id' => $id,
						'subject' => $subject,
						'date' => $date,
						'hour' => $hour,
						'reason' => $reason,
						'status' => $status,
						'request_date' => $request_date,
						'approval_date' => $approval_date
					];
				}
				$res['status'] = 1;
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching submitted requests: " . $e->getMessage());
		}

		return $res;
	}

	// Function to add CIA Attachment
	public function addCIAAttachment($subjectId, $assessmentNumber, $fileTitle, $filePath)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addCIAAttachment - ";

		try {
            $this->assertSubjectScope((int)$subjectId);
            $this->conn->begin_transaction();
            $lock = $this->conn->prepare('SELECT id FROM subjects WHERE id=? FOR UPDATE');
            $lock->bind_param('i', $subjectId); $lock->execute(); $lock->store_result();
            $count = $this->conn->prepare('SELECT COUNT(*) FROM cia_attachments WHERE subject_id=? AND assessment_number=?');
            $count->bind_param('ii', $subjectId, $assessmentNumber); $count->execute();
            if ((int)$count->get_result()->fetch_row()[0] >= 5) { throw new DomainException('This assessment already has five attachments.'); }

			$stmt = $this->conn->prepare("INSERT INTO `cia_attachments`(`subject_id`, `assessment_number`, `file_title`, `file_path`) VALUES (?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert cia_attachments statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiss", $subjectId, $assessmentNumber, $fileTitle, $filePath);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
				if (!$this->dbActivityLog($_SESSION['userid'] ?? 0, "UPLOAD_CIA_ATTACHMENT", "Uploaded CIA Attachment: $fileTitle for Subject ID: $subjectId, Mid: $assessmentNumber", "faculty", "CIA_ATTACHMENT", (string)$subjectId)) { throw new RuntimeException('Unable to save attachment audit.'); }
                $this->conn->commit();
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
            $this->conn->rollback(); $res['status'] = 0;
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get CIa Attchaments
	public function getCIAAttachments($subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getCIAAttachments - ";

		try {
            $this->assertSubjectScope((int)$subjectId);
			$stmt = $this->conn->prepare("SELECT `id`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $fileTitle, $filePath);
				$res["count"] = 0;
				while ($stmt->fetch()) {
					$res['status'] = 1;
					$res['files'][$res["count"]]['id'] = $id;
					$res['files'][$res["count"]]['file_title'] = $fileTitle;
					$res['files'][$res["count"]]['file_path'] = $filePath;
					$res["count"]++;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get CIa Attchaments
	public function getCIAAttachmentsBySubId($subjectId)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getCIAAttachmentsBySubId - ";

		try {
            $this->assertSubjectScope((int)$subjectId);
			$stmt = $this->conn->prepare("SELECT `id`, `assessment_number`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? ORDER BY `assessment_number`");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("i", $subjectId);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $assessmentNumber, $fileTitle, $filePath);
				$res["count"] = 0;
				while ($stmt->fetch()) {
					$res['status'] = 1;
					$res['files'][$res["count"]]['id'] = $id;
					$res['files'][$res["count"]]['assessment_number'] = $assessmentNumber;
					$res['files'][$res["count"]]['file_title'] = $fileTitle;
					$res['files'][$res["count"]]['file_path'] = $filePath;
					$res["count"]++;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to delete CIA Attachment
	public function deleteCIAAttachment($id, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deleteCIAAttachment - ";

		try {
            $this->assertSubjectScope((int)$subjectId);
			$stmt = $this->conn->prepare("DELETE FROM `cia_attachments` WHERE `id`=? and `subject_id`=? and `assessment_number`=?");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert cia_attachments statement: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $id, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
				$this->dbActivityLog($_SESSION['userid'] ?? 0, "DELETE_CIA_ATTACHMENT", "Deleted CIA Attachment ID: $id for Subject ID: $subjectId, Mid: $assessmentNumber", "faculty", "CIA_ATTACHMENT", (string)$id);
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get mapped students by subject ID
	public function getTempStHTNoByDoJ($doj)
	{
		$students = [];
		$myname = $this->classname . " - getTempStHTNoByDoJ - ";

		try {
			$stmt = $this->conn->prepare("SELECT `htno` FROM `temp_joiningdates` WHERE doj<=?");
			if (!$stmt) {
				throw new Exception("Failed to prepare mapped students query: " . $this->conn->error);
			}

			$stmt->bind_param("s", $doj);
			if ($stmt->execute()) {
				$stmt->bind_result($htno);
				while ($stmt->fetch()) {
					array_push($students, $htno);
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error fetching mapped students for subject ID $doj: " . $e->getMessage());
		}

		return $students;
	}

	// Function to add a Subject Questionnaire Question
	public function addSubjectQuestionnaireQuestion($subjectId, $questionNumber, $questionText, $createdByUserId, $createdByRole)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addSubjectQuestionnaireQuestion - ";

		try {
			// Check if question number already exists for this subject
			$checkStmt = $this->conn->prepare("SELECT COUNT(*) FROM subject_questionnaire_questions WHERE subject_id = ? AND question_number = ?");
			if (!$checkStmt) {
				throw new Exception("Failed to prepare question check query: " . $this->conn->error);
			}
			$checkStmt->bind_param("ii", $subjectId, $questionNumber);
			$checkStmt->execute();
			$checkStmt->bind_result($count);
			$checkStmt->fetch();
			$checkStmt->close();

			if ($count > 0) {
				$res['err'] = "Question Number " . htmlspecialchars($questionNumber) . " already exists for this subject.";
				return $res; // Exit if question number exists
			}


			$stmt = $this->conn->prepare("INSERT INTO subject_questionnaire_questions (subject_id, question_number, question_text, created_by_user_id, created_by_role) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare addSubjectQuestionnaireQuestion statement: " . $this->conn->error);
			}
			$stmt->bind_param("iisis", $subjectId, $questionNumber, $questionText, $createdByUserId, $createdByRole);
			if ($stmt->execute()) {
				$res['status'] = 1;
				// Log the activity
				$this->dbActivityLog($_SESSION['userid'] ?? 0, "ADD_QUESTIONNAIRE_QUESTION", "Added Question " . $questionNumber . " for Subject ID: " . $subjectId, "faculty", "QUESTIONNAIRE", (string)$subjectId);
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
				$res['err'] = "Failed to add questionnaire question.";
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
			$res['err'] = $e->getMessage();
		}
		return $res;
	}

	// Function to get Subject Questionnaire Questions by Subject ID
	public function getSubjectQuestionnaireQuestions($subjectId)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getSubjectQuestionnaireQuestions - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `question_number`, `question_text`, `created_by_user_id`, `created_by_role` FROM `subject_questionnaire_questions` WHERE `subject_id` = ? ORDER BY `question_number`");
			if (!$stmt) {
				throw new Exception("Failed to prepare getSubjectQuestionnaireQuestions statement: " . $this->conn->error);
			}

			$stmt->bind_param("i", $subjectId);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				$res['data'] = $result->fetch_all(MYSQLI_ASSOC);
				$res['status'] = 1;
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	public function getStudentsForExceptionalAttendance($sub_id, $date, $hour)
	{
		$res = [];
		$myname = $this->classname . " - getStudentsForExceptionalAttendance - ";

		try {
			$sql = "
            SELECT
                ss.stu_id AS id,
                s.username,
				u.name,
                s.date_of_joining
            FROM
                student_sub ss
            JOIN
                students s ON ss.stu_id = s.id
			JOIN users u ON s.username = u.username

            WHERE
                ss.sub_id = ?
				 AND s.status = 1
            AND
                NOT EXISTS (
                    SELECT 1
                    FROM attendance a
                    WHERE a.stu_id = s.id
                    AND a.sub_id = ?
                    AND a.date = ?
                    AND a.hour = ?
                )
            AND
                (s.date_of_joining <= ? OR s.date_of_joining IS NULL)
            ORDER BY
                s.username ASC
        ";
			$stmt = $this->conn->prepare($sql);
			if (!$stmt) {
				throw new Exception("Failed to prepare getStudentsForExceptionalAttendance query: " . $this->conn->error);
			}
			$stmt->bind_param("iisis", $sub_id, $sub_id, $date, $hour, $date);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				$data = [];
				while ($row = $result->fetch_assoc()) {
					$data[] = $row;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
				return [];
			}
			$stmt->close();
			
			$res = $data; // Set the filtered data to the result
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Error retrieving students for exceptional attendance: " . $e->getMessage());
		}
		return $res;
	}

		// Function to mark attendance
	public function markExceptionalAttendance($hours, $studentIds, $sub_id, $date, $faculty_id)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - markExceptionalAttendance - ";

		try {
			$this->conn->begin_transaction(); // Start transaction for data consistency

			// Fetch all mapped students for the subject
			$unmarkedStudents = $this->getStudentsForExceptionalAttendance($sub_id, $date, $hours[0]);

			$allMappedStudents = [];
			foreach ($unmarkedStudents as $student){
				array_push($allMappedStudents, $student['id']);
			}
			
			// Add attendance records
			$stmt = $this->conn->prepare("INSERT INTO `attendance` (`stu_id`, `sub_id`, `date`, `hour`, `status`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare attendance insert statement: " . $this->conn->error);
			}

			foreach ($hours as $hour) {
				foreach ($allMappedStudents as $stu_id) { // Iterate over all mapped students
					$status = in_array($stu_id, $studentIds) ? 'P' : 'A'; // Determine status based on checkbox
					$stmt->bind_param("iisss", $stu_id, $sub_id, $date, $hour, $status);
					if (!$stmt->execute()) {
						$this->conn->rollback();
						$this->logs->errLog($myname . "Attendance insert failed: " . $this->conn->error);
						return $res; // Return on first error
					} else {
						$res["status"] = 1; // Attendance marked successfully						
					}
				}
			}
			$stmt->close();
			$hours1 = implode(",", $hours);
			$sub_res = $this->getSubjectDetails($sub_id);
			if (!empty($sub_res["data"]["subcode"])) {
				$sub_code = $sub_res["data"]["subcode"];
			} else {
				$sub_code = $sub_id;
			}
			$this->dbActivityLog($faculty_id, "Exceptional Attendance", "Marked exceptional attendance for Subj: " . $sub_code . " Dt." . $date . " Hr." . $hours1, "faculty", "ATTENDANCE", (string)$sub_id);
			$this->conn->commit(); // Commit transaction
		} catch (Exception $e) {
			$this->conn->rollback(); // Rollback on exception
			$this->logs->errLog($myname . "Error marking attendance: " . $e->getMessage());
		}

		return $res;
	}

}

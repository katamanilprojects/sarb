<?php
require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {require_once __DIR__.'/services/FeatureManager.php'; \FeatureManager::requireAction('MOD_ATTENDANCE','export');}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_ATTENDANCE');

if (empty($_POST['sub_id']) || empty($_POST['secretcode']) || $_POST['secretcode'] != $_SESSION['secretcode']) {
    die("Invalid Request"); // Handle invalid requests
}

if(!empty($_SESSION['facid'])){
    $facid = $_SESSION['facid'];
}elseif(!empty($_POST['facid'])){
    $facid = $_POST['facid'];
}

$sub_id = $_POST['sub_id'];

require_once("faculty.class.php");
$attendanceObj = new Faculty();
$start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

$attendanceData = $attendanceObj->getDetailedAttendanceBySubject($sub_id, $start_date, $end_date);

if (!empty($attendanceData['data'])) {
    $dateHours = array_keys($attendanceData['data'][0]['attendance']);
    usort($dateHours, function($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=detailed_attendance.csv');
    $output = fopen('php://output', 'w');

    if(!empty($facid)){
        // Fetch and process Class, Subject, and Faculty details
        $details = $attendanceObj->getClassSubjectFacultyDetails($sub_id, $facid);
    }else{
        require_once("hod.class.php");
        $hodObj = new HOD();
        $details = $hodObj->getClsSubFacBySubID($sub_id);
    }
    
    if (!empty($details['data'])) {
        fputcsv($output, ['','Acad. Year:',$details['data']['acad_year']]);
        fputcsv($output, ['','Class:',$details['data']['classname']]);
        fputcsv($output, ['','Subject:',$details['data']['sub_fullname']]);
        fputcsv($output, ['','Faculty:',$details['data']['faculty_name']]);
        if(!empty($start_date) && !empty($end_date)){
            list($year1, $month1, $day1) = explode('-', $start_date);
            list($year2, $month2, $day2) = explode('-', $end_date);
            $daterange = $day1.'/'.$month1.'/'.$year1.' - '.$day2.'/'.$month2.'/'.$year2;
            fputcsv($output, ['','Date Range:',$daterange]);
        }
        fputcsv($output, []); // Empty row for separation
    }

    $formattedDateHours = array_map(function($dh) { 
        list($year, $month, $day, $hour_id, $hour) = explode('-', $dh); // Explode date-hour string
        return "$day/$month/$year ($hour)"; // Directly format
    }, $dateHours);

    // Output header rows (Corrected)
    fputcsv($output, array_merge(['S.No', 'Roll No', 'Student Name'], $formattedDateHours, ['Total Classes Held', 'Classes Present', 'Percentage']));

    // Output data rows
    $sno = 1;
    foreach ($attendanceData['data'] as $student) {
        $row = [$sno++, $student['username'], $student['name']];
        $totalPresent = 0;
        $totalClasses = 0;
        foreach ($dateHours as $dateHour) {
            $status = $student['attendance'][$dateHour];
            $row[] = $status;
            $totalClasses++;
            if ($status == 'P') {
                $totalPresent++;
            }
        }
        $totalPresent = $student['total_present'];
        $totalClasses = $student['total_classes'];

        $percentage = $totalClasses > 0 ? round(($totalPresent / $totalClasses) * 100, 2) : 0;
        $row[] = $totalClasses;
        $row[] = $totalPresent;
        $row[] = $percentage . '%';
        fputcsv($output, $row);
    }

    fclose($output);
} else {
    echo "No attendance data found for this subject.";
}

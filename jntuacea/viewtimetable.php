<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';


/**
 * Timetable Viewer - Mobile-First Refactored Version
 * Fixed: Distinct timings, correct statistics, department grouping
 */

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$pageTitle = "Live Timetable";

// Use your existing includes
if (!empty($_SESSION['role']) && $_SESSION['role'] === 'academic_section') {
    require_once('academicsectionheader.php');
} else{
    require_once 'header.php';
}

require_once 'hod.class.php';
require_once 'admin.class.php';

$hodObj = new HOD();
$adminObj = new Admin();

// Get database connection using your existing pattern
require_once 'dbcredentials.class.php';
$db = DBCredentials::getInstance();
$conn = $db->getConnection();

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && $token && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function for safe output
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Set Timezone
date_default_timezone_set('Asia/Kolkata');

// Handle CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>');
    }
}

// Get Current Time Info
$current_time = date('H:i:s');
$current_day = date('l');
$current_date = date('l, F d, Y');
$current_time_display = date('h:i A');
$active_acad_year = '2026-2027';

// Get Form Inputs (Sanitized)
$selected_dept_id = filter_input(INPUT_POST, 'dept_id', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'all';
$selected_time_slot = filter_input(INPUT_POST, 'time_slot', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'current';
$selected_day = filter_input(INPUT_POST, 'day', FILTER_SANITIZE_SPECIAL_CHARS) ?? $current_day;
$selected_acad_year = filter_input(INPUT_POST, 'acad_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? $active_acad_year;
$view_mode = filter_input(INPUT_POST, 'view_mode', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'cards';

// Get Departments
$departments = $adminObj->getAllDepartments();

// Function to get current time slot based on DISTINCT timings from active classes
function getCurrentTimeSlot($conn, $current_time, $acad_year): ?array {
    $query = "SELECT DISTINCT ct.start_time, ct.end_time, ct.hour_desc 
              FROM class_timings ct
              INNER JOIN class_timetables t ON ct.id = t.Hour
              INNER JOIN classes c ON t.class_id = c.id
              WHERE ? BETWEEN ct.start_time AND ct.end_time
                AND c.acad_year = ?
                AND c.status = 1
                AND t.subject_id IS NOT NULL
              LIMIT 1";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ss", $current_time, $acad_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        $stmt->close();
    }
    return null;
}

// Get all DISTINCT time slots that are actually being used by active classes
function getAllActiveTimeSlots($conn, $acad_year): array {
    $query = "SELECT DISTINCT ct.start_time, ct.end_time, 
              MIN(ct.hour_desc) as hour_desc
              FROM class_timings ct
              INNER JOIN class_timetables t ON ct.id = t.Hour
              INNER JOIN classes c ON t.class_id = c.id
              WHERE c.acad_year = ?
                AND c.status = 1
                AND t.subject_id IS NOT NULL
              GROUP BY ct.start_time, ct.end_time
              ORDER BY ct.start_time";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $acad_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $slots = [];
        while ($row = $result->fetch_assoc()) {
            $slots[] = $row;
        }
        
        $stmt->close();
        return $slots;
    }
    
    return [];
}

// Get ongoing classes by time slot - considering ALL IDs with same start/end time
function getOngoingClassesByTime($conn, $time_slot, $weekday, $acad_year, $dept_id = 'all'): array {
    if ($time_slot === 'current' || empty($time_slot)) {
        return [];
    }
    
    // Parse time slot
    list($start_time, $end_time) = explode('|', $time_slot);
    
    $dept_filter = '';
    $types = "ssss";
    $params = [$start_time, $end_time, $weekday, $acad_year];
    
    if ($dept_id !== 'all') {
        $dept_filter = "AND d.id = ?";
        $types .= "i";
        $params[] = (int)$dept_id;
    }
    
    // Query considers ALL class_timings IDs that have the same start_time and end_time
    $query = "SELECT 
                c.id as class_id, c.classname, c.yearsem,
                s.id as subject_id, s.subcode, s.sub_shortname, 
                s.sub_fullname, s.sub_type,
                t.building_name, t.class_hall_name, t.subject_code,
                ct.hour_desc, ct.start_time, ct.end_time,
                d.id as dept_id, d.dept_shortname, d.dept_fullname,
                sp.spec_shortname, sp.spec_fullname,
                u.name as faculty_name, f.designation, f.username as faculty_username
              FROM class_timetables t
              INNER JOIN classes c ON t.class_id = c.id
              LEFT JOIN subjects s ON t.subject_id = s.id
              INNER JOIN class_timings ct ON t.Hour = ct.id AND ct.timing_id = c.timing_id
              INNER JOIN specialization sp ON c.spec_id = sp.id
              INNER JOIN departments d ON sp.dept_id = d.id
              LEFT JOIN faculty_sub fs ON s.id = fs.sub_id
              LEFT JOIN faculties f ON fs.faculty_id = f.id
              LEFT JOIN users u ON f.username = u.username
              WHERE ct.start_time = ? 
                AND ct.end_time = ? 
                AND t.weekday = ? 
                AND c.acad_year = ?
                AND c.status = 1 
                AND t.subject_code IS NOT NULL AND t.subject_code != ''
                $dept_filter
              ORDER BY d.id, c.classname, s.sub_fullname";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        
        $stmt->close();
        return $classes;
    }
    
    return [];
}

// Get data
$current_time_slot = getCurrentTimeSlot($conn, $current_time, $active_acad_year);
$all_time_slots = getAllActiveTimeSlots($conn, $active_acad_year);

// Determine display time slot
$display_time_slot = ($selected_time_slot === 'current' && $current_time_slot) 
    ? $current_time_slot['start_time'] . '|' . $current_time_slot['end_time'] 
    : $selected_time_slot;

$display_time_desc = 'Select a time slot';
if ($current_time_slot && $selected_time_slot === 'current') {
    $display_time_desc = $current_time_slot['hour_desc'] . ' (' . 
                         date('h:i A', strtotime($current_time_slot['start_time'])) . ' - ' . 
                         date('h:i A', strtotime($current_time_slot['end_time'])) . ')';
} elseif ($selected_time_slot !== 'current' && strpos($selected_time_slot, '|') !== false) {
    list($st, $et) = explode('|', $selected_time_slot);
    foreach ($all_time_slots as $slot) {
        if ($slot['start_time'] === $st && $slot['end_time'] === $et) {
            $display_time_desc = $slot['hour_desc'] . ' (' . 
                               date('h:i A', strtotime($slot['start_time'])) . ' - ' . 
                               date('h:i A', strtotime($slot['end_time'])) . ')';
            break;
        }
    }
}

// Get classes
$ongoing_classes = getOngoingClassesByTime($conn, $display_time_slot, $selected_day, $selected_acad_year, $selected_dept_id);

// Group classes by subject
$classes_grouped = [];
foreach ($ongoing_classes as $class) {    
    $key = $class['dept_id'] . '_' . ($class['subject_id'] ?? 'activity_' . $class['subject_code']) . '_' . $class['start_time'] . '_' . $class['end_time'];

    if (!isset($classes_grouped[$key])) {
        $classes_grouped[$key] = [
            'subject_id' => $class['subject_id'],
            'subcode' => $class['subject_id'] ? $class['subcode'] : $class['subject_code'],
            'sub_shortname' => $class['sub_shortname'],
            'sub_fullname' => $class['subject_id'] ? $class['sub_fullname'] : $class['subject_code'],
            'sub_type' => $class['sub_type'] ?: 'Activity',
            'start_time' => $class['start_time'],
            'end_time' => $class['end_time'],
            'hour_desc' => $class['hour_desc'],
            'dept_id' => $class['dept_id'],
            'dept_shortname' => $class['dept_shortname'],
            'dept_fullname' => $class['dept_fullname'],
            'classes' => [],
            'faculties' => []
        ];
    }
    
    // Add class info
    $classes_grouped[$key]['classes'][] = [
        'class_id' => $class['class_id'],
        'classname' => $class['classname'],
        'yearsem' => $class['yearsem'],
        'building_name' => $class['building_name'] ?? '',
        'class_hall_name' => $class['class_hall_name'] ?? ''
    ];
    
    // Add faculty info
    if (!empty($class['faculty_username'])) {
        $faculty_key = $class['faculty_username'];
        if (!isset($classes_grouped[$key]['faculties'][$faculty_key])) {
            $classes_grouped[$key]['faculties'][$faculty_key] = [
                'faculty_name' => $class['faculty_name'],
                'designation' => $class['designation'] ?? ''
            ];
        }
    }
}

// Group by department
$classes_by_dept = [];
foreach ($classes_grouped as $group) {
    $dept_name = $group['dept_fullname'];
    if (!isset($classes_by_dept[$dept_name])) {
        $classes_by_dept[$dept_name] = [
            'dept_id' => $group['dept_id'],
            'dept_shortname' => $group['dept_shortname'],
            'groups' => []
        ];
    }
    $classes_by_dept[$dept_name]['groups'][] = $group;
}

// Calculate DISTINCT statistics properly
$unique_subjects = count($classes_grouped);

// Count distinct classes (not rows, as same class can have multiple subjects)
$distinct_classes = array_unique(array_map(function($c) {
    return $c['class_id'];
}, $ongoing_classes));
$total_classes = count($distinct_classes);

// Count distinct faculties
$distinct_faculties = array_unique(array_filter(array_column($ongoing_classes, 'faculty_username')));
$active_faculties = count($distinct_faculties);

// Count distinct rooms
$distinct_rooms = array_unique(array_map(function($c) {
    return $c['building_name'] . '-' . $c['class_hall_name'];
}, array_filter($ongoing_classes, function($c) {
    return !empty($c['building_name']) && !empty($c['class_hall_name']);
})));
$occupied_rooms = count($distinct_rooms);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="Real-time class schedule viewer">
    <meta name="theme-color" content="#3b82f6">
    
    <style>
        /* Mobile-First CSS with Modern Colors */
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --text: #1f2937;
            --text-light: #6b7280;
            --bg: #ffffff;
            --bg-alt: #f8fafc;
            --border: #e5e7eb;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--text);
        }
        
        .container-custom {
            width: 100%;
            padding: 1rem;
            margin: 0 auto;
        }
        
        /* Header with Gradient */
        .header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .header-custom h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .header-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.875rem;
            opacity: 0.95;
        }
        
        /* Stats Grid with Gradient Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:nth-child(1) {
            border-left-color: #3b82f6;
        }
        
        .stat-card:nth-child(2) {
            border-left-color: #10b981;
        }
        
        .stat-card:nth-child(3) {
            border-left-color: #f59e0b;
        }
        
        .stat-card:nth-child(4) {
            border-left-color: #8b5cf6;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        /* Filter Section with Gradient */
        .filter-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.95);
            min-height: 44px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: white;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }
        
        /* Alert with Modern Style */
        .alert-info {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);
        }
        
        /* Department Section Header */
        .dept-section {
            margin-bottom: 2rem;
        }
        
        .dept-header {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        /* Department-specific colors with gradients */
        .dept-header.dept-CSE {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }
        
        .dept-header.dept-ECE {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
        }
        
        .dept-header.dept-EEE {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }
        
        .dept-header.dept-CIVIL {
            background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%);
        }
        
        .dept-header.dept-MECH,
        .dept-header.dept-ME {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
        }
        
        .dept-header.dept-CHE {
            background: linear-gradient(135deg, #00BCD4 0%, #0097A7 100%);
        }
        
        .dept-header.dept-IT {
            background: linear-gradient(135deg, #673AB7 0%, #512DA8 100%);
        }
        
        /* Default for other departments */
        .dept-header:not([class*="dept-CSE"]):not([class*="dept-ECE"]):not([class*="dept-EEE"]):not([class*="dept-CIVIL"]):not([class*="dept-MECH"]):not([class*="dept-ME"]):not([class*="dept-CHE"]):not([class*="dept-IT"]) {
            background: linear-gradient(135deg, #607D8B 0%, #455A64 100%);
        }
        
        .dept-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        /* Class Cards with Department Colors */
        .classes-grid {
            display: grid;
            gap: 1rem;
        }
        
        .class-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        /* Card border colors by department */
        .class-card.dept-CSE {
            border-left-color: #2196F3;
        }
        
        .class-card.dept-ECE {
            border-left-color: #9C27B0;
        }
        
        .class-card.dept-EEE {
            border-left-color: #FF9800;
        }
        
        .class-card.dept-CIVIL {
            border-left-color: #4CAF50;
        }
        
        .class-card.dept-MECH,
        .class-card.dept-ME {
            border-left-color: #E91E63;
        }
        
        .class-card.dept-CHE {
            border-left-color: #00BCD4;
        }
        
        .class-card.dept-IT {
            border-left-color: #673AB7;
        }
        
        .subject-name {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text);
        }
        
        .subject-code {
            font-size: 0.875rem;
            color: var(--text-light);
            font-family: 'Courier New', monospace;
            background: var(--bg-alt);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .detail-row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.625rem;
            font-size: 0.875rem;
            align-items: flex-start;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-light);
            min-width: 70px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: var(--text);
            flex: 1;
        }
        
        /* Badge Styles with Modern Colors */
        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8125rem;
            margin: 0.125rem;
            font-weight: 500;
        }
        
        .badge-theory {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }
        
        .badge-lab {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        
        .badge-tutorial {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        
        .badge-practical {
            background: linear-gradient(135deg, #FCE7F3 0%, #FBCFE8 100%);
            color: #9F1239;
            border: 1px solid #F9A8D4;
        }
        
        .badge-skill {
            background: linear-gradient(135deg, #E0E7FF 0%, #C7D2FE 100%);
            color: #3730A3;
            border: 1px solid #A5B4FC;
        }
        
        .badge-dti {
            background: linear-gradient(135deg, #FED7AA 0%, #FDBA74 100%);
            color: #9A3412;
            border: 1px solid #FB923C;
        }
        
        .badge-oe {
            background: linear-gradient(135deg, #CCFBF1 0%, #99F6E4 100%);
            color: #134E4A;
            border: 1px solid #5EEAD4;
        }
        
        .badge-pe {
            background: linear-gradient(135deg, #F3E8FF 0%, #E9D5FF 100%);
            color: #581C87;
            border: 1px solid #D8B4FE;
        }
        
        .badge-mncc {
            background: linear-gradient(135deg, #FECACA 0%, #FCA5A5 100%);
            color: #7F1D1D;
            border: 1px solid #F87171;
        }
        
        .class-badge {
            background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
            color: #374151;
            border: 1px solid #D1D5DB;
        }
        
        /* Table with Modern Styling */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .table {
            width: 100%;
            min-width: 600px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table th {
            padding: 1rem 0.75rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            font-size: 0.8125rem;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            border-top: 1px solid var(--border);
            font-size: 0.875rem;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .empty-text {
            font-size: 1rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        /* Responsive Design */
        @media (min-width: 640px) {
            .container-custom {
                padding: 1.5rem;
            }
            
            .header-custom h1 {
                font-size: 2rem;
            }
            
            .header-info {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
            }
            
            .filter-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .classes-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .container-custom {
                max-width: 1280px;
                padding: 2rem;
            }
            
            .header-custom h1 {
                font-size: 2.25rem;
            }
            
            .filter-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .classes-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Print Styles */
        @media print {
            .filter-section {
                display: none;
            }
            
            .class-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-custom">
            <h1>📚 Real-Time Class Schedule</h1>
            <div class="header-info">
                <div>📅 <strong><?= e($current_date) ?></strong></div>
                <div>🕐 <strong><?= e($current_time_display) ?></strong></div>
                <div>📖 <strong><?= e($active_acad_year) ?></strong></div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $unique_subjects ?></div>
                <div class="stat-label">Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $total_classes ?></div>
                <div class="stat-label">Classes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $active_faculties ?></div>
                <div class="stat-label">Faculties</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $occupied_rooms ?></div>
                <div class="stat-label">Rooms</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
            <form method="post" action="viewtimetable.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="dept_id">🏛️ Department</label>
                        <select name="dept_id" id="dept_id" class="form-control" onchange="this.form.submit()">
                            <option value="all">All Departments</option>
                            <?php 
                            if (!empty($departments['data'])) {
                                foreach ($departments['data'] as $dept): 
                            ?>
                                <option value="<?= $dept['id'] ?>" <?= $selected_dept_id == $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['dept_shortname']) ?>
                                </option>
                            <?php 
                                endforeach;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="day">📅 Day</label>
                        <select name="day" id="day" class="form-control" onchange="this.form.submit()">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            foreach ($days as $day): 
                            ?>
                                <option value="<?= $day ?>" <?= $selected_day === $day ? 'selected' : '' ?>>
                                    <?= $day ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_slot">⏰ Time Slot</label>
                        <select name="time_slot" id="time_slot" class="form-control" onchange="this.form.submit()">
                            <option value="current" <?= $selected_time_slot === 'current' ? 'selected' : '' ?>>
                                Current Time Slot
                            </option>
                            <?php foreach ($all_time_slots as $slot): ?>
                                <?php $slot_value = $slot['start_time'] . '|' . $slot['end_time']; ?>
                                <option value="<?= e($slot_value) ?>" <?= $selected_time_slot === $slot_value ? 'selected' : '' ?>>
                                    <?= e($slot['hour_desc']) ?> (<?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="view_mode">👁️ View Mode</label>
                        <select name="view_mode" id="view_mode" class="form-control" onchange="this.form.submit()">
                            <option value="cards" <?= $view_mode === 'cards' ? 'selected' : '' ?>>Card View</option>
                            <option value="table" <?= $view_mode === 'table' ? 'selected' : '' ?>>Table View</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Current Slot -->
        <?php if ($current_time_slot && $selected_time_slot === 'current'): ?>
            <div class="alert-info">
                <strong>⏰ Current Slot:</strong> <?= e($display_time_desc) ?>
            </div>
        <?php endif; ?>
        
        <!-- Classes -->
        <?php if (empty($ongoing_classes)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p class="empty-text">No classes scheduled for the selected time slot</p>
            </div>
        <?php elseif ($view_mode === 'cards'): ?>
            <!-- CARD VIEW - DEPARTMENT-WISE GROUPING -->
            <?php foreach ($classes_by_dept as $dept_name => $dept_data): ?>
                <div class="dept-section">
                    <!-- Department Header -->
                    <div class="dept-header dept-<?= e($dept_data['dept_shortname']) ?>">
                        <span><?= e($dept_name) ?></span>
                        <span class="dept-badge"><?= count($dept_data['groups']) ?> Subject<?= count($dept_data['groups']) > 1 ? 's' : '' ?></span>
                    </div>
                    
                    <!-- Cards Grid for this Department -->
                    <div class="classes-grid">
                        <?php foreach ($dept_data['groups'] as $group): ?>
                            <div class="class-card dept-<?= e($dept_data['dept_shortname']) ?>">
                                <div class="subject-name"><?= e($group['sub_fullname']) ?></div>
                                <div class="subject-code"><?= e($group['subcode']) ?></div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Type:</span>
                                    <span class="detail-value">
                                        <span class="badge badge-<?= strtolower($group['sub_type']) ?>">
                                            <?= e($group['sub_type']) ?>
                                        </span>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Faculty:</span>
                                    <span class="detail-value">
                                        <?= !empty($group['faculties']) ? e(implode(', ', array_column($group['faculties'], 'faculty_name'))) : 'Not Assigned' ?>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Classes:</span>
                                    <span class="detail-value">
                                        <?php 
                                        // Remove duplicate classnames using array_unique
                                        $unique_classnames = array_unique(array_column($group['classes'], 'classname'));
                                        foreach ($unique_classnames as $classname): 
                                        ?>
                                            <span class="badge class-badge"><?= e($classname) ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="detail-label">Rooms:</span>
                                    <span class="detail-value">
                                        <?php 
                                        $rooms = array_map(function($c) {
                                            return !empty($c['building_name']) && !empty($c['class_hall_name']) 
                                                ? $c['class_hall_name'] 
                                                : 'TBA';
                                        }, $group['classes']);
                                        echo e(implode(', ', array_unique($rooms)));
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- TABLE VIEW -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Classes</th>
                            <th>Faculty</th>
                            <th>Rooms</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes_by_dept as $dept_name => $dept_data): ?>
                            <?php foreach ($dept_data['groups'] as $group): ?>
                                <tr>
                                    <td><strong><?= e($group['dept_shortname']) ?></strong></td>
                                    <td>
                                        <strong><?= e($group['sub_fullname']) ?></strong><br>
                                        <span class="badge badge-<?= strtolower($group['sub_type']) ?>">
                                            <?= e($group['sub_type']) ?>
                                        </span>
                                    </td>
                                    <td><code><?= e($group['subcode']) ?></code></td>
                                    <td>
                                        <?php 
                                        // Remove duplicate classnames
                                        $unique_classnames = array_unique(array_column($group['classes'], 'classname'));
                                        echo e(implode(', ', $unique_classnames));
                                        ?>
                                    </td>
                                    <td>
                                        <?= !empty($group['faculties']) ? e(implode(', ', array_column($group['faculties'], 'faculty_name'))) : '-' ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $rooms = array_filter(array_map(function($c) {
                                            return !empty($c['class_hall_name']) ? $c['class_hall_name'] : null;
                                        }, $group['classes']));
                                        echo !empty($rooms) ? e(implode(', ', array_unique($rooms))) : 'TBA';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-refresh every 5 minutes if showing current time
        <?php if ($selected_time_slot === 'current'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 300000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php require_once 'hodfooter.php'; ?>

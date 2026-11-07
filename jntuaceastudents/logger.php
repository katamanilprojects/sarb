<?php
require_once __DIR__ . '/bootstrap.php';

function logRequest(): void {
    static $logged = false;
    if ($logged) {
        return;
    }
    $logged = true;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Identify user/student roll number
    $user = $_SESSION['user'] ?? $_POST['username'] ?? (!empty($_POST['student_id']) ? 'ID:' . $_POST['student_id'] : 'GUEST');

    // Detect bot fingerprint from real log evidence
    $secFetch = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'MISSING';
    $isBot    = ($secFetch === 'MISSING') ? 'YES' : 'NO';

    // Detect fake Chrome version (scraper uses Chrome/120 on SM-S901B)
    $chromeVer = 0;
    if (preg_match('/Chrome\/(\d+)/', $ua, $m)) {
        $chromeVer = (int)$m[1];
    }
    $fakeUA = ($chromeVer > 0 && $chromeVer < 130) ? 'YES' : 'NO';
    if (strpos($ua, 'SM-S901B') !== false) {
        $fakeUA = 'YES';
    }

    $row = [
        date("Y-m-d H:i:s"),                               // time
        $ip,                                                // ip
        $user,                                              // user
        $_SERVER['REQUEST_METHOD'],                         // method
        $_SERVER['REQUEST_URI'],                            // page
        $secFetch,                                          // sec_fetch_site
        $_SERVER['HTTP_SEC_FETCH_MODE']  ?? 'MISSING',     // sec_fetch_mode
        $_SERVER['HTTP_ORIGIN']          ?? 'MISSING',     // origin
        $ua,                                                // user_agent
        $chromeVer,                                         // chrome_version
        $fakeUA,                                            // fake_ua
        $isBot,                                             // is_bot
        !empty($_POST) ? implode('|', array_keys($_POST)) : '', // post_keys
    ];

    $file   = __DIR__ . '/logs/request_log.log';
    $exists = file_exists($file);
    $handle = fopen($file, 'a');

    // Write header only once
    if (!$exists) {
        fputcsv($handle, [
            'time','ip','user','method','page',
            'sec_fetch_site','sec_fetch_mode','origin',
            'user_agent','chrome_version','fake_ua',
            'is_bot','post_keys'
        ]);
    }

    fputcsv($handle, $row);
    fclose($handle);

    // If scraper detected, write to a dedicated audit log
    if (isScraperBot()) {
        $botLogFile = __DIR__ . '/logs/bot_scrapers.log';
        $entry = date("Y-m-d H:i:s") . " | User: " . str_pad((string)$user, 14) . " | IP: " . str_pad((string)$ip, 39) . " | URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
        file_put_contents($botLogFile, $entry, FILE_APPEND);
    }
}

function isScraperBot(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) {
        return false;
    }
    // Laser-focused fingerprint: The scraper specifically hardcodes the Galaxy S22 EU model (SM-S901B) and Chrome 120
    // Real Indian retail Galaxy S22 units use SM-S901E, so SM-S901B is 100% unique to this scraping tool.
    if (strpos($ua, 'SM-S901B') !== false) {
        return true;
    }
    return false;
}

/**
 * Log specifically whenever a scraper is intercepted and served invalid dummy data
 */
function logInvalidResponseServed(string $sourcePage = 'studentsubjectatt.php'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $user = $_SESSION['user'] ?? $_POST['username'] ?? (!empty($_POST['student_id']) ? 'ID:' . $_POST['student_id'] : 'UNKNOWN');
    $subcode = $_POST['subcode'] ?? 'UNKNOWN';
    $subjectId = $_POST['subject_id'] ?? 'UNKNOWN';
    $studentId = $_POST['student_id'] ?? 'UNKNOWN';
    $timestamp = date("Y-m-d H:i:s");

    // Structured CSV log for easy export/reporting
    $csvFile = __DIR__ . '/logs/invalid_served.csv';
    $exists  = file_exists($csvFile);
    $fp      = @fopen($csvFile, 'a');
    if ($fp) {
        if (!$exists) {
            fputcsv($fp, ['timestamp', 'roll_number', 'student_id', 'subject_id', 'subcode', 'ip', 'page', 'user_agent']);
        }
        fputcsv($fp, [$timestamp, $user, $studentId, $subjectId, $subcode, $ip, $sourcePage, $ua]);
        fclose($fp);
    }
}
?>

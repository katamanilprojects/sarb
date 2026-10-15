<?php
require_once __DIR__ . '/bootstrap.php';

function logRequest(): void {
    $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Detect bot fingerprint from real log evidence
    $secFetch = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'MISSING';
    $isBot    = ($secFetch === 'MISSING') ? 'YES' : 'NO';

    // Detect fake Chrome version (scraper uses Chrome/120)
    $chromeVer = 0;
    if (preg_match('/Chrome\/(\d+)/', $ua, $m)) {
        $chromeVer = (int)$m[1];
    }
    $fakeUA = ($chromeVer > 0 && $chromeVer < 130) ? 'YES' : 'NO';

    $row = [
        date("Y-m-d H:i:s"),                               // time
        $ip,                                                // ip
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
            'time','ip','method','page',
            'sec_fetch_site','sec_fetch_mode','origin',
            'user_agent','chrome_version','fake_ua',
            'is_bot','post_keys'
        ]);
    }

    fputcsv($handle, $row);
    fclose($handle);
}
?>

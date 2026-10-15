<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

if (!defined('TURNSTILE_SECRET')) {
    $envPath = is_file(__DIR__ . '/.env.php') ? __DIR__ . '/.env.php' : dirname(__DIR__, 2) . '/.env.php';
    if (is_file($envPath)) {
        require_once $envPath;
    }
}

defined('TURNSTILE_SECRET') || define('TURNSTILE_SECRET', '');

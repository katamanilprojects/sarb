<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = dirname(__DIR__) . '/jntuacea/core';
$target = dirname(__DIR__) . '/jntuaceastudents/core';
if (!is_dir($target)) { mkdir($target, 0755, true); }
$manifest = [];
foreach (glob($source . '/*.php') as $file) {
    $name = basename($file);
    if (in_array('--check', $argv, true)) {
        if (!is_file($target . '/' . $name) || hash_file('sha256', $file) !== hash_file('sha256', $target . '/' . $name)) {
            fwrite(STDERR, "Core mismatch: $name\n"); exit(1);
        }
    } else { copy($file, $target . '/' . $name); }
    $manifest[$name] = hash_file('sha256', $file);
}
if (!in_array('--check', $argv, true)) {
    foreach ([$source, $target] as $dir) { file_put_contents($dir . '/release.json', json_encode(['version' => '2026.10.08.2', 'files' => $manifest], JSON_PRETTY_PRINT)); }
}
echo "Shared core verified (" . count($manifest) . " files).\n";

<?php
require_once __DIR__ . '/core/RequestSecurity.php';
\College\RequestSecurity::boot('staff', basename($_SERVER['SCRIPT_FILENAME'] ?? ''));

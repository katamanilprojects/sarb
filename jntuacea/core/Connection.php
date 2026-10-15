<?php
declare(strict_types=1);
namespace College;
require_once __DIR__ . '/Clock.php';

/** One connection per request, shared by services participating in a transaction. */
final class Connection
{
    private static ?\mysqli $connection = null;

    public static function get(): \mysqli
    {
        Clock::configure();
        if (self::$connection === null) {
            \mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            self::$connection = new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            self::$connection->set_charset('utf8mb4');
        }
        return self::$connection;
    }
}

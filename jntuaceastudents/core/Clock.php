<?php
declare(strict_types=1);
namespace College;
final class Clock
{
    public static function configure(): void
    {
        $timezone=defined('COLLEGE_TIMEZONE')?(string)COLLEGE_TIMEZONE:'Asia/Kolkata';
        if(!in_array($timezone,\DateTimeZone::listIdentifiers(),true)){throw new \RuntimeException('Invalid COLLEGE_TIMEZONE configuration.');}
        date_default_timezone_set($timezone);
    }
}

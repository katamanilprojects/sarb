<?php
declare(strict_types=1);
namespace College;

final class DateRange
{
    /** Validate once; callers must still bind these values as query parameters. */
    public static function validate(mixed $start, mixed $end): void
    {
        if (($start === null) !== ($end === null)) {
            throw new \InvalidArgumentException('Supply both start and end dates.');
        }
        foreach ([$start, $end] as $value) {
            if ($value === null) { continue; }
            if (!is_string($value)) { throw new \InvalidArgumentException('Dates must be text values in YYYY-MM-DD format.'); }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                throw new \InvalidArgumentException('Enter valid dates in YYYY-MM-DD format.');
            }
        }
        if ($start !== null && $start > $end) {
            throw new \InvalidArgumentException('Start date must be on or before end date.');
        }
    }
}

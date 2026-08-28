<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Weekday-only business-day arithmetic. Deliberately holiday-naive —
 * a demo-scope simplification, not a full business calendar.
 */
class BusinessDays
{
    public static function subtract(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $date = $from;
        $remaining = $days;

        while ($remaining > 0) {
            $date = $date->subDay();

            if (! $date->isWeekend()) {
                $remaining--;
            }
        }

        return $date;
    }
}

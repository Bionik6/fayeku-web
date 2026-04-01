<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('format_date')) {
    /**
     * Format a date as "21 Jan 2026" or "21 Jan 2026, 14:35" when $withTime is true.
     *
     * @param  Carbon|CarbonInterface|string|null  $date
     */
    function format_date(Carbon|CarbonInterface|string|null $date, bool $withTime = false, bool $withYear = true): string
    {
        if (! $date) {
            return '—';
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $months = [
            1  => 'Jan', 2  => 'Fév', 3  => 'Mar', 4  => 'Avr',
            5  => 'Mai', 6  => 'Jun', 7  => 'Jul', 8  => 'Aoû',
            9  => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
        ];

        $formatted = $carbon->format('d') . ' ' . $months[$carbon->month];

        if ($withYear) {
            $formatted .= ' ' . $carbon->format('Y');
        }

        if ($withTime) {
            $formatted .= ', ' . $carbon->format('H:i');
        }

        return $formatted;
    }
}

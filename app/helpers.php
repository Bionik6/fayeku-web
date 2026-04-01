<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\PME\Invoicing\Services\CurrencyService;

if (! function_exists('format_date')) {
    /**
     * Format a date as "21 Jan 2026" or "21 Jan 2026, 14:35" when $withTime is true.
     */
    function format_date(Carbon|CarbonInterface|string|null $date, bool $withTime = false, bool $withYear = true): string
    {
        if (! $date) {
            return '—';
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $months = [
            1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aoû',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
        ];

        $formatted = $carbon->format('d').' '.$months[$carbon->month];

        if ($withYear) {
            $formatted .= ' '.$carbon->format('Y');
        }

        if ($withTime) {
            $formatted .= ', '.$carbon->format('H:i');
        }

        return $formatted;
    }
}

if (! function_exists('format_month')) {
    /**
     * Format a date as "Janvier 2026" — full French month name, first letter uppercase.
     * Pass withYear: false to get just "Janvier".
     */
    function format_month(Carbon|CarbonInterface|string|null $date, bool $withYear = true): string
    {
        if (! $date) {
            return '—';
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $formatted = $months[$carbon->month];

        if ($withYear) {
            $formatted .= ' '.$carbon->format('Y');
        }

        return $formatted;
    }
}

if (! function_exists('format_phone')) {
    /**
     * Format a phone number using the country patterns defined in fayeku.phone_countries.
     *
     * Example: "+221771234567" → "+221 77 123 45 67"
     * Falls back to the raw value if no matching country prefix is found.
     *
     * Complexity: O(n) one-time index build, then O(1) per call — the index is
     * keyed by prefix string so lookup is a direct hash map access, trying at
     * most max_prefix_length candidates (≤ 4 in the current config).
     */
    function format_phone(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }

        /**
         * Built once per process: a map of prefix → format string, plus the
         * sorted list of distinct prefix lengths to try (longest first).
         *
         * @var array{
         *   index: array<string, string>,
         *   lengths: list<int>,
         * }|null $cache
         */
        static $cache = null;

        if ($cache === null) {
            $index = [];
            foreach (config('fayeku.phone_countries', []) as $data) {
                $prefix = $data['prefix'] ?? '';
                $format = $data['format'] ?? '';
                if ($prefix && $format) {
                    // First entry wins for shared prefixes (e.g. +1 for US/CA/JM).
                    $index[$prefix] ??= $format;
                }
            }

            // Distinct prefix lengths, longest first — at most ~3 values in practice.
            $lengths = array_unique(array_map('strlen', array_keys($index)));
            rsort($lengths);

            $cache = ['index' => $index, 'lengths' => $lengths];
        }

        // O(1): try each prefix length (≤ 4 iterations) until a match is found.
        foreach ($cache['lengths'] as $len) {
            $prefix = substr($phone, 0, $len);

            if (! isset($cache['index'][$prefix])) {
                continue;
            }

            $format = $cache['index'][$prefix];
            $localDigits = (string) preg_replace('/\D+/', '', substr($phone, $len));
            $formatLen = strlen($format);
            $localLen = strlen($localDigits);
            $result = '';
            $cursor = 0;

            for ($i = 0; $i < $formatLen; $i++) {
                if ($format[$i] === 'X') {
                    if ($cursor < $localLen) {
                        $result .= $localDigits[$cursor++];
                    }
                } else {
                    $result .= $format[$i];
                }
            }

            return $result;
        }

        return $phone;
    }
}

if (! function_exists('format_money')) {
    /**
     * Format an amount for display using the currency's configuration.
     *
     * Amounts are expected in the smallest unit (same convention as CurrencyService::format):
     *   - XOF / JPY (0 decimals): stored value IS the amount → 14_632_000 → "14 632 000 FCFA"
     *   - USD / EUR  (2 decimals): stored value is in cents  →     1_250   → "12.50 USD"
     *
     * Pass compact: true for table/inline display — renders a symbol instead of the full label:
     *   - XOF : "885 000 F"   (symbol after, with space)
     *   - EUR : "€885,00"     (symbol before, no space)
     *   - CHF : "CHF 885.00"  (symbol before, with space)
     */
    function format_money(int|float $amount, string $currency = 'XOF', bool $withLabel = true, bool $compact = false): string
    {
        if (! $compact) {
            return CurrencyService::format((int) $amount, $currency, $withLabel);
        }

        // symbol, position ('before'|'after'), space between symbol and number
        $symbols = [
            'XOF' => ['symbol' => 'F',    'position' => 'after',  'space' => true],
            'EUR' => ['symbol' => '€',    'position' => 'before', 'space' => false],
            'USD' => ['symbol' => '$',    'position' => 'before', 'space' => false],
            'GBP' => ['symbol' => '£',    'position' => 'before', 'space' => false],
            'JPY' => ['symbol' => '¥',    'position' => 'before', 'space' => false],
            'CAD' => ['symbol' => 'CA$',  'position' => 'before', 'space' => false],
            'AUD' => ['symbol' => 'A$',   'position' => 'before', 'space' => false],
            'HKD' => ['symbol' => 'HK$',  'position' => 'before', 'space' => false],
            'NZD' => ['symbol' => 'NZ$',  'position' => 'before', 'space' => false],
            'CNH' => ['symbol' => '¥',    'position' => 'before', 'space' => false],
            'CHF' => ['symbol' => 'CHF',  'position' => 'before', 'space' => true],
        ];

        $number = CurrencyService::format((int) $amount, $currency, withLabel: false);

        $config = $symbols[$currency] ?? ['symbol' => $currency, 'position' => 'before', 'space' => true];
        $sep = $config['space'] ? ' ' : '';

        return $config['position'] === 'after'
            ? $number.$sep.$config['symbol']
            : $config['symbol'].$sep.$number;
    }
}

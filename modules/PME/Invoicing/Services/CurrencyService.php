<?php

namespace Modules\PME\Invoicing\Services;

class CurrencyService
{
    /**
     * Full list of supported currencies with their display configuration.
     *
     * - label: short label for display after amounts
     * - name: full name for dropdown
     * - decimals: number of decimal places (0 for FCFA/JPY, 2 for most others)
     * - dec_sep: decimal separator
     * - thousands_sep: thousands separator
     *
     * @return array<string, array{label: string, name: string, decimals: int, dec_sep: string, thousands_sep: string}>
     */
    public static function currencies(): array
    {
        return [
            'XOF' => ['label' => 'FCFA', 'name' => 'FCFA (XOF)', 'decimals' => 0, 'dec_sep' => '', 'thousands_sep' => ' '],
            'USD' => ['label' => 'USD', 'name' => 'Dollar américain (USD)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'EUR' => ['label' => 'EUR', 'name' => 'Euro (EUR)', 'decimals' => 2, 'dec_sep' => ',', 'thousands_sep' => ' '],
            'JPY' => ['label' => 'JPY', 'name' => 'Yen japonais (JPY)', 'decimals' => 0, 'dec_sep' => '', 'thousands_sep' => ','],
            'CAD' => ['label' => 'CAD', 'name' => 'Dollar canadien (CAD)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'GBP' => ['label' => 'GBP', 'name' => 'Livre sterling (GBP)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'AUD' => ['label' => 'AUD', 'name' => 'Dollar australien (AUD)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'CNH' => ['label' => 'CNH', 'name' => 'Yuan chinois (CNH)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'CHF' => ['label' => 'CHF', 'name' => 'Franc suisse (CHF)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ' '],
            'HKD' => ['label' => 'HKD', 'name' => 'Dollar Hong Kong (HKD)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
            'NZD' => ['label' => 'NZD', 'name' => 'Dollar néo-zélandais (NZD)', 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','],
        ];
    }

    /**
     * Get the short label for a currency code (e.g. "FCFA", "USD").
     */
    public static function label(string $code): string
    {
        return self::currencies()[$code]['label'] ?? $code;
    }

    /**
     * Get the full name for a currency code (e.g. "FCFA (XOF)").
     */
    public static function name(string $code): string
    {
        return self::currencies()[$code]['name'] ?? $code;
    }

    /**
     * Get the number of decimal places for a currency.
     */
    public static function decimals(string $code): int
    {
        return self::currencies()[$code]['decimals'] ?? 2;
    }

    /**
     * Check if a currency uses decimals.
     */
    public static function hasDecimals(string $code): bool
    {
        return self::decimals($code) > 0;
    }

    /**
     * Format an amount stored in the smallest unit (cents) for display.
     *
     * For currencies with decimals (USD, EUR...), the stored value is in cents:
     *   1250 => "12.50 USD"
     *
     * For currencies without decimals (XOF, JPY), the stored value IS the amount:
     *   1250 => "1 250 FCFA"
     */
    public static function format(int $amount, string $code, bool $withLabel = true): string
    {
        $config = self::currencies()[$code] ?? null;

        if (! $config) {
            $formatted = number_format($amount, 0, '.', ' ');

            return $withLabel ? "{$formatted} {$code}" : $formatted;
        }

        if ($config['decimals'] > 0) {
            $divisor = 10 ** $config['decimals'];
            $value = $amount / $divisor;
            $formatted = number_format($value, $config['decimals'], $config['dec_sep'], $config['thousands_sep']);
        } else {
            $formatted = number_format($amount, 0, '', $config['thousands_sep']);
        }

        return $withLabel ? "{$formatted} {$config['label']}" : $formatted;
    }

    /**
     * Parse a user-input string into the smallest unit (cents).
     *
     * For XOF: "1 500 000" => 1500000 (no conversion)
     * For USD: "12,500.50" => 1250050 (dollars to cents)
     * For EUR: "12 500,50" => 1250050 (euros to cents)
     */
    public static function parse(string $input, string $code): int
    {
        $config = self::currencies()[$code] ?? null;

        if (! $config || $config['decimals'] === 0) {
            return (int) preg_replace('/\D/', '', $input);
        }

        $cleaned = str_replace($config['thousands_sep'], '', $input);

        if ($config['dec_sep'] !== '.') {
            $cleaned = str_replace($config['dec_sep'], '.', $cleaned);
        }

        $cleaned = preg_replace('/[^\d.]/', '', $cleaned);

        $value = (float) $cleaned;

        return (int) round($value * (10 ** $config['decimals']));
    }

    /**
     * Get all supported currency codes.
     *
     * @return string[]
     */
    public static function codes(): array
    {
        return array_keys(self::currencies());
    }

    /**
     * Get the formatting config for JavaScript (Alpine.js).
     *
     * @return array{decimals: int, dec_sep: string, thousands_sep: string, label: string}
     */
    public static function jsConfig(string $code): array
    {
        $config = self::currencies()[$code] ?? ['label' => $code, 'decimals' => 2, 'dec_sep' => '.', 'thousands_sep' => ','];

        return [
            'decimals' => $config['decimals'],
            'decSep' => $config['dec_sep'],
            'thousandsSep' => $config['thousands_sep'],
            'label' => $config['label'],
        ];
    }
}

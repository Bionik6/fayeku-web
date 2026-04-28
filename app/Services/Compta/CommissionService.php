<?php

namespace App\Services\Compta;

class CommissionService
{
    public const int DEFAULT_RATE = 15;

    /**
     * Monthly subscription price (FCFA) for the given plan slug.
     */
    public static function planMonthlyPrice(string $planSlug): int
    {
        return match ($planSlug) {
            'essentiel' => 20_000,
            default => 10_000,
        };
    }

    /**
     * Calculate the commission amount for a given subscription price.
     */
    public static function calculate(int $subscriptionPrice, int $ratePercent = self::DEFAULT_RATE): int
    {
        return (int) round($subscriptionPrice * $ratePercent / 100);
    }
}

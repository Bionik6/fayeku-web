<?php

use App\Services\Compta\CommissionService;

// ─── Calcul de base ──────────────────────────────────────────────────────────

test('la commission Essentiel est calculée correctement (20 000 × 15 % = 3 000)', function () {
    expect(CommissionService::calculate(20_000))->toBe(3_000);
});

test('la commission Basique est calculée correctement (10 000 × 15 % = 1 500)', function () {
    expect(CommissionService::calculate(10_000))->toBe(1_500);
});

test('la commission avec un taux personnalisé est correcte', function () {
    expect(CommissionService::calculate(20_000, 10))->toBe(2_000);
    expect(CommissionService::calculate(10_000, 20))->toBe(2_000);
});

test('la commission est zéro pour un abonnement gratuit', function () {
    expect(CommissionService::calculate(0))->toBe(0);
});

test('la commission est zéro pour un taux à zéro', function () {
    expect(CommissionService::calculate(20_000, 0))->toBe(0);
});

// ─── Cohérence abonnement × taux ─────────────────────────────────────────────

test('la commission ne peut jamais dépasser le prix de l\'abonnement', function (int $price, int $rate) {
    $commission = CommissionService::calculate($price, $rate);

    expect($commission)->toBeLessThanOrEqual($price);
})->with([
    'essentiel 15%' => [20_000, 15],
    'basique 15%' => [10_000, 15],
    'essentiel 50%' => [20_000, 50],
    'basique 100%' => [10_000, 100],
]);

test('la commission est exactement abonnement × taux / 100', function (int $price, int $rate, int $expected) {
    expect(CommissionService::calculate($price, $rate))->toBe($expected);
})->with([
    'essentiel 15%' => [20_000, 15, 3_000],
    'basique 15%' => [10_000, 15, 1_500],
    'essentiel 10%' => [20_000, 10, 2_000],
    'basique 20%' => [10_000, 20, 2_000],
    'premium 15%' => [50_000, 15, 7_500],
]);

// ─── Taux par défaut ─────────────────────────────────────────────────────────

test('le taux par défaut est 15 %', function () {
    expect(CommissionService::DEFAULT_RATE)->toBe(15);
});

test('le calcul sans taux explicite utilise 15 %', function () {
    $withDefault = CommissionService::calculate(20_000);
    $withExplicit = CommissionService::calculate(20_000, 15);

    expect($withDefault)->toBe($withExplicit);
});

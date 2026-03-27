<?php

use Modules\PME\Invoicing\Services\CurrencyService;

// ─── Label ───────────────────────────────────────────────────────────────────

test('label returns FCFA for XOF', function () {
    expect(CurrencyService::label('XOF'))->toBe('FCFA');
});

test('label returns USD for USD', function () {
    expect(CurrencyService::label('USD'))->toBe('USD');
});

test('label returns code itself for unknown currency', function () {
    expect(CurrencyService::label('ZZZ'))->toBe('ZZZ');
});

// ─── Decimals ────────────────────────────────────────────────────────────────

test('XOF has 0 decimals', function () {
    expect(CurrencyService::decimals('XOF'))->toBe(0)
        ->and(CurrencyService::hasDecimals('XOF'))->toBeFalse();
});

test('JPY has 0 decimals', function () {
    expect(CurrencyService::decimals('JPY'))->toBe(0)
        ->and(CurrencyService::hasDecimals('JPY'))->toBeFalse();
});

test('USD has 2 decimals', function () {
    expect(CurrencyService::decimals('USD'))->toBe(2)
        ->and(CurrencyService::hasDecimals('USD'))->toBeTrue();
});

test('EUR has 2 decimals', function () {
    expect(CurrencyService::decimals('EUR'))->toBe(2)
        ->and(CurrencyService::hasDecimals('EUR'))->toBeTrue();
});

// ─── Format (XOF / no decimals) ─────────────────────────────────────────────

test('format XOF simple amount', function () {
    expect(CurrencyService::format(50000, 'XOF'))->toBe('50 000 FCFA');
});

test('format XOF zero', function () {
    expect(CurrencyService::format(0, 'XOF'))->toBe('0 FCFA');
});

test('format XOF large amount', function () {
    expect(CurrencyService::format(1500000, 'XOF'))->toBe('1 500 000 FCFA');
});

test('format XOF very large amount', function () {
    expect(CurrencyService::format(250000000, 'XOF'))->toBe('250 000 000 FCFA');
});

test('format XOF without label', function () {
    expect(CurrencyService::format(50000, 'XOF', withLabel: false))->toBe('50 000');
});

// ─── Format (USD / 2 decimals, comma thousands, dot decimal) ─────────────────

test('format USD whole dollars stored as cents', function () {
    expect(CurrencyService::format(1200000, 'USD'))->toBe('12,000.00 USD');
});

test('format USD with cents', function () {
    expect(CurrencyService::format(1250050, 'USD'))->toBe('12,500.50 USD');
});

test('format USD small amount', function () {
    expect(CurrencyService::format(99, 'USD'))->toBe('0.99 USD');
});

test('format USD zero', function () {
    expect(CurrencyService::format(0, 'USD'))->toBe('0.00 USD');
});

test('format USD one cent', function () {
    expect(CurrencyService::format(1, 'USD'))->toBe('0.01 USD');
});

test('format USD without label', function () {
    expect(CurrencyService::format(1250050, 'USD', withLabel: false))->toBe('12,500.50');
});

// ─── Format (EUR / 2 decimals, space thousands, comma decimal) ───────────────

test('format EUR whole euros', function () {
    expect(CurrencyService::format(1200000, 'EUR'))->toBe('12 000,00 EUR');
});

test('format EUR with cents', function () {
    expect(CurrencyService::format(1250050, 'EUR'))->toBe('12 500,50 EUR');
});

test('format EUR small amount', function () {
    expect(CurrencyService::format(50, 'EUR'))->toBe('0,50 EUR');
});

// ─── Format (JPY / no decimals, comma thousands) ─────────────────────────────

test('format JPY no decimals', function () {
    expect(CurrencyService::format(15000, 'JPY'))->toBe('15,000 JPY');
});

test('format JPY large amount', function () {
    expect(CurrencyService::format(1000000, 'JPY'))->toBe('1,000,000 JPY');
});

// ─── Format (GBP / 2 decimals) ──────────────────────────────────────────────

test('format GBP with pence', function () {
    expect(CurrencyService::format(149999, 'GBP'))->toBe('1,499.99 GBP');
});

// ─── Format (CHF / space thousands, dot decimal) ─────────────────────────────

test('format CHF with centimes', function () {
    expect(CurrencyService::format(250075, 'CHF'))->toBe('2 500.75 CHF');
});

// ─── Format (unknown currency) ───────────────────────────────────────────────

test('format unknown currency falls back gracefully', function () {
    expect(CurrencyService::format(5000, 'ZZZ'))->toBe('5 000 ZZZ');
});

// ─── Parse (XOF / no decimals) ──────────────────────────────────────────────

test('parse XOF plain digits', function () {
    expect(CurrencyService::parse('1500000', 'XOF'))->toBe(1500000);
});

test('parse XOF formatted with spaces', function () {
    expect(CurrencyService::parse('1 500 000', 'XOF'))->toBe(1500000);
});

test('parse XOF empty string', function () {
    expect(CurrencyService::parse('', 'XOF'))->toBe(0);
});

test('parse XOF with non-numeric chars', function () {
    expect(CurrencyService::parse('abc 1 500 xyz', 'XOF'))->toBe(1500);
});

// ─── Parse (USD / 2 decimals) ────────────────────────────────────────────────

test('parse USD formatted string to cents', function () {
    expect(CurrencyService::parse('12,500.50', 'USD'))->toBe(1250050);
});

test('parse USD whole dollars', function () {
    expect(CurrencyService::parse('100', 'USD'))->toBe(10000);
});

test('parse USD with no thousands separator', function () {
    expect(CurrencyService::parse('12500.50', 'USD'))->toBe(1250050);
});

test('parse USD zero', function () {
    expect(CurrencyService::parse('0', 'USD'))->toBe(0);
});

test('parse USD small amount', function () {
    expect(CurrencyService::parse('0.99', 'USD'))->toBe(99);
});

// ─── Parse (EUR / space thousands, comma decimal) ────────────────────────────

test('parse EUR formatted string to cents', function () {
    expect(CurrencyService::parse('12 500,50', 'EUR'))->toBe(1250050);
});

test('parse EUR whole euros', function () {
    expect(CurrencyService::parse('1000', 'EUR'))->toBe(100000);
});

test('parse EUR with comma decimal', function () {
    expect(CurrencyService::parse('99,99', 'EUR'))->toBe(9999);
});

// ─── Parse (JPY / no decimals) ──────────────────────────────────────────────

test('parse JPY formatted with commas', function () {
    expect(CurrencyService::parse('15,000', 'JPY'))->toBe(15000);
});

test('parse JPY plain digits', function () {
    expect(CurrencyService::parse('15000', 'JPY'))->toBe(15000);
});

// ─── Roundtrip (format then parse) ──────────────────────────────────────────

test('roundtrip XOF: format then parse returns original', function () {
    $original = 1500000;
    $formatted = CurrencyService::format($original, 'XOF', withLabel: false);
    $parsed = CurrencyService::parse($formatted, 'XOF');

    expect($parsed)->toBe($original);
});

test('roundtrip USD: format then parse returns original', function () {
    $original = 1250050;
    $formatted = CurrencyService::format($original, 'USD', withLabel: false);
    $parsed = CurrencyService::parse($formatted, 'USD');

    expect($parsed)->toBe($original);
});

test('roundtrip EUR: format then parse returns original', function () {
    $original = 1250050;
    $formatted = CurrencyService::format($original, 'EUR', withLabel: false);
    $parsed = CurrencyService::parse($formatted, 'EUR');

    expect($parsed)->toBe($original);
});

test('roundtrip CHF: format then parse returns original', function () {
    $original = 250075;
    $formatted = CurrencyService::format($original, 'CHF', withLabel: false);
    $parsed = CurrencyService::parse($formatted, 'CHF');

    expect($parsed)->toBe($original);
});

test('roundtrip JPY: format then parse returns original', function () {
    $original = 15000;
    $formatted = CurrencyService::format($original, 'JPY', withLabel: false);
    $parsed = CurrencyService::parse($formatted, 'JPY');

    expect($parsed)->toBe($original);
});

// ─── All currencies are defined ──────────────────────────────────────────────

test('all 11 currencies are supported', function () {
    $codes = CurrencyService::codes();

    expect($codes)->toContain('XOF', 'USD', 'EUR', 'JPY', 'CAD', 'GBP', 'AUD', 'CNH', 'CHF', 'HKD', 'NZD')
        ->and($codes)->toHaveCount(11);
});

test('every currency has a label, name, decimals, dec_sep, thousands_sep', function () {
    foreach (CurrencyService::currencies() as $code => $config) {
        expect($config)->toHaveKeys(['label', 'name', 'decimals', 'dec_sep', 'thousands_sep']);
        expect($config['label'])->toBeString()->not->toBeEmpty();
        expect($config['name'])->toBeString()->not->toBeEmpty();
        expect($config['decimals'])->toBeInt()->toBeGreaterThanOrEqual(0);
    }
});

// ─── jsConfig ────────────────────────────────────────────────────────────────

// ─── maxAmount ──────────────────────────────────────────────────────────────

test('maxAmount for XOF (0 decimals) is 999 999 999', function () {
    expect(CurrencyService::maxAmount('XOF'))->toBe(999_999_999);
});

test('maxAmount for JPY (0 decimals) is 999 999 999', function () {
    expect(CurrencyService::maxAmount('JPY'))->toBe(999_999_999);
});

test('maxAmount for USD (2 decimals) is 99 999 999 999 (999 999 999.99 in cents)', function () {
    expect(CurrencyService::maxAmount('USD'))->toBe(99_999_999_999);
});

test('maxAmount for EUR (2 decimals) is 99 999 999 999', function () {
    expect(CurrencyService::maxAmount('EUR'))->toBe(99_999_999_999);
});

test('maxAmount for GBP (2 decimals) is 99 999 999 999', function () {
    expect(CurrencyService::maxAmount('GBP'))->toBe(99_999_999_999);
});

test('maxAmount for unknown currency defaults to 2 decimals', function () {
    expect(CurrencyService::maxAmount('ZZZ'))->toBe(99_999_999_999);
});

// ─── jsConfig ────────────────────────────────────────────────────────────────

test('jsConfig returns correct shape for XOF', function () {
    $config = CurrencyService::jsConfig('XOF');

    expect($config)->toBe([
        'decimals' => 0,
        'decSep' => '',
        'thousandsSep' => ' ',
        'label' => 'FCFA',
        'maxAmount' => 999_999_999,
    ]);
});

test('jsConfig returns correct shape for USD', function () {
    $config = CurrencyService::jsConfig('USD');

    expect($config)->toBe([
        'decimals' => 2,
        'decSep' => '.',
        'thousandsSep' => ',',
        'label' => 'USD',
        'maxAmount' => 99_999_999_999,
    ]);
});

test('jsConfig returns correct shape for EUR', function () {
    $config = CurrencyService::jsConfig('EUR');

    expect($config)->toBe([
        'decimals' => 2,
        'decSep' => ',',
        'thousandsSep' => ' ',
        'label' => 'EUR',
        'maxAmount' => 99_999_999_999,
    ]);
});

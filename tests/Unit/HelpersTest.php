<?php

use Carbon\Carbon;
use Tests\TestCase;

// format_phone uses config(), which requires the Laravel container.
uses(TestCase::class);

// ─── format_date ─────────────────────────────────────────────────────────────

describe('format_date', function () {
    it('formats a Carbon instance as "d Mon YYYY"', function () {
        expect(format_date(Carbon::create(2026, 1, 21)))->toBe('21 Jan 2026');
    });

    it('formats a date string', function () {
        expect(format_date('2026-04-01'))->toBe('01 Avr 2026');
    });

    it('returns a dash for null', function () {
        expect(format_date(null))->toBe('—');
    });

    it('zero-pads the day', function () {
        expect(format_date(Carbon::create(2026, 3, 4)))->toBe('04 Mar 2026');
    });

    it('includes the time when withTime is true', function () {
        $date = Carbon::create(2026, 1, 21, 14, 35);

        expect(format_date($date))->toBe('21 Jan 2026')
            ->and(format_date($date, withTime: true))->toBe('21 Jan 2026, 14:35');
    });

    it('zero-pads hours and minutes in time', function () {
        expect(format_date(Carbon::create(2026, 1, 21, 9, 5), withTime: true))->toBe('21 Jan 2026, 09:05');
    });

    it('omits the year when withYear is false', function () {
        expect(format_date(Carbon::create(2026, 1, 21), withYear: false))->toBe('21 Jan');
    });

    it('combines withTime and withYear false', function () {
        expect(format_date(Carbon::create(2026, 1, 21, 9, 5), withTime: true, withYear: false))->toBe('21 Jan, 09:05');
    });

    it('uses correct 3-letter French abbreviations without dots for all months', function (int $month, string $abbreviation) {
        expect(format_date(Carbon::create(2026, $month, 1)))->toBe("01 {$abbreviation} 2026");
    })->with([
        'janvier' => [1,  'Jan'],
        'février' => [2,  'Fév'],
        'mars' => [3,  'Mar'],
        'avril' => [4,  'Avr'],
        'mai' => [5,  'Mai'],
        'juin' => [6,  'Jun'],
        'juillet' => [7,  'Jul'],
        'août' => [8,  'Aoû'],
        'septembre' => [9,  'Sep'],
        'octobre' => [10, 'Oct'],
        'novembre' => [11, 'Nov'],
        'décembre' => [12, 'Déc'],
    ]);
});

// ─── format_month ─────────────────────────────────────────────────────────────

describe('format_month', function () {
    it('formats a date as "Mois Année"', function () {
        expect(format_month(Carbon::create(2026, 1, 15)))->toBe('Janvier 2026');
    });

    it('formats a date string', function () {
        expect(format_month('2026-04-01'))->toBe('Avril 2026');
    });

    it('returns a dash for null', function () {
        expect(format_month(null))->toBe('—');
    });

    it('omits the year when withYear is false', function () {
        expect(format_month(Carbon::create(2026, 6, 1), withYear: false))->toBe('Juin');
    });

    it('uses full French month names for all months', function (int $month, string $name) {
        expect(format_month(Carbon::create(2026, $month, 1)))->toBe("{$name} 2026");
    })->with([
        'janvier' => [1,  'Janvier'],
        'février' => [2,  'Février'],
        'mars' => [3,  'Mars'],
        'avril' => [4,  'Avril'],
        'mai' => [5,  'Mai'],
        'juin' => [6,  'Juin'],
        'juillet' => [7,  'Juillet'],
        'août' => [8,  'Août'],
        'septembre' => [9,  'Septembre'],
        'octobre' => [10, 'Octobre'],
        'novembre' => [11, 'Novembre'],
        'décembre' => [12, 'Décembre'],
    ]);
});

// ─── format_amount ────────────────────────────────────────────────────────────

describe('format_amount', function () {
    it('appends F suffix for XOF with no space', function () {
        expect(format_amount(1_560_000))->toBe('1 560 000F');
    });

    it('prepends € for EUR with no space', function () {
        expect(format_amount(4_000, 'EUR'))->toBe('€40,00');
    });

    it('prepends $ for USD with no space', function () {
        expect(format_amount(4_000, 'USD'))->toBe('$40.00');
    });

    it('prepends £ for GBP with no space', function () {
        expect(format_amount(4_000, 'GBP'))->toBe('£40.00');
    });

    it('prepends ¥ for JPY with no space', function () {
        expect(format_amount(1_250, 'JPY'))->toBe('¥1,250');
    });

    it('prepends CHF with a space for CHF', function () {
        expect(format_amount(4_000, 'CHF'))->toBe('CHF 40.00');
    });

    it('formats all currencies with the correct symbol and position', function (int $amount, string $currency, string $expected) {
        expect(format_amount($amount, $currency))->toBe($expected);
    })->with([
        'CAD' => [4_000, 'CAD', 'CA$40.00'],
        'AUD' => [4_000, 'AUD', 'A$40.00'],
        'HKD' => [4_000, 'HKD', 'HK$40.00'],
        'NZD' => [4_000, 'NZD', 'NZ$40.00'],
        'CNH' => [4_000, 'CNH', '¥40.00'],
    ]);

    it('falls back to currency code as prefix with a space for unknown currencies', function () {
        expect(format_amount(4_000, 'XYZ'))->toBe('XYZ 4 000');
    });
});

// ─── format_money ─────────────────────────────────────────────────────────────

describe('format_money', function () {
    it('formats XOF by default', function () {
        expect(format_money(14_632_000))->toBe('14 632 000 FCFA');
    });

    it('formats zero', function () {
        expect(format_money(0))->toBe('0 FCFA');
    });

    it('uses space as thousands separator for XOF', function () {
        expect(format_money(1_000))->toBe('1 000 FCFA')
            ->and(format_money(1_000_000))->toBe('1 000 000 FCFA');
    });

    it('can omit the label', function () {
        expect(format_money(14_632_000, withLabel: false))->toBe('14 632 000');
    });

    it('formats USD cents with dot decimal separator', function () {
        expect(format_money(1_250, 'USD'))->toBe('12.50 USD');
    });

    it('formats EUR cents with comma decimal separator', function () {
        expect(format_money(1_250, 'EUR'))->toBe('12,50 EUR');
    });

    it('formats JPY with no decimals and comma thousands separator', function () {
        expect(format_money(1_250, 'JPY'))->toBe('1,250 JPY');
    });

    it('formats CHF cents with space thousands separator', function () {
        expect(format_money(1_250, 'CHF'))->toBe('12.50 CHF');
    });

    it('formats various currencies correctly', function (int $amount, string $currency, string $expected) {
        expect(format_money($amount, $currency))->toBe($expected);
    })->with([
        'GBP' => [1_000, 'GBP', '10.00 GBP'],
        'CAD' => [1_000, 'CAD', '10.00 CAD'],
        'AUD' => [1_000, 'AUD', '10.00 AUD'],
        'HKD' => [1_000, 'HKD', '10.00 HKD'],
        'NZD' => [1_000, 'NZD', '10.00 NZD'],
        'CNH' => [1_000, 'CNH', '10.00 CNH'],
    ]);

    it('falls back gracefully for unknown currency codes', function () {
        expect(format_money(1_000, 'XYZ'))->toBe('1 000 XYZ');
    });
});

// ─── format_phone ─────────────────────────────────────────────────────────────

describe('format_phone', function () {
    it('returns a dash for null', function () {
        expect(format_phone(null))->toBe('—');
    });

    it('returns a dash for an empty string', function () {
        expect(format_phone(''))->toBe('—');
    });

    it('returns the raw value when the prefix is unknown', function () {
        expect(format_phone('+999123456789'))->toBe('+999123456789');
    });

    it('formats a Senegalese number', function () {
        expect(format_phone('+221771234567'))->toBe('+221 77 123 45 67');
    });

    it('formats an Ivorian number', function () {
        expect(format_phone('+2250701234567'))->toBe('+225 07 01 23 4567');
    });

    it('formats a French number', function () {
        expect(format_phone('+33612345678'))->toBe('+33 6 12 34 56 78');
    });

    it('formats a US number with dashes', function () {
        expect(format_phone('+12125551234'))->toBe('+1 212-555-1234');
    });

    it('formats a Moroccan number with dashes', function () {
        expect(format_phone('+212612345678'))->toBe('+212 612-345678');
    });

    it('matches the longest prefix first to avoid ambiguity', function () {
        // +225 (CI, 3 chars) must not be confused with +22 or +2
        expect(format_phone('+2250701234567'))->toStartWith('+225 ');
    });

    it('formats numbers from various regions correctly', function (string $phone, string $expected) {
        expect(format_phone($phone))->toBe($expected);
    })->with([
        'Mali (+223)' => ['+22376543210',  '+223 76 54 32 10'],
        'Burkina Faso (+226)' => ['+22670123456',  '+226 70 12 34 56'],
        'Cameroun (+237)' => ['+237612345678', '+237 6 12 34 56 78'],
        'Belgique (+32)' => ['+32470123456',  '+32 470 12 34 56'],
    ]);
});

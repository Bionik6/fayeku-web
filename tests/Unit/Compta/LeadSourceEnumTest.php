<?php

use App\Enums\Compta\LeadSource;

test('LeadSource exposes the 7 expected cases with stable string values', function () {
    expect(array_map(
        fn (LeadSource $case) => [$case->name, $case->value],
        LeadSource::cases(),
    ))->toEqual([
        ['Organic', 'organic'],
        ['Referral', 'referral'],
        ['Event', 'event'],
        ['WhatsAppOutreach', 'whatsapp_outreach'],
        ['LinkedIn', 'linkedin'],
        ['Press', 'press'],
        ['Other', 'other'],
    ]);
});

test('LeadSource::from rejects unknown values', function () {
    expect(fn () => LeadSource::from('unknown'))->toThrow(ValueError::class);
});

test('LeadSource::tryFrom returns null for unknown values', function () {
    expect(LeadSource::tryFrom('unknown'))->toBeNull();
    expect(LeadSource::tryFrom('whatsapp_outreach'))->toBe(LeadSource::WhatsAppOutreach);
});

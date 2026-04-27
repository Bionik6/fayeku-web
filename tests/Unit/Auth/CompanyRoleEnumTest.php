<?php

use App\Enums\Auth\CompanyRole;

test('CompanyRole exposes the 3 expected cases with stable string values', function () {
    expect(array_map(
        fn (CompanyRole $case) => [$case->name, $case->value],
        CompanyRole::cases(),
    ))->toEqual([
        ['Owner', 'owner'],
        ['Admin', 'admin'],
        ['Member', 'member'],
    ]);
});

test('CompanyRole::tryFrom returns null for unknown values', function () {
    expect(CompanyRole::tryFrom('unknown'))->toBeNull();
    expect(CompanyRole::tryFrom('owner'))->toBe(CompanyRole::Owner);
});

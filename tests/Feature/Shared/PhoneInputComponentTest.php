<?php

use Illuminate\Support\Facades\Blade;

it('renders with a single country as static label', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\' => \'SEN (+221)\']" country-value="SN" />',
    );

    expect($html)
        ->toContain('SEN (+221)')
        ->not->toContain('Rechercher un pays...')
        ->toContain('type="hidden"');
});

it('renders with multiple countries as searchable dropdown', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\', \'CI\', \'FR\']" country-value="SN" />',
    );

    expect($html)
        ->toContain('phoneInput(')
        ->toContain('Rechercher un pays...')
        ->toContain('<button');
});

it('defaults to SN and CI when no countries prop is passed', function (): void {
    $html = Blade::render('<x-phone-input />');

    expect($html)
        ->toContain('SEN (+221)')
        ->toContain('CIV (+225)');
});

it('renders all countries when receiving a full label map', function (): void {
    $countries = collect(config('fayeku.phone_countries'))
        ->map(fn ($c) => $c['label'])
        ->all();

    $html = Blade::render(
        '<x-phone-input :countries="$countries" country-value="SN" />',
        ['countries' => $countries],
    );

    expect($html)
        ->toContain('SEN (+221)')
        ->toContain('FRA (+33)')
        ->toContain('phoneInput(');
});

it('pre-formats phone value for Senegal', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\']" country-value="SN" phone-value="779123456" />',
    );

    // Formatted SN: XX XXX XX XX → 77 912 34 56
    expect($html)->toContain('77 912 34 56');
});

it('pre-formats phone value for France', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'FR\']" country-value="FR" phone-value="612345678" />',
    );

    // Formatted FR: X XX XX XX XX → 6 12 34 56 78
    expect($html)->toContain('6 12 34 56 78');
});

it('pre-formats phone value for Morocco', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'MA\']" country-value="MA" phone-value="612345678" />',
    );

    // Formatted MA: XXX-XXXXXX → 612-345678
    expect($html)->toContain('612-345678');
});

it('renders in readonly mode with formatted value', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\']" country-value="SN" phone-value="779123456" :readonly="true" />',
    );

    expect($html)
        ->toContain('77 912 34 56')
        ->toContain('cursor-not-allowed')
        ->not->toContain('phoneInput(');
});

it('shows a dash when readonly and phone value is empty', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\']" country-value="SN" phone-value="" :readonly="true" />',
    );

    expect($html)->toContain('—');
});

it('includes wire model attributes when provided', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\',\'CI\']" country-model="myCountry" phone-model="myPhone" />',
    );

    // Country model is passed to the Alpine factory (synced via $wire.set in selectCountry).
    // Phone model is bound via wire:model on the phone input.
    expect($html)
        ->toContain("countryModel: 'myCountry'")
        ->toContain('wire:model="myPhone"');
});

it('passes format strings to the Alpine phoneInput factory', function (): void {
    $html = Blade::render(
        '<x-phone-input :countries="[\'SN\',\'FR\']" country-value="SN" />',
    );

    expect($html)
        ->toContain('+221 XX XXX XX XX')
        ->toContain('+33 X XX XX XX XX');
});

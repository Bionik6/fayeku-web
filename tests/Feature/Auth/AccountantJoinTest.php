<?php

use Illuminate\Testing\TestResponse;

function validPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'mamadou@diallo-compta.sn',
        'country_code' => 'SN',
        'phone' => '771234567',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Centraliser les factures de mes clients PME.',
    ], $overrides);
}

function postJoin(array $payload): TestResponse
{
    return test()->post(route('marketing.accountants.join.store'), $payload);
}

test('GET /accountant/join renders the form', function () {
    $this->get(route('marketing.accountants.join'))
        ->assertOk()
        ->assertSee('Vous êtes un cabinet d\'expertise comptable', false)
        ->assertSee('Prénom')
        ->assertSee('Email')
        ->assertSee('attendez-vous de Fayeku');
});

test('POST with valid data redirects with success message', function () {
    postJoin(validPayload())
        ->assertRedirect(route('marketing.accountants.join'))
        ->assertSessionHas('success');
});

test('POST without first_name fails validation', function () {
    postJoin(validPayload(['first_name' => '']))
        ->assertSessionHasErrors(['first_name']);
});

test('POST without last_name fails validation', function () {
    postJoin(validPayload(['last_name' => '']))
        ->assertSessionHasErrors(['last_name']);
});

test('POST without firm fails validation', function () {
    postJoin(validPayload(['firm' => '']))
        ->assertSessionHasErrors(['firm']);
});

test('POST without email fails validation', function () {
    postJoin(validPayload(['email' => '']))
        ->assertSessionHasErrors(['email']);
});

test('POST with invalid email fails validation', function () {
    postJoin(validPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors(['email']);
});

test('POST without phone fails validation', function () {
    postJoin(validPayload(['phone' => '']))
        ->assertSessionHasErrors(['phone']);
});

test('POST without region fails validation', function () {
    postJoin(validPayload(['region' => '']))
        ->assertSessionHasErrors(['region']);
});

test('POST with invalid region fails validation', function () {
    postJoin(validPayload(['region' => 'Paris']))
        ->assertSessionHasErrors(['region']);
});

test('POST without portfolio_size fails validation', function () {
    postJoin(validPayload(['portfolio_size' => '']))
        ->assertSessionHasErrors(['portfolio_size']);
});

test('POST with invalid portfolio_size fails validation', function () {
    postJoin(validPayload(['portfolio_size' => '999 dossiers']))
        ->assertSessionHasErrors(['portfolio_size']);
});

test('POST without message fails validation', function () {
    postJoin(validPayload(['message' => '']))
        ->assertSessionHasErrors(['message']);
});

test('POST with message too short fails validation', function () {
    postJoin(validPayload(['message' => 'Court']))
        ->assertSessionHasErrors(['message']);
});

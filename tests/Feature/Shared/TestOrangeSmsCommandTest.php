<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.orange_sms.client_id' => null,
        'services.orange_sms.client_secret' => null,
        'services.orange_sms.sender_address' => null,
    ]);
});

test('echoue quand les credentials Orange manquent', function () {
    $this->artisan('orange:test-sms', ['phone' => '+221770000000'])
        ->expectsOutputToContain('Credentials manquants dans .env : ORANGE_SMS_CLIENT_ID, ORANGE_SMS_CLIENT_SECRET, ORANGE_SMS_SENDER_ADDRESS')
        ->assertExitCode(1);
});

test('echoue avec --otp si OTP_CHANNEL n\'est pas "sms"', function () {
    config([
        'services.orange_sms.client_id' => 'cid',
        'services.orange_sms.client_secret' => 'csecret',
        'services.orange_sms.sender_address' => 'tel:+2210000',
        'fayeku.otp_channel' => 'whatsapp',
    ]);

    $this->artisan('orange:test-sms', ['phone' => '+221770000000', '--otp' => true])
        ->expectsOutputToContain('OTP_CHANNEL doit valoir "sms"')
        ->assertExitCode(1);

    expect(DB::table('otp_codes')->count())->toBe(0);
});

test('avec --otp et OTP_CHANNEL=sms, genere un OTP et le persiste', function () {
    config([
        'services.orange_sms.client_id' => 'cid',
        'services.orange_sms.client_secret' => 'csecret',
        'services.orange_sms.sender_address' => 'tel:+2210000',
        'fayeku.otp_channel' => 'sms',
        'fayeku.otp_expiry_minutes' => 10,
    ]);

    $this->artisan('orange:test-sms', ['phone' => '+221771112233', '--otp' => true])
        ->expectsOutputToContain('OTP genere et stocke dans otp_codes')
        ->assertExitCode(0);

    $row = DB::table('otp_codes')->where('identifier', '+221771112233')->first();

    expect($row)->not->toBeNull()
        ->and($row->purpose)->toBe('verification')
        ->and($row->used_at)->toBeNull()
        ->and((int) $row->attempts)->toBe(0);
});

test('sans --otp, echoue en environnement testing car le provider actif est FakeSmsProvider', function () {
    config([
        'services.orange_sms.client_id' => 'cid',
        'services.orange_sms.client_secret' => 'csecret',
        'services.orange_sms.sender_address' => 'tel:+2210000',
    ]);

    $this->artisan('orange:test-sms', ['phone' => '+221770000000'])
        ->expectsOutputToContain('FakeSmsProvider')
        ->assertExitCode(1);
});

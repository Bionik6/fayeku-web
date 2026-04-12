<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Auth\Company;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createUnsetupSme(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Amadou Diallo',
        'setup_completed_at' => null,
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

test('company setup page renders for sme with incomplete setup', function () {
    ['user' => $user] = createUnsetupSme();

    $this->actingAs($user)
        ->get(route('auth.company-setup'))
        ->assertOk();
});

test('company setup redirects to dashboard if already complete', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => now()]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('auth.company-setup'))
        ->assertRedirect(route('pme.dashboard'));
});

test('guest cannot access company setup page', function () {
    $this->get(route('auth.company-setup'))
        ->assertRedirect(route('login'));
});

test('unverified phone cannot access company setup page', function () {
    $user = User::factory()->unverified()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('auth.company-setup'))
        ->assertRedirect(route('auth.otp'));
});

test('sme can complete company setup', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    $response = $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'Ma Société SARL',
            'sector' => 'Commerce général',
            'ninea' => '1234567890',
            'rccm' => 'SN-DKR-2024-B-12345',
        ]);

    $response->assertRedirect(route('pme.dashboard'));
    expect($company->fresh()->name)->toBe('Ma Société SARL');
    expect($company->fresh()->sector)->toBe('Commerce général');
    expect($company->fresh()->ninea)->toBe('1234567890');
    expect($company->fresh()->rccm)->toBe('SN-DKR-2024-B-12345');
    expect($company->fresh()->setup_completed_at)->not->toBeNull();
});

test('company setup fails without required fields', function () {
    ['user' => $user] = createUnsetupSme();

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [])
        ->assertSessionHasErrors(['company_name', 'sector']);
});

test('company setup succeeds without optional ninea and rccm', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'Ma Société',
            'sector' => 'Services',
        ])
        ->assertRedirect(route('pme.dashboard'));

    expect($company->fresh()->ninea)->toBeNull();
    expect($company->fresh()->rccm)->toBeNull();
});

test('double submission of company setup is idempotent', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'First Name',
            'sector' => 'BTP',
        ]);

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'Second Name',
            'sector' => 'Commerce',
        ])
        ->assertRedirect(route('pme.dashboard'));

    expect($company->fresh()->name)->toBe('First Name');
});

test('otp verification redirects sme without company setup to company setup page', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => null]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    createOtpCode('+221771234567', '123456');

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('auth.company-setup'));
});

test('otp verification redirects sme with completed setup to pme dashboard', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => now()]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    createOtpCode('+221771234567', '123456');

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('pme.dashboard'));
});

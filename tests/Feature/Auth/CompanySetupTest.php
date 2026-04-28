<?php

use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('company setup page does NOT pre-populate the name field when no session hint is set', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    // Even though the temp Company.name is "Amadou Diallo" (= user's full name set
    // by AuthService::register as a placeholder), the form must NOT pre-fill it.
    $content = $this->actingAs($user)
        ->get(route('auth.company-setup'))
        ->assertOk()
        ->assertDontSee('value="'.$company->name.'"', false)
        ->getContent();

    // The company_name input renders with empty value="".
    expect($content)->toMatch('/<input[^>]*name="company_name"[^>]*value=""/s');
});

test('company setup page pre-populates the name from invitee_company_name session (cabinet-typed invitations)', function () {
    ['user' => $user] = createUnsetupSme();

    $this->actingAs($user)
        ->withSession(['invitee_company_name' => 'Transport Ngor SARL'])
        ->get(route('auth.company-setup'))
        ->assertOk()
        ->assertSee('value="Transport Ngor SARL"', false);
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
        ->assertRedirect(route('sme.auth.otp'));
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

test('completing company setup backfills the related PartnerInvitation with the chosen company name', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    // Synthetic referral invitation: invitee_company_name is null (no leak of the
    // user's full name) until setup completes.
    $firm = Company::factory()->accountantFirm()->create();
    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'sync-test-token',
        'invitee_company_name' => null,
        'invitee_name' => 'Amadou Diallo',
        'invitee_phone' => $user->phone,
        'recommended_plan' => 'essentiel',
        'channel' => 'link',
        'status' => 'registering',
        'sme_company_id' => $company->id,
        'expires_at' => now()->addDays(30),
        'link_opened_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'Diallo Bâtiment SARL',
            'sector' => 'BTP',
        ])->assertRedirect(route('pme.dashboard'));

    expect($invitation->fresh()->invitee_company_name)->toBe('Diallo Bâtiment SARL');
});

test('completing company setup does NOT overwrite an already-set invitee_company_name on the invitation', function () {
    ['user' => $user, 'company' => $company] = createUnsetupSme();

    // Cabinet-typed invitation already carries a meaningful company name.
    $firm = Company::factory()->accountantFirm()->create();
    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'preset-token',
        'invitee_company_name' => 'Transport Ngor SARL',
        'invitee_name' => 'Amadou Diallo',
        'invitee_phone' => $user->phone,
        'recommended_plan' => 'essentiel',
        'channel' => 'whatsapp',
        'status' => 'registering',
        'sme_company_id' => $company->id,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->post(route('auth.company-setup.submit'), [
            'company_name' => 'Different SARL',
            'sector' => 'BTP',
        ])->assertRedirect(route('pme.dashboard'));

    // Cabinet's reference name is preserved; only null entries get backfilled.
    expect($invitation->fresh()->invitee_company_name)->toBe('Transport Ngor SARL');
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
        ->post(route('sme.auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('auth.company-setup'));
});

test('otp verification redirects sme with completed setup to pme dashboard', function () {
    $user = User::factory()->unverified()->create(['phone' => '+221771234567', 'profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme', 'setup_completed_at' => now()]);
    $company->users()->attach($user->id, ['role' => 'owner']);
    createOtpCode('+221771234567', '123456');

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('pme.dashboard'));
});

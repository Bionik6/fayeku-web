<?php

use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use App\Services\Compta\InvitationService;
use App\Services\Shared\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createInvitation(array $overrides = []): array
{
    $firm = Company::factory()->accountantFirm()->create(['name' => 'Cabinet Test']);
    $user = User::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $invitation = PartnerInvitation::create(array_merge([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'test-landing-token',
        'invitee_company_name' => 'PME Invitée SARL',
        'invitee_name' => 'Moussa Diallo',
        'invitee_phone' => '+221770000099',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'expires_at' => now()->addDays(30),
    ], $overrides));

    return compact('firm', 'user', 'invitation');
}

// ─── Registration pré-remplie ────────────────────────────────────────────────

test('le formulaire d\'inscription est pré-rempli avec les données d\'invitation', function () {
    ['invitation' => $invitation] = createInvitation();

    $this->get(route('sme.auth.register', ['join' => $invitation->token]))
        ->assertOk()
        ->assertSee('Cabinet Test')
        ->assertSee('PME Invitée SARL')
        ->assertSee('Moussa')
        ->assertSee('Diallo');
});

test('le formulaire pré-remplit le numéro de téléphone', function () {
    ['invitation' => $invitation] = createInvitation(['invitee_phone' => '+221770000099']);

    // The phone component formats 770000099 (SN) as "77 000 00 99"
    $this->get(route('sme.auth.register', ['join' => $invitation->token]))
        ->assertOk()
        ->assertSee('77 000 00 99');
});

test('le formulaire masque le sélecteur de profil quand invitation présente', function () {
    ['invitation' => $invitation] = createInvitation();

    $response = $this->get(route('sme.auth.register', ['join' => $invitation->token]));

    $response->assertOk();
    $response->assertDontSee('Cabinet d\'expertise comptable');
});

// ─── Inscription via invitation ──────────────────────────────────────────────

test('inscription via invitation crée la subscription avec le plan recommandé et invited_by_firm_id', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation();

    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ])->assertRedirect(route('sme.auth.otp'));

    $subscription = Subscription::first();
    expect($subscription)->not->toBeNull();
    expect($subscription->plan_slug)->toBe('essentiel');
    expect($subscription->invited_by_firm_id)->toBe($firm->id);
});

test('inscription via invitation crée la relation AccountantCompany', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation();

    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ]);

    $accountantCompany = AccountantCompany::first();
    expect($accountantCompany)->not->toBeNull();
    expect($accountantCompany->accountant_firm_id)->toBe($firm->id);
    expect($accountantCompany->started_at)->not->toBeNull();
});

test('inscription via invitation met le statut à registering', function () {
    ['invitation' => $invitation] = createInvitation();

    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ]);

    $invitation->refresh();
    expect($invitation->status)->toBe('registering');
    expect($invitation->sme_company_id)->not->toBeNull();
});

test('inscription via invitation définit le plan de la company', function () {
    ['invitation' => $invitation] = createInvitation();

    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ]);

    $company = Company::where('type', 'sme')->first();
    expect($company->plan)->toBe('essentiel');
});

test('inscription sans invitation reste sur le plan basique', function () {
    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Amadou',
        'last_name' => 'Ba',
        'phone' => '770000011',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'Ma PME SARL',
    ]);

    $subscription = Subscription::first();
    expect($subscription->plan_slug)->toBe('basique');
    expect($subscription->invited_by_firm_id)->toBeNull();
});

test('inscription avec token invalide échoue', function () {
    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '770000022',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'Test Co',
        'invitation_token' => 'token-invalide',
    ])->assertSessionHasErrors('invitation_token');
});

// ─── OTP finalisation ────────────────────────────────────────────────────────

test('vérification OTP finalise le statut de l\'invitation à accepted', function () {
    ['invitation' => $invitation] = createInvitation();

    // Register via invitation
    $this->post(route('sme.auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ]);

    $invitation->refresh();
    expect($invitation->status)->toBe('registering');

    // Simulate OTP verification
    $user = User::where('phone', '+221770000099')->first();
    $otpService = app(OtpService::class);

    // Get OTP from database
    $otp = DB::table('otp_codes')
        ->where('phone', '+221770000099')
        ->latest()
        ->first();

    // We need to use the actual OTP - let's generate a fresh one and verify it
    $otpService->generate('+221770000099');
    $otpCode = DB::table('otp_codes')
        ->where('phone', '+221770000099')
        ->latest()
        ->first();

    // Since OTP is hashed, we need to mock the verify or use a known approach
    // Let's test by directly calling the controller with a mocked OtpService
    $mockOtpService = Mockery::mock(OtpService::class);
    $mockOtpService->shouldReceive('verify')->andReturn(true);
    app()->instance(OtpService::class, $mockOtpService);

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221770000099', 'invitation_token' => $invitation->token])
        ->post(route('sme.auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('auth.company-setup'));

    $invitation->refresh();
    expect($invitation->status)->toBe('accepted');
    expect($invitation->accepted_at)->not->toBeNull();
});

// ─── Nouveau flux /join/{invite_code} ───────────────────────────────────────

test('/join/{code} redirige vers register et stocke le code en session', function () {
    $firm = Company::factory()->accountantFirm()->create(['invite_code' => 'ABC123']);

    $this->get(route('join.landing', 'ABC123'))
        ->assertRedirect(route('sme.auth.register'))
        ->assertSessionHas('joining_firm_code', 'ABC123');
});

test('/join/{code} est insensible à la casse', function () {
    $firm = Company::factory()->accountantFirm()->create(['invite_code' => 'ABC123']);

    $this->get(route('join.landing', 'abc123'))
        ->assertRedirect(route('sme.auth.register'));
});

test('/join/{code} invalide retourne 404', function () {
    $this->get(route('join.landing', 'XXXXXX'))
        ->assertNotFound();
});

test('/join/{code} redirige vers le dashboard si déjà connecté (PME)', function () {
    $firm = Company::factory()->accountantFirm()->create(['invite_code' => 'FRM001']);
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('join.landing', 'FRM001'))
        ->assertRedirect(route('pme.dashboard'));
});

test('le formulaire affiche la bannière du cabinet via joining_firm_code', function () {
    $firm = Company::factory()->accountantFirm()->create([
        'invite_code' => 'TST001',
        'name' => 'Cabinet Test Join',
    ]);

    $this->withSession(['joining_firm_code' => 'TST001'])
        ->get(route('sme.auth.register'))
        ->assertOk()
        ->assertSee('Cabinet Test Join');
});

test('inscription via /join/{code} avec invitation existante crée la relation', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation([
        'invitee_phone' => '+221770000099',
        'recommended_plan' => 'essentiel',
    ]);

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('sme.auth.register.submit'), [
            'first_name' => 'Ibrahima',
            'last_name' => 'Ciss',
            'phone' => '770000099',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'company_name' => 'Khalil Soft',
        ])->assertRedirect(route('sme.auth.otp'));

    $subscription = Subscription::first();
    expect($subscription->plan_slug)->toBe('essentiel');
    expect($subscription->invited_by_firm_id)->toBe($firm->id);

    $invitation->refresh();
    expect($invitation->status)->toBe('registering');
});

test('inscription via /join/{code} sans invitation existante lie quand même au cabinet', function () {
    $firm = Company::factory()->accountantFirm()->create(['invite_code' => 'NOINV1']);

    $this->withSession(['joining_firm_code' => 'NOINV1'])
        ->post(route('sme.auth.register.submit'), [
            'first_name' => 'Omar',
            'last_name' => 'Sy',
            'phone' => '770000077',
            'password' => 'P@ssword123!',
            'password_confirmation' => 'P@ssword123!',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'company_name' => 'Omar Co',
        ])->assertRedirect(route('sme.auth.otp'));

    $subscription = Subscription::first();
    expect($subscription->invited_by_firm_id)->toBe($firm->id);

    $accountantCompany = AccountantCompany::first();
    expect($accountantCompany->accountant_firm_id)->toBe($firm->id);
});

test('le message WhatsApp composé utilise /join/{invite_code}', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation();

    $message = app(InvitationService::class)->composeWhatsAppMessage($invitation);

    expect($message)->toContain('/join/'.$firm->invite_code);
});

test('buildWhatsAppLink contient le numéro et le lien encodé', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation([
        'invitee_phone' => '+221770000099',
    ]);

    $link = app(InvitationService::class)->buildWhatsAppLink($invitation);

    expect($link)
        ->toStartWith('https://wa.me/221770000099?text=')
        ->toContain(rawurlencode('/join/'.$firm->invite_code));
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Shared\Models\User;
use Modules\Shared\Services\OtpService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createInvitation(array $overrides = []): array
{
    $firm = Company::factory()->accountantFirm()->create(['name' => 'Cabinet Test']);
    $user = User::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $invitation = PartnerInvitation::create(array_merge([
        'accountant_firm_id' => $firm->id,
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

// ─── Landing route ───────────────────────────────────────────────────────────

test('invitation landing redirige vers register avec le token', function () {
    ['invitation' => $invitation] = createInvitation();

    $this->get(route('invitation.landing', $invitation->token))
        ->assertRedirect(route('auth.register', ['join' => $invitation->token]));
});

test('invitation landing met à jour link_opened_at', function () {
    ['invitation' => $invitation] = createInvitation();

    expect($invitation->link_opened_at)->toBeNull();

    $this->get(route('invitation.landing', $invitation->token));

    $invitation->refresh();
    expect($invitation->link_opened_at)->not->toBeNull();
});

test('invitation landing ne met à jour link_opened_at qu\'une seule fois', function () {
    $openedAt = now()->subHour();
    ['invitation' => $invitation] = createInvitation(['link_opened_at' => $openedAt]);

    $this->get(route('invitation.landing', $invitation->token));

    $invitation->refresh();
    expect($invitation->link_opened_at->timestamp)->toBe($openedAt->timestamp);
});

test('invitation expirée affiche la vue expirée', function () {
    ['invitation' => $invitation] = createInvitation([
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('invitation.landing', $invitation->token))
        ->assertOk()
        ->assertSee(__('Invitation expirée'));
});

test('invitation déjà acceptée redirige vers login', function () {
    ['invitation' => $invitation] = createInvitation([
        'status' => 'accepted',
        'accepted_at' => now(),
    ]);

    $this->get(route('invitation.landing', $invitation->token))
        ->assertRedirect(route('login'));
});

test('token invalide retourne 404', function () {
    $this->get('/invite/token-qui-nexiste-pas')
        ->assertNotFound();
});

test('invitation redirige vers login si le numéro existe déjà', function () {
    User::factory()->create(['phone' => '+221770000099']);

    ['invitation' => $invitation] = createInvitation(['invitee_phone' => '+221770000099']);

    $this->get(route('invitation.landing', $invitation->token))
        ->assertRedirect(route('login'));
});

test('invitation affiche le message déjà inscrit si le numéro existe', function () {
    User::factory()->create(['phone' => '+221770000099']);

    ['invitation' => $invitation] = createInvitation(['invitee_phone' => '+221770000099']);

    $this->get(route('invitation.landing', $invitation->token))
        ->assertRedirect(route('login'));

    $this->followingRedirects()
        ->get(route('invitation.landing', $invitation->token))
        ->assertSee(__('Vous êtes déjà inscrit sur Fayeku. Veuillez vous connecter.'));
});

// ─── Registration pré-remplie ────────────────────────────────────────────────

test('le formulaire d\'inscription est pré-rempli avec les données d\'invitation', function () {
    ['invitation' => $invitation] = createInvitation();

    $this->get(route('auth.register', ['join' => $invitation->token]))
        ->assertOk()
        ->assertSee('Cabinet Test')
        ->assertSee('PME Invitée SARL')
        ->assertSee('Moussa')
        ->assertSee('Diallo');
});

test('le formulaire pré-remplit le numéro de téléphone', function () {
    ['invitation' => $invitation] = createInvitation(['invitee_phone' => '+221770000099']);

    // The phone component formats 770000099 (SN) as "77 000 00 99"
    $this->get(route('auth.register', ['join' => $invitation->token]))
        ->assertOk()
        ->assertSee('77 000 00 99');
});

test('le formulaire masque le sélecteur de profil quand invitation présente', function () {
    ['invitation' => $invitation] = createInvitation();

    $response = $this->get(route('auth.register', ['join' => $invitation->token]));

    $response->assertOk();
    $response->assertDontSee('Cabinet d\'expertise comptable');
});

// ─── Inscription via invitation ──────────────────────────────────────────────

test('inscription via invitation crée la subscription avec le plan recommandé et invited_by_firm_id', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation();

    $this->post(route('auth.register.submit'), [
        'first_name' => 'Moussa',
        'last_name' => 'Diallo',
        'phone' => '770000099',
        'password' => 'P@ssword123!',
        'password_confirmation' => 'P@ssword123!',
        'profile_type' => 'sme',
        'country_code' => 'SN',
        'company_name' => 'PME Invitée SARL',
        'invitation_token' => $invitation->token,
    ])->assertRedirect(route('auth.otp'));

    $subscription = Subscription::first();
    expect($subscription)->not->toBeNull();
    expect($subscription->plan_slug)->toBe('essentiel');
    expect($subscription->invited_by_firm_id)->toBe($firm->id);
});

test('inscription via invitation crée la relation AccountantCompany', function () {
    ['firm' => $firm, 'invitation' => $invitation] = createInvitation();

    $this->post(route('auth.register.submit'), [
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

    $this->post(route('auth.register.submit'), [
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

    $this->post(route('auth.register.submit'), [
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
    $this->post(route('auth.register.submit'), [
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
    $this->post(route('auth.register.submit'), [
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
    $this->post(route('auth.register.submit'), [
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
        ->post(route('auth.otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('pme.dashboard'));

    $invitation->refresh();
    expect($invitation->status)->toBe('accepted');
    expect($invitation->accepted_at)->not->toBeNull();
});

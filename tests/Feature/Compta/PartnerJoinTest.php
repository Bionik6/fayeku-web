<?php

use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── GET /join/{code} ─────────────────────────────────────────────────────────

test('GET /join/{code} stocke le code en session et redirige vers le formulaire d\'inscription', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $this->get(route('join.landing', ['code' => $firm->invite_code]))
        ->assertRedirect(route('register'))
        ->assertSessionHas('joining_firm_code', $firm->invite_code);
});

test('GET /join/{code} accepte le code en minuscules (lookup insensible à la casse)', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $this->get(route('join.landing', ['code' => strtolower($firm->invite_code)]))
        ->assertRedirect(route('register'))
        ->assertSessionHas('joining_firm_code', $firm->invite_code);
});

test('GET /join/{code} renvoie 404 si le code n\'existe pas', function () {
    $this->get(route('join.landing', ['code' => 'NOPE99']))
        ->assertNotFound();
});

test('GET /join/{code} ignore les compagnies qui ne sont pas des cabinets', function () {
    $sme = Company::factory()->create(['type' => 'sme']);
    DB::table('companies')
        ->where('id', $sme->id)
        ->update(['invite_code' => 'SMECOD']);

    $this->get(route('join.landing', ['code' => 'SMECOD']))
        ->assertNotFound();
});

test('GET /join/{code} redirige un SME déjà connecté vers son tableau de bord', function () {
    $firm = Company::factory()->accountantFirm()->create();
    $sme = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($sme)
        ->get(route('join.landing', ['code' => $firm->invite_code]))
        ->assertRedirect(route('pme.dashboard'))
        ->assertSessionMissing('joining_firm_code');
});

test('GET /join/{code} redirige un comptable déjà connecté vers son tableau de bord', function () {
    $firm = Company::factory()->accountantFirm()->create();
    $accountant = User::factory()->accountantFirm()->create();

    $this->actingAs($accountant)
        ->get(route('join.landing', ['code' => $firm->invite_code]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('joining_firm_code');
});

// ─── GET /sme/register avec joining_firm_code en session ──────────────────────

test('GET /register expose le cabinet quand joining_firm_code est en session', function () {
    $firm = Company::factory()->accountantFirm()->create(['name' => 'Cabinet Ndiaye Conseil']);

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->get(route('register'))
        ->assertOk()
        ->assertViewHas('joiningFirm', fn ($f) => $f?->id === $firm->id)
        ->assertViewHas('invitation', null)
        ->assertSee('Cabinet Ndiaye Conseil')
        ->assertSee('vous recommande Fayeku')
        ->assertDontSee('Cette page est réservée aux PME');
});

test('GET /register sans session ni token n\'expose pas de cabinet et montre la disclaimer PME', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertViewHas('joiningFirm', null)
        ->assertViewHas('invitation', null)
        ->assertSee('Cette page est réservée aux PME');
});

test('le middleware web est appliqué sur /join/{code} (sinon la session ne persiste pas)', function () {
    $route = collect(app('router')->getRoutes()->getRoutesByName())
        ->get('join.landing');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('web');
});

test('flow réel : /join/{code} -> /register affiche le banner du cabinet (session persiste via web middleware)', function () {
    $firm = Company::factory()->accountantFirm()->create(['name' => 'Cabinet Ndiaye Conseil']);

    // Étape 1 : visite du lien d'invitation
    $this->get(route('join.landing', ['code' => $firm->invite_code]))
        ->assertRedirect(route('register'))
        ->assertSessionHas('joining_firm_code', $firm->invite_code);

    // Étape 2 : la session continue sur la page d'inscription
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Cabinet Ndiaye Conseil')
        ->assertSee('vous recommande Fayeku')
        ->assertDontSee('Cette page est réservée aux PME');
});

// ─── POST /sme/register : flow complet de création ────────────────────────────

test('POST /sme/register crée le user, la company SME, la subscription et la liaison cabinet', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('register.submit'), [
            'first_name' => 'Aïssatou',
            'last_name' => 'Diop',
            'phone' => '770000123',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'));

    $user = User::where('phone', '+221770000123')->first();
    expect($user)->not->toBeNull();
    expect($user->profile_type)->toBe('sme');
    expect($user->first_name)->toBe('Aïssatou');

    $smeCompany = Company::where('type', 'sme')->where('name', 'Aïssatou Diop')->first();
    expect($smeCompany)->not->toBeNull();

    expect(AccountantCompany::query()
        ->where('accountant_firm_id', $firm->id)
        ->where('sme_company_id', $smeCompany->id)
        ->exists())->toBeTrue();

    $subscription = Subscription::where('company_id', $smeCompany->id)->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->invited_by_firm_id)->toBe($firm->id);
    expect($subscription->status)->toBe('trial');
});

test('POST /sme/register sans invitation tombe sur le plan basique par défaut', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('register.submit'), [
            'first_name' => 'Pape',
            'last_name' => 'Ndiaye',
            'phone' => '770000999',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'));

    $smeCompany = Company::where('type', 'sme')->where('name', 'Pape Ndiaye')->first();
    $subscription = Subscription::where('company_id', $smeCompany->id)->first();
    expect($subscription->plan_slug)->toBe('basique');
});

test('POST /sme/register vide joining_firm_code de la session après inscription', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('register.submit'), [
            'first_name' => 'Moussa',
            'last_name' => 'Sarr',
            'phone' => '770000456',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'))
        ->assertSessionMissing('joining_firm_code');
});

test('POST /sme/register sans joining_firm_code ni token ne crée pas de liaison cabinet', function () {
    $this->post(route('register.submit'), [
        'first_name' => 'Solo',
        'last_name' => 'Faye',
        'phone' => '770000111',
        'country_code' => 'SN',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertRedirect(route('sme.auth.otp'));

    expect(AccountantCompany::count())->toBe(0);

    $smeCompany = Company::where('type', 'sme')->where('name', 'Solo Faye')->first();
    $subscription = Subscription::where('company_id', $smeCompany->id)->first();
    expect($subscription->invited_by_firm_id)->toBeNull();
    expect($subscription->plan_slug)->toBe('basique');
});

// ─── POST /sme/register : matching automatique d'une PartnerInvitation ───────

test('POST /sme/register associe automatiquement une PartnerInvitation existante quand le téléphone correspond', function () {
    $firm = Company::factory()->accountantFirm()->create();
    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'auto-match-token',
        'invitee_phone' => '+221770000789',
        'invitee_name' => 'Khady',
        'invitee_company_name' => 'Khady SARL',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'expires_at' => now()->addDays(20),
    ]);

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('register.submit'), [
            'first_name' => 'Khady',
            'last_name' => 'Mbaye',
            'phone' => '770000789',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'));

    $invitation->refresh();
    expect($invitation->status)->toBe('registering');
    expect($invitation->sme_company_id)->not->toBeNull();

    $subscription = Subscription::where('company_id', $invitation->sme_company_id)->first();
    expect($subscription->plan_slug)->toBe('essentiel');
    expect($subscription->invited_by_firm_id)->toBe($firm->id);
});

test('POST /sme/register lie le cabinet sans toucher à une PartnerInvitation d\'un autre numéro', function () {
    $firm = Company::factory()->accountantFirm()->create();
    $other = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'other-token',
        'invitee_phone' => '+221770000222',
        'invitee_name' => 'Autre',
        'recommended_plan' => 'basique',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'expires_at' => now()->addDays(20),
    ]);

    $this->withSession(['joining_firm_code' => $firm->invite_code])
        ->post(route('register.submit'), [
            'first_name' => 'Direct',
            'last_name' => 'Jonction',
            'phone' => '770000333',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'));

    $other->refresh();
    expect($other->status)->toBe('pending');
    expect($other->sme_company_id)->toBeNull();

    $smeCompany = Company::where('type', 'sme')->where('name', 'Direct Jonction')->first();
    expect(AccountantCompany::query()
        ->where('accountant_firm_id', $firm->id)
        ->where('sme_company_id', $smeCompany->id)
        ->exists())->toBeTrue();
});

// ─── End-to-end : du clic sur le lien jusqu'à l'inscription ──────────────────

test('flow complet : GET /join/{code} → POST /sme/register lie le SME au cabinet', function () {
    $firm = Company::factory()->accountantFirm()->create();

    $session = $this->get(route('join.landing', ['code' => $firm->invite_code]))
        ->assertRedirect(route('register'));

    $code = $session->getSession()->get('joining_firm_code');
    expect($code)->toBe($firm->invite_code);

    $this->withSession(['joining_firm_code' => $code])
        ->get(route('register'))
        ->assertOk()
        ->assertViewHas('joiningFirm', fn ($f) => $f?->id === $firm->id);

    $this->withSession(['joining_firm_code' => $code])
        ->post(route('register.submit'), [
            'first_name' => 'Bineta',
            'last_name' => 'Lo',
            'phone' => '770000555',
            'country_code' => 'SN',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('sme.auth.otp'));

    $smeCompany = Company::where('type', 'sme')->where('name', 'Bineta Lo')->first();
    expect($smeCompany)->not->toBeNull();
    expect(AccountantCompany::query()
        ->where('accountant_firm_id', $firm->id)
        ->where('sme_company_id', $smeCompany->id)
        ->exists())->toBeTrue();
});

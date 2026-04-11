<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Partnership\Services\InvitationService;
use Modules\Shared\Interfaces\WhatsAppProviderInterface;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function invTestCreateFirm(int $smeCount = 0): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smes = [];
    for ($i = 0; $i < $smeCount; $i++) {
        $sme = Company::factory()->create();
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);
        $smes[] = $sme;
    }

    return compact('user', 'firm', 'smes');
}

// ─── Accès & rendu ────────────────────────────────────────────────────────────

test('la page invitations est accessible pour un utilisateur authentifié', function () {
    ['user' => $user] = invTestCreateFirm();

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertSuccessful();
});

test('la page invitations redirige un utilisateur non authentifié', function () {
    $this->get(route('invitations.index'))
        ->assertRedirect(route('login'));
});

test('le composant invitations se rend sans erreur pour un user sans cabinet', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->assertOk();
});

test('le composant invitations affiche les sections principales', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->assertOk()
        ->assertSee('Invitations & activations')
        ->assertSee('Suivi des invitations')
        ->assertSee('Inviter une PME');
});

test('la section développer votre portefeuille est visible', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->assertSee('Développez votre portefeuille partenaire')
        ->assertSee('Partager votre lien partenaire')
        ->assertSee('Invitations en attente');
});

test('la section revenus partenaire affiche le lien vers les commissions', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->assertSee('Revenus partenaire ce mois-ci')
        ->assertSee('Voir mes commissions');
});

// ─── KPIs ────────────────────────────────────────────────────────────────────

test('les KPIs affichent les bons compteurs', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-1',
        'invitee_company_name' => 'Test SARL',
        'invitee_name' => 'Test Contact',
        'invitee_phone' => '+221770000001',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-2',
        'invitee_company_name' => 'Activated Co',
        'invitee_name' => 'Contact 2',
        'invitee_phone' => '+221770000002',
        'status' => 'accepted',
        'accepted_at' => now(),
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index');

    expect($component->get('totalSent'))->toBe(2);
    expect($component->get('pendingCount'))->toBe(1);
    expect($component->get('activatedThisMonth'))->toBe(1);
    expect($component->get('conversionRate'))->toBe(50);
});

// ─── Pont vers Commissions ────────────────────────────────────────────────────

test('commissionMonthTotal retourne le total des commissions du mois en cours', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = invTestCreateFirm(1);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 15_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 8_000,
        'period_month' => now()->subMonth()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index');

    expect($component->get('commissionMonthTotal'))->toBe(15_000);
});

test('commissionMonthTotal vaut 0 sans commissions ce mois', function () {
    ['user' => $user] = invTestCreateFirm();

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index');

    expect($component->get('commissionMonthTotal'))->toBe(0);
});

// ─── Événement invitation-sent ────────────────────────────────────────────────

test('onInvitationSent rafraîchit la liste des invitations', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index');

    expect($component->get('totalSent'))->toBe(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'new-token',
        'invitee_company_name' => 'New Co',
        'invitee_name' => 'New Contact',
        'invitee_phone' => '+221770000010',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component->dispatch('invitation-sent');

    expect($component->get('totalSent'))->toBe(1);
    expect($component->get('pendingCount'))->toBe(1);
});

// ─── Filtres ─────────────────────────────────────────────────────────────────

test('le filtre non ouverts fonctionne', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-a',
        'invitee_company_name' => 'Not Opened Co',
        'invitee_name' => 'Contact A',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-b',
        'invitee_company_name' => 'Opened Co',
        'invitee_name' => 'Contact B',
        'status' => 'pending',
        'link_opened_at' => now(),
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('setFilter', 'not_opened');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Not Opened Co');
});

test('le filtre activés fonctionne', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-c',
        'invitee_company_name' => 'Active Co',
        'invitee_name' => 'Contact C',
        'status' => 'accepted',
        'accepted_at' => now(),
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-d',
        'invitee_company_name' => 'Pending Co',
        'invitee_name' => 'Contact D',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('setFilter', 'activated');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Active Co');
});

test('le filtre à relancer affiche les invitations de plus de 2 jours', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    // Ancienne invitation (> 2 jours) → doit apparaître
    $old = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-old',
        'invitee_company_name' => 'Old Pending Co',
        'invitee_name' => 'Contact Old',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);
    DB::table('partner_invitations')
        ->where('id', $old->id)
        ->update(['created_at' => now()->subDays(5)]);

    // Invitation récente (< 2 jours) → ne doit PAS apparaître
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-new',
        'invitee_company_name' => 'New Pending Co',
        'invitee_name' => 'Contact New',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('setFilter', 'to_remind');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Old Pending Co');
});

// ─── Recherche ───────────────────────────────────────────────────────────────

test('la recherche filtre par nom d\'entreprise', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-e',
        'invitee_company_name' => 'Dakar Import',
        'invitee_name' => 'Contact E',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-f',
        'invitee_company_name' => 'Thiès Export',
        'invitee_name' => 'Contact F',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->set('inviteSearch', 'Dakar');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Dakar Import');
});

// ─── Relance ────────────────────────────────────────────────────────────────

test('remindInvitation incrémente le compteur de relances', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'remind-token',
        'invitee_company_name' => 'Remind Co',
        'invitee_name' => 'Contact',
        'invitee_phone' => '+221770000001',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'reminder_count' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('remindInvitation', $invitation->id);

    $invitation->refresh();
    expect($invitation->reminder_count)->toBe(1);
    expect($invitation->last_reminder_at)->not->toBeNull();
});

// ─── Renvoi invitation expirée ──────────────────────────────────────────────

test('resendInvitation réinitialise une invitation expirée', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'expired-token',
        'invitee_company_name' => 'Expired Co',
        'invitee_name' => 'Contact',
        'invitee_phone' => '+221770000001',
        'status' => 'expired',
        'channel' => 'whatsapp',
        'reminder_count' => 3,
    ]);

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('resendInvitation', $invitation->id);

    $invitation->refresh();
    expect($invitation->status)->toBe('pending');
    expect($invitation->reminder_count)->toBe(0);
    expect($invitation->expires_at)->not->toBeNull();
});

// ─── WhatsApp ──────────────────────────────────────────────────────────────

test('le message essentiel contient la promotion 2 mois offerts', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'test-token-promo',
        'invitee_company_name' => 'Promo Co',
        'invitee_name' => 'Awa Ndiaye',
        'invitee_phone' => '+221770000077',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $mock = Mockery::mock(WhatsAppProviderInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $phone, string $msg) => str_contains($msg, '2 mois offerts'))
        ->andReturn(true);
    app()->instance(WhatsAppProviderInterface::class, $mock);

    $service = app(InvitationService::class);
    $result = $service->sendInvitationMessage($invitation);

    expect($result)->toBeTrue();
});

test('le message basique ne contient pas la promotion', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'test-token-basic',
        'invitee_company_name' => 'Basic Co',
        'invitee_name' => 'Omar Ba',
        'invitee_phone' => '+221770000066',
        'recommended_plan' => 'basique',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $mock = Mockery::mock(WhatsAppProviderInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $phone, string $msg) => ! str_contains($msg, '2 mois offerts')
            && str_contains($msg, 'Omar Ba')
        )
        ->andReturn(true);
    app()->instance(WhatsAppProviderInterface::class, $mock);

    $service = app(InvitationService::class);
    $result = $service->sendInvitationMessage($invitation);

    expect($result)->toBeTrue();
});

test('remindInvitation envoie un message de rappel WhatsApp', function () {
    $mock = Mockery::mock(WhatsAppProviderInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $phone, string $msg) => str_contains($msg, 'rappel'))
        ->andReturn(true);
    app()->instance(WhatsAppProviderInterface::class, $mock);

    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'remind-wa-token',
        'invitee_company_name' => 'Remind WA Co',
        'invitee_name' => 'Contact WA',
        'invitee_phone' => '+221770000055',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'reminder_count' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('remindInvitation', $invitation->id)
        ->assertDispatched('toast', type: 'success');
});

test('resendInvitation envoie un message WhatsApp', function () {
    $mock = Mockery::mock(WhatsAppProviderInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $phone, string $msg) => str_contains($msg, '/join/'))
        ->andReturn(true);
    app()->instance(WhatsAppProviderInterface::class, $mock);

    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'resend-wa-token',
        'invitee_company_name' => 'Resend WA Co',
        'invitee_name' => 'Contact Resend',
        'invitee_phone' => '+221770000044',
        'status' => 'expired',
        'channel' => 'whatsapp',
        'reminder_count' => 3,
    ]);

    Livewire::actingAs($user)
        ->test('pages::invitations.index')
        ->call('resendInvitation', $invitation->id)
        ->assertDispatched('toast', type: 'success');
});

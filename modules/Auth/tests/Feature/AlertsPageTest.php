<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Accès & rendu ────────────────────────────────────────────────────────────

test('la page alertes est accessible pour un utilisateur authentifié', function () {
    ['user' => $user] = createFirmWithSmes(0);

    $this->actingAs($user)
        ->get(route('alerts.index'))
        ->assertSuccessful();
});

test('la page alertes redirige un utilisateur non authentifié', function () {
    $this->get(route('alerts.index'))
        ->assertRedirect(route('login'));
});

test('le composant alertes se rend sans erreur pour un user sans cabinet', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::alerts.index')
        ->assertOk();
});

test('le composant alertes se rend sans erreur pour un cabinet sans client', function () {
    ['user' => $user] = createFirmWithSmes(0);

    Livewire::actingAs($user)
        ->test('pages::alerts.index')
        ->assertOk()
        ->assertSee('Alertes');
});

// ─── Alertes critiques ────────────────────────────────────────────────────────

test('une facture en retard > 60 jours génère une alerte critique', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'total' => 500_000,
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(1);
});

test('plusieurs factures critiques génèrent autant d\'alertes critiques', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(2);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(70), 'amount_paid' => 0]);
    createInvoice($smes[1], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(61), 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(2);
});

test('une facture en retard < 60 jours ne génère pas d\'alerte critique', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(30),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(0);
});

// ─── Alertes en veille ────────────────────────────────────────────────────────

test('un client sans facture ce mois génère une alerte en veille', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'watch'))->toHaveCount(1);
});

test('un client avec une facture récente ne génère pas d\'alerte en veille', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(5)]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'watch'))->toHaveCount(0);
});

// ─── Alertes nouvelles inscriptions ──────────────────────────────────────────

test('une invitation acceptée dans les 7 jours génère une alerte nouvelle', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234567',
        'invitee_name' => 'Dakar Pharma',
        'recommended_plan' => 'essentiel',
        'status' => 'accepted',
        'accepted_at' => now()->subDays(2),
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'new'))->toHaveCount(1);
});

test('une invitation acceptée il y a plus de 7 jours ne génère pas d\'alerte nouvelle', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234567',
        'invitee_name' => 'Vieille Pharma',
        'status' => 'accepted',
        'accepted_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'new'))->toHaveCount(0);
});

// ─── Filtre ───────────────────────────────────────────────────────────────────

test('le filtre "critical" ne retourne que les alertes critiques', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'amount_paid' => 0]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234568',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::alerts.index', ['filter' => 'critical']);
    $alerts = $component->get('alerts');

    expect(collect($alerts)->pluck('type')->unique()->values()->toArray())->toEqual(['critical']);
});

test('le filtre "watch" ne retourne que les alertes en veille', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234569',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::alerts.index', ['filter' => 'watch']);
    $alerts = $component->get('alerts');

    expect(collect($alerts)->pluck('type')->unique()->values()->toArray())->toEqual(['watch']);
});

test('le filtre "new" ne retourne que les nouvelles inscriptions', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'amount_paid' => 0]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234570',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::alerts.index', ['filter' => 'new']);
    $alerts = $component->get('alerts');

    expect(collect($alerts)->pluck('type')->unique()->values()->toArray())->toEqual(['new']);
});

test('le filtre "all" retourne toutes les alertes', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'amount_paid' => 0]);
    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234571',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::alerts.index', ['filter' => 'all']);
    $alerts = $component->get('alerts');

    $types = collect($alerts)->pluck('type')->unique()->sort()->values()->toArray();
    expect($types)->toContain('critical')
        ->and($types)->toContain('new');
});

// ─── Compteurs ────────────────────────────────────────────────────────────────

test('counts retourne le bon nombre d\'alertes par type', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(2);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'amount_paid' => 0]);
    createInvoice($smes[1], ['issued_at' => now()->subDays(45)]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234572',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');
    $counts = $component->get('counts');

    expect($counts['critical'])->toBe(1)
        ->and($counts['watch'])->toBeGreaterThanOrEqual(1)
        ->and($counts['new'])->toBe(1)
        ->and($counts['all'])->toBeGreaterThanOrEqual(3);
});

// ─── Changement de filtre via wire:click ─────────────────────────────────────

test('$set filter met à jour les alertes affichées', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'amount_paid' => 0]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234573',
        'invitee_name' => 'Nouvelle PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::alerts.index');

    $component->set('filter', 'critical');
    expect(collect($component->get('alerts'))->where('type', 'new'))->toHaveCount(0);

    $component->set('filter', 'new');
    expect(collect($component->get('alerts'))->where('type', 'critical'))->toHaveCount(0);

    $component->set('filter', 'all');
    $types = collect($component->get('alerts'))->pluck('type')->unique();
    expect($types)->toContain('critical')->and($types)->toContain('new');
});

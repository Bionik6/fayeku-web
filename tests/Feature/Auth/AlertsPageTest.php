<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Compta\PartnerInvitation;
use App\Models\Compta\DismissedAlert;
use App\Enums\PME\InvoiceStatus;
use App\Models\Shared\User;

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
    $user = User::factory()->accountantFirm()->create();

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertOk();
});

test('le composant alertes se rend sans erreur pour un cabinet sans client', function () {
    ['user' => $user] = createFirmWithSmes(0);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertOk()
        ->assertSee('Alertes');
});

test('la page affiche le nouveau copy du hero et des filtres', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertSee('1 alerte active · 1 critique à traiter')
        ->assertSee('1 critique à traiter')
        ->assertSee('Filtrer les alertes')
        ->assertSee('À surveiller')
        ->assertDontSee('Filtrer par criticité')
        ->assertDontSee('En veille');
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

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(1);
});

test('plusieurs factures critiques sur des entreprises différentes génèrent une alerte par entreprise', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(2);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(70), 'amount_paid' => 0]);
    createInvoice($smes[1], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(61), 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(2);
});

test('plusieurs factures critiques sur la même entreprise génèrent une seule alerte groupée', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(70), 'total' => 200_000, 'amount_paid' => 0]);
    createInvoice($smes[0], ['status' => InvoiceStatus::Overdue->value, 'due_at' => now()->subDays(65), 'total' => 300_000, 'amount_paid' => 0]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');
    $criticals = collect($alerts)->where('type', 'critical');

    expect($criticals)->toHaveCount(1);

    $alert = $criticals->first();
    expect($alert['alert_key'])->toBe('critical_'.$smes[0]->id);
    expect($alert['subtitle'])->toContain('2 factures impayées');
    expect($alert['subtitle'])->toContain('500 000');
});

test('une facture en retard < 60 jours ne génère pas d\'alerte critique', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(30),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'critical'))->toHaveCount(0);
});

// ─── Alertes en veille ────────────────────────────────────────────────────────

test('un client sans facture ce mois génère une alerte en veille', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(45)]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'watch'))->toHaveCount(1);
});

test('une alerte à surveiller utilise le nouveau copy narratif', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->startOfDay()->subDays(45)]);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertSee('Inactif depuis')
        ->assertSee('Dernier contact il y a')
        ->assertSee('jours')
        ->assertSee('À surveiller')
        ->assertSee('Actions')
        ->assertDontSee('En veille');
});

test('un client avec une facture récente ne génère pas d\'alerte en veille', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], ['issued_at' => now()->subDays(5)]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
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

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    expect(collect($alerts)->where('type', 'new'))->toHaveCount(1);
});

test('une nouvelle inscription utilise le copy harmonisé', function () {
    ['user' => $user, 'firm' => $firm] = createFirmWithSmes(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234568',
        'invitee_name' => 'Bâ Industries',
        'recommended_plan' => 'essentiel',
        'status' => 'accepted',
        'accepted_at' => now()->subDays(2),
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertSee('Bâ Industries · Nouvelle inscription')
        ->assertSee('Via votre lien partenaire · Offre Essentiel · Essai 2 mois');
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

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
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
        ->test('pages::compta.alerts.index', ['filter' => 'critical']);
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
        ->test('pages::compta.alerts.index', ['filter' => 'watch']);
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
        ->test('pages::compta.alerts.index', ['filter' => 'new']);
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
        ->test('pages::compta.alerts.index', ['filter' => 'all']);
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

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
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

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');

    $component->set('filter', 'critical');
    expect(collect($component->get('alerts'))->where('type', 'new'))->toHaveCount(0);

    $component->set('filter', 'new');
    expect(collect($component->get('alerts'))->where('type', 'critical'))->toHaveCount(0);

    $component->set('filter', 'all');
    $types = collect($component->get('alerts'))->pluck('type')->unique();
    expect($types)->toContain('critical')->and($types)->toContain('new');
});

test('setFilter() désactive showDismissed et applique le filtre', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->set('showDismissed', true);

    expect($component->get('showDismissed'))->toBeTrue();

    $component->call('setFilter', 'critical');

    expect($component->get('showDismissed'))->toBeFalse()
        ->and($component->get('filter'))->toBe('critical');
});

// ─── Dismiss / Undismiss ──────────────────────────────────────────────────────

test('dismiss() crée un enregistrement DismissedAlert et masque l\'alerte', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $alertKey = 'critical_'.$smes[0]->id;

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');

    expect(collect($component->get('alerts'))->where('alert_key', $alertKey))->toHaveCount(1);

    $component->call('dismiss', $alertKey);

    expect(DismissedAlert::where('user_id', $user->id)->where('alert_key', $alertKey)->exists())->toBeTrue()
        ->and(collect($component->get('alerts'))->where('alert_key', $alertKey))->toHaveCount(0);
});

test('dismiss() est idempotent pour la même alerte', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $alertKey = 'critical_'.$smes[0]->id;

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->call('dismiss', $alertKey);
    $component->call('dismiss', $alertKey);

    expect(DismissedAlert::where('user_id', $user->id)->where('alert_key', $alertKey)->count())->toBe(1);
});

test('undismiss() supprime l\'enregistrement et restaure l\'alerte', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $alertKey = 'critical_'.$smes[0]->id;

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => $alertKey,
        'dismissed_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');

    expect(collect($component->get('alerts'))->where('alert_key', $alertKey))->toHaveCount(0);

    $component->call('undismiss', $alertKey);

    expect(DismissedAlert::where('user_id', $user->id)->where('alert_key', $alertKey)->exists())->toBeFalse()
        ->and(collect($component->get('alerts'))->where('alert_key', $alertKey))->toHaveCount(1);
});

test('counts() inclut le nombre d\'alertes archivées', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => 'critical_'.$smes[0]->id,
        'dismissed_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $counts = $component->get('counts');

    expect($counts['dismissed'])->toBe(1)
        ->and($counts['critical'])->toBe(0);
});

// ─── showDismissed ────────────────────────────────────────────────────────────

test('showDismissed=true affiche uniquement les alertes archivées avec le flag dismissed', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $alertKey = 'critical_'.$smes[0]->id;

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => $alertKey,
        'dismissed_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->set('showDismissed', true);

    $alerts = $component->get('alerts');

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['alert_key'])->toBe($alertKey)
        ->and($alerts[0]['dismissed'])->toBeTrue();
});

test('une alerte critique conserve le menu actions et son copy secondaire', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->assertSee('Actions')
        ->assertSee('Voir le client')
        ->assertSee('Marquer comme traité')
        ->assertDontSee('Marquer comme vu')
        ->assertDontSee('Relancer')
        ->assertDontSee('Contacter');
});

test('showDismissed=true retourne une liste vide quand aucune alerte n\'est archivée', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->set('showDismissed', true);

    expect($component->get('alerts'))->toHaveCount(0);
});

// ─── Modale facture ───────────────────────────────────────────────────────────

test('viewInvoice() définit selectedInvoiceId', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');

    expect($component->get('selectedInvoiceId'))->toBeNull();

    $component->call('viewInvoice', $invoice->id);

    expect($component->get('selectedInvoiceId'))->toBe($invoice->id);
});

test('closeInvoice() réinitialise selectedInvoiceId à null', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->call('viewInvoice', $invoice->id);
    $component->call('closeInvoice');

    expect($component->get('selectedInvoiceId'))->toBeNull();
});

test('selectedInvoice retourne la facture correspondante quand un ID est sélectionné', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $component->call('viewInvoice', $invoice->id);

    expect($component->get('selectedInvoice'))->not->toBeNull()
        ->and($component->get('selectedInvoice')->id)->toBe($invoice->id);
});

test('la modale facture sur alertes réutilise le copy partagé de la fiche client', function () {
    ['user' => $user, 'smes' => $smes] = createFirmWithSmes(1);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.alerts.index')
        ->call('viewInvoice', $invoice->id)
        ->assertSee('Échéance le')
        ->assertSee('Détail des prestations')
        ->assertSee('Récapitulatif');
});

// ─── alert_key ────────────────────────────────────────────────────────────────

test('chaque alerte contient une alert_key unique selon son type', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = createFirmWithSmes(2);

    $invoice = createInvoice($smes[0], [
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now()->subDays(90),
        'due_at' => now()->subDays(65),
        'amount_paid' => 0,
    ]);
    createInvoice($smes[1], ['issued_at' => now()->subDays(45)]);
    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => fake()->uuid(),
        'invitee_phone' => '+221701234580',
        'invitee_name' => 'Test PME',
        'status' => 'accepted',
        'accepted_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::compta.alerts.index');
    $alerts = $component->get('alerts');

    $keys = collect($alerts)->pluck('alert_key');

    expect($keys->unique())->toHaveCount($keys->count());

    $criticalAlert = collect($alerts)->firstWhere('type', 'critical');
    $watchAlert = collect($alerts)->firstWhere('type', 'watch');
    $newAlert = collect($alerts)->firstWhere('type', 'new');

    expect($criticalAlert['alert_key'])->toStartWith('critical_')
        ->and($watchAlert['alert_key'])->toStartWith('watch_')
        ->and($newAlert['alert_key'])->toStartWith('new_');
});

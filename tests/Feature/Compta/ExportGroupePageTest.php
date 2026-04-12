<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Enums\Compta\ExportFormat;
use App\Models\Compta\ExportHistory;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function exportTestCreateFirm(int $smeCount = 3): array
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

function exportTestCreateInvoice(Company $sme, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 100_000,
    ], $overrides)));
}

// ─── Accès & rendu ────────────────────────────────────────────────────────────

test('la page export est accessible pour un utilisateur authentifié', function () {
    ['user' => $user] = exportTestCreateFirm(0);

    $this->actingAs($user)
        ->get(route('export.index'))
        ->assertSuccessful();
});

test('la page export redirige un utilisateur non authentifié', function () {
    $this->get(route('export.index'))
        ->assertRedirect(route('login'));
});

test('le composant export se rend sans erreur pour un user sans cabinet', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->assertOk();
});

test('le composant export affiche les trois sections', function () {
    ['user' => $user] = exportTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->assertOk()
        ->assertSee('Historique des exports')
        ->assertSee('Retrouvez les derniers exports générés pour votre cabinet.')
        ->assertSee('Export par client')
        ->assertSee('Plan de comptes');
});

test('la page affiche le nouveau copy métier export', function () {
    ['user' => $user] = exportTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->assertSee('Export groupé')
        ->assertSee('Exportez les écritures de plusieurs clients sur une période donnée')
        ->assertSee('client éligible')
        ->assertSee('Période sélectionnée :')
        ->assertSee('Lancer un export')
        ->assertSee('Rechercher une entreprise...')
        ->assertSee('Comptes utilisés pour générer les écritures du fichier exporté.');
});

// ─── Historique ───────────────────────────────────────────────────────────────

test('l\'historique affiche les exports passés', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = exportTestCreateFirm(2);

    ExportHistory::create([
        'firm_id' => $firm->id,
        'user_id' => $user->id,
        'period' => '2026-03',
        'format' => 'sage100',
        'scope' => 'all',
        'client_ids' => [$smes[0]->id, $smes[1]->id],
        'clients_count' => 2,
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->assertSee('2026-03')
        ->assertSee('Sage 100')
        ->assertSee('Lancé par')
        ->assertSee('Statut')
        ->assertSee('Indisponible')
        ->assertSee('Terminé');
});

// ─── Clients ──────────────────────────────────────────────────────────────────

test('la liste des clients affiche les SMEs actives', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(2);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.export.index');

    $component->assertSee($smes[0]->name)
        ->assertSee($smes[1]->name);
});

test('la recherche filtre les clients', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(2);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->set('searchClient', $smes[0]->name)
        ->assertSee($smes[0]->name)
        ->assertDontSee($smes[1]->name);
});

// ─── Export modal ─────────────────────────────────────────────────────────────

test('mountExportModal initialise les valeurs par défaut', function () {
    ['user' => $user] = exportTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->call('mountExportModal')
        ->assertSet('exportPeriod', now()->format('Y-m'))
        ->assertSet('exportFormat', 'excel')
        ->assertSet('clientSelection', 'all')
        ->assertSet('selectedClientIds', []);
});

test('exportClient pré-remplit la modale avec le client sélectionné', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->call('exportClient', $smes[0]->id)
        ->assertSet('clientSelection', 'manual')
        ->assertSet('selectedClientIds', [$smes[0]->id])
        ->assertSee('Périmètre clients')
        ->assertSee('Tous les clients éligibles')
        ->assertSee('1 client sélectionné');
});

test('toggleClient ajoute et retire un client', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(2);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->set('clientSelection', 'manual')
        ->call('toggleClient', $smes[0]->id)
        ->assertSet('selectedClientIds', [$smes[0]->id]);

    $component->call('toggleClient', $smes[0]->id)
        ->assertSet('selectedClientIds', []);
});

test('toggleAllClients sélectionne puis désélectionne tous les clients', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(3);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->set('clientSelection', 'manual')
        ->call('toggleAllClients');

    $selectedIds = $component->get('selectedClientIds');
    expect($selectedIds)->toHaveCount(3);

    $component->call('toggleAllClients')
        ->assertSet('selectedClientIds', []);
});

// ─── generateExport ───────────────────────────────────────────────────────────

test('generateExport crée un enregistrement ExportHistory pour le format Excel', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = exportTestCreateFirm(2);

    exportTestCreateInvoice($smes[0], ['issued_at' => now()]);
    exportTestCreateInvoice($smes[1], ['issued_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->call('mountExportModal')
        ->set('exportFormat', 'excel')
        ->call('generateExport');

    expect(ExportHistory::where('firm_id', $firm->id)->count())->toBe(1);

    $history = ExportHistory::where('firm_id', $firm->id)->first();
    expect($history->format)->toBe(ExportFormat::Excel);
    expect($history->scope)->toBe('all');
    expect($history->clients_count)->toBe(2);
});

test('generateExport ne crée pas d\'historique pour les formats non disponibles', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = exportTestCreateFirm(1);

    exportTestCreateInvoice($smes[0], ['issued_at' => now()]);

    foreach (['sage100', 'ebp'] as $format) {
        Livewire::actingAs($user)
            ->test('pages::compta.export.index')
            ->call('mountExportModal')
            ->set('exportFormat', $format)
            ->call('generateExport');
    }

    expect(ExportHistory::where('firm_id', $firm->id)->count())->toBe(0);
});

test('generateExport en mode manuel utilise seulement les clients sélectionnés', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = exportTestCreateFirm(3);

    exportTestCreateInvoice($smes[0], ['issued_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->call('mountExportModal')
        ->set('exportFormat', 'excel')
        ->set('clientSelection', 'manual')
        ->set('selectedClientIds', [$smes[0]->id])
        ->call('generateExport');

    $history = ExportHistory::where('firm_id', $firm->id)->first();
    expect($history->scope)->toBe('manual');
    expect($history->clients_count)->toBe(1);
    expect($history->client_ids)->toBe([$smes[0]->id]);
});

test('generateExport ne crée rien si pas de période', function () {
    ['user' => $user, 'firm' => $firm] = exportTestCreateFirm(1);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->set('exportPeriod', '')
        ->call('generateExport');

    expect(ExportHistory::where('firm_id', $firm->id)->count())->toBe(0);
});

// ─── exportInvoiceCount ───────────────────────────────────────────────────────

test('exportInvoiceCount compte les factures pour la période', function () {
    ['user' => $user, 'smes' => $smes] = exportTestCreateFirm(1);

    exportTestCreateInvoice($smes[0], ['issued_at' => now()->startOfMonth()]);
    exportTestCreateInvoice($smes[0], ['issued_at' => now()->subYear()]);

    $count = Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->call('mountExportModal')
        ->get('exportInvoiceCount');

    expect($count)->toBe(1);
});

// ─── Plan de comptes ──────────────────────────────────────────────────────────

test('le plan de comptes affiche les codes comptables', function () {
    ['user' => $user] = exportTestCreateFirm(0);

    Livewire::actingAs($user)
        ->test('pages::compta.export.index')
        ->assertSee('710000')
        ->assertSee('411000')
        ->assertSee('445710');
});

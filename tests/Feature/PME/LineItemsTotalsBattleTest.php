<?php

use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Proforma;
use App\Models\Shared\User;
use App\Services\PME\InvoiceService;
use App\Services\PME\ProformaService;
use App\Services\PME\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * BATTLE TEST — Calcul des totaux sur les lignes de devis / proformas / factures.
 *
 * Contrat invariant :  subtotal == sum(quantity[i] * unit_price[i]) pour toutes les lignes.
 *                      total    == discounted_subtotal + tax_amount.
 *
 * Aucun chemin (form, service, conversion, sauvegarde, rechargement) ne doit jamais
 * stocker un total désynchronisé des lignes qui le composent.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

function reportedScreenshotLines(): array
{
    return [
        ['description' => 'Cuisinière Astech 5 feux 90X60 à gaz avec four', 'quantity' => 1, 'unit_price' => 235_000],
        ['description' => 'Micro onde Astech 38 litres', 'quantity' => 1, 'unit_price' => 95_000],
        ['description' => 'Machine à laver Astech 7 Kg inverter', 'quantity' => 1, 'unit_price' => 175_000],
        ['description' => 'Bouilloire Electrique Astech 1.7 L 360°', 'quantity' => 1, 'unit_price' => 10_000],
        ['description' => 'Climatiseur Split Astech 12000BTU inverter + Wifi', 'quantity' => 5, 'unit_price' => 195_000],
        ['description' => 'Climatiseur Split Astech 18000BTU 2cv inverter + Wifi', 'quantity' => 3, 'unit_price' => 245_000],
        ['description' => 'Machine à café Astech avec capsule Nespresso', 'quantity' => 1, 'unit_price' => 60_000],
    ];
}

function expectedScreenshotSubtotal(): int
{
    return 235_000 + 95_000 + 175_000 + 10_000 + (5 * 195_000) + (3 * 245_000) + 60_000;
    // = 2 285 000 FCFA
}

function createSmeForBattle(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    return compact('user', 'company', 'client');
}

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 1 — Le scénario exact rapporté par l'utilisateur (screenshot)
// ════════════════════════════════════════════════════════════════════════════

test('REGRESSION SCREENSHOT — proforma 7 lignes Astech sans TVA = 2 285 000 FCFA', function () {
    $totals = (new ProformaService)->calculateProformaTotals(reportedScreenshotLines(), taxRate: 0);

    expect($totals['subtotal'])->toBe(2_285_000)
        ->and($totals['total'])->toBe(2_285_000)
        ->and($totals['tax_amount'])->toBe(0);
});

test('REGRESSION SCREENSHOT — devis 7 lignes Astech sans TVA = 2 285 000 FCFA', function () {
    $totals = (new QuoteService)->calculateQuoteTotals(reportedScreenshotLines(), taxRate: 0);

    expect($totals['subtotal'])->toBe(2_285_000)
        ->and($totals['total'])->toBe(2_285_000);
});

test('REGRESSION SCREENSHOT — facture 7 lignes Astech sans TVA = 2 285 000 FCFA', function () {
    $totals = (new InvoiceService)->calculateInvoiceTotals(reportedScreenshotLines(), taxRate: 0);

    expect($totals['subtotal'])->toBe(2_285_000)
        ->and($totals['total'])->toBe(2_285_000);
});

test('REGRESSION SCREENSHOT — proforma persiste 2 285 000 et le rechargement DB donne pareil', function () {
    ['company' => $company, 'client' => $client] = createSmeForBattle();

    $proforma = (new ProformaService)->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-COGBEW',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], reportedScreenshotLines());

    expect($proforma->subtotal)->toBe(2_285_000)
        ->and($proforma->total)->toBe(2_285_000);

    // Rechargement complet depuis la DB : invariant tenu
    $reloaded = Proforma::query()->with('lines')->find($proforma->id);
    $sumOfLines = $reloaded->lines->sum(fn ($l) => $l->quantity * $l->unit_price);

    expect($reloaded->subtotal)->toBe($sumOfLines)
        ->and($reloaded->total)->toBe($sumOfLines);
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 2 — Property-based : 200 jeux aléatoires, l'invariant doit tenir
// ════════════════════════════════════════════════════════════════════════════

test('PROPERTY — calculateProformaTotals respecte sum(qty × price) sur 200 jeux aléatoires', function () {
    $service = new ProformaService;

    for ($run = 0; $run < 200; $run++) {
        $lineCount = random_int(1, 12);
        $lines = [];
        $expected = 0;

        for ($i = 0; $i < $lineCount; $i++) {
            $qty = random_int(1, 50);
            $price = random_int(0, 9_999_999);
            $lines[] = ['quantity' => $qty, 'unit_price' => $price];
            $expected += $qty * $price;
        }

        $totals = $service->calculateProformaTotals($lines, taxRate: 0);

        expect($totals['subtotal'])->toBe($expected, "Subtotal incorrect pour le run $run");
        expect($totals['total'])->toBe($expected, "Total (sans TVA ni remise) incorrect pour le run $run");
    }
});

test('PROPERTY — calculateProformaTotals avec TVA 18% : tax = round(subtotal * 0.18)', function () {
    $service = new ProformaService;

    for ($run = 0; $run < 100; $run++) {
        $lineCount = random_int(1, 8);
        $lines = [];
        $subtotal = 0;

        for ($i = 0; $i < $lineCount; $i++) {
            $qty = random_int(1, 20);
            $price = random_int(100, 999_999);
            $lines[] = ['quantity' => $qty, 'unit_price' => $price];
            $subtotal += $qty * $price;
        }

        $expectedTax = (int) round($subtotal * 0.18);
        $totals = $service->calculateProformaTotals($lines, taxRate: 18);

        expect($totals['subtotal'])->toBe($subtotal);
        expect($totals['tax_amount'])->toBe($expectedTax);
        expect($totals['total'])->toBe($subtotal + $expectedTax);
    }
});

test('PROPERTY — remise % : discounted_subtotal = subtotal - round(subtotal × discount/100)', function () {
    $service = new ProformaService;

    foreach ([5, 10, 25, 50, 100] as $discount) {
        for ($run = 0; $run < 20; $run++) {
            $qty = random_int(1, 30);
            $price = random_int(100, 999_999);
            $subtotal = $qty * $price;
            $expectedDiscount = (int) round($subtotal * $discount / 100);
            $expectedDiscounted = $subtotal - $expectedDiscount;

            $totals = $service->calculateProformaTotals(
                [['quantity' => $qty, 'unit_price' => $price]],
                taxRate: 0,
                discount: $discount,
                discountType: 'percent'
            );

            expect($totals['subtotal'])->toBe($subtotal);
            expect($totals['discount_amount'])->toBe($expectedDiscount);
            expect($totals['discounted_subtotal'])->toBe($expectedDiscounted);
            expect($totals['total'])->toBe($expectedDiscounted);
        }
    }
});

test('PROPERTY — remise fixe ne peut pas dépasser le subtotal', function () {
    $service = new ProformaService;

    for ($run = 0; $run < 50; $run++) {
        $qty = random_int(1, 10);
        $price = random_int(1_000, 100_000);
        $subtotal = $qty * $price;
        $oversizedDiscount = $subtotal + random_int(1_000, 1_000_000);

        $totals = $service->calculateProformaTotals(
            [['quantity' => $qty, 'unit_price' => $price]],
            taxRate: 0,
            discount: $oversizedDiscount,
            discountType: 'fixed'
        );

        expect($totals['discount_amount'])->toBe($subtotal);
        expect($totals['total'])->toBe(0);
    }
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 3 — Edge cases : null, string, types impurs
// ════════════════════════════════════════════════════════════════════════════

dataset('impure_lines', [
    'qty en string' => [['quantity' => '5', 'unit_price' => 100], 500],
    'price en string' => [['quantity' => 5, 'unit_price' => '100'], 500],
    'les deux en string' => [['quantity' => '5', 'unit_price' => '100'], 500],
    'qty zéro' => [['quantity' => 0, 'unit_price' => 100_000], 0],
    'price zéro' => [['quantity' => 10, 'unit_price' => 0], 0],
    'qty et price zéro' => [['quantity' => 0, 'unit_price' => 0], 0],
    'price max int courant (9 999 999)' => [['quantity' => 1, 'unit_price' => 9_999_999], 9_999_999],
]);

test('calculateProformaTotals normalise les types impurs', function (array $line, int $expected) {
    $totals = (new ProformaService)->calculateProformaTotals([$line], taxRate: 0);

    expect($totals['subtotal'])->toBe($expected);
})->with('impure_lines');

test('calculateProformaTotals sur tableau vide retourne tout à zéro', function () {
    $totals = (new ProformaService)->calculateProformaTotals([], taxRate: 18, discount: 10, discountType: 'percent');

    expect($totals['subtotal'])->toBe(0)
        ->and($totals['discount_amount'])->toBe(0)
        ->and($totals['discounted_subtotal'])->toBe(0)
        ->and($totals['tax_amount'])->toBe(0)
        ->and($totals['total'])->toBe(0);
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 4 — Live form : le total affiché côté serveur reflète immédiatement
//           le tableau lines (le bug racine du screenshot)
// ════════════════════════════════════════════════════════════════════════════

test('LIVE FORM — proforma : changer lines.X.quantity met à jour computedTotals immédiatement', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('lines', [
            ['description' => 'Climatiseur', 'quantity' => 1, 'unit_price' => 245_000],
        ])
        ->set('taxMode', '0');

    expect($component->get('computedTotals')['subtotal'])->toBe(245_000);

    $component->set('lines.0.quantity', 3);
    expect($component->get('computedTotals')['subtotal'])->toBe(735_000);

    $component->set('lines.0.quantity', 10);
    expect($component->get('computedTotals')['subtotal'])->toBe(2_450_000);
});

test('LIVE FORM — proforma : ajouter une ligne reflète immédiatement dans le subtotal', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('lines', [['description' => 'A', 'quantity' => 1, 'unit_price' => 100_000]])
        ->set('taxMode', '0');

    expect($component->get('computedTotals')['subtotal'])->toBe(100_000);

    $component->call('addLine');
    $component->set('lines.1', ['description' => 'B', 'quantity' => 5, 'unit_price' => 50_000]);

    expect($component->get('computedTotals')['subtotal'])->toBe(350_000);
});

test('LIVE FORM — proforma : reproduire EXACTEMENT le scénario du screenshot via l\'UI', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', collect(reportedScreenshotLines())->map(fn ($l) => [
            'description' => 'Item',
            'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'],
        ])->all());

    expect($component->get('computedTotals')['subtotal'])->toBe(2_285_000)
        ->and($component->get('computedTotals')['total'])->toBe(2_285_000);
});

test('LIVE FORM — proforma : saveDraft persiste un total cohérent avec les lignes', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeForBattle();

    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', collect(reportedScreenshotLines())->map(fn ($l) => [
            'description' => 'Item',
            'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'],
        ])->all())
        ->call('saveDraft');

    $proforma = Proforma::query()->where('company_id', $company->id)->with('lines')->first();
    $sum = $proforma->lines->sum(fn ($l) => $l->quantity * $l->unit_price);

    expect($proforma->subtotal)->toBe(2_285_000)
        ->and($proforma->total)->toBe(2_285_000)
        ->and($sum)->toBe(2_285_000)
        ->and($proforma->subtotal)->toBe($sum);
});

test('LIVE FORM — proforma : rouvrir en édition après save donne les mêmes totaux', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeForBattle();

    // 1. Sauvegarde initiale
    Livewire::actingAs($user)
        ->test('pages::pme.proformas.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', collect(reportedScreenshotLines())->map(fn ($l) => [
            'description' => 'Item',
            'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'],
        ])->all())
        ->call('saveDraft');

    $proforma = Proforma::query()->where('company_id', $company->id)->first();

    // 2. Rouverture
    $reedit = Livewire::actingAs($user)
        ->test('pages::pme.proformas.form', ['proforma' => $proforma]);

    expect($reedit->get('computedTotals')['subtotal'])->toBe(2_285_000)
        ->and($reedit->get('computedTotals')['total'])->toBe(2_285_000);
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 5 — Symétrie avec le devis et la facture (même composant line-items)
// ════════════════════════════════════════════════════════════════════════════

test('LIVE FORM — devis : changer quantity met à jour computedTotals immédiatement', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', [['description' => 'X', 'quantity' => 1, 'unit_price' => 245_000]]);

    expect($component->get('computedTotals')['subtotal'])->toBe(245_000);

    $component->set('lines.0.quantity', 3);

    expect($component->get('computedTotals')['subtotal'])->toBe(735_000);
});

test('LIVE FORM — facture : changer quantity met à jour computedTotals immédiatement', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', [['description' => 'X', 'quantity' => 1, 'unit_price' => 245_000]]);

    expect($component->get('computedTotals')['subtotal'])->toBe(245_000);

    $component->set('lines.0.quantity', 3);

    expect($component->get('computedTotals')['subtotal'])->toBe(735_000);
});

test('LIVE FORM — devis et facture : screenshot scenario donne 2 285 000', function () {
    ['user' => $user, 'client' => $client] = createSmeForBattle();

    $lines = collect(reportedScreenshotLines())->map(fn ($l) => [
        'description' => 'Item',
        'quantity' => $l['quantity'],
        'unit_price' => $l['unit_price'],
    ])->all();

    $devis = Livewire::actingAs($user)
        ->test('pages::pme.quotes.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', $lines);

    $facture = Livewire::actingAs($user)
        ->test('pages::pme.invoices.form')
        ->set('clientId', $client->id)
        ->set('taxMode', '0')
        ->set('lines', $lines);

    expect($devis->get('computedTotals')['subtotal'])->toBe(2_285_000);
    expect($facture->get('computedTotals')['subtotal'])->toBe(2_285_000);
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 6 — Conversion proforma → facture conserve les totaux
// ════════════════════════════════════════════════════════════════════════════

test('CONVERSION — proforma → facture : invoice.total == proforma.total == sum(lignes)', function () {
    ['company' => $company, 'client' => $client] = createSmeForBattle();
    $service = new ProformaService;

    $proforma = $service->create($company, [
        'client_id' => $client->id,
        'reference' => 'FYK-PRO-CONV01',
        'currency' => 'XOF',
        'tax_rate' => 0,
        'discount' => 0,
        'discount_type' => 'percent',
        'issued_at' => now()->format('Y-m-d'),
        'valid_until' => now()->addDays(30)->format('Y-m-d'),
    ], reportedScreenshotLines());

    $invoice = $service->convertToInvoice($proforma, $company);
    $sumProforma = $proforma->fresh()->lines->sum(fn ($l) => $l->quantity * $l->unit_price);
    $sumInvoice = $invoice->lines->sum(fn ($l) => $l->quantity * $l->unit_price);

    expect($sumProforma)->toBe(2_285_000)
        ->and($sumInvoice)->toBe(2_285_000)
        ->and($invoice->subtotal)->toBe($proforma->subtotal)
        ->and($invoice->total)->toBe($proforma->total)
        ->and($invoice->total)->toBe($sumInvoice);
});

// ════════════════════════════════════════════════════════════════════════════
//  ZONE 7 — Symétrie inter-services : même input → même output
// ════════════════════════════════════════════════════════════════════════════

test('SYMÉTRIE — Quote, Proforma et Invoice services produisent le même résultat sur le screenshot', function () {
    $lines = reportedScreenshotLines();

    $proforma = (new ProformaService)->calculateProformaTotals($lines, taxRate: 18, discount: 5, discountType: 'percent');
    $quote = (new QuoteService)->calculateQuoteTotals($lines, taxRate: 18, discount: 5, discountType: 'percent');
    $invoice = (new InvoiceService)->calculateInvoiceTotals($lines, taxRate: 18, discount: 5, discountType: 'percent');

    foreach (['subtotal', 'discount_amount', 'discounted_subtotal', 'tax_amount', 'total'] as $key) {
        expect($proforma[$key])->toBe($quote[$key], "Quote vs Proforma diffèrent sur $key")
            ->and($proforma[$key])->toBe($invoice[$key], "Invoice vs Proforma diffèrent sur $key");
    }
});

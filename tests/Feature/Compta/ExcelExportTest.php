<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Enums\Compta\ExportFormat;
use App\Models\Compta\ExportHistory;
use App\Services\Compta\ExcelExporter;
use App\Services\Compta\ExportService;
use App\Models\PME\Client;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;
use App\Models\Shared\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function excelTestCreateFirm(int $smeCount = 1): array
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

function excelTestCreateInvoice(Company $sme, array $overrides = []): Invoice
{
    $client = Client::factory()->create(['company_id' => $sme->id]);

    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => $client->id,
        'reference' => 'FAC-'.fake()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ], $overrides)));
}

// ─── ExcelExporter ───────────────────────────────────────────────────────────

test('ExcelExporter génère un fichier xlsx valide', function () {
    ['smes' => $smes] = excelTestCreateFirm();

    $invoice = excelTestCreateInvoice($smes[0]);

    $exporter = new ExcelExporter;
    $filePath = $exporter->export(collect([$invoice]));

    expect($filePath)->toBeFile();
    expect(str_ends_with($filePath, '.xlsx'))->toBeTrue();

    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getTitle())->toBe('Écritures comptables');

    // Header row
    expect($sheet->getCell('A1')->getValue())->toBe('Date pièce');
    expect($sheet->getCell('D1')->getValue())->toBe('N° compte');
    expect($sheet->getCell('F1')->getValue())->toBe('Débit');
    expect($sheet->getCell('G1')->getValue())->toBe('Crédit');

    // Ligne 1 : Débit Client (TTC)
    expect($sheet->getCell('D2')->getValue())->toBe('411000');
    expect($sheet->getCell('F2')->getValue())->toEqual(1180);

    // Ligne 2 : Crédit Ventes (HT)
    expect($sheet->getCell('D3')->getValue())->toBe('710000');
    expect($sheet->getCell('G3')->getValue())->toEqual(1000);

    // Ligne 3 : Crédit TVA
    expect($sheet->getCell('D4')->getValue())->toBe('445710');
    expect($sheet->getCell('G4')->getValue())->toEqual(180);

    @unlink($filePath);
});

test('ExcelExporter omet la ligne TVA si tax_amount est 0', function () {
    ['smes' => $smes] = excelTestCreateFirm();

    $invoice = excelTestCreateInvoice($smes[0], [
        'subtotal' => 50_000,
        'tax_amount' => 0,
        'total' => 50_000,
    ]);

    $exporter = new ExcelExporter;
    $filePath = $exporter->export(collect([$invoice]));

    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Seulement 2 lignes de données + 1 header
    expect($sheet->getCell('D2')->getValue())->toBe('411000');
    expect($sheet->getCell('D3')->getValue())->toBe('710000');
    expect($sheet->getCell('D4')->getValue())->toBeNull();

    @unlink($filePath);
});

test('ExcelExporter retourne le bon mime type et filename', function () {
    $exporter = new ExcelExporter;

    expect($exporter->mimeType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($exporter->filename('2026-03'))->toStartWith('export-comptable-2026-03-');
    expect($exporter->filename('2026-03'))->toEndWith('.xlsx');
});

// ─── ExportService ───────────────────────────────────────────────────────────

test('ExportService::generate crée le fichier et met à jour file_path', function () {
    Storage::fake('local');
    ['firm' => $firm, 'smes' => $smes, 'user' => $user] = excelTestCreateFirm();

    excelTestCreateInvoice($smes[0]);

    $history = ExportHistory::create([
        'firm_id' => $firm->id,
        'user_id' => $user->id,
        'period' => now()->format('Y-m'),
        'format' => ExportFormat::Excel,
        'scope' => 'all',
        'client_ids' => [$smes[0]->id],
        'clients_count' => 1,
    ]);

    app(ExportService::class)->generate($history);

    $history->refresh();
    expect($history->file_path)->not->toBeNull();
    expect($history->file_path)->toStartWith('exports/');

    Storage::disk('local')->assertExists($history->file_path);
});

test('ExportService::fetchInvoices filtre par période mensuelle', function () {
    ['smes' => $smes] = excelTestCreateFirm();

    excelTestCreateInvoice($smes[0], ['issued_at' => now()->startOfMonth()]);
    excelTestCreateInvoice($smes[0], ['issued_at' => now()->subYear()]);

    $service = new ExportService;
    $invoices = $service->fetchInvoices([$smes[0]->id], now()->format('Y-m'));

    expect($invoices)->toHaveCount(1);
});

test('ExportService::fetchInvoices filtre par trimestre', function () {
    ['smes' => $smes] = excelTestCreateFirm();

    excelTestCreateInvoice($smes[0], ['issued_at' => now()->startOfYear()->addMonth()]);
    excelTestCreateInvoice($smes[0], ['issued_at' => now()->startOfYear()->addMonths(5)]);

    $service = new ExportService;
    $invoices = $service->fetchInvoices([$smes[0]->id], now()->year.'-T1');

    expect($invoices)->toHaveCount(1);
});

// ─── Download route ──────────────────────────────────────────────────────────

test('le téléchargement fonctionne pour un export avec fichier', function () {
    Storage::fake('local');
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = excelTestCreateFirm();

    excelTestCreateInvoice($smes[0]);

    $history = ExportHistory::create([
        'firm_id' => $firm->id,
        'user_id' => $user->id,
        'period' => now()->format('Y-m'),
        'format' => ExportFormat::Excel,
        'scope' => 'all',
        'client_ids' => [$smes[0]->id],
        'clients_count' => 1,
    ]);

    app(ExportService::class)->generate($history);
    $history->refresh();

    $this->actingAs($user)
        ->get(route('export.download', $history))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('le téléchargement est interdit pour un autre cabinet', function () {
    Storage::fake('local');
    ['firm' => $firm, 'smes' => $smes] = excelTestCreateFirm();

    $otherUser = User::factory()->accountantFirm()->create();
    $otherFirm = Company::factory()->accountantFirm()->create();
    $otherFirm->users()->attach($otherUser->id, ['role' => 'admin']);

    $history = ExportHistory::create([
        'firm_id' => $firm->id,
        'user_id' => $otherUser->id,
        'period' => now()->format('Y-m'),
        'format' => ExportFormat::Excel,
        'scope' => 'all',
        'client_ids' => [$smes[0]->id],
        'clients_count' => 1,
        'file_path' => 'exports/test.xlsx',
    ]);

    $this->actingAs($otherUser)
        ->get(route('export.download', $history))
        ->assertForbidden();
});

test('le téléchargement retourne 404 si pas de fichier', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = excelTestCreateFirm();

    $history = ExportHistory::create([
        'firm_id' => $firm->id,
        'user_id' => $user->id,
        'period' => now()->format('Y-m'),
        'format' => ExportFormat::Excel,
        'scope' => 'all',
        'client_ids' => [$smes[0]->id],
        'clients_count' => 1,
        'file_path' => null,
    ]);

    $this->actingAs($user)
        ->get(route('export.download', $history))
        ->assertNotFound();
});

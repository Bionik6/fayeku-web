<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Portfolio\Services\PortfolioService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeInvoiceStub(array $attributes = []): Invoice
{
    $invoice = new Invoice;
    foreach ($attributes as $key => $value) {
        $invoice->$key = $value;
    }

    return $invoice;
}

function makeAccountantWithSmes(int $count = 3): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smes = Company::factory()->count($count)->create(['type' => 'sme']);

    foreach ($smes as $sme) {
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);
    }

    return compact('user', 'firm', 'smes');
}

// ─── PortfolioService::clientStatus() ─────────────────────────────────────────

describe('PortfolioService::clientStatus()', function () {
    it('returns current when there are no invoices', function () {
        $service = new PortfolioService;

        expect($service->clientStatus(collect()))->toBe('current');
    });

    it('returns current when all invoices are paid', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Paid, 'due_at' => now()->subDays(5)]),
            makeInvoiceStub(['status' => InvoiceStatus::Paid, 'due_at' => now()->subDays(10)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('current');
    });

    it('returns current when all invoices are draft or sent but not yet due', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Draft, 'due_at' => now()->addDays(30)]),
            makeInvoiceStub(['status' => InvoiceStatus::Sent, 'due_at' => now()->addDays(15)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('current');
    });

    it('returns watch when an invoice is overdue within 59 days', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Overdue, 'due_at' => now()->subDays(30)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('watch');
    });

    it('returns watch when an invoice is partially paid', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::PartiallyPaid, 'due_at' => now()->subDays(5)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('watch');
    });

    it('returns critical when an overdue invoice exceeds 60 days', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Overdue, 'due_at' => now()->subDays(61)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('critical');
    });

    it('returns critical when one invoice is critical even with other attente invoices', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Overdue, 'due_at' => now()->subDays(61)]),
            makeInvoiceStub(['status' => InvoiceStatus::PartiallyPaid, 'due_at' => now()->subDays(10)]),
        ]);

        expect($service->clientStatus($invoices))->toBe('critical');
    });

    it('does not return critique for overdue invoice without due_at', function () {
        $service = new PortfolioService;
        $invoices = collect([
            makeInvoiceStub(['status' => InvoiceStatus::Overdue, 'due_at' => null]),
        ]);

        // No due_at → cannot determine 60-day threshold → falls to attente
        expect($service->clientStatus($invoices))->toBe('watch');
    });
});

// ─── Dashboard vs Clients consistency ─────────────────────────────────────────

describe('Dashboard vs Clients à_jour count consistency', function () {
    it('dashboard upToDateCount matches clients index statusCounts[current]', function () {
        ['user' => $user, 'smes' => $smes] = makeAccountantWithSmes(3);

        // SME 0: paid invoices only → current
        Invoice::factory()->forCompany($smes[0])->paid()->create();

        // SME 1: overdue within 30 days → watch
        Invoice::factory()->forCompany($smes[1])->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(20),
        ]);

        // SME 2: overdue > 60 days → critical
        Invoice::factory()->forCompany($smes[2])->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(90),
        ]);

        $dashboardComponent = Livewire::actingAs($user)->test('pages::dashboard.index');
        $clientsComponent = Livewire::actingAs($user)->test('pages::clients.index');

        expect($dashboardComponent->get('upToDateCount'))
            ->toBe($clientsComponent->get('statusCounts')['current']);
    });

    it('dashboard watchCount matches clients index statusCounts[attente]', function () {
        ['user' => $user, 'smes' => $smes] = makeAccountantWithSmes(2);

        // SME 0: partially paid → watch
        Invoice::factory()->forCompany($smes[0])->create([
            'status' => InvoiceStatus::PartiallyPaid,
            'due_at' => now()->subDays(5),
        ]);

        // SME 1: paid → current
        Invoice::factory()->forCompany($smes[1])->paid()->create();

        $dashboardComponent = Livewire::actingAs($user)->test('pages::dashboard.index');
        $clientsComponent = Livewire::actingAs($user)->test('pages::clients.index');

        expect($dashboardComponent->get('watchCount'))
            ->toBe($clientsComponent->get('statusCounts')['watch']);
    });

    it('dashboard criticalCount matches clients index statusCounts[critique]', function () {
        ['user' => $user, 'smes' => $smes] = makeAccountantWithSmes(2);

        // SME 0: overdue > 60 days → critical
        Invoice::factory()->forCompany($smes[0])->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(75),
        ]);

        // SME 1: paid → current
        Invoice::factory()->forCompany($smes[1])->paid()->create();

        $dashboardComponent = Livewire::actingAs($user)->test('pages::dashboard.index');
        $clientsComponent = Livewire::actingAs($user)->test('pages::clients.index');

        expect($dashboardComponent->get('criticalCount'))
            ->toBe($clientsComponent->get('statusCounts')['critical']);
    });

    it('client with no invoices is counted as a_jour on both dashboard and clients page', function () {
        ['user' => $user] = makeAccountantWithSmes(1);
        // No invoices created → current by default

        $dashboardComponent = Livewire::actingAs($user)->test('pages::dashboard.index');
        $clientsComponent = Livewire::actingAs($user)->test('pages::clients.index');

        expect($dashboardComponent->get('upToDateCount'))->toBe(1);
        expect($clientsComponent->get('statusCounts')['current'])->toBe(1);
    });

    it('all status counts are consistent between dashboard and clients page', function () {
        ['user' => $user, 'smes' => $smes] = makeAccountantWithSmes(4);

        // smes[0]: paid → current
        Invoice::factory()->forCompany($smes[0])->paid()->create();

        // smes[1]: overdue 20d → watch
        Invoice::factory()->forCompany($smes[1])->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(20),
        ]);

        // smes[2]: overdue 90d → critical
        Invoice::factory()->forCompany($smes[2])->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(90),
        ]);

        // smes[3]: no invoices → current

        $dashboardComponent = Livewire::actingAs($user)->test('pages::dashboard.index');
        $clientsComponent = Livewire::actingAs($user)->test('pages::clients.index');
        $statusCounts = $clientsComponent->get('statusCounts');

        expect($dashboardComponent->get('upToDateCount'))->toBe($statusCounts['current'])
            ->and($dashboardComponent->get('watchCount'))->toBe($statusCounts['watch'])
            ->and($dashboardComponent->get('criticalCount'))->toBe($statusCounts['critical'])
            ->and($dashboardComponent->get('upToDateCount') + $dashboardComponent->get('watchCount') + $dashboardComponent->get('criticalCount'))->toBe(4);
    });
});

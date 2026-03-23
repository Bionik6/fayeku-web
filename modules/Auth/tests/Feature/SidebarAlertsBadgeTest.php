<?php

use App\Livewire\Sidebar\AlertsBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Compta\Portfolio\Models\DismissedAlert;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

function createFirmForSidebarBadge(): array
{
    $user = User::factory()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $sme = Company::factory()->create();
    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $sme->id,
        'started_at' => now()->subMonths(3),
    ]);

    return compact('user', 'firm', 'sme');
}

function createSidebarBadgeInvoice(Company $sme, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $sme->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->numerify('###'),
        'status' => InvoiceStatus::Overdue->value,
        'issued_at' => now(),
        'due_at' => now()->subDays(65),
        'subtotal' => 100_000,
        'tax_amount' => 0,
        'total' => 100_000,
        'amount_paid' => 0,
    ], $overrides)));
}

test('le badge sidebar affiche le nombre d alertes actives', function () {
    ['user' => $user, 'sme' => $sme] = createFirmForSidebarBadge();

    createSidebarBadgeInvoice($sme);

    Livewire::actingAs($user)
        ->test(AlertsBadge::class)
        ->assertSet('count', 1)
        ->assertSee('1');
});

test('le badge sidebar se met a jour quand les alertes changent', function () {
    ['user' => $user, 'sme' => $sme] = createFirmForSidebarBadge();

    $invoice = createSidebarBadgeInvoice($sme);

    $component = Livewire::actingAs($user)
        ->test(AlertsBadge::class)
        ->assertSet('count', 1);

    DismissedAlert::create([
        'user_id' => $user->id,
        'alert_key' => 'critical_'.$invoice->id,
        'dismissed_at' => now(),
    ]);

    $component
        ->dispatch('alerts-updated')
        ->assertSet('count', 0);

    DismissedAlert::where('user_id', $user->id)
        ->where('alert_key', 'critical_'.$invoice->id)
        ->delete();

    $component
        ->dispatch('alerts-updated')
        ->assertSet('count', 1);
});

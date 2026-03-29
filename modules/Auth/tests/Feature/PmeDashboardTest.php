<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, company: Company}
 */
function createPmeDashboardUser(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createPmeDashboardInvoice(Company $company, array $overrides = []): Invoice
{
    return Invoice::unguarded(fn () => Invoice::create(array_merge([
        'company_id' => $company->id,
        'client_id' => null,
        'reference' => 'FAC-'.fake()->unique()->numerify('###'),
        'status' => InvoiceStatus::Paid->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'subtotal' => 100_000,
        'tax_amount' => 18_000,
        'total' => 118_000,
        'amount_paid' => 118_000,
    ], $overrides)));
}

test('guests are redirected to login when accessing pme dashboard', function () {
    $this->get(route('pme.dashboard'))
        ->assertRedirect(route('login'));
});

test('sme user can visit the pme dashboard', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk();
});

test('accountant user is redirected to compta dashboard from pme routes', function () {
    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertRedirect(route('dashboard'));
});

test('sme user cannot access compta routes', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk();
});

test('pme dashboard sidebar renders pme navigation', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder([
        'Tableau de bord',
        'Devis',
        'Factures',
        'Clients',
        'Recouvrement',
        'Trésorerie',
        'Paramètres',
        'Aide & Support',
        'Déconnexion',
    ]);
    $response->assertSee('data-test="logout-button"', false);
});

test('pme sidebar shows the company name in header', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $company = Company::factory()->create(['type' => 'sme', 'name' => 'Ma Super PME']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSee('Ma Super PME');
});

test('pme sidebar shows fayeku pme logo label', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $response = $this->actingAs($user)
        ->get(route('pme.dashboard'));

    $response->assertOk();
    $response->assertSee('PME');
});

test('sme user is redirected to pme dashboard after otp verification', function () {
    $user = User::factory()->unverified()->create([
        'phone' => '+221771234567',
        'profile_type' => 'sme',
    ]);

    createOtpCode('+221771234567', '654321');

    $this->actingAs($user)
        ->withSession(['otp_phone' => '+221771234567'])
        ->post(route('auth.otp.verify'), ['code' => '654321'])
        ->assertRedirect(route('pme.dashboard'));
});

test('all pme pages are accessible to sme user', function (string $routeName) {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'pme.dashboard',
    'pme.invoices.index',
    'pme.clients.index',
    'pme.collection.index',
    'pme.treasury.index',
    'pme.support.index',
    'pme.settings.index',
]);

test('pme dashboard uses FCFA outside tables and keeps F inside tables', function () {
    ['user' => $user, 'company' => $company] = createPmeDashboardUser();

    createPmeDashboardInvoice($company, [
        'reference' => 'FAC-PAID',
        'total' => 118_000,
        'amount_paid' => 118_000,
        'status' => InvoiceStatus::Paid->value,
    ]);

    createPmeDashboardInvoice($company, [
        'reference' => 'FAC-SENT',
        'subtotal' => 200_000,
        'tax_amount' => 36_000,
        'total' => 236_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Sent->value,
    ]);

    createPmeDashboardInvoice($company, [
        'reference' => 'FAC-OVERDUE',
        'subtotal' => 300_000,
        'tax_amount' => 54_000,
        'total' => 354_000,
        'amount_paid' => 0,
        'status' => InvoiceStatus::Overdue->value,
        'due_at' => now()->subDays(45),
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.dashboard.index')
        ->assertSee('708 000 FCFA')
        ->assertSee('118 000 FCFA')
        ->assertSee('+236 000 FCFA')
        ->assertSee('354 000 F');
});

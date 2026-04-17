<?php

use App\Enums\PME\DunningStrategy;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Exceptions\Shared\QuotaExceededException;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use App\Services\Shared\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A Monday at 10:00 so we're inside the 8–18 window and not weekend.
    $this->travelTo(now()->startOfWeek()->setHour(10)->setMinute(0));

    foreach ([0, 3, 7, 15, 30] as $offset) {
        DunningTemplate::updateOrCreate(
            ['day_offset' => $offset],
            ['body' => "Rappel J+{$offset} pour {invoice_reference} ({amount} FCFA).", 'active' => true]
        );
    }
});

function createAutoReminderCompany(): Company
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'plan' => 'essentiel',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return $company;
}

function createOverdueInvoiceForAutoReminder(
    Company $company,
    int $daysOverdue = 10,
    DunningStrategy $strategy = DunningStrategy::Standard,
    array $clientOverrides = [],
    array $invoiceOverrides = [],
): Invoice {
    $client = Client::factory()->create(array_merge(
        ['company_id' => $company->id, 'dunning_strategy' => $strategy],
        $clientOverrides,
    ));

    return Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create(array_merge([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays($daysOverdue),
            'total' => 100_000,
            'amount_paid' => 0,
            'reminders_enabled' => true,
        ], $invoiceOverrides));
}

// ─── Dispatching ─────────────────────────────────────────────────────────────

it('dispatches a WhatsApp reminder matching a strategy offset', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 3);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, function ($job) {
        return $job->mode === ReminderMode::Auto
            && $job->channel === ReminderChannel::WhatsApp
            && $job->dayOffset === 3
            && str_contains($job->messageBody, 'Rappel J+3');
    });
});

it('falls back to email when client has no phone but has email', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 3, clientOverrides: [
        'phone' => null,
        'email' => 'finance@example.com',
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, fn ($job) => $job->channel === ReminderChannel::Email);
});

it('skips when client has neither phone nor email', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 3, clientOverrides: [
        'phone' => null,
        'email' => null,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch for clients with None strategy', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 30, strategy: DunningStrategy::None);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch when reminders_enabled is false on the invoice', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 30, invoiceOverrides: ['reminders_enabled' => false]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch twice for the same day_offset', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    $invoice = createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp,
        'mode' => ReminderMode::Auto,
        'day_offset' => 3,
        'sent_at' => now()->subDay(),
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('dispatches for multiple matching offsets in a single run', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 8);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatchedTimes(SendReminderJob::class, 2);
    Bus::assertDispatched(SendReminderJob::class, fn ($job) => $job->dayOffset === 3);
    Bus::assertDispatched(SendReminderJob::class, fn ($job) => $job->dayOffset === 7);
});

it('honors the Strict strategy with day-zero reminder on due date', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder(
        $company,
        daysOverdue: 0,
        strategy: DunningStrategy::Strict,
    );

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, fn ($job) => $job->dayOffset === 0);
});

it('honors the Soft strategy — no dispatch before J+7', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder(
        $company,
        daysOverdue: 5,
        strategy: DunningStrategy::Soft,
    );

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('skips overdue-but-not-yet-triggered invoices for Standard at J+1', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 1);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('skips paid invoices', function () {
    Bus::fake(SendReminderJob::class);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 10, invoiceOverrides: [
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

// ─── Send window ─────────────────────────────────────────────────────────────

it('does not dispatch outside the 8–18 window', function () {
    Bus::fake(SendReminderJob::class);

    $this->travelTo(now()->startOfWeek()->setHour(6)->setMinute(0));

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch on weekends', function () {
    Bus::fake(SendReminderJob::class);

    $this->travelTo(now()->startOfWeek()->addDays(5)->setHour(10)); // Saturday

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

// ─── Quota ───────────────────────────────────────────────────────────────────

it('stops dispatching when the quota is exceeded', function () {
    Bus::fake(SendReminderJob::class);

    $quota = Mockery::mock(QuotaService::class);
    $quota->shouldReceive('authorize')->andThrow(new QuotaExceededException('reminders', 0, 0));
    $quota->shouldReceive('consume')->andReturn();
    $this->app->instance(QuotaService::class, $quota);

    $company = createAutoReminderCompany();
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 10);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

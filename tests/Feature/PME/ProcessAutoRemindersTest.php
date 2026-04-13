<?php

use App\Console\Commands\ProcessAutoRemindersCommand;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Exceptions\Shared\QuotaExceededException;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\PME\ReminderRule;
use App\Models\Shared\User;
use App\Services\Shared\QuotaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setHour(10)->setMinute(0));
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createAutoReminderCompany(array $settingsOverrides = []): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'plan' => 'essentiel',
        'reminder_settings' => array_merge([
            'enabled' => true,
            'mode' => 'auto',
            'default_channel' => 'whatsapp',
            'default_tone' => 'cordial',
            'send_hour_start' => 0,
            'send_hour_end' => 23,
            'exclude_weekends' => false,
            'attach_pdf' => true,
        ], $settingsOverrides),
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createOverdueInvoiceForAutoReminder(Company $company, int $daysOverdue = 10, array $clientOverrides = []): Invoice
{
    $client = Client::factory()->create(array_merge(
        ['company_id' => $company->id],
        $clientOverrides,
    ));

    return Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays($daysOverdue),
            'total' => 100_000,
            'amount_paid' => 0,
        ]);
}

function createRule(Company $company, int $triggerDays = 3, ReminderChannel $channel = ReminderChannel::WhatsApp): ReminderRule
{
    return ReminderRule::create([
        'company_id' => $company->id,
        'name' => "Relance J+{$triggerDays}",
        'trigger_days' => $triggerDays,
        'channel' => $channel,
        'is_active' => true,
    ]);
}

// ─── Dispatching auto reminders ──────────────────────────────────────────────

it('dispatches a reminder for an overdue invoice matching a rule', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, function ($job) {
        return $job->mode === ReminderMode::Auto
            && $job->channel === ReminderChannel::WhatsApp;
    });
});

it('does not dispatch when company mode is manual', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany(['mode' => 'manual']);
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch when reminders are disabled', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany(['enabled' => false]);
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

// ─── Duplicate prevention ───────────────────────────────────────────────────

it('does not dispatch a duplicate if an auto reminder was already sent for that rule', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    $rule = createRule($company, triggerDays: 3);
    $invoice = createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => $rule->channel,
        'mode' => 'auto',
        'sent_at' => now()->subDay(),
        'message_body' => 'Auto reminder',
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('still dispatches auto reminder even if a manual reminder exists', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);
    $invoice = createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    Reminder::create([
        'invoice_id' => $invoice->id,
        'channel' => ReminderChannel::WhatsApp,
        'mode' => 'manual',
        'sent_at' => now()->subDays(2),
        'message_body' => 'Manual reminder',
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class);
});

// ─── Send window ─────────────────────────────────────────────────────────────

it('does not dispatch outside the send window', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany([
        'send_hour_start' => 8,
        'send_hour_end' => 18,
    ]);
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->travelTo(now()->setHour(20));

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch on weekends when exclude_weekends is true', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany([
        'exclude_weekends' => true,
    ]);
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->travelTo(now()->next('Saturday')->setHour(10));

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('dispatches on weekends when exclude_weekends is false', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany([
        'exclude_weekends' => false,
        'send_hour_start' => 0,
        'send_hour_end' => 23,
    ]);
    createRule($company, triggerDays: 3);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->travelTo(now()->next('Saturday')->setHour(10));

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class);
});

// ─── Quota enforcement ───────────────────────────────────────────────────────

it('stops dispatching when quota is exhausted', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);

    for ($i = 0; $i < 5; $i++) {
        createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);
    }

    $callCount = 0;
    $mock = Mockery::mock(QuotaService::class);
    $mock->shouldReceive('authorize')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount > 2) {
            throw new QuotaExceededException('reminders');
        }
    });
    $mock->shouldReceive('consume')->andReturnNull();

    $command = new ProcessAutoRemindersCommand($mock);
    $command->setLaravel($this->app);

    $this->app->make(Kernel::class)
        ->registerCommand($command);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    expect(Bus::dispatched(SendReminderJob::class)->count())->toBe(2);
});

// ─── Contact validation ─────────────────────────────────────────────────────

it('does not dispatch WhatsApp reminder when client has no phone', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3, channel: ReminderChannel::WhatsApp);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5, clientOverrides: [
        'phone' => null,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch SMS reminder when client has no phone', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3, channel: ReminderChannel::Sms);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5, clientOverrides: [
        'phone' => null,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch Email reminder when client has no email', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3, channel: ReminderChannel::Email);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5, clientOverrides: [
        'email' => null,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

// ─── Multiple rules ─────────────────────────────────────────────────────────

it('dispatches reminders for multiple rules independently', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3, channel: ReminderChannel::WhatsApp);
    createRule($company, triggerDays: 7, channel: ReminderChannel::Email);
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 10);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, 2);

    $dispatched = Bus::dispatched(SendReminderJob::class);
    $channels = $dispatched->map(fn ($job) => $job->channel)->all();

    expect($channels)->toContain(ReminderChannel::WhatsApp)
        ->toContain(ReminderChannel::Email);
});

it('only dispatches matching rules based on trigger_days', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3, channel: ReminderChannel::WhatsApp);
    createRule($company, triggerDays: 30, channel: ReminderChannel::Email);

    // Invoice is only 5 days overdue → J+3 matches, J+30 does not
    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, 1);
    Bus::assertDispatched(SendReminderJob::class, fn ($job) => $job->channel === ReminderChannel::WhatsApp);
});

// ─── Invoice status filtering ───────────────────────────────────────────────

it('does not dispatch for paid invoices', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);

    $client = Client::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Paid,
        'due_at' => now()->subDays(10),
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('does not dispatch for draft invoices', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);

    $client = Client::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::Draft,
        'due_at' => now()->subDays(10),
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

it('dispatches for partially paid invoices', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();
    createRule($company, triggerDays: 3);

    $client = Client::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->forCompany($company)->withClient($client)->create([
        'status' => InvoiceStatus::PartiallyPaid,
        'due_at' => now()->subDays(10),
        'total' => 100_000,
        'amount_paid' => 50_000,
    ]);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertDispatched(SendReminderJob::class, 1);
});

// ─── Inactive rules ─────────────────────────────────────────────────────────

it('ignores inactive reminder rules', function () {
    Bus::fake(SendReminderJob::class);

    ['company' => $company] = createAutoReminderCompany();

    ReminderRule::create([
        'company_id' => $company->id,
        'name' => 'Relance J+3',
        'trigger_days' => 3,
        'channel' => ReminderChannel::WhatsApp,
        'is_active' => false,
    ]);

    createOverdueInvoiceForAutoReminder($company, daysOverdue: 5);

    $this->artisan('reminders:process-auto')->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

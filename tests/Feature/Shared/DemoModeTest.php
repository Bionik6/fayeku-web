<?php

use App\Enums\PME\DunningStrategy;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Interfaces\Shared\OtpChannelInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use App\Models\PME\Reminder;
use App\Models\Shared\Notification;
use App\Models\Shared\User;
use App\Services\PME\ReminderService;
use App\Services\PME\WhatsAppNotificationService;
use App\Services\Shared\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

// ─── Bandeau visuel ──────────────────────────────────────────────────────────

test('le bandeau démo apparaît sur le dashboard PME quand le mode est actif', function () {
    config(['fayeku.demo' => true]);

    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk()
        ->assertSee('Mode démonstration actif');
});

test('le bandeau démo n\'apparaît pas sur le dashboard PME quand le mode est inactif', function () {
    config(['fayeku.demo' => false]);

    $user = User::factory()->create(['profile_type' => 'sme']);

    $this->actingAs($user)
        ->get(route('pme.dashboard'))
        ->assertOk()
        ->assertDontSee('Mode démonstration actif');
});

test('le bandeau démo apparaît sur le dashboard comptable quand le mode est actif', function () {
    config(['fayeku.demo' => true]);

    $user = User::factory()->accountantFirm()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Mode démonstration actif');
});

// ─── ReminderService gating ──────────────────────────────────────────────────

test('ReminderService::send ne touche pas au canal externe en mode démo', function () {
    config(['fayeku.demo' => true]);

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221771112233',
    ]);
    $invoice = Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue->value,
            'due_at' => now()->subDays(5),
            'total' => 150_000,
            'amount_paid' => 0,
        ]);

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('send');
        $m->shouldNotReceive('sendTemplate');
    });

    $reminder = app(ReminderService::class)->send(
        $invoice,
        $company,
        ReminderChannel::WhatsApp,
        'Test démo',
        ReminderMode::Manual,
    );

    expect($reminder)->toBeInstanceOf(Reminder::class)
        ->and($reminder->channel)->toBe(ReminderChannel::WhatsApp)
        ->and($reminder->mode)->toBe(ReminderMode::Manual)
        ->and($reminder->recipient_phone)->toBe('+221771112233')
        ->and($reminder->message_body)->toBe('Test démo');
});

// ─── ProcessAutoRemindersCommand gating ─────────────────────────────────────

test('la commande de relances automatiques ne dispatch rien en mode démo', function () {
    $this->travelTo(now()->startOfWeek()->setHour(10));

    foreach ([0, 3, 7, 15, 30] as $offset) {
        DunningTemplate::updateOrCreate(
            ['day_offset' => $offset],
            ['body' => "Rappel J+{$offset}.", 'active' => true],
        );
    }

    config(['fayeku.demo' => true]);

    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'phone' => '+221771112233',
        'dunning_strategy' => DunningStrategy::Standard,
    ]);
    Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'status' => InvoiceStatus::Overdue->value,
            'due_at' => now()->subDays(15),
            'reminders_enabled' => true,
            'total' => 100_000,
            'amount_paid' => 0,
        ]);

    Bus::fake();

    $this->artisan('reminders:process-auto')
        ->assertSuccessful();

    Bus::assertNotDispatched(SendReminderJob::class);
});

// ─── OTP gating ──────────────────────────────────────────────────────────────

test('OtpService::generate ne contacte aucun canal externe en mode démo', function () {
    config(['fayeku.demo' => true]);

    $this->mock(OtpChannelInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('send');
    });

    $code = app(OtpService::class)->generate('+221771112233');

    expect($code)->toMatch('/^\d{6}$/');
});

test('OtpService::verify accepte le code de bypass en mode démo hors environnement local', function () {
    config([
        'fayeku.demo' => true,
        'fayeku.otp_bypass_code' => '123456',
    ]);

    app()->detectEnvironment(fn () => 'production');

    $verified = app(OtpService::class)->verify('+221771112233', '123456');

    expect($verified)->toBeTrue();
});

// ─── WhatsAppNotificationService gating ─────────────────────────────────────

test('WhatsAppNotificationService persiste la notification sans appeler le provider en mode démo', function () {
    config(['fayeku.demo' => true]);

    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Démo SARL',
        'sender_name' => 'Aïssatou Démo',
        'sender_role' => 'CEO',
    ]);
    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Client Démo',
        'phone' => '+221771112233',
    ]);
    $invoice = Invoice::unguarded(fn () => Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'reference' => 'FAC-DEMO-01',
        'status' => InvoiceStatus::Sent->value,
        'issued_at' => now(),
        'due_at' => now()->addDays(15),
        'subtotal' => 250_000,
        'tax_amount' => 0,
        'total' => 250_000,
        'amount_paid' => 0,
        'currency' => 'XOF',
    ]));

    $this->mock(WhatsAppProviderInterface::class, function (MockInterface $m) {
        $m->shouldNotReceive('send');
        $m->shouldNotReceive('sendTemplate');
    });

    $notif = app(WhatsAppNotificationService::class)->sendInvoiceCreated($invoice, $company);

    expect($notif)->toBeInstanceOf(Notification::class)
        ->and($notif->channel)->toBe('whatsapp')
        ->and($notif->recipient_phone)->toBe('+221771112233');
});

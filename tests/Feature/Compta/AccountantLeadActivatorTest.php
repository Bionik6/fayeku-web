<?php

use App\Enums\Auth\CompanyRole;
use App\Enums\Compta\LeadSource;
use App\Mail\Compta\AccountantActivationLinkMail;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\AccountantLead;
use App\Models\Shared\User;
use App\Services\Compta\AccountantLeadActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeLead(array $overrides = []): AccountantLead
{
    return AccountantLead::create(array_merge([
        'first_name' => 'Mamadou',
        'last_name' => 'Diallo',
        'firm' => 'Cabinet Diallo & Associés',
        'email' => 'mamadou@diallo-compta.sn',
        'country_code' => 'SN',
        'phone' => '+221771234567',
        'region' => 'Dakar',
        'portfolio_size' => '1 à 20 dossiers',
        'message' => 'Centraliser les factures de mes clients PME.',
    ], $overrides));
}

test('activate creates user, company, pivot, subscription', function () {
    Mail::fake();
    $lead = makeLead();

    app(AccountantLeadActivator::class)->activate($lead);

    $lead->refresh();
    expect($lead->status)->toBe('activated');
    expect($lead->activated_at)->not->toBeNull();
    expect($lead->user_id)->not->toBeNull();
    expect($lead->company_id)->not->toBeNull();

    $user = User::find($lead->user_id);
    expect($user->profile_type)->toBe('accountant_firm');
    expect($user->is_active)->toBeFalse();
    expect($user->phone)->toBe('+221771234567');

    $company = Company::find($lead->company_id);
    expect($company->type)->toBe('accountant_firm');
    expect($company->name)->toBe('Cabinet Diallo & Associés');
    expect($company->invite_code)->not->toBeNull();
    expect($company->users()->where('role', CompanyRole::Owner->value)->where('user_id', $user->id)->exists())->toBeTrue();

    $sub = Subscription::where('company_id', $company->id)->sole();
    expect($sub->plan_slug)->toBe('basique');
    expect($sub->status)->toBe('trial');
    expect($sub->trial_ends_at)->not->toBeNull();
});

test('activate sends an activation email with a working token', function () {
    Mail::fake();
    $lead = makeLead();

    app(AccountantLeadActivator::class)->activate($lead);
    $lead->refresh();

    Mail::assertSent(AccountantActivationLinkMail::class, function (AccountantActivationLinkMail $mail) use ($lead) {
        return $mail->hasTo($lead->email)
            && $lead->isActivationTokenValid($mail->token);
    });
});

test('activating a lead twice throws', function () {
    Mail::fake();
    $lead = makeLead();
    app(AccountantLeadActivator::class)->activate($lead);

    expect(fn () => app(AccountantLeadActivator::class)->activate($lead->fresh()))
        ->toThrow(DomainException::class);
});

test('isActivationTokenValid rejects unrelated tokens', function () {
    Mail::fake();
    $lead = makeLead();
    app(AccountantLeadActivator::class)->activate($lead);

    expect($lead->fresh()->isActivationTokenValid('not-the-token'))->toBeFalse();
});

test('resendActivation regenerates the token and resends the email', function () {
    Mail::fake();
    $lead = makeLead();
    app(AccountantLeadActivator::class)->activate($lead);
    $oldHash = $lead->fresh()->activation_token_hash;

    app(AccountantLeadActivator::class)->resendActivation($lead->fresh());

    expect($lead->fresh()->activation_token_hash)->not->toBe($oldHash);
    Mail::assertSent(AccountantActivationLinkMail::class, 2);
});

test('resendActivation refuses non-activated leads', function () {
    $lead = makeLead();

    expect(fn () => app(AccountantLeadActivator::class)->resendActivation($lead))
        ->toThrow(DomainException::class);
});

test('AccountantLead casts source to LeadSource enum from any branded source', function () {
    foreach (LeadSource::cases() as $case) {
        $lead = makeLead([
            'email' => "lead-{$case->value}@cabinet.sn",
            'phone' => '+22177'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
            'source' => $case,
        ]);

        expect($lead->fresh()->source)->toBe($case);
    }
});

test('AccountantLead source cast accepts a string and returns the enum', function () {
    $lead = makeLead(['source' => 'whatsapp_outreach']);

    expect($lead->fresh()->source)->toBe(LeadSource::WhatsAppOutreach);
});

test('Activator attaches the new user with the owner role string', function () {
    Mail::fake();
    $lead = makeLead();

    app(AccountantLeadActivator::class)->activate($lead);

    $row = DB::table('company_user')
        ->where('company_id', $lead->fresh()->company_id)
        ->sole();

    expect($row->role)->toBe(CompanyRole::Owner->value);
});

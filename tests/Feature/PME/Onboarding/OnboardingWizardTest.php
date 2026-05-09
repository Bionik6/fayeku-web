<?php

use App\Enums\Auth\LegalForm;
use App\Enums\Auth\OnboardingIntent;
use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Models\PME\Client;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function smeForWizard(?array $companyAttrs = null): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()
        ->pendingOnboarding()
        ->create(array_merge(['type' => 'sme'], $companyAttrs ?? []));
    $company->users()->attach($user->id, ['role' => 'owner']);

    return [$user, $company];
}

// ─── mount() : positionnement de currentStep ─────────────────────────────────

test('mount positionne currentStep à 0 quand aucune étape n\'est faite', function () {
    [$user] = smeForWizard();

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('currentStep', 0);
});

test('mount positionne currentStep à 1 quand l\'intent est posé', function () {
    [$user] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('currentStep', 1);
});

test('mount positionne currentStep à 2 quand l\'identité est posée', function () {
    [$user] = smeForWizard(['legal_form' => LegalForm::Sarl->value]);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('currentStep', 2);
});

test('mount positionne currentStep à 3 quand la signature est posée', function () {
    [$user] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'Awa Diop']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('currentStep', 3);
});

// ─── selectIntent ────────────────────────────────────────────────────────────

test('selectIntent rejette une valeur invalide', function () {
    [$user] = smeForWizard();

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->call('selectIntent', 'NOT_AN_INTENT')
        ->assertHasErrors('intent');

    expect($user->fresh()->onboarding_intent)->toBeNull();
});

test('selectIntent persiste un choix valide sur le User', function () {
    [$user] = smeForWizard();

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->call('selectIntent', OnboardingIntent::RecoverUnpaid->value)
        ->assertSet('intent', OnboardingIntent::RecoverUnpaid->value);

    expect($user->fresh()->onboarding_intent)->toBe(OnboardingIntent::RecoverUnpaid);
});

test('goToStep(1) est bloqué tant que l\'intent n\'est pas choisi', function () {
    [$user] = smeForWizard();

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->call('goToStep', 1)
        ->assertSet('currentStep', 0)
        ->assertHasErrors('intent');
});

// ─── saveIdentity ────────────────────────────────────────────────────────────

test('saveIdentity exige nom + forme juridique + secteur', function () {
    [$user] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', '')
        ->set('legalForm', null)
        ->set('sector', '')
        ->call('saveIdentity')
        ->assertHasErrors(['companyName', 'legalForm', 'sector']);
});

test('saveIdentity persiste les champs sur la Company et passe au step 2', function () {
    [$user, $company] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', 'Kaay Digital SARL')
        ->set('legalForm', LegalForm::Sarl->value)
        ->set('sector', 'Technologie / Informatique')
        ->set('rccm', 'SN-DKR-2024-B-12345')
        ->set('address', 'Plateau, Dakar')
        ->set('billingEmail', 'compta@kaay.sn')
        ->call('saveIdentity')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2);

    $fresh = $company->fresh();
    expect($fresh->name)->toBe('Kaay Digital SARL')
        ->and($fresh->legal_form)->toBe(LegalForm::Sarl)
        ->and($fresh->sector)->toBe('Technologie / Informatique')
        ->and($fresh->rccm)->toBe('SN-DKR-2024-B-12345')
        ->and($fresh->address)->toBe('Plateau, Dakar')
        ->and($fresh->email)->toBe('compta@kaay.sn');
});

test('saveIdentity gère le secteur "Autre" via sectorOther', function () {
    [$user, $company] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', 'Atelier Custom')
        ->set('legalForm', LegalForm::Gie->value)
        ->set('sector', '__other__')
        ->set('sectorOther', 'Astrophysique appliquée')
        ->call('saveIdentity')
        ->assertHasNoErrors();

    expect($company->fresh()->sector)->toBe('Astrophysique appliquée');
});

test('saveIdentity rejette un NINEA mal formé', function () {
    [$user] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', 'Test')
        ->set('legalForm', LegalForm::Sarl->value)
        ->set('sector', 'Commerce de gros')
        ->set('ninea', 'not-a-ninea')
        ->call('saveIdentity')
        ->assertHasErrors('ninea');
});

test('saveIdentity stocke un logo PNG valide', function () {
    Storage::fake();
    [$user, $company] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    $logo = UploadedFile::fake()->image('logo.png', 200, 200);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', 'Logo Co')
        ->set('legalForm', LegalForm::Sas->value)
        ->set('sector', 'Médias & Communication')
        ->set('logoUpload', $logo)
        ->call('saveIdentity')
        ->assertHasNoErrors();

    $path = $company->fresh()->logo_path;
    expect($path)->toStartWith('company-logos/');
    Storage::assertExists($path);
});

test('saveIdentity rejette un logo > 1 Mo', function () {
    Storage::fake();
    [$user] = smeForWizard();
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    $logo = UploadedFile::fake()->image('big.png')->size(2048);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1)
        ->set('companyName', 'Big Logo')
        ->set('legalForm', LegalForm::Sas->value)
        ->set('sector', 'Médias & Communication')
        ->set('logoUpload', $logo)
        ->call('saveIdentity')
        ->assertHasErrors('logoUpload');
});

// ─── saveSignature ───────────────────────────────────────────────────────────

test('saveSignature exige une signature non vide et limite à 60 chars', function () {
    [$user] = smeForWizard(['legal_form' => LegalForm::Sarl->value]);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 2)
        ->set('senderName', '')
        ->call('saveSignature')
        ->assertHasErrors('senderName');

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 2)
        ->set('senderName', str_repeat('A', 61))
        ->call('saveSignature')
        ->assertHasErrors('senderName');
});

test('saveSignature persiste sur sender_name et passe au step 3', function () {
    [$user, $company] = smeForWizard(['legal_form' => LegalForm::Sarl->value]);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 2)
        ->set('senderName', 'Kaay Digital SARL')
        ->set('senderRole', 'Gérante')
        ->call('saveSignature')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 3);

    $fresh = $company->fresh();
    expect($fresh->sender_name)->toBe('Kaay Digital SARL')
        ->and($fresh->sender_role)->toBe('Gérante')
        ->and($fresh->composeSenderSignature())->toContain('Kaay Digital SARL');
});

// ─── saveFirstClient ─────────────────────────────────────────────────────────

test('saveFirstClient rejette un client sans numéro de téléphone', function () {
    [$user] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'A B']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 3)
        ->set('clientName', 'Sans Téléphone')
        ->set('clientPhone', '')
        ->call('saveFirstClient')
        ->assertHasErrors('clientPhone');
});

test('saveFirstClient crée un client lié et passe au step 4', function () {
    [$user, $company] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'A B']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 3)
        ->set('clientName', 'Kaay Client')
        ->set('clientPhoneCountry', 'SN')
        ->set('clientPhone', '771234567')
        ->set('clientEmail', 'client@example.com')
        ->call('saveFirstClient')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 4)
        ->assertSet('firstClientCreated', true);

    $client = Client::query()->where('company_id', $company->id)->first();
    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('Kaay Client')
        ->and($client->phone)->toBe('+221771234567')
        ->and($client->email)->toBe('client@example.com');
});

test('skipFirstClient passe au step 4 sans créer de client', function () {
    [$user, $company] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'A B']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 3)
        ->call('skipFirstClient')
        ->assertSet('currentStep', 4)
        ->assertSet('firstClientCreated', false);

    expect(Client::query()->where('company_id', $company->id)->count())->toBe(0);
});

// ─── complete ────────────────────────────────────────────────────────────────

test('complete pose setup_completed_at, backfill PartnerInvitation et redirige vers le dashboard', function () {
    [$user, $company] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'A B', 'name' => 'Kaay Final']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    $invitation = PartnerInvitation::query()->create([
        'sme_company_id' => $company->id,
        'invitee_company_name' => null,
        'token' => 'tok-'.uniqid(),
        'status' => 'accepted',
        'invitee_email' => 'mariama@example.com',
        'accountant_firm_id' => Company::factory()->accountantFirm()->create()->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 4)
        ->call('complete')
        ->assertRedirect(route('pme.dashboard'));

    expect($company->fresh()->setup_completed_at)->not->toBeNull()
        ->and($invitation->fresh()->invitee_company_name)->toBe('Kaay Final');
});

// ─── préfilling depuis une invitation cabinet ────────────────────────────────

test('mount préfille senderName avec le nom complet du user quand sender_name est vide', function () {
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'first_name' => 'Awa',
        'last_name' => 'Diop',
    ]);
    $company = Company::factory()
        ->pendingOnboarding()
        ->create(['type' => 'sme', 'sender_name' => null]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('senderName', 'Awa Diop');
});

test('mount préserve sender_name déjà saisi par le user', function () {
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'first_name' => 'Awa',
        'last_name' => 'Diop',
    ]);
    $company = Company::factory()
        ->pendingOnboarding()
        ->create(['type' => 'sme', 'sender_name' => 'Mariama Sy']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('senderName', 'Mariama Sy');
});

test('mount préfille companyName depuis Company::name (invitation cabinet)', function () {
    [$user] = smeForWizard(['name' => 'Invitée par cabinet']);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('companyName', 'Invitée par cabinet');
});

test('completeAndCreateInvoice marque setup_completed_at puis redirige vers la création de facture', function () {
    [$user, $company] = smeForWizard(['legal_form' => LegalForm::Sarl->value, 'sender_name' => 'A B', 'name' => 'Kaay Final']);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 4)
        ->call('completeAndCreateInvoice')
        ->assertRedirect(route('pme.invoices.create'));

    expect($company->fresh()->setup_completed_at)->not->toBeNull();
});

test('mount NE préfille PAS companyName si la valeur est le nom complet du user (placeholder à l\'inscription)', function () {
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'first_name' => 'Awa',
        'last_name' => 'Diop',
    ]);
    $company = Company::factory()
        ->pendingOnboarding()
        ->create(['type' => 'sme', 'name' => 'Awa Diop']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->assertSet('companyName', '');
});

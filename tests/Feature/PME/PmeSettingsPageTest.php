<?php

use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\Commission;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSmeSettingsUser(): array
{
    $user = User::factory()->create([
        'phone_verified_at' => now(),
        'profile_type' => 'sme',
    ]);

    $company = Company::factory()->create([
        'type' => 'sme',
        'name' => 'Entreprise Test',
        'email' => 'contact@test-pme.sn',
        'address' => '18 Avenue Cheikh Anta Diop',
        'city' => 'Dakar',
        'country_code' => 'SN',
        'ninea' => 'SN20240180',
        'rccm' => 'SN-DKR-2023-B-01800',
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function createSmeSettingsWithSubscription(string $planSlug = 'essentiel', string $status = 'trial'): array
{
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    $factory = Subscription::factory()->for($company);

    $subscription = match ($status) {
        'active' => $factory->active()->create(['plan_slug' => $planSlug, 'price_paid' => 20000]),
        'cancelled' => $factory->active()->create(['plan_slug' => $planSlug, 'status' => 'cancelled', 'cancelled_at' => now()]),
        default => $factory->create(['plan_slug' => $planSlug]),
    };

    return compact('user', 'company', 'subscription');
}

// ─── Navigation & rendering ──────────────────────────────────────────────

it('redirects unauthenticated users to login', function () {
    $this->get(route('pme.settings.index'))->assertRedirect(route('login'));
});

it('renders settings page for authenticated SME user', function () {
    ['user' => $user] = createSmeSettingsUser();

    $this->actingAs($user)
        ->get(route('pme.settings.index'))
        ->assertOk()
        ->assertSee('Paramètres');
});

it('shows company section by default', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->assertSet('activeSection', 'company')
        ->assertSee('Mon Entreprise');
});

it('switches between all six sections', function () {
    ['user' => $user] = createSmeSettingsUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.settings.index');

    foreach (['company', 'profile', 'signature', 'password', 'plan', 'danger'] as $section) {
        $component->call('setSection', $section)
            ->assertSet('activeSection', $section);
    }
});

it('rejects invalid section names', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'invalid')
        ->assertSet('activeSection', 'company');
});

it('clears validation errors when switching sections', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', '')
        ->call('saveCompanyProfile')
        ->assertHasErrors(['firmName'])
        ->call('setSection', 'profile')
        ->assertHasNoErrors();
});

// ─── Company profile (Mon Entreprise) ────────────────────────────────────

it('loads company data on mount', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->assertSet('firmName', 'Entreprise Test')
        ->assertSet('firmEmail', 'contact@test-pme.sn')
        ->assertSet('firmCity', 'Dakar')
        ->assertSet('firmNinea', 'SN20240180')
        ->assertSet('firmRccm', 'SN-DKR-2023-B-01800');
});

it('saves company profile information', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', 'Nouvelle Entreprise')
        ->set('firmEmail', 'new@pme.sn')
        ->set('firmCity', 'Saint-Louis')
        ->set('firmNinea', 'SN99999')
        ->call('saveCompanyProfile')
        ->assertHasNoErrors();

    $company->refresh();

    expect($company->name)->toBe('Nouvelle Entreprise');
    expect($company->email)->toBe('new@pme.sn');
    expect($company->city)->toBe('Saint-Louis');
    expect($company->ninea)->toBe('SN99999');
});

it('validates required company name', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', '')
        ->call('saveCompanyProfile')
        ->assertHasErrors(['firmName']);
});

it('validates company email format', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmEmail', 'not-an-email')
        ->call('saveCompanyProfile')
        ->assertHasErrors(['firmEmail']);
});

// ─── Country verrouillé sur le Sénégal ────────────────────────────────────

it('initialise toujours firmCountry à SN au mount', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->assertSet('firmCountry', 'SN');
});

it('initialise firmCountry à SN même si la company avait un autre pays en DB', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['country_code' => 'CI']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->assertSet('firmCountry', 'SN');
});

it('saveCompanyProfile ne valide plus firmCountry et persiste toujours SN', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', 'Diop Services SARL')
        ->set('firmCountry', '') // tentative de corruption
        ->call('saveCompanyProfile')
        ->assertHasNoErrors();

    expect($company->fresh()->country_code)->toBe('SN');
});

it('saveCompanyProfile écrase toute valeur firmCountry non-SN à SN', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmName', 'Diop Services SARL')
        ->set('firmCountry', 'CI') // valeur étrangère
        ->call('saveCompanyProfile')
        ->assertHasNoErrors();

    expect($company->fresh()->country_code)->toBe('SN');
});

it('normalise le téléphone avec le préfixe sénégalais +221', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('firmPhone', '77 243 22 31')
        ->call('saveCompanyProfile')
        ->assertHasNoErrors();

    expect($company->fresh()->phone)->toStartWith('+221');
});

// ─── User profile (Mon Profil) ───────────────────────────────────────────

it('loads user data on mount', function () {
    ['user' => $user] = createSmeSettingsUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.settings.index');

    expect($component->get('firstName'))->toBe($user->first_name);
    expect($component->get('lastName'))->toBe($user->last_name);
});

it('saves user account information', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'profile')
        ->set('firstName', 'Amadou')
        ->set('lastName', 'Diop')
        ->set('userEmail', 'amadou@test.sn')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->first_name)->toBe('Amadou');
    expect($user->last_name)->toBe('Diop');
    expect($user->email)->toBe('amadou@test.sn');
});

it('validates required user name fields', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'profile')
        ->set('firstName', '')
        ->set('lastName', '')
        ->call('saveAccount')
        ->assertHasErrors(['firstName', 'lastName']);
});

// ─── Password (Mot de passe) ─────────────────────────────────────────────

it('updates password successfully', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'password')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'New-Secure-Pass-123!')
        ->set('newPasswordConfirmation', 'New-Secure-Pass-123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('New-Secure-Pass-123!', $user->password))->toBeTrue();
});

it('rejects wrong current password', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'password')
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'New-Secure-Pass-123!')
        ->set('newPasswordConfirmation', 'New-Secure-Pass-123!')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);
});

it('rejects mismatched password confirmation', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'password')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'New-Secure-Pass-123!')
        ->set('newPasswordConfirmation', 'Different-Pass-456!')
        ->call('updatePassword')
        ->assertHasErrors(['newPassword']);
});

it('resets password fields after update', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'password')
        ->set('currentPassword', 'password')
        ->set('newPassword', 'New-Secure-Pass-123!')
        ->set('newPasswordConfirmation', 'New-Secure-Pass-123!')
        ->call('updatePassword')
        ->assertSet('currentPassword', '')
        ->assertSet('newPassword', '')
        ->assertSet('newPasswordConfirmation', '');
});

// ─── Plan section ────────────────────────────────────────────────────────

it('shows no subscription message when company has none', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Aucun abonnement actif');
});

it('displays current plan details with subscription', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Essentiel')
        ->assertSee('Actif')
        ->assertSee('Mensuel');
});

it('shows trial badge and expiry for trial subscriptions', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('basique', 'trial');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Basique')
        ->assertSee('Essai');
});

it('displays all three plan cards for comparison', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('basique', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Changer de plan')
        ->assertSee('Basique')
        ->assertSee('Essentiel')
        ->assertSee('Entreprise');
});

it('marks current plan card with badge', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Actuel')
        ->assertSee('Plan actuel');
});

it('shows upgrade indicator for higher plans', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('basique', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Upgrade');
});

it('shows downgrade indicator for lower plans', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Downgrade');
});

it('shows detailed comparison table', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('basique', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Comparaison détaillée');
});

it('shows cancel button for active subscriptions', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee("Résilier l'abonnement");
});

it('hides cancel button for cancelled subscriptions', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'cancelled');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Résilié')
        ->assertDontSee("Résilier l'abonnement");
});

it('opens cancel plan modal', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('essentiel', 'active');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->set('showCancelPlanModal', true)
        ->assertSee('Résilier votre abonnement ?')
        ->assertSee('Garder mon plan')
        ->assertSee('En résiliant, vous perdez');
});

it('shows payment button during trial', function () {
    ['user' => $user] = createSmeSettingsWithSubscription('basique', 'trial');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Payer mon abonnement');
});

it('returns correct plan rank ordering', function () {
    ['user' => $user] = createSmeSettingsUser();

    $component = Livewire::actingAs($user)
        ->test('pages::pme.settings.index');

    expect($component->call('planRank', 'basique')->get('activeSection'))->not->toBeNull();

    // Verify rank ordering via component instance
    $instance = $component->instance();
    expect($instance->planRank('basique'))->toBe(0);
    expect($instance->planRank('essentiel'))->toBe(1);
    expect($instance->planRank('entreprise'))->toBe(2);
});

// ─── Danger section (Delete account) ─────────────────────────────────────

it('shows delete account section', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->assertSee('Supprimer le compte')
        ->assertSee('Supprimer mon compte');
});

it('opens delete account modal', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->set('showDeleteAccountModal', true)
        ->assertSee('Êtes-vous sûr de vouloir supprimer votre compte')
        ->assertSee('Mot de passe');
});

it('deletes user account with correct password', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->set('showDeleteAccountModal', true)
        ->set('deletePassword', 'password')
        ->call('deleteUser')
        ->assertRedirect('/');

    expect(User::find($user->id))->toBeNull();
});

it('rejects delete with wrong password', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->set('showDeleteAccountModal', true)
        ->set('deletePassword', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['deletePassword']);

    expect(User::find($user->id))->not->toBeNull();
});

it('rejects delete without password', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->set('showDeleteAccountModal', true)
        ->set('deletePassword', '')
        ->call('deleteUser')
        ->assertHasErrors(['deletePassword']);
});

it('deletes the SME company along with the user (cabinet dashboards stay clean)', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    // The cabinet has a referral linkage, an invitation row and a commission tied
    // to this PME — all of these MUST disappear when the PME closes their account.
    $firm = Company::factory()->accountantFirm()->create();
    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $company->id,
        'started_at' => now()->subMonth(),
    ]);
    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'cleanup-token',
        'invitee_phone' => $user->phone,
        'invitee_name' => 'Owner',
        'invitee_company_name' => 'Entreprise Test',
        'recommended_plan' => 'essentiel',
        'channel' => 'link',
        'status' => 'accepted',
        'sme_company_id' => $company->id,
        'expires_at' => now()->addDays(30),
    ]);
    $commission = Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $company->id,
        'amount' => 3_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'danger')
        ->set('showDeleteAccountModal', true)
        ->set('deletePassword', 'password')
        ->call('deleteUser')
        ->assertRedirect('/');

    // User AND SME company are both gone.
    expect(User::find($user->id))->toBeNull();
    expect(Company::find($company->id))->toBeNull();

    // FK cascades drop everything tied to the SME, so the cabinet's invitations
    // and commissions dashboards no longer surface the deleted PME.
    expect(AccountantCompany::where('sme_company_id', $company->id)->exists())->toBeFalse();
    expect(PartnerInvitation::find($invitation->id))->toBeNull();
    expect(Commission::find($commission->id))->toBeNull();

    // The cabinet itself is untouched.
    expect(Company::find($firm->id))->not->toBeNull();
});

// ─── Signature des relances ──────────────────────────────────────────────

it('loads sender_name and sender_role on mount', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['sender_name' => 'Moussa Diop', 'sender_role' => 'Directeur commercial']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->assertSet('senderName', 'Moussa Diop')
        ->assertSet('senderRole', 'Directeur commercial');
});

it('computes signature preview live (both name and role)', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['name' => 'Khalil Softwares']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'signature')
        ->set('senderName', 'Moussa Diop')
        ->set('senderRole', 'Directeur commercial')
        ->assertSee('Moussa Diop, Directeur commercial Khalil Softwares');
});

it('computes signature preview with name only', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['name' => 'Khalil Softwares']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'signature')
        ->set('senderName', 'Moussa Diop')
        ->set('senderRole', '')
        ->assertSee('Moussa Diop, Khalil Softwares');
});

it('computes signature preview fallback (L\'equipe)', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['name' => 'Khalil Softwares']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'signature')
        ->set('senderName', '')
        ->set('senderRole', '')
        ->assertSee("L'équipe Khalil Softwares");
});

it('saves signature (name + role)', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('senderName', '  Moussa Diop  ')
        ->set('senderRole', 'Directeur commercial')
        ->call('saveSignature')
        ->assertHasNoErrors();

    $company->refresh();

    expect($company->sender_name)->toBe('Moussa Diop') // trimmed
        ->and($company->sender_role)->toBe('Directeur commercial');
});

it('saves signature with null values when fields are empty', function () {
    ['user' => $user, 'company' => $company] = createSmeSettingsUser();
    $company->update(['sender_name' => 'Old Name', 'sender_role' => 'Old Role']);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('senderName', '')
        ->set('senderRole', '')
        ->call('saveSignature');

    $company->refresh();

    expect($company->sender_name)->toBeNull()
        ->and($company->sender_role)->toBeNull();
});

it('rejects signature fields exceeding 100 characters', function () {
    ['user' => $user] = createSmeSettingsUser();

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('senderName', str_repeat('a', 101))
        ->call('saveSignature')
        ->assertHasErrors(['senderName']);
});

// ─── URL structure ───────────────────────────────────────────────────────

it('generates settings URL with pme prefix', function () {
    expect(route('pme.settings.index'))->toContain('/pme/settings');
});

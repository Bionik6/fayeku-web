<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use App\Services\Shared\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSmeCompanyForQuota(string $plan = 'basique'): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create([
        'type' => 'sme',
        'plan' => $plan,
    ]);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

function consumeReminderQuota(Company $company, int $count): void
{
    DB::table('quota_usage')->insert([
        'id' => (string) Str::ulid(),
        'company_id' => $company->id,
        'quota_type' => 'reminders',
        'period_start' => now()->startOfMonth()->toDateString(),
        'used' => $count,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ─── Service usage() ────────────────────────────────────────────────────────

test('QuotaService::usage retourne un snapshot complet', function () {
    ['company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 15);

    $usage = app(QuotaService::class)->usage($company, 'reminders');

    expect($usage)->toMatchArray([
        'used' => 15,
        'limit' => 20,
        'addons' => 0,
        'unlimited' => false,
        'available' => 5,
        'percent' => 75,
    ]);
});

test('QuotaService::usage indique unlimited pour le plan essentiel', function () {
    ['company' => $company] = createSmeCompanyForQuota('essentiel');

    $usage = app(QuotaService::class)->usage($company, 'reminders');

    expect($usage['unlimited'])->toBeTrue()
        ->and($usage['percent'])->toBeNull();
});

// ─── Composant quota-banner ─────────────────────────────────────────────────

test('la banniere ne s affiche pas quand le plan est illimite', function () {
    ['company' => $company] = createSmeCompanyForQuota('essentiel');

    $html = Blade::render('<x-shared.quota-banner :company="$company" />', compact('company'));

    expect(trim($html))->toBe('');
});

test('la banniere ne s affiche pas en dessous du seuil 80%', function () {
    ['company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 10); // 10/20 = 50%

    $html = Blade::render('<x-shared.quota-banner :company="$company" />', compact('company'));

    expect(trim($html))->toBe('');
});

test('la banniere warning s affiche au dela du seuil', function () {
    ['company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 17); // 17/20 = 85%

    $html = Blade::render('<x-shared.quota-banner :company="$company" />', compact('company'));

    expect($html)->toContain('bientôt atteint')
        ->toContain('border-amber-200')
        ->toContain('3')  // available
        ->toContain('Voir les plans');
});

test('la banniere epuise s affiche quand available = 0', function () {
    ['company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 20); // exhausted

    $html = Blade::render('<x-shared.quota-banner :company="$company" />', compact('company'));

    expect($html)->toContain('épuisé')
        ->toContain('border-rose-200')
        ->toContain('20/20');
});

// ─── Deep-link settings?section=plan ────────────────────────────────────────

test('Settings page accepte ?section=plan via #[Url]', function () {
    ['user' => $user] = createSmeCompanyForQuota();

    Livewire::actingAs($user)
        ->withQueryParams(['section' => 'plan'])
        ->test('pages::pme.settings.index')
        ->assertSet('activeSection', 'plan');
});

test('Settings page ignore les sections invalides dans l URL', function () {
    ['user' => $user] = createSmeCompanyForQuota();

    Livewire::actingAs($user)
        ->withQueryParams(['section' => 'hacker'])
        ->test('pages::pme.settings.index')
        ->assertSet('activeSection', 'company');
});

// ─── Bloc usage dans Settings > Plan ────────────────────────────────────────

test('l onglet Plan affiche le bloc Usage WhatsApp avec la progression', function () {
    ['user' => $user, 'company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 12); // 12/20 = 60%

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Usage WhatsApp ce mois-ci')
        ->assertSee('12')
        ->assertSee('60%');
});

test('l onglet Plan signale un quota epuise', function () {
    ['user' => $user, 'company' => $company] = createSmeCompanyForQuota('basique');
    consumeReminderQuota($company, 20);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('setSection', 'plan')
        ->assertSee('Épuisé')
        ->assertSee('Votre quota est épuisé');
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Services\ClientService;
use Modules\Shared\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function createSmeUserForQuery(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

/**
 * Count SME company queries in the log.
 * The query joins company_user and filters on type — the value 'sme' is in bindings.
 */
function countSmeCompanyQueries(): int
{
    return countCompanyQueriesForType('sme');
}

function countFirmCompanyQueries(): int
{
    return countCompanyQueriesForType('accountant_firm');
}

function countCompanyQueriesForType(string $type): int
{
    return collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'company_user')
            && str_contains($q['query'], '"type"')
            && in_array($type, $q['bindings'] ?? [], true))
        ->count();
}

// ─── User::smeCompany() ─────────────────────────────────────────────────────

test('smeCompany retourne la bonne entreprise SME', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuery();

    expect($user->smeCompany())
        ->toBeInstanceOf(Company::class)
        ->and($user->smeCompany()->id)->toBe($company->id);
});

test('smeCompany retourne null si l\'utilisateur n\'a pas de SME', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    expect($user->smeCompany())->toBeNull();
});

test('smeCompany ignore les cabinets comptables', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    expect($user->smeCompany())->toBeNull();
});

test('smeCompany ne lance qu\'une seule requête par instance', function () {
    ['user' => $user] = createSmeUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $user->smeCompany();
    $user->smeCompany();
    $user->smeCompany();

    expect(countSmeCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

// ─── ClientService::companyForUser() ────────────────────────────────────────

test('ClientService companyForUser délègue à smeCompany', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForQuery();

    $result = app(ClientService::class)->companyForUser($user);

    expect($result)->toBeInstanceOf(Company::class)
        ->and($result->id)->toBe($company->id);
});

// ─── Sidebar + page = pas de doublon ────────────────────────────────────────

test('la page PME clients ne duplique pas la requête SME company', function () {
    ['user' => $user] = createSmeUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('pme.clients.index'));

    expect(countSmeCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

test('la page PME dashboard ne duplique pas la requête SME company', function () {
    ['user' => $user] = createSmeUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('pme.dashboard'));

    expect(countSmeCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

test('la page PME recouvrement ne duplique pas la requête SME company', function () {
    ['user' => $user] = createSmeUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('pme.collection.index'));

    expect(countSmeCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

// ─── User::accountantFirm() ─────────────────────────────────────────────────

function createFirmUserForQuery(): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $sme = Company::factory()->create();
    AccountantCompany::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $sme->id,
        'started_at' => now()->subMonth(),
    ]);

    return compact('user', 'firm', 'sme');
}

test('accountantFirm retourne le bon cabinet comptable', function () {
    ['user' => $user, 'firm' => $firm] = createFirmUserForQuery();

    expect($user->accountantFirm())
        ->toBeInstanceOf(Company::class)
        ->and($user->accountantFirm()->id)->toBe($firm->id);
});

test('accountantFirm retourne null pour un utilisateur SME', function () {
    ['user' => $user] = createSmeUserForQuery();

    expect($user->accountantFirm())->toBeNull();
});

test('accountantFirm ne lance qu\'une seule requête par instance', function () {
    ['user' => $user] = createFirmUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $user->accountantFirm();
    $user->accountantFirm();
    $user->accountantFirm();

    expect(countFirmCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

test('la page compta dashboard ne duplique pas la requête firm', function () {
    ['user' => $user] = createFirmUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('dashboard'));

    expect(countFirmCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

test('la page compta clients ne duplique pas la requête firm', function () {
    ['user' => $user] = createFirmUserForQuery();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('clients.index'));

    expect(countFirmCompanyQueries())->toBe(1);

    DB::disableQueryLog();
});

<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSmeForLogo(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    return compact('user', 'company');
}

beforeEach(function () {
    Storage::fake();
});

// ─── Upload ──────────────────────────────────────────────────────────────────

test('upload d\'un PNG enregistre le logo et met à jour logo_path', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('logoUpload', $file);

    $company->refresh();

    expect($company->logo_path)->toBe("company-logos/{$company->id}.png");
    Storage::assertExists($company->logo_path);
});

test('upload d\'un JPG est accepté', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $file = UploadedFile::fake()->image('logo.jpg', 300, 300);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('logoUpload', $file)
        ->assertHasNoErrors('logoUpload');

    expect($company->fresh()->logo_path)->toBe("company-logos/{$company->id}.jpg");
});

// ─── Validation ──────────────────────────────────────────────────────────────

test('upload d\'un PDF est rejeté avec un message d\'erreur', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $file = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('logoUpload', $file)
        ->assertHasErrors('logoUpload');

    expect($company->fresh()->logo_path)->toBeNull();
});

test('upload supérieur à 1 Mo est rejeté', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    // 2 Mo
    $file = UploadedFile::fake()->image('big.png')->size(2048);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('logoUpload', $file)
        ->assertHasErrors('logoUpload');

    expect($company->fresh()->logo_path)->toBeNull();
});

// ─── Replace ─────────────────────────────────────────────────────────────────

test('remplacer un logo existant supprime l\'ancien fichier', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $oldPath = "company-logos/{$company->id}.png";
    Storage::put($oldPath, 'fake-old-content');
    $company->update(['logo_path' => $oldPath]);

    $newFile = UploadedFile::fake()->image('new.webp');

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->set('logoUpload', $newFile);

    $company->refresh();

    expect($company->logo_path)->toBe("company-logos/{$company->id}.webp");
    Storage::assertExists($company->logo_path);
    Storage::assertMissing($oldPath);
});

// ─── Remove ──────────────────────────────────────────────────────────────────

test('removeLogo supprime le fichier et vide logo_path', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $path = "company-logos/{$company->id}.png";
    Storage::put($path, 'fake-content');
    $company->update(['logo_path' => $path]);

    Livewire::actingAs($user)
        ->test('pages::pme.settings.index')
        ->call('removeLogo');

    expect($company->fresh()->logo_path)->toBeNull();
    Storage::assertMissing($path);
});

// ─── Serve route ─────────────────────────────────────────────────────────────

test('la route /pme/company/logo sert le logo de la société courante', function () {
    ['user' => $user, 'company' => $company] = createSmeForLogo();
    $path = "company-logos/{$company->id}.png";
    // Minimal valid 1x1 PNG (signature + IHDR + IDAT + IEND)
    Storage::put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII='));
    $company->update(['logo_path' => $path]);

    $response = $this->actingAs($user)->get(route('pme.company.logo'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/');
});

test('la route /pme/company/logo renvoie 404 si pas de logo', function () {
    ['user' => $user] = createSmeForLogo();

    $this->actingAs($user)
        ->get(route('pme.company.logo'))
        ->assertNotFound();
});

test('la route /pme/company/logo redirige les non-authentifiés', function () {
    $this->get(route('pme.company.logo'))->assertRedirect(route('login'));
});

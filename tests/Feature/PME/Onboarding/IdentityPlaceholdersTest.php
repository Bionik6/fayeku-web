<?php

use App\Enums\Auth\OnboardingIntent;
use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('step identity has company name and address placeholders + address optional chip', function () {
    $user = User::factory()->create([
        'profile_type' => 'sme',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);
    $user->update(['onboarding_intent' => OnboardingIntent::InvoiceFaster]);
    $company = Company::factory()->pendingOnboarding()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);

    $component = Livewire::actingAs($user)
        ->test('pages::pme.onboarding.index')
        ->set('currentStep', 1);

    $html = $component->html();

    expect($html)->toContain('placeholder="Nom de l&#039;entreprise"')
        ->and($html)->toContain('placeholder="Avenue Pompidou, Plateau, Dakar"');

    // Le label Adresse doit afficher la chip « facultatif » à côté du libellé.
    $addressMatch = preg_match('#Adresse[^<]*<span[^>]*>\s*facultatif\s*</span>#s', $html);
    expect($addressMatch)->toBe(1, 'la chip "facultatif" devrait être affichée à côté du libellé "Adresse"');
});

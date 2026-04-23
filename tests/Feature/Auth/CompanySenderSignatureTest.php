<?php

use App\Models\Auth\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('compose signature avec nom et role renvoie "Nom, Role Company"', function () {
    $company = Company::factory()->create([
        'name' => 'Sow BTP',
        'sender_name' => 'Ibrahima Ciss',
        'sender_role' => 'Manager',
    ]);

    expect($company->composeSenderSignature())->toBe('Ibrahima Ciss, Manager Sow BTP');
});

test('compose signature avec nom seul renvoie "Nom, Company"', function () {
    $company = Company::factory()->create([
        'name' => 'Sow BTP',
        'sender_name' => 'Ibrahima Ciss',
        'sender_role' => null,
    ]);

    expect($company->composeSenderSignature())->toBe('Ibrahima Ciss, Sow BTP');
});

test('compose signature sans nom ni role renvoie "L\'equipe Company"', function () {
    $company = Company::factory()->create([
        'name' => 'Sow BTP',
        'sender_name' => null,
        'sender_role' => null,
    ]);

    expect($company->composeSenderSignature())->toBe("L'équipe Sow BTP");
});

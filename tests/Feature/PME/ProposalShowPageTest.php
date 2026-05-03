<?php

use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSmeUserForProposal(): array
{
    $user = User::factory()->create(['profile_type' => 'sme']);
    $company = Company::factory()->create(['type' => 'sme']);
    $company->users()->attach($user->id, ['role' => 'owner']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    return compact('user', 'company', 'client');
}

test('quote show page renders the activity feed with lifecycle events', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeUserForProposal();

    $quote = ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withClient($client)
        ->withLines(2)
        ->create([
            'issued_at' => '2026-04-01',
            'valid_until' => '2026-05-01',
            'sent_at' => '2026-04-02 09:00:00',
            'accepted_at' => '2026-04-08 14:00:00',
        ]);

    $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSee('Activité')
        ->assertSee('Devis créé')
        ->assertSee('Devis envoyé')
        ->assertSee('Devis accepté')
        ->assertSee('Date de validité');
});

test('proforma show page renders po_received and converted events when present', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeUserForProposal();

    $proforma = ProposalDocument::factory()
        ->proforma()
        ->forCompany($company)
        ->withClient($client)
        ->withLines(2)
        ->create([
            'issued_at' => '2026-04-01',
            'valid_until' => '2026-05-15',
            'sent_at' => '2026-04-02 09:00:00',
            'po_reference' => 'BC-2026-001',
            'po_received_at' => '2026-04-06',
        ]);

    $this->actingAs($user)
        ->get(route('pme.proformas.show', $proforma))
        ->assertOk()
        ->assertSee('Activité')
        ->assertSee('Proforma créée')
        ->assertSee('Proforma envoyée')
        ->assertSee('Bon de commande reçu')
        ->assertSee('BC-2026-001');
});

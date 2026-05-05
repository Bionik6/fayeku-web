<?php

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
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

test('quote show page renders the current lifecycle after KPIs', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeUserForProposal();

    $quote = ProposalDocument::factory()
        ->quote()
        ->sent()
        ->forCompany($company)
        ->withClient($client)
        ->withLines(1)
        ->create([
            'sent_at' => now()->subDays(2),
            'valid_until' => now()->addDays(20),
        ]);

    $html = (string) $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSeeText('État : Envoyé — en attente de réponse client')
        ->getContent();

    expect($html)->toContain('data-document-lifecycle')
        ->and(strpos($html, 'Conversion'))->toBeLessThan(strpos($html, 'data-document-lifecycle'))
        ->and(strpos($html, 'data-document-lifecycle'))->toBeLessThan(strpos($html, 'Aperçu du devis'));
});

test('quote show page renders accepted factured declined and derived expired lifecycles', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeUserForProposal();

    $accepted = ProposalDocument::factory()
        ->quote()
        ->accepted()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(8),
            'accepted_at' => now()->subDay(),
        ]);

    $factured = ProposalDocument::factory()
        ->quote()
        ->accepted()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(10),
            'accepted_at' => now()->subDays(5),
        ]);
    Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'proposal_document_id' => $factured->id,
            'reference' => 'FYK-FAC-DS0701',
            'status' => InvoiceStatus::Draft,
        ]);

    $declined = ProposalDocument::factory()
        ->quote()
        ->declined()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(12),
            'declined_at' => now()->subDays(3),
        ]);

    $expired = ProposalDocument::factory()
        ->quote()
        ->sent()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(40),
            'valid_until' => now()->subDays(5),
        ]);

    $cases = [
        [$accepted, ['État : Accepté — à convertir en facture']],
        [$factured, ['État : Facturé — facture créée', 'FYK-FAC-DS0701']],
        [$declined, ['État : Refusé — sortie de cycle', 'Le devis a été marqué comme refusé']],
        [$expired, ['État : Expiré — durée de validité dépassée', 'La date de validité est dépassée']],
    ];

    foreach ($cases as [$quote, $expectedTexts]) {
        $response = $this->actingAs($user)
            ->get(route('pme.quotes.show', $quote))
            ->assertOk();

        foreach ($expectedTexts as $text) {
            $response->assertSeeText($text);
        }
    }
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

test('proforma show page renders sent purchase order factured declined and derived expired lifecycles', function () {
    ['user' => $user, 'company' => $company, 'client' => $client] = createSmeUserForProposal();

    $sent = ProposalDocument::factory()
        ->proforma()
        ->sent()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(2),
            'valid_until' => now()->addDays(20),
        ]);

    $poReceived = ProposalDocument::factory()
        ->proforma()
        ->poReceived()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(8),
            'po_reference' => 'BC-2026/0142',
            'po_received_at' => now()->subDay(),
        ]);

    $factured = ProposalDocument::factory()
        ->proforma()
        ->poReceived()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(10),
            'po_reference' => 'BC-2026/0143',
            'po_received_at' => now()->subDays(4),
        ]);
    Invoice::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'proposal_document_id' => $factured->id,
            'reference' => 'FYK-FAC-DS0801',
            'status' => InvoiceStatus::Draft,
        ]);

    $declined = ProposalDocument::factory()
        ->proforma()
        ->declined()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(12),
            'declined_at' => now()->subDays(2),
        ]);

    $expired = ProposalDocument::factory()
        ->proforma()
        ->sent()
        ->forCompany($company)
        ->withClient($client)
        ->create([
            'sent_at' => now()->subDays(35),
            'valid_until' => now()->subDays(4),
        ]);

    $cases = [
        [$sent, ['État : Envoyée — en attente du BC']],
        [$poReceived, ['État : BC reçu — prêt à facturer', 'BC-2026/0142']],
        [$factured, ['État : Facturée — facture créée', 'FYK-FAC-DS0801']],
        [$declined, ['État : Refusée — sortie de cycle', 'La proforma a été marquée comme refusée']],
        [$expired, ['État : Expirée — validité dépassée', 'Aucun BC reçu avant la date de validité']],
    ];

    foreach ($cases as [$proforma, $expectedTexts]) {
        $response = $this->actingAs($user)
            ->get(route('pme.proformas.show', $proforma))
            ->assertOk();

        foreach ($expectedTexts as $text) {
            $response->assertSeeText($text);
        }
    }
});

test('client card shows the avatar fallback when client has neither email nor phone', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForProposal();

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Sabira SASU',
        'email' => null,
        'phone' => null,
    ]);

    $proforma = ProposalDocument::factory()
        ->proforma()
        ->forCompany($company)
        ->withClient($client)
        ->withLines(1)
        ->create();

    $this->actingAs($user)
        ->get(route('pme.proformas.show', $proforma))
        ->assertOk()
        ->assertSee('Sabira SASU')
        ->assertSee('Nouveau client')
        ->assertSee('Coordonnées non renseignées')
        ->assertSee('SS'); // initials avatar
});

test('client card shows NINEA and RCCM when set, in regular text', function () {
    ['user' => $user, 'company' => $company] = createSmeUserForProposal();

    $client = Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'CBAO Groupe Attijariwafa',
        'email' => 'it@cbao.sn',
        'phone' => '+221338600008',
        'tax_id' => 'SN2024CBA0008',
        'rccm' => 'SN-DKR-2008-B-15422',
    ]);

    $quote = ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withClient($client)
        ->withLines(1)
        ->create();

    $response = $this->actingAs($user)
        ->get(route('pme.quotes.show', $quote))
        ->assertOk()
        ->assertSee('NINEA')
        ->assertSee('SN2024CBA0008')
        ->assertSee('RCCM')
        ->assertSee('SN-DKR-2008-B-15422');

    // No font-mono on NINEA / RCCM lines.
    expect($response->getContent())->not->toMatch('/font-mono[^"]*">[^<]*NINEA/');
});

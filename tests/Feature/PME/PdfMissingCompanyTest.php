<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * VERROU — résilience du template PDF face à une société manquante.
 *
 * En prod (demo.fayeku.sn) un document proforma s'est retrouvé orphelin : sa
 * société avait été supprimée en dur, donc `$doc->company` valait null et le
 * template `pdf/document.blade.php` plantait en 500 sur `$company->name`.
 *
 * Le PDF est servi par une URL publique partagée en SMS/WhatsApp : il doit se
 * dégrader proprement (PDF valide sans bloc société) plutôt que de renvoyer une
 * erreur 500 au client. Ces tests reproduisent l'orphelin sur les trois types
 * de document (devis, proforma, facture) qui partagent tous `pdf.document`.
 */

/**
 * Supprime physiquement la société en désactivant les contraintes FK, pour
 * recréer l'état orphelin observé en production.
 */
function orphanCompany(Company $company): void
{
    if ($company->getConnection()->getDriverName() === 'sqlite') {
        // `PRAGMA foreign_keys` est ignoré dans une transaction (RefreshDatabase) ;
        // `defer_foreign_keys` reporte la vérification FK au commit — qui n'arrive
        // jamais ici (rollback). La ligne société devient donc orpheline.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $company->delete();

        return;
    }

    Schema::disableForeignKeyConstraints();
    $company->delete();
    Schema::enableForeignKeyConstraints();
}

function bootstrapOrphanDocs(): array
{
    $company = Company::factory()->create(['type' => 'sme', 'name' => 'PME Test']);
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Client Démo']);

    $quote = ProposalDocument::factory()->quote()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ProposalDocumentStatus::Sent,
    ]);

    $proforma = ProposalDocument::factory()->proforma()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => ProposalDocumentStatus::Sent,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
    ]);

    return compact('company', 'client', 'quote', 'proforma', 'invoice');
}

// ─── La route publique ne plante plus quand la société a disparu ─────────────

test('GET /p/{code}/pdf — proforma orpheline : 200 + PDF, pas de 500', function () {
    ['company' => $company, 'proforma' => $proforma] = bootstrapOrphanDocs();
    orphanCompany($company);

    $response = $this->get('/p/'.$proforma->public_code.'/pdf');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('GET /d/{code}/pdf — devis orphelin : 200 + PDF, pas de 500', function () {
    ['company' => $company, 'quote' => $quote] = bootstrapOrphanDocs();
    orphanCompany($company);

    $response = $this->get('/d/'.$quote->public_code.'/pdf');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('GET /f/{code}/pdf — facture orpheline : 200 + PDF, pas de 500', function () {
    ['company' => $company, 'invoice' => $invoice] = bootstrapOrphanDocs();
    orphanCompany($company);

    $response = $this->get('/f/'.$invoice->public_code.'/pdf');

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

// ─── Le rendu Blade reste correct dans les deux sens ─────────────────────────

test('le template ne lit aucune propriété sur null quand la société manque', function () {
    ['company' => $company, 'proforma' => $proforma] = bootstrapOrphanDocs();
    orphanCompany($company);

    $orphan = ProposalDocument::query()
        ->with(['company', 'client', 'lines'])
        ->findOrFail($proforma->id);

    expect($orphan->company)->toBeNull();

    $html = view('pdf.document', [
        'doc' => $orphan,
        'type' => 'proforma',
        'logoBase64' => null,
    ])->render();

    // Le document reste identifiable : référence + destinataire toujours rendus.
    expect($html)
        ->toContain($orphan->reference)
        ->toContain('Client Démo');
});

test('le template affiche bien la société quand elle est présente (happy path)', function () {
    ['proforma' => $proforma] = bootstrapOrphanDocs();

    $doc = ProposalDocument::query()
        ->with(['company', 'client', 'lines'])
        ->findOrFail($proforma->id);

    $html = view('pdf.document', [
        'doc' => $doc,
        'type' => 'proforma',
        'logoBase64' => null,
    ])->render();

    expect($html)->toContain('PME Test');
});

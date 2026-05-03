<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Enum: ProposalDocumentType ─────────────────────────────────────────────

test('Type::referencePrefix returns FYK-DEV- for quote', function () {
    expect(ProposalDocumentType::Quote->referencePrefix())->toBe('FYK-DEV-');
});

test('Type::referencePrefix returns FYK-PRO- for proforma', function () {
    expect(ProposalDocumentType::Proforma->referencePrefix())->toBe('FYK-PRO-');
});

test('Type::label returns French human label', function () {
    expect(ProposalDocumentType::Quote->label())->toBe('Devis')
        ->and(ProposalDocumentType::Proforma->label())->toBe('Proforma');
});

// ─── Enum: ProposalDocumentStatus ────────────────────────────────────────────

test('Status::editable lists Draft and Sent', function () {
    expect(ProposalDocumentStatus::editable())
        ->toBe([ProposalDocumentStatus::Draft, ProposalDocumentStatus::Sent]);
});

test('Status::isAllowedFor restricts Accepted to quotes', function () {
    expect(ProposalDocumentStatus::Accepted->isAllowedFor(ProposalDocumentType::Quote))->toBeTrue()
        ->and(ProposalDocumentStatus::Accepted->isAllowedFor(ProposalDocumentType::Proforma))->toBeFalse();
});

test('Status::isAllowedFor restricts PoReceived and Converted to proformas', function () {
    expect(ProposalDocumentStatus::PoReceived->isAllowedFor(ProposalDocumentType::Proforma))->toBeTrue()
        ->and(ProposalDocumentStatus::PoReceived->isAllowedFor(ProposalDocumentType::Quote))->toBeFalse()
        ->and(ProposalDocumentStatus::Converted->isAllowedFor(ProposalDocumentType::Proforma))->toBeTrue()
        ->and(ProposalDocumentStatus::Converted->isAllowedFor(ProposalDocumentType::Quote))->toBeFalse();
});

test('Status::isAllowedFor allows shared states for both types', function () {
    foreach ([ProposalDocumentStatus::Draft, ProposalDocumentStatus::Sent, ProposalDocumentStatus::Declined, ProposalDocumentStatus::Expired] as $status) {
        expect($status->isAllowedFor(ProposalDocumentType::Quote))->toBeTrue()
            ->and($status->isAllowedFor(ProposalDocumentType::Proforma))->toBeTrue();
    }
});

// ─── Factory ────────────────────────────────────────────────────────────────

test('factory creates a quote by default with FYK-DEV- reference', function () {
    $document = ProposalDocument::factory()->create();

    expect($document->type)->toBe(ProposalDocumentType::Quote)
        ->and($document->reference)->toStartWith('FYK-DEV-')
        ->and($document->status)->toBe(ProposalDocumentStatus::Draft);
});

test('factory state ::proforma() yields a proforma with FYK-PRO- reference', function () {
    $document = ProposalDocument::factory()->proforma()->create();

    expect($document->type)->toBe(ProposalDocumentType::Proforma)
        ->and($document->reference)->toStartWith('FYK-PRO-');
});

test('factory withLines populates lines and recomputes totals', function () {
    $document = ProposalDocument::factory()->withLines(3)->create();

    expect($document->lines)->toHaveCount(3)
        ->and($document->fresh()->subtotal)->toBeGreaterThan(0)
        ->and($document->fresh()->total)->toBeGreaterThanOrEqual($document->fresh()->subtotal);
});

test('factory accepted() implies quote type', function () {
    $document = ProposalDocument::factory()->accepted()->create();

    expect($document->type)->toBe(ProposalDocumentType::Quote)
        ->and($document->status)->toBe(ProposalDocumentStatus::Accepted);
});

test('factory poReceived() and converted() imply proforma type', function () {
    $po = ProposalDocument::factory()->poReceived()->create();
    $converted = ProposalDocument::factory()->converted()->create();

    expect($po->type)->toBe(ProposalDocumentType::Proforma)
        ->and($po->status)->toBe(ProposalDocumentStatus::PoReceived)
        ->and($converted->type)->toBe(ProposalDocumentType::Proforma)
        ->and($converted->status)->toBe(ProposalDocumentStatus::Converted);
});

// ─── Model: scopes ──────────────────────────────────────────────────────────

test('scope quotes() returns only quotes', function () {
    ProposalDocument::factory()->quote()->count(3)->create();
    ProposalDocument::factory()->proforma()->count(2)->create();

    expect(ProposalDocument::query()->quotes()->count())->toBe(3);
});

test('scope proformas() returns only proformas', function () {
    ProposalDocument::factory()->quote()->count(3)->create();
    ProposalDocument::factory()->proforma()->count(2)->create();

    expect(ProposalDocument::query()->proformas()->count())->toBe(2);
});

test('scope ofType() filters by type', function () {
    ProposalDocument::factory()->quote()->count(3)->create();
    ProposalDocument::factory()->proforma()->count(2)->create();

    expect(ProposalDocument::query()->ofType(ProposalDocumentType::Quote)->count())->toBe(3)
        ->and(ProposalDocument::query()->ofType(ProposalDocumentType::Proforma)->count())->toBe(2);
});

// ─── Model: helpers ─────────────────────────────────────────────────────────

test('isQuote / isProforma reflect the type', function () {
    $quote = ProposalDocument::factory()->quote()->create();
    $proforma = ProposalDocument::factory()->proforma()->create();

    expect($quote->isQuote())->toBeTrue()
        ->and($quote->isProforma())->toBeFalse()
        ->and($proforma->isQuote())->toBeFalse()
        ->and($proforma->isProforma())->toBeTrue();
});

test('typeLabel accessor exposes the localized label', function () {
    $quote = ProposalDocument::factory()->quote()->create();

    expect($quote->type_label)->toBe('Devis');
});

// ─── Model: persistence helpers (HasUlid, HasPublicCode) ────────────────────

test('a created proposal document gets a ULID id and unique 8-char public_code', function () {
    $document = ProposalDocument::factory()->create();

    expect($document->id)->toBeString()->toHaveLength(26)
        ->and($document->public_code)->toBeString()->toHaveLength(8);
});

test('public_code is unique across types', function () {
    ProposalDocument::factory()->quote()->count(5)->create();
    ProposalDocument::factory()->proforma()->count(5)->create();

    $codes = ProposalDocument::query()->pluck('public_code')->all();

    expect($codes)->toHaveCount(10)
        ->and(array_unique($codes))->toHaveCount(10);
});

// ─── Model: relations ───────────────────────────────────────────────────────

test('belongs to a company and a client', function () {
    $company = Company::factory()->create(['type' => 'sme']);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $document = ProposalDocument::factory()
        ->forCompany($company)
        ->withClient($client)
        ->create();

    expect($document->company->id)->toBe($company->id)
        ->and($document->client->id)->toBe($client->id);
});

// ─── Model: timeline ────────────────────────────────────────────────────────

test('timeline emits created + valid_until events for a fresh proforma', function () {
    $proforma = ProposalDocument::factory()->proforma()->create([
        'issued_at' => '2026-04-01',
        'valid_until' => '2026-05-15',
    ]);

    $types = $proforma->timeline()->pluck('type')->all();

    expect($types)->toContain('created')
        ->and($types)->toContain('valid_until');
});

test('timeline includes sent, accepted and converted events when timestamps are set', function () {
    $proforma = ProposalDocument::factory()->proforma()->create([
        'issued_at' => '2026-04-01',
        'valid_until' => '2026-05-15',
        'sent_at' => '2026-04-02 09:00:00',
        'accepted_at' => '2026-04-05 14:30:00',
        'converted_at' => '2026-04-08 10:00:00',
    ]);

    $types = $proforma->timeline()->pluck('type')->all();

    expect($types)->toContain('sent')
        ->and($types)->toContain('accepted')
        ->and($types)->toContain('converted');
});

test('timeline orders events chronologically', function () {
    $quote = ProposalDocument::factory()->quote()->create([
        'issued_at' => '2026-04-01',
        'valid_until' => '2026-05-01',
        'sent_at' => '2026-04-03 09:00:00',
        'declined_at' => '2026-04-10 16:00:00',
    ]);

    $events = $quote->timeline();

    expect($events->pluck('type')->all())
        ->toBe(['created', 'sent', 'declined', 'valid_until']);
});

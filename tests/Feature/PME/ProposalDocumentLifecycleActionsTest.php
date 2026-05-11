<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Events\PME\ProposalDocumentAccepted;
use App\Events\PME\ProposalDocumentConverted;
use App\Models\Auth\Company;
use App\Models\PME\ProposalDocument;
use App\Services\PME\ProposalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function makeLifecycleCompany(): Company
{
    return Company::factory()->create(['type' => 'sme']);
}

function makeQuoteFor(Company $company, ProposalDocumentStatus $status, array $overrides = []): ProposalDocument
{
    return ProposalDocument::factory()
        ->quote()
        ->forCompany($company)
        ->withLines(2)
        ->create(array_merge(['status' => $status], $overrides));
}

function makeProformaFor(Company $company, ProposalDocumentStatus $status, array $overrides = []): ProposalDocument
{
    return ProposalDocument::factory()
        ->proforma()
        ->forCompany($company)
        ->withLines(2)
        ->create(array_merge(['status' => $status], $overrides));
}

// ─── markAsCancelled ─────────────────────────────────────────────────────────

it('cancels a sent quote with a reason', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent);

    app(ProposalDocumentService::class)->markAsCancelled($quote, 'Le client a changé d\'avis');

    expect($quote->fresh())
        ->status->toBe(ProposalDocumentStatus::Cancelled)
        ->cancelled_at->not->toBeNull()
        ->cancellation_reason->toBe('Le client a changé d\'avis');
});

it('rejects cancellation with an empty reason', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent);

    expect(fn () => app(ProposalDocumentService::class)->markAsCancelled($quote, '   '))
        ->toThrow(DomainException::class);
});

it('rejects cancellation of a converted proforma', function () {
    $proforma = makeProformaFor(makeLifecycleCompany(), ProposalDocumentStatus::Converted);

    expect(fn () => app(ProposalDocumentService::class)->markAsCancelled($proforma, 'motif'))
        ->toThrow(DomainException::class);
});

it('rejects double cancellation', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Cancelled);

    expect(fn () => app(ProposalDocumentService::class)->markAsCancelled($quote, 'motif'))
        ->toThrow(DomainException::class);
});

// ─── extendValidity ──────────────────────────────────────────────────────────

it('extends validity of an expired quote and reverts status to sent', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Expired, [
        'valid_until' => now()->subDays(5)->toDateString(),
    ]);

    app(ProposalDocumentService::class)->extendValidity($quote, now()->addDays(15));

    $fresh = $quote->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Sent);
    expect($fresh->valid_until->toDateString())->toBe(now()->addDays(15)->toDateString());
    expect($fresh->validity_extended_at)->not->toBeNull();
});

it('extends validity of an implicitly-expired sent quote and reverts status to sent', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent, [
        'valid_until' => now()->subDays(2)->toDateString(),
    ]);

    app(ProposalDocumentService::class)->extendValidity($quote, now()->addDays(10));

    expect($quote->fresh()->status)->toBe(ProposalDocumentStatus::Sent);
});

it('keeps status untouched when extending an already-active sent quote', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent, [
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    app(ProposalDocumentService::class)->extendValidity($quote, now()->addDays(30));

    $fresh = $quote->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Sent);
    expect($fresh->valid_until->toDateString())->toBe(now()->addDays(30)->toDateString());
});

it('rejects an extension date that is not in the future of current validity', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent, [
        'valid_until' => now()->addDays(10)->toDateString(),
    ]);

    expect(fn () => app(ProposalDocumentService::class)->extendValidity($quote, now()->addDays(5)))
        ->toThrow(DomainException::class);
});

// ─── archive ─────────────────────────────────────────────────────────────────

it('archives a declined quote', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Declined);

    app(ProposalDocumentService::class)->archive($quote);

    expect($quote->fresh()->archived_at)->not->toBeNull();
});

it('archives an expired proforma', function () {
    $proforma = makeProformaFor(makeLifecycleCompany(), ProposalDocumentStatus::Expired);

    app(ProposalDocumentService::class)->archive($proforma);

    expect($proforma->fresh()->archived_at)->not->toBeNull();
});

it('refuses to archive a sent quote', function () {
    $quote = makeQuoteFor(makeLifecycleCompany(), ProposalDocumentStatus::Sent);

    expect(fn () => app(ProposalDocumentService::class)->archive($quote))
        ->toThrow(DomainException::class);
});

// ─── duplicate ──────────────────────────────────────────────────────────────

it('duplicates a quote as a new draft with fresh dates and lines', function () {
    $company = makeLifecycleCompany();
    $original = makeQuoteFor($company, ProposalDocumentStatus::Sent, [
        'notes' => 'Notes originales',
        'issued_at' => now()->subDays(20)->toDateString(),
    ]);

    $copy = app(ProposalDocumentService::class)->duplicate($original, $company);

    expect($copy->status)->toBe(ProposalDocumentStatus::Draft);
    expect($copy->reference)->not->toBe($original->reference);
    expect($copy->reference)->toStartWith('FYK-DEV-');
    expect($copy->notes)->toBe('Notes originales');
    expect($copy->issued_at->toDateString())->toBe(now()->toDateString());
    expect($copy->lines)->toHaveCount($original->lines->count());
});

it('duplicates a proforma keeping the proforma type and prefix', function () {
    $company = makeLifecycleCompany();
    $proforma = makeProformaFor($company, ProposalDocumentStatus::Sent);

    $copy = app(ProposalDocumentService::class)->duplicate($proforma, $company);

    expect($copy->type)->toBe(ProposalDocumentType::Proforma);
    expect($copy->reference)->toStartWith('FYK-PRO-');
});

// ─── proforma & quote double-trace shortcut ─────────────────────────────────

it('accepts and converts a sent proforma in a single call with two distinct timestamps', function () {
    Event::fake([ProposalDocumentAccepted::class, ProposalDocumentConverted::class]);
    $company = makeLifecycleCompany();
    $proforma = makeProformaFor($company, ProposalDocumentStatus::Sent);

    $invoice = app(ProposalDocumentService::class)->convertToInvoice($proforma, $company);

    $fresh = $proforma->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Converted);
    expect($fresh->accepted_at)->not->toBeNull();
    expect($fresh->converted_at)->not->toBeNull();
    expect($fresh->accepted_at->lessThan($fresh->converted_at))->toBeTrue();
    expect($invoice->status)->toBe(InvoiceStatus::Draft);

    Event::assertDispatched(ProposalDocumentAccepted::class);
    Event::assertDispatched(ProposalDocumentConverted::class);
});

it('also accepts and converts a sent quote with the accepted event', function () {
    Event::fake([ProposalDocumentAccepted::class, ProposalDocumentConverted::class]);
    $company = makeLifecycleCompany();
    $quote = makeQuoteFor($company, ProposalDocumentStatus::Sent);

    app(ProposalDocumentService::class)->convertToInvoice($quote, $company);

    $fresh = $quote->fresh();
    expect($fresh->status)->toBe(ProposalDocumentStatus::Accepted);
    expect($fresh->accepted_at)->not->toBeNull();

    Event::assertDispatched(ProposalDocumentAccepted::class);
});

it('does not re-accept an already-accepted quote when converting', function () {
    Event::fake([ProposalDocumentAccepted::class]);
    $company = makeLifecycleCompany();
    $quote = makeQuoteFor($company, ProposalDocumentStatus::Accepted, [
        'accepted_at' => now()->subDay(),
    ]);

    app(ProposalDocumentService::class)->convertToInvoice($quote, $company);

    Event::assertNotDispatched(ProposalDocumentAccepted::class);
});

<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeInvoiceLifecycleCompany(): Company
{
    return Company::factory()->create(['type' => 'sme']);
}

function makeInvoiceLifecycle(Company $company, InvoiceStatus $status, array $overrides = []): Invoice
{
    return Invoice::factory()
        ->forCompany($company)
        ->withLines(2)
        ->create(array_merge(['status' => $status], $overrides));
}

// ─── markAsCancelled ─────────────────────────────────────────────────────────

it('cancels a sent invoice with a reason', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Sent);

    app(InvoiceService::class)->markAsCancelled($invoice, 'Erreur client');

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Cancelled);
    expect($fresh->cancelled_at)->not->toBeNull();
    expect($fresh->cancellation_reason)->toBe('Erreur client');
});

it('rejects cancellation of a draft invoice', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Draft);

    expect(fn () => app(InvoiceService::class)->markAsCancelled($invoice, 'motif'))
        ->toThrow(DomainException::class);
});

it('rejects cancellation with an empty reason', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Sent);

    expect(fn () => app(InvoiceService::class)->markAsCancelled($invoice, '   '))
        ->toThrow(DomainException::class);
});

// ─── duplicate ──────────────────────────────────────────────────────────────

it('duplicates an invoice as a draft without copying payments', function () {
    $company = makeInvoiceLifecycleCompany();
    $invoice = makeInvoiceLifecycle($company, InvoiceStatus::Paid, [
        'notes' => 'Notes facture',
        'amount_paid' => 100_000,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000,
        'paid_at' => now()->subDay(),
        'method' => PaymentMethod::Cash,
        'recorded_by' => null,
    ]);

    $copy = app(InvoiceService::class)->duplicate($invoice, $company);

    expect($copy->status)->toBe(InvoiceStatus::Draft);
    expect($copy->reference)->not->toBe($invoice->reference);
    expect($copy->reference)->toStartWith('FYK-FAC-');
    expect($copy->amount_paid)->toBe(0);
    expect($copy->payments()->count())->toBe(0);
    expect($copy->notes)->toBe('Notes facture');
});

// ─── archive ─────────────────────────────────────────────────────────────────

it('archives a cancelled invoice', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Cancelled, [
        'cancelled_at' => now(),
        'cancellation_reason' => 'X',
    ]);

    app(InvoiceService::class)->archive($invoice);

    expect($invoice->fresh()->archived_at)->not->toBeNull();
});

it('refuses to archive a paid invoice', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Paid);

    expect(fn () => app(InvoiceService::class)->archive($invoice))
        ->toThrow(DomainException::class);
});

// ─── deleteDraft ────────────────────────────────────────────────────────────

it('release strategy force-deletes a draft', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Draft);
    $id = $invoice->id;

    app(InvoiceService::class)->deleteDraft($invoice, 'release');

    expect(Invoice::withTrashed()->find($id))->toBeNull();
});

it('vacant strategy soft-deletes a draft and marks it archived', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Draft);
    $id = $invoice->id;

    app(InvoiceService::class)->deleteDraft($invoice, 'vacant');

    expect(Invoice::query()->find($id))->toBeNull();
    $trashed = Invoice::withTrashed()->find($id);
    expect($trashed)->not->toBeNull();
    expect($trashed->archived_at)->not->toBeNull();
});

it('rejects deleteDraft on a non-draft invoice', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Sent);

    expect(fn () => app(InvoiceService::class)->deleteDraft($invoice, 'release'))
        ->toThrow(DomainException::class);
});

it('rejects an invalid deleteDraft strategy', function () {
    $invoice = makeInvoiceLifecycle(makeInvoiceLifecycleCompany(), InvoiceStatus::Draft);

    expect(fn () => app(InvoiceService::class)->deleteDraft($invoice, 'unknown'))
        ->toThrow(DomainException::class);
});

<?php

namespace App\Http\Controllers\PME;

use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Models\PME\ProposalDocumentLine;
use App\Services\PME\PdfService;
use App\Services\PME\ProposalDocumentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Streams an in-memory PDF preview of a proforma or quote from cached
 * form data — never persists anything to the database. Used by the
 * create/edit form "Aperçu PDF" button so a user can preview without
 * leaving a draft behind.
 */
class ProposalDocumentPreviewController
{
    public const QUOTE_CACHE_PREFIX = 'pme.preview.quote.';

    public const PROFORMA_CACHE_PREFIX = 'pme.preview.proforma.';

    public function quote(string $tempId, PdfService $pdf, ProposalDocumentService $service): Response
    {
        return $this->respond(self::QUOTE_CACHE_PREFIX, ProposalDocumentType::Quote, $tempId, $pdf, $service);
    }

    public function proforma(string $tempId, PdfService $pdf, ProposalDocumentService $service): Response
    {
        return $this->respond(self::PROFORMA_CACHE_PREFIX, ProposalDocumentType::Proforma, $tempId, $pdf, $service);
    }

    private function respond(string $prefix, ProposalDocumentType $type, string $tempId, PdfService $pdf, ProposalDocumentService $service): Response
    {
        $payload = Cache::get($prefix.$tempId);
        abort_unless(is_array($payload), 404);

        $company = Company::query()->find($payload['company_id'] ?? null);
        abort_unless($company, 404);
        abort_unless(
            (bool) auth()->user()?->companies()->where('companies.id', $company->id)->exists(),
            403,
        );

        $document = $this->buildPreviewDocument($payload, $company, $type, $service);

        return $pdf->streamDocument($document);
    }

    /**
     * @param  array{company_id: string, data: array<string, mixed>, lines: array<int, array<string, mixed>>}  $payload
     */
    private function buildPreviewDocument(array $payload, Company $company, ProposalDocumentType $type, ProposalDocumentService $service): ProposalDocument
    {
        $data = $payload['data'];
        $lines = $payload['lines'];

        $taxRate = (int) ($data['tax_rate'] ?? 0);
        $totals = $service->calculateTotals(
            $lines,
            $taxRate,
            (int) ($data['discount'] ?? 0),
            $data['discount_type'] ?? 'percent',
        );

        $document = new ProposalDocument([
            'company_id' => $company->id,
            'client_id' => $data['client_id'] ?? null,
            'type' => $type,
            'reference' => $data['reference'] ?? '—',
            'currency' => $data['currency'] ?? 'XOF',
            'issued_at' => $data['issued_at'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'discount' => (int) ($data['discount'] ?? 0),
            'discount_type' => $data['discount_type'] ?? 'percent',
            'notes' => $data['notes'] ?? null,
            'dossier_reference' => $data['dossier_reference'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'delivery_terms' => $data['delivery_terms'] ?? null,
        ]);

        $document->setRelation('company', $company);

        $client = ! empty($data['client_id'])
            ? Client::query()->where('id', $data['client_id'])->where('company_id', $company->id)->first()
            : null;
        $document->setRelation('client', $client);

        $linesCollection = collect($lines)->map(function (array $line) use ($taxRate) {
            return new ProposalDocumentLine([
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => (int) ($line['unit_price'] ?? 0),
                'tax_rate' => $taxRate,
                'total' => (int) ($line['quantity'] ?? 0) * (int) ($line['unit_price'] ?? 0),
            ]);
        });
        $document->setRelation('lines', $linesCollection);

        return $document;
    }
}

@php
    use App\Services\PME\CurrencyService;

    /**
     * Template PDF unifié — factures, devis, proformas.
     *
     * @var \App\Models\PME\Invoice|\App\Models\PME\ProposalDocument $doc
     * @var 'invoice'|'quote'|'proforma' $type
     * @var string|null $logoBase64
     */
    $isInvoice = $type === 'invoice';
    $isQuote = $type === 'quote';
    $isProforma = $type === 'proforma';

    $company = $doc->company;
    $client = $doc->client;
    $currency = $doc->currency ?? 'XOF';

    $docTypeLabel = match ($type) {
        'invoice' => __('FACTURE N°'),
        'quote' => __('DEVIS N°'),
        'proforma' => __('PROFORMA N°'),
    };

    $firstDateLabel = __("Date d'émission: ");
    $secondDateLabel = $isInvoice ? __("Date d'échéance: ") : __("Valide jusqu'au: ");
    $secondDate = $isInvoice ? $doc->due_at : $doc->valid_until;

    $uniqueTaxRates = $doc->lines->where('tax_rate', '>', 0)->pluck('tax_rate')->unique();
    $singleTaxRate = $uniqueTaxRates->count() === 1 ? $uniqueTaxRates->first() : null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTypeLabel }} {{ $doc->reference }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1F2937;
            line-height: 1.5;
            padding: 56px 60px 80px 60px;
        }

        /* ── Header ── */
        .header { width: 100%; border-collapse: collapse; margin-bottom: 36px; }
        .header td { vertical-align: top; padding: 0; }
        .logo-cell { width: 60%; }
        .logo-img { max-height: 64px; max-width: 180px; margin-bottom: 16px; }
        .company-name { font-size: 14pt; font-weight: bold; color: #0F172A; }
        .company-line { font-size: 9.5pt; color: #5A6B66; margin-top: 2px; }

        .doc-cell { text-align: right; padding-top: 0; line-height: 1.2; }
        .doc-label { font-size: 10pt; color: #5A6B66; letter-spacing: 1.4px; line-height: 1; font-weight: bold; }
        .doc-ref { font-size: 14pt; font-weight: bold; color: #1F4E4A; line-height: 1; margin-top: 4px; }
        .doc-date { font-size: 10pt; color: #5A6B66; line-height: 1.3; margin-top: 0; }
        .doc-date.first { margin-top: 4px; }

        /* ── Facturé à ── */
        .billed-card {
            background-color: #E8F0EC;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 38px;
        }
        .billed-label { font-size: 8pt; color: #5A6B66; letter-spacing: 1.2px; margin-bottom: 0; line-height: 1.2; }
        .billed-name { font-size: 14pt; font-weight: bold; color: #0F172A; line-height: 1.3; margin-top: 2px; }
        .billed-detail { font-size: 9.5pt; color: #5A6B66; line-height: 1.3; margin-top: 2px; }
        .billed-detail:first-of-type { margin-top: 4px; }

        /* ── Items ── */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .items thead th {
            font-size: 10pt; font-weight: bold;
            color: #1F4E4A;
            padding: 4px 8px 12px 8px;
            text-align: left;
            border-bottom: 1.5px solid #1F4E4A;
        }
        .items thead th.center { text-align: center; }
        .items thead th.right { text-align: right; }
        .items tbody td {
            padding: 14px 8px; font-size: 10pt;
            color: #1F2937; border-bottom: 1px solid #E2E8F0; vertical-align: top;
        }
        .items tbody td.center { text-align: center; font-variant-numeric: tabular-nums; }
        .items tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }

        /* ── Totals ── */
        .totals-wrapper { width: 100%; margin-top: 24px; }
        .totals { margin-left: auto; border-collapse: collapse; width: 360px; }
        .totals td { padding: 6px 8px; font-size: 12pt; }
        .totals .label { color: #5A6B66; text-align: left; }
        .totals .value {
            text-align: right; color: #5A6B66;
            font-variant-numeric: tabular-nums;
        }
        .totals .total-row td {
            border-top: 1.5px solid #1F4E4A;
            padding-top: 16px; padding-bottom: 4px;
            font-weight: bold; color: #1F4E4A;
            font-size: 14pt;
        }
        .totals .discount .value { color: #DC2626; }
        .totals .deposit .value { color: #059669; }
        .totals .remaining td {
            border-top: 1px solid #E2E8F0;
            padding-top: 14px; padding-bottom: 4px;
            font-weight: bold; font-size: 11pt; color: #1F4E4A;
        }

        /* ── Conditions / Notes ── */
        .terms-block { margin-top: 26px; }
        .terms-title { font-size: 8pt; color: #5A6B66; letter-spacing: 1.4px; margin-bottom: 10px; }
        .terms-grid { width: 100%; border-collapse: collapse; }
        .terms-grid td { vertical-align: top; padding: 6px 14px 6px 0; font-size: 9.5pt; color: #5A6B66; width: 33%; }
        .terms-grid .terms-label { font-size: 8pt; color: #5A6B66; letter-spacing: 1.2px; margin-bottom: 4px; }
        .terms-grid .terms-value { color: #1F2937; font-weight: bold; }

        .non-binding {
            margin-top: 22px;
            background-color: #FFF7ED;
            border: 1px solid #FED7AA;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 9pt;
            color: #9A3412;
        }

        .notes-block { margin-top: 26px; padding-top: 14px; border-top: 1px solid #E2E8F0; }
        .notes-label { font-size: 8pt; color: #5A6B66; letter-spacing: 1.4px; margin-bottom: 6px; }
        .notes-content { font-size: 9.5pt; color: #5A6B66; line-height: 1.6; }

        /* ── Footer ── */
        .footer {
            position: fixed;
            bottom: 30px; left: 60px; right: 60px;
            font-size: 7.5pt; color: #5A6B66;
            border-top: 1px solid #E2E8F0;
            padding-top: 8px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { padding: 0; vertical-align: bottom; }
        .footer-right { text-align: right; }
        .footer-fayeku { color: #1F4E4A; font-weight: bold; text-decoration: none; }
    </style>
</head>
<body>
{{-- Header --}}
<table class="header">
    <tr>
        <td class="logo-cell">
            @if ($logoBase64)
                <img class="logo-img" src="{{ $logoBase64 }}" alt="{{ $company?->name }}">
            @endif
            @if ($company)
                <div class="company-name">{{ $company->name }}</div>
                @php
                    $cityLine = trim(($company->address ?? '').($company->address && $company->city ? ', ' : '').($company->city ?? ''));
                @endphp
                @if ($cityLine !== '')
                    <div class="company-line">{{ $cityLine }}</div>
                @endif
                @if ($company->phone || $company->email)
                    <div class="company-line">
                        @if ($company->phone){{ format_phone($company->phone) }}@endif
                        @if ($company->phone && $company->email) · @endif
                        @if ($company->email){{ $company->email }}@endif
                    </div>
                @endif
                @if ($company->ninea || $company->rccm)
                    <div class="company-line">
                        @if ($company->ninea)NINEA: {{ $company->ninea }}@endif
                        @if ($company->ninea && $company->rccm) · @endif
                        @if ($company->rccm)RCCM: {{ $company->rccm }}@endif
                    </div>
                @endif
            @endif
        </td>
        <td class="doc-cell">
            <div class="doc-label">{{ $docTypeLabel }}</div>
            <div class="doc-ref">{{ $doc->reference }}</div>
            <div class="doc-date first">{{ $firstDateLabel }} {{ $doc->issued_at->locale('fr_FR')->translatedFormat('j F Y') }}</div>
            <div class="doc-date">{{ $secondDateLabel }} {{ $secondDate->locale('fr_FR')->translatedFormat('j F Y') }}</div>
        </td>
    </tr>
</table>

{{-- Facturé à --}}
<div class="billed-card">
    <div class="billed-label">{{ $isQuote ? __('DESTINATAIRE') : __('FACTURÉ À') }}</div>
    @if ($client)
        <div class="billed-name">{{ $client->name }}</div>
        @if ($client->address)
            <div class="billed-detail">{{ $client->address }}</div>
        @endif
        @if ($client->phone || $client->email)
            <div class="billed-detail">
                @if ($client->phone){{ format_phone($client->phone) }}@endif
                @if ($client->phone && $client->email) · @endif
                @if ($client->email){{ $client->email }}@endif
            </div>
        @endif
        @if ($client->tax_id || $client->rccm)
            <div class="billed-detail">
                @if ($client->tax_id)NINEA: {{ $client->tax_id }}@endif
                @if ($client->tax_id && $client->rccm) · @endif
                @if ($client->rccm)RCCM: {{ $client->rccm }}@endif
            </div>
        @endif
    @else
        <div class="billed-name">—</div>
    @endif
</div>

{{-- Items --}}
<table class="items">
    <thead>
    <tr>
        <th>{{ __('Description') }}</th>
        <th class="center" style="width: 10%;">{{ __('Qté') }}</th>
        <th class="right" style="width: 22%;">{{ __('Prix unitaire') }}</th>
        <th class="right" style="width: 24%;">{{ __('Montant HT') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($doc->lines as $line)
        <tr>
            <td>{!! nl2br(e($line->description)) !!}</td>
            <td class="center">{{ $line->quantity }}</td>
            <td class="right">{{ CurrencyService::format($line->unit_price, $currency) }}</td>
            <td class="right">{{ CurrencyService::format($line->total, $currency) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Totals --}}
<div class="totals-wrapper">
    <table class="totals">
        <tr>
            <td class="label">{{ __('Sous-total HT') }}</td>
            <td class="value">{{ CurrencyService::format($doc->subtotal, $currency) }}</td>
        </tr>
        @if ($doc->discount > 0)
            @php
                $discountAmount = ($doc->discount_type ?? 'percent') === 'fixed'
                    ? $doc->discount
                    : (int) round($doc->subtotal * $doc->discount / 100);
                $discountLabel = ($doc->discount_type ?? 'percent') === 'fixed'
                    ? __('Remise (montant fixe)')
                    : __('Remise (:rate%)', ['rate' => $doc->discount]);
            @endphp
            <tr class="discount">
                <td class="label">{{ $discountLabel }}</td>
                <td class="value">-{{ CurrencyService::format($discountAmount, $currency) }}</td>
            </tr>
        @endif
        @if ($doc->tax_amount > 0)
            <tr>
                <td class="label">{{ __('TVA') }}@if ($singleTaxRate !== null) {{ $singleTaxRate }}%@endif</td>
                <td class="value">{{ CurrencyService::format($doc->tax_amount, $currency) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td class="label">{{ __('Total TTC') }}</td>
            <td class="value">{{ CurrencyService::format($doc->total, $currency) }}</td>
        </tr>
        @if ($isInvoice && $doc->deposit_amount > 0)
            @php
                $depositResolved = min((int) $doc->deposit_amount, (int) $doc->total);
                $remaining = max(0, (int) $doc->total - $depositResolved);
            @endphp
            <tr class="deposit">
                <td class="label">{{ __('Acompte versé') }}</td>
                <td class="value">-{{ CurrencyService::format($depositResolved, $currency) }}</td>
            </tr>
            <tr class="remaining">
                <td class="label">{{ __('Reste à payer') }}</td>
                <td class="value">{{ CurrencyService::format($remaining, $currency) }}</td>
            </tr>
        @endif
    </table>
</div>

{{-- Conditions (proforma uniquement) --}}
@if ($isProforma && ($doc->dossier_reference || $doc->payment_terms || $doc->delivery_terms))
    <div class="terms-block">
        <div class="terms-title">{{ __('CONDITIONS') }}</div>
        <table class="terms-grid">
            <tr>
                @if ($doc->dossier_reference)
                    <td>
                        <div class="terms-label">{{ __('Référence dossier') }}</div>
                        <div class="terms-value">{{ $doc->dossier_reference }}</div>
                    </td>
                @endif
                @if ($doc->payment_terms)
                    <td>
                        <div class="terms-label">{{ __('Paiement') }}</div>
                        <div class="terms-value">{{ $doc->payment_terms }}</div>
                    </td>
                @endif
                @if ($doc->delivery_terms)
                    <td>
                        <div class="terms-label">{{ __('Délai') }}</div>
                        <div class="terms-value">{{ $doc->delivery_terms }}</div>
                    </td>
                @endif
            </tr>
        </table>
    </div>
@endif

{{-- Moyen de paiement (facture uniquement) --}}
@if ($isInvoice && $doc->payment_method)
    @php
        $paymentLabels = [
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'cash' => __('Espèces'),
            'bank_transfer' => __('Virement bancaire'),
        ];
    @endphp
    <div class="notes-block">
        <div class="notes-label">{{ __('MOYEN DE PAIEMENT') }}</div>
        <div class="notes-content">
            {{ $paymentLabels[$doc->payment_method] ?? $doc->payment_method }}@if ($doc->payment_details) — {!! nl2br(e($doc->payment_details)) !!}@endif
        </div>
    </div>
@endif

{{-- Notes --}}
@if ($doc->notes)
    <div class="notes-block">
        <div class="notes-label">{{ __('NOTES') }}</div>
        <div class="notes-content">{!! nl2br(e($doc->notes)) !!}</div>
    </div>
@endif

{{-- Mention non-engagement (proforma uniquement) --}}
@if ($isProforma)
    <div class="non-binding">
        {{ __('Document non comptable, à titre informatif. Valable jusqu\'au :date et soumis à confirmation par bon de commande.', ['date' => $doc->valid_until->locale('fr_FR')->translatedFormat('j F Y')]) }}
    </div>
@endif

{{-- Footer --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td>Par <a href="https://fayeku.sn" class="footer-fayeku">Fayeku</a></td>
            <td class="footer-right">{{ $company?->name }}</td>
        </tr>
    </table>
</div>
</body>
</html>

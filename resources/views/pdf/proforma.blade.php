@php
    use App\Services\PME\CurrencyService;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Facture Proforma') }} {{ $proforma->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1e293b;
            line-height: 1.5;
            padding: 40px 50px;
        }

        /* ── Header ── */
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .header td {
            vertical-align: middle;
            padding: 0;
        }
        .invoice-title {
            font-size: 26pt;
            font-weight: bold;
            color: #024D4D;
            letter-spacing: -0.5px;
        }
        .invoice-ref {
            font-size: 9.5pt;
            color: #1e293b;
            font-weight: bold;
            margin-top: -8px;
        }
        .header-logo {
            text-align: right;
        }
        .header-logo img {
            max-height: 50px;
            max-width: 150px;
        }

        /* ── Info block ── */
        .info-block-wrapper {
            background-color: #f0faf6;
            border-radius: 8px;
            margin-bottom: 30px;
            padding: 2px;
        }
        .info-block {
            width: 100%;
            border-collapse: collapse;
        }
        .info-block td {
            vertical-align: top;
            padding: 16px 20px;
        }
        .info-block td.info-dates {
            width: 28%;
        }
        .info-block td.info-party {
            width: 36%;
        }
        .info-block td.party-from {
            border-left: 1px solid #d8ede4;
            border-right: 1px solid #d8ede4;
        }
        .info-label {
            font-size: 8pt;
            font-weight: bold;
            color: #024D4D;
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 9pt;
            color: #1e293b;
            font-weight: bold;
        }
        .info-detail {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.7;
            margin-top: 2px;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table thead th {
            font-size: 9pt;
            font-weight: bold;
            color: #024D4D;
            border-bottom: 2px solid #024D4D;
            padding: 8px 8px 10px 8px;
            text-align: left;
        }
        .items-table thead th.right {
            text-align: right;
        }
        .items-table thead th.center {
            text-align: center;
        }
        .items-table tbody td {
            padding: 11px 8px;
            font-size: 9pt;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            color: #334155;
            white-space: nowrap;
        }
        .items-table tbody td:first-child {
            white-space: normal;
        }
        .items-table tbody td.right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .items-table tbody td.center {
            text-align: center;
        }

        /* ── Totals ── */
        .totals-wrapper {
            width: 100%;
            margin-bottom: 28px;
        }
        .totals-table {
            margin-left: auto;
            border-collapse: collapse;
            width: 340px;
        }
        .totals-table td {
            padding: 5px 0;
            font-size: 9.5pt;
            white-space: nowrap;
        }
        .totals-table .label {
            color: #475569;
            text-align: left;
        }
        .totals-table .value {
            text-align: right;
            font-variant-numeric: tabular-nums;
            color: #1e293b;
        }
        .totals-table .discount .value {
            color: #dc2626;
        }
        .totals-table .total-row td {
            border-top: 1.5px solid #024D4D;
            padding-top: 10px;
            font-weight: bold;
            font-size: 13pt;
            color: #024D4D;
        }

        /* ── Bottom sections ── */
        .bottom-section {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .bottom-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #024D4D;
            margin-bottom: 4px;
        }
        .bottom-content {
            font-size: 9pt;
            color: #475569;
            line-height: 1.6;
        }
        .terms-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .terms-grid td {
            vertical-align: top;
            padding: 8px 10px 8px 0;
            font-size: 9pt;
            color: #475569;
            width: 33%;
        }
        .terms-grid .terms-label {
            font-size: 8pt;
            font-weight: bold;
            color: #024D4D;
            margin-bottom: 2px;
        }
        .non-binding {
            margin-top: 14px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 8.5pt;
            color: #9a3412;
        }

        /* ── Footer ── */
        .footer {
            position: fixed;
            bottom: 28px;
            left: 50px;
            right: 50px;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            padding: 0;
            vertical-align: bottom;
        }
        .footer-right {
            text-align: right;
        }
        .footer-fayeku {
            color: #024D4D;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header">
        <tr>
            <td>
                <div class="invoice-title">{{ __('Facture Proforma') }}</div>
                <div class="invoice-ref">#{{ $proforma->reference }}</div>
            </td>
            <td class="header-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="{{ $proforma->company->name }}">
                @endif
            </td>
        </tr>
    </table>

    {{-- Info block --}}
    <div class="info-block-wrapper">
    <table class="info-block">
        <tr>
            <td class="info-dates">
                <div class="info-label">{{ __('Date d\'émission') }}</div>
                <div class="info-value">{{ $proforma->issued_at->locale('fr_FR')->translatedFormat('d') }} {{ ucfirst($proforma->issued_at->locale('fr_FR')->translatedFormat('F')) }} {{ $proforma->issued_at->translatedFormat('Y') }}</div>
                <div style="height: 10px;"></div>
                <div class="info-label">{{ __('Valide jusqu\'au') }}</div>
                <div class="info-value">{{ $proforma->valid_until->locale('fr_FR')->translatedFormat('d') }} {{ ucfirst($proforma->valid_until->locale('fr_FR')->translatedFormat('F')) }} {{ $proforma->valid_until->translatedFormat('Y') }}</div>
            </td>
            <td class="info-party party-from">
                <div class="info-label">{{ __('Émetteur') }}</div>
                <div class="info-value">{{ $proforma->company->name }}</div>
                <div class="info-detail">
                    @if ($proforma->company->address)
                        {{ $proforma->company->address }}
                        @if ($proforma->company->city), {{ $proforma->company->city }} @endif
                        <br>
                    @endif
                    @if ($proforma->company->phone) {{ format_phone($proforma->company->phone) }}<br> @endif
                    @if ($proforma->company->email) {{ $proforma->company->email }}<br> @endif
                    @if ($proforma->company->ninea) {{ __('NINEA') }} : {{ $proforma->company->ninea }}<br> @endif
                    @if ($proforma->company->rccm) {{ __('RCCM') }} : {{ $proforma->company->rccm }} @endif
                </div>
            </td>
            <td class="info-party">
                <div class="info-label">{{ __('Destinataire') }}</div>
                <div class="info-value">{{ $proforma->client->name }}</div>
                <div class="info-detail">
                    @if ($proforma->client->address) {{ $proforma->client->address }}<br> @endif
                    @if ($proforma->client->phone) {{ format_phone($proforma->client->phone) }}<br> @endif
                    @if ($proforma->client->email) {{ $proforma->client->email }} @endif
                </div>
            </td>
        </tr>
    </table>
    </div>

    {{-- Items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">{{ __('Description') }}</th>
                <th class="center">{{ __('Qté') }}</th>
                <th class="right">{{ __('Prix unitaire') }}</th>
                <th class="center">{{ __('TVA') }}</th>
                <th class="right">{{ __('Montant HT') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($proforma->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="center">{{ $line->quantity }}</td>
                    <td class="right">{{ CurrencyService::format($line->unit_price, $proforma->currency ?? 'XOF') }}</td>
                    <td class="center">{{ $line->tax_rate > 0 ? $line->tax_rate . '%' : '-' }}</td>
                    <td class="right">{{ CurrencyService::format($line->total, $proforma->currency ?? 'XOF') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="label">{{ __('Sous-total HT') }}</td>
                <td class="value">{{ CurrencyService::format($proforma->subtotal, $proforma->currency ?? 'XOF') }}</td>
            </tr>
            @if ($proforma->discount > 0)
                @php
                    $discountAmount = ($proforma->discount_type ?? 'percent') === 'fixed'
                        ? $proforma->discount
                        : (int) round($proforma->subtotal * $proforma->discount / 100);
                    $discountLabel = ($proforma->discount_type ?? 'percent') === 'fixed'
                        ? __('Remise (montant fixe)')
                        : __('Remise (:rate%)', ['rate' => $proforma->discount]);
                @endphp
                <tr class="discount">
                    <td class="label">{{ $discountLabel }}</td>
                    <td class="value">-{{ CurrencyService::format($discountAmount, $proforma->currency ?? 'XOF') }}</td>
                </tr>
            @endif
            @if ($proforma->tax_amount > 0)
                <tr>
                    <td class="label">{{ __('TVA') }}</td>
                    <td class="value">{{ CurrencyService::format($proforma->tax_amount, $proforma->currency ?? 'XOF') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">{{ __('Total TTC') }}</td>
                <td class="value">{{ CurrencyService::format($proforma->total, $proforma->currency ?? 'XOF') }}</td>
            </tr>
        </table>
    </div>

    {{-- Conditions proforma --}}
    @if ($proforma->dossier_reference || $proforma->payment_terms || $proforma->delivery_terms)
        <div class="bottom-section">
            <div class="bottom-title">{{ __('Conditions') }}</div>
            <table class="terms-grid">
                <tr>
                    @if ($proforma->dossier_reference)
                        <td>
                            <div class="terms-label">{{ __('Référence dossier') }}</div>
                            <div>{{ $proforma->dossier_reference }}</div>
                        </td>
                    @endif
                    @if ($proforma->payment_terms)
                        <td>
                            <div class="terms-label">{{ __('Conditions de paiement') }}</div>
                            <div>{{ $proforma->payment_terms }}</div>
                        </td>
                    @endif
                    @if ($proforma->delivery_terms)
                        <td>
                            <div class="terms-label">{{ __('Délai d\'exécution') }}</div>
                            <div>{{ $proforma->delivery_terms }}</div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    {{-- Notes --}}
    @if ($proforma->notes)
        <div class="bottom-section">
            <div class="bottom-title">{{ __('Notes') }}</div>
            <div class="bottom-content">{!! nl2br(e($proforma->notes)) !!}</div>
        </div>
    @endif

    {{-- Mention non-engagement --}}
    <div class="non-binding">
        {{ __('Document non comptable, à titre informatif. Valable jusqu\'au :date et soumis à confirmation par bon de commande.', ['date' => $proforma->valid_until->locale('fr_FR')->translatedFormat('d F Y')]) }}
    </div>

    {{-- Footer --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>Par <a href="https://fayeku.sn" class="footer-fayeku">Fayeku</a></td>
                <td class="footer-right">{{ $proforma->company->name }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

@php
    use Modules\PME\Invoicing\Services\CurrencyService;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Devis') }} {{ $quote->reference }}</title>
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
            vertical-align: top;
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
                <div class="invoice-title">{{ __('Devis') }}</div>
                <div class="invoice-ref">#{{ $quote->reference }}</div>
            </td>
            <td class="header-logo">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="{{ $quote->company->name }}">
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
                <div class="info-value">{{ $quote->issued_at->locale('fr_FR')->translatedFormat('d') }} {{ ucfirst($quote->issued_at->locale('fr_FR')->translatedFormat('F')) }} {{ $quote->issued_at->translatedFormat('Y') }}</div>
                <div style="height: 10px;"></div>
                <div class="info-label">{{ __('Valide jusqu\'au') }}</div>
                <div class="info-value">{{ $quote->valid_until->locale('fr_FR')->translatedFormat('d') }} {{ ucfirst($quote->valid_until->locale('fr_FR')->translatedFormat('F')) }} {{ $quote->valid_until->translatedFormat('Y') }}</div>
            </td>
            <td class="info-party party-from">
                <div class="info-label">{{ __('Émetteur') }}</div>
                <div class="info-value">{{ $quote->company->name }}</div>
                <div class="info-detail">
                    @if ($quote->company->address)
                        {{ $quote->company->address }}
                        @if ($quote->company->city), {{ $quote->company->city }} @endif
                        <br>
                    @endif
                    @if ($quote->company->phone) {{ $quote->company->phone }}<br> @endif
                    @if ($quote->company->email) {{ $quote->company->email }} @endif
                </div>
            </td>
            <td class="info-party">
                <div class="info-label">{{ __('Destinataire') }}</div>
                <div class="info-value">{{ $quote->client->name }}</div>
                <div class="info-detail">
                    @if ($quote->client->address) {{ $quote->client->address }}<br> @endif
                    @if ($quote->client->phone) {{ $quote->client->phone }}<br> @endif
                    @if ($quote->client->email) {{ $quote->client->email }} @endif
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
            @foreach ($quote->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="center">{{ $line->quantity }}</td>
                    <td class="right">{{ CurrencyService::format($line->unit_price, $quote->currency ?? 'XOF') }}</td>
                    <td class="center">{{ $line->tax_rate > 0 ? $line->tax_rate . '%' : '-' }}</td>
                    <td class="right">{{ CurrencyService::format($line->total, $quote->currency ?? 'XOF') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="label">{{ __('Sous-total HT') }}</td>
                <td class="value">{{ CurrencyService::format($quote->subtotal, $quote->currency ?? 'XOF') }}</td>
            </tr>
            @if ($quote->discount > 0)
                <tr class="discount">
                    <td class="label">{{ __('Remise (:rate%)', ['rate' => $quote->discount]) }}</td>
                    <td class="value">-{{ CurrencyService::format((int) round($quote->subtotal * $quote->discount / 100), $quote->currency ?? 'XOF') }}</td>
                </tr>
            @endif
            @if ($quote->tax_amount > 0)
                <tr>
                    <td class="label">{{ __('TVA') }}</td>
                    <td class="value">{{ CurrencyService::format($quote->tax_amount, $quote->currency ?? 'XOF') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">{{ __('Total TTC') }}</td>
                <td class="value">{{ CurrencyService::format($quote->total, $quote->currency ?? 'XOF') }}</td>
            </tr>
        </table>
    </div>

    {{-- Notes --}}
    @if ($quote->notes)
        <div class="bottom-section">
            <div class="bottom-title">{{ __('Notes') }}</div>
            <div class="bottom-content">{!! nl2br(e($quote->notes)) !!}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>Par <a href="https://fayeku.sn" class="footer-fayeku">Fayeku</a></td>
                <td class="footer-right">{{ $quote->company->name }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

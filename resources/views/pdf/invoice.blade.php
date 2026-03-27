@php
    use Modules\PME\Invoicing\Services\CurrencyService;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Facture') }} {{ $invoice->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            line-height: 1.5;
            padding: 40px 50px;
        }

        /* Header */
        .header {
            margin-bottom: 30px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .logo img {
            max-height: 50px;
            max-width: 160px;
        }
        .invoice-title {
            text-align: right;
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: -0.5px;
        }

        /* Meta */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .meta-label {
            color: #6b7280;
            font-size: 9pt;
            width: 140px;
        }
        .meta-value {
            font-size: 9.5pt;
            font-weight: bold;
        }

        /* Parties */
        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .parties td {
            vertical-align: top;
            width: 50%;
            padding: 0;
        }
        .party-title {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .party-name {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .party-detail {
            font-size: 9pt;
            color: #374151;
            line-height: 1.6;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table thead th {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            border-bottom: 1.5px solid #e5e7eb;
            padding: 8px 6px;
            text-align: left;
        }
        .items-table thead th.right {
            text-align: right;
        }
        .items-table thead th.center {
            text-align: center;
        }
        .items-table tbody td {
            padding: 10px 6px;
            font-size: 9.5pt;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .items-table tbody td.right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .items-table tbody td.center {
            text-align: center;
        }

        /* Totals */
        .totals-wrapper {
            width: 100%;
        }
        .totals-table {
            margin-left: auto;
            border-collapse: collapse;
            width: 260px;
        }
        .totals-table td {
            padding: 5px 6px;
            font-size: 9.5pt;
        }
        .totals-table .label {
            color: #6b7280;
            text-align: left;
        }
        .totals-table .value {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .totals-table .total-row td {
            border-top: 1.5px solid #1a1a2e;
            padding-top: 8px;
            font-weight: bold;
            font-size: 11pt;
            color: #1a1a2e;
        }
        .totals-table .discount .value {
            color: #dc2626;
        }

        /* Notes */
        .notes-section {
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .notes-title {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .notes-content {
            font-size: 9pt;
            color: #374151;
            line-height: 1.6;
        }

        /* Payment */
        .payment-section {
            margin-top: 16px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 30px;
            left: 50px;
            right: 50px;
            font-size: 8pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
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
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="{{ $invoice->company->name }}">
                    @endif
                </td>
                <td class="invoice-title">{{ __('Facture') }}</td>
            </tr>
        </table>
    </div>

    {{-- Metadata --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">{{ __('N° de facture') }}</td>
            <td class="meta-value">{{ $invoice->reference }}</td>
        </tr>
        <tr>
            <td class="meta-label">{{ __('Date d\'émission') }}</td>
            <td class="meta-value">{{ $invoice->issued_at->locale('fr_FR')->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">{{ __('Date d\'échéance') }}</td>
            <td class="meta-value">{{ $invoice->due_at->locale('fr_FR')->translatedFormat('d F Y') }}</td>
        </tr>
    </table>

    {{-- Parties --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party-title">{{ __('Facturé à') }}</div>
                <div class="party-name">{{ $invoice->client->name }}</div>
                <div class="party-detail">
                    @if ($invoice->client->email) {{ $invoice->client->email }}<br> @endif
                    @if ($invoice->client->phone) {{ $invoice->client->phone }}<br> @endif
                    @if ($invoice->client->address) {{ $invoice->client->address }} @endif
                </div>
            </td>
            <td>
                <div class="party-title">{{ __('De') }}</div>
                <div class="party-name">{{ $invoice->company->name }}</div>
                <div class="party-detail">
                    @if ($invoice->company->email) {{ $invoice->company->email }}<br> @endif
                    @if ($invoice->company->phone) {{ $invoice->company->phone }}<br> @endif
                    @if ($invoice->company->address)
                        {{ $invoice->company->address }}
                        @if ($invoice->company->city), {{ $invoice->company->city }} @endif
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">{{ __('Description') }}</th>
                <th class="right">{{ __('Prix unitaire') }}</th>
                <th class="center">{{ __('Quantité') }}</th>
                <th class="center">{{ __('TVA') }}</th>
                <th class="right">{{ __('Montant') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ CurrencyService::format($line->unit_price, $invoice->currency) }}</td>
                    <td class="center">{{ $line->quantity }}</td>
                    <td class="center">{{ $line->tax_rate > 0 ? $line->tax_rate . '%' : '-' }}</td>
                    <td class="right">{{ CurrencyService::format($line->total, $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="label">{{ __('Sous-total') }}</td>
                <td class="value">{{ CurrencyService::format($invoice->subtotal, $invoice->currency) }}</td>
            </tr>
            @if ($invoice->discount > 0)
                <tr class="discount">
                    <td class="label">{{ __('Remise (:rate%)', ['rate' => $invoice->discount]) }}</td>
                    <td class="value">-{{ CurrencyService::format((int) round($invoice->subtotal * $invoice->discount / 100), $invoice->currency) }}</td>
                </tr>
            @endif
            @if ($invoice->tax_amount > 0)
                <tr>
                    <td class="label">{{ __('TVA') }}</td>
                    <td class="value">{{ CurrencyService::format($invoice->tax_amount, $invoice->currency) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">{{ __('Total') }}</td>
                <td class="value">{{ CurrencyService::format($invoice->total, $invoice->currency) }}</td>
            </tr>
        </table>
    </div>

    {{-- Notes --}}
    @if ($invoice->notes)
        <div class="notes-section">
            <div class="notes-title">{{ __('Notes') }}</div>
            <div class="notes-content">{!! nl2br(e($invoice->notes)) !!}</div>
        </div>
    @endif

    {{-- Payment method --}}
    @if ($invoice->payment_method)
        <div class="notes-section payment-section">
            <div class="notes-title">{{ __('Moyen de paiement') }}</div>
            <div class="notes-content">
                @php
                    $paymentLabels = [
                        'wave' => 'Wave',
                        'orange_money' => 'Orange Money',
                        'cash' => __('Espèces'),
                        'bank_transfer' => __('Virement bancaire'),
                    ];
                @endphp
                {{ $paymentLabels[$invoice->payment_method] ?? $invoice->payment_method }}
                @if ($invoice->payment_details)
                    <br>{!! nl2br(e($invoice->payment_details)) !!}
                @endif
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>Par Fayeku</td>
                <td class="footer-right">{{ $invoice->company->name }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

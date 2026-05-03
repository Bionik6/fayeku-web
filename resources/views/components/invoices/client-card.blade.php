@props([
    'invoice',
])

@php
    use App\Enums\PME\InvoiceStatus;

    $client = $invoice->client;

    $context = ['average_days' => 0, 'outstanding' => 0, 'last_invoice_date' => null];

    if ($client) {
        $clientInvoices = $client->invoices()
            ->where('id', '!=', $invoice->id)
            ->get(['status', 'total', 'amount_paid', 'issued_at', 'paid_at']);

        $paid = $clientInvoices->filter(
            fn ($inv) => $inv->status === InvoiceStatus::Paid && $inv->paid_at && $inv->issued_at
        );
        $context['average_days'] = $paid->isNotEmpty()
            ? (int) round($paid->avg(fn ($inv) => $inv->issued_at->diffInDays($inv->paid_at)))
            : 0;

        $context['outstanding'] = (int) $clientInvoices
            ->filter(fn ($inv) => in_array($inv->status, [
                InvoiceStatus::Sent,
                InvoiceStatus::Overdue,
                InvoiceStatus::PartiallyPaid,
            ], true))
            ->sum(fn ($inv) => (int) $inv->total - (int) $inv->amount_paid);

        $lastInvoice = $clientInvoices->sortByDesc('issued_at')->first();
        $context['last_invoice_date'] = $lastInvoice?->issued_at
            ? format_date($lastInvoice->issued_at)
            : null;
    }

    $metaItems = [];
    if ($context['average_days'] > 0) {
        $metaItems[] = ['text' => __('Délai moyen :days jours', ['days' => $context['average_days']]), 'class' => 'text-slate-700'];
    }
    if ($context['outstanding'] > 0) {
        $metaItems[] = ['text' => __('Impayé : :amount', ['amount' => format_money($context['outstanding'], $invoice->currency)]), 'class' => 'text-amber-600'];
    }
    if ($context['last_invoice_date']) {
        $metaItems[] = ['text' => __('Dernière facture : :date', ['date' => $context['last_invoice_date']]), 'class' => 'text-slate-700'];
    }
@endphp

<x-client-card
    :client="$client"
    no-client-message="Aucun client final renseigné sur cette facture."
>
    @if (! empty($metaItems))
        <div class="mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-700">
            @foreach ($metaItems as $i => $item)
                @if ($i > 0)
                    <span class="text-slate-500">⋅</span>
                @endif
                <span class="{{ $item['class'] }}">{{ $item['text'] }}</span>
            @endforeach
        </div>
    @endif
</x-client-card>

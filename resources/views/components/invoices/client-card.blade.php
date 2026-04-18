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
@endphp

<section class="app-shell-panel p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Client') }}</h3>
        @if ($client)
            <a
                href="{{ route('pme.clients.show', $client->id) }}"
                wire:navigate
                class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-strong"
            >
                {{ __('Voir la fiche client') }}
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        @endif
    </div>

    @if ($client)
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
            <p class="font-semibold text-ink">{{ $client->name }}</p>

            <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-slate-700">
                @if ($client->email)
                    <span>{{ $client->email }}</span>
                @endif
                @if ($client->email && $client->phone)
                    <span class="text-slate-500">⋅</span>
                @endif
                @if ($client->phone)
                    <span>{{ format_phone($client->phone) }}</span>
                @endif
            </div>

            @php
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

            @if ($client->address || $client->tax_id)
                <div class="mt-3 border-t border-slate-200/70 pt-3 text-sm text-slate-600">
                    @if ($client->address)
                        <p class="flex items-start gap-1.5">
                            <flux:icon name="map-pin" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                            <span>{{ $client->address }}</span>
                        </p>
                    @endif
                    @if ($client->tax_id)
                        <p class="mt-1 font-mono text-sm text-slate-500">{{ __('NINEA') }} : {{ $client->tax_id }}</p>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
            {{ __('Aucun client final renseigné sur cette facture.') }}
        </div>
    @endif
</section>

<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Portfolio\Models\DismissedAlert;
use Modules\Compta\Portfolio\Services\AlertService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Alertes')] class extends Component {
    #[Url(as: 'filtre')]
    public string $filter = 'all';

    #[Url(as: 'archivees')]
    public bool $showDismissed = false;

    public ?string $selectedInvoiceId = null;

    public ?Company $firm = null;

    public function mount(): void
    {
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();
    }

    public function viewInvoice(string $id): void
    {
        $this->selectedInvoiceId = $id;
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
    }

    #[Computed]
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->where('id', $this->selectedInvoiceId)
            ->with(['client', 'lines'])
            ->first();
    }

    public function setFilter(string $key): void
    {
        $this->filter = $key;
        $this->showDismissed = false;
    }

    public function dismiss(string $alertKey): void
    {
        DismissedAlert::firstOrCreate(
            ['user_id' => auth()->id(), 'alert_key' => $alertKey],
            ['dismissed_at' => now()]
        );

        unset($this->alerts, $this->counts);
        $this->dispatch('alerts-updated');
    }

    public function undismiss(string $alertKey): void
    {
        DismissedAlert::where('user_id', auth()->id())
            ->where('alert_key', $alertKey)
            ->delete();

        unset($this->alerts, $this->counts);
        $this->dispatch('alerts-updated');
    }

    /** @return array<string> */
    private function dismissedKeys(): array
    {
        return once(fn () => DismissedAlert::where('user_id', auth()->id())
            ->pluck('alert_key')
            ->toArray());
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function alerts(): array
    {
        if (! $this->firm) {
            return [];
        }

        $filterValue = (! $this->showDismissed && $this->filter !== 'all') ? $this->filter : null;
        $all = app(AlertService::class)->build($this->firm, $filterValue);

        $dismissedKeys = $this->dismissedKeys();

        if ($this->showDismissed) {
            return array_values(array_map(
                fn (array $a) => array_merge($a, ['dismissed' => true]),
                array_filter($all, fn (array $a) => in_array($a['alert_key'], $dismissedKeys))
            ));
        }

        return array_values(array_filter(
            $all,
            fn (array $a) => ! in_array($a['alert_key'], $dismissedKeys)
        ));
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        if (! $this->firm) {
            return ['all' => 0, 'critical' => 0, 'watch' => 0, 'new' => 0, 'dismissed' => 0];
        }

        $all = app(AlertService::class)->build($this->firm);

        $dismissedKeys = $this->dismissedKeys();

        $active = array_filter($all, fn (array $a) => ! in_array($a['alert_key'], $dismissedKeys));

        return [
            'all'       => count($active),
            'critical'  => count(array_filter($active, fn ($a) => $a['type'] === 'critical')),
            'watch'     => count(array_filter($active, fn ($a) => $a['type'] === 'watch')),
            'new'       => count(array_filter($active, fn ($a) => $a['type'] === 'new')),
            'dismissed' => count(array_filter($all, fn (array $a) => in_array($a['alert_key'], $dismissedKeys))),
        ];
    }
}

?>

<div class="space-y-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Surveillance') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Alertes') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $this->counts['all'] }} {{ $this->counts['all'] > 1 ? 'alertes actives' : 'alerte active' }}
                    · {{ ucfirst(now()->locale('fr_FR')->translatedFormat('F Y')) }}
                </p>
            </div>

            @if ($this->counts['all'] > 0)
                <span class="inline-flex shrink-0 items-center self-start rounded-full bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20 lg:self-center">
                    {{ $this->counts['critical'] > 0 ? $this->counts['critical'].' critique'.($this->counts['critical'] > 1 ? 's' : '') : __('Tout est à jour') }}
                </span>
            @else
                <span class="inline-flex shrink-0 items-center self-start rounded-full bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 ring-1 ring-inset ring-green-600/20 lg:self-center">
                    {{ __('Tout est à jour') }}
                </span>
            @endif
        </div>
    </section>

    {{-- ─── Filtres ────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel p-5">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Filtrer par criticité') }}</p>
        <div class="flex flex-wrap gap-2">

            {{-- Filtres par criticité --}}
            @foreach ([
                'all'      => ['label' => 'Toutes',    'count' => $this->counts['all'],      'activeClass' => 'bg-primary text-white',     'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'critical' => ['label' => 'Critiques', 'count' => $this->counts['critical'], 'activeClass' => 'bg-rose-500 text-white',    'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'watch'    => ['label' => 'En veille', 'count' => $this->counts['watch'],    'activeClass' => 'bg-amber-500 text-white',   'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'new'      => ['label' => 'Nouvelles', 'count' => $this->counts['new'],      'activeClass' => 'bg-emerald-600 text-white', 'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
            ] as $key => $tab)
                <button
                    wire:click="setFilter('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        $tab['activeClass']                                                                            => ! $showDismissed && $filter === $key,
                        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary' => $showDismissed || $filter !== $key,
                    ])
                >
                    {{ $tab['label'] }}
                    <span @class([
                        'rounded-full px-1.5 py-px text-xs font-bold',
                        $tab['badgeActive']   => ! $showDismissed && $filter === $key,
                        $tab['badgeInactive'] => $showDismissed || $filter !== $key,
                    ])>{{ $tab['count'] }}</span>
                </button>
            @endforeach

            {{-- Onglet Archivées --}}
            <button
                wire:click="$set('showDismissed', {{ $showDismissed ? 'false' : 'true' }})"
                @class([
                    'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                    'bg-slate-600 text-white'                                                                           => $showDismissed,
                    'bg-white border border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-700'      => ! $showDismissed,
                ])
            >
                <flux:icon name="archive-box" class="size-3.5" />
                {{ __('Archivées') }}
                @if ($this->counts['dismissed'] > 0)
                    <span @class([
                        'rounded-full px-1.5 py-px text-xs font-bold',
                        'bg-white/20 text-white'      => $showDismissed,
                        'bg-slate-100 text-slate-500' => ! $showDismissed,
                    ])>{{ $this->counts['dismissed'] }}</span>
                @endif
            </button>

        </div>
    </section>

    {{-- ─── Liste des alertes ──────────────────────────────────────────── --}}
    <section class="app-shell-panel">
        @if (count($this->alerts) > 0)
            <div class="divide-y divide-slate-100">
                @foreach ($this->alerts as $alert)
                    <div @class([
                        'flex items-center gap-4 px-6 py-4 transition',
                        'opacity-50' => $alert['dismissed'] ?? false,
                    ])>
                        {{-- Icône type --}}
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-2xl text-base font-bold',
                            'bg-rose-100 text-rose-600'       => $alert['type'] === 'critical',
                            'bg-amber-100 text-amber-600'     => $alert['type'] === 'watch',
                            'bg-emerald-100 text-emerald-600' => $alert['type'] === 'new',
                        ])>
                            @if ($alert['type'] === 'critical') ! @elseif ($alert['type'] === 'watch') ~ @else + @endif
                        </span>

                        {{-- Titre + sous-titre --}}
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-ink">{{ $alert['title'] }}</p>
                            <p class="mt-0.5 truncate text-sm text-slate-500">{{ $alert['subtitle'] }}</p>
                        </div>

                        {{-- Badge statut --}}
                        @if ($alert['dismissed'] ?? false)
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-400/20">
                                <span class="size-1.5 rounded-full bg-slate-400"></span>
                                Archivée
                            </span>
                        @else
                            <span @class([
                                'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                'bg-rose-50 text-rose-700 ring-rose-600/20'    => $alert['type'] === 'critical',
                                'bg-amber-50 text-amber-700 ring-amber-600/20' => $alert['type'] === 'watch',
                                'bg-green-50 text-green-700 ring-green-600/20' => $alert['type'] === 'new',
                            ])>
                                <span @class([
                                    'size-1.5 rounded-full',
                                    'bg-rose-500'  => $alert['type'] === 'critical',
                                    'bg-amber-500' => $alert['type'] === 'watch',
                                    'bg-green-500' => $alert['type'] === 'new',
                                ])></span>
                                @if ($alert['type'] === 'critical') Critique
                                @elseif ($alert['type'] === 'watch') En veille
                                @else Nouvelle inscription
                                @endif
                            </span>
                        @endif

                        {{-- Dropdown actions --}}
                        <flux:dropdown position="bottom" align="end">
                            <button type="button" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                {{ __('Actions') }}
                                <x-app.icon name="chevron-down" class="size-3.5" />
                            </button>
                            <flux:menu>
                                @if ($alert['type'] === 'critical' && ($alert['invoice_id'] ?? null))
                                    <flux:menu.item wire:click="viewInvoice('{{ $alert['invoice_id'] }}')">
                                        <x-app.icon name="invoice" class="size-4 text-slate-400" />
                                        {{ __('Voir Facture') }}
                                    </flux:menu.item>
                                @endif

                                @if ($alert['company_id'] ?? null)
                                    <flux:menu.item :href="route('clients.show', $alert['company_id'])" wire:navigate>
                                        <x-app.icon name="user" class="size-4 text-slate-400" />
                                        {{ __('Voir Fiche Client') }}
                                    </flux:menu.item>
                                @endif

                                <flux:menu.separator />

                                @if ($alert['dismissed'] ?? false)
                                    <flux:menu.item wire:click="undismiss('{{ $alert['alert_key'] }}')">
                                        <x-app.icon name="restore" class="size-4 text-slate-400" />
                                        {{ __('Restaurer') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item wire:click="dismiss('{{ $alert['alert_key'] }}')">
                                        <x-app.icon name="check" class="size-4 text-slate-400" />
                                        {{ __('Marquer comme vu') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-50">
                    <flux:icon name="{{ $showDismissed ? 'archive-box' : 'check-circle' }}" class="size-7 {{ $showDismissed ? 'text-slate-400' : 'text-emerald-600' }}" />
                </div>
                <p class="mt-4 font-semibold text-ink">
                    @if ($showDismissed) {{ __('Aucune alerte archivée') }}
                    @elseif ($filter === 'all') {{ __('Aucune alerte pour le moment') }}
                    @elseif ($filter === 'critical') {{ __('Aucun impayé critique') }}
                    @elseif ($filter === 'watch') {{ __('Aucun client en veille') }}
                    @else {{ __('Aucune nouvelle inscription cette semaine') }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-400">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
            </div>
        @endif
    </section>

    {{-- ─── Modale détail facture ─────────────────────────────────────────── --}}
    @if ($this->selectedInvoice)
        @php
            $inv = $this->selectedInvoice;
            $client = $inv->client;

            $statusConfig = match ($inv->status) {
                InvoiceStatus::Paid          => ['label' => 'Payée',      'class' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'],
                InvoiceStatus::Overdue       => ['label' => 'Impayée',    'class' => 'bg-rose-100 text-rose-700'],
                InvoiceStatus::PartiallyPaid => ['label' => 'Partiel',    'class' => 'bg-orange-100 text-orange-700'],
                InvoiceStatus::Sent,
                InvoiceStatus::Certified     => ['label' => 'En attente', 'class' => 'bg-amber-50 text-amber-700'],
                InvoiceStatus::Draft         => ['label' => 'Brouillon',  'class' => 'bg-slate-100 text-slate-600'],
                InvoiceStatus::Cancelled     => ['label' => 'Annulée',    'class' => 'bg-slate-100 text-slate-500'],
                default                      => ['label' => ucfirst($inv->status->value), 'class' => 'bg-slate-100 text-slate-600'],
            };
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeInvoice">
            <div class="relative w-full max-w-[1200px] overflow-hidden rounded-2xl bg-white shadow-2xl">

                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-slate-100 px-10 py-7">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Facture') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-ink">{{ $inv->reference }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Émise le') }} {{ $inv->issued_at->locale('fr_FR')->translatedFormat('j F Y') }}
                            &nbsp;·&nbsp;
                            {{ __('Échéance') }} {{ $inv->due_at->locale('fr_FR')->translatedFormat('j F Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusConfig['class'] }}">
                            {{ $statusConfig['label'] }}
                        </span>
                        <button wire:click="closeInvoice" class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 gap-0 lg:grid-cols-3">

                        {{-- Colonne principale --}}
                        <div class="col-span-2 px-10 py-8">
                            @if ($client)
                                <div class="mb-6">
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Destinataire') }}</p>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                        <p class="font-semibold text-ink">{{ $client->name }}</p>
                                        @if ($client->phone)
                                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="phone" class="size-3.5 shrink-0" />{{ $client->phone }}
                                            </p>
                                        @endif
                                        @if ($client->email)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="envelope" class="size-3.5 shrink-0" />{{ $client->email }}
                                            </p>
                                        @endif
                                        @if ($client->address)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="map-pin" class="size-3.5 shrink-0" />{{ $client->address }}
                                            </p>
                                        @endif
                                        @if ($client->tax_id)
                                            <p class="mt-1 font-mono text-xs text-slate-400">{{ __('Réf. fiscale') }} : {{ $client->tax_id }}</p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mb-6 rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">
                                    {{ __('Aucun client final renseigné sur cette facture.') }}
                                </div>
                            @endif

                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Détail des prestations') }}</p>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-left">
                                            <th class="pb-2 pr-4 text-xs font-semibold text-slate-500">{{ __('Description') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('Qté') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('PU HT') }}</th>
                                            <th class="pb-2 px-4 text-right text-xs font-semibold text-slate-500">{{ __('TVA') }}</th>
                                            <th class="pb-2 pl-4 text-right text-xs font-semibold text-slate-500">{{ __('Total HT') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @forelse ($inv->lines as $line)
                                            <tr>
                                                <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600">{{ $line->quantity }}</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600">{{ number_format($line->unit_price, 0, ',', ' ') }} F</td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-500">{{ $line->tax_rate }} %</td>
                                                <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink">{{ number_format($line->total, 0, ',', ' ') }} F</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t border-slate-200">
                                        <tr>
                                            <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                            <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink">{{ number_format($inv->subtotal, 0, ',', ' ') }} F</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                            <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink">{{ number_format($inv->tax_amount, 0, ',', ' ') }} F</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                            <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink">{{ number_format($inv->total, 0, ',', ' ') }} F</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Colonne latérale --}}
                        <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ __('Récapitulatif') }}</p>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ number_format($inv->subtotal, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('TVA') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ number_format($inv->tax_amount, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-3">
                                    <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                                    <dd class="tabular-nums text-lg font-bold text-ink">{{ number_format($inv->total, 0, ',', ' ') }} FCFA</dd>
                                </div>
                                @if ($inv->status === InvoiceStatus::PartiallyPaid)
                                    <div class="flex justify-between text-amber-600">
                                        <dt>{{ __('Encaissé') }}</dt>
                                        <dd class="tabular-nums font-medium">{{ number_format($inv->amount_paid, 0, ',', ' ') }} FCFA</dd>
                                    </div>
                                    <div class="flex justify-between text-rose-600">
                                        <dt class="font-semibold">{{ __('Reste dû') }}</dt>
                                        <dd class="tabular-nums font-bold">{{ number_format($inv->total - $inv->amount_paid, 0, ',', ' ') }} FCFA</dd>
                                    </div>
                                @endif
                            </dl>
                            <div class="mt-6 space-y-2 border-t border-slate-200 pt-4 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">{{ __('Émise le') }}</span>
                                    <span class="text-ink">{{ $inv->issued_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">{{ __('Échéance') }}</span>
                                    <span @class(['font-medium', 'text-rose-600' => $inv->status === InvoiceStatus::Overdue, 'text-ink' => $inv->status !== InvoiceStatus::Overdue])>
                                        {{ $inv->due_at->locale('fr_FR')->translatedFormat('j M Y') }}
                                    </span>
                                </div>
                                @if ($inv->paid_at)
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">{{ __('Payée le') }}</span>
                                        <span class="text-green-600">{{ $inv->paid_at->locale('fr_FR')->translatedFormat('j M Y') }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-6">
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end border-t border-slate-100 px-10 py-5">
                    <flux:button variant="ghost" wire:click="closeInvoice">{{ __('Fermer') }}</flux:button>
                </div>
            </div>
        </div>
    @endif

</div>

<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Models\Compta\DismissedAlert;
use App\Services\Compta\AlertService;
use App\Models\PME\Invoice;

new #[Title('Alertes')] class extends Component {
    #[Url(as: 'filtre')]
    public string $filter = 'all';

    #[Url(as: 'archivees')]
    public bool $showDismissed = false;

    public ?string $selectedInvoiceId = null;

    public ?Company $firm = null;

    public function mount(): void
    {
        $this->firm = auth()->user()->accountantFirm();
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

    #[Computed]
    public function heroSummary(): string
    {
        $activeLabel = $this->counts['all'] > 1 ? 'alertes actives' : 'alerte active';
        $criticalLabel = $this->counts['critical'] > 1 ? 'critiques à traiter' : 'critique à traiter';

        return sprintf(
            '%s %s · %s %s · %s',
            $this->counts['all'],
            $activeLabel,
            $this->counts['critical'],
            $criticalLabel,
            format_month(now())
        );
    }

    public function heroBadgeLabel(): string
    {
        if ($this->counts['critical'] === 0) {
            return __('Aucune critique à traiter');
        }

        return $this->counts['critical'].' '.($this->counts['critical'] > 1 ? 'critiques à traiter' : 'critique à traiter');
    }

    public function alertBadgeLabel(string $type): string
    {
        return match ($type) {
            'critical' => 'Critique',
            'watch' => 'À surveiller',
            default => 'Nouvelle inscription',
        };
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
                <p class="mt-1 text-sm text-slate-500">{{ $this->heroSummary }}</p>
            </div>

            @if ($this->counts['all'] > 0)
                <span class="inline-flex shrink-0 items-center self-start rounded-full bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20 lg:self-center">
                    {{ $this->heroBadgeLabel() }}
                </span>
            @else
                <span class="inline-flex shrink-0 items-center self-start rounded-full bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 ring-1 ring-inset ring-green-600/20 lg:self-center">
                    {{ __('Tout est à jour') }}
                </span>
            @endif
        </div>
    </section>

    {{-- ─── Liste des alertes ──────────────────────────────────────────── --}}
    <x-ui.table-panel
        :title="__('Alertes clients')"
        :description="__('Suivez les impayés critiques, les clients à surveiller et les nouvelles inscriptions.')"
        :filterLabel="__('Filtrer les alertes')"
    >
        <x-slot:filters>
            {{-- Filtres par criticité --}}
            @foreach ([
                'all'      => ['label' => 'Toutes',       'count' => $this->counts['all'],      'activeClass' => 'bg-primary text-white',     'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'critical' => ['label' => 'Critiques',    'count' => $this->counts['critical'], 'activeClass' => 'bg-rose-500 text-white',    'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'watch'    => ['label' => 'À surveiller', 'count' => $this->counts['watch'],    'activeClass' => 'bg-amber-500 text-white',   'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'new'      => ['label' => 'Nouvelles',    'count' => $this->counts['new'],      'activeClass' => 'bg-emerald-600 text-white', 'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
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
                        'rounded-full px-1.5 py-px text-sm font-bold',
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
                    'bg-slate-500 text-white'                                                                      => $showDismissed,
                    'bg-white border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-600' => ! $showDismissed,
                ])
            >
                <flux:icon name="archive-box" class="size-3.5" />
                {{ __('Archivées') }}
                @if ($this->counts['dismissed'] > 0)
                    <span @class([
                        'rounded-full px-1.5 py-px text-sm font-bold',
                        'bg-white/20 text-white'      => $showDismissed,
                        'bg-slate-100 text-slate-500' => ! $showDismissed,
                    ])>{{ $this->counts['dismissed'] }}</span>
                @endif
            </button>
        </x-slot:filters>

        @if (count($this->alerts) > 0)
            <div class="divide-y divide-slate-100 border-t border-slate-100">
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
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-sm font-semibold text-slate-500 ring-1 ring-inset ring-slate-400/20">
                                <span class="size-1.5 rounded-full bg-slate-400"></span>
                                Archivée
                            </span>
                        @else
                            <span @class([
                                'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-sm font-semibold ring-1 ring-inset',
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
                                {{ $this->alertBadgeLabel($alert['type']) }}
                            </span>
                        @endif

                        <div class="flex shrink-0 items-center">
                            <x-ui.dropdown>
                                @if ($alert['type'] === 'critical' && ($alert['invoice_id'] ?? null))
                                    <x-ui.dropdown-item wire:click="viewInvoice('{{ $alert['invoice_id'] }}')">
                                        <x-slot:icon><x-app.icon name="invoice" class="size-4 text-slate-400" /></x-slot:icon>
                                        {{ __('Voir la facture') }}
                                    </x-ui.dropdown-item>
                                @endif

                                @if ($alert['company_id'] ?? null)
                                    <x-ui.dropdown-item :href="route('clients.show', $alert['company_id'])" wire:navigate>
                                        <x-slot:icon><x-app.icon name="user" class="size-4 text-slate-400" /></x-slot:icon>
                                        {{ __('Voir le client') }}
                                    </x-ui.dropdown-item>
                                @endif

                                <x-ui.dropdown-separator />

                                @if ($alert['dismissed'] ?? false)
                                    <x-ui.dropdown-item wire:click="undismiss('{{ $alert['alert_key'] }}')">
                                        <x-slot:icon><x-app.icon name="restore" class="size-4 text-slate-400" /></x-slot:icon>
                                        {{ __('Restaurer') }}
                                    </x-ui.dropdown-item>
                                @else
                                    <x-ui.dropdown-item wire:click="dismiss('{{ $alert['alert_key'] }}')">
                                        <x-slot:icon><x-app.icon name="check" class="size-4 text-slate-400" /></x-slot:icon>
                                        {{ __('Marquer comme traité') }}
                                    </x-ui.dropdown-item>
                                @endif
                            </x-ui.dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-50">
                    <flux:icon name="{{ $showDismissed ? 'archive-box' : 'check-circle' }}" class="size-7 {{ $showDismissed ? 'text-slate-500' : 'text-emerald-600' }}" />
                </div>
                <p class="mt-4 font-semibold text-ink">
                    @if ($showDismissed) {{ __('Aucune alerte archivée') }}
                    @elseif ($filter === 'all') {{ __('Aucune alerte pour le moment') }}
                    @elseif ($filter === 'critical') {{ __('Aucun impayé critique') }}
                    @elseif ($filter === 'watch') {{ __('Aucun client à surveiller') }}
                    @else {{ __('Aucune nouvelle inscription cette semaine') }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
            </div>
        @endif
    </x-ui.table-panel>

    {{-- ─── Modale détail facture ─────────────────────────────────────────── --}}
    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif

</div>

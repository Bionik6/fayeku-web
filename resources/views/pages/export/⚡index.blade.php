<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Export\Enums\ExportFormat;
use Modules\Compta\Export\Models\ExportHistory;
use Modules\Compta\Portfolio\Services\PortfolioService;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Export Groupé')] class extends Component {
    public ?Company $firm = null;

    #[Url] public string $searchClient = '';

    // ─── Modal state ──────────────────────────────────────────────────────
    public string $exportPeriod = '';

    public string $exportFormat = 'sage100';

    public string $clientSelection = 'all';

    /** @var array<int, string> */
    public array $selectedClientIds = [];

    public function mount(): void
    {
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();
    }

    // ─── Computed ─────────────────────────────────────────────────────────

    /** @return Collection<int, ExportHistory> */
    #[Computed]
    public function exportHistories(): Collection
    {
        if (! $this->firm) {
            return collect();
        }

        return ExportHistory::query()
            ->where('firm_id', $this->firm->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->with('user')
            ->get();
    }

    /** @return Collection<int, Company> */
    #[Computed]
    public function clients(): Collection
    {
        if (! $this->firm) {
            return collect();
        }

        $smeIds = app(PortfolioService::class)->activeSmeIds($this->firm);
        $query = Company::whereIn('id', $smeIds)->orderBy('name');

        if ($this->searchClient !== '') {
            $term = mb_strtolower($this->searchClient);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
        }

        return $query->get();
    }

    /** @return array<int, array{value: string, label: string, type: string}> */
    #[Computed]
    public function exportPeriods(): array
    {
        $year = now()->year;
        $periods = [];

        for ($m = (int) now()->month; $m >= 1; $m--) {
            $date = now()->setMonth($m)->startOfMonth();
            $periods[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->locale('fr_FR')->translatedFormat('F Y')),
                'type' => 'Mois',
            ];
        }

        $quarterLabels = ['T1' => [1, 3], 'T2' => [4, 6], 'T3' => [7, 9], 'T4' => [10, 12]];
        foreach ($quarterLabels as $label => [$from, $to]) {
            if ($from <= now()->month) {
                $periods[] = [
                    'value' => $year.'-'.$label,
                    'label' => $label.' '.$year,
                    'type' => 'Trimestre',
                ];
            }
        }

        $periods[] = ['value' => $year.'-S1', 'label' => 'S1 '.$year, 'type' => 'Semestre'];
        if (now()->month > 6) {
            $periods[] = ['value' => $year.'-S2', 'label' => 'S2 '.$year, 'type' => 'Semestre'];
        }

        $periods[] = ['value' => (string) $year, 'label' => 'Année '.$year, 'type' => 'Année'];

        return $periods;
    }

    #[Computed]
    public function exportInvoiceCount(): int
    {
        if (empty($this->exportPeriod)) {
            return 0;
        }

        return $this->exportFilteredInvoices()->count();
    }

    #[Computed]
    public function selectedClientsCount(): int
    {
        if ($this->clientSelection === 'all') {
            return $this->firm
                ? app(PortfolioService::class)->activeSmeIds($this->firm)->count()
                : 0;
        }

        return count($this->selectedClientIds);
    }

    // ─── Actions ──────────────────────────────────────────────────────────

    public function mountExportModal(): void
    {
        $this->exportPeriod = now()->format('Y-m');
        $this->exportFormat = 'sage100';
        $this->clientSelection = 'all';
        $this->selectedClientIds = [];
        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);
    }

    public function updatedExportPeriod(): void
    {
        unset($this->exportInvoiceCount);
    }

    public function updatedClientSelection(): void
    {
        if ($this->clientSelection === 'all') {
            $this->selectedClientIds = [];
        }
        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);
    }

    public function updatedSelectedClientIds(): void
    {
        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);
    }

    public function toggleClient(string $id): void
    {
        if (in_array($id, $this->selectedClientIds)) {
            $this->selectedClientIds = array_values(array_filter(
                $this->selectedClientIds,
                fn (string $cid) => $cid !== $id
            ));
        } else {
            $this->selectedClientIds[] = $id;
        }

        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);
    }

    public function toggleAllClients(): void
    {
        if (! $this->firm) {
            return;
        }

        $allIds = app(PortfolioService::class)->activeSmeIds($this->firm)->toArray();

        if (count($this->selectedClientIds) === count($allIds)) {
            $this->selectedClientIds = [];
        } else {
            $this->selectedClientIds = $allIds;
        }

        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);
    }

    public function generateExport(): void
    {
        if (! $this->firm || empty($this->exportPeriod)) {
            return;
        }

        $clientIds = $this->resolveClientIds();

        if (empty($clientIds)) {
            return;
        }

        ExportHistory::create([
            'firm_id' => $this->firm->id,
            'user_id' => auth()->id(),
            'period' => $this->exportPeriod,
            'format' => $this->exportFormat,
            'scope' => $this->clientSelection,
            'client_ids' => $clientIds,
            'clients_count' => count($clientIds),
        ]);

        unset($this->exportHistories);

        $this->modal('export-groupe')->close();

        // TODO: Implement actual file generation via ExportService
        session()->flash('export-success', __('Export généré avec succès.'));
    }

    public function exportClient(string $companyId): void
    {
        $this->clientSelection = 'manual';
        $this->selectedClientIds = [$companyId];
        $this->exportPeriod = now()->format('Y-m');
        $this->exportFormat = 'sage100';
        unset($this->exportInvoiceCount);
        unset($this->selectedClientsCount);

        $this->modal('export-groupe')->show();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public function exportPeriodLabel(): string
    {
        if (empty($this->exportPeriod)) {
            return '';
        }

        $period = collect($this->exportPeriods)->firstWhere('value', $this->exportPeriod);

        return $period['label'] ?? $this->exportPeriod;
    }

    /** @return array<int, string> */
    private function resolveClientIds(): array
    {
        if ($this->clientSelection === 'all' && $this->firm) {
            return app(PortfolioService::class)->activeSmeIds($this->firm)->toArray();
        }

        return $this->selectedClientIds;
    }

    private function exportFilteredInvoices(): Collection
    {
        $clientIds = $this->resolveClientIds();

        if (empty($clientIds)) {
            return collect();
        }

        $query = Invoice::query()->whereIn('company_id', $clientIds);

        $period = $this->exportPeriod;
        $year = (int) substr($period, 0, 4);

        return match (true) {
            str_contains($period, '-T1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 3)->get(),
            str_contains($period, '-T2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 4)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-T3') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 9)->get(),
            str_contains($period, '-T4') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 10)->whereMonth('issued_at', '<=', 12)->get(),
            str_contains($period, '-S1') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 1)->whereMonth('issued_at', '<=', 6)->get(),
            str_contains($period, '-S2') => $query->whereYear('issued_at', $year)->whereMonth('issued_at', '>=', 7)->whereMonth('issued_at', '<=', 12)->get(),
            strlen($period) === 4        => $query->whereYear('issued_at', $year)->get(),
            default                      => $query->whereYear('issued_at', $year)->whereMonth('issued_at', (int) substr($period, 5, 2))->get(),
        };
    }

    /** @return array<int, array{code: string, label: string}> */
    public function planDeComptes(): array
    {
        return [
            ['code' => '710000', 'label' => 'Ventes de marchandises'],
            ['code' => '411000', 'label' => 'Clients'],
            ['code' => '445710', 'label' => 'TVA collectée'],
            ['code' => '601000', 'label' => 'Achats de marchandises'],
            ['code' => '445660', 'label' => 'TVA déductible'],
            ['code' => '401000', 'label' => 'Fournisseurs'],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Comptabilité') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Export Groupé') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $this->clients->count() }} {{ $this->clients->count() > 1 ? 'clients' : 'client' }}
                    · {{ ucfirst(now()->locale('fr_FR')->translatedFormat('F Y')) }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <flux:modal.trigger name="export-groupe">
                    <button
                        type="button"
                        wire:click="mountExportModal"
                        class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                    >
                        <x-app.icon name="export" class="size-4" />
                        {{ __('Exporter') }}
                    </button>
                </flux:modal.trigger>
            </div>
        </div>
    </section>

    {{-- ─── Flash message ──────────────────────────────────────────────── --}}
    @if (session()->has('export-success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
            {{ session('export-success') }}
        </div>
    @endif

    {{-- ─── Historique des exports ─────────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="px-6 pt-6 pb-4">
            <h2 class="text-lg font-bold text-ink">{{ __('Historique des exports') }}</h2>
        </div>

        @if ($this->exportHistories->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-400">{{ __('Aucun export réalisé pour le moment.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Date') }}</th>
                            <th class="px-6 py-3">{{ __('Période') }}</th>
                            <th class="px-6 py-3">{{ __('Format') }}</th>
                            <th class="px-6 py-3">{{ __('Clients') }}</th>
                            <th class="px-6 py-3">{{ __('Utilisateur') }}</th>
                            <th class="px-6 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->exportHistories as $history)
                            <tr class="transition hover:bg-slate-50/50">
                                <td class="whitespace-nowrap px-6 py-3.5 font-medium text-ink">
                                    {{ $history->created_at->locale('fr_FR')->translatedFormat('j M Y H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $history->period }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                        {{ match($history->format) {
                                            ExportFormat::Sage100 => 'Sage 100',
                                            ExportFormat::Ebp => 'EBP',
                                            ExportFormat::Excel => 'Excel',
                                            default => $history->format->value ?? $history->format,
                                        } }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $history->clients_count }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">{{ $history->user?->full_name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-ink shadow-sm transition hover:bg-slate-50"
                                    >
                                        <x-app.icon name="export" class="size-3.5" />
                                        {{ __('Re-télécharger') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ─── Export client individuel ───────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="flex items-center justify-between px-6 pt-6 pb-4">
            <h2 class="text-lg font-bold text-ink">{{ __('Export client individuel') }}</h2>

            <div class="relative">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchClient"
                    placeholder="{{ __('Rechercher un client...') }}"
                    class="w-64 rounded-xl border border-slate-200 bg-white px-4 py-2 pl-10 text-sm text-ink placeholder-slate-400 shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
                <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
        </div>

        @if ($this->clients->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-slate-400">{{ __('Aucun client trouvé.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Client') }}</th>
                            <th class="px-6 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->clients as $client)
                            <tr class="transition hover:bg-slate-50/50">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-xl bg-mist text-xs font-bold text-primary">
                                            {{ mb_substr($client->name, 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-ink">{{ $client->name }}</p>
                                            <p class="text-xs text-slate-400">{{ $client->ninea ?? $client->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <button
                                        type="button"
                                        wire:click="exportClient('{{ $client->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-ink shadow-sm transition hover:bg-slate-50"
                                    >
                                        <x-app.icon name="export" class="size-3.5" />
                                        {{ __('Exporter') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ─── Plan de comptes (Sage 100) ─────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="px-6 pt-6 pb-4">
            <h2 class="text-lg font-bold text-ink">{{ __('Plan de comptes (Sage 100)') }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __('Comptes utilisés pour les écritures comptables') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-t border-slate-100 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <th class="px-6 py-3">{{ __('Code') }}</th>
                        <th class="px-6 py-3">{{ __('Libellé') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->planDeComptes() as $account)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="whitespace-nowrap px-6 py-3.5">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">
                                    {{ $account['code'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 font-medium text-ink">{{ $account['label'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- ─── Modale Export Groupé ───────────────────────────────────────── --}}
    <flux:modal name="export-groupe" variant="bare" closable class="!bg-transparent !p-0 !shadow-none !ring-0">
        <div class="w-[540px] max-w-[540px] rounded-[2rem] bg-white p-8 shadow-[0_28px_70px_rgba(15,23,42,0.18)]">

            <h3 class="text-xl font-bold text-ink">{{ __('Export groupé') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Paramètres de l\'export comptable') }}</p>

            {{-- Période --}}
            <div class="mt-6">
                <label class="text-sm font-medium text-slate-700">{{ __('Période') }}</label>
                <select
                    wire:model.live="exportPeriod"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                >
                    @foreach (['Mois', 'Trimestre', 'Semestre', 'Année'] as $groupType)
                        @php $grouped = collect($this->exportPeriods)->where('type', $groupType); @endphp
                        @if ($grouped->isNotEmpty())
                            <optgroup label="{{ $groupType }}">
                                @foreach ($grouped as $period)
                                    <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>

            {{-- Format --}}
            <div class="mt-5">
                <label class="text-sm/6 font-medium text-slate-700">{{ __('Format') }}</label>
                <div class="mt-3 flex items-center space-x-10">
                    @foreach ([
                        'sage100' => 'Sage 100',
                        'ebp'    => 'EBP',
                        'excel'  => 'Excel',
                    ] as $value => $label)
                        <div class="flex items-center">
                            <input
                                id="export-format-{{ $value }}"
                                type="radio"
                                wire:model="exportFormat"
                                value="{{ $value }}"
                                class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-accent checked:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent not-checked:before:hidden forced-colors:appearance-auto forced-colors:before:hidden"
                            />
                            <label for="export-format-{{ $value }}" class="ml-3 block cursor-pointer text-sm/6 font-medium text-ink">
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Clients inclus --}}
            <div class="mt-5">
                <label class="text-sm/6 font-medium text-slate-700">{{ __('Clients inclus') }}</label>
                <div class="mt-3 flex items-center space-x-10">
                    @foreach ([
                        'all'    => __('Tous les clients'),
                        'manual' => __('Sélection manuelle'),
                    ] as $value => $label)
                        <div class="flex items-center">
                            <input
                                id="client-selection-{{ $value }}"
                                type="radio"
                                wire:model.live="clientSelection"
                                value="{{ $value }}"
                                class="relative size-4 appearance-none rounded-full border border-slate-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-accent checked:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent not-checked:before:hidden forced-colors:appearance-auto forced-colors:before:hidden"
                            />
                            <label for="client-selection-{{ $value }}" class="ml-3 block cursor-pointer text-sm/6 font-medium text-ink">
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- Client checkboxes --}}
                @if ($clientSelection === 'manual')
                    <div class="mt-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs text-slate-400">
                                {{ count($selectedClientIds) }} {{ __('sélectionné(s)') }}
                            </span>
                            <button
                                type="button"
                                wire:click="toggleAllClients"
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                {{ count($selectedClientIds) === $this->clients->count() ? __('Tout désélectionner') : __('Tout sélectionner') }}
                            </button>
                        </div>
                        <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 p-3">
                            @foreach ($this->clients as $client)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 transition hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleClient('{{ $client->id }}')"
                                        @checked(in_array($client->id, $selectedClientIds))
                                        class="size-4 rounded border-slate-300 text-accent focus:ring-accent"
                                    />
                                    <span class="text-sm font-medium text-ink">{{ $client->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Résumé --}}
            @if ($exportPeriod)
                <div class="mt-6 rounded-xl bg-slate-50 px-5 py-3 text-sm font-medium text-slate-600">
                    {{ $this->exportInvoiceCount }} {{ $this->exportInvoiceCount > 1 ? 'factures' : 'facture' }}
                    · {{ $this->exportPeriodLabel() }}
                    · {{ ['sage100' => 'Sage 100', 'ebp' => 'EBP', 'excel' => 'Excel'][$exportFormat] ?? $exportFormat }}
                    · {{ $this->selectedClientsCount }} {{ $this->selectedClientsCount > 1 ? 'clients' : 'client' }}
                </div>
            @endif

            {{-- Action --}}
            <div class="mt-6">
                <button
                    type="button"
                    wire:click="generateExport"
                    @class([
                        'w-full rounded-2xl py-3.5 text-base font-semibold transition',
                        'bg-primary text-white shadow-sm hover:bg-primary/90' => $this->exportInvoiceCount > 0,
                        'cursor-not-allowed bg-slate-100 text-slate-400' => $this->exportInvoiceCount === 0,
                    ])
                    @if ($this->exportInvoiceCount === 0) disabled @endif
                >
                    {{ __('Générer et télécharger') }}
                </button>
            </div>
        </div>
    </flux:modal>

</div>

<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Services\PME\ClientService;

new #[Title('Clients')] #[Layout('layouts::pme')] class extends Component {
    public ?Company $company = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'segment')]
    public string $segment = 'all';

    #[Url(as: 'sort')]
    public string $sort = 'revenue_desc';

    #[Url(as: 'periode')]
    public string $period = 'year';

    /** @var array<string, mixed>|null */
    private ?array $portfolioCache = null;

    public function mount(): void
    {
        $this->company = app(ClientService::class)->companyForUser(auth()->user());
    }

    #[Computed]
    public function currentPeriodLabel(): string
    {
        return $this->periodOptions[$this->period] ?? $this->periodOptions['year'];
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $rows = $this->allRows;

        if ($this->search !== '') {
            $term = mb_strtolower(trim($this->search));

            $rows = array_values(array_filter($rows, function (array $row) use ($term): bool {
                return str_contains(mb_strtolower($row['name']), $term)
                    || str_contains(mb_strtolower((string) ($row['phone'] ?? '')), $term)
                    || str_contains(mb_strtolower((string) ($row['email'] ?? '')), $term);
            }));
        }

        $rows = array_values(array_filter($rows, fn (array $row) => $this->matchesSegment($row)));

        usort($rows, function (array $a, array $b): int {
            $comparison = match ($this->sort) {
                'outstanding_desc' => $b['outstanding_amount'] <=> $a['outstanding_amount'],
                'score_desc' => match (true) {
                    $a['payment_score'] === null && $b['payment_score'] === null => 0,
                    $a['payment_score'] === null => 1,
                    $b['payment_score'] === null => -1,
                    default => $b['payment_score'] <=> $a['payment_score'],
                },
                'delay_desc' => $b['average_payment_days'] <=> $a['average_payment_days'],
                'name_asc' => strcmp($a['name'], $b['name']),
                default => $b['period_revenue'] <=> $a['period_revenue'],
            };

            if ($comparison !== 0) {
                return $comparison;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function allRows(): array
    {
        return $this->portfolioData()['rows'];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return $this->portfolioData()['summary'];
    }

    /** @return array<string, int> */
    #[Computed]
    public function segmentCounts(): array
    {
        return $this->portfolioData()['segment_counts'];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function insight(): ?array
    {
        return $this->portfolioData()['insight'];
    }

    /** @return array<string, string> */
    #[Computed]
    public function periodOptions(): array
    {
        return app(ClientService::class)->periodOptions();
    }

    /** @return array<string, string> */
    #[Computed]
    public function sortOptions(): array
    {
        return app(ClientService::class)->sortOptions();
    }

    #[Computed]
    public function hasFinancialData(): bool
    {
        return collect($this->allRows)->contains(
            fn (array $row) => $row['invoice_count'] > 0 || $row['quote_count'] > 0
        );
    }

    #[On('client-created')]
    public function onClientCreated(string $id, string $name): void
    {
        session()->flash('client-saved', $name);

        $this->redirect(route('pme.clients.show', $id), navigate: true);
    }

    private function portfolioData(): array
    {
        if ($this->portfolioCache !== null) {
            return $this->portfolioCache;
        }

        if (! $this->company) {
            return $this->portfolioCache = [
                'rows' => [],
                'summary' => app(ClientService::class)->summary([]),
                'segment_counts' => app(ClientService::class)->segmentCounts([]),
                'insight' => null,
            ];
        }

        $service = app(ClientService::class);
        $rows = $service->portfolioRows($this->company, $this->period);

        return $this->portfolioCache = [
            'rows' => $rows,
            'summary' => $service->summary($rows),
            'segment_counts' => $service->segmentCounts($rows),
            'insight' => $service->insight($rows),
        ];
    }

    private function matchesSegment(array $row): bool
    {
        return match ($this->segment) {
            'reliable' => $row['is_reliable'],
            'watch' => $row['is_watch'],
            'frequent_delays' => $row['has_frequent_delays'],
            'inactive' => $row['is_inactive'],
            'big_accounts' => $row['is_big_account'],
            default => true,
        };
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @if (session('client-saved'))
        <div
            x-data
            x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', title: @js(session('client-saved').' '.__('a été ajouté à votre portefeuille.')) } })))"
        ></div>
    @endif

    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">
                    {{ __('Clients') }} · {{ $this->currentPeriodLabel }}
                </p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Mes clients') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $this->summary['active_clients'] }} {{ $this->summary['active_clients'] > 1 ? __('clients actifs') : __('client actif') }}
                    · {{ __('vue portefeuille enrichie par la facturation, les paiements et les relances') }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="$dispatch('open-create-client-modal')"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    {{ __('Nouveau client') }}
                </button>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="users" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Actifs') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Clients actifs') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $this->summary['active_clients'] }}</p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ $this->currentPeriodLabel }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('CA moyen par client') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-ink">
                @if ($this->summary['average_revenue_per_client'] > 0)
                    {{ format_money($this->summary['average_revenue_per_client']) }}
                @else
                    —
                @endif
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="sparkles" class="size-5 text-emerald-600" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ $this->summary['best_payer']['score_label'] ?? __('À définir') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Meilleur payeur') }}</p>
            <p class="mt-1 text-xl font-semibold tracking-tight text-ink">
                {{ $this->summary['best_payer']['name'] ?? '—' }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                {{ $this->summary['best_payer']['support'] ?? __('Aucun historique suffisant pour le moment.') }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="shield-exclamation" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    {{ $this->summary['watch_client']['score_label'] ?? __('Stable') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Client à surveiller') }}</p>
            <p class="mt-1 text-xl font-semibold tracking-tight text-ink">
                {{ $this->summary['watch_client']['name'] ?? '—' }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                {{ $this->summary['watch_client']['support'] ?? __('Aucun signal critique détecté.') }}
            </p>
        </article>
    </section>

    @if ($this->insight)
        <section class="rounded-[1.75rem] border px-5 py-4 @if ($this->insight['tone'] === 'rose') border-rose-200 bg-rose-50 @elseif ($this->insight['tone'] === 'amber') border-amber-200 bg-amber-50 @else border-teal-200 bg-teal-50/70 @endif">
            <p class="text-sm font-semibold text-ink">{{ $this->insight['title'] }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $this->insight['body'] }}</p>
        </section>
    @endif

    <x-ui.table-panel
        :title="__('Mes clients')"
        :description="__('Liste des clients et indicateurs associés.')"
        :filterLabel="__('Filtrer les clients')"
    >
        <x-slot:filters>
            @foreach ([
                'all'             => ['label' => 'Tous',              'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'reliable'        => ['label' => 'Bons payeurs',      'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'watch'           => ['label' => 'À surveiller',      'activeClass' => 'bg-amber-500 text-white',   'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'frequent_delays' => ['label' => 'Retards fréquents', 'activeClass' => 'bg-rose-500 text-white',    'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'inactive'        => ['label' => 'Inactifs',          'activeClass' => 'bg-slate-500 text-white',   'badgeInactive' => 'bg-slate-100 text-slate-600'],
                'big_accounts'    => ['label' => 'Gros comptes',      'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
            ] as $value => $tab)
                <x-ui.filter-chip
                    wire:click="$set('segment', '{{ $value }}')"
                    :label="$tab['label']"
                    :active="$segment === $value"
                    :activeClass="$tab['activeClass']"
                    :badgeInactive="$tab['badgeInactive']"
                    :count="$this->segmentCounts[$value]"
                />
            @endforeach
        </x-slot:filters>

        <x-slot:search>
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="{{ __('Rechercher un client, un secteur, un contact...') }}"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    />
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:w-[26rem] xl:grid-cols-2">
                    <x-select-native>
                        <select
                            wire:model.live="period"
                            class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            @foreach ($this->periodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-select-native>

                    <x-select-native>
                        <select
                            wire:model.live="sort"
                            class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                        >
                            @foreach ($this->sortOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-select-native>
                </div>
            </div>

            <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
                {{ __('Le score paiement combine le délai moyen, les retards, les montants impayés et la fréquence de relance.') }}
            </div>
        </x-slot:search>

        @if ($this->segmentCounts['all'] === 0)
            <div class="flex flex-col items-center justify-center border-t border-slate-100 p-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
                    <x-app.icon name="clients" class="size-6 text-primary" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Votre portefeuille client démarre ici') }}</h3>
                <p class="mt-2 max-w-xl text-sm text-slate-500">
                    {{ __("Ajoutez vos premiers clients pour suivre leur chiffre d'affaires, leurs délais de paiement et vos impayés en cours.") }}
                </p>
                <button
                    type="button"
                    wire:click="$dispatch('open-create-client-modal')"
                    class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Ajouter un client') }}
                </button>
            </div>
        @else
            @if (! $this->hasFinancialData)
                <div class="border-t border-slate-100 px-5 py-4 text-sm text-slate-600">
                    {{ __("Vos clients sont créés, mais la vue intelligence client s'enrichira dès les premières factures, paiements et relances.") }}
                </div>
            @endif

            @if (count($this->rows) > 0)
                <div class="overflow-x-auto border-t border-slate-100">
                    <table class="w-full min-w-[1040px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/80">
                                <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Client') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('CA') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Factures ce mois') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Impayés en cours') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Délai moyen') }}</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Score paiement') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->rows as $row)
                                <tr wire:key="pme-client-row-{{ $row['id'] }}" class="cursor-pointer align-top transition hover:bg-slate-50/70" @click="Livewire.navigate('{{ route('pme.clients.show', $row['id']) }}')">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-mist text-sm font-bold text-primary">
                                                {{ $row['initials'] }}
                                            </span>
                                            <div class="min-w-0">
                                                <span class="font-semibold text-ink">{{ $row['name'] }}</span>
                                                <p class="mt-1 text-sm text-slate-500">{{ $row['last_interaction_label'] }}</p>
                                                <p class="mt-1 text-sm text-slate-500">{{ $row['last_interaction_detail'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 font-semibold text-ink">
                                        @if ($row['period_revenue'] > 0)
                                            {{ format_money($row['period_revenue'], compact: true) }}
                                        @else
                                            <span class="text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ $row['invoice_count_this_month'] }}</td>
                                    <td class="px-4 py-4">
                                        @if ($row['outstanding_amount'] > 0)
                                            <div class="font-semibold text-rose-600">
                                                {{ format_money($row['outstanding_amount'], compact: true) }}
                                            </div>
                                        @else
                                            <span class="text-slate-500">{{ format_money(0, compact: true) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($row['average_payment_days'] > 0)
                                            <span class="font-semibold text-ink">{{ $row['average_payment_days'] }}j</span>
                                        @else
                                            <span class="text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($row['payment_score'] !== null)
                                            <span @class([
                                                'inline-flex whitespace-nowrap items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset',
                                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $row['payment_tone'] === 'emerald',
                                                'bg-teal-50 text-teal-700 ring-teal-600/20' => $row['payment_tone'] === 'teal',
                                                'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['payment_tone'] === 'amber',
                                                'bg-rose-50 text-rose-700 ring-rose-600/20' => $row['payment_tone'] === 'rose',
                                            ])>
                                                <span>{{ $row['payment_label'] }}</span>
                                                <span class="opacity-70">{{ $row['payment_score'] }}</span>
                                            </span>
                                        @else
                                            <span class="text-sm text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-14 text-center">
                    <p class="text-base font-semibold text-ink">{{ __('Aucun client ne correspond à vos filtres') }}</p>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Essayez une autre recherche, élargissez la période ou changez de segmentation.') }}
                    </p>
                </div>
            @endif
        @endif
    </x-ui.table-panel>

    <livewire:create-client-modal :company="$company" />

</div>

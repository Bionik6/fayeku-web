<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Clients\Services\ClientService;

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

    public bool $showCreateClientModal = false;

    public string $clientName = '';

    public string $clientSector = '';

    public string $clientPhone = '';

    public string $clientEmail = '';

    public string $clientTaxId = '';

    public string $clientAddress = '';

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
                    || str_contains(mb_strtolower($row['sector']), $term)
                    || str_contains(mb_strtolower((string) ($row['phone'] ?? '')), $term)
                    || str_contains(mb_strtolower((string) ($row['email'] ?? '')), $term);
            }));
        }

        $rows = array_values(array_filter($rows, fn (array $row) => $this->matchesSegment($row)));

        usort($rows, function (array $a, array $b): int {
            $comparison = match ($this->sort) {
                'outstanding_desc' => $b['outstanding_amount'] <=> $a['outstanding_amount'],
                'score_desc' => $b['payment_score'] <=> $a['payment_score'],
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

    public function openCreateClientModal(): void
    {
        $this->resetValidation();
        $this->resetClientForm();
        $this->showCreateClientModal = true;
    }

    public function saveClient(): void
    {
        abort_unless($this->company && auth()->user()->can('create', Client::class), 403);

        $validated = $this->validate([
            'clientName' => ['required', 'string', 'max:255'],
            'clientSector' => ['nullable', 'string', 'max:100'],
            'clientPhone' => ['nullable', 'string', 'max:30'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
            'clientTaxId' => ['nullable', 'string', 'max:100'],
            'clientAddress' => ['nullable', 'string', 'max:500'],
        ], [
            'clientName.required' => __('Le nom du client est requis.'),
            'clientEmail.email' => __('L’adresse email doit être valide.'),
        ]);

        $client = Client::query()->create([
            'company_id' => $this->company->id,
            'name' => trim($validated['clientName']),
            'sector' => $this->emptyToNull($validated['clientSector'] ?? ''),
            'phone' => $this->normalizePhone($validated['clientPhone'] ?? ''),
            'email' => $this->emptyToNull($validated['clientEmail'] ?? ''),
            'tax_id' => $this->emptyToNull($validated['clientTaxId'] ?? ''),
            'address' => $this->emptyToNull($validated['clientAddress'] ?? ''),
        ]);

        $this->showCreateClientModal = false;
        session()->flash('client-saved', $client->name);

        $this->redirect(route('pme.clients.show', $client), navigate: true);
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

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        $prefix = match ($this->company?->country_code) {
            'CI' => '225',
            default => '221',
        };

        if (str_starts_with($digits, $prefix)) {
            return '+'.$digits;
        }

        return '+'.$prefix.$digits;
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resetClientForm(): void
    {
        $this->clientName = '';
        $this->clientSector = '';
        $this->clientPhone = '';
        $this->clientEmail = '';
        $this->clientTaxId = '';
        $this->clientAddress = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @if (session('client-saved'))
        <section class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('client-saved') }} {{ __('a été ajouté à votre portefeuille.') }}
        </section>
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
                    wire:click="openCreateClientModal"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
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
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
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
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                    {{ $this->currentPeriodLabel }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('CA moyen par client') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-ink">
                @if ($this->summary['average_revenue_per_client'] > 0)
                    {{ number_format($this->summary['average_revenue_per_client'], 0, ',', ' ') }} F
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
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
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
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
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

    <section class="app-shell-panel p-5">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="{{ __('Rechercher un client, un secteur, un contact...') }}"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-400 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    />
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:w-[26rem] xl:grid-cols-2">
                    <select
                        wire:model.live="period"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    >
                        @foreach ($this->periodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select
                        wire:model.live="sort"
                        class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    >
                        @foreach ($this->sortOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ([
                    'all' => ['label' => 'Tous', 'count' => $this->segmentCounts['all']],
                    'reliable' => ['label' => 'Bons payeurs', 'count' => $this->segmentCounts['reliable']],
                    'watch' => ['label' => 'À surveiller', 'count' => $this->segmentCounts['watch']],
                    'frequent_delays' => ['label' => 'Retards fréquents', 'count' => $this->segmentCounts['frequent_delays']],
                    'inactive' => ['label' => 'Inactifs', 'count' => $this->segmentCounts['inactive']],
                    'big_accounts' => ['label' => 'Gros comptes', 'count' => $this->segmentCounts['big_accounts']],
                ] as $value => $tab)
                    <button
                        type="button"
                        wire:click="$set('segment', '{{ $value }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
                            'bg-primary text-white shadow-sm' => $segment === $value,
                            'border border-slate-200 bg-white text-slate-600 hover:border-primary/30 hover:text-primary' => $segment !== $value,
                        ])
                    >
                        {{ $tab['label'] }}
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-bold',
                            'bg-white/20 text-white' => $segment === $value,
                            'bg-slate-100 text-slate-500' => $segment !== $value,
                        ])>{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
                {{ __('Le score paiement combine le délai moyen, les retards, les montants impayés et la fréquence de relance.') }}
            </div>
        </div>
    </section>

    @if ($this->segmentCounts['all'] === 0)
        <section class="app-shell-panel flex flex-col items-center justify-center p-16 text-center">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-mist">
                <x-app.icon name="clients" class="size-6 text-primary" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-ink">{{ __('Votre portefeuille client démarre ici') }}</h3>
            <p class="mt-2 max-w-xl text-sm text-slate-500">
                {{ __('Ajoutez vos premiers clients pour suivre leur chiffre d’affaires, leurs délais de paiement et vos impayés en cours.') }}
            </p>
            <button
                type="button"
                wire:click="openCreateClientModal"
                class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
            >
                <flux:icon name="plus" class="size-4" />
                {{ __('Ajouter un client') }}
            </button>
        </section>
    @else
        @if (! $this->hasFinancialData)
            <section class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600">
                {{ __('Vos clients sont créés, mais la vue intelligence client s’enrichira dès les premières factures, paiements et relances.') }}
            </section>
        @endif

        <section class="app-shell-panel overflow-hidden">
            @if (count($this->rows) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/80">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Client') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('CA') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Factures ce mois') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Impayés en cours') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Délai moyen') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Score paiement') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->rows as $row)
                                <tr wire:key="pme-client-row-{{ $row['id'] }}" class="align-top transition hover:bg-slate-50/70">
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-mist text-xs font-bold text-primary">
                                                {{ $row['initials'] }}
                                            </span>
                                            <div class="min-w-0">
                                                <a
                                                    href="{{ route('pme.clients.show', $row['id']) }}"
                                                    wire:navigate
                                                    class="font-semibold text-ink transition hover:text-primary"
                                                >
                                                    {{ $row['name'] }}
                                                </a>
                                                <p class="mt-1 text-xs text-slate-500">{{ $row['last_interaction_label'] }}</p>
                                                <p class="mt-1 text-xs text-slate-400">{{ $row['last_interaction_detail'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 font-semibold text-ink">
                                        @if ($row['period_revenue'] > 0)
                                            {{ number_format($row['period_revenue'], 0, ',', ' ') }} F
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-600">{{ $row['invoice_count_this_month'] }}</td>
                                    <td class="px-4 py-4">
                                        @if ($row['outstanding_amount'] > 0)
                                            <div class="font-semibold text-rose-600">
                                                {{ number_format($row['outstanding_amount'], 0, ',', ' ') }} F
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ $row['outstanding_count'] }} {{ $row['outstanding_count'] > 1 ? __('factures ouvertes') : __('facture ouverte') }}
                                            </div>
                                        @else
                                            <span class="text-slate-400">0 F</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($row['average_payment_days'] > 0)
                                            <span class="font-semibold text-ink">{{ $row['average_payment_days'] }}j</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
                                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $row['payment_tone'] === 'emerald',
                                            'bg-teal-50 text-teal-700 ring-teal-600/20' => $row['payment_tone'] === 'teal',
                                            'bg-amber-50 text-amber-700 ring-amber-600/20' => $row['payment_tone'] === 'amber',
                                            'bg-rose-50 text-rose-700 ring-rose-600/20' => $row['payment_tone'] === 'rose',
                                        ])>
                                            <span>{{ $row['payment_label'] }}</span>
                                            <span class="opacity-70">{{ $row['payment_score'] }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <flux:dropdown position="bottom" align="end">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary"
                                            >
                                                {{ __('Actions') }}
                                                <flux:icon name="chevron-down" class="size-3.5" />
                                            </button>

                                            <flux:menu>
                                                <flux:menu.item :href="route('pme.clients.show', $row['id'])" wire:navigate>
                                                    <flux:icon name="eye" class="size-4 text-slate-400" />
                                                    {{ __('Voir client') }}
                                                </flux:menu.item>

                                                <flux:menu.item :href="route('pme.invoices.index', ['q' => $row['name']])" wire:navigate>
                                                    <flux:icon name="document-text" class="size-4 text-slate-400" />
                                                    {{ __('Nouvelle facture') }}
                                                </flux:menu.item>

                                                <flux:menu.item :href="route('pme.clients.show', ['client' => $row['id'], 'focus' => 'relances'])" wire:navigate>
                                                    <flux:icon name="bell" class="size-4 text-slate-400" />
                                                    {{ __('Relancer') }}
                                                </flux:menu.item>

                                                <flux:menu.item :href="route('pme.clients.show', ['client' => $row['id'], 'focus' => 'impayes'])" wire:navigate>
                                                    <flux:icon name="exclamation-circle" class="size-4 text-slate-400" />
                                                    {{ __('Voir impayés') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
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
        </section>
    @endif

    <flux:modal wire:model="showCreateClientModal" class="max-w-2xl">
        <form wire:submit="saveClient" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nouveau client') }}</flux:heading>
                <flux:subheading>
                    {{ __('Ajoutez les informations de contact et les données business utiles à la facturation et au recouvrement.') }}
                </flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="clientName" :label="__('Nom du client')" type="text" required autofocus />
                <flux:input wire:model="clientSector" :label="__('Secteur')" type="text" placeholder="Télécom, Santé, Distribution..." />
                <flux:input wire:model="clientPhone" :label="__('Téléphone / WhatsApp')" type="tel" placeholder="+221 77 123 45 67" />
                <flux:input wire:model="clientEmail" :label="__('Email')" type="email" placeholder="contact@client.sn" />
                <flux:input wire:model="clientTaxId" :label="__('Identifiant fiscal')" type="text" placeholder="NINEA / RCCM / NCC" />
                <div class="md:col-span-2">
                    <flux:textarea wire:model="clientAddress" :label="__('Adresse')" rows="3" />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                {{ __('Les coordonnées client serviront aussi aux relances WhatsApp, SMS et email selon le canal choisi.') }}
            </div>

            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    wire:click="$set('showCreateClientModal', false)"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                >
                    {{ __('Annuler') }}
                </button>
                <flux:button variant="primary" type="submit">
                    {{ __('Créer le client') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>

<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Portfolio\Services\AlertService;

new #[Title('Alertes')] class extends Component {
    #[Url(as: 'filtre')]
    public string $filter = 'all';

    public ?Company $firm = null;

    public function mount(): void
    {
        $this->firm = auth()->user()->companies()
            ->where('type', 'accountant_firm')
            ->first();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function alerts(): array
    {
        if (! $this->firm) {
            return [];
        }

        $filterValue = $this->filter === 'all' ? null : $this->filter;

        return app(AlertService::class)->build($this->firm, $filterValue);
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        if (! $this->firm) {
            return ['all' => 0, 'critical' => 0, 'watch' => 0, 'new' => 0];
        }

        $service = app(AlertService::class);
        $all = $service->build($this->firm);

        return [
            'all'      => count($all),
            'critical' => count(array_filter($all, fn ($a) => $a['type'] === 'critical')),
            'watch'    => count(array_filter($all, fn ($a) => $a['type'] === 'watch')),
            'new'      => count(array_filter($all, fn ($a) => $a['type'] === 'new')),
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
                        {{ $this->counts['all'] }} {{ $this->counts['all'] > 1 ? 'alertes' : 'alerte' }}
                        · {{ ucfirst(now()->locale('fr_FR')->translatedFormat('F Y')) }}
                    </p>
                </div>

                @if ($this->counts['all'] > 0)
                    <span class="inline-flex shrink-0 items-center self-start rounded-full bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 lg:self-center">
                        {{ $this->counts['critical'] > 0 ? $this->counts['critical'].' critique'.($this->counts['critical'] > 1 ? 's' : '') : __('Tout est à jour') }}
                    </span>
                @else
                    <span class="inline-flex shrink-0 items-center self-start rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 lg:self-center">
                        {{ __('Tout est à jour') }}
                    </span>
                @endif
            </div>
        </section>

        {{-- ─── Filtres ────────────────────────────────────────────────────── --}}
        <section class="app-shell-panel p-5">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Filtrer par criticité') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ([
                    'all'      => ['label' => 'Toutes',    'count' => $this->counts['all'],      'activeClass' => 'bg-primary text-white',      'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-slate-100 text-slate-500'],
                    'critical' => ['label' => 'Critiques', 'count' => $this->counts['critical'], 'activeClass' => 'bg-rose-500 text-white',     'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-rose-100 text-rose-700'],
                    'watch'    => ['label' => 'En veille', 'count' => $this->counts['watch'],    'activeClass' => 'bg-amber-500 text-white',    'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-amber-100 text-amber-700'],
                    'new'      => ['label' => 'Nouvelles', 'count' => $this->counts['new'],      'activeClass' => 'bg-emerald-600 text-white',  'badgeActive' => 'bg-white/20 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                ] as $key => $tab)
                    <button
                        wire:click="$set('filter', '{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            $tab['activeClass']                                              => $filter === $key,
                            'bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary' => $filter !== $key,
                        ])
                    >
                        {{ $tab['label'] }}
                        <span @class([
                            'rounded-full px-1.5 py-px text-xs font-bold',
                            $tab['badgeActive']   => $filter === $key,
                            $tab['badgeInactive'] => $filter !== $key,
                        ])>{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- ─── Liste des alertes ──────────────────────────────────────────── --}}
        <section class="app-shell-panel overflow-hidden">
            @if (count($this->alerts) > 0)
                <div class="divide-y divide-slate-100">
                    @foreach ($this->alerts as $alert)
                        <div class="flex items-center gap-4 px-6 py-4">
                            <span @class([
                                'flex size-10 shrink-0 items-center justify-center rounded-2xl text-base font-bold',
                                'bg-rose-100 text-rose-600'     => $alert['type'] === 'critical',
                                'bg-amber-100 text-amber-600'   => $alert['type'] === 'watch',
                                'bg-emerald-100 text-emerald-600' => $alert['type'] === 'new',
                            ])>
                                @if ($alert['type'] === 'critical') ! @elseif ($alert['type'] === 'watch') ~ @else + @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">{{ $alert['title'] }}</p>
                                <p class="mt-0.5 truncate text-sm text-slate-500">{{ $alert['subtitle'] }}</p>
                            </div>

                            <span @class([
                                'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-rose-50 text-rose-700'       => $alert['type'] === 'critical',
                                'bg-amber-50 text-amber-700'     => $alert['type'] === 'watch',
                                'bg-emerald-50 text-emerald-700' => $alert['type'] === 'new',
                            ])>
                                @if ($alert['type'] === 'critical') Critique
                                @elseif ($alert['type'] === 'watch') En veille
                                @else Nouvelle inscription
                                @endif
                            </span>

                            @if ($alert['company_id'])
                                <a
                                    href="{{ route('clients.show', $alert['company_id']) }}"
                                    wire:navigate
                                    class="shrink-0 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:border-primary/20 hover:text-primary"
                                >
                                    {{ __('Voir fiche') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-50">
                        <flux:icon name="check-circle" class="size-7 text-emerald-600" />
                    </div>
                    <p class="mt-4 font-semibold text-ink">
                        @if ($filter === 'all') {{ __('Aucune alerte pour le moment') }}
                        @elseif ($filter === 'critical') {{ __('Aucun impayé critique') }}
                        @elseif ($filter === 'watch') {{ __('Aucun client en veille') }}
                        @else {{ __('Aucune nouvelle inscription cette semaine') }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-slate-400">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
                </div>
            @endif
        </section>

</div>

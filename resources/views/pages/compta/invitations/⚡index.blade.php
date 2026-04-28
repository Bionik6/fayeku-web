<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Models\Compta\Commission;
use App\Models\Compta\PartnerInvitation;
use App\Services\Compta\InvitationService;

new #[Title('Invitations')] class extends Component
{
    public ?Company $firm = null;

    public string $inviteSearch = '';

    public string $statusFilter = 'all';

    public function mount(): void
    {
        $this->firm = auth()->user()->accountantFirm();
    }

    // ─── Computed : Invitations ────────────────────────────────────────────

    /** @return Collection<int, PartnerInvitation> */
    #[Computed]
    public function invitations(): Collection
    {
        if (! $this->firm) {
            return collect();
        }

        $query = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->orderByDesc('created_at');

        if ($this->inviteSearch !== '') {
            $search = $this->inviteSearch;
            $query->where(function ($q) use ($search) {
                $q->where('invitee_company_name', 'like', "%{$search}%")
                    ->orWhere('invitee_name', 'like', "%{$search}%")
                    ->orWhere('invitee_phone', 'like', "%{$search}%");
            });
        }

        match ($this->statusFilter) {
            'not_opened' => $query->where('status', 'pending')->whereNull('link_opened_at'),
            'opened' => $query->where('status', 'pending')->whereNotNull('link_opened_at'),
            'registering' => $query->where('status', 'registering'),
            'activated' => $query->where('status', 'accepted'),
            'expired' => $query->where('status', 'expired'),
            'to_remind' => $query->where('status', 'pending')->where('created_at', '<', now()->subDays(2)),
            default => null,
        };

        return $query->get();
    }

    #[Computed]
    public function totalSent(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('status', '!=', 'accepted')
            ->where('status', '!=', 'expired')
            ->count();
    }

    #[Computed]
    public function activatedThisMonth(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('status', 'accepted')
            ->whereYear('accepted_at', now()->year)
            ->whereMonth('accepted_at', now()->month)
            ->count();
    }

    #[Computed]
    public function conversionRate(): int
    {
        $total = $this->totalSent;
        if ($total === 0) {
            return 0;
        }

        $activated = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('status', 'accepted')
            ->whereYear('created_at', now()->year)
            ->count();

        return (int) round($activated / $total * 100);
    }

    // ─── Computed : Compteurs statuts ─────────────────────────────────────

    /** @return array{all: int, to_remind: int, activated: int, expired: int} */
    #[Computed]
    public function statusCounts(): array
    {
        if (! $this->firm) {
            return ['all' => 0, 'to_remind' => 0, 'activated' => 0, 'expired' => 0];
        }

        $base = PartnerInvitation::query()->where('accountant_firm_id', $this->firm->id);

        return [
            'all' => (clone $base)->count(),
            'to_remind' => (clone $base)->where('status', 'pending')->where('created_at', '<', now()->subDays(2))->count(),
            'activated' => (clone $base)->where('status', 'accepted')->count(),
            'expired' => (clone $base)->where('status', 'expired')->count(),
        ];
    }

    // ─── Computed : Pont vers Commissions ─────────────────────────────────

    #[Computed]
    public function commissionMonthTotal(): int
    {
        if (! $this->firm) {
            return 0;
        }

        return (int) Commission::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->whereYear('period_month', now()->year)
            ->whereMonth('period_month', now()->month)
            ->sum('amount');
    }

    #[Computed]
    public function nextPaymentDate(): string
    {
        return format_date(now()->addMonth()->startOfMonth()->addDays(4), withYear: false);
    }

    // ─── Actions ──────────────────────────────────────────────────────────

    #[On('invitation-sent')]
    public function onInvitationSent(): void
    {
        unset($this->invitations, $this->totalSent, $this->pendingCount);
    }

    #[Computed]
    public function partnerShareMessage(): string
    {
        if (! $this->firm) {
            return '';
        }

        return app(InvitationService::class)
            ->composePartnerShareMessage($this->firm, auth()->user());
    }

    public function setFilter(string $filter): void
    {
        $this->statusFilter = $filter;
        unset($this->invitations);
    }

    public function updatedInviteSearch(): void
    {
        unset($this->invitations);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 1. Hero                                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Programme Partenaire Fayeku') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Invitations & activations') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __("Invitez des PME, suivez leur progression et relancez les contacts qui n'ont pas encore activé leur espace.") }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    x-data="{ link: '{{ $this->firm?->invite_code ? route('join.landing', ['code' => $this->firm->invite_code]) : '' }}' }"
                    x-on:click="navigator.clipboard.writeText(link).then(() => $dispatch('toast', { type: 'success', title: 'Lien copié dans le presse-papiers !' }))"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50"
                >
                    <flux:icon name="link" class="size-4" />
                    {{ __('Copier mon lien') }}
                </button>
                <button
                    type="button"
                    wire:click="$dispatch('open-invite-pme')"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-primary/20 bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Inviter une PME') }}
                </button>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 2. KPI de suivi                                               --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">

        {{-- Invitations envoyées --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="paper-airplane" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Depuis jan.') }} {{ now()->year }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Invitations envoyées') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $this->totalSent }}</p>
        </article>

        {{-- En attente d'activation --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-600" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ $this->pendingCount }} {{ __('PME') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __("En attente d'activation") }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-600">{{ $this->pendingCount }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('PME à relancer') }}</p>
        </article>

        {{-- Activées ce mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ format_month(now()) }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Activées ce mois') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">{{ $this->activatedThisMonth }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('En') }} {{ format_month(now()) }}</p>
        </article>

        {{-- Taux de conversion --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-50">
                    <flux:icon name="chart-bar" class="size-5 text-sky-600" />
                </div>
                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-sky-50 px-2.5 py-1 text-sm font-medium text-sky-700">%</span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Taux de conversion') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $this->conversionRate }}%</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Invitations devenues clients actifs') }}</p>
        </article>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 3. Pont vers Commissions                                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex items-center gap-4 p-5">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                <flux:icon name="banknotes" class="size-5 text-accent" />
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-ink">
                    {{ __('Revenus partenaire ce mois-ci') }}
                    <span class="ml-2 font-bold text-accent">{{ format_money($this->commissionMonthTotal) }}</span>
                </p>
                <p class="mt-0.5 text-sm text-slate-500">
                    @if ($this->pendingCount > 0)
                        {{ $this->pendingCount }} {{ $this->pendingCount > 1 ? __('PME sont encore en attente d\'activation.') : __('PME est encore en attente d\'activation.') }}
                        {{ __('Une fois activées, elles pourront générer de nouvelles commissions éligibles.') }}
                    @else
                        {{ __('Basé sur vos PME actives actuelles.') }}
                        @if ($this->nextPaymentDate)
                            {{ __('Versement prévu le') }} {{ $this->nextPaymentDate }} {{ __('via Wave.') }}
                        @endif
                    @endif
                </p>
            </div>
            <a
                href="{{ route('commissions.index') }}"
                wire:navigate
                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
            >
                {{ __('Voir mes commissions') }}
                <flux:icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 4. Actions pour recruter des PME                              --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <section class="app-shell-panel p-6">
        <x-section-header
            :title="__('Développez votre portefeuille partenaire')"
            :subtitle="__('Invitez vos clients, partagez votre lien et suivez les activations pour transformer vos recommandations en revenus récurrents.')"
        />

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Carte 1 : Inviter une PME --}}
            <div class="flex flex-col rounded-2xl border border-primary/20 bg-primary/5 p-5">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                    <flux:icon name="paper-airplane" class="size-5 text-primary" />
                </div>
                <p class="mt-3 text-sm font-semibold text-ink">{{ __('Inviter une PME') }}</p>
                <p class="mt-1 flex-1 text-sm text-slate-500">{{ __('Invitez un client PME existant à rejoindre Fayeku et commencez à générer des revenus partenaire dès son activation.') }}</p>
                <button
                    type="button"
                    wire:click="$dispatch('open-invite-pme')"
                    class="mt-4 inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Inviter une PME') }}
                </button>
            </div>

            {{-- Carte 2 : Copier le lien --}}
            @php
                $partnerMessage = $this->partnerShareMessage;
                $partnerWhatsAppUrl = $firm?->invite_code && $partnerMessage
                    ? 'https://wa.me/?text='.rawurlencode($partnerMessage)
                    : '';
            @endphp
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex size-10 items-center justify-center rounded-xl bg-slate-100">
                    <flux:icon name="link" class="size-5 text-slate-600" />
                </div>
                <p class="mt-3 text-sm font-semibold text-ink">{{ __('Partager votre lien partenaire') }}</p>
                <p class="mt-1 flex-1 text-sm text-slate-500">{{ __("Partagez votre lien d'invitation personnalisé par WhatsApp, email ou directement avec vos clients.") }}</p>
                <div class="mt-4 flex items-center gap-2">
                    <button
                        type="button"
                        x-data="{ message: @js($partnerMessage) }"
                        x-on:click="navigator.clipboard.writeText(message).then(() => $dispatch('toast', { type: 'success', title: 'Message copié dans le presse-papiers !' }))"
                        class="inline-flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-100"
                    >
                        <flux:icon name="clipboard" class="size-4" />
                        {{ __('Copier') }}
                    </button>
                    @if ($partnerWhatsAppUrl)
                        <a
                            href="{{ $partnerWhatsAppUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-green-200 bg-green-50 px-3 py-2.5 text-sm font-medium text-green-700 shadow-sm transition hover:bg-green-100"
                        >
                            <flux:icon name="chat-bubble-left-right" class="size-4" />
                            {{ __('WhatsApp') }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Carte 3 : Invitations en attente --}}
            <div class="flex flex-col rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100">
                    <flux:icon name="clock" class="size-5 text-amber-600" />
                </div>
                <p class="mt-3 text-sm font-semibold text-ink">{{ __('Invitations en attente') }}</p>
                <p class="mt-1 flex-1 text-sm text-slate-500">{{ __("Suivez les PME invitées, relancez celles qui n'ont pas encore activé leur espace et transformez vos invitations en revenus récurrents.") }}</p>
                @if ($this->pendingCount > 0)
                    <p class="mt-2 text-sm font-semibold text-amber-700">
                        {{ $this->pendingCount }} {{ $this->pendingCount > 1 ? __('invitations en attente') : __('invitation en attente') }}
                    </p>
                @endif
                <button
                    type="button"
                    x-on:click="document.getElementById('invitations-table').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="mt-4 inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-sm font-medium text-amber-700 shadow-sm transition hover:bg-amber-50"
                >
                    {{ __('Voir les invitations') }}
                    <flux:icon name="arrow-down" class="size-4" />
                </button>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Bloc 5. Tableau de suivi des invitations                           --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <x-ui.table-panel
        id="invitations-table"
        :title="__('Suivi des invitations')"
        :description="__('Consultez l\'état de chaque invitation envoyée à vos clients PME.')"
        :filterLabel="__('Filtrer les invitations')"
    >
        <x-slot:filters>
            @foreach ([
                'all'       => ['label' => 'Tout',       'dot' => null,           'activeClass' => 'bg-primary text-white',       'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'to_remind' => ['label' => 'À relancer', 'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',     'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'activated' => ['label' => 'Activées',   'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white',   'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'expired'   => ['label' => 'Expirées',   'dot' => 'bg-rose-400',  'activeClass' => 'bg-rose-600 text-white',      'badgeInactive' => 'bg-rose-100 text-rose-700'],
            ] as $key => $tab)
                @php $isActive = ($key === 'all' && $statusFilter === 'all') || $statusFilter === $key; @endphp
                <x-ui.filter-chip
                    wire:click="setFilter('{{ $key }}')"
                    wire:key="filter-{{ $key }}"
                    :label="$tab['label']"
                    :dot="$tab['dot']"
                    :active="$isActive"
                    :activeClass="$tab['activeClass']"
                    :badgeInactive="$tab['badgeInactive']"
                    :count="$this->statusCounts[$key]"
                />
            @endforeach
        </x-slot:filters>

        <x-slot:search>
            <div class="relative">
                <svg class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    wire:model.live.debounce.300ms="inviteSearch"
                    type="text"
                    placeholder="{{ __('Rechercher une entreprise ou un contact…') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                />
            </div>
        </x-slot:search>

        @if ($this->invitations->isEmpty())
            <div class="px-6 pb-8 pt-4 text-center">
                @if ($this->totalSent === 0)
                    <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-teal-50">
                        <flux:icon name="paper-airplane" class="size-6 text-primary" />
                    </div>
                    <p class="mt-4 text-sm font-medium text-ink">{{ __('Aucune invitation envoyée') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Invitez vos premiers clients PME pour commencer à développer vos commissions partenaires.') }}</p>
                    <button
                        type="button"
                        wire:click="$dispatch('open-invite-pme')"
                        class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90"
                    >
                        <flux:icon name="plus" class="size-4" />
                        {{ __('Inviter une PME') }}
                    </button>
                @else
                    <p class="text-sm font-medium text-ink">{{ __('Aucune invitation trouvée') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Essayez de modifier vos filtres ou lancez une nouvelle invitation.') }}</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-t border-slate-100 text-sm font-semibold text-slate-500">
                            <th class="px-6 py-3">{{ __('Entreprise') }}</th>
                            <th class="px-6 py-3">{{ __('Contact') }}</th>
                            <th class="px-6 py-3">{{ __('Invité le') }}</th>
                            <th class="px-6 py-3">{{ __('Statut') }}</th>
                            <th class="px-6 py-3">{{ __('Dernière relance') }}</th>
                            <th class="px-6 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->invitations as $invitation)
                            @php
                                $displayStatus = match (true) {
                                    $invitation->status === 'accepted' => 'activated',
                                    $invitation->status === 'registering' => 'registering',
                                    $invitation->status === 'expired' => 'expired',
                                    default => 'sent',
                                };
                            @endphp
                            <tr
                                wire:key="inv-{{ $invitation->id }}"
                                @class([
                                    'transition hover:bg-slate-50/50',
                                    'cursor-pointer' => $displayStatus === 'activated' && $invitation->sme_company_id,
                                ])
                                @if ($displayStatus === 'activated' && $invitation->sme_company_id)
                                    x-on:click="Livewire.navigate('{{ route('clients.show', $invitation->sme_company_id) }}')"
                                @endif
                            >
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <div class="font-medium text-ink">{{ $invitation->invitee_company_name ?? '—' }}</div>
                                    @if ($displayStatus === 'activated' && $invitation->accepted_at)
                                        <div class="mt-0.5 text-xs text-emerald-600">
                                            {{ __('Activée le') }} {{ format_date($invitation->accepted_at) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="text-sm text-ink">{{ $invitation->invitee_name ?? '—' }}</div>
                                    @if ($invitation->invitee_phone)
                                        <div class="flex items-center gap-1 text-sm text-slate-500">
                                            @if ($invitation->channel === 'email')
                                                <flux:icon name="envelope" class="size-3.5 text-slate-400" />
                                            @else
                                                <flux:icon name="chat-bubble-left-right" class="size-3.5 text-emerald-500" />
                                            @endif
                                            {{ format_phone($invitation->invitee_phone) }}
                                        </div>
                                    @endif
                                    @if ($invitation->invitee_email)
                                        <div class="truncate text-xs text-slate-400">{{ $invitation->invitee_email }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ format_date($invitation->created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span @class([
                                        'inline-flex items-center whitespace-nowrap gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset',
                                        'bg-blue-50 text-blue-600 ring-blue-600/20' => $displayStatus === 'sent',
                                        'bg-green-50 text-green-700 ring-green-600/20' => $displayStatus === 'registering',
                                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $displayStatus === 'activated',
                                        'bg-rose-50 text-rose-700 ring-rose-600/20' => $displayStatus === 'expired',
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-blue-500' => $displayStatus === 'sent',
                                            'bg-green-500' => $displayStatus === 'registering',
                                            'bg-emerald-500' => $displayStatus === 'activated',
                                            'bg-rose-500' => $displayStatus === 'expired',
                                        ])></span>
                                        {{ match ($displayStatus) {
                                            'activated' => __('Activée'),
                                            'registering' => __('Nouvelle inscription'),
                                            'expired' => __('Expirée'),
                                            default => __('Envoyée'),
                                        } }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-500">
                                    {{ $invitation->last_reminder_at ? format_date($invitation->last_reminder_at) : __('Aucune') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @php $btnBase = 'inline-flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-sm font-semibold transition'; @endphp
                                    @if ($displayStatus === 'sent')
                                        <button
                                            type="button"
                                            wire:click.stop="$dispatch('open-invite-pme-followup', { id: '{{ $invitation->id }}', context: 'reminder' })"
                                            class="{{ $btnBase }} border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                        >
                                            {{ __('Relancer') }}
                                        </button>
                                    @elseif ($displayStatus === 'expired')
                                        <button
                                            type="button"
                                            wire:click.stop="$dispatch('open-invite-pme-followup', { id: '{{ $invitation->id }}', context: 'resend' })"
                                            class="{{ $btnBase }} border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100"
                                        >
                                            {{ __('Renvoyer') }}
                                        </button>
                                    @elseif ($displayStatus === 'activated' && $invitation->sme_company_id)
                                        <a
                                            href="{{ route('clients.show', $invitation->sme_company_id) }}"
                                            wire:navigate
                                            x-on:click.stop
                                            class="{{ $btnBase }} border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                                        >
                                            {{ __('Voir le client') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.table-panel>

    {{-- ─── Modale : Inviter une PME ───────────────────────────────────────── --}}
    <livewire:invite-pme-modal />

</div>

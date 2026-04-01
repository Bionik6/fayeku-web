<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Auth\Services\AuthService;
use Modules\Compta\Partnership\Services\InvitationService;

new #[Title('Invitations')] class extends Component {
    public ?Company $firm = null;

    public string $search = '';

    public string $statusFilter = 'all';

    // ─── Formulaire d'invitation ──────────────────────────────────────
    public string $inviteCompanyName = '';

    public string $inviteContactName = '';

    public string $inviteCountryCode = 'SN';

    public string $invitePhone = '';

    public string $invitePlan = 'basique';

    public function mount(): void
    {
        $this->firm = auth()->user()->accountantFirm();
    }

    // ─── Computed ─────────────────────────────────────────────────────────

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

        if ($this->search !== '') {
            $search = $this->search;
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
            'to_remind' => $query->where('status', 'pending')
                ->where('created_at', '<', now()->subDays(2)),
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

    /** @return array{not_opened: int, incomplete: int, pending_validation: int} */
    #[Computed]
    public function priorityItems(): array
    {
        if (! $this->firm) {
            return ['not_opened' => 0, 'incomplete' => 0, 'pending_validation' => 0];
        }

        $base = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id);

        return [
            'not_opened' => (clone $base)->where('status', 'pending')->whereNull('link_opened_at')->count(),
            'incomplete' => (clone $base)->where('status', 'registering')->count(),
            'pending_validation' => (clone $base)->where('status', 'pending_validation')->count(),
        ];
    }

    // ─── Actions ──────────────────────────────────────────────────────────

    #[On('invitation-sent')]
    public function onInvitationSent(): void
    {
        unset($this->invitations, $this->totalSent, $this->pendingCount, $this->priorityItems);
    }

    public function sendInvitation(): void
    {
        $this->validate([
            'inviteCompanyName' => ['required', 'string', 'max:255'],
            'inviteContactName' => ['required', 'string', 'max:255'],
            'invitePhone' => ['required', 'string', 'max:30'],
        ]);

        $normalizedPhone = AuthService::normalizePhone($this->invitePhone, $this->inviteCountryCode);

        // Vérifier les doublons
        $duplicate = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm?->id)
            ->where('invitee_phone', $normalizedPhone)
            ->whereIn('status', ['pending', 'registering', 'accepted'])
            ->exists();

        if ($duplicate) {
            $this->addError('invitePhone', __('Une invitation a déjà été envoyée à ce numéro.'));

            return;
        }

        $invitation = PartnerInvitation::create([
            'accountant_firm_id' => $this->firm->id,
            'token' => \Illuminate\Support\Str::random(32),
            'invitee_company_name' => $this->inviteCompanyName,
            'invitee_name' => $this->inviteContactName,
            'invitee_phone' => $normalizedPhone,
            'recommended_plan' => $this->invitePlan,
            'status' => 'pending',
            'channel' => 'whatsapp',
            'expires_at' => now()->addDays(30),
        ]);

        $sent = app(InvitationService::class)->sendInvitationMessage($invitation);

        $this->reset('inviteCompanyName', 'inviteContactName', 'invitePhone', 'invitePlan');
        unset($this->invitations, $this->totalSent, $this->pendingCount, $this->priorityItems);

        if ($sent) {
            $this->dispatch('toast', type: 'success', title: __('Invitation envoyée avec succès.'));
        } else {
            $this->dispatch('toast', type: 'warning', title: __('Invitation créée mais l\'envoi WhatsApp a échoué.'));
        }
    }

    public function remindInvitation(string $id): void
    {
        $invitation = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm?->id)
            ->findOrFail($id);

        $invitation->update([
            'reminder_count' => $invitation->reminder_count + 1,
            'last_reminder_at' => now(),
        ]);

        $sent = app(InvitationService::class)->sendReminderMessage($invitation);

        unset($this->invitations);

        if ($sent) {
            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } else {
            $this->dispatch('toast', type: 'warning', title: __('Relance enregistrée mais l\'envoi WhatsApp a échoué.'));
        }
    }

    public function resendInvitation(string $id): void
    {
        $invitation = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm?->id)
            ->findOrFail($id);

        $invitation->update([
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
            'link_opened_at' => null,
            'reminder_count' => 0,
            'last_reminder_at' => null,
        ]);

        $sent = app(InvitationService::class)->sendResendMessage($invitation);

        unset($this->invitations, $this->pendingCount, $this->priorityItems);

        if ($sent) {
            $this->dispatch('toast', type: 'success', title: __('Invitation renvoyée avec succès.'));
        } else {
            $this->dispatch('toast', type: 'warning', title: __('Invitation réinitialisée mais l\'envoi WhatsApp a échoué.'));
        }
    }

    public function setFilter(string $filter): void
    {
        $this->statusFilter = $filter;
        unset($this->invitations);
    }

    public function updatedSearch(): void
    {
        unset($this->invitations);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">


    {{-- ─── En-tête ──────────────────────────────────────────────────────── --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Invitations') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Invitations & activations') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Invitez des PME, suivez leur progression et relancez les contacts qui n\'ont pas encore activé leur compte.') }}</p>
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

    {{-- ─── KPI Cards ──────────────────────────────────────────────────── --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Invitations envoyées --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="paper-airplane" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
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
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ $this->pendingCount }} PME
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('En attente d\'activation') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-amber-600">{{ $this->pendingCount }}</p>
        </article>

        {{-- Activées ce mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-accent" />
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ format_month(now()) }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Activées ce mois') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-accent">{{ $this->activatedThisMonth }}</p>
        </article>

        {{-- Taux de conversion --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-sky-50">
                    <flux:icon name="chart-bar" class="size-5 text-sky-600" />
                </div>
                <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-sm font-medium text-sky-700">
                    %
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Taux de conversion') }}</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-ink">{{ $this->conversionRate }}%</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Invitations devenues clients actifs') }}</p>
        </article>
    </section>


    {{-- ─── Tableau principal ────────────────────────────────────────────── --}}
    <section class="app-shell-panel">
        <div class="px-6 pt-6 pb-2">
            <h3 class="text-lg font-bold text-ink">{{ __('Suivi des invitations') }}</h3>
            <p class="mt-0.5 text-sm text-slate-500">{{ __('Consultez l\'état de chaque invitation envoyée à vos clients PME.') }}</p>
        </div>

        {{-- Search + filters --}}
        <div class="flex flex-col gap-3 px-6 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative max-w-xs flex-1">
                <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Rechercher une entreprise ou un contact') }}"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-ink shadow-sm placeholder:text-slate-500 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ([
                    'all' => __('Tout'),
                    'to_remind' => __('À relancer'),
                    'activated' => __('Activées'),
                    'expired' => __('Expirées'),
                ] as $value => $label)
                    <button
                        type="button"
                        wire:click="setFilter('{{ $value }}')"
                        wire:key="filter-{{ $value }}"
                        @class([
                            'rounded-full px-3 py-1.5 text-sm font-medium transition',
                            'bg-primary text-white' => $statusFilter === $value,
                            'bg-slate-100 text-slate-600 hover:bg-slate-200' => $statusFilter !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

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
                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90"
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
                                    $invitation->status === 'expired' => 'expired',
                                    default => 'sent',
                                };
                            @endphp
                            <tr wire:key="inv-{{ $invitation->id }}" class="transition hover:bg-slate-50/50">
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
                                        <div class="text-sm text-slate-500">{{ format_phone($invitation->invitee_phone) }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                    {{ format_date($invitation->created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-sm font-semibold',
                                        'bg-blue-50 text-blue-600' => $displayStatus === 'sent',
                                        'bg-emerald-50 text-emerald-700' => $displayStatus === 'activated',
                                        'bg-rose-50 text-rose-700' => $displayStatus === 'expired',
                                    ])>
                                        {{ match ($displayStatus) {
                                            'activated' => __('Activée'),
                                            'expired' => __('Expirée'),
                                            default => __('Envoyée'),
                                        } }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-slate-500">
                                                    {{ $invitation->last_reminder_at ? format_date($invitation->last_reminder_at) : __('Aucune') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5">
                                    @php $btnBase = 'inline-flex w-32 items-center justify-center gap-1.5 rounded-xl border px-3 py-1.5 text-sm font-semibold transition'; @endphp
                                    @if ($displayStatus === 'sent')
                                        <button
                                            type="button"
                                            wire:click="remindInvitation('{{ $invitation->id }}')"
                                            class="{{ $btnBase }} border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100"
                                        >
                                            {{ __('Relancer') }}
                                        </button>
                                    @elseif ($displayStatus === 'expired')
                                        <button
                                            type="button"
                                            wire:click="resendInvitation('{{ $invitation->id }}')"
                                            class="{{ $btnBase }} border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100"
                                        >
                                            {{ __('Renvoyer') }}
                                        </button>
                                    @elseif ($displayStatus === 'activated' && $invitation->sme_company_id)
                                        <a
                                            href="{{ route('clients.show', $invitation->sme_company_id) }}"
                                            wire:navigate
                                            class="{{ $btnBase }} border-primary/20 bg-primary/5 text-primary hover:bg-primary/10"
                                        >
                                            {{ __('Voir le client') }}
                                        </a>
                                    @else
                                        <span class="{{ $btnBase }} cursor-default border-slate-200 bg-slate-50 text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ─── Modale : Inviter une PME (composant partagé) ──────────────────── --}}
    <livewire:invite-pme-modal />

</div>

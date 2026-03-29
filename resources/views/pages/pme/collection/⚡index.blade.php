<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderMode;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Collection\Models\ReminderRule;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

new #[Title('Recouvrement')] #[Layout('layouts::pme')] class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'filtre')]
    public string $ageFilter = 'all';

    public ?Company $company = null;

    public string $currentMonth = '';

    /* ---------- KPI ---------- */
    public int $criticalCount = 0;

    public int $criticalAmount = 0;

    public int $lateCount = 0;

    public int $lateAmount = 0;

    public int $pendingCount = 0;

    public int $pendingAmount = 0;

    public int $remindersThisMonth = 0;

    /* ---------- Config modal ---------- */
    public bool $showConfigModal = false;

    public bool $configEnabled = false;

    public string $configMode = 'manual';

    public string $configChannel = 'whatsapp';

    public string $configTone = 'cordial';

    public int $configHourStart = 8;

    public int $configHourEnd = 18;

    public bool $configExcludeWeekends = true;

    public bool $configAttachPdf = true;

    /** @var array<int, int> */
    public array $configRuleDays = [3, 7, 15, 30];

    /* ---------- Slide-over states ---------- */
    public ?string $previewInvoiceId = null;

    public string $previewTone = 'cordial';

    public bool $previewAttachPdf = true;

    public ?string $timelineInvoiceId = null;

    /* ---------- Internal ---------- */

    public function mount(): void
    {
        $this->currentMonth = ucfirst(now()->locale('fr_FR')->translatedFormat('F Y'));
        $this->company = auth()->user()->smeCompany();

        if (! $this->company) {
            return;
        }

        $this->ensureDefaults();
        $this->loadConfigFromCompany();
        $this->refreshKpis();
    }

    /* =====================================================
     *  COMPUTED
     * ===================================================== */

    /**
     * Single base query — all overdue invoices with eager-loaded relations.
     */
    #[Computed]
    public function overdueInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->company) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [
                InvoiceStatus::Overdue,
                InvoiceStatus::Sent,
                InvoiceStatus::Certified,
                InvoiceStatus::CertificationFailed,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with(['client', 'reminders'])
            ->get();
    }

    #[Computed]
    public function invoiceRows(): array
    {
        $rows = $this->overdueInvoices->map(function (Invoice $inv) {
            $daysOverdue = (int) now()->diffInDays($inv->due_at, false);
            $remaining = $inv->total - $inv->amount_paid;
            $lastReminder = $inv->reminders->sortByDesc('created_at')->first();
            $nextReminderDate = $this->calculateNextReminder($inv);

            return [
                'id' => $inv->id,
                'reference' => $inv->reference ?? '—',
                'client_name' => $inv->client?->name ?? '—',
                'client_phone' => $inv->client?->phone,
                'client_email' => $inv->client?->email,
                'total' => $inv->total,
                'remaining' => $remaining,
                'days_overdue' => abs($daysOverdue),
                'reminder_count' => $inv->reminders->count(),
                'last_reminder_at' => $lastReminder?->sent_at?->format('d/m/Y'),
                'next_reminder_at' => $nextReminderDate?->format('d/m/Y'),
                'mode' => $this->company->getReminderMode()->value,
                'status' => $inv->status->value,
            ];
        });

        // Age filter
        $rows = match ($this->ageFilter) {
            'critical' => $rows->filter(fn ($r) => $r['days_overdue'] > 60),
            'late' => $rows->filter(fn ($r) => $r['days_overdue'] >= 30 && $r['days_overdue'] <= 60),
            'pending' => $rows->filter(fn ($r) => $r['days_overdue'] < 30),
            default => $rows,
        };

        // Search filter
        if ($this->search !== '') {
            $q = mb_strtolower($this->search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['reference']), $q)
                || str_contains(mb_strtolower($r['client_name']), $q));
        }

        return $rows->sortByDesc('days_overdue')->values()->all();
    }

    #[Computed]
    public function rules(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->company) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return ReminderRule::query()
            ->where('company_id', $this->company->id)
            ->where('is_active', true)
            ->orderBy('trigger_days')
            ->get();
    }

    #[Computed]
    public function previewInvoice(): ?Invoice
    {
        if (! $this->previewInvoiceId) {
            return null;
        }

        return $this->overdueInvoices->firstWhere('id', $this->previewInvoiceId);
    }

    #[Computed]
    public function timelineInvoice(): ?Invoice
    {
        if (! $this->timelineInvoiceId) {
            return null;
        }

        $invoice = $this->overdueInvoices->firstWhere('id', $this->timelineInvoiceId);

        // Sort reminders by date for the timeline view
        $invoice?->setRelation('reminders', $invoice->reminders->sortBy('created_at')->values());

        return $invoice;
    }

    #[Computed]
    public function counts(): array
    {
        $mapped = $this->mappedOverdueRows();

        return [
            'all' => count($this->invoiceRows),
            'critical' => $mapped->filter(fn ($r) => $r['days_overdue'] > 60)->count(),
            'late' => $mapped->filter(fn ($r) => $r['days_overdue'] >= 30 && $r['days_overdue'] <= 60)->count(),
            'pending' => $mapped->filter(fn ($r) => $r['days_overdue'] < 30)->count(),
        ];
    }

    #[Computed]
    public function totalPendingAmount(): int
    {
        return $this->mappedOverdueRows()->sum('remaining');
    }

    #[Computed]
    public function totalPendingCount(): int
    {
        return $this->overdueInvoices->count();
    }

    /* =====================================================
     *  ACTIONS
     * ===================================================== */

    public function setAgeFilter(string $filter): void
    {
        $this->ageFilter = $filter;
    }

    public function toggleGlobalReminders(): void
    {
        abort_unless($this->company, 403);

        $settings = $this->company->reminder_settings ?? Company::defaultReminderSettings();
        $settings['enabled'] = ! ($settings['enabled'] ?? false);

        $this->company->update(['reminder_settings' => $settings]);
        $this->configEnabled = $settings['enabled'];
    }

    public function openConfigModal(): void
    {
        $this->loadConfigFromCompany();
        $this->showConfigModal = true;
    }

    public function saveConfig(): void
    {
        abort_unless($this->company, 403);

        $this->validate([
            'configMode' => 'required|in:auto,manual',
            'configChannel' => 'required|in:whatsapp,sms,email',
            'configTone' => 'required|in:cordial,ferme,urgent',
            'configHourStart' => 'required|integer|min:0|max:23',
            'configHourEnd' => 'required|integer|min:0|max:23|gte:configHourStart',
            'configRuleDays' => 'required|array|min:1',
            'configRuleDays.*' => 'integer|min:1|max:90',
        ]);

        $this->company->update([
            'reminder_settings' => [
                'enabled' => $this->configEnabled,
                'mode' => $this->configMode,
                'default_channel' => $this->configChannel,
                'default_tone' => $this->configTone,
                'send_hour_start' => $this->configHourStart,
                'send_hour_end' => $this->configHourEnd,
                'exclude_weekends' => $this->configExcludeWeekends,
                'attach_pdf' => $this->configAttachPdf,
            ],
        ]);

        $this->syncReminderRules();
        $this->showConfigModal = false;
        $this->dispatch('toast', type: 'success', title: __('Configuration sauvegardée.'));
    }

    public function openPreview(string $invoiceId): void
    {
        abort_unless($this->company, 403);
        abort_unless($this->overdueInvoices->contains('id', $invoiceId), 404);

        $this->previewInvoiceId = $invoiceId;
        $this->previewTone = $this->company->getReminderSetting('default_tone', 'cordial');
        $this->previewAttachPdf = (bool) $this->company->getReminderSetting('attach_pdf', true);
        $this->timelineInvoiceId = null;
    }

    public function closePreview(): void
    {
        $this->previewInvoiceId = null;
    }

    public function openTimeline(string $invoiceId): void
    {
        abort_unless($this->company, 403);
        abort_unless($this->overdueInvoices->contains('id', $invoiceId), 404);

        $this->timelineInvoiceId = $invoiceId;
        $this->previewInvoiceId = null;
    }

    public function closeTimeline(): void
    {
        $this->timelineInvoiceId = null;
    }

    public function sendReminder(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        try {
            $channel = ReminderChannel::from(
                $this->company->getReminderSetting('default_channel', 'whatsapp')
            );

            app(\Modules\PME\Collection\Services\ReminderService::class)
                ->send($invoice, $this->company, $channel);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }

        $this->refreshKpis();
    }

    public function toggleRuleDay(int $day): void
    {
        if (in_array($day, $this->configRuleDays)) {
            $this->configRuleDays = array_values(array_filter(
                $this->configRuleDays,
                fn ($d) => $d !== $day
            ));
        } else {
            $this->configRuleDays[] = $day;
            sort($this->configRuleDays);
        }
    }

    /* =====================================================
     *  PRIVATE HELPERS
     * ===================================================== */

    private function refreshKpis(): void
    {
        if (! $this->company) {
            return;
        }

        $rows = $this->mappedOverdueRows();

        $this->criticalCount = $rows->filter(fn ($r) => $r['days_overdue'] > 60)->count();
        $this->criticalAmount = $rows->filter(fn ($r) => $r['days_overdue'] > 60)->sum('remaining');
        $this->lateCount = $rows->filter(fn ($r) => $r['days_overdue'] >= 30 && $r['days_overdue'] <= 60)->count();
        $this->lateAmount = $rows->filter(fn ($r) => $r['days_overdue'] >= 30 && $r['days_overdue'] <= 60)->sum('remaining');
        $this->pendingCount = $rows->filter(fn ($r) => $r['days_overdue'] < 30)->count();
        $this->pendingAmount = $rows->filter(fn ($r) => $r['days_overdue'] < 30)->sum('remaining');

        $this->remindersThisMonth = Reminder::query()
            ->whereIn('invoice_id', Invoice::query()
                ->where('company_id', $this->company->id)
                ->select('id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    /**
     * Lightweight mapping of overdue invoices for KPI/filter calculations.
     *
     * @return \Illuminate\Support\Collection<int, array{remaining: int, days_overdue: int}>
     */
    private function mappedOverdueRows(): \Illuminate\Support\Collection
    {
        return $this->overdueInvoices->map(function (Invoice $inv) {
            return [
                'remaining' => $inv->total - $inv->amount_paid,
                'days_overdue' => (int) abs(now()->diffInDays($inv->due_at, false)),
            ];
        });
    }

    private function loadConfigFromCompany(): void
    {
        if (! $this->company) {
            return;
        }

        $settings = $this->company->reminder_settings ?? Company::defaultReminderSettings();

        $this->configEnabled = (bool) ($settings['enabled'] ?? false);
        $this->configMode = $settings['mode'] ?? 'manual';
        $this->configChannel = $settings['default_channel'] ?? 'whatsapp';
        $this->configTone = $settings['default_tone'] ?? 'cordial';
        $this->configHourStart = (int) ($settings['send_hour_start'] ?? 8);
        $this->configHourEnd = (int) ($settings['send_hour_end'] ?? 18);
        $this->configExcludeWeekends = (bool) ($settings['exclude_weekends'] ?? true);
        $this->configAttachPdf = (bool) ($settings['attach_pdf'] ?? true);

        $this->configRuleDays = ReminderRule::query()
            ->where('company_id', $this->company->id)
            ->where('is_active', true)
            ->orderBy('trigger_days')
            ->pluck('trigger_days')
            ->all();

        if (empty($this->configRuleDays)) {
            $this->configRuleDays = [3, 7, 15, 30];
        }
    }

    private function ensureDefaults(): void
    {
        if (! $this->company) {
            return;
        }

        if (! $this->company->reminder_settings) {
            $this->company->update(['reminder_settings' => Company::defaultReminderSettings()]);
        }

        if ($this->company->reminderRules()->count() === 0) {
            $defaults = [
                ['name' => 'Relance J+3', 'trigger_days' => 3],
                ['name' => 'Relance J+7', 'trigger_days' => 7],
                ['name' => 'Relance J+15', 'trigger_days' => 15],
                ['name' => 'Relance J+30', 'trigger_days' => 30],
            ];

            $channel = $this->company->getReminderSetting('default_channel', 'whatsapp');

            foreach ($defaults as $rule) {
                $this->company->reminderRules()->create([
                    ...$rule,
                    'channel' => $channel,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function syncReminderRules(): void
    {
        if (! $this->company) {
            return;
        }

        $existing = $this->company->reminderRules()->pluck('trigger_days')->all();
        $channel = $this->configChannel;

        // Deactivate rules no longer selected
        $this->company->reminderRules()
            ->whereNotIn('trigger_days', $this->configRuleDays)
            ->update(['is_active' => false]);

        // Reactivate or create rules
        foreach ($this->configRuleDays as $day) {
            $this->company->reminderRules()->updateOrCreate(
                ['trigger_days' => $day],
                [
                    'name' => "Relance J+{$day}",
                    'channel' => $channel,
                    'is_active' => true,
                ]
            );
        }
    }

    private function calculateNextReminder(Invoice $inv): ?\Carbon\CarbonInterface
    {
        if (! $this->company || ! $inv->due_at) {
            return null;
        }

        $rules = $this->rules;
        $sentDays = $inv->reminders->map(function ($r) use ($inv) {
            return (int) abs($r->created_at->diffInDays($inv->due_at, false));
        })->all();

        foreach ($rules as $rule) {
            if (! in_array($rule->trigger_days, $sentDays, true)) {
                $nextDate = $inv->due_at->copy()->addDays($rule->trigger_days);
                if ($nextDate->isPast()) {
                    continue;
                }

                return $nextDate;
            }
        }

        return null;
    }

    /**
     * @return array{greeting: string, body: string, closing: string}
     */
    public function buildPreviewMessage(): array
    {
        $inv = $this->previewInvoice;
        if (! $inv) {
            return ['greeting' => '', 'body' => '', 'closing' => ''];
        }

        $clientName = $inv->client?->name ?? '—';
        $reference = $inv->reference ?? '—';
        $remaining = number_format($inv->total - $inv->amount_paid, 0, ',', ' ');
        $dueDate = $inv->due_at?->format('d/m/Y') ?? '—';

        $toneGreetings = [
            'cordial' => "Bonjour {$clientName},",
            'ferme' => "Bonjour {$clientName},",
            'urgent' => "{$clientName},",
        ];

        $toneBody = [
            'cordial' => "Nous souhaitons vous rappeler que la facture {$reference} d'un montant de {$remaining} FCFA, échue le {$dueDate}, reste en attente de règlement.\n\nNous vous serions reconnaissants de bien vouloir procéder au paiement dans les meilleurs délais.",
            'ferme' => "La facture {$reference} d'un montant de {$remaining} FCFA est en retard de paiement depuis le {$dueDate}.\n\nNous vous demandons de procéder au règlement dans les plus brefs délais.",
            'urgent' => "URGENT : La facture {$reference} ({$remaining} FCFA) est impayée depuis le {$dueDate}. Malgré nos précédentes relances, aucun règlement n'a été effectué.\n\nNous vous prions de régulariser cette situation immédiatement.",
        ];

        $toneClosing = [
            'cordial' => 'Cordialement,',
            'ferme' => 'Dans l\'attente de votre règlement,',
            'urgent' => 'En espérant une action immédiate de votre part,',
        ];

        $tone = $this->previewTone;

        return [
            'greeting' => $toneGreetings[$tone] ?? $toneGreetings['cordial'],
            'body' => $toneBody[$tone] ?? $toneBody['cordial'],
            'closing' => $toneClosing[$tone] ?? $toneClosing['cordial'],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- ============================================= --}}
    {{-- A. HEADER                                     --}}
    {{-- ============================================= --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Recouvrement') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Relances & impayés') }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $this->totalPendingCount }} {{ __('factures en attente') }} · {{ number_format($this->totalPendingAmount, 0, ',', ' ') }} FCFA {{ __('à encaisser') }}
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                {{-- Toggle global --}}
                <button
                    wire:click="toggleGlobalReminders"
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition
                        {{ $configEnabled
                            ? 'border-accent/30 bg-accent/10 text-accent'
                            : 'border-slate-200 bg-white text-slate-600' }}"
                >
                    <span class="relative flex size-5 items-center rounded-full transition
                        {{ $configEnabled ? 'bg-accent' : 'bg-slate-300' }}">
                        <span class="absolute size-3 rounded-full bg-white transition-all
                            {{ $configEnabled ? 'left-[0.45rem]' : 'left-[0.15rem]' }}"></span>
                    </span>
                    {{ __('Relances activées') }}
                </button>

                <button
                    wire:click="openConfigModal"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="cog-6-tooth" class="size-4" />
                    {{ __('Configurer les règles') }}
                </button>
            </div>
        </div>
    </section>

    {{-- ============================================= --}}
    {{-- B. KPI CARDS                                  --}}
    {{-- ============================================= --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Critiques > 60j --}}
        <article class="app-shell-stat-card cursor-pointer transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]" wire:click="setAgeFilter('critical')">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    > 60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('Critiques') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-rose-500">{{ $criticalCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ number_format($criticalAmount, 0, ',', ' ') }} FCFA</p>
        </article>

        {{-- En retard 30-60j --}}
        <article class="app-shell-stat-card cursor-pointer transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]" wire:click="setAgeFilter('late')">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    30–60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('En retard') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-amber-500">{{ $lateCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ number_format($lateAmount, 0, ',', ' ') }} FCFA</p>
        </article>

        {{-- En attente < 30j --}}
        <article class="app-shell-stat-card cursor-pointer transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]" wire:click="setAgeFilter('pending')">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="bell" class="size-5 text-primary" />
                </div>
                <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-sm font-semibold text-primary">
                    < 30j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('En attente') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-primary">{{ $pendingCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ number_format($pendingAmount, 0, ',', ' ') }} FCFA</p>
        </article>

        {{-- Relances ce mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-blue-50">
                    <flux:icon name="paper-airplane" class="size-5 text-blue-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-sm font-semibold text-blue-700">
                    {{ $currentMonth }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('Relances ce mois') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-blue-500">{{ $remindersThisMonth }}</p>
        </article>
    </section>

    {{-- ============================================= --}}
    {{-- C + D. MODE DE RELANCE & REGLES (côte à côte) --}}
    {{-- ============================================= --}}
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- C. MODE DE RELANCE --}}
        <div class="app-shell-panel p-5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-600">{{ __('Mode de relance') }}</h3>
            <div class="mt-4 grid grid-cols-1 gap-4">
                @php
                    $isAuto = $configMode === 'auto';
                @endphp
                {{-- Automatique --}}
                <div @class([
                    'rounded-2xl border-2 p-5 transition',
                    'border-primary bg-primary/5 ring-2 ring-primary/20' => $isAuto,
                    'border-slate-200 bg-white' => ! $isAuto,
                ])>
                    <div class="flex items-center gap-3">
                        <div @class([
                            'flex size-10 items-center justify-center rounded-xl',
                            'bg-primary/10' => $isAuto,
                            'bg-slate-100' => ! $isAuto,
                        ])>
                            <flux:icon name="bolt" @class(['size-5', 'text-primary' => $isAuto, 'text-slate-500' => ! $isAuto]) />
                        </div>
                        <div>
                            <p class="font-semibold text-ink">{{ __('Automatique') }}</p>
                            @if ($isAuto)
                                <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">{{ __('Actif') }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">
                        {{ __('Fayeku envoie les relances selon vos règles sans intervention manuelle.') }}
                    </p>
                    @if ($isAuto)
                        <p class="mt-2 text-sm font-medium text-primary">
                            <flux:icon name="arrow-right" class="inline size-3" />
                            {{ __('Prochaine relance automatique selon le calendrier configuré.') }}
                        </p>
                    @endif
                </div>

                {{-- Manuel --}}
                <div @class([
                    'rounded-2xl border-2 p-5 transition',
                    'border-primary bg-primary/5 ring-2 ring-primary/20' => ! $isAuto,
                    'border-slate-200 bg-white' => $isAuto,
                ])>
                    <div class="flex items-center gap-3">
                        <div @class([
                            'flex size-10 items-center justify-center rounded-xl',
                            'bg-primary/10' => ! $isAuto,
                            'bg-slate-100' => $isAuto,
                        ])>
                            <flux:icon name="hand-raised" @class(['size-5', 'text-primary' => ! $isAuto, 'text-slate-500' => $isAuto]) />
                        </div>
                        <div>
                            <p class="font-semibold text-ink">{{ __('Manuel') }}</p>
                            @if (! $isAuto)
                                <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">{{ __('Actif') }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">
                        {{ __('Vous validez chaque message avant envoi. Aucune relance ne part sans votre accord.') }}
                    </p>
                    @if (! $isAuto)
                        <p class="mt-2 text-sm font-medium text-primary">
                            <flux:icon name="arrow-right" class="inline size-3" />
                            {{ __('Utilisez le bouton "Relancer" sur chaque facture.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- D. REGLES DE RELANCE (design cards) --}}
        <div class="app-shell-panel flex flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="font-semibold text-ink">{{ __('Règles de relance') }}</h3>
                    <p class="mt-0.5 text-sm text-slate-600">{{ __('Résumé de la configuration actuellement appliquée sur votre société.') }}</p>
                </div>
                <button wire:click="openConfigModal" class="text-sm font-semibold text-primary hover:underline">
                    {{ __('Modifier') }}
                </button>
            </div>
            <div class="flex-1 p-5">
                <div class="grid grid-cols-2 gap-3">
                    {{-- Fréquence --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Fréquence') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">
                            @foreach ($this->rules as $rule)
                                J+{{ $rule->trigger_days }}{{ ! $loop->last ? ', ' : '' }}
                            @endforeach
                        </p>
                    </div>
                    {{-- Canal --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Canal') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink capitalize">{{ $configChannel === 'whatsapp' ? 'WhatsApp' : ucfirst($configChannel) }}</p>
                    </div>
                    {{-- Ton --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Ton') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink capitalize">{{ $configTone === 'cordial' ? __('Courtois') : ($configTone === 'ferme' ? __('Ferme') : __('Urgent')) }}</p>
                    </div>
                    {{-- Heure d'envoi --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Heure d\'envoi') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ str_pad($configHourStart, 2, '0', STR_PAD_LEFT) }}:00</p>
                    </div>
                    {{-- Jours autorisés --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Jours autorisés') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ $configExcludeWeekends ? __('Lun–ven') : __('Lun–dim') }}</p>
                    </div>
                    {{-- Pièce jointe --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-600">{{ __('Pièce jointe') }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink">{{ $configAttachPdf ? __('Facture PDF') : __('Sans pièce jointe') }}</p>
                    </div>
                </div>
            </div>
        </div>

    </section>

    {{-- ============================================= --}}
    {{-- E. TABLEAU FACTURES A RELANCER                --}}
    {{-- ============================================= --}}
    <section class="app-shell-panel p-4 md:p-5">

        {{-- Filtres par âge --}}
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'all'      => ['label' => 'Toutes',    'dot' => null],
                'critical' => ['label' => 'Critiques', 'dot' => 'bg-rose-500'],
                'late'     => ['label' => 'En retard', 'dot' => 'bg-amber-500'],
                'pending'  => ['label' => 'En attente','dot' => 'bg-teal-500'],
            ] as $key => $tab)
                @php $count = $this->counts[$key] ?? 0; @endphp
                <button
                    wire:click="setAgeFilter('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-primary text-white shadow-sm' => $ageFilter === $key,
                        'bg-white border border-slate-200 text-slate-600 hover:border-primary/30' => $ageFilter !== $key,
                    ])
                >
                    @if ($tab['dot'])
                        <span class="size-1.5 rounded-full {{ $tab['dot'] }}"></span>
                    @endif
                    {{ __($tab['label']) }}
                    <span @class([
                        'inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-sm font-bold min-w-[1.25rem]',
                        'bg-white/20 text-white' => $ageFilter === $key,
                        'bg-slate-100 text-slate-600' => $ageFilter !== $key,
                    ])>{{ $count }}</span>
                </button>
            @endforeach
        </div>

        {{-- Recherche --}}
        <div class="mt-3 flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-48">
                <flux:icon name="magnifying-glass" class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="{{ __('Référence, client…') }}"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-4 text-sm focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/20"
                />
            </div>
        </div>

        {{-- Table --}}
        @if (count($this->invoiceRows) > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-600">{{ __('Facture') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-600">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-slate-600">{{ __('Montant') }}</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-600">{{ __('Retard') }}</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-600">{{ __('Relances') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-600">{{ __('Dernière') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-600">{{ __('Prochaine') }}</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-slate-600">{{ __('Mode') }}</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-slate-600">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->invoiceRows as $row)
                            <tr wire:key="coll-{{ $row['id'] }}" class="transition hover:bg-slate-50/50">
                                <td class="px-4 py-4 font-medium text-ink">{{ $row['reference'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $row['client_name'] }}</td>
                                <td class="px-4 py-4 text-right font-semibold tabular-nums text-ink">
                                    {{ number_format($row['remaining'], 0, ',', ' ') }} <span class="text-sm text-slate-600">F</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                        'bg-rose-50 text-rose-700' => $row['days_overdue'] > 60,
                                        'bg-amber-50 text-amber-700' => $row['days_overdue'] >= 30 && $row['days_overdue'] <= 60,
                                        'bg-slate-100 text-slate-600' => $row['days_overdue'] < 30,
                                    ])>
                                        {{ $row['days_overdue'] }}j
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center tabular-nums text-slate-600">{{ $row['reminder_count'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $row['last_reminder_at'] ?? '—' }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $row['next_reminder_at'] ?? '—' }}</td>
                                <td class="px-4 py-4 text-center">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                        'bg-blue-50 text-blue-700' => $row['mode'] === 'auto',
                                        'bg-slate-100 text-slate-600' => $row['mode'] === 'manual',
                                    ])>
                                        {{ $row['mode'] === 'auto' ? __('Auto') : __('Manuel') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right" x-on:click.stop>
                                    <flux:dropdown position="bottom" align="end">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                                            {{ __('Actions') }}
                                            <flux:icon name="chevron-down" class="size-3.5" />
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item wire:click="openPreview('{{ $row['id'] }}')">
                                                <flux:icon name="chat-bubble-left-right" class="size-4 text-slate-500" />
                                                {{ __('Aperçu WhatsApp') }}
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="openTimeline('{{ $row['id'] }}')">
                                                <flux:icon name="clock" class="size-4 text-slate-500" />
                                                {{ __('Historique') }}
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="sendReminder('{{ $row['id'] }}')" wire:confirm="{{ __('Envoyer une relance pour cette facture ?') }}">
                                                <flux:icon name="paper-airplane" class="size-4 text-primary" />
                                                {{ __('Relancer maintenant') }}
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
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-7 text-emerald-600" />
                </div>
                <p class="mt-4 font-semibold text-ink">{{ __('Aucune facture à relancer') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Tous vos clients sont à jour. Beau travail !') }}</p>
            </div>
        @endif
    </section>

    {{-- ============================================= --}}
    {{-- F. SLIDE-OVER : APERCU WHATSAPP               --}}
    {{-- ============================================= --}}
    @if ($previewInvoiceId && $this->previewInvoice)
        <div
            class="fixed inset-0 z-50 flex justify-end bg-black/40"
            wire:click.self="closePreview"
            @keydown.escape.window="$wire.closePreview()"
        >
            <div class="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h3 class="font-semibold text-ink">{{ __('Aperçu de la relance') }}</h3>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $this->previewInvoice->reference }} · {{ $this->previewInvoice->client?->name }}</p>
                    </div>
                    <button wire:click="closePreview" class="rounded-xl p-2 transition hover:bg-slate-100">
                        <flux:icon name="x-mark" class="size-5 text-slate-500" />
                    </button>
                </div>

                {{-- Message preview --}}
                <div class="flex-1 overflow-y-auto bg-[#e5ddd5] px-6 py-6">
                    <div class="space-y-1">
                        @php $msg = $this->buildPreviewMessage(); @endphp
                        <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-slate-800">{{ $msg['greeting'] }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $msg['body'] }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $msg['closing'] }}</p>
                            <p class="text-sm text-slate-600">{{ $company->name }}</p>

                            @if ($previewAttachPdf)
                                <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                                    <flux:icon name="document" class="size-4 text-rose-500" />
                                    <span class="text-sm font-medium text-slate-600">{{ $this->previewInvoice->reference }}.pdf</span>
                                </div>
                            @endif

                            <p class="mt-2 text-right text-[10px] text-slate-600">{{ now()->format('H:i') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Options --}}
                <div class="border-t border-slate-100 px-6 py-4 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <label class="text-sm font-semibold text-slate-600">{{ __('Ton du message') }}</label>
                            <x-select-native>
                                <select wire:model.live="previewTone" class="col-start-1 row-start-1 mt-1 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 pr-8 text-sm">
                                    <option value="cordial">{{ __('Cordial') }}</option>
                                    <option value="ferme">{{ __('Ferme') }}</option>
                                    <option value="urgent">{{ __('Urgent') }}</option>
                                </select>
                            </x-select-native>
                        </div>
                        <div class="flex flex-col items-start gap-1 pt-3">
                            <label class="text-sm font-semibold text-slate-600">{{ __('Joindre PDF') }}</label>
                            <button
                                wire:click="$toggle('previewAttachPdf')"
                                class="relative flex h-6 w-11 items-center rounded-full transition
                                    {{ $previewAttachPdf ? 'bg-primary' : 'bg-slate-300' }}"
                            >
                                <span class="absolute size-4 rounded-full bg-white shadow transition-all
                                    {{ $previewAttachPdf ? 'left-[1.4rem]' : 'left-1' }}"></span>
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button
                            wire:click="closePreview"
                            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                        >
                            {{ __('Fermer') }}
                        </button>
                        <button
                            wire:click="sendReminder('{{ $previewInvoiceId }}')"
                            wire:confirm="{{ __('Envoyer cette relance ?') }}"
                            class="flex-1 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                        >
                            <flux:icon name="paper-airplane" class="mr-1 inline size-4" />
                            {{ __('Envoyer maintenant') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================= --}}
    {{-- G. SLIDE-OVER : TIMELINE                      --}}
    {{-- ============================================= --}}
    @if ($timelineInvoiceId && $this->timelineInvoice)
        <div
            class="fixed inset-0 z-50 flex justify-end bg-black/40"
            wire:click.self="closeTimeline"
            @keydown.escape.window="$wire.closeTimeline()"
        >
            <div class="flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h3 class="font-semibold text-ink">{{ __('Historique') }}</h3>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $this->timelineInvoice->reference }} · {{ $this->timelineInvoice->client?->name }}</p>
                    </div>
                    <button wire:click="closeTimeline" class="rounded-xl p-2 transition hover:bg-slate-100">
                        <flux:icon name="x-mark" class="size-5 text-slate-500" />
                    </button>
                </div>

                {{-- Timeline --}}
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <div class="relative ml-4 border-l-2 border-slate-200 pl-6 space-y-6">

                        {{-- Échéance --}}
                        <div class="relative">
                            <div class="absolute -left-[1.85rem] top-0.5 flex size-4 items-center justify-center rounded-full bg-slate-200">
                                <span class="size-2 rounded-full bg-slate-500"></span>
                            </div>
                            <p class="text-sm font-semibold text-slate-600">{{ $this->timelineInvoice->due_at?->format('d/m/Y') }}</p>
                            <p class="mt-0.5 text-sm font-medium text-ink">{{ __('Date d\'échéance') }}</p>
                            <p class="text-sm text-slate-600">
                                {{ __('Montant dû') }} : {{ number_format($this->timelineInvoice->total - $this->timelineInvoice->amount_paid, 0, ',', ' ') }} FCFA
                            </p>
                        </div>

                        {{-- Relances --}}
                        @foreach ($this->timelineInvoice->reminders as $reminder)
                            <div class="relative" wire:key="tl-{{ $reminder->id }}">
                                <div class="absolute -left-[1.85rem] top-0.5 flex size-4 items-center justify-center rounded-full
                                    {{ $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Delivered ? 'bg-accent/20' : ($reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Failed ? 'bg-rose-100' : 'bg-blue-100') }}">
                                    <span class="size-2 rounded-full
                                        {{ $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Delivered ? 'bg-accent' : ($reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Failed ? 'bg-rose-500' : 'bg-blue-500') }}"></span>
                                </div>
                                <p class="text-sm font-semibold text-slate-600">{{ $reminder->created_at->format('d/m/Y H:i') }}</p>
                                <p class="mt-0.5 text-sm font-medium text-ink">
                                    {{ __('Relance') }} {{ $reminder->channel?->value ?? '' }}
                                </p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                        'bg-accent/10 text-accent' => $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Delivered,
                                        'bg-blue-50 text-blue-700' => $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Sent,
                                        'bg-amber-50 text-amber-700' => $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Pending,
                                        'bg-rose-50 text-rose-700' => $reminder->status === \Modules\PME\Collection\Enums\ReminderStatus::Failed,
                                    ])>
                                        {{ $reminder->status?->value ?? '—' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach

                        {{-- Paiement si payé --}}
                        @if ($this->timelineInvoice->paid_at)
                            <div class="relative">
                                <div class="absolute -left-[1.85rem] top-0.5 flex size-4 items-center justify-center rounded-full bg-accent/20">
                                    <span class="size-2 rounded-full bg-accent"></span>
                                </div>
                                <p class="text-sm font-semibold text-slate-600">{{ $this->timelineInvoice->paid_at->format('d/m/Y') }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-accent">{{ __('Paiement reçu') }}</p>
                            </div>
                        @endif

                        {{-- Aucun événement --}}
                        @if ($this->timelineInvoice->reminders->isEmpty() && ! $this->timelineInvoice->paid_at)
                            <div class="relative">
                                <div class="absolute -left-[1.85rem] top-0.5 flex size-4 items-center justify-center rounded-full bg-slate-100">
                                    <span class="size-2 rounded-full bg-slate-400"></span>
                                </div>
                                <p class="text-sm text-slate-600">{{ __('Aucune relance envoyée pour le moment.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================= --}}
    {{-- H. MODAL : CONFIGURER LES REGLES              --}}
    {{-- ============================================= --}}
    @if ($showConfigModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="$set('showConfigModal', false)"
            @keydown.escape.window="$wire.set('showConfigModal', false)"
        >
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Configurer les relances') }}</h2>
                    <button wire:click="$set('showConfigModal', false)" class="rounded-xl p-2 transition hover:bg-slate-100">
                        <flux:icon name="x-mark" class="size-5 text-slate-500" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="max-h-[70vh] overflow-y-auto px-8 py-6 space-y-6">

                    {{-- Toggle activer/désactiver --}}
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="font-semibold text-ink">{{ __('Activer les relances') }}</p>
                            <p class="text-sm text-slate-600">{{ __('Activer ou désactiver globalement les relances automatiques.') }}</p>
                        </div>
                        <button
                            wire:click="$toggle('configEnabled')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $configEnabled ? 'bg-accent' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $configEnabled ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                    {{-- Mode auto/manuel --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Mode de relance') }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <button
                                wire:click="$set('configMode', 'auto')"
                                @class([
                                    'rounded-xl border-2 p-4 text-left transition',
                                    'border-primary bg-primary/5' => $configMode === 'auto',
                                    'border-slate-200' => $configMode !== 'auto',
                                ])
                            >
                                <flux:icon name="bolt" @class(['size-5', 'text-primary' => $configMode === 'auto', 'text-slate-500' => $configMode !== 'auto']) />
                                <p class="mt-2 text-sm font-semibold text-ink">{{ __('Automatique') }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ __('Envoi selon le calendrier') }}</p>
                            </button>
                            <button
                                wire:click="$set('configMode', 'manual')"
                                @class([
                                    'rounded-xl border-2 p-4 text-left transition',
                                    'border-primary bg-primary/5' => $configMode === 'manual',
                                    'border-slate-200' => $configMode !== 'manual',
                                ])
                            >
                                <flux:icon name="hand-raised" @class(['size-5', 'text-primary' => $configMode === 'manual', 'text-slate-500' => $configMode !== 'manual']) />
                                <p class="mt-2 text-sm font-semibold text-ink">{{ __('Manuel') }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ __('Validation avant envoi') }}</p>
                            </button>
                        </div>
                    </div>

                    {{-- Jours de relance --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Jours de relance après échéance') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ([3, 5, 7, 10, 15, 20, 30, 45, 60] as $day)
                                <button
                                    wire:click="toggleRuleDay({{ $day }})"
                                    @class([
                                        'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                                        'bg-primary text-white shadow-sm' => in_array($day, $configRuleDays),
                                        'bg-slate-100 text-slate-600 hover:bg-slate-200' => ! in_array($day, $configRuleDays),
                                    ])
                                >
                                    J+{{ $day }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Canal par défaut --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Canal par défaut') }}</p>
                        <div class="mt-2 flex gap-3">
                            @foreach ([
                                'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'chat-bubble-left-right'],
                                'sms' => ['label' => 'SMS', 'icon' => 'device-phone-mobile'],
                                'email' => ['label' => 'Email', 'icon' => 'envelope'],
                            ] as $channelKey => $channelData)
                                <button
                                    wire:click="$set('configChannel', '{{ $channelKey }}')"
                                    @class([
                                        'inline-flex items-center gap-2 rounded-xl border-2 px-4 py-2.5 text-sm font-semibold transition',
                                        'border-primary bg-primary/5 text-primary' => $configChannel === $channelKey,
                                        'border-slate-200 text-slate-600' => $configChannel !== $channelKey,
                                    ])
                                >
                                    <flux:icon name="{{ $channelData['icon'] }}" class="size-4" />
                                    {{ $channelData['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Ton par défaut --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Ton par défaut') }}</p>
                        <x-select-native>
                            <select wire:model="configTone" class="col-start-1 row-start-1 mt-2 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 pr-8 text-sm">
                                <option value="cordial">{{ __('Cordial — Poli et professionnel') }}</option>
                                <option value="ferme">{{ __('Ferme — Direct et clair') }}</option>
                                <option value="urgent">{{ __('Urgent — Insistant et prioritaire') }}</option>
                            </select>
                        </x-select-native>
                    </div>

                    {{-- Horaires d'envoi --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Horaires d\'envoi') }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-600">{{ __('De') }}</label>
                                <input wire:model="configHourStart" type="number" min="0" max="23"
                                    class="w-20 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-center text-sm" />
                                <span class="text-sm text-slate-600">h</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-600">{{ __('à') }}</label>
                                <input wire:model="configHourEnd" type="number" min="0" max="23"
                                    class="w-20 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-center text-sm" />
                                <span class="text-sm text-slate-600">h</span>
                            </div>
                        </div>
                    </div>

                    {{-- Exclusion week-end --}}
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Exclure les week-ends') }}</p>
                            <p class="text-sm text-slate-600">{{ __('Ne pas envoyer de relances le samedi et dimanche.') }}</p>
                        </div>
                        <button
                            wire:click="$toggle('configExcludeWeekends')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $configExcludeWeekends ? 'bg-primary' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $configExcludeWeekends ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                    {{-- Facture PDF jointe --}}
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Joindre la facture PDF') }}</p>
                            <p class="text-sm text-slate-600">{{ __('Attacher automatiquement le PDF de la facture à chaque relance.') }}</p>
                        </div>
                        <button
                            wire:click="$toggle('configAttachPdf')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $configAttachPdf ? 'bg-primary' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $configAttachPdf ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 border-t border-slate-100 px-8 py-5">
                    <button
                        wire:click="$set('showConfigModal', false)"
                        class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                    >
                        {{ __('Annuler') }}
                    </button>
                    <button
                        wire:click="saveConfig"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                    >
                        {{ __('Enregistrer') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>

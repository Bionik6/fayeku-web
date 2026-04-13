<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Models\PME\Reminder;
use App\Models\PME\ReminderRule;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

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

    /* ---------- Config — état sauvegardé (UI principale) ---------- */
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

    /* ---------- Config — brouillon modal (non persisté avant Enregistrer) ---------- */
    public bool $draftEnabled = false;

    public string $draftMode = 'manual';

    public string $draftChannel = 'whatsapp';

    public string $draftTone = 'cordial';

    public int $draftHourStart = 8;

    public int $draftHourEnd = 18;

    public bool $draftExcludeWeekends = true;

    public bool $draftAttachPdf = true;

    /** @var array<int, int> */
    public array $draftRuleDays = [3, 7, 15, 30];

    /* ---------- Slide-over states ---------- */
    public ?string $selectedInvoiceId = null;

    public ?string $previewInvoiceId = null;

    public ?string $confirmSendReminderId = null;

    public ?string $confirmDeleteId = null;

    public ?string $confirmMarkPaidId = null;

    public string $previewTone = 'cordial';

    public bool $previewAttachPdf = true;

    public string $previewChannel = 'whatsapp';

    public ?string $timelineInvoiceId = null;

    /* ---------- Internal ---------- */

    public function mount(): void
    {
        $this->currentMonth = format_month(now());
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
                'client_id' => $inv->client_id,
                'client_name' => $inv->client?->name ?? '—',
                'client_phone' => $inv->client?->phone,
                'client_email' => $inv->client?->email,
                'total' => $inv->total,
                'remaining' => $remaining,
                'days_overdue' => abs($daysOverdue),
                'reminder_count' => $inv->reminders->count(),
                'last_reminder_at' => format_date($lastReminder?->sent_at),
                'next_reminder_at' => format_date($nextReminderDate),
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
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId || ! $this->company) {
            return null;
        }

        return Invoice::query()
            ->with(['client', 'lines'])
            ->where('company_id', $this->company->id)
            ->whereKey($this->selectedInvoiceId)
            ->first();
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

        $this->draftEnabled        = $this->configEnabled;
        $this->draftMode           = $this->configMode;
        $this->draftChannel        = $this->configChannel;
        $this->draftTone           = $this->configTone;
        $this->draftHourStart      = $this->configHourStart;
        $this->draftHourEnd        = $this->configHourEnd;
        $this->draftExcludeWeekends = $this->configExcludeWeekends;
        $this->draftAttachPdf      = $this->configAttachPdf;
        $this->draftRuleDays       = $this->configRuleDays;

        $this->showConfigModal = true;
    }

    public function saveConfig(): void
    {
        abort_unless($this->company, 403);

        if ($this->draftEnabled) {
            $this->validate([
                'draftMode'       => 'required|in:auto,manual',
                'draftChannel'    => 'required|in:whatsapp,sms,email',
                'draftTone'       => 'required|in:cordial,ferme,urgent',
                'draftHourStart'  => 'required|integer|min:0|max:23',
                'draftHourEnd'    => 'required|integer|min:0|max:23|gte:draftHourStart',
                'draftRuleDays'   => 'required|array|min:1',
                'draftRuleDays.*' => 'integer|min:1|max:90',
            ]);
        }

        $this->company->update([
            'reminder_settings' => [
                'enabled'          => $this->draftEnabled,
                'mode'             => $this->draftMode,
                'default_channel'  => $this->draftChannel,
                'default_tone'     => $this->draftTone,
                'send_hour_start'  => $this->draftHourStart,
                'send_hour_end'    => $this->draftHourEnd,
                'exclude_weekends' => $this->draftExcludeWeekends,
                'attach_pdf'       => $this->draftAttachPdf,
            ],
        ]);

        $this->configEnabled        = $this->draftEnabled;
        $this->configMode           = $this->draftMode;
        $this->configChannel        = $this->draftChannel;
        $this->configTone           = $this->draftTone;
        $this->configHourStart      = $this->draftHourStart;
        $this->configHourEnd        = $this->draftHourEnd;
        $this->configExcludeWeekends = $this->draftExcludeWeekends;
        $this->configAttachPdf      = $this->draftAttachPdf;
        $this->configRuleDays       = $this->draftRuleDays;

        if ($this->draftEnabled) {
            $this->syncReminderRules();
        }

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
        $this->previewChannel = $this->company->getReminderSetting('default_channel', 'whatsapp');
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

    public function viewInvoice(string $invoiceId): void
    {
        abort_unless($this->company, 403);

        Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $this->selectedInvoiceId = $invoiceId;
        unset($this->selectedInvoice);
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
        unset($this->selectedInvoice);
    }

    public function confirmMarkPaid(string $id): void
    {
        $this->confirmMarkPaidId = $id;
    }

    public function cancelMarkPaid(): void
    {
        $this->confirmMarkPaidId = null;
    }

    public function markAsPaid(string $invoiceId): void
    {
        $this->confirmMarkPaidId = null;
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $invoice->total,
            'paid_at' => now(),
        ]);

        if ($this->selectedInvoiceId === $invoiceId) {
            $this->selectedInvoiceId = null;
        }

        unset($this->overdueInvoices, $this->invoiceRows, $this->counts, $this->totalPendingAmount, $this->totalPendingCount, $this->selectedInvoice);
        $this->refreshKpis();

        $this->dispatch('toast', type: 'success', title: __('La facture a été marquée comme payée.'));
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteInvoice(string $invoiceId): void
    {
        $this->confirmDeleteId = null;
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        $invoice->delete();

        if ($this->selectedInvoiceId === $invoiceId) {
            $this->selectedInvoiceId = null;
        }

        unset($this->overdueInvoices, $this->invoiceRows, $this->counts, $this->totalPendingAmount, $this->totalPendingCount, $this->selectedInvoice);
        $this->refreshKpis();

        $this->dispatch('toast', type: 'success', title: __('La facture a été supprimée.'));
    }

    public function confirmSendReminder(string $id): void
    {
        $this->confirmSendReminderId = $id;
    }

    public function cancelSendReminder(): void
    {
        $this->confirmSendReminderId = null;
    }

    public function sendReminder(string $invoiceId): void
    {
        $this->confirmSendReminderId = null;
        abort_unless($this->company, 403);

        $invoice = Invoice::query()
            ->where('company_id', $this->company->id)
            ->findOrFail($invoiceId);

        try {
            $channel = ReminderChannel::from($this->previewChannel);

            // Ensure previewInvoiceId is set so buildPreviewMessage() can resolve the invoice.
            $previousPreviewId = $this->previewInvoiceId;
            if ($this->previewInvoiceId !== $invoiceId) {
                $this->previewInvoiceId = $invoiceId;
                $this->previewTone = $this->company->getReminderSetting('default_tone', 'cordial');
                unset($this->previewInvoice);
            }

            $msg = $this->buildPreviewMessage();
            $messageBody = implode("\n\n", array_filter([
                $msg['greeting'],
                $msg['body'],
                $msg['closing'],
                $this->company->name,
            ])) ?: null;

            $this->previewInvoiceId = $previousPreviewId;

            app(\App\Services\PME\ReminderService::class)
                ->send($invoice, $this->company, $channel, $messageBody, mode: \App\Enums\PME\ReminderMode::Manual);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }

        $this->refreshKpis();
    }

    public function toggleRuleDay(int $day): void
    {
        if (in_array($day, $this->draftRuleDays)) {
            $this->draftRuleDays = array_values(array_filter(
                $this->draftRuleDays,
                fn ($d) => $d !== $day
            ));
        } else {
            $this->draftRuleDays[] = $day;
            sort($this->draftRuleDays);
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

        $validModes    = ['auto', 'manual'];
        $validChannels = ['whatsapp', 'sms', 'email'];
        $validTones    = ['cordial', 'ferme', 'urgent'];

        $this->configEnabled = (bool) ($settings['enabled'] ?? false);
        $this->configMode = in_array($settings['mode'] ?? '', $validModes) ? $settings['mode'] : 'manual';
        $this->configChannel = in_array($settings['default_channel'] ?? '', $validChannels) ? $settings['default_channel'] : 'whatsapp';
        $this->configTone = in_array($settings['default_tone'] ?? '', $validTones) ? $settings['default_tone'] : 'cordial';
        $this->configHourStart = min(23, max(0, (int) ($settings['send_hour_start'] ?? 8)));
        $this->configHourEnd = min(23, max(0, (int) ($settings['send_hour_end'] ?? 18)));
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
        $remaining = format_money($inv->total - $inv->amount_paid);
        $dueDate = format_date($inv->due_at);

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

    /**
     * Preview message for the config modal, using placeholder data.
     *
     * @return array{greeting: string, body: string, closing: string}
     */
    public function buildConfigPreviewMessage(): array
    {
        $clientName = 'Jean Dupont';
        $reference = 'FAC-2026-001';
        $amount = '500 000';
        $dueDate = format_date(now()->subDays(15));

        $toneGreetings = [
            'cordial' => "Bonjour {$clientName},",
            'ferme' => "Bonjour {$clientName},",
            'urgent' => "{$clientName},",
        ];

        $toneBody = [
            'cordial' => "Nous souhaitons vous rappeler que la facture {$reference} d'un montant de {$amount} FCFA, échue le {$dueDate}, reste en attente de règlement.\n\nNous vous serions reconnaissants de bien vouloir procéder au paiement dans les meilleurs délais.",
            'ferme' => "La facture {$reference} d'un montant de {$amount} FCFA est en retard de paiement depuis le {$dueDate}.\n\nNous vous demandons de procéder au règlement dans les plus brefs délais.",
            'urgent' => "URGENT : La facture {$reference} ({$amount} FCFA) est impayée depuis le {$dueDate}. Malgré nos précédentes relances, aucun règlement n'a été effectué.\n\nNous vous prions de régulariser cette situation immédiatement.",
        ];

        $toneClosing = [
            'cordial' => 'Cordialement,',
            'ferme' => 'Dans l\'attente de votre règlement,',
            'urgent' => 'En espérant une action immédiate de votre part,',
        ];

        $tone = $this->draftTone;

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
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Recouvrement') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Relances & impayés') }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $this->totalPendingCount }} {{ __('factures en attente') }} · {{ format_money($this->totalPendingAmount) }} {{ __('à encaisser') }}
                </p>
            </div>
            @if ($configEnabled)
            <button
                wire:click="openConfigModal"
                class="inline-flex shrink-0 items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
            >
                <flux:icon name="cog-6-tooth" class="size-4" />
                {{ __('Configurer les règles') }}
            </button>
            @endif
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
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    > 60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('Critiques') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-rose-500">{{ $criticalCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ format_money($criticalAmount) }}</p>
        </article>

        {{-- En retard 30-60j --}}
        <article class="app-shell-stat-card cursor-pointer transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]" wire:click="setAgeFilter('late')">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    30–60j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('En retard') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-amber-500">{{ $lateCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ format_money($lateAmount) }}</p>
        </article>

        {{-- En attente < 30j --}}
        <article class="app-shell-stat-card cursor-pointer transition hover:shadow-[0_20px_45px_rgba(15,23,42,0.1)]" wire:click="setAgeFilter('pending')">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                    <flux:icon name="bell" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-teal-50 px-2.5 py-1 text-sm font-semibold text-primary">
                    < 30j
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-600">{{ __('En attente') }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-primary">{{ $pendingCount }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ format_money($pendingAmount) }}</p>
        </article>

        {{-- Relances ce mois --}}
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-blue-50">
                    <flux:icon name="paper-airplane" class="size-5 text-blue-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-blue-50 px-2.5 py-1 text-sm font-semibold text-blue-700">
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

    @if (! $configEnabled)
        {{-- Placeholder relances désactivées --}}
        <section class="app-shell-panel flex flex-col items-center justify-center gap-6 px-8 py-14 text-center">
            <div class="flex size-16 items-center justify-center rounded-2xl bg-slate-100">
                <flux:icon name="bell-slash" class="size-8 text-slate-400" />
            </div>
            <div>
                <p class="text-lg font-semibold text-ink">{{ __('Relances désactivées') }}</p>
                <p class="mt-1 max-w-md text-sm text-slate-500">
                    {{ __('Activez les relances pour rappeler automatiquement vos clients lorsque leurs factures sont en retard.') }}
                </p>
            </div>
            <button
                wire:click="openConfigModal"
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
            >
                <flux:icon name="bell" class="size-4" />
                {{ __('Activer les relances') }}
            </button>
        </section>
    @else
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
                                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">{{ __('Actif') }}</span>
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
                                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">{{ __('Actif') }}</span>
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
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-ink">{{ __('Règles de relance') }}</h3>
                <p class="mt-0.5 text-sm text-slate-600">{{ __('Résumé de la configuration actuellement appliquée sur votre société.') }}</p>
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
    @endif {{-- $configEnabled --}}

    {{-- ============================================= --}}
    {{-- E. TABLEAU FACTURES A RELANCER                --}}
    {{-- ============================================= --}}
    <x-ui.table-panel
        :title="__('Factures à relancer')"
        :description="__('Factures en retard classées par ancienneté. Cliquez sur une ligne pour envoyer une relance.')"
        :filterLabel="__('Filtrer par ancienneté')"
    >
        <x-slot:filters>
            @foreach ([
                'all'      => ['label' => 'Toutes',     'dot' => null,           'activeClass' => 'bg-primary text-white',   'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'critical' => ['label' => 'Critiques',  'dot' => 'bg-rose-500',  'activeClass' => 'bg-rose-500 text-white',  'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'late'     => ['label' => 'En retard',  'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white', 'badgeInactive' => 'bg-amber-100 text-amber-700'],
                'pending'  => ['label' => 'En attente', 'dot' => 'bg-blue-400',  'activeClass' => 'bg-blue-500 text-white',  'badgeInactive' => 'bg-blue-100 text-blue-700'],
            ] as $key => $tab)
                <x-ui.filter-chip
                    wire:click="setAgeFilter('{{ $key }}')"
                    :label="__($tab['label'])"
                    :dot="$tab['dot']"
                    :active="$ageFilter === $key"
                    :activeClass="$tab['activeClass']"
                    :badgeInactive="$tab['badgeInactive']"
                    :count="$this->counts[$key] ?? 0"
                />
            @endforeach
        </x-slot:filters>

        <x-slot:search>
            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-48 flex-1">
                    <flux:icon name="magnifying-glass" class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-500" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="{{ __('Référence, client…') }}"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3 pl-10 pr-4 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                    />
                </div>
            </div>
        </x-slot:search>

        {{-- Table --}}
        @if (count($this->invoiceRows) > 0)
            <div class="overflow-x-auto border-t border-slate-100">
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
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->invoiceRows as $row)
                            <tr wire:key="coll-{{ $row['id'] }}" class="transition hover:bg-slate-50/50">
                                <td class="px-4 py-4 font-medium text-ink">{{ $row['reference'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $row['client_name'] }}</td>
                                <td class="px-4 py-4 text-right font-semibold tabular-nums text-ink whitespace-nowrap">
                                    {{ format_money($row['remaining'], compact: true) }}
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span @class([
                                        'inline-flex whitespace-nowrap items-center rounded-full px-2.5 py-0.5 text-sm font-semibold',
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
                                <td class="px-4 py-4" x-on:click.stop>
                                    <x-ui.dropdown>
                                        <x-ui.dropdown-item wire:click="viewInvoice('{{ $row['id'] }}')">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Voir la facture') }}
                                        </x-ui.dropdown-item>
                                        @if ($row['client_id'])
                                            <x-ui.dropdown-item :href="route('pme.clients.show', $row['client_id'])" wire:navigate>
                                                <x-slot:icon>
                                                    <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                    </svg>
                                                </x-slot:icon>
                                                {{ __('Voir le client') }}
                                            </x-ui.dropdown-item>
                                        @endif
                                        <x-ui.dropdown-item :href="route('pme.invoices.pdf', $row['id'])" target="_blank">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Afficher en PDF') }}
                                        </x-ui.dropdown-item>
                                        <x-ui.dropdown-item wire:click="openTimeline('{{ $row['id'] }}')" :count="$row['reminder_count']">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Voir les relances') }}
                                        </x-ui.dropdown-item>
                                        <x-ui.dropdown-item wire:click="openPreview('{{ $row['id'] }}')">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Relancer le client') }}
                                        </x-ui.dropdown-item>
                                        <x-ui.dropdown-item wire:click="confirmMarkPaid('{{ $row['id'] }}')">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Marquer comme payée') }}
                                        </x-ui.dropdown-item>
                                        <x-ui.dropdown-separator />
                                        <x-ui.dropdown-item wire:click="confirmDelete('{{ $row['id'] }}')" variant="danger">
                                            <x-slot:icon>
                                                <svg class="size-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </x-slot:icon>
                                            {{ __('Supprimer la facture') }}
                                        </x-ui.dropdown-item>
                                    </x-ui.dropdown>
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
    </x-ui.table-panel>

    {{-- ============================================= --}}
    {{-- F. SLIDE-OVER : APERCU WHATSAPP               --}}
    {{-- ============================================= --}}
    @if ($previewInvoiceId && $this->previewInvoice)
        <x-collection.reminder-preview-slideover
            :invoice="$this->previewInvoice"
            :message="$this->buildPreviewMessage()"
            :company="$company"
            :preview-invoice-id="$previewInvoiceId"
            :preview-attach-pdf="$previewAttachPdf"
            :preview-channel="$previewChannel"
        />
    @endif

    {{-- ============================================= --}}
    {{-- G. SLIDE-OVER : TIMELINE                      --}}
    {{-- ============================================= --}}
    @if ($timelineInvoiceId && $this->timelineInvoice)
        <x-ui.drawer
            :title="__('Historique des relances')"
            :subtitle="$this->timelineInvoice->reference . ' · ' . ($this->timelineInvoice->client?->name ?? '')"
            close-action="closeTimeline"
        >
            <x-collection.reminder-feed :invoice="$this->timelineInvoice" />
        </x-ui.drawer>
    @endif

    {{-- ============================================= --}}
    {{-- H. MODAL : DÉTAIL FACTURE                     --}}
    {{-- ============================================= --}}
    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif

    {{-- ============================================= --}}
    {{-- I. MODAUX DE CONFIRMATION                     --}}
    {{-- ============================================= --}}
    <x-ui.confirm-modal
        :confirm-id="$confirmMarkPaidId"
        :title="__('Marquer comme payée')"
        :description="__('Cette facture sera marquée comme entièrement payée. Cette action est irréversible.')"
        confirm-action="markAsPaid"
        cancel-action="cancelMarkPaid"
        :confirm-label="__('Confirmer le paiement')"
        variant="primary"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmDeleteId"
        :title="__('Supprimer la facture')"
        :description="__('Cette action est irréversible. La facture sera définitivement supprimée.')"
        confirm-action="deleteInvoice"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

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
                            wire:click="$toggle('draftEnabled')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $draftEnabled ? 'bg-accent' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $draftEnabled ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                    @if ($draftEnabled)
                    {{-- Mode auto/manuel --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Mode de relance') }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <button
                                wire:click="$set('draftMode', 'auto')"
                                @class([
                                    'rounded-xl border-2 p-4 text-left transition',
                                    'border-primary bg-primary/5' => $draftMode === 'auto',
                                    'border-slate-200' => $draftMode !== 'auto',
                                ])
                            >
                                <flux:icon name="bolt" @class(['size-5', 'text-primary' => $draftMode === 'auto', 'text-slate-500' => $draftMode !== 'auto']) />
                                <p class="mt-2 text-sm font-semibold text-ink">{{ __('Automatique') }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ __('Envoi selon le calendrier') }}</p>
                            </button>
                            <button
                                wire:click="$set('draftMode', 'manual')"
                                @class([
                                    'rounded-xl border-2 p-4 text-left transition',
                                    'border-primary bg-primary/5' => $draftMode === 'manual',
                                    'border-slate-200' => $draftMode !== 'manual',
                                ])
                            >
                                <flux:icon name="hand-raised" @class(['size-5', 'text-primary' => $draftMode === 'manual', 'text-slate-500' => $draftMode !== 'manual']) />
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
                                        'bg-primary text-white shadow-sm' => in_array($day, $draftRuleDays),
                                        'bg-slate-100 text-slate-600 hover:bg-slate-200' => ! in_array($day, $draftRuleDays),
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
                                    wire:click="$set('draftChannel', '{{ $channelKey }}')"
                                    @class([
                                        'inline-flex items-center gap-2 rounded-xl border-2 px-4 py-2.5 text-sm font-semibold transition',
                                        'border-primary bg-primary/5 text-primary' => $draftChannel === $channelKey,
                                        'border-slate-200 text-slate-600' => $draftChannel !== $channelKey,
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
                            <select wire:model.live="draftTone" class="col-start-1 row-start-1 mt-2 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 pr-8 text-sm">
                                <option value="cordial">{{ __('Cordial — Poli et professionnel') }}</option>
                                <option value="ferme">{{ __('Ferme — Direct et clair') }}</option>
                                <option value="urgent">{{ __('Urgent — Insistant et prioritaire') }}</option>
                            </select>
                        </x-select-native>
                    </div>

                    {{-- Aperçu du message --}}
                    @php $preview = $this->buildConfigPreviewMessage(); @endphp
                    <div class="rounded-2xl bg-mist p-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">{{ __('Aperçu du message') }}</p>
                        <div class="max-w-sm rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-slate-800">{{ $preview['greeting'] }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $preview['body'] }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $preview['closing'] }}</p>
                            <p class="text-sm text-slate-600">{{ $company->name }}</p>
                            @if ($draftAttachPdf)
                                <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                                    <flux:icon name="document" class="size-4 text-rose-500" />
                                    <span class="text-sm font-medium text-slate-600">{{ __('Facture') }}.pdf</span>
                                </div>
                            @endif
                            <p class="mt-2 text-right text-[10px] text-slate-400">{{ now()->format('H:i') }}</p>
                        </div>
                    </div>

                    {{-- Horaires d'envoi --}}
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Horaires d\'envoi') }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-600">{{ __('De') }}</label>
                                <input wire:model="draftHourStart" type="number" min="0" max="23"
                                    class="w-20 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-center text-sm" />
                                <span class="text-sm text-slate-600">h</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-600">{{ __('à') }}</label>
                                <input wire:model="draftHourEnd" type="number" min="0" max="23"
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
                            wire:click="$toggle('draftExcludeWeekends')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $draftExcludeWeekends ? 'bg-primary' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $draftExcludeWeekends ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                    {{-- Facture PDF jointe --}}
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Joindre la facture PDF') }}</p>
                            <p class="text-sm text-slate-600">{{ __('Attacher automatiquement le PDF de la facture à chaque relance.') }}</p>
                        </div>
                        <button
                            wire:click="$toggle('draftAttachPdf')"
                            class="relative flex h-7 w-12 items-center rounded-full transition
                                {{ $draftAttachPdf ? 'bg-primary' : 'bg-slate-300' }}"
                        >
                            <span class="absolute size-5 rounded-full bg-white shadow transition-all
                                {{ $draftAttachPdf ? 'left-[1.4rem]' : 'left-1' }}"></span>
                        </button>
                    </div>

                    @endif {{-- $draftEnabled --}}

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

    <x-ui.confirm-modal
        :confirm-id="$confirmSendReminderId"
        :title="__('Envoyer une relance')"
        :description="__('Une relance sera envoyée immédiatement au client pour cette facture.')"
        confirm-action="sendReminder"
        cancel-action="cancelSendReminder"
        :confirm-label="__('Envoyer')"
        variant="primary"
    />

</div>

<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\PaymentService;
use App\Services\PME\ReminderService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Facture')] #[Layout('layouts::pme')] class extends Component {
    public Invoice $invoice;

    public ?Company $company = null;

    public bool $showPaymentModal = false;

    public ?string $editingPaymentId = null;

    public string $paymentAmount = '';

    public string $paymentPaidAt = '';

    public string $paymentMethod = 'transfer';

    public string $paymentReference = '';

    public string $paymentNotes = '';

    public ?string $confirmMarkPaid = null;

    public ?string $confirmDeletePaymentId = null;

    public ?string $confirmDeleteInvoice = null;

    public ?string $previewInvoiceId = null;

    public string $previewTone = 'cordial';

    public bool $previewAttachPdf = true;

    public string $previewChannel = 'whatsapp';

    public function mount(Invoice $invoice): void
    {
        $this->company = auth()->user()->smeCompany();

        abort_unless(
            $this->company && $invoice->company_id === $this->company->id,
            404
        );

        $invoice->load(['client', 'lines', 'reminders', 'payments']);

        $this->invoice = $invoice;
    }

    #[Computed]
    public function statusDisplay(): array
    {
        return $this->invoice->status->display();
    }

    #[Computed]
    public function remainingAmount(): int
    {
        return max(0, (int) $this->invoice->total - (int) $this->invoice->amount_paid);
    }

    #[Computed]
    public function dueLabel(): ?string
    {
        if (! $this->invoice->due_at) {
            return null;
        }

        $due = $this->invoice->due_at;

        if ($this->invoice->status === InvoiceStatus::Paid) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);

        if ($days < 0) {
            return __('Retard de :days jour(s)', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __("Échéance aujourd'hui");
        }

        return __('Dans :days jour(s)', ['days' => $days]);
    }

    #[Computed]
    public function sentRemindersCount(): int
    {
        return $this->invoice->reminders->count();
    }

    /**
     * @return array{at: \Carbon\Carbon, offset: int, days_from_now: int}|null
     */
    #[Computed]
    public function nextUpcomingReminder(): ?array
    {
        $next = $this->invoice->timeline()->firstWhere('type', 'upcoming');

        if (! $next) {
            return null;
        }

        return [
            'at' => $next['at'],
            'offset' => (int) ($next['meta']['offset'] ?? 0),
            'days_from_now' => (int) now()->startOfDay()->diffInDays($next['at']->copy()->startOfDay(), false),
        ];
    }

    #[Computed]
    public function timelineEvents(): \Illuminate\Support\Collection
    {
        return $this->invoice->timeline();
    }

    public function sendInvoice(): void
    {
        abort_unless($this->company, 403);

        if ($this->invoice->status !== InvoiceStatus::Draft) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture a déjà été envoyée.'));

            return;
        }

        $this->invoice->update([
            'status' => InvoiceStatus::Sent,
        ]);

        $this->invoice->refresh();
        unset($this->statusDisplay);

        $this->dispatch('toast', type: 'success', title: __('Facture marquée comme envoyée.'));
    }

    public function markAsPaid(?string $invoiceId = null): void
    {
        abort_unless($this->company, 403);

        $this->confirmMarkPaid = null;

        $this->invoice->update([
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $this->invoice->total,
            'paid_at' => now(),
        ]);

        $this->invoice->refresh();
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents);

        $this->dispatch('toast', type: 'success', title: __('Facture marquée comme payée.'));
    }

    public function requestMarkPaid(): void
    {
        $this->confirmMarkPaid = $this->invoice->id;
    }

    public function cancelMarkPaid(): void
    {
        $this->confirmMarkPaid = null;
    }

    public function openPaymentModal(): void
    {
        if (! $this->invoice->canReceivePayment()) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut pas recevoir de paiement.'));

            return;
        }

        $this->editingPaymentId = null;
        $this->paymentAmount = (string) $this->remainingAmount;
        $this->paymentPaidAt = now()->toDateString();
        $this->paymentMethod = PaymentMethod::Transfer->value;
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->resetValidation();
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    public function recordPayment(): void
    {
        abort_unless($this->company, 403);

        if (! $this->invoice->canReceivePayment()) {
            $this->showPaymentModal = false;
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut pas recevoir de paiement.'));

            return;
        }

        $validated = $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:1'],
            'paymentPaidAt' => ['required', 'date'],
            'paymentMethod' => ['required', new \Illuminate\Validation\Rules\Enum(PaymentMethod::class)],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'paymentAmount.required' => __('Le montant est requis.'),
            'paymentAmount.min' => __('Le montant doit être supérieur à 0.'),
            'paymentPaidAt.required' => __('La date de paiement est requise.'),
        ]);

        // Conserve la date choisie par l'utilisateur mais ajoute l'heure actuelle,
        // pour que la timeline reste correctement ordonnée contre les autres événements du jour.
        $paidAt = \Carbon\Carbon::parse($validated['paymentPaidAt'])->setTimeFrom(now());

        app(PaymentService::class)->record($this->invoice, [
            'amount' => (int) $validated['paymentAmount'],
            'paid_at' => $paidAt,
            'method' => $validated['paymentMethod'],
            'reference' => $validated['paymentReference'] ?: null,
            'notes' => $validated['paymentNotes'] ?: null,
            'recorded_by' => auth()->id(),
        ]);

        $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
        $this->showPaymentModal = false;
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents);

        $this->dispatch('toast', type: 'success', title: __('Paiement enregistré.'));
    }

    public function requestDeletePayment(string $paymentId): void
    {
        $this->confirmDeletePaymentId = $paymentId;
    }

    public function cancelDeletePayment(): void
    {
        $this->confirmDeletePaymentId = null;
    }

    public function deletePayment(?string $paymentId = null): void
    {
        abort_unless($this->company, 403);

        $paymentId ??= $this->confirmDeletePaymentId;

        if (! $paymentId) {
            return;
        }

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
            ->first();

        $this->confirmDeletePaymentId = null;

        if (! $payment) {
            $this->dispatch('toast', type: 'error', title: __('Paiement introuvable.'));

            return;
        }

        app(PaymentService::class)->delete($payment);

        $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents);

        $this->dispatch('toast', type: 'success', title: __('Paiement supprimé.'));
    }

    public function openReminderPreview(): void
    {
        abort_unless($this->company, 403);

        if (! $this->invoice->canReceiveReminder()) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut plus être relancée.'));

            return;
        }

        $this->previewInvoiceId = $this->invoice->id;
        $this->previewTone = 'cordial';
        $this->previewAttachPdf = true;
        $this->previewChannel = filled($this->invoice->client?->phone)
            ? ReminderChannel::WhatsApp->value
            : ReminderChannel::Email->value;
    }

    public function closeReminderPreview(): void
    {
        $this->previewInvoiceId = null;
    }

    public function sendReminderNow(?string $invoiceId = null): void
    {
        abort_unless($this->company, 403);

        if (! $this->invoice->canReceiveReminder()) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture ne peut plus être relancée.'));

            return;
        }

        if (now()->isWeekend()) {
            $this->dispatch('toast', type: 'warning', title: __('Les relances ne peuvent être envoyées qu\'en jour ouvré (lundi au vendredi).'));

            return;
        }

        try {
            $channel = ReminderChannel::from($this->previewChannel);
            $msg = $this->buildPreviewMessage();
            $messageBody = implode("\n\n", array_filter([
                $msg['greeting'],
                $msg['body'],
                $msg['closing'],
                $this->company->name,
            ])) ?: null;

            app(ReminderService::class)
                ->send($this->invoice, $this->company, $channel, $messageBody, mode: ReminderMode::Manual);

            $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
            $this->previewInvoiceId = null;
            unset($this->timelineEvents);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }
    }

    /**
     * @return array{greeting: string, body: string, closing: string}
     */
    public function buildPreviewMessage(): array
    {
        $inv = $this->invoice;
        $clientName = $inv->client?->name ?? '—';
        $reference = $inv->reference ?? '—';
        $remaining = format_money((int) $inv->total - (int) $inv->amount_paid);
        $dueDate = format_date($inv->due_at);

        $toneGreetings = [
            'cordial' => "Bonjour {$clientName},",
            'ferme' => "Bonjour {$clientName},",
            'urgent' => "{$clientName},",
        ];

        $toneBody = [
            'cordial' => "Nous souhaitons vous rappeler que la facture {$reference} d'un montant de {$remaining}, échue le {$dueDate}, reste en attente de règlement.\n\nNous vous serions reconnaissants de bien vouloir procéder au paiement dans les meilleurs délais.",
            'ferme' => "La facture {$reference} d'un montant de {$remaining} est en retard de paiement depuis le {$dueDate}.\n\nNous vous demandons de procéder au règlement dans les plus brefs délais.",
            'urgent' => "URGENT : La facture {$reference} ({$remaining}) est impayée depuis le {$dueDate}. Malgré nos précédentes relances, aucun règlement n'a été effectué.\n\nNous vous prions de régulariser cette situation immédiatement.",
        ];

        $toneClosing = [
            'cordial' => 'Cordialement,',
            'ferme' => "Dans l'attente de votre règlement,",
            'urgent' => 'En espérant une action immédiate de votre part,',
        ];

        $tone = $this->previewTone;

        return [
            'greeting' => $toneGreetings[$tone] ?? $toneGreetings['cordial'],
            'body' => $toneBody[$tone] ?? $toneBody['cordial'],
            'closing' => $toneClosing[$tone] ?? $toneClosing['cordial'],
        ];
    }

    public function requestDeleteInvoice(): void
    {
        $this->confirmDeleteInvoice = $this->invoice->id;
    }

    public function cancelDeleteInvoice(): void
    {
        $this->confirmDeleteInvoice = null;
    }

    public function deleteInvoice(?string $invoiceId = null): void
    {
        abort_unless($this->company, 403);

        $this->confirmDeleteInvoice = null;
        $this->invoice->delete();

        session()->flash('success', __('La facture a été supprimée.'));

        // Pas de navigate:true ici : un redirect classique garantit que le flash
        // est bien rejoué au prochain rendu de la page cible.
        $this->redirect(route('pme.invoices.index'));
    }

    public function duplicateInvoice(): void
    {
        abort_unless($this->company, 403);

        $copy = $this->invoice->replicate(['reference', 'status', 'paid_at', 'amount_paid', 'certification_authority', 'certification_data']);
        $copy->reference = 'FYK-FAC-'.strtoupper(\Illuminate\Support\Str::random(6));
        $copy->status = InvoiceStatus::Draft;
        $copy->paid_at = null;
        $copy->amount_paid = 0;
        $copy->issued_at = now();
        $copy->due_at = now()->addDays(30);
        $copy->save();

        foreach ($this->invoice->lines as $line) {
            $copy->lines()->create($line->only(['description', 'quantity', 'unit_price', 'tax_rate', 'total']));
        }

        $this->redirect(route('pme.invoices.edit', $copy), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @php
        $inv = $this->invoice;
        $status = $this->statusDisplay;
        $remaining = $this->remainingAmount;
    @endphp

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('pme.invoices.index') }}" wire:navigate class="text-sm font-semibold text-slate-500 transition hover:text-primary">
                    {{ __('← Retour aux factures') }}
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-semibold tracking-tight text-ink">
                        {{ __('Facture') }} {{ $inv->reference ?? '—' }}
                    </h2>
                    <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $status['class'] }}">
                        {{ __($status['label']) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    @if ($inv->client)
                        <a href="{{ route('pme.clients.show', $inv->client_id) }}" wire:navigate class="font-medium text-ink hover:text-primary">
                            {{ $inv->client->name }}
                        </a>
                        ·
                    @endif
                    {{ __('Émise le') }} {{ $inv->issued_at ? format_date($inv->issued_at) : '—' }}
                    @if ($inv->due_at)
                        · {{ __('échéance') }} {{ format_date($inv->due_at) }}
                        @if ($this->dueLabel)
                            <span @class([
                                'ml-1 font-medium',
                                'text-rose-600' => $inv->status === InvoiceStatus::Overdue,
                                'text-slate-500' => $inv->status !== InvoiceStatus::Overdue,
                            ])>({{ $this->dueLabel }})</span>
                        @endif
                    @endif
                </p>
            </div>

        </div>
    </section>

    {{-- KPIs --}}
    @php
        $next = $this->nextUpcomingReminder;
        $isOverdue = $inv->status === InvoiceStatus::Overdue;
        $hasRemaining = $remaining > 0 && $inv->status !== InvoiceStatus::Draft;
    @endphp
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Montant TTC') }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">
                {{ format_money($inv->total, $inv->currency) }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <p @class([
                'text-sm font-semibold uppercase tracking-[0.2em]',
                'text-rose-600' => $hasRemaining,
                'text-slate-500' => ! $hasRemaining,
            ])>{{ __('Reste dû') }}</p>
            <p @class([
                'mt-2 text-3xl font-semibold tracking-tight',
                'text-rose-600' => $hasRemaining,
                'text-ink' => ! $hasRemaining,
            ])>
                {{ format_money($remaining, $inv->currency) }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Relances') }}</p>
            <p class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight text-ink">{{ $this->sentRemindersCount }}</span>
                @if ($this->sentRemindersCount > 0)
                    <span class="text-sm text-slate-500">{{ __('envoyée(s)') }}</span>
                @endif
            </p>
            @if ($this->sentRemindersCount === 0)
                <p class="mt-1 text-sm text-slate-500">{{ __('Aucune pour le moment') }}</p>
            @endif
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Prochaine relance') }}</p>
            @if ($next)
                <p class="mt-2 text-xl font-semibold text-ink">
                    @if ($next['days_from_now'] === 0)
                        {{ __("Aujourd'hui") }}
                    @elseif ($next['days_from_now'] === 1)
                        {{ __('Demain') }}
                    @else
                        {{ __('Dans :days jours', ['days' => $next['days_from_now']]) }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ format_date($next['at']) }} · {{ __('Auto J+:offset', ['offset' => $next['offset']]) }}
                </p>
            @else
                <p class="mt-2 text-xl font-semibold text-slate-400">—</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Aucune prévue') }}</p>
            @endif
        </article>
    </section>

    {{-- Corps 2 colonnes --}}
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Colonne gauche --}}
        <div class="flex flex-col gap-6 xl:col-span-2">

            {{-- Carte client --}}
            <x-invoices.client-card :invoice="$inv" />

            {{-- Aperçu facture --}}
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Aperçu de la facture') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Les informations envoyées au client.') }}</p>
                </div>
                <div class="mt-6">
                    <x-invoices.preview-card :invoice="$inv" :show-client="false" />
                </div>
            </article>

            {{-- Paiements liés (pas de paiement sur brouillons) --}}
            @if ($inv->status !== InvoiceStatus::Draft)
            <article class="app-shell-panel p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-ink">{{ __('Paiements enregistrés') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Cumulé') }} : {{ format_money($inv->amount_paid, $inv->currency) }} / {{ format_money($inv->total, $inv->currency) }}
                        </p>
                    </div>
                    @if ($inv->canReceivePayment())
                        <button
                            type="button"
                            wire:click="openPaymentModal"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-strong"
                        >
                            <flux:icon name="plus" class="size-4" />
                            {{ __('Enregistrer un paiement') }}
                        </button>
                    @endif
                </div>

                @if ($inv->payments->isNotEmpty())
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-left">
                                    <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Date') }}</th>
                                    <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Méthode') }}</th>
                                    <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                                    <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500">{{ __('Montant') }}</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($inv->payments->sortByDesc('paid_at') as $payment)
                                    <tr wire:key="payment-{{ $payment->id }}">
                                        <td class="py-3 pr-4 text-slate-600 whitespace-nowrap">{{ format_date($payment->paid_at) }}</td>
                                        <td class="py-3 px-4 text-slate-600">{{ __($payment->method->label()) }}</td>
                                        <td class="py-3 px-4 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                        <td class="py-3 px-4 text-right font-semibold text-ink whitespace-nowrap">
                                            {{ format_money($payment->amount, $inv->currency) }}
                                        </td>
                                        <td class="py-3 pl-4 text-right">
                                            <button
                                                type="button"
                                                wire:click="requestDeletePayment('{{ $payment->id }}')"
                                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-500"
                                                aria-label="{{ __('Supprimer le paiement') }}"
                                            >
                                                <flux:icon name="trash" class="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-5 py-6 text-center text-sm text-slate-500">
                        {{ __('Aucun paiement enregistré pour cette facture.') }}
                    </div>
                @endif
            </article>
            @endif

            {{-- Activité : création, échéance, relances (envoyées + à venir), paiements --}}
            <article class="app-shell-panel p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-ink">{{ __('Activité') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Toute la vie de cette facture : création, échéance, relances et paiements.') }}</p>
                    </div>
                    @if ($inv->status !== InvoiceStatus::Draft && $inv->canReceiveReminder())
                        <button
                            type="button"
                            wire:click="openReminderPreview"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                        >
                            <flux:icon name="bell-alert" class="size-3.5" />
                            {{ __('Relancer maintenant') }}
                        </button>
                    @endif
                </div>

                <div class="mt-5">
                    <x-invoices.activity-feed :invoice="$inv" />
                </div>
            </article>

        </div>

        {{-- Colonne droite (sidebar sticky sur xl, sinon bloc centré sous le contenu) --}}
        <div class="mx-auto flex w-full max-w-md flex-col gap-6 xl:mx-0 xl:max-w-none">

            {{-- Actions rapides --}}
            <article class="app-shell-panel p-6 xl:sticky xl:top-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions rapides') }}</h3>

                <div class="mt-4 space-y-2">
                    @if ($inv->status === InvoiceStatus::Draft)
                        <a href="{{ route('pme.invoices.edit', $inv) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                            <flux:icon name="pencil-square" class="size-4" /> {{ __('Modifier la facture') }}
                        </a>
                        <button type="button" wire:click="sendInvoice" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="paper-airplane" class="size-4" /> {{ __('Envoyer au client') }}
                        </button>
                    @endif

                    @if (in_array($inv->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid], true))
                        @if ($remaining > 0)
                            <button type="button" wire:click="openPaymentModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                                <flux:icon name="banknotes" class="size-4" /> {{ __('Enregistrer un paiement') }}
                            </button>
                        @endif

                        @if ($inv->status === InvoiceStatus::Sent)
                            <a href="{{ route('pme.invoices.edit', $inv) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                                <flux:icon name="pencil-square" class="size-4" /> {{ __('Modifier la facture') }}
                            </a>
                        @endif

                        <button type="button" wire:click="openReminderPreview" @disabled(! $inv->canReceiveReminder()) class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                            <flux:icon name="bell-alert" class="size-4" /> {{ __('Relancer le client') }}
                        </button>

                        <button type="button" wire:click="requestMarkPaid" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="check-circle" class="size-4" /> {{ __('Marquer comme payée') }}
                        </button>
                    @endif

                    <a href="{{ route('pme.invoices.pdf', $inv) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="arrow-down-tray" class="size-4" /> {{ __('Télécharger le PDF') }}
                    </a>
                </div>

                <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                    @if ($inv->client_id)
                        <a href="{{ route('pme.clients.show', $inv->client_id) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="user" class="size-4" /> {{ __('Voir le client') }}
                        </a>
                    @endif
                    <button type="button" wire:click="duplicateInvoice" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="document-duplicate" class="size-4" /> {{ __('Dupliquer') }}
                    </button>
                    <button type="button" wire:click="requestDeleteInvoice" class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50">
                        <flux:icon name="trash" class="size-4" /> {{ __('Supprimer la facture') }}
                    </button>
                </div>
            </article>

        </div>
    </section>

    {{-- Modale paiement --}}
    @if ($showPaymentModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="closePaymentModal"
            x-data
            @keydown.escape.window="$wire.closePaymentModal()"
        >
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="recordPayment">
                    <div class="flex items-start justify-between border-b border-slate-100 px-7 py-5">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ __('Enregistrer un paiement') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Reste dû') }} : {{ format_money($remaining, $inv->currency) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closePaymentModal"
                            class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>

                    <div class="grid gap-4 px-7 py-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Montant') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                wire:model="paymentAmount"
                                type="number"
                                min="1"
                                step="1"
                                required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                            @error('paymentAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Date') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                wire:model="paymentPaidAt"
                                type="date"
                                required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                            @error('paymentPaidAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Méthode') }}</label>
                            <x-select-native>
                                <select wire:model="paymentMethod" class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-slate-700 focus:border-primary/50 focus:outline-none">
                                    @foreach (PaymentMethod::cases() as $method)
                                        <option value="{{ $method->value }}">{{ __($method->label()) }}</option>
                                    @endforeach
                                </select>
                            </x-select-native>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Référence') }}</label>
                            <input
                                wire:model="paymentReference"
                                type="text"
                                placeholder="{{ __('N° de transaction, chèque, etc.') }}"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                            <textarea
                                wire:model="paymentNotes"
                                rows="3"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                        <button
                            type="button"
                            wire:click="closePaymentModal"
                            class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                        >
                            {{ __('Annuler') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                        >
                            {{ __('Enregistrer') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Slide-over de prévisualisation / envoi de relance --}}
    @if ($previewInvoiceId && $this->company)
        <x-collection.reminder-preview-slideover
            :invoice="$inv"
            :message="$this->buildPreviewMessage()"
            :company="$company"
            :preview-invoice-id="$previewInvoiceId"
            :preview-attach-pdf="$previewAttachPdf"
            :preview-channel="$previewChannel"
            close-action="closeReminderPreview"
            send-action="sendReminderNow"
        />
    @endif

    <x-ui.confirm-modal
        :confirm-id="$confirmMarkPaid"
        :title="__('Marquer comme payée')"
        :description="__('Cette facture sera marquée comme entièrement payée. Cette action est irréversible.')"
        confirm-action="markAsPaid"
        cancel-action="cancelMarkPaid"
        :confirm-label="__('Confirmer le paiement')"
        variant="primary"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmDeletePaymentId"
        :title="__('Supprimer ce paiement')"
        :description="__('Le paiement sera retiré et le statut de la facture recalculé.')"
        confirm-action="deletePayment"
        cancel-action="cancelDeletePayment"
        :confirm-label="__('Supprimer')"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmDeleteInvoice"
        :title="__('Supprimer la facture')"
        :description="__('Cette action est irréversible. La facture sera définitivement supprimée.')"
        confirm-action="deleteInvoice"
        cancel-action="cancelDeleteInvoice"
        :confirm-label="__('Supprimer')"
    />

</div>

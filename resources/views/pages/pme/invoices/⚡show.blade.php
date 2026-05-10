<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\DocumentLifecycleService;
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


    public string $previewChannel = 'whatsapp';

    // Modal "Envoyer la facture"
    public bool $showSendModal = false;

    /** 'whatsapp' | 'email' */
    public string $sendChannel = 'whatsapp';

    public string $sendRecipient = '';

    public string $sendMessage = '';

    /** Code pays ISO-2 pour le composant phone-input (WhatsApp). */
    public string $sendCountry = 'SN';

    /** @var array<string, string> Liste des pays disponibles pour le composant phone-input. */
    public array $sendPhoneCountries = [];

    public function mount(Invoice $invoice): void
    {
        $this->company = auth()->user()->smeCompany();

        abort_unless(
            $this->company && $invoice->company_id === $this->company->id,
            404
        );

        $invoice->load(['client', 'lines', 'reminders', 'payments']);

        $this->invoice = $invoice;

        // Liste des pays pour le sélecteur du composant phone-input.
        $this->sendPhoneCountries = collect(config('fayeku.phone_countries', []))
            ->map(fn ($c) => $c['label'])
            ->all();

        // Auto-ouvre la modale d'envoi quand on arrive depuis le formulaire
        // de création/édition (`?send=1`) — flow "Créer et envoyer la facture".
        if (request()->boolean('send')) {
            $this->openSendModal();
        }
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
            $abs = abs($days);

            return $abs > 1
                ? __('Retard de :days jours', ['days' => $abs])
                : __('Retard de :days jour', ['days' => $abs]);
        }

        if ($days === 0) {
            return __("Échéance aujourd'hui");
        }

        return $days > 1
            ? __('Dans :days jours', ['days' => $days])
            : __('Dans :days jour', ['days' => $days]);
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

    #[Computed]
    public function lifecycleState(): array
    {
        return app(DocumentLifecycleService::class)->forInvoice($this->invoice);
    }

    public function sendInvoice(): void
    {
        // Conservé pour compat — passe rapidement la facture en Sent sans modal.
        // Le nouveau flow d'envoi est dans openSendModal/confirmSend.
        abort_unless($this->company, 403);

        if ($this->invoice->status !== InvoiceStatus::Draft) {
            $this->dispatch('toast', type: 'warning', title: __('Cette facture a déjà été envoyée.'));

            return;
        }

        $this->invoice->update([
            'status' => InvoiceStatus::Sent,
            'sent_at' => $this->invoice->sent_at ?? now(),
        ]);

        $this->invoice->refresh();
        unset($this->statusDisplay, $this->lifecycleState);

        $this->dispatch('toast', type: 'success', title: __('Facture marquée comme envoyée.'));
    }

    // ─── Envoi (WhatsApp / Email) ────────────────────────────────────────────

    public function openSendModal(): void
    {
        if (in_array($this->invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Paid], true)) {
            return;
        }

        $this->resetErrorBag();
        $client = $this->invoice->client;

        // Canal par défaut : WhatsApp si téléphone dispo, sinon email.
        $this->sendChannel = filled($client?->phone) ? 'whatsapp' : 'email';
        $this->fillSendDefaults();
        $this->showSendModal = true;
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
        $this->resetErrorBag();
    }

    public function updatedSendChannel(): void
    {
        $this->fillSendDefaults();
    }

    private function fillSendDefaults(): void
    {
        $client = $this->invoice->client;

        if ($this->sendChannel === 'email') {
            $this->sendRecipient = $client?->email ?? '';
        } else {
            $clientPhone = $client?->phone ?? '';
            if (filled($clientPhone)) {
                $parsed = \App\Support\PhoneNumber::parse($clientPhone);
                $this->sendCountry = $parsed['country_code'];
                $this->sendRecipient = $parsed['local_number'];
            } else {
                $this->sendCountry = $this->company?->country_code ?? 'SN';
                $this->sendRecipient = '';
            }
        }
        $this->sendMessage = $this->buildSendMessage();
    }

    private function buildSendMessage(): string
    {
        $link = route('pme.invoices.pdf', $this->invoice->public_code);
        $total = format_money($this->invoice->total, $this->invoice->currency);
        $dueAt = $this->invoice->due_at ? format_date($this->invoice->due_at) : '—';
        $reference = $this->invoice->reference;
        $paymentMethods = $this->paymentMethodsLabel();
        $signature = $this->buildSignature();

        $depositResolved = (int) $this->invoice->deposit_amount > 0
            ? min((int) $this->invoice->deposit_amount, (int) $this->invoice->total)
            : 0;

        if ($depositResolved > 0) {
            $deposit = format_money($depositResolved, $this->invoice->currency);
            $remaining = format_money(max(0, (int) $this->invoice->total - $depositResolved), $this->invoice->currency);

            return <<<MSG
                Bonjour,

                Veuillez trouver notre facture n° {$reference}, d'un montant total de {$total} TTC.

                Acompte déjà versé : {$deposit}.
                Reste à payer : {$remaining}.

                Consulter la facture :
                {$link}

                Échéance de paiement : {$dueAt}.
                Moyens de paiement acceptés : {$paymentMethods}.

                {$signature}
                MSG;
        }

        return <<<MSG
            Bonjour,

            Veuillez trouver notre facture n° {$reference}, d'un montant de {$total}.

            Consulter la facture :
            {$link}

            Échéance de paiement : {$dueAt}.
            Moyens de paiement acceptés : {$paymentMethods}.

            {$signature}
            MSG;
    }

    private function paymentMethodsLabel(): string
    {
        return match ($this->invoice->payment_method) {
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'cash' => 'Espèces',
            'bank_transfer' => 'virement bancaire',
            default => 'Wave, Orange Money, virement bancaire',
        };
    }

    private function buildSignature(): string
    {
        $sender = trim((string) ($this->company?->sender_name ?? ''));
        $companyName = trim((string) ($this->company?->name ?? ''));

        $lines = ['Cordialement,'];
        if ($sender !== '') {
            $lines[] = $sender;
        }
        if ($companyName !== '') {
            $lines[] = $companyName;
        }

        return implode("\n", $lines);
    }

    #[Computed]
    public function sendOpenUrl(): string
    {
        if ($this->sendChannel === 'whatsapp') {
            $digits = \App\Support\PhoneNumber::digitsForWhatsApp($this->sendRecipient, $this->sendCountry);

            return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->sendMessage);
        }

        $subject = rawurlencode((string) __('Facture :ref', ['ref' => $this->invoice->reference]));

        return 'mailto:'.$this->sendRecipient.'?subject='.$subject.'&body='.rawurlencode($this->sendMessage);
    }

    public function confirmSend(): void
    {
        $rules = $this->sendChannel === 'email'
            ? ['sendRecipient' => ['required', 'email']]
            : ['sendRecipient' => ['required', 'string', 'min:6']];

        $this->validate($rules + [
            'sendMessage' => ['required', 'string', 'min:5'],
        ], [
            'sendRecipient.required' => __('Renseignez un destinataire.'),
            'sendRecipient.email' => __("L'adresse email n'est pas valide."),
            'sendMessage.required' => __('Le message ne peut pas être vide.'),
        ]);

        // Le clic sur "Envoyer depuis WhatsApp/messagerie" déclenche la transition
        // Draft → Sent (ou PartiallyPaid si un acompte a déjà été enregistré,
        // ou Paid si l'acompte couvre 100% du total). markAsSent encapsule cette
        // logique pour rester cohérent avec le service.
        $statusChanged = false;
        if ($this->invoice->status === InvoiceStatus::Draft) {
            app(\App\Services\PME\InvoiceService::class)->markAsSent($this->invoice);
            $this->invoice->refresh();
            unset($this->statusDisplay, $this->lifecycleState);
            $statusChanged = true;
        }

        // L'ouverture du canal externe (WhatsApp / mailto) se fait côté client
        // dans le click-handler de la modale (cf. send-modal.blade.php) pour ne
        // pas déclencher le popup-blocker. Ici on se contente de fermer la modale
        // et de notifier la transition de statut.
        $this->showSendModal = false;

        if ($statusChanged) {
            $this->dispatch('toast', type: 'success', title: __('Facture marquée comme envoyée.'));
        }
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
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents, $this->lifecycleState);

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

        $payment = app(PaymentService::class)->record($this->invoice, [
            'amount' => (int) $validated['paymentAmount'],
            'paid_at' => $paidAt,
            'method' => $validated['paymentMethod'],
            'reference' => $validated['paymentReference'] ?: null,
            'notes' => $validated['paymentNotes'] ?: null,
            'recorded_by' => auth()->id(),
        ]);

        $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
        $this->showPaymentModal = false;
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents, $this->lifecycleState);

        if ($this->company) {
            $notifier = app(\App\Services\PME\WhatsAppNotificationService::class);
            if ((int) $this->invoice->amount_paid >= (int) $this->invoice->total) {
                $notifier->sendInvoicePaidFull($this->invoice, $payment, $this->company);
            } else {
                $notifier->sendInvoicePartiallyPaid($this->invoice, $payment, $this->company);
            }
        }

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
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents, $this->lifecycleState);

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

            $catalog = app(\App\Services\Shared\WhatsAppTemplateCatalog::class);
            $templateKey = $catalog->manualReminderKeyForTone($this->previewTone);
            $messageBody = $this->buildPreviewMessage() ?: null;

            app(ReminderService::class)
                ->send($this->invoice, $this->company, $channel, $messageBody, mode: ReminderMode::Manual, templateKey: $templateKey);

            $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
            $this->previewInvoiceId = null;
            unset($this->timelineEvents);

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }
    }

    public function buildPreviewMessage(): string
    {
        return app(\App\Services\Shared\WhatsAppTemplateCatalog::class)
            ->renderManualReminder($this->invoice, $this->company, $this->previewTone);
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

    <x-documents.lifecycle-card :lifecycle="$this->lifecycleState" />

    {{-- Corps 2 colonnes : Aperçu en premier (full width sur mobile, 2/3 sur lg+),
         sidebar Client+Actions à droite (full width sur mobile, 1/3 sur lg+). --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Colonne gauche : Aperçu / Paiements / Activity --}}
        <div class="flex flex-col gap-6 lg:col-span-2">

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

            {{-- Acompte versé à la création (visible dès qu'un acompte > 0, même en brouillon) --}}
            @php
                $depositPayments = $inv->payments->where('is_deposit', true);
                $manualPayments = $inv->payments->where('is_deposit', false);
            @endphp
            @if ($depositPayments->isNotEmpty())
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Avance / acompte déjà payé') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Acompte versé à la création de la facture — déduit du reste à payer.') }}</p>
                </div>

                {{-- Mobile: cartes empilées --}}
                <div class="mt-5 space-y-3 sm:hidden">
                    @foreach ($depositPayments->sortByDesc('paid_at') as $payment)
                        <div wire:key="deposit-card-{{ $payment->id }}" class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-xs uppercase tracking-wide text-slate-500">{{ format_date($payment->paid_at) }}</span>
                                <span class="font-semibold text-ink tabular-nums whitespace-nowrap">{{ format_money($payment->amount, $inv->currency) }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ __($payment->method->label()) }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop: tableau --}}
                <div class="mt-5 hidden overflow-x-auto sm:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left">
                                <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Date') }}</th>
                                <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Méthode') }}</th>
                                <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500">{{ __('Montant') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($depositPayments->sortByDesc('paid_at') as $payment)
                                <tr wire:key="deposit-{{ $payment->id }}">
                                    <td class="py-3 pr-4 text-slate-600 whitespace-nowrap">{{ format_date($payment->paid_at) }}</td>
                                    <td class="py-3 px-4 text-slate-600">{{ __($payment->method->label()) }}</td>
                                    <td class="py-3 px-4 text-right font-semibold text-ink whitespace-nowrap">
                                        {{ format_money($payment->amount, $inv->currency) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
            @endif

            {{-- Paiements liés (pas de paiement sur brouillons) --}}
            @if ($inv->status !== InvoiceStatus::Draft)
            <article class="app-shell-panel p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-ink">{{ __('Paiements enregistrés') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Cumulé') }} : {{ format_money($inv->amount_paid, $inv->currency) }} / {{ format_money($inv->total, $inv->currency) }}
                            @if ($depositPayments->isNotEmpty())
                                · {{ __('dont acompte :') }} {{ format_money((int) $depositPayments->sum('amount'), $inv->currency) }}
                            @endif
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

                @if ($manualPayments->isNotEmpty())
                    {{-- Mobile: cartes empilées --}}
                    <div class="mt-5 space-y-3 sm:hidden">
                        @foreach ($manualPayments->sortByDesc('paid_at') as $payment)
                            <div wire:key="payment-card-{{ $payment->id }}" class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-xs uppercase tracking-wide text-slate-500">{{ format_date($payment->paid_at) }}</span>
                                    <span class="font-semibold text-ink tabular-nums whitespace-nowrap">{{ format_money($payment->amount, $inv->currency) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ __($payment->method->label()) }}</p>
                                @if ($payment->reference)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Réf.') }} : {{ $payment->reference }}</p>
                                @endif
                                <button
                                    type="button"
                                    wire:click="requestDeletePayment('{{ $payment->id }}')"
                                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-rose-500 hover:text-rose-600"
                                    aria-label="{{ __('Supprimer le paiement') }}"
                                >
                                    <flux:icon name="trash" class="size-3.5" />
                                    {{ __('Supprimer') }}
                                </button>
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop: tableau --}}
                    <div class="mt-5 hidden overflow-x-auto sm:block">
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
                                @foreach ($manualPayments->sortByDesc('paid_at') as $payment)
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

        {{-- Colonne droite : client + actions (sticky sur lg). --}}
        <div class="flex w-full flex-col gap-6">

            {{-- Carte client --}}
            <x-invoices.client-card :invoice="$inv" />

            {{-- Actions rapides --}}
            @php
                $isPayable = in_array($inv->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid], true);
                $isClosed = in_array($inv->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
                $canEdit = in_array($inv->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true);
            @endphp
            <article class="app-shell-panel p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions rapides') }}</h3>

                {{-- Actions primaires (3 max selon le statut) --}}
                <div class="mt-4 space-y-2">
                    {{-- Enregistrer un paiement : uniquement si la facture peut encore recevoir un paiement --}}
                    @if ($isPayable && $remaining > 0)
                        <button type="button" wire:click="openPaymentModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                            <flux:icon name="banknotes" class="size-4" /> {{ __('Enregistrer un paiement') }}
                        </button>
                    @endif

                    {{-- Relancer le client : uniquement quand applicable --}}
                    @if ($isPayable)
                        <button type="button" wire:click="openReminderPreview" @disabled(! $inv->canReceiveReminder()) class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50">
                            <flux:icon name="bell-alert" class="size-4" /> {{ __('Relancer le client') }}
                        </button>
                    @endif

                    {{-- Télécharger le PDF : toujours visible --}}
                    <a href="{{ route('pme.invoices.pdf', $inv) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="arrow-down-tray" class="size-4" /> {{ __('Télécharger le PDF') }}
                    </a>

                    {{-- Plus d'actions : dropdown teleporté pour échapper au sticky/overflow --}}
                    <div
                        x-data="{ open: false, top: 0, right: 0, width: 0 }"
                        class="relative"
                        @click.window="open = false"
                        @keydown.escape.window="open = false"
                    >
                        <button
                            type="button"
                            x-ref="moreActionsTrigger"
                            @click.stop="
                                const wasOpen = open;
                                if (wasOpen) { open = false; return; }
                                const rect = $refs.moreActionsTrigger.getBoundingClientRect();
                                top = rect.bottom + 8;
                                right = window.innerWidth - rect.right;
                                width = rect.width;
                                open = true;
                            "
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                            :aria-expanded="open"
                        >
                            <flux:icon name="ellipsis-horizontal" class="size-4" />
                            {{ __("Plus d'actions") }}
                            <svg class="size-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        <template x-teleport="body">
                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.stop
                                :style="`position: fixed; z-index: 9999; top: ${top}px; right: ${right}px; min-width: ${width}px`"
                                class="overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                                role="menu"
                            >
                                @if (! $isClosed)
                                    <button type="button" @click="open = false" wire:click="openSendModal" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <flux:icon name="paper-airplane" class="size-4 text-slate-400" />
                                        {{ $inv->status === InvoiceStatus::Draft ? __('Envoyer au client') : __('Renvoyer au client') }}
                                    </button>
                                @endif
                                @if ($canEdit)
                                    <a href="{{ route('pme.invoices.edit', $inv) }}" @click="open = false" wire:navigate class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <flux:icon name="pencil-square" class="size-4 text-slate-400" />
                                        {{ __('Modifier la facture') }}
                                    </a>
                                @endif
                                @if ($isPayable)
                                    <button type="button" @click="open = false" wire:click="requestMarkPaid" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <flux:icon name="check-circle" class="size-4 text-slate-400" />
                                        {{ __('Marquer comme payée') }}
                                    </button>
                                @endif
                                @if (! $isClosed || $canEdit || $isPayable)
                                    <div class="my-1 border-t border-slate-100"></div>
                                @endif
                                @if ($inv->client_id)
                                    <a href="{{ route('pme.clients.show', $inv->client_id) }}" @click="open = false" wire:navigate class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <flux:icon name="user" class="size-4 text-slate-400" />
                                        {{ __('Voir le client') }}
                                    </a>
                                @endif
                                <button type="button" @click="open = false" wire:click="duplicateInvoice" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <flux:icon name="document-duplicate" class="size-4 text-slate-400" />
                                    {{ __('Dupliquer') }}
                                </button>
                                <div class="my-1 border-t border-slate-100"></div>
                                <button type="button" @click="open = false" wire:click="requestDeleteInvoice" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-rose-600 transition hover:bg-rose-50">
                                    <flux:icon name="trash" class="size-4 text-rose-400" />
                                    {{ __('Supprimer la facture') }}
                                </button>
                            </div>
                        </template>
                    </div>
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

    <x-invoicing.send-modal
        :title="__('Envoyer la facture')"
        :show-send-modal="$showSendModal"
        :send-channel="$sendChannel"
        :send-recipient="$sendRecipient"
        :send-country="$sendCountry"
        :send-phone-countries="$sendPhoneCountries"
        :send-open-url="$this->sendOpenUrl"
    />

</div>

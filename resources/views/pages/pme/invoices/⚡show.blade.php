<?php

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\PaymentMethod;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use App\Services\PME\CurrencyService;
use App\Services\PME\DocumentLifecycleService;
use App\Services\PME\InvoiceService;
use App\Services\PME\PaymentService;
use App\Services\PME\ReminderService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Facture')] #[Layout('layouts::pme')] class extends Component {
    use WithFileUploads;

    public Invoice $invoice;

    public ?Company $company = null;

    public bool $showPaymentModal = false;

    public ?string $editingPaymentId = null;

    public string $paymentAmount = '';

    public string $paymentPaidAt = '';

    public string $paymentMethod = 'transfer';

    public string $paymentReference = '';

    public string $paymentNotes = '';

    /** Preuve de paiement (PDF/JPG/PNG) optionnelle. */
    public $paymentProofFile = null;

    /** Chemin de la preuve déjà existante (édition) — pour l'affichage. */
    public ?string $paymentExistingProofPath = null;

    public bool $removePaymentProof = false;

    // Modale "Détails du paiement"
    public ?string $paymentDetailsId = null;

    /** @var array<string, mixed> Config currency JS pour le formateur de montant. */
    public array $currencyJs = [];

    public ?string $confirmMarkPaid = null;

    public ?string $confirmDeletePaymentId = null;

    public ?string $confirmDeleteInvoice = null;

    // Modal "Annuler la facture"
    public bool $showCancelModal = false;

    public string $cancelReason = '';

    // Modal "Supprimer brouillon"
    public bool $showDeleteDraftModal = false;

    public string $deleteDraftStrategy = 'release';

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
        $this->currencyJs = CurrencyService::jsConfig($invoice->currency);

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

        $this->sendMessage = $this->sendChannel === 'email'
            ? $this->buildEmailMessage()
            : $this->buildWhatsAppMessage();
    }

    /**
     * Message WhatsApp — gras via *...* (rendu en gras dans le client WhatsApp).
     */
    private function buildWhatsAppMessage(): string
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

                Veuillez trouver notre facture n° *{$reference}*, d'un montant total de *{$total}* TTC.

                Acompte déjà versé : *{$deposit}*.
                Reste à payer : *{$remaining}*.

                Consulter la facture :
                {$link}

                Échéance de paiement : *{$dueAt}*.
                Moyens de paiement acceptés : {$paymentMethods}.

                {$signature}
                MSG;
        }

        return <<<MSG
            Bonjour,

            Veuillez trouver notre facture n° *{$reference}*, d'un montant de *{$total}*.

            Consulter la facture :
            {$link}

            Échéance de paiement : *{$dueAt}*.
            Moyens de paiement acceptés : {$paymentMethods}.

            {$signature}
            MSG;
    }

    /**
     * Message email — plain text, ton plus narratif que la version WhatsApp.
     * Pas d'astérisques (qui apparaîtraient comme des marqueurs littéraux dans
     * la plupart des clients mail).
     */
    private function buildEmailMessage(): string
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

                Vous trouverez la facture {$reference} d'un montant total de {$total} TTC, à régler avant le {$dueAt}.
                Un acompte de {$deposit} a déjà été versé, reste à payer {$remaining}.

                Consulter la facture en ligne :
                {$link}

                Moyens de paiement acceptés : {$paymentMethods}.

                Pour toute question, n'hésitez pas à répondre directement à cet email.

                {$signature}
                MSG;
        }

        return <<<MSG
            Bonjour,

            Vous trouverez la facture {$reference} d'un montant de {$total}, à régler avant le {$dueAt}.

            Consulter la facture en ligne :
            {$link}

            Moyens de paiement acceptés : {$paymentMethods}.

            Pour toute question, n'hésitez pas à répondre directement à cet email.

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
    public function sendEmailSubject(): string
    {
        $reference = $this->invoice->reference;
        $companyName = trim((string) ($this->company?->name ?? ''));

        return $companyName !== ''
            ? "Facture {$reference} — {$companyName}"
            : "Facture {$reference}";
    }

    #[Computed]
    public function sendOpenUrl(): string
    {
        if ($this->sendChannel === 'whatsapp') {
            $digits = \App\Support\PhoneNumber::digitsForWhatsApp($this->sendRecipient, $this->sendCountry);

            return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->sendMessage);
        }

        $subject = rawurlencode($this->sendEmailSubject);

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
        $this->paymentProofFile = null;
        $this->paymentExistingProofPath = null;
        $this->removePaymentProof = false;
        $this->resetValidation();
        $this->showPaymentModal = true;
    }

    public function openPaymentDetails(string $paymentId): void
    {
        abort_unless($this->company, 403);

        $exists = Payment::query()
            ->whereKey($paymentId)
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
            ->exists();

        if (! $exists) {
            $this->dispatch('toast', type: 'error', title: __('Paiement introuvable.'));

            return;
        }

        $this->paymentDetailsId = $paymentId;
    }

    public function closePaymentDetails(): void
    {
        $this->paymentDetailsId = null;
    }

    #[Computed]
    public function selectedPaymentDetails(): ?Payment
    {
        if (! $this->paymentDetailsId || ! $this->company) {
            return null;
        }

        return Payment::query()
            ->whereKey($this->paymentDetailsId)
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
            ->first();
    }

    public function editFromDetails(): void
    {
        $id = $this->paymentDetailsId;
        $this->paymentDetailsId = null;
        if ($id) {
            $this->openEditPaymentModal($id);
        }
    }

    public function deleteFromDetails(): void
    {
        $id = $this->paymentDetailsId;
        $this->paymentDetailsId = null;
        if ($id) {
            $this->requestDeletePayment($id);
        }
    }

    public function openEditPaymentModal(string $paymentId): void
    {
        abort_unless($this->company, 403);

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
            ->first();

        if (! $payment) {
            $this->dispatch('toast', type: 'error', title: __('Paiement introuvable.'));

            return;
        }

        $this->editingPaymentId = $payment->id;
        $this->paymentAmount = (string) $payment->amount;
        $this->paymentPaidAt = $payment->paid_at->format('Y-m-d');
        $this->paymentMethod = $payment->method->value;
        $this->paymentReference = $payment->reference ?? '';
        $this->paymentNotes = $payment->notes ?? '';
        $this->paymentProofFile = null;
        $this->paymentExistingProofPath = $payment->proof_file_path;
        $this->removePaymentProof = false;
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

        $isEdit = $this->editingPaymentId !== null;

        if (! $isEdit && ! $this->invoice->canReceivePayment()) {
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
            'paymentProofFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'paymentAmount.required' => __('Le montant est requis.'),
            'paymentAmount.min' => __('Le montant doit être supérieur à 0.'),
            'paymentPaidAt.required' => __('La date de paiement est requise.'),
            'paymentProofFile.mimes' => __('La preuve doit être un PDF ou une image (JPG/PNG).'),
            'paymentProofFile.max' => __('Le fichier ne doit pas dépasser 5 Mo.'),
        ]);

        // Conserve la date choisie par l'utilisateur mais ajoute l'heure actuelle,
        // pour que la timeline reste correctement ordonnée contre les autres événements du jour.
        $paidAt = \Carbon\Carbon::parse($validated['paymentPaidAt'])->setTimeFrom(now());

        // Résolution du chemin de la preuve : nouveau fichier > suppression > existant > null.
        $proofPath = $this->resolvePaymentProofPath();

        if ($isEdit) {
            $payment = Payment::query()
                ->whereKey($this->editingPaymentId)
                ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
                ->firstOrFail();

            $payment = app(PaymentService::class)->update($payment, [
                'amount' => (int) $validated['paymentAmount'],
                'paid_at' => $paidAt,
                'method' => $validated['paymentMethod'],
                'reference' => $validated['paymentReference'] ?: null,
                'notes' => $validated['paymentNotes'] ?: null,
                'proof_file_path' => $proofPath,
            ]);
            $toastTitle = __('Paiement mis à jour.');
        } else {
            $payment = app(PaymentService::class)->record($this->invoice, [
                'amount' => (int) $validated['paymentAmount'],
                'paid_at' => $paidAt,
                'method' => $validated['paymentMethod'],
                'reference' => $validated['paymentReference'] ?: null,
                'notes' => $validated['paymentNotes'] ?: null,
                'proof_file_path' => $proofPath,
                'recorded_by' => auth()->id(),
            ]);
            $toastTitle = __('Paiement enregistré.');
        }

        $this->invoice = $this->invoice->fresh(['client', 'lines', 'reminders', 'payments']);
        $this->showPaymentModal = false;
        $this->editingPaymentId = null;
        $this->paymentProofFile = null;
        $this->paymentExistingProofPath = null;
        $this->removePaymentProof = false;
        unset($this->statusDisplay, $this->remainingAmount, $this->timelineEvents, $this->lifecycleState);

        if (! $isEdit && $this->company) {
            $notifier = app(\App\Services\PME\WhatsAppNotificationService::class);
            if ((int) $this->invoice->amount_paid >= (int) $this->invoice->total) {
                $notifier->sendInvoicePaidFull($this->invoice, $payment, $this->company);
            } else {
                $notifier->sendInvoicePartiallyPaid($this->invoice, $payment, $this->company);
            }
        }

        $this->dispatch('toast', type: 'success', title: $toastTitle);
    }

    /**
     * Stocke la nouvelle preuve si présente, et résout le chemin final selon
     * l'état du formulaire (nouveau fichier / suppression / conservation).
     */
    private function resolvePaymentProofPath(): ?string
    {
        if ($this->paymentProofFile) {
            $ext = strtolower($this->paymentProofFile->getClientOriginalExtension() ?: 'bin');
            $filename = 'proof-'.now()->format('YmdHis').'.'.$ext;
            $path = "pme/invoices/{$this->invoice->id}/payments/{$filename}";
            Storage::put($path, file_get_contents($this->paymentProofFile->getRealPath()));

            return $path;
        }

        if ($this->removePaymentProof) {
            return null;
        }

        return $this->paymentExistingProofPath;
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

    public function downloadPaymentProof(string $paymentId)
    {
        abort_unless($this->company, 403);

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $this->company->id))
            ->firstOrFail();

        abort_unless($payment->proof_file_path && Storage::exists($payment->proof_file_path), 404);

        $ext = pathinfo($payment->proof_file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $name = 'preuve-paiement-'.$payment->id.'.'.$ext;

        return Storage::download($payment->proof_file_path, $name);
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
        $copy = app(InvoiceService::class)->duplicate($this->invoice, $this->company);

        $this->redirect(route('pme.invoices.edit', $copy), navigate: true);
    }

    // ─── Annulation ──────────────────────────────────────────────────────────

    public function openCancelModal(): void
    {
        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->resetErrorBag();
    }

    public function confirmCancel(): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'min:3']]);

        try {
            app(InvoiceService::class)->markAsCancelled($this->invoice, $this->cancelReason);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->invoice->refresh();
        $this->showCancelModal = false;
        unset($this->statusDisplay, $this->remainingAmount, $this->dueLabel, $this->lifecycleState);
        $this->dispatch('toast', type: 'success', title: __('La facture a été annulée.'));
    }

    // ─── Suppression de brouillon (Libérer / Vacant) ─────────────────────────

    public function openDeleteDraftModal(): void
    {
        if ($this->invoice->status !== InvoiceStatus::Draft) {
            return;
        }
        $this->deleteDraftStrategy = 'release';
        $this->showDeleteDraftModal = true;
    }

    public function closeDeleteDraftModal(): void
    {
        $this->showDeleteDraftModal = false;
    }

    public function confirmDeleteDraft(): void
    {
        abort_unless($this->company, 403);

        try {
            app(InvoiceService::class)->deleteDraft($this->invoice, $this->deleteDraftStrategy);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        session()->flash('success', __('La facture brouillon a été supprimée.'));
        $this->redirect(route('pme.invoices.index'));
    }

    // ─── Archivage ───────────────────────────────────────────────────────────

    public function archive(): void
    {
        try {
            app(InvoiceService::class)->archive($this->invoice);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        session()->flash('success', __('La facture a été archivée.'));
        $this->redirect(route('pme.invoices.index'));
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

            {{-- Paiements enregistrés (acomptes + paiements manuels dans un seul bloc, badge "Type" pour distinguer) --}}
            @php
                $allPayments = $inv->payments->sortByDesc('paid_at');
                $depositPayments = $inv->payments->where('is_deposit', true);
            @endphp
            @if ($inv->status !== InvoiceStatus::Draft || $depositPayments->isNotEmpty())
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Paiements enregistrés') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Cumulé') }} : {{ format_money($inv->amount_paid, $inv->currency) }} / {{ format_money($inv->total, $inv->currency) }}
                        @if ($depositPayments->isNotEmpty())
                            · {{ __('dont acompte :') }} {{ format_money((int) $depositPayments->sum('amount'), $inv->currency) }}
                        @endif
                    </p>
                </div>

                @if ($allPayments->isNotEmpty())
                    {{-- Mobile: cartes empilées, chaque carte ouvre les détails --}}
                    <div class="mt-5 space-y-3 sm:hidden">
                        @foreach ($allPayments as $payment)
                            <button
                                type="button"
                                wire:key="payment-card-{{ $payment->id }}"
                                wire:click="openPaymentDetails('{{ $payment->id }}')"
                                class="block w-full rounded-xl border border-slate-100 bg-slate-50/60 p-4 text-left transition hover:border-primary/30 hover:bg-slate-50"
                            >
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-xs uppercase tracking-wide text-slate-500">{{ format_date($payment->paid_at) }}</span>
                                    <span class="font-semibold text-ink tabular-nums whitespace-nowrap">{{ format_money($payment->amount, $inv->currency) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ __($payment->method->label()) }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                    @if ($payment->is_deposit)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Acompte') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ __('Paiement') }}</span>
                                    @endif
                                    @if ($payment->reference)
                                        <span>{{ __('Réf.') }} : {{ $payment->reference }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Desktop: tableau, chaque ligne ouvre les détails --}}
                    <div class="mt-5 hidden overflow-x-auto sm:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-left">
                                    <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Date') }}</th>
                                    <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Méthode') }}</th>
                                    <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                                    <th class="pb-2 px-4 text-sm font-semibold text-slate-500">{{ __('Type') }}</th>
                                    <th class="pb-2 pl-4 text-right text-sm font-semibold text-slate-500">{{ __('Montant') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($allPayments as $payment)
                                    <tr
                                        wire:key="payment-{{ $payment->id }}"
                                        wire:click="openPaymentDetails('{{ $payment->id }}')"
                                        class="cursor-pointer transition hover:bg-slate-50"
                                    >
                                        <td class="py-3 pr-4 text-slate-600 whitespace-nowrap">{{ format_date($payment->paid_at) }}</td>
                                        <td class="py-3 px-4 text-slate-600">{{ __($payment->method->label()) }}</td>
                                        <td class="py-3 px-4 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                        <td class="py-3 px-4">
                                            @if ($payment->is_deposit)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Acompte') }}</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ __('Paiement') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pl-4 text-right font-semibold text-ink whitespace-nowrap">
                                            {{ format_money($payment->amount, $inv->currency) }}
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

            {{-- Actions : principale + menu --}}
            @php
                $isOverdue = $inv->status === InvoiceStatus::Overdue
                    || (in_array($inv->status, [InvoiceStatus::Sent, InvoiceStatus::Certified, InvoiceStatus::CertificationFailed], true)
                        && $inv->due_at && $inv->due_at->isPast()
                        && (int) $inv->amount_paid < (int) $inv->total);
                $isPartial = $inv->status === InvoiceStatus::PartiallyPaid;
                $isSent = in_array($inv->status, [InvoiceStatus::Sent, InvoiceStatus::Certified, InvoiceStatus::CertificationFailed], true);
                $isPaid = $inv->status === InvoiceStatus::Paid;
                $isDraft = $inv->status === InvoiceStatus::Draft;
                $isCancelled = $inv->status === InvoiceStatus::Cancelled;
                $canEdit = $isDraft || $isSent;
            @endphp
            <article class="app-shell-panel p-6 lg:sticky lg:top-6" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions') }}</h3>

                <div class="mt-4 space-y-2">
                    {{-- Action principale par statut --}}
                    @if ($isDraft)
                        <button type="button" wire:click="openSendModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-send">
                            <flux:icon name="paper-airplane" class="size-4" /> {{ __('Envoyer la facture') }}
                        </button>
                    @elseif ($isOverdue)
                        <button type="button" wire:click="openReminderPreview" @disabled(! $inv->canReceiveReminder()) class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong disabled:opacity-50" data-action="primary-remind">
                            <flux:icon name="bell-alert" class="size-4" /> {{ __('Relancer') }}
                        </button>
                    @elseif (($isSent || $isPartial) && $remaining > 0)
                        <button type="button" wire:click="openPaymentModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-record-payment">
                            <flux:icon name="banknotes" class="size-4" /> {{ __('Enregistrer un paiement') }}
                        </button>
                    @elseif ($isPaid)
                        <a href="{{ route('pme.invoices.pdf', $inv) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-download-receipt">
                            <flux:icon name="arrow-down-tray" class="size-4" /> {{ __('Télécharger le reçu') }}
                        </a>
                    @endif

                    {{-- Menu Actions --}}
                    <div class="relative">
                        <button type="button" @click="menuOpen = !menuOpen" class="relative flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary" data-action="menu-toggle">
                            <flux:icon name="ellipsis-horizontal" class="size-4" />
                            <span>{{ __('Autres actions') }}</span>
                            <flux:icon name="chevron-down" class="absolute right-4 size-4" />
                        </button>
                        <div x-show="menuOpen" x-cloak x-transition class="absolute right-0 left-0 z-30 mt-2 origin-top overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            <a href="{{ route('pme.invoices.pdf', $inv) }}" target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <flux:icon name="eye" class="size-4 text-slate-400" /> {{ __('Aperçu PDF') }}
                            </a>
                            <a href="{{ route('pme.invoices.pdf', $inv) }}" download target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <flux:icon name="arrow-down-tray" class="size-4 text-slate-400" /> {{ __('Télécharger PDF') }}
                            </a>

                            @if ($canEdit)
                                <a href="{{ route('pme.invoices.edit', $inv) }}" wire:navigate @click="menuOpen = false" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="edit">
                                    <flux:icon name="pencil-square" class="size-4 text-slate-400" /> {{ __('Modifier') }}
                                </a>
                            @endif

                            @if ($isSent && ! $isOverdue)
                                <button type="button" wire:click="openSendModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="resend">
                                    <flux:icon name="paper-airplane" class="size-4 text-slate-400" /> {{ __('Renvoyer') }}
                                </button>
                                <button type="button" wire:click="openReminderPreview" @disabled(! $inv->canReceiveReminder()) @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50" data-action="remind">
                                    <flux:icon name="bell-alert" class="size-4 text-slate-400" /> {{ __('Relancer manuellement') }}
                                </button>
                            @endif

                            @if (! $isDraft && ! $isCancelled)
                                <button type="button" wire:click="duplicateInvoice" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="duplicate">
                                    <flux:icon name="document-duplicate" class="size-4 text-slate-400" /> {{ __('Dupliquer') }}
                                </button>
                            @endif

                            @if (! $isDraft && ! $isCancelled && ! $isPaid)
                                <button type="button" wire:click="openCancelModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50" data-action="cancel">
                                    <flux:icon name="no-symbol" class="size-4" /> {{ __('Annuler') }}
                                </button>
                            @endif

                            @if ($isCancelled)
                                <button type="button" wire:click="archive" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="archive">
                                    <flux:icon name="archive-box" class="size-4 text-slate-400" /> {{ __('Archiver') }}
                                </button>
                            @endif

                            @if ($isDraft)
                                <button type="button" wire:click="openDeleteDraftModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50" data-action="delete-draft">
                                    <flux:icon name="trash" class="size-4" /> {{ __('Supprimer') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($inv->client_id)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <a href="{{ route('pme.clients.show', $inv->client_id) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="user" class="size-4" /> {{ __('Voir le client') }}
                        </a>
                    </div>
                @endif
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
                            <h2 class="text-lg font-semibold text-ink">{{ $editingPaymentId ? __('Modifier le paiement') : __('Enregistrer un paiement') }}</h2>
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
                        {{-- Montant — formatteur identique à la saisie des lignes de facture --}}
                        <div
                            x-data="{
                                raw: {{ (int) (is_numeric($paymentAmount) ? $paymentAmount : 0) }},
                                formatted: '',
                                get noDecimals() { return $wire.currencyJs.decimals === 0; },
                                get maxRaw() { return $wire.currencyJs.maxAmount; },
                                formatNoDecimal(v) {
                                    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, $wire.currencyJs.thousandsSep);
                                },
                                clamp(v) { return Math.min(Math.max(v, 0), this.maxRaw); },
                                onInput(e) {
                                    if (this.noDecimals) {
                                        this.raw = this.clamp(parseInt(e.target.value.replace(/\D/g, '')) || 0);
                                        this.formatted = this.formatNoDecimal(this.raw);
                                        e.target.value = this.formatted;
                                    } else {
                                        let v = e.target.value.replace(/[^\d.]/g, '');
                                        this.raw = this.clamp(Math.round(parseFloat(v || '0') * Math.pow(10, $wire.currencyJs.decimals)));
                                    }
                                    $wire.set('paymentAmount', this.raw.toString());
                                },
                                init() {
                                    if (this.noDecimals) {
                                        this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                    } else {
                                        this.formatted = this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '';
                                    }
                                    this.$watch('$wire.paymentAmount', (val) => {
                                        let n = parseInt(val) || 0;
                                        if (n !== this.raw) {
                                            this.raw = this.clamp(n);
                                            this.formatted = this.noDecimals
                                                ? (this.raw > 0 ? this.formatNoDecimal(this.raw) : '')
                                                : (this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '');
                                        }
                                    });
                                }
                            }"
                        >
                            <label for="payment-amount" class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Montant') }} (<span x-text="$wire.currencyJs.label"></span>) <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="payment-amount"
                                type="text"
                                :inputmode="noDecimals ? 'numeric' : 'decimal'"
                                :value="formatted"
                                @input="onInput($event)"
                                placeholder="0"
                                required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                            @error('paymentAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Date — flatpickr, comme sur la page nouvelle facture --}}
                        <div>
                            <label for="payment-date" class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Date') }} <span class="text-rose-500">*</span>
                            </label>
                            <div
                                wire:ignore
                                x-data="{
                                    picker: null,
                                    init() {
                                        this.picker = flatpickr(this.$refs.input, {
                                            dateFormat: 'Y-m-d',
                                            altInput: true,
                                            altFormat: 'd/m/Y',
                                            defaultDate: $wire.paymentPaidAt,
                                            onChange: (dates, dateStr) => $wire.set('paymentPaidAt', dateStr),
                                        });
                                        this.$watch('$wire.paymentPaidAt', (val) => {
                                            if (this.picker && val) this.picker.setDate(val, false);
                                        });
                                    },
                                    destroy() { if (this.picker) this.picker.destroy(); }
                                }"
                            >
                                <input id="payment-date" x-ref="input" type="text" readonly required
                                       class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            </div>
                            @error('paymentPaidAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Méthode') }}</label>
                            <x-select-native>
                                <select wire:model="paymentMethod" class="col-start-1 row-start-1 appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-base text-slate-700 focus:border-primary/50 focus:outline-none">
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
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                            <textarea
                                wire:model="paymentNotes"
                                rows="3"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                            ></textarea>
                        </div>

                        {{-- Preuve de paiement (PDF/JPG/PNG, optionnelle) --}}
                        <div class="md:col-span-2">
                            <p class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Preuve de paiement') }}
                                <span class="font-normal text-slate-400">{{ __('(optionnel · PDF ou image, 5 Mo max)') }}</span>
                            </p>

                            @if ($paymentExistingProofPath && ! $paymentProofFile && ! $removePaymentProof)
                                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-base">
                                    <span class="inline-flex items-center gap-2 text-slate-700">
                                        <flux:icon name="document" class="size-4 text-slate-400" />
                                        {{ basename($paymentExistingProofPath) }}
                                    </span>
                                    <button type="button" wire:click="$set('removePaymentProof', true)" class="text-sm font-semibold text-rose-600 hover:text-rose-500">{{ __('Supprimer') }}</button>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez un nouveau fichier ci-dessous pour le remplacer.') }}</p>
                            @elseif ($removePaymentProof)
                                <div class="flex items-center justify-between gap-3 rounded-2xl border border-rose-100 bg-rose-50/60 px-4 py-3 text-base text-rose-700">
                                    <span>{{ __('Fichier marqué pour suppression à l\'enregistrement.') }}</span>
                                    <button type="button" wire:click="$set('removePaymentProof', false)" class="text-sm font-semibold text-rose-700 hover:text-rose-600 underline">{{ __('Annuler') }}</button>
                                </div>
                            @endif

                            <label for="payment-proof-file" class="mt-1 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/80 px-4 py-5 text-center transition hover:border-primary/60">
                                @if ($paymentProofFile)
                                    <flux:icon name="document-check" class="size-6 text-emerald-600" />
                                    <div class="text-base font-medium text-slate-700">{{ $paymentProofFile->getClientOriginalName() }}</div>
                                    <button type="button" wire:click="$set('paymentProofFile', null)" class="text-sm font-semibold text-rose-600 underline hover:text-rose-500">{{ __('Retirer') }}</button>
                                @else
                                    <flux:icon name="cloud-arrow-up" class="size-6 text-slate-400" />
                                    <div class="text-base text-slate-600">
                                        {{ __('Glisser un fichier ici, ou') }}
                                        <span class="font-semibold text-primary underline">{{ __('parcourir') }}</span>
                                    </div>
                                    <div class="text-sm text-slate-500">{{ __('PDF, JPG, PNG — 5 Mo max') }}</div>
                                @endif
                                <input id="payment-proof-file" wire:model="paymentProofFile" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" />
                            </label>
                            @error('paymentProofFile') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div wire:loading wire:target="paymentProofFile" class="mt-1 inline-flex items-center gap-2 text-sm text-slate-500">
                                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                {{ __('Téléversement…') }}
                            </div>
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
                            {{ $editingPaymentId ? __('Mettre à jour') : __('Enregistrer') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modale "Détails du paiement" : ouverte au clic sur une ligne de la liste --}}
    @if ($this->selectedPaymentDetails)
        @php $pd = $this->selectedPaymentDetails; @endphp
        <div class="relative z-50" role="dialog" aria-modal="true" x-data @keydown.escape.window="$wire.closePaymentDetails()">
            <div class="fixed inset-0 bg-slate-500/75" aria-hidden="true"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                    <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white text-left shadow-xl">
                        <button type="button" wire:click="closePaymentDetails" class="absolute top-4 right-4 z-10 rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Fermer') }}">
                            <flux:icon name="x-mark" class="size-5" />
                        </button>

                        {{-- Header --}}
                        <div class="flex items-start gap-4 px-6 pt-5 pb-5">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                                <flux:icon name="banknotes" class="size-7 text-emerald-700" />
                            </div>
                            <div class="flex-1 pr-12">
                                <h3 class="text-xl font-semibold text-slate-900">{{ __('Détails du paiement') }}</h3>
                                <p class="mt-1 text-base text-slate-500">
                                    {{ format_money($pd->amount, $inv->currency) }} · {{ format_date($pd->paid_at) }}
                                </p>
                            </div>
                        </div>

                        {{-- Body --}}
                        <div class="border-t border-slate-100 px-6 py-5">
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Type') }}</dt>
                                    <dd class="mt-1">
                                        @if ($pd->is_deposit)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Acompte') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ __('Paiement') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Méthode') }}</dt>
                                    <dd class="mt-1 text-base text-slate-800">{{ __($pd->method->label()) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Montant') }}</dt>
                                    <dd class="mt-1 text-base font-semibold text-ink tabular-nums">{{ format_money($pd->amount, $inv->currency) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Date') }}</dt>
                                    <dd class="mt-1 text-base text-slate-800">{{ format_date($pd->paid_at) }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Référence') }}</dt>
                                    <dd class="mt-1 text-base text-slate-800">{{ $pd->reference ?: '—' }}</dd>
                                </div>
                                @if ($pd->notes)
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Notes') }}</dt>
                                        <dd class="mt-1 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-base text-slate-700">{{ $pd->notes }}</dd>
                                    </div>
                                @endif
                                @if ($pd->proof_file_path)
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Preuve de paiement') }}</dt>
                                        <dd class="mt-1">
                                            <button type="button" wire:click="downloadPaymentProof('{{ $pd->id }}')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                                                <flux:icon name="document" class="size-4 text-slate-400" />
                                                {{ basename($pd->proof_file_path) }}
                                                <flux:icon name="arrow-down-tray" class="size-4 text-slate-400" />
                                            </button>
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        {{-- Footer --}}
                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-6 py-3">
                            <button type="button" wire:click="deleteFromDetails" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 ring-1 ring-inset ring-rose-200 hover:bg-rose-50">
                                <span class="inline-flex items-center gap-2"><flux:icon name="trash" class="size-4" /> {{ __('Supprimer') }}</span>
                            </button>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="closePaymentDetails" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">{{ __('Fermer') }}</button>
                                <button type="button" wire:click="editFromDetails" class="rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-strong">
                                    <span class="inline-flex items-center gap-2"><flux:icon name="pencil-square" class="size-4" /> {{ __('Modifier') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
        :send-email-subject="$this->sendEmailSubject"
    />

    <x-ui.cancel-with-reason-modal
        :show="$showCancelModal"
        :title="__('Annuler la facture')"
        :description="__('L\'annulation est définitive. La facture conserve sa référence mais sort des encours.')"
    />

    <x-invoicing.delete-draft-modal
        :show="$showDeleteDraftModal"
        :reference="$inv->reference ?? ''"
    />

</div>

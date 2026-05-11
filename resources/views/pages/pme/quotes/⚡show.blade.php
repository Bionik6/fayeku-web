<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\ProposalDocument;
use App\Services\PME\DocumentLifecycleService;
use App\Services\PME\ProposalDocumentService;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Devis')] #[Layout('layouts::pme')] class extends Component {
    public ProposalDocument $quote;

    public ?Company $company = null;

    public ?string $confirmConvert = null;

    public ?string $confirmDelete = null;

    // Modal "Annuler le devis"
    public bool $showCancelModal = false;

    public string $cancelReason = '';

    // Modal "Prolonger la validité"
    public bool $showExtendValidityModal = false;

    public ?string $newValidUntil = null;

    // Modal "Envoyer le devis"
    public bool $showSendModal = false;

    /** 'whatsapp' | 'email' */
    public string $sendChannel = 'whatsapp';

    public string $sendRecipient = '';

    public string $sendMessage = '';

    /** Code pays ISO-2 pour le composant phone-input (WhatsApp). */
    public string $sendCountry = 'SN';

    /** @var array<string, string> Liste des pays disponibles pour le composant phone-input. */
    public array $sendPhoneCountries = [];

    public function mount(ProposalDocument $quote): void
    {
        $this->company = auth()->user()->smeCompany();

        abort_unless(
            $this->company && $quote->company_id === $this->company->id && $quote->isQuote(),
            404
        );

        $quote->load(['client', 'lines', 'invoice']);

        $this->quote = $quote;

        // Liste des pays pour le sélecteur du composant phone-input.
        $this->sendPhoneCountries = collect(config('fayeku.phone_countries', []))
            ->map(fn ($c) => $c['label'])
            ->all();

        // Auto-ouvre la modale d'envoi quand on arrive depuis le formulaire
        // de création/édition (`?send=1`) — flow "Créer et envoyer le devis".
        if (request()->boolean('send')) {
            $this->openSendModal();
        }
    }

    #[Computed]
    public function statusDisplay(): array
    {
        $isExpired = $this->quote->status === ProposalDocumentStatus::Expired
            || ($this->quote->valid_until && $this->quote->valid_until->isPast() && $this->quote->status === ProposalDocumentStatus::Sent);

        return match (true) {
            $this->quote->invoice !== null => ['label' => 'Facturé', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
            $isExpired => ['label' => 'Expiré', 'class' => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/20'],
            $this->quote->status === ProposalDocumentStatus::Accepted => ['label' => 'Accepté', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
            $this->quote->status === ProposalDocumentStatus::Sent => ['label' => 'Envoyé', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'],
            $this->quote->status === ProposalDocumentStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
            $this->quote->status === ProposalDocumentStatus::Declined => ['label' => 'Refusé', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'],
            $this->quote->status === ProposalDocumentStatus::Cancelled => ['label' => 'Annulé', 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
            default => ['label' => ucfirst($this->quote->status->value), 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
        };
    }

    #[Computed]
    public function validityLabel(): ?string
    {
        if (! $this->quote->valid_until) {
            return null;
        }

        if ($this->quote->status === ProposalDocumentStatus::Accepted || $this->quote->invoice) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->quote->valid_until->copy()->startOfDay(), false);

        if ($days < 0) {
            $abs = abs($days);

            return $abs > 1
                ? __('Expiré depuis :days jours', ['days' => $abs])
                : __('Expiré depuis :days jour', ['days' => $abs]);
        }

        if ($days === 0) {
            return __("Expire aujourd'hui");
        }

        return $days > 1
            ? __('Dans :days jours', ['days' => $days])
            : __('Dans :days jour', ['days' => $days]);
    }

    #[Computed]
    public function isEditable(): bool
    {
        return in_array($this->quote->status, ProposalDocumentStatus::editable(), true);
    }

    #[Computed]
    public function lifecycleState(): array
    {
        return app(DocumentLifecycleService::class)->forQuote($this->quote);
    }

    public function markAsAccepted(): void
    {
        if ($this->quote->status !== ProposalDocumentStatus::Sent) {
            return;
        }
        app(ProposalDocumentService::class)->markAsAccepted($this->quote);
        $this->quote->refresh();
        unset($this->statusDisplay, $this->validityLabel, $this->isEditable, $this->lifecycleState);
        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme accepté.'));
    }

    public function markAsDeclined(): void
    {
        if ($this->quote->status !== ProposalDocumentStatus::Sent) {
            return;
        }
        app(ProposalDocumentService::class)->markAsDeclined($this->quote);
        $this->quote->refresh();
        unset($this->statusDisplay, $this->validityLabel, $this->isEditable, $this->lifecycleState);
        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme refusé.'));
    }

    public function requestConvert(): void
    {
        $this->confirmConvert = $this->quote->id;
    }

    public function cancelConvert(): void
    {
        $this->confirmConvert = null;
    }

    public function convertToInvoice(?string $id = null): void
    {
        abort_unless($this->company, 403);
        $this->confirmConvert = null;

        try {
            $invoice = app(ProposalDocumentService::class)->convertToInvoice($this->quote, $this->company);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->redirect(route('pme.invoices.edit', $invoice), navigate: true);
    }

    public function requestDelete(): void
    {
        $this->confirmDelete = $this->quote->id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = null;
    }

    public function deleteQuote(?string $id = null): void
    {
        $this->confirmDelete = null;
        if ($this->quote->status !== ProposalDocumentStatus::Draft) {
            return;
        }
        $this->quote->delete();
        session()->flash('success', __('Le devis a été supprimé.'));
        $this->redirect(route('pme.quotes.index'), navigate: true);
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
        $this->validate(['cancelReason' => ['required', 'string', 'min:3']], [
            'cancelReason.required' => __('Le motif est requis.'),
            'cancelReason.min' => __('Le motif doit faire au moins 3 caractères.'),
        ]);

        try {
            app(ProposalDocumentService::class)->markAsCancelled($this->quote, $this->cancelReason);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->quote->refresh();
        $this->showCancelModal = false;
        unset($this->statusDisplay, $this->validityLabel, $this->isEditable, $this->lifecycleState);
        $this->dispatch('toast', type: 'success', title: __('Le devis a été annulé.'));
    }

    // ─── Prolongation de validité ────────────────────────────────────────────

    public function openExtendValidityModal(): void
    {
        $this->newValidUntil = ($this->quote->valid_until ?? now())
            ->copy()->addDays(30)->toDateString();
        $this->resetErrorBag();
        $this->showExtendValidityModal = true;
    }

    public function closeExtendValidityModal(): void
    {
        $this->showExtendValidityModal = false;
        $this->resetErrorBag();
    }

    public function confirmExtendValidity(): void
    {
        $this->validate(['newValidUntil' => ['required', 'date', 'after:today']], [
            'newValidUntil.required' => __('Renseignez une date.'),
            'newValidUntil.after' => __('La date doit être dans le futur.'),
        ]);

        try {
            app(ProposalDocumentService::class)->extendValidity($this->quote, Carbon::parse($this->newValidUntil));
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->quote->refresh();
        $this->showExtendValidityModal = false;
        unset($this->statusDisplay, $this->validityLabel, $this->isEditable, $this->lifecycleState);
        $this->dispatch('toast', type: 'success', title: __('Validité prolongée.'));
    }

    // ─── Duplication & Archivage ─────────────────────────────────────────────

    public function duplicate(): void
    {
        abort_unless($this->company, 403);

        $copy = app(ProposalDocumentService::class)->duplicate($this->quote, $this->company);

        $this->redirect(route('pme.quotes.edit', $copy), navigate: true);
    }

    public function archive(): void
    {
        try {
            app(ProposalDocumentService::class)->archive($this->quote);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        session()->flash('success', __('Le devis a été archivé.'));
        $this->redirect(route('pme.quotes.index'), navigate: true);
    }

    // ─── Envoi (WhatsApp / Email) ────────────────────────────────────────────

    public function openSendModal(): void
    {
        $this->resetErrorBag();
        $client = $this->quote->client;

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
        $client = $this->quote->client;

        if ($this->sendChannel === 'email') {
            $this->sendRecipient = $client?->email ?? '';
        } else {
            $clientPhone = $client?->phone ?? '';
            // PhoneNumber::parse couvre toute l'Afrique de l'Ouest (config `phone_countries`),
            // contrairement à AuthService qui se limite à SN/CI.
            if (filled($clientPhone)) {
                $parsed = PhoneNumber::parse($clientPhone);
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
        $link = route('pme.quotes.pdf', $this->quote->public_code);
        $total = format_money($this->quote->total, $this->quote->currency);
        $validUntil = $this->quote->valid_until ? format_date($this->quote->valid_until) : '—';
        $reference = $this->quote->reference;
        $signature = $this->buildSignature();

        return <<<MSG
            Bonjour,

            Suite à votre demande, veuillez trouver notre devis n° {$reference}, d'un montant de {$total}.

            Consulter le devis :
            {$link}

            Ce devis est valable jusqu'au {$validUntil}. Nous restons disponibles pour toute question ou modification.

            {$signature}
            MSG;
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
            $digits = PhoneNumber::digitsForWhatsApp($this->sendRecipient, $this->sendCountry);

            return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->sendMessage);
        }

        $subject = rawurlencode((string) __('Devis :ref', ['ref' => $this->quote->reference]));

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
        // de statut Draft → Sent. C'est l'intention claire de l'utilisateur :
        // il a validé que le message part, on bascule la fiche en conséquence.
        $statusChanged = false;
        if ($this->quote->status === ProposalDocumentStatus::Draft) {
            app(ProposalDocumentService::class)->markAsSent($this->quote);
            $this->quote->refresh();
            unset($this->statusDisplay, $this->validityLabel, $this->isEditable, $this->lifecycleState);
            $statusChanged = true;
        }

        // L'ouverture du canal externe (WhatsApp / mailto) se fait côté client
        // dans le click-handler de la modale (cf. send-modal.blade.php) pour ne
        // pas déclencher le popup-blocker.
        $this->showSendModal = false;

        if ($statusChanged) {
            $this->dispatch('toast', type: 'success', title: __('Devis marqué comme envoyé.'));
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @php
        $q = $this->quote;
        $status = $this->statusDisplay;
    @endphp

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('pme.quotes.index') }}" wire:navigate class="text-sm font-semibold text-slate-500 transition hover:text-primary">
                    {{ __('← Retour aux devis & proformas') }}
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ $q->reference ?? '—' }}</h2>
                    <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $status['class'] }}">
                        {{ __($status['label']) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    @if ($q->client)
                        <a href="{{ route('pme.clients.show', $q->client_id) }}" wire:navigate class="font-medium text-ink hover:text-primary">{{ $q->client->name }}</a>
                        ·
                    @endif
                    {{ __('Émis le') }} {{ $q->issued_at ? format_date($q->issued_at) : '—' }}
                    @if ($q->valid_until)
                        · {{ __('valide jusqu\'au') }} {{ format_date($q->valid_until) }}
                        @if ($this->validityLabel)
                            <span class="ml-1 font-medium text-slate-500">({{ $this->validityLabel }})</span>
                        @endif
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Type') }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ __('Devis') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Proposition commerciale envoyée au client') }}</p>
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Montant TTC') }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ format_money($q->total, $q->currency) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('HT :ht · TVA :tva', ['ht' => format_money($q->subtotal, $q->currency), 'tva' => format_money($q->tax_amount, $q->currency)]) }}</p>
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Validité') }}</p>
            @if ($q->valid_until)
                <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ format_date($q->valid_until) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $this->validityLabel ?? '—' }}</p>
            @else
                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-400">—</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Aucune date de validité') }}</p>
            @endif
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Conversion') }}</p>
            @if ($q->invoice)
                <p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-600">{{ __('Facturé') }}</p>
                <a href="{{ route('pme.invoices.show', $q->invoice) }}" wire:navigate class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-strong">
                    {{ $q->invoice->reference }} <flux:icon name="arrow-right" class="size-3.5" />
                </a>
            @elseif (in_array($q->status, [ProposalDocumentStatus::Sent, ProposalDocumentStatus::Accepted], true))
                <p class="mt-2 text-2xl font-semibold tracking-tight text-amber-500">{{ __('À facturer') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Pas encore convertie en facture') }}</p>
            @else
                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-400">—</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Conversion non disponible') }}</p>
            @endif
        </article>
    </section>

    <x-documents.lifecycle-card :lifecycle="$this->lifecycleState" />

    {{-- Corps 2 colonnes : Aperçu en premier (full width sur mobile, 2/3 sur lg+),
         sidebar Client+Actions en 2e (full width sur mobile, 1/3 sur lg+).
         Le breakpoint est lg pour activer la grille dès la tablette. --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Colonne gauche : Aperçu / Activity --}}
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Aperçu --}}
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Aperçu du devis') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Les informations envoyées au client.') }}</p>
                </div>
                <div class="mt-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left">
                                <th class="pb-2 pr-4 text-sm font-semibold text-slate-500">{{ __('Description') }}</th>
                                <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Qté') }}</th>
                                <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('PU HT') }}</th>
                                <th class="pb-2 px-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('TVA') }}</th>
                                <th class="pb-2 pl-4 text-right text-sm font-semibold text-slate-500 whitespace-nowrap">{{ __('Total HT') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($q->lines as $line)
                                <tr>
                                    <td class="py-3 pr-4 text-ink">{!! nl2br(e($line->description)) !!}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ $line->quantity }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ format_money($line->unit_price, $q->currency) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                                    <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">{{ format_money($line->total, $q->currency) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t border-slate-200">
                            <tr>
                                <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">{{ format_money($q->subtotal, $q->currency) }}</td>
                            </tr>
                            @if ($q->discount > 0)
                                @php $discountAmount = ($q->discount_type ?? 'percent') === 'fixed' ? $q->discount : (int) round($q->subtotal * $q->discount / 100); @endphp
                                <tr>
                                    <td colspan="4" class="pt-1 pr-4 text-right text-sm text-emerald-600">{{ ($q->discount_type ?? 'percent') === 'fixed' ? __('Remise') : __('Remise (:rate%)', ['rate' => $q->discount]) }}</td>
                                    <td class="pt-1 pl-4 text-right tabular-nums text-sm text-emerald-600 whitespace-nowrap">− {{ format_money($discountAmount, $q->currency) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">{{ format_money($q->tax_amount, $q->currency) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink whitespace-nowrap">{{ format_money($q->total, $q->currency) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($q->notes)
                    <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Notes') }}</p>
                        {{ $q->notes }}
                    </div>
                @endif
            </article>

            {{-- Activité (jalons clés) --}}
            <article class="app-shell-panel p-6">
                <h3 class="text-lg font-semibold text-ink">{{ __('Activité') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Les jalons clés de ce devis.') }}</p>
                <div class="mt-5">
                    <x-proposals.activity-feed :document="$q" />
                </div>
            </article>
        </div>

        {{-- Colonne droite : client + actions. Full width sur mobile, col 3 sur lg+ --}}
        <div class="flex w-full flex-col gap-6">
            {{-- Carte client --}}
            <x-client-card :client="$q->client" no-client-message="Aucun client renseigné sur ce devis." />

            {{-- Actions rapides : action principale + menu Actions --}}
            @php
                $isExpired = ! $q->invoice
                    && ($q->status === ProposalDocumentStatus::Expired
                        || ($q->status === ProposalDocumentStatus::Sent && $q->valid_until && $q->valid_until->isPast()));
            @endphp
            <article class="app-shell-panel p-6 lg:sticky lg:top-6" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions') }}</h3>

                <div class="mt-4 space-y-2">
                    {{-- Action principale par statut --}}
                    @if ($q->invoice)
                        <a href="{{ route('pme.invoices.show', $q->invoice) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="view-invoice">
                            <flux:icon name="arrow-right" class="size-4" /> {{ __('Voir la facture liée') }}
                        </a>
                    @elseif ($q->status === ProposalDocumentStatus::Draft)
                        <button type="button" wire:click="openSendModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-send">
                            <flux:icon name="paper-airplane" class="size-4" /> {{ __('Envoyer le devis') }}
                        </button>
                    @elseif ($isExpired)
                        <button type="button" wire:click="openExtendValidityModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-extend">
                            <flux:icon name="calendar-days" class="size-4" /> {{ __('Prolonger la validité') }}
                        </button>
                    @elseif ($q->status === ProposalDocumentStatus::Sent)
                        <button type="button" wire:click="markAsAccepted" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-accept">
                            <flux:icon name="check-circle" class="size-4" /> {{ __('Marquer comme accepté') }}
                        </button>
                        <button type="button" wire:click="markAsDeclined" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary" data-action="primary-decline">
                            <flux:icon name="x-circle" class="size-4" /> {{ __('Marquer comme refusé') }}
                        </button>
                    @elseif ($q->status === ProposalDocumentStatus::Accepted)
                        <button type="button" wire:click="requestConvert" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong" data-action="primary-convert">
                            <flux:icon name="document-arrow-up" class="size-4" /> {{ __('Convertir en facture') }}
                        </button>
                    @endif

                    {{-- Menu Actions --}}
                    <div class="relative">
                        <button type="button" @click="menuOpen = !menuOpen" class="relative flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary" data-action="menu-toggle">
                            <flux:icon name="ellipsis-horizontal" class="size-4" />
                            <span>{{ __('Autres actions') }}</span>
                            <flux:icon name="chevron-down" class="absolute right-4 size-4" />
                        </button>
                        <div x-show="menuOpen" x-cloak x-transition class="absolute right-0 left-0 z-30 mt-2 origin-top overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            {{-- Toujours présents --}}
                            <a href="{{ route('pme.quotes.pdf', $q) }}" target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <flux:icon name="eye" class="size-4 text-slate-400" /> {{ __('Aperçu PDF') }}
                            </a>
                            <a href="{{ route('pme.quotes.pdf', $q) }}" download target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <flux:icon name="arrow-down-tray" class="size-4 text-slate-400" /> {{ __('Télécharger PDF') }}
                            </a>

                            {{-- Brouillon : Modifier --}}
                            @if ($this->isEditable)
                                <a href="{{ route('pme.quotes.edit', $q) }}" wire:navigate class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="edit">
                                    <flux:icon name="pencil-square" class="size-4 text-slate-400" /> {{ __('Modifier') }}
                                </a>
                            @endif

                            {{-- Envoyé : Renvoyer + Prolonger + Annuler --}}
                            @if ($q->status === ProposalDocumentStatus::Sent && ! $isExpired)
                                <button type="button" wire:click="openSendModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="resend">
                                    <flux:icon name="paper-airplane" class="size-4 text-slate-400" /> {{ __('Renvoyer') }}
                                </button>
                                <button type="button" wire:click="openExtendValidityModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="extend">
                                    <flux:icon name="calendar-days" class="size-4 text-slate-400" /> {{ __('Prolonger la validité') }}
                                </button>
                            @endif

                            {{-- Dupliquer (presque tous les statuts) --}}
                            @if ($q->status !== ProposalDocumentStatus::Cancelled || $q->isArchived())
                                <button type="button" wire:click="duplicate" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="duplicate">
                                    <flux:icon name="document-duplicate" class="size-4 text-slate-400" /> {{ __('Dupliquer') }}
                                </button>
                            @else
                                <button type="button" wire:click="duplicate" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="duplicate">
                                    <flux:icon name="document-duplicate" class="size-4 text-slate-400" /> {{ __('Dupliquer') }}
                                </button>
                            @endif

                            {{-- Annuler (Envoyé uniquement, hors expiré) --}}
                            @if ($q->status === ProposalDocumentStatus::Sent && ! $isExpired)
                                <button type="button" wire:click="openCancelModal" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50" data-action="cancel">
                                    <flux:icon name="no-symbol" class="size-4" /> {{ __('Annuler') }}
                                </button>
                            @endif

                            {{-- Archiver (terminal non-actif) --}}
                            @if (in_array($q->status, [ProposalDocumentStatus::Declined, ProposalDocumentStatus::Cancelled], true) || $isExpired)
                                <button type="button" wire:click="archive" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-action="archive">
                                    <flux:icon name="archive-box" class="size-4 text-slate-400" /> {{ __('Archiver') }}
                                </button>
                            @endif

                            {{-- Supprimer (Draft uniquement) --}}
                            @if ($q->status === ProposalDocumentStatus::Draft)
                                <button type="button" wire:click="requestDelete" @click="menuOpen = false" class="flex w-full items-center gap-3 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50" data-action="delete">
                                    <flux:icon name="trash" class="size-4" /> {{ __('Supprimer') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($q->client_id)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <a href="{{ route('pme.clients.show', $q->client_id) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="user" class="size-4" /> {{ __('Voir le client') }}
                        </a>
                    </div>
                @endif
            </article>
        </div>
    </section>

    {{-- Confirmation : convertir en facture --}}
    <x-ui.confirm-modal
        :confirm-id="$confirmConvert"
        :title="__('Convertir en facture')"
        :description="__('Ce devis sera converti en facture brouillon. Vous pourrez la modifier avant de l\'envoyer.')"
        confirm-action="convertToInvoice"
        cancel-action="cancelConvert"
        :confirm-label="__('Convertir')"
        variant="primary"
    />

    {{-- Confirmation : suppression --}}
    <x-ui.confirm-modal
        :confirm-id="$confirmDelete"
        :title="__('Supprimer le devis')"
        :description="__('Cette action est irréversible. Le devis sera définitivement supprimé.')"
        confirm-action="deleteQuote"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

    <x-invoicing.send-modal
        :title="__('Envoyer le devis')"
        :show-send-modal="$showSendModal"
        :send-channel="$sendChannel"
        :send-recipient="$sendRecipient"
        :send-country="$sendCountry"
        :send-phone-countries="$sendPhoneCountries"
        :send-open-url="$this->sendOpenUrl"
    />

    <x-ui.cancel-with-reason-modal
        :show="$showCancelModal"
        :title="__('Annuler le devis')"
    />

    <x-proposals.extend-validity-modal
        :show="$showExtendValidityModal"
        :min-date="now()->addDay()->toDateString()"
    />
</div>

<?php

use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Models\PME\Proforma;
use App\Services\PME\ProformaService;
use App\Support\PhoneNumber;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Proforma')] #[Layout('layouts::pme')] class extends Component {
    public Proforma $proforma;

    public ?Company $company = null;

    public ?string $confirmConvert = null;

    public ?string $confirmDelete = null;

    // Modal "Enregistrer un bon de commande"
    public bool $showPoModal = false;

    public string $poReference = '';

    public string $poReceivedAt = '';

    public string $poNotes = '';

    // Modal "Envoyer la proforma"
    public bool $showSendModal = false;

    /** 'whatsapp' | 'email' */
    public string $sendChannel = 'whatsapp';

    public string $sendRecipient = '';

    public string $sendMessage = '';

    /** Code pays ISO-2 pour le composant phone-input (WhatsApp). */
    public string $sendCountry = 'SN';

    /** @var array<string, string> Liste des pays disponibles pour le composant phone-input. */
    public array $sendPhoneCountries = [];

    public function mount(Proforma $proforma): void
    {
        $this->company = auth()->user()->smeCompany();

        abort_unless(
            $this->company && $proforma->company_id === $this->company->id,
            404
        );

        $proforma->load(['client', 'lines', 'invoice']);

        $this->proforma = $proforma;

        // Liste des pays pour le sélecteur du composant phone-input.
        $this->sendPhoneCountries = collect(config('fayeku.phone_countries', []))
            ->map(fn ($c) => $c['label'])
            ->all();
    }

    #[Computed]
    public function statusDisplay(): array
    {
        $isExpired = $this->proforma->status === ProformaStatus::Expired
            || ($this->proforma->valid_until && $this->proforma->valid_until->isPast() && $this->proforma->status === ProformaStatus::Sent);

        return match (true) {
            $isExpired => ['label' => 'Expirée', 'class' => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/20'],
            $this->proforma->status === ProformaStatus::PoReceived => ['label' => 'BC reçu', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
            $this->proforma->status === ProformaStatus::Converted => ['label' => 'Facturée', 'class' => 'bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-600/20'],
            $this->proforma->status === ProformaStatus::Sent => ['label' => 'Envoyée', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'],
            $this->proforma->status === ProformaStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
            $this->proforma->status === ProformaStatus::Declined => ['label' => 'Refusée', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'],
            default => ['label' => ucfirst($this->proforma->status->value), 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
        };
    }

    #[Computed]
    public function validityLabel(): ?string
    {
        if (! $this->proforma->valid_until) {
            return null;
        }

        if (in_array($this->proforma->status, [ProformaStatus::Converted, ProformaStatus::PoReceived], true) || $this->proforma->invoice) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->proforma->valid_until->copy()->startOfDay(), false);

        if ($days < 0) {
            return __('Expirée depuis :days jour(s)', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __("Expire aujourd'hui");
        }

        return __('Dans :days jour(s)', ['days' => $days]);
    }

    #[Computed]
    public function isEditable(): bool
    {
        return in_array($this->proforma->status, [ProformaStatus::Draft, ProformaStatus::Sent], true);
    }

    // ─── Bon de commande ─────────────────────────────────────────────────────

    public function openPoModal(): void
    {
        if ($this->proforma->status !== ProformaStatus::Sent) {
            return;
        }
        $this->resetErrorBag();
        $this->poReference = $this->proforma->po_reference ?? '';
        $this->poReceivedAt = $this->proforma->po_received_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->poNotes = $this->proforma->po_notes ?? '';
        $this->showPoModal = true;
    }

    public function closePoModal(): void
    {
        $this->showPoModal = false;
        $this->resetErrorBag();
    }

    public function recordPurchaseOrder(): void
    {
        $this->validate([
            'poReference' => ['required', 'string', 'max:100'],
            'poReceivedAt' => ['required', 'date'],
            'poNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'poReference.required' => __('La référence du bon de commande est requise.'),
            'poReceivedAt.required' => __('La date du bon de commande est requise.'),
        ]);

        if ($this->proforma->status !== ProformaStatus::Sent) {
            $this->showPoModal = false;

            return;
        }

        app(ProformaService::class)->markAsPoReceived($this->proforma, [
            'reference' => trim($this->poReference),
            'received_at' => $this->poReceivedAt,
            'notes' => trim($this->poNotes),
        ]);
        $this->proforma->refresh();
        $this->showPoModal = false;
        $this->dispatch('toast', type: 'success', title: __('Bon de commande enregistré. La proforma peut être convertie en facture.'));
    }

    /**
     * Conserve l'action rapide depuis la liste unifiée (clic dans le dropdown
     * sans passer par la modale BC). Statut seul, sans détails.
     */
    public function markAsPoReceived(): void
    {
        if ($this->proforma->status !== ProformaStatus::Sent) {
            return;
        }
        app(ProformaService::class)->markAsPoReceived($this->proforma);
        $this->proforma->refresh();
        $this->dispatch('toast', type: 'success', title: __('Bon de commande reçu : la proforma peut être convertie en facture.'));
    }

    // ─── Envoi (WhatsApp / Email) ────────────────────────────────────────────

    public function openSendModal(): void
    {
        if ($this->proforma->status === ProformaStatus::Converted) {
            return;
        }

        $this->resetErrorBag();
        $client = $this->proforma->client;

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
        $client = $this->proforma->client;

        if ($this->sendChannel === 'email') {
            $this->sendRecipient = $client?->email ?? '';
        } else {
            $clientPhone = $client?->phone ?? '';
            // Détection du pays + extraction du numéro local depuis le format international.
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
        $link = route('pme.proformas.pdf', $this->proforma->public_code);
        $total = format_money($this->proforma->total, $this->proforma->currency);
        $validUntil = $this->proforma->valid_until ? format_date($this->proforma->valid_until) : '—';
        $signature = $this->buildSignature();

        return <<<MSG
            Bonjour,

            Conformément à notre échange, veuillez trouver ci-joint la facture proforma n° {$this->proforma->reference} d'un montant de {$total} TTC, valable jusqu'au {$validUntil}.

            Document à consulter ici :
            {$link}

            Ce document vous permettra de monter votre engagement budgétaire. Je reste disponible pour toute précision.

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
            // Garantit le préfixe pays correct quelle que soit la saisie (local ou international).
            $digits = PhoneNumber::digitsForWhatsApp($this->sendRecipient, $this->sendCountry);

            return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->sendMessage);
        }

        $subject = rawurlencode((string) __('Facture Proforma :ref', ['ref' => $this->proforma->reference]));

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
        // Draft → Sent : l'utilisateur a validé l'envoi, on bascule la fiche.
        $statusChanged = false;
        if ($this->proforma->status === ProformaStatus::Draft) {
            app(ProformaService::class)->markAsSent($this->proforma);
            $this->proforma->refresh();
            $statusChanged = true;
        }

        $url = $this->sendOpenUrl;
        $this->showSendModal = false;
        $this->dispatch('open-external-url', url: $url);

        if ($statusChanged) {
            $this->dispatch('toast', type: 'success', title: __('Proforma marquée comme envoyée.'));
        }
    }

    public function markAsDeclined(): void
    {
        if ($this->proforma->status !== ProformaStatus::Sent) {
            return;
        }
        app(ProformaService::class)->markAsDeclined($this->proforma);
        $this->proforma->refresh();
        $this->dispatch('toast', type: 'success', title: __('La proforma a été marquée comme refusée.'));
    }

    public function requestConvert(): void
    {
        $this->confirmConvert = $this->proforma->id;
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
            $invoice = app(ProformaService::class)->convertToInvoice($this->proforma, $this->company);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->redirect(route('pme.invoices.edit', $invoice), navigate: true);
    }

    public function requestDelete(): void
    {
        $this->confirmDelete = $this->proforma->id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = null;
    }

    public function deleteProforma(?string $id = null): void
    {
        $this->confirmDelete = null;
        if ($this->proforma->status !== ProformaStatus::Draft) {
            return;
        }
        $this->proforma->delete();
        session()->flash('success', __('La proforma a été supprimée.'));
        $this->redirect(route('pme.quotes.index'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6"
     x-data
     x-on:open-external-url.window="window.open($event.detail.url, '_blank')">
    @php
        $p = $this->proforma;
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
                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20">{{ __('Proforma') }}</span>
                    <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ $p->reference ?? '—' }}</h2>
                    <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $status['class'] }}">
                        {{ __($status['label']) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    @if ($p->client)
                        <a href="{{ route('pme.clients.show', $p->client_id) }}" wire:navigate class="font-medium text-ink hover:text-primary">{{ $p->client->name }}</a>
                        ·
                    @endif
                    {{ __('Émise le') }} {{ $p->issued_at ? format_date($p->issued_at) : '—' }}
                    @if ($p->valid_until)
                        · {{ __('valide jusqu\'au') }} {{ format_date($p->valid_until) }}
                        @if ($this->validityLabel)
                            <span class="ml-1 font-medium text-slate-500">({{ $this->validityLabel }})</span>
                        @endif
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Montant TTC') }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ format_money($p->total, $p->currency) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('HT :ht · TVA :tva', ['ht' => format_money($p->subtotal, $p->currency), 'tva' => format_money($p->tax_amount, $p->currency)]) }}</p>
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Validité') }}</p>
            @if ($p->valid_until)
                <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ format_date($p->valid_until) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $this->validityLabel ?? '—' }}</p>
            @else
                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-400">—</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Aucune date de validité') }}</p>
            @endif
        </article>

        <article class="app-shell-stat-card">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">{{ __('Bon de commande') }}</p>
            @if ($p->invoice)
                <p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-600">{{ __('Facturée') }}</p>
                <a href="{{ route('pme.invoices.show', $p->invoice) }}" wire:navigate class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-strong">
                    {{ $p->invoice->reference }} <flux:icon name="arrow-right" class="size-3.5" />
                </a>
            @elseif ($p->status === ProformaStatus::PoReceived)
                @if ($p->po_reference)
                    <p class="mt-2 truncate text-2xl font-semibold tracking-tight text-emerald-600">{{ $p->po_reference }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Reçu le') }} {{ $p->po_received_at ? format_date($p->po_received_at) : '—' }}
                    </p>
                @else
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-600">{{ __('BC reçu') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Prête à convertir en facture') }}</p>
                @endif
            @elseif ($p->status === ProformaStatus::Sent)
                <p class="mt-2 text-2xl font-semibold tracking-tight text-amber-500">{{ __('En attente') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Pas encore de bon de commande') }}</p>
            @else
                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-400">—</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Non applicable') }}</p>
            @endif
        </article>
    </section>

    {{-- Corps 2 colonnes : Aperçu en premier (full width sur mobile, 2/3 sur lg+),
         sidebar Client+Actions en 2e (full width sur mobile, 1/3 sur lg+). --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Colonne gauche : Conditions / Aperçu / Activity --}}
        <div class="flex flex-col gap-6 lg:col-span-2">

            {{-- Conditions (uniquement si renseignées) --}}
            @if ($p->dossier_reference || $p->payment_terms || $p->delivery_terms)
                <article class="app-shell-panel p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Conditions') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Informations attendues par les acheteurs publics et grands comptes.') }}</p>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @if ($p->dossier_reference)
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Référence dossier') }}</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $p->dossier_reference }}</p>
                            </div>
                        @endif
                        @if ($p->payment_terms)
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Conditions de paiement') }}</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $p->payment_terms }}</p>
                            </div>
                        @endif
                        @if ($p->delivery_terms)
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Délai d\'exécution') }}</p>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $p->delivery_terms }}</p>
                            </div>
                        @endif
                    </div>
                </article>
            @endif

            {{-- Aperçu --}}
            <article class="app-shell-panel p-6">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Aperçu de la proforma') }}</h3>
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
                            @forelse ($p->lines as $line)
                                <tr>
                                    <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ $line->quantity }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">{{ format_money($line->unit_price, $p->currency) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                                    <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">{{ format_money($line->total, $p->currency) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t border-slate-200">
                            <tr>
                                <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">{{ format_money($p->subtotal, $p->currency) }}</td>
                            </tr>
                            @if ($p->discount > 0)
                                @php $discountAmount = ($p->discount_type ?? 'percent') === 'fixed' ? $p->discount : (int) round($p->subtotal * $p->discount / 100); @endphp
                                <tr>
                                    <td colspan="4" class="pt-1 pr-4 text-right text-sm text-emerald-600">{{ ($p->discount_type ?? 'percent') === 'fixed' ? __('Remise') : __('Remise (:rate%)', ['rate' => $p->discount]) }}</td>
                                    <td class="pt-1 pl-4 text-right tabular-nums text-sm text-emerald-600 whitespace-nowrap">− {{ format_money($discountAmount, $p->currency) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">{{ format_money($p->tax_amount, $p->currency) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink whitespace-nowrap">{{ format_money($p->total, $p->currency) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($p->notes)
                    <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Notes') }}</p>
                        {{ $p->notes }}
                    </div>
                @endif
            </article>

            {{-- Activité --}}
            <article class="app-shell-panel p-6">
                <h3 class="text-lg font-semibold text-ink">{{ __('Activité') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Les jalons clés de cette proforma.') }}</p>
                <ol class="mt-5 space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <flux:icon name="document-plus" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">{{ __('Proforma créée') }}</p>
                            <p class="text-sm text-slate-500">{{ format_date($p->created_at) }}</p>
                        </div>
                    </li>
                    @if ($p->valid_until)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                                <flux:icon name="calendar" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('Date de validité') }}</p>
                                <p class="text-sm text-slate-500">{{ format_date($p->valid_until) }} @if ($this->validityLabel) · {{ $this->validityLabel }} @endif</p>
                            </div>
                        </li>
                    @endif
                    @if ($p->status === ProformaStatus::PoReceived)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <flux:icon name="check-circle" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('Bon de commande reçu') }}</p>
                                <p class="text-sm text-slate-500">{{ format_date($p->updated_at) }}</p>
                            </div>
                        </li>
                    @endif
                    @if ($p->status === ProformaStatus::Declined)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                                <flux:icon name="x-circle" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('Proforma refusée') }}</p>
                                <p class="text-sm text-slate-500">{{ format_date($p->updated_at) }}</p>
                            </div>
                        </li>
                    @endif
                    @if ($p->invoice)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-teal-50 text-teal-600">
                                <flux:icon name="document-arrow-up" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ __('Convertie en facture') }}</p>
                                <a href="{{ route('pme.invoices.show', $p->invoice) }}" wire:navigate class="text-sm font-medium text-primary hover:text-primary-strong">{{ $p->invoice->reference }}</a>
                            </div>
                        </li>
                    @endif
                </ol>
            </article>
        </div>

        {{-- Colonne droite : client + actions. Full width sur mobile, col 3 sur lg+ --}}
        <div class="flex w-full flex-col gap-6">
            {{-- Carte client --}}
            <article class="app-shell-panel p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Client') }}</h3>
                    @if ($p->client)
                        <a href="{{ route('pme.clients.show', $p->client_id) }}" wire:navigate class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-strong">
                            {{ __('Voir la fiche') }} <flux:icon name="arrow-right" class="size-4" />
                        </a>
                    @endif
                </div>
                @if ($p->client)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                        <p class="font-semibold text-ink">{{ $p->client->name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-slate-700">
                            @if ($p->client->email) <span class="break-all">{{ $p->client->email }}</span> @endif
                            @if ($p->client->email && $p->client->phone) <span class="text-slate-500">⋅</span> @endif
                            @if ($p->client->phone) <span>{{ format_phone($p->client->phone) }}</span> @endif
                        </div>
                        @if ($p->client->address || $p->client->tax_id)
                            <div class="mt-3 border-t border-slate-200/70 pt-3 text-sm text-slate-600">
                                @if ($p->client->address)
                                    <p class="flex items-start gap-1.5">
                                        <flux:icon name="map-pin" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                                        <span>{{ $p->client->address }}</span>
                                    </p>
                                @endif
                                @if ($p->client->tax_id)
                                    <p class="mt-1 font-mono text-sm text-slate-500">{{ __('NINEA') }} : {{ $p->client->tax_id }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-700">{{ __('Aucun client renseigné sur cette proforma.') }}</div>
                @endif
            </article>

            {{-- Actions rapides --}}
            <article class="app-shell-panel p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions rapides') }}</h3>

                <div class="mt-4 space-y-2">
                    @if ($p->status === ProformaStatus::PoReceived && ! $p->invoice)
                        <button type="button" wire:click="requestConvert" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                            <flux:icon name="document-arrow-up" class="size-4" /> {{ __('Convertir en facture') }}
                        </button>
                    @endif

                    @if ($p->status === ProformaStatus::Sent)
                        <button type="button" wire:click="openPoModal" class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            <flux:icon name="document-check" class="size-4" /> {{ __('Enregistrer un bon de commande') }}
                        </button>
                        <button type="button" wire:click="markAsDeclined" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="x-circle" class="size-4" /> {{ __('Marquer comme refusée') }}
                        </button>
                    @endif

                    @if ($p->status !== ProformaStatus::Converted)
                        <button type="button" wire:click="openSendModal" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="paper-airplane" class="size-4" /> {{ __('Envoyer la proforma') }}
                        </button>
                    @endif

                    @if ($this->isEditable)
                        <a href="{{ route('pme.proformas.edit', $p) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="pencil-square" class="size-4" /> {{ __('Modifier la proforma') }}
                        </a>
                    @endif

                    <a href="{{ route('pme.proformas.pdf', $p) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="arrow-down-tray" class="size-4" /> {{ __('Télécharger le PDF') }}
                    </a>
                </div>

                <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                    @if ($p->client_id)
                        <a href="{{ route('pme.clients.show', $p->client_id) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="user" class="size-4" /> {{ __('Voir le client') }}
                        </a>
                    @endif

                    @if ($p->status === ProformaStatus::Draft)
                        <button type="button" wire:click="requestDelete" class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50">
                            <flux:icon name="trash" class="size-4" /> {{ __('Supprimer la proforma') }}
                        </button>
                    @endif
                </div>
            </article>
        </div>
    </section>

    <x-ui.confirm-modal
        :confirm-id="$confirmConvert"
        :title="__('Convertir en facture')"
        :description="__('Cette proforma sera convertie en facture brouillon. Vous pourrez la modifier avant de l\'envoyer.')"
        confirm-action="convertToInvoice"
        cancel-action="cancelConvert"
        :confirm-label="__('Convertir')"
        variant="primary"
    />

    <x-ui.confirm-modal
        :confirm-id="$confirmDelete"
        :title="__('Supprimer la proforma')"
        :description="__('Cette action est irréversible. La proforma sera définitivement supprimée.')"
        confirm-action="deleteProforma"
        cancel-action="cancelDelete"
        :confirm-label="__('Supprimer')"
    />

    {{-- Modal : Enregistrer un bon de commande --}}
    @if ($showPoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="closePoModal" x-data
             @keydown.escape.window="$wire.closePoModal()">
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="recordPurchaseOrder">
                    <div class="flex items-start justify-between border-b border-slate-100 px-7 py-5">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ __('Enregistrer un bon de commande') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Renseignez la référence et la date du BC émis par le client. La proforma passera au statut « BC reçu ».') }}</p>
                        </div>
                        <button type="button" wire:click="closePoModal" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>

                    <div class="grid gap-4 px-7 py-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Référence du BC') }} <span class="text-rose-500">*</span></label>
                            <input wire:model="poReference" type="text" placeholder="{{ __('Ex : BC-2026/0142') }}"
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            @error('poReference') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Date du BC') }} <span class="text-rose-500">*</span></label>
                            <input wire:model="poReceivedAt" type="date"
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            @error('poReceivedAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Notes (optionnel)') }}</label>
                            <textarea wire:model="poNotes" rows="3" placeholder="{{ __('Détails internes sur ce bon de commande…') }}"
                                      class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                            @error('poNotes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                        <button type="button" wire:click="closePoModal" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30">{{ __('Annuler') }}</button>
                        <button type="submit" class="rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">{{ __('Enregistrer le BC') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal : Envoyer la proforma --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="closeSendModal" x-data
             @keydown.escape.window="$wire.closeSendModal()">
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-7 py-5">
                    <div>
                        <h2 class="text-lg font-semibold text-ink">{{ __('Envoyer la proforma') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez le canal. Le lien public du PDF est inclus dans le message — vous l\'envoyez depuis votre propre WhatsApp ou messagerie.') }}</p>
                    </div>
                    <button type="button" wire:click="closeSendModal" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="px-7 py-6">
                    <div class="mb-5 flex gap-2">
                        <button type="button" wire:click="$set('sendChannel', 'whatsapp')" wire:loading.attr="disabled"
                                class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $sendChannel === 'whatsapp' ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                            <flux:icon name="chat-bubble-left-right" class="mr-1 inline size-4" /> {{ __('WhatsApp') }}
                        </button>
                        <button type="button" wire:click="$set('sendChannel', 'email')" wire:loading.attr="disabled"
                                class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $sendChannel === 'email' ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                            <flux:icon name="envelope" class="mr-1 inline size-4" /> {{ __('Email') }}
                        </button>
                    </div>

                    <div class="space-y-4">
                        @if ($sendChannel === 'whatsapp')
                            <div wire:key="send-phone-{{ $sendChannel }}">
                                <x-phone-input
                                    :label="__('Téléphone du client (WhatsApp)')"
                                    country-name="sendCountry"
                                    :country-value="$sendCountry"
                                    country-model="sendCountry"
                                    phone-name="sendRecipient"
                                    :phone-value="$sendRecipient"
                                    phone-model="sendRecipient"
                                    :countries="$sendPhoneCountries"
                                    container-class="flex items-stretch rounded-2xl border border-slate-200 bg-slate-50/80 transition has-[:focus]:border-primary/40 has-[:focus]:ring-2 has-[:focus]:ring-primary/10"
                                    text-size="text-sm"
                                    placeholder-class="placeholder:text-slate-500"
                                    required
                                />
                                @error('sendRecipient') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div wire:key="send-email-{{ $sendChannel }}">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                    {{ __('Adresse email du client') }} <span class="text-rose-500">*</span>
                                </label>
                                <input wire:model.live.debounce.300ms="sendRecipient" type="email"
                                       placeholder="contact@client.sn"
                                       class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                @error('sendRecipient') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <label class="block text-sm font-medium text-slate-700">{{ __('Message') }}</label>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        x-on:click="navigator.clipboard.writeText($wire.sendMessage).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                    <template x-if="!copied">
                                        <span class="inline-flex items-center gap-1.5">
                                            <flux:icon name="document-duplicate" class="size-3.5" />
                                            {{ __('Copier le message') }}
                                        </span>
                                    </template>
                                    <template x-if="copied">
                                        <span class="inline-flex items-center gap-1.5 text-emerald-600">
                                            <flux:icon name="check" class="size-3.5" />
                                            {{ __('Copié') }}
                                        </span>
                                    </template>
                                </button>
                            </div>
                            <textarea wire:model.live.debounce.300ms="sendMessage" rows="10"
                                      class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 font-mono text-[15px] leading-relaxed text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                            @error('sendMessage') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                            <flux:icon name="information-circle" class="mr-1 inline size-3.5" />
                            {{ __('Le PDF ne peut pas être joint via WhatsApp Web ou mailto. Le lien public dans le message reste accessible 24/24 — votre client pourra le télécharger en cliquant.') }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                    <button type="button" wire:click="closeSendModal" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30">{{ __('Annuler') }}</button>
                    <button type="button" wire:click="confirmSend"
                            class="rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                        @if ($sendChannel === 'whatsapp')
                            {{ __('Envoyer depuis WhatsApp') }}
                        @else
                            {{ __('Envoyer depuis ma messagerie') }}
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

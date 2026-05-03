<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Models\Auth\Company;
use App\Models\PME\ProposalDocument;
use App\Services\PME\ProposalDocumentService;
use App\Support\PhoneNumber;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Devis')] #[Layout('layouts::pme')] class extends Component {
    public ProposalDocument $quote;

    public ?Company $company = null;

    public ?string $confirmConvert = null;

    public ?string $confirmDelete = null;

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
    }

    #[Computed]
    public function statusDisplay(): array
    {
        $isExpired = $this->quote->status === ProposalDocumentStatus::Expired
            || ($this->quote->valid_until && $this->quote->valid_until->isPast() && $this->quote->status === ProposalDocumentStatus::Sent);

        return match (true) {
            $isExpired => ['label' => 'Expiré', 'class' => 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-500/20'],
            $this->quote->status === ProposalDocumentStatus::Accepted => ['label' => 'Accepté', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
            $this->quote->status === ProposalDocumentStatus::Sent => ['label' => 'Envoyé', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'],
            $this->quote->status === ProposalDocumentStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/20'],
            $this->quote->status === ProposalDocumentStatus::Declined => ['label' => 'Refusé', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'],
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
            return __('Expiré depuis :days jour(s)', ['days' => abs($days)]);
        }

        if ($days === 0) {
            return __("Expire aujourd'hui");
        }

        return __('Dans :days jour(s)', ['days' => $days]);
    }

    #[Computed]
    public function isEditable(): bool
    {
        return in_array($this->quote->status, ProposalDocumentStatus::editable(), true);
    }

    public function markAsAccepted(): void
    {
        if ($this->quote->status !== ProposalDocumentStatus::Sent) {
            return;
        }
        app(ProposalDocumentService::class)->markAsAccepted($this->quote);
        $this->quote->refresh();
        $this->dispatch('toast', type: 'success', title: __('Le devis a été marqué comme accepté.'));
    }

    public function markAsDeclined(): void
    {
        if ($this->quote->status !== ProposalDocumentStatus::Sent) {
            return;
        }
        app(ProposalDocumentService::class)->markAsDeclined($this->quote);
        $this->quote->refresh();
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
        $signature = $this->buildSignature();

        return <<<MSG
            Bonjour,

            Suite à votre demande, je vous transmets le devis n° {$this->quote->reference} d'un montant de {$total} TTC, valable jusqu'au {$validUntil}.

            Vous pouvez consulter le détail en cliquant ici :
            {$link}

            N'hésitez pas à revenir vers moi pour toute question ou ajustement.

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
            unset($this->statusDisplay);
            $statusChanged = true;
        }

        $url = $this->sendOpenUrl;
        $this->showSendModal = false;
        $this->dispatch('open-external-url', url: $url);

        if ($statusChanged) {
            $this->dispatch('toast', type: 'success', title: __('Devis marqué comme envoyé.'));
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6"
     x-data
     x-on:open-external-url.window="window.open($event.detail.url, '_blank')">
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
                                    <td class="py-3 pr-4 text-ink">{{ $line->description }}</td>
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

            {{-- Actions rapides --}}
            <article class="app-shell-panel p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Actions rapides') }}</h3>

                <div class="mt-4 space-y-2">
                    @if ($q->status === ProposalDocumentStatus::Sent && ! $q->invoice)
                        <button type="button" wire:click="requestConvert" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                            <flux:icon name="document-arrow-up" class="size-4" /> {{ __('Convertir en facture') }}
                        </button>
                    @endif

                    @if ($q->status === ProposalDocumentStatus::Accepted && ! $q->invoice)
                        <button type="button" wire:click="requestConvert" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                            <flux:icon name="document-arrow-up" class="size-4" /> {{ __('Convertir en facture') }}
                        </button>
                    @endif

                    @if ($this->isEditable)
                        <a href="{{ route('pme.quotes.edit', $q) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="pencil-square" class="size-4" /> {{ __('Modifier le devis') }}
                        </a>
                    @endif

                    @if ($q->status === ProposalDocumentStatus::Sent)
                        <button type="button" wire:click="markAsAccepted" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="check-circle" class="size-4" /> {{ __('Marquer comme accepté') }}
                        </button>
                        <button type="button" wire:click="markAsDeclined" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="x-circle" class="size-4" /> {{ __('Marquer comme refusé') }}
                        </button>
                    @endif

                    <button type="button" wire:click="openSendModal" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="paper-airplane" class="size-4" /> {{ __('Envoyer le devis') }}
                    </button>

                    <a href="{{ route('pme.quotes.pdf', $q) }}" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        <flux:icon name="arrow-down-tray" class="size-4" /> {{ __('Télécharger le PDF') }}
                    </a>
                </div>

                <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                    @if ($q->client_id)
                        <a href="{{ route('pme.clients.show', $q->client_id) }}" wire:navigate class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                            <flux:icon name="user" class="size-4" /> {{ __('Voir le client') }}
                        </a>
                    @endif

                    @if ($q->status === ProposalDocumentStatus::Draft)
                        <button type="button" wire:click="requestDelete" class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50">
                            <flux:icon name="trash" class="size-4" /> {{ __('Supprimer le devis') }}
                        </button>
                    @endif
                </div>
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

    {{-- Modal : Envoyer le devis --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="closeSendModal" x-data
             @keydown.escape.window="$wire.closeSendModal()">
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-7 py-5">
                    <div>
                        <h2 class="text-lg font-semibold text-ink">{{ __('Envoyer le devis') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez le canal. Le lien public du PDF est inclus dans le message — vous l\'envoyez depuis votre propre WhatsApp ou messagerie.') }}</p>
                    </div>
                    <button type="button" wire:click="closeSendModal" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="px-7 py-6">
                    <div class="mb-5 flex gap-2">
                        <button type="button" wire:click="$set('sendChannel', 'whatsapp')"
                                class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $sendChannel === 'whatsapp' ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                            <flux:icon name="chat-bubble-left-right" class="mr-1 inline size-4" /> {{ __('WhatsApp') }}
                        </button>
                        <button type="button" wire:click="$set('sendChannel', 'email')"
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

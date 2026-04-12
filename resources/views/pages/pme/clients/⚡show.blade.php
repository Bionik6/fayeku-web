<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Clients\Services\ClientService;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Services\ReminderService;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\Quote;
use Modules\PME\Invoicing\Services\QuoteService;

new #[Title('Client')] #[Layout('layouts::pme')] class extends Component {
    public Client $client;

    public ?Company $company = null;

    public bool $showEditClientModal = false;

    public string $clientName = '';

    public string $clientPhone = '';

    public string $clientPhoneCountry = 'SN';

    /** @var array<string, string> */
    public array $clientPhoneCountries = [];

    public string $clientEmail = '';

    public string $clientTaxId = '';

    public string $clientAddress = '';

    public ?string $selectedInvoiceId = null;

    public ?string $selectedQuoteId = null;

    public ?string $previewInvoiceId = null;

    public ?string $timelineInvoiceId = null;

    public string $previewTone = 'cordial';

    public bool $previewAttachPdf = true;

    public string $previewChannel = 'whatsapp';

    public string $invoiceStatusFilter = 'all';

    #[Url(as: 'focus')]
    public string $focus = '';

    /** @var array<string, mixed>|null */
    private ?array $detailCache = null;

    public function mount(Client $client): void
    {
        $this->company = app(ClientService::class)->companyForUser(auth()->user());

        abort_unless(
            $this->company
            && $client->company_id === $this->company->id
            && auth()->user()->can('view', $client),
            403
        );

        $this->client = $client;
        $this->clientPhoneCountries = collect(config('fayeku.phone_countries'))
            ->map(fn ($c) => $c['label'])
            ->all();
    }

    public function openEditClientModal(): void
    {
        abort_unless(auth()->user()->can('update', $this->client), 403);

        $this->resetValidation();
        $this->fillClientForm();
        $this->showEditClientModal = true;
    }

    public function saveClientUpdates(): void
    {
        abort_unless(auth()->user()->can('update', $this->client), 403);

        $validated = $this->validate([
            'clientName' => ['required', 'string', 'max:255'],
            'clientPhone' => ['required', 'string', 'max:30'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
            'clientTaxId' => ['nullable', 'string', 'max:100'],
            'clientAddress' => ['nullable', 'string', 'max:500'],
        ], [
            'clientName.required' => __('Le nom du client est requis.'),
            'clientPhone.required' => __('Le numéro de téléphone est requis.'),
            'clientEmail.email' => __('L’adresse email doit être valide.'),
        ]);

        $this->client->update([
            'name' => trim($validated['clientName']),
            'phone' => $this->normalizePhone($validated['clientPhone']),
            'email' => $this->emptyToNull($validated['clientEmail'] ?? ''),
            'tax_id' => $this->emptyToNull($validated['clientTaxId'] ?? ''),
            'address' => $this->emptyToNull($validated['clientAddress'] ?? ''),
        ]);

        $this->client->refresh();
        $this->detailCache = null;
        $this->showEditClientModal = false;

        $this->dispatch('toast', type: 'success', title: __('Les informations client ont été mises à jour.'));
    }

public function viewInvoice(string $id): void
    {
        abort_unless(
            $this->client->invoices()->whereKey($id)->exists(),
            404
        );

        $this->selectedInvoiceId = $id;
    }

    public function closeInvoice(): void
    {
        $this->selectedInvoiceId = null;
    }

    public function viewQuote(string $id): void
    {
        abort_unless(
            $this->client->quotes()->whereKey($id)->exists(),
            404
        );

        $this->selectedQuoteId = $id;
    }

    public function closeQuote(): void
    {
        $this->selectedQuoteId = null;
    }

    public function convertToInvoice(string $quoteId): void
    {
        $quote = Quote::query()
            ->where('company_id', $this->client->company_id)
            ->with('lines')
            ->findOrFail($quoteId);

        try {
            $invoice = app(QuoteService::class)->convertToInvoice($quote, $this->company);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->dispatch('toast', type: 'error', title: $e->getMessage());

            return;
        }

        $this->redirect(route('pme.invoices.edit', $invoice), navigate: true);
    }

    public function openPreview(string $invoiceId): void
    {
        abort_unless(
            $this->client->invoices()->whereKey($invoiceId)->exists(),
            404
        );

        $this->previewInvoiceId = $invoiceId;
        $this->previewTone = $this->company->getReminderSetting('default_tone', 'cordial');
        $this->previewAttachPdf = (bool) $this->company->getReminderSetting('attach_pdf', true);
        $this->previewChannel = $this->company->getReminderSetting('default_channel', 'whatsapp');
    }

    public function closePreview(): void
    {
        $this->previewInvoiceId = null;
    }

    public function setInvoiceStatusFilter(string $status): void
    {
        $this->invoiceStatusFilter = $status;
    }

    public function openTimeline(string $invoiceId): void
    {
        abort_unless(
            $this->client->invoices()->whereKey($invoiceId)->exists(),
            404
        );

        $this->timelineInvoiceId = $invoiceId;
        $this->selectedInvoiceId = null;
    }

    public function closeTimeline(): void
    {
        $this->timelineInvoiceId = null;
    }

    public function sendReminder(string $invoiceId): void
    {
        abort_unless(
            $this->client->invoices()->whereKey($invoiceId)->exists(),
            404
        );

        $invoice = Invoice::query()
            ->where('company_id', $this->client->company_id)
            ->findOrFail($invoiceId);

        try {
            $channel = ReminderChannel::from($this->previewChannel);

            $msg = $this->buildPreviewMessage();
            $messageBody = implode("\n\n", array_filter([
                $msg['greeting'],
                $msg['body'],
                $msg['closing'],
                $this->company->name,
            ])) ?: null;

            app(ReminderService::class)->send($invoice, $this->company, $channel, $messageBody);

            $this->previewInvoiceId = null;
            $this->detailCache = null;

            $this->dispatch('toast', type: 'success', title: __('Relance envoyée avec succès.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: __('Service d\'envoi bientôt disponible. Votre relance sera envoyée prochainement.'));
        }
    }

    #[Computed]
    public function previewInvoice(): ?Invoice
    {
        if (! $this->previewInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->with('client')
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereKey($this->previewInvoiceId)
            ->first();
    }

    #[Computed]
    public function timelineInvoice(): ?Invoice
    {
        if (! $this->timelineInvoiceId) {
            return null;
        }

        $invoice = Invoice::query()
            ->with(['client', 'reminders'])
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereKey($this->timelineInvoiceId)
            ->first();

        $invoice?->setRelation(
            'reminders',
            $invoice->reminders->sortBy('created_at')->values()
        );

        return $invoice;
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

    /** @return array<string, mixed> */
    #[Computed]
    public function detail(): array
    {
        return $this->detailCache ??= app(ClientService::class)->detail($this->client);
    }

    #[Computed]
    public function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->with(['client', 'lines'])
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereKey($this->selectedInvoiceId)
            ->first();
    }

    #[Computed]
    public function selectedQuote(): ?Quote
    {
        if (! $this->selectedQuoteId) {
            return null;
        }

        return Quote::query()
            ->with(['client', 'lines', 'invoice'])
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereKey($this->selectedQuoteId)
            ->first();
    }

    private function fillClientForm(): void
    {
        $this->clientName = $this->client->name;
        $this->clientPhone = $this->client->phone ?? '';
        $this->clientPhoneCountry = $this->phoneCountry($this->client->phone);
        $this->clientEmail = $this->client->email ?? '';
        $this->clientTaxId = $this->client->tax_id ?? '';
        $this->clientAddress = $this->client->address ?? '';
    }

    private function phoneCountry(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        foreach (config('fayeku.phone_countries', []) as $code => $country) {
            $prefix = preg_replace('/\D+/', '', $country['prefix']);

            if ($prefix !== '' && str_starts_with($digits, $prefix)) {
                return $code;
            }
        }

        return 'SN';
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        $prefix = preg_replace(
            '/\D+/',
            '',
            (string) config("fayeku.phone_countries.{$this->clientPhoneCountry}.prefix", '221')
        );

        if (str_starts_with($digits, $prefix)) {
            return '+'.$digits;
        }

        return '+'.$prefix.$digits;
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @if (session('client-saved'))
        <div x-init="$dispatch('toast', { type: 'success', title: '{{ session('client-saved') }} {{ __('est prêt pour la facturation et le suivi.') }}' })"></div>
    @endif

    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('pme.clients.index') }}" wire:navigate class="text-sm font-semibold text-slate-500 transition hover:text-primary">
                    {{ __('← Retour aux clients') }}
                </a>
                @if ($this->detail['row']['payment_score'] !== null)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span @class([
                        'inline-flex whitespace-nowrap items-center rounded-full px-2.5 py-1 text-sm font-semibold',
                        'bg-emerald-50 text-emerald-700' => $this->detail['row']['payment_tone'] === 'emerald',
                        'bg-teal-50 text-teal-700' => $this->detail['row']['payment_tone'] === 'teal',
                        'bg-amber-50 text-amber-700' => $this->detail['row']['payment_tone'] === 'amber',
                        'bg-rose-50 text-rose-700' => $this->detail['row']['payment_tone'] === 'rose',
                    ])>
                        {{ $this->detail['row']['payment_label'] }} · {{ $this->detail['row']['payment_score'] }}
                    </span>
                </div>
                @endif
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ $this->detail['row']['name'] }}</h2>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $this->detail['row']['last_interaction_label'] }} · {{ $this->detail['row']['last_interaction_detail'] }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.invoices.create', ['client' => $client->id]) }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouvelle facture') }}
                </a>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/8">
                    <flux:icon name="banknotes" class="size-5 text-primary" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-500">
                    {{ __('Cumul') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total facturé') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-ink">
                @if ($this->detail['row']['total_revenue'] > 0)
                    {{ format_money($this->detail['row']['total_revenue']) }}
                @else
                    —
                @endif
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700">
                    {{ __('Encaissé') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total encaissé') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-emerald-700">
                @if ($this->detail['row']['total_collected'] > 0)
                    {{ format_money($this->detail['row']['total_collected']) }}
                @else
                    —
                @endif
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                    <flux:icon name="exclamation-circle" class="size-5 text-rose-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-rose-50 px-2.5 py-1 text-sm font-semibold text-rose-700">
                    {{ __('Ouvert') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Solde en cours') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-rose-600">
                {{ $this->detail['row']['outstanding_amount'] > 0
                    ? format_money($this->detail['row']['outstanding_amount'])
                    : format_money(0) }}
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex whitespace-nowrap items-center rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                    {{ __('Paiement') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Délai moyen') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-ink">
                @if ($this->detail['row']['average_payment_days'] > 0)
                    {{ $this->detail['row']['average_payment_days'] }}j
                @else
                    —
                @endif
            </p>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <article class="app-shell-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-ink">{{ __('Informations client') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Coordonnées utiles pour la facturation et le recouvrement.') }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="openEditClientModal"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                        {{ __('Éditer') }}
                    </button>
                    @if ($this->detail['contact']['phone'] !== '—')
                        <a
                            href="https://wa.me/{{ ltrim(preg_replace('/\D+/', '', $this->detail['contact']['phone']), '0') }}"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                        >
                            {{ __('WhatsApp') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Téléphone / WhatsApp') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ format_phone($this->detail['contact']['phone']) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Email') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink break-all">{{ $this->detail['contact']['email'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Identifiant fiscal') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ $this->detail['contact']['tax_id'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Adresse') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ $this->detail['contact']['address'] }}</p>
                </div>
            </div>
        </article>

        <div class="grid gap-6">
            <article class="app-shell-panel p-6">
                <h3 class="text-lg font-semibold text-ink">{{ __('Score paiement') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Le score reste explicable: délai moyen, retards, impayés et fréquence de relance.') }}</p>
                <div class="mt-5 flex items-end justify-between gap-4">
                    <div>
                        @if ($this->detail['row']['payment_score'] !== null)
                            <p class="text-4xl font-semibold tracking-tight text-ink">{{ $this->detail['row']['payment_score'] }}</p>
                        @else
                            <p class="text-4xl font-semibold tracking-tight text-slate-300">—</p>
                        @endif
                    </div>
                    @if ($this->detail['row']['payment_label'] !== null)
                        <span @class([
                            'inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold',
                            'bg-emerald-50 text-emerald-700' => $this->detail['row']['payment_tone'] === 'emerald',
                            'bg-teal-50 text-teal-700' => $this->detail['row']['payment_tone'] === 'teal',
                            'bg-amber-50 text-amber-700' => $this->detail['row']['payment_tone'] === 'amber',
                            'bg-rose-50 text-rose-700' => $this->detail['row']['payment_tone'] === 'rose',
                        ])>
                            {{ $this->detail['row']['payment_label'] }}
                        </span>
                    @endif
                </div>
                <p class="mt-4 text-sm text-slate-600">{{ $this->detail['row']['score_explanation'] }}</p>
            </article>

            <article class="app-shell-panel p-6">
                <h3 class="text-lg font-semibold text-ink">{{ __('Exposition au risque') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Poids du client dans vos montants encore ouverts.') }}</p>
                <p class="mt-5 text-3xl font-semibold tracking-tight text-ink">{{ $this->detail['exposure']['share'] }}%</p>
                <p class="mt-2 text-sm text-slate-600">
                    @if ($this->detail['exposure']['total_outstanding'] > 0)
                        {{ __('Ce client représente') }} {{ $this->detail['exposure']['share'] }}% {{ __('de vos montants en attente, soit') }}
                        {{ format_money($this->detail['exposure']['total_outstanding']) }}.
                    @else
                        {{ __('Aucune exposition en attente pour le moment.') }}
                    @endif
                </p>
            </article>
        </div>
    </section>

    <section @class([
        'app-shell-panel overflow-hidden',
        'ring-2 ring-primary/15' => $focus === 'impayes',
    ])>
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-ink">{{ __('Factures et impayés') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Historique des factures, montants restants dus et niveau de relance associé.') }}</p>
        </div>

        @php
            $allInvoices = collect($this->detail['invoices']);
            $invoiceStatusCounts = $allInvoices->countBy('status_value');
            $invoiceStatusTabs = [
                'all'            => ['label' => 'Tous',         'dot' => null,           'activeClass' => 'bg-primary text-white',     'badgeInactive' => 'bg-slate-100 text-slate-500'],
                'sent'           => ['label' => 'Envoyée',      'dot' => 'bg-blue-500',  'activeClass' => 'bg-blue-500 text-white',    'badgeInactive' => 'bg-blue-100 text-blue-700'],
                'paid'           => ['label' => 'Payée',        'dot' => 'bg-accent',    'activeClass' => 'bg-emerald-600 text-white', 'badgeInactive' => 'bg-emerald-100 text-emerald-700'],
                'overdue'        => ['label' => 'En retard',    'dot' => 'bg-rose-500',  'activeClass' => 'bg-rose-500 text-white',    'badgeInactive' => 'bg-rose-100 text-rose-700'],
                'partially_paid' => ['label' => 'Part. payée',  'dot' => 'bg-amber-400', 'activeClass' => 'bg-amber-500 text-white',   'badgeInactive' => 'bg-amber-100 text-amber-700'],
            ];
            $filteredInvoices = $invoiceStatusFilter === 'all'
                ? $allInvoices
                : $allInvoices->where('status_value', $invoiceStatusFilter);
        @endphp

        @if ($allInvoices->isNotEmpty())
            <div class="flex flex-wrap gap-2 border-b border-slate-100 px-6 py-4">
                @foreach ($invoiceStatusTabs as $key => $tab)
                    @php $count = $key === 'all' ? $allInvoices->count() : ($invoiceStatusCounts[$key] ?? 0); @endphp
                    @if ($key === 'all' || $count > 0)
                        <x-ui.filter-chip
                            wire:click="setInvoiceStatusFilter('{{ $key }}')"
                            :label="__($tab['label'])"
                            :dot="$tab['dot']"
                            :active="$invoiceStatusFilter === $key"
                            :activeClass="$tab['activeClass']"
                            :badgeInactive="$tab['badgeInactive']"
                            :count="$count"
                        />
                    @endif
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Référence') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Émise le') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Échéance') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Montant') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Reste dû') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-slate-500">{{ __('Relances') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($filteredInvoices as $invoice)
                            <tr
                                wire:key="client-invoice-{{ $invoice['id'] }}"
                                wire:click="viewInvoice('{{ $invoice['id'] }}')"
                                class="cursor-pointer transition hover:bg-slate-50/70 focus-within:bg-slate-50/70"
                            >
                                <td class="px-6 py-4 font-semibold text-ink">{{ $invoice['reference'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['issued_at_label'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['due_at_label'] }}</td>
                                <td class="px-4 py-4 font-semibold text-ink">{{ format_money($invoice['total'], compact: true) }}</td>
                                <td class="px-4 py-4">
                                    @if ($invoice['remaining'] > 0)
                                        <span class="font-semibold text-rose-600">{{ format_money($invoice['remaining'], compact: true) }}</span>
                                    @else
                                        <span class="text-slate-500">{{ format_money(0, compact: true) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex whitespace-nowrap items-center rounded-full px-2.5 py-1 text-sm font-medium ring-1 ring-inset',
                                        'bg-emerald-50 text-emerald-700 ring-emerald-200' => $invoice['status_tone'] === 'emerald',
                                        'bg-amber-50 text-amber-700 ring-amber-200' => $invoice['status_tone'] === 'amber',
                                        'bg-rose-50 text-rose-700 ring-rose-200' => $invoice['status_tone'] === 'rose',
                                        'bg-slate-100 text-slate-700 ring-slate-200' => $invoice['status_tone'] === 'slate',
                                        'bg-sky-50 text-sky-700 ring-sky-200' => $invoice['status_tone'] === 'sky',
                                    ])>
                                        {{ $invoice['status'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['reminders_count'] }}</td>
                                <td class="px-4 py-4" x-on:click.stop>
                                    <flux:dropdown position="bottom" align="end">
                                        <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                            {{ __('Actions') }}
                                            <flux:icon name="chevron-down" class="size-3.5" />
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item wire:click="viewInvoice('{{ $invoice['id'] }}')">
                                                <flux:icon name="eye" class="size-4 text-slate-500" />
                                                {{ __('Voir la facture') }}
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="openTimeline('{{ $invoice['id'] }}')">
                                                <flux:icon name="clock" class="size-4 text-slate-500" />
                                                {{ __('Voir les relances') }}
                                            </flux:menu.item>
                                            @if ($invoice['is_overdue'])
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="openPreview('{{ $invoice['id'] }}')">
                                                    <flux:icon name="paper-airplane" class="size-4 text-slate-500" />
                                                    {{ __('Relancer le client') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                {{ __('Aucune facture liée à ce client pour le moment.') }}
            </div>
        @endif
    </section>

    <section class="app-shell-panel overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-ink">{{ __('Devis') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Suivi des propositions commerciales envoyées à ce client.') }}</p>
        </div>

        @if (count($this->detail['quotes']) > 0)
            <div class="divide-y divide-slate-100">
                @foreach ($this->detail['quotes'] as $quote)
                    <div
                        wire:key="client-quote-{{ $quote['id'] }}"
                        wire:click="viewQuote('{{ $quote['id'] }}')"
                        class="flex cursor-pointer items-center justify-between gap-4 px-6 py-4 transition hover:bg-slate-50/70"
                    >
                        <div>
                            <p class="font-semibold text-ink">{{ $quote['reference'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $quote['issued_at_label'] }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <p class="font-semibold text-ink">{{ format_money($quote['total']) }}</p>
                            <span @class([
                                'inline-flex whitespace-nowrap items-center rounded-full px-2 py-1 text-xs font-medium',
                                'bg-gray-50 text-gray-600 inset-ring inset-ring-gray-500/10' => $quote['status_tone'] === 'gray',
                                'bg-blue-50 text-blue-700 inset-ring inset-ring-blue-700/10' => $quote['status_tone'] === 'blue',
                                'bg-green-50 text-green-700 inset-ring inset-ring-green-600/20' => $quote['status_tone'] === 'green',
                                'bg-red-50 text-red-700 inset-ring inset-ring-red-600/10' => $quote['status_tone'] === 'red',
                            ])>{{ $quote['status'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                {{ __('Aucun devis pour ce client pour le moment.') }}
            </div>
        @endif
    </section>

    @if ($showEditClientModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="$set('showEditClientModal', false)"
            x-data
            @keydown.escape.window="$wire.set('showEditClientModal', false)"
        >
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="saveClientUpdates">
                    <div class="flex items-start justify-between border-b border-slate-100 px-7 py-6">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ __('Modifier le client') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Mettez à jour les coordonnées utiles à la facturation et au recouvrement.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="$set('showEditClientModal', false)"
                            class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto px-7 py-6">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                    {{ __('Nom client ou Raison Sociale') }} <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    wire:model="clientName"
                                    type="text"
                                    required
                                    autofocus
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                                @error('clientName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <x-phone-input
                                :label="__('Téléphone / WhatsApp')"
                                country-name="clientPhoneCountry"
                                :country-value="$clientPhoneCountry"
                                country-model="clientPhoneCountry"
                                phone-name="clientPhone"
                                :phone-value="$clientPhone"
                                phone-model="clientPhone"
                                :countries="$clientPhoneCountries"
                                required
                            />
                            @error('clientPhone') <p class="-mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                                <input
                                    wire:model="clientEmail"
                                    type="email"
                                    placeholder="contact@client.sn"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                                @error('clientEmail') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Identifiant fiscal') }}</label>
                                <input
                                    wire:model="clientTaxId"
                                    type="text"
                                    placeholder="NINEA / RCCM / NCC"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Adresse') }}</label>
                                <input
                                    wire:model="clientAddress"
                                    type="text"
                                    placeholder="{{ __('Rue, quartier, ville…') }}"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            {{ __('Les coordonnées client serviront aussi aux relances WhatsApp, SMS et email selon le canal choisi.') }}
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                        <button
                            type="button"
                            wire:click="$set('showEditClientModal', false)"
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

    @if ($this->selectedInvoice)
        <x-invoices.detail-modal :invoice="$this->selectedInvoice" close-action="closeInvoice" />
    @endif

    @if ($this->selectedQuote)
        @php
            $q = $this->selectedQuote;
            $client = $q->client;
            $statusConfig = match ($q->status) {
                \Modules\PME\Invoicing\Enums\QuoteStatus::Accepted => ['label' => 'Accepté', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Sent => ['label' => 'Envoyé', 'class' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Draft => ['label' => 'Brouillon', 'class' => 'bg-slate-100 text-slate-600'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Declined => ['label' => 'Refusé', 'class' => 'bg-rose-50 text-rose-700'],
                \Modules\PME\Invoicing\Enums\QuoteStatus::Expired => ['label' => 'Expiré', 'class' => 'bg-slate-100 text-slate-500'],
                default => ['label' => ucfirst($q->status->value), 'class' => 'bg-slate-100 text-slate-600'],
            };
        @endphp
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="closeQuote"
            x-data
            @keydown.escape.window="$wire.closeQuote()"
        >
            <div class="relative w-full max-w-[1200px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-10 py-7">
                    <div>
                        <p class="text-sm font-semibold tracking-[0.24em] text-slate-400">{{ __('Devis') }}</p>
                        <h2 class="mt-1 text-xl font-bold text-ink">{{ $q->reference }}</h2>
                        <div class="mt-1 flex items-center gap-3">
                            <p class="text-sm text-slate-500">
                                {{ __('Émis le') }} {{ format_date($q->issued_at) }}
                                @if ($q->valid_until)
                                    &nbsp;·&nbsp;
                                    {{ __('Valide jusqu\'au') }} {{ format_date($q->valid_until) }}
                                @endif
                            </p>
                            <span class="inline-flex whitespace-nowrap items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusConfig['class'] }}">
                                {{ $statusConfig['label'] }}
                            </span>
                        </div>
                    </div>
                    <button
                        wire:click="closeQuote"
                        class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 gap-0 lg:grid-cols-3">
                        <div class="col-span-2 px-10 py-8">
                            @if ($client)
                                <div class="mb-6">
                                    <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Destinataire') }}</p>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-5 py-4">
                                        <p class="font-semibold text-ink">{{ $client->name }}</p>
                                        @if ($client->phone)
                                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="phone" class="size-3.5 shrink-0" />
                                                {{ format_phone($client->phone) }}
                                            </p>
                                        @endif
                                        @if ($client->email)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="envelope" class="size-3.5 shrink-0" />
                                                {{ $client->email }}
                                            </p>
                                        @endif
                                        @if ($client->address)
                                            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-slate-500">
                                                <flux:icon name="map-pin" class="size-3.5 shrink-0" />
                                                {{ $client->address }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div>
                                <p class="mb-3 text-sm font-semibold text-slate-500">{{ __('Détail des prestations') }}</p>
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
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-600 whitespace-nowrap">
                                                    {{ format_money($line->unit_price, $q->currency) }}
                                                </td>
                                                <td class="py-3 px-4 text-right tabular-nums text-slate-500 whitespace-nowrap">{{ $line->tax_rate }} %</td>
                                                <td class="py-3 pl-4 text-right tabular-nums font-medium text-ink whitespace-nowrap">
                                                    {{ format_money($line->total, $q->currency) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-slate-400">{{ __('Aucune ligne.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t border-slate-200">
                                        <tr>
                                            <td colspan="4" class="pt-4 pr-4 text-right text-sm text-slate-500">{{ __('Sous-total HT') }}</td>
                                            <td class="pt-4 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                                {{ format_money($q->subtotal, $q->currency) }}
                                            </td>
                                        </tr>
                                        @if ($q->discount > 0)
                                            @php $discountAmount = (int) round($q->subtotal * $q->discount / 100); @endphp
                                            <tr>
                                                <td colspan="4" class="pt-1 pr-4 text-right text-sm text-emerald-600">{{ __('Réduction') }} ({{ $q->discount }} %)</td>
                                                <td class="pt-1 pl-4 text-right tabular-nums text-sm text-emerald-600 whitespace-nowrap">
                                                    − {{ format_money($discountAmount, $q->currency) }}
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="4" class="pt-1 pr-4 text-right text-sm text-slate-500">{{ __('TVA') }}</td>
                                            <td class="pt-1 pl-4 text-right tabular-nums text-sm text-ink whitespace-nowrap">
                                                {{ format_money($q->tax_amount, $q->currency) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="pt-2 pr-4 text-right text-base font-semibold text-ink">{{ __('Total TTC') }}</td>
                                            <td class="pt-2 pl-4 text-right tabular-nums text-base font-bold text-ink whitespace-nowrap">
                                                {{ format_money($q->total, $q->currency) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50/60 px-8 py-8 lg:border-t-0 lg:border-l">
                            <p class="mb-4 text-sm font-semibold text-slate-500">{{ __('Récapitulatif') }}</p>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('Montant HT') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ format_money($q->subtotal, $q->currency) }}</dd>
                                </div>
                                @if ($q->discount > 0)
                                    @php $discountAmount = (int) round($q->subtotal * $q->discount / 100); @endphp
                                    <div class="flex justify-between text-emerald-600">
                                        <dt>{{ __('Réduction') }} ({{ $q->discount }} %)</dt>
                                        <dd class="tabular-nums font-medium">− {{ format_money($discountAmount, $q->currency) }}</dd>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('TVA') }}</dt>
                                    <dd class="tabular-nums font-medium text-ink">{{ format_money($q->tax_amount, $q->currency) }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-3">
                                    <dt class="font-semibold text-ink">{{ __('Total TTC') }}</dt>
                                    <dd class="tabular-nums text-lg font-bold text-ink">{{ format_money($q->total, $q->currency) }}</dd>
                                </div>
                            </dl>

                            @if (in_array($q->status, [\Modules\PME\Invoicing\Enums\QuoteStatus::Sent, \Modules\PME\Invoicing\Enums\QuoteStatus::Accepted]) && ! $q->invoice)
                                <div class="mt-6">
                                    <button
                                        wire:click="convertToInvoice('{{ $q->id }}')"
                                        wire:confirm="{{ __('Convertir ce devis en facture ?') }}"
                                        class="flex w-full items-center justify-center rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                                    >
                                        <flux:icon name="document-arrow-up" class="mr-2 size-4" />
                                        {{ __('Convertir en facture') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-10 py-5">
                    <flux:button variant="ghost" wire:click="closeQuote">
                        {{ __('Fermer') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Slide-over historique des relances --}}
    @if ($timelineInvoiceId && $this->timelineInvoice)
        <x-ui.drawer
            :title="__('Historique des relances')"
            :subtitle="$this->timelineInvoice->reference . ' · ' . $this->client->name"
            close-action="closeTimeline"
        >
            <x-collection.reminder-feed :invoice="$this->timelineInvoice" />
        </x-ui.drawer>
    @endif

    {{-- Slide-over aperçu relance --}}
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

</div>

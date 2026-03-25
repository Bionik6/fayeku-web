<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Clients\Services\ClientService;

new #[Title('Client')] #[Layout('layouts::pme')] class extends Component {
    public Client $client;

    public ?Company $company = null;

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
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function detail(): array
    {
        return $this->detailCache ??= app(ClientService::class)->detail($this->client);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    @if (session('client-saved'))
        <section class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('client-saved') }} {{ __('est prêt pour la facturation et le suivi.') }}
        </section>
    @endif

    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('pme.clients.index') }}" wire:navigate class="text-sm font-semibold text-slate-400 transition hover:text-primary">
                    {{ __('← Retour aux clients') }}
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                        {{ $this->detail['contact']['sector'] }}
                    </span>
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-emerald-50 text-emerald-700' => $this->detail['row']['payment_tone'] === 'emerald',
                        'bg-teal-50 text-teal-700' => $this->detail['row']['payment_tone'] === 'teal',
                        'bg-amber-50 text-amber-700' => $this->detail['row']['payment_tone'] === 'amber',
                        'bg-rose-50 text-rose-700' => $this->detail['row']['payment_tone'] === 'rose',
                    ])>
                        {{ $this->detail['row']['payment_label'] }} · {{ $this->detail['row']['payment_score'] }}
                    </span>
                </div>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ $this->detail['row']['name'] }}</h2>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $this->detail['row']['last_interaction_label'] }} · {{ $this->detail['row']['last_interaction_detail'] }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a
                    href="{{ route('pme.invoices.index', ['q' => $this->detail['row']['name']]) }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                >
                    <flux:icon name="plus" class="size-4" />
                    {{ __('Nouvelle facture') }}
                </a>
                <a
                    href="{{ route('pme.clients.show', ['client' => $client, 'focus' => 'impayes']) }}"
                    wire:navigate
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
                >
                    {{ __('Voir les impayés') }}
                </a>
                <a
                    href="{{ route('pme.clients.show', ['client' => $client, 'focus' => 'relances']) }}"
                    wire:navigate
                    class="text-sm font-semibold text-slate-400 transition hover:text-primary"
                >
                    {{ __('Aller aux relances') }} →
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
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                    {{ __('Cumul') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total facturé') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-ink">
                @if ($this->detail['row']['total_revenue'] > 0)
                    {{ number_format($this->detail['row']['total_revenue'], 0, ',', ' ') }} F
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
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    {{ __('Encaissé') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Total encaissé') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-emerald-700">
                @if ($this->detail['row']['total_collected'] > 0)
                    {{ number_format($this->detail['row']['total_collected'], 0, ',', ' ') }} F
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
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                    {{ __('Ouvert') }}
                </span>
            </div>
            <p class="mt-4 text-sm font-medium text-slate-500">{{ __('Solde en cours') }}</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-rose-600">
                @if ($this->detail['row']['outstanding_amount'] > 0)
                    {{ number_format($this->detail['row']['outstanding_amount'], 0, ',', ' ') }} F
                @else
                    0 F
                @endif
            </p>
        </article>

        <article class="app-shell-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                    <flux:icon name="clock" class="size-5 text-amber-500" />
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
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

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Téléphone / WhatsApp') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ $this->detail['contact']['phone'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Email') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink break-all">{{ $this->detail['contact']['email'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Secteur') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ $this->detail['contact']['sector'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Identifiant fiscal') }}</p>
                    <p class="mt-2 text-sm font-semibold text-ink">{{ $this->detail['contact']['tax_id'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Adresse') }}</p>
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
                        <p class="text-4xl font-semibold tracking-tight text-ink">{{ $this->detail['row']['payment_score'] }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-700">{{ $this->detail['row']['payment_label'] }}</p>
                    </div>
                    <span @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-emerald-50 text-emerald-700' => $this->detail['row']['payment_tone'] === 'emerald',
                        'bg-teal-50 text-teal-700' => $this->detail['row']['payment_tone'] === 'teal',
                        'bg-amber-50 text-amber-700' => $this->detail['row']['payment_tone'] === 'amber',
                        'bg-rose-50 text-rose-700' => $this->detail['row']['payment_tone'] === 'rose',
                    ])>
                        {{ $this->detail['row']['payment_label'] }}
                    </span>
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
                        {{ number_format($this->detail['exposure']['total_outstanding'], 0, ',', ' ') }} F.
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

        @if (count($this->detail['invoices']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Référence') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Émise le') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Échéance') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Montant') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Reste dû') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Statut') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500">{{ __('Relances') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->detail['invoices'] as $invoice)
                            <tr wire:key="client-invoice-{{ $invoice['id'] }}" class="transition hover:bg-slate-50/70">
                                <td class="px-6 py-4 font-semibold text-ink">{{ $invoice['reference'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['issued_at_label'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['due_at_label'] }}</td>
                                <td class="px-4 py-4 font-semibold text-ink">{{ number_format($invoice['total'], 0, ',', ' ') }} F</td>
                                <td class="px-4 py-4">
                                    @if ($invoice['remaining'] > 0)
                                        <span class="font-semibold text-rose-600">{{ number_format($invoice['remaining'], 0, ',', ' ') }} F</span>
                                    @else
                                        <span class="text-slate-400">0 F</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['status'] }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ $invoice['reminders_count'] }}</td>
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

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <article class="app-shell-panel overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-ink">{{ __('Devis') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Suivi des propositions commerciales envoyées à ce client.') }}</p>
            </div>

            @if (count($this->detail['quotes']) > 0)
                <div class="divide-y divide-slate-100">
                    @foreach ($this->detail['quotes'] as $quote)
                        <div wire:key="client-quote-{{ $quote['id'] }}" class="flex items-center justify-between gap-4 px-6 py-4">
                            <div>
                                <p class="font-semibold text-ink">{{ $quote['reference'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $quote['issued_at_label'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-ink">{{ number_format($quote['total'], 0, ',', ' ') }} F</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $quote['status'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-12 text-center text-sm text-slate-500">
                    {{ __('Aucun devis pour ce client pour le moment.') }}
                </div>
            @endif
        </article>

        <article class="app-shell-panel overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-semibold text-ink">{{ __('Paiements') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Encaissements reçus, y compris les paiements partiels enregistrés.') }}</p>
            </div>

            @if (count($this->detail['payments']) > 0)
                <div class="divide-y divide-slate-100">
                    @foreach ($this->detail['payments'] as $payment)
                        <div wire:key="client-payment-{{ $payment['id'] }}" class="flex items-center justify-between gap-4 px-6 py-4">
                            <div>
                                <p class="font-semibold text-ink">{{ $payment['reference'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $payment['paid_at_label'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-emerald-700">{{ number_format($payment['amount'], 0, ',', ' ') }} F</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $payment['status'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-12 text-center text-sm text-slate-500">
                    {{ __('Aucun paiement enregistré pour ce client.') }}
                </div>
            @endif
        </article>
    </section>

    <section @class([
        'app-shell-panel overflow-hidden',
        'ring-2 ring-primary/15' => $focus === 'relances',
    ])>
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-ink">{{ __('Relances') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Historique des rappels déjà envoyés à ce client et canaux utilisés.') }}</p>
        </div>

        @if (count($this->detail['reminders']) > 0)
            <div class="divide-y divide-slate-100">
                @foreach ($this->detail['reminders'] as $reminder)
                    <div wire:key="client-reminder-{{ $reminder['id'] }}" class="px-6 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="font-semibold text-ink">{{ $reminder['invoice_reference'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $reminder['sent_at_label'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $reminder['channel'] }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $reminder['status'] }}
                                </span>
                            </div>
                        </div>
                        @if ($reminder['body'])
                            <p class="mt-3 text-sm text-slate-600">{{ $reminder['body'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                {{ __('Aucune relance n’a encore été envoyée à ce client.') }}
            </div>
        @endif
    </section>

    <section class="app-shell-panel overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-lg font-semibold text-ink">{{ __('Chronologie des interactions') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Un historique lisible des factures, devis, paiements et relances liés à ce client.') }}</p>
        </div>

        @if (count($this->detail['timeline']) > 0)
            <div class="divide-y divide-slate-100">
                @foreach ($this->detail['timeline'] as $index => $event)
                    <div wire:key="client-timeline-{{ $index }}" class="flex gap-4 px-6 py-4">
                        <div class="mt-1 flex size-9 shrink-0 items-center justify-center rounded-2xl bg-mist text-primary">
                            <flux:icon name="sparkles" class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $event['title'] }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $event['body'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $event['date_label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                {{ __('La chronologie se remplira avec les premières interactions client.') }}
            </div>
        @endif
    </section>

</div>

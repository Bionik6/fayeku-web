<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aide & Support')] #[Layout('layouts::pme')] class extends Component {

    public string $search = '';

    public ?int $openFaq = null;

    public string $subject = '';

    public string $category = '';

    public string $message = '';

    public bool $contactSent = false;

    public ?int $openGuide = null;

    /** @var array<int, array{subject: string, category: string, date: string, status: string}> */
    public array $requests = [];

    public function toggleFaq(int $index): void
    {
        $this->openFaq = $this->openFaq === $index ? null : $index;
    }

    public function toggleGuide(int $index): void
    {
        $this->openGuide = $this->openGuide === $index ? null : $index;
    }

    public function submitContact(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $this->requests = array_merge([
            [
                'subject' => $this->subject,
                'category' => $this->category,
                'date' => now()->locale('fr_FR')->translatedFormat('j M Y'),
                'status' => 'Ouverte',
            ],
        ], $this->requests);

        $this->contactSent = true;
        $this->reset(['subject', 'category', 'message']);
    }

    public function resetContact(): void
    {
        $this->contactSent = false;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- En-tête --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Aide & Support') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ __('Comment pouvons-nous vous aider ?') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Trouvez des réponses, consultez les guides ou contactez notre équipe.') }}</p>
        </div>
    </section>

    {{-- Barre de recherche --}}
    <section>
        <div class="relative">
            <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-500" />
            <input
                wire:model="search"
                type="search"
                placeholder="{{ __('Rechercher une question, une facture, un client...') }}"
                class="auth-input w-full pl-11"
            />
        </div>
    </section>

    {{-- Actions rapides --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

        <article class="app-shell-panel flex flex-col gap-4 p-6">
            <div class="flex size-10 items-center justify-center rounded-xl bg-teal-50">
                <flux:icon name="chat-bubble-left-ellipsis" class="size-5 text-primary" />
            </div>
            <div>
                <p class="font-semibold text-ink">{{ __('Contacter le support') }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ __('Posez votre question à notre équipe.') }}</p>
            </div>
            <button
                type="button"
                x-data
                x-on:click="document.getElementById('contact-form').scrollIntoView({ behavior: 'smooth' })"
                class="mt-auto inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
            >
                {{ __('Envoyer un message') }}
            </button>
        </article>

        <article class="app-shell-panel flex flex-col gap-4 p-6">
            <div class="flex size-10 items-center justify-center rounded-xl bg-violet-50">
                <flux:icon name="book-open" class="size-5 text-violet-600" />
            </div>
            <div>
                <p class="font-semibold text-ink">{{ __('Consulter les guides') }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ __('Découvrez les étapes clés pour utiliser Fayeku PME.') }}</p>
            </div>
            <button
                type="button"
                x-data
                x-on:click="document.getElementById('guides-section').scrollIntoView({ behavior: 'smooth' })"
                class="mt-auto inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
            >
                {{ __('Voir les guides') }}
            </button>
        </article>

        <article class="app-shell-panel flex flex-col gap-4 p-6">
            <div class="flex size-10 items-center justify-center rounded-xl bg-rose-50">
                <flux:icon name="exclamation-triangle" class="size-5 text-rose-500" />
            </div>
            <div>
                <p class="font-semibold text-ink">{{ __('Signaler un problème') }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ __('Prévenez-nous si quelque chose ne fonctionne pas comme prévu.') }}</p>
            </div>
            <button
                type="button"
                x-data
                x-on:click="$wire.set('category', 'Autre'); document.getElementById('contact-form').scrollIntoView({ behavior: 'smooth' })"
                class="mt-auto inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
            >
                {{ __('Signaler un incident') }}
            </button>
        </article>

        <article class="app-shell-panel flex flex-col gap-4 p-6">
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                <flux:icon name="clock" class="size-5 text-amber-500" />
            </div>
            <div>
                <p class="font-semibold text-ink">{{ __('Suivre mes demandes') }}</p>
                <p class="mt-0.5 text-sm text-slate-500">{{ __('Retrouvez vos échanges et demandes récentes.') }}</p>
            </div>
            <button
                type="button"
                x-data
                x-on:click="document.getElementById('requests-section').scrollIntoView({ behavior: 'smooth' })"
                class="mt-auto inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary"
            >
                {{ __('Voir mes demandes') }}
            </button>
        </article>

    </section>

    {{-- FAQ + Guides --}}
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- FAQ --}}
        <div class="app-shell-panel p-6">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Questions fréquentes') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Les réponses aux questions les plus courantes sur Fayeku PME.') }}</p>

            @php
                $faqs = [
                    [
                        'q' => __('Comment créer une facture ?'),
                        'a' => __('Rendez-vous dans la section "Factures et devis", puis cliquez sur "Nouvelle facture". Renseignez les informations du client, les lignes de facture et validez. La facture est générée automatiquement en PDF.'),
                    ],
                    [
                        'q' => __('Comment suivre mes impayés ?'),
                        'a' => __('La section "Recouvrement et relance" centralise toutes vos factures impayées. Vous pouvez filtrer par ancienneté, montant ou client, et déclencher des relances automatiques.'),
                    ],
                    [
                        'q' => __('Comment ajouter un client ?'),
                        'a' => __('Dans la section "Clients", cliquez sur "Nouveau client". Renseignez le nom, les coordonnées et les informations légales (NINEA, RCCM). Le client est immédiatement disponible pour facturation.'),
                    ],
                    [
                        'q' => __('Comment consulter ma trésorerie ?'),
                        'a' => __('La section "Trésorerie" affiche vos flux financiers sur la période choisie (semaine, mois, trimestre). Vous pouvez aussi consulter les prévisions basées sur vos factures en cours.'),
                    ],
                    [
                        'q' => __('Comment créer un devis ?'),
                        'a' => __('Dans "Factures et devis", sélectionnez l\'onglet "Devis" puis "Nouveau devis". Renseignez le client et les lignes du devis. Une fois accepté par le client, il peut être converti en facture en un clic.'),
                    ],
                    [
                        'q' => __('Comment configurer les relances automatiques ?'),
                        'a' => __('Dans Paramètres > Recouvrement, définissez vos règles de relance : délai avant première relance, fréquence, canal (e-mail ou SMS) et modèle de message. Les relances sont ensuite déclenchées automatiquement.'),
                    ],
                    [
                        'q' => __('Comment exporter mes données comptables ?'),
                        'a' => __('Vos factures et données financières sont accessibles depuis la section "Trésorerie". Votre comptable peut également y accéder directement via son espace Fayeku Compta si vous l\'avez invité.'),
                    ],
                    [
                        'q' => __('Comment inviter mon comptable ?'),
                        'a' => __('Dans Paramètres > Mon comptable, renseignez le numéro ou l\'e-mail de votre cabinet comptable. Une invitation lui sera envoyée pour accéder à votre espace depuis Fayeku Compta.'),
                    ],
                ];
            @endphp

            <div class="mt-5 divide-y divide-slate-100">
                @foreach ($faqs as $i => $faq)
                    <div wire:key="faq-{{ $i }}" class="py-4">
                        <button
                            type="button"
                            wire:click="toggleFaq({{ $i }})"
                            class="flex w-full items-center justify-between gap-4 text-left"
                        >
                            <span class="font-medium text-ink">{{ $faq['q'] }}</span>
                            @if ($openFaq === $i)
                                <flux:icon name="chevron-up" class="size-4 shrink-0 text-slate-500" />
                            @else
                                <flux:icon name="chevron-down" class="size-4 shrink-0 text-slate-500" />
                            @endif
                        </button>
                        @if ($openFaq === $i)
                            <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $faq['a'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Guides --}}
        <div id="guides-section" class="app-shell-panel p-6">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Guides Fayeku PME') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Suivez les principales étapes pour bien utiliser votre espace PME.') }}</p>

            @php
                $guides = [
                    [
                        'title' => __('Démarrer avec Fayeku PME'),
                        'desc' => __('Configurer votre entreprise et prendre en main les principaux écrans.'),
                        'icon' => 'rocket-launch',
                        'color' => 'text-primary',
                        'bg' => 'bg-teal-50',
                        'steps' => [
                            __('Complétez votre profil d\'entreprise dans Paramètres > Mon entreprise (nom, adresse, NINEA, RCCM).'),
                            __('Ajoutez vos premiers clients via la section Clients.'),
                            __('Créez votre première facture depuis la section Factures et devis.'),
                            __('Explorez le Dashboard pour suivre votre chiffre d\'affaires et vos indicateurs clés.'),
                            __('Invitez votre comptable dans Paramètres > Mon comptable pour lui donner accès à vos données.'),
                        ],
                    ],
                    [
                        'title' => __('Créer et gérer vos factures'),
                        'desc' => __('Émettre des factures professionnelles et suivre leurs paiements.'),
                        'icon' => 'document-text',
                        'color' => 'text-violet-500',
                        'bg' => 'bg-violet-50',
                        'steps' => [
                            __('Dans Factures et devis, cliquez sur "Nouvelle facture" et sélectionnez un client existant ou créez-en un nouveau.'),
                            __('Ajoutez les lignes de facture : description, quantité, prix unitaire, TVA applicable.'),
                            __('Vérifiez le récapitulatif et cliquez sur "Valider" pour générer la facture définitive en PDF.'),
                            __('Envoyez la facture par e-mail directement depuis l\'interface ou téléchargez le PDF.'),
                            __('Suivez l\'état de la facture (en attente, partiellement payée, réglée) depuis la liste des factures.'),
                        ],
                    ],
                    [
                        'title' => __('Configurer le recouvrement'),
                        'desc' => __('Automatiser vos relances pour réduire les impayés.'),
                        'icon' => 'bell-alert',
                        'color' => 'text-rose-500',
                        'bg' => 'bg-rose-50',
                        'steps' => [
                            __('Dans Paramètres > Recouvrement, définissez votre délai de paiement par défaut (ex: 30 jours).'),
                            __('Créez vos règles de relance : J+5, J+15, J+30 avec le canal souhaité (e-mail ou SMS).'),
                            __('Personnalisez les modèles de messages de relance selon votre ton commercial.'),
                            __('Dans la section Recouvrement, visualisez toutes les factures en retard triées par ancienneté.'),
                            __('Déclenchez une relance manuelle ou laissez les relances automatiques s\'exécuter selon votre planning.'),
                        ],
                    ],
                    [
                        'title' => __('Suivre votre trésorerie'),
                        'desc' => __('Visualiser vos flux financiers et anticiper vos besoins.'),
                        'icon' => 'banknotes',
                        'color' => 'text-emerald-500',
                        'bg' => 'bg-emerald-50',
                        'steps' => [
                            __('La section Trésorerie affiche un solde estimé basé sur vos factures émises et reçues.'),
                            __('Filtrez par période (semaine, mois, trimestre, année) pour analyser vos flux.'),
                            __('Consultez les prévisions de trésorerie basées sur les échéances de vos factures en cours.'),
                            __('Identifiez les périodes à risque (solde bas, pic d\'impayés) pour anticiper vos besoins en financement.'),
                            __('Exportez votre tableau de trésorerie pour le partager avec votre comptable ou banquier.'),
                        ],
                    ],
                ];
            @endphp

            <div class="mt-5 divide-y divide-slate-100">
                @foreach ($guides as $i => $guide)
                    <div wire:key="guide-{{ $i }}" class="py-3">
                        <button
                            type="button"
                            wire:click="toggleGuide({{ $i }})"
                            class="flex w-full items-center gap-4 text-left"
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $guide['bg'] }}">
                                <flux:icon :name="$guide['icon']" class="size-4 {{ $guide['color'] }}" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink">{{ $guide['title'] }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $guide['desc'] }}</p>
                            </div>
                            @if ($openGuide === $i)
                                <flux:icon name="chevron-up" class="size-4 shrink-0 text-slate-500" />
                            @else
                                <flux:icon name="chevron-right" class="size-4 shrink-0 text-slate-300" />
                            @endif
                        </button>
                        @if ($openGuide === $i)
                            <div class="ml-13 mt-4 pl-1">
                                <ol class="space-y-2.5">
                                    @foreach ($guide['steps'] as $step => $text)
                                        <li class="flex items-start gap-3">
                                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-500">
                                                {{ $step + 1 }}
                                            </span>
                                            <p class="text-sm leading-relaxed text-slate-600">{{ $text }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </section>

    {{-- Formulaire de contact --}}
    <section id="contact-form" class="app-shell-panel p-6">
        <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Contacter notre équipe') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('Vous n\'avez pas trouvé votre réponse ? Envoyez-nous votre demande et nous vous répondrons dès que possible.') }}</p>

        @if ($contactSent)
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                        <flux:icon name="check" class="size-4 text-emerald-700" />
                    </div>
                    <div>
                        <p class="font-semibold text-emerald-800">{{ __('Message envoyé avec succès !') }}</p>
                        <p class="mt-0.5 text-sm text-emerald-700">{{ __('Notre équipe vous répond généralement sous 24 à 48 heures ouvrées.') }}</p>
                        <button
                            type="button"
                            wire:click="resetContact"
                            class="mt-3 text-sm font-semibold text-emerald-700 underline underline-offset-2 hover:text-emerald-900"
                        >
                            {{ __('Envoyer un autre message') }}
                        </button>
                    </div>
                </div>
            </div>
        @else
            <form wire:submit="submitContact" class="mt-6 space-y-5">

                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="auth-label">
                        <span>{{ __('Sujet') }}</span>
                        <input
                            wire:model="subject"
                            type="text"
                            class="auth-input"
                            placeholder="{{ __('Ex : Problème avec ma facture') }}"
                        />
                        @error('subject')
                            <p class="auth-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="auth-label">
                        <span>{{ __('Catégorie') }}</span>
                        <x-select-native>
                            <select wire:model="category" class="auth-select">
                                <option value="">{{ __('Choisir une catégorie') }}</option>
                                @foreach (['Compte & accès', 'Clients', 'Factures', 'Devis', 'Recouvrement', 'Trésorerie', 'Paramètres', 'Autre'] as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </x-select-native>
                        @error('category')
                            <p class="auth-error">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <label class="auth-label">
                    <span>{{ __('Message') }}</span>
                    <textarea
                        wire:model="message"
                        rows="5"
                        class="auth-textarea"
                        placeholder="{{ __('Décrivez votre demande ou le problème rencontré...') }}"
                    ></textarea>
                    @error('message')
                        <p class="auth-error">{{ $message }}</p>
                    @enderror
                </label>

                <div class="flex items-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-3 text-sm text-slate-500">
                    <flux:icon name="paper-clip" class="size-4 shrink-0" />
                    {{ __('Pièce jointe — disponible prochainement') }}
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                    <p class="text-sm text-slate-500">{{ __('Notre équipe vous répond généralement sous 24 à 48 heures ouvrées.') }}</p>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-white shadow-[0_8px_24px_rgba(2,77,77,0.18)] transition hover:bg-primary/90"
                    >
                        <flux:icon name="paper-airplane" class="size-4" />
                        {{ __('Envoyer ma demande') }}
                    </button>
                </div>

            </form>
        @endif
    </section>

    {{-- État des services + Demandes récentes --}}
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- État des services --}}
        <div class="app-shell-panel p-6">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('État des services') }}</h3>
            <div class="mt-5 space-y-3">
                @foreach ([__('Fayeku PME'), __('Facturation'), __('Recouvrement automatique'), __('Notifications')] as $service)
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50/60 px-4 py-3">
                        <span class="text-sm font-medium text-slate-700">{{ $service }}</span>
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            {{ __('Opérationnel') }}
                        </span>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-slate-500">
                {{ __('Dernière vérification :') }} {{ now()->locale('fr_FR')->translatedFormat('j F Y à H:i') }}
            </p>
        </div>

        {{-- Demandes récentes --}}
        <div id="requests-section" class="app-shell-panel p-6">
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Mes demandes récentes') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Retrouvez l\'historique de vos échanges avec notre équipe.') }}</p>

            @if (empty($requests))
                <div class="mt-6 flex flex-col items-center justify-center py-8 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                        <flux:icon name="inbox" class="size-5 text-slate-500" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-500">{{ __('Aucune demande pour le moment.') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Vos demandes de support apparaîtront ici une fois envoyées.') }}</p>
                </div>
            @else
                <div class="mt-5 divide-y divide-slate-100">
                    @foreach ($requests as $request)
                        <div class="flex items-start gap-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">{{ $request['subject'] }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $request['category'] }} · {{ $request['date'] }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                <span class="size-1.5 rounded-full bg-amber-500"></span>
                                {{ $request['status'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </section>

</div>

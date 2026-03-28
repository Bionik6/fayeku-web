<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aide & Support')] class extends Component {

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
                placeholder="{{ __('Rechercher une question, un export, une alerte...') }}"
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
                <p class="mt-0.5 text-sm text-slate-500">{{ __('Découvrez les étapes clés pour utiliser Fayeku Compta.') }}</p>
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
            <p class="mt-1 text-sm text-slate-500">{{ __('Les réponses aux questions les plus courantes sur Fayeku Compta.') }}</p>

            @php
                $faqs = [
                    [
                        'q' => __('Comment exporter mes écritures comptables ?'),
                        'a' => __('Rendez-vous dans la section "Export Groupé", sélectionnez vos clients et la période souhaitée, puis choisissez le format compatible avec votre logiciel (Sage 100, EBP…). Cliquez sur "Générer l\'export" pour télécharger le fichier.'),
                    ],
                    [
                        'q' => __('Pourquoi un client est-il marqué comme critique ?'),
                        'a' => __('Un client est marqué comme critique lorsqu\'il dépasse le seuil d\'impayés configuré dans vos paramètres. Vous pouvez consulter la fiche du client pour voir le détail des factures en retard.'),
                    ],
                    [
                        'q' => __('Comment fonctionne le programme partenaire ?'),
                        'a' => __('Le programme partenaire vous permet de gagner des commissions en recommandant Fayeku à d\'autres cabinets. Consultez la section "Commissions" pour suivre votre niveau, vos filleuls et vos versements.'),
                    ],
                    [
                        'q' => __('Comment retrouver un export déjà généré ?'),
                        'a' => __('Les exports précédents sont accessibles dans la section "Export Groupé", onglet "Historique". Vous pouvez les filtrer par date ou par client et les retélécharger à tout moment.'),
                    ],
                    [
                        'q' => __('Comment modifier le format de mes exports ?'),
                        'a' => __('Rendez-vous dans Paramètres > Profil du cabinet, puis modifiez vos préférences d\'export comptable (format, plan comptable, séparateur…). Les changements s\'appliquent aux prochains exports.'),
                    ],
                    [
                        'q' => __('Comment archiver un client ?'),
                        'a' => __('Depuis la liste des clients, ouvrez la fiche du client souhaité et cliquez sur "Actions" puis "Archiver". Le client ne sera plus visible dans la liste principale mais reste accessible via les filtres.'),
                    ],
                    [
                        'q' => __('Comment configurer mes notifications ?'),
                        'a' => __('Dans Paramètres > Compte & sécurité, vous trouverez les préférences de notifications. Vous pouvez activer ou désactiver les alertes par e-mail ou SMS selon vos besoins.'),
                    ],
                    [
                        'q' => __('Comment relancer une PME en attente ?'),
                        'a' => __('Depuis la fiche client ou la liste des alertes, sélectionnez le client concerné et cliquez sur "Relancer". Vous pouvez personnaliser le message de relance avant de l\'envoyer.'),
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
            <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Guides Fayeku Compta') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Suivez les principales étapes pour bien utiliser votre espace cabinet.') }}</p>

            @php
                $guides = [
                    [
                        'title' => __('Démarrer avec Fayeku Compta'),
                        'desc' => __('Configurer votre cabinet et prendre en main les principaux écrans.'),
                        'icon' => 'rocket-launch',
                        'color' => 'text-primary',
                        'bg' => 'bg-teal-50',
                        'steps' => [
                            __('Complétez votre profil de cabinet dans Paramètres > Profil du cabinet (nom, adresse, NINEA, RCCM).'),
                            __('Ajoutez vos premiers clients via la section Clients, ou importez-les en masse depuis votre liste existante.'),
                            __('Configurez votre format d\'export par défaut dans Paramètres > Export comptable (Sage 100, EBP…).'),
                            __('Explorez le Dashboard pour suivre les indicateurs clés : montants en attente, clients critiques, nouvelles inscriptions.'),
                            __('Activez les alertes pour être notifié automatiquement des impayés critiques et des changements de statut.'),
                        ],
                    ],
                    [
                        'title' => __('Comprendre les alertes'),
                        'desc' => __('Identifier les impayés critiques, les clients à surveiller et les nouvelles inscriptions.'),
                        'icon' => 'bell-alert',
                        'color' => 'text-rose-500',
                        'bg' => 'bg-rose-50',
                        'steps' => [
                            __('Les alertes critiques signalent des clients dont les impayés dépassent le seuil configuré dans vos paramètres.'),
                            __('Les alertes "à surveiller" concernent des retards modérés qui méritent un suivi attentif.'),
                            __('Les nouvelles inscriptions vous informent des PME qui viennent de rejoindre Fayeku et sont à affecter.'),
                            __('Depuis la liste des alertes, cliquez sur "Actions" pour relancer un client, consulter sa fiche ou générer un export.'),
                            __('Ajustez vos seuils d\'alerte dans Paramètres > Export comptable selon votre politique de recouvrement.'),
                        ],
                    ],
                    [
                        'title' => __('Exporter vers Sage 100 ou EBP'),
                        'desc' => __('Générer un export comptable compatible avec votre logiciel.'),
                        'icon' => 'arrow-up-tray',
                        'color' => 'text-violet-500',
                        'bg' => 'bg-violet-50',
                        'steps' => [
                            __('Rendez-vous dans la section Export Groupé depuis le menu principal.'),
                            __('Sélectionnez les clients à inclure dans l\'export (un seul, plusieurs, ou tout le portefeuille).'),
                            __('Choisissez la période comptable : mois en cours, mois précédent, trimestre ou plage personnalisée.'),
                            __('Sélectionnez le format de sortie : Sage 100 Comptabilité, EBP Comptabilité, ou CSV générique.'),
                            __('Cliquez sur "Générer l\'export". Le fichier est téléchargé automatiquement et disponible dans l\'historique.'),
                        ],
                    ],
                    [
                        'title' => __('Gérer vos clients'),
                        'desc' => __('Suivre votre portefeuille, consulter les fiches clients et analyser les retards.'),
                        'icon' => 'users',
                        'color' => 'text-amber-500',
                        'bg' => 'bg-amber-50',
                        'steps' => [
                            __('La liste des clients affiche l\'ensemble de votre portefeuille avec leur statut (critique, à surveiller, sain).'),
                            __('Utilisez les filtres en haut de page pour trier par statut, montant en attente ou date d\'inscription.'),
                            __('Cliquez sur un client pour accéder à sa fiche détaillée : factures, historique de paiements et alertes actives.'),
                            __('Depuis la fiche client, vous pouvez relancer par SMS ou e-mail, archiver le client ou générer un export individuel.'),
                            __('Les clients archivés restent accessibles via le filtre "Archivés" mais n\'apparaissent plus dans la vue principale.'),
                        ],
                    ],
                    [
                        'title' => __('Suivre vos commissions'),
                        'desc' => __('Comprendre votre niveau partenaire, vos commissions et vos versements.'),
                        'icon' => 'banknotes',
                        'color' => 'text-emerald-500',
                        'bg' => 'bg-emerald-50',
                        'steps' => [
                            __('La section Commissions affiche votre niveau partenaire actuel et les conditions du niveau suivant.'),
                            __('Chaque filleul actif génère une commission mensuelle dont le taux dépend de votre niveau.'),
                            __('Suivez le tableau de vos filleuls pour voir leur statut (actif, en attente, expiré) et les commissions associées.'),
                            __('Les versements sont effectués automatiquement selon le calendrier mensuel de paiement.'),
                            __('Consultez l\'historique des versements pour voir le détail de chaque paiement et les filleuls inclus.'),
                        ],
                    ],
                    [
                        'title' => __('Configurer vos paramètres comptables'),
                        'desc' => __('Définir votre format d\'export, votre plan comptable et vos préférences.'),
                        'icon' => 'cog-6-tooth',
                        'color' => 'text-slate-500',
                        'bg' => 'bg-slate-100',
                        'steps' => [
                            __('Dans Paramètres > Profil du cabinet, complétez vos informations légales : NINEA, RCCM, adresse fiscale.'),
                            __('Dans Paramètres > Export comptable, sélectionnez le format par défaut compatible avec votre logiciel.'),
                            __('Configurez les préfixes de comptes clients et fournisseurs selon votre plan comptable SYSCOHADA.'),
                            __('Définissez le séparateur de champs (point-virgule, tabulation) et le format de date pour vos fichiers d\'export.'),
                            __('Enregistrez vos modifications. Elles s\'appliquent immédiatement à tous les prochains exports générés.'),
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
                            placeholder="{{ __('Ex : Problème avec mon export Sage 100') }}"
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
                                @foreach (['Compte & accès', 'Clients', 'Alertes', 'Export comptable', 'Commissions', 'Facturation', 'Paramètres', 'Autre'] as $cat)
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
                @foreach ([__('Fayeku Compta'), __('Export comptable'), __('Commissions & paiements'), __('Notifications')] as $service)
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

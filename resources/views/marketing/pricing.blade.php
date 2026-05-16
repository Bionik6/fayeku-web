<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

@php
    $plans = [
        [
            'slug' => 'basique',
            'name' => 'Basique',
            'eyebrow' => 'Facturez proprement',
            'tagline' => 'Pour structurer votre facturation et démarrer votre activité.',
            'price' => '10 000',
            'price_yearly' => '100 000',
            'unit' => 'FCFA/mois HT',
            'unit_yearly' => 'FCFA/an HT',
            'note' => 'Tout ce qu\'il faut pour démarrer proprement',
            'cta_label' => 'Essayer 30 jours',
            'cta_href' => route('register'),
            'cta_variant' => 'secondary',
            'popular' => false,
            'limits' => [
                ['label' => 'Documents', 'sublabel' => 'Devis · Proformas · Factures', 'value' => '30 / mois'],
                ['label' => 'Clients', 'value' => 'Illimités'],
                ['label' => 'Utilisateurs', 'value' => '1'],
            ],
            'features' => [
                'Devis, proformas et factures',
                'Suivi des impayés en temps réel',
                'Relances <strong>manuelles</strong> (WhatsApp, email)',
                'Templates personnalisables (logo, NINEA)',
                'Accès Fayeku Compta pour votre comptable',
                'Support email · 24h ouvrées',
            ],
        ],
        [
            'slug' => 'essentiel',
            'name' => 'Essentiel',
            'eyebrow' => 'Contrôlez votre trésorerie',
            'tagline' => 'Pour automatiser vos relances et piloter votre cash.',
            'price' => '20 000',
            'price_yearly' => '200 000',
            'unit' => 'FCFA/mois HT',
            'unit_yearly' => 'FCFA/an HT',
            'note' => 'Utilisateur supplémentaire : 3 000 FCFA/mois',
            'cta_label' => 'Essayer 30 jours',
            'cta_href' => route('register'),
            'cta_variant' => 'primary',
            'popular' => true,
            'highlight' => [
                'title' => 'Sans limite de volume',
                'subtitle' => 'Documents (devis, proformas, factures) et clients illimités',
            ],
            'lead' => 'Tout Basique inclus, plus :',
            'features' => [
                '<strong>3 utilisateurs inclus</strong>',
                'Relances WhatsApp automatiques · J-3 à J+60',
                'Scoring de fiabilité client · délai moyen, retards',
                'Prévision de trésorerie 90 jours',
                'Devis avec signature électronique <span class="opacity-70">(bientôt)</span>',
                'Multi-devises (FCFA, EUR, USD)',
                'Conformité DGID dès disponibilité officielle',
                'Accès Fayeku Compta inclus',
                'Support WhatsApp prioritaire · 4h ouvrées',
            ],
        ],
        [
            'slug' => 'entreprise',
            'name' => 'Entreprise',
            'eyebrow' => 'Gérez votre groupe',
            'tagline' => 'Pour les sociétés qui doivent piloter plusieurs entités, intégrer leur SI et structurer leurs validations.',
            'price_prefix' => 'À partir de',
            'price' => '70 000',
            'price_yearly' => '700 000',
            'unit' => 'FCFA/mois HT',
            'unit_yearly' => 'FCFA/an HT',
            'note' => 'Sur devis · Utilisateur supplémentaire : 2 000 FCFA/mois',
            'cta_label' => 'Nous contacter',
            'cta_href' => route('marketing.contact'),
            'cta_variant' => 'secondary',
            'popular' => false,
            'highlight' => [
                'title' => 'Sans limite de volume',
                'subtitle' => 'Jusqu\'à 25 utilisateurs inclus',
            ],
            'lead' => 'Tout Essentiel inclus, plus :',
            'features' => [
                '<strong>Multi-entités</strong> · gérez plusieurs sociétés depuis un compte',
                '<strong>Workflows de validation</strong> · circuits d\'approbation par seuil et par rôle',
                'Rôles &amp; permissions avancés',
                '<strong>API + Webhooks</strong> · intégrez Fayeku à votre SI',
                '<strong>SSO</strong> · Azure AD, Google Workspace',
                'Account Manager dédié · SLA 99,5 %',
                'Migration depuis votre ancien système incluse',
            ],
        ],
    ];

    $comparePlans = ['Basique', 'Essentiel', 'Entreprise'];

    $compareSections = [
        [
            'title' => "Limites mensuelles",
            'rows' => [
                ['Documents (devis, proformas, factures) / mois', '30',          'Illimités',   'Illimités'],
                ['Clients',                                       'Illimités',   'Illimités',   'Illimités'],
                ['Utilisateurs',                                  '1',           '3 (+3k/util.)', '25 inclus (+2k/util.)'],
                ['Historique clients',                            '✓',           '✓',           '✓'],
            ],
        ],
        [
            'title' => 'Facturation & devis',
            'rows' => [
                ['Création de factures',                    '✓', '✓', '✓'],
                ['Templates personnalisables (logo, NINEA)','✓', '✓', '✓'],
                ['Devis & proformas basiques',              '✓', '✓', '✓'],
                ['Conversion devis → facture automatique',  '—', '✓', '✓'],
                ['Facturation récurrente automatique',      '—', '✓', '✓'],
                ['Multi-devises (FCFA, EUR, USD)',          '—', '✓', '✓'],
                ['Bons de commande',                        '—', '✓', '✓'],
                ['Conformité DGID (dès disponibilité)',     '—', '✓', '✓'],
            ],
        ],
        [
            'title' => 'Recouvrement & paiement',
            'rows' => [
                ['Marquer facture payée (Wave / OM / Cash)',          '✓', '✓', '✓'],
                ['Dashboard impayés temps réel',                      '✓', '✓', '✓'],
                ['Relances manuelles (WhatsApp, email)',              '✓', '✓', '✓'],
                ['Relances WhatsApp automatiques · J-3 à J+60',       '—', '✓', '✓'],
                ['Calendrier complet J-3 → J+60 configurable',        '—', '✓', '✓'],
                ['Scoring de fiabilité client',                       '—', '✓', '✓'],
            ],
        ],
        [
            'title' => 'Pilotage & analytics',
            'rows' => [
                ['Historique paiements clients',          '✓', '✓', '✓'],
                ['Délai moyen de paiement',               '✓', '✓', '✓'],
                ['Rapports avancés (CA, top clients)',    '—', '✓', '✓'],
                ['Trésorerie prévisionnelle 90 jours',    '—', '✓', '✓'],
                ['Niveau de confiance par facture',       '—', '✓', '✓'],
                ['Exposition au risque par client',       '—', '✓', '✓'],
            ],
        ],
        [
            'title' => 'Collaboration comptable',
            'rows' => [
                ['Accès Fayeku Compta',                       'Inclus', 'Inclus', 'Inclus'],
                ['Lecture factures temps réel',               '✓', '✓', '✓'],
                ['Collecte automatique des pièces',           '—', '✓', '✓'],
                ['Export Sage 100 / EBP / Excel',             '✓', '✓', '✓'],
                ['Exports comptables programmés',             '—', '—', '✓'],
                ['Gestion de plusieurs comptables',           '—', '—', '✓'],
            ],
        ],
        [
            'title' => 'Gouvernance & intégrations',
            'rows' => [
                ['Multi-entités (plusieurs sociétés)',           '—', '—', '✓'],
                ['Workflows de validation par seuil et rôle',    '—', '—', '✓'],
                ['Rôles & permissions avancés',                  '—', '—', '✓'],
                ['API + Webhooks',                               '—', '—', '✓'],
                ['SSO (Azure AD, Google Workspace)',             '—', '—', '✓'],
            ],
        ],
        [
            'title' => 'Support & accompagnement',
            'rows' => [
                ['Support',                          'Email 24h ouvrées', 'WhatsApp prioritaire · 4h ouvrées', 'Account manager dédié'],
                ['Onboarding',                       'Guides',    'Guidé en ligne (1h visio)', 'Accompagnement dédié'],
                ['Migration depuis ancien système',  '—',         '—',             '✓'],
                ['SLA garanti (uptime 99,5 %)',      '—',         '—',             '✓'],
                ['Support téléphonique direct',      '—',         '—',             '✓'],
            ],
        ],
    ];

    $testimonials = [
        [
            'quote' => "Nous avons réduit notre délai moyen de paiement de 38 à 21 jours en deux mois. Les relances WhatsApp et email font le travail à notre place — nos clients paient sans qu'on ait à insister.",
            'name' => 'Awa Diop',
            'role' => 'Dirigeante · Atelier Numérique SARL',
            'logo' => 'AN',
            'plan' => 'Essentiel',
        ],
        [
            'quote' => "Le score de fiabilité client et la prévision de trésorerie ont changé notre façon de piloter. On sait quels impayés relancer en priorité, et le cash arrive avant la fin du mois.",
            'name' => 'Mamadou Ndiaye',
            'role' => 'CFO · Sayar Distribution',
            'logo' => 'SD',
            'plan' => 'Essentiel',
        ],
        [
            'quote' => "Fayeku Compta nous donne accès aux factures de toutes nos PME clientes en temps réel. Les exports Sage 100 nous font gagner deux journées par mois sur la clôture.",
            'name' => 'Ousmane Sarr',
            'role' => 'Expert-comptable · Cabinet Sarr & Associés',
            'logo' => 'SA',
            'plan' => 'Partenaire',
        ],
    ];

    $pricingFaq = [
        ['q' => "Que se passe-t-il à la fin de mes 30 jours d'essai ?", 'a' => "Vous choisissez votre plan. Aucun prélèvement automatique tant que vous n'avez pas confirmé."],
        ['q' => "Puis-je changer de plan à tout moment ?", 'a' => "Oui. Vous pouvez passer à un plan supérieur ou inférieur depuis vos paramètres. Le prorata est calculé automatiquement."],
        ['q' => "Y a-t-il vraiment des limites cachées sur Essentiel et Entreprise ?", 'a' => "Non. Documents et clients sont sans limite. Un fair use s'applique pour prévenir les abus (mention en CGU), mais aucune PME en usage normal n'est concernée."],
        ['q' => "Y a-t-il un engagement ?", 'a' => "Non. Vous pouvez résilier à tout moment depuis votre tableau de bord. Aucun engagement contractuel multi-mois."],
        ['q' => "Quelle est la différence entre Basique et Essentiel ?", 'a' => "Basique gère vos factures avec des relances <strong>manuelles</strong> (WhatsApp, email). Essentiel ajoute les relances WhatsApp <strong>automatiques</strong> (J-3 à J+60), le scoring de fiabilité client, la prévision de trésorerie 90 jours et le support prioritaire."],
        ['q' => "Combien d'utilisateurs sont inclus ?", 'a' => "Basique : 1 utilisateur. Essentiel : 3 utilisateurs (supplémentaire 3 000 FCFA/mois). Entreprise : jusqu'à 25 utilisateurs inclus (supplémentaire 2 000 FCFA/mois)."],
        ['q' => "Comment payer ?", 'a' => "Wave, Orange Money, virement bancaire. Paiement mensuel ou annuel (2 mois offerts à l'année). Pas de frais de setup."],
        ['q' => "Et la conformité DGID ?", 'a' => "Fayeku intégrera nativement la conformité DGID dans les plans Essentiel et Entreprise dès publication officielle, sans surcoût."],
    ];
@endphp

{{-- Hero --}}
<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-8 sm:pt-14 sm:pb-12 lg:pt-24 lg:pb-16 text-center relative">
        <p class="eyebrow mb-4">Tarifs</p>
        <h1 class="h1 mb-6 max-w-3xl mx-auto">Découvrez les offres <span style="color: var(--color-teal-fayeku);">Fayeku.</span></h1>
        <p class="text-lg max-w-2xl mx-auto leading-relaxed" style="color: var(--color-marketing-slate);">
            Sans engagement, résiliable à tout moment. 30 jours d'essai sur tous les plans payants, sans paiement préalable.
        </p>
    </div>
</section>

{{-- Plans --}}
<section class="pb-12 sm:pb-16 lg:pb-24" x-data="{ annual: false }">
    {{-- Intro framing --}}
    <div class="max-w-3xl mx-auto px-5 lg:px-8 mb-8 text-center">
        <p class="text-lg font-semibold" style="color: var(--color-marketing-ink);">Choisissez votre plan selon votre besoin, pas selon votre volume.</p>
        <p class="text-sm mt-2" style="color: var(--color-marketing-slate);">Tous les plans donnent accès à Fayeku Compta pour votre comptable.</p>
    </div>

    {{-- Billing toggle --}}
    <div class="max-w-7xl mx-auto px-5 lg:px-8 mb-10 flex justify-center">
        <div class="inline-flex items-center gap-1 p-1 rounded-full border border-gray-200 bg-white shadow-sm" role="tablist" aria-label="Choisir la périodicité">
            <button type="button" role="tab" :aria-selected="!annual" @click="annual = false"
                class="px-5 py-2 rounded-full text-sm font-semibold transition"
                :class="!annual ? 'shadow-sm' : 'hover:bg-gray-50'"
                :style="!annual ? 'background: var(--color-teal-fayeku); color: #ffffff' : 'color: var(--color-marketing-slate)'">
                Mensuel
            </button>
            <button type="button" role="tab" :aria-selected="annual" @click="annual = true"
                class="px-5 py-2 rounded-full text-sm font-semibold transition inline-flex items-center gap-2"
                :class="annual ? 'shadow-sm' : 'hover:bg-gray-50'"
                :style="annual ? 'background: var(--color-teal-fayeku); color: #ffffff' : 'color: var(--color-marketing-slate)'">
                Annuel
                <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full"
                    :style="annual ? 'background: rgba(255,255,255,0.18); color: #ffffff' : 'background: var(--color-mint-100); color: var(--color-vivid)'">
                    2 mois offerts
                </span>
            </button>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            <article class="card p-7 flex flex-col relative reveal-scale delay-{{ $loop->index + 1 }} {{ $plan['popular'] ? 'lg:-mt-4 lg:mb-0' : '' }}" @if ($plan['popular']) style="box-shadow: 0 0 0 2px var(--color-vivid), var(--shadow-card);" @endif>
                @if ($plan['popular'])
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 text-white text-[10px] font-semibold tracking-widest uppercase px-3 py-1 rounded-full whitespace-nowrap" style="background: var(--color-vivid);">Le plus populaire</div>
                @endif

                <div class="eyebrow mb-3">{{ $plan['eyebrow'] ?? $plan['name'] }}</div>
                <h3 class="text-3xl sm:text-4xl font-bold mb-2 tracking-tight">{{ $plan['name'] }}</h3>
                <p class="text-sm mb-6" style="color: var(--color-marketing-slate);">{{ $plan['tagline'] }}</p>

                <div class="mb-6 pb-6 border-b border-gray-100">
                    @if (! empty($plan['price_prefix']))
                        <div class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: var(--color-marketing-slate);">{{ $plan['price_prefix'] }}</div>
                    @endif
                    <div class="flex items-baseline gap-1">
                        @if (! empty($plan['price_yearly']))
                            <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink); word-spacing: -0.3em;" x-text="annual ? '{{ $plan['price_yearly'] }}' : '{{ $plan['price'] }}'">{{ $plan['price'] }}</span>
                        @else
                            <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink); word-spacing: -0.3em;">{{ $plan['price'] }}</span>
                        @endif
                    </div>
                    @if (! empty($plan['unit_yearly']))
                        <div class="text-sm mt-1" style="color: var(--color-marketing-slate);" x-text="annual ? '{{ $plan['unit_yearly'] }}' : '{{ $plan['unit'] }}'">{{ $plan['unit'] }}</div>
                    @else
                        <div class="text-sm mt-1" style="color: var(--color-marketing-slate);">{{ $plan['unit'] }}</div>
                    @endif
                    @if (! empty($plan['price_yearly']))
                        <div class="text-xs mt-1" style="color: var(--color-vivid); font-weight: 600;" x-show="annual" x-cloak>2 mois offerts vs mensuel</div>
                    @endif
                    <div class="text-xs mt-2" style="color: var(--color-marketing-slate);">{!! $plan['note'] !!}</div>
                </div>

                @if (! empty($plan['limits']))
                    <div class="rounded-2xl p-4 mb-6" style="background: var(--color-mint-50);">
                        <p class="text-[10px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-vivid);">Limites mensuelles</p>
                        <ul class="space-y-3 text-sm">
                            @foreach ($plan['limits'] as $limit)
                                <li>
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span style="color: var(--color-marketing-ink);">{!! $limit['label'] !!}</span>
                                        <span class="font-semibold tabular-nums whitespace-nowrap" style="color: var(--color-marketing-ink);">{!! $limit['value'] !!}</span>
                                    </div>
                                    @if (! empty($limit['sublabel']))
                                        <p class="text-[14px] mt-1" style="color: var(--color-marketing-slate);">{{ $limit['sublabel'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif (! empty($plan['highlight']))
                    <div class="rounded-2xl p-4 mb-6 text-center" style="background: var(--color-mint-100);">
                        <p class="text-sm font-semibold leading-tight" style="color: var(--color-marketing-ink);">{{ $plan['highlight']['title'] }}</p>
                        <p class="text-[14px] mt-1.5" style="color: var(--color-marketing-slate);">{{ $plan['highlight']['subtitle'] }}</p>
                    </div>
                @endif

                @if (! empty($plan['lead']))
                    <p class="text-sm font-semibold mb-4" style="color: var(--color-vivid);">{!! $plan['lead'] !!}</p>
                @endif

                <ul class="space-y-2.5 text-sm mb-8" style="color: var(--color-marketing-ink);">
                    @foreach ($plan['features'] as $feature)
                        <li class="flex items-start gap-2"><span class="check"></span><span>{!! $feature !!}</span></li>
                    @endforeach
                </ul>

                <a href="{{ $plan['cta_href'] }}" class="{{ $plan['cta_variant'] === 'primary' ? 'btn-primary' : 'btn-secondary' }} justify-center mt-auto">{{ $plan['cta_label'] }}</a>
            </article>
        @endforeach
    </div>

    <p class="text-center text-sm mt-8" style="color: var(--color-marketing-slate);">
        Chaque plan payant inclut <strong style="color: var(--color-marketing-ink);">30 jours d'essai</strong>, sans paiement préalable. Annuel : <strong style="color: var(--color-marketing-ink);">2 mois offerts</strong> (−16,7 %).
    </p>
</section>


{{-- Cabinet promo --}}
<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="border rounded-3xl px-6 sm:px-8 lg:px-10 py-8 flex flex-col lg:flex-row items-start lg:items-center gap-6" style="background: var(--color-mint-50); border-color: var(--color-mint-200);">
            <div class="shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center" style="background: var(--color-teal-fayeku);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color: var(--color-vivid);">Fayeku Cabinet · 0 FCFA</p>
                <p class="leading-relaxed" style="color: var(--color-marketing-slate);">L'espace dédié aux experts-comptables est gratuit et le restera, peu importe le plan de leurs clients PME. Multi-clients, exports Sage / EBP, coffre-fort de pièces, programme partenaire 15 %.</p>
            </div>
            <a href="{{ route('marketing.accountants') }}" class="inline-flex items-center gap-2 bg-white border rounded-full px-5 py-3 text-sm font-medium whitespace-nowrap shadow-sm" style="border-color: var(--color-mint-200); color: var(--color-marketing-ink);">Découvrir<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
        </div>
    </div>
</section>

{{-- Comparaison détaillée --}}
<section class="pb-12 sm:pb-16 lg:pb-24" x-data="{ open: 0 }">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-10">
            <p class="eyebrow mb-3">Comparatif détaillé</p>
            <h2 class="h2 mb-3">Comparer toutes les fonctionnalités.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Cliquez sur une catégorie pour la déplier.</p>
        </div>

        <div class="card divide-y divide-gray-100 overflow-hidden">
            @foreach ($compareSections as $i => $section)
                <div>
                    <button
                        @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                        class="w-full flex items-center justify-between gap-4 text-left py-5 px-6 hover:bg-[color:var(--color-mint-50)] transition ringfx"
                        :aria-expanded="open === {{ $i }}"
                        type="button">
                        <span class="font-semibold text-lg" style="color: var(--color-marketing-ink);">{{ $section['title'] }}</span>
                        <svg :class="open === {{ $i }} ? 'rotate-180' : ''" class="w-5 h-5 transition-transform shrink-0" style="color: var(--color-marketing-slate);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead style="background: var(--color-mint-50);">
                                    <tr>
                                        <th class="text-left p-4 font-semibold" style="color: var(--color-marketing-ink);">Fonctionnalité</th>
                                        @foreach ($comparePlans as $plan)
                                            <th class="p-4 font-semibold text-center" style="color: var(--color-marketing-ink);">{{ $plan }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($section['rows'] as $row)
                                        <tr>
                                            <td class="p-4" style="color: var(--color-marketing-slate);">{{ $row[0] }}</td>
                                            @for ($c = 1; $c <= 3; $c++)
                                                <td class="p-4 text-center font-medium {{ $c === 2 ? 'bg-[color:var(--color-mint-50)]/40' : '' }}">{{ $row[$c] }}</td>
                                            @endfor
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Testimonials --}}
<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-10">
            <p class="eyebrow mb-3">Ils nous font confiance</p>
            <h2 class="h2 mb-3">Ils sont dirigeants, DAF, experts-comptables et ils recommandent Fayeku.</h2>
        </div>

        <div class="grid md:grid-cols-3 gap-5">
            @foreach ($testimonials as $t)
                <article class="card p-7 flex flex-col">
                    <svg class="mb-5" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
                    <p class="text-base leading-relaxed mb-6" style="color: var(--color-marketing-ink);">« {{ $t['quote'] }} »</p>
                    <div class="flex items-center gap-3 mt-auto pt-5 border-t border-gray-100">
                        <div class="w-10 h-10 rounded-full font-bold text-sm flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $t['logo'] }}</div>
                        <div class="leading-tight">
                            <div class="text-sm font-semibold" style="color: var(--color-marketing-ink);">{{ $t['name'] }}</div>
                            <div class="text-xs" style="color: var(--color-marketing-slate);">{{ $t['role'] }}</div>
                            <div class="text-[10px] font-semibold tracking-wider uppercase mt-1" style="color: var(--color-vivid);">Plan {{ $t['plan'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- FAQ --}}
<section class="pb-12 sm:pb-16 lg:pb-24" x-data="{ open: null }">
    <div class="max-w-4xl mx-auto px-5 lg:px-8">
        <div class="mb-10 text-center">
            <p class="eyebrow mb-3">FAQ Tarifs</p>
            <h2 class="h2 mb-3">Questions fréquentes.</h2>
        </div>
        <div class="card divide-y divide-gray-100">
            @foreach ($pricingFaq as $i => $item)
                <div>
                    <button
                        @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                        class="w-full flex items-center justify-between gap-4 text-left py-5 px-6 hover:bg-[color:var(--color-mint-50)] transition ringfx rounded-2xl"
                        :aria-expanded="open === {{ $i }}"
                        type="button">
                        <span class="font-medium" style="color: var(--color-marketing-ink);">{{ $item['q'] }}</span>
                        <svg :class="open === {{ $i }} ? 'rotate-45' : ''" :style="open === {{ $i }} ? 'color: var(--color-vivid);' : 'color: var(--color-marketing-slate);'" class="w-5 h-5 transition-transform shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-6 pb-5 -mt-1 leading-relaxed" style="color: var(--color-marketing-slate);">{{ $item['a'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

</x-layouts.marketing>

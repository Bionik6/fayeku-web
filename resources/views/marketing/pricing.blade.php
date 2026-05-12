<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

@php
    $plans = [
        [
            'slug' => 'basique',
            'name' => 'Basique',
            'tagline' => 'Pour facturer proprement et démarrer.',
            'price' => '10 000',
            'unit' => 'FCFA/mois HT',
            'note' => 'Jusqu\'à 1 utilisateur',
            'cta_label' => 'Essayer 30 jours',
            'cta_href' => route('register'),
            'cta_variant' => 'secondary',
            'popular' => false,
            'features' => [
                'Factures illimitées',
                'Devis & factures récurrentes basiques',
                'Suivi des impayés en temps réel',
                '20 relances WhatsApp automatiques / mois',
                'Historique clients · 100 max',
                'Accès Fayeku Cabinet activable',
                'Templates personnalisables (logo, NINEA)',
                'Support email · 48h',
            ],
        ],
        [
            'slug' => 'essentiel',
            'name' => 'Essentiel',
            'tagline' => 'Pour automatiser vos relances et piloter votre cash.',
            'price' => '20 000',
            'unit' => 'FCFA/mois HT',
            'note' => 'Jusqu\'à 3 utilisateurs',
            'cta_label' => 'Essayer 30 jours',
            'cta_href' => route('register'),
            'cta_variant' => 'primary',
            'popular' => true,
            'features' => [
                'Tout Basique inclus',
                'Relances WhatsApp automatiques illimitées',
                'Relance intelligente IA · message pré-rédigé',
                'Score fiabilité client',
                'Prévision de trésorerie 90 jours',
                'Devis signature électronique · conversion auto',
                'Multi-devises (FCFA, EUR, USD)',
                'Conformité DGID (dès disponibilité)',
                'Accès Fayeku Cabinet inclus',
                'Support WhatsApp · &lt; 4h',
            ],
        ],
        [
            'slug' => 'entreprise',
            'name' => 'Entreprise',
            'tagline' => 'Pour les groupes et structures établies.',
            'price' => 'À partir de 70 000',
            'unit' => 'FCFA/mois HT',
            'note' => 'Utilisateurs illimités + rôles',
            'cta_label' => 'Nous contacter',
            'cta_href' => route('marketing.contact'),
            'cta_variant' => 'secondary',
            'popular' => false,
            'features' => [
                'Tout Essentiel inclus',
                'Multi-entités (plusieurs sociétés)',
                'Workflows de validation interne',
                'API publique (REST + webhooks)',
                'SSO (Google Workspace, M365, SAML)',
                'Audit log complet · reporting consolidé',
                'Account manager dédié · SLA 99,9 %',
                'Onboarding présentiel · 3 sessions de formation',
                'Migration depuis ancien système',
            ],
        ],
    ];

    $comparePlans = ['Gratuit', 'Basique', 'Essentiel', 'Entreprise'];

    $compareSections = [
        [
            'title' => "Limites d'usage",
            'rows' => [
                ['Factures / mois',                  '3',         'Illimitées',  'Illimitées',  'Illimitées'],
                ['Clients',                          '3 max',     '100 max',     'Illimités',   'Illimités + rôles'],
                ['Utilisateurs',                     '1',         '1',           '3',           'Illimités + rôles'],
                ['Relances WhatsApp auto / mois',    '—',         '20',          'Illimitées',  'Illimitées'],
                ['Historique clients',               '—',         '✓',           '✓',           '✓'],
                ['Coffre-fort documents',            '—',         '1 Go',        '20 Go',       'Illimité'],
                ['Branding Fayeku obligatoire',      '✓',         '—',           '—',           '—'],
                ['Watermark PDF',                    '✓',         '—',           '—',           '—'],
                ["Durée de l'essai gratuit",         'Illimitée', '30 jours',    '30 jours',    '30 jours'],
            ],
        ],
        [
            'title' => 'Facturation & devis',
            'rows' => [
                ['Création de factures',                    '✓', '✓', '✓', '✓'],
                ['Templates personnalisables (logo, NINEA)','—', '✓', '✓', '✓'],
                ['Devis & proformas basiques',              '—', '✓', '✓', '✓'],
                ['Signature électronique sur devis',        '—', '—', '✓', '✓'],
                ['Conversion devis → facture automatique',  '—', '—', '✓', '✓'],
                ['Facturation récurrente automatique',      '—', '—', '✓', '✓'],
                ['Multi-devises (FCFA, EUR, USD)',          '—', '—', '✓', '✓'],
                ['Bons de commande',                        '—', '—', '✓', '✓'],
                ['Multi-entités (plusieurs sociétés)',      '—', '—', '—', '✓'],
                ['Workflows de validation interne',         '—', '—', '—', '✓'],
                ['Conformité DGID (dès disponibilité)',     '—', '—', '✓', '✓'],
            ],
        ],
        [
            'title' => 'Recouvrement & paiement',
            'rows' => [
                ['Wave link manuel dans messages',                    '✓', '✓', '✓', '✓'],
                ['Marquer facture payée (Wave / OM / Cash)',          '✓', '✓', '✓', '✓'],
                ['Dashboard impayés temps réel',                      '—', '✓', '✓', '✓'],
                ['Relances WhatsApp J+7, J+15',                       '—', '✓ 20/mois', '✓ Illimitées', '✓ Illimitées'],
                ["Rappel avant échéance J-3",                         '—', '—', '✓', '✓'],
                ['Relances J+30 et J+60 personnalisables',            '—', '—', '✓', '✓'],
                ['Calendrier complet J-3 → J+60 configurable',        '—', '—', '✓', '✓'],
                ['Wave link automatique dans chaque relance',         '—', '—', '✓', '✓'],
                ['Confirmations de paiement automatiques',            '—', '—', '✓', '✓'],
                ['Score fiabilité client',                            '—', '—', '✓', '✓'],
                ['Relance intelligente IA',                           '—', '—', '✓', '✓'],
                ['Scénarios personnalisés par segment client',        '—', '—', '—', '✓'],
            ],
        ],
        [
            'title' => 'Pilotage & analytics',
            'rows' => [
                ['Historique paiements clients',          '—', '✓', '✓', '✓'],
                ['Délai moyen de paiement',               '—', '✓', '✓', '✓'],
                ['Export Excel',                          '—', '✓', '✓', '✓'],
                ['Rapports avancés (CA, top clients)',    '—', '—', '✓', '✓'],
                ['Trésorerie prévisionnelle 90 jours',    '—', '—', '✓', '✓'],
                ['Niveau de confiance par facture',       '—', '—', '✓', '✓'],
                ['Exposition au risque par client',       '—', '—', '✓', '✓'],
                ['Audit log complet',                     '—', '—', '—', '✓'],
                ['Reporting consolidé groupe',            '—', '—', '—', '✓'],
                ['API publique (REST + webhooks)',        '—', '—', '—', '✓'],
                ['SSO (Google Workspace, M365, SAML)',    '—', '—', '—', '✓'],
            ],
        ],
        [
            'title' => 'Collaboration comptable',
            'rows' => [
                ['Accès Fayeku Cabinet',                      '—', 'Activable', 'Inclus', 'Inclus'],
                ['Lecture factures temps réel',               '—', '✓', '✓', '✓'],
                ['Collecte automatique des pièces',           '—', '—', '✓', '✓'],
                ['Export Sage 100 / EBP / Excel',             '—', '✓', '✓', '✓'],
                ['Exports comptables programmés',             '—', '—', '—', '✓'],
                ['Gestion de plusieurs comptables',           '—', '—', '—', '✓'],
            ],
        ],
        [
            'title' => 'Support & accompagnement',
            'rows' => [
                ['Support',                          '—', 'Email 48h', 'WhatsApp < 4h', 'Account manager dédié'],
                ['Onboarding',                       '—', 'Guides',    'Guidé en ligne (1h visio)', 'Présentiel Dakar'],
                ['Formation équipe incluse',         '—', '—',         '—',             '✓ (3 sessions)'],
                ['Migration depuis ancien système',  '—', '—',         '—',             '✓'],
                ['SLA garanti (uptime 99,9 %)',      '—', '—',         '—',             '✓'],
                ['Support téléphonique direct',      '—', '—',         '—',             '✓'],
            ],
        ],
    ];

    $testimonials = [
        [
            'quote' => "Nous avons réduit notre délai moyen de paiement de 38 à 21 jours en deux mois. Les relances WhatsApp font le travail à notre place — nos clients paient sans qu'on ait à insister.",
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
        ['q' => "Y a-t-il un engagement ?", 'a' => "Non. Vous pouvez résilier à tout moment depuis votre tableau de bord. Aucun engagement contractuel multi-mois."],
        ['q' => "L'essai 30 jours nécessite-t-il une carte bancaire ?", 'a' => "Non. Vous démarrez sans aucune information de paiement. À la fin de l'essai, vous basculez automatiquement sur le plan Gratuit (3 factures/mois) si vous ne choisissez pas de plan payant."],
        ['q' => "Le plan Gratuit est-il vraiment gratuit ?", 'a' => "Oui, à vie. 3 factures par mois, sans engagement, sans carte bancaire. Vos factures portent un léger watermark Fayeku."],
        ['q' => "Quelle est la différence entre Basique et Essentiel ?", 'a' => "Basique : facturation et 20 relances WhatsApp automatiques/mois. Essentiel : relances illimitées, IA, score fiabilité client, prévision trésorerie 90j, devis avec signature et conformité DGID."],
        ['q' => "Combien d'utilisateurs sont inclus ?", 'a' => "Basique : 1 utilisateur. Essentiel : 3 utilisateurs (utilisateur supplémentaire 3 000 FCFA/mois). Entreprise : utilisateurs illimités avec rôles."],
        ['q' => "Comment payer ?", 'a' => "Wave, Orange Money, virement bancaire. Paiement mensuel ou annuel (−2 mois offerts à l'année). Pas de frais de setup."],
        ['q' => "Puis-je changer de plan ?", 'a' => "Oui, à tout moment. La facturation est ajustée au prorata du jour."],
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
            Sans engagement, résiliable à tout moment. 30 jours d'essai sur tous les plans payants, sans carte bancaire.
        </p>
    </div>
</section>

{{-- Plans --}}
<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            <article class="card p-7 flex flex-col relative {{ $plan['popular'] ? 'lg:-mt-4 lg:mb-0' : '' }}" @if ($plan['popular']) style="box-shadow: 0 0 0 2px var(--color-vivid), var(--shadow-card);" @endif>
                @if ($plan['popular'])
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 text-white text-[10px] font-semibold tracking-widest uppercase px-3 py-1 rounded-full whitespace-nowrap" style="background: var(--color-vivid);">Le plus populaire</div>
                @endif

                <div class="eyebrow mb-3">{{ $plan['name'] }}</div>
                <h3 class="text-2xl font-bold mb-2">{{ $plan['name'] }}</h3>
                <p class="text-sm mb-6" style="color: var(--color-marketing-slate);">{{ $plan['tagline'] }}</p>

                <div class="mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-baseline gap-1">
                        <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink);">{{ $plan['price'] }}</span>
                    </div>
                    <div class="text-sm mt-1" style="color: var(--color-marketing-slate);">{{ $plan['unit'] }}</div>
                    <div class="text-xs mt-2" style="color: var(--color-marketing-slate);">{!! $plan['note'] !!}</div>
                </div>

                <ul class="space-y-2.5 text-sm mb-8" style="color: var(--color-marketing-ink);">
                    @foreach ($plan['features'] as $feature)
                        <li class="flex items-start gap-2"><span class="check"></span>{!! $feature !!}</li>
                    @endforeach
                </ul>

                <a href="{{ $plan['cta_href'] }}" class="{{ $plan['cta_variant'] === 'primary' ? 'btn-primary' : 'btn-secondary' }} justify-center mt-auto">{{ $plan['cta_label'] }}</a>
            </article>
        @endforeach
    </div>

    <p class="text-center text-sm mt-8" style="color: var(--color-marketing-slate);">
        Chaque plan inclut <strong style="color: var(--color-marketing-ink);">30 jours d'essai</strong>, sans carte bancaire. Annuel : <strong style="color: var(--color-marketing-ink);">2 mois offerts</strong> (−16,7 %).
    </p>
</section>

{{-- Plan Gratuit safety net --}}
<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="card p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <div class="shrink-0 w-14 h-14 rounded-2xl flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color: var(--color-vivid);">Plan Gratuit · à vie</p>
                <h3 class="text-xl font-bold mb-2" style="color: var(--color-marketing-ink);">Pas prêt à payer ? Démarrez gratuitement.</h3>
                <p class="leading-relaxed text-sm" style="color: var(--color-marketing-slate);">
                    3 factures par mois, sans engagement, sans carte bancaire. Vos factures portent un léger watermark Fayeku. Vous basculez automatiquement sur ce plan à la fin de votre essai 30 jours si vous ne choisissez pas de plan payant.
                </p>
            </div>
            <a href="{{ route('register') }}" class="btn-secondary whitespace-nowrap">Démarrer gratuitement</a>
        </div>
    </div>
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
                                            @for ($c = 1; $c <= 4; $c++)
                                                <td class="p-4 text-center font-medium {{ $c === 3 ? 'bg-[color:var(--color-mint-50)]/40' : '' }}">{{ $row[$c] }}</td>
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

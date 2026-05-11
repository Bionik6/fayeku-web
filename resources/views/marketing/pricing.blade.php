<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

<div x-data="{ p: 'monthly' }">

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-8 sm:pt-14 sm:pb-12 lg:pt-24 lg:pb-16 text-center relative">
        <p class="eyebrow mb-4">Tarifs</p>
        <h1 class="h1 mb-6 max-w-3xl mx-auto">Simple, transparent, <span style="color: var(--color-teal-fayeku);">sans surprise.</span></h1>
        <p class="text-lg max-w-2xl mx-auto mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">30 jours d'essai sur tous les plans. Sans engagement, sans carte bancaire. Payez au mois ou bénéficiez de 2 mois offerts à l'année.</p>
        <div class="inline-flex pill-toggle">
            <button type="button" @click="p='monthly'" :class="p==='monthly' && 'active'">Mensuel</button>
            <button type="button" @click="p='yearly'" :class="p==='yearly' && 'active'">Annuel · −2 mois</button>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid md:grid-cols-3 gap-5">
        <article class="card p-7 flex flex-col">
            <div class="eyebrow mb-3">Solo</div>
            <h3 class="text-2xl font-bold mb-2">Solo</h3>
            <p class="text-sm mb-5" style="color: var(--color-marketing-slate);">Pour les indépendants et TPE qui démarrent.</p>
            <div class="mb-6">
                <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink);" x-text="p === 'monthly' ? '9 900' : '99 000'">9 900</span><span class="ml-1" style="color: var(--color-marketing-slate);" x-text="p === 'monthly' ? 'FCFA/mois' : 'FCFA/an'">FCFA/mois</span>
                <div class="text-[11px] mt-1" style="color: var(--color-vivid);" x-show="p === 'yearly'" x-cloak>Soit 8 250 FCFA/mois · 2 mois offerts</div>
            </div>
            <ul class="space-y-2.5 text-sm mb-7" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-2"><span class="check"></span>50 factures/mois</li>
                <li class="flex items-start gap-2"><span class="check"></span>Devis &amp; factures récurrentes</li>
                <li class="flex items-start gap-2"><span class="check"></span>Relances WhatsApp manuelles</li>
                <li class="flex items-start gap-2"><span class="check"></span>1 utilisateur</li>
            </ul>
            <a href="{{ route('register') }}" class="btn-secondary justify-center mt-auto">Commencer</a>
        </article>

        <article class="card p-7 flex flex-col relative" style="box-shadow: 0 0 0 2px var(--color-vivid), var(--shadow-card);">
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 text-white text-[10px] font-semibold tracking-widest uppercase px-3 py-1 rounded-full" style="background: var(--color-vivid);">Le plus choisi</div>
            <div class="eyebrow mb-3">Essentiel</div>
            <h3 class="text-2xl font-bold mb-2">Essentiel</h3>
            <p class="text-sm mb-5" style="color: var(--color-marketing-slate);">Pour les PME qui veulent automatiser leurs relances.</p>
            <div class="mb-6">
                <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink);" x-text="p === 'monthly' ? '24 900' : '249 000'">24 900</span><span class="ml-1" style="color: var(--color-marketing-slate);" x-text="p === 'monthly' ? 'FCFA/mois' : 'FCFA/an'">FCFA/mois</span>
                <div class="text-[11px] mt-1" style="color: var(--color-vivid);" x-show="p === 'yearly'" x-cloak>Soit 20 750 FCFA/mois · 2 mois offerts</div>
            </div>
            <ul class="space-y-2.5 text-sm mb-7" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-2"><span class="check"></span>Factures illimitées</li>
                <li class="flex items-start gap-2"><span class="check"></span>Relances WhatsApp automatiques</li>
                <li class="flex items-start gap-2"><span class="check"></span>Prévision trésorerie 90 jours</li>
                <li class="flex items-start gap-2"><span class="check"></span>Conformité DGID (dès disponibilité)</li>
                <li class="flex items-start gap-2"><span class="check"></span>5 utilisateurs · Accès cabinet</li>
            </ul>
            <a href="{{ route('register') }}" class="btn-primary justify-center mt-auto">Essayer 30 jours</a>
        </article>

        <article class="card p-7 flex flex-col">
            <div class="eyebrow mb-3">Entreprise</div>
            <h3 class="text-2xl font-bold mb-2">Entreprise</h3>
            <p class="text-sm mb-5" style="color: var(--color-marketing-slate);">Pour les structures établies avec besoins avancés.</p>
            <div class="mb-6">
                <span class="font-mono text-4xl font-bold" style="color: var(--color-marketing-ink);" x-text="p === 'monthly' ? '59 900' : '599 000'">59 900</span><span class="ml-1" style="color: var(--color-marketing-slate);" x-text="p === 'monthly' ? 'FCFA/mois' : 'FCFA/an'">FCFA/mois</span>
                <div class="text-[11px] mt-1" style="color: var(--color-vivid);" x-show="p === 'yearly'" x-cloak>Soit 49 917 FCFA/mois · 2 mois offerts</div>
            </div>
            <ul class="space-y-2.5 text-sm mb-7" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-2"><span class="check"></span>Tout Essentiel inclus</li>
                <li class="flex items-start gap-2"><span class="check"></span>Score fiabilité client IA</li>
                <li class="flex items-start gap-2"><span class="check"></span>Workflows personnalisés</li>
                <li class="flex items-start gap-2"><span class="check"></span>Utilisateurs illimités</li>
                <li class="flex items-start gap-2"><span class="check"></span>Support prioritaire dédié</li>
            </ul>
            <a href="{{ route('marketing.contact') }}" class="btn-secondary justify-center mt-auto">Nous contacter</a>
        </article>
    </div>
</section>

</div>
{{-- /x-data Mensuel/Annuel --}}

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="border rounded-3xl px-6 sm:px-8 lg:px-10 py-8 flex flex-col lg:flex-row items-start lg:items-center gap-6" style="background: var(--color-mint-50); border-color: var(--color-mint-200);">
            <div class="shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center" style="background: var(--color-teal-fayeku);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color: var(--color-vivid);">Fayeku Compta · 0 FCFA</p>
                <p class="leading-relaxed" style="color: var(--color-marketing-slate);">L'espace dédié aux experts-comptables est gratuit et le restera. Multi-clients, exports Sage/EBP, coffre-fort de pièces, programme partenaire 15%.</p>
            </div>
            <a href="{{ route('marketing.accountants') }}" class="inline-flex items-center gap-2 bg-white border rounded-full px-5 py-3 text-sm font-medium whitespace-nowrap shadow-sm" style="border-color: var(--color-mint-200); color: var(--color-marketing-ink);">Découvrir<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-10">
            <p class="eyebrow mb-3">Comparatif</p>
            <h2 class="h2 mb-3">Tout ce qui est inclus, par plan.</h2>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead style="background: var(--color-mint-50);">
                        <tr>
                            <th class="text-left p-4 font-semibold">Fonctionnalité</th>
                            <th class="p-4 font-semibold">Solo</th>
                            <th class="p-4 font-semibold">Essentiel</th>
                            <th class="p-4 font-semibold">Entreprise</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ([
                            ['Factures', '50/mois', 'Illimitées', 'Illimitées'],
                            ['Devis & récurrentes', '✓', '✓', '✓'],
                            ['Relances WhatsApp manuelles', '✓', '✓', '✓'],
                            ['Relances automatiques (J+7, J+15, J+30)', '—', '✓', '✓'],
                            ['Prévision trésorerie 90 jours', '—', '✓', '✓'],
                            ['Conformité DGID (dès disponibilité)', '—', '✓', '✓'],
                            ['Score fiabilité client (IA)', '—', '—', '✓'],
                            ['Workflows personnalisés', '—', '—', '✓'],
                            ['Utilisateurs', '1', '5', 'Illimités'],
                            ['Accès cabinet (Fayeku Compta)', '—', '✓', '✓'],
                            ['Support', 'Email', 'Email + WhatsApp', 'Prioritaire dédié'],
                        ] as $row)
                            <tr>
                                <td class="p-4" style="color: var(--color-marketing-slate);">{{ $row[0] }}</td>
                                <td class="p-4 text-center font-medium">{{ $row[1] }}</td>
                                <td class="p-4 text-center font-medium">{{ $row[2] }}</td>
                                <td class="p-4 text-center font-medium">{{ $row[3] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@php
    $pricingFaq = [
        ['q' => 'Y a-t-il un engagement ?', 'a' => 'Non. Vous pouvez résilier à tout moment depuis votre tableau de bord.'],
        ['q' => "L'essai 30 jours nécessite-t-il une carte bancaire ?", 'a' => 'Non. Vous démarrez sans aucune information de paiement.'],
        ['q' => 'Comment payer ?', 'a' => 'Wave, Orange Money, virement, carte bancaire. Mensuel ou annuel.'],
        ['q' => 'Puis-je changer de plan ?', 'a' => 'Oui, à tout moment. La facturation est ajustée au prorata.'],
        ['q' => 'Les tarifs incluent-ils la TVA ?', 'a' => "Les prix affichés sont hors taxes. La TVA en vigueur s'ajoute le cas échéant."],
    ];
@endphp

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

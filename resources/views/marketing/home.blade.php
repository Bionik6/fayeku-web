<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

<section class="hero-bg relative overflow-hidden" x-data="{ persona: 'entreprise' }">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-12 sm:pt-14 sm:pb-16 lg:pt-20 lg:pb-28 grid lg:grid-cols-12 gap-12 lg:gap-10 items-center">
        <div class="lg:col-span-6">
            <div class="pill-toggle mb-7" role="tablist" aria-label="Type d'audience">
                <button type="button" @click="persona = 'entreprise'" :class="persona === 'entreprise' && 'active'">Je suis une entreprise</button>
                <button type="button" @click="persona = 'expert'" :class="persona === 'expert' && 'active'">Je suis expert-comptable</button>
            </div>

            {{-- Variante Entreprise --}}
            <div x-show="persona === 'entreprise'">
                <p class="eyebrow mb-4">Plateforme PME · Cabinet comptable · Trésorerie</p>
                <h1 class="h1 mb-6">
                    Facturez proprement.<br/>
                    <span style="color: var(--color-teal-fayeku);">Récupérez votre argent plus vite.</span>
                </h1>
                <p class="text-lg max-w-xl mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">
                    Fayeku aide les PME sénégalaises à créer leurs factures, suivre leurs impayés et relancer automatiquement leurs clients sur WhatsApp. Reprenez le contrôle de votre trésorerie.
                </p>

                <div class="flex flex-wrap gap-3 mb-8">
                    <a href="{{ route('register') }}" class="btn-primary">Essayer 30 jours
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                    <a href="{{ route('marketing.pricing') }}" class="btn-secondary">Voir les tarifs</a>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color: var(--color-marketing-slate);">
                    <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>30 jours d'essai</span>
                    <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Paiement Wave & Orange Money</span>
                    <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Pensé à Dakar</span>
                </div>
            </div>

            {{-- Variante Expert-comptable --}}
            <div x-show="persona === 'expert'" x-cloak>
                <div class="inline-flex items-center gap-2 mb-5 px-3 py-1.5 rounded-full bg-white border border-gray-100 shadow-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#0FB85C" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span class="text-sm font-semibold" style="color: var(--color-marketing-ink);">Fayeku Compta est gratuit</span>
                </div>
                <p class="eyebrow mb-4">Cockpit multi-clients pour cabinets</p>
                <h1 class="h1 mb-6">
                    Facturez proprement.<br/>
                    <span style="color: var(--color-teal-fayeku);">Contrôlez votre trésorerie.</span>
                </h1>
                <p class="text-lg max-w-xl mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">
                    Centralisez les factures de vos PME clientes, collectez les pièces, exportez vers vos outils comptables et activez un programme partenaire récurrent.
                </p>

                <div class="flex flex-wrap gap-3 mb-8">
                    <a href="{{ route('marketing.accountants') }}" class="btn-primary">Découvrir Fayeku Compta
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                    <a href="{{ route('marketing.accountants') }}#programme" class="btn-secondary">Voir le programme partenaire</a>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color: var(--color-marketing-slate);">
                    <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>0 FCFA · à vie</span>
                    <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>15% commission récurrente</span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-6 relative">
            <div class="absolute -top-10 -right-8 w-72 h-72 rounded-full blur-3xl opacity-60 -z-0" style="background: var(--color-mint-200);"></div>
            <div class="absolute -bottom-10 -left-8 w-56 h-56 rounded-full blur-3xl -z-0" style="background: rgba(15, 184, 92, 0.15);"></div>

            {{-- Mockup Entreprise --}}
            <div x-show="persona === 'entreprise'" class="relative">
            <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
            <div class="relative browser">
                <div class="browser-bar">
                    <span class="browser-dot bg-rose-300"></span>
                    <span class="browser-dot bg-amber-300"></span>
                    <span class="browser-dot bg-emerald-300"></span>
                    <div class="ml-3 text-[11px] font-mono truncate" style="color: var(--color-marketing-slate);">fayeku.sn — Tableau de bord</div>
                </div>

                <div class="p-5 lg:p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <div class="text-[11px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Bienvenue</div>
                            <div class="text-lg font-semibold tracking-tight" style="color: var(--color-marketing-ink);">Atelier Numérique SARL</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg text-white flex items-center justify-center text-xs font-semibold" style="background: var(--color-teal-fayeku);">AN</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 mb-5">
                        <div class="rounded-xl p-3" style="background: var(--color-mint-50);">
                            <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-marketing-slate);">CA facturé</div>
                            <div class="font-mono text-[15px] font-bold leading-none" style="color: var(--color-marketing-ink);">12.8M</div>
                            <div class="text-[10px] text-emerald-600 mt-1 flex items-center gap-0.5">▲ +14%</div>
                        </div>
                        <div class="rounded-xl p-3" style="background: var(--color-mint-50);">
                            <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-marketing-slate);">Encaissé</div>
                            <div class="font-mono text-[15px] font-bold leading-none" style="color: var(--color-marketing-ink);">8.4M</div>
                            <div class="text-[10px] text-emerald-600 mt-1">▲ +18%</div>
                        </div>
                        <div class="text-white rounded-xl p-3 relative overflow-hidden" style="background: var(--color-teal-fayeku);">
                            <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-mint-200);">À encaisser 90j</div>
                            <div class="font-mono text-[15px] font-bold leading-none" style="color: var(--color-vivid);">14.7M</div>
                            <div class="text-[10px] mt-1" style="color: var(--color-mint-200);">▲ +12%</div>
                        </div>
                        <div class="bg-rose-50 rounded-xl p-3">
                            <div class="text-[10px] uppercase tracking-wider font-semibold mb-1" style="color: var(--color-marketing-slate);">En retard</div>
                            <div class="font-mono text-[15px] font-bold text-rose-600 leading-none">2.1M</div>
                            <div class="text-[10px] text-rose-600 mt-1">4 factures</div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center gap-3 p-2.5 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 font-mono font-bold text-xs flex items-center justify-center">SO</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold truncate" style="color: var(--color-marketing-ink);">Sonatel Business</div>
                                <div class="text-[10px] font-mono" style="color: var(--color-marketing-slate);">FAC-2026-0145 · Éch. 12 mai</div>
                            </div>
                            <div class="font-mono text-[12px] font-semibold">1 250 000</div>
                            <span class="text-[9px] font-semibold tracking-wider uppercase px-2 py-0.5 rounded-full" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">Payée</span>
                        </div>
                        <div class="flex items-center gap-3 p-2.5 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-violet-100 text-violet-700 font-mono font-bold text-xs flex items-center justify-center">DL</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold truncate" style="color: var(--color-marketing-ink);">Dakar Logistique</div>
                                <div class="text-[10px] font-mono" style="color: var(--color-marketing-slate);">FAC-2026-0144 · J+7</div>
                            </div>
                            <div class="font-mono text-[12px] font-semibold">680 000</div>
                            <span class="text-[9px] font-semibold tracking-wider uppercase bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Envoyée</span>
                        </div>
                        <div class="flex items-center gap-3 p-2.5 rounded-lg">
                            <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 font-mono font-bold text-xs flex items-center justify-center">MS</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold truncate" style="color: var(--color-marketing-ink);">Médina Stores SARL</div>
                                <div class="text-[10px] font-mono" style="color: var(--color-marketing-slate);">FAC-2026-0142 · J-12</div>
                            </div>
                            <div class="font-mono text-[12px] font-semibold">320 000</div>
                            <span class="text-[9px] font-semibold tracking-wider uppercase bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full">En retard</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex absolute -top-4 -left-4 lg:-left-8 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[220px]" style="box-shadow: var(--shadow-float);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-vivid);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[11px] font-medium" style="color: var(--color-marketing-slate);">Paiement reçu</div>
                    <div class="font-mono text-[15px] font-bold" style="color: var(--color-marketing-ink);">+1 250 000 FCFA</div>
                    <div class="text-[10px]" style="color: var(--color-marketing-slate);">Sonatel · Wave</div>
                </div>
            </div>

            <div class="hidden sm:flex absolute -bottom-6 -right-2 lg:-right-6 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[240px]" style="box-shadow: var(--shadow-float);">
                <div class="w-9 h-9 rounded-xl text-white flex items-center justify-center shrink-0" style="background: var(--color-teal-fayeku);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[11px] font-medium" style="color: var(--color-marketing-slate);">Relance programmée</div>
                    <div class="text-[12px] font-semibold" style="color: var(--color-marketing-ink);">Médina Stores · J+15</div>
                    <div class="text-[10px] font-semibold" style="color: var(--color-vivid);">Demain · 9h00</div>
                </div>
            </div>
            </div>
            {{-- /Mockup Entreprise --}}

            {{-- Mockup Expert-comptable --}}
            <div x-show="persona === 'expert'" x-cloak class="relative">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 overflow-hidden" style="box-shadow: var(--shadow-float);">
                    <div class="p-5 lg:p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="text-[9px] font-bold tracking-widest uppercase mb-1" style="color: var(--color-vivid);">Vue principale</div>
                                <div class="text-[15px] font-bold leading-tight" style="color: var(--color-marketing-ink);">Bonjour, Cabinet Nio Far SA</div>
                                <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Avril 2026 · 3 impayés critiques à traiter · Versement partenaire le 05 Mai</div>
                            </div>
                            <span class="text-[9px] font-bold tracking-wider text-white px-2 py-1 rounded-full inline-flex items-center gap-1 whitespace-nowrap" style="background: var(--color-teal-deep);">Platinum <span style="color: var(--color-vivid);">★</span></span>
                        </div>

                        <div class="grid grid-cols-4 gap-2 mb-3">
                            <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                    </div>
                                    <span class="text-[7px] bg-gray-100 px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap" style="color: var(--color-marketing-slate);">Portef.</span>
                                </div>
                                <div class="text-[9px] font-semibold leading-tight" style="color: var(--color-marketing-slate);">Clients suivis</div>
                                <div class="font-mono text-[18px] font-bold leading-none mt-1" style="color: var(--color-marketing-ink);">25</div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-vivid);">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <span class="text-[7px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-vivid);">À jour</span>
                                </div>
                                <div class="text-[9px] font-semibold leading-tight" style="color: var(--color-marketing-slate);">Clients à jour</div>
                                <div class="font-mono text-[18px] font-bold leading-none mt-1" style="color: var(--color-vivid);">19</div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-5 h-5 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                                    </div>
                                    <span class="text-[7px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap">Surveiller</span>
                                </div>
                                <div class="text-[9px] font-semibold leading-tight" style="color: var(--color-marketing-slate);">À relancer</div>
                                <div class="font-mono text-[18px] font-bold text-amber-700 leading-none mt-1">3</div>
                            </div>
                            <div class="bg-white rounded-xl border border-rose-100 p-2.5 ring-1 ring-rose-100">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/></svg>
                                    </div>
                                    <span class="text-[7px] bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap">&gt; 60j</span>
                                </div>
                                <div class="text-[9px] font-semibold leading-tight" style="color: var(--color-marketing-slate);">Critiques</div>
                                <div class="font-mono text-[18px] font-bold text-rose-600 leading-none mt-1">3</div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl border border-gray-100 p-3 mb-3">
                            <div class="flex items-start justify-between mb-1">
                                <div>
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center mb-1.5" style="background: var(--color-mint-100); color: var(--color-vivid);">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                                    </div>
                                    <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Commissions du mois</div>
                                    <div class="font-mono text-[18px] font-bold leading-none mt-0.5" style="color: var(--color-marketing-ink);">63 000 FCFA</div>
                                    <div class="text-[9px] mt-1" style="color: var(--color-marketing-slate);">Avril 2026 · Versement prévu le 05 Mai</div>
                                </div>
                                <span class="text-[8px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap">Avril 2026</span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">Alertes récentes</div>
                                <span class="text-[9px] font-semibold" style="color: var(--color-teal-fayeku);">Voir toutes les alertes →</span>
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2 p-2 rounded-lg border border-gray-100">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 font-bold text-[10px] flex items-center justify-center shrink-0">!</div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">Atlas Chantier SA · Impayé critique <span class="text-[8px] bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded-full font-semibold ml-1">Impayé critique</span></div>
                                            <div class="text-[8px] truncate" style="color: var(--color-marketing-slate);">6 factures impayées · 5 040 000 FCFA · J74 max · Aucune relance envoyée</div>
                                        </div>
                                    </div>
                                    <span class="text-[8px] border border-gray-200 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="color: var(--color-marketing-slate);">Actions ⌄</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 p-2 rounded-lg border border-gray-100">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 font-bold text-[10px] flex items-center justify-center shrink-0">!</div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">Horizon Negoces SARL · Impayé critique <span class="text-[8px] bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded-full font-semibold ml-1">Impayé critique</span></div>
                                            <div class="text-[8px] truncate" style="color: var(--color-marketing-slate);">8 factures impayées · 4 720 000 FCFA · J70 max · Aucune relance envoyée</div>
                                        </div>
                                    </div>
                                    <span class="text-[8px] border border-gray-200 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="color: var(--color-marketing-slate);">Actions ⌄</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- /Mockup Expert-comptable --}}
        </div>
    </div>
</section>

<section class="py-12 sm:py-16 lg:py-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Le problème</p>
            <h2 class="h2 mb-5">Les impayés et Excel ne devraient pas piloter votre entreprise.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">
                Aujourd'hui, beaucoup de PME facturent encore sur Word ou Excel, relancent à la main sur WhatsApp, et envoient leurs pièces comptables en vrac en fin de mois. Résultat : paiements en retard, trésorerie floue, stress fiscal, perte de temps.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 lg:gap-5">
            @foreach ([
                ['title' => 'Factures encore créées sur Word ou Excel', 'desc' => "Numérotation manuelle, mises en forme cassées, calculs faux. Pas d'historique exploitable."],
                ['title' => 'Relances manuelles sur WhatsApp ou par téléphone', 'desc' => "On oublie, on n'ose pas, on insiste trop tard. La relation client s'use, le cash n'arrive pas."],
                ['title' => 'Pièces comptables envoyées en vrac en fin de mois', 'desc' => "Le cabinet court après les justificatifs. La saisie devient un puzzle. La clôture déborde."],
                ['title' => 'Trésorerie floue et retards de paiement difficiles à anticiper', 'desc' => "On découvre les trous trop tard. Les décisions se prennent dans le doute, pas dans les chiffres."],
            ] as $pain)
                <div class="card p-6 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold mb-1">{{ $pain['title'] }}</h3>
                        <p class="text-sm" style="color: var(--color-marketing-slate);">{{ $pain['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12 text-center mx-auto">
            <p class="eyebrow mb-3">Pourquoi Fayeku</p>
            <h2 class="h2 mb-5">Une plateforme pensée pour les réalités du Sénégal.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">
                Conçu à Dakar pour les PME sénégalaises : facturation claire, relances utiles, collaboration comptable plus fluide.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-5">
            @foreach ([
                ['title' => 'Se faire payer plus vite', 'desc' => 'Relances WhatsApp automatisées, suivi des impayés en temps réel, messages prêts à envoyer.', 'svg' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'],
                ['title' => 'Contrôler le cash', 'desc' => "Délai moyen de paiement, prévision de trésorerie à 90 jours, vision claire de l'argent à encaisser.", 'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['title' => 'Travailler mieux avec son comptable', 'desc' => 'Accès cabinet, exports Sage, coffre-fort documents. Moins de WhatsApp, plus de structure.', 'svg' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ] as $card)
                <article class="card p-7">
                    <div class="relative w-[76px] h-[76px] mb-5">
                        <span class="absolute bottom-0 right-0 w-[62px] h-[62px] rounded-full" style="background: var(--color-vivid);"></span>
                        <svg class="absolute top-0 left-0" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-teal-fayeku);">{!! $card['svg'] !!}</svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">{{ $card['title'] }}</h3>
                    <p class="leading-relaxed" style="color: var(--color-marketing-slate);">{{ $card['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div class="order-2 lg:order-1">
            <p class="eyebrow mb-3">Facturation pro</p>
            <h2 class="h2 mb-5">Une facture qui inspire confiance, en 30 secondes.</h2>
            <p class="text-lg leading-relaxed mb-6" style="color: var(--color-marketing-slate);">
                Templates avec votre logo, NINEA, TVA et numérotation continue. Vos factures partent par WhatsApp ou email en un clic, et leur statut est suivi automatiquement.
            </p>
            <ul class="space-y-3" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-3"><span class="check"></span>Modèle PDF aux normes sénégalaises (NINEA, TVA 18%, mentions légales)</li>
                <li class="flex items-start gap-3"><span class="check"></span>Envoi WhatsApp natif, lecture suivie automatiquement</li>
                <li class="flex items-start gap-3"><span class="check"></span>Devis et factures récurrentes inclus dès le plan Essentiel</li>
            </ul>
        </div>
        <div class="order-1 lg:order-2 relative">
            <img src="/mockup-invoice.png" alt="Exemple de facture Sayar Distribution éditée dans Fayeku, montant 1 475 000 FCFA, envoyée via WhatsApp" class="w-full max-w-[620px] mx-auto" />
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div class="relative">
            <div class="absolute -top-6 -left-6 w-48 h-48 rounded-full blur-3xl opacity-60 -z-0" style="background: var(--color-mint-200);"></div>
            <div class="browser relative max-w-[520px] mx-auto">
                <div class="browser-bar">
                    <span class="browser-dot bg-rose-300"></span>
                    <span class="browser-dot bg-amber-300"></span>
                    <span class="browser-dot bg-emerald-300"></span>
                    <div class="ml-3 text-[11px] font-mono truncate" style="color: var(--color-marketing-slate);">fayeku.sn — Relance automatique</div>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Scénario actif</div>
                            <div class="font-semibold" style="color: var(--color-marketing-ink);">Relance standard PME</div>
                        </div>
                        <span class="text-[10px] font-semibold tracking-wider uppercase px-2 py-1 rounded-full inline-flex items-center gap-1.5" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background: var(--color-vivid);"></span>En route</span>
                    </div>

                    <div class="relative pl-4 mb-5">
                        <div class="absolute left-[7px] top-2 bottom-2 w-px bg-gray-200"></div>
                        <ol class="space-y-3">
                            <li class="flex items-center gap-3 relative">
                                <span class="absolute -left-4 w-3.5 h-3.5 rounded-full border-2 border-white ring-2 ring-[color:var(--color-vivid)]/20" style="background: var(--color-vivid);"></span>
                                <span class="font-mono text-[11px] font-bold w-12" style="color: var(--color-teal-fayeku);">J+3</span>
                                <span class="flex-1 text-[12px]" style="color: var(--color-marketing-ink);">Rappel amical · WhatsApp</span>
                                <span class="text-[10px] font-semibold" style="color: var(--color-vivid);">Envoyé</span>
                            </li>
                            <li class="flex items-center gap-3 relative">
                                <span class="absolute -left-4 w-3.5 h-3.5 rounded-full border-2 border-white ring-2 ring-[color:var(--color-vivid)]/20" style="background: var(--color-vivid);"></span>
                                <span class="font-mono text-[11px] font-bold w-12" style="color: var(--color-teal-fayeku);">J+7</span>
                                <span class="flex-1 text-[12px]" style="color: var(--color-marketing-ink);">Relance ferme · WhatsApp + PDF</span>
                                <span class="text-[10px] font-semibold" style="color: var(--color-vivid);">Envoyé</span>
                            </li>
                            <li class="flex items-center gap-3 relative">
                                <span class="absolute -left-4 w-3.5 h-3.5 rounded-full bg-amber-400 border-2 border-white ring-2 ring-amber-200"></span>
                                <span class="font-mono text-[11px] font-bold w-12" style="color: var(--color-teal-fayeku);">J+15</span>
                                <span class="flex-1 text-[12px]" style="color: var(--color-marketing-ink);">Relance directe · WhatsApp + Email</span>
                                <span class="text-[10px] text-amber-600 font-semibold">Demain 9h</span>
                            </li>
                            <li class="flex items-center gap-3 relative">
                                <span class="absolute -left-4 w-3.5 h-3.5 rounded-full bg-gray-200 border-2 border-white"></span>
                                <span class="font-mono text-[11px] font-bold w-12" style="color: var(--color-marketing-slate);">J+30</span>
                                <span class="flex-1 text-[12px]" style="color: var(--color-marketing-slate);">Mise en demeure assistée</span>
                                <span class="text-[10px]" style="color: var(--color-marketing-slate);">En attente</span>
                            </li>
                        </ol>
                    </div>

                    <div class="rounded-xl border border-gray-100 p-3" style="background: var(--color-mint-50);">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-6 h-6 rounded-md text-white text-[10px] font-bold flex items-center justify-center" style="background: var(--color-teal-fayeku);">F</div>
                            <div class="text-[11px] font-semibold" style="color: var(--color-marketing-ink);">Aperçu du message · J+15</div>
                        </div>
                        <p class="text-[12px] leading-relaxed" style="color: var(--color-marketing-ink);">
                            Bonjour M. Diop 👋 Petit rappel pour la facture <span class="font-mono font-semibold">FAC-2026-0142</span> de <span class="font-mono font-semibold">320 000 FCFA</span>, échue depuis 12 jours. Vous pouvez régler via Wave au 77 123 45 67.
                        </p>
                        <div class="flex items-center gap-2 mt-2 text-[10px]" style="color: var(--color-marketing-slate);">
                            <span class="inline-flex items-center gap-1 bg-white border border-gray-200 px-1.5 py-0.5 rounded"><svg width="10" height="10" viewBox="0 0 24 24" fill="#DC2626" stroke="none"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>PDF joint</span>
                            <span>·</span>
                            <span>Programmé · 27 mai · 09:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex absolute -bottom-4 -right-2 lg:-right-4 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[230px]" style="box-shadow: var(--shadow-float);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-vivid);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[11px] font-medium" style="color: var(--color-marketing-slate);">Délai moyen réduit</div>
                    <div class="font-mono text-[15px] font-bold" style="color: var(--color-marketing-ink);">−9 jours</div>
                    <div class="text-[10px] font-semibold" style="color: var(--color-vivid);">vs. relances manuelles</div>
                </div>
            </div>
        </div>
        <div>
            <p class="eyebrow mb-3">Relance automatique</p>
            <h2 class="h2 mb-5">Vos clients vous paient avant même que vous ayez à insister.</h2>
            <p class="text-lg leading-relaxed mb-6" style="color: var(--color-marketing-slate);">
                Fayeku envoie les relances WhatsApp à votre place, au bon moment, avec le bon ton. La facture PDF est jointe automatiquement. Le paiement est rapproché dès qu'il arrive.
            </p>
            <ul class="space-y-3" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-3"><span class="check"></span>Scénarios prêts : J+3 amical, J+7 ferme, J+15 et J+30</li>
                <li class="flex items-start gap-3"><span class="check"></span>Messages personnalisables par client et par secteur</li>
                <li class="flex items-start gap-3"><span class="check"></span>Marquez « payé » via Wave, Orange Money, virement ou cash</li>
            </ul>
        </div>
    </div>
</section>

<section class="py-12 sm:py-16 lg:py-28 bg-white">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <p class="text-xs font-semibold tracking-widest uppercase mb-3" style="color: var(--color-vivid);">Comment ça marche</p>
            <h2 class="h2 mb-4">Simple. En 4 étapes.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Un parcours pensé pour le quotidien d'une PME et lisible pour le cabinet.</p>
        </div>

        <ol class="relative grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-12 lg:gap-y-0 lg:gap-x-2">
            @foreach ([
                ['n' => 1, 'title' => 'Créez et envoyez vos factures', 'desc' => 'Générez vos factures en quelques clics et envoyez-les par WhatsApp ou email.', 'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>'],
                ['n' => 2, 'title' => 'Relancez automatiquement', 'desc' => 'Fayeku relance vos clients par WhatsApp et email, au bon moment.', 'svg' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
                ['n' => 3, 'title' => 'Suivez vos encaissements', 'desc' => 'Visualisez votre cash en temps réel et anticipez sur 90 jours.', 'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['n' => 4, 'title' => 'Collaborez avec votre comptable', 'desc' => 'Partagez documents, exports et accédez à votre cabinet.', 'svg' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6"/><path d="M19 8l3 3-3 3"/>'],
            ] as $step)
                <li class="relative flex flex-col items-center text-center px-4">
                    <div class="relative mb-6">
                        <div class="w-24 h-24 rounded-full border-2 border-dashed flex items-center justify-center bg-white" style="border-color: var(--color-mint-200);">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#024D4E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $step['svg'] !!}</svg>
                        </div>
                        <span class="absolute -top-2 -left-2 w-9 h-9 rounded-full text-white font-bold text-sm flex items-center justify-center shadow-md ring-4 ring-white" style="background: var(--color-vivid);">{{ $step['n'] }}</span>
                        @if ($step['n'] < 4)
                            <span class="hidden lg:block absolute top-1/2 left-[calc(100%+0.75rem)] w-[calc(200%+1.5rem)] -translate-y-1/2 border-t-2 border-dashed" style="border-color: var(--color-mint-200);" aria-hidden="true"></span>
                        @endif
                    </div>
                    <h3 class="font-semibold text-lg leading-snug mb-2" style="color: var(--color-marketing-ink);">{{ $step['title'] }}</h3>
                    <p class="text-sm leading-relaxed max-w-[230px]" style="color: var(--color-marketing-slate);">{{ $step['desc'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="py-12 sm:py-16 lg:py-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Fonctionnalités</p>
            <h2 class="h2 mb-5">Tout ce qu'il faut pour facturer, relancer, et piloter.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Tout est là dès le premier jour. Activez ce dont vous avez besoin.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ([
                ['title' => 'Facturation professionnelle', 'desc' => 'Templates logo + NINEA + TVA, numérotation automatique.', 'bg' => 'var(--color-mint-100)', 'color' => 'var(--color-teal-fayeku)', 'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>'],
                ['title' => 'Suivi des impayés', 'desc' => 'Vue en temps réel des factures en retard et de leur ancienneté.', 'bg' => 'rgb(255 241 242)', 'color' => '#f43f5e', 'svg' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
                ['title' => 'Relances WhatsApp automatiques', 'desc' => 'Scénarios J+7, J+15 et manuels, avec PDF joint.', 'bg' => 'rgb(236 253 245)', 'color' => '#059669', 'svg' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
                ['title' => 'Relance intelligente IA', 'desc' => 'Quand relancer et quel message envoyer selon le client.', 'bg' => 'rgb(245 243 255)', 'color' => '#7c3aed', 'svg' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
                ['title' => 'Score fiabilité client', 'desc' => 'Priorisez les comptes les plus risqués, anticipez les retards.', 'bg' => 'rgb(255 251 235)', 'color' => '#d97706', 'svg' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
                ['title' => 'Prévision trésorerie 90 jours', 'desc' => 'Encaissements attendus, alertes de creux à venir.', 'bg' => 'rgb(240 249 255)', 'color' => '#0284c7', 'svg' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'],
                ['title' => 'Factures récurrentes &amp; devis', 'desc' => 'Signature devis et conversion en facture en un clic.', 'bg' => 'rgb(238 242 255)', 'color' => '#4f46e5', 'svg' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>'],
                ['title' => 'Coffre-fort documents', 'desc' => 'Collecte de pièces simple pour le cabinet, fini les PDF sur WhatsApp.', 'bg' => 'rgb(240 253 250)', 'color' => 'var(--color-teal-fayeku)', 'svg' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
            ] as $feature)
                <article class="card p-6">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4" style="background: {{ $feature['bg'] }}; color: {{ $feature['color'] }};">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $feature['svg'] !!}</svg>
                    </div>
                    <h3 class="font-semibold mb-1">{!! $feature['title'] !!}</h3>
                    <p class="text-sm" style="color: var(--color-marketing-slate);">{{ $feature['desc'] }}</p>
                </article>
            @endforeach
            <article class="card p-6 relative">
                <div class="absolute top-5 right-5 text-[10px] font-semibold tracking-wider uppercase px-2 py-1 rounded-full" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">En préparation</div>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 class="font-semibold mb-1">Conformité DGID</h3>
                <p class="text-sm" style="color: var(--color-marketing-slate);">Intégration dès disponibilité officielle des modalités techniques.</p>
            </article>
        </div>
    </div>
</section>

<section class="relative overflow-hidden" style="background: var(--color-teal-fayeku);">
    <div class="grain absolute inset-0 opacity-40"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-12 sm:py-16 lg:py-28 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center relative">
        <div>
            <p class="eyebrow-mint mb-3">Fayeku Compta</p>
            <h2 class="h2 text-white mb-5">Fayeku Compta : gratuit et complet</h2>
            <p class="text-lg leading-relaxed mb-8" style="color: rgba(212, 240, 224, 0.9);">
                Centralisez vos PME clientes, suivez leurs factures en temps réel, collectez les pièces et exportez en un clic. Fayeku Compta est gratuit et conçu pour vous faire gagner du temps.
            </p>
            <a href="{{ route('marketing.accountants') }}" class="btn-white">Découvrir Fayeku Compta
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
        <ul class="space-y-3">
            @foreach ([
                ['label' => 'Dashboard multi-clients', 'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['label' => 'Vue factures en temps réel', 'svg' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
                ['label' => 'Exports Sage 100 / EBP / Excel', 'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'],
                ['label' => 'Collecte de pièces', 'svg' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
                ['label' => 'Rapport mensuel automatique', 'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/>'],
                ['label' => 'Commission récurrente partenaire', 'svg' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>'],
            ] as $bullet)
                <li class="flex items-center gap-4 border border-white/10 rounded-xl px-5 py-4 text-white" style="background: rgba(255, 255, 255, 0.06);">
                    <span class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: rgba(15, 184, 92, 0.12);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $bullet['svg'] !!}</svg>
                    </span>
                    {{ $bullet['label'] }}
                </li>
            @endforeach
        </ul>
    </div>
</section>

<section class="py-10 sm:py-12 lg:py-20">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="border rounded-3xl px-6 sm:px-8 lg:px-10 py-8 lg:py-7 flex flex-col lg:flex-row items-start lg:items-center gap-6 lg:gap-8" style="background: var(--color-mint-50); border-color: var(--color-mint-200);">
            <div class="shrink-0 w-16 h-16 lg:w-[72px] lg:h-[72px] rounded-2xl flex items-center justify-center" style="background: var(--color-teal-fayeku);">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs sm:text-sm font-semibold tracking-widest uppercase mb-2" style="color: var(--color-vivid);">
                    Conformité DGID : Fayeku vous prépare pour demain.
                </p>
                <p class="leading-relaxed max-w-3xl" style="color: var(--color-marketing-slate);">
                    Fayeku intégrera la conformité DGID dans les plans Essentiel et Entreprise. Lorsque les modalités officielles seront publiées, vos factures seront prêtes à être conformes, sans reparamétrage complexe.
                </p>
            </div>
            <div class="shrink-0">
                <a href="{{ route('marketing.compliance') }}" class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 transition-colors border rounded-full px-5 py-3 text-sm font-medium whitespace-nowrap shadow-sm" style="border-color: var(--color-mint-200); color: var(--color-marketing-ink);">
                    En savoir plus sur la conformité
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Repères</p>
            <h2 class="h2 mb-5">Ce que Fayeku change concrètement.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Des éléments tangibles pour évaluer rapidement la promesse produit.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            <article class="card p-7">
                <div class="font-mono text-5xl font-bold tracking-tight mb-2" style="color: var(--color-teal-fayeku);">30<span class="text-2xl ml-1 align-top">sec</span></div>
                <div class="font-semibold mb-2">pour créer une facture</div>
                <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">Un flux simple pour passer de l'émission à l'envoi sans tableur.</p>
            </article>
            <article class="card p-7">
                <div class="font-mono text-5xl font-bold tracking-tight mb-2" style="color: var(--color-teal-fayeku);">90<span class="text-2xl ml-1 align-top">jours</span></div>
                <div class="font-semibold mb-2">de visibilité cash</div>
                <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">Une prévision de trésorerie lisible pour anticiper les échéances.</p>
            </article>
            <article class="card p-7">
                <div class="font-mono text-5xl font-bold tracking-tight mb-2" style="color: var(--color-teal-fayeku);">0<span class="text-2xl ml-1 align-top">FCFA</span></div>
                <div class="font-semibold mb-2">pour Fayeku Compta</div>
                <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">Votre cabinet accède à l'espace dédié sans coût supplémentaire.</p>
            </article>
        </div>
    </div>
</section>

@php
    $homeFaq = [
        ['q' => 'Puis-je utiliser Fayeku sans intégrer Wave / Orange Money ?', 'a' => 'Oui. Les paiements se font hors application. Marquez une facture comme payée via Wave, Orange Money, virement ou cash, puis Fayeku gère le suivi et les relances WhatsApp.'],
        ['q' => 'Fayeku remplace-t-il mon comptable ?', 'a' => 'Non. Fayeku structure le flux pour votre comptable et lui donne accès à un espace dédié (Fayeku Compta) gratuit.'],
        ['q' => 'Est-ce compatible avec Sage / EBP ?', 'a' => 'Oui. Fayeku Compta génère des exports Sage 100, EBP et Excel directement utilisables par votre cabinet.'],
        ['q' => "Puis-je tester Fayeku avant de m'engager ?", 'a' => "Oui, 30 jours d'essai sont inclus sur tous les plans, sans engagement et sans paiement préalable."],
        ['q' => 'Comment marche le programme partenaire comptable ?', 'a' => "Vous recommandez Fayeku à vos clients PME et touchez 15% de commission récurrente tant qu'ils restent abonnés."],
        ['q' => 'Le paiement annuel est-il obligatoire ?', 'a' => "Non. Vous pouvez payer au mois ou bénéficier de 2 mois offerts en payant à l'année."],
        ['q' => 'Quid de la conformité DGID ?', 'a' => 'Fayeku intègrera la conformité officielle dès publication des modalités techniques par la DGID, sur les plans Essentiel et Entreprise.'],
        ['q' => 'Où est hébergée la plateforme ?', 'a' => 'Sur des serveurs sécurisés en Europe (Hetzner, Helsinki), avec sauvegardes quotidiennes.'],
        ['q' => "Qu'arrive-t-il à mes données si j'arrête Fayeku ?", 'a' => "Vous pouvez exporter l'ensemble de vos données (factures, clients, paiements) au format CSV ou PDF à tout moment."],
    ];
@endphp

<section class="pb-12 sm:pb-16 lg:pb-28" x-data="{ open: null }">
    <div class="max-w-4xl mx-auto px-5 lg:px-8">
        <div class="mb-12 text-center">
            <p class="eyebrow mb-3">FAQ</p>
            <h2 class="h2 mb-5">Les questions qui reviennent le plus avant de se lancer.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Des réponses courtes, directes, orientées usage réel.</p>
        </div>

        <div class="card divide-y divide-gray-100">
            @foreach ($homeFaq as $i => $item)
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

<section class="px-5 lg:px-8 pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto rounded-3xl py-10 sm:py-12 lg:py-20 px-6 text-center relative overflow-hidden" style="background: var(--color-mint-100);">
        <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full blur-3xl" style="background: rgba(15, 184, 92, 0.10);"></div>
        <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl" style="background: rgba(2, 77, 78, 0.10);"></div>
        <div class="relative">
            <h2 class="h2 mb-4 max-w-2xl mx-auto">Prêt à reprendre le contrôle de votre trésorerie ?</h2>
            <p class="text-lg mb-8 max-w-xl mx-auto" style="color: var(--color-marketing-slate);">Démarrez en 2 minutes. Aucun engagement. Paiement Wave ou Orange Money.</p>
            <a href="{{ route('register') }}" class="btn-primary">Essayer 30 jours
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

</x-layouts.marketing>

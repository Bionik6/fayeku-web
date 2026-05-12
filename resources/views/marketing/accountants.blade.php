<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-12 sm:pt-14 sm:pb-16 lg:pt-20 lg:pb-24 grid lg:grid-cols-12 gap-10 items-center relative">
        <div class="lg:col-span-6">
            <p class="eyebrow mb-4">Pour les experts-comptables</p>
            <h1 class="h1 mb-6">Fayeku Compta. <span style="color: var(--color-teal-fayeku);">Gratuit. Complet.</span></h1>
            <p class="text-lg max-w-xl mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">Un espace cabinet dédié pour suivre toutes vos PME clientes, collecter les pièces, exporter vers Sage 100 / EBP et faire grandir votre cabinet grâce au programme partenaire.</p>
            <div class="flex flex-wrap gap-3 mb-6">
                <a href="{{ route('marketing.accountants.join') }}" class="btn-primary">Activer mon espace cabinet<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
                <a href="#programme" class="btn-secondary">Voir le programme</a>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color: var(--color-marketing-slate);">
                <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>0 FCFA · à vie</span>
                <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>15% commission récurrente</span>
            </div>
        </div>
        <div class="lg:col-span-6 relative">
            <div class="absolute -top-10 -right-8 w-72 h-72 rounded-full blur-3xl opacity-60 -z-0" style="background: var(--color-mint-200);"></div>
            <div class="absolute -bottom-10 -left-8 w-56 h-56 rounded-full blur-3xl -z-0" style="background: rgba(15, 184, 92, 0.15);"></div>
            <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
            <div class="relative border border-gray-100 rounded-2xl overflow-hidden" style="background: var(--color-offwhite); box-shadow: var(--shadow-float);">
                <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md border flex items-center justify-center" style="background: var(--color-mint-100); border-color: var(--color-mint-200);">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#024D4E" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="7" height="18" rx="1"/></svg>
                        </div>
                        <div class="text-[10px]" style="color: var(--color-marketing-slate);">Tableau de bord <span class="text-gray-300">/</span> <span class="font-semibold" style="color: var(--color-marketing-ink);">Dashboard</span></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full text-[10px] font-bold flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">ON</div>
                        <div class="leading-tight text-right">
                            <div class="text-[11px] font-semibold" style="color: var(--color-marketing-ink);">Ousmane Ndiaye</div>
                            <div class="text-[9px]" style="color: var(--color-marketing-slate);">Cabinet Ndiaye Conseil</div>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="text-[9px] font-bold tracking-widest uppercase mb-1" style="color: var(--color-vivid);">Vue principale</div>
                            <div class="text-[15px] font-bold leading-tight" style="color: var(--color-marketing-ink);">Bonjour, Cabinet Ndiaye Conseil</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Mai 2026 · 5 impayés critiques · Versement le 05 Jun</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-bold tracking-wider text-white px-2 py-1 rounded-full inline-flex items-center gap-1" style="background: var(--color-teal-deep);">Platinum <span style="color: var(--color-vivid);">★</span></span>
                            <div class="text-[9px] mt-1" style="color: var(--color-marketing-slate);">25 clients actifs</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 mb-4">
                        @foreach ([
                            ['icon_bg' => 'var(--color-mint-100)', 'icon_color' => 'var(--color-teal-fayeku)', 'pill' => 'Portef.', 'pill_bg' => 'bg-gray-100', 'pill_text' => 'var(--color-marketing-slate)', 'label' => 'Clients suivis', 'value' => '25', 'value_color' => 'var(--color-marketing-ink)'],
                            ['icon_bg' => 'var(--color-mint-100)', 'icon_color' => 'var(--color-vivid)', 'pill' => 'À jour', 'pill_bg' => 'bg-[color:var(--color-mint-100)]', 'pill_text' => 'var(--color-vivid)', 'label' => 'Clients à jour', 'value' => '17', 'value_color' => 'var(--color-vivid)'],
                            ['icon_bg' => 'rgb(254 243 199)', 'icon_color' => '#b45309', 'pill' => 'Surveiller', 'pill_bg' => 'bg-amber-100', 'pill_text' => '#b45309', 'label' => 'À relancer', 'value' => '3', 'value_color' => '#b45309'],
                            ['icon_bg' => 'rgb(254 226 226)', 'icon_color' => '#dc2626', 'pill' => '> 60j', 'pill_bg' => 'bg-rose-50', 'pill_text' => '#dc2626', 'label' => 'Impayés critiques', 'value' => '5', 'value_color' => '#dc2626'],
                        ] as $stat)
                            <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background: {{ $stat['icon_bg'] }}; color: {{ $stat['icon_color'] }};">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                                    </div>
                                    <span class="text-[8px] {{ $stat['pill_bg'] }} px-1.5 py-0.5 rounded-full font-semibold" style="color: {{ $stat['pill_text'] }};">{{ $stat['pill'] }}</span>
                                </div>
                                <div class="text-[8px] font-semibold leading-tight" style="color: var(--color-marketing-slate);">{{ $stat['label'] }}</div>
                                <div class="font-mono text-[18px] font-bold leading-none mt-1" style="color: {{ $stat['value_color'] }};">{{ $stat['value'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-white rounded-xl border border-gray-100 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">Aperçu du portefeuille</div>
                            <span class="text-[9px] font-semibold" style="color: var(--color-teal-fayeku);">Voir tout →</span>
                        </div>
                        <table class="w-full" style="table-layout: fixed;">
                            <colgroup>
                                <col style="width: 44%;">
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                                <col style="width: 16%;">
                            </colgroup>
                            <tbody>
                                @foreach ([
                                    ['init' => 'SH', 'name' => 'Saatys Home & Design', 'plan' => 'Essentiel', 'amt' => '9 213 400F', 'status' => 'Critique', 'color' => '#dc2626'],
                                    ['init' => 'SB', 'name' => 'Sow BTP SARL', 'plan' => 'Essentiel', 'amt' => '9 853 000F', 'status' => 'Critique', 'color' => '#dc2626'],
                                    ['init' => 'CT', 'name' => 'Coury Textile SARL', 'plan' => 'Basique', 'amt' => '420 000F', 'status' => 'Attente', 'color' => '#b45309'],
                                    ['init' => 'NA', 'name' => 'Ndioum Agro SA', 'plan' => 'Essentiel', 'amt' => '—', 'status' => 'À jour', 'color' => 'var(--color-vivid)'],
                                ] as $row)
                                    @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                    <tr>
                                        <td class="py-1.5 {{ $bb }}">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <div class="w-5 h-5 rounded-md font-bold text-[8px] flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['init'] }}</div>
                                                <div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">{{ $row['name'] }}</div>
                                            </div>
                                        </td>
                                        <td class="py-1.5 text-center {{ $bb }}"><span class="text-[8px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['plan'] }}</span></td>
                                        <td class="py-1.5 font-mono text-[10px] font-bold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">{{ $row['amt'] }}</td>
                                        <td class="py-1.5 text-[8px] font-semibold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">● {{ $row['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex absolute -top-4 -left-4 lg:-left-8 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[240px]" style="box-shadow: var(--shadow-float);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-vivid);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[10px] font-medium" style="color: var(--color-marketing-slate);">Commission mai 2026</div>
                    <div class="font-mono text-[14px] font-bold" style="color: var(--color-marketing-ink);">64 500 FCFA</div>
                    <div class="text-[10px] font-semibold" style="color: var(--color-vivid);">Versée le 05 Jun · Wave</div>
                </div>
            </div>

            <div class="hidden sm:flex absolute -bottom-6 -right-2 lg:-right-4 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[240px]" style="box-shadow: var(--shadow-float);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-teal-deep); color: var(--color-vivid);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15 9 22 9 17 14 19 22 12 18 5 22 7 14 2 9 9 9"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[10px] font-medium" style="color: var(--color-marketing-slate);">Niveau partenaire</div>
                    <div class="text-[13px] font-bold" style="color: var(--color-marketing-ink);">Platinum atteint</div>
                    <div class="text-[10px] font-semibold" style="color: var(--color-vivid);">25 PME actives · 15% à vie</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Problèmes actuels --}}
<section class="py-12 sm:py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="card p-6 sm:p-8 lg:p-12">
            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-start">
                <div>
                    <p class="eyebrow mb-3">Problèmes actuels</p>
                    <h2 class="h2 mb-5">La comptabilité ne devrait pas être un puzzle.</h2>
                    <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">
                        Aujourd'hui, une grande partie du temps d'un cabinet est consommée par des tâches à faible valeur : demander des pièces, trier des fichiers, recoller l'historique… Fayeku réduit cette friction pour vous permettre de vous concentrer sur l'essentiel : la qualité et le conseil.
                    </p>
                </div>
                <ul class="space-y-3">
                    @foreach ([
                        'WhatsApp, email et papier créent des trous dans la collecte',
                        'Fin de mois chaotique et peu prédictible',
                        'Historique client difficile à reconstituer',
                        'Relances comptables mélangées aux relances commerciales',
                    ] as $pain)
                        <li class="rounded-full px-5 py-4 text-sm sm:text-base font-medium" style="background: var(--color-mint-100); color: var(--color-marketing-ink);">{{ $pain }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- Ce que Fayeku change · gains avec icônes colorées --}}
<section class="py-12 sm:py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-10 sm:mb-12">
            <p class="eyebrow mb-3">Ce que Fayeku change</p>
            <h2 class="h2 mb-3">Des gains immédiats pour vos équipes et vos clients.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Une couche opérationnelle claire entre la PME et le cabinet.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ([
                ['title' => 'Dashboard multi-clients', 'desc' => 'Une vue consolidée de tous vos clients PME : statuts, retards et activité récente.', 'bg' => 'rgb(239 246 255)', 'color' => '#2563eb', 'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                ['title' => 'Vue factures en temps réel', 'desc' => 'Accédez aux factures de chaque client dès leur création, sans attendre la fin du mois.', 'bg' => 'var(--color-mint-100)', 'color' => 'var(--color-vivid)', 'svg' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
                ['title' => 'Exports Sage 100 / EBP / Excel', 'desc' => 'Exportez les écritures en un clic vers votre logiciel comptable, sans ressaisie.', 'bg' => 'rgb(245 243 255)', 'color' => '#7c3aed', 'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'],
                ['title' => 'Collecte de pièces', 'desc' => 'Vos clients déposent leurs pièces directement dans Fayeku. Fini les PDFs sur WhatsApp.', 'bg' => 'rgb(255 247 237)', 'color' => '#ea580c', 'svg' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
                ['title' => 'Rapport mensuel automatique', 'desc' => 'Un récapitulatif mensuel par client généré automatiquement, prêt à archiver.', 'bg' => 'rgb(236 254 255)', 'color' => '#0891b2', 'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/>'],
                ['title' => 'Commission récurrente partenaire', 'desc' => 'Percevez 15% récurrent à vie sur chaque client PME que vous recommandez.', 'bg' => 'rgb(254 252 232)', 'color' => '#ca8a04', 'svg' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>'],
            ] as $card)
                <article class="card p-6">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-5" style="background: {{ $card['bg'] }}; color: {{ $card['color'] }};">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['svg'] !!}</svg>
                    </div>
                    <h3 class="font-semibold text-lg mb-2">{{ $card['title'] }}</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $card['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24" style="background: rgba(241, 250, 244, 0.4);">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-12 sm:py-16 lg:py-24">
        <h2 class="h2 text-center mb-16">Un cockpit pensé pour votre quotidien.</h2>

        {{-- Row 1 — Portefeuille (text left, mockup right) --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center mb-24">
            <div>
                <p class="eyebrow mb-3">Portefeuille</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Tous vos clients PME en un coup d'œil</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Visualisez le statut de chaque client, le nombre de factures impayées et les alertes critiques. Filtrez par état et accédez au détail en un clic.</p>
            </div>
            <div class="relative hidden lg:block">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Portefeuille</div>
                            <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Clients</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">25 clients suivis · 3 critiques · 3 à surveiller</div>
                        </div>
                        <button type="button" class="text-[10px] font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1" style="background: var(--color-teal-fayeku); color: var(--color-vivid);"><span>+</span> Inviter une PME</button>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-4 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                            <div class="flex items-start justify-between mb-2">
                                <div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/></svg></div>
                                <span class="text-[7px] bg-gray-100 px-1.5 py-0.5 rounded-full font-semibold" style="color: var(--color-marketing-slate);">Portef.</span>
                            </div>
                            <div class="text-[8px] font-semibold" style="color: var(--color-marketing-slate);">Total en attente</div>
                            <div class="font-mono text-[12px] font-bold leading-none mt-1" style="color: var(--color-marketing-ink);">13 520 000F</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                            <div class="flex items-start justify-between mb-2">
                                <div class="w-5 h-5 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/></svg></div>
                                <span class="text-[7px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">Critiques</span>
                            </div>
                            <div class="text-[8px] font-semibold" style="color: var(--color-marketing-slate);">Clients critiques</div>
                            <div class="font-mono text-[16px] font-bold text-amber-700 leading-none mt-1">3</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5">
                            <div class="flex items-start justify-between mb-2">
                                <div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-vivid);"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                                <span class="text-[7px] bg-gray-100 px-1.5 py-0.5 rounded-full font-semibold" style="color: var(--color-marketing-slate);">Mai 2026</span>
                            </div>
                            <div class="text-[8px] font-semibold" style="color: var(--color-marketing-slate);">Taux recouvrement</div>
                            <div class="font-mono text-[16px] font-bold leading-none mt-1" style="color: var(--color-vivid);">86%</div>
                        </div>
                    </div>
                    <div class="text-[8px] font-bold tracking-widest uppercase mb-2" style="color: var(--color-marketing-slate);">Filtrer les clients</div>
                    <div class="flex gap-1.5 mb-2 text-[9px]">
                        <span class="text-white px-2 py-0.5 rounded-full font-semibold" style="background: var(--color-teal-deep);">Tous 25</span>
                        <span class="px-2 py-0.5 rounded-full" style="color: var(--color-vivid);">● À jour 19</span>
                        <span class="text-amber-700 px-2 py-0.5 rounded-full">● Surveiller 3</span>
                        <span class="text-rose-600 px-2 py-0.5 rounded-full">● Critiques 3</span>
                    </div>
                    <table class="w-full text-[9px]" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 32%;">
                            <col style="width: 16%;">
                            <col style="width: 16%;">
                            <col style="width: 20%;">
                            <col style="width: 16%;">
                        </colgroup>
                        <thead>
                            <tr class="text-[8px] uppercase font-bold tracking-wider" style="color: var(--color-marketing-slate);">
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Client</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Offre</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Impayés</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">En attente</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['init' => 'HN', 'name' => 'Horizon Negoces SARL', 'plan' => 'Essentiel', 'inv' => '8 factures', 'amt' => '4 720 000F', 'status' => 'Critique', 'color' => '#dc2626'],
                                ['init' => 'AC', 'name' => 'Atlas Chantier SA', 'plan' => 'Essentiel', 'inv' => '6 factures', 'amt' => '5 040 000F', 'status' => 'Critique', 'color' => '#dc2626'],
                                ['init' => 'NC', 'name' => 'Nova Chimie SARL', 'plan' => 'Basique', 'inv' => '1 facture', 'amt' => '420 000F', 'status' => 'Surveiller', 'color' => '#b45309'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 {{ $bb }}">
                                        <div class="flex items-center gap-2 min-w-0"><div class="w-5 h-5 rounded-md font-bold text-[8px] flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['init'] }}</div><div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">{{ $row['name'] }}</div></div>
                                    </td>
                                    <td class="py-1.5 text-center {{ $bb }}"><span class="text-[8px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['plan'] }}</span></td>
                                    <td class="py-1.5 font-mono text-[9px] font-bold text-center whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">{{ $row['inv'] }}</td>
                                    <td class="py-1.5 font-mono text-[10px] font-bold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">{{ $row['amt'] }}</td>
                                    <td class="py-1.5 text-[8px] font-semibold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">● {{ $row['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Row 2 — Commissions (mockup left, text right) --}}
        <div class="grid lg:grid-cols-2 gap-10 items-stretch mb-24">
            <div class="order-2 lg:order-1 relative hidden lg:block">
                <div class="absolute inset-0 rounded-3xl -z-0 transform -rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Programme partenaire</div>
                            <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Commissions &amp; partenariat</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">25 PME actives référées · Niveau Platinum · Mai 2026</div>
                        </div>
                        <span class="text-[9px] px-2 py-1 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-teal-deep); color: var(--color-mint-200);">★ Platinum</span>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mt-4 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-vivid);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/></svg></div>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-vivid);">Mai 26</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Commission du mois</div>
                            <div class="font-mono text-[15px] font-bold leading-none mt-1.5 whitespace-nowrap" style="color: var(--color-vivid);">64 500F</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">Cumul</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Cumul 2026</div>
                            <div class="font-mono text-[15px] font-bold leading-none mt-1.5 whitespace-nowrap" style="color: var(--color-marketing-ink);">287 200F</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg></div>
                                <span class="text-[9px] bg-gray-100 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="color: var(--color-marketing-slate);">Portefeuille</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">PME actives référées</div>
                            <div class="font-mono text-[18px] font-bold leading-none mt-1.5" style="color: var(--color-marketing-ink);">25</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md bg-blue-50 text-blue-700 flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
                                <span class="text-[9px] bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">Wave</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Prochaine paye</div>
                            <div class="font-mono text-[15px] font-bold leading-none mt-1.5 whitespace-nowrap" style="color: var(--color-marketing-ink);">05 Jun</div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="text-[10px] font-bold" style="color: var(--color-marketing-ink);">Progression de niveau</div>
                        <span class="text-[8px] font-semibold" style="color: var(--color-vivid);">Niveau Platinum atteint ✓</span>
                    </div>
                    <div class="relative h-1.5 bg-gray-100 rounded-full overflow-hidden mb-1"><div class="absolute inset-y-0 left-0 w-full" style="background: linear-gradient(to right, var(--color-mint-200), var(--color-vivid), var(--color-teal-deep));"></div></div>
                    <div class="grid grid-cols-3 text-[8px] mb-3" style="color: var(--color-marketing-slate);"><div>Partner 1–4</div><div class="text-center">Gold 5–14</div><div class="text-right text-blue-700 font-bold">Platinum 15+</div></div>
                    <div class="text-[9px] font-bold tracking-widest uppercase mb-1.5" style="color: var(--color-marketing-slate);">Dernières commissions</div>
                    <table class="w-full" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 56%;">
                            <col style="width: 26%;">
                            <col style="width: 18%;">
                        </colgroup>
                        <tbody>
                            @foreach ([
                                ['init' => 'SH', 'bg' => 'mint', 'name' => 'Saatys Home & Design', 'meta' => 'Pro · récurrent · J+18', 'tag' => '15 % MRR', 'tag_class' => 'mint', 'amount' => '+5 250F'],
                                ['init' => 'DN', 'bg' => 'mint', 'name' => 'Dakar Negoces SARL', 'meta' => 'Essentiel · 1ʳᵉ signature', 'tag' => 'Bonus +1 mois', 'tag_class' => 'amber', 'amount' => '+12 500F'],
                                ['init' => 'TS', 'bg' => 'violet', 'name' => 'Teranga Services', 'meta' => 'Essentiel · récurrent', 'tag' => '15 % MRR', 'tag_class' => 'mint', 'amount' => '+2 250F'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 {{ $bb }}">
                                        <div class="flex items-center gap-2 min-w-0">
                                            @if ($row['bg'] === 'mint')
                                                <div class="w-5 h-5 rounded-md font-bold text-[8px] flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['init'] }}</div>
                                            @else
                                                <div class="w-5 h-5 rounded-md bg-violet-100 text-violet-700 font-bold text-[8px] flex items-center justify-center shrink-0">{{ $row['init'] }}</div>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">{{ $row['name'] }}</div>
                                                <div class="text-[8px]" style="color: var(--color-marketing-slate);">{{ $row['meta'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-1.5 text-center {{ $bb }}">
                                        @if ($row['tag_class'] === 'mint')
                                            <span class="text-[8px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">{{ $row['tag'] }}</span>
                                        @else
                                            <span class="text-[8px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap">{{ $row['tag'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 font-mono text-[10px] font-bold text-right whitespace-nowrap {{ $bb }}" style="color: var(--color-vivid);">{{ $row['amount'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="order-1 lg:order-2 flex flex-col justify-center">
                <p class="eyebrow mb-3">Commissions</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Suivez vos revenus partenaire</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Progression de tiers Partner → Gold → Platinum, commission du mois et nombre de PME actives référées. Tout est transparent.</p>
            </div>
        </div>

        {{-- Row 3 — Alertes (text left, mockup right) --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="eyebrow mb-3">Alertes</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Restez informé sans effort</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Impayés critiques, retards anormaux, clients inactifs — Fayeku Compta vous alerte en temps réel pour que vous puissiez intervenir au bon moment.</p>
            </div>
            <div class="relative hidden lg:block">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Surveillance</div>
                            <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Alertes</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">11 alertes actives · 3 critiques à traiter · Avril 2026</div>
                        </div>
                        <span class="text-[9px] bg-rose-50 text-rose-600 px-2 py-1 rounded-full font-semibold whitespace-nowrap">3 critiques</span>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mt-4 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/></svg></div>
                                <span class="text-[9px] bg-rose-50 text-rose-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">&gt; 60 jours</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Critiques</div>
                            <div class="font-mono text-[18px] font-bold text-rose-600 leading-none mt-1.5">3</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                                <span class="text-[9px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">30–60 jours</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">À surveiller</div>
                            <div class="font-mono text-[18px] font-bold text-amber-700 leading-none mt-1.5">5</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-vivid);"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></svg></div>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-vivid);">&lt; 30 jours</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Inactivité</div>
                            <div class="font-mono text-[18px] font-bold leading-none mt-1.5" style="color: var(--color-marketing-ink);">3</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-6 h-6 rounded-md bg-blue-50 text-blue-700 flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div>
                                <span class="text-[9px] bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">Mai 26</span>
                            </div>
                            <div class="text-[10px] font-semibold" style="color: var(--color-marketing-slate);">Relances envoyées</div>
                            <div class="font-mono text-[18px] font-bold leading-none mt-1.5" style="color: var(--color-marketing-ink);">12</div>
                        </div>
                    </div>
                    <div class="text-[8px] font-bold tracking-widest uppercase mb-1.5" style="color: var(--color-marketing-slate);">Filtrer les alertes</div>
                    <div class="flex gap-1.5 mb-2 text-[9px]">
                        <span class="text-white px-2 py-0.5 rounded-full font-semibold" style="background: var(--color-teal-deep);">Toutes 11</span>
                        <span class="text-rose-600 px-2 py-0.5 rounded-full">● Critiques 3</span>
                        <span class="text-amber-700 px-2 py-0.5 rounded-full">● Surveiller 5</span>
                        <span class="px-2 py-0.5 rounded-full" style="color: var(--color-marketing-slate);">● Inactivité 3</span>
                    </div>
                    <table class="w-full text-[9px]" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 46%;">
                            <col style="width: 14%;">
                            <col style="width: 22%;">
                            <col style="width: 18%;">
                        </colgroup>
                        <thead>
                            <tr class="text-[8px] uppercase font-bold tracking-wider" style="color: var(--color-marketing-slate);">
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Client</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Factures</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Montant</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['icon' => '!', 'icon_bg' => 'bg-rose-50 text-rose-600', 'name' => 'Atlas Chantier SA', 'sub' => 'Impayé critique · J74 max', 'inv' => '6', 'amt' => '5 040 000F', 'status' => 'Critique', 'color' => '#dc2626'],
                                ['icon' => '!', 'icon_bg' => 'bg-rose-50 text-rose-600', 'name' => 'Horizon Negoces SARL', 'sub' => 'Impayé critique · J70 max', 'inv' => '8', 'amt' => '4 720 000F', 'status' => 'Critique', 'color' => '#dc2626'],
                                ['icon' => '~', 'icon_bg' => 'bg-amber-50 text-amber-700', 'name' => 'Nova Chimie SARL', 'sub' => 'Retard anormal · J45', 'inv' => '3', 'amt' => '1 350 000F', 'status' => 'Surveiller', 'color' => '#b45309'],
                                ['icon' => '·', 'icon_bg' => 'bg-gray-100', 'name' => 'Teranga Services', 'sub' => 'Inactif depuis 84 jours', 'inv' => '—', 'amt' => '—', 'status' => 'Inactif', 'color' => 'var(--color-marketing-slate)'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 {{ $bb }}">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-5 h-5 rounded-md {{ $row['icon_bg'] }} font-bold text-[10px] flex items-center justify-center shrink-0" @if ($row['icon_bg'] === 'bg-gray-100') style="color: var(--color-marketing-slate);" @endif>{{ $row['icon'] }}</div>
                                            <div class="min-w-0">
                                                <div class="text-[10px] font-semibold truncate" style="color: var(--color-marketing-ink);">{{ $row['name'] }}</div>
                                                <div class="text-[8px] truncate" style="color: var(--color-marketing-slate);">{{ $row['sub'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-1.5 font-mono text-[9px] font-bold text-center whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">{{ $row['inv'] }}</td>
                                    <td class="py-1.5 font-mono text-[10px] font-bold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">{{ $row['amt'] }}</td>
                                    <td class="py-1.5 text-[8px] font-semibold text-right whitespace-nowrap {{ $bb }}" style="color: {{ $row['color'] }};">● {{ $row['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div>
            <p class="eyebrow mb-3">Pièces &amp; exports</p>
            <h2 class="h2 mb-5">Fini les WhatsApp en fin de mois.</h2>
            <p class="text-lg leading-relaxed mb-6" style="color: var(--color-marketing-slate);">Vos clients déposent leurs justificatifs directement dans Fayeku. Vous récupérez tout, classé par dossier et par mois, prêt à exporter vers Sage 100, EBP ou Excel.</p>
            <ul class="space-y-3" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-3"><span class="check"></span>Coffre-fort partagé client / cabinet, classé par nature</li>
                <li class="flex items-start gap-3"><span class="check"></span>Relances automatiques pour les pièces manquantes</li>
                <li class="flex items-start gap-3"><span class="check"></span>Exports prêts à importer : Sage 100, EBP, CSV libre</li>
                <li class="flex items-start gap-3"><span class="check"></span>Journal d'audit pour traçabilité complète</li>
            </ul>
        </div>
        <div class="relative">
            <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
            <div class="relative bg-white rounded-2xl border border-gray-100 p-6" style="box-shadow: var(--shadow-float);">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Comptabilité</div>
                        <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Export groupé</div>
                        <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Paramètres de l'export comptable</div>
                    </div>
                </div>
                <div class="text-[11px] font-semibold mt-5 mb-1.5" style="color: var(--color-marketing-ink);">Format</div>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <label class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 cursor-pointer" style="border: 2px solid var(--color-teal-fayeku); background: var(--color-mint-50);">
                        <span class="w-3.5 h-3.5 rounded-full bg-white" style="border: 3px solid var(--color-teal-fayeku);"></span>
                        <span class="text-[11px] font-semibold" style="color: var(--color-marketing-ink);">Excel</span>
                    </label>
                    <label class="flex items-center gap-1.5 border border-gray-200 rounded-lg px-2.5 py-2 cursor-pointer">
                        <span class="w-3.5 h-3.5 rounded-full border border-gray-300"></span>
                        <span class="text-[11px]" style="color: var(--color-marketing-slate);">Sage 100</span>
                    </label>
                    <label class="flex items-center gap-1.5 border border-gray-200 rounded-lg px-2.5 py-2 cursor-pointer">
                        <span class="w-3.5 h-3.5 rounded-full border border-gray-300"></span>
                        <span class="text-[11px]" style="color: var(--color-marketing-slate);">EBP</span>
                    </label>
                </div>
                <div class="border rounded-lg px-3 py-2.5 mb-4" style="background: var(--color-mint-50); border-color: var(--color-mint-100);">
                    <div class="text-[11px] font-semibold" style="color: var(--color-teal-fayeku);">32 factures · Mai 2026 · Excel · 25 clients</div>
                    <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Inclut écritures de vente, encaissements et grand-livre auxiliaire.</div>
                </div>
                <button type="button" class="w-full text-[12px] font-semibold py-3 rounded-lg flex items-center justify-center gap-2" style="background: var(--color-teal-fayeku); color: var(--color-vivid);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Générer et télécharger
                </button>
            </div>
        </div>
    </div>
</section>

<section id="programme" class="relative overflow-hidden" style="background: var(--color-teal-fayeku);">
    <div class="grain absolute inset-0 opacity-40"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-12 sm:py-16 lg:py-24 relative grid lg:grid-cols-2 gap-12 items-start">
        <div>
            <p class="eyebrow-mint mb-5">Experts-comptables</p>
            <h2 class="h2 text-white mb-6 leading-[1.05]">Programme Partenaire<br/>Experts-Comptables</h2>
            <p class="text-lg leading-relaxed max-w-md" style="color: rgba(212, 240, 224, 0.8);">Recommandez Fayeku à vos clients et gagnez une commission récurrente, tant que vos clients restent actifs.</p>
        </div>
        <div class="space-y-3">
            @foreach ([
                '15% de commission mensuelle récurrente, à vie',
                'Bonus première signature : +1 mois de MRR',
                'Les PME invitées via votre lien reçoivent 30 jours Essentiel offerts',
                'Versement : Wave le 5 du mois',
            ] as $bullet)
                <div class="border border-white/15 rounded-full px-6 py-4 text-white text-base">{{ $bullet }}</div>
            @endforeach

            <div class="border border-white/15 rounded-3xl divide-y divide-white/10 mt-6">
                @foreach ([
                    ['Commission mensuelle récurrente', '15% sur tous les plans, pour toujours'],
                    ['Bonus première signature', '+1 mois MRR'],
                    ['Trial PMEs invitées via lien comptable', '30 jours Essentiel offerts'],
                    ['Versement', 'Wave le 5 du mois'],
                ] as $row)
                    <div class="grid grid-cols-[1.4fr_1fr] gap-6 px-6 py-5">
                        <div class="text-white font-bold text-base">{{ $row[0] }}</div>
                        <div class="text-base" style="color: rgba(212, 240, 224, 0.85);">{{ $row[1] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="pt-6 flex justify-center">
                <a href="{{ route('marketing.accountants.join') }}" class="bg-white font-bold text-base px-10 py-4 rounded-full transition hover:bg-[color:var(--color-mint-50)]" style="color: var(--color-teal-fayeku);">Devenir partenaire</a>
            </div>
        </div>
    </div>
</section>

{{-- Statuts Partenaires --}}
<section class="py-12 sm:py-16 lg:py-24" style="background: rgba(241, 250, 244, 0.4);">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-10">
            <p class="eyebrow mb-3">Statuts partenaires</p>
            <h2 class="h2 mb-3">Partner, Gold ou Platinum selon votre portefeuille actif.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Le programme monte en puissance avec votre nombre de clients actifs Fayeku.</p>
        </div>

        @php
            $tierRows = [
                ['Clients actifs Fayeku',            '1 – 4', '5 – 14', '15+'],
                ['Commission 15% récurrente',        '✓', '✓', '✓'],
                ['Accès Compta complet',             '✓', '✓', '✓'],
                ['Badge partenaire officiel',        '✗', '✓', '✓'],
                ['Visibilité sur fayeku.sn',         '✗', '✓', '✓'],
                ['Leads PME entrants de Fayeku',     '✗', '✓', '✓'],
                ['Groupe WhatsApp Partners',         '✗', '✓', '✓'],
                ['Account manager dédié',            '✗', '✗', '✓'],
                ['Co-marketing & événements',        '✗', '✗', '✓'],
                ['Bonus trimestriel top prescripteur','✗', '✗', '✓'],
            ];
        @endphp

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="table-layout: fixed;">
                    <colgroup>
                        <col style="width: 40%;">
                        <col style="width: 20%;">
                        <col style="width: 20%;">
                        <col style="width: 20%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-left p-4 text-[11px] uppercase tracking-wider font-semibold" style="color: var(--color-marketing-slate);">Fonctionnalité</th>
                            <th class="p-4 text-center font-semibold" style="color: var(--color-marketing-slate);">Partner</th>
                            <th class="p-4 text-center font-semibold" style="color: var(--color-marketing-slate);">Gold</th>
                            <th class="p-4 text-center font-bold rounded-t-2xl" style="background: var(--color-teal-fayeku); color: var(--color-vivid);">Platinum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($tierRows as $row)
                            @php $isLastRow = $loop->last; @endphp
                            <tr>
                                <td class="p-4 font-semibold" style="color: var(--color-marketing-ink);">{{ $row[0] }}</td>
                                @foreach ([$row[1], $row[2], $row[3]] as $cIdx => $val)
                                    @php
                                        $isHighlight = $cIdx === 2;
                                        $isCheck = $val === '✓';
                                        $isCross = $val === '✗';
                                    @endphp
                                    <td class="p-4 text-center font-medium {{ $isHighlight ? 'bg-[color:var(--color-mint-50)]/40' : '' }} {{ $isHighlight && $isLastRow ? 'rounded-b-2xl' : '' }}">
                                        @if ($isCheck)
                                            <span class="inline-flex w-6 h-6 rounded-full items-center justify-center" style="color: var(--color-vivid);">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            </span>
                                        @elseif ($isCross)
                                            <span class="inline-flex w-6 h-6 rounded-full items-center justify-center text-gray-300">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </span>
                                        @else
                                            <span style="color: var(--color-marketing-slate);">{{ $val }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="px-5 lg:px-8 py-12 sm:py-16 lg:py-24">
    <div class="max-w-7xl mx-auto rounded-3xl py-10 px-5 sm:py-14 sm:px-6 text-center relative overflow-hidden" style="background: var(--color-mint-100);">
        <div class="relative">
            <h2 class="h2 mb-4 max-w-2xl mx-auto">Activez votre espace cabinet en 5 minutes.</h2>
            <p class="text-lg mb-8 max-w-xl mx-auto" style="color: var(--color-marketing-slate);">Fayeku Compta est gratuit et le restera. Aucun engagement, aucune carte bancaire.</p>
            <a href="{{ route('marketing.accountants.join') }}" class="btn-primary">Demander un accès cabinet
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

</x-layouts.marketing>

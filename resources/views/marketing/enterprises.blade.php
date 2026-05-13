<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-12 sm:pt-14 sm:pb-16 lg:pt-20 lg:pb-24 grid lg:grid-cols-12 gap-10 items-center relative">
        <div class="lg:col-span-6">
            <p class="eyebrow mb-4">Pour les PME sénégalaises</p>
            <h1 class="h1 mb-6">Facturez, suivez, encaissez.<br/><span style="color: var(--color-teal-fayeku);">Tout en un seul endroit.</span></h1>
            <p class="text-lg max-w-xl mb-8 leading-relaxed" style="color: var(--color-marketing-slate);">Fayeku est pensé pour les entreprises sénégalaises qui veulent professionnaliser leur facturation, réduire les impayés et garder la main sur leur trésorerie.</p>
            <div class="flex flex-wrap gap-3 mb-6">
                <a href="{{ route('register') }}" class="btn-primary">Essayer 30 jours<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
                <a href="{{ route('marketing.pricing') }}" class="btn-secondary">Voir les tarifs</a>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color: var(--color-marketing-slate);">
                <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>30 jours d'essai</span>
                <span class="flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0FB85C" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Paiement Wave & Orange Money</span>
            </div>
        </div>
        <div class="lg:col-span-6 relative">
            <div class="absolute -top-10 -right-8 w-72 h-72 rounded-full blur-3xl opacity-60 -z-0" style="background: var(--color-mint-200);"></div>
            <div class="absolute -bottom-10 -left-8 w-56 h-56 rounded-full blur-3xl -z-0" style="background: rgba(15, 184, 92, 0.15);"></div>
            <div class="relative max-w-[480px] mx-auto">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-6 animate-float" style="box-shadow: var(--shadow-float);">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <div class="w-10 h-10 rounded-lg text-white font-bold text-sm flex items-center justify-center mb-2" style="background: var(--color-teal-fayeku);">AN</div>
                            <div class="text-xs" style="color: var(--color-marketing-slate);">Atelier Numérique SARL</div>
                            <div class="text-[10px] font-mono" style="color: var(--color-marketing-slate);">NINEA 005422198</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Facture</div>
                            <div class="font-mono text-sm font-bold" style="color: var(--color-marketing-ink);">FAC-2026-0147</div>
                            <span class="inline-block mt-1 text-[10px] font-semibold tracking-wider uppercase px-2 py-0.5 rounded-full" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">Émise · 11 mai</span>
                        </div>
                    </div>
                    <div class="space-y-2 border-y border-gray-100 py-4 mb-4">
                        <div class="flex items-center justify-between text-[12px]"><span style="color: var(--color-marketing-ink);">Refonte site web · Sayar Distribution</span><span class="font-mono font-semibold">1 250 000</span></div>
                        <div class="flex items-center justify-between text-[12px]"><span style="color: var(--color-marketing-ink);">Hébergement annuel · 12 mois</span><span class="font-mono font-semibold">225 000</span></div>
                        <div class="flex items-center justify-between text-[12px]" style="color: var(--color-marketing-slate);"><span>TVA 18%</span><span class="font-mono">265 500</span></div>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Total TTC</div>
                        <div class="font-mono text-2xl font-bold" style="color: var(--color-marketing-ink);">1 740 500 <span class="text-sm" style="color: var(--color-marketing-slate);">FCFA</span></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl p-2.5 text-center" style="background: var(--color-mint-50);"><div class="text-[9px] uppercase tracking-wider font-semibold mb-0.5" style="color: var(--color-marketing-slate);">Échéance</div><div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">10 juin</div></div>
                        <div class="rounded-xl p-2.5 text-center" style="background: var(--color-mint-50);"><div class="text-[9px] uppercase tracking-wider font-semibold mb-0.5" style="color: var(--color-marketing-slate);">Canal</div><div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">WhatsApp</div></div>
                        <div class="rounded-xl p-2.5 text-center" style="background: var(--color-mint-50);"><div class="text-[9px] uppercase tracking-wider font-semibold mb-0.5" style="color: var(--color-marketing-slate);">Lue</div><div class="text-[11px] font-bold" style="color: var(--color-vivid);">✓ 09:32</div></div>
                    </div>
                </div>
            </div>
            <div class="hidden sm:flex absolute -top-5 -left-2 lg:-left-6 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[230px] animate-float" style="box-shadow: var(--shadow-float); animation-delay: -2s;">
                <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[11px] font-medium" style="color: var(--color-marketing-slate);">Facture envoyée</div>
                    <div class="text-[12px] font-semibold" style="color: var(--color-marketing-ink);">Sayar Distribution</div>
                    <div class="text-[10px] font-semibold" style="color: var(--color-vivid);">WhatsApp · à l'instant</div>
                </div>
            </div>
            <div class="hidden sm:flex absolute -bottom-6 -right-2 lg:-right-4 bg-white rounded-2xl border border-gray-100 p-3 items-center gap-3 max-w-[240px] animate-float" style="box-shadow: var(--shadow-float); animation-delay: -4s;">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-mint-100); color: var(--color-vivid);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-[11px] font-medium" style="color: var(--color-marketing-slate);">Paiement reçu</div>
                    <div class="font-mono text-[14px] font-bold" style="color: var(--color-marketing-ink);">+1 740 500 FCFA</div>
                    <div class="text-[10px]" style="color: var(--color-marketing-slate);">Wave · J+4</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-12 sm:py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Bénéfices concrets</p>
            <h2 class="h2 mb-5">Ce que vous gagnez avec Fayeku.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Des résultats mesurables sur votre trésorerie, votre relation client et votre comptabilité.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            @foreach ([
                ['big' => '−40', 'unit' => '%', 'title' => 'de délai de paiement', 'desc' => 'Les relances WhatsApp et email automatiques font la différence dès le premier mois.'],
                ['big' => '+12', 'unit' => 'h', 'title' => 'économisées par mois', 'desc' => "Plus d'Excel, plus de WhatsApp manuel, plus de fichiers perdus."],
                ['big' => '100', 'unit' => '%', 'title' => 'de visibilité trésorerie', 'desc' => 'Sachez à tout moment ce qui est facturé, encaissé et en retard.'],
            ] as $stat)
                <article class="card p-7">
                    <div class="font-mono text-5xl font-bold tracking-tight mb-2" style="color: var(--color-teal-fayeku);">{{ $stat['big'] }}<span class="text-2xl ml-1 align-top">{{ $stat['unit'] }}</span></div>
                    <div class="font-semibold mb-2">{{ $stat['title'] }}</div>
                    <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{{ $stat['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div>
            <p class="eyebrow mb-3">Anatomie d'une facture Fayeku</p>
            <h2 class="h2 mb-5">Tout y est. Rien n'est laissé au hasard.</h2>
            <p class="text-lg leading-relaxed mb-6" style="color: var(--color-marketing-slate);">Logo, NINEA, mentions légales, TVA, numérotation continue. Conforme dès aujourd'hui, prête pour la transition DGID demain.</p>
            <ul class="space-y-3" style="color: var(--color-marketing-ink);">
                <li class="flex items-start gap-3"><span class="check"></span>Logo, coordonnées et NINEA pré-remplis</li>
                <li class="flex items-start gap-3"><span class="check"></span>Numérotation continue automatique</li>
                <li class="flex items-start gap-3"><span class="check"></span>TVA 18% calculée et détaillée</li>
                <li class="flex items-start gap-3"><span class="check"></span>QR code de paiement Wave / Orange Money</li>
            </ul>
        </div>
        <div class="relative">
            <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
            <div class="relative bg-white rounded-3xl border border-gray-100 p-6 lg:p-8 max-w-[560px] mx-auto" style="box-shadow: var(--shadow-float);">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <div class="w-12 h-12 rounded-xl text-white font-bold flex items-center justify-center mb-2" style="background: var(--color-teal-fayeku);">AN</div>
                        <div class="text-sm font-semibold" style="color: var(--color-marketing-ink);">Atelier Numérique SARL</div>
                        <div class="text-[11px]" style="color: var(--color-marketing-slate);">Dakar, Sénégal</div>
                        <div class="text-[10px] font-mono mt-1" style="color: var(--color-marketing-slate);">NINEA 005422198 · RCCM SN-DKR-2021-B-12345</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Facture N°</div>
                        <div class="font-mono text-base font-bold" style="color: var(--color-marketing-ink);">FAC-2026-0147</div>
                        <div class="text-[10px] mt-1" style="color: var(--color-marketing-slate);">Émise le 11 mai 2026</div>
                        <div class="text-[10px]" style="color: var(--color-marketing-slate);">Échéance 10 juin 2026</div>
                    </div>
                </div>
                <div class="rounded-xl p-4 mb-5" style="background: var(--color-mint-50);">
                    <div class="text-[10px] uppercase tracking-widest font-semibold mb-1" style="color: var(--color-marketing-slate);">Facturé à</div>
                    <div class="text-sm font-semibold" style="color: var(--color-marketing-ink);">Sayar Distribution</div>
                    <div class="text-[11px]" style="color: var(--color-marketing-slate);">Plateau, Dakar · NINEA 008891234</div>
                </div>
                <div class="space-y-1.5 mb-5">
                    <div class="grid grid-cols-12 text-[10px] uppercase tracking-wider font-semibold pb-2 border-b border-gray-100" style="color: var(--color-marketing-slate);">
                        <div class="col-span-7">Description</div><div class="col-span-2 text-right">Qté</div><div class="col-span-3 text-right">Montant</div>
                    </div>
                    <div class="grid grid-cols-12 py-2 text-[12px] border-b border-gray-50">
                        <div class="col-span-7" style="color: var(--color-marketing-ink);">Refonte site web</div><div class="col-span-2 text-right font-mono">1</div><div class="col-span-3 text-right font-mono font-semibold">1 250 000</div>
                    </div>
                    <div class="grid grid-cols-12 py-2 text-[12px] border-b border-gray-50">
                        <div class="col-span-7" style="color: var(--color-marketing-ink);">Hébergement 12 mois</div><div class="col-span-2 text-right font-mono">1</div><div class="col-span-3 text-right font-mono font-semibold">225 000</div>
                    </div>
                </div>
                <div class="space-y-1.5 mb-5">
                    <div class="flex justify-between text-[12px]" style="color: var(--color-marketing-slate);"><span>Sous-total HT</span><span class="font-mono">1 475 000</span></div>
                    <div class="flex justify-between text-[12px]" style="color: var(--color-marketing-slate);"><span>TVA 18%</span><span class="font-mono">265 500</span></div>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                        <span class="text-[11px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Total TTC</span>
                        <span class="font-mono text-xl font-bold" style="color: var(--color-marketing-ink);">1 740 500 <span class="text-xs" style="color: var(--color-marketing-slate);">FCFA</span></span>
                    </div>
                </div>
                <div class="flex items-center justify-between rounded-xl p-3" style="background: var(--color-mint-50);">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#024D4E"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="12" y="12" width="4" height="4"/></svg>
                        </div>
                        <div class="leading-tight">
                            <div class="text-[10px] uppercase tracking-widest font-semibold" style="color: var(--color-marketing-slate);">Payer via</div>
                            <div class="text-[12px] font-semibold" style="color: var(--color-marketing-ink);">Wave · Orange Money · Virement</div>
                        </div>
                    </div>
                    <span class="text-[10px] font-semibold tracking-wider uppercase text-white px-2 py-1 rounded-full" style="background: var(--color-teal-fayeku);">QR code</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Cas d'usage</p>
            <h2 class="h2 mb-5">Conçu pour vos réalités terrain.</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ([
                ['title' => 'Sociétés de services &amp; agences', 'desc' => 'Devis signés en ligne, conversion automatique en facture, suivi par projet.', 'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
                ['title' => 'Commerce &amp; distribution', 'desc' => 'Factures B2B récurrentes, encours clients, paiements échelonnés suivis.', 'svg' => '<path d="M16 11V7a4 4 0 0 0-8 0v4"/><rect x="3" y="11" width="18" height="11" rx="2"/>'],
                ['title' => 'Cabinets de conseil &amp; freelances', 'desc' => 'Facturation au forfait ou à la journée, exports en un clic pour le comptable.', 'svg' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
                ['title' => 'BTP &amp; installateurs', 'desc' => 'Avances, situations de travaux, retenues de garantie : structurés et suivis.', 'svg' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>'],
                ['title' => 'Restauration &amp; hôtellerie', 'desc' => 'Factures groupes &amp; événements, contrats fournisseurs, multi-points de vente.', 'svg' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/>'],
                ['title' => 'Santé &amp; professions libérales', 'desc' => 'Factures honoraires, suivi des règlements mutuelles &amp; entreprises.', 'svg' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>'],
            ] as $card)
                <article class="card p-6">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['svg'] !!}</svg>
                    </div>
                    <h3 class="font-semibold mb-1">{!! $card['title'] !!}</h3>
                    <p class="text-sm" style="color: var(--color-marketing-slate);">{!! $card['desc'] !!}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24" style="background: rgba(241, 250, 244, 0.4);">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-12 sm:py-16 lg:py-24">
        <div class="max-w-3xl mb-16">
            <p class="eyebrow mb-3">L'admin PME</p>
            <h2 class="h2 mb-5">Un cockpit pensé pour votre quotidien.</h2>
            <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Factures, devis, relances, trésorerie, fiche client — tout votre cycle commercial dans une interface unique, conçue pour les PME sénégalaises.</p>
        </div>

        {{-- Row 1 — Factures --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center mb-24">
            <div>
                <p class="eyebrow mb-3">Facturation</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Émettez et suivez vos factures en un clic</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Tableau de bord mensuel : factures émises, impayées, montant facturé HT et factures en retard. Filtrez par statut, recherchez en un instant.</p>
            </div>
            <div class="relative">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Facturation · Mai 2026 · 53 factures</div>
                            <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Factures</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Gérez vos factures clients.</div>
                        </div>
                        <button type="button" class="text-[10px] font-semibold px-3 py-1.5 rounded-lg" style="background: var(--color-teal-fayeku); color: var(--color-vivid);">+ Nouvelle facture</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div><span class="text-[7px] bg-gray-100 px-1.5 py-0.5 rounded-full font-semibold" style="color: var(--color-marketing-slate);">Ce mois</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">Factures émises</div><div class="font-mono text-[16px] font-bold leading-none mt-0.5" style="color: var(--color-marketing-ink);">7</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span class="text-[7px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">Impayées</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">En attente</div><div class="font-mono text-[16px] font-bold text-amber-700 leading-none mt-0.5">23</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-vivid);"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/></svg></div><span class="text-[7px] bg-gray-100 px-1.5 py-0.5 rounded-full font-semibold" style="color: var(--color-marketing-slate);">HT</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">Montant facturé</div><div class="font-mono text-[11px] font-bold leading-none mt-0.5" style="color: var(--color-marketing-ink);">10 900 000F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/></svg></div><span class="text-[7px] bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded-full font-semibold">Action</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">En retard</div><div class="font-mono text-[16px] font-bold text-rose-600 leading-none mt-0.5">18</div></div>
                    </div>
                    <div class="text-[8px] font-bold tracking-widest uppercase mb-1.5" style="color: var(--color-marketing-slate);">Filtrer par statut</div>
                    <div class="flex gap-1.5 mb-2 text-[9px]"><span class="text-white px-2 py-0.5 rounded-full font-semibold" style="background: var(--color-teal-deep);">Tous 53</span><span class="text-amber-700 px-2 py-0.5 rounded-full border border-gray-100">Brouillon 9</span><span class="text-blue-600 px-2 py-0.5 rounded-full border border-gray-100">Envoyée 15</span><span class="px-2 py-0.5 rounded-full border border-gray-100" style="color: var(--color-vivid);">Payée 21</span><span class="text-rose-600 px-2 py-0.5 rounded-full border border-gray-100">Retard 3</span></div>
                    <table class="w-full text-[9px]" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 22%;">
                            <col style="width: 32%;">
                            <col style="width: 18%;">
                            <col style="width: 12%;">
                            <col style="width: 16%;">
                        </colgroup>
                        <thead>
                            <tr class="text-[8px] uppercase font-bold tracking-wider" style="color: var(--color-marketing-slate);">
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Référence</th>
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Client</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">TTC</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Échéance</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['ref' => 'FYK-FAC-1QSWKV', 'client' => 'Ibrahima Ciss', 'amt' => '1 500 000F', 'due' => '09 Jun', 'status' => 'Part. payée', 'amt_cls' => '', 'due_cls' => '', 'status_cls' => 'text-amber-700 bg-amber-50'],
                                ['ref' => 'FYK-FAC-UAZTQT', 'client' => 'Agence Informatique État', 'amt' => '600 000F', 'due' => '08 Jun', 'status' => 'Envoyée', 'amt_cls' => '', 'due_cls' => '', 'status_cls' => 'text-blue-700 bg-blue-50'],
                                ['ref' => 'FYK-FAC-DS0501', 'client' => 'LafargeHolcim Sénégal', 'amt' => '3 304 000F', 'due' => 'J+95', 'status' => 'En retard', 'amt_cls' => 'text-rose-600 font-bold', 'due_cls' => 'text-rose-600 font-bold', 'status_cls' => 'text-rose-600 bg-rose-50'],
                                ['ref' => 'FYK-FAC-DS0801', 'client' => 'Agence Informatique État', 'amt' => '7 670 000F', 'due' => '09 Mai', 'status' => 'Payée', 'amt_cls' => '', 'due_cls' => '', 'status_cls' => 'mint'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 font-mono truncate {{ $bb }}" style="color: var(--color-marketing-ink);">{{ $row['ref'] }}</td>
                                    <td class="py-1.5 truncate {{ $bb }}" style="color: var(--color-marketing-slate);">{{ $row['client'] }}</td>
                                    <td class="py-1.5 font-mono text-right whitespace-nowrap {{ $row['amt_cls'] }} {{ $bb }}" @if ($row['amt_cls'] === '') style="color: var(--color-marketing-ink);" @endif>{{ $row['amt'] }}</td>
                                    <td class="py-1.5 text-right whitespace-nowrap {{ $row['due_cls'] }} {{ $bb }}" @if ($row['due_cls'] === '') style="color: var(--color-marketing-slate);" @endif>{{ $row['due'] }}</td>
                                    <td class="py-1.5 text-center {{ $bb }}">
                                        @if ($row['status_cls'] === 'mint')
                                            <span class="px-1.5 py-0.5 rounded font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-vivid);">{{ $row['status'] }}</span>
                                        @else
                                            <span class="{{ $row['status_cls'] }} px-1.5 py-0.5 rounded font-semibold whitespace-nowrap">{{ $row['status'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Row 2 — Recouvrement (mockup left, text right) --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center mb-24">
            <div class="order-2 lg:order-1 relative">
                <div class="absolute inset-0 rounded-3xl -z-0 transform -rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Recouvrement</div>
                    <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Relances &amp; impayés</div>
                    <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">13 factures en attente · 21 372 200 FCFA à encaisser</div>
                    <div class="grid grid-cols-4 gap-2 mt-3 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0"/></svg></div><span class="text-[7px] bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded-full font-semibold">&gt; 60j</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">Critiques</div><div class="font-mono text-[16px] font-bold text-rose-600 leading-none mt-0.5">3</div><div class="text-[8px] font-mono mt-0.5" style="color: var(--color-marketing-slate);">4 755 400 F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span class="text-[7px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">30–60j</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">En retard</div><div class="font-mono text-[16px] font-bold text-amber-700 leading-none mt-0.5">1</div><div class="text-[8px] font-mono mt-0.5" style="color: var(--color-marketing-slate);">1 088 000 F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md flex items-center justify-center" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div><span class="text-[7px] px-1.5 py-0.5 rounded-full font-semibold" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">&lt; 30j</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">En attente</div><div class="font-mono text-[16px] font-bold leading-none mt-0.5" style="color: var(--color-teal-fayeku);">9</div><div class="text-[8px] font-mono mt-0.5" style="color: var(--color-marketing-slate);">15 528 800 F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="flex items-center justify-between"><div class="w-5 h-5 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div><span class="text-[7px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold">Mai 2026</span></div><div class="text-[8px] font-semibold mt-1" style="color: var(--color-marketing-slate);">Relances ce mois</div><div class="font-mono text-[16px] font-bold text-blue-600 leading-none mt-0.5">8</div></div>
                    </div>
                    <div class="text-[11px] font-bold mb-1" style="color: var(--color-marketing-ink);">Factures à relancer</div>
                    <div class="text-[9px] mb-2" style="color: var(--color-marketing-slate);">Classées par ancienneté de retard.</div>
                    <table class="w-full text-[9px]" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 22%;">
                            <col style="width: 36%;">
                            <col style="width: 18%;">
                            <col style="width: 12%;">
                            <col style="width: 12%;">
                        </colgroup>
                        <thead>
                            <tr class="text-[8px] uppercase font-bold tracking-wider" style="color: var(--color-marketing-slate);">
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Facture</th>
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Client</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Montant</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Retard</th>
                                <th class="pb-1.5 border-b border-gray-100"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['ref' => 'FYK-FAC-DS0501', 'client' => 'LafargeHolcim Sénégal', 'amt' => '3 304 000F', 'days' => '95j', 'days_bg' => 'bg-rose-50 text-rose-600'],
                                ['ref' => 'FYK-FAC-DS0502', 'client' => 'SENELEC', 'amt' => '802 400F', 'days' => '91j', 'days_bg' => 'bg-rose-50 text-rose-600'],
                                ['ref' => 'FYK-FAC-DS0503', 'client' => 'AUCHAN Sénégal', 'amt' => '649 000F', 'days' => '65j', 'days_bg' => 'bg-rose-50 text-rose-600'],
                                ['ref' => 'FYK-FAC-DS0504', 'client' => 'TotalEnergies Marketing SN', 'amt' => '1 088 000F', 'days' => '45j', 'days_bg' => 'bg-amber-50 text-amber-700'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 font-mono truncate {{ $bb }}" style="color: var(--color-marketing-ink);">{{ $row['ref'] }}</td>
                                    <td class="py-1.5 truncate {{ $bb }}" style="color: var(--color-marketing-slate);">{{ $row['client'] }}</td>
                                    <td class="py-1.5 font-mono text-right whitespace-nowrap {{ $bb }}" style="color: var(--color-marketing-ink);">{{ $row['amt'] }}</td>
                                    <td class="py-1.5 text-center {{ $bb }}">
                                        <span class="{{ $row['days_bg'] }} px-1.5 py-0.5 rounded font-semibold whitespace-nowrap">{{ $row['days'] }}</span>
                                    </td>
                                    <td class="py-1.5 text-right font-semibold whitespace-nowrap {{ $bb }}" style="color: var(--color-teal-fayeku);">Relancer</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <p class="eyebrow mb-3">Recouvrement</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Vos impayés, classés par âge de retard</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Critiques, en retard, en attente : Fayeku priorise les factures à relancer et déclenche WhatsApp, Email ou SMS selon votre stratégie de relance.</p>
            </div>
        </div>

        {{-- Row 3 — Trésorerie --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center mb-24">
            <div>
                <p class="eyebrow mb-3">Trésorerie</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Anticipez vos encaissements à 30 et 90 jours</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Pour chaque facture ouverte, Fayeku estime la date d'encaissement et le niveau de confiance en se basant sur l'historique de paiement du client. Vous voyez ce qui va tomber, et ce qui est à risque.</p>
            </div>
            <div class="relative">
                <div class="absolute inset-0 rounded-3xl -z-0 transform rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="text-[10px] font-bold tracking-widest uppercase" style="color: var(--color-vivid);">Trésorerie</div>
                            <div class="text-[16px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">Trésorerie</div>
                            <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Vision 30 jours · mai → juin 2026</div>
                        </div>
                        <div class="flex gap-1 text-[9px]"><span class="text-white px-2 py-0.5 rounded-full font-semibold" style="background: var(--color-teal-deep);">30 jours</span><span class="px-2 py-0.5 rounded-full border border-gray-100" style="color: var(--color-marketing-slate);">90 jours</span></div>
                    </div>
                    <div class="grid grid-cols-4 gap-2 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] font-semibold uppercase" style="color: var(--color-vivid);">Réalisé</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Encaissé à date</div><div class="font-mono text-[10px] font-bold mt-1" style="color: var(--color-marketing-ink);">40 585 400F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] font-semibold uppercase" style="color: var(--color-vivid);">30 jours</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Entrées prévues</div><div class="font-mono text-[10px] font-bold mt-1" style="color: var(--color-vivid);">10 150 456F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] text-amber-700 font-semibold uppercase">Historique</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Délai moyen</div><div class="font-mono text-[14px] font-bold mt-1" style="color: var(--color-marketing-ink);">21j</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] text-rose-600 font-semibold uppercase">Risqué</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Montant à risque</div><div class="font-mono text-[10px] font-bold text-rose-600 mt-1">25 997 800F</div></div>
                    </div>
                    <div class="text-[10px] font-bold mb-2" style="color: var(--color-marketing-ink);">Prévision d'encaissement</div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="border rounded-xl p-2.5" style="background: var(--color-mint-50); border-color: var(--color-mint-100);"><div class="flex items-center justify-between"><div class="text-[8px] font-bold tracking-widest uppercase" style="color: var(--color-marketing-slate);">Mois en cours</div><span class="text-[8px] bg-white px-1.5 py-0.5 rounded-full font-bold" style="color: var(--color-vivid);">47%</span></div><div class="text-[10px] font-semibold mt-1" style="color: var(--color-marketing-ink);">Mai 2026</div><div class="font-mono text-[11px] font-bold mt-0.5" style="color: var(--color-marketing-ink);">4 797 466F</div><div class="h-1 bg-white rounded-full mt-1.5 overflow-hidden"><div class="h-full w-[47%] bg-amber-400"></div></div><div class="text-[7px] mt-1" style="color: var(--color-marketing-slate);">13 factures · confiance 23%</div></div>
                        <div class="border rounded-xl p-2.5" style="background: var(--color-mint-50); border-color: var(--color-mint-100);"><div class="flex items-center justify-between"><div class="text-[8px] font-bold tracking-widest uppercase" style="color: var(--color-marketing-slate);">Mois suivant</div><span class="text-[8px] bg-white px-1.5 py-0.5 rounded-full font-bold" style="color: var(--color-vivid);">53%</span></div><div class="text-[10px] font-semibold mt-1" style="color: var(--color-marketing-ink);">Juin 2026</div><div class="font-mono text-[11px] font-bold mt-0.5" style="color: var(--color-vivid);">5 352 990F</div><div class="h-1 bg-white rounded-full mt-1.5 overflow-hidden"><div class="h-full w-[72%]" style="background: var(--color-vivid);"></div></div><div class="text-[7px] mt-1" style="color: var(--color-marketing-slate);">5 factures · confiance 72%</div></div>
                        <div class="border border-gray-100 rounded-xl p-2.5" style="background: var(--color-offwhite);"><div class="flex items-center justify-between"><div class="text-[8px] font-bold tracking-widest uppercase" style="color: var(--color-marketing-slate);">Tendance</div><span class="text-[8px] bg-white px-1.5 py-0.5 rounded-full font-bold" style="color: var(--color-marketing-slate);">0%</span></div><div class="text-[10px] font-semibold mt-1" style="color: var(--color-marketing-slate);">Au-delà</div><div class="font-mono text-[11px] font-bold mt-0.5" style="color: var(--color-marketing-slate);">0 FCFA</div><div class="h-1 bg-white rounded-full mt-1.5 overflow-hidden"></div><div class="text-[7px] mt-1" style="color: var(--color-marketing-slate);">Aucune entrée estimée</div></div>
                    </div>
                    <div class="text-[10px] font-bold mb-1" style="color: var(--color-marketing-ink);">Entrées attendues</div>
                    <table class="w-full text-[9px]" style="table-layout: fixed;">
                        <colgroup>
                            <col style="width: 22%;">
                            <col style="width: 32%;">
                            <col style="width: 18%;">
                            <col style="width: 12%;">
                            <col style="width: 16%;">
                        </colgroup>
                        <thead>
                            <tr class="text-[8px] uppercase font-bold tracking-wider" style="color: var(--color-marketing-slate);">
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Document</th>
                                <th class="text-left pb-1.5 border-b border-gray-100 font-bold">Client</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Montant</th>
                                <th class="text-right pb-1.5 border-b border-gray-100 font-bold">Confiance</th>
                                <th class="text-center pb-1.5 border-b border-gray-100 font-bold">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['ref' => 'FYK-FAC-DS0610', 'client' => 'Plan International Sénégal', 'amt' => '1 003 000F', 'conf' => '85%', 'status' => 'Fort', 'class' => 'vivid'],
                                ['ref' => 'FYK-FAC-1QSWKV', 'client' => 'Ibrahima Ciss', 'amt' => '1 500 000F', 'conf' => '95%', 'status' => 'Fort', 'class' => 'vivid'],
                                ['ref' => 'FYK-FAC-DS0607', 'client' => 'Wari Sénégal SA', 'amt' => '1 156 400F', 'conf' => '60%', 'status' => 'Moyen', 'class' => 'amber'],
                                ['ref' => 'FYK-FAC-DS0501', 'client' => 'LafargeHolcim Sénégal', 'amt' => '3 304 000F', 'conf' => '5%', 'status' => 'Risqué', 'class' => 'rose'],
                            ] as $row)
                                @php $bb = $loop->last ? '' : 'border-b border-gray-100'; @endphp
                                <tr>
                                    <td class="py-1.5 font-mono truncate {{ $bb }}" style="color: var(--color-marketing-ink);">{{ $row['ref'] }}</td>
                                    <td class="py-1.5 truncate {{ $bb }}" style="color: var(--color-marketing-slate);">{{ $row['client'] }}</td>
                                    <td class="py-1.5 font-mono text-right whitespace-nowrap {{ $bb }}" style="color: var(--color-marketing-ink);">{{ $row['amt'] }}</td>
                                    @if ($row['class'] === 'vivid')
                                        <td class="py-1.5 text-right font-semibold {{ $bb }}" style="color: var(--color-vivid);">{{ $row['conf'] }}</td>
                                        <td class="py-1.5 text-center {{ $bb }}"><span class="px-1.5 py-0.5 rounded font-semibold whitespace-nowrap" style="background: var(--color-mint-100); color: var(--color-vivid);">{{ $row['status'] }}</span></td>
                                    @elseif ($row['class'] === 'amber')
                                        <td class="py-1.5 text-right text-amber-700 font-semibold {{ $bb }}">{{ $row['conf'] }}</td>
                                        <td class="py-1.5 text-center {{ $bb }}"><span class="text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded font-semibold whitespace-nowrap">{{ $row['status'] }}</span></td>
                                    @else
                                        <td class="py-1.5 text-right text-rose-600 font-semibold {{ $bb }}">{{ $row['conf'] }}</td>
                                        <td class="py-1.5 text-center {{ $bb }}"><span class="text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded font-semibold whitespace-nowrap">{{ $row['status'] }}</span></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Row 4 — Fiche client (mockup left, text right) --}}
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div class="order-2 lg:order-1 relative">
                <div class="absolute inset-0 rounded-3xl -z-0 transform -rotate-1" style="background: var(--color-mint-100);"></div>
                <div class="relative bg-white rounded-2xl border border-gray-100 p-5" style="box-shadow: var(--shadow-card);">
                    <div class="flex items-center gap-2 mb-3"><span class="text-[9px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold">À surveiller · 56</span></div>
                    <div class="text-[18px] font-bold leading-tight" style="color: var(--color-marketing-ink);">Agence de l'Informatique de l'État</div>
                    <div class="text-[10px] mt-0.5" style="color: var(--color-marketing-slate);">Devis envoyé hier · FYK-DEV-CBR7HR</div>
                    <div class="grid grid-cols-4 gap-2 mt-4 mb-4">
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] font-bold uppercase tracking-widest" style="color: var(--color-marketing-slate);">Cumul</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Total facturé</div><div class="font-mono text-[10px] font-bold mt-1" style="color: var(--color-marketing-ink);">17 617 000F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] font-bold uppercase tracking-widest" style="color: var(--color-vivid);">Encaissé</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Total encaissé</div><div class="font-mono text-[10px] font-bold mt-1" style="color: var(--color-vivid);">11 325 000F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] text-rose-600 font-bold uppercase tracking-widest">Ouvert</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Solde en cours</div><div class="font-mono text-[10px] font-bold text-rose-600 mt-1">6 292 000F</div></div>
                        <div class="bg-white rounded-xl border border-gray-100 p-2.5"><div class="text-[7px] text-amber-700 font-bold uppercase tracking-widest">Paiement</div><div class="text-[7px] mt-0.5" style="color: var(--color-marketing-slate);">Délai moyen</div><div class="font-mono text-[14px] font-bold mt-1" style="color: var(--color-marketing-ink);">25j</div></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">Score paiement</div>
                            <div class="text-[9px] mb-2" style="color: var(--color-marketing-slate);">Délai, retards, impayés et relances.</div>
                            <div class="flex items-end gap-2"><div class="font-mono text-[32px] font-bold leading-none" style="color: var(--color-marketing-ink);">56</div><span class="text-[9px] bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-semibold mb-1">À surveiller</span></div>
                            <div class="h-1.5 bg-gray-100 rounded-full mt-2 overflow-hidden"><div class="h-full w-[56%] bg-gradient-to-r from-rose-400 via-amber-400 to-[color:var(--color-vivid)]"></div></div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold" style="color: var(--color-marketing-ink);">Stratégie de relance</div>
                            <div class="text-[9px] mb-1.5" style="color: var(--color-marketing-slate);">Active sur toutes les factures impayées.</div>
                            <div class="border rounded-lg p-2" style="background: var(--color-mint-50); border-color: var(--color-mint-100);"><div class="text-[10px] font-bold" style="color: var(--color-teal-fayeku);">Standard</div><div class="text-[8px]" style="color: var(--color-marketing-slate);">WhatsApp à J+3, J+7, J+15 et J+30</div></div>
                        </div>
                    </div>
                    <div class="bg-rose-50 border border-rose-100 rounded-lg p-2.5 mt-3"><div class="text-[10px] font-bold text-rose-700">Exposition au risque · 17%</div><div class="text-[8px] text-rose-700/80 mt-0.5">Ce client représente 17% de vos montants en attente, soit 6 292 000 FCFA.</div></div>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <p class="eyebrow mb-3">Clients</p>
                <h3 class="text-3xl font-bold mb-5 leading-tight">Une fiche par client, tout est centralisé</h3>
                <p class="text-lg leading-relaxed" style="color: var(--color-marketing-slate);">Total facturé, encaissé, solde, délai moyen et score de paiement explicable. La stratégie de relance s'applique automatiquement à toutes les factures du client.</p>
            </div>
        </div>
    </div>
</section>

<section class="px-5 lg:px-8 pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto rounded-3xl py-10 sm:py-12 lg:py-20 px-6 text-center relative overflow-hidden" style="background: var(--color-mint-100);">
        <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full blur-3xl" style="background: rgba(15, 184, 92, 0.10);"></div>
        <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl" style="background: rgba(2, 77, 78, 0.10);"></div>
        <div class="relative">
            <h2 class="h2 mb-4 max-w-2xl mx-auto">Démarrez votre essai gratuit.</h2>
            <p class="text-lg mb-8 max-w-xl mx-auto" style="color: var(--color-marketing-slate);">30 jours offerts. Sans engagement. Paiement Wave ou Orange Money.</p>
            <a href="{{ route('register') }}" class="btn-primary">Essayer 30 jours
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

</x-layouts.marketing>

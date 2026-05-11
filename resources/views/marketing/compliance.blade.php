<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-10 pb-10 sm:pt-14 sm:pb-12 lg:pt-24 lg:pb-20 relative">
        <p class="eyebrow mb-4">Conformité &amp; sécurité</p>
        <h1 class="h1 mb-6 max-w-4xl">Préparé pour la <span style="color: var(--color-teal-fayeku);">DGID.</span> Sécurisé pour vos données.</h1>
        <p class="text-lg max-w-2xl mb-2 leading-relaxed" style="color: var(--color-marketing-slate);">Fayeku intégrera la conformité DGID dans les plans Essentiel et Entreprise dès publication officielle des modalités techniques. En attendant, vos factures restent juridiquement valables et déjà structurées pour la transition.</p>
    </div>
</section>

<section class="pb-16">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="border rounded-3xl px-6 sm:px-8 lg:px-10 py-8 flex flex-col lg:flex-row items-start lg:items-center gap-6" style="background: var(--color-mint-50); border-color: var(--color-mint-200);">
            <div class="shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center" style="background: var(--color-teal-fayeku);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color: var(--color-vivid);">Conformité DGID : Fayeku vous prépare pour demain.</p>
                <p class="leading-relaxed" style="color: var(--color-marketing-slate);">Fayeku intégrera la conformité DGID dans les plans Essentiel et Entreprise. Lorsque les modalités officielles seront publiées, vos factures seront prêtes à être conformes, sans reparamétrage complexe.</p>
            </div>
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Notre engagement</p>
            <h2 class="h2 mb-5">Ce que Fayeku prépare pour la conformité.</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-5">
            @foreach ([
                ['title' => 'Mentions légales complètes', 'desc' => "NINEA, RCCM, TVA 18%, numérotation continue, mentions obligatoires : tout est intégré dès aujourd'hui.", 'svg' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
                ['title' => 'Archivage 10 ans', 'desc' => 'Toutes vos factures, devis et pièces conservés de manière inaltérable, accessibles à tout moment.', 'svg' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
                ['title' => 'Transmission vers la DGID', 'desc' => 'Architecture prête à intégrer le format et le canal officiels dès leur publication par la DGID.', 'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'],
                ['title' => 'Transition sans reparamétrage', 'desc' => 'Le jour J, vos factures basculent côté plateforme. Aucune action complexe à mener côté entreprise.', 'svg' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
            ] as $card)
                <article class="card p-7">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['svg'] !!}</svg>
                    </div>
                    <h3 class="font-semibold text-lg mb-2">{{ $card['title'] }}</h3>
                    <p class="text-sm" style="color: var(--color-marketing-slate);">{{ $card['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-12 sm:pb-16 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8">
        <div class="max-w-3xl mb-12">
            <p class="eyebrow mb-3">Sécurité &amp; hébergement</p>
            <h2 class="h2 mb-5">Vos données, protégées.</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ([
                ['title' => 'Chiffrement TLS 1.3', 'desc' => 'Toutes les communications sont chiffrées en transit et au repos (AES-256).', 'svg' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
                ['title' => 'Hébergement Europe', 'desc' => 'Serveurs Hetzner, Helsinki. Sauvegardes quotidiennes redondantes.', 'svg' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10"/>'],
                ['title' => 'Authentification renforcée', 'desc' => "Mots de passe hashés (Argon2), 2FA optionnelle, journal d'audit complet.", 'svg' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
                ['title' => 'Disponibilité 99,9%', 'desc' => "Architecture multi-zones avec bascule automatique en cas d'incident.", 'svg' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
                ['title' => 'Export libre des données', 'desc' => 'Vos factures, clients, paiements téléchargeables en CSV/PDF à tout moment.', 'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'],
                ['title' => 'Conformité RGPD', 'desc' => "Vous restez propriétaire de vos données. Droit d'accès, rectification, suppression respectés.", 'svg' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
            ] as $card)
                <article class="card p-6">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $card['svg'] !!}</svg>
                    </div>
                    <h3 class="font-semibold mb-1">{{ $card['title'] }}</h3>
                    <p class="text-sm" style="color: var(--color-marketing-slate);">{{ $card['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="px-5 lg:px-8 pb-12 sm:pb-16 lg:pb-28">
    <div class="max-w-7xl mx-auto rounded-3xl py-14 px-6 text-center relative overflow-hidden" style="background: var(--color-mint-100);">
        <div class="relative">
            <h2 class="h2 mb-4 max-w-2xl mx-auto">Une question sur la conformité ?</h2>
            <p class="text-lg mb-8 max-w-xl mx-auto" style="color: var(--color-marketing-slate);">Notre équipe répond en moins de 24h ouvrées.</p>
            <a href="{{ route('marketing.contact') }}" class="btn-primary">Nous contacter
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

</x-layouts.marketing>

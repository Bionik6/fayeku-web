<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
    <section class="py-20">
        <div class="mx-auto w-full max-w-4xl space-y-10 px-4 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">Légal</p>
                <div class="space-y-3">
                    <h1 class="text-balance text-3xl font-semibold text-ink sm:text-4xl">Mentions légales</h1>
                    <p class="text-pretty text-base leading-7 text-slate-600 sm:text-lg">Informations légales relatives à l&rsquo;éditeur et à l&rsquo;hébergement du site fayeku.sn.</p>
                </div>
            </div>

            <div class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                <dl class="grid gap-6 text-base leading-7 text-slate-600 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">Éditeur</dt>
                        <dd class="mt-2 text-ink">{{ $site['legal']['editor'] }} ({{ $site['legal']['company'] }})</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">Directeur de la publication</dt>
                        <dd class="mt-2 text-ink">{{ $site['legal']['editor'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">NINEA</dt>
                        <dd class="mt-2 text-ink">{{ $site['legal']['ninea'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">RCCM</dt>
                        <dd class="mt-2 text-ink">{{ $site['legal']['rccm'] }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">Siège social</dt>
                        <dd class="mt-2 text-ink">{{ $site['contact']['address'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">Email</dt>
                        <dd class="mt-2">
                            <a href="mailto:{{ $site['contact']['email'] }}" class="text-primary hover:underline">{{ $site['contact']['email'] }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-teal">Téléphone</dt>
                        <dd class="mt-2">
                            <a href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone']) }}" class="text-primary hover:underline">{{ $site['contact']['phone'] }}</a>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-6">
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Propriété intellectuelle</h2>
                    <p class="mt-3 text-base leading-7 text-slate-600">Les contenus, marques, logos et maquettes présentés sur ce site sont protégés et ne peuvent pas être réutilisés sans autorisation écrite préalable.</p>
                </article>

                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Hébergement</h2>
                    <p class="mt-3 text-base leading-7 text-slate-600">Le site fayeku.sn est hébergé sur une infrastructure cloud sécurisée à haute disponibilité. Les coordonnées complètes de l&rsquo;hébergeur sont communiquées sur demande à <a href="mailto:{{ $site['contact']['email'] }}" class="text-primary hover:underline">{{ $site['contact']['email'] }}</a>.</p>
                </article>
            </div>

            <p class="text-center text-sm text-slate-500">&copy; {{ now()->year }} {{ $site['name'] }} — Édité par {{ $site['legal']['editor'] }} ({{ $site['legal']['company'] }}).</p>
        </div>
    </section>
</x-layouts.marketing>

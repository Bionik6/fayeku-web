<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">

@if (session('success'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 8000)"
        x-show="show"
        x-transition.opacity
        role="status"
        aria-live="polite"
        class="fixed right-4 top-20 z-50 max-w-sm rounded-2xl border px-5 py-4 text-sm shadow-lg"
        style="background: var(--color-mint-50); border-color: var(--color-mint-200); color: var(--color-teal-fayeku);"
    >
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="flex-1">{{ session('success') }}</p>
            <button type="button" @click="show = false" class="ml-auto -mr-1 -mt-1 rounded-md p-1" aria-label="Fermer">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
@endif

<section class="hero-bg relative overflow-hidden">
    <div class="absolute -top-24 right-0 w-[480px] h-[480px] rounded-full blur-3xl opacity-50 -z-0" style="background: var(--color-mint-200);"></div>
    <div class="max-w-7xl mx-auto px-5 lg:px-8 pt-16 pb-12 lg:pt-24 lg:pb-16 relative">
        <p class="eyebrow mb-4">Contact</p>
        <h1 class="h1 mb-6 max-w-3xl">Parlons de votre <span style="color: var(--color-teal-fayeku);">trésorerie.</span></h1>
        <p class="text-lg max-w-2xl leading-relaxed" style="color: var(--color-marketing-slate);">Démo personnalisée, accompagnement, partenariat cabinet : l'équipe Fayeku vous répond en moins de 24h ouvrées.</p>
    </div>
</section>

<section class="pb-20 lg:pb-24">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 grid lg:grid-cols-5 gap-10">
        <form action="{{ route('marketing.contact.store') }}" method="POST" class="lg:col-span-3 card p-8 space-y-5">
            @csrf
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="full_name" class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Nom complet</label>
                    <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" placeholder="Awa Diop" required class="w-full border rounded-xl px-4 py-3 text-sm outline-none focus:border-[color:var(--color-teal-fayeku)] focus:ring-2 focus:ring-[color:var(--color-mint-200)] {{ $errors->has('full_name') ? 'border-rose-400' : 'border-gray-200' }}" />
                    @error('full_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="company" class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Entreprise</label>
                    <input id="company" name="company" type="text" value="{{ old('company') }}" placeholder="Atelier Numérique SARL" class="w-full border rounded-xl px-4 py-3 text-sm outline-none focus:border-[color:var(--color-teal-fayeku)] focus:ring-2 focus:ring-[color:var(--color-mint-200)] {{ $errors->has('company') ? 'border-rose-400' : 'border-gray-200' }}" />
                    @error('company') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="email" class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Email pro</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="awa@atelier.sn" required class="w-full border rounded-xl px-4 py-3 text-sm outline-none focus:border-[color:var(--color-teal-fayeku)] focus:ring-2 focus:ring-[color:var(--color-mint-200)] {{ $errors->has('email') ? 'border-rose-400' : 'border-gray-200' }}" />
                    @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Téléphone / WhatsApp</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="+221 77 ..." class="w-full border rounded-xl px-4 py-3 text-sm outline-none focus:border-[color:var(--color-teal-fayeku)] focus:ring-2 focus:ring-[color:var(--color-mint-200)] {{ $errors->has('phone') ? 'border-rose-400' : 'border-gray-200' }}" />
                    @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <span class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Vous êtes</span>
                <div class="grid sm:grid-cols-3 gap-2">
                    @foreach (['pme' => 'Une PME', 'expert' => 'Un expert-comptable', 'autre' => 'Autre'] as $value => $label)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2.5 text-sm cursor-pointer hover:border-[color:var(--color-teal-fayeku)] hover:bg-[color:var(--color-mint-50)]">
                            <input type="radio" name="profile" value="{{ $value }}" class="accent-[color:var(--color-teal-fayeku)]" @checked(old('profile', 'pme') === $value) /> {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('profile') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="message" class="block text-sm font-medium mb-1.5" style="color: var(--color-marketing-ink);">Votre message</label>
                <textarea id="message" name="message" rows="5" placeholder="Décrivez votre besoin..." required class="w-full border rounded-xl px-4 py-3 text-sm outline-none focus:border-[color:var(--color-teal-fayeku)] focus:ring-2 focus:ring-[color:var(--color-mint-200)] {{ $errors->has('message') ? 'border-rose-400' : 'border-gray-200' }}">{{ old('message') }}</textarea>
                @error('message') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <p class="text-xs" style="color: var(--color-marketing-slate);">En envoyant ce formulaire, vous acceptez d'être recontacté par Fayeku.</p>
                <button type="submit" class="btn-primary">Envoyer<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
            </div>
        </form>

        <aside class="lg:col-span-2 space-y-4">
            <article class="card p-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                <h3 class="font-semibold mb-1">Email</h3>
                <a href="mailto:{{ $site['contact']['email'] }}" class="text-sm hover:text-[color:var(--color-teal-fayeku)]" style="color: var(--color-marketing-slate);">{{ $site['contact']['email'] }}</a>
            </article>
            <article class="card p-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                <h3 class="font-semibold mb-1">Téléphone &amp; WhatsApp</h3>
                <a href="tel:{{ preg_replace('/\s+/', '', $site['contact']['phone']) }}" class="text-sm hover:text-[color:var(--color-teal-fayeku)]" style="color: var(--color-marketing-slate);">{{ $site['contact']['phone'] }}</a>
            </article>
            <article class="card p-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                <h3 class="font-semibold mb-1">Adresse</h3>
                <p class="text-sm leading-relaxed" style="color: var(--color-marketing-slate);">{!! nl2br(e($site['contact']['address'])) !!}</p>
            </article>
            <article class="card p-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background: var(--color-mint-100); color: var(--color-teal-fayeku);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <h3 class="font-semibold mb-1">Horaires</h3>
                <p class="text-sm" style="color: var(--color-marketing-slate);">Lun. – Ven. · 9h00 – 18h00<br/>Réponse sous 24h ouvrées.</p>
            </article>
        </aside>
    </div>
</section>

</x-layouts.marketing>

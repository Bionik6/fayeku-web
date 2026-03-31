<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
<section class="relative overflow-hidden">
      <div class="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(circle_at_top,rgba(34,197,94,0.18),transparent_48%),linear-gradient(180deg,#EAF7F2_0%,rgba(234,247,242,0)_100%)]"></div>
      <div class="mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:pb-24 lg:pt-24">
        <div class="space-y-8">
          <div class="space-y-4">
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">Contact</p>
            <h1 class="max-w-3xl text-balance text-4xl font-semibold leading-[1.08] text-ink sm:text-5xl lg:text-[52px] lg:leading-[60px]">Parlons de votre mise en place Fayeku.</h1>
            <p class="max-w-2xl text-pretty text-lg leading-8 text-slate-600">Demandez vos 2 mois d’essai, une démo ou un échange sur vos besoins PME, cabinet ou conformité.</p>
          </div>

          
        </div>

        <div>
        <div class="rounded-[2rem] border border-primary/10 bg-white p-8 shadow-soft">
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Coordonnées</p>
          <div class="mt-4 space-y-3 text-base text-slate-600">
            <p>Dakar, Sénégal</p>
            <p><a href="mailto:hello@fayeku.sn" class="hover:text-primary">hello@fayeku.sn</a></p>
            
          </div>
        </div>
      </div>
      </div>
    </section>
  

    <section class="pb-24">
      <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
        <div class="space-y-5">
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Formulaire</p>
          <h2 class="text-balance text-3xl font-semibold text-ink sm:text-4xl">Dites-nous où vous en êtes.</h2>
          <p class="text-base leading-7 text-slate-600">Décrivez votre activité, votre volume de factures, votre cabinet actuel ou vos enjeux de conformité.</p>
        </div>
        
    <form action="#" method="post" data-demo-form class="space-y-5 rounded-4xl border border-primary/10 bg-white p-6 shadow-soft sm:p-8">
      <div class="grid gap-5 sm:grid-cols-2">
        <label class="space-y-2 text-sm font-medium text-ink">
          <span>Nom</span>
          <input required type="text" name="name" class="w-full rounded-2xl border border-primary/10 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
        </label>
        <label class="space-y-2 text-sm font-medium text-ink">
          <span>Email</span>
          <input required type="email" name="email" class="w-full rounded-2xl border border-primary/10 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
        </label>
        <x-phone-input
          label="Téléphone"
          country-name="country_code"
          country-value="SN"
          phone-name="phone"
          phone-placeholder="XX XXX XX XX"
        />
        <label class="space-y-2 text-sm font-medium text-ink">
          <span>Pays</span>
          <select name="country" class="w-full rounded-2xl border border-primary/10 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
            <option>Sénégal</option>
            <option>Autre pays d’Afrique de l’Ouest</option>
            <option>Autre</option>
          </select>
        </label>
      </div>

      <label class="block space-y-2 text-sm font-medium text-ink">
        <span>Message</span>
        <textarea required rows="5" name="message" class="w-full rounded-3xl border border-primary/10 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="Décrivez votre contexte, votre nombre de clients ou vos besoins de conformité."></textarea>
      </label>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <button type="submit" class="inline-flex items-center justify-center rounded-full bg-primary px-5 py-3 text-sm font-semibold text-accent">Envoyer la demande</button>
      </div>

      <p data-form-success hidden class="rounded-3xl bg-mist px-4 py-3 text-sm text-primary">
        Merci pour votre message. Nous reviendrons vers vous sous 24h ouvrées.
      </p>
    </form>
  
      </div>
    </section>
</x-layouts.marketing>

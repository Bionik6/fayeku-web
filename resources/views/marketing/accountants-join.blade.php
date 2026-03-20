<x-layouts.marketing-immersive :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
<section class="relative overflow-hidden bg-[#024D4D]">
      <div class="absolute left-1/2 top-0 hidden h-full w-[1px] bg-white/18 lg:block"></div>
      <div class="absolute -right-24 top-24 hidden h-56 w-56 rounded-full border-[12px] border-white/40 lg:block"></div>
      <div class="absolute -bottom-16 right-24 hidden h-64 w-64 rounded-full border-[12px] border-white/40 lg:block"></div>

      <div class="grid min-h-screen lg:grid-cols-[0.94fr_1.06fr]">
        <div class="bg-[#D9EEE6] px-6 py-12 sm:px-10 lg:px-14 lg:py-16">
          <div class="max-w-xl space-y-8 lg:ml-auto lg:mr-10 lg:mt-10">
            <a href="/" class="inline-flex items-center gap-3 text-[#024D4D]" aria-label="Accueil Fayeku">
              <img src="/logo-mark.svg" alt="" width="56" height="56" class="h-14 w-14" />
              <div>
                <div class="text-2xl font-semibold">Fayeku</div>
                <div class="text-sm text-[#1D5D5D]">Facturation &amp; trésorerie</div>
              </div>
            </a>

            <h1 class="text-balance text-4xl font-semibold leading-[1.1] text-[#024D4D] sm:text-5xl lg:text-[52px] lg:leading-[60px]">Vous êtes un cabinet d'expertise comptable</h1>

            <p class="max-w-xl text-xl leading-9 text-[#1D5D5D]">Après soumission, un membre de l'équipe Fayeku vous contacte sous 24h ouvrées pour faire le point sur vos besoins et configurer votre accès Compta.</p>

            <div class="space-y-6 pt-2 text-lg leading-8 text-[#1D5D5D]">
              <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Gagnez en productivité : Fayeku centralise automatiquement les flux de facturation de vos clients.</p><p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Collaborez plus efficacement : les échanges avec vos clients se font dans un espace structuré.</p><p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Consacrez-vous au conseil : proposez un outil de gestion concret sur lequel appuyer vos recommandations.</p>
            </div>
          </div>
        </div>

        <div class="relative px-5 py-12 sm:px-8 lg:px-14 lg:py-16">
          <div class="mx-auto max-w-xl lg:ml-10 lg:mr-auto lg:mt-4">
            
    <div class="relative">
      <div class="absolute inset-0 translate-x-3 translate-y-3 rounded-[2rem] bg-accent" aria-hidden></div>
      <form action="#" method="post" data-demo-form class="relative space-y-5 rounded-[2rem] border border-[#024D4D]/10 bg-white p-6 shadow-soft sm:p-8">
        <p class="max-w-xl text-base leading-8 text-slate-700">
          Formulaire réservé aux cabinets d’expertise comptable. Vous êtes une entreprise ?
          <a href="/entreprises/" class="font-semibold text-primary underline-offset-4 hover:underline">Par ici</a>
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="space-y-2 text-sm font-medium text-ink">
            <span>Prénom *</span>
            <input required type="text" name="first_name" placeholder="Entrez votre prénom..." class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="space-y-2 text-sm font-medium text-ink">
            <span>Nom *</span>
            <input required type="text" name="last_name" placeholder="Entrez votre nom" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
          </label>
        </div>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>E-mail professionnel *</span>
          <input required type="email" name="email" placeholder="Entrez votre adresse email..." class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>Numéro de téléphone *</span>
          <input required type="tel" name="phone" placeholder="+221 77 123 45 67" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>Nom du cabinet *</span>
          <input required type="text" name="firm" placeholder="Nom commercial ou raison sociale" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" />
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>Région *</span>
          <select name="region" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
            <option>Dakar</option><option>Thiès</option><option>Saint-Louis</option><option>Diourbel</option><option>Kaolack</option><option>Ziguinchor</option><option>Tambacounda</option><option>Autre région</option>
          </select>
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>Combien de dossiers gérez-vous au sein de votre cabinet ? *</span>
          <select name="portfolio_size" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
            <option>1 à 20 dossiers</option><option>21 à 50 dossiers</option><option>51 à 100 dossiers</option><option>101 à 250 dossiers</option><option>250+ dossiers</option>
          </select>
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>Qu’attendez-vous de Fayeku ?</span>
          <textarea rows="5" name="message" placeholder="Collecte de pièces, exports, visibilité temps réel, programme partenaire..." class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-base text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"></textarea>
        </label>

        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#024D4D] px-5 py-4 text-base font-semibold text-[#10B75C] transition hover:bg-[#013c3c]">
          Soumettre
        </button>

        <p class="text-center text-sm leading-6 text-slate-500">
          Ces informations sont utilisées pour gérer votre demande de contact, conformément à notre
          <a href="/confidentialite/" class="underline underline-offset-4">politique de confidentialité</a>.
        </p>

        <p data-form-success hidden class="rounded-3xl bg-mist px-4 py-3 text-sm text-primary">
          Votre demande a bien été reçue. Un conseiller Fayeku vous contactera sous 24h pour valider votre accès Compta.
        </p>
      </form>
    </div>
  
          </div>
        </div>
      </div>
    </section>
</x-layouts.marketing-immersive>

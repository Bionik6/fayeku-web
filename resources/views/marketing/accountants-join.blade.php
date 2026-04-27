<x-layouts.marketing-immersive :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
@if (session('success'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 8000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="status"
        aria-live="polite"
        data-testid="lead-toast"
        class="fixed right-4 top-4 z-50 max-w-sm rounded-2xl border border-teal-200 bg-teal-50 px-5 py-4 text-sm text-teal-800 shadow-lg"
    >
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <p class="flex-1">{{ session('success') }}</p>
            <button type="button" @click="show = false" class="ml-auto -mr-1 -mt-1 rounded-md p-1 text-teal-600/70 transition hover:bg-teal-100 hover:text-teal-700" aria-label="Fermer">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
@endif

<section class="relative overflow-hidden bg-[#024D4D]">
      <div class="absolute left-1/2 top-0 hidden h-full w-[1px] bg-white/18 lg:block"></div>

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
              <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Gagnez en productivité : Fayeku centralise automatiquement les flux de facturation de vos clients.</p>
              <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Collaborez plus efficacement : les échanges avec vos clients se font dans un espace structuré.</p>
              <p class="max-w-2xl"><span class="font-semibold text-accent">✓</span> Consacrez-vous au conseil : proposez un outil de gestion concret sur lequel appuyer vos recommandations.</p>
            </div>
          </div>
        </div>

        <div class="relative px-5 py-12 sm:px-8 lg:px-14 lg:py-16">
          <div class="mx-auto max-w-xl lg:ml-10 lg:mr-auto lg:mt-4">

    <div class="relative">
      <div class="absolute inset-0 translate-x-3 translate-y-3 rounded-[2rem] bg-accent" aria-hidden></div>
      <form action="{{ route('marketing.accountants.join.store') }}" method="post" class="relative space-y-5 rounded-[2rem] border border-[#024D4D]/10 bg-white p-6 shadow-soft sm:p-8">
        @csrf

        <x-auth-header
          :title="__('Rejoindre Fayeku Compta')"
          :description="__('Remplissez les informations ci-dessous pour soumettre votre demande d\'accès.')"
        />

        {{-- Info banner --}}
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
          <p class="text-sm font-semibold text-teal-800">
            {{ __('Cette page est réservée aux cabinets d\'expertise comptable.') }}
          </p>
          <p class="mt-1 text-sm text-teal-700">
            {{ __('Vous êtes une PME ?') }}
            <a href="{{ route('sme.auth.register') }}" class="font-medium underline">
              {{ __('Inscrivez-vous ici →') }}
            </a>
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="space-y-2 text-sm font-medium text-ink">
            <span>{{ __('Prénom') }} *</span>
            <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="{{ __('Entrez votre prénom...') }}" required class="w-full rounded-2xl border px-4 py-3 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('first_name') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}" />
            @error('first_name')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </label>
          <label class="space-y-2 text-sm font-medium text-ink">
            <span>{{ __('Nom') }} *</span>
            <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="{{ __('Entrez votre nom') }}" required class="w-full rounded-2xl border px-4 py-3 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('last_name') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}" />
            @error('last_name')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </label>
        </div>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>{{ __('Nom du cabinet') }} *</span>
          <input type="text" name="firm" value="{{ old('firm') }}" placeholder="{{ __('Nom commercial ou raison sociale') }}" required class="w-full rounded-2xl border px-4 py-3 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('firm') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}" />
          @error('firm')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
        </label>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>{{ __('Email') }} *</span>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('votre@cabinet.com') }}" required class="w-full rounded-2xl border px-4 py-3 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('email') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}" />
          @error('email')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
        </label>

        <x-phone-input
          :label="__('Téléphone')"
          country-name="country_code"
          :country-value="old('country_code', 'SN')"
          phone-name="phone"
          :phone-value="old('phone', '')"
          :required="true"
          phone-placeholder="XX XXX XX XX"
          :countries="['SN' => config('fayeku.countries.SN.label', 'SEN (+221)')]"
        />
        @error('phone')
          <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror

        <div class="space-y-2">
          <label class="block text-sm font-medium text-ink" for="region">{{ __('Région') }} *</label>
          <x-select-native>
            <select id="region" name="region" required class="col-start-1 row-start-1 w-full appearance-none rounded-2xl border px-4 py-3 pr-8 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('region') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}">
              @foreach (['Dakar', 'Thiès', 'Saint-Louis', 'Diourbel', 'Kaolack', 'Ziguinchor', 'Tambacounda', 'Autre région'] as $region)
                <option value="{{ $region }}" @selected(old('region', 'Dakar') === $region)>{{ $region }}</option>
              @endforeach
            </select>
          </x-select-native>
          @error('region')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-ink" for="portfolio_size">{{ __('Combien de dossiers gérez-vous au sein de votre cabinet ?') }} *</label>
          <x-select-native>
            <select id="portfolio_size" name="portfolio_size" required class="col-start-1 row-start-1 w-full appearance-none rounded-2xl border px-4 py-3 pr-8 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('portfolio_size') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}">
              @foreach (['1 à 20 dossiers', '21 à 50 dossiers', '51 à 100 dossiers', '101 à 250 dossiers', '250+ dossiers'] as $size)
                <option value="{{ $size }}" @selected(old('portfolio_size', '1 à 20 dossiers') === $size)>{{ $size }}</option>
              @endforeach
            </select>
          </x-select-native>
          @error('portfolio_size')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
        </div>

        <label class="block space-y-2 text-sm font-medium text-ink">
          <span>{{ __('Qu\'attendez-vous de Fayeku ?') }} *</span>
          <textarea rows="5" name="message" required placeholder="{{ __('Collecte de pièces, exports, visibilité temps réel, programme partenaire...') }}" class="w-full rounded-3xl border px-4 py-3 text-base text-ink outline-none transition focus:ring-2 focus:ring-primary/15 {{ $errors->has('message') ? 'border-red-400 focus:border-red-400' : 'border-slate-300 focus:border-primary' }}">{{ old('message') }}</textarea>
          @error('message')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
        </label>

        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#024D4D] px-5 py-4 text-base font-semibold text-[#10B75C] transition hover:bg-[#013c3c]">
          {{ __('Soumettre') }}
        </button>

        <p class="text-center text-sm leading-6 text-slate-500">
          Ces informations sont utilisées pour gérer votre demande de contact, conformément à notre
          <a href="{{ route('marketing.privacy') }}" class="underline underline-offset-4">politique de confidentialité</a>.
        </p>
      </form>
    </div>

          </div>
        </div>
      </div>
    </section>
</x-layouts.marketing-immersive>

<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <section class="app-shell-panel overflow-hidden">
            <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">{{ __('Vue cabinet') }}</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ __('Pilotez votre portefeuille clients depuis un seul cockpit.') }}</h2>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        {{ __('Ce skeleton pose la structure Fayeku Compta avec une navigation laterale claire, un header de supervision et des blocs d analyse prets a accueillir les ecrans metier.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-primary/20 hover:text-primary">
                        {{ __('Exporter le rapport') }}
                    </button>
                    <button type="button" class="rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-[0_18px_36px_rgba(2,77,77,0.18)] transition hover:bg-primary-strong">
                        {{ __('Nouvelle invitation') }}
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="app-shell-stat-card">
                <div class="flex items-center justify-between">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-mist text-primary">
                        <x-app.icon name="clients" class="size-5" />
                    </span>
                    <span class="app-shell-stat-pill">{{ __('+8.4%') }}</span>
                </div>
                <p class="mt-6 text-sm font-medium text-slate-500">{{ __('Clients actifs suivis') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">128</p>
            </article>

            <article class="app-shell-stat-card">
                <div class="flex items-center justify-between">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-mist text-primary">
                        <x-app.icon name="invitations" class="size-5" />
                    </span>
                    <span class="app-shell-stat-pill">{{ __('12 en attente') }}</span>
                </div>
                <p class="mt-6 text-sm font-medium text-slate-500">{{ __('Invitations envoyees') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">36</p>
            </article>

            <article class="app-shell-stat-card">
                <div class="flex items-center justify-between">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-mist text-primary">
                        <x-app.icon name="commissions" class="size-5" />
                    </span>
                    <span class="app-shell-stat-pill">{{ __('Mars 2026') }}</span>
                </div>
                <p class="mt-6 text-sm font-medium text-slate-500">{{ __('Commissions estimees') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">480 000 FCFA</p>
            </article>

            <article class="app-shell-stat-card">
                <div class="flex items-center justify-between">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-mist text-primary">
                        <x-app.icon name="export" class="size-5" />
                    </span>
                    <span class="app-shell-stat-pill">{{ __('Pret') }}</span>
                </div>
                <p class="mt-6 text-sm font-medium text-slate-500">{{ __('Exports groupés ce mois') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-ink">14</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.85fr)]">
            <article class="app-shell-panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Activité du portefeuille') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Vue de synthese du pipeline cabinet, des exports et des invitations recentes.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">{{ __('Mensuel') }}</span>
                </div>

                <div class="mt-8 grid gap-4">
                    <div class="relative overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5">
                        <x-placeholder-pattern class="absolute inset-0 size-full stroke-primary/8" />
                        <div class="relative">
                            <div class="flex items-end gap-3">
                                <div class="h-24 w-7 rounded-t-full bg-primary/12"></div>
                                <div class="h-36 w-7 rounded-t-full bg-primary/20"></div>
                                <div class="h-28 w-7 rounded-t-full bg-primary/15"></div>
                                <div class="h-44 w-7 rounded-t-full bg-accent/35"></div>
                                <div class="h-32 w-7 rounded-t-full bg-primary/20"></div>
                                <div class="h-52 w-7 rounded-t-full bg-primary"></div>
                                <div class="h-40 w-7 rounded-t-full bg-primary/20"></div>
                                <div class="h-48 w-7 rounded-t-full bg-accent/40"></div>
                            </div>
                            <div class="mt-5 flex items-center justify-between text-xs font-medium uppercase tracking-[0.2em] text-slate-400">
                                <span>{{ __('Jan') }}</span>
                                <span>{{ __('Fev') }}</span>
                                <span>{{ __('Mar') }}</span>
                                <span>{{ __('Avr') }}</span>
                                <span>{{ __('Mai') }}</span>
                                <span>{{ __('Juin') }}</span>
                                <span>{{ __('Juil') }}</span>
                                <span>{{ __('Aout') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-sm font-medium text-slate-500">{{ __('Clients onboardés') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-ink">09</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-sm font-medium text-slate-500">{{ __('Exports prêts') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-ink">04</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-sm font-medium text-slate-500">{{ __('Relances cabinet') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-ink">17</p>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-shell-panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold tracking-tight text-ink">{{ __('Raccourcis') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Blocs prets pour les prochaines fonctionnalites du cabinet.') }}</p>
                    </div>
                    <span class="rounded-full bg-mist px-3 py-1 text-xs font-semibold text-primary">{{ __('Skeleton') }}</span>
                </div>

                <div class="mt-8 space-y-4">
                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                                <x-app.icon name="dashboard" class="size-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-ink">{{ __('Vue générale') }}</p>
                                <p class="text-sm text-slate-500">{{ __('Indicateurs globaux du cabinet') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                                <x-app.icon name="clients" class="size-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-ink">{{ __('Portefeuille clients') }}</p>
                                <p class="text-sm text-slate-500">{{ __('Accès futur aux fiches PME') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                                <x-app.icon name="support" class="size-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-ink">{{ __('Support cabinet') }}</p>
                                <p class="text-sm text-slate-500">{{ __('Zone d\'aide et d\'assistance a brancher') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>
</x-layouts::app>

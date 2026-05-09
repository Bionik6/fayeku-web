<?php

use App\Actions\Auth\CompleteOnboardingStep;
use App\Enums\Auth\LegalForm;
use App\Enums\Auth\OnboardingIntent;
use App\Models\Auth\Company;
use App\Rules\Sn\Ninea as NineaRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Title('Configuration de votre espace')]
#[Layout('layouts::onboarding')]
class extends Component {
    use WithFileUploads;

    public int $currentStep = 0;

    // Step 0 — intent
    public ?string $intent = null;

    // Step 1 — identity
    public string $companyName = '';
    public ?string $legalForm = null;
    public string $sector = '';
    public string $sectorOther = '';
    public string $ninea = '';
    public string $rccm = '';
    public string $address = '';
    public string $billingEmail = '';
    public $logoUpload = null;

    // Step 2 — signature
    public string $senderName = '';
    public string $senderRole = '';

    // Step 3 — first client
    public string $clientName = '';
    public string $clientPhone = '';
    public string $clientPhoneCountry = 'SN';
    public string $clientEmail = '';
    public bool $firstClientCreated = false;

    public function mount(): void
    {
        $user = auth()->user();
        $company = $this->company;

        if (! $company) {
            abort(404);
        }

        if ($company->setup_completed_at !== null) {
            $this->redirectRoute('pme.dashboard', navigate: true);

            return;
        }

        $this->intent = $user->onboarding_intent?->value;
        // Le placeholder posé à l'inscription (= nom complet de l'utilisateur) ne
        // doit pas pré-remplir le champ Raison sociale — le user le saisirait par
        // erreur. On ne pré-remplit que si le nom est manifestement la vraie
        // raison sociale (différente du nom complet du user).
        $userFullName = trim($user->first_name.' '.$user->last_name);
        $this->companyName = $company->name && $company->name !== $userFullName
            ? $company->name
            : '';
        $this->legalForm = $company->legal_form?->value;
        $sectorList = $this->knownSectors();
        if (in_array($company->sector, $sectorList, true)) {
            $this->sector = $company->sector;
        } elseif ($company->sector) {
            $this->sector = '__other__';
            $this->sectorOther = $company->sector;
        }
        $this->ninea = $company->ninea ?? '';
        $this->rccm = $company->rccm ?? '';
        $this->address = $company->address ?? '';
        $this->billingEmail = $company->email ?? '';
        // Pré-remplit la signature avec le nom du détenteur de l'entreprise — récupéré
        // à l'inscription. L'utilisateur peut la modifier avant de valider.
        $this->senderName = $company->sender_name ?: $userFullName;
        $this->senderRole = $company->sender_role ?? '';
        $this->firstClientCreated = $company->clients()->exists();
        $this->clientPhoneCountry = $company->country_code ?? 'SN';

        $this->currentStep = $this->resolveInitialStep($user, $company);
    }

    #[Computed]
    public function company(): ?Company
    {
        return auth()->user()?->smeCompany();
    }

    #[Computed]
    public function existingLogoUrl(): ?string
    {
        $path = $this->company?->logo_path;
        if (! $path) {
            return null;
        }

        return route('pme.company.logo');
    }

    public function selectIntent(string $intent): void
    {
        $intentEnum = OnboardingIntent::tryFrom($intent);

        if (! $intentEnum) {
            $this->addError('intent', __('Choix invalide.'));

            return;
        }

        $this->intent = $intentEnum->value;
        auth()->user()->update(['onboarding_intent' => $intentEnum]);
    }

    public function goToStep(int $step): void
    {
        if ($step < 0 || $step > 4) {
            return;
        }

        if ($step === 1 && ! $this->intent) {
            $this->addError('intent', __('Sélectionnez un choix avant de continuer.'));

            return;
        }

        $this->currentStep = $step;
        $this->dispatch('current-step-changed', step: $step);
    }

    public function previousStep(): void
    {
        $this->goToStep(max(0, $this->currentStep - 1));
    }

    public function saveIdentity(CompleteOnboardingStep $action): void
    {
        // Si l'utilisateur choisit "Autre", on remplace la sentinelle par la valeur
        // saisie AVANT la validation pour que la règle `not_in:__other__` n'échoue pas.
        if ($this->sector === '__other__' && trim($this->sectorOther) !== '') {
            $this->sector = trim($this->sectorOther);
        }

        $this->validate([
            'companyName' => ['required', 'string', 'max:255'],
            'legalForm' => ['required', Rule::in(array_column(LegalForm::cases(), 'value'))],
            'sector' => ['required', 'string', 'max:100', 'not_in:__other__'],
            'ninea' => ['nullable', 'string', 'max:20', new NineaRule],
            'rccm' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'billingEmail' => ['nullable', 'email', 'max:255'],
            'logoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], [], [
            'companyName' => __('onboarding.identity.company_name'),
            'legalForm' => __('onboarding.identity.legal_form'),
            'sector' => __('onboarding.identity.sector'),
            'ninea' => 'NINEA',
            'address' => __('onboarding.identity.address'),
            'billingEmail' => __('onboarding.identity.billing_email'),
            'logoUpload' => __('onboarding.identity.logo'),
        ]);

        $action->persistIdentity(
            $this->company,
            [
                'name' => $this->companyName,
                'legal_form' => $this->legalForm,
                'sector' => $this->sector,
                'ninea' => $this->ninea,
                'rccm' => $this->rccm,
                'address' => $this->address,
                'billing_email' => $this->billingEmail,
            ],
            $this->logoUpload ?: null,
        );

        $this->logoUpload = null;
        unset($this->company, $this->existingLogoUrl);

        $this->goToStep(2);
    }

    public function removeLogo(): void
    {
        $company = $this->company;
        if ($company?->logo_path && Storage::exists($company->logo_path)) {
            Storage::delete($company->logo_path);
        }
        $company?->update(['logo_path' => null]);
        unset($this->company, $this->existingLogoUrl);
    }

    public function saveSignature(CompleteOnboardingStep $action): void
    {
        $this->validate([
            'senderName' => ['required', 'string', 'max:60'],
            'senderRole' => ['nullable', 'string', 'max:60'],
        ], [], [
            'senderName' => __('onboarding.signature.sender_name'),
            'senderRole' => __('onboarding.signature.sender_role'),
        ]);

        $action->persistSignature($this->company, $this->senderName, $this->senderRole);
        unset($this->company);

        $this->goToStep(3);
    }

    public function saveFirstClient(CompleteOnboardingStep $action): void
    {
        $this->validate([
            'clientName' => ['required', 'string', 'max:255'],
            'clientPhone' => ['required', 'string', 'max:30'],
            'clientPhoneCountry' => ['required', 'string', 'max:3'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
        ], [
            'clientName.required' => __('Le nom du client est requis.'),
            'clientPhone.required' => __('Le numéro de téléphone est requis.'),
            'clientEmail.email' => __("L'adresse email doit être valide."),
        ]);

        $action->createFirstClient($this->company, [
            'name' => $this->clientName,
            'phone' => $this->clientPhone,
            'phone_country' => $this->clientPhoneCountry,
            'email' => $this->clientEmail,
        ]);

        $this->firstClientCreated = true;

        $this->goToStep(4);
    }

    public function skipFirstClient(): void
    {
        $this->goToStep(4);
    }

    public function complete(CompleteOnboardingStep $action): void
    {
        $action->markCompleted($this->company);

        $this->redirectRoute('pme.dashboard', navigate: true);
    }

    public function completeAndCreateInvoice(CompleteOnboardingStep $action): void
    {
        $action->markCompleted($this->company);

        $this->redirectRoute('pme.invoices.create', navigate: true);
    }

    /**
     * @return array<int, string>
     */
    private function knownSectors(): array
    {
        return [
            'Agriculture / Élevage', 'Agroalimentaire & Transformation', 'Artisanat & Arts',
            'Bâtiment / Construction', 'Commerce (boutique, magasin, supermarché)', 'Commerce de gros',
            'Éducation (école, formation)', 'Énergie, Mines & Pétrole', 'Environnement & Eau',
            'Finance, Banque & Assurance', 'Hôtellerie', 'Immobilier & Foncier',
            'Industrie manufacturière', 'Médias & Communication', 'Pêche',
            'Restauration (restaurant, fast-food, traiteur)', 'Salon de coiffure / Esthétique',
            'Santé (clinique, pharmacie)', 'Services automobiles', 'Services aux entreprises & Conseil',
            'Technologie / Informatique', 'Télécommunications', 'Textile, Habillement & Cuir',
            'Tourisme & Loisirs', 'Transport / Logistique',
        ];
    }

    private function resolveInitialStep($user, Company $company): int
    {
        if (! $user->onboarding_intent) {
            return 0;
        }
        if (! $company->legal_form) {
            return 1;
        }
        if (! $company->sender_name) {
            return 2;
        }

        return 3;
    }

}; ?>

<div
    data-test="onboarding-wizard"
    data-current-step="{{ $currentStep }}"
    class="grid min-h-screen lg:grid-cols-[0.94fr_1.06fr]"
>
    {{-- ─── Section gauche : logo + aside réactif (badge + titre + description + carte de progression + tip + footer) ─── --}}
    <div class="relative flex overflow-hidden bg-[#D9EEE6] px-6 py-10 sm:px-10 lg:px-14 lg:py-12">
        <div class="pointer-events-none absolute -left-20 top-10 hidden h-72 w-72 rounded-full bg-white/40 blur-2xl lg:block" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-20 left-32 hidden h-80 w-80 rounded-full bg-white/30 blur-3xl lg:block" aria-hidden="true"></div>

        <div class="relative flex w-full max-w-xl flex-col gap-7 lg:ml-auto lg:mr-10">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-[#024D4D]" wire:navigate aria-label="Accueil Fayeku">
                <img src="/logo-mark.svg" alt="Fayeku" class="h-12 w-12" />
                <div>
                    <p class="text-lg font-semibold leading-tight">Fayeku</p>
                    <p class="text-xs text-[#1D5D5D]">Facturation & trésorerie</p>
                </div>
            </a>

            @include('pages.pme.onboarding._aside', ['currentStep' => $currentStep])

            {{-- Footer de l'aside : poussé en bas via mt-auto --}}
            <div class="mt-auto flex items-center justify-between border-t border-[#024D4D]/10 pt-4 text-xs text-[#1D5D5D]">
                @if ($currentStep >= 4)
                    <a href="{{ route('pme.settings.index') }}" class="font-medium text-[#024D4D] hover:underline" wire:navigate>
                        {{ __('onboarding.wizard.modify_settings') }}
                    </a>
                @else
                    <form method="POST" action="{{ route('auth.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="cursor-pointer font-medium text-[#024D4D] hover:underline">
                            ← {{ __('onboarding.wizard.resume_later') }}
                        </button>
                    </form>
                @endif

                <a href="{{ route('marketing.contact') }}" class="font-medium text-[#024D4D] hover:underline">
                    {{ __('onboarding.wizard.help') }}
                </a>
            </div>
        </div>
    </div>

    {{-- ─── Section droite : carte qui contient le formulaire de l'étape courante ─── --}}
    <div class="relative overflow-hidden px-5 py-12 sm:px-8 lg:px-14 lg:py-16">
        <div class="absolute -right-24 top-24 hidden h-56 w-56 rounded-full border-[14px] border-white/15 lg:block" aria-hidden="true"></div>
        <div class="absolute -bottom-16 right-24 hidden h-72 w-72 rounded-full border-[14px] border-white/15 lg:block" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-xl lg:ml-10 lg:mr-auto lg:mt-4">
            <div class="relative rounded-3xl border border-[#024D4D]/10 bg-white p-6 shadow-[0_30px_60px_-15px_rgba(2,77,77,0.45)] sm:p-8">
                <div class="flex flex-col gap-6">
                    @switch($currentStep)
                        @case(0)
                            @include('pages.pme.onboarding._step-intent')
                            @break
                        @case(1)
                            @include('pages.pme.onboarding._step-identity')
                            @break
                        @case(2)
                            @include('pages.pme.onboarding._step-signature', ['company' => $this->company])
                            @break
                        @case(3)
                            @include('pages.pme.onboarding._step-first-client')
                            @break
                        @case(4)
                            @include('pages.pme.onboarding._step-confirmation', ['company' => $this->company])
                            @break
                    @endswitch
                </div>
            </div>
        </div>
    </div>
</div>

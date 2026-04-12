<?php

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Auth\Company;
use App\Services\Auth\AuthService;
use App\Models\Compta\PartnerInvitation;
use App\Services\Compta\InvitationService;

new class extends Component {
    public ?Company $firm = null;

    public string $inviteCompanyName = '';

    public string $inviteContactName = '';

    public string $inviteCountryCode = 'SN';

    public string $invitePhone = '';

    public string $invitePlan = 'essentiel';

    public function mount(): void
    {
        $this->firm = auth()->user()->accountantFirm();
    }

    #[On('open-invite-pme')]
    public function openModal(): void
    {
        $this->resetForm();
        $this->modal('invite-pme')->show();
    }

    public function sendInvitation(): void
    {
        $this->inviteCompanyName = trim($this->inviteCompanyName);
        $this->inviteContactName = trim($this->inviteContactName);

        $this->validate([
            'inviteCompanyName' => 'required|string|max:255',
            'inviteContactName' => 'required|string|max:255',
            'invitePhone' => 'required|string|max:30',
            'invitePlan' => 'required|in:basique,essentiel',
        ], [
            'inviteCompanyName.required' => __('Le nom de l\'entreprise est requis.'),
            'inviteContactName.required' => __('Le nom du contact est requis.'),
            'invitePhone.required' => __('Le numéro WhatsApp est requis.'),
        ]);

        if (! $this->firm) {
            return;
        }

        $normalizedPhone = AuthService::normalizePhone($this->invitePhone, $this->inviteCountryCode);

        $existing = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->where('invitee_phone', $normalizedPhone)
            ->where('status', '!=', 'expired')
            ->first();

        if ($existing) {
            $this->addError('invitePhone', __('Cette PME a déjà été invitée récemment.'));

            return;
        }

        $invitation = PartnerInvitation::create([
            'accountant_firm_id' => $this->firm->id,
            'token' => Str::random(32),
            'invitee_company_name' => $this->inviteCompanyName,
            'invitee_name' => $this->inviteContactName,
            'invitee_phone' => $normalizedPhone,
            'recommended_plan' => $this->invitePlan,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ]);

        $sent = app(InvitationService::class)->sendInvitationMessage($invitation);

        $this->resetForm();
        $this->modal('invite-pme')->close();
        $this->dispatch('invitation-sent');

        if ($sent) {
            $this->dispatch('toast', type: 'success', title: __('Invitation envoyée avec succès.'));
        } else {
            $this->dispatch('toast', type: 'warning', title: __('Invitation créée mais l\'envoi WhatsApp a échoué.'));
        }
    }

    public function resetForm(): void
    {
        $this->inviteCompanyName = '';
        $this->inviteContactName = '';
        $this->inviteCountryCode = 'SN';
        $this->invitePhone = '';
        $this->invitePlan = 'essentiel';
        $this->resetErrorBag();
    }
}; ?>

<flux:modal name="invite-pme" variant="bare" class="!bg-transparent !p-0 !shadow-none !ring-0">
    <div class="w-[540px] max-w-[540px] rounded-[2rem] bg-white p-8">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-xl font-bold text-ink">{{ __('Inviter une PME') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('Envoyez une invitation personnalisée à une PME pour l\'aider à rejoindre Fayeku.') }}</p>
            </div>
            <flux:modal.close>
                <button type="button" class="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </flux:modal.close>
        </div>

        {{-- Nom entreprise --}}
        <div class="mt-6">
            <label for="invite-company" class="text-sm font-medium text-slate-700">{{ __('Nom de l\'entreprise') }} <span class="text-red-500">*</span></label>
            <input
                id="invite-company"
                type="text"
                wire:model.live.debounce.300ms="inviteCompanyName"
                placeholder="{{ __('Ex. Transport Ngor SARL') }}"
                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
            @error('inviteCompanyName')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Nom contact --}}
        <div class="mt-4">
            <label for="invite-contact" class="text-sm font-medium text-slate-700">{{ __('Nom du contact') }} <span class="text-red-500">*</span></label>
            <input
                id="invite-contact"
                type="text"
                wire:model.live.debounce.300ms="inviteContactName"
                placeholder="{{ __('Ex. Moussa Diallo') }}"
                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-ink shadow-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
            />
            @error('inviteContactName')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Numéro WhatsApp --}}
        <div class="mt-4">
            <label class="text-sm font-medium text-slate-700">
                {{ __('Numéro WhatsApp') }} <span class="text-red-500">*</span>
            </label>
            <div class="mt-1.5">
                <x-phone-input
                    :show-label="false"
                    :countries="['SN']"
                    country-name="inviteCountryCode"
                    :country-value="$inviteCountryCode"
                    phone-name="invitePhone"
                    :phone-value="$invitePhone"
                    phone-model="invitePhone"
                    :required="true"
                />
            </div>
            @error('invitePhone')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Plan recommandé --}}
        <div class="mt-4">
            <label class="text-sm font-medium text-slate-700">{{ __('Plan recommandé') }}</label>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <label @class([
                    'relative flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                    'border-primary bg-primary/5 ring-2 ring-primary' => $invitePlan === 'basique',
                    'border-slate-200 bg-white hover:bg-slate-50' => $invitePlan !== 'basique',
                ])>
                    <input type="radio" wire:model.live="invitePlan" value="basique" class="sr-only" />
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Basique') }}</p>
                        <p class="text-sm text-slate-500">10 000 FCFA / mois</p>
                    </div>
                </label>
                <label @class([
                    'relative flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                    'border-primary bg-primary/5 ring-2 ring-primary' => $invitePlan === 'essentiel',
                    'border-slate-200 bg-white hover:bg-slate-50' => $invitePlan !== 'essentiel',
                ])>
                    <input type="radio" wire:model.live="invitePlan" value="essentiel" class="sr-only" />
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-ink">{{ __('Essentiel') }}</p>
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ __('Recommandé') }}</span>
                        </div>
                        <p class="text-sm text-slate-500">20 000 FCFA / mois · 2 mois offerts</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Aperçu message --}}
        <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-500">{{ __('Aperçu message WhatsApp') }}</p>
            <p class="mt-2 text-sm italic text-slate-600">
                "{{ __('Bonjour') }} {{ $inviteContactName ?: '[Contact]' }}, {{ $firm?->name ?? __('votre cabinet') }} {{ __('vous invite à rejoindre Fayeku pour simplifier votre facturation.') }}
                @if ($invitePlan === 'essentiel')
                    {{ __('Profitez de 2 mois offerts pour démarrer.') }}
                @endif
                "
            </p>
        </div>

        {{-- Actions --}}
        <div class="mt-6">
            <button
                type="button"
                wire:click="sendInvitation"
                class="w-full rounded-2xl bg-primary py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-primary/90"
            >
                {{ __('Envoyer l\'invitation WhatsApp') }}
            </button>
        </div>

        @if ($firm)
            <p class="mt-3 text-center text-sm text-slate-500">
                {{ __('Votre lien') }} : <span class="font-medium text-primary">fayeku.sn/invite/{{ $firm->invite_code }}</span>
            </p>
        @endif
    </div>
</flux:modal>

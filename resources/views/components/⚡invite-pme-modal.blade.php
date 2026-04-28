<?php

use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Services\Auth\AuthService;
use App\Services\Compta\InvitationService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Company $firm = null;

    public ?PartnerInvitation $existingInvitation = null;

    /** @var 'invite'|'reminder'|'resend' */
    public string $context = 'invite';

    public bool $showDrawer = false;

    public string $inviteCompanyName = '';

    public string $inviteContactName = '';

    public string $inviteCountryCode = 'SN';

    public string $invitePhone = '';

    public string $inviteEmail = '';

    public string $invitePlan = 'essentiel';

    /** @var 'whatsapp'|'email' */
    public string $inviteChannel = 'whatsapp';

    public function mount(): void
    {
        $this->firm = auth()->user()->accountantFirm();
    }

    #[On('open-invite-pme')]
    public function openModal(): void
    {
        $this->existingInvitation = null;
        $this->context = 'invite';
        $this->resetForm();
        $this->showDrawer = true;
    }

    #[On('open-invite-pme-followup')]
    public function openFollowup(string $id, string $context = 'reminder'): void
    {
        if (! $this->firm) {
            return;
        }

        $invitation = PartnerInvitation::query()
            ->where('accountant_firm_id', $this->firm->id)
            ->find($id);

        if (! $invitation) {
            return;
        }

        $this->existingInvitation = $invitation;
        $this->context = in_array($context, ['reminder', 'resend'], true) ? $context : 'reminder';

        $this->inviteCompanyName = $invitation->invitee_company_name ?? '';
        $this->inviteContactName = $invitation->invitee_name ?? '';
        $this->invitePhone = $invitation->invitee_phone ?? '';
        $this->inviteEmail = $invitation->invitee_email ?? '';
        $this->invitePlan = $invitation->recommended_plan ?? 'essentiel';
        $this->inviteChannel = ($invitation->channel === 'email' && $this->inviteEmail !== '') ? 'email' : 'whatsapp';

        $this->resetErrorBag();
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->existingInvitation = null;
        $this->context = 'invite';
        $this->resetForm();
    }

    #[Computed]
    public function drawerTitle(): string
    {
        return match ($this->context) {
            'reminder' => __('Aperçu de la relance'),
            'resend' => __('Aperçu du renvoi'),
            default => __('Aperçu de l\'invitation'),
        };
    }

    #[Computed]
    public function drawerSubtitle(): string
    {
        $company = trim($this->inviteCompanyName);
        $contact = trim($this->inviteContactName);

        return match (true) {
            $company !== '' && $contact !== '' => $company.' · '.$contact,
            $company !== '' => $company,
            $contact !== '' => $contact,
            default => __('À renseigner ci-dessous'),
        };
    }

    private function previewInvitation(): PartnerInvitation
    {
        $invitation = $this->existingInvitation ?: new PartnerInvitation;

        $phone = trim($this->invitePhone);
        $normalized = $phone === ''
            ? ''
            : AuthService::normalizePhone($phone, $this->inviteCountryCode);

        $invitation->fill([
            'invitee_name' => trim($this->inviteContactName) ?: null,
            'invitee_phone' => $normalized ?: null,
            'invitee_email' => trim($this->inviteEmail) ?: null,
            'invitee_company_name' => trim($this->inviteCompanyName) ?: null,
            'recommended_plan' => $this->invitePlan,
        ]);

        $invitation->setRelation('accountantFirm', $this->firm);
        $invitation->setRelation('creator', $invitation->creator ?? auth()->user());

        return $invitation;
    }

    #[Computed]
    public function whatsAppMessage(): string
    {
        return app(InvitationService::class)
            ->composeWhatsAppMessage($this->previewInvitation(), $this->context);
    }

    #[Computed]
    public function emailPreview(): array
    {
        return app(InvitationService::class)
            ->composeEmail($this->previewInvitation(), $this->context);
    }

    #[Computed]
    public function whatsAppLink(): string
    {
        if (trim($this->invitePhone) === '') {
            return '';
        }

        return app(InvitationService::class)
            ->buildWhatsAppLink($this->previewInvitation(), $this->context);
    }

    #[Computed]
    public function mailtoLink(): string
    {
        if (trim($this->inviteEmail) === '') {
            return '';
        }

        return app(InvitationService::class)
            ->buildMailtoLink($this->previewInvitation(), $this->context);
    }

    #[Computed]
    public function copyText(): string
    {
        if ($this->inviteChannel === 'email') {
            $email = $this->emailPreview;

            return $email['subject']."\n\n".$email['body'];
        }

        return $this->whatsAppMessage;
    }

    #[Computed]
    public function canSend(): bool
    {
        if (trim($this->inviteCompanyName) === '' || trim($this->inviteContactName) === '') {
            return false;
        }

        return $this->inviteChannel === 'email'
            ? trim($this->inviteEmail) !== ''
            : trim($this->invitePhone) !== '';
    }

    public function confirmSent(string $channel): void
    {
        if (! $this->firm || ! in_array($channel, ['whatsapp', 'email'], true)) {
            return;
        }

        $rules = [
            'inviteCompanyName' => 'required|string|max:255',
            'inviteContactName' => 'required|string|max:255',
            'invitePlan' => 'required|in:basique,essentiel',
        ];

        if ($channel === 'whatsapp') {
            $rules['invitePhone'] = 'required|string|max:30';
        } else {
            $rules['inviteEmail'] = 'required|email|max:255';
        }

        $this->validate($rules, [
            'inviteCompanyName.required' => __('Le nom de l\'entreprise est requis.'),
            'inviteContactName.required' => __('Le nom du contact est requis.'),
            'invitePhone.required' => __('Le numéro WhatsApp est requis.'),
            'inviteEmail.required' => __('L\'adresse e-mail est requise.'),
            'inviteEmail.email' => __('L\'adresse e-mail n\'est pas valide.'),
        ]);

        $invitation = $this->persistInvitation();

        if (! $invitation) {
            return;
        }

        $service = app(InvitationService::class);

        if ($this->context === 'reminder') {
            $service->markReminded($invitation, $channel);
            $title = $channel === 'email'
                ? __('Relance e-mail ouverte.')
                : __('Relance WhatsApp ouverte.');
        } else {
            $service->markSent($invitation, $channel);
            $title = match ([$this->context, $channel]) {
                ['resend', 'email'] => __('Invitation renvoyée par e-mail.'),
                ['resend', 'whatsapp'] => __('Invitation renvoyée sur WhatsApp.'),
                ['invite', 'email'] => __('Invitation envoyée par e-mail.'),
                default => __('Invitation envoyée sur WhatsApp.'),
            };
        }

        $this->showDrawer = false;
        $this->existingInvitation = null;
        $this->context = 'invite';
        $this->resetForm();
        $this->dispatch('invitation-sent');
        $this->dispatch('toast', type: 'success', title: $title);
    }

    private function persistInvitation(): ?PartnerInvitation
    {
        $name = trim($this->inviteCompanyName);
        $contact = trim($this->inviteContactName);
        $phone = trim($this->invitePhone);
        $email = trim($this->inviteEmail) ?: null;

        $normalizedPhone = $phone === ''
            ? null
            : AuthService::normalizePhone($phone, $this->inviteCountryCode);

        if ($this->existingInvitation) {
            $payload = [
                'invitee_company_name' => $name,
                'invitee_name' => $contact,
                'invitee_phone' => $normalizedPhone,
                'invitee_email' => $email,
                'recommended_plan' => $this->invitePlan,
            ];

            if ($this->context === 'resend') {
                $payload['status'] = 'pending';
                $payload['expires_at'] = now()->addDays(30);
                $payload['link_opened_at'] = null;
                $payload['reminder_count'] = 0;
                $payload['last_reminder_at'] = null;
            }

            $this->existingInvitation->forceFill($payload)->save();

            return $this->existingInvitation;
        }

        if ($normalizedPhone) {
            $duplicate = PartnerInvitation::query()
                ->where('accountant_firm_id', $this->firm->id)
                ->where('invitee_phone', $normalizedPhone)
                ->where('status', '!=', 'expired')
                ->first();

            if ($duplicate) {
                $this->addError('invitePhone', __('Cette PME a déjà été invitée récemment.'));

                return null;
            }
        }

        return PartnerInvitation::create([
            'accountant_firm_id' => $this->firm->id,
            'created_by_user_id' => auth()->id(),
            'token' => Str::random(32),
            'invitee_company_name' => $name,
            'invitee_name' => $contact,
            'invitee_phone' => $normalizedPhone,
            'invitee_email' => $email,
            'recommended_plan' => 'essentiel',
            'channel' => 'whatsapp',
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function resetForm(): void
    {
        $this->inviteCompanyName = '';
        $this->inviteContactName = '';
        $this->inviteCountryCode = 'SN';
        $this->invitePhone = '';
        $this->inviteEmail = '';
        $this->invitePlan = 'essentiel';
        $this->inviteChannel = 'whatsapp';
        $this->resetErrorBag();
    }
}; ?>

<div>
@if ($showDrawer)
    @php
        $rendered = $inviteChannel === 'email'
            ? ($this->emailPreview['subject']."\n\n".$this->emailPreview['body'])
            : $this->whatsAppMessage;

        $previewHtml = e($rendered);
        $previewHtml = preg_replace('/\*([^\*\n]+)\*/', '<strong>$1</strong>', $previewHtml);
        $previewHtml = preg_replace('/_([^_\n]+)_/', '<em>$1</em>', $previewHtml);
        $previewHtml = nl2br($previewHtml);

        $sendLink = $inviteChannel === 'email' ? $this->mailtoLink : $this->whatsAppLink;
        $sendTarget = $inviteChannel === 'email' ? null : '_blank';
    @endphp

    <div
        wire:key="invite-pme-drawer-{{ $existingInvitation?->id ?? 'new' }}-{{ $context }}"
        x-data="{
            open: false,
            close() {
                this.open = false;
                setTimeout(() => $wire.closeDrawer(), 500);
            },
        }"
        x-init="$nextTick(() => { open = true })"
        @keydown.escape.window="close()"
        class="fixed inset-0 z-50 overflow-hidden"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div
            x-show="open"
            x-transition:enter="ease-in-out duration-500"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-500"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500/75"
            aria-hidden="true"
        ></div>

        {{-- Panel --}}
        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden" @click.self="close()">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <div
                        x-show="open"
                        x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="pointer-events-auto w-screen max-w-lg"
                    >
                        <div class="flex h-full flex-col bg-white shadow-xl">

                            {{-- Header --}}
                            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-ink">{{ $this->drawerTitle }}</h3>
                                    <p class="mt-0.5 truncate text-sm text-slate-600">{{ $this->drawerSubtitle }}</p>
                                </div>
                                <button @click="close()" class="relative rounded-md text-slate-400 hover:text-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    <span class="absolute -inset-2.5"></span>
                                    <span class="sr-only">{{ __('Fermer') }}</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                                        <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Body : preview only --}}
                            <div class="flex flex-1 flex-col overflow-hidden bg-mist" x-data="{ copied: false }">
                                <div class="flex shrink-0 items-center justify-between px-6 pt-5 pb-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {{ $inviteChannel === 'email' ? __('Aperçu de l\'e-mail') : __('Aperçu du message') }}
                                    </p>
                                    <button
                                        type="button"
                                        x-on:click="navigator.clipboard.writeText(@js($this->copyText)).then(() => { copied = true; $dispatch('toast', { type: 'success', title: '{{ __('Message copié dans le presse-papiers !') }}' }); setTimeout(() => copied = false, 2000); })"
                                        class="inline-flex cursor-pointer items-center gap-x-1 rounded-md bg-primary px-2 py-1 text-xs font-semibold text-white shadow-xs hover:bg-primary-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                    >
                                        <flux:icon name="clipboard" class="-ml-0.5 size-3.5" x-show="!copied" />
                                        <flux:icon name="check" class="-ml-0.5 size-3.5" x-show="copied" x-cloak />
                                        <span x-text="copied ? '{{ __('Copié') }}' : '{{ __('Copier') }}'"></span>
                                    </button>
                                </div>

                                <div class="flex-1 overflow-y-auto px-6 pb-6">
                                    <div class="max-w-[92%] rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                                        <p class="text-sm leading-relaxed text-slate-700">{!! $previewHtml !!}</p>
                                        <p class="mt-2 text-right text-[10px] text-slate-400">{{ now()->format('H:i') }}</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Footer : inputs + channel + buttons --}}
                            <div class="space-y-3 border-t border-slate-100 px-6 py-4">

                                {{-- Nom entreprise --}}
                                <div>
                                    <label for="invite-company" class="text-sm font-semibold text-slate-600">{{ __('Nom de l\'entreprise') }} <span class="text-red-500">*</span></label>
                                    <input
                                        id="invite-company"
                                        type="text"
                                        wire:model.live.debounce.300ms="inviteCompanyName"
                                        placeholder="{{ __('Ex. Transport Ngor SARL') }}"
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm font-medium text-ink focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-none"
                                    />
                                    @error('inviteCompanyName')
                                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Nom contact --}}
                                <div>
                                    <label for="invite-contact" class="text-sm font-semibold text-slate-600">{{ __('Nom du contact') }} <span class="text-red-500">*</span></label>
                                    <input
                                        id="invite-contact"
                                        type="text"
                                        wire:model.live.debounce.300ms="inviteContactName"
                                        placeholder="{{ __('Ex. Moussa Diallo') }}"
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm font-medium text-ink focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-none"
                                    />
                                    @error('inviteContactName')
                                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Channel selector --}}
                                <div>
                                    <label class="text-sm font-semibold text-slate-600">{{ __('Canal d\'envoi') }}</label>
                                    <div class="mt-1 flex gap-2">
                                        <button
                                            type="button"
                                            wire:click="$set('inviteChannel', 'whatsapp')"
                                            @class([
                                                'flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition',
                                                'border-primary bg-primary/5 text-primary' => $inviteChannel === 'whatsapp',
                                                'border-slate-200 text-slate-600 hover:bg-slate-50' => $inviteChannel !== 'whatsapp',
                                            ])
                                        >
                                            <flux:icon name="chat-bubble-left-right" class="size-4" />
                                            {{ __('WhatsApp') }}
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="$set('inviteChannel', 'email')"
                                            @class([
                                                'flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition',
                                                'border-primary bg-primary/5 text-primary' => $inviteChannel === 'email',
                                                'border-slate-200 text-slate-600 hover:bg-slate-50' => $inviteChannel !== 'email',
                                            ])
                                        >
                                            <flux:icon name="envelope" class="size-4" />
                                            {{ __('Email') }}
                                        </button>
                                    </div>
                                </div>

                                {{-- Phone OR Email input --}}
                                @if ($inviteChannel === 'whatsapp')
                                    <div>
                                        <label class="text-sm font-semibold text-slate-600">{{ __('Numéro WhatsApp') }} <span class="text-red-500">*</span></label>
                                        <div class="mt-1">
                                            <x-phone-input
                                                :show-label="false"
                                                :countries="['SN']"
                                                country-name="inviteCountryCode"
                                                :country-value="$inviteCountryCode"
                                                phone-name="invitePhone"
                                                :phone-value="$invitePhone"
                                                phone-model="invitePhone"
                                                :required="true"
                                                container-class="flex items-stretch rounded-xl border border-slate-200 bg-slate-50/80 transition has-[:focus]:border-primary has-[:focus]:bg-white has-[:focus]:ring-1 has-[:focus]:ring-primary"
                                                padding-class="px-3 py-2"
                                                text-size="text-sm"
                                            />
                                        </div>
                                        @error('invitePhone')
                                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @else
                                    <div>
                                        <label for="invite-email" class="text-sm font-semibold text-slate-600">{{ __('Adresse e-mail') }} <span class="text-red-500">*</span></label>
                                        <input
                                            id="invite-email"
                                            type="email"
                                            wire:model.live.debounce.300ms="inviteEmail"
                                            placeholder="{{ __('contact@entreprise.com') }}"
                                            class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm font-medium text-ink focus:border-primary focus:bg-white focus:ring-1 focus:ring-primary focus:outline-none"
                                        />
                                        @error('inviteEmail')
                                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                {{-- Action buttons --}}
                                <div class="flex gap-3 pt-2">
                                    <button
                                        @click="close()"
                                        type="button"
                                        class="flex flex-1 items-center justify-center rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50"
                                    >
                                        {{ __('Fermer') }}
                                    </button>
                                    @if ($this->canSend && $sendLink)
                                        <a
                                            href="{{ $sendLink }}"
                                            @if ($sendTarget) target="{{ $sendTarget }}" rel="noopener noreferrer" @endif
                                            wire:click="confirmSent('{{ $inviteChannel }}')"
                                            class="inline-flex flex-1 cursor-pointer items-center justify-center gap-x-2 rounded-md bg-primary px-3.5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-primary-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                        >
                                            <flux:icon name="paper-airplane" class="-ml-0.5 size-5" />
                                            {{ __('Envoyer maintenant') }}
                                        </a>
                                    @else
                                        <button
                                            type="button"
                                            disabled
                                            class="inline-flex flex-1 cursor-not-allowed items-center justify-center gap-x-2 rounded-md bg-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-400"
                                        >
                                            <flux:icon name="paper-airplane" class="-ml-0.5 size-5" />
                                            {{ __('Envoyer maintenant') }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>

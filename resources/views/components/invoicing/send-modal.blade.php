@props([
    'title',
    'showSendModal',
    'sendChannel',
    'sendRecipient',
    'sendCountry',
    'sendPhoneCountries',
    'sendOpenUrl',
    'sendEmailSubject' => null,
])

{{-- Modale d'envoi partagée entre les show pages factures / devis / proformas.
     Le composant Livewire parent doit exposer :
     - propriétés : showSendModal, sendChannel, sendRecipient, sendCountry,
       sendPhoneCountries, sendMessage
     - méthodes : closeSendModal(), confirmSend() --}}
@if ($showSendModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         wire:click.self="closeSendModal" x-data
         @keydown.escape.window="$wire.closeSendModal()">
        <div class="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-7 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-ink">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Choisissez le canal. Le lien public du PDF est inclus dans le message — vous l\'envoyez depuis votre propre WhatsApp ou messagerie.') }}</p>
                </div>
                <button type="button" wire:click="closeSendModal" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>

            <div class="px-7 py-6">
                <div class="mb-5 flex gap-2">
                    <button type="button" wire:click="$set('sendChannel', 'whatsapp')"
                            class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $sendChannel === 'whatsapp' ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                        <flux:icon name="chat-bubble-left-right" class="mr-1 inline size-4" /> {{ __('WhatsApp') }}
                    </button>
                    <button type="button" wire:click="$set('sendChannel', 'email')"
                            class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ $sendChannel === 'email' ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                        <flux:icon name="envelope" class="mr-1 inline size-4" /> {{ __('Email') }}
                    </button>
                </div>

                <div class="space-y-4">
                    @if ($sendChannel === 'whatsapp')
                        <div wire:key="send-phone-{{ $sendChannel }}">
                            <x-phone-input
                                :label="__('Téléphone du client (WhatsApp)')"
                                country-name="sendCountry"
                                :country-value="$sendCountry"
                                country-model="sendCountry"
                                phone-name="sendRecipient"
                                :phone-value="$sendRecipient"
                                phone-model="sendRecipient"
                                :phone-model-live="true"
                                :countries="$sendPhoneCountries"
                                container-class="flex items-stretch rounded-2xl border border-slate-200 bg-slate-50/80 transition has-[:focus]:border-primary/40 has-[:focus]:ring-2 has-[:focus]:ring-primary/10"
                                text-size="text-sm"
                                placeholder-class="placeholder:text-slate-500"
                                required
                            />
                            @error('sendRecipient') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div wire:key="send-email-{{ $sendChannel }}">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Adresse email du client') }} <span class="text-rose-500">*</span>
                            </label>
                            <input wire:model.live.debounce.300ms="sendRecipient" name="sendRecipient" type="email"
                                   placeholder="contact@client.sn"
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            @error('sendRecipient') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-slate-700">{{ __('Message') }}</label>
                            <button type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText($wire.sendMessage).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-primary/30 hover:text-primary">
                                <template x-if="!copied">
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon name="document-duplicate" class="size-3.5" />
                                        {{ __('Copier le message') }}
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="inline-flex items-center gap-1.5 text-emerald-600">
                                        <flux:icon name="check" class="size-3.5" />
                                        {{ __('Copié') }}
                                    </span>
                                </template>
                            </button>
                        </div>
                        <textarea wire:model.live.debounce.300ms="sendMessage" rows="10"
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 font-mono text-[15px] leading-relaxed text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                        @error('sendMessage') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        <flux:icon name="information-circle" class="mr-1 inline size-3.5" />
                        {{ __('Le PDF ne peut pas être joint via WhatsApp Web ou mailto. Le lien public dans le message reste accessible 24/24 — votre client pourra le télécharger en cliquant.') }}
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                <button type="button" wire:click="closeSendModal" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30">{{ __('Annuler') }}</button>
                {{-- Le clic ouvre le canal externe (WhatsApp / mailto) SYNCHRONEMENT depuis
                     le user-gesture afin que le navigateur ne bloque pas le popup, puis
                     appelle confirmSend() qui valide + transitionne le statut côté serveur.
                     L'URL est reconstruite côté client à partir des valeurs actuelles
                     des champs DOM pour éviter toute désync avec la valeur Livewire
                     (cf. wire:model debounce + clic rapide). --}}
                <button type="button"
                        data-send-url="{{ $sendOpenUrl }}"
                        data-send-fallback-url="{{ $sendOpenUrl }}"
                        data-send-channel="{{ $sendChannel }}"
                        data-send-subject="{{ rawurlencode($sendEmailSubject ?? $title) }}"
                        data-can-send="{{ filled($sendRecipient) ? '1' : '0' }}"
                        x-on:click="
                            const channel = $el.dataset.sendChannel;
                            const message = $wire.sendMessage || '';
                            const recipientInput = document.querySelector(channel === 'email' ? 'input[type=&quot;email&quot;][name=&quot;sendRecipient&quot;]' : 'input[type=&quot;tel&quot;][name=&quot;sendRecipient&quot;]');
                            // Fallback sur la valeur Livewire si le selector DOM échoue (eg. attribut name absent).
                            const recipient = ((recipientInput?.value ?? $wire.sendRecipient) || '').trim();
                            $el.dataset.canSend = recipient ? '1' : '0';

                            if ($el.dataset.canSend === '1') {
                                if (channel === 'whatsapp') {
                                    const countryInput = document.querySelector('input[type=&quot;hidden&quot;][name=&quot;sendCountry&quot;]');
                                    const prefix = countryInput?.dataset.prefix || '';
                                    const digits = (prefix + recipient).replace(/\D+/g, '');
                                    const url = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(message);
                                    window.open(url, '_blank', 'noopener,noreferrer');
                                } else if (channel === 'email') {
                                    const url = 'mailto:' + recipient + '?subject=' + $el.dataset.sendSubject + '&body=' + encodeURIComponent(message);
                                    // mailto: doit invoquer le handler de protocole du système (Mail.app / Gmail / Outlook).
                                    // window.open(mailto, _blank) crée un onglet fantôme dans Chrome qui se ferme aussitôt
                                    // sans déclencher le client mail. Affecter window.location.href laisse le navigateur
                                    // intercepter le scheme mailto: et ouvrir le client natif sans naviguer hors de la page.
                                    window.location.href = url;
                                }
                            }
                            $wire.confirmSend();
                        "
                        class="rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-strong">
                    @if ($sendChannel === 'whatsapp')
                        {{ __('Envoyer depuis WhatsApp') }}
                    @else
                        {{ __('Envoyer depuis ma messagerie') }}
                    @endif
                </button>
            </div>
        </div>
    </div>
@endif

@props([
    'invoice',
    'message',
    'company',
    'previewInvoiceId',
    'previewAttachPdf' => true,
    'previewChannel'   => 'whatsapp',
    'closeAction'      => 'closePreview',
    'sendAction'       => 'sendReminder',
])

<?php
use Modules\PME\Collection\Enums\ReminderChannel;

$client = $invoice->client;
$availableChannels = [];

if ($client?->email) {
    $availableChannels[] = [
        'value' => ReminderChannel::Email->value,
        'label' => 'Email',
        'icon'  => 'envelope',
    ];
}

if ($client?->phone) {
    $availableChannels[] = [
        'value' => ReminderChannel::WhatsApp->value,
        'label' => 'WhatsApp',
        'icon'  => 'chat-bubble-left-right',
    ];
    $availableChannels[] = [
        'value' => ReminderChannel::Sms->value,
        'label' => 'SMS',
        'icon'  => 'device-phone-mobile',
    ];
}
?>

<div
    x-data="{
        open: false,
        close() {
            this.open = false;
            setTimeout(() => $wire.{{ $closeAction }}(), 500);
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
                    class="pointer-events-auto w-screen max-w-md"
                >
                    <div class="flex h-full flex-col bg-white shadow-xl">

                        {{-- Header --}}
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                            <div>
                                <h3 class="font-semibold text-ink">{{ __('Aperçu de la relance') }}</h3>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $invoice->reference }} · {{ $invoice->client?->name }}</p>
                            </div>
                            <button @click="close()" class="relative rounded-md text-slate-400 hover:text-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                <span class="absolute -inset-2.5"></span>
                                <span class="sr-only">{{ __('Fermer') }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6">
                                    <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>

                        {{-- Message preview --}}
                        <div class="flex-1 overflow-y-auto bg-mist px-6 py-6">
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-white p-4 shadow-sm">
                                <p class="text-sm font-medium text-slate-800">{{ $message['greeting'] }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $message['body'] }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $message['closing'] }}</p>
                                <p class="text-sm text-slate-600">{{ $company->name }}</p>
                                @if ($previewAttachPdf)
                                    <div class="mt-3 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                                        <flux:icon name="document" class="size-4 text-rose-500" />
                                        <span class="text-sm font-medium text-slate-600">{{ $invoice->reference }}.pdf</span>
                                    </div>
                                @endif
                                <p class="mt-2 text-right text-[10px] text-slate-400">{{ now()->format('H:i') }}</p>
                            </div>
                        </div>

                        {{-- Options --}}
                        <div class="space-y-4 border-t border-slate-100 px-6 py-4">

                            {{-- Channel selector --}}
                            @if (count($availableChannels) > 0)
                                <div>
                                    <label class="text-sm font-semibold text-slate-600">{{ __('Canal d\'envoi') }}</label>
                                    <div class="mt-1.5 flex gap-2">
                                        @foreach ($availableChannels as $ch)
                                            <button
                                                type="button"
                                                wire:click="$set('previewChannel', '{{ $ch['value'] }}')"
                                                @class([
                                                    'flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition',
                                                    'border-primary bg-primary/5 text-primary' => $previewChannel === $ch['value'],
                                                    'border-slate-200 text-slate-600 hover:bg-slate-50' => $previewChannel !== $ch['value'],
                                                ])
                                            >
                                                <flux:icon name="{{ $ch['icon'] }}" class="size-4" />
                                                {{ $ch['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <label class="text-sm font-semibold text-slate-600">{{ __('Ton du message') }}</label>
                                    <x-select-native>
                                        <select wire:model.live="previewTone" class="col-start-1 row-start-1 mt-1 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 pr-8 text-sm">
                                            <option value="cordial">{{ __('Cordial') }}</option>
                                            <option value="ferme">{{ __('Ferme') }}</option>
                                            <option value="urgent">{{ __('Urgent') }}</option>
                                        </select>
                                    </x-select-native>
                                </div>
                                <div class="flex flex-col items-start gap-1 pt-3">
                                    <label class="text-sm font-semibold text-slate-600">{{ __('Joindre PDF') }}</label>
                                    <button
                                        wire:click="$toggle('previewAttachPdf')"
                                        class="relative flex h-6 w-11 items-center rounded-full transition {{ $previewAttachPdf ? 'bg-primary' : 'bg-slate-300' }}"
                                    >
                                        <span class="absolute size-4 rounded-full bg-white shadow transition-all {{ $previewAttachPdf ? 'left-[1.4rem]' : 'left-1' }}"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button
                                    @click="close()"
                                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                                >
                                    {{ __('Fermer') }}
                                </button>
                                <button
                                    wire:click="{{ $sendAction }}('{{ $previewInvoiceId }}')"
                                    wire:confirm="{{ __('Envoyer cette relance ?') }}"
                                    class="flex-1 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong"
                                >
                                    <flux:icon name="paper-airplane" class="mr-1 inline size-4" />
                                    {{ __('Envoyer maintenant') }}
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

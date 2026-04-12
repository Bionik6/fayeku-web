@props([
    'invoice'        => null,
    'reminders'      => null,
    'showInvoiceRef' => false,
    'emptyMessage'   => 'Aucune relance envoyée pour le moment.',
])

<?php
use Modules\PME\Collection\Enums\ReminderChannel;

/** @var \Modules\PME\Invoicing\Models\Invoice|null $invoice */
$items = collect($reminders ?? $invoice?->reminders?->sortBy('created_at') ?? []);

// Build a flat ordered list of typed feed items for a single pass loop.
$feedItems = [];

if ($invoice) {
    $feedItems[] = ['type' => 'due_date'];
}

foreach ($items as $reminder) {
    $feedItems[] = ['type' => 'reminder', 'reminder' => $reminder];
}

if ($invoice?->paid_at) {
    $feedItems[] = ['type' => 'payment'];
}

if ($items->isEmpty() && ! $invoice?->paid_at) {
    $feedItems[] = ['type' => 'empty'];
}
?>

<div class="flow-root pb-12">
    <ul role="list" class="-mb-8">
        @foreach ($feedItems as $item)
            @php $isLast = $loop->last; @endphp

            {{-- ── Due date marker ──────────────────────── --}}
            @if ($item['type'] === 'due_date')
                <li>
                    <div @class(['relative', 'pb-6' => ! $isLast])>
                        @if (! $isLast)
                            <span aria-hidden="true" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200"></span>
                        @endif
                        <div class="relative flex items-start gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 ring-4 ring-white">
                                <flux:icon name="calendar" class="size-4 text-slate-400" />
                            </div>
                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-sm font-medium text-ink">{{ __('Date d\'échéance') }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ format_date($invoice->due_at) }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ __('Montant dû') }} : {{ format_money((int) $invoice->total - (int) $invoice->amount_paid) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </li>

            {{-- ── Reminder item ─────────────────────────── --}}
            @elseif ($item['type'] === 'reminder')
                @php
                    $reminder = $item['reminder'];

                    [$iconName, $iconBg, $iconColor] = match ($reminder->channel) {
                        ReminderChannel::WhatsApp => ['chat-bubble-left-right', 'bg-emerald-50', 'text-emerald-600'],
                        ReminderChannel::Email    => ['envelope', 'bg-blue-50', 'text-blue-600'],
                        ReminderChannel::Sms      => ['device-phone-mobile', 'bg-violet-50', 'text-violet-600'],
                        default                   => ['bell', 'bg-slate-100', 'text-slate-500'],
                    };

                    $channelLabel = match ($reminder->channel) {
                        ReminderChannel::WhatsApp => 'WhatsApp',
                        ReminderChannel::Email    => 'Email',
                        ReminderChannel::Sms      => 'SMS',
                        default                   => ucfirst($reminder->channel?->value ?? ''),
                    };

                    $modeLabel = $reminder->is_manual ? __('Manuel') : __('Automatique');
                    $modeBg    = $reminder->is_manual ? 'bg-slate-100' : 'bg-blue-50';
                    $modeText  = $reminder->is_manual ? 'text-slate-600' : 'text-blue-700';
                @endphp
                <li wire:key="rf-{{ $reminder->id }}">
                    <div @class(['relative', 'pb-6' => ! $isLast])>
                        @if (! $isLast)
                            <span aria-hidden="true" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200"></span>
                        @endif
                        <div class="relative flex items-start gap-3">
                            <div @class(['flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white', $iconBg])>
                                <flux:icon :name="$iconName" @class(['size-4', $iconColor]) />
                            </div>
                            <div class="min-w-0 flex-1 pt-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        @if ($showInvoiceRef && $reminder->invoice)
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                {{ $reminder->invoice->reference ?? '—' }}
                                            </p>
                                        @endif
                                        <p class="text-sm font-medium text-ink">{{ __('Relance') }} {{ $channelLabel }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ format_date($reminder->sent_at ?? $reminder->created_at, withTime: true) }}
                                        </p>
                                    </div>
                                    <span @class([
                                        'mt-0.5 shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                        $modeBg,
                                        $modeText,
                                    ])>
                                        {{ $modeLabel }}
                                    </span>
                                </div>
                                @if ($reminder->message_body)
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $reminder->message_body }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </li>

            {{-- ── Payment marker ────────────────────────── --}}
            @elseif ($item['type'] === 'payment')
                <li>
                    <div @class(['relative', 'pb-6' => ! $isLast])>
                        @if (! $isLast)
                            <span aria-hidden="true" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200"></span>
                        @endif
                        <div class="relative flex items-start gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent/10 ring-4 ring-white">
                                <flux:icon name="check-circle" class="size-4 text-accent" />
                            </div>
                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-sm font-semibold text-accent">{{ __('Paiement reçu') }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ format_date($invoice->paid_at) }}</p>
                            </div>
                        </div>
                    </div>
                </li>

            {{-- ── Empty state ────────────────────────────── --}}
            @elseif ($item['type'] === 'empty')
                <li>
                    <div class="relative flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 ring-4 ring-white">
                            <flux:icon name="bell-slash" class="size-4 text-slate-400" />
                        </div>
                        <div class="min-w-0 flex-1 pt-1.5">
                            <p class="text-sm text-slate-500">{{ $emptyMessage }}</p>
                        </div>
                    </div>
                </li>
            @endif
        @endforeach
    </ul>
</div>

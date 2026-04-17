@props([
    'invoice',
    'emptyMessage' => 'Aucune activité pour le moment.',
])

<?php
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;

/** @var \App\Models\PME\Invoice $invoice */
$events = $invoice->timeline();
?>

<div class="flow-root pb-12">
    @if ($events->isEmpty())
        <div class="relative flex items-start gap-3">
            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 ring-4 ring-white">
                <flux:icon name="bell-slash" class="size-4 text-slate-400" />
            </div>
            <div class="min-w-0 flex-1 pt-1.5">
                <p class="text-sm text-slate-500">{{ $emptyMessage }}</p>
            </div>
        </div>
    @else
        <ul role="list" class="-mb-8">
            @foreach ($events as $event)
                @php
                    $isLast = $loop->last;
                    $type = $event['type'];
                    $meta = $event['meta'] ?? [];

                    // Icon, colors, mode badge derived per event type.
                    $iconName = 'bell';
                    $iconBg = 'bg-slate-100';
                    $iconColor = 'text-slate-500';
                    $title = __($event['label']);
                    $subtitle = format_date($event['at'], withTime: ! in_array($type, ['created', 'due_date', 'upcoming'], true));
                    $badge = null;
                    $badgeBg = 'bg-slate-100';
                    $badgeText = 'text-slate-600';
                    $body = null;
                    $isDashed = false;

                    switch ($type) {
                        case 'created':
                            $iconName = 'document-text';
                            $iconBg = 'bg-slate-100';
                            $iconColor = 'text-slate-500';
                            break;

                        case 'due_date':
                            $iconName = 'calendar';
                            $iconBg = 'bg-slate-100';
                            $iconColor = 'text-slate-400';
                            $title = __('Date d\'échéance');
                            if (isset($meta['amount_due']) && $meta['amount_due'] > 0) {
                                $body = __('Montant dû') . ' : ' . format_money($meta['amount_due'], $invoice->currency);
                            }
                            break;

                        case 'reminder':
                            /** @var \App\Models\PME\Reminder $reminder */
                            $reminder = $meta['reminder'];
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
                            $title = __('Relance') . ' ' . $channelLabel;
                            $isManual = $reminder->mode === ReminderMode::Manual;
                            $badge = $isManual ? __('Manuel') : __('Automatique');
                            $badgeBg = $isManual ? 'bg-slate-100' : 'bg-blue-50';
                            $badgeText = $isManual ? 'text-slate-600' : 'text-blue-700';
                            if ($reminder->message_body) {
                                $body = $reminder->message_body;
                            }
                            break;

                        case 'upcoming':
                            $iconName = 'clock';
                            $iconBg = 'bg-white';
                            $iconColor = 'text-slate-400';
                            $title = $event['label'];
                            $badge = __('À venir');
                            $badgeBg = 'bg-slate-50';
                            $badgeText = 'text-slate-500';
                            $isDashed = true;
                            break;

                        case 'payment':
                            $iconName = 'banknotes';
                            $iconBg = 'bg-emerald-50';
                            $iconColor = 'text-emerald-600';
                            if (isset($meta['amount'])) {
                                $body = format_money($meta['amount'], $invoice->currency);
                            }
                            break;

                        case 'paid':
                            $iconName = 'check-circle';
                            $iconBg = 'bg-accent/10';
                            $iconColor = 'text-accent';
                            break;
                    }
                @endphp

                <li wire:key="activity-{{ $loop->index }}-{{ $type }}">
                    <div @class([
                        'relative',
                        'pb-6' => ! $isLast,
                        'opacity-80' => $type === 'upcoming',
                    ])>
                        @if (! $isLast)
                            <span
                                aria-hidden="true"
                                @class([
                                    'absolute top-4 left-4 -ml-px h-full',
                                    'w-0.5 bg-slate-200' => ! $isDashed,
                                    'w-px border-l border-dashed border-slate-300' => $isDashed,
                                ])
                            ></span>
                        @endif
                        <div class="relative flex items-start gap-3">
                            <div @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                                $iconBg,
                                'border border-dashed border-slate-300' => $isDashed,
                            ])>
                                <flux:icon :name="$iconName" @class(['size-4', $iconColor]) />
                            </div>
                            <div class="min-w-0 flex-1 pt-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p @class([
                                            'text-sm font-medium',
                                            'text-slate-500' => $type === 'upcoming',
                                            'text-accent font-semibold' => $type === 'paid',
                                            'text-ink' => ! in_array($type, ['upcoming', 'paid'], true),
                                        ])>{{ $title }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                                    </div>
                                    @if ($badge)
                                        <span @class([
                                            'mt-0.5 shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                            $badgeBg,
                                            $badgeText,
                                        ])>{{ $badge }}</span>
                                    @endif
                                </div>
                                @if ($body)
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $body }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

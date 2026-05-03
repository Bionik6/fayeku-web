@props([
    'document',
    'emptyMessage' => 'Aucune activité pour le moment.',
])

<?php
/** @var \App\Models\PME\ProposalDocument $document */
$events = $document->timeline();
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

                    $iconName = 'document-text';
                    $iconBg = 'bg-slate-100';
                    $iconColor = 'text-slate-500';
                    $title = __($event['label']);
                    $subtitle = format_date($event['at'], withTime: ! in_array($type, ['created', 'valid_until', 'po_received'], true));
                    $body = null;
                    $linkLabel = null;
                    $linkUrl = null;

                    switch ($type) {
                        case 'created':
                            $iconName = 'document-plus';
                            break;

                        case 'sent':
                            $iconName = 'paper-airplane';
                            $iconBg = 'bg-blue-50';
                            $iconColor = 'text-blue-600';
                            break;

                        case 'valid_until':
                            $iconName = 'calendar';
                            $iconColor = 'text-slate-400';
                            break;

                        case 'accepted':
                            $iconName = 'check-circle';
                            $iconBg = 'bg-emerald-50';
                            $iconColor = 'text-emerald-600';
                            break;

                        case 'po_received':
                            $iconName = 'check-circle';
                            $iconBg = 'bg-emerald-50';
                            $iconColor = 'text-emerald-600';
                            if (! empty($meta['po_reference'])) {
                                $body = __('Référence') . ' : ' . $meta['po_reference'];
                            }
                            break;

                        case 'declined':
                            $iconName = 'x-circle';
                            $iconBg = 'bg-rose-50';
                            $iconColor = 'text-rose-600';
                            break;

                        case 'converted':
                            $iconName = 'document-arrow-up';
                            $iconBg = 'bg-teal-50';
                            $iconColor = 'text-teal-600';
                            $invoice = $meta['invoice'] ?? null;
                            if ($invoice) {
                                $linkLabel = $invoice->reference;
                                $linkUrl = route('pme.invoices.show', $invoice);
                            }
                            break;
                    }
                @endphp

                <li wire:key="proposal-activity-{{ $loop->index }}-{{ $type }}">
                    <div @class([
                        'relative',
                        'pb-6' => ! $isLast,
                    ])>
                        @if (! $isLast)
                            <span aria-hidden="true" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200"></span>
                        @endif
                        <div class="relative flex items-start gap-3">
                            <div @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                                $iconBg,
                            ])>
                                <flux:icon :name="$iconName" @class(['size-4', $iconColor]) />
                            </div>
                            <div class="min-w-0 flex-1 pt-1">
                                <p @class([
                                    'text-sm font-medium',
                                    'text-emerald-600 font-semibold' => in_array($type, ['accepted', 'po_received'], true),
                                    'text-rose-600 font-semibold' => $type === 'declined',
                                    'text-teal-600 font-semibold' => $type === 'converted',
                                    'text-ink' => ! in_array($type, ['accepted', 'po_received', 'declined', 'converted'], true),
                                ])>{{ $title }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                                @if ($body)
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $body }}</p>
                                @endif
                                @if ($linkUrl)
                                    <a href="{{ $linkUrl }}" wire:navigate class="mt-1 inline-flex text-sm font-medium text-primary hover:text-primary-strong">{{ $linkLabel }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<?php

namespace App\Models\PME;

use App\Enums\Compta\CertificationAuthority;
use App\Enums\PME\DunningStrategy;
use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Traits\Shared\HasPublicCode;
use App\Traits\Shared\HasUlid;
use Carbon\Carbon;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasPublicCode, HasUlid, SoftDeletes;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_id', 'proposal_document_id',
        'reference', 'currency', 'status',
        'issued_at', 'sent_at', 'due_at', 'paid_at', 'cancelled_at',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type', 'amount_paid',
        'notes', 'payment_terms', 'payment_instructions',
        'payment_method', 'payment_details', 'reminders_enabled',
        'certification_authority', 'certification_data',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'sent_at' => 'datetime',
        'due_at' => 'date',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'discount' => 'integer',
        'amount_paid' => 'integer',
        'status' => InvoiceStatus::class,
        'reminders_enabled' => 'boolean',
        'certification_authority' => CertificationAuthority::class,
        'certification_data' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProposalDocument::class, 'proposal_document_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * A reminder (manual or automatic) can only be sent for unpaid, active
     * invoices whose client has at least one contact channel filled in.
     */
    public function canReceiveReminder(): bool
    {
        if (in_array($this->status, [
            InvoiceStatus::Paid,
            InvoiceStatus::Cancelled,
            InvoiceStatus::Draft,
        ], true)) {
            return false;
        }

        return (bool) $this->client?->hasContact();
    }

    /**
     * A payment can only be recorded against a non-draft, non-cancelled invoice
     * that still has an outstanding balance.
     */
    public function canReceivePayment(): bool
    {
        if (in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            return false;
        }

        return (int) $this->amount_paid < (int) $this->total;
    }

    /**
     * Build a chronologically ordered activity feed combining all lifecycle
     * events and upcoming scheduled reminders — no activity_log table.
     *
     * @return Collection<int, array{at: Carbon, type: string, label: string, meta: array<string, mixed>}>
     */
    public function timeline(): Collection
    {
        $events = collect();

        if ($this->issued_at) {
            $events->push([
                'at' => $this->issued_at->copy()->startOfDay(),
                'type' => 'created',
                'label' => 'Facture créée',
                'meta' => [],
            ]);
        }

        if ($this->sent_at) {
            $events->push([
                'at' => $this->sent_at,
                'type' => 'sent',
                'label' => 'Facture envoyée',
                'meta' => [],
            ]);
        }

        if ($this->due_at) {
            $events->push([
                'at' => $this->due_at->copy()->startOfDay(),
                'type' => 'due_date',
                'label' => 'Date d\'échéance',
                'meta' => ['amount_due' => (int) $this->total - (int) $this->amount_paid],
            ]);
        }

        if ($this->cancelled_at) {
            $events->push([
                'at' => $this->cancelled_at,
                'type' => 'cancelled',
                'label' => 'Facture annulée',
                'meta' => [],
            ]);
        }

        foreach ($this->reminders as $reminder) {
            $at = $reminder->sent_at ?? $reminder->created_at;
            if (! $at) {
                continue;
            }
            $events->push([
                'at' => $at,
                'type' => 'reminder',
                'label' => 'Relance '.$reminder->channel->value.' envoyée',
                'meta' => ['reminder' => $reminder, 'channel' => $reminder->channel, 'mode' => $reminder->mode],
            ]);
        }

        foreach ($this->payments as $payment) {
            $events->push([
                'at' => $payment->paid_at,
                'type' => 'payment',
                'label' => 'Paiement enregistré',
                'meta' => ['amount' => $payment->amount, 'method' => $payment->method],
            ]);
        }

        if ($this->paid_at) {
            $events->push([
                'at' => $this->paid_at,
                'type' => 'paid',
                'label' => 'Facture soldée',
                'meta' => [],
            ]);
        }

        if (! $this->paid_at
            && $this->status !== InvoiceStatus::Draft
            && $this->status !== InvoiceStatus::Cancelled
            && (bool) ($this->reminders_enabled ?? true)
            && $this->due_at
        ) {
            $strategy = $this->client?->dunning_strategy;

            if ($strategy instanceof DunningStrategy && $strategy !== DunningStrategy::None) {
                $alreadySent = $this->reminders
                    ->whereNotNull('day_offset')
                    ->pluck('day_offset')
                    ->map(fn ($o) => (int) $o)
                    ->all();

                foreach ($strategy->offsets() as $offset) {
                    if (in_array($offset, $alreadySent, true)) {
                        continue;
                    }
                    $scheduledAt = $this->due_at->copy()->addDays($offset);
                    if ($scheduledAt->isPast()) {
                        continue;
                    }
                    $events->push([
                        'at' => $scheduledAt,
                        'type' => 'upcoming',
                        'label' => "Relance prévue à J+{$offset}",
                        'meta' => ['offset' => $offset],
                    ]);
                }
            }
        }

        return $events->sortBy('at')->values();
    }
}

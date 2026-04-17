<?php

namespace App\Models\PME;

use App\Enums\Compta\CertificationAuthority;
use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_id', 'quote_id', 'reference', 'currency', 'status',
        'issued_at', 'due_at', 'paid_at',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type', 'amount_paid',
        'notes', 'payment_terms', 'payment_instructions',
        'payment_method', 'payment_details', 'reminders_enabled',
        'certification_authority', 'certification_data',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'paid_at' => 'datetime',
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * A reminder (manual or automatic) can only be sent for unpaid, active invoices.
     */
    public function canReceiveReminder(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Paid,
            InvoiceStatus::Cancelled,
            InvoiceStatus::Draft,
        ], true);
    }
}

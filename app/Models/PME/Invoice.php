<?php

namespace App\Models\PME;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\Company;
use App\Enums\Compta\CertificationAuthority;
use App\Models\PME\Client;
use App\Models\PME\Reminder;
use App\Enums\PME\InvoiceStatus;
use App\Traits\Shared\HasUlid;

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
        'payment_method', 'payment_details', 'reminder_schedule',
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
        'reminder_schedule' => 'array',
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
}

<?php

namespace Modules\PME\Invoicing\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\Company;
use Modules\Compta\Compliance\Enums\CertificationAuthority;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\Shared\Traits\HasUlid;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_id', 'reference', 'currency', 'status',
        'issued_at', 'due_at', 'paid_at',
        'subtotal', 'tax_amount', 'total', 'amount_paid',
        'notes', 'payment_terms', 'payment_instructions',
        'certification_authority', 'certification_data',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'amount_paid' => 'integer',
        'status' => InvoiceStatus::class,
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

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}

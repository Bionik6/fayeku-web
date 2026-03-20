<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\Shared\Traits\HasUlid;

class Invoice extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'reference', 'status',
        'issued_at', 'due_at', 'paid_at',
        'subtotal', 'tax_amount', 'total', 'notes',
        // FNE (Côte d'Ivoire)
        'fne_reference', 'fne_token', 'fne_certified_at',
        'fne_balance_sticker', 'fne_raw_response',
        // DGID (Sénégal — reserved, API not yet published)
        'dgid_reference', 'dgid_token', 'dgid_certified_at',
    ];

    protected $casts = [
        'issued_at'           => 'date',
        'due_at'              => 'date',
        'paid_at'             => 'datetime',
        'fne_certified_at'    => 'datetime',
        'dgid_certified_at'   => 'datetime',
        'subtotal'            => 'integer',
        'tax_amount'          => 'integer',
        'total'               => 'integer',
        'fne_balance_sticker' => 'integer',
        'fne_raw_response'    => 'array',
        'status'              => InvoiceStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\Modules\PME\Clients\Models\Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(\Modules\PME\Collection\Models\Reminder::class);
    }
}

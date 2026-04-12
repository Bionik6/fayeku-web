<?php

namespace App\Models\PME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Enums\PME\QuoteStatus;
use App\Traits\Shared\HasUlid;

class Quote extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'reference', 'currency', 'status',
        'issued_at', 'valid_until',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type', 'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'discount' => 'integer',
        'status' => QuoteStatus::class,
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
        return $this->hasMany(QuoteLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'quote_id');
    }
}

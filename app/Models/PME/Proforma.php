<?php

namespace App\Models\PME;

use App\Enums\PME\ProformaStatus;
use App\Models\Auth\Company;
use App\Traits\Shared\HasPublicCode;
use App\Traits\Shared\HasUlid;
use Database\Factories\ProformaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proforma extends Model
{
    /** @use HasFactory<ProformaFactory> */
    use HasFactory, HasPublicCode, HasUlid, SoftDeletes;

    protected static function newFactory(): ProformaFactory
    {
        return ProformaFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_id', 'reference', 'currency', 'status',
        'issued_at', 'valid_until',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type',
        'dossier_reference', 'payment_terms', 'delivery_terms', 'notes',
        'po_reference', 'po_received_at', 'po_notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
        'po_received_at' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'discount' => 'integer',
        'status' => ProformaStatus::class,
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
        return $this->hasMany(ProformaLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'proforma_id');
    }
}

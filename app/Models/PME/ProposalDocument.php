<?php

namespace App\Models\PME;

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Traits\Shared\HasPublicCode;
use App\Traits\Shared\HasUlid;
use Database\Factories\ProposalDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalDocument extends Model
{
    /** @use HasFactory<ProposalDocumentFactory> */
    use HasFactory, HasPublicCode, HasUlid, SoftDeletes;

    protected static function newFactory(): ProposalDocumentFactory
    {
        return ProposalDocumentFactory::new();
    }

    protected $fillable = [
        'company_id', 'client_id', 'type', 'reference', 'currency', 'status',
        'issued_at', 'valid_until',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type', 'notes',
        'dossier_reference', 'payment_terms', 'delivery_terms',
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
        'type' => ProposalDocumentType::class,
        'status' => ProposalDocumentStatus::class,
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
        return $this->hasMany(ProposalDocumentLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'proposal_document_id');
    }

    public function scopeQuotes(Builder $query): Builder
    {
        return $query->where('type', ProposalDocumentType::Quote);
    }

    public function scopeProformas(Builder $query): Builder
    {
        return $query->where('type', ProposalDocumentType::Proforma);
    }

    public function scopeOfType(Builder $query, ProposalDocumentType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isQuote(): bool
    {
        return $this->type === ProposalDocumentType::Quote;
    }

    public function isProforma(): bool
    {
        return $this->type === ProposalDocumentType::Proforma;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type->label();
    }
}

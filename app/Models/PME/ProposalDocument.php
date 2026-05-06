<?php

namespace App\Models\PME;

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Traits\Shared\HasPublicCode;
use App\Traits\Shared\HasUlid;
use Carbon\Carbon;
use Database\Factories\ProposalDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
        'sent_at', 'accepted_at', 'declined_at', 'converted_at',
        'subtotal', 'tax_amount', 'total', 'discount', 'discount_type', 'notes',
        'dossier_reference', 'payment_terms', 'delivery_terms',
        'po_reference', 'po_received_at', 'po_notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'converted_at' => 'datetime',
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

    /**
     * Build a chronologically ordered activity feed for the document, combining
     * lifecycle timestamps, the validity deadline, and the linked invoice when
     * the document has been converted.
     *
     * @return Collection<int, array{at: Carbon, type: string, label: string, meta: array<string, mixed>}>
     */
    public function timeline(): Collection
    {
        $isProforma = $this->isProforma();
        $events = collect();

        $createdAt = $this->issued_at ?? $this->created_at;
        if ($createdAt) {
            $events->push([
                'at' => $createdAt->copy()->startOfDay(),
                'type' => 'created',
                'label' => $isProforma ? 'Proforma créée' : 'Devis créé',
                'meta' => [],
            ]);
        }

        if ($this->sent_at) {
            $events->push([
                'at' => $this->sent_at,
                'type' => 'sent',
                'label' => $isProforma ? 'Proforma envoyée' : 'Devis envoyé',
                'meta' => [],
            ]);
        }

        if ($this->valid_until) {
            $events->push([
                'at' => $this->valid_until->copy()->startOfDay(),
                'type' => 'valid_until',
                'label' => 'Date de validité',
                'meta' => [],
            ]);
        }

        if ($this->accepted_at) {
            $events->push([
                'at' => $this->accepted_at,
                'type' => 'accepted',
                'label' => $isProforma ? 'Proforma acceptée' : 'Devis accepté',
                'meta' => [],
            ]);
        }

        if ($this->po_received_at) {
            $events->push([
                'at' => $this->po_received_at->copy()->startOfDay(),
                'type' => 'po_received',
                'label' => 'Bon de commande reçu',
                'meta' => [
                    'po_reference' => $this->po_reference,
                    'po_notes' => $this->po_notes,
                ],
            ]);
        }

        if ($this->declined_at) {
            $events->push([
                'at' => $this->declined_at,
                'type' => 'declined',
                'label' => $isProforma ? 'Proforma refusée' : 'Devis refusé',
                'meta' => [],
            ]);
        }

        if ($this->converted_at) {
            $events->push([
                'at' => $this->converted_at,
                'type' => 'converted',
                'label' => 'Convertie en facture',
                'meta' => ['invoice' => $this->invoice],
            ]);
        }

        return $events->sortBy('at')->values();
    }
}

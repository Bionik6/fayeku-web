<?php

namespace App\Models\PME;

use App\Traits\Shared\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalDocumentLine extends Model
{
    use HasUlid;

    protected $fillable = [
        'proposal_document_id', 'description', 'quantity',
        'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'tax_rate' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProposalDocument::class, 'proposal_document_id');
    }
}

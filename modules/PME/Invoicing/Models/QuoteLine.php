<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PME\Invoicing\Enums\InvoiceLineType;
use Modules\Shared\Traits\HasUlid;

class QuoteLine extends Model
{
    use HasUlid;

    protected $fillable = [
        'quote_id', 'description', 'type', 'quantity',
        'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
        'type' => InvoiceLineType::class,
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'tax_rate' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}

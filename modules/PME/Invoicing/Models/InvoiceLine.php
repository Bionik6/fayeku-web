<?php

namespace Modules\PME\Invoicing\Models;

use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\PME\Invoicing\Enums\LineType;
use Modules\Shared\Traits\HasUlid;

class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory, HasUlid;

    protected static function newFactory(): InvoiceLineFactory
    {
        return InvoiceLineFactory::new();
    }

    protected $fillable = [
        'invoice_id', 'description', 'type', 'quantity',
        'unit_price', 'tax_rate', 'total',
    ];

    protected $casts = [
        'type' => LineType::class,
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'tax_rate' => 'integer',
        'total' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

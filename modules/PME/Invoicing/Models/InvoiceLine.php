<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class InvoiceLine extends Model
{
    use HasUlid;

    protected $fillable = [
        'invoice_id', 'description', 'quantity',
        'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'integer',
        'tax_rate'   => 'integer',
        'discount'   => 'integer',
        'total'      => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

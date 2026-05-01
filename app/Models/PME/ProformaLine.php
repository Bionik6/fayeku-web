<?php

namespace App\Models\PME;

use App\Traits\Shared\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaLine extends Model
{
    use HasUlid;

    protected $fillable = [
        'proforma_id', 'description', 'quantity',
        'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'tax_rate' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }
}

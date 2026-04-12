<?php

namespace App\Models\PME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Shared\HasUlid;

class QuoteLine extends Model
{
    use HasUlid;

    protected $fillable = [
        'quote_id', 'description', 'quantity',
        'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
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

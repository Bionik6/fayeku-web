<?php

namespace App\Models\PME;

use App\Enums\PME\PaymentMethod;
use App\Models\Shared\User;
use App\Traits\Shared\HasUlid;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'invoice_id', 'amount', 'paid_at', 'method',
        'reference', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'method' => PaymentMethod::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

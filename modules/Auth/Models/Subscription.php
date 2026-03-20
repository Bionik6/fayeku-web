<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class Subscription extends Model
{
    use HasUlid;

    protected $fillable = [
        'company_id', 'plan_slug', 'price_paid', 'billing_cycle', 'status',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancelled_at', 'invited_by_firm_id',
    ];

    protected $casts = [
        'price_paid' => 'integer',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

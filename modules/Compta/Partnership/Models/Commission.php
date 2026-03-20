<?php

namespace Modules\Compta\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class Commission extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id', 'sme_company_id', 'subscription_id',
        'amount', 'period_month', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'integer',
        'paid_at' => 'datetime',
    ];
}

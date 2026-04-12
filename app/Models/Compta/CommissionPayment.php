<?php

namespace App\Models\Compta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;

class CommissionPayment extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id',
        'period_month',
        'active_clients_count',
        'amount',
        'paid_at',
        'payment_method',
        'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'integer',
        'active_clients_count' => 'integer',
        'period_month' => 'date',
        'paid_at' => 'date',
    ];

    /** @return BelongsTo<Company, $this> */
    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'accountant_firm_id');
    }
}

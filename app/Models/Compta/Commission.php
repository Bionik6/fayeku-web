<?php

namespace App\Models\Compta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Traits\Shared\HasUlid;

class Commission extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id', 'sme_company_id', 'subscription_id',
        'amount', 'period_month', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'period_month' => 'date',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'accountant_firm_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function smeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'sme_company_id');
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Shared\HasUlid;

class AccountantCompany extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id', 'sme_company_id',
        'started_at', 'ended_at', 'ended_reason',
    ];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'accountant_firm_id');
    }

    public function smeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'sme_company_id');
    }
}

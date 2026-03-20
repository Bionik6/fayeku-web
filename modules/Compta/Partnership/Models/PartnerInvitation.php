<?php

namespace Modules\Compta\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class PartnerInvitation extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id', 'token', 'invitee_phone', 'invitee_name',
        'recommended_plan', 'status', 'expires_at', 'accepted_at', 'sme_company_id',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class, 'accountant_firm_id');
    }
}

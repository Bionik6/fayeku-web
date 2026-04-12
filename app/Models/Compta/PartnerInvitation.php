<?php

namespace App\Models\Compta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;

class PartnerInvitation extends Model
{
    use HasUlid;

    protected $fillable = [
        'accountant_firm_id', 'token', 'invitee_phone', 'invitee_name',
        'invitee_company_name', 'recommended_plan', 'channel', 'status',
        'expires_at', 'accepted_at', 'sme_company_id',
        'link_opened_at', 'last_reminder_at', 'reminder_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'link_opened_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'reminder_count' => 'integer',
    ];

    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'accountant_firm_id');
    }

    public function smeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'sme_company_id');
    }
}

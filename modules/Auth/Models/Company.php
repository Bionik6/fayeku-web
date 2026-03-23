<?php

namespace Modules\Auth\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Shared\Models\User;
use Modules\Shared\Traits\HasUlid;

class Company extends Model
{
    use HasFactory, HasUlid;

    protected $fillable = ['name', 'type', 'plan', 'country_code', 'phone', 'email', 'address', 'city', 'ninea', 'rccm', 'logo_path'];

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'company_user', 'company_id', 'user_id'
        )->withPivot('role')->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function managedSmes(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, 'accountant_firm_id')->whereNull('ended_at');
    }

    public function activeAccountants(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, 'sme_company_id')->whereNull('ended_at');
    }
}

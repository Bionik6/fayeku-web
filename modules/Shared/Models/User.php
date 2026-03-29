<?php

namespace Modules\Shared\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Models\Company;
use Modules\Shared\Traits\HasUlid;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlid, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email',
        'password', 'profile_type', 'country_code', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function initials(): string
    {
        return mb_strtoupper(
            mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)
        );
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'company_user', 'user_id', 'company_id'
        )->withPivot('role')->withTimestamps();
    }

    private bool $smeCompanyLoaded = false;

    private ?Company $smeCompanyCache = null;

    /**
     * Return the user's SME company, cached on the instance (1 query per object lifecycle).
     */
    public function smeCompany(): ?Company
    {
        if (! $this->smeCompanyLoaded) {
            $this->smeCompanyCache = $this->companies()->where('type', 'sme')->first();
            $this->smeCompanyLoaded = true;
        }

        return $this->smeCompanyCache;
    }
}

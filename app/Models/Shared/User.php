<?php

namespace App\Models\Shared;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;

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

    /** @var array<string, array{loaded: bool, value: ?Company}> */
    private array $companyCache = [];

    /**
     * Return the user's SME company, cached on the instance (1 query per object lifecycle).
     */
    public function smeCompany(): ?Company
    {
        return $this->cachedCompany('sme');
    }

    /**
     * Return the user's accountant firm, cached on the instance (1 query per object lifecycle).
     */
    public function accountantFirm(): ?Company
    {
        return $this->cachedCompany('accountant_firm');
    }

    private function cachedCompany(string $type): ?Company
    {
        if (! isset($this->companyCache[$type])) {
            $this->companyCache[$type] = $this->companies()->where('type', $type)->first();
        }

        return $this->companyCache[$type];
    }
}

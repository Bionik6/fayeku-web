<?php

namespace App\Models\Auth;

use App\Models\Shared\User;
use App\Traits\Shared\HasUlid;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, HasUlid;

    protected $fillable = [
        'name',
        'type',
        'plan',
        'invite_code',
        'country_code',
        'phone',
        'email',
        'sender_name',
        'sender_role',
        'address',
        'city',
        'ninea',
        'rccm',
        'logo_path',
        'sector',
        'setup_completed_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Company $company) {
            if ($company->type === 'accountant_firm' && empty($company->invite_code)) {
                do {
                    $code = strtoupper(Str::random(6));
                } while (static::where('invite_code', $code)->exists());

                $company->invite_code = $code;
            }
        });
    }

    protected $casts = [
        'setup_completed_at' => 'datetime',
    ];

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'company_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function managedSmes(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, 'accountant_firm_id')
            ->whereNull('ended_at');
    }

    public function activeAccountants(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, 'sme_company_id')
            ->whereNull('ended_at');
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    public function composeSenderSignature(): string
    {
        $name = $this->sender_name;
        $role = $this->sender_role;
        $companyName = $this->name;

        if ($name && $role) {
            return "{$name}, {$role} {$companyName}";
        }

        if ($name) {
            return "{$name}, {$companyName}";
        }

        return "L'équipe {$companyName}";
    }
}

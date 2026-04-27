<?php

namespace App\Models\Compta;

use App\Enums\Compta\LeadSource;
use App\Models\Auth\Company;
use App\Models\Shared\User;
use App\Traits\Shared\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountantLead extends Model
{
    use HasUlid;

    protected $fillable = [
        'first_name', 'last_name', 'firm', 'email', 'country_code', 'phone',
        'region', 'portfolio_size', 'message', 'source', 'status', 'notes',
        'contacted_at', 'activated_at', 'rejected_at', 'rejected_reason',
        'user_id', 'company_id',
        'activation_token_hash', 'activation_token_expires_at',
    ];

    protected $hidden = [
        'activation_token_hash',
    ];

    protected $casts = [
        'source' => LeadSource::class,
        'contacted_at' => 'datetime',
        'activated_at' => 'datetime',
        'rejected_at' => 'datetime',
        'activation_token_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function generateActivationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->forceFill([
            'activation_token_hash' => hash('sha256', $token),
            'activation_token_expires_at' => now()->addDays(7),
        ])->save();

        return $token;
    }

    public function invalidateActivationToken(): void
    {
        $this->forceFill([
            'activation_token_hash' => null,
            'activation_token_expires_at' => null,
        ])->save();
    }

    public function isActivationTokenValid(string $token): bool
    {
        if (! $this->activation_token_hash || ! $this->activation_token_expires_at) {
            return false;
        }

        if (! hash_equals($this->activation_token_hash, hash('sha256', $token))) {
            return false;
        }

        return $this->activation_token_expires_at->isFuture();
    }
}

<?php

namespace Modules\Shared\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Shared\Traits\HasUlid;

class User extends Authenticatable
{
    use HasApiTokens, HasUlid, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'phone',
        'password', 'profile_type', 'country_code', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    public function companies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Auth\Models\Company::class,
            'company_user', 'user_id', 'company_id'
        )->withPivot('role')->withTimestamps();
    }
}

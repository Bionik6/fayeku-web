<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Shared\HasUlid;

class Country extends Model
{
    use HasUlid;

    protected $fillable = ['code', 'name', 'phone_prefix', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}

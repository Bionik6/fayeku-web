<?php

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class Country extends Model
{
    use HasUlid;

    protected $fillable = ['code', 'name', 'phone_prefix', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];
}

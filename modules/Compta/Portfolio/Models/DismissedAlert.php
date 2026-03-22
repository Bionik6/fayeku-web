<?php

namespace Modules\Compta\Portfolio\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class DismissedAlert extends Model
{
    use HasUlid;

    protected $fillable = ['user_id', 'alert_key', 'dismissed_at'];

    /** @var array<string, string> */
    protected $casts = [
        'dismissed_at' => 'datetime',
    ];
}

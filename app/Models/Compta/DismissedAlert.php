<?php

namespace App\Models\Compta;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Shared\HasUlid;

class DismissedAlert extends Model
{
    use HasUlid;

    protected $fillable = ['user_id', 'alert_key', 'dismissed_at'];

    /** @var array<string, string> */
    protected $casts = [
        'dismissed_at' => 'datetime',
    ];
}

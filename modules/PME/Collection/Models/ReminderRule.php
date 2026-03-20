<?php

namespace Modules\PME\Collection\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class ReminderRule extends Model
{
    use HasUlid;

    protected $fillable = [
        'company_id', 'name', 'trigger_days', 'channel', 'template', 'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'trigger_days' => 'integer',
    ];
}

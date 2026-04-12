<?php

namespace App\Models\PME;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Traits\Shared\HasUlid;

class ReminderRule extends Model
{
    use HasUlid;

    protected $fillable = [
        'company_id', 'name', 'trigger_days', 'channel', 'template', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_days' => 'integer',
        'channel' => ReminderChannel::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

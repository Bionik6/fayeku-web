<?php

namespace App\Models\Shared;

use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'company_id',
        'notifiable_type',
        'notifiable_id',
        'template_key',
        'channel',
        'sent_at',
        'message_body',
        'recipient_phone',
        'recipient_email',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}

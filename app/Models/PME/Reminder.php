<?php

namespace App\Models\PME;

use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Traits\Shared\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'invoice_id', 'channel', 'mode', 'sent_at',
        'message_body', 'recipient_phone', 'recipient_email',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'channel' => ReminderChannel::class,
        'mode' => ReminderMode::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

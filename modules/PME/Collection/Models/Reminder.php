<?php

namespace Modules\PME\Collection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Traits\HasUlid;

class Reminder extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'invoice_id', 'channel', 'status', 'sent_at',
        'message_body', 'recipient_phone', 'recipient_email',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'channel' => ReminderChannel::class,
        'status' => ReminderStatus::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

<?php

namespace App\Models\PME;

use App\Enums\PME\DunningStrategy;
use App\Enums\PME\ProposalDocumentType;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Traits\Shared\HasUlid;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasUlid, SoftDeletes;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'address', 'tax_id', 'rccm', 'dunning_strategy',
    ];

    /**
     * True when neither an email nor a phone number is recorded.
     */
    public function lacksContact(): bool
    {
        return blank($this->email) && blank($this->phone);
    }

    /**
     * Two-letter initials derived from the client name (e.g. "Sabira Sasu" → "SS").
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    protected $casts = [
        'dunning_strategy' => DunningStrategy::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function proposalDocuments(): HasMany
    {
        return $this->hasMany(ProposalDocument::class);
    }

    public function quotes(): HasMany
    {
        return $this->proposalDocuments()->where('type', ProposalDocumentType::Quote);
    }

    public function proformas(): HasMany
    {
        return $this->proposalDocuments()->where('type', ProposalDocumentType::Proforma);
    }

    /**
     * True when the client has at least one contact channel filled in.
     * A client without phone nor email cannot receive any reminder.
     */
    public function hasContact(): bool
    {
        return filled($this->phone) || filled($this->email);
    }

    /**
     * True when the client can be reached on the given reminder channel.
     * WhatsApp and SMS need a phone, Email needs an email.
     */
    public function canReceiveReminderOn(ReminderChannel $channel): bool
    {
        return match ($channel) {
            ReminderChannel::Email => filled($this->email),
            ReminderChannel::WhatsApp, ReminderChannel::Sms => filled($this->phone),
        };
    }
}

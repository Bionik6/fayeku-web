<?php

namespace App\Notifications;

use App\Mail\Compta\AccountantPasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountantPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountantPasswordResetMail
    {
        return (new AccountantPasswordResetMail(
            firstName: (string) ($notifiable->first_name ?? ''),
            resetUrl: $this->resetUrl,
            expiresInMinutes: $this->expiresInMinutes,
        ))->to($notifiable->email);
    }
}

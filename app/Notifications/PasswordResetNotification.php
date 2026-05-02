<?php

namespace App\Notifications;

use App\Mail\Auth\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
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

    public function toMail(object $notifiable): PasswordResetMail
    {
        return (new PasswordResetMail(
            firstName: (string) ($notifiable->first_name ?? ''),
            resetUrl: $this->resetUrl,
            expiresInMinutes: $this->expiresInMinutes,
        ))->to($notifiable->email);
    }
}

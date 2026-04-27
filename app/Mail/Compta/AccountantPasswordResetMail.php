<?php

namespace App\Mail\Compta;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountantPasswordResetMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fayeku Compta - Réinitialisez votre mot de passe',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.compta.accountant-password-reset',
            with: [
                'firstName' => $this->firstName,
                'resetUrl' => $this->resetUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}

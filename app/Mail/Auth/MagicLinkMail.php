<?php

namespace App\Mail\Auth;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $magicUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fayeku - Votre lien de connexion',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.magic-link',
            with: [
                'firstName' => $this->firstName,
                'magicUrl' => $this->magicUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}

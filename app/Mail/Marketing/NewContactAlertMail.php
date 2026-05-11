<?php

namespace App\Mail\Marketing;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewContactAlertMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function envelope(): Envelope
    {
        $name = $this->payload['full_name'] ?? 'Anonyme';

        return new Envelope(
            subject: 'Nouveau contact marketing : '.$name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.marketing.contact-alert',
            with: ['payload' => $this->payload],
        );
    }
}

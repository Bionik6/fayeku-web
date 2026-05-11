<?php

namespace App\Mail\Marketing;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactReceivedMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fayeku — Nous avons bien reçu votre message',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.marketing.contact-received',
            with: ['payload' => $this->payload],
        );
    }
}

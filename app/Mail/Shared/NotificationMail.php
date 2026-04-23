<?php

namespace App\Mail\Shared;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable générique pour toutes les notifications Fayeku (facture envoyée,
 * paiement reçu, relance, devis…). Le corps est rendu depuis
 * WhatsAppTemplateCatalog pour rester aligné avec le message WhatsApp.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
        public readonly string $companyName,
        public readonly ?string $ctaUrl = null,
        public readonly ?string $ctaLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'body' => $this->body,
                'companyName' => $this->companyName,
                'ctaUrl' => $this->ctaUrl,
                'ctaLabel' => $this->ctaLabel,
            ],
        );
    }
}

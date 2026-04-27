<?php

namespace App\Mail\Compta;

use App\Models\Compta\AccountantLead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountantActivationLinkMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly AccountantLead $lead,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fayeku Compta - Activez votre accès',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.compta.accountant-activation-link',
            with: [
                'lead' => $this->lead,
                'activationUrl' => route('accountant.activation', $this->token),
            ],
        );
    }
}

<?php

namespace App\Mail\Compta;

use App\Models\Compta\AccountantLead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountantLeadReceivedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly AccountantLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fayeku Compta - Nous avons bien reçu votre demande',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.compta.accountant-lead-received',
            with: ['lead' => $this->lead],
        );
    }
}

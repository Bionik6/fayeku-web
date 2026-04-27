<?php

namespace App\Mail\Compta;

use App\Models\Compta\AccountantLead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAccountantLeadAlertMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly AccountantLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande cabinet: '.$this->lead->firm,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.compta.accountant-lead-alert',
            with: ['lead' => $this->lead],
        );
    }
}

<?php

namespace App\Mail\PME;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\PME\Invoice;
use App\Services\PME\PdfService;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Facture :reference', ['reference' => $this->invoice->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'messageBody' => $this->messageBody,
                'companyName' => $this->invoice->company->name ?? '',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdfContent = app(PdfService::class)->rawContent($this->invoice);

        return [
            Attachment::fromData(fn () => $pdfContent, "facture-{$this->invoice->reference}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

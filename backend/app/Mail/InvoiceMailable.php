<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\WebsiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        $issuerName = WebsiteSettings::businessCompanyName();

        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} - ".$issuerName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'amountDue' => $this->invoice->amount_due,
                'dueDate' => $this->invoice->due_date,
                'issuerName' => WebsiteSettings::businessCompanyName(),
            ],
        );
    }

    /**
     * Attach invoice PDF when available.
     */
    public function attachments(): array
    {
        $relativePdfPath = (string) ($this->invoice->pdf_path ?? '');
        if ($relativePdfPath === '') {
            return [];
        }

        $fullPath = storage_path('app/private/'.$relativePdfPath);
        if (! is_file($fullPath)) {
            return [];
        }

        $filename = (string) ($this->invoice->invoice_number ?: ('invoice-'.$this->invoice->id));

        return [
            Attachment::fromPath($fullPath)
                ->as($filename.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

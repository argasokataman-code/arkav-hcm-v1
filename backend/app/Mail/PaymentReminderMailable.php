<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        $isOverdue = $this->invoice->due_date->isPast();
        $subject = $isOverdue ? 'Overdue Invoice Reminder' : 'Upcoming Invoice Due';

        return new Envelope(
            subject: "$subject - {$this->invoice->invoice_number}",
        );
    }

    public function content(): Content
    {
        $isOverdue = $this->invoice->due_date->isPast();
        $daysOverdue = abs($this->invoice->due_date->diffInDays(now()));

        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'isOverdue' => $isOverdue,
                'daysOverdue' => $daysOverdue,
            ],
        );
    }
}

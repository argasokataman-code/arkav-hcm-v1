<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AdminComposeMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $messageBody,
        public string $senderName,
        public ?string $deliveryUuid = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-compose',
            with: [
                'subjectLine' => $this->subjectLine,
                'messageBody' => $this->messageBody,
                'senderName' => $this->senderName,
            ],
        );
    }

    public function headers(): Headers
    {
        if ($this->deliveryUuid === null || trim($this->deliveryUuid) === '') {
            return new Headers();
        }

        $uuid = trim($this->deliveryUuid);

        return new Headers(
            text: [
                'X-Arcav-Delivery-UUID' => $uuid,
                'X-Mailin-custom' => 'arcav_delivery_uuid:'.$uuid,
            ],
        );
    }
}
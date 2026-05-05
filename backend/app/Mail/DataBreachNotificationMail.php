<?php

namespace App\Mail;

use App\Models\DataBreachIncident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataBreachNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly DataBreachIncident $incident,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi Insiden Keamanan Data - ARCAV HCM',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.security.breach-notification',
            with: [
                'recipientName' => $this->user->name,
                'incidentTitle' => $this->incident->title,
                'incidentDescription' => $this->incident->description,
                'affectedDataTypes' => (array) ($this->incident->affected_data_types ?? []),
                'detectedAt' => optional($this->incident->detected_at)?->format('d M Y H:i'),
                'dpoName' => (string) config('pdp.dpo_name', 'Tim Data Protection ARCAV HCM'),
                'dpoEmail' => (string) config('pdp.dpo_email', 'dpo@arcav.id'),
                'privacyContactUrl' => (string) config('pdp.privacy_contact_url', url('/privacy-policy')),
            ],
        );
    }
}

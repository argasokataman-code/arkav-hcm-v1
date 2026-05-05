<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileUpdatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int,string> $changedFields
     */
    public function __construct(
        public readonly User $employee,
        public readonly array $changedFields,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi Perubahan Data Profil – ARCAV HCM',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee.profile-updated',
            with: [
                'employeeName'  => $this->employee->name,
                'changedFields' => $this->changedFields,
                'updatedAt'     => now()->format('d M Y H:i'),
            ],
        );
    }
}

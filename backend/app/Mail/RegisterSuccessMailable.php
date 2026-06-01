<?php

namespace App\Mail;

use App\Models\User;
use App\Support\WebsiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterSuccessMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        $issuerName = WebsiteSettings::businessCompanyName();

        return new Envelope(
            subject: 'Registrasi Berhasil - '.$issuerName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.register-success',
            with: [
                'user' => $this->user,
                'issuerName' => WebsiteSettings::businessCompanyName(),
            ],
        );
    }
}

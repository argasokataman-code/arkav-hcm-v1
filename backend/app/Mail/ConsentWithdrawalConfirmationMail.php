<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsentWithdrawalConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{ai_chat: bool, biometric: bool} $withdrawnScopes
     */
    public function __construct(
        public readonly User $user,
        public readonly string $scope,
        public readonly array $withdrawnScopes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Konfirmasi Pencabutan Persetujuan Data - ARCAV HCM',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.privacy.consent-withdrawal-confirmation',
            with: [
                'recipientName' => $this->user->name,
                'scope' => $this->scope,
                'withdrawnScopes' => $this->withdrawnScopes,
                'withdrawnAt' => now()->format('d M Y H:i'),
                'dpoEmail' => (string) config('pdp.dpo_email', 'dpo@arcav.id'),
            ],
        );
    }
}

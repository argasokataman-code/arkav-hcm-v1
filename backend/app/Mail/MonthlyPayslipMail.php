<?php

namespace App\Mail;

use App\Models\User;
use App\Services\PayslipEncryptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyPayslipMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $slip
     */
    public function __construct(
        public User $user,
        public array $slip,
        public string $pdfContent,
        public string $companyName = '',
        public bool $isEncrypted = false,
        public string $decryptionPassword = '',
    ) {}

    public function envelope(): Envelope
    {
        $period = $this->slip['period'] ?? [];
        $label = sprintf(
            '%02d/%04d',
            (int) ($period['periodMonth'] ?? 0),
            (int) ($period['periodYear'] ?? 0),
        );

        return new Envelope(
            subject: 'Payslip '.$label.' - '.$this->companyName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-payslip',
            with: [
                'user' => $this->user,
                'slip' => $this->slip,
                'appName' => $this->companyName,
                'isEncrypted' => $this->isEncrypted,
                'decryptionPassword' => $this->decryptionPassword,
            ],
        );
    }

    public function attachments(): array
    {
        $slipNumber = (string) ($this->slip['slipNumber'] ?? 'payslip');
        $extension = $this->isEncrypted ? 'enc' : 'pdf';
        $content = $this->pdfContent;

        return [
            Attachment::fromData(fn () => $content, $slipNumber.'.'.$extension)
                ->withMime($this->isEncrypted ? 'application/octet-stream' : 'application/pdf'),
        ];
    }
}

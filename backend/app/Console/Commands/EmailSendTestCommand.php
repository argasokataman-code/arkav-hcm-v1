<?php

namespace App\Console\Commands;

use App\Services\EmailSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EmailSendTestCommand extends Command
{
    protected $signature = 'email:send-test {to : Recipient email address} {--subject=Arkav SMTP Runtime Test}';

    protected $description = 'Send a runtime test email using the currently active Laravel mail transport.';

    public function handle(EmailSettingsService $emailSettingsService): int
    {
        $to = trim((string) $this->argument('to'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid recipient email address.');

            return self::INVALID;
        }

        $profile = $emailSettingsService->getRuntimeTransportProfile();
        $transport = $emailSettingsService->resolveRuntimeSmtpTransport();
        $provider = (string) ($profile['provider'] ?? 'unknown');
        $mailer = (string) config('mail.default', 'unknown');

        $this->line('Runtime provider: '.$provider);
        $this->line('Laravel mailer: '.$mailer);
        $this->line('From address: '.(string) config('mail.from.address', 'n/a'));
        $this->line('Resolved SMTP source: '.(string) ($transport['source'] ?? 'none'));
        $this->line('Resolved SMTP host: '.(string) ($transport['host'] ?? 'n/a'));
        $this->line('Resolved SMTP port: '.(string) ($transport['port'] ?? 'n/a'));

        try {
            Mail::raw('Arkav runtime outbound email test at '.now()->toIso8601String(), function ($message) use ($to): void {
                $message->to($to)->subject((string) $this->option('subject'));
            });
        } catch (\Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Dispatch success. Check recipient inbox/spam when using real SMTP transport.');

        return self::SUCCESS;
    }
}

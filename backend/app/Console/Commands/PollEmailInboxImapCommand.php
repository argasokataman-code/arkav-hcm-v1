<?php

namespace App\Console\Commands;

use App\Models\NotificationDelivery;
use Illuminate\Console\Command;

class PollEmailInboxImapCommand extends Command
{
    protected $signature = 'email:poll-imap-inbox {--all : Ambil semua pesan, bukan hanya UNSEEN}';

    protected $description = 'Poll mailbox IMAP lalu simpan pesan inbound ke inbox runtime.';

    public function handle(): int
    {
        $config = config('services.email_inbound.imap', []);
        $enabled = (bool) ($config['enabled'] ?? false);
        if (! $enabled) {
            $this->line('IMAP inbound polling disabled.');

            return self::SUCCESS;
        }

        if (! function_exists('imap_open')) {
            $this->error('PHP IMAP extension tidak tersedia.');

            return self::FAILURE;
        }

        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 993);
        $encryption = strtolower(trim((string) ($config['encryption'] ?? 'ssl')));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $folder = trim((string) ($config['folder'] ?? 'INBOX'));

        if ($host === '' || $username === '' || $password === '') {
            $this->warn('IMAP credential belum lengkap.');

            return self::SUCCESS;
        }

        $mailbox = sprintf('{%s:%d/imap/%s}'.$folder, $host, $port, $encryption === 'tls' ? 'tls' : 'ssl');
        $stream = @imap_open($mailbox, $username, $password);
        if (! is_resource($stream) && ! ($stream instanceof \IMAP\Connection)) {
            $this->error('Gagal membuka koneksi IMAP.');

            return self::FAILURE;
        }

        $criteria = $this->option('all') ? 'ALL' : 'UNSEEN';
        $messageNumbers = imap_search($stream, $criteria) ?: [];

        $saved = 0;
        foreach ($messageNumbers as $messageNumber) {
            $overviewList = imap_fetch_overview($stream, (string) $messageNumber, 0) ?: [];
            $overview = $overviewList[0] ?? null;
            if (! is_object($overview)) {
                continue;
            }

            $messageId = trim((string) ($overview->message_id ?? ''));
            if ($messageId === '') {
                $messageId = sha1((string) $messageNumber.'|'.(string) ($overview->date ?? now()->toIso8601String()));
            }
            $messageId = mb_substr($messageId, 0, 64);

            $exists = NotificationDelivery::query()
                ->where('event_key', 'email.inbound.received')
                ->where('channel', 'mail')
                ->where('notification_uuid', $messageId)
                ->exists();

            if ($exists) {
                continue;
            }

            $body = trim((string) imap_fetchbody($stream, (int) $messageNumber, '1'));
            if ($body === '') {
                $body = trim((string) imap_fetchbody($stream, (int) $messageNumber, '1.1'));
            }

            $subject = trim((string) ($overview->subject ?? '(No subject)'));
            $from = trim((string) ($overview->from ?? ''));
            $to = trim((string) ($overview->to ?? ''));

            $receivedAt = null;
            $rawDate = trim((string) ($overview->date ?? ''));
            if ($rawDate !== '') {
                try {
                    $receivedAt = now()->parse($rawDate);
                } catch (\Throwable) {
                    $receivedAt = now();
                }
            }

            NotificationDelivery::query()->create([
                'event_key' => 'email.inbound.received',
                'channel' => 'mail',
                'status' => 'sent',
                'notification_uuid' => $messageId,
                'recipient' => $to !== '' ? mb_substr($to, 0, 191) : null,
                'attempt_count' => 1,
                'sent_at' => $receivedAt ?? now(),
                'metadata' => [
                    'direction' => 'inbound',
                    'provider' => 'imap',
                    'from' => $from,
                    'to' => $to,
                    'subject' => $subject,
                    'messagePreview' => mb_substr($body, 0, 160),
                    'receivedAt' => optional($receivedAt)->toIso8601String(),
                ],
            ]);

            $saved++;
        }

        imap_close($stream);

        $this->info(sprintf('Inbox polling selesai. %d pesan baru disimpan.', $saved));

        return self::SUCCESS;
    }
}

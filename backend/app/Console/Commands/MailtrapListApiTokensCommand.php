<?php

namespace App\Console\Commands;

use App\Services\MailtrapAccountApiService;
use Illuminate\Console\Command;
use RuntimeException;

class MailtrapListApiTokensCommand extends Command
{
    protected $signature = 'mailtrap:tokens';

    protected $description = 'List Mailtrap API tokens visible by the configured token/account.';

    public function handle(MailtrapAccountApiService $service): int
    {
        try {
            $tokens = $service->listApiTokens();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $tokens) {
            $this->warn('No visible Mailtrap API tokens for this account/token scope.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            $rows[] = [
                'id' => (string) ($token['id'] ?? '-'),
                'name' => (string) ($token['name'] ?? '-'),
                'last4' => (string) ($token['last_4_digits'] ?? '-'),
                'expires_at' => (string) ($token['expires_at'] ?? '-'),
                'created_by' => (string) ($token['created_by'] ?? '-'),
            ];
        }

        $this->table(['ID', 'Name', 'Last4', 'Expires At', 'Created By'], $rows);

        return self::SUCCESS;
    }
}

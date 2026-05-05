<?php

namespace App\Console\Commands;

use App\Models\AiChatLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeExpiredAiChatLogs extends Command
{
    protected $signature = 'pdp:purge-ai-chat-logs {--days=} {--dry-run}';

    protected $description = 'Purge AI chat logs older than configured retention window (default 365 days).';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('pdp.retention.ai_chat_days', 365));
        if ($days <= 0) {
            $this->error('Retention days must be greater than 0.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $query = AiChatLog::withTrashed()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        $this->info('AI chat retention cutoff: '.$cutoff->toDateTimeString());
        $this->line('Matching rows: '.$count);

        if ($dryRun || $count === 0) {
            if ($dryRun) {
                $this->comment('Dry run enabled, no rows deleted.');
            }

            return self::SUCCESS;
        }

        $deleted = $query->forceDelete();
        $this->info('Purged rows: '.(int) $deleted);

        return self::SUCCESS;
    }
}

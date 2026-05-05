<?php

namespace App\Console\Commands;

use App\Models\ErasureRequest;
use Illuminate\Console\Command;

class PurgeCompletedErasures extends Command
{
    protected $signature = 'pdp:purge-completed-erasures
                            {--days=90 : Purge erasure request records older than this many days}';

    protected $description = 'Purge old completed erasure request records (UU PDP data minimization)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = ErasureRequest::query()
            ->where('status', 'completed')
            ->where('completed_at', '<', $cutoff)
            ->delete();

        $this->info("Purged {$count} completed erasure request(s) older than {$days} days.");

        return self::SUCCESS;
    }
}

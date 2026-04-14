<?php

namespace App\Console\Commands;

use App\Services\Wilayah\WilayahSyncService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;

class WilayahSyncCommand extends Command implements Isolatable
{
    public const NAME = 'wilayah:sync';

    protected $signature = self::NAME;

    protected $description = 'Sync Indonesian wilayah data from wilayah.id into local database tables.';

    public function handle(WilayahSyncService $syncService): int
    {
        try {
            $summary = $syncService->sync();
        } catch (Throwable $throwable) {
            $this->error('Wilayah sync failed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('Wilayah sync completed.');
        $this->line(sprintf(
            'provinces=%d regencies=%d districts=%d villages=%d',
            $summary['provinces'] ?? 0,
            $summary['regencies'] ?? 0,
            $summary['districts'] ?? 0,
            $summary['villages'] ?? 0
        ));
        $this->line('source='.$summary['source'].', syncedAt='.$summary['syncedAt']);

        return self::SUCCESS;
    }
}
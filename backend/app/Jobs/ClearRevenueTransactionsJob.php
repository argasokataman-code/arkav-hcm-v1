<?php

namespace App\Jobs;

use App\Models\PlatformRevenueTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClearRevenueTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $cutoff = now()->subDays(2);

        PlatformRevenueTransaction::query()
            ->where('status', PlatformRevenueTransaction::STATUS_POSTED)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_UNCLEARED)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('occurred_at')
                    ->orWhere('occurred_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $row->update([
                        'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
                        'clearing_date' => now()->toDateString(),
                    ]);
                }
            });
    }
}

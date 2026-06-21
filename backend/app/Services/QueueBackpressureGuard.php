<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueueBackpressureGuard
{
    /**
     * Check and record event throughput for the given channel.
     * Logs a WARNING when the rolling 1-minute event count exceeds $threshold.
     *
     * Does NOT throw — this is an observability guard, not a hard circuit-breaker.
     * Integrate monitoring alerts on 'tax_governance.queue_backpressure_alert' log channel.
     *
     * @param  string  $channel  Logical queue/listener channel name (e.g. 'revenue_capture').
     * @param  int  $threshold  Max events-per-minute before warning is emitted.
     */
    public function check(string $channel, int $threshold = 200): void
    {
        $windowKey = 'queue_bp:'.$channel.':'.now()->format('Y-m-d-H-i');

        $count = (int) Cache::get($windowKey, 0);

        Cache::put($windowKey, $count + 1, now()->addMinutes(2));

        if ($count >= $threshold) {
            Log::warning('tax_governance.queue_backpressure_alert', [
                'channel' => $channel,
                'window_events' => $count + 1,
                'threshold' => $threshold,
                'window_minute' => now()->format('Y-m-d H:i'),
            ]);
        }
    }
}

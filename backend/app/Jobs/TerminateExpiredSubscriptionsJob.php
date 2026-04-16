<?php

namespace App\Jobs;

use App\Services\SubscriptionTerminationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Auto-terminate subscriptions whose end_date has passed.
 *
 * This job runs on a schedule and marks subscriptions as 'expired'
 * when their end_date is in the past. It preserves the invoice
 * and payment history for audit purposes.
 *
 * Frequency: Run daily at midnight
 */
class TerminateExpiredSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $terminationService = app(SubscriptionTerminationService::class);

        Log::info('Starting TerminateExpiredSubscriptionsJob');

        $expiredSubscriptions = $terminationService->getExpiredSubscriptions();

        if ($expiredSubscriptions->isEmpty()) {
            Log::info('No expired subscriptions found');
            return;
        }

        Log::info("Found {$expiredSubscriptions->count()} expired subscription(s)");

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $terminationService->terminateExpiredSubscription(
                    $subscription,
                    'Auto-terminated: Subscription end_date expired'
                );

                Log::info("Successfully terminated expired subscription {$subscription->id}");
            } catch (\Exception $e) {
                Log::error("Failed to terminate subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("TerminateExpiredSubscriptionsJob completed");
    }
}

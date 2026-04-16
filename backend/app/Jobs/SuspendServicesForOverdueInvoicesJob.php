<?php

namespace App\Jobs;

use App\Services\SubscriptionTerminationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Auto-suspend subscriptions with overdue invoices.
 *
 * This job runs on a schedule and suspends services when:
 * - Invoice is not paid
 * - Invoice due_date is 7+ days in the past
 *
 * Companies are notified and have opportunity to pay to reactivate.
 *
 * Frequency: Run twice daily (morning + afternoon) to catch overdue invoices
 */
class SuspendServicesForOverdueInvoicesJob implements ShouldQueue
{
    use Queueable;

    private const GRACE_PERIOD_DAYS = 7;

    public function handle(): void
    {
        $terminationService = app(SubscriptionTerminationService::class);

        Log::info('Starting SuspendServicesForOverdueInvoicesJob', [
            'grace_period_days' => self::GRACE_PERIOD_DAYS,
        ]);

        $overdueInvoices = $terminationService->getSubscriptionsWithOverdueInvoices(
            self::GRACE_PERIOD_DAYS
        );

        if (empty($overdueInvoices)) {
            Log::info('No subscriptions with overdue invoices found');
            return;
        }

        Log::info("Found " . count($overdueInvoices) . " subscription(s) with overdue invoice(s)");

        foreach ($overdueInvoices as [$subscription, $invoice]) {
            try {
                $terminationService->suspendDueToOverdueInvoice($subscription, $invoice);

                Log::info("Successfully suspended subscription {$subscription->id} for overdue invoice {$invoice->id}");
            } catch (\Exception $e) {
                Log::error("Failed to suspend subscription {$subscription->id}", [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("SuspendServicesForOverdueInvoicesJob completed");
    }
}

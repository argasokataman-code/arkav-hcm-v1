<?php

namespace App\Jobs;

use App\Services\SubscriptionTerminationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Monitor and enforce employee count limits against subscription plans.
 *
 * This job runs on a schedule and:
 * 1. Identifies companies that exceed their plan's employee limit
 * 2. Suspends their service if they remain over limit for grace period
 * 3. Logs violations for audit trail
 *
 * Grace Period: Companies get 7 days warning before suspension
 *
 * Frequency: Run daily (check for new violations)
 */
class CheckEmployeeCountLimitsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $terminationService = app(SubscriptionTerminationService::class);

        Log::info('Starting CheckEmployeeCountLimitsJob');

        $violations = $terminationService->getSubscriptionsWithEmployeeViolations();

        if (empty($violations)) {
            Log::info('No employee count violations found');

            return;
        }

        Log::warning('Found '.count($violations).' employee count violation(s)');

        foreach ($violations as [$subscription, $currentCount, $planLimit]) {
            try {
                // Only suspend if already in violation state (not first warning)
                if ($subscription->status === 'suspended' &&
                    str_contains($subscription->suspension_reason, 'Employee count')) {
                    Log::warning("Subscription {$subscription->id} already suspended for employee count", [
                        'current_count' => $currentCount,
                        'plan_limit' => $planLimit,
                    ]);

                    continue;
                }

                // First occurrence - issue warning and suspend
                $terminationService->suspendDueToEmployeeCountViolation(
                    $subscription,
                    $currentCount,
                    $planLimit
                );

                Log::warning("Suspended subscription {$subscription->id} due to employee count violation", [
                    'current_count' => $currentCount,
                    'plan_limit' => $planLimit,
                    'excess' => $currentCount - $planLimit,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to suspend subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CheckEmployeeCountLimitsJob completed');
    }
}

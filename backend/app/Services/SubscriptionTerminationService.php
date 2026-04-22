<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles subscription termination and service deactivation
 * - Auto-terminates expired subscriptions
 * - Suspends services on overdue invoices
 * - Handles employee count violations
 */
class SubscriptionTerminationService
{
    /**
     * Terminate subscription due to expiration.
     * Status becomes 'expired', invoice remains for audit.
     */
    public function terminateExpiredSubscription(Subscription $subscription, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($subscription, $reason) {
            if ($reason === null) {
                $reason = $subscription->status === 'pending_payment'
                    ? 'Provisioning window ended without payment'
                    : 'Subscription end_date expired';
            }

            Log::info('Terminating subscription', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'reason' => $reason,
                'ended_at' => $subscription->ends_at,
            ]);

            $subscription->update([
                'status' => 'expired',
                'terminated_at' => now(),
                'termination_reason' => $reason,
            ]);

            // Notify company of termination
            $this->notifySubscriptionTerminated($subscription, 'expiration');

            return true;
        });
    }

    /**
     * Suspend service due to overdue invoice (payment not received).
     * Status becomes 'suspended', not deleted.
     */
    public function suspendDueToOverdueInvoice(Subscription $subscription, Invoice $invoice): bool
    {
        return DB::transaction(function () use ($subscription, $invoice) {
            $daysOverdue = $invoice->due_date ? $invoice->due_date->diffInDays(now()) : 0;

            Log::warning('Suspending subscription due to overdue invoice', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'days_overdue' => $daysOverdue,
                'due_date' => $invoice->due_date,
            ]);

            $subscription->update([
                'status' => 'suspended',
                'suspension_reason' => "Invoice {$invoice->invoice_number} overdue by {$daysOverdue} days",
                'suspended_at' => now(),
            ]);

            // Notify company immediately
            $this->notifySubscriptionSuspended($subscription, $invoice);

            return true;
        });
    }

    /**
     * Suspend due to employee count violation.
     */
    public function suspendDueToEmployeeCountViolation(
        Subscription $subscription,
        int $currentCount,
        int $planLimit
    ): bool {
        return DB::transaction(function () use ($subscription, $currentCount, $planLimit) {
            $excess = $currentCount - $planLimit;

            Log::warning('Suspending subscription due to employee count violation', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'current_employee_count' => $currentCount,
                'plan_limit' => $planLimit,
                'excess_employees' => $excess,
            ]);

            $subscription->update([
                'status' => 'suspended',
                'suspension_reason' => "Employee count ({$currentCount}) exceeds plan limit ({$planLimit}) by {$excess}",
                'suspended_at' => now(),
            ]);

            // Notify company with grace period for correction
            $this->notifyEmployeeCountViolation($subscription, $currentCount, $planLimit);

            return true;
        });
    }

    /**
     * Reactivate suspended subscription after issue is resolved.
     */
    public function reactivateSuspended(Subscription $subscription, string $reason): bool
    {
        return DB::transaction(function () use ($subscription, $reason) {
            if ($subscription->status !== 'suspended') {
                Log::notice('Cannot reactivate non-suspended subscription', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ]);

                return false;
            }

            Log::info('Reactivating suspended subscription', [
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'reason' => $reason,
            ]);

            $subscription->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $this->notifySubscriptionReactivated($subscription);

            return true;
        });
    }

    /**
     * Get subscriptions that should be terminated.
     */
    public function getExpiredSubscriptions(?CarbonInterface $as = null): \Illuminate\Database\Eloquent\Collection
    {
        $date = $as ?? now();

        return Subscription::query()
            ->where(function ($q) use ($date): void {
                $q->where(function ($q2) use ($date): void {
                    $q2->whereIn('status', ['active', 'trial'])
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<', $date);
                })->orWhere(function ($q2) use ($date): void {
                    $q2->where('status', 'pending_payment')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<', $date);
                });
            })
            ->get();
    }

    /**
     * Get subscriptions with overdue invoices (1+ day past due by default).
     * Returns array of [subscription, invoice] pairs.
     */
    public function getSubscriptionsWithOverdueInvoices(int $graceDays = 1): array
    {
        $invoices = Invoice::query()
            ->where('is_paid', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->subDays($graceDays))
            ->get();

        $result = [];

        foreach ($invoices as $invoice) {
            $subscription = Subscription::query()
                ->where('company_id', $invoice->company_id)
                ->whereIn('status', ['active', 'trial'])
                ->first();

            if ($subscription) {
                $result[] = [$subscription, $invoice];
            }
        }

        return $result;
    }

    /**
     * Check and get subscriptions with employee count violations.
     * Returns array of [subscription, currentCount, planLimit] tuples.
     */
    public function getSubscriptionsWithEmployeeViolations(): array
    {
        $result = [];

        // Get active subscriptions with employee limits
        $subscriptions = Subscription::query()
            ->with('package.features')
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->package) {
                continue;
            }

            $employeeFeature = $subscription->package->features
                ->firstWhere('feature_code', 'max_employees');

            if (!$employeeFeature || $employeeFeature->limit === null || $employeeFeature->limit === 0) {
                // No employee limit on this plan
                continue;
            }

            $currentCount = \App\Models\EmployeeProfile::query()
                ->where('company_id', $subscription->company_id)
                ->where('employment_status', '!=', 'terminated')
                ->count();

            if ($currentCount > $employeeFeature->limit) {
                $result[] = [$subscription, $currentCount, $employeeFeature->limit];
            }
        }

        return $result;
    }

    // ============ NOTIFICATION METHODS ============

    private function notifySubscriptionTerminated(Subscription $subscription, string $reason): void
    {
        try {
            // TODO: Send email to company admin
            // $subscription->company->notifySubscriptionTerminated($reason);
            Log::info('Subscription termination notification would be sent', [
                'company_id' => $subscription->company_id,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify subscription termination', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifySubscriptionSuspended(Subscription $subscription, Invoice $invoice): void
    {
        try {
            // TODO: Send urgent email to company admin
            // Subject: "⚠️ Service Suspended - Invoice {$invoice->invoice_number} Overdue"
            // Body: "Your service will be automatically suspended in 3 days if payment is not received"
            Log::info('Service suspension notification would be sent', [
                'company_id' => $subscription->company_id,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify service suspension', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyEmployeeCountViolation(
        Subscription $subscription,
        int $currentCount,
        int $planLimit
    ): void {
        try {
            // TODO: Send warning email to company admin
            // Subject: "⚠️ Employee Count Violation - Immediate Action Required"
            // Body: "You have {$currentCount} employees but plan allows {$planLimit}.
            //        Please reduce employee count within 7 days or upgrade plan.
            //        Service will be suspended if not resolved."
            Log::info('Employee count violation notification would be sent', [
                'company_id' => $subscription->company_id,
                'current_count' => $currentCount,
                'plan_limit' => $planLimit,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify employee count violation', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifySubscriptionReactivated(Subscription $subscription): void
    {
        try {
            // TODO: Send confirmation email
            Log::info('Service reactivation notification would be sent', [
                'company_id' => $subscription->company_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify service reactivation', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

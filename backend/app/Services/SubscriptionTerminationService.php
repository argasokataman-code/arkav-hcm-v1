<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
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
    public function __construct(private readonly CompanyStatusSynchronizer $companyStatusSynchronizer) {}

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

            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

            // Notify company of termination
            $this->notifySubscriptionTerminated($subscription, 'expiration');

            return true;
        });
    }

    /**
     * Inactivate service due to overdue invoice (payment not received).
     * Billing delinquency should not be classified as suspended enforcement.
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
                'status' => 'inactive',
                'suspension_reason' => "Invoice {$invoice->invoice_number} overdue by {$daysOverdue} days",
                'suspended_at' => now(),
            ]);

            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

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

            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

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

            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

            $this->notifySubscriptionReactivated($subscription);

            return true;
        });
    }

    /**
     * Get subscriptions that should be terminated.
     */
    public function getExpiredSubscriptions(?CarbonInterface $as = null): Collection
    {
        $date = $as ?? now();

        return Subscription::query()
            ->select(['id', 'company_id', 'uuid', 'status', 'ends_at', 'auto_renew', 'billing_cycle', 'amount', 'package_uuid', 'plan_code', 'metadata', 'starts_at', 'created_at', 'company_uuid'])
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
            ->select(['id', 'company_id', 'uuid', 'invoice_number', 'amount_due', 'due_date', 'is_paid', 'status', 'subscription_id'])
            ->where('is_paid', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->subDays($graceDays))
            ->get();

        $companyIds = $invoices->pluck('company_id')->unique()->filter()->values()->all();
        if ($companyIds === []) {
            return [];
        }

        $subscriptions = Subscription::query()
            ->select(['id', 'company_id', 'uuid', 'status', 'ends_at', 'auto_renew', 'billing_cycle', 'amount', 'package_uuid', 'plan_code', 'metadata', 'starts_at', 'created_at', 'company_uuid'])
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', ['active', 'trial'])
            ->get()
            ->keyBy('company_id');

        $result = [];

        foreach ($invoices as $invoice) {
            $subscription = $subscriptions->get($invoice->company_id);

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
            ->select(['id', 'company_id', 'uuid', 'status', 'package_uuid', 'plan_code', 'auto_renew', 'ends_at', 'billing_cycle', 'amount', 'starts_at', 'created_at', 'metadata', 'company_uuid'])
            ->with([
                'package' => fn ($q) => $q->select(['uuid', 'name', 'monthly_price', 'yearly_price']),
                'package.features' => fn ($q) => $q->select(['package_uuid', 'feature_code', 'limit']),
            ])
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->package) {
                continue;
            }

            $employeeFeature = $subscription->package->features
                ->firstWhere('feature_code', 'max_employees');

            if (! $employeeFeature || $employeeFeature->limit === null || $employeeFeature->limit === 0) {
                // No employee limit on this plan
                continue;
            }

            $currentCount = EmployeeProfile::query()
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
            app(NotificationService::class)->notifySubscriptionReactivated(
                $subscription->fresh(['company', 'package'])
            );
        } catch (\Exception $e) {
            Log::error('Failed to notify service reactivation', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

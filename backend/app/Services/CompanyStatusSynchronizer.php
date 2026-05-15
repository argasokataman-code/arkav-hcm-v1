<?php

namespace App\Services;

use App\Models\Subscription;

class CompanyStatusSynchronizer
{
    public function syncFromSubscription(Subscription $subscription): void
    {
        $company = $subscription->company()->first();

        if (! $company) {
            return;
        }

        $targetStatus = $this->mapSubscriptionStatusToCompanyStatus((string) $subscription->status);

        if ((string) $company->status === $targetStatus) {
            return;
        }

        $company->forceFill([
            'status' => $targetStatus,
        ])->save();
    }

    public function mapSubscriptionStatusToCompanyStatus(string $subscriptionStatus): string
    {
        return match ($subscriptionStatus) {
            'active', 'trial', 'grace_period', 'pending_payment' => 'active',
            'suspended' => 'suspended',
            'inactive', 'expired', 'cancelled' => 'inactive',
            default => 'inactive',
        };
    }
}
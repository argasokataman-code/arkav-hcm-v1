<?php

namespace Tests\Feature;

use App\Events\AddonPurchased;
use App\Events\SubscriptionCreated;
use App\Models\Company;
use App\Models\Package;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TaxGovernanceRevenueCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_created_event_captures_revenue_once(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'amount' => 250000,
            'status' => 'active',
        ]);

        SubscriptionCreated::dispatch((int) $subscription->id, null);
        SubscriptionCreated::dispatch((int) $subscription->id, null);

        $this->assertDatabaseCount('platform_revenue_transactions', 1);
        $this->assertDatabaseHas('platform_revenue_transactions', [
            'company_id' => $company->id,
            'source_event_type' => 'subscription.created',
            'source_entity_id' => $subscription->id,
            'transaction_type' => 'subscription',
            'idempotency_key' => 'subscription_created:' . $subscription->id,
        ]);
    }

    public function test_subscription_duplicate_dispatch_emits_observability_log(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'amount' => 175000,
            'status' => 'active',
        ]);

        Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(function (string $message, array $context) use ($company, $subscription): bool {
                return $message === 'tax_governance.revenue_capture_duplicate_skipped'
                    && ($context['source_event_type'] ?? null) === 'subscription.created'
                    && ($context['idempotency_key'] ?? null) === 'subscription_created:' . $subscription->id
                    && (int) ($context['company_id'] ?? 0) === (int) $company->id
                    && (int) ($context['source_entity_id'] ?? 0) === (int) $subscription->id;
            });

        SubscriptionCreated::dispatch((int) $subscription->id, null);
        SubscriptionCreated::dispatch((int) $subscription->id, null);

        $this->assertDatabaseCount('platform_revenue_transactions', 1);
    }

    public function test_addon_purchased_event_captures_revenue_once(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
        ]);

        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'transaction_type' => 'addon',
            'description' => 'Addon purchase',
            'amount' => 50000,
            'tax_amount' => 5000,
            'discount_amount' => 0,
            'total_amount' => 55000,
            'status' => 'paid',
        ]);

        AddonPurchased::dispatch((int) $transaction->id, null);
        AddonPurchased::dispatch((int) $transaction->id, null);

        $this->assertDatabaseCount('platform_revenue_transactions', 1);
        $this->assertDatabaseHas('platform_revenue_transactions', [
            'company_id' => $company->id,
            'source_event_type' => 'addon.purchased',
            'source_entity_id' => $transaction->id,
            'transaction_type' => 'addon_feature',
            'idempotency_key' => 'addon_purchased:' . $transaction->id,
        ]);
    }
}

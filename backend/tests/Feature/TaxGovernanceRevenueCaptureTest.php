<?php

namespace Tests\Feature;

use App\Events\AddonPurchased;
use App\Events\PayrollFinalized;
use App\Events\SubscriptionCreated;
use App\Models\Company;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\Package;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\User;
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

    public function test_payroll_finalized_event_does_not_capture_revenue_when_service_fee_zero(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 4,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $run = HcmPayrollRun::query()->create([
            'company_id' => $company->id,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
            'meta' => [
                'platform_service_fee_amount' => 0,
            ],
        ]);

        HcmPayrollLine::query()->create([
            'company_id' => $company->id,
            'hcm_payroll_run_id' => $run->id,
            'user_id' => $user->id,
            'kind' => 'earning',
            'category' => 'base',
            'amount' => 4000000,
            'component_code' => 'BASIC',
            'component_name' => 'Basic Salary',
            'sort_order' => 1,
        ]);

        PayrollFinalized::dispatch((int) $run->id, null);
        PayrollFinalized::dispatch((int) $run->id, null);

        $this->assertDatabaseCount('platform_revenue_transactions', 0);
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

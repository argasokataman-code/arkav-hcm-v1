<?php

namespace Tests\Feature;

use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Jobs\ApplySubscriptionChangeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HcmAddonPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private function mockUserUuid(): string
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'test-'.time().'-'.rand(100,999).'@example.com',
            'password' => bcrypt('password'),
        ])->uuid;
    }

    #[Test]
    public function downgrade_preserves_compatible_addon_in_subscription_amount(): void
    {
        $company = $this->createIsolatedTestCompany();

        $fromPackage = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        $toPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $fromPackage->uuid,
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
            'auto_renew' => true,
        ]);

        // Create paid addon on this subscription
        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'package_addon_id' => $addon->id,
            'transaction_type' => 'addon',
            'description' => 'Asset Management addon',
            'amount' => 49000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 49000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $metadata = ['addon_applied_transaction_ids' => [$transaction->id], 'addon_recurring_total' => 49000];
        $subscription->update([
            'amount' => 1348000,
            'metadata' => $metadata,
        ]);

        // Create change request (downgrade)
        $changeRequest = HcmSubscriptionChangeRequest::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_uuid' => $company->uuid,
            'user_uuid' => $this->mockUserUuid(),
            'current_subscription_uuid' => $subscription->uuid,
            'from_package_uuid' => $fromPackage->uuid,
            'to_package_uuid' => $toPackage->uuid,
            'action' => HcmSubscriptionChangeRequest::ACTION_DOWNGRADE,
            'status' => HcmSubscriptionChangeRequest::STATUS_APPROVED,
            'effective_at' => now(),
        ]);

        $job = new ApplySubscriptionChangeJob($changeRequest->id);
        $job->handle();

        $subscription->refresh();

        $this->assertSame('pending_payment', $subscription->status);
        $this->assertSame($toPackage->uuid, $subscription->package_uuid);
        $this->assertSame(248000.0, (float) $subscription->amount,
            'Downgrade should include compatible addon amount');
    }

    #[Test]
    public function downgrade_skips_incompatible_addon_from_subscription_amount(): void
    {
        $company = $this->createIsolatedTestCompany();

        $fromPackage = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        $toPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        // Assign addon to fromPackage only (not toPackage)
        $addon = PackageAddon::query()->create([
            'code' => 'performance',
            'name' => 'Performance Review',
            'price_per_unit' => 79000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        DB::table('package_addon_assignments')->insert([
            'package_uuid' => $fromPackage->uuid,
            'package_addon_id' => $addon->id,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $fromPackage->uuid,
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
            'auto_renew' => true,
        ]);

        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'package_addon_id' => $addon->id,
            'transaction_type' => 'addon',
            'description' => 'Performance addon',
            'amount' => 79000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 79000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $metadata = ['addon_applied_transaction_ids' => [$transaction->id], 'addon_recurring_total' => 79000];
        $subscription->update([
            'amount' => 1378000,
            'metadata' => $metadata,
        ]);

        $changeRequest = HcmSubscriptionChangeRequest::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_uuid' => $company->uuid,
            'user_uuid' => $this->mockUserUuid(),
            'current_subscription_uuid' => $subscription->uuid,
            'from_package_uuid' => $fromPackage->uuid,
            'to_package_uuid' => $toPackage->uuid,
            'action' => HcmSubscriptionChangeRequest::ACTION_DOWNGRADE,
            'status' => HcmSubscriptionChangeRequest::STATUS_APPROVED,
            'effective_at' => now(),
        ]);

        $job = new ApplySubscriptionChangeJob($changeRequest->id);
        $job->handle();

        $subscription->refresh();

        $this->assertSame('pending_payment', $subscription->status);
        // Addon (79000) is NOT built-in to Starter → stays as paid addon in recurring
        $this->assertSame(278000.0, (float) $subscription->amount,
            'Downgrade should keep paid addon not built-in to target plan');
    }

    #[Test]
    public function upgrade_handles_addon_carry_over(): void
    {
        $company = $this->createIsolatedTestCompany();

        $fromPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        $toPackage = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $fromPackage->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => true,
        ]);

        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'package_addon_id' => $addon->id,
            'transaction_type' => 'addon',
            'description' => 'Asset Management addon',
            'amount' => 49000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 49000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $metadata = ['addon_applied_transaction_ids' => [$transaction->id], 'addon_recurring_total' => 49000];
        $subscription->update([
            'amount' => 248000,
            'metadata' => $metadata,
        ]);

        $changeRequest = HcmSubscriptionChangeRequest::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_uuid' => $company->uuid,
            'user_uuid' => $this->mockUserUuid(),
            'current_subscription_uuid' => $subscription->uuid,
            'from_package_uuid' => $fromPackage->uuid,
            'to_package_uuid' => $toPackage->uuid,
            'action' => HcmSubscriptionChangeRequest::ACTION_UPGRADE,
            'status' => HcmSubscriptionChangeRequest::STATUS_APPROVED,
            'effective_at' => now(),
        ]);

        $job = new ApplySubscriptionChangeJob($changeRequest->id);
        $job->handle();

        $subscription->refresh();

        $this->assertSame($toPackage->uuid, $subscription->package_uuid);
        $this->assertSame(1348000.0, (float) $subscription->amount,
            'Upgrade should carry over addon amount');
        $this->assertSame('active', $subscription->status,
            'Upgrade should keep subscription active');
    }

    #[Test]
    public function upgrade_removes_built_in_addon_from_recurring(): void
    {
        $company = $this->createIsolatedTestCompany();

        $fromPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        $toPackage = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        // Addon is BUILT-IN to enterprise package
        DB::table('package_addon_assignments')->insert([
            'package_uuid' => $toPackage->uuid,
            'package_addon_id' => $addon->id,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $fromPackage->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => true,
        ]);

        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'package_addon_id' => $addon->id,
            'transaction_type' => 'addon',
            'description' => 'Asset Management addon',
            'amount' => 49000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 49000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $metadata = ['addon_applied_transaction_ids' => [$transaction->id], 'addon_recurring_total' => 49000];
        $subscription->update([
            'amount' => 248000,
            'metadata' => $metadata,
        ]);

        $changeRequest = HcmSubscriptionChangeRequest::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_uuid' => $company->uuid,
            'user_uuid' => $this->mockUserUuid(),
            'current_subscription_uuid' => $subscription->uuid,
            'from_package_uuid' => $fromPackage->uuid,
            'to_package_uuid' => $toPackage->uuid,
            'action' => HcmSubscriptionChangeRequest::ACTION_UPGRADE,
            'status' => HcmSubscriptionChangeRequest::STATUS_APPROVED,
            'effective_at' => now(),
        ]);

        $job = new ApplySubscriptionChangeJob($changeRequest->id);
        $job->handle();

        $subscription->refresh();

        $this->assertSame(1299000.0, (float) $subscription->amount,
            'Built-in addon should be removed from recurring on upgrade');
        $this->assertSame('active', $subscription->status);
    }
}

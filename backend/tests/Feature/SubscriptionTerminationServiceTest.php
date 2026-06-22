<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionTerminationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubscriptionTerminationServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $queryCount = 0;

    private function startQueryTracking(): void
    {
        $this->queryCount = 0;
        DB::listen(function ($query): void {
            ++$this->queryCount;
        });
    }

    private function assertQueryCountLessThan(int $max, string $label = 'dispatch'): void
    {
        $this->assertLessThanOrEqual(
            $max,
            $this->queryCount,
            "Query count exceeded limit for {$label}. Expected ≤{$max}, got {$this->queryCount}. Consider adding ->select(...) to queries in this code path."
        );
    }

    // ===== TERMINATE =====

    public function test_terminate_expired_subscription_changes_status_and_syncs_company(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $result = $service->terminateExpiredSubscription($subscription, 'Test expiration');
        $this->assertQueryCountLessThan(10, 'terminate_expired');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertNotNull($subscription->terminated_at);
        $this->assertSame('Test expiration', $subscription->termination_reason);
    }

    public function test_terminate_expired_subscription_with_null_reason_uses_default(): void
    {
        Log::spy();

        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $result = $service->terminateExpiredSubscription($subscription);
        $this->assertQueryCountLessThan(10, 'terminate_expired_default_reason');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertStringContainsString('end_date expired', (string) $subscription->termination_reason);
    }

    // ===== SUSPEND OVERDUE =====

    public function test_suspend_due_to_overdue_invoice(): void
    {
        Log::spy();

        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'due_date' => now()->subDays(5),
            'is_paid' => false,
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $result = $service->suspendDueToOverdueInvoice($subscription, $invoice);
        $this->assertQueryCountLessThan(10, 'suspend_overdue_invoice');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertSame('inactive', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
        $this->assertStringContainsString((string) $invoice->invoice_number, (string) $subscription->suspension_reason);
    }

    // ===== SUSPEND EMPLOYEE COUNT =====

    public function test_suspend_due_to_employee_count_violation(): void
    {
        Log::spy();

        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $result = $service->suspendDueToEmployeeCountViolation($subscription, 15, 10);
        $this->assertQueryCountLessThan(10, 'suspend_employee_violation');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
        $this->assertStringContainsString('exceeds plan limit', (string) $subscription->suspension_reason);
    }

    // ===== REACTIVATE =====

    public function test_reactivate_suspended_subscription(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'suspended',
            'suspended_at' => now()->subDays(2),
            'suspension_reason' => 'Overdue invoice',
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $result = $service->reactivateSuspended($subscription, 'Payment received');
        $this->assertQueryCountLessThan(10, 'reactivate_suspended');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->suspended_at);
        $this->assertNull($subscription->suspension_reason);
    }

    public function test_reactivate_non_suspended_returns_false(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        $service = app(SubscriptionTerminationService::class);

        $result = $service->reactivateSuspended($subscription, 'Should not work');

        $this->assertFalse($result);
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
    }

    // ===== READ METHODS (SELECT * HOTSPOTS) =====

    public function test_get_expired_subscriptions_returns_expired_ones(): void
    {
        $this->seedExpiredFixture();

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $expired = $service->getExpiredSubscriptions();
        $this->assertQueryCountLessThan(5, 'get_expired_subscriptions');

        $this->assertCount(1, $expired);
        $this->assertSame('expired_company', $expired->first()->company->code);
    }

    public function test_get_expired_subscriptions_with_pending_payment(): void
    {
        $company = Company::factory()->create(['code' => 'pending_pay', 'status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'pending_payment',
            'ends_at' => now()->subDay(),
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $expired = $service->getExpiredSubscriptions();
        $this->assertQueryCountLessThan(5, 'get_expired_pending_payment');

        $this->assertCount(1, $expired);
    }

    public function test_get_subscriptions_with_overdue_invoices(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'due_date' => now()->subDays(5),
            'is_paid' => false,
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $overdue = $service->getSubscriptionsWithOverdueInvoices(1);
        $this->assertQueryCountLessThan(10, 'get_overdue_invoices');

        $this->assertCount(1, $overdue);
        [$returnedSub, $returnedInvoice] = $overdue[0];
        $this->assertSame($subscription->id, $returnedSub->id);
        $this->assertFalse($returnedInvoice->is_paid);
    }

    public function test_get_subscriptions_with_overdue_invoices_skips_paid(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        // Overdue but paid — must be excluded
        Invoice::factory()->create([
            'company_id' => $company->id,
            'due_date' => now()->subDays(5),
            'is_paid' => true,
        ]);

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $overdue = $service->getSubscriptionsWithOverdueInvoices(1);
        $this->assertQueryCountLessThan(5, 'get_overdue_invoices_skips_paid');

        $this->assertCount(0, $overdue);
    }

    public function test_get_subscriptions_with_employee_violations(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        \App\Models\PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Max Employees',
            'limit' => 5,
        ]);

        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        // Create 8 non-terminated employees
        User::factory(8)->create()->each(function ($user) use ($company): void {
            \App\Models\EmployeeProfile::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $user->id,
                'employment_status' => 'permanent',
            ]);
        });

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $violations = $service->getSubscriptionsWithEmployeeViolations();
        $this->assertQueryCountLessThan(15, 'get_employee_violations');

        $this->assertCount(1, $violations);
        [$violatedSub, $currentCount, $planLimit] = $violations[0];
        $this->assertSame($subscription->id, $violatedSub->id);
        $this->assertSame(8, $currentCount);
        $this->assertSame(5, $planLimit);
    }

    public function test_get_subscriptions_with_employee_violations_skips_within_limit(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);
        \App\Models\PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Max Employees',
            'limit' => 20,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
        ]);

        // Create 5 non-terminated employees (within limit of 20)
        User::factory(5)->create()->each(function ($user) use ($company): void {
            \App\Models\EmployeeProfile::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'company_id' => $company->id,
                'user_id' => $user->id,
                'employment_status' => 'permanent',
            ]);
        });

        $service = app(SubscriptionTerminationService::class);

        $this->startQueryTracking();
        $violations = $service->getSubscriptionsWithEmployeeViolations();
        $this->assertQueryCountLessThan(10, 'get_employee_violations_skips_within_limit');

        $this->assertCount(0, $violations);
    }

    // ===== HELPERS =====

    private function seedExpiredFixture(): void
    {
        $company = Company::factory()->create(['code' => 'expired_company', 'status' => 'active']);
        $package = Package::factory()->create(['status' => 'active']);

        // Active subscription that has ended — should be returned
        Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'ends_at' => now()->subDay(),
        ]);

        // Active subscription still within validity — should NOT be returned
        Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'ends_at' => now()->addMonth(),
        ]);
    }
}

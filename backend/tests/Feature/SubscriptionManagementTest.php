<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Services\EmployeeCountValidator;
use App\Services\SubscriptionTerminationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionTerminationService $terminationService;
    private EmployeeCountValidator $employeeValidator;
    private Company $company;
    private Package $package;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->terminationService = app(SubscriptionTerminationService::class);
        $this->employeeValidator = app(EmployeeCountValidator::class);

        // Create test company
        $this->company = Company::factory()->create(['name' => 'Test Corp']);

        // Create test package with employee limit
        $this->package = Package::create([
            'name' => 'Pro Plan',
            'code' => 'pro',
            'monthly_price' => 100.00,
            'yearly_price' => 1000.00,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        // Add employee limit feature
        PackageFeature::create([
            'package_id' => $this->package->id,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 5, // 5 employee limit for testing
        ]);

        // Create active subscription
        $this->subscription = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'active',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->addMonths(9),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);
    }

    // ============ TERMINATION TESTS ============

    public function test_terminate_expired_subscription(): void
    {
        // Create expired subscription
        $expiredSub = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'active',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDays(1), // Expired
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);

        $this->assertEquals('active', $expiredSub->status);

        // Terminate it
        $result = $this->terminationService->terminateExpiredSubscription($expiredSub);

        $this->assertTrue($result);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $expiredSub->id,
            'status' => 'expired',
        ]);
    }

    public function test_get_expired_subscriptions(): void
    {
        // Create multiple subscriptions with different statuses
        $expired1 = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'active',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subDays(2),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);

        $expired2 = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'trial',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subDays(1),
            'trial_ends_at' => now()->subDays(1),
            'billing_cycle' => 'monthly',
            'amount' => 0.00,
        ]);

        // This one is not expired
        $active = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'active',
            'starts_at' => now()->subMonths(1),
            'ends_at' => now()->addMonths(11),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);

        $expiredSubs = $this->terminationService->getExpiredSubscriptions();

        $this->assertCount(2, $expiredSubs); // The 2 expired ones (not the active one from setUp)
        $this->assertTrue($expiredSubs->contains($expired1));
        $this->assertTrue($expiredSubs->contains($expired2));
        $this->assertFalse($expiredSubs->contains($active));
    }

    public function test_suspend_due_to_overdue_invoice(): void
    {
        // Create unpaid invoice with past due date
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-2026-04-001',
            'is_paid' => false,
            'issue_date' => now()->subDays(30),
            'due_date' => now()->subDays(10), // 10 days overdue
            'amount_due' => 100.00,
            'status' => 'sent',
        ]);

        $this->assertEquals('active', $this->subscription->status);

        // Suspend due to overdue
        $result = $this->terminationService->suspendDueToOverdueInvoice($this->subscription, $invoice);

        $this->assertTrue($result);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'suspended',
        ]);

        // Check suspension reason contains invoice number
        $updatedSub = $this->subscription->fresh();
        $this->assertStringContainsString('INV-2026-04-001', $updatedSub->suspension_reason);
    }

    public function test_get_subscriptions_with_overdue_invoices(): void
    {
        // Create overdue invoice (7+ days late)
        $overdueInvoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-2026-04-002',
            'is_paid' => false,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(8),
            'amount_due' => 100.00,
            'status' => 'sent',
        ]);

        // Create recent unpaid invoice (not yet 7 days overdue)
        $recentInvoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-2026-04-003',
            'is_paid' => false,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->subDays(3),
            'amount_due' => 100.00,
            'status' => 'sent',
        ]);

        $overdueList = $this->terminationService->getSubscriptionsWithOverdueInvoices(graceDays: 7);

        $this->assertCount(1, $overdueList); // Only the 8-days-overdue one
        [$sub, $invoice] = $overdueList[0];
        $this->assertEquals($this->subscription->id, $sub->id);
        $this->assertEquals('INV-2026-04-002', $invoice->invoice_number);
    }

    // ============ EMPLOYEE COUNT VALIDATION TESTS ============

    public function test_employee_count_validator_allows_within_limit(): void
    {
        // Create 3 employees (limit is 5)
        for ($i = 0; $i < 3; $i++) {
            $user = \App\Models\User::create([
                'name' => "Employee {$i}",
                'email' => "emp{$i}@test.com",
                'password' => bcrypt('password'),
            ]);
            EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'nik' => "12345{$i}",
                'hire_date' => now(),
                'employment_status' => 'active',
            ]);
        }

        $result = $this->employeeValidator->canAddEmployees($this->company, countToAdd: 2);

        $this->assertTrue($result['canAdd']);
        $this->assertEquals(2, $result['remaining']);
        $this->assertEquals(5, $result['limit']);
    }

    public function test_employee_count_validator_rejects_exceeding_limit(): void
    {
        // Create 4 employees (limit is 5)
        for ($i = 0; $i < 4; $i++) {
            $user = \App\Models\User::create([
                'name' => "Employee {$i}",
                'email' => "emp{$i}@test2.com",
                'password' => bcrypt('password'),
            ]);
            EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'nik' => "54321{$i}",
                'hire_date' => now(),
                'employment_status' => 'active',
            ]);
        }

        $result = $this->employeeValidator->canAddEmployees($this->company, countToAdd: 2);

        $this->assertFalse($result['canAdd']);
        $this->assertEquals(1, $result['remaining']);
        $this->assertEquals(5, $result['limit']);
    }

    public function test_employee_violation_detection(): void
    {
        // Create 6 employees (exceeds limit of 5)
        for ($i = 0; $i < 6; $i++) {
            $user = \App\Models\User::create([
                'name' => "Employee {$i}",
                'email' => "emp{$i}@test3.com",
                'password' => bcrypt('password'),
            ]);
            EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'nik' => "99999{$i}",
                'hire_date' => now(),
                'employment_status' => 'active',
            ]);
        }

        $violations = $this->terminationService->getSubscriptionsWithEmployeeViolations();

        $this->assertNotEmpty($violations);
        [$violatingSub, $currentCount, $planLimit] = $violations[0];
        $this->assertEquals($this->subscription->id, $violatingSub->id);
        $this->assertEquals(6, $currentCount);
        $this->assertEquals(5, $planLimit);
    }

    public function test_suspend_due_to_employee_count_violation(): void
    {
        $result = $this->terminationService->suspendDueToEmployeeCountViolation(
            $this->subscription,
            currentCount: 7,
            planLimit: 5
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'suspended',
        ]);

        $updatedSub = $this->subscription->fresh();
        $this->assertStringContainsString('exceeds plan limit', $updatedSub->suspension_reason);
        $this->assertStringContainsString('7', $updatedSub->suspension_reason);
        $this->assertStringContainsString('5', $updatedSub->suspension_reason);
    }

    public function test_reactivate_suspended_subscription(): void
    {
        // Suspend the subscription first
        $this->subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Test suspension',
        ]);

        // Reactivate it
        $result = $this->terminationService->reactivateSuspended(
            $this->subscription,
            'Payment received'
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    public function test_cannot_reactivate_non_suspended_subscription(): void
    {
        // Subscription is already active
        $this->assertEquals('active', $this->subscription->status);

        $result = $this->terminationService->reactivateSuspended(
            $this->subscription,
            'Not applicable'
        );

        $this->assertFalse($result);
    }

    public function test_employee_count_validator_allows_unlimited(): void
    {
        // Remove employee limit
        PackageFeature::where('package_id', $this->package->id)
            ->where('feature_code', 'max_employees')
            ->delete();

        PackageFeature::create([
            'package_id' => $this->package->id,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => null, // Unlimited
        ]);

        // Create 100 employees
        for ($i = 0; $i < 100; $i++) {
            $user = \App\Models\User::create([
                'name' => "Employee {$i}",
                'email' => "emp{$i}@test4.com",
                'password' => bcrypt('password'),
            ]);
            EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $user->id,
                'nik' => "nik{$i}",
                'hire_date' => now(),
                'employment_status' => 'active',
            ]);
        }

        $result = $this->employeeValidator->canAddEmployees($this->company, countToAdd: 50);

        $this->assertTrue($result['canAdd']);
        $this->assertNull($result['remaining']);
        $this->assertNull($result['limit']);
    }

    public function test_company_active_subscription_ignores_active_when_ends_at_passed(): void
    {
        $co = Company::factory()->create();
        Subscription::create([
            'company_id' => $co->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);

        $this->assertNull($co->fresh()->activeSubscription());
    }

    public function test_get_expired_includes_pending_payment_past_due_window(): void
    {
        $pending = Subscription::create([
            'company_id' => $this->company->id,
            'package_id' => $this->package->id,
            'plan_code' => 'pro',
            'status' => 'pending_payment',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
        ]);

        $expired = $this->terminationService->getExpiredSubscriptions();

        $this->assertTrue($expired->pluck('id')->contains($pending->id));
    }
}

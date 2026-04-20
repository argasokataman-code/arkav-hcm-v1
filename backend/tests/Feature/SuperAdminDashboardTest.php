<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\DashboardMetric;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $userToken;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a company and test data
        $this->company = Company::create([
            'code' => 'TEST001',
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'country' => 'US',
            'industry' => 'Technology',
            'currency' => 'USD',
        ]);

        // Register and login admin user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->adminToken = $adminLogin->json('data.accessToken');

        // Register and login regular user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $userLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->userToken = $userLogin->json('data.accessToken');
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function userRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->userToken);
    }

    public function test_admin_can_get_kpi()
    {
        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/kpi');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'totalCompanies',
                'totalUsers',
                'mrr',
                'arr',
                'activeSubscriptions',
                'churnRate',
                'customerLifetimeValue',
                'netRevenueRetention',
            ],
        ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_non_admin_cannot_get_kpi()
    {
        $response = $this->userRequest()->getJson('/v1/saas/dashboard/kpi');

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_admin_can_list_companies()
    {
        // Create additional companies
        Company::create([
            'code' => 'TEST002',
            'name' => 'Another Company',
            'email' => 'another@company.com',
            'country' => 'UK',
            'industry' => 'Finance',
            'currency' => 'GBP',
        ]);

        $package = Package::create([
            'name' => 'Starter Plan',
            'code' => 'STARTER',
            'description' => 'Starter plan',
            'price' => 49.99,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 49.99,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/companies');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
        $this->assertArrayHasKey('pagination', $response->json());
        $this->assertArrayHasKey('totalRevenue', $response->json('data.0'));
    }

    public function test_admin_can_get_top_companies()
    {
        // Create test package and subscriptions
        $package = Package::create([
            'name' => 'Pro Plan',
            'code' => 'PRO',
            'description' => 'Professional plan',
            'price' => 99.99,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 99.99,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/companies/top-performers');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_admin_can_get_company_details()
    {
        $package = Package::create([
            'name' => 'Basic Plan',
            'code' => 'BASIC',
            'description' => 'Basic plan',
            'price' => 29.99,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 29.99,
        ]);

        $response = $this->adminRequest()->getJson("/v1/saas/dashboard/companies/{$this->company->uuid}/details");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'code',
                'email',
                'userCount',
                'totalRevenue',
                'activeSubscriptions',
                'subscriptionsByStatus',
            ],
        ]);

        $this->assertEquals($this->company->name, $response->json('data.name'));
    }

    public function test_admin_can_get_user_stats()
    {
        // Create additional users
        User::create([
            'company_id' => $this->company->id,
            'name' => 'Test User 1',
            'email' => 'testuser1@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'totalUsers',
                'verifiedUsers',
                'unverifiedUsers',
                'newUsersThisMonth',
                'verificationRate',
            ],
        ]);

        $this->assertGreaterThan(0, $response->json('data.totalUsers'));
    }

    public function test_admin_can_get_user_retention_summary(): void
    {
        $retainedUser = User::create([
            'name' => 'Retained User',
            'email' => 'retained@example.com',
            'password' => bcrypt('password'),
        ]);

        $churnedUser = User::create([
            'name' => 'Churned User',
            'email' => 'churned@example.com',
            'password' => bcrypt('password'),
        ]);

        $newUser = User::create([
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => bcrypt('password'),
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id' => $retainedUser->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now()->subMonths(2),
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id' => $churnedUser->id,
            'role' => 'employee',
            'status' => 'inactive',
            'joined_at' => now()->subMonths(2),
        ]);

        CompanyUser::create([
            'company_id' => $this->company->id,
            'user_id' => $newUser->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now()->subDays(5),
        ]);

        CompanyUser::query()->where('user_id', $retainedUser->id)->update(['joined_at' => now()->subMonths(2)->startOfDay()]);
        CompanyUser::query()->where('user_id', $churnedUser->id)->update(['joined_at' => now()->subMonths(2)->startOfDay()]);
        CompanyUser::query()->where('user_id', $newUser->id)->update(['joined_at' => now()->subDays(5)->startOfDay()]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/users/retention');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.previousCohortUsers'));
        $this->assertEquals(1, $response->json('data.retainedUsers'));
        $this->assertEquals(1, $response->json('data.churnedUsers'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.newUsersThisMonth'));
        $this->assertEquals(50.0, $response->json('data.retentionRate'));
    }

    public function test_admin_can_get_monthly_revenue()
    {
        $package = Package::create([
            'name' => 'Enterprise',
            'code' => 'ENT',
            'description' => 'Enterprise plan',
            'price' => 299.99,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 299.99,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/revenue/monthly');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertCount(12, $response->json('data'));
    }

    public function test_admin_can_get_revenue_by_plan()
    {
        $package = Package::create([
            'name' => 'Premium',
            'code' => 'PREM',
            'description' => 'Premium plan',
            'price' => 199.99,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 199.99,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/revenue/by-plan');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_admin_can_get_revenue_forecast(): void
    {
        $package = Package::create([
            'name' => 'Forecast Plan',
            'code' => 'FORECAST',
            'description' => 'Forecast plan',
            'price' => 100,
        ]);

        foreach ([120, 180, 240] as $index => $amount) {
            $subscription = Subscription::create([
                'company_id' => $this->company->id,
                'package_uuid' => $package->uuid,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'amount' => $amount,
            ]);

            Subscription::query()
                ->whereKey($subscription->id)
                ->update(['created_at' => now()->subMonths(2 - $index)->startOfMonth()->addDays(2)]);
        }

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/revenue/forecast');

        $response->assertOk();
        $this->assertSame('average_delta_last_6_months', $response->json('data.method'));
        $this->assertCount(3, $response->json('data.forecast'));
    }

    public function test_admin_can_get_subscription_status()
    {
        $package = Package::create([
            'name' => 'Trial',
            'code' => 'TRIAL',
            'description' => 'Trial plan',
            'price' => 0,
        ]);

        // Create subscriptions with different statuses
        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/subscriptions/status');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('active', $response->json('data'));
    }

    public function test_admin_can_get_subscription_health(): void
    {
        $package = Package::create([
            'name' => 'Health Plan',
            'code' => 'HEALTH',
            'description' => 'Health plan',
            'price' => 79,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 79,
            'ends_at' => now()->addDays(7),
            'auto_renew' => false,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'amount' => 0,
            'ends_at' => now()->addDays(10),
            'auto_renew' => true,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'cancelled',
            'billing_cycle' => 'monthly',
            'amount' => 79,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/subscriptions/health');

        $response->assertOk();
        $this->assertIsNumeric($response->json('data.healthScore'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.expiringSoon'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.autoRenewDisabled'));
    }

    public function test_admin_can_view_audit_logs()
    {
        // Create audit log entries
        AuditLog::create([
            'super_admin_id' => User::where('email', 'qa.login@example.com')->first()->id,
            'action' => 'view_dashboard',
            'target_type' => 'dashboard',
            'target_id' => null,
            'details' => ['page' => 'kpi'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'superAdminId',
                    'action',
                    'targetType',
                    'details',
                    'createdAt',
                ],
            ],
            'pagination',
        ]);
    }

    public function test_admin_can_filter_audit_logs()
    {
        $adminUser = User::where('email', 'qa.login@example.com')->first();

        AuditLog::create([
            'super_admin_id' => $adminUser->id,
            'action' => 'modify_subscription',
            'target_type' => 'subscription',
            'target_id' => 1,
            'details' => ['change' => 'status'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/audit-logs?action=modify_subscription');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_admin_can_get_audit_log_detail(): void
    {
        $auditLog = AuditLog::create([
            'super_admin_id' => User::where('email', 'qa.login@example.com')->first()->id,
            'action' => 'modify_subscription',
            'target_type' => 'subscription',
            'target_id' => 42,
            'details' => ['change' => 'plan'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/audit-logs/'.$auditLog->id);

        $response->assertOk();
        $this->assertTrue($response->json('data.isSensitiveAction'));
        $this->assertEquals('PHPUnit', $response->json('data.userAgent'));
    }

    public function test_admin_can_get_custom_report_summary(): void
    {
        $package = Package::create([
            'name' => 'Custom Report Plan',
            'code' => 'CUSTOMREP',
            'description' => 'Custom report plan',
            'price' => 150,
        ]);

        Subscription::create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 150,
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/reports/custom?group_by=status');

        $response->assertOk();
        $this->assertEquals('status', $response->json('data.filters.groupBy'));
        $this->assertIsArray($response->json('data.breakdown'));
        $this->assertArrayHasKey('totalRevenue', $response->json('data.summary'));
    }

    public function test_non_admin_cannot_access_company_details()
    {
        $response = $this->userRequest()->getJson("/v1/saas/dashboard/companies/{$this->company->uuid}/details");

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_admin_can_get_metric_trend()
    {
        // Create sample metrics
        for ($i = 0; $i < 5; $i++) {
            DashboardMetric::create([
                'metric_date' => now()->subDays($i),
                'metric_key' => 'mrr',
                'metric_value' => 1000 + ($i * 100),
                'metric_metadata' => ['currency' => 'USD'],
            ]);
        }

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/kpi/mrr');

        $response->assertStatus(200);
        $this->assertEquals('mrr', $response->json('data.metricKey'));
        $this->assertArrayHasKey('trend', $response->json('data'));
    }

    public function test_dashboard_kpi_returns_expected_fields()
    {
        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/kpi');

        $data = $response->json('data');
        
        $this->assertArrayHasKey('totalCompanies', $data);
        $this->assertArrayHasKey('totalUsers', $data);
        $this->assertArrayHasKey('mrr', $data);
        $this->assertArrayHasKey('arr', $data);
        $this->assertIsNumeric($data['totalCompanies']);
        $this->assertIsNumeric($data['mrr']);
    }

    public function test_dashboard_kpi_prefers_cached_metric_when_available(): void
    {
        DashboardMetric::create([
            'metric_date' => now()->toDateString(),
            'metric_key' => 'mrr',
            'metric_value' => 777,
            'metric_metadata' => ['source' => 'test-cache'],
            'calculated_at' => now(),
            'next_calculation_at' => now()->addHour(),
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/kpi');

        $response->assertOk();
        $this->assertEquals(777, $response->json('data.mrr'));
    }

    public function test_company_list_pagination()
    {
        // Create 20 companies with unique codes
        for ($i = 1; $i <= 20; $i++) {
            Company::create([
                'code' => 'COMP' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Company ' . $i,
                'email' => 'company' . $i . '@test.com',
                'country' => 'US',
                'industry' => 'Tech',
                'currency' => 'USD',
            ]);
        }

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/companies');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(20, $response->json('pagination.total'));
        $this->assertEquals(15, $response->json('pagination.per_page'));
        $this->assertEquals(1, $response->json('pagination.current_page'));
    }

    public function test_company_list_honors_requested_per_page(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Company::create([
                'code' => 'PERPAGE' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Per Page Company ' . $i,
                'email' => 'perpage' . $i . '@test.com',
                'country' => 'US',
                'industry' => 'Tech',
                'currency' => 'USD',
            ]);
        }

        $response = $this->adminRequest()->getJson('/v1/saas/dashboard/companies?per_page=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('pagination.per_page'));
    }
}

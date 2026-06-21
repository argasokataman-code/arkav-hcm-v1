<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EmployeeCountValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_employee_is_blocked_when_plan_employee_limit_exceeded(): void
    {
        $company = Company::factory()->create();

        $package = Package::create([
            'name' => 'Starter',
            'code' => 'starter',
            'monthly_price' => 10.00,
            'yearly_price' => 100.00,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => null,
        ]);

        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 1,
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 10.00,
        ]);

        // Existing employee: company already at its limit (1).
        $existingUser = User::create([
            'name' => 'Existing Employee',
            'email' => 'existing@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::create([
            'company_id' => $company->id,
            'user_id' => $existingUser->id,
            'employment_status' => 'active',
        ]);

        // API auth (tenant admin only, not the global super admin bootstrap account)
        $admin = User::create([
            'name' => 'QA Admin',
            'email' => 'tenant-owner@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $rawToken = 'test-token-employee-limit';
        AuthToken::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHour(),
        ]);

        // The limit check happens before payload validation, so we can send a minimal payload.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawToken,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/employees', []);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'EMPLOYEE_COUNT_EXCEEDED');
    }

    public function test_pending_payment_subscription_does_not_grant_employee_slots(): void
    {
        $company = Company::factory()->create();
        $package = Package::create([
            'code' => 'pend_pkg',
            'name' => 'Pending pkg',
            'monthly_price' => 1,
            'yearly_price' => 10,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 5,
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'pend_pkg',
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1,
        ]);

        $result = app(EmployeeCountValidator::class)->canAddEmployees($company, 1);

        $this->assertFalse($result['canAdd']);
        $this->assertStringContainsString('No active subscription', $result['message']);
    }

    public function test_employee_limit_validator_reads_updated_active_package_limit_automatically(): void
    {
        $company = Company::factory()->create(['code' => 'AUTOUPGRADE1']);

        $starter = Package::create([
            'name' => 'Starter',
            'code' => 'starter-auto',
            'monthly_price' => 10.00,
            'yearly_price' => 100.00,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        $pro = Package::create([
            'name' => 'Pro',
            'code' => 'pro-auto',
            'monthly_price' => 20.00,
            'yearly_price' => 200.00,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::create([
            'package_uuid' => $starter->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 1,
        ]);
        PackageFeature::create([
            'package_uuid' => $pro->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 5,
        ]);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'package_uuid' => $starter->uuid,
            'plan_code' => $starter->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 10.00,
        ]);

        $validator = app(EmployeeCountValidator::class);

        $this->assertSame(1, $validator->getPlanEmployeeLimit($company));

        $subscription->update([
            'package_uuid' => $pro->uuid,
            'plan_code' => $pro->code,
            'amount' => 20.00,
        ]);

        $this->assertSame(5, $validator->getPlanEmployeeLimit($company->fresh()));
    }
}

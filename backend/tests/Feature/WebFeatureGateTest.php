<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, company: Company}
     */
    private function makeAdminTenant(string $emailSuffix, array $featureCodes = []): array
    {
        $code = 'TST' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $company = Company::query()->create([
            'code' => $code,
            'name' => 'Web Feature Gate ' . $emailSuffix,
            'legal_name' => 'Web Feature Gate ' . $emailSuffix . ' Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'wfg-' . strtolower($emailSuffix),
            'name' => 'WFG ' . $emailSuffix,
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        foreach ($featureCodes as $featureCode) {
            PackageFeature::query()->create([
                'package_uuid' => $package->uuid,
                'feature_code' => $featureCode,
                'feature_name' => ucfirst($featureCode),
                'limit' => 1,
            ]);
        }

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $user = User::query()->create([
            'name' => 'WFG Admin ' . $emailSuffix,
            'email' => 'wfg.' . strtolower($emailSuffix) . '@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        // Tenant admin via HCM RBAC (active role with admin permission marker).
        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'HCM_ADMIN',
            'name' => 'HCM Admin',
            'status' => 'active',
            'is_system' => true,
        ]);
        $permission = HcmPermission::query()->updateOrCreate(
            ['code' => 'hcm.admin'],
            [
                'module' => 'hcm',
                'resource' => 'system',
                'action' => 'admin',
                'name' => 'HCM Admin',
                'is_active' => true,
            ]
        );
        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'uuid' => (string) Str::uuid(),
        ]);
        HcmUserRole::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return ['user' => $user, 'company' => $company];
    }

    public function test_tickets_web_page_blocked_when_subscription_lacks_tickets_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoTickets', ['employee_management']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/tickets-admin')
            ->assertRedirect(url('upgrade') . '?blocked=tickets');

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/ticket-master')
            ->assertRedirect(url('upgrade') . '?blocked=tickets');
    }

    public function test_payroll_web_pages_blocked_when_subscription_lacks_payroll_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoPayroll', ['employee_management']);

        foreach (['/payroll', '/payroll-run', '/employee-salary'] as $path) {
            $this->actingAs($tenant['user'])
                ->withHeader('X-Company-Code', $tenant['company']->code)
                ->get($path)
                ->assertRedirect(url('upgrade') . '?blocked=payroll');
        }
    }

    public function test_training_web_pages_blocked_when_subscription_lacks_training_feature(): void
    {
        $tenant = $this->makeAdminTenant('NoTraining', ['employee_management']);

        foreach (['/training', '/trainers', '/training-type'] as $path) {
            $this->actingAs($tenant['user'])
                ->withHeader('X-Company-Code', $tenant['company']->code)
                ->get($path)
                ->assertRedirect(url('upgrade') . '?blocked=training');
        }
    }

    public function test_tickets_web_page_accessible_when_subscription_includes_feature(): void
    {
        $tenant = $this->makeAdminTenant('WithTickets', ['tickets']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/tickets-admin')
            ->assertOk();
    }
}

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

class CompanyOverviewFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, company: Company}
     */
    private function createOwnerTenant(array $featureCodes = []): array
    {
        $code = 'COV'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $company = Company::query()->create([
            'code' => $code,
            'name' => 'Company Overview '.$code,
            'legal_name' => 'Company Overview '.$code.' Ltd',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'cov-'.strtolower($code),
            'name' => 'COV Package '.$code,
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        foreach ($featureCodes as $fc) {
            PackageFeature::query()->create([
                'package_uuid' => $package->uuid,
                'feature_code' => $fc,
                'feature_name' => ucfirst(str_replace('_', ' ', $fc)),
                'limit' => null,
            ]);
        }

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Max Employees',
            'limit' => 10,
        ]);

        $user = User::query()->create([
            'name' => 'COV Owner '.$code,
            'email' => 'cov.owner.'.strtolower($code).'@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company->update(['owner_user_id' => $user->id]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
        ]);

        // HCM Admin RBAC (required by hcm.web.admin middleware)
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

    public function test_spt_masa_section_hidden_when_feature_absent(): void
    {
        $tenant = $this->createOwnerTenant(['employee_management', 'payroll']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/company-overview')
            ->assertOk()
            ->assertDontSee('SPT Masa PPh 21');
    }

    public function test_tax_governance_section_hidden_when_feature_absent(): void
    {
        $tenant = $this->createOwnerTenant(['employee_management', 'payroll']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/company-overview')
            ->assertOk()
            ->assertDontSee('Kebijakan Pajak (PPh21 Governance)');
    }

    public function test_spt_masa_section_visible_when_feature_included(): void
    {
        $tenant = $this->createOwnerTenant(['employee_management', 'spt_masa_pph21']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/company-overview')
            ->assertOk()
            ->assertSee('SPT Masa PPh 21');
    }

    public function test_tax_governance_section_visible_when_feature_included(): void
    {
        $tenant = $this->createOwnerTenant(['employee_management', 'tax_governance']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/company-overview')
            ->assertOk()
            ->assertSee('Kebijakan Pajak (PPh21 Governance)');
    }

    public function test_both_sections_visible_when_both_features_included(): void
    {
        $tenant = $this->createOwnerTenant(['employee_management', 'spt_masa_pph21', 'tax_governance']);

        $this->actingAs($tenant['user'])
            ->withHeader('X-Company-Code', $tenant['company']->code)
            ->get('/company-overview')
            ->assertOk()
            ->assertSee('SPT Masa PPh 21')
            ->assertSee('Kebijakan Pajak (PPh21 Governance)');
    }

    public function test_non_owner_redirected_to_company_profile(): void
    {
        $code = 'COV'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $company = Company::query()->create([
            'code' => $code,
            'name' => 'COV NonOwner '.$code,
            'legal_name' => 'COV NonOwner Ltd',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::query()->create([
            'name' => 'COV Admin '.$code,
            'email' => 'cov.admin.'.strtolower($code).'@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        // Minimal package + subscription so middleware don't redirect to /subscription
        $pkg = Package::query()->create([
            'code' => 'cov-no-'.strtolower($code),
            'name' => 'COV NonOwner Pkg',
            'monthly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $pkg->uuid,
            'plan_code' => $pkg->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        // HCM Admin RBAC
        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'HCM_ADMIN',
            'name' => 'HCM Admin',
            'status' => 'active',
            'is_system' => true,
        ]);
        $perm = HcmPermission::query()->updateOrCreate(
            ['code' => 'hcm.admin'],
            ['module' => 'hcm', 'resource' => 'system', 'action' => 'admin', 'name' => 'HCM Admin', 'is_active' => true]
        );
        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
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

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/company-overview')
            ->assertRedirectToRoute('company-profile');
    }
}

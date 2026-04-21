<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
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

class AssetManagementWebPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_with_asset_view_permission_cannot_access_asset_admin_pages(): void
    {
        $company = Company::query()->create([
            'code' => 'asset_web_view_only',
            'name' => 'Asset Web View Only',
            'legal_name' => 'Asset Web View Only Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'asset-web',
            'name' => 'Asset Web',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'asset_management',
            'feature_name' => 'Asset Management',
            'limit' => null,
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

        $user = User::query()->create([
            'name' => 'Asset View Member',
            'email' => 'asset.web.view@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-401',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $permission = HcmPermission::query()->create([
            'code' => 'asset.view',
            'module' => 'asset_management',
            'resource' => 'asset',
            'action' => 'view',
            'name' => 'View Assets',
            'is_active' => true,
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'ASSET_VIEW_WEB',
            'name' => 'Asset View Web',
            'status' => 'active',
            'is_system' => false,
        ]);

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

        $assetsResponse = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/assets');

        $assetsResponse->assertRedirect(url('employee-dashboard'));

        $categoriesResponse = $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/asset-categories');

        $categoriesResponse->assertRedirect(url('employee-dashboard'));
    }
}

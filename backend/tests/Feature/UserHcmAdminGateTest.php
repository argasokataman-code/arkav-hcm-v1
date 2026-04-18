<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHcmAdminGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_active_admin_role_is_treated_as_hcm_admin(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'ADMIN',
            'name' => 'Administrator',
            'status' => 'active',
            'is_system' => true,
        ]);

        HcmUserRole::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->assertTrue($user->isHcmAdmin());
        $this->assertTrue($user->isHcmAdminForCompany($company->id));
    }

    public function test_user_without_admin_assignment_is_not_treated_as_hcm_admin(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertFalse($user->isHcmAdmin());
        $this->assertFalse($user->isHcmAdminForCompany($company->id));
    }
}

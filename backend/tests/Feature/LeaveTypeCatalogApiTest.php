<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function adminToken(): string
    {
        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Type Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $adminEmail)->firstOrFail();

        $this->company = Company::create([
            'name' => 'Leave Type Test Company',
            'code' => 'LTC_'.time(),
        ]);

        CompanyUser::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $perm = HcmPermission::firstOrCreate(
            ['code' => 'leave.manage'],
            ['name' => 'Leave Manage', 'module' => 'leave', 'resource' => 'leave', 'action' => 'manage', 'is_active' => true]
        );

        $role = HcmRole::firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'LEAVE_ADMIN_TEST'],
            ['name' => 'Leave Admin Test', 'status' => 'active']
        );

        HcmRolePermission::withoutTimestamps(fn () => HcmRolePermission::firstOrCreate([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'company_id' => $this->company->id,
        ]));

        HcmUserRole::updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $this->company->id],
            ['role_id' => $role->id, 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $email = 'leave-type-employee@example.com';

        if ($this->company === null) {
            $this->company = Company::create([
                'name' => 'Leave Type Employee Company',
                'code' => 'LTE_'.time(),
            ]);
        }

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Type Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();

        CompanyUser::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_admin_can_list_leave_types(): void
    {
        $token = $this->adminToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/leave-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'isEnabled',
                        'days',
                        'carryForward',
                        'maxCarryDays',
                        'earnedLeave',
                        'sortOrder',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_update_and_disable_leave_type(): void
    {
        $token = $this->adminToken();

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/leave-types', [
            'code' => 'study_leave',
            'name' => 'Study Leave',
            'days' => 3,
            'carryForward' => false,
            'maxCarryDays' => null,
            'earnedLeave' => false,
            'isEnabled' => true,
            'sortOrder' => 99,
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'study_leave')
            ->assertJsonPath('data.name', 'Study Leave');

        $leaveTypeId = (int) $create->json('data.id');
        $this->assertDatabaseHas('hcm_leave_type_settings', [
            'id' => $leaveTypeId,
            'code' => 'study_leave',
            'name' => 'Study Leave',
            'is_enabled' => 1,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/leave-types/'.$leaveTypeId, [
            'name' => 'Updated Study Leave',
            'days' => 5,
            'carryForward' => true,
            'maxCarryDays' => 2,
            'earnedLeave' => true,
            'isEnabled' => false,
            'sortOrder' => 7,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Study Leave')
            ->assertJsonPath('data.isEnabled', false)
            ->assertJsonPath('data.days', 5);

        $this->assertDatabaseHas('hcm_leave_type_settings', [
            'id' => $leaveTypeId,
            'name' => 'Updated Study Leave',
            'is_enabled' => 0,
            'sort_order' => 7,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/leave-types/'.$leaveTypeId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.isEnabled', false);
    }

    public function test_non_admin_cannot_manage_leave_types(): void
    {
        $token = $this->employeeToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/leave-types')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/leave-types', [
            'code' => 'blocked_leave',
            'name' => 'Blocked Leave',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}

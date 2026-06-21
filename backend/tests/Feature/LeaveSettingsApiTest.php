<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmLeaveCustomPolicy;
use App\Models\HcmLeaveTypeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function hcmAdminBearerToken(string $email = 'leavesettings@example.com'): string
    {
        $company = $this->leaveSettingsCompany();
        $result = $this->createHcmAdminWithCompany([
            'name' => 'Leave Settings User',
            'email' => $email,
            'password' => 'StrongPass1',
        ], $company);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['company_id' => $company->id, 'designation' => 'HR Admin']
        );

        return $result['token'];
    }

    private function employeeBearerToken(string $email = 'leavesettings-emp@example.com'): string
    {
        $company = $this->leaveSettingsCompany();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Settings Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'employee', 'status' => 'active']
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['company_id' => $company->id, 'designation' => 'Staff']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function leaveSettingsCompany(): Company
    {
        if ($this->company instanceof Company) {
            return $this->company;
        }

        $this->company = $this->createIsolatedTestCompany(['code' => 'LEAVESET'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4))]);

        return $this->company;
    }

    public function test_leave_settings_index_returns_seeded_types(): void
    {
        $this->artisan('migrate');
        $token = $this->hcmAdminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->getJson('/v1/hcm/leave-settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(6, 'data.types');
    }

    public function test_update_type_and_custom_policy_flow(): void
    {
        $token = $this->hcmAdminBearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->putJson('/v1/hcm/leave-settings/types/annual_leave', [
            'isEnabled' => false,
            'days' => 14,
            'carryForward' => true,
            'maxCarryDays' => 7,
            'earnedLeave' => false,
        ])->assertOk()->assertJsonPath('success', true);

        $row = HcmLeaveTypeSetting::query()->where('code', 'annual_leave')->first();
        $this->assertFalse($row->is_enabled);
        $this->assertEquals(14.0, (float) $row->days);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->postJson('/v1/hcm/leave-settings/custom-policies', [
            'leaveTypeCode' => 'annual_leave',
            'name' => 'Extra 2 days',
            'days' => 2,
            'assigneeUserIds' => [],
        ])->assertStatus(201)->assertJsonPath('success', true);

        $p = HcmLeaveCustomPolicy::query()->first();
        $this->assertNotNull($p);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->putJson('/v1/hcm/leave-settings/custom-policies/'.$p->id, [
            'name' => 'Extra 3 days',
            'days' => 3,
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->deleteJson('/v1/hcm/leave-settings/custom-policies/'.$p->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, HcmLeaveCustomPolicy::query()->count());
    }

    public function test_non_hcm_admin_cannot_access_leave_settings(): void
    {
        $this->artisan('migrate');
        $token = $this->employeeBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->leaveSettingsCompany()->id])
            ->getJson('/v1/hcm/leave-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->leaveSettingsCompany()->id])
            ->putJson('/v1/hcm/leave-settings/types/annual_leave', ['isEnabled' => true])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->leaveSettingsCompany()->id])
            ->postJson('/v1/hcm/leave-settings/custom-policies', [
                'leaveTypeCode' => 'annual_leave',
                'name' => 'X',
                'days' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_can_create_custom_policy_with_new_leave_type_name(): void
    {
        $token = $this->hcmAdminBearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->leaveSettingsCompany()->id,
        ])->postJson('/v1/hcm/leave-settings/custom-policies', [
            'leaveTypeName' => 'Marriage Leave',
            'name' => 'Marriage 3 days',
            'days' => 3,
            'assigneeUserIds' => [],
        ])->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('hcm_leave_type_settings', [
            'name' => 'Marriage Leave',
        ]);
        $this->assertDatabaseHas('hcm_leave_custom_policies', [
            'name' => 'Marriage 3 days',
        ]);
    }

    public function test_custom_policy_accepts_numeric_assignee_ids_for_active_company_members(): void
    {
        $company = Company::factory()->create(['code' => 'leave_settings_numeric']);
        $adminEmail = 'leave-settings-admin-'.uniqid().'@example.com';
        $adminToken = $this->hcmAdminBearerToken($adminEmail);
        $adminUser = User::query()->where('email', $adminEmail)->firstOrFail();
        $employee = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $adminUser->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->postJson('/v1/hcm/leave-settings/custom-policies', [
            'leaveTypeCode' => 'annual_leave',
            'name' => 'Numeric assignee policy',
            'days' => 2,
            'assigneeUserIds' => [$employee->id],
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigneeUserIds.0', $employee->id);

        $policyId = (int) $response->json('data.id');
        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->putJson('/v1/hcm/leave-settings/custom-policies/'.$policyId, [
            'assigneeUserIds' => [$employee->id],
        ])->assertOk()
            ->assertJsonPath('data.assigneeUserIds.0', $employee->id);
    }

    public function test_custom_policy_rejects_assignee_ids_outside_active_company(): void
    {
        $company = Company::factory()->create(['code' => 'leave_settings_assignee_company']);
        $adminEmail = 'leave-settings-admin-'.uniqid().'@example.com';
        $adminToken = $this->hcmAdminBearerToken($adminEmail);
        $adminUser = User::query()->where('email', $adminEmail)->firstOrFail();
        $otherCompany = Company::factory()->create(['code' => 'leave_settings_other_company']);
        $foreignUser = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $adminUser->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $foreignUser->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->postJson('/v1/hcm/leave-settings/custom-policies', [
            'leaveTypeCode' => 'annual_leave',
            'name' => 'Foreign assignee policy',
            'days' => 2,
            'assigneeUserIds' => [$foreignUser->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['assigneeUserIds.0']);
    }
}

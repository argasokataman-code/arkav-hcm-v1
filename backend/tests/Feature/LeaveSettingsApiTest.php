<?php

namespace Tests\Feature;

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

    private function hcmAdminBearerToken(string $email = 'leavesettings@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Settings User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Admin']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ]);

        $login->assertOk();

        $token = $login->json('data.accessToken');
        $this->assertNotEmpty($token);

        return $token;
    }

    private function employeeBearerToken(string $email = 'leavesettings-emp@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Settings Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'Staff']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_leave_settings_index_returns_seeded_types(): void
    {
        $this->artisan('migrate');
        $token = $this->hcmAdminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
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
        ])->putJson('/v1/hcm/leave-settings/custom-policies/'.$p->id, [
            'name' => 'Extra 3 days',
            'days' => 3,
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/v1/hcm/leave-settings/custom-policies/'.$p->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, HcmLeaveCustomPolicy::query()->count());
    }

    public function test_non_hcm_admin_cannot_access_leave_settings(): void
    {
        $this->artisan('migrate');
        $token = $this->employeeBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/leave-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/leave-settings/types/annual_leave', ['isEnabled' => true])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

}

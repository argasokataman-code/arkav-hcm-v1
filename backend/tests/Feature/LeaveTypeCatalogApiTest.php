<?php

namespace Tests\Feature;

use App\Models\HcmLeaveTypeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Type Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $email = 'leave-type-employee@example.com';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Type Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_admin_can_list_leave_types(): void
    {
        $token = $this->adminToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
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
        ])->getJson('/v1/hcm/leave-types')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/leave-types', [
            'code' => 'blocked_leave',
            'name' => 'Blocked Leave',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}

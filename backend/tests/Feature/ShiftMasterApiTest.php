<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\HcmShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class ShiftMasterApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(bool $isAdmin = true, string $email = 'shiftadmin@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Shift Admin',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $isAdmin ? 'HR Admin' : 'Employee']
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

    public function test_shifts_forbidden_for_non_admin(): void
    {
        $token = $this->bearerToken(false, 'shiftemp@example.com');
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/shifts')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_shifts_crud_flow(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/shifts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/shifts', [
            'name' => 'Morning',
            'code' => 'morning',
            'startTime' => '08:00',
            'endTime' => '17:00',
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $id = HcmShift::query()->where('code', 'morning')->value('id');
        $this->assertNotNull($id);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/v1/hcm/shifts/'.$id, [
            'name' => 'Morning Updated',
            'code' => 'morning',
            'startTime' => '08:30',
            'endTime' => '17:30',
            'isActive' => true,
            'sortOrder' => 2,
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/v1/hcm/shifts/'.$id)
            ->assertOk();

        $this->assertNull(HcmShift::query()->find($id));
    }

    public function test_update_shift_returns_404_when_not_found(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/v1/hcm/shifts/999999', [
            'name' => 'Not Found',
            'code' => 'not_found',
            'startTime' => '08:00',
            'endTime' => '17:00',
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'SHIFT_NOT_FOUND');
    }

    public function test_shifts_forbidden_when_switching_to_unowned_company(): void
    {
        $token = $this->bearerToken();

        Company::query()->create([
            'code' => 'shift_other_company',
            'name' => 'Shift Other Company',
            'legal_name' => 'Shift Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => 'shift_other_company',
        ])->getJson('/v1/hcm/shifts')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_tenant_admin_cannot_mutate_global_shift_template(): void
    {
        $token = $this->bearerToken();

        $globalShift = HcmShift::query()->create([
            'company_id' => null,
            'code' => 'global_template',
            'name' => 'Global Template',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/v1/hcm/shifts/'.$globalShift->id, [
            'name' => 'Global Template Updated',
            'code' => 'global_template',
            'startTime' => '08:30',
            'endTime' => '17:30',
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/v1/hcm/shifts/'.$globalShift->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->assertNotNull(HcmShift::query()->find($globalShift->id));
    }
}

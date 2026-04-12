<?php

namespace Tests\Feature;

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
}

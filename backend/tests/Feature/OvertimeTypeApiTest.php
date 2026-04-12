<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmOvertimeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class OvertimeTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(bool $isAdmin = true, string $email = 'ottypeadmin@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'OT Type Admin',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $isAdmin ? 'HR Admin' : 'Staff']
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

    public function test_migration_seeds_default_types(): void
    {
        $this->assertGreaterThanOrEqual(3, HcmOvertimeType::query()->count());
    }

    public function test_non_admin_sees_only_active_types(): void
    {
        $this->bearerToken(true, 'ottypeadm@example.com');
        HcmOvertimeType::query()->where('code', 'weekday_ot')->update(['is_active' => false]);

        $tokenEmp = $this->bearerToken(false, 'ottypeemp@example.com');
        $resp = $this->withHeaders(['Authorization' => 'Bearer '.$tokenEmp])
            ->getJson('/v1/hcm/overtime-types')
            ->assertOk()
            ->assertJsonPath('success', true);

        $codes = collect($resp->json('data'))->pluck('code')->all();
        $this->assertNotContains('weekday_ot', $codes);
    }

    public function test_admin_crud_overtime_type(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/overtime-types', [
                'name' => 'Custom OT',
                'code' => 'custom_ot',
                'paymentMultiplier' => 1.75,
                'description' => 'Test',
                'isActive' => true,
                'sortOrder' => 10,
            ])->assertStatus(201);

        $id = HcmOvertimeType::query()->where('code', 'custom_ot')->value('id');
        $this->assertNotNull($id);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/overtime-types/'.$id, [
                'name' => 'Custom OT Updated',
                'code' => 'custom_ot',
                'paymentMultiplier' => 2,
                'description' => 'X',
                'isActive' => true,
                'sortOrder' => 11,
            ])->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/overtime-types/'.$id)
            ->assertOk();

        $this->assertNull(HcmOvertimeType::query()->find($id));
    }

    public function test_mutations_forbidden_for_non_admin(): void
    {
        $token = $this->bearerToken(false, 'ottypeemp2@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/overtime-types', [
                'name' => 'X',
                'paymentMultiplier' => 1,
            ])->assertStatus(403);
    }
}

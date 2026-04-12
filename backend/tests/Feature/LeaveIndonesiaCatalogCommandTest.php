<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveIndonesiaCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(string $email, string $designation): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave Catalog User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        \App\Models\EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $designation]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_seed_indonesia_catalog_creates_deduct_and_non_deduct_types(): void
    {
        $this->artisan('hcm:leave-seed-indonesia', ['--sync-legacy' => true])
            ->assertExitCode(0);

        $annual = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $sick = LeaveType::query()->where('code', 'sick_leave')->firstOrFail();
        $joint = LeaveType::query()->where('code', 'joint_leave')->firstOrFail();

        $this->assertTrue((bool) $annual->deduct_from_balance);
        $this->assertFalse((bool) $sick->deduct_from_balance);
        $this->assertTrue((bool) $joint->deduct_from_balance);
    }

    public function test_leave_type_options_exposes_deduct_metadata(): void
    {
        $this->artisan('hcm:leave-seed-indonesia', ['--sync-legacy' => true])
            ->assertExitCode(0);

        $token = $this->bearerToken('leave-metadata-user@example.com', 'Staff');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-type-options')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $annual = $rows->firstWhere('code', 'annual_leave');
        $sick = $rows->firstWhere('code', 'sick_leave');

        $this->assertNotNull($annual);
        $this->assertNotNull($sick);
        $this->assertTrue((bool) ($annual['deductFromBalance'] ?? false));
        $this->assertFalse((bool) ($sick['deductFromBalance'] ?? true));
    }
}

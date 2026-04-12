<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class OvertimeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndProfile(string $name, string $email, string $designation): User
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $designation]
        );

        return $user;
    }

    private function loginToken(string $email): string
    {
        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ]);
        $login->assertOk();
        $token = $login->json('data.accessToken');
        $this->assertNotEmpty($token);

        return $token;
    }

    public function test_me_includes_hcm_admin_flag(): void
    {
        $this->registerAndProfile('OT Emp', 'otemp@example.com', 'Staff');
        $this->registerAndProfile('OT Admin', 'otadm@example.com', 'HR Admin');

        $tEmp = $this->loginToken('otemp@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tEmp])
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.hcmAdmin', false);

        $tAdm = $this->loginToken('otadm@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.hcmAdmin', true);
    }

    public function test_non_admin_index_only_own_rows(): void
    {
        $admin = $this->registerAndProfile('OT Admin', 'otadm2@example.com', 'HR Admin');
        $alice = $this->registerAndProfile('Alice', 'alice@example.com', 'Staff');
        $bob = $this->registerAndProfile('Bob', 'bob@example.com', 'Staff');

        $tAdm = $this->loginToken('otadm2@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $alice->id,
                'workDate' => '2026-04-01',
                'minutes' => 60,
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $bob->id,
                'workDate' => '2026-04-02',
                'minutes' => 90,
            ])->assertStatus(201);

        $this->assertSame(2, OvertimeRequest::query()->count());

        $tBob = $this->loginToken('bob@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tBob])
            ->getJson('/v1/hcm/overtime-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.userId', $bob->id);

        $tAlice = $this->loginToken('alice@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAlice])
            ->getJson('/v1/hcm/overtime-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.userId', $alice->id);
    }

    public function test_non_admin_cannot_create_for_other_user(): void
    {
        $this->registerAndProfile('Alice', 'alice2@example.com', 'Staff');
        $bob = $this->registerAndProfile('Bob', 'bob2@example.com', 'Staff');

        $tAlice = $this->loginToken('alice2@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAlice])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $bob->id,
                'workDate' => '2026-04-01',
                'minutes' => 60,
            ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_non_admin_cannot_approve_other_users_request(): void
    {
        $this->registerAndProfile('OT Admin', 'otadm3@example.com', 'HR Admin');
        $alice = $this->registerAndProfile('Alice', 'alice3@example.com', 'Staff');
        $bob = $this->registerAndProfile('Bob', 'bob3@example.com', 'Staff');

        $tAdm = $this->loginToken('otadm3@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $alice->id,
                'workDate' => '2026-04-05',
                'minutes' => 45,
            ])->assertStatus(201);
        $otId = OvertimeRequest::query()->firstOrFail()->id;

        $tBob = $this->loginToken('bob3@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tBob])
            ->putJson('/v1/hcm/overtime-requests/'.$otId, [
                'status' => 'approved',
            ])->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->putJson('/v1/hcm/overtime-requests/'.$otId, [
                'status' => 'approved',
            ])->assertOk();

        $this->assertSame('approved', OvertimeRequest::query()->find($otId)?->status);
    }

    public function test_calculate_includes_salary_component_link(): void
    {
        $this->registerAndProfile('OT Calc', 'otcalc@example.com', 'Staff');
        $token = $this->loginToken('otcalc@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/overtime-requests/calculate', [
                'baseMonthlySalary' => 5_000_000,
                'fixedAllowance' => 0,
                'minutes' => 120,
                'dayType' => 'workday',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.salaryComponent.code', 'upah_lembur')
            ->assertJsonStructure(['data' => ['salaryComponent' => ['id', 'code', 'name']]]);
    }

    public function test_create_overtime_links_salary_component_row(): void
    {
        $comp = HcmSalaryComponent::query()->where('code', 'upah_lembur')->first();
        $this->assertNotNull($comp);

        $admin = $this->registerAndProfile('OT Admin', 'otadm4@example.com', 'HR Admin');
        $alice = $this->registerAndProfile('Alice', 'alice4@example.com', 'Staff');

        $tAdm = $this->loginToken('otadm4@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $alice->id,
                'workDate' => '2026-04-10',
                'minutes' => 60,
            ])
            ->assertStatus(201);

        $row = OvertimeRequest::query()->firstOrFail();
        $this->assertSame($comp->id, $row->hcm_salary_component_id);
    }

    public function test_admin_can_filter_index_by_work_date(): void
    {
        $this->registerAndProfile('OT Admin', 'otadm6@example.com', 'HR Admin');
        $alice = $this->registerAndProfile('Alice', 'alice6@example.com', 'Staff');

        $tAdm = $this->loginToken('otadm6@example.com');
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $alice->id,
                'workDate' => '2026-04-01',
                'minutes' => 60,
            ])->assertStatus(201);
        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->postJson('/v1/hcm/overtime-requests', [
                'userId' => $alice->id,
                'workDate' => '2026-04-02',
                'minutes' => 30,
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$tAdm])
            ->getJson('/v1/hcm/overtime-requests?workDate=2026-04-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.workDate', '2026-04-01');
    }
}

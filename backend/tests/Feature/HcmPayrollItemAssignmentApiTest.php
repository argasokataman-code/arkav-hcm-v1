<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollItemAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Assignment Admin',
            'email' => 'assignment-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'assignment-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Admin', 'employment_status' => 'active'],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'assignment-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(string $email = 'assignment-employee@example.com', float $baseSalary = 4_500_000): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Assignment Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 100_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_assignment_endpoints_require_hcm_admin(): void
    {
        $employeeToken = $this->employeeToken();
        $adminToken = $this->adminToken();

        $itemId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Allowance Assignment RBAC',
                'code' => 'allowance_assignment_rbac',
                'kind' => 'addition',
                'category' => 'other_addition',
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $targetUser = User::query()->where('email', 'assignment-employee@example.com')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$employeeToken])
            ->getJson('/v1/hcm/payroll-item-assignments?userId='.(int) $targetUser->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => (int) $targetUser->id,
                'payrollItemId' => $itemId,
                'amount' => 175000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $assignmentId = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$employeeToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => (int) $targetUser->id,
                'payrollItemId' => $itemId,
                'amount' => 100000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$employeeToken])
            ->putJson('/v1/hcm/payroll-item-assignments/'.$assignmentId, [
                'amount' => 200000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$employeeToken])
            ->deleteJson('/v1/hcm/payroll-item-assignments/'.$assignmentId)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_assignment_is_included_in_monthly_draft_lines(): void
    {
        $this->employeeToken('assignment-employee-2@example.com', 5_000_000);
        $adminToken = $this->adminToken();

        $employee = User::query()->where('email', 'assignment-employee-2@example.com')->firstOrFail();

        $itemResponse = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Allowance Assignment Included',
                'code' => 'allowance_assignment_included',
                'kind' => 'addition',
                'category' => 'other_addition',
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $itemId = (int) $itemResponse->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => (int) $employee->id,
                'payrollItemId' => $itemId,
                'amount' => 250000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.payrollItem.code', 'allowance_assignment_included')
            ->assertJsonPath('data.amount', 250000);

        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => 2026,
                'periodMonth' => 12,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $runId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');

        $line = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $runId)
            ->where('user_id', $employee->id)
            ->where('component_code', 'allowance_assignment_included')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame('addition', $line->kind);
        $this->assertEquals(250000.0, (float) $line->amount);
        $this->assertSame(
            1,
            HcmPayrollItem::query()->where('code', 'allowance_assignment_included')->count(),
        );
    }
}

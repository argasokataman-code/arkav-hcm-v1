<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollItem;
use App\Models\HcmPayrollLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollItemAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function payrollCompany(): Company
    {
        return Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            ['name' => 'Default Company', 'domain' => 'default-company.local']
        );
    }

    private function adminToken(): string
    {
        $this->company ??= $this->payrollCompany();

        $result = $this->createHcmAdminWithCompany([
            'name' => 'Assignment Admin',
            'email' => 'assignment-admin@example.com',
            'password' => 'StrongPass1',
        ], $this->company);

        $this->company = $result['company'];

        $user = User::query()->where('email', 'assignment-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['company_id' => $this->company->id, 'designation' => 'HR Admin', 'employment_status' => 'active'],
        );

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

        return $result['token'];
    }

    private function employeeToken(string $email = 'assignment-employee@example.com', float $baseSalary = 4_500_000): string
    {
        if (! $this->company) {
            $this->company = $this->payrollCompany();
        }

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Assignment Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ]
        );

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company->id,
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 100_000,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();

        $this->withHeaders(['X-Company-Id' => (string) $this->company->id]);

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
            ->getJson('/v1/hcm/payroll-item-assignments?userId='.$targetUser->uuid)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $targetUser->uuid,
                'payrollItemId' => $itemId,
                'amount' => 175000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $assignmentId = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$employeeToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $targetUser->uuid,
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
                'userId' => $employee->uuid,
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

    /**
     * M7 — Two active assignments for the same (user, payroll item) whose
     * effective ranges overlap must be rejected with PAYROLL_ITEM_ASSIGNMENT_OVERLAP.
     */
    public function test_overlapping_active_assignments_are_rejected(): void
    {
        $this->employeeToken('overlap-target@example.com', 5_000_000);
        $adminToken = $this->adminToken();
        $employee = User::query()->where('email', 'overlap-target@example.com')->firstOrFail();

        $itemId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Overlap Allowance',
                'code' => 'overlap_allowance',
                'kind' => 'addition',
                'category' => 'other_addition',
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 100_000,
                'effectiveStartDate' => '2027-01-01',
                'effectiveEndDate' => '2027-06-30',
            ])
            ->assertStatus(201);

        // Overlaps mid-range → reject
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 150_000,
                'effectiveStartDate' => '2027-04-01',
                'effectiveEndDate' => '2027-09-30',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_ITEM_ASSIGNMENT_OVERLAP');
    }

    /**
     * M7 — Adjacent (non-overlapping) ranges are allowed: assignment 1 ends
     * 2027-06-30, assignment 2 starts 2027-07-01 is valid.
     */
    public function test_adjacent_non_overlapping_assignments_are_accepted(): void
    {
        $this->employeeToken('adjacent-target@example.com', 5_000_000);
        $adminToken = $this->adminToken();
        $employee = User::query()->where('email', 'adjacent-target@example.com')->firstOrFail();

        $itemId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Adjacent Allowance',
                'code' => 'adjacent_allowance',
                'kind' => 'addition',
                'category' => 'other_addition',
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 100_000,
                'effectiveStartDate' => '2027-01-01',
                'effectiveEndDate' => '2027-06-30',
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 150_000,
                'effectiveStartDate' => '2027-07-01',
                'effectiveEndDate' => '2027-12-31',
            ])
            ->assertStatus(201);
    }

    /**
     * M7 — An open-ended existing assignment (end_date NULL) must block any
     * new assignment whose start is >= its start.
     */
    public function test_open_ended_existing_blocks_future_assignment(): void
    {
        $this->employeeToken('open-target@example.com', 5_000_000);
        $adminToken = $this->adminToken();
        $employee = User::query()->where('email', 'open-target@example.com')->firstOrFail();

        $itemId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Open Ended Allowance',
                'code' => 'open_ended_allowance',
                'kind' => 'addition',
                'category' => 'other_addition',
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 100_000,
                'effectiveStartDate' => '2027-01-01',
                // no end date
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll-item-assignments', [
                'userId' => $employee->uuid,
                'payrollItemId' => $itemId,
                'amount' => 200_000,
                'effectiveStartDate' => '2027-05-01',
                'effectiveEndDate' => '2027-08-31',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_ITEM_ASSIGNMENT_OVERLAP');
    }
}

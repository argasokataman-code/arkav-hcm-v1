<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Company;
use App\Models\HcmResignation;
use App\Models\HcmTermination;
use App\Models\HcmThrYearlySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollThrApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'THR Admin',
            'email' => 'thr-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'thr-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'thr-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function workerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'THR Worker',
            'email' => 'thr-worker@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'thr-worker@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => 4_000_000,
                'fixed_allowance' => 0,
                'bank_account_no' => '1234567890',
                'bank_name' => 'BCA',
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'thr-worker@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_thr_calculate_forbidden_for_non_admin(): void
    {
        $tok = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$tok])
            ->postJson('/v1/hcm/payroll/thr-calculate', [
                'joinDate' => '2024-01-01',
                'cutoffDate' => '2024-12-31',
                'baseMonthlySalary' => 6_000_000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_thr_calculate_validation(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-calculate', [])
            ->assertStatus(422);
    }

    public function test_thr_invalid_cutoff_before_join(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-calculate', [
                'joinDate' => '2025-06-01',
                'cutoffDate' => '2025-01-01',
                'baseMonthlySalary' => 6_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'invalid_dates')
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.thrGross', 0);
    }

    public function test_thr_not_eligible_under_one_full_month(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-calculate', [
                'joinDate' => '2024-06-01',
                'cutoffDate' => '2024-06-15',
                'baseMonthlySalary' => 6_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'not_eligible')
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.monthsOfService', 0)
            ->assertJsonPath('data.thrGross', 0);
    }

    public function test_thr_pro_rata_five_months(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-calculate', [
                'joinDate' => '2024-01-01',
                'cutoffDate' => '2024-06-01',
                'baseMonthlySalary' => 6_000_000,
                'fixedMonthlyAllowance' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pro_rata')
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.monthsOfService', 5)
            ->assertJsonPath('data.multiplier', 0.416667)
            ->assertJsonPath('data.referenceMonthlyWage', 6_000_000)
            ->assertJsonPath('data.thrGross', 2_500_000);
    }

    public function test_thr_full_twelve_months_includes_fixed_allowance_in_base(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-calculate', [
                'joinDate' => '2023-01-01',
                'cutoffDate' => '2024-01-15',
                'baseMonthlySalary' => 5_000_000,
                'fixedMonthlyAllowance' => 1_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'full')
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.monthsOfService', 12)
            ->assertJsonPath('data.multiplier', 1)
            ->assertJsonPath('data.referenceMonthlyWage', 6_000_000)
            ->assertJsonPath('data.thrGross', 6_000_000);
    }

    public function test_thr_settings_forbidden_for_non_admin(): void
    {
        $tok = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$tok])
            ->getJson('/v1/hcm/payroll/thr-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_thr_settings_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();

        Company::query()->create([
            'code' => 'thr_other_company',
            'name' => 'THR Other Company',
            'legal_name' => 'THR Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Code' => 'thr_other_company',
        ])->getJson('/v1/hcm/payroll/thr-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_thr_settings_list_and_upsert(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/thr-settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.settings');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/thr-settings/2026', [
                'eidDate' => '2026-03-20',
                'paymentDate' => '2026-03-12',
                'calculationCutoffDate' => '2026-03-19',
                'notes' => 'H-7 bayar',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.calendarYear', 2026)
            ->assertJsonPath('data.eidDate', '2026-03-20')
            ->assertJsonPath('data.paymentDate', '2026-03-12')
            ->assertJsonPath('data.calculationCutoffDate', '2026-03-19');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/thr-settings')
            ->assertOk()
            ->assertJsonCount(1, 'data.settings')
            ->assertJsonPath('data.settings.0.calendarYear', 2026);
    }

    public function test_thr_batch_generate_requires_cutoff_in_settings(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/thr-settings/2030', [
                'eidDate' => '2030-03-01',
            ])
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2030])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'THR_SETUP_CUTOFF_REQUIRED');
    }

    public function test_thr_batch_generate_assign_and_my_slip_includes_thr(): void
    {
        $admin = $this->adminToken();
        $workerTok = $this->workerToken();
        $worker = User::query()->where('email', 'thr-worker@example.com')->firstOrFail();
        EmployeeProfile::query()->where('user_id', $worker->id)->update([
            'hire_date' => '2020-01-01',
            'base_salary' => 3_000_000,
            'fixed_allowance' => 0,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/thr-settings/2027', [
                'eidDate' => '2027-04-10',
                'paymentDate' => '2027-04-05',
                'calculationCutoffDate' => '2027-04-09',
            ])
            ->assertOk();

        $gen = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2027])
            ->assertOk();
        $batchId = (int) $gen->json('data.batch.id');
        $this->assertGreaterThan(0, $batchId);
        $workerLineAfterGen = collect($gen->json('data.lines'))->firstWhere('userId', $worker->id);
        $this->assertNotNull($workerLineAfterGen);
        $this->assertSame('BCA', $workerLineAfterGen['bankName']);
        $this->assertSame('1234567890', $workerLineAfterGen['bankAccountNo']);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/post-payroll', [
                'batchId' => $batchId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'THR_POST_UNPAID_PAYABLE_LINES');

        $disburse = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/disburse', [
                'batchId' => $batchId,
                'userIds' => [$worker->id],
            ])
            ->assertOk();
        $workerLine = collect($disburse->json('data.lines'))->firstWhere('userId', $worker->id);
        $this->assertNotNull($workerLine);
        $this->assertSame('paid', $workerLine['paymentStatus']);
        $this->assertTrue($workerLine['hasSlip']);

        $lineId = (int) collect(
            $this->withHeaders(['Authorization' => 'Bearer '.$admin])
                ->getJson('/v1/hcm/payroll/thr-batch?calendarYear=2027')
                ->json('data.lines'),
        )->firstWhere('userId', $worker->id)['id'];
        $this->assertGreaterThan(0, $lineId);

        $slip = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->get('/v1/hcm/payroll/thr-batch/lines/'.$lineId.'/slip');
        $slip->assertOk();
        $this->assertStringContainsString('pdf', (string) $slip->headers->get('content-type'));

        $mySlip = $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-thr-slip')
            ->assertOk()
            ->assertJsonPath('data.line.userId', $worker->id)
            ->assertJsonPath('data.batch.calendarYear', 2027)
            ->assertJsonPath('data.line.calendarYear', 2027)
            ->assertJsonPath('data.history.0.lineId', $lineId);

        $publicNo = $mySlip->json('data.line.thrSlipPublicNo');
        $this->assertIsString($publicNo);
        $this->assertNotSame('', $publicNo);
        $this->assertSame('#'.$publicNo, $mySlip->json('data.line.slipNumber'));
        $this->assertMatchesRegularExpression('/^THR-2027-[0-9A-Z]{26}$/', $publicNo);

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->get('/v1/hcm/payroll/thr-batch/lines/'.$lineId.'/slip')
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/post-payroll', [
                'batchId' => $batchId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'THR_BATCH_NOT_DRAFT');

        $this->withHeaders(['Authorization' => 'Bearer '.$workerTok])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2027&periodMonth=4')
            ->assertOk()
            ->assertJsonPath('data.run.purpose', 'thr')
            ->assertJsonCount(1, 'data.lines')
            ->assertJsonPath('data.lines.0.componentCode', 'thr')
            ->assertJsonPath('data.lines.0.amount', 3_000_000);
    }

    public function test_thr_batch_forbidden_for_non_admin(): void
    {
        $tok = $this->workerToken();
        $this->withHeaders(['Authorization' => 'Bearer '.$tok])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2027])
            ->assertStatus(403);
    }

    /**
     * Regression test: Approved resignation should block employee from THR batch.
     * Without proper filtering, resigned employees could still appear in THR lines.
     */
    public function test_resigned_employee_excluded_from_thr_batch(): void
    {
        $adminToken = $this->adminToken();
        
        // Create an active employee (without using seed)
        $resignedUser = $this->createEmployeeWithProfile('Resign User', 'resign@test.com', '2024-01-15', 50_000_000);
        
        // Verify resignation record is created and approved
        HcmResignation::query()->create([
            'user_id' => $resignedUser->id,
            'department' => 'Test',
            'reason' => 'Personal',
            'notice_date' => '2024-10-01',
            'resignation_date' => '2024-10-31',
            'status' => 'approved',
        ]);
        
        // Verify the record exists in DB
        $resignationCount = HcmResignation::query()
            ->where('user_id', $resignedUser->id)
            ->where('status', 'approved')
            ->count();
        $this->assertEquals(1, $resignationCount, 'Resignation record should be created and approved');
        
        // Set THR settings
        HcmThrYearlySetting::query()->updateOrCreate(
            ['calendar_year' => 2024],
            [
                'eid_date' => '2024-03-01',
                'payment_date' => '2024-04-01',
                'calculation_cutoff_date' => '2024-12-31',
            ]
        );
        
        // Generate THR batch
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2024])
            ->assertOk();
        
        $batchData = $response->json('data');
        $lines = $batchData['lines'] ?? [];
        
        // Assert: resigned employee should NOT be in THR lines
        $hasResignedUser = collect($lines)
            ->contains(fn ($line) => $line['userId'] === $resignedUser->id);
        
        $this->assertFalse($hasResignedUser,
            'Resigned employee should be completely excluded from THR batch');
    }

    /**
     * Regression test: Approved termination should block employee from THR batch.
     * Without proper filtering, terminated employees could still appear in THR lines.
     */
    public function test_terminated_employee_excluded_from_thr_batch(): void
    {
        $adminToken = $this->adminToken();
        
        // Create an active employee
        $terminatedUser = $this->createEmployeeWithProfile('Terminated User', 'terminated@test.com', '2024-01-15', 50_000_000);
        
        // Create and approve termination (before cutoff)
        HcmTermination::query()->create([
            'user_id' => $terminatedUser->id,
            'department' => 'Test',
            'termination_type' => 'end_of_contract',
            'reason' => 'Contract ended',
            'notice_date' => '2024-09-01',
            'termination_date' => '2024-09-30',
            'status' => 'approved',
        ]);
        
        // Verify the record exists
        $terminationCount = HcmTermination::query()
            ->where('user_id', $terminatedUser->id)
            ->where('status', 'approved')
            ->count();
        $this->assertEquals(1, $terminationCount, 'Termination record should be created and approved');
        
        // Set THR settings
        HcmThrYearlySetting::query()->updateOrCreate(
            ['calendar_year' => 2024],
            [
                'eid_date' => '2024-03-01',
                'payment_date' => '2024-04-01',
                'calculation_cutoff_date' => '2024-12-31',
            ]
        );
        
        // Generate THR batch
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2024])
            ->assertOk();
        
        $batchData = $response->json('data');
        $lines = $batchData['lines'] ?? [];
        
        // Assert: terminated employee should NOT be in THR lines
        $hasTerminatedUser = collect($lines)
            ->contains(fn ($line) => $line['userId'] === $terminatedUser->id);
        
        $this->assertFalse($hasTerminatedUser,
            'Terminated employee should be completely excluded from THR batch');
    }

    /**
     * Regression test: Pending resignation (not yet approved) should NOT block employee from THR batch.
     * Only approved resignations should block payroll inclusion.
     */
    public function test_pending_resignation_does_not_block_thr(): void
    {
        $adminToken = $this->adminToken();
        
        // Create an active employee
        $user = $this->createEmployeeWithProfile('Pending Resign User', 'pending@test.com', '2024-01-15', 50_000_000);
        
        // Create resignation BUT do NOT approve it
        HcmResignation::query()->create([
            'user_id' => $user->id,
            'department' => 'Test',
            'reason' => 'Personal',
            'notice_date' => '2024-10-01',
            'resignation_date' => '2024-10-31',
            'status' => 'pending',  // Not approved
        ]);
        
        // Set THR settings
        HcmThrYearlySetting::query()->updateOrCreate(
            ['calendar_year' => 2024],
            [
                'eid_date' => '2024-03-01',
                'payment_date' => '2024-04-01',
                'calculation_cutoff_date' => '2024-12-31',
            ]
        );
        
        // Generate THR batch
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2024])
            ->assertOk();
        
        $batchData = $response->json('data');
        $lines = $batchData['lines'] ?? [];
        
        // Verify the user IS in the lines (because resignation is pending, not approved)
        $hasUser = collect($lines)
            ->contains(fn ($line) => $line['userId'] === $user->id);
        
        $this->assertTrue($hasUser,
            'Employee with pending resignation should still appear in THR batch');
    }

    /**
     * Helper: Create employee profile with salary and hire date
     */
    private function createEmployeeWithProfile(string $name, string $email, string $hireDate, float $baseSalary): User
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'TestPass1',
            'confirmPassword' => 'TestPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'Operations',
                'designation' => 'Staff',
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 0,
                'hire_date' => $hireDate,
            ],
        );

        return $user;
    }
}

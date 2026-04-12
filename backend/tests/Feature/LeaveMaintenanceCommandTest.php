<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveMaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_accrual_is_idempotent_for_same_period(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['hire_date' => '2025-01-01']
        );

        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $policy = LeavePolicy::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('is_earned_leave', true)
            ->firstOrFail();

        LeavePolicyAssignment::query()->create([
            'company_id' => null,
            'policy_id' => $policy->id,
            'employee_id' => $user->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->artisan('hcm:leave-maintenance', [
            '--mode' => 'monthly-accrual',
            '--date' => '2026-04-30',
        ])->assertExitCode(0);

        $this->artisan('hcm:leave-maintenance', [
            '--mode' => 'monthly-accrual',
            '--date' => '2026-04-30',
        ])->assertExitCode(0);

        $rows = LeaveLedger::query()
            ->where('employee_id', $user->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('reference_type', 'system_monthly_accrual')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEquals(1.0, (float) $rows->first()->amount);
    }
}

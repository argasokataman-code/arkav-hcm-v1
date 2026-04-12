<?php

namespace Tests\Feature;

use App\Models\HcmLeaveCustomPolicy;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveFoundationBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_custom_policy_assignments_from_legacy_settings(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        HcmLeaveCustomPolicy::query()->create([
            'leave_type_code' => 'annual_leave',
            'name' => 'Legacy 14 Days Policy',
            'days' => 14,
            'assignee_user_ids' => [$u1->id, $u2->id],
        ]);

        $this->artisan('hcm:leave-backfill-foundation', ['--assign-all-users' => true])
            ->assertExitCode(0);

        $leaveType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $customPolicy = LeavePolicy::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('name', 'Legacy Custom: Legacy 14 Days Policy')
            ->firstOrFail();

        $this->assertEquals(14.0, (float) $customPolicy->days_per_year);

        $legacyType = \App\Models\HcmLeaveTypeSetting::query()->where('code', 'annual_leave')->firstOrFail();
        $legacyCustom = HcmLeaveCustomPolicy::query()->where('name', 'Legacy 14 Days Policy')->firstOrFail();
        $this->assertSame((int) $leaveType->id, (int) $legacyType->leave_type_id);
        $this->assertSame((int) $leaveType->id, (int) $legacyCustom->leave_type_id);
        $this->assertSame((int) $customPolicy->id, (int) $legacyCustom->leave_policy_id);

        $assigned = LeavePolicyAssignment::query()
            ->where('policy_id', $customPolicy->id)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([(int) $u1->id, (int) $u2->id], $assigned);
    }

    public function test_backfill_is_idempotent_for_repeated_runs(): void
    {
        $user = User::factory()->create();

        HcmLeaveCustomPolicy::query()->create([
            'leave_type_code' => 'annual_leave',
            'name' => 'Legacy Annual Custom',
            'days' => 13,
            'assignee_user_ids' => [$user->id],
        ]);

        $this->artisan('hcm:leave-backfill-foundation', ['--assign-all-users' => true])->assertExitCode(0);
        $this->artisan('hcm:leave-backfill-foundation', ['--assign-all-users' => true])->assertExitCode(0);

        $this->assertSame(1, LeaveType::query()->where('code', 'annual_leave')->count());
        $this->assertSame(1, LeavePolicy::query()->where('name', 'Legacy Custom: Legacy Annual Custom')->count());

        $customPolicyId = LeavePolicy::query()->where('name', 'Legacy Custom: Legacy Annual Custom')->value('id');
        $this->assertSame(
            1,
            LeavePolicyAssignment::query()
                ->where('policy_id', $customPolicyId)
                ->where('employee_id', $user->id)
                ->count()
        );
    }
}

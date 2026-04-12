<?php

namespace Tests\Feature;

use App\Models\HcmLeaveCustomPolicy;
use App\Models\HcmLeaveTypeSetting;
use App\Models\LeaveApproval;
use App\Models\LeaveLedger;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveTestingDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_testing_data_creates_cross_schema_leave_fixtures(): void
    {
        $this->artisan('hcm:leave-seed-testing-data', ['--fresh' => true])->assertExitCode(0);

        $this->assertGreaterThanOrEqual(4, User::query()->count());
        $this->assertGreaterThanOrEqual(9, LeaveType::query()->count());
        $this->assertGreaterThanOrEqual(7, LeaveRequest::query()->count());
        $this->assertGreaterThanOrEqual(7, LeaveApproval::query()->count());
        $this->assertGreaterThanOrEqual(4, LeavePolicyAssignment::query()->count());
        $this->assertGreaterThanOrEqual(2, HcmLeaveCustomPolicy::query()->count());
        $this->assertGreaterThanOrEqual(1, LeaveLedger::query()->count());

        $legacyType = HcmLeaveTypeSetting::query()->where('code', 'annual_leave')->firstOrFail();
        $this->assertNotNull($legacyType->leave_type_id);

        $legacyCustom = HcmLeaveCustomPolicy::query()->where('name', 'Engineering Annual 15')->firstOrFail();
        $this->assertNotNull($legacyCustom->leave_type_id);
        $this->assertNotNull($legacyCustom->leave_policy_id);
    }
}

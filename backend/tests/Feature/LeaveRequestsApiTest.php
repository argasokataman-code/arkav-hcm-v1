<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\EmployeeLeaveBalance;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveLedger;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestBreakdown;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class LeaveRequestsApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(string $email, string $designation): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Leave User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $designation]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_authenticated_user_can_list_enabled_leave_type_options(): void
    {
        $this->artisan('migrate');
        $employeeToken = $this->bearerToken('leave-types-emp@example.com', 'Employee');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$employeeToken,
        ])->getJson('/v1/hcm/leave-type-options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_create_leave_for_other_user(): void
    {
        $employeeToken = $this->bearerToken('employee-leave@example.com', 'Employee');
        $other = User::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$employeeToken,
        ])->postJson('/v1/hcm/leave-requests', [
            'userId' => $other->id,
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-11',
            'notes' => 'Trying create for other user',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_can_create_leave_for_other_user(): void
    {
        $adminToken = $this->bearerToken('admin-leave@example.com', 'HR Admin');
        $other = User::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->postJson('/v1/hcm/leave-requests', [
            'userId' => $other->id,
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-11',
            'notes' => 'Created by admin',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_non_admin_cannot_update_another_users_leave_status(): void
    {
        $employeeToken = $this->bearerToken('employee-leave2@example.com', 'Staff');
        $other = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-11',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$employeeToken,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'approved',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_can_update_another_users_leave_status(): void
    {
        $adminToken = $this->bearerToken('admin-leave2@example.com', 'HR Manager');
        $other = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-11',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_admin_approval_posts_leave_ledger_and_balance(): void
    {
        $adminToken = $this->bearerToken('admin-leave3@example.com', 'HR Manager');
        $other = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-11',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'approved',
        ])->assertOk();

        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $ledger = LeaveLedger::query()
            ->where('employee_id', $other->id)
            ->where('leave_type_id', $annualType->id)
            ->where('reference_type', 'leave_request_approval')
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(-2.0, (float) $ledger->amount);

        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $other->id)
            ->where('leave_type_id', $annualType->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(-2.0, (float) $balance->balance);
        $this->assertEquals(2.0, (float) $balance->used);
    }

    public function test_approve_decline_reapprove_keeps_net_usage_consistent(): void
    {
        $adminToken = $this->bearerToken('admin-leave4@example.com', 'HR Manager');
        $other = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-16',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $headers = ['Authorization' => 'Bearer '.$adminToken];

        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])->assertOk();
        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'declined'])->assertOk();
        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])->assertOk();

        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $net = (float) LeaveLedger::query()
            ->where('employee_id', $other->id)
            ->where('leave_type_id', $annualType->id)
            ->where('reference_id', 'like', 'leave_request:'.$leave->id.':%')
            ->sum('amount');

        $this->assertEquals(-2.0, $net);
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_scope_me_returns_balance_summary_meta_for_ui_cards(): void
    {
        $token = $this->bearerToken('leave-balance-meta@example.com', 'Staff');
        $user = User::query()->where('email', 'leave-balance-meta@example.com')->firstOrFail();
        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        EmployeeLeaveBalance::query()->create([
            'company_id' => null,
            'employee_id' => $user->id,
            'leave_type_id' => $annualType->id,
            'year' => (int) now()->year,
            'balance' => 10,
            'used' => 2,
            'expired' => 0,
            'carried_forward' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?scope=me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.balanceSummary.totalBalance', 10)
            ->assertJsonPath('meta.balanceSummary.totalUsed', 2)
            ->assertJsonCount(1, 'meta.balanceSummary.byType');
    }

    public function test_index_filter_leave_type_accepts_name_and_legacy_code_variants(): void
    {
        $adminToken = $this->bearerToken('leave-filter-admin@example.com', 'HR Admin');
        $employee = User::factory()->create();

        LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);
        LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-11',
            'date_to' => '2026-04-11',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->getJson('/v1/hcm/leave-requests?leaveType=Annual%20Leave&userId='.$employee->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_leave_type_label_for_legacy_value(): void
    {
        $token = $this->bearerToken('leave-labels@example.com', 'Staff');
        $user = User::query()->where('email', 'leave-labels@example.com')->firstOrFail();

        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'annual_leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?scope=me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.leaveType', 'annual_leave')
            ->assertJsonPath('data.0.leaveTypeLabel', 'Annual Leave');
    }

    public function test_store_auto_calculates_working_days_excluding_weekend_and_holiday(): void
    {
        $token = $this->bearerToken('leave-autodays@example.com', 'Staff');

        HolidayCalendar::query()->create([
            'company_id' => null,
            'date' => '2026-04-13',
            'name' => 'Cuti Bersama',
            'is_national' => true,
            'is_joint_leave' => true,
            'deduct_from_leave' => true,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/leave-requests', [
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-13',
            'notes' => 'Auto calc working days',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $request = LeaveRequest::query()->latest('id')->firstOrFail();
        $this->assertEquals(1.0, (float) $request->days);
    }

    public function test_store_persists_leave_request_breakdowns_with_holiday_calendar_link(): void
    {
        $token = $this->bearerToken('leave-breakdown-create@example.com', 'Staff');

        $holiday = Holiday::query()->create([
            'title' => 'Hari Libur Breakdown',
            'holiday_date' => '2026-04-13',
            'description' => 'test',
            'is_active' => true,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);
        $calendar = HolidayCalendar::query()->create([
            'company_id' => null,
            'holiday_id' => $holiday->id,
            'date' => '2026-04-13',
            'name' => 'Hari Libur Breakdown',
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/leave-requests', [
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-13',
            'notes' => 'Breakdown create',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $request = LeaveRequest::query()->latest('id')->firstOrFail();
        $rows = LeaveRequestBreakdown::query()
            ->where('leave_request_id', $request->id)
            ->orderBy('leave_date')
            ->get();

        $this->assertCount(4, $rows);
        $this->assertEquals(1.0, (float) $rows->sum('deducted_days'));

        $holidayRow = LeaveRequestBreakdown::query()
            ->where('leave_request_id', $request->id)
            ->whereDate('leave_date', '2026-04-13')
            ->first();
        $this->assertNotNull($holidayRow);
        $this->assertTrue((bool) $holidayRow->is_holiday);
        $this->assertSame((int) $calendar->id, (int) $holidayRow->holiday_calendar_id);
        $this->assertSame(0.0, (float) $holidayRow->deducted_days);
    }

    public function test_employee_update_resyncs_leave_request_breakdowns(): void
    {
        $token = $this->bearerToken('leave-breakdown-update@example.com', 'Staff');
        $user = User::query()->where('email', 'leave-breakdown-update@example.com')->firstOrFail();

        $leave = LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'pending',
            'notes' => null,
        ]);

        LeaveRequestBreakdown::query()->create([
            'leave_request_id' => $leave->id,
            'leave_date' => '2026-04-10',
            'unit_type' => 'full_day',
            'session' => null,
            'minutes' => null,
            'is_working_day' => true,
            'is_holiday' => false,
            'holiday_name' => null,
            'holiday_calendar_id' => null,
            'deducted_days' => 1,
            'meta' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-11',
            'leaveType' => 'Annual Leave',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $breakdownDates = DB::table('leave_request_breakdowns')
            ->where('leave_request_id', $leave->id)
            ->orderBy('leave_date')
            ->pluck('leave_date')
            ->values()
            ->all();

        $this->assertSame(['2026-04-10', '2026-04-11'], $breakdownDates);
        $this->assertSame(1.0, (float) DB::table('leave_request_breakdowns')->where('leave_request_id', $leave->id)->sum('deducted_days'));
    }

    public function test_scope_me_includes_holiday_meta_for_leave_menu(): void
    {
        $token = $this->bearerToken('leave-holiday-meta@example.com', 'Staff');

        HolidayCalendar::query()->create([
            'company_id' => null,
            'date' => now()->addDays(5)->toDateString(),
            'name' => 'Hari Testing Nasional',
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?scope=me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'meta.holidays');
    }

    public function test_leave_requests_support_status_and_date_filters(): void
    {
        $token = $this->bearerToken('leave-filter@example.com', 'HR Admin');
        $user = User::query()->where('email', 'leave-filter@example.com')->firstOrFail();

        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);
        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'Sick Leave',
            'date_from' => '2026-04-20',
            'date_to' => '2026-04-21',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?status=approved&dateFrom=2026-04-01&dateTo=2026-04-15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('meta.filters.status', 'approved');
    }

    public function test_leave_holiday_meta_includes_holiday_id_linkage(): void
    {
        $token = $this->bearerToken('leave-holiday-link@example.com', 'Staff');

        $holiday = Holiday::query()->create([
            'title' => 'Hari Link Holiday',
            'holiday_date' => now()->addDays(7)->toDateString(),
            'description' => 'test link',
            'is_active' => true,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);
        HolidayCalendar::query()->create([
            'company_id' => null,
            'holiday_id' => $holiday->id,
            'date' => $holiday->holiday_date->toDateString(),
            'name' => 'Hari Link Holiday',
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?scope=me')
            ->assertOk()
            ->assertJsonPath('success', true);

        $holidayIds = collect($response->json('meta.holidays', []))
            ->pluck('holidayId')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->assertContains((int) $holiday->id, $holidayIds);
    }

    public function test_leave_requests_export_csv_uses_active_filters(): void
    {
        $token = $this->bearerToken('leave-export-admin@example.com', 'HR Admin');
        $user = User::query()->where('email', 'leave-export-admin@example.com')->firstOrFail();

        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-03',
            'date_to' => '2026-04-03',
            'days' => 1,
            'status' => 'approved',
            'notes' => 'export-me',
        ]);
        LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => 'Sick Leave',
            'date_from' => '2026-05-03',
            'date_to' => '2026-05-03',
            'days' => 1,
            'status' => 'pending',
            'notes' => 'should-not-export',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/v1/hcm/leave-requests/export?status=approved&dateFrom=2026-04-01&dateTo=2026-04-30');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Employee,Email,"Leave Type","Date From","Date To",Days,Status,Notes', $content);
        $this->assertStringContainsString('export-me', $content);
        $this->assertStringNotContainsString('should-not-export', $content);
    }
}

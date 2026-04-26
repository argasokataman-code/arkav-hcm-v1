<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\AttendanceRecord;
use App\Models\CompanyUser;
use App\Models\EmployeeLeaveBalance;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveLedger;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestBreakdown;
use App\Models\LeaveType;
use App\Models\Company;
use App\Models\User;
use App\Notifications\LeaveRequestedNotification;
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
        $employee = User::query()->where('email', 'employee-leave@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $employee->company_id ?? 1,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

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
        $company = Company::factory()->create(['code' => 'leave_admin_create_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave@example.com',
            'name' => 'Admin Leave',
        ], $company);
        $adminToken = $admin['token'];
        $other = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Seed balance for the other user
        $leaveType = \App\Models\LeaveType::where('code', 'annual_leave')->first();
        $companyId = $company->id;

        \App\Models\EmployeeLeaveBalance::create([
            'company_id' => $companyId,
            'employee_id' => $other->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'balance' => 10.0,
            'used' => 0.0,
            'expired' => 0.0,
            'carried_forward' => 0.0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->postJson('/v1/hcm/leave-requests', [
            'userId' => $other->id,
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-11',
            'notes' => 'Created by admin',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_employee_leave_submission_notifies_tenant_admin_owner(): void
    {
        $company = Company::factory()->create(['code' => 'leave_notification_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'owner-leave-notif@example.com',
            'name' => 'Owner Leave Notif',
        ], $company);

        $employee = User::factory()->create([
            'email' => 'employee-leave-notif@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $employee->id],
            ['role' => 'employee', 'status' => 'active', 'joined_at' => now()]
        );

        EmployeeProfile::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $employee->id],
            ['employment_status' => 'active', 'designation' => 'Staff', 'team' => 'Operations', 'nik' => 'EMP-LEAVE-NOTIF-1', 'hire_date' => now()->subMonth()->toDateString()]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $employee->email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();
        $employeeToken = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$employeeToken,
            'X-Company-Code' => $company->code,
        ])->postJson('/v1/hcm/leave-requests', [
            'leaveType' => 'hospitalisation',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-10',
            'days' => 1,
            'notes' => 'leave notification smoke test',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $adminUser = User::query()->where('email', 'owner-leave-notif@example.com')->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $adminUser->id,
            'type' => LeaveRequestedNotification::class,
        ]);
    }

    public function test_non_admin_cannot_update_another_users_leave_status(): void
    {
        $employeeToken = $this->bearerToken('employee-leave2@example.com', 'Staff');
        $other = User::factory()->create();
        $employee = User::query()->where('email', 'employee-leave2@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $employee->company_id ?? 1,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $employee->company_id ?? 1,
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
        $company = Company::factory()->create(['code' => 'leave_admin_update_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave2@example.com',
            'name' => 'Admin Leave Update',
        ], $company);
        $adminToken = $admin['token'];
        $other = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
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
            'X-Company-Code' => $company->code,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_admin_cannot_override_employee_notes_when_not_declining(): void
    {
        $company = Company::factory()->create(['code' => 'leave_admin_note_lock_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave-note-lock@example.com',
            'name' => 'Admin Note Lock',
        ], $company);
        $adminToken = $admin['token'];
        $employee = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'pending',
            'notes' => 'Employee original note',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'approved',
            'notes' => 'Admin attempted overwrite',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('approved', (string) $leave->fresh()->status);
        $this->assertSame('Employee original note', (string) $leave->fresh()->notes);
    }

    public function test_admin_decline_requires_reason_and_preserves_employee_note_context(): void
    {
        $company = Company::factory()->create(['code' => 'leave_admin_decline_reason_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave-decline-reason@example.com',
            'name' => 'Admin Decline Reason',
        ], $company);
        $adminToken = $admin['token'];
        $employee = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'pending',
            'notes' => 'Employee original note',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'declined',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'declined',
            'notes' => 'Insufficient supporting documents.',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $saved = (string) $leave->fresh()->notes;
        $this->assertStringContainsString('Employee original note', $saved);
        $this->assertStringContainsString('[Admin rejection reason]', $saved);
        $this->assertStringContainsString('Insufficient supporting documents.', $saved);
    }

    public function test_admin_approval_posts_leave_ledger_and_balance(): void
    {
        $company = Company::factory()->create(['code' => 'leave_admin_approve_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave3@example.com',
            'name' => 'Admin Leave Approve',
        ], $company);
        $adminToken = $admin['token'];
        $other = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
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
            'X-Company-Code' => $company->code,
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
        $company = Company::factory()->create(['code' => 'leave_admin_reapprove_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave4@example.com',
            'name' => 'Admin Leave Reapprove',
        ], $company);
        $adminToken = $admin['token'];
        $other = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Seed balance for the test
        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        EmployeeLeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $other->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'balance' => 10.0,
            'used' => 0.0,
            'expired' => 0.0,
            'carried_forward' => 0.0,
        ]);

        // Seed policy for the test
        $policy = \App\Models\LeavePolicy::create([
            'company_id' => $company->id,
            'leave_type_id' => $annualType->id,
            'name' => 'Test Annual Policy',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'max_days_per_year' => 12,
            'max_consecutive_days' => 5,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-16',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ];

        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])->assertOk();
        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, [
            'status' => 'declined',
            'notes' => 'Policy recheck required',
        ])->assertOk();
        $this->withHeaders($headers)->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])->assertOk();

        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        $net = (float) LeaveLedger::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $other->id)
            ->where('leave_type_id', $annualType->id)
            ->where('reference_id', 'like', 'leave_request:'.$leave->id.':%')
            ->sum('amount');

        $this->assertEquals(-2.0, $net);
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_approved_leave_only_updates_attendance_within_same_company(): void
    {
        $company = Company::factory()->create(['code' => 'leave_attendance_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'admin-leave4@example.com',
            'name' => 'Admin Leave Attendance',
        ], $company);
        $adminToken = $admin['token'];
        $other = User::factory()->create();
        $otherCompany = Company::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $other->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-15',
            'date_to' => '2026-04-15',
            'days' => 1,
            'status' => 'pending',
            'notes' => null,
        ]);

        AttendanceRecord::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $other->id,
            'work_date' => '2026-04-15',
            'status' => 'present',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => $company->code,
        ])
            ->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'company_id' => $leave->company_id,
            'user_id' => $other->id,
            'work_date' => '2026-04-15 00:00:00',
            'status' => 'on_leave',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'company_id' => $otherCompany->id,
            'user_id' => $other->id,
            'work_date' => '2026-04-15 00:00:00',
            'status' => 'present',
        ]);
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
        $company = Company::factory()->create(['code' => 'leave_filter_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'leave-filter-admin@example.com',
            'name' => 'Leave Filter Admin',
        ], $company);
        $adminToken = $admin['token'];
        $employee = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);
        LeaveRequest::query()->create([
            'company_id' => $company->id,
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
            'X-Company-Code' => $company->code,
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
            'company_id' => $user->company_id ?? 1,
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
        $user = User::query()->where('email', 'leave-autodays@example.com')->firstOrFail();

        // Seed balance for the test user
        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        EmployeeLeaveBalance::create([
            'company_id' => $user->company_id ?? 1,
            'employee_id' => $user->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'balance' => 10.0,
            'used' => 0.0,
            'expired' => 0.0,
            'carried_forward' => 0.0,
        ]);

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
        $user = User::query()->where('email', 'leave-breakdown-create@example.com')->firstOrFail();

        // Seed balance for the test user
        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
        EmployeeLeaveBalance::create([
            'company_id' => $user->company_id ?? 1,
            'employee_id' => $user->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'balance' => 10.0,
            'used' => 0.0,
            'expired' => 0.0,
            'carried_forward' => 0.0,
        ]);

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
            'company_id' => $user->company_id ?? 1,
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
            'company_id' => $user->company_id ?? 1,
            'user_id' => $user->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-10',
            'days' => 1,
            'status' => 'approved',
            'notes' => null,
        ]);
        LeaveRequest::query()->create([
            'company_id' => $user->company_id ?? 1,
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
            'company_id' => $user->company_id ?? 1,
            'user_id' => $user->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-04-03',
            'date_to' => '2026-04-03',
            'days' => 1,
            'status' => 'approved',
            'notes' => 'export-me',
        ]);
        LeaveRequest::query()->create([
            'company_id' => $user->company_id ?? 1,
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
        ])->get('/v1/hcm/leave-requests/export?scope=me&status=approved&dateFrom=2026-04-01&dateTo=2026-04-30');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Leave Type","Date From","Date To",Days,Status,Notes', $content);
        $this->assertStringContainsString('export-me', $content);
        $this->assertStringNotContainsString('should-not-export', $content);
    }

    public function test_leave_request_update_forbidden_when_switching_to_unowned_company(): void
    {
        $adminToken = $this->bearerToken('leave-idor-admin@example.com', 'HR Admin');

        \App\Models\Company::query()->create([
            'code' => 'leave_idor_other_company',
            'name' => 'Leave IDOR Other Company',
            'legal_name' => 'Leave IDOR Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $otherUser = User::factory()->create();
        $leave = LeaveRequest::query()->create([
            'company_id' => $otherUser->company_id ?? 1,
            'user_id' => $otherUser->id,
            'leave_type' => 'Annual',
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-11',
            'days' => 2,
            'status' => 'pending',
            'notes' => null,
        ]);

        // Admin from unowned company cannot approve/decline that leave
        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Code' => 'leave_idor_other_company',
        ])->putJson('/v1/hcm/leave-requests/'.$leave->id, ['status' => 'approved'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_store_rejects_leave_request_when_balance_insufficient(): void
    {
        $token = $this->bearerToken('leave-insufficient-balance@example.com', 'Staff');
        $user = User::query()->where('email', 'leave-insufficient-balance@example.com')->firstOrFail();
        $annualType = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

        // Set low balance
        EmployeeLeaveBalance::query()->create([
            'company_id' => null,
            'employee_id' => $user->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'balance' => 1.0, // Only 1 day available
            'used' => 0,
            'expired' => 0,
            'carried_forward' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/leave-requests', [
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-14', // Monday
            'dateTo' => '2026-04-15', // Tuesday, 2 working days
            'notes' => 'Should fail due to insufficient balance',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'LEAVE_INSUFFICIENT_BALANCE');
    }

    public function test_admin_cannot_create_leave_for_user_outside_active_company(): void
    {
        $company = Company::factory()->create(['code' => 'leave_foreign_create_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'leave-foreign-admin@example.com',
            'name' => 'Leave Foreign Admin',
        ], $company);
        $otherCompany = Company::factory()->create(['code' => 'leave_foreign_target_company']);
        $foreignUser = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $foreignUser->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Code' => $company->code,
        ])->postJson('/v1/hcm/leave-requests', [
            'userId' => $foreignUser->id,
            'leaveType' => 'Annual Leave',
            'dateFrom' => '2026-04-10',
            'dateTo' => '2026-04-11',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['userId']);
    }

    public function test_admin_cannot_view_leave_balance_for_user_outside_active_company(): void
    {
        $company = Company::factory()->create(['code' => 'leave_foreign_balance_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'leave-balance-admin@example.com',
            'name' => 'Leave Balance Admin',
        ], $company);
        $otherCompany = Company::factory()->create(['code' => 'leave_foreign_balance_target']);
        $foreignUser = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $foreignUser->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Code' => $company->code,
        ])->getJson('/v1/hcm/employee-leave-balance?leaveType=Annual%20Leave&userId='.$foreignUser->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_NOT_IN_COMPANY');
    }
}

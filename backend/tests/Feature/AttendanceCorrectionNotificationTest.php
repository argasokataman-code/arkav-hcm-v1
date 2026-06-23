<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AttendanceCorrectionApprovedNotification;
use App\Notifications\AttendanceCorrectionRequestedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AttendanceCorrectionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private ?User $adminUser = null;

    private ?User $employeeUser = null;

    private string $adminToken = '';

    private string $employeeToken = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Admin
        $result = $this->createHcmAdminWithCompany([
            'name' => 'HCM Admin',
            'email' => 'hcmadmin@test.local',
            'password' => 'StrongPass1',
        ]);
        $this->company = $result['company'];
        $this->adminUser = $result['user'];
        $this->adminToken = $result['token'];

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $this->adminUser->id],
            ['designation' => 'HR Admin']
        );

        // Employee
        $employee = User::factory()->create([
            'name' => 'Test Employee',
            'email' => 'employee@test.local',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            ['designation' => 'Staff']
        );
        $this->employeeUser = $employee;

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'employee@test.local',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);
        $this->employeeToken = $login->json('data.accessToken');
    }

    private function makeAttendanceRecord(array $attrs = []): AttendanceRecord
    {
        $defaults = [
            'company_id' => $this->company->id,
            'user_id' => $this->employeeUser->id,
            'work_date' => Carbon::today()->toDateString(),
            'check_in_at' => Carbon::now()->subHours(4),
            'check_out_at' => Carbon::now()->subHour(),
            'status' => 'needs_review',
            'correction_status' => 'none',
        ];

        return AttendanceRecord::query()->create(array_merge($defaults, $attrs));
    }

    private function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Company-Id' => (string) $this->company->id,
        ];
    }

    private function employeeHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->employeeToken,
            'X-Company-Id' => (string) $this->company->id,
        ];
    }

    // -------------------------------------------------------------------------
    // GAP-A: requestCorrection() notifies HCM admins
    // -------------------------------------------------------------------------

    public function test_request_correction_sends_notification_to_admin(): void
    {
        Notification::fake();
        $this->makeAttendanceRecord();

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-request', [
                'workDate' => Carbon::today()->toDateString(),
                'reason' => 'Lupa absen keluar karena server down',
            ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($this->adminUser, AttendanceCorrectionRequestedNotification::class);
    }

    // -------------------------------------------------------------------------
    // GAP-M: re-request guard — returns 422 when already pending
    // -------------------------------------------------------------------------

    public function test_request_correction_returns_422_when_already_pending(): void
    {
        Notification::fake();
        $this->makeAttendanceRecord(['correction_status' => 'requested']);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-request', [
                'workDate' => Carbon::today()->toDateString(),
                'reason' => 'Attempting duplicate request',
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'CORRECTION_ALREADY_PENDING');

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // GAP-O: window validation — returns 422 when workDate outside window
    // -------------------------------------------------------------------------

    public function test_request_correction_returns_422_when_outside_window(): void
    {
        Notification::fake();

        // Set window to 7 days
        CompanySetting::query()->updateOrCreate(
            ['company_id' => $this->company->id, 'key' => 'attendance_correction_window_days'],
            ['value' => '7', 'type' => 'integer']
        );

        $oldDate = Carbon::today()->subDays(10)->toDateString();
        $this->makeAttendanceRecord(['work_date' => $oldDate]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-request', [
                'workDate' => $oldDate,
                'reason' => 'Old record correction',
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'CORRECTION_WINDOW_EXCEEDED');

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // GAP-O: window=0 means unlimited
    // -------------------------------------------------------------------------

    public function test_request_correction_allowed_when_window_is_zero(): void
    {
        Notification::fake();

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $this->company->id, 'key' => 'attendance_correction_window_days'],
            ['value' => '0', 'type' => 'integer']
        );

        $oldDate = Carbon::today()->subDays(90)->toDateString();
        $this->makeAttendanceRecord(['work_date' => $oldDate]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-request', [
                'workDate' => $oldDate,
                'reason' => 'Very old correction with unlimited window',
            ])->assertOk()
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // GAP-F: adminUpsertRecord approve sends notification to employee
    // -------------------------------------------------------------------------

    public function test_admin_upsert_approve_sends_notification_to_employee(): void
    {
        Notification::fake();
        $rec = $this->makeAttendanceRecord(['correction_status' => 'requested', 'correction_reason' => 'Server down']);

        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/admin/record', [
                'userId' => $this->employeeUser->id,
                'workDate' => $rec->work_date->toDateString(),
                'checkInTime' => '08:00',
                'checkOutTime' => '17:00',
                'breakMinutes' => 60,
                'lateMinutes' => 0,
            ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($this->employeeUser, AttendanceCorrectionApprovedNotification::class);
    }

    // -------------------------------------------------------------------------
    // GAP-N: delete with pending correction notifies employee
    // -------------------------------------------------------------------------

    public function test_admin_delete_record_with_pending_correction_notifies_employee(): void
    {
        Notification::fake();
        $this->makeAttendanceRecord(['correction_status' => 'requested', 'correction_reason' => 'Please fix']);

        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/admin/record', [
                'userId' => $this->employeeUser->id,
                'workDate' => Carbon::today()->toDateString(),
                'checkInTime' => '',
                'checkOutTime' => '',
                'breakMinutes' => 0,
                'lateMinutes' => 0,
            ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($this->employeeUser, AttendanceCorrectionApprovedNotification::class);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation: other company's admin NOT notified
    // -------------------------------------------------------------------------

    public function test_correction_notification_does_not_reach_other_tenant_admin(): void
    {
        Notification::fake();

        // Create a second company + admin
        $otherResult = $this->createHcmAdminWithCompany([
            'name' => 'Other Admin',
            'email' => 'otheradmin@test.local',
            'password' => 'StrongPass1',
        ]);
        $otherAdmin = $otherResult['user'];

        $this->makeAttendanceRecord();

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-request', [
                'workDate' => Carbon::today()->toDateString(),
                'reason' => 'Tenant isolation test reason',
            ])->assertOk();

        Notification::assertNotSentTo($otherAdmin, AttendanceCorrectionRequestedNotification::class);
        Notification::assertSentTo($this->adminUser, AttendanceCorrectionRequestedNotification::class);
    }

    // -------------------------------------------------------------------------
    // GAP-O: attendance settings GET requires admin
    // -------------------------------------------------------------------------

    public function test_attendance_settings_show_requires_admin(): void
    {
        $this->withHeaders($this->employeeHeaders())
            ->getJson('/v1/hcm/attendance/settings')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // GAP-O: settings GET returns default 30
    // -------------------------------------------------------------------------

    public function test_attendance_settings_show_returns_default(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/v1/hcm/attendance/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correctionWindowDays', 30);
    }

    // -------------------------------------------------------------------------
    // GAP-O: settings PUT saves and returns new value
    // -------------------------------------------------------------------------

    public function test_attendance_settings_update_saves_value(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/settings', ['correctionWindowDays' => 14])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correctionWindowDays', 14);

        $saved = CompanySetting::query()
            ->where('company_id', $this->company->id)
            ->where('key', 'attendance_correction_window_days')
            ->value('value');
        $this->assertSame('14', $saved);
    }

    // -------------------------------------------------------------------------
    // GAP-O: settings PUT validates range
    // -------------------------------------------------------------------------

    public function test_attendance_settings_update_rejects_out_of_range(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/settings', ['correctionWindowDays' => 400])
            ->assertStatus(422);
    }

    // ================================================================
    // B3: Employee can cancel pending correction
    // ================================================================

    public function test_employee_can_cancel_pending_correction(): void
    {
        // Create needs_review record + request correction
        $record = $this->makeAttendanceRecord([
            'status' => 'needs_review',
            'correction_status' => 'requested',
            'correction_reason' => 'I need to fix my time',
            'correction_requested_at' => Carbon::now(),
        ]);

        // Cancel
        $response = $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-cancel', [
                'workDate' => $record->work_date->toDateString(),
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $record->refresh();
        $this->assertSame('none', (string) $record->correction_status);
        $this->assertNull($record->correction_reason);
        $this->assertNull($record->correction_requested_at);
    }

    public function test_employee_cannot_cancel_non_pending_correction(): void
    {
        // Already approved
        $record = $this->makeAttendanceRecord([
            'correction_status' => 'approved',
        ]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-cancel', [
                'workDate' => $record->work_date->toDateString(),
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'CORRECTION_NOT_PENDING');
    }

    public function test_employee_cannot_cancel_others_correction(): void
    {
        // Create record for a different user
        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other-b3@test.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $record = $this->makeAttendanceRecord([
            'user_id' => $otherUser->id,
            'status' => 'needs_review',
            'correction_status' => 'requested',
            'correction_reason' => 'Other user issue',
            'correction_requested_at' => Carbon::now(),
        ]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/correction-cancel', [
                'workDate' => $record->work_date->toDateString(),
            ])->assertStatus(404);
    }

    // ================================================================
    // B1: Max break limit
    // ================================================================

    public function test_break_start_rejected_when_at_max_limit(): void
    {
        // Set max break to 60 minutes
        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/settings', [
                'correctionWindowDays' => 30,
                'maxBreakMinutes' => 60,
            ])->assertOk();

        // Create record with break already at max, punch in, no active break
        $this->makeAttendanceRecord([
            'status' => 'present',
            'check_in_at' => Carbon::now()->subHours(4),
            'check_out_at' => null,
            'break_minutes' => 60,
            'break_started_at' => null,
        ]);

        // Try to start a new break → should be rejected
        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/break')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BREAK_LIMIT_REACHED');
    }

    public function test_break_start_allowed_when_under_max_limit(): void
    {
        // Set max break to 120 minutes
        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/settings', [
                'correctionWindowDays' => 30,
                'maxBreakMinutes' => 120,
            ])->assertOk();

        // Create record with 30 min break, not at max
        $this->makeAttendanceRecord([
            'status' => 'present',
            'check_in_at' => Carbon::now()->subHours(4),
            'check_out_at' => null,
            'break_minutes' => 30,
            'break_started_at' => null,
        ]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_start');
    }

    public function test_max_break_zero_means_unlimited(): void
    {
        // maxBreakMinutes = 0 means unlimited
        $this->withHeaders($this->adminHeaders())
            ->putJson('/v1/hcm/attendance/settings', [
                'correctionWindowDays' => 30,
                'maxBreakMinutes' => 0,
            ])->assertOk();

        // Create record with lots of break
        $this->makeAttendanceRecord([
            'status' => 'present',
            'check_in_at' => Carbon::now()->subHours(4),
            'check_out_at' => null,
            'break_minutes' => 480,
            'break_started_at' => null,
        ]);

        $this->withHeaders($this->employeeHeaders())
            ->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_start');
    }

    // ================================================================
    // B4: Feature gate middleware for attendance_correction
    // ================================================================

    public function test_correction_endpoints_are_feature_gated(): void
    {
        $company = Company::query()->create([
            'code' => 'no_correction_feature',
            'name' => 'No Correction Feature',
            'legal_name' => 'No Correction Feature Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'basic-no-corr',
            'name' => 'Basic No Correction',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        // Package has OTHER features but NOT attendance_correction
        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'attendance',
            'feature_name' => 'Attendance',
            'limit' => 1,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $user = User::query()->create([
            'name' => 'Corr Gate Admin',
            'email' => 'corr.gate@test.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'corr.gate@test.com',
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        // correction-request should be blocked
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/attendance/me/correction-request', [
            'workDate' => now()->toDateString(),
            'reason' => 'Test feature gate.',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');

        // correction-cancel should also be blocked
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/attendance/me/correction-cancel', [
            'workDate' => now()->toDateString(),
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\User;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var Company */
    private ?Company $company = null;

    private function bearerToken(bool $isAdmin = true, string $email = 'att@example.com'): string
    {
        if (! $this->company) {
            $this->company = Company::query()->firstOrCreate(
                ['code' => 'TEST_COMPANY'],
                ['name' => 'Test Company', 'domain' => 'test-company.local']
            );
        }

        if ($isAdmin) {
            $result = $this->createHcmAdminWithCompany([
                'name' => 'Att User',
                'email' => $email,
                'password' => 'StrongPass1',
            ], $this->company);
            $this->company = $result['company'];
            $token = $result['token'];
        } else {
            // For non-admin, create user directly and add to company without HCM permissions
            $user = User::factory()->create([
                'name' => 'Att User',
                'email' => $email,
            ]);

            if (class_exists('App\\Models\\CompanyUser')) {
                \App\Models\CompanyUser::firstOrCreate([
                    'user_id' => $user->id,
                    'company_id' => $this->company->id,
                ]);
            }

            $login = $this->postJson('/v1/identity/auth/login', [
                'email' => $email,
                'password' => 'password',
                'companyCode' => $this->company->code,
            ]);

            $login->assertOk();
            $token = $login->json('data.accessToken');
        }

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $isAdmin ? 'HR Admin' : 'Employee']
        );

        return $token;
    }

    /** @return array{latitude: float, longitude: float} */
    private function punchGpsBody(): array
    {
        return ['latitude' => -6.2088, 'longitude' => 106.8456];
    }

    private function attachUserToActiveCompany(User $user): void
    {
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
    }

    public function test_attendance_admin_requires_auth(): void
    {
        $this->getJson('/v1/hcm/attendance/admin')->assertStatus(401);
    }

    public function test_attendance_admin_forbidden_for_non_admin_user(): void
    {
        $token = $this->bearerToken(false, 'staff@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_attendance_admin_forbidden_when_switching_to_unowned_company(): void
    {
        $token = $this->bearerToken(true, 'att-admin@example.com');

        Company::query()->create([
            'code' => 'attendance_other_company',
            'name' => 'Attendance Other Company',
            'legal_name' => 'Attendance Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => 'attendance_other_company',
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_attendance_admin_returns_rows_and_summary(): void
    {
        $token = $this->bearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/admin');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'date',
                    'summary' => [
                        'totalEmployees',
                        'present',
                        'absent',
                        'lateLogin',
                        'uninformed',
                        'permission',
                    ],
                ],
            ]);
    }

    public function test_attendance_punch_flow(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'in');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('data.action', 'out');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ATTENDANCE_ALREADY_COMPLETE');
    }

    public function test_attendance_punch_requires_latitude_and_longitude(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', [])
            ->assertStatus(422);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', ['latitude' => 91, 'longitude' => 0])
            ->assertStatus(422);
    }

    public function test_attendance_break_start_and_end_updates_break_minutes(): void
    {
        $token = $this->bearerToken();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_start');

        // make sure there is measurable break duration
        usleep(1500000);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_end');

        $today = now(config('app.timezone'))->toDateString();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $record = \App\Models\AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->firstOrFail();

        $this->assertGreaterThanOrEqual(0, (int) $record->break_minutes);
        $this->assertNull($record->break_started_at);
    }

    public function test_attendance_punch_out_too_early_marked_needs_review(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();

        \App\Models\AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'status' => 'present',
                'check_in_at' => now(config('app.timezone'))->subHour(),
                'check_out_at' => null,
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'out')
            ->assertJsonPath('data.needsReview', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertOk()
            ->assertJsonPath('data.needsReview', true)
            ->assertJsonPath('data.attendanceStatus', 'needs_review');
    }

    public function test_attendance_zero_minutes_is_not_treated_as_normal_present(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();
        $sameTime = now(config('app.timezone'))->startOfMinute();

        \App\Models\AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'status' => 'present',
                'check_in_at' => $sameTime,
                'check_out_at' => $sameTime,
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertOk()
            ->assertJsonPath('data.needsReview', true);
    }

    public function test_attendance_me_today_returns_profile_photo_url_from_employee_profile(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['profile_photo_path' => 'employee-photos/att-user.png']
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profilePhotoUrl', '/storage/employee-photos/att-user.png');
    }

    public function test_attendance_daily_target_and_progress_are_aligned(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();

        \App\Models\AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'status' => 'present',
                'check_in_at' => now(config('app.timezone'))->startOfDay()->setTime(9, 0),
                'check_out_at' => now(config('app.timezone'))->startOfDay()->setTime(17, 0),
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertOk()
            ->assertJsonPath('data.productionProgressPercent', 100);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/me/stats')
            ->assertOk()
            ->assertJsonPath('data.todayTarget', 8)
            ->assertJsonPath('data.weekTarget', 40)
            ->assertJsonPath('data.monthTarget', $this->expectedCurrentMonthWorkHoursTarget());
    }

    private function expectedCurrentMonthWorkHoursTarget(): int
    {
        $start = now(config('app.timezone'))->copy()->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        $weekdayCount = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $weekdayCount++;
            }
            $cursor->addDay();
        }

        return $weekdayCount * 8;
    }

    public function test_attendance_can_request_correction_after_checkout(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();

        \App\Models\AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'status' => 'needs_review',
                'check_in_at' => now(config('app.timezone'))->subHour(),
                'check_out_at' => now(config('app.timezone')),
                'break_minutes' => 0,
                'late_minutes' => 0,
                'correction_status' => 'none',
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->postJson('/v1/hcm/attendance/me/correction-request', [
            'workDate' => $today,
            'reason' => 'Accidentally tapped punch out too early.',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correctionStatus', 'requested');
    }

    public function test_attendance_admin_upsert_record(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $date = '2026-04-02';

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/attendance/admin/record', [
            'userId' => $user->id,
            'workDate' => $date,
            'checkInTime' => '09:05',
            'checkOutTime' => '17:00',
            'breakMinutes' => 30,
            'lateMinutes' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/admin?date='.$date)
            ->assertOk()
            ->assertJsonFragment(['userId' => $user->id]);
    }

    public function test_attendance_admin_selfie_download_returns_not_found_when_selfie_missing(): void
    {
        $token = $this->bearerToken(true, 'att-selfie-admin@example.com');
        $user = User::factory()->create();
        $this->attachUserToActiveCompany($user);

        $record = \App\Models\AttendanceRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'work_date' => '2026-04-02',
            'status' => 'present',
            'check_in_at' => now()->setTime(9, 5, 0),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/admin/records/'.$record->id.'/selfie/download')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SELFIE_NOT_FOUND');
    }

    public function test_attendance_admin_upsert_rejects_checkout_before_checkin(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/attendance/admin/record', [
            'userId' => $user->id,
            'workDate' => '2026-04-02',
            'checkInTime' => '17:00',
            'checkOutTime' => '09:00',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_attendance_admin_upsert_rejects_late_without_checkin(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/attendance/admin/record', [
            'userId' => $user->id,
            'workDate' => '2026-04-02',
            'breakMinutes' => 0,
            'lateMinutes' => 10,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_attendance_admin_allows_hr_manager_designation(): void
    {
        $token = $this->bearerToken(true, 'hrmanager@example.com');
        $user = User::query()->where('email', 'hrmanager@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Manager']
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_attendance_admin_allows_qa_login_super_admin_email(): void
    {
        $token = $this->bearerToken(false, 'qa.login@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_timesheets_endpoint_returns_rows_for_admin(): void
    {
        $token = $this->bearerToken(true, 'timesheetadmin@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/timesheets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['dateFrom', 'dateTo', 'projects'],
            ]);
    }

    public function test_timesheets_rejects_reversed_date_range(): void
    {
        $token = $this->bearerToken(true, 'timesheet-range@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/timesheets?dateFrom=2026-04-30&dateTo=2026-04-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dateTo']);
    }

    public function test_schedule_timing_endpoint_returns_rows_for_admin(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->getJson('/v1/hcm/schedule-timing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_schedule_timing_admin_can_upsert_manual_override(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-edit@example.com');
        $target = User::factory()->create();
        $this->attachUserToActiveCompany($target);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'startTime' => '08:30',
            'endTime' => '17:30',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_schedule_timing_admin_can_destroy_override(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-del@example.com');
        $target = User::factory()->create();
        $this->attachUserToActiveCompany($target);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'startTime' => '08:00',
            'endTime' => '17:00',
        ])->assertOk();

        $this->assertDatabaseHas('hcm_schedule_timings', ['user_id' => $target->id]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->deleteJson('/v1/hcm/schedule-timing/'.$target->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('hcm_schedule_timings', 0);
    }

    public function test_schedule_timing_admin_can_apply_shift_id(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-shift@example.com');
        $target = User::factory()->create();
        $this->attachUserToActiveCompany($target);
        $shift = HcmShift::query()->create([
            'code' => 'test_shift_apply',
            'name' => 'Apply Me',
            'start_time' => '10:15',
            'end_time' => '19:15',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'shiftId' => $shift->id,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $row = HcmScheduleTiming::query()->where('user_id', $target->id)->firstOrFail();
        $this->assertSame($shift->id, (int) $row->hcm_shift_id);
        $this->assertStringContainsString('10:15', (string) $row->start_time);
        $this->assertStringContainsString('19:15', (string) $row->end_time);
    }

    public function test_attendance_admin_upsert_record_forbidden_when_switching_to_unowned_company(): void
    {
        $token = $this->bearerToken(true, 'att-upsert-other@example.com');

        Company::query()->create([
            'code' => 'att_upsert_other_company',
            'name' => 'Att Upsert Other Company',
            'legal_name' => 'Att Upsert Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $target = User::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => 'att_upsert_other_company',
        ])->putJson('/v1/hcm/attendance/admin/record', [
            'userId' => $target->id,
            'workDate' => now()->toDateString(),
            'checkInTime' => '09:00',
            'checkOutTime' => '18:00',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_attendance_admin_upsert_record_rejects_target_user_outside_active_company(): void
    {
        $token = $this->bearerToken(true, 'att-upsert-scope@example.com');

        $otherCompany = Company::query()->create([
            'code' => 'att_target_other_company',
            'name' => 'Att Target Other Company',
            'legal_name' => 'Att Target Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $target = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $target->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/attendance/admin/record', [
            'userId' => $target->id,
            'workDate' => '2026-04-02',
            'checkInTime' => '09:00',
            'checkOutTime' => '17:00',
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_NOT_IN_COMPANY');
    }

    public function test_timesheets_forbidden_when_switching_to_unowned_company(): void
    {
        $token = $this->bearerToken(true, 'att-timesheet-other@example.com');

        Company::query()->create([
            'code' => 'att_timesheet_other_company',
            'name' => 'Att Timesheet Other Company',
            'legal_name' => 'Att Timesheet Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => 'att_timesheet_other_company',
        ])->getJson('/v1/hcm/timesheets')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_schedule_timing_write_rejects_target_user_outside_active_company(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-scope@example.com');

        $otherCompany = Company::query()->create([
            'code' => 'st_target_other_company',
            'name' => 'ST Target Other Company',
            'legal_name' => 'ST Target Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $target = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $target->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'startTime' => '08:30',
            'endTime' => '17:30',
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_NOT_IN_COMPANY');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/schedule-timing/'.$target->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_NOT_IN_COMPANY');
    }
}

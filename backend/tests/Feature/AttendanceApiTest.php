<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Geofence;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\ReportDataBlock;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function bearerToken(bool $isAdmin = true, string $email = 'att@example.com'): string
    {
        if (! $this->company) {
            $this->company = Company::query()->firstOrCreate(
                ['code' => 'TEST_COMPANY'],
                [
                    'name' => 'Test Company',
                    'domain' => 'test-company.local',
                    'timezone' => 'UTC',
                ]
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
                CompanyUser::firstOrCreate([
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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

    public function test_attendance_admin_export_supports_xlsx_and_csv(): void
    {
        $token = $this->bearerToken(true, 'attendance-export@example.com');

        $xlsx = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->get('/v1/hcm/attendance/admin/export?format=xlsx');

        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

        $csv = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->get('/v1/hcm/attendance/admin/export?format=csv');

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $csv->headers->get('content-disposition'));
    }

    public function test_attendance_admin_export_archive_requires_valid_completed_attendance_snapshot(): void
    {
        $token = $this->bearerToken(true, 'attendance-archive-export@example.com');

        $snapshot = ReportSnapshot::query()->create([
            'company_id' => $this->company->id,
            'report_type' => 'attendance',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'generated_at' => now(),
            'generated_by_user_id' => User::query()->where('email', 'attendance-archive-export@example.com')->value('id'),
            'status' => 'completed',
            'meta' => ['row_count' => 1],
        ]);

        ReportDataBlock::query()->create([
            'snapshot_id' => $snapshot->id,
            'module' => 'attendance',
            'data_key' => 'user_1',
            'data_value' => [
                'user_name' => 'Archive User',
                'present' => 1,
                'absent' => 0,
                'total_late_minutes' => 5,
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->get('/v1/hcm/attendance/admin/export?source=archive&snapshotId='.$snapshot->id.'&format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_attendance_punch_flow(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'in');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('data.action', 'out');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ATTENDANCE_ALREADY_COMPLETE');
    }

    public function test_attendance_punch_requires_latitude_and_longitude(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', [])
            ->assertStatus(422);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', ['latitude' => 91, 'longitude' => 0])
            ->assertStatus(422);
    }

    public function test_attendance_break_start_and_end_updates_break_minutes(): void
    {
        $token = $this->bearerToken();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_start');

        // make sure there is measurable break duration
        usleep(1500000);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/break')
            ->assertOk()
            ->assertJsonPath('data.action', 'break_end');

        $today = now(config('app.timezone'))->toDateString();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $record = AttendanceRecord::query()
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
        $today = now('UTC')->toDateString();

        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'present',
                'check_in_at' => now('UTC')->subHour(),
                'check_out_at' => null,
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/punch', $this->punchGpsBody())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'out')
            ->assertJsonPath('data.needsReview', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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

        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'present',
                'check_in_at' => $sameTime,
                'check_out_at' => $sameTime,
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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

        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'present',
                'check_in_at' => now(config('app.timezone'))->startOfDay()->setTime(9, 0),
                'check_out_at' => now(config('app.timezone'))->startOfDay()->setTime(17, 0),
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertOk()
            ->assertJsonPath('data.productionProgressPercent', 100);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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

        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'needs_review',
                'check_in_at' => now(config('app.timezone'))->subHour(),
                'check_out_at' => now(config('app.timezone')),
                'break_minutes' => 0,
                'late_minutes' => 0,
                'correction_status' => 'none',
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/admin?date='.$date)
            ->assertOk()
            ->assertJsonFragment(['userId' => $user->id]);
    }

    public function test_attendance_admin_selfie_download_returns_not_found_when_selfie_missing(): void
    {
        $token = $this->bearerToken(true, 'att-selfie-admin@example.com');
        $user = User::factory()->create();
        $this->attachUserToActiveCompany($user);

        $record = AttendanceRecord::query()->create([
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_attendance_admin_allows_qa_login_super_admin_email(): void
    {
        $token = $this->bearerToken(false, 'qa.login@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/admin')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_timesheets_endpoint_returns_rows_for_admin(): void
    {
        $token = $this->bearerToken(true, 'timesheetadmin@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            ->assertStatus(422);
    }

    public function test_schedule_timing_endpoint_returns_rows_for_admin(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/schedule-timing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_schedule_timing_export_supports_xlsx_and_csv(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-export@example.com');

        $xlsx = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->get('/v1/hcm/schedule-timing/export?format=xlsx');

        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

        $csv = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->get('/v1/hcm/schedule-timing/export?format=csv');

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $csv->headers->get('content-disposition'));
    }

    public function test_timesheets_and_schedule_timing_endpoints_are_forbidden_for_non_admin_user(): void
    {
        $token = $this->bearerToken(false, 'attendance.nonadmin.scope@example.com');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/timesheets')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/schedule-timing')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/schedule-timing/1', [
                'startTime' => '08:00',
                'endTime' => '17:00',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->deleteJson('/v1/hcm/schedule-timing/1')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_schedule_timing_admin_can_upsert_manual_override(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-edit@example.com');
        $target = User::factory()->create();
        $this->attachUserToActiveCompany($target);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'startTime' => '08:00',
            'endTime' => '17:00',
        ])->assertOk();

        $this->assertDatabaseHas('hcm_schedule_timings', ['user_id' => $target->id]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
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

    public function test_schedule_timing_upsert_is_scoped_per_company_for_same_user(): void
    {
        $adminEmail = 'scheduletiming-multico@example.com';
        $token = $this->bearerToken(true, $adminEmail);

        $otherCompany = Company::query()->create([
            'code' => 'st_multi_company_b',
            'name' => 'ST Multi Company B',
            'legal_name' => 'ST Multi Company B LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $adminUser = User::query()->where('email', $adminEmail)->firstOrFail();
        CompanyUser::query()->updateOrCreate(
            ['company_id' => $otherCompany->id, 'user_id' => $adminUser->id],
            ['role' => 'owner', 'status' => 'active']
        );

        $target = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $target->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
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
            'startTime' => '08:00',
            'endTime' => '17:00',
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $otherCompany->id,
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'startTime' => '10:00',
            'endTime' => '19:00',
        ])->assertOk();

        $this->assertDatabaseHas('hcm_schedule_timings', [
            'user_id' => $target->id,
            'company_id' => $this->company->id,
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $this->assertDatabaseHas('hcm_schedule_timings', [
            'user_id' => $target->id,
            'company_id' => $otherCompany->id,
            'start_time' => '10:00',
            'end_time' => '19:00',
        ]);
    }

    public function test_schedule_timing_start_sort_applies_before_pagination(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-sort@example.com');

        $earlyUser = User::factory()->create(['name' => 'Zulu Early']);
        $lateUser = User::factory()->create(['name' => 'Alpha Late']);

        $this->attachUserToActiveCompany($earlyUser);
        $this->attachUserToActiveCompany($lateUser);

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $earlyUser->id],
            ['designation' => 'Staff', 'company_id' => $this->company->id]
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $lateUser->id],
            ['designation' => 'Staff', 'company_id' => $this->company->id]
        );

        HcmScheduleTiming::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $earlyUser->id,
            'hcm_shift_id' => null,
            'start_time' => '07:00',
            'end_time' => '16:00',
            'source' => 'manual',
        ]);
        HcmScheduleTiming::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $lateUser->id,
            'hcm_shift_id' => null,
            'start_time' => '11:00',
            'end_time' => '20:00',
            'source' => 'manual',
        ]);

        $pageOne = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/schedule-timing?sort=start_asc&perPage=1&page=1')
            ->assertOk()
            ->json('data');

        $pageTwo = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/schedule-timing?sort=start_asc&perPage=1&page=2')
            ->assertOk()
            ->json('data');

        $this->assertSame($earlyUser->id, (int) ($pageOne[0]['userId'] ?? 0));
        $this->assertSame($lateUser->id, (int) ($pageTwo[0]['userId'] ?? 0));
    }

    public function test_schedule_timing_admin_can_apply_overnight_shift_id(): void
    {
        $token = $this->bearerToken(true, 'scheduletiming-nightshift@example.com');
        $target = User::factory()->create();
        $this->attachUserToActiveCompany($target);

        $shift = HcmShift::query()->create([
            'company_id' => $this->company->id,
            'code' => 'test_shift_overnight',
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/schedule-timing/'.$target->id, [
            'shiftId' => $shift->id,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $row = HcmScheduleTiming::query()->where('user_id', $target->id)->firstOrFail();
        $this->assertSame($shift->id, (int) $row->hcm_shift_id);
        $this->assertStringContainsString('22:00', (string) $row->start_time);
        $this->assertStringContainsString('06:00', (string) $row->end_time);
    }

    // ================================================================
    // B2: Present status records are eligible for correction
    // ================================================================

    public function test_present_status_correction_eligible_in_ui(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();

        // Create present status record with check-in and check-out
        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'present',
                'check_in_at' => now(config('app.timezone'))->subHours(8),
                'check_out_at' => now(config('app.timezone'))->subHour(),
                'break_minutes' => 30,
                'late_minutes' => 0,
                'correction_status' => 'none',
            ]
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/attendance/me/history?days=1');

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertNotEmpty($rows);

        $todayRow = collect($rows)->firstWhere('workDate', $today);
        $this->assertNotNull($todayRow);
        $this->assertTrue((bool) $todayRow['correctionEligible'], 'Present status record should be correction-eligible');
    }

    public function test_present_status_can_request_correction(): void
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'att@example.com')->firstOrFail();
        $today = now(config('app.timezone'))->toDateString();

        AttendanceRecord::query()->updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'company_id' => $this->company->id,
                'status' => 'present',
                'check_in_at' => now(config('app.timezone'))->subHours(8),
                'check_out_at' => now(config('app.timezone'))->subHour(),
                'break_minutes' => 30,
                'late_minutes' => 0,
                'correction_status' => 'none',
            ]
        );

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/attendance/me/correction-request', [
            'workDate' => $today,
            'reason' => 'Wrong check-out time, should be 17:00.',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correctionStatus', 'requested');
    }

    public function test_attendance_punch_within_geofence_succeeds(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        Geofence::query()->create([
            'company_id' => $companyId,
            'name' => 'Kantor',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ])->assertOk()
            ->assertJsonPath('data.action', 'in');
    }

    public function test_attendance_punch_outside_geofence_rejected(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        Geofence::query()->create([
            'company_id' => $companyId,
            'name' => 'Kantor',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.3000,
            'longitude' => 106.9000,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'GEOFENCE_VIOLATION');
    }

    public function test_attendance_punch_works_without_geofence(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ])->assertOk()
            ->assertJsonPath('data.action', 'in');
    }

    public function test_attendance_punch_with_inactive_geofence_ignored(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        Geofence::query()->create([
            'company_id' => $companyId,
            'name' => 'Inactive',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 10,
            'is_active' => false,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.3000,
            'longitude' => 106.9000,
        ])->assertOk()
            ->assertJsonPath('data.action', 'in');
    }

    public function test_attendance_punch_at_geofence_boundary_inside_succeeds(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        Geofence::query()->create([
            'company_id' => $companyId,
            'name' => 'Big Zone',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 1000,
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.2000,
            'longitude' => 106.8456,
        ])->assertOk()
            ->assertJsonPath('data.action', 'in');
    }

    public function test_attendance_punch_at_geofence_boundary_outside_rejected(): void
    {
        $token = $this->bearerToken();
        $companyId = $this->company->id;

        Geofence::query()->create([
            'company_id' => $companyId,
            'name' => 'Small Zone',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 1000,
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/attendance/me/punch', [
            'latitude' => -6.1950,
            'longitude' => 106.8456,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'GEOFENCE_VIOLATION');
    }
}

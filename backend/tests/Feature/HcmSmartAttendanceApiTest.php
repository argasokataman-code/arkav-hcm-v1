<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmScheduleRoster;
use App\Models\HcmShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmSmartAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_smart_attendance_shifting_plan_returns_structured_payload(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_COMPANY',
            'name' => 'Smart Attendance Company',
            'legal_name' => 'Smart Attendance Company LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'name' => 'Smart Admin',
            'email' => 'smart.admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $employee = User::factory()->create([
            'name' => 'Scheduler Employee',
            'email' => 'scheduler.employee@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'designation' => 'Operations',
        ]);

        $morningShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'MORN',
            'name' => 'Morning Shift',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $nightShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'NITE',
            'name' => 'Night Shift',
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $weekStart = now('Asia/Jakarta')->startOfWeek()->toDateString();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/smart-attendance-shifting/generate', [
            'weekStart' => $weekStart,
            'rules' => [
                'max_work_days_per_week' => 5,
                'min_days_off_per_week' => 2,
                'min_rest_hours_between_shifts' => 12,
                'max_consecutive_night_shifts' => 3,
            ],
            'coverageRequirements' => [
                [
                    'date' => $weekStart,
                    'required' => [
                        ['shift_id' => (string) $morningShift->id, 'headcount' => 1],
                        ['shift_id' => (string) $nightShift->id, 'headcount' => 1],
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'schedule_generation' => [
                        'validation_status',
                        'weekly_schedule',
                        'violations',
                        'unmet_coverage',
                    ],
                    'attendance_analysis' => [
                        'employee_summaries',
                        'flags',
                    ],
                    'recommendation' => [
                        'fairness_score',
                        'fatigue_risk_score',
                        'improvement_suggestions',
                    ],
                    'explanation',
                ],
            ]);

        $this->assertIsString($response->json('data.explanation'));
        $this->assertIsArray($response->json('data.schedule_generation.weekly_schedule'));
        $this->assertIsNumeric($response->json('data.recommendation.fairness_score'));
        $this->assertIsNumeric($response->json('data.recommendation.fatigue_risk_score'));
    }

    public function test_generate_smart_attendance_shifting_plan_requires_admin(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_GUARD',
            'name' => 'Smart Attendance Guard',
            'legal_name' => 'Smart Attendance Guard LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $employee = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'regular.smart@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'regular.smart@example.com',
            'password' => 'password',
            'companyCode' => $company->code,
        ]);
        $login->assertOk();

        $token = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/smart-attendance-shifting/generate', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_non_admin_cannot_access_smart_attendance_settings_publish_and_roster_endpoints(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_GUARD_EXT',
            'name' => 'Smart Attendance Guard Extended',
            'legal_name' => 'Smart Attendance Guard Extended LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $employee = User::factory()->create([
            'name' => 'Regular Smart User',
            'email' => 'regular.smart.ext@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'regular.smart.ext@example.com',
            'password' => 'password',
            'companyCode' => $company->code,
        ]);
        $login->assertOk();

        $token = (string) $login->json('data.accessToken');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/smart-attendance-shifting/settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/smart-attendance-shifting/settings', [
                'defaultRules' => [
                    'max_work_days_per_week' => 5,
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/smart-attendance-shifting/publish-roster', [
                'weeklySchedule' => [],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/schedule-rosters?dateFrom=2026-04-01&dateTo=2026-04-07')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_generate_shifting_mode_distributes_multiple_shift_types(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_VARIATION',
            'name' => 'Smart Attendance Variation',
            'legal_name' => 'Smart Attendance Variation LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'name' => 'Variation Admin',
            'email' => 'smart.variation.admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $employeeIds = [];
        foreach (range(1, 6) as $i) {
            $employee = User::factory()->create([
                'name' => 'Scheduler Variation '.$i,
                'email' => 'scheduler.variation.'.$i.'@example.com',
            ]);
            CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $employee->id,
                'role' => 'employee',
                'status' => 'active',
                'joined_at' => now(),
            ]);
            EmployeeProfile::query()->create([
                'company_id' => $company->id,
                'user_id' => $employee->id,
                'designation' => 'Customer Service',
            ]);
            $employeeIds[] = (int) $employee->id;
        }

        $morningShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'MORN_VARIATION',
            'name' => 'Morning Shift',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $afternoonShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'AFT_VARIATION',
            'name' => 'Afternoon Shift',
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $nightShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'NITE_VARIATION',
            'name' => 'Night Shift',
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $weekStart = now('Asia/Jakarta')->startOfWeek()->toDateString();
        $coverageRequirements = [];
        foreach (range(0, 6) as $offset) {
            $date = now('Asia/Jakarta')->startOfWeek()->addDays($offset)->toDateString();
            $coverageRequirements[] = [
                'date' => $date,
                'required' => [
                    ['shift_id' => (string) $morningShift->id, 'headcount' => 2],
                    ['shift_id' => (string) $afternoonShift->id, 'headcount' => 2],
                    ['shift_id' => (string) $nightShift->id, 'headcount' => 2],
                ],
            ];
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/smart-attendance-shifting/generate', [
            'weekStart' => $weekStart,
            'employeeIds' => $employeeIds,
            'shiftCategory' => 'shifting_24h',
            'rules' => [
                'max_work_days_per_week' => 6,
                'min_days_off_per_week' => 1,
                'min_rest_hours_between_shifts' => 8,
                'max_consecutive_night_shifts' => 3,
                'illegal_transition_rules' => [],
            ],
            'coverageRequirements' => $coverageRequirements,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $weekly = $response->json('data.schedule_generation.weekly_schedule');
        $allShiftIds = collect(is_array($weekly) ? $weekly : [])
            ->flatMap(function ($row): array {
                $assignments = is_array($row) && isset($row['assignments']) && is_array($row['assignments'])
                    ? $row['assignments']
                    : [];

                return collect($assignments)
                    ->pluck('shift_id')
                    ->filter(fn ($id): bool => is_string($id) && $id !== '' && strtoupper($id) !== 'OFF')
                    ->values()
                    ->all();
            })
            ->unique()
            ->values();

        $this->assertGreaterThanOrEqual(2, $allShiftIds->count(), 'Shifting output should not collapse into a single shift type.');
    }

    public function test_generate_respects_afternoon_to_morning_forbidden_transition_matrix(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_TRANSITION',
            'name' => 'Smart Attendance Transition',
            'legal_name' => 'Smart Attendance Transition LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'name' => 'Transition Admin',
            'email' => 'smart.transition.admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $employee = User::factory()->create([
            'name' => 'Transition Employee',
            'email' => 'smart.transition.employee@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'designation' => 'Operations',
        ]);

        $afternoonShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'AFT_TRANSITION',
            'name' => 'Afternoon Shift',
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'shift_type' => 'afternoon',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $morningShift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'MORN_TRANSITION',
            'name' => 'Morning Shift',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'shift_type' => 'morning',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $weekStartDate = now('Asia/Jakarta')->startOfWeek();
        $dayOne = $weekStartDate->toDateString();
        $dayTwo = $weekStartDate->copy()->addDay()->toDateString();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/smart-attendance-shifting/generate', [
            'weekStart' => $dayOne,
            'employeeIds' => [(int) $employee->id],
            'shiftCategory' => 'shifting_24h',
            'rules' => [
                'max_work_days_per_week' => 2,
                'min_days_off_per_week' => 5,
                'min_rest_hours_between_shifts' => 8,
                'max_consecutive_night_shifts' => 3,
                'illegal_transition_rules' => ['afternoon_to_morning'],
            ],
            'coverageRequirements' => [
                [
                    'date' => $dayOne,
                    'required' => [
                        ['shift_id' => (string) $afternoonShift->id, 'headcount' => 1],
                    ],
                ],
                [
                    'date' => $dayTwo,
                    'required' => [
                        ['shift_id' => (string) $morningShift->id, 'headcount' => 1],
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $weeklySchedule = $response->json('data.schedule_generation.weekly_schedule');
        $this->assertIsArray($weeklySchedule);

        $assignments = collect($weeklySchedule)
            ->firstWhere('employee_id', (string) $employee->id)['assignments'] ?? [];
        $this->assertIsArray($assignments);

        $normalized = collect($assignments)
            ->map(function ($row): array {
                return [
                    'date' => (string) ($row['date'] ?? ''),
                    'shift_id' => (string) ($row['shift_id'] ?? ''),
                ];
            })
            ->sortBy('date')
            ->values();

        for ($i = 0; $i < $normalized->count() - 1; $i++) {
            $currentShift = (string) ($normalized[$i]['shift_id'] ?? '');
            $nextShift = (string) ($normalized[$i + 1]['shift_id'] ?? '');
            $this->assertFalse(
                $currentShift === (string) $afternoonShift->id && $nextShift === (string) $morningShift->id,
                'Forbidden transition afternoon_to_morning should not appear in generated schedule.'
            );
        }

        $unmetCoverage = $response->json('data.schedule_generation.unmet_coverage');
        $this->assertIsArray($unmetCoverage);
        $this->assertTrue(
            collect($unmetCoverage)->contains(function ($row) use ($dayTwo, $morningShift): bool {
                return (string) ($row['date'] ?? '') === $dayTwo
                    && (string) ($row['shift_id'] ?? '') === (string) $morningShift->id;
            }),
            'Expected uncovered morning requirement because afternoon_to_morning transition is forbidden.'
        );
    }

    public function test_smart_planner_settings_can_be_saved_and_loaded_per_tenant(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_SETTINGS',
            'name' => 'Smart Attendance Settings',
            'legal_name' => 'Smart Attendance Settings LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'name' => 'Settings Admin',
            'email' => 'smart.settings.admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->putJson('/v1/hcm/smart-attendance-shifting/settings', [
            'defaultRules' => [
                'max_work_days_per_week' => 4,
                'min_days_off_per_week' => 3,
                'min_rest_hours_between_shifts' => 10,
                'max_consecutive_night_shifts' => 2,
            ],
            'forbiddenTransitions' => ['night:morning', 'afternoon:morning'],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.defaultRules.max_work_days_per_week', 4)
            ->assertJsonPath('data.forbiddenTransitions.0', 'night:morning');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->getJson('/v1/hcm/smart-attendance-shifting/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.defaultRules.min_days_off_per_week', 3)
            ->assertJsonPath('data.forbiddenTransitions.1', 'afternoon:morning');

        $settingsAdmin = User::query()->where('email', 'smart.settings.admin@example.com')->firstOrFail();

        $this->assertDatabaseHas('hcm_smart_planner_settings', [
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'created_by_user_id' => $settingsAdmin->id,
            'created_by_user_uuid' => $settingsAdmin->uuid,
            'updated_by_user_id' => $settingsAdmin->id,
            'updated_by_user_uuid' => $settingsAdmin->uuid,
        ]);
    }

    public function test_publish_daily_roster_upserts_per_user_per_date(): void
    {
        $company = Company::query()->create([
            'code' => 'SMART_ATTENDANCE_ROSTER',
            'name' => 'Smart Attendance Roster',
            'legal_name' => 'Smart Attendance Roster LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = $this->createHcmAdminWithCompany([
            'name' => 'Roster Admin',
            'email' => 'smart.roster.admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $employee = User::factory()->create([
            'name' => 'Roster Employee',
            'email' => 'smart.roster.employee@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'designation' => 'CS',
        ]);

        $shift = HcmShift::query()->create([
            'company_id' => $company->id,
            'code' => 'ROSTER_M',
            'name' => 'Roster Morning',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'shift_type' => 'morning',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $payload = [
            'weeklySchedule' => [
                [
                    'employee_id' => $employee->id,
                    'assignments' => [
                        [
                            'date' => now('Asia/Jakarta')->startOfWeek()->toDateString(),
                            'shift_id' => (string) $shift->id,
                            'start_time' => '07:00',
                            'end_time' => '15:00',
                            'cross_day' => false,
                        ],
                        [
                            'date' => now('Asia/Jakarta')->startOfWeek()->addDay()->toDateString(),
                            'shift_id' => 'OFF',
                            'start_time' => null,
                            'end_time' => null,
                            'cross_day' => false,
                        ],
                    ],
                ],
            ],
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/smart-attendance-shifting/publish-roster', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2);

        $this->assertDatabaseHas('hcm_schedule_rosters', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'hcm_shift_id' => $shift->id,
            'roster_status' => 'working',
        ]);

        $this->assertDatabaseHas('hcm_schedule_rosters', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'hcm_shift_id' => null,
            'roster_status' => 'off',
        ]);

        $this->assertGreaterThanOrEqual(2, HcmScheduleRoster::query()->where('company_id', $company->id)->count());
    }
}
